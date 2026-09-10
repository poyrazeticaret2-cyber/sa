<?php
/**
 * AlmancaPro - Bildirim kuyrugu ve gonderim gecmisi.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/notify.php';
app_boot();

$admin = require_admin();

if (is_post()) {
    csrf_require();
    $action = (string)input('action', '');

    if ($action === 'cancel') {
        $id = input_int('id', 0);
        db_exec('UPDATE notification_queue SET status = "cancelled" WHERE id = ? AND status = "pending"', [$id]);
        admin_log((int)$admin['id'], 'NOTIFICATION_CANCELLED', 'notification_queue', (string)$id);
        flash('success', 'Bildirim iptal edildi.');
        redirect('/admin-notifications.php');
    }

    if ($action === 'retry') {
        $id = input_int('id', 0);
        db_exec('UPDATE notification_queue SET status = "pending", attempts = 0, last_error = NULL WHERE id = ? AND status IN ("failed","cancelled")', [$id]);
        admin_log((int)$admin['id'], 'NOTIFICATION_RETRY', 'notification_queue', (string)$id);
        flash('success', 'Bildirim yeniden kuyruğa alındı.');
        redirect('/admin-notifications.php');
    }

    if ($action === 'process') {
        $res = notify_process_queue(25);
        admin_log((int)$admin['id'], 'NOTIFICATION_QUEUE_PROCESSED', 'notification_queue', null, $res);
        flash('success', sprintf('Kuyruk işlendi: %d gönderildi, %d başarısız, %d atlandı.',
            (int)($res['sent'] ?? 0), (int)($res['failed'] ?? 0), (int)($res['skipped'] ?? 0)));
        redirect('/admin-notifications.php');
    }

    if ($action === 'plan') {
        $n = notify_plan_daily();
        admin_log((int)$admin['id'], 'NOTIFICATION_PLANNED', 'notification_queue', null, ['count' => $n]);
        flash('success', $n . ' bildirim planlandı.');
        redirect('/admin-notifications.php');
    }

    if ($action === 'purge') {
        $n = db_exec('DELETE FROM notification_queue WHERE status IN ("sent","cancelled") AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)');
        admin_log((int)$admin['id'], 'NOTIFICATION_PURGED', 'notification_queue', null, ['count' => $n]);
        flash('success', $n . ' eski kayıt temizlendi.');
        redirect('/admin-notifications.php');
    }
}

$status = (string)input('status', 'pending');
if (!in_array($status, ['pending', 'sending', 'sent', 'failed', 'cancelled', 'all'], true)) {
    $status = 'pending';
}
$page = max(1, input_int('page', 1));
$perPage = 30;
$where = $status === 'all' ? '' : ' WHERE q.status = ?';
$params = $status === 'all' ? [] : [$status];

$total = (int)db_value('SELECT COUNT(*) FROM notification_queue q' . $where, $params, 0);
$totalPages = (int)ceil($total / $perPage);
$rows = db_all(
    'SELECT q.*, u.name AS user_name FROM notification_queue q LEFT JOIN users u ON u.id = q.user_id' . $where . '
     ORDER BY q.scheduled_at DESC, q.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
    $params
);

$counts = db_row(
    'SELECT SUM(status="pending") AS pending, SUM(status="sent") AS sent, SUM(status="failed") AS failed,
            SUM(status="cancelled") AS cancelled,
            SUM(status="pending" AND scheduled_at <= UTC_TIMESTAMP()) AS due
     FROM notification_queue'
) ?? [];

$byTemplate = db_all(
    'SELECT template, channel, COUNT(*) AS cnt, SUM(status="sent") AS sent, SUM(status="failed") AS failed
     FROM notification_queue GROUP BY template, channel ORDER BY cnt DESC LIMIT 20'
);

$log = db_all(
    'SELECT l.*, u.name AS user_name FROM notification_log l LEFT JOIN users u ON u.id = l.user_id
     ORDER BY l.id DESC LIMIT 25'
);

$prefStats = db_row(
    'SELECT COUNT(*) AS total, SUM(telegram_enabled=1) AS tg, SUM(email_enabled=1) AS mail,
            SUM(mode="hafif") AS hafif, SUM(mode="normal") AS normal_, SUM(mode="yogun") AS yogun, SUM(mode="ozel") AS ozel
     FROM notification_preferences'
) ?? [];

render_admin_start($admin, 'Bildirimler');
?>
<div class="stat-grid">
  <div class="card stat"><div class="stat__label">Bekleyen</div><div class="stat__value"><?= (int)($counts['pending'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Zamanı gelmiş</div><div class="stat__value"><?= (int)($counts['due'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Gönderilen</div><div class="stat__value"><?= (int)($counts['sent'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Başarısız</div><div class="stat__value"><?= (int)($counts['failed'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">İptal</div><div class="stat__value"><?= (int)($counts['cancelled'] ?? 0) ?></div></div>
</div>

<section class="card">
  <h2 class="card__title">İşlemler</h2>
  <p class="small">Kuyruk normalde cron tarafından işlenir. Buradaki butonlar aynı işlemi elle tetikler ve
    aynı kilit mekanizmasını kullandığı için çift gönderim oluşturmaz.</p>
  <div class="row">
    <form method="post" action="/admin-notifications.php">
      <?= csrf_field() ?><input type="hidden" name="action" value="plan">
      <button class="btn btn--sm btn--inline" type="submit">Günlük bildirimleri planla</button>
    </form>
    <form method="post" action="/admin-notifications.php">
      <?= csrf_field() ?><input type="hidden" name="action" value="process">
      <button class="btn btn--sm btn--inline" type="submit">Kuyruğu işle (25)</button>
    </form>
    <form method="post" action="/admin-notifications.php" data-confirm="30 günden eski gönderilmiş/iptal kayıtlar silinsin mi?">
      <?= csrf_field() ?><input type="hidden" name="action" value="purge">
      <button class="btn btn--sm btn--secondary btn--inline" type="submit">Eski kayıtları temizle</button>
    </form>
  </div>
  <p class="small mt-8">Son cron çalışması: <strong><?= e((string)setting('cron_last_run', '') ?: 'Hiç çalışmadı') ?></strong>
    <?php if ((string)setting('cron_last_summary', '') !== ''): ?><br><?= e((string)setting('cron_last_summary', '')) ?><?php endif; ?></p>
</section>

<div class="grid grid--2">
  <section class="card">
    <h2 class="card__title">Kullanıcı tercihleri</h2>
    <table class="table">
      <tbody>
        <tr><td>Tercih kaydı olan kullanıcı</td><td class="num"><?= (int)($prefStats['total'] ?? 0) ?></td></tr>
        <tr><td>Telegram bildirimi açık</td><td class="num"><?= (int)($prefStats['tg'] ?? 0) ?></td></tr>
        <tr><td>E-posta bildirimi açık</td><td class="num"><?= (int)($prefStats['mail'] ?? 0) ?></td></tr>
        <tr><td>Hafif / Normal / Yoğun / Özel</td>
            <td class="num"><?= (int)($prefStats['hafif'] ?? 0) ?> / <?= (int)($prefStats['normal_'] ?? 0) ?> / <?= (int)($prefStats['yogun'] ?? 0) ?> / <?= (int)($prefStats['ozel'] ?? 0) ?></td></tr>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h2 class="card__title">Şablon dağılımı</h2>
    <?php if ($byTemplate === []): ?>
      <?php render_empty('Kayıt yok', 'Henüz bildirim üretilmedi.'); ?>
    <?php else: ?>
    <table class="table">
      <thead><tr><th>Şablon</th><th>Kanal</th><th>Toplam</th><th>Gönderilen</th><th>Hata</th></tr></thead>
      <tbody>
      <?php foreach ($byTemplate as $t): ?>
        <tr>
          <td class="small"><?= e((string)$t['template']) ?></td>
          <td><?= e((string)$t['channel']) ?></td>
          <td class="num"><?= (int)$t['cnt'] ?></td>
          <td class="num"><?= (int)$t['sent'] ?></td>
          <td class="num"><?= (int)$t['failed'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
</div>

<section class="card">
  <h2 class="card__title">Kuyruk</h2>
  <nav class="tabs" aria-label="Durum filtresi">
    <?php foreach (['pending' => 'Bekleyen', 'sent' => 'Gönderilen', 'failed' => 'Başarısız', 'cancelled' => 'İptal', 'all' => 'Tümü'] as $k => $label): ?>
      <a class="tab<?= $status === $k ? ' is-active' : '' ?>" href="/admin-notifications.php?status=<?= e($k) ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if ($rows === []): ?>
    <?php render_empty('Kayıt yok', 'Bu durumda bildirim bulunmuyor.'); ?>
  <?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>#</th><th>Kullanıcı</th><th>Kanal</th><th>Şablon</th><th>Planlanan</th><th>Durum</th><th>Deneme</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="num"><?= (int)$r['id'] ?></td>
        <td><?php if ($r['user_id'] !== null): ?><a href="/admin-user.php?id=<?= (int)$r['user_id'] ?>"><?= e((string)($r['user_name'] ?? '—')) ?></a><?php else: ?>—<?php endif; ?></td>
        <td><?= e((string)$r['channel']) ?></td>
        <td class="small"><?= e((string)$r['template']) ?></td>
        <td class="num small"><?= e(local_datetime((string)$r['scheduled_at'])) ?></td>
        <td><?= e((string)$r['status']) ?><?php if (!empty($r['last_error'])): ?><br><span class="small"><?= e(str_limit((string)$r['last_error'], 40)) ?></span><?php endif; ?></td>
        <td class="num"><?= (int)$r['attempts'] ?></td>
        <td>
          <?php if ((string)$r['status'] === 'pending'): ?>
            <form method="post" action="/admin-notifications.php">
              <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn--sm btn--secondary btn--inline" type="submit">İptal</button>
            </form>
          <?php elseif (in_array((string)$r['status'], ['failed', 'cancelled'], true)): ?>
            <form method="post" action="/admin-notifications.php">
              <?= csrf_field() ?><input type="hidden" name="action" value="retry"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn--sm btn--secondary btn--inline" type="submit">Tekrar</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php render_pagination($page, $totalPages, '/admin-notifications.php?status=' . urlencode($status)); ?>
  <?php endif; ?>
</section>

<section class="card">
  <h2 class="card__title">Gönderim geçmişi</h2>
  <?php if ($log === []): ?>
    <?php render_empty('Kayıt yok', 'Henüz bildirim gönderilmedi.'); ?>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Tarih</th><th>Kullanıcı</th><th>Kanal</th><th>Şablon</th><th>Sonuç</th></tr></thead>
    <tbody>
    <?php foreach ($log as $l): ?>
      <tr>
        <td class="num small"><?= e(local_datetime((string)$l['created_at'])) ?></td>
        <td><?= e((string)($l['user_name'] ?? '—')) ?></td>
        <td><?= e((string)$l['channel']) ?></td>
        <td class="small"><?= e((string)$l['template']) ?></td>
        <td><?= (string)$l['status'] === 'sent' ? '✓ Gönderildi' : '✕ Hata: ' . e(str_limit((string)($l['error'] ?? ''), 50)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>
<?php
render_admin_end();
