<?php
/**
 * AlmancaPro - E-posta dogrulama.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mailer.php';
app_boot();

$userId = null;
$current = current_user();
if ($current !== null && (int)$current['is_verified'] === 1) {
    redirect('/onboarding.php');
}
if ($current !== null) {
    $userId = (int)$current['id'];
} elseif (!empty($_SESSION['pending_verify_user'])) {
    $userId = (int)$_SESSION['pending_verify_user'];
}

if ($userId === null) {
    flash('info', 'Doğrulama için önce giriş yap veya kayıt ol.');
    redirect('/login.php');
}

$user = db_row('SELECT * FROM users WHERE id = ?', [$userId]);
if ($user === null) {
    unset($_SESSION['pending_verify_user']);
    redirect('/register.php');
}
if ((int)$user['is_verified'] === 1) {
    unset($_SESSION['pending_verify_user']);
    auth_login_user($user);
    redirect('/onboarding.php');
}

$error = '';
$notice = '';
$cooldown = max(0, 60 - auth_verification_last_sent_seconds($userId));

if (is_post()) {
    csrf_require();
    $action = (string)input('action', 'verify');

    if ($action === 'resend') {
        if (!rate_limit_hit('verify_resend', (string)$userId, 5, 3600)) {
            $error = 'Çok fazla kod istedin. Lütfen bir saat sonra tekrar dene.';
        } elseif (auth_verification_last_sent_seconds($userId) < 60) {
            $error = 'Yeni kod istemek için biraz beklemelisin.';
        } else {
            $code = auth_create_verification_code($userId);
            $mail = mail_send_verification($user, $code);
            if ($mail['ok']) {
                $notice = 'Yeni kod gönderildi. Gelen kutunu ve spam klasörünü kontrol et.';
            } else {
                app_log('Dogrulama kodu gonderilemedi: ' . $mail['error']);
                $error = smtp_is_configured()
                    ? 'Kod gönderilemedi. Lütfen birazdan tekrar dene.'
                    : 'E-posta gönderimi henüz yapılandırılmadı. Yönetici SMTP ayarlarını tamamladığında kod gönderilecek.';
            }
            $cooldown = 60;
        }
    } else {
        if (!rate_limit_hit('verify_attempt', (string)$userId . '|' . client_ip(), 20, 900)) {
            $error = 'Çok fazla deneme yapıldı. Lütfen 15 dakika sonra tekrar dene.';
        } else {
            $result = auth_check_verification_code($userId, (string)input('code', ''));
            if ($result['ok']) {
                $fresh = db_row('SELECT * FROM users WHERE id = ?', [$userId]);
                unset($_SESSION['pending_verify_user']);
                auth_login_user($fresh ?? $user);
                award_activity_safe($userId);
                flash('success', 'E-posta adresin doğrulandı. Şimdi seni tanıyalım.');
                redirect('/onboarding.php');
            }
            $error = $result['error'];
        }
    }
    $cooldown = max($cooldown, max(0, 60 - auth_verification_last_sent_seconds($userId)));
}

/** Kayit sonrasi ilk etkinlik kaydi. */
function award_activity_safe(int $userId): void
{
    try {
        require_once __DIR__ . '/learning.php';
        award_activity($userId, 'account_verified', null, 'E-posta doğrulandı', 0, 0);
    } catch (Throwable $e) {
        /* yoksay */
    }
}

$masked = preg_replace('/^(.).*(@.*)$/u', '$1***$2', (string)$user['email']);

render_head('E-postanı doğrula · ' . APP_NAME, ['css' => ['auth.css'], 'noindex' => true]);
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-card__head">
      <?php render_logo('/'); ?>
      <div class="auth-steps" aria-hidden="true" style="margin-top: 20px;">
        <span class="auth-step is-done"></span><span class="auth-step is-active"></span><span class="auth-step"></span>
      </div>
      <h1>E-postanı doğrula</h1>
      <p class="auth-card__sub">
        <strong><?= e((string)$masked) ?></strong> adresine gönderilen 6 haneli kodu gir.
        Kod 10 dakika geçerlidir.
      </p>
    </div>

    <?php render_flashes(); ?>
    <?php if ($notice !== ''): ?>
      <div class="alert alert--success"><span class="alert__icon" aria-hidden="true">✓</span><span><?= e($notice) ?></span></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="alert alert--error"><span class="alert__icon" aria-hidden="true">✕</span><span><?= e($error) ?></span></div>
    <?php endif; ?>
    <?php if (!smtp_is_configured()): ?>
      <div class="alert alert--warn"><span class="alert__icon" aria-hidden="true">●</span>
        <span>E-posta gönderimi henüz yapılandırılmadı. Yönetici SMTP ayarlarını tamamladığında doğrulama kodun gönderilecek.</span></div>
    <?php endif; ?>

    <form method="post" action="/verify.php" data-guard novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="verify">
      <div class="field">
        <label for="code">Doğrulama kodu</label>
        <input id="code" name="code" class="otp-input" type="text" inputmode="numeric" pattern="[0-9]{6}"
               maxlength="6" autocomplete="one-time-code" required autofocus>
      </div>
      <button class="btn btn--block btn--lg" type="submit">DOĞRULA</button>
    </form>

    <form method="post" action="/verify.php" class="resend-row">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="resend">
      <span class="small muted">Kod gelmedi mi?</span>
      <button class="btn btn--sm btn--secondary btn--inline" type="submit"<?= $cooldown > 0 ? ' disabled' : '' ?>>
        <?= $cooldown > 0 ? 'TEKRAR GÖNDER (' . (int)$cooldown . ' sn)' : 'TEKRAR GÖNDER' ?>
      </button>
    </form>

    <div class="auth-alt">
      Yanlış hesap mı? <a href="/logout.php">Çıkış yap</a>
    </div>
  </div>
</div>
<?php render_foot(); ?>
