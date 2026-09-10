<?php
/**
 * AlmancaPro - Kullanici yonetimi.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
app_boot();

$admin = require_admin();

if (is_post()) {
    csrf_require();
    $action = (string)input('action', '');
    $userId = input_int('user_id', 0);
    $user = $userId > 0 ? db_row('SELECT * FROM users WHERE id = ?', [$userId]) : null;

    if ($user !== null) {
        if ($action === 'disable') {
            db_exec('UPDATE users SET is_active = 0 WHERE id = ?', [$userId]);
            db_exec('DELETE FROM remember_tokens WHERE user_id = ?', [$userId]);
            admin_log((int)$admin['id'], 'USER_DISABLED', 'user', (string)$userId);
            flash('success', 'Kullanıcı devre dışı bırakıldı.');
        } elseif ($action === 'enable') {
            db_exec('UPDATE users SET is_active = 1 WHERE id = ?', [$userId]);
            admin_log((int)$admin['id'], 'USER_ENABLED', 'user', (string)$userId);
            flash('success', 'Kullanıcı yeniden etkinleştirildi.');
        } elseif ($action === 'verify') {
            db_exec('UPDATE users SET is_verified = 1 WHERE id = ?', [$userId]);
            admin_log((int)$admin['id'], 'USER_VERIFIED', 'user', (string)$userId);
            flash('success', 'Kullanıcı e-postası doğrulanmış olarak işaretlendi.');
        }
    }
    redirect('/admin-users.php?' . http_build_query(array_filter([
        'q' => input('q'), 'filter' => input('filter'), 'level' => input('level'), 'page' => input_int('page', 1),
    ])));
}

$q = trim((string)input('q', ''));
$filter = (string)input('filter', '');
$level = (string)input('level', '');
$page = max(1, input_int('page', 1));
$perPage = 25;

$where = ['1=1'];
$params = [];
if ($q !== '') {
    if (ctype_digit($q)) {
        $where[] = '(u.id = ? OR u.name LIKE ? OR u.email LIKE ?)';
        $params[] = (int)$q;
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    } else {
        $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
}
switch ($filter) {
    case 'verified':   $where[] = 'u.is_verified = 1'; break;
    case 'unverified': $where[] = 'u.is_verified = 0'; break;
    case 'active':     $where[] = 'u.is_active = 1'; break;
    case 'inactive':   $where[] = 'u.is_active = 0'; break;
    case 'telegram':   $where[] = 'tc.id IS NOT NULL'; break;
    case 'recent':     $where[] = 'u.last_login_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)'; break;
}
if (in_array($level, cefr_levels(), true)) {
    $where[] = 'u.cefr_level = ?';
    $params[] = $level;
}
$whereSql = implode(' AND ', $where);

$total = (int)db_value(
    'SELECT COUNT(*) FROM users u LEFT JOIN telegram_connections tc ON tc.user_id = u.id AND tc.is_active = 1 WHERE ' . $whereSql,
    $params, 0
);
$totalPages = (int)max(1, ceil($total / $perPage));
$page = min($page, $totalPages);

$listParams = $params;
$listParams[] = $perPage;
$listParams[] = ($page - 1) * $perPage;
$users = db_all(
    'SELECT u.*, tc.id AS telegram_id,
            (SELECT COUNT(*) FROM user_lesson_progress p WHERE p.user_id = u.id AND p.status = "completed") lessons_done,
            (SELECT COUNT(*) FROM user_vocabulary_mastery m WHERE m.user_id = u.id AND m.status = "mastered") vocab_mastered
     FROM users u
     LEFT JOIN telegram_connections tc ON tc.user_id = u.id AND tc.is_active = 1
     WHERE ' . $whereSql . '
     ORDER BY u.id DESC LIMIT ? OFFSET ?',
    $listParams
);

$baseUrl = '/admin-users.php?q=' . urlencode($q) . '&filter=' . urlencode($filter) . '&level=' . urlencode($level);

render_admin_start($admin, 'Kullanıcılar');
?>
<h1>Kullanıcılar</h1>
<p style="color: var(--admin-ink-2);">Toplam <span class="mono"><?= $total ?></span> kayıt.
  Yöneticiler kullanıcı şifrelerini göremez; şifreler geri döndürülemez biçimde saklanır.</p>

<form method="get" action="/admin-users.php" class="filter-bar" style="margin: 18px 0;">
  <label class="sr-only" for="q">Ara</label>
  <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="ID, ad veya e-posta" style="max-width: 260px;">
  <label class="sr-only" for="filter">Filtre</label>
  <select id="filter" name="filter" style="max-width: 200px;">
    <?php foreach (['' => 'Tüm kullanıcılar', 'verified' => 'Doğrulanmış', 'unverified' => 'Doğrulanmamış', 'active' => 'Aktif', 'inactive' => 'Devre dışı', 'telegram' => 'Telegram bağlı', 'recent' => 'Son 7 günde giriş'] as $k => $label): ?>
      <option value="<?= e($k) ?>"<?= $filter === $k ? ' selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="sr-only" for="level">Seviye</label>
  <select id="level" name="level" style="max-width: 140px;">
    <option value="">Tüm seviyeler</option>
    <?php foreach (cefr_levels() as $lv): ?>
      <option value="<?= e($lv) ?>"<?= $level === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn--sm btn--inline" type="submit">FİLTRELE</button>
  <a class="btn btn--sm btn--secondary btn--inline" href="/admin-users.php">TEMİZLE</a>
</form>

<div class="table-wrap">
  <table class="data">
    <thead>
      <tr>
        <th>ID</th><th>Ad</th><th>E-posta</th><th>Seviye</th><th>Ders</th><th>Kelime</th>
        <th>Telegram</th><th>Durum</th><th>Son giriş</th><th>İşlem</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td data-label="ID" class="mono"><?= (int)$u['id'] ?></td>
          <td data-label="Ad"><a href="/admin-user.php?id=<?= (int)$u['id'] ?>"><?= e((string)$u['name']) ?></a></td>
          <td data-label="E-posta" class="small"><?= e((string)$u['email']) ?></td>
          <td data-label="Seviye" class="mono"><?= e((string)$u['cefr_level']) ?></td>
          <td data-label="Ders" class="mono"><?= (int)$u['lessons_done'] ?></td>
          <td data-label="Kelime" class="mono"><?= (int)$u['vocab_mastered'] ?></td>
          <td data-label="Telegram"><?= $u['telegram_id'] !== null ? '✓' : '—' ?></td>
          <td data-label="Durum">
            <?php if ((int)$u['is_active'] !== 1): ?>
              <?php render_badge('✕', 'DEVRE DIŞI', 'bad'); ?>
            <?php elseif ((int)$u['is_verified'] !== 1): ?>
              <?php render_badge('○', 'DOĞRULANMADI', 'muted'); ?>
            <?php else: ?>
              <?php render_badge('✓', 'AKTİF', 'ok'); ?>
            <?php endif; ?>
          </td>
          <td data-label="Son giriş" class="mono small"><?= e(local_datetime((string)($u['last_login_at'] ?? ''), 'd.m.y H:i')) ?></td>
          <td data-label="İşlem">
            <form method="post" action="/admin-users.php" style="display: inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <input type="hidden" name="q" value="<?= e($q) ?>">
              <input type="hidden" name="filter" value="<?= e($filter) ?>">
              <input type="hidden" name="level" value="<?= e($level) ?>">
              <input type="hidden" name="page" value="<?= $page ?>">
              <?php if ((int)$u['is_active'] === 1): ?>
                <button class="btn btn--sm btn--danger btn--inline" type="submit" name="action" value="disable"
                        data-confirm="Kullanıcı devre dışı bırakılsın mı?">DEVRE DIŞI</button>
              <?php else: ?>
                <button class="btn btn--sm btn--secondary btn--inline" type="submit" name="action" value="enable">ETKİNLEŞTİR</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($users === []): ?>
        <tr><td colspan="10">Kayıt bulunamadı.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php render_pagination($page, $totalPages, $baseUrl); ?>
<?php render_admin_end(); ?>
