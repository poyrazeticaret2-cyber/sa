<?php
/**
 * AlmancaPro - Her sayfanin ilk satirinda cagrilan onyukleyici.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/auth.php';

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Kritik bir hata olustugunda kullaniciya guvenli sayfa gosterir. */
function fatal_page(string $title, string $message, int $code = 500): never
{
    if (!headers_sent()) {
        http_response_code($code);
    }
    render_head($title . ' · ' . APP_NAME, ['noindex' => true]);
    echo '<div class="auth-wrap"><div class="auth-card">';
    echo '<div class="auth-card__head">';
    render_logo('/');
    echo '<h1 style="margin-top:18px">' . e($title) . '</h1>';
    echo '<p class="auth-card__sub">' . e($message) . '</p>';
    echo '</div>';
    echo '<a class="btn btn--block" href="/">ANA SAYFAYA DÖN</a>';
    echo '</div></div>';
    render_foot();
    exit;
}

/** Uygulamayi baslatir: oturum, guvenlik basliklari, kurulum. */
function app_boot(bool $requireInstall = true): void
{
    app_session_start();
    send_security_headers();

    if (!$requireInstall) {
        return;
    }

    try {
        ensure_installed();
    } catch (Throwable $e) {
        app_log('Kurulum/bağlantı hatası: ' . $e->getMessage());
        fatal_page(
            'Site şu anda kullanılamıyor',
            'Veritabanına bağlanılamadı. Lütfen kısa bir süre sonra tekrar dene.',
            503
        );
    }

    /* Bakim modu: yonetici disindaki herkes bilgilendirilir. */
    if (setting_bool('maintenance_mode', false) && current_admin_id_safe() === null) {
        $allowed = ['admin-login.php', 'admin.php', 'admin-logout.php', 'telegram-webhook.php', 'cron.php'];
        if (!in_array(current_path(), $allowed, true)) {
            fatal_page(
                'Kısa bir bakım yapıyoruz',
                'Sistem birazdan tekrar açılacak. Verilerinin hepsi güvende, ilerlemen kaybolmaz.',
                503
            );
        }
    }
}

/** admin-auth.php yuklenmemis olabilir; guvenli kontrol. */
function current_admin_id_safe(): ?int
{
    app_session_start();
    if (!empty($_SESSION['admin_id']) && !empty($_SESSION['admin_authenticated'])) {
        return (int)$_SESSION['admin_id'];
    }
    return null;
}
