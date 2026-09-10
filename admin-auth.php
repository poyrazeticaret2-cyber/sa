<?php
/**
 * AlmancaPro - Admin kimlik dogrulama.
 *
 * Normal kullanici oturumu ($_SESSION['user_id']) HICBIR sekilde
 * admin yetkisi vermez. Admin oturumu ayri anahtarlarla tutulur.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const ADMIN_MAX_FAILED = 5;
const ADMIN_LOCK_MINUTES = 15;

function admin_login_session(array $admin): void
{
    app_session_start();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_login_at'] = time();
    $_SESSION['admin_fingerprint'] = hash('sha256', user_agent());

    db_exec(
        'UPDATE admins SET last_login_at = UTC_TIMESTAMP(), last_login_ip = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?',
        [client_ip(), (int)$admin['id']]
    );
    admin_log((int)$admin['id'], 'LOGIN_SUCCESS', 'admin', (string)$admin['id']);
}

function admin_logout_session(): void
{
    app_session_start();
    $id = current_admin_id();
    if ($id !== null) {
        admin_log($id, 'LOGOUT', 'admin', (string)$id);
    }
    unset($_SESSION['admin_id'], $_SESSION['admin_authenticated'], $_SESSION['admin_login_at'], $_SESSION['admin_fingerprint']);
    session_regenerate_id(true);
}

function current_admin_id(): ?int
{
    app_session_start();
    if (!empty($_SESSION['admin_id']) && !empty($_SESSION['admin_authenticated'])) {
        return (int)$_SESSION['admin_id'];
    }
    return null;
}

function current_admin(bool $fresh = false): ?array
{
    static $cache = null;
    if ($cache !== null && !$fresh) {
        return $cache;
    }
    $id = current_admin_id();
    if ($id === null) {
        return null;
    }
    try {
        $admin = db_row('SELECT * FROM admins WHERE id = ? LIMIT 1', [$id]);
    } catch (Throwable $e) {
        return null;
    }
    if ($admin === null || (int)$admin['is_active'] !== 1) {
        admin_logout_session();
        return null;
    }
    return $cache = $admin;
}

/** Admin oturumu zorunlu. */
function require_admin(): array
{
    $admin = current_admin();
    if ($admin === null) {
        if (wants_json()) {
            json_response(false, 'Yönetici girişi gerekli.', [], [], 401);
        }
        redirect('/admin-login.php');
    }
    /* Sifre degistirme zorunlulugu */
    $script = current_path();
    if ((int)$admin['must_change_password'] === 1
        && !in_array($script, ['admin-password.php', 'admin-logout.php'], true)) {
        redirect('/admin-password.php?first=1');
    }
    return $admin;
}

/**
 * Admin giris denemesi.
 * @return array{ok:bool,error:string,admin:?array}
 */
function admin_attempt_login(string $username, string $password): array
{
    $ip = client_ip();
    $generic = 'Kullanıcı adı veya şifre hatalı.';

    if (!rate_limit_hit('admin_login_ip', $ip, 15, 900)) {
        return ['ok' => false, 'error' => 'Çok fazla deneme yapıldı. 15 dakika sonra tekrar dene.', 'admin' => null];
    }
    if (!rate_limit_hit('admin_login_user', mb_strtolower($username), 10, 900)) {
        return ['ok' => false, 'error' => 'Çok fazla deneme yapıldı. 15 dakika sonra tekrar dene.', 'admin' => null];
    }

    $admin = db_row('SELECT * FROM admins WHERE username = ? LIMIT 1', [$username]);
    admin_record_attempt($username, false);

    if ($admin === null) {
        /* Kullanici sayimi yapilmaz: ayni sureyi harcamak icin sahte dogrulama. */
        password_verify($password, '$2y$12$usesomesillystringfoursev3nuGjPFnZAt7bnLKlqEcqOB0z0x2ZzC');
        return ['ok' => false, 'error' => $generic, 'admin' => null];
    }

    if ($admin['locked_until'] !== null && strtotime((string)$admin['locked_until']) > time()) {
        return ['ok' => false, 'error' => 'Hesap geçici olarak kilitlendi. Birazdan tekrar dene.', 'admin' => null];
    }

    if ((int)$admin['is_active'] !== 1) {
        return ['ok' => false, 'error' => $generic, 'admin' => null];
    }

    if (!password_verify($password, (string)$admin['password_hash'])) {
        $failed = (int)$admin['failed_login_attempts'] + 1;
        if ($failed >= ADMIN_MAX_FAILED) {
            db_exec(
                'UPDATE admins SET failed_login_attempts = ?, locked_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE) WHERE id = ?',
                [$failed, ADMIN_LOCK_MINUTES, $admin['id']]
            );
        } else {
            db_exec('UPDATE admins SET failed_login_attempts = ? WHERE id = ?', [$failed, $admin['id']]);
        }
        admin_log((int)$admin['id'], 'LOGIN_FAILED', 'admin', (string)$admin['id']);
        return ['ok' => false, 'error' => $generic, 'admin' => null];
    }

    /* Hash algoritmasi guncellendiyse yenile. */
    if (password_needs_rehash((string)$admin['password_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash_app($password), $admin['id']]);
    }

    admin_record_attempt($username, true);
    rate_limit_reset('admin_login_user', mb_strtolower($username));
    return ['ok' => true, 'error' => '', 'admin' => $admin];
}

function admin_record_attempt(string $username, bool $success): void
{
    try {
        db_exec(
            'INSERT INTO admin_login_attempts (username, ip_address, success) VALUES (?, ?, ?)',
            [substr($username, 0, 64), client_ip(), $success ? 1 : 0]
        );
    } catch (Throwable $e) {
        /* yoksay */
    }
}

function admin_change_password(int $adminId, string $current, string $new): array
{
    $admin = db_row('SELECT * FROM admins WHERE id = ?', [$adminId]);
    if ($admin === null) {
        return ['ok' => false, 'error' => 'Yönetici bulunamadı.'];
    }
    if (!password_verify($current, (string)$admin['password_hash'])) {
        return ['ok' => false, 'error' => 'Mevcut şifre hatalı.'];
    }
    $problems = password_problems($new);
    if ($problems !== []) {
        return ['ok' => false, 'error' => implode(' ', $problems)];
    }
    if ($new === DEFAULT_ADMIN_PASSWORD) {
        return ['ok' => false, 'error' => 'Varsayılan şifreyi yeniden kullanamazsın.'];
    }
    db_exec(
        'UPDATE admins SET password_hash = ?, password_changed_at = UTC_TIMESTAMP(), must_change_password = 0 WHERE id = ?',
        [password_hash_app($new), $adminId]
    );
    admin_log($adminId, 'PASSWORD_CHANGED', 'admin', (string)$adminId);
    return ['ok' => true, 'error' => ''];
}
