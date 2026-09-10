<?php
/**
 * AlmancaPro - Giris.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
app_boot();

if (is_logged_in()) {
    redirect('/dashboard.php');
}

$error = '';
$email = '';
$next = (string)input('next', '/dashboard.php');
if (!str_starts_with($next, '/') || str_starts_with($next, '//')) {
    $next = '/dashboard.php';
}

if (is_post()) {
    csrf_require();
    $email = mb_strtolower((string)input('email', ''));
    $password = (string)($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);

    $ipOk = rate_limit_hit('login_ip', client_ip(), 25, 900);
    $userOk = rate_limit_hit('login_user', $email, 10, 900);

    if (!$ipOk || !$userOk) {
        $error = 'Çok fazla giriş denemesi yapıldı. Lütfen 15 dakika sonra tekrar dene.';
        auth_record_login_attempt($email, false);
    } elseif ($email === '' || $password === '') {
        $error = 'E-posta ve şifre alanlarını doldur.';
    } else {
        $user = auth_find_user_by_email($email);
        auth_record_login_attempt($email, false);

        if ($user === null) {
            /* Zamanlama farkini azaltmak icin sahte dogrulama. */
            password_verify($password, '$2y$12$usesomesillystringfoursev3nuGjPFnZAt7bnLKlqEcqOB0z0x2ZzC');
            $error = 'E-posta veya şifre hatalı.';
        } elseif (!password_verify($password, (string)$user['password_hash'])) {
            $error = 'E-posta veya şifre hatalı.';
        } elseif ((int)$user['is_active'] !== 1) {
            $error = 'Bu hesap devre dışı. Destek için site yöneticisine ulaş.';
        } else {
            if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
                db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash_app($password), (int)$user['id']]);
            }
            auth_record_login_attempt($email, true);
            rate_limit_reset('login_user', $email);

            if ((int)$user['is_verified'] !== 1) {
                $_SESSION['pending_verify_user'] = (int)$user['id'];
                redirect('/verify.php');
            }
            auth_login_user($user, $remember);
            redirect((int)$user['onboarding_completed'] === 1 ? $next : '/onboarding.php');
        }
    }
}

render_head('Giriş yap · ' . APP_NAME, ['css' => ['auth.css']]);
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-card__head">
      <?php render_logo('/'); ?>
      <h1 style="margin-top: 20px;">Tekrar hoş geldin</h1>
      <p class="auth-card__sub">Kaldığın yerden devam et.</p>
    </div>

    <?php render_flashes(); ?>
    <?php if ($error !== ''): ?>
      <div class="alert alert--error"><span class="alert__icon" aria-hidden="true">✕</span><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <form method="post" action="/login.php?next=<?= e(urlencode($next)) ?>" data-guard novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">E-posta</label>
        <input id="email" name="email" type="email" autocomplete="email" inputmode="email" value="<?= e($email) ?>" required autofocus>
      </div>
      <?php render_password_field('password', 'password', 'Şifre', 'current-password', false); ?>
      <div class="row-between" style="margin-bottom: 6px;">
        <span class="checkline" style="min-height: 44px;">
          <input id="remember" name="remember" type="checkbox" value="1" checked>
          <label for="remember">Beni hatırla</label>
        </span>
        <a class="small" href="/forgot-password.php">Şifremi unuttum</a>
      </div>
      <button class="btn btn--block btn--lg" type="submit">GİRİŞ YAP</button>
    </form>

    <div class="auth-alt">
      Hesabın yok mu? <a href="/register.php">Ücretsiz kayıt ol</a>
    </div>
  </div>
</div>
<?php render_foot(); ?>
