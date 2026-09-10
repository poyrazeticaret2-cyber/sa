<?php
/**
 * AlmancaPro - Yonetim paneli ana ekrani.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/telegram-api.php';
require_once __DIR__ . '/mailer.php';
app_boot();

$admin = require_admin();

$stats = db_row(
    'SELECT
        (SELECT COUNT(*) FROM users) users_total,
        (SELECT COUNT(*) FROM users WHERE is_verified = 1) users_verified,
        (SELECT COUNT(*) FROM users WHERE DATE(created_at) = UTC_DATE()) users_today,
        (SELECT COUNT(*) FROM users WHERE last_login_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)) users_active,
        (SELECT COUNT(*) FROM telegram_connections WHERE is_active = 1) telegram_linked,
        (SELECT COUNT(*) FROM lessons WHERE is_active = 1) lessons,
        (SELECT COUNT(*) FROM skills WHERE is_active = 1) skills,
        (SELECT COUNT(*) FROM vocabulary WHERE is_active = 1) vocabulary,
        (SELECT COUNT(*) FROM exercises WHERE is_active = 1) exercises,
        (SELECT COUNT(*) FROM exercise_attempts WHERE DATE(created_at) = UTC_DATE()) attempts_today,
        (SELECT COUNT(*) FROM study_sessions WHERE DATE(started_at) = UTC_DATE()) sessions_today,
        (SELECT COUNT(*) FROM questions WHERE status = "open") open_questions,
        (SELECT COUNT(*) FROM donations WHERE status = "beklemede") pending_donations,
        (SELECT COUNT(*) FROM notification_queue WHERE status = "pending") queue_pending,
        (SELECT COUNT(*) FROM notification_queue WHERE status = "failed") queue_failed'
) ?? [];

$dau = db_all(
    'SELECT DATE(created_at) d, COUNT(DISTINCT user_id) c
     FROM exercise_attempts WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 14 DAY)
     GROUP BY DATE(created_at) ORDER BY d'
);
$dauMap = [];
foreach ($dau as $row) {
    $dauMap[(string)$row['d']] = (int)$row['c'];
}
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $date = gmdate('Y-m-d', strtotime('-' . $i . ' days'));
    $days[] = ['date' => $date, 'count' => $dauMap[$date] ?? 0];
}
$maxDau = max(1, max(array_column($days, 'count')));

$recentUsers = db_all('SELECT id, name, email, cefr_level, is_verified, created_at FROM users ORDER BY id DESC LIMIT 8');

$cronLast = (string)setting('cron_last_run', '');
$cronStale = $cronLast === '' || strtotime($cronLast) < strtotime('-2 days');

$health = [
    ['PHP sürümü', PHP_VERSION, version_compare(PHP_VERSION, '8.2.0', '>=')],
    ['Veritabanı', 'bağlı', true],
    ['SMTP', smtp_is_configured() ? 'yapılandırıldı' : 'yapılandırılmadı', smtp_is_configured()],
    ['Telegram botu', telegram_is_configured() ? 'yapılandırıldı' : 'yapılandırılmadı', telegram_is_configured()],
    ['Telegram webhook', setting_bool('telegram_webhook_set') ? 'kurulu' : 'kurulu değil', setting_bool('telegram_webhook_set')],
    ['AI öğretmen', setting_bool('ai_enabled') ? 'açık' : 'kapalı (isteğe bağlı)', true],
    ['Cron', $cronLast !== '' ? local_datetime($cronLast, 'd.m.Y H:i') : 'hiç çalışmadı', !$cronStale],
    ['HTTPS', is_https() ? 'aktif' : 'aktif değil', is_https()],
];

render_admin_start($admin, 'Dashboard');
?>
<h1>Dashboard</h1>

<?php if ($cronStale): ?>
  <div class="alert alert--warn"><span class="alert__icon" aria-hidden="true">●</span>
    <span>Cron son 2 gündür çalışmamış görünüyor. Bildirimler ve günlük planlar gecikebilir.
      <a href="/admin-health.php">Kurulum talimatı</a>.</span></div>
<?php endif; ?>

<div class="grid grid-4" style="margin-bottom: 20px;">
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)($stats['users_total'] ?? 0) ?></div><div class="admin-stat__label">Toplam kullanıcı</div></div>
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)($stats['users_verified'] ?? 0) ?></div><div class="admin-stat__label">Doğrulanmış</div></div>
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)($stats['users_today'] ?? 0) ?></div><div class="admin-stat__label">Bugün kayıt</div></div>
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)($stats['users_active'] ?? 0) ?></div><div class="admin-stat__label">Son 7 günde aktif</div></div>
</div>

<div class="grid grid-4" style="margin-bottom: 20px;">
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)($stats['telegram_linked'] ?? 0) ?></div><div class="admin-stat__label">Telegram bağlı</div></div>
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)($stats['attempts_today'] ?? 0) ?></div><div class="admin-stat__label">Bugün cevaplanan soru</div></div>
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)($stats['sessions_today'] ?? 0) ?></div><div class="admin-stat__label">Bugün başlayan oturum</div></div>
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)($stats['open_questions'] ?? 0) ?></div><div class="admin-stat__label">Cevapsız soru</div></div>
</div>

<div class="grid grid-4" style="margin-bottom: 24px;">
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)($stats['lessons'] ?? 0) ?></div><div class="admin-stat__label">Ders</div></div>
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)($stats['skills'] ?? 0) ?></div><div class="admin-stat__label">Skill</div></div>
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)($stats['vocabulary'] ?? 0) ?></div><div class="admin-stat__label">Kelime</div></div>
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)($stats['exercises'] ?? 0) ?></div><div class="admin-stat__label">Alıştırma</div></div>
</div>

<div class="grid grid-2">
  <section class="admin-card">
    <p class="eyebrow">Günlük aktif kullanıcı (14 gün)</p>
    <div style="display: flex; gap: 4px; align-items: flex-end; height: 110px; margin-top: 14px;">
      <?php foreach ($days as $d):
          $h = max(2, (int)round(($d['count'] / $maxDau) * 100)); ?>
        <div style="flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; gap: 5px;">
          <span class="mono" style="font-size: 10px; color: var(--admin-ink-2);"><?= $d['count'] > 0 ? $d['count'] : '' ?></span>
          <span style="width: 100%; height: <?= $h ?>px; background: <?= $d['count'] > 0 ? 'var(--brass)' : 'var(--admin-surface-2)' ?>;"
                title="<?= e($d['date']) ?>: <?= $d['count'] ?> kullanıcı"></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="admin-card">
    <p class="eyebrow">Sistem durumu</p>
    <?php foreach ($health as [$name, $value, $ok]): ?>
      <div class="health-row">
        <span class="health-row__name"><?= e($name) ?></span>
        <span class="health-row__detail"><?= e($value) ?></span>
        <?php render_badge($ok ? '✓' : '●', $ok ? 'OK' : 'UYARI', $ok ? 'ok' : 'warn'); ?>
      </div>
    <?php endforeach; ?>
    <div style="margin-top: 14px;">
      <a class="btn btn--sm btn--secondary btn--inline" href="/admin-health.php">AYRINTILI DURUM</a>
    </div>
  </section>
</div>

<div class="grid grid-2" style="margin-top: 20px;">
  <section class="admin-card">
    <p class="eyebrow">Bekleyen işler</p>
    <div class="health-row">
      <span class="health-row__name">Cevapsız kullanıcı sorusu</span>
      <span class="health-row__detail"><?= (int)($stats['open_questions'] ?? 0) ?></span>
      <a class="btn btn--sm btn--secondary btn--inline" href="/admin-questions.php">AÇ</a>
    </div>
    <div class="health-row">
      <span class="health-row__name">Bekleyen bağış bildirimi</span>
      <span class="health-row__detail"><?= (int)($stats['pending_donations'] ?? 0) ?></span>
      <a class="btn btn--sm btn--secondary btn--inline" href="/admin-donations.php">AÇ</a>
    </div>
    <div class="health-row">
      <span class="health-row__name">Bildirim kuyruğu (bekleyen)</span>
      <span class="health-row__detail"><?= (int)($stats['queue_pending'] ?? 0) ?></span>
      <a class="btn btn--sm btn--secondary btn--inline" href="/admin-notifications.php">AÇ</a>
    </div>
    <div class="health-row">
      <span class="health-row__name">Başarısız bildirim</span>
      <span class="health-row__detail"><?= (int)($stats['queue_failed'] ?? 0) ?></span>
      <a class="btn btn--sm btn--secondary btn--inline" href="/admin-notifications.php">AÇ</a>
    </div>
  </section>

  <section class="admin-card">
    <p class="eyebrow">Son kayıtlar</p>
    <div class="table-wrap" style="border: 0;">
      <table class="data">
        <thead><tr><th>Ad</th><th>Seviye</th><th>Durum</th><th>Tarih</th></tr></thead>
        <tbody>
          <?php foreach ($recentUsers as $u): ?>
            <tr>
              <td data-label="Ad"><a href="/admin-user.php?id=<?= (int)$u['id'] ?>"><?= e((string)$u['name']) ?></a></td>
              <td data-label="Seviye" class="mono"><?= e((string)$u['cefr_level']) ?></td>
              <td data-label="Durum"><?php render_badge((int)$u['is_verified'] === 1 ? '✓' : '○', (int)$u['is_verified'] === 1 ? 'DOĞRULANDI' : 'BEKLİYOR', (int)$u['is_verified'] === 1 ? 'ok' : 'muted'); ?></td>
              <td data-label="Tarih" class="mono small"><?= e(local_datetime((string)$u['created_at'], 'd.m.Y H:i')) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($recentUsers === []): ?>
            <tr><td colspan="4">Henüz kullanıcı yok.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php render_admin_end(); ?>
