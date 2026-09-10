<?php
/**
 * AlmancaPro - PDO baglanti katmani.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

final class Database
{
    private static ?PDO $pdo = null;
    private static ?string $lastError = null;
    private static string $lastErrorCode = '';

    public static function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        try {
            self::$pdo = new PDO(self::dsn(), DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            self::$pdo->exec("SET time_zone = '+00:00'");
            self::$pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
        } catch (Throwable $e) {
            /* Hicbir kimlik bilgisi mesaja karistirilmaz; yalnizca hata kodu tutulur. */
            self::$lastError = 'connection_failed';
            self::$lastErrorCode = (string)$e->getCode();
            error_log('[AlmancaPro] Veritabanı bağlantısı kurulamadı (kod: ' . self::$lastErrorCode . ')');
            throw new RuntimeException('database_unavailable', 0);
        }

        return self::$pdo;
    }

    /** Baglanti kurulabiliyor mu? Kimlik bilgisi sizdirmadan true/false doner. */
    public static function isAvailable(): bool
    {
        try {
            self::pdo();
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /** Son baglanti hatasinin surucu kodu (or. 1045, 1049, 2002). */
    public static function lastErrorCode(): string
    {
        return self::$lastErrorCode;
    }

    /**
     * Baglanti hatasini yoneticinin anlayacagi Turkce bir aciklamaya cevirir.
     * Hicbir kimlik bilgisi mesaja karistirilmaz.
     */
    public static function connectionHint(): string
    {
        return match (self::$lastErrorCode) {
            '1049' => 'Sunucuda bu adda bir veritabanı yok.',
            '1045' => 'Veritabanı kullanıcı adı veya şifresi kabul edilmedi.',
            '2002' => 'Veritabanı sunucusuna ulaşılamıyor.',
            '2005' => 'Veritabanı sunucu adresi çözümlenemedi.',
            '1044' => 'Kullanıcının bu veritabanına erişim yetkisi yok.',
            default => 'Veritabanına bağlanılamadı.',
        };
    }

    /** Verilen bilgilerle baglanti dener; basarisizsa Turkce sebep doner. */
    public static function testConnection(string $host, int $port, string $name, string $user, string $pass): array
    {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, DB_CHARSET);
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->query('SELECT 1');
            return ['ok' => true, 'error' => ''];
        } catch (Throwable $e) {
            $code = (string)$e->getCode();
            $hint = match ($code) {
                '1049' => 'Bu adda bir veritabanı yok. Plesk > Veritabanları bölümünde adı birebir kopyalayın.',
                '1045' => 'Kullanıcı adı veya şifre hatalı. Plesk > Veritabanları > Kullanıcılar bölümünden şifreyi yenileyebilirsiniz.',
                '2002' => 'Sunucuya ulaşılamıyor. Sunucu alanına "localhost" yazmayı deneyin.',
                '2005' => 'Sunucu adresi çözümlenemedi. "localhost" yazmayı deneyin.',
                '1044' => 'Bu kullanıcının bu veritabanına yetkisi yok. Plesk\'te kullanıcıyı veritabanına atayın.',
                default => 'Bağlantı kurulamadı (kod: ' . $code . ').',
            };
            return ['ok' => false, 'error' => $hint];
        }
    }
}

/** Kisayol: PDO ornegi. */
function db(): PDO
{
    return Database::pdo();
}

/** Hazir sorgu calistirir ve PDOStatement doner. */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** Tek satir doner. */
function db_row(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Tum satirlari doner. */
function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/** Tek kolon degeri doner. */
function db_value(string $sql, array $params = [], mixed $default = null): mixed
{
    $v = db_query($sql, $params)->fetchColumn();
    return $v === false ? $default : $v;
}

/** INSERT calistirir ve yeni id doner. */
function db_insert(string $sql, array $params = []): int
{
    db_query($sql, $params);
    return (int)db()->lastInsertId();
}

/** Etkilenen satir sayisini doner. */
function db_exec(string $sql, array $params = []): int
{
    return db_query($sql, $params)->rowCount();
}

/** Tablo var mi? */
function db_table_exists(string $table): bool
{
    try {
        $n = db_value(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
            0
        );
        return (int)$n > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/** Kolon var mi? */
function db_column_exists(string $table, string $column): bool
{
    try {
        $n = db_value(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
            0
        );
        return (int)$n > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/** Islem sarmalayici. */
function db_transaction(callable $fn): mixed
{
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $result = $fn($pdo);
        if ($own) {
            $pdo->commit();
        }
        return $result;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
