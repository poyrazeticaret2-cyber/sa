<?php
/**
 * AlmancaPro - Kullanici detayi.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/planner.php';
app_boot();

$admin = require_admin();
$userId = input_int('id', 0);
$user = $userId > 0 ? db_row('SELECT * FROM users WHERE id = ?', [$userId]) : null;
if ($user === null) {
    flash('error', 'Kullanıcı bulunamadı.');
    redirect('/admin-users.php');
}

if (is_post()) {
    csrf_require();
    $action = (string)input('action', '');
    if ($action === 'reset_progress') {
        db_transaction(function () use ($userId) {
            db_exec('DELETE FROM user_skill_mastery WHERE user_id = ?', [$userId]);
            db_exec('DELETE FROM user_vocabulary_mastery WHERE user_id = ?', [$userId]);
            db_exec('DELETE FROM user_lesson_progress WHERE user_id = ?', [$userId]);
            db_exec('UPDATE users SET streak_count = 0, total_xp = 0 WHERE id = ?', [$userId]);
        });
        admin_log((int)$admin['id'], 'USER_PROGRESS_RESET', 'user', (string)$userId);
        flash('success', 'Kullanıcının öğrenme ilerlemesi sıfırlandı.');
    } elseif ($action === 'set_level') {
        $level = (string)input('cefr_level', 'A0');
        if (in_array($level, cefr_levels(), true)) {
            db_exec('UPDATE users SET cefr_level = ? WHERE id = ?', [$level, $userId]);
            admin_log((int)$admin['id'], 'USER_LEVEL_CHANGED', 'user', (string)$userId, ['level' => $level]);
            flash('success', 'Seviye güncellendi.');
        }
    }
    redirect('/admin-user.php?id=' . $userId);
}

$tz = user_timezone($user);
$mastery = db_row(
    'SELECT COUNT(*) total, AVG(mastery_score) avg_score,
            SUM(CASE WHEN status = "mastered" THEN 1 ELSE 0 END) mastered,
            SUM(CASE WHEN status IN ("weak","overdue") THEN 1 ELSE 0 END) weak
     FROM user_skill_mastery WHERE user_id = ? AND attempts > 0',
    [$userId]
) ?? [];
$vocab = db_row(
    'SELECT COUNT(*) total, SUM(CASE WHEN status = "mastered" THEN 1 ELSE 0 END) mastered
     FROM user_vocabulary_mastery WHERE user_id = ?',
    [$userId]
) ?? [];
$lessons = db_row(
    'SELECT COUNT(*) total, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) done
     FROM user_lesson_progress WHERE user_id = ?',
    [$userId]
) ?? [];
$attempts = db_row(
    'SELECT COUNT(*) total, SUM(is_correct) correct FROM exercise_attempts WHERE user_id = ?',
    [$userId]
) ?? [];
$telegram = db_row('SELECT * FROM telegram_connections WHERE user_id = ?', [$userId]);
$prefs = db_row('SELECT * FROM notification_preferences WHERE user_id = ?', [$userId]);
$weakSkills = weak_skills($userId, 8);
$errorAreas = weak_error_areas($userId, 6);
$activity = db_all('SELECT * FROM user_activity WHERE user_id = ? ORDER BY id DESC LIMIT 12', [$userId]);
$studySeconds = (int)$user['total_study_seconds'];

render_admin_start($admin, 'Kullanıcı: ' . (string)$user['name']);
?>
<a class="small" href="/admin-users.php">← Kullanıcılar</a>
<h1 style="margin-top: 12px;"><?= e((string)$user['name']) ?></h1>
<p style="color: var(--admin-ink-2);">
  <span class="mono">#<?= (int)$user['id'] ?></span> · <?= e((string)$user['email']) ?> ·
  Kayıt: <?= e(local_datetime((string)$user['created_at'], 'd.m.Y H:i')) ?>
</p>

<div class="grid grid-4" style="margin: 20px 0;">
  <div class="admin-stat"><div class="admin-stat__num"><?= e((string)$user['cefr_level']) ?></div><div class="admin-stat__label">Mevcut seviye (başlangıç: <?= e((string)$user['start_level']) ?>)</div></div>
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)round((float)($mastery['avg_score'] ?? 0)) ?>%</div><div class="admin-stat__label">Ortalama mastery</div></div>
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)($lessons['done'] ?? 0) ?></div><div class="admin-stat__label">Tamamlanan ders</div></div>
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)($vocab['mastered'] ?? 0) ?></div><div class="admin-stat__label">Mastered kelime</div></div>
</div>

<div class="grid grid-4" style="margin-bottom: 24px;">
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)($attempts['total'] ?? 0) ?></div><div class="admin-stat__label">Cevaplanan soru</div></div>
  <div class="admin-stat"><div class="admin-stat__num"><?= pct((int)($attempts['correct'] ?? 0), max(1, (int)($attempts['total'] ?? 0))) ?>%</div><div class="admin-stat__label">Doğruluk</div></div>
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)round($studySeconds / 60) ?></div><div class="admin-stat__label">Toplam dakika</div></div>
  <div class="admin-stat"><div class="admin-stat__num"><?= (int)$user['streak_count'] ?></div><div class="admin-stat__label">Seri (en uzun <?= (int)$user['longest_streak'] ?>)</div></div>
</div>

<div class="grid grid-2">
  <section class="admin-card">
    <p class="eyebrow">Hesap durumu</p>
    <div class="health-row"><span class="health-row__name">Doğrulama</span>
      <?php render_badge((int)$user['is_verified'] === 1 ? '✓' : '○', (int)$user['is_verified'] === 1 ? 'DOĞRULANDI' : 'BEKLİYOR', (int)$user['is_verified'] === 1 ? 'ok' : 'muted'); ?></div>
    <div class="health-row"><span class="health-row__name">Hesap</span>
      <?php render_badge((int)$user['is_active'] === 1 ? '✓' : '✕', (int)$user['is_active'] === 1 ? 'AKTİF' : 'DEVRE DIŞI', (int)$user['is_active'] === 1 ? 'ok' : 'bad'); ?></div>
    <div class="health-row"><span class="health-row__name">Telegram</span>
      <span class="health-row__detail"><?= $telegram !== null ? e('@' . (string)($telegram['username'] ?? 'bağlı')) : 'bağlı değil' ?></span></div>
    <div class="health-row"><span class="health-row__name">Bildirim modu</span>
      <span class="health-row__detail"><?= e((string)($prefs['mode'] ?? '—')) ?></span></div>
    <div class="health-row"><span class="health-row__name">Saat dilimi</span>
      <span class="health-row__detail mono"><?= e($tz) ?></span></div>
    <div class="health-row"><span class="health-row__name">Gidiş tarihi</span>
      <span class="health-row__detail mono"><?= e((string)($user['departure_date'] ?? '—')) ?></span></div>
    <div class="health-row"><span class="health-row__name">Son giriş IP</span>
      <span class="health-row__detail mono"><?= e((string)($user['last_login_ip'] ?? '—')) ?></span></div>
  </section>

  <section class="admin-card">
    <p class="eyebrow">Yönetim işlemleri</p>
    <form method="post" action="/admin-user.php?id=<?= $userId ?>" style="margin-bottom: 18px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="set_level">
      <div class="field">
        <label for="cefr_level">CEFR seviyesi</label>
        <select id="cefr_level" name="cefr_level">
          <?php foreach (cefr_levels() as $lv): ?>
            <option value="<?= e($lv) ?>"<?= (string)$user['cefr_level'] === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint">Seviye değişikliği ders kilitlerini etkilemez; kilitler mastery verisine göre hesaplanır.</div>
      </div>
      <button class="btn btn--sm btn--inline" type="submit">SEVİYEYİ GÜNCELLE</button>
    </form>

    <form method="post" action="/admin-user.php?id=<?= $userId ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset_progress">
      <button class="btn btn--sm btn--danger btn--inline" type="submit"
              data-confirm="Kullanıcının bütün öğrenme ilerlemesi silinecek. Emin misin?">İLERLEMEYİ SIFIRLA</button>
    </form>
    <p class="small" style="color: var(--admin-ink-2); margin-top: 12px;">
      Yönetici kullanıcının şifresini göremez ve değiştiremez. Kullanıcı şifresini yalnızca kendisi
      "şifremi unuttum" akışıyla sıfırlayabilir.
    </p>
  </section>
</div>

<div class="grid grid-2" style="margin-top: 20px;">
  <section class="admin-card">
    <p class="eyebrow">Zayıf konular</p>
    <?php if ($weakSkills === []): ?>
      <p class="small" style="color: var(--admin-ink-2);">Zayıf konu yok.</p>
    <?php else: ?>
      <?php foreach ($weakSkills as $w): ?>
        <div class="health-row">
          <span class="health-row__name"><?= e((string)$w['name']) ?></span>
          <span class="health-row__detail mono"><?= (int)$w['mastery_score'] ?>%</span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section class="admin-card">
    <p class="eyebrow">En sık hata türleri</p>
    <?php if ($errorAreas === []): ?>
      <p class="small" style="color: var(--admin-ink-2);">Kayıtlı hata yok.</p>
    <?php else: ?>
      <?php foreach ($errorAreas as $ea): ?>
        <div class="health-row">
          <span class="health-row__name"><?= e((string)$ea['name']) ?></span>
          <span class="health-row__detail mono"><?= (int)$ea['error_count'] ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</div>

<section class="admin-card" style="margin-top: 20px;">
  <p class="eyebrow">Son etkinlikler</p>
  <div class="table-wrap" style="border: 0;">
    <table class="data">
      <thead><tr><th>Tür</th><th>Başlık</th><th>XP</th><th>Tarih</th></tr></thead>
      <tbody>
        <?php foreach ($activity as $a): ?>
          <tr>
            <td data-label="Tür" class="mono small"><?= e((string)$a['activity_type']) ?></td>
            <td data-label="Başlık"><?= e((string)$a['title']) ?></td>
            <td data-label="XP" class="mono"><?= (int)$a['xp'] ?></td>
            <td data-label="Tarih" class="mono small"><?= e(local_datetime((string)$a['created_at'], 'd.m.y H:i', $tz)) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($activity === []): ?><tr><td colspan="4">Etkinlik yok.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php render_admin_end(); ?>
