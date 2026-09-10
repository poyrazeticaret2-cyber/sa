<?php
/**
 * AlmancaPro - Yeni sifre belirleme.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mailer.php';
app_boot();

$token = (string)input('token', '');
$reset = auth_find_password_reset($token);
$errors = [];
$done = false;

if ($reset === null) {
    render_head('Bağlantı geçersiz · ' . APP_NAME, ['css' => ['auth.css'], 'noindex' => true]);
    ?>
    <div class="auth-wrap">
      <div class="auth-card">
        <div class="auth-card__head">
          <?php render_logo('/'); ?>
          <h1 style="margin-top: 20px;">Bağlantı geçersiz</h1>
          <p class="auth-card__sub">Bu sıfırlama bağlantısı kullanılmış veya süresi dolmuş. Yeni bir bağlantı isteyebilirsin.</p>
        </div>
        <a class="btn btn--block" href="/forgot-password.php">YENİ BAĞLANTI İSTE</a>
        <div class="auth-alt"><a href="/login.php">Giriş ekranına dön</a></div>
      </div>
    </div>
    <?php
    render_foot();
    exit;
}

if (is_post()) {
    csrf_require();
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password_confirm'] ?? '');

    $problems = password_problems($password);
    if ($problems !== []) {
        $errors['password'] = implode(' ', $problems);
    }
    if ($password !== $password2) {
        $errors['password_confirm'] = 'Şifreler eşleşmiyor.';
    }

    if ($errors === []) {
        auth_consume_password_reset((int)$reset['id'], (int)$reset['user_id'], $password);
        $user = db_row('SELECT * FROM users WHERE id = ?', [(int)$reset['user_id']]);
        if ($user !== null && smtp_is_configured()) {
            mail_send_security_notice($user, 'Hesabının şifresi az önce sıfırlandı. Bütün cihazlardaki "beni hatırla" oturumları kapatıldı.');
        }
        $done = true;
    }
}

render_head('Yeni şifre belirle · ' . APP_NAME, ['css' => ['auth.css'], 'noindex' => true]);
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-card__head">
      <?php render_logo('/'); ?>
      <h1 style="margin-top: 20px;"><?= $done ? 'Şifren güncellendi' : 'Yeni şifre belirle' ?></h1>
      <p class="auth-card__sub">
        <?= $done ? 'Yeni şifrenle giriş yapabilirsin. Diğer cihazlardaki oturumlar kapatıldı.' : e((string)$reset['email']) . ' hesabı için yeni bir şifre oluştur.' ?>
      </p>
    </div>

    <?php if ($done): ?>
      <a class="btn btn--block btn--lg" href="/login.php">GİRİŞ YAP</a>
    <?php else: ?>
      <form method="post" action="/reset-password.php" data-guard novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <?php render_password_field('password', 'password', 'Yeni şifre', 'new-password', true); ?>
        <?php render_password_strength('password'); ?>
        <?php if (isset($errors['password'])): ?><div class="hint text-danger" style="margin-bottom:14px;">✕ <?= e($errors['password']) ?></div><?php endif; ?>
        <?php render_password_field('password_confirm', 'password_confirm', 'Yeni şifre tekrar', 'new-password', true); ?>
        <div class="hint" data-match-for="password" data-match-with="password_confirm" style="margin-top:-10px;margin-bottom:14px;"></div>
        <?php if (isset($errors['password_confirm'])): ?><div class="hint text-danger" style="margin-bottom:14px;">✕ <?= e($errors['password_confirm']) ?></div><?php endif; ?>
        <button class="btn btn--block btn--lg" type="submit">ŞİFREYİ GÜNCELLE</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php render_foot(); ?>
