<?php
/**
 * AlmancaPro - Yonetici sifre degistirme.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
app_boot();

$admin = require_admin();
$first = input_int('first', 0) === 1 || (int)$admin['must_change_password'] === 1;
$error = '';

if (is_post()) {
    csrf_require();
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $new2 = (string)($_POST['new_password_confirm'] ?? '');

    if (!rate_limit_hit('admin_pwchange', (string)$admin['id'], 10, 900)) {
        $error = 'Çok fazla deneme yapıldı. Lütfen bir süre bekle.';
    } elseif ($new !== $new2) {
        $error = 'Yeni şifreler eşleşmiyor.';
    } else {
        $res = admin_change_password((int)$admin['id'], $current, $new);
        if ($res['ok']) {
            flash('success', 'Şifren güncellendi.');
            redirect('/admin.php');
        }
        $error = $res['error'];
    }
}

render_admin_start($admin, 'Şifre Değiştir');
?>
<h1>Şifre değiştir</h1>

<?php if ($first): ?>
  <div class="alert alert--warn"><span class="alert__icon" aria-hidden="true">●</span>
    <span>Güvenlik için varsayılan admin şifresini değiştirmeniz önerilir. Değiştirene kadar diğer sayfalara erişim kapalıdır.</span></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
  <div class="alert alert--error"><span class="alert__icon" aria-hidden="true">✕</span><span><?= e($error) ?></span></div>
<?php endif; ?>

<div class="admin-card" style="max-width: 520px;">
  <form method="post" action="/admin-password.php" data-guard novalidate>
    <?= csrf_field() ?>
    <?php render_password_field('current_password', 'current_password', 'Mevcut Şifre', 'current-password', false); ?>
    <?php render_password_field('new_password', 'new_password', 'Yeni Şifre', 'new-password', true); ?>
    <?php render_password_strength('new_password'); ?>
    <?php render_password_field('new_password_confirm', 'new_password_confirm', 'Yeni Şifre Tekrar', 'new-password', true); ?>
    <div class="hint" data-match-for="new_password" data-match-with="new_password_confirm" style="margin-top:-10px;margin-bottom:14px;"></div>
    <button class="btn btn--block" type="submit">ŞİFREYİ DEĞİŞTİR</button>
  </form>
  <p class="small" style="margin-top: 16px; color: var(--admin-ink-2);">
    Şifre en az 8 karakter olmalı, en az bir harf ve bir rakam içermeli. Varsayılan şifre yeniden kullanılamaz.
  </p>
</div>

<div class="admin-card" style="max-width: 520px; margin-top: 20px;">
  <h2 style="font-size: 16px;">Hesap bilgileri</h2>
  <p class="small" style="color: var(--admin-ink-2); margin: 0;">
    Kullanıcı adı: <span class="mono"><?= e((string)$admin['username']) ?></span><br>
    Son giriş: <?= e(local_datetime((string)($admin['last_login_at'] ?? ''), 'd.m.Y H:i')) ?><br>
    Son giriş IP: <span class="mono"><?= e((string)($admin['last_login_ip'] ?? '—')) ?></span><br>
    Şifre değişimi: <?= e(local_datetime((string)($admin['password_changed_at'] ?? ''), 'd.m.Y H:i')) ?>
  </p>
</div>
<?php render_admin_end(); ?>
