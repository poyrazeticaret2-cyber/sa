<?php
/**
 * AlmancaPro - Giris noktasi.
 *
 * Bu dosya BILEREK eski PHP soz dizimiyle yazilmistir (PHP 5.4+ ayristirir).
 * Amaci: sunucuda PHP surumu yetersizse ham "500 Internal Server Error"
 * yerine ne yapilmasi gerektigini anlatan bir sayfa gostermek.
 * Asil acilis sayfasi home.php dosyasindadir.
 */

if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    header('HTTP/1.1 503 Service Unavailable');
    header('Content-Type: text/html; charset=utf-8');
    header('Retry-After: 3600');
    ?><!doctype html>
<html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PHP sürümü güncellenmeli</title>
<style>
body{font:16px/1.6 -apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;background:#F4F2ED;
     color:#111110;margin:0;padding:24px}
.b{max-width:620px;margin:8vh auto;background:#fff;border:1px solid #DCD8CF;padding:32px}
h1{font-size:22px;margin:0 0 14px}
code{background:#FAF9F6;border:1px solid #DCD8CF;padding:2px 6px;font-size:14px}
ol{padding-left:20px}li{margin-bottom:8px}
.m{color:#77746B;font-size:14px;margin-top:22px}
</style></head><body><div class="b">
<h1>PHP sürümü güncellenmeli</h1>
<p>AlmancaPro <strong>PHP 8.2</strong> veya üzeri ister.
Bu sunucuda çalışan sürüm: <code><?php echo htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8'); ?></code></p>
<ol>
  <li>Plesk paneline girin.</li>
  <li><strong>Websites &amp; Domains</strong> &gt; alan adınız &gt; <strong>PHP Settings</strong> bölümünü açın.</li>
  <li><strong>PHP version</strong> alanından <strong>8.2</strong> (veya daha yenisini) seçin.</li>
  <li><strong>Run PHP as</strong> alanında <strong>FPM application served by Apache</strong> seçili olsun.</li>
  <li>Kaydedin ve bu sayfayı yenileyin.</li>
</ol>
<p class="m">Ayrıntılı kontrol listesi için <code>/tani.php</code> adresini açabilirsiniz.</p>
</div></body></html><?php
    exit;
}

define('ALMANCAPRO_ENTRY', true);
require __DIR__ . '/home.php';
