<?php
/**
 * AlmancaPro - Kullanici kimlik dogrulama katmani.
 *
 * Kullanici oturumu ($_SESSION['user_id']) admin oturumundan
 * ($_SESSION['admin_id']) tamamen ayridir; biri digerine yetki vermez.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/* ------------------------------------------------------------------
 * Oturum kurma / kapatma
 * ------------------------------------------------------------------ */

function auth_login_user(array $user, bool $remember = false): void
{
    app_session_start();
    session_regenerate_id(true);      /* session fixation korumasi */
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_authenticated'] = true;
    $_SESSION['user_login_at'] = time();
    $_SESSION['user_fingerprint'] = hash('sha256', user_agent());
    unset($_SESSION['pending_verify_user']);

    db_exec('UPDATE users SET last_login_at = UTC_TIMESTAMP(), last_login_ip = ? WHERE id = ?', [client_ip(), (int)$user['id']]);

    if ($remember) {
        auth_issue_remember_token((int)$user['id']);
    }
}

function auth_logout(): void
{
    app_session_start();
    $uid = current_user_id();
    auth_clear_remember_cookie($uid);
    unset($_SESSION['user_id'], $_SESSION['user_authenticated'], $_SESSION['user_login_at'], $_SESSION['user_fingerprint']);
    if (empty($_SESSION['admin_id'])) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    } else {
        session_regenerate_id(true);
    }
}

/* ------------------------------------------------------------------
 * "Beni hatirla"
 * ------------------------------------------------------------------ */

function auth_issue_remember_token(int $userId): void
{
    $selector = bin2hex(random_bytes(12));           /* 24 karakter */
    $validator = random_token(32);
    db_exec(
        'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, user_agent)
         VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? DAY), ?)',
        [$userId, $selector, hash_token($validator), REMEMBER_DAYS, user_agent()]
    );
    setcookie(REMEMBER_COOKIE, $selector . ':' . $validator, [
        'expires'  => time() + (REMEMBER_DAYS * 86400),
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function auth_clear_remember_cookie(?int $userId = null): void
{
    $raw = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if (is_string($raw) && str_contains($raw, ':')) {
        [$selector] = explode(':', $raw, 2);
        try {
            db_exec('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
        } catch (Throwable $e) {
            /* yoksay */
        }
    }
    if ($userId !== null) {
        /* kullanici cikis yapinca yalnizca bu cihazin tokeni silinir */
    }
    setcookie(REMEMBER_COOKIE, '', [
        'expires'  => time() - 42000,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function auth_forget_all_devices(int $userId): void
{
    db_exec('DELETE FROM remember_tokens WHERE user_id = ?', [$userId]);
}

function auth_try_remember_login(): bool
{
    $raw = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if (!is_string($raw) || !str_contains($raw, ':')) {
        return false;
    }
    [$selector, $validator] = explode(':', $raw, 2);
    if ($selector === '' || $validator === '') {
        return false;
    }
    try {
        $row = db_row(
            'SELECT rt.id, rt.user_id, rt.token_hash, rt.expires_at
             FROM remember_tokens rt WHERE rt.selector = ? LIMIT 1',
            [$selector]
        );
    } catch (Throwable $e) {
        return false;
    }
    if ($row === null) {
        auth_clear_remember_cookie();
        return false;
    }
    if (strtotime((string)$row['expires_at']) < time()) {
        db_exec('DELETE FROM remember_tokens WHERE id = ?', [$row['id']]);
        auth_clear_remember_cookie();
        return false;
    }
    if (!hash_equals((string)$row['token_hash'], hash_token($validator))) {
        /* Calinmis/gecersiz token: kullanicinin tum tokenlarini iptal et. */
        db_exec('DELETE FROM remember_tokens WHERE user_id = ?', [$row['user_id']]);
        auth_clear_remember_cookie();
        return false;
    }

    $user = db_row('SELECT * FROM users WHERE id = ? AND is_active = 1 AND is_verified = 1', [$row['user_id']]);
    if ($user === null) {
        return false;
    }
    /* Token rotasyonu */
    db_exec('DELETE FROM remember_tokens WHERE id = ?', [$row['id']]);
    auth_login_user($user, true);
    return true;
}

/* ------------------------------------------------------------------
 * Mevcut kullanici
 * ------------------------------------------------------------------ */

function current_user_id(): ?int
{
    app_session_start();
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_authenticated'])) {
        return (int)$_SESSION['user_id'];
    }
    return null;
}

function current_user(bool $fresh = false): ?array
{
    static $cache = null;
    if ($cache !== null && !$fresh) {
        return $cache;
    }
    $id = current_user_id();
    if ($id === null) {
        if (!auth_try_remember_login()) {
            return null;
        }
        $id = current_user_id();
        if ($id === null) {
            return null;
        }
    }
    try {
        $user = db_row('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
    } catch (Throwable $e) {
        return null;
    }
    if ($user === null || (int)$user['is_active'] !== 1) {
        auth_logout();
        return null;
    }
    return $cache = $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/** Giris zorunlu. Dogrulanmamis kullanici verify sayfasina yonlenir. */
function require_login(bool $requireVerified = true): array
{
    $user = current_user();
    if ($user === null) {
        if (wants_json()) {
            json_response(false, 'Bu işlem için giriş yapmalısın.', [], [], 401);
        }
        $next = urlencode((string)($_SERVER['REQUEST_URI'] ?? '/dashboard.php'));
        redirect('/login.php?next=' . $next);
    }
    if ($requireVerified && (int)$user['is_verified'] !== 1) {
        if (wants_json()) {
            json_response(false, 'E-posta adresini doğrulaman gerekiyor.', [], [], 403);
        }
        redirect('/verify.php');
    }
    return $user;
}

/** Onboarding tamamlanmadan uygulama sayfalarina girilmez. */
function require_onboarded(): array
{
    $user = require_login();
    if ((int)$user['onboarding_completed'] !== 1 && current_path() !== 'onboarding.php') {
        redirect('/onboarding.php');
    }
    return $user;
}

/* ------------------------------------------------------------------
 * Kayit / dogrulama / sifre
 * ------------------------------------------------------------------ */

function auth_find_user_by_email(string $email): ?array
{
    return db_row('SELECT * FROM users WHERE email = ? LIMIT 1', [mb_strtolower(trim($email))]);
}

function auth_record_login_attempt(string $identifier, bool $success): void
{
    try {
        db_exec(
            'INSERT INTO login_attempts (identifier, ip_address, success) VALUES (?, ?, ?)',
            [substr(mb_strtolower($identifier), 0, 190), client_ip(), $success ? 1 : 0]
        );
    } catch (Throwable $e) {
        /* yoksay */
    }
}

/**
 * Yeni dogrulama kodu olusturur, hash'ini saklar ve duz kodu doner.
 * Duz kod yalnizca e-posta gonderimi icin kullanilir, DB'ye yazilmaz.
 */
function auth_create_verification_code(int $userId): string
{
    db_exec('UPDATE email_verifications SET consumed_at = UTC_TIMESTAMP() WHERE user_id = ? AND consumed_at IS NULL', [$userId]);
    $code = random_numeric_code(6);
    db_exec(
        'INSERT INTO email_verifications (user_id, code_hash, expires_at, ip_address)
         VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE), ?)',
        [$userId, hash_token($code), client_ip()]
    );
    return $code;
}

/**
 * @return array{ok:bool,error:string}
 */
function auth_check_verification_code(int $userId, string $code): array
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) {
        return ['ok' => false, 'error' => 'Kod 6 haneli olmalı.'];
    }
    $row = db_row(
        'SELECT * FROM email_verifications WHERE user_id = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1',
        [$userId]
    );
    if ($row === null) {
        return ['ok' => false, 'error' => 'Geçerli bir kod bulunamadı. Yeni kod iste.'];
    }
    if (strtotime((string)$row['expires_at']) < time()) {
        return ['ok' => false, 'error' => 'Kodun süresi doldu. Yeni kod iste.'];
    }
    if ((int)$row['attempts'] >= 5) {
        return ['ok' => false, 'error' => 'Çok fazla hatalı deneme yaptın. Yeni kod iste.'];
    }
    db_exec('UPDATE email_verifications SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
    if (!hash_equals((string)$row['code_hash'], hash_token($code))) {
        return ['ok' => false, 'error' => 'Kod hatalı. Tekrar dene.'];
    }
    db_exec('UPDATE email_verifications SET consumed_at = UTC_TIMESTAMP() WHERE id = ?', [$row['id']]);
    db_exec('UPDATE users SET is_verified = 1 WHERE id = ?', [$userId]);
    return ['ok' => true, 'error' => ''];
}

/** Son gonderimden bu yana gecen saniye. */
function auth_verification_last_sent_seconds(int $userId): int
{
    $v = db_value(
        'SELECT TIMESTAMPDIFF(SECOND, created_at, UTC_TIMESTAMP()) FROM email_verifications WHERE user_id = ? ORDER BY id DESC LIMIT 1',
        [$userId]
    );
    return $v === null ? 99999 : (int)$v;
}

function auth_create_password_reset(int $userId): string
{
    db_exec('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND used_at IS NULL', [$userId]);
    $token = random_token(32);
    db_exec(
        'INSERT INTO password_resets (user_id, token_hash, expires_at, ip_address)
         VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 60 MINUTE), ?)',
        [$userId, hash_token($token), client_ip()]
    );
    return $token;
}

function auth_find_password_reset(string $token): ?array
{
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $row = db_row(
        'SELECT pr.*, u.email, u.name FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > UTC_TIMESTAMP() LIMIT 1',
        [hash_token($token)]
    );
    return $row;
}

function auth_consume_password_reset(int $resetId, int $userId, string $newPassword): void
{
    db_transaction(function () use ($resetId, $userId, $newPassword) {
        db_exec('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE id = ?', [$resetId]);
        db_exec(
            'UPDATE users SET password_hash = ?, password_changed_at = UTC_TIMESTAMP() WHERE id = ?',
            [password_hash_app($newPassword), $userId]
        );
        db_exec('DELETE FROM remember_tokens WHERE user_id = ?', [$userId]);
    });
}
