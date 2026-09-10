<?php
/**
 * AlmancaPro - Sistem durumu.
 * Hicbir sirri (DB sifresi, SMTP sifresi, bot token, API key, cron secret) ekrana yazmaz.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/telegram-api.php';
app_boot();

$admin = require_admin();

/** @return array{0:string,1:string,2:string} durum, baslik, aciklama */
function health_row(bool $ok, bool $warnOnly, string $label, string $okText, string $badText): array
{
    return [$ok ? 'ok' : ($warnOnly ? 'warn' : 'error'), $label, $ok ? $okText : $badText];
}

$rows = [];

/* --- Calisma ortami --- */
$rows[] = health_row(
    PHP_VERSION_ID >= 80200, false, 'PHP sürümü',
    PHP_VERSION . ' (gereken: 8.2+)',
    PHP_VERSION . ' — 8.2 veya üzeri gerekli'
);
foreach (['pdo' => 'PDO', 'pdo_mysql' => 'pdo_mysql', 'curl' => 'cURL', 'openssl' => 'OpenSSL', 'mbstring' => 'mbstring', 'json' => 'JSON'] as $ext => $label) {
    $rows[] = health_row(extension_loaded($ext), $ext === 'curl', 'PHP eklentisi: ' . $label, 'Yüklü', 'Yüklü değil');
}
$rows[] = health_row(
    defined('PASSWORD_ARGON2ID'), true, 'Argon2id şifre algoritması',
    'Destekleniyor (kullanılıyor)',
    'Desteklenmiyor — PASSWORD_DEFAULT (bcrypt) kullanılıyor, bu da güvenlidir'
);

/* --- Veritabani --- */
$dbOk = false;
$dbVersion = '—';
$charset = '—';
try {
    $dbVersion = (string)db_value('SELECT VERSION()', [], '—');
    $charset = (string)db_value('SELECT @@character_set_database', [], '—');
    $dbOk = true;
} catch (Throwable) {
    $dbOk = false;
}
$rows[] = health_row($dbOk, false, 'Veritabanı bağlantısı', 'Çalışıyor · ' . $dbVersion, 'Bağlanılamıyor');
$rows[] = health_row($charset === 'utf8mb4', false, 'Karakter seti', $charset, $charset . ' — utf8mb4 olmalı');

$expected = array_keys(almancapro_schema_statements());
$missing = [];
if ($dbOk) {
    foreach ($expected as $t) {
        if (!db_table_exists($t)) {
            $missing[] = $t;
        }
    }
}
$rows[] = health_row(
    $dbOk && $missing === [], false, 'Veritabanı şeması',
    count($expected) . ' tablo mevcut',
    'Eksik tablo: ' . implode(', ', array_slice($missing, 0, 6))
);

/* --- Icerik --- */
$counts = [];
if ($dbOk) {
    foreach (['lessons', 'modules', 'skills', 'vocabulary', 'exercises', 'grammar_topics', 'lesson_prerequisites', 'scenarios', 'error_categories'] as $t) {
        $counts[$t] = (int)db_value('SELECT COUNT(*) FROM ' . $t, [], 0);
    }
}
$levelCounts = $dbOk
    ? db_all('SELECT cefr_level, COUNT(*) AS cnt FROM lessons GROUP BY cefr_level ORDER BY cefr_level')
    : [];
$levelsPresent = array_column($levelCounts, 'cefr_level');
$rows[] = health_row(
    count(array_intersect(['A0', 'A1', 'A2', 'B1'], $levelsPresent)) === 4, false,
    'Müfredat seviyeleri',
    'A0, A1, A2, B1 içerikleri yüklü',
    'Eksik seviye: ' . implode(', ', array_diff(['A0', 'A1', 'A2', 'B1'], $levelsPresent))
);
$rows[] = health_row(($counts['exercises'] ?? 0) > 100, false, 'Alıştırma havuzu', ($counts['exercises'] ?? 0) . ' alıştırma', 'Yetersiz alıştırma');

/* --- Yazilabilir dizinler --- */
$rows[] = health_row(is_writable(__DIR__), true, 'Kök dizin yazılabilir',
    'Yazılabilir (kurulum kilidi oluşturulabilir)', 'Yazılamıyor — kurulum kilidi ve log dosyası oluşturulamaz');
$rows[] = health_row(
    true, true, 'Hata günlüğü',
    'PHP error_log kullanılıyor (Plesk > Loglar). Sırlar loglanmadan önce temizlenir.',
    'Log yazılamıyor'
);

/* --- Guvenlik --- */
$rows[] = health_row(is_https(), true, 'HTTPS',
    'Aktif — çerezler Secure bayrağıyla gönderiliyor',
    'Kapalı — canlı ortamda HTTPS zorunlu tutulmalı');
$rows[] = health_row(is_installed(), false, 'Kurulum kilidi', 'Kurulum tamamlandı', 'Kurulum tamamlanmadı');
$rows[] = health_row(!APP_DEBUG, false, 'Hata gösterimi',
    'Üretim modu — hata ayrıntıları kullanıcıya gösterilmiyor',
    'DEBUG açık — canlıda kapatın');

/* --- Dis servisler --- */
$rows[] = health_row(smtp_is_configured(), true, 'SMTP',
    'Yapılandırıldı (' . (string)setting('smtp_host', '') . ')',
    'Yapılandırılmadı — doğrulama ve şifre sıfırlama e-postaları gönderilemez');
$rows[] = health_row(telegram_is_configured(), true, 'Telegram bot',
    'Token ve kullanıcı adı tanımlı',
    'Yapılandırılmadı — Telegram hatırlatmaları devre dışı');
$rows[] = health_row(setting_bool('telegram_webhook_set'), true, 'Telegram webhook',
    'Ayarlandı', 'Ayarlanmadı');
$cronLast = (string)setting('cron_last_run', '');
$cronFresh = $cronLast !== '' && strtotime($cronLast) !== false && (time() - (int)strtotime($cronLast)) < 3600;
$rows[] = health_row($cronFresh, true, 'Cron görevi',
    'Son çalışma: ' . $cronLast,
    $cronLast === '' ? 'Hiç çalışmadı — Plesk zamanlanmış görevi ekleyin' : ('Son çalışma eski: ' . $cronLast));
$rows[] = health_row(setting_bool('ai_enabled'), true, 'AI desteği',
    'Etkin (' . (string)setting('ai_model', '') . ')',
    'Kapalı — sistem bilgi bankası ile çalışır, bu bir hata değildir');

$okCount = count(array_filter($rows, static fn(array $r): bool => $r[0] === 'ok'));
$warnCount = count(array_filter($rows, static fn(array $r): bool => $r[0] === 'warn'));
$errCount = count(array_filter($rows, static fn(array $r): bool => $r[0] === 'error'));

render_admin_start($admin, 'Sistem Durumu');
?>
<div class="stat-grid">
  <div class="card stat"><div class="stat__label">✓ Uygun</div><div class="stat__value"><?= $okCount ?></div></div>
  <div class="card stat"><div class="stat__label">● Uyarı</div><div class="stat__value"><?= $warnCount ?></div></div>
  <div class="card stat"><div class="stat__label">✕ Hata</div><div class="stat__value"><?= $errCount ?></div></div>
</div>

<section class="card">
  <h2 class="card__title">Kontroller</h2>
  <p class="small">Uyarılar sistemi durdurmaz: SMTP, Telegram, cron ve AI yapılandırılmadan da AlmancaPro çalışır.
    Hata olarak işaretlenen satırlar giderilmelidir.</p>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Durum</th><th>Kontrol</th><th>Ayrıntı</th></tr></thead>
    <tbody>
    <?php foreach ($rows as [$state, $label, $detail]): ?>
      <tr>
        <td><?php render_badge(
            $state === 'ok' ? '✓' : ($state === 'warn' ? '●' : '✕'),
            $state === 'ok' ? 'Uygun' : ($state === 'warn' ? 'Uyarı' : 'Hata'),
            $state === 'ok' ? 'success' : ($state === 'warn' ? 'warn' : 'danger')
        ); ?></td>
        <td><?= e($label) ?></td>
        <td class="small"><?= e($detail) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>

<section class="card">
  <h2 class="card__title">İçerik envanteri</h2>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Tablo</th><th>Kayıt</th></tr></thead>
    <tbody>
    <?php foreach ($counts as $t => $c): ?>
      <tr><td class="mono"><?= e($t) ?></td><td class="num"><?= $c ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if ($levelCounts !== []): ?>
    <table class="table mt-16">
      <thead><tr><th>Seviye</th><th>Ders</th></tr></thead>
      <tbody>
      <?php foreach ($levelCounts as $l): ?>
        <tr><td><?= e((string)$l['cefr_level']) ?></td><td class="num"><?= (int)$l['cnt'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="card">
  <h2 class="card__title">Sunucu bilgisi</h2>
  <table class="table">
    <tbody>
      <tr><td>PHP SAPI</td><td class="mono"><?= e(PHP_SAPI) ?></td></tr>
      <tr><td>Bellek limiti</td><td class="mono"><?= e((string)ini_get('memory_limit')) ?></td></tr>
      <tr><td>Maksimum yürütme süresi</td><td class="mono"><?= e((string)ini_get('max_execution_time')) ?> sn</td></tr>
      <tr><td>Yükleme limiti</td><td class="mono"><?= e((string)ini_get('upload_max_filesize')) ?></td></tr>
      <tr><td>Sunucu saati (UTC)</td><td class="mono"><?= e(now_utc()) ?></td></tr>
      <tr><td>Yerel saat</td><td class="mono"><?= e(local_datetime(now_utc(), 'd.m.Y H:i', (string)setting('default_timezone', APP_DEFAULT_TIMEZONE))) ?></td></tr>
      <tr><td>.htaccess</td><td><?= is_file(__DIR__ . '/.htaccess') ? '✓ Mevcut' : '● Yok (nginx ortamında normaldir)' ?></td></tr>
    </tbody>
  </table>
</section>
<?php
render_admin_end();
