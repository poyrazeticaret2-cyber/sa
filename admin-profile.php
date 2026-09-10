<?php
/**
 * AlmancaPro - Yonetici profili.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
app_boot();

$admin = require_admin();
$errors = [];

if (is_post()) {
    csrf_require();
    $displayName = trim((string)input('display_name', ''));
    if (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 80) {
        $errors['display_name'] = 'Görünen ad 2-80 karakter olmalı.';
    }
    if ($errors === []) {
        db_exec('UPDATE admins SET display_name = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?', [$displayName, (int)$admin['id']]);
        admin_log((int)$admin['id'], 'ADMIN_PROFILE_UPDATED', 'admin', (string)$admin['id']);
        flash('success', 'Profil güncellendi.');
        redirect('/admin-profile.php');
    }
}

$admin = current_admin(true) ?? $admin;
$recentLogins = db_all(
    'SELECT * FROM admin_login_attempts WHERE username = ? ORDER BY id DESC LIMIT 10',
    [(string)$admin['username']]
);
$recentActions = db_all(
    'SELECT * FROM admin_logs WHERE admin_id = ? ORDER BY id DESC LIMIT 15',
    [(int)$admin['id']]
);

render_admin_start($admin, 'Profil');
?>
<section class="card" style="max-width:640px">
  <h2 class="card__title">Hesap</h2>
  <form method="post" action="/admin-profile.php" class="stack" data-guard>
    <?= csrf_field() ?>
    <label class="field">
      <span class="field__label" for="ap-name">Görünen ad</span>
      <input class="input<?= isset($errors['display_name']) ? ' is-invalid' : '' ?>" id="ap-name" name="display_name"
        value="<?= e((string)$admin['display_name']) ?>" required>
      <?php if (isset($errors['display_name'])): ?><span class="field__error">✕ <?= e($errors['display_name']) ?></span><?php endif; ?>
    </label>
    <p class="small">Kullanıcı adı güvenlik gereği panelden değiştirilemez.</p>
    <div class="row"><button class="btn btn--inline" type="submit">Kaydet</button></div>
  </form>

  <table class="table mt-16">
    <tbody>
      <tr><td>Kullanıcı adı</td><td class="mono"><?= e((string)$admin['username']) ?></td></tr>
      <tr><td>Durum</td><td><?= (int)$admin['is_active'] === 1 ? '✓ Aktif' : '✕ Pasif' ?></td></tr>
      <tr><td>Son giriş</td><td class="mono"><?= e($admin['last_login_at'] !== null ? local_datetime((string)$admin['last_login_at']) : '—') ?></td></tr>
      <tr><td>Son giriş IP</td><td class="mono"><?= e((string)($admin['last_login_ip'] ?? '—')) ?></td></tr>
      <tr><td>Şifre değişim tarihi</td><td class="mono"><?= e($admin['password_changed_at'] !== null ? local_datetime((string)$admin['password_changed_at']) : '—') ?></td></tr>
      <tr><td>Başarısız giriş sayacı</td><td class="num"><?= (int)$admin['failed_login_attempts'] ?></td></tr>
    </tbody>
  </table>
  <div class="row mt-16">
    <a class="btn btn--sm btn--inline" href="/admin-password.php">Şifre Değiştir</a>
  </div>
</section>

<div class="grid grid--2">
  <section class="card">
    <h2 class="card__title">Son giriş denemeleri</h2>
    <?php if ($recentLogins === []): ?>
      <?php render_empty('Kayıt yok', 'Giriş denemesi bulunmuyor.'); ?>
    <?php else: ?>
    <table class="table">
      <thead><tr><th>Tarih</th><th>IP</th><th>Sonuç</th></tr></thead>
      <tbody>
      <?php foreach ($recentLogins as $l): ?>
        <tr>
          <td class="num small"><?= e(local_datetime((string)$l['created_at'])) ?></td>
          <td class="mono small"><?= e((string)$l['ip_address']) ?></td>
          <td><?= (int)$l['success'] === 1 ? '✓ Başarılı' : '✕ Başarısız' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2 class="card__title">Son işlemleriniz</h2>
    <?php if ($recentActions === []): ?>
      <?php render_empty('Kayıt yok', 'Henüz işlem yapmadınız.'); ?>
    <?php else: ?>
    <table class="table">
      <thead><tr><th>Tarih</th><th>İşlem</th><th>Hedef</th></tr></thead>
      <tbody>
      <?php foreach ($recentActions as $a): ?>
        <tr>
          <td class="num small"><?= e(local_datetime((string)$a['created_at'])) ?></td>
          <td class="mono small"><?= e((string)$a['action']) ?></td>
          <td class="small"><?= e(trim((string)($a['target_type'] ?? '') . ' ' . (string)($a['target_id'] ?? ''))) ?: '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
</div>
<?php
render_admin_end();
