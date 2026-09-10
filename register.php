<?php
/**
 * AlmancaPro - Kayit.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mailer.php';
app_boot();

if (is_logged_in()) {
    redirect('/dashboard.php');
}

if (!setting_bool('registration_open', true)) {
    fatal_page('Kayıtlar şu anda kapalı', 'Yeni kayıt alımı geçici olarak durduruldu. Daha sonra tekrar dene.', 503);
}

$errors = [];
$old = ['name' => '', 'email' => ''];

if (is_post()) {
    csrf_require();

    $name = (string)input('name', '');
    $email = mb_strtolower((string)input('email', ''));
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password_confirm'] ?? '');
    $terms = !empty($_POST['terms']);
    $privacy = !empty($_POST['privacy']);
    $old = ['name' => $name, 'email' => $email];

    if (!rate_limit_hit('register_ip', client_ip(), 10, 3600)) {
        $errors['form'] = 'Çok fazla kayıt denemesi yapıldı. Lütfen bir süre sonra tekrar dene.';
    }
    if ($name === '' || mb_strlen($name) < 2) {
        $errors['name'] = 'Adını en az 2 karakter yaz.';
    }
    if (mb_strlen($name) > 120) {
        $errors['name'] = 'Ad çok uzun.';
    }
    if (!valid_email($email)) {
        $errors['email'] = 'Geçerli bir e-posta adresi gir.';
    }
    $pwProblems = password_problems($password);
    if ($pwProblems !== []) {
        $errors['password'] = implode(' ', $pwProblems);
    }
    if ($password !== $password2) {
        $errors['password_confirm'] = 'Şifreler eşleşmiyor.';
    }
    if (!$terms) {
        $errors['terms'] = 'Devam etmek için kullanım koşullarını onaylaman gerekiyor.';
    }
    if (!$privacy) {
        $errors['privacy'] = 'Devam etmek için gizlilik politikasını onaylaman gerekiyor.';
    }

    if ($errors === []) {
        $existing = auth_find_user_by_email($email);
        if ($existing !== null) {
            /* Kullanici sayimi yapmadan bilgilendir: mevcut hesap varsa giris onerilir. */
            if ((int)$existing['is_verified'] === 1) {
                flash('info', 'Bu e-posta ile zaten bir hesap var. Giriş yapabilirsin.');
                redirect('/login.php');
            }
            /* Dogrulanmamis hesap: yeni kod gonder ve dogrulama ekranina yonlendir. */
            $_SESSION['pending_verify_user'] = (int)$existing['id'];
            $code = auth_create_verification_code((int)$existing['id']);
            $mail = mail_send_verification($existing, $code);
            if (!$mail['ok']) {
                app_log('Dogrulama e-postasi gonderilemedi: ' . $mail['error']);
            }
            redirect('/verify.php');
        }

        try {
            $userId = db_transaction(function () use ($name, $email, $password) {
                $id = db_insert(
                    'INSERT INTO users (name, email, password_hash, timezone, daily_minutes, terms_accepted_at, privacy_accepted_at, password_changed_at)
                     VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                    [$name, $email, password_hash_app($password), APP_DEFAULT_TIMEZONE, setting_int('default_daily_goal', 30)]
                );
                db_exec('INSERT IGNORE INTO notification_preferences (user_id, timezone, daily_target) VALUES (?, ?, ?)',
                    [$id, APP_DEFAULT_TIMEZONE, setting_int('default_daily_goal', 30)]);
                return $id;
            });

            $user = db_row('SELECT * FROM users WHERE id = ?', [$userId]);
            $_SESSION['pending_verify_user'] = (int)$userId;

            $code = auth_create_verification_code((int)$userId);
            $mail = mail_send_verification($user ?? [], $code);
            if (!$mail['ok']) {
                app_log('Dogrulama e-postasi gonderilemedi: ' . $mail['error']);
                flash('warning', 'Hesabın oluşturuldu fakat doğrulama e-postası gönderilemedi. Yönetici SMTP ayarlarını tamamladığında kodu tekrar isteyebilirsin.');
            }
            redirect('/verify.php');
        } catch (Throwable $e) {
            app_log('Kayit hatasi: ' . $e->getMessage());
            $errors['form'] = 'Kayıt tamamlanamadı. Lütfen tekrar dene.';
        }
    }
}

render_head('Kayıt ol · ' . APP_NAME, ['css' => ['auth.css']]);
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-card__head">
      <?php render_logo('/'); ?>
      <h1 style="margin-top: 20px;">Almancayı gerçekten öğren.</h1>
      <p class="auth-card__sub">Hesap oluştur ve bugün ilk dersine başla.</p>
      <span class="free-note">Tamamen ücretsiz</span>
    </div>

    <?php render_flashes(); ?>
    <?php if (isset($errors['form'])): ?>
      <div class="alert alert--error"><span class="alert__icon" aria-hidden="true">✕</span><span><?= e($errors['form']) ?></span></div>
    <?php endif; ?>

    <form method="post" action="/register.php" data-guard novalidate>
      <?= csrf_field() ?>

      <div class="field">
        <label for="name">Ad</label>
        <input id="name" name="name" type="text" autocomplete="name" value="<?= e($old['name']) ?>" required>
        <?php if (isset($errors['name'])): ?><div class="hint text-danger">✕ <?= e($errors['name']) ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label for="email">E-posta</label>
        <input id="email" name="email" type="email" autocomplete="email" inputmode="email" value="<?= e($old['email']) ?>" required>
        <?php if (isset($errors['email'])): ?><div class="hint text-danger">✕ <?= e($errors['email']) ?></div><?php endif; ?>
      </div>

      <?php render_password_field('password', 'password', 'Şifre', 'new-password', true); ?>
      <?php render_password_strength('password'); ?>
      <?php if (isset($errors['password'])): ?><div class="hint text-danger" style="margin-top:-10px;margin-bottom:14px;">✕ <?= e($errors['password']) ?></div><?php endif; ?>

      <?php render_password_field('password_confirm', 'password_confirm', 'Şifre tekrar', 'new-password', true); ?>
      <div class="hint" data-match-for="password" data-match-with="password_confirm" style="margin-top:-10px;margin-bottom:14px;"></div>
      <?php if (isset($errors['password_confirm'])): ?><div class="hint text-danger" style="margin-bottom:14px;">✕ <?= e($errors['password_confirm']) ?></div><?php endif; ?>

      <div class="checkline">
        <input id="terms" name="terms" type="checkbox" value="1" required>
        <label for="terms"><a href="/terms.php" target="_blank" rel="noopener">Kullanım koşullarını</a> okudum ve onaylıyorum.</label>
      </div>
      <?php if (isset($errors['terms'])): ?><div class="hint text-danger">✕ <?= e($errors['terms']) ?></div><?php endif; ?>

      <div class="checkline">
        <input id="privacy" name="privacy" type="checkbox" value="1" required>
        <label for="privacy"><a href="/privacy.php" target="_blank" rel="noopener">Gizlilik politikasını</a> okudum ve onaylıyorum.</label>
      </div>
      <?php if (isset($errors['privacy'])): ?><div class="hint text-danger">✕ <?= e($errors['privacy']) ?></div><?php endif; ?>

      <button class="btn btn--block btn--lg" type="submit" style="margin-top: 18px;">ÜCRETSİZ BAŞLA</button>
    </form>

    <div class="auth-alt">
      Zaten hesabın var mı? <a href="/login.php">Giriş yap</a>
    </div>
  </div>
</div>
<?php render_foot(); ?>
