<?php
/**
 * AlmancaPro - Yonetici girisi.
 * Normal kullanici oturumu burada hicbir yetki vermez.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
app_boot();

if (current_admin_id() !== null) {
    redirect('/admin.php');
}

$error = '';
$username = '';

if (is_post()) {
    csrf_require();
    $username = trim((string)input('username', ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Kullanıcı adı ve şifre gerekli.';
    } else {
        $result = admin_attempt_login($username, $password);
        if ($result['ok'] && $result['admin'] !== null) {
            admin_login_session($result['admin']);
            redirect((int)$result['admin']['must_change_password'] === 1 ? '/admin-password.php?first=1' : '/admin.php');
        }
        $error = $result['error'];
    }
}

render_head('Yönetim Paneli · ' . APP_NAME, ['css' => ['admin.css'], 'body_class' => 'admin', 'noindex' => true]);
?>
<div class="admin-login-wrap">
  <div class="admin-login">
    <div style="margin-bottom: 26px;">
      <?php render_logo('/admin-login.php'); ?>
    </div>
    <h1 class="admin-login__title">Yönetim Paneli</h1>
    <p class="admin-login__sub">Yetkili giriş</p>

    <?php if ($error !== ''): ?>
      <div class="alert alert--error"><span class="alert__icon" aria-hidden="true">✕</span><span><?= e($error) ?></span></div>
    <?php endif; ?>
    <?php render_flashes(); ?>

    <form method="post" action="/admin-login.php" data-guard novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="username">Kullanıcı Adı</label>
        <input id="username" name="username" type="text" autocomplete="username" value="<?= e($username) ?>" required autofocus>
      </div>
      <?php render_password_field('password', 'password', 'Şifre', 'current-password', false); ?>
      <button class="btn btn--block btn--lg" type="submit">GİRİŞ YAP</button>
    </form>

    <p class="small" style="margin-top: 22px; color: var(--admin-ink-2);">
      Bu alan yalnızca site yöneticileri içindir. Giriş denemeleri kaydedilir.
    </p>
    <p class="small"><a href="/">← Siteye dön</a></p>
  </div>
</div>
<?php render_foot(); ?>
