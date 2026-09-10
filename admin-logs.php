<?php
/**
 * AlmancaPro - Yonetici ve guvenlik loglari.
 * Metadata icinde sir tutulmaz; goruntuleme oncesi ayrica temizlenir.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
app_boot();

$admin = require_admin();

if (is_post()) {
    csrf_require();
    if ((string)input('action', '') === 'purge') {
        $days = max(30, min(365, input_int('days', 90)));
        $n = db_exec('DELETE FROM admin_logs WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', [$days]);
        db_exec('DELETE FROM login_attempts WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', [$days]);
        db_exec('DELETE FROM admin_login_attempts WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', [$days]);
        admin_log((int)$admin['id'], 'LOGS_PURGED', 'admin_logs', null, ['days' => $days, 'deleted' => $n]);
        flash('success', $n . ' yönetici log kaydı ve eski giriş denemeleri silindi.');
        redirect('/admin-logs.php');
    }
}

$tab = (string)input('tab', 'admin');
if (!in_array($tab, ['admin', 'login', 'adminlogin', 'activity'], true)) {
    $tab = 'admin';
}
$page = max(1, input_int('page', 1));
$perPage = 40;
$offset = ($page - 1) * $perPage;
$actionFilter = trim((string)input('action_filter', ''));

$rows = [];
$total = 0;

if ($tab === 'admin') {
    $where = '';
    $params = [];
    if ($actionFilter !== '') {
        $where = ' WHERE l.action = ?';
        $params[] = $actionFilter;
    }
    $total = (int)db_value('SELECT COUNT(*) FROM admin_logs l' . $where, $params, 0);
    $rows = db_all(
        'SELECT l.*, a.username FROM admin_logs l LEFT JOIN admins a ON a.id = l.admin_id' . $where . '
         ORDER BY l.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
        $params
    );
} elseif ($tab === 'login') {
    $total = (int)db_value('SELECT COUNT(*) FROM login_attempts', [], 0);
    $rows = db_all('SELECT * FROM login_attempts ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
} elseif ($tab === 'adminlogin') {
    $total = (int)db_value('SELECT COUNT(*) FROM admin_login_attempts', [], 0);
    $rows = db_all('SELECT * FROM admin_login_attempts ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
} else {
    $total = (int)db_value('SELECT COUNT(*) FROM user_activity', [], 0);
    $rows = db_all(
        'SELECT ua.*, u.name AS user_name FROM user_activity ua LEFT JOIN users u ON u.id = ua.user_id
         ORDER BY ua.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
    );
}
$totalPages = (int)ceil($total / $perPage);

$actions = db_all('SELECT action, COUNT(*) AS cnt FROM admin_logs GROUP BY action ORDER BY cnt DESC LIMIT 40');

$base = '/admin-logs.php?tab=' . urlencode($tab) . ($actionFilter !== '' ? '&action_filter=' . urlencode($actionFilter) : '');

render_admin_start($admin, 'Loglar');
?>
<section class="card">
  <nav class="tabs" aria-label="Log türü">
    <?php foreach (['admin' => 'Yönetici işlemleri', 'adminlogin' => 'Admin girişleri', 'login' => 'Kullanıcı girişleri', 'activity' => 'Kullanıcı aktivitesi'] as $k => $label): ?>
      <a class="tab<?= $tab === $k ? ' is-active' : '' ?>" href="/admin-logs.php?tab=<?= e($k) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <p class="small">Toplam <strong><?= $total ?></strong> kayıt. Şifreler, doğrulama kodları, tokenlar ve API anahtarları
    hiçbir log tablosunda tutulmaz.</p>

  <?php if ($tab === 'admin'): ?>
    <form class="filters" method="get" action="/admin-logs.php">
      <input type="hidden" name="tab" value="admin">
      <label class="field">
        <span class="field__label">İşlem türü</span>
        <select class="select" name="action_filter" data-autosubmit>
          <option value="">Tümü</option>
          <?php foreach ($actions as $a): ?>
            <option value="<?= e((string)$a['action']) ?>"<?= $actionFilter === (string)$a['action'] ? ' selected' : '' ?>>
              <?= e((string)$a['action']) ?> (<?= (int)$a['cnt'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <noscript><button class="btn btn--sm btn--inline" type="submit">Filtrele</button></noscript>
    </form>
  <?php endif; ?>

  <?php if ($rows === []): ?>
    <?php render_empty('Kayıt yok', 'Bu log türünde henüz kayıt bulunmuyor.'); ?>
  <?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <?php if ($tab === 'admin'): ?>
      <thead><tr><th>Tarih</th><th>Yönetici</th><th>İşlem</th><th>Hedef</th><th>IP</th><th>Ayrıntı</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="num small"><?= e(local_datetime((string)$r['created_at'])) ?></td>
          <td><?= e((string)($r['username'] ?? '—')) ?></td>
          <td class="mono small"><?= e((string)$r['action']) ?></td>
          <td class="small"><?= e(trim((string)($r['target_type'] ?? '') . ' ' . (string)($r['target_id'] ?? ''))) ?: '—' ?></td>
          <td class="mono small"><?= e((string)($r['ip_address'] ?? '—')) ?></td>
          <td class="small"><?= e(str_limit(scrub_secrets((string)($r['metadata'] ?? '')), 70)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    <?php elseif ($tab === 'adminlogin' || $tab === 'login'): ?>
      <thead><tr><th>Tarih</th><th>Kimlik</th><th>IP</th><th>Sonuç</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="num small"><?= e(local_datetime((string)$r['created_at'])) ?></td>
          <td class="small"><?= e((string)($r['username'] ?? $r['identifier'] ?? $r['email'] ?? '—')) ?></td>
          <td class="mono small"><?= e((string)($r['ip_address'] ?? '—')) ?></td>
          <td><?= (int)($r['success'] ?? 0) === 1 ? '✓ Başarılı' : '✕ Başarısız' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    <?php else: ?>
      <thead><tr><th>Tarih</th><th>Kullanıcı</th><th>Etkinlik</th><th>Ayrıntı</th><th>XP</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="num small"><?= e(local_datetime((string)$r['created_at'])) ?></td>
          <td><?php if ($r['user_id'] !== null): ?><a href="/admin-user.php?id=<?= (int)$r['user_id'] ?>"><?= e((string)($r['user_name'] ?? '—')) ?></a><?php else: ?>—<?php endif; ?></td>
          <td class="mono small"><?= e((string)($r['activity_type'] ?? '—')) ?></td>
          <td class="small"><?= e(str_limit((string)($r['title'] ?? ''), 60)) ?></td>
          <td class="num"><?= (int)($r['xp'] ?? 0) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    <?php endif; ?>
  </table>
  </div>
  <?php render_pagination($page, $totalPages, $base); ?>
  <?php endif; ?>
</section>

<section class="card">
  <h2 class="card__title">Bakım</h2>
  <form method="post" action="/admin-logs.php" class="row" data-confirm="Seçilen süreden eski loglar kalıcı olarak silinsin mi?">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="purge">
    <label class="field">
      <span class="field__label" for="lg-days">Şundan eski kayıtları sil</span>
      <select class="select" id="lg-days" name="days">
        <option value="90">90 gün</option>
        <option value="180">180 gün</option>
        <option value="365">365 gün</option>
      </select>
    </label>
    <button class="btn btn--sm btn--danger btn--inline" type="submit">Temizle</button>
  </form>
</section>
<?php
render_admin_end();
