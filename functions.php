<?php
/**
 * AlmancaPro - Ortak yardimci fonksiyonlar.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/* ==================================================================
 * Cikti guvenligi
 * ================================================================== */

/** HTML kacisi. */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** HTML attribute kacisi (e() ile ayni ama niyet acik olsun). */
function eattr(?string $value): string
{
    return e($value);
}

/** JS icine gomulen JSON. */
function ejson(mixed $value): string
{
    return json_encode(
        $value,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?: '{}';
}

/** Coklu satirli metni guvenli paragraflara cevirir. */
function e_paragraphs(?string $text): string
{
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }
    $parts = preg_split('/\n{2,}/', $text) ?: [];
    $out = '';
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        $out .= '<p>' . nl2br(e($p)) . '</p>';
    }
    return $out;
}

/* ==================================================================
 * Oturum
 * ================================================================== */

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    return strtolower((string)$proto) === 'https';
}

function app_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (PHP_SAPI === 'cli') {
        return;
    }

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();

    if (!isset($_SESSION['__created'])) {
        $_SESSION['__created'] = time();
        session_regenerate_id(true);
    }
}

function send_security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), payment=(), usb=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; font-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header_remove('X-Powered-By');
}

/* ==================================================================
 * CSRF
 * ================================================================== */

function csrf_token(): string
{
    app_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . e(csrf_token()) . '">';
}

function csrf_validate(?string $token = null): bool
{
    app_session_start();
    if ($token === null) {
        $token = $_POST[CSRF_TOKEN_NAME] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }
    $expected = $_SESSION['csrf'] ?? '';
    return is_string($token) && $expected !== '' && hash_equals((string)$expected, $token);
}

/** POST istegi icin CSRF zorunlulugu; basarisizsa uygun cevabi verip cikar. */
function csrf_require(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    if (csrf_validate()) {
        return;
    }
    if (wants_json()) {
        json_response(false, 'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar dene.', [], [], 419);
    }
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $sameSite = $referer !== '' && $host !== '' && str_contains($referer, $host);
    if ($sameSite) {
        flash('error', 'Güvenlik doğrulaması başarısız oldu. Sayfayı yenileyip tekrar dene.');
        redirect($referer);
    }
    http_response_code(419);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>Güvenlik doğrulaması başarısız</title></head>'
        . '<body style="font-family:sans-serif;padding:32px"><h1>Güvenlik doğrulaması başarısız</h1>'
        . '<p>İstek doğrulanamadı. Sayfayı yenileyip tekrar dene.</p><p><a href="/">Ana sayfaya dön</a></p></body></html>';
    exit;
}

/* ==================================================================
 * Istek yardimcilari
 * ================================================================== */

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function wants_json(): bool
{
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $xhr = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return str_contains($accept, 'application/json') || $xhr === 'xmlhttprequest';
}

function input(string $key, ?string $default = null): ?string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    if (is_array($v)) {
        return $default;
    }
    return $v === null ? $default : trim((string)$v);
}

function input_int(string $key, int $default = 0): int
{
    $v = input($key);
    return $v === null || $v === '' ? $default : (int)$v;
}

function input_array(string $key): array
{
    $v = $_POST[$key] ?? $_GET[$key] ?? [];
    return is_array($v) ? $v : [];
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return substr((string)$ip, 0, 45);
}

function user_agent(): string
{
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 190);
}

function redirect(string $url, int $code = 302): never
{
    if (!headers_sent()) {
        header('Location: ' . $url, true, $code);
    }
    exit;
}

function json_response(bool $success, string $message = '', array $data = [], array $errors = [], int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => (object)$data,
        'errors'  => (object)$errors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ==================================================================
 * Flash mesajlari
 * ================================================================== */

function flash(string $type, string $message): void
{
    app_session_start();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_take(): array
{
    app_session_start();
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($items) ? $items : [];
}

/* ==================================================================
 * Site ayarlari
 * ================================================================== */

function settings_all(bool $fresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$fresh) {
        return $cache;
    }
    $cache = [];
    try {
        foreach (db_all('SELECT setting_key, setting_value FROM site_settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Throwable $e) {
        $cache = [];
    }
    return $cache;
}

function setting(string $key, ?string $default = null): ?string
{
    $all = settings_all();
    $v = $all[$key] ?? null;
    if ($v === null || $v === '') {
        return $default;
    }
    return $v;
}

function setting_bool(string $key, bool $default = false): bool
{
    $v = setting($key);
    if ($v === null) {
        return $default;
    }
    return in_array(strtolower($v), ['1', 'true', 'yes', 'on', 'evet'], true);
}

function setting_int(string $key, int $default = 0): int
{
    $v = setting($key);
    return $v === null ? $default : (int)$v;
}

function setting_set(string $key, ?string $value, bool $isSecret = false): void
{
    db_exec(
        'INSERT INTO site_settings (setting_key, setting_value, is_secret) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_secret = VALUES(is_secret)',
        [$key, $value, $isSecret ? 1 : 0]
    );
    settings_all(true);
}

/** Bir sirri maskeleyerek gosterir: hicbir zaman tam deger dondurmez. */
function mask_secret(?string $value, int $visible = 4): string
{
    $value = (string)$value;
    if ($value === '') {
        return '';
    }
    $len = mb_strlen($value);
    if ($len <= $visible) {
        return str_repeat('*', $len);
    }
    return str_repeat('*', max(4, $len - $visible)) . mb_substr($value, -$visible);
}

function site_url(string $path = ''): string
{
    $base = setting('site_url');
    if ($base === null || $base === '') {
        $scheme = is_https() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host;
    }
    $base = rtrim($base, '/');
    if ($path === '') {
        return $base;
    }
    return $base . '/' . ltrim($path, '/');
}

function site_name(): string
{
    return setting('site_name', APP_NAME) ?? APP_NAME;
}

/* ==================================================================
 * Zaman
 * ================================================================== */

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function utc_plus(string $modifier): string
{
    return gmdate('Y-m-d H:i:s', strtotime($modifier, time()));
}

function user_timezone(?array $user = null): string
{
    $tz = $user['timezone'] ?? APP_DEFAULT_TIMEZONE;
    try {
        new DateTimeZone((string)$tz);
        return (string)$tz;
    } catch (Throwable $e) {
        return APP_DEFAULT_TIMEZONE;
    }
}

/** UTC datetime'i kullanici saat dilimine cevirip formatlar. */
function local_datetime(?string $utc, string $format = 'd.m.Y H:i', string $tz = APP_DEFAULT_TIMEZONE): string
{
    if ($utc === null || $utc === '' || str_starts_with($utc, '0000')) {
        return '-';
    }
    try {
        $dt = new DateTime($utc, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone($tz));
        return $dt->format($format);
    } catch (Throwable $e) {
        return '-';
    }
}

/** Kullanicinin yerel tarihini (Y-m-d) doner. */
function local_date(string $tz = APP_DEFAULT_TIMEZONE, ?int $ts = null): string
{
    try {
        $dt = new DateTime('@' . ($ts ?? time()));
        $dt->setTimezone(new DateTimeZone($tz));
        return $dt->format('Y-m-d');
    } catch (Throwable $e) {
        return gmdate('Y-m-d', $ts ?? time());
    }
}

function human_minutes(int $minutes): string
{
    if ($minutes < 60) {
        return $minutes . ' dk';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $m === 0 ? $h . ' saat' : $h . ' saat ' . $m . ' dk';
}

/* ==================================================================
 * Metin
 * ================================================================== */

/** Turkce karakterleri ASCII'ye cevirir. */
function tr_to_ascii(string $text): string
{
    $map = [
        'ş' => 's', 'Ş' => 'S', 'ı' => 'i', 'İ' => 'I', 'ğ' => 'g', 'Ğ' => 'G',
        'ü' => 'u', 'Ü' => 'U', 'ö' => 'o', 'Ö' => 'O', 'ç' => 'c', 'Ç' => 'C',
        'â' => 'a', 'Â' => 'A', 'î' => 'i', 'Î' => 'I', 'û' => 'u', 'Û' => 'U',
    ];
    return strtr($text, $map);
}

/** Almanca ozel karakterleri karsilastirma icin normalize eder. */
function de_normalize(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function slugify(string $text): string
{
    $text = tr_to_ascii($text);
    $text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
    return trim($text, '-');
}

function str_limit(?string $text, int $len = 80): string
{
    $text = trim((string)$text);
    if (mb_strlen($text) <= $len) {
        return $text;
    }
    return mb_substr($text, 0, $len - 1) . '…';
}

/* ==================================================================
 * Kriptografi / token
 * ================================================================== */

function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function hash_token(string $token): string
{
    return hash('sha256', $token);
}

/** 6 haneli sayisal dogrulama kodu. */
function random_numeric_code(int $digits = 6): string
{
    $min = (int)str_pad('1', $digits, '0');
    $max = (int)str_repeat('9', $digits);
    return (string)random_int($min, $max);
}

/** Karisan karakterler haric 6 haneli bagis kodu. */
function random_donation_code(int $len = 6): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

function password_hash_app(string $password): string
{
    if (defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    return password_hash($password, PASSWORD_DEFAULT);
}

/* ==================================================================
 * Hiz limiti / brute force
 * ================================================================== */

/**
 * Sayaci arttirir. Limit asilmissa false doner.
 */
function rate_limit_hit(string $bucket, string $identifier, int $limit, int $windowSeconds): bool
{
    $identifier = substr($identifier, 0, 190);
    $now = time();
    try {
        $row = db_row(
            'SELECT id, hits, UNIX_TIMESTAMP(window_start) AS ws FROM rate_limits WHERE bucket = ? AND identifier = ?',
            [$bucket, $identifier]
        );
        if ($row === null) {
            db_exec(
                'INSERT INTO rate_limits (bucket, identifier, hits, window_start) VALUES (?, ?, 1, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE hits = hits + 1',
                [$bucket, $identifier]
            );
            return true;
        }
        if (($now - (int)$row['ws']) > $windowSeconds) {
            db_exec('UPDATE rate_limits SET hits = 1, window_start = UTC_TIMESTAMP() WHERE id = ?', [$row['id']]);
            return true;
        }
        if ((int)$row['hits'] >= $limit) {
            return false;
        }
        db_exec('UPDATE rate_limits SET hits = hits + 1 WHERE id = ?', [$row['id']]);
        return true;
    } catch (Throwable $e) {
        return true;
    }
}

function rate_limit_reset(string $bucket, string $identifier): void
{
    try {
        db_exec('DELETE FROM rate_limits WHERE bucket = ? AND identifier = ?', [$bucket, substr($identifier, 0, 190)]);
    } catch (Throwable $e) {
        /* yoksay */
    }
}

/* ==================================================================
 * Kilit (cron mutex)
 * ================================================================== */

function acquire_lock(string $name, int $ttlSeconds = 300): bool
{
    try {
        db_exec('DELETE FROM app_locks WHERE expires_at < UTC_TIMESTAMP()');
        db_exec(
            'INSERT INTO app_locks (lock_name, locked_at, expires_at, owner) VALUES (?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND), ?)',
            [$name, $ttlSeconds, substr((string)getmypid(), 0, 64)]
        );
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function release_lock(string $name): void
{
    try {
        db_exec('DELETE FROM app_locks WHERE lock_name = ?', [$name]);
    } catch (Throwable $e) {
        /* yoksay */
    }
}

/* ==================================================================
 * Loglama (hicbir sir loglanmaz)
 * ================================================================== */

/** Metinden bilinen sir desenlerini temizler. */
function scrub_secrets(string $text): string
{
    $patterns = [
        '/\b\d{6,10}:[A-Za-z0-9_\-]{30,}\b/' => '[telegram-token]',
        '/\b(sk|pk)-[A-Za-z0-9_\-]{16,}\b/'  => '[api-key]',
        '/(password|passwd|pwd|secret|token|api[_-]?key)\s*[=:]\s*\S+/i' => '$1=[gizli]',
    ];
    foreach ($patterns as $p => $r) {
        $text = preg_replace($p, $r, $text) ?? $text;
    }
    /* Cok kisa degerler metnin icinde tesadufen gecebilir; maskeleme
       yalnizca gercekci uzunluktaki sirlar icin uygulanir. */
    if (strlen(DB_PASSWORD) >= 6) {
        $text = str_replace(DB_PASSWORD, '[gizli]', $text);
    }

    /* Veritabanindaki sirlar: degerleri metinde geciyorsa maskele.
       settings_all() onbellekten okur, bu yuzden ek sorgu maliyeti yoktur.
       Kurulum oncesinde ayarlar okunamayabilir; o durumda sessizce atlanir. */
    try {
        foreach (['smtp_password', 'telegram_bot_token', 'ai_api_key', 'cron_secret'] as $key) {
            $value = settings_all()[$key] ?? '';
            if (is_string($value) && strlen($value) >= 8) {
                $text = str_replace($value, '[gizli]', $text);
            }
        }
    } catch (Throwable) {
        /* Ayarlara ulasilamiyorsa yalnizca desen tabanli temizlik uygulanir. */
    }

    return $text;
}

/**
 * Uygulama log dosyasinin yolu.
 * Once storage/ denenir; yazilamazsa sistem gecici dizini kullanilir.
 * Basarisiz olursa bos dize doner ve yalnizca PHP error_log kullanilir.
 */
function app_log_path(): string
{
    static $path = null;
    if ($path !== null) {
        return $path;
    }
    $dir = APP_ROOT . '/storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        /* Dizin listelenmesin ve dogrudan indirilemesin. */
        @file_put_contents($dir . '/.htaccess', "Require all denied
Deny from all
");
        @file_put_contents($dir . '/index.html', '');
    }
    /* Dosya adi .php ile biter ve ilk satiri "exit" dir: sunucu .htaccess'i
       yok saysa bile (or. yalnizca nginx) icerik tarayiciya sizmaz. */
    if (is_dir($dir) && is_writable($dir)) {
        return $path = $dir . '/almancapro-log.php';
    }
    $tmp = sys_get_temp_dir();
    if ($tmp !== '' && is_writable($tmp)) {
        return $path = $tmp . '/almancapro-log.php';
    }
    return $path = '';
}

/** Log dosyasinin ilk satiri: dogrudan cagrilirsa hicbir sey yazdirmaz. */
const APP_LOG_GUARD = "<?php exit; /* AlmancaPro hata gunlugu - dogrudan okunamaz */ ?>\n";

/** Log dosyasini asiri buyumeye karsi kirpar. */
function app_log_rotate(string $file): void
{
    if (is_file($file) && filesize($file) > 512000) {
        $lines = @file($file);
        if ($lines !== false) {
            @file_put_contents($file, APP_LOG_GUARD . implode('', array_slice($lines, -300)));
        }
    }
}

function app_log(string $message): void
{
    $clean = scrub_secrets($message);
    error_log('[AlmancaPro] ' . $clean);

    $file = app_log_path();
    if ($file === '') {
        return;
    }
    if (!is_file($file)) {
        @file_put_contents($file, APP_LOG_GUARD);
    }
    app_log_rotate($file);
    @file_put_contents(
        $file,
        gmdate('Y-m-d H:i:s') . ' UTC  ' . $clean . "\n",
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Beklenmeyen bir hatayi kaydeder ve kullaniciya gosterilecek kisa bir
 * referans kodu dondurur. Ayrintilar yalnizca sunucudaki loga yazilir.
 */
function app_log_exception(Throwable $e, string $context = ''): string
{
    $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $file = str_replace(APP_ROOT . '/', '', $e->getFile());
    app_log(sprintf(
        'HATA [%s] %s%s: %s @ %s:%d',
        $ref,
        $context !== '' ? $context . ' · ' : '',
        get_class($e),
        $e->getMessage(),
        $file,
        $e->getLine()
    ));
    app_log('HATA [' . $ref . '] izleme: ' . str_replace(APP_ROOT . '/', '', $e->getTraceAsString()));
    return $ref;
}

function admin_log(?int $adminId, string $action, ?string $targetType = null, ?string $targetId = null, array $metadata = []): void
{
    try {
        $meta = $metadata === [] ? null : scrub_secrets((string)json_encode($metadata, JSON_UNESCAPED_UNICODE));
        db_exec(
            'INSERT INTO admin_logs (admin_id, action, target_type, target_id, ip_address, user_agent, metadata)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$adminId, $action, $targetType, $targetId, client_ip(), user_agent(), $meta]
        );
    } catch (Throwable $e) {
        app_log('admin_log yazılamadı: ' . $e->getMessage());
    }
}

/* ==================================================================
 * Dogrulama
 * ================================================================== */

function valid_email(string $email): bool
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL) && mb_strlen($email) <= 190;
}

function password_problems(string $password): array
{
    $errors = [];
    if (mb_strlen($password) < 8) {
        $errors[] = 'Şifre en az 8 karakter olmalı.';
    }
    if (mb_strlen($password) > 200) {
        $errors[] = 'Şifre çok uzun.';
    }
    if (!preg_match('/[A-Za-zÀ-ÿ]/u', $password)) {
        $errors[] = 'Şifre en az bir harf içermeli.';
    }
    if (!preg_match('/\d/', $password)) {
        $errors[] = 'Şifre en az bir rakam içermeli.';
    }
    return $errors;
}

/** TR IBAN dogrulamasi (mod-97). */
function valid_tr_iban(string $iban): bool
{
    $iban = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
    if (!preg_match('/^TR\d{24}$/', $iban)) {
        return false;
    }
    $rearranged = substr($iban, 4) . substr($iban, 0, 4);
    $numeric = '';
    foreach (str_split($rearranged) as $ch) {
        $numeric .= ctype_alpha($ch) ? (string)(ord($ch) - 55) : $ch;
    }
    $remainder = 0;
    foreach (str_split($numeric) as $digit) {
        $remainder = ($remainder * 10 + (int)$digit) % 97;
    }
    return $remainder === 1;
}

function format_iban(string $iban): string
{
    $iban = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
    return trim(chunk_split($iban, 4, ' '));
}

/* ==================================================================
 * Gorunum yardimcilari
 * ================================================================== */

function pct(int|float $value, int $total): int
{
    if ($total <= 0) {
        return 0;
    }
    return (int)max(0, min(100, round(($value / $total) * 100)));
}

function cefr_levels(): array
{
    return ['A0', 'A1', 'A2', 'B1'];
}

function cefr_label(string $level): string
{
    return match ($level) {
        'A0' => 'A0 · Sıfırdan başlangıç',
        'A1' => 'A1 · Temel',
        'A2' => 'A2 · Temel üstü',
        'B1' => 'B1 · Orta',
        default => $level,
    };
}

function mastery_status_label(string $status): array
{
    return match ($status) {
        'mastered'  => ['✓', 'MASTERED', 'ok'],
        'strong'    => ['✓', 'GÜÇLÜ', 'ok'],
        'reviewing' => ['↺', 'TEKRARDA', 'warn'],
        'learning'  => ['●', 'ÖĞRENİYORSUN', 'active'],
        'weak'      => ['●', 'ZAYIF', 'warn'],
        'overdue'   => ['↺', 'TEKRAR GEREKİYOR', 'warn'],
        'introduced'=> ['○', 'YENİ', 'muted'],
        default     => ['○', 'BAŞLAMADIN', 'muted'],
    };
}

function article_shape(string $article): string
{
    return match ($article) {
        'der' => 'der',
        'die' => 'die',
        'das' => 'das',
        default => '',
    };
}

/** Artikel rozeti HTML'i (renk + sekil + metin). */
function artikel_badge(?string $article, string $size = 'sm'): string
{
    if ($article === null || $article === '') {
        return '';
    }
    $a = strtolower($article);
    if (!in_array($a, ['der', 'die', 'das'], true)) {
        return '';
    }
    $cls = 'artikel artikel--' . $a . ($size === 'lg' ? ' artikel--lg' : '');
    return '<span class="' . $cls . '"><span class="artikel__shape" aria-hidden="true"></span>' . strtoupper($a) . '</span>';
}

/** Kelimenin tam sozluk basligi: "der Tisch – die Tische". */
function vocab_headword(array $v): string
{
    $head = trim((string)($v['article'] ?? '') . ' ' . (string)$v['german']);
    $head = trim($head);
    if (!empty($v['plural'])) {
        $head .= ' – ' . $v['plural'];
    }
    return $head;
}

function current_path(): string
{
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    return $script;
}

/* ==================================================================
 * Kurulum durumu
 * ================================================================== */

function is_installed(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        if (!db_table_exists('site_settings')) {
            return $cached = false;
        }
        $v = db_value("SELECT setting_value FROM site_settings WHERE setting_key = 'install_completed'");
        return $cached = ($v === '1');
    } catch (Throwable $e) {
        return $cached = false;
    }
}

/** Kurulum tamamlanmamissa kurulumu tetikler (idempotent). */
function ensure_installed(): void
{
    if (is_installed()) {
        return;
    }

    /* Ilk kurulum ~10.000 satir yazar. Paylasimli sunucularda varsayilan
       max_execution_time buna yetmeyip "500 Internal Server Error" uretebilir.
       Once limitleri yukseltmeyi dene. */
    $limitRaised = false;
    if (function_exists('set_time_limit') && !in_array('set_time_limit', explode(',', str_replace(' ', '', (string)ini_get('disable_functions'))), true)) {
        $limitRaised = @set_time_limit(0);
    }
    @ini_set('memory_limit', '256M');
    if (function_exists('ignore_user_abort')) {
        ignore_user_abort(true);
    }

    /* Limit yukseltilemediyse ve mevcut sure riskliyse, kurulumu bu istekte
       yapma: kullaniciyi kurulum sayfasina yonlendir. Orada islem adim adim
       ve ilerleme gostererek yapilir, boylece zaman asimi olusmaz. */
    $maxTime = (int)ini_get('max_execution_time');
    $risky = !$limitRaised && $maxTime > 0 && $maxTime < 120;
    if ($risky && current_path() !== 'install.php' && PHP_SAPI !== 'cli') {
        redirect('/install.php?otomatik=1');
    }

    require_once __DIR__ . '/installer.php';
    $result = almancapro_run_install();

    if (empty($result['ok'])) {
        /* Kurulum yarim kaldi. Sessizce devam edersek her sayfa
           "eksik tablo" hatasi verir; bunun yerine acikca soyle. */
        $failed = [];
        foreach (($result['steps'] ?? []) as $step) {
            if (($step['status'] ?? '') !== 'ok') {
                $failed[] = $step['name'] . ' (' . $step['detail'] . ')';
            }
        }
        app_log('Kurulum tamamlanamadi: ' . ($result['error'] ?? 'bilinmeyen')
            . ($failed !== [] ? ' · ' . implode('; ', $failed) : ''));

        throw new RuntimeException('install_incomplete:' . ($result['error'] ?? 'unknown'));
    }
}
