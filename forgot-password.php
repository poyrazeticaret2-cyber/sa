<?php
/**
 * AlmancaPro - Şifre sıfırlama istegi.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mailer.php';
app_boot();

if (is_logged_in()) {
    redirect('/dashboard.php');
}

$sent = false;
$error = '';
$email = '';

if (is_post()) {
    csrf_require();
    $email = mb_strtolower((string)input('email', ''));

    if (!rate_limit_hit('reset_ip', client_ip(), 10, 3600)) {
        $error = 'Çok fazla istek gönderildi. Lütfen bir saat sonra tekrar dene.';
    } elseif (!valid_email($email)) {
        $error = 'Geçerli bir e-posta adresi gir.';
    } else {
        /* Kullanici sayimi yapilmaz: sonuc her durumda ayni gorunur. */
        $user = auth_find_user_by_email($email);
        if ($user !== null && (int)$user['is_active'] === 1) {
            if (rate_limit_hit('reset_user', $email, 5, 3600)) {
                $token = auth_create_password_reset((int)$user['id']);
                $mail = mail_send_password_reset($user, $token);
                if (!$mail['ok']) {
                    app_log('Şifre sıfırlama e-postasi gonderilemedi: ' . $mail['error']);
                }
            }
        }
        $sent = true;
    }
}

render_head('Şifremi unuttum · ' . APP_NAME, ['css' => ['auth.css'], 'noindex' => true]);
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-card__head">
      <?php render_logo('/'); ?>
      <h1 style="margin-top: 20px;">Şifreni sıfırla</h1>
      <p class="auth-card__sub">E-posta adresini gir, sana sıfırlama bağlantısı gönderelim.</p>
    </div>

    <?php if ($sent): ?>
      <div class="alert alert--success"><span class="alert__icon" aria-hidden="true">✓</span>
        <span>Bu adrese kayıtlı bir hesap varsa sıfırlama bağlantısı gönderildi. Gelen kutunu ve spam klasörünü kontrol et. Bağlantı 60 dakika geçerlidir.</span></div>
      <?php if (!smtp_is_configured()): ?>
        <div class="alert alert--warn"><span class="alert__icon" aria-hidden="true">●</span>
          <span>E-posta gönderimi henüz yapılandırılmadı. Yönetici SMTP ayarlarını tamamladığında bağlantı gönderilecek.</span></div>
      <?php endif; ?>
      <a class="btn btn--block btn--secondary" href="/login.php">GİRİŞ EKRANINA DÖN</a>
    <?php else: ?>
      <?php if ($error !== ''): ?>
        <div class="alert alert--error"><span class="alert__icon" aria-hidden="true">✕</span><span><?= e($error) ?></span></div>
      <?php endif; ?>
      <form method="post" action="/forgot-password.php" data-guard novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="email">E-posta</label>
          <input id="email" name="email" type="email" autocomplete="email" inputmode="email" value="<?= e($email) ?>" required autofocus>
        </div>
        <button class="btn btn--block btn--lg" type="submit">SIFIRLAMA BAĞLANTISI GÖNDER</button>
      </form>
      <div class="auth-alt"><a href="/login.php">Giriş ekranına dön</a></div>
    <?php endif; ?>
  </div>
</div>
<?php render_foot(); ?>
