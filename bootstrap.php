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

/**
 * Yakalanmamis hatalar icin guvenli sayfa.
 *
 * Ham "500 Internal Server Error" yerine ne oldugunu anlatan bir sayfa
 * gosterilir. Hata ayrintisi (dosya, satir, izleme) yalnizca sunucudaki
 * loga yazilir; kullaniciya sadece kisa bir referans kodu verilir.
 * APP_DEBUG aciksa ya da yonetici oturumu varsa ayrinti ekranda da gosterilir.
 */
function app_handle_throwable(Throwable $e): void
{
    $ref = app_log_exception($e, current_path());

    if (wants_json()) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Beklenmeyen bir hata oluştu. Referans: ' . $ref,
            'data'    => (object)[],
            'errors'  => (object)[],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $showDetail = APP_DEBUG || current_admin_id_safe() !== null;
    $detail = $showDetail
        ? get_class($e) . ': ' . $e->getMessage()
            . ' @ ' . str_replace(APP_ROOT . '/', '', $e->getFile()) . ':' . $e->getLine()
        : '';

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    ?><!doctype html>
<html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Bir şeyler ters gitti · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/main.css?v=<?= e(APP_VERSION) ?>">
</head><body>
<div class="page"><div class="focus-area">
  <p class="eyebrow">Hata</p>
  <h1>Bir şeyler ters gitti</h1>
  <p>Bu sayfa açılırken beklenmeyen bir hata oluştu. Öğrenme verilerin güvende;
     hiçbir ilerleme kaybolmadı.</p>
  <p class="small">Hata referansı: <strong class="mono"><?= e($ref) ?></strong><br>
     Bu kodu site yöneticisine iletirseniz sorun sunucu kayıtlarından bulunabilir.</p>
  <?php if ($detail !== ''): ?>
    <div class="alert alert--error" style="margin-top:18px">
      <span class="alert__icon" aria-hidden="true">✕</span>
      <span class="mono" style="font-size:13px;word-break:break-word"><?= e($detail) ?></span>
    </div>
    <p class="small">Bu ayrıntı yalnızca yöneticilere gösterilir.</p>
  <?php endif; ?>
  <div class="row" style="margin-top:22px">
    <a class="btn btn--inline" href="/">ANA SAYFA</a>
    <a class="btn btn--secondary btn--inline" href="javascript:history.back()">GERİ DÖN</a>
  </div>
  <p class="small" style="margin-top:26px">
    Site yöneticisiyseniz <a href="/tani.php">/tani.php</a> sayfası kurulum durumunu ve
    son hataları listeler.</p>
</div></div>
</body></html><?php
}

/** Hata yakalayicilarini bir kez kurar. */
function app_register_error_handlers(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    set_exception_handler(static function (Throwable $e): void {
        app_handle_throwable($e);
    });

    /* Uyarilari da istisnaya cevirme; yalnizca logla ki sayfa calismaya devam etsin. */
    set_error_handler(static function (int $no, string $str, string $file = '', int $line = 0): bool {
        if ((error_reporting() & $no) === 0) {
            return false;
        }
        if (in_array($no, [E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            throw new ErrorException($str, 0, $no, $file, $line);
        }
        app_log(sprintf('UYARI (%d) %s @ %s:%d', $no, $str, str_replace(APP_ROOT . '/', '', $file), $line));
        return true;
    });

    register_shutdown_function(static function (): void {
        $err = error_get_last();
        if ($err === null || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        app_handle_throwable(new ErrorException(
            $err['message'],
            0,
            $err['type'],
            $err['file'],
            (int)$err['line']
        ));
    });
}

/** Uygulamayi baslatir: oturum, guvenlik basliklari, kurulum. */
function app_boot(bool $requireInstall = true): void
{
    app_register_error_handlers();
    app_session_start();
    send_security_headers();

    if (!$requireInstall) {
        return;
    }

    try {
        ensure_installed();
    } catch (Throwable $e) {
        $ref = app_log_exception($e, 'kurulum');
        $incomplete = str_starts_with($e->getMessage(), 'install_incomplete');

        /* Yonetici veya hata ayiklama modunda somut yonlendirme goster. */
        if (APP_DEBUG || current_admin_id_safe() !== null || $incomplete) {
            fatal_page(
                $incomplete ? 'Kurulum tamamlanmadı' : 'Veritabanına bağlanılamadı',
                $incomplete
                    ? 'Veritabanı tabloları veya eğitim içeriği eksik. Kurulumu tamamlamak için '
                      . '/install.php adresini açın; ayrıntılar için /tani.php sayfasına bakın. '
                      . 'Hata referansı: ' . $ref
                    : 'Veritabanı sunucusuna ulaşılamıyor. Bağlantı ayarlarını /tani.php sayfasından '
                      . 'kontrol edebilirsiniz. Hata referansı: ' . $ref,
                503
            );
        }

        fatal_page(
            'Site şu anda kullanılamıyor',
            'Kısa bir süre sonra tekrar dene. Hata referansı: ' . $ref,
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
