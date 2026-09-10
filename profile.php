<?php
/**
 * AlmancaPro - Profil ve sifre degistirme.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
require_once __DIR__ . '/mailer.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];
$tz = user_timezone($user);
$counts = due_review_counts($userId);

$errors = [];
$notice = '';

if (is_post()) {
    csrf_require();
    $action = (string)input('action', '');

    if ($action === 'profile') {
        $name = trim((string)input('name', ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors['name'] = 'Adın 2-120 karakter arasında olmalı.';
        } else {
            db_exec('UPDATE users SET name = ? WHERE id = ?', [$name, $userId]);
            flash('success', 'Profilin güncellendi.');
            redirect('/profile.php');
        }
    } elseif ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $new2 = (string)($_POST['new_password_confirm'] ?? '');

        if (!password_verify($current, (string)$user['password_hash'])) {
            $errors['current_password'] = 'Mevcut şifre hatalı.';
        }
        $problems = password_problems($new);
        if ($problems !== []) {
            $errors['new_password'] = implode(' ', $problems);
        }
        if ($new !== $new2) {
            $errors['new_password_confirm'] = 'Şifreler eşleşmiyor.';
        }
        if ($errors === []) {
            db_exec('UPDATE users SET password_hash = ?, password_changed_at = UTC_TIMESTAMP() WHERE id = ?',
                [password_hash_app($new), $userId]);
            auth_forget_all_devices($userId);
            if (smtp_is_configured()) {
                mail_send_security_notice($user, 'Hesabının şifresi az önce değiştirildi. Diğer cihazlardaki oturumlar kapatıldı.');
            }
            flash('success', 'Şifren güncellendi. Diğer cihazlardaki oturumlar kapatıldı.');
            redirect('/profile.php');
        }
    }
}

$stats = db_row(
    'SELECT
        (SELECT COUNT(*) FROM user_lesson_progress WHERE user_id = ? AND status = "completed") lessons,
        (SELECT COUNT(*) FROM user_vocabulary_mastery WHERE user_id = ? AND status = "mastered") vocab,
        (SELECT COUNT(*) FROM exercise_attempts WHERE user_id = ?) attempts,
        (SELECT COUNT(*) FROM study_sessions WHERE user_id = ? AND status = "completed") sessions',
    [$userId, $userId, $userId, $userId]
) ?? [];

$donations = db_all('SELECT * FROM donations WHERE user_id = ? ORDER BY id DESC LIMIT 10', [$userId]);
$telegram = db_row('SELECT * FROM telegram_connections WHERE user_id = ?', [$userId]);

render_app_start($user, 'Profil · ' . APP_NAME, [], ['review.php' => $counts['due']]);
?>
<p class="eyebrow">Hesabım</p>
<h1>Profil</h1>

<?php if ($notice !== ''): ?>
  <div class="alert alert--success"><span class="alert__icon" aria-hidden="true">✓</span><span><?= e($notice) ?></span></div>
<?php endif; ?>

<div class="hairline-grid grid-4" style="margin: 22px 0;">
  <div style="padding: 18px;"><div class="num" style="font-size: 22px; color: var(--ink);"><?= (int)($stats['lessons'] ?? 0) ?></div><div class="small muted">tamamlanan ders</div></div>
  <div style="padding: 18px;"><div class="num" style="font-size: 22px; color: var(--ink);"><?= (int)($stats['vocab'] ?? 0) ?></div><div class="small muted">mastered kelime</div></div>
  <div style="padding: 18px;"><div class="num" style="font-size: 22px; color: var(--ink);"><?= (int)($stats['attempts'] ?? 0) ?></div><div class="small muted">cevaplanan soru</div></div>
  <div style="padding: 18px;"><div class="num" style="font-size: 22px; color: var(--ink);"><?= (int)$user['total_xp'] ?></div><div class="small muted">XP</div></div>
</div>

<div class="grid grid-2">
  <section class="card">
    <h2 style="font-size: 18px;">Hesap bilgileri</h2>
    <form method="post" action="/profile.php" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="profile">
      <div class="field">
        <label for="name">Ad</label>
        <input id="name" name="name" type="text" autocomplete="name" value="<?= e((string)$user['name']) ?>" required>
        <?php if (isset($errors['name'])): ?><div class="hint text-danger">✕ <?= e($errors['name']) ?></div><?php endif; ?>
      </div>
      <div class="field">
        <label for="email">E-posta</label>
        <input id="email" type="email" value="<?= e((string)$user['email']) ?>" readonly>
        <div class="hint">E-posta adresi güvenlik nedeniyle panelden değiştirilemez.</div>
      </div>
      <div class="field">
        <label>Üyelik</label>
        <p class="small muted" style="margin: 0;">
          Kayıt: <?= e(local_datetime((string)$user['created_at'], 'd.m.Y', $tz)) ?> ·
          Son giriş: <?= e(local_datetime((string)($user['last_login_at'] ?? ''), 'd.m.Y H:i', $tz)) ?>
        </p>
      </div>
      <button class="btn btn--inline" type="submit">KAYDET</button>
    </form>
  </section>

  <section class="card">
    <h2 style="font-size: 18px;">Şifre değiştir</h2>
    <form method="post" action="/profile.php" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <?php render_password_field('current_password', 'current_password', 'Mevcut şifre', 'current-password', false); ?>
      <?php if (isset($errors['current_password'])): ?><div class="hint text-danger" style="margin-bottom:12px;">✕ <?= e($errors['current_password']) ?></div><?php endif; ?>
      <?php render_password_field('new_password', 'new_password', 'Yeni şifre', 'new-password', true); ?>
      <?php render_password_strength('new_password'); ?>
      <?php if (isset($errors['new_password'])): ?><div class="hint text-danger" style="margin-bottom:12px;">✕ <?= e($errors['new_password']) ?></div><?php endif; ?>
      <?php render_password_field('new_password_confirm', 'new_password_confirm', 'Yeni şifre tekrar', 'new-password', true); ?>
      <div class="hint" data-match-for="new_password" data-match-with="new_password_confirm" style="margin-top:-10px;margin-bottom:14px;"></div>
      <?php if (isset($errors['new_password_confirm'])): ?><div class="hint text-danger" style="margin-bottom:12px;">✕ <?= e($errors['new_password_confirm']) ?></div><?php endif; ?>
      <button class="btn btn--inline" type="submit">ŞİFREYİ DEĞİŞTİR</button>
      <p class="small muted" style="margin-top: 10px;">Şifre değişince diğer bütün cihazlardaki oturumlar kapatılır.</p>
    </form>
  </section>
</div>

<div class="grid grid-2" style="margin-top: 24px;">
  <section class="card">
    <h2 style="font-size: 18px;">Telegram</h2>
    <?php if ($telegram !== null && (int)$telegram['is_active'] === 1): ?>
      <p class="small"><span class="badge badge--ok"><span aria-hidden="true">✓</span>BAĞLI</span>
        <?php if (!empty($telegram['username'])): ?> <span class="mono">@<?= e((string)$telegram['username']) ?></span><?php endif; ?></p>
      <p class="small muted">Bağlantı tarihi: <?= e(local_datetime((string)$telegram['linked_at'], 'd.m.Y H:i', $tz)) ?></p>
    <?php else: ?>
      <p class="small">Telegram hesabın bağlı değil.</p>
    <?php endif; ?>
    <a class="btn btn--sm btn--secondary btn--inline" href="/telegram.php">TELEGRAM AYARLARI</a>
  </section>

  <section class="card">
    <h2 style="font-size: 18px;">Hesap işlemleri</h2>
    <p class="small">Bildirim ve öğrenme tercihlerini Ayarlar sayfasından değiştirebilirsin.</p>
    <div class="row">
      <a class="btn btn--sm btn--secondary btn--inline" href="/settings.php">AYARLAR</a>
      <a class="btn btn--sm btn--danger btn--inline" href="/delete-account.php">HESABI SİL</a>
    </div>
  </section>
</div>

<?php if ($donations !== []): ?>
  <section class="card card--flush" style="margin-top: 24px;">
    <div class="card__head"><strong>Destek geçmişin</strong></div>
    <?php foreach ($donations as $d):
        $st = (string)$d['status'];
        [$icon, $label, $variant] = match ($st) {
            'onaylandi' => ['✓', 'ULAŞTI', 'ok'],
            'kod_eslesmedi' => ['●', 'KOD EŞLEŞMEDİ', 'warn'],
            'bulunamadi' => ['✕', 'BULUNAMADI', 'bad'],
            default => ['●', 'BEKLEMEDE', 'warn'],
        };
    ?>
      <div class="plan-item">
        <span class="plan-item__state" aria-hidden="true"><?= $icon ?></span>
        <span class="plan-item__title">
          <span class="mono"><?= e((string)$d['code']) ?></span> · <?= e(number_format((float)$d['amount'], 2, ',', '.')) ?> TL
        </span>
        <span class="plan-item__meta"><?php render_badge($icon, $label, $variant); ?></span>
      </div>
    <?php endforeach; ?>
    <div style="padding: 14px 18px; border-top: 1px solid var(--line);">
      <p class="small muted" style="margin: 0;">
        Otomatik çekim yoktur. Düzenli destek vermek istersen bankanın mobil uygulamasından aynı IBAN ve
        aynı açıklama ile düzenli ödeme talimatı verebilirsin; talimatı yine kendi bankandan durdurursun.
      </p>
    </div>
  </section>
<?php endif; ?>

<?php render_app_end(); ?>
