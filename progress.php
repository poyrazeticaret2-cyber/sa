<?php
/**
 * AlmancaPro - Ilerleme raporu.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];
$tz = user_timezone($user);
$counts = due_review_counts($userId);

$levels = level_summary($userId);

$skillStats = db_row(
    'SELECT COUNT(*) total, AVG(mastery_score) avg_score,
            SUM(CASE WHEN status = "mastered" THEN 1 ELSE 0 END) mastered,
            SUM(CASE WHEN status = "strong" THEN 1 ELSE 0 END) strong,
            SUM(CASE WHEN status IN ("weak","overdue") THEN 1 ELSE 0 END) weak
     FROM user_skill_mastery WHERE user_id = ? AND attempts > 0',
    [$userId]
) ?? [];

$vocabStats = db_row(
    'SELECT COUNT(*) total,
            SUM(CASE WHEN status = "mastered" THEN 1 ELSE 0 END) mastered,
            SUM(CASE WHEN status IN ("introduced","learning","reviewing","strong") THEN 1 ELSE 0 END) learning
     FROM user_vocabulary_mastery WHERE user_id = ?',
    [$userId]
) ?? [];

$accuracy = db_row(
    'SELECT COUNT(*) total, SUM(is_correct) correct FROM exercise_attempts WHERE user_id = ?',
    [$userId]
) ?? [];
$accuracyPct = pct((int)($accuracy['correct'] ?? 0), max(1, (int)($accuracy['total'] ?? 0)));

$reviewDone = db_row(
    'SELECT COUNT(*) total, SUM(is_correct) correct
     FROM exercise_attempts a JOIN study_sessions s ON s.id = a.session_id
     WHERE a.user_id = ? AND s.session_type = "review"',
    [$userId]
) ?? [];

/* 14 gunluk calisma suresi */
$daily = db_all(
    'SELECT DATE(started_at) d, SUM(duration_seconds) secs
     FROM study_sessions WHERE user_id = ? AND started_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 14 DAY)
     GROUP BY DATE(started_at) ORDER BY d',
    [$userId]
);
$dailyMap = [];
foreach ($daily as $d) {
    $dailyMap[(string)$d['d']] = (int)round((int)$d['secs'] / 60);
}
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $date = gmdate('Y-m-d', strtotime('-' . $i . ' days'));
    $days[] = ['date' => $date, 'minutes' => $dailyMap[$date] ?? 0];
}
$maxMinutes = max(1, max(array_column($days, 'minutes')));

$strong = db_all(
    'SELECT s.name, m.mastery_score FROM user_skill_mastery m JOIN skills s ON s.id = m.skill_id
     WHERE m.user_id = ? AND m.mastery_score >= 75 ORDER BY m.mastery_score DESC LIMIT 6',
    [$userId]
);
$weak = weak_skills($userId, 6);
$errorAreas = weak_error_areas($userId, 6);

$totalMinutes = (int)round((int)$user['total_study_seconds'] / 60);

render_app_start($user, 'İlerlemem · ' . APP_NAME, [], ['review.php' => $counts['due']]);
?>
<p class="eyebrow">İlerleme raporu</p>
<h1>İlerlemem</h1>

<div class="hairline-grid grid-4" style="margin: 22px 0;">
  <div style="padding: 18px;">
    <div class="eyebrow">Mevcut seviye</div>
    <div class="num" style="font-size: 26px; color: var(--ink);"><?= e((string)$user['cefr_level']) ?></div>
    <div class="small muted">başlangıç: <?= e((string)$user['start_level']) ?></div>
  </div>
  <div style="padding: 18px;">
    <div class="eyebrow">Genel mastery</div>
    <div class="num" style="font-size: 26px; color: var(--ink);"><?= (int)round((float)($skillStats['avg_score'] ?? 0)) ?>%</div>
    <div class="small muted"><?= (int)($skillStats['mastered'] ?? 0) ?> konu mastered</div>
  </div>
  <div style="padding: 18px;">
    <div class="eyebrow">Doğruluk</div>
    <div class="num" style="font-size: 26px; color: var(--ink);"><?= $accuracyPct ?>%</div>
    <div class="small muted"><?= (int)($accuracy['total'] ?? 0) ?> cevap</div>
  </div>
  <div style="padding: 18px;">
    <div class="eyebrow">Toplam çalışma</div>
    <div class="num" style="font-size: 26px; color: var(--ink);"><?= $totalMinutes ?></div>
    <div class="small muted">dakika · seri <?= (int)$user['streak_count'] ?> gün</div>
  </div>
</div>

<div class="grid grid-2" style="margin-bottom: 24px;">
  <section class="card">
    <p class="eyebrow">Seviye ilerlemesi</p>
    <?php foreach ($levels as $lv): ?>
      <div class="progress-row" style="margin-bottom: 12px;">
        <span class="badge badge--level" style="min-width: 44px;"><?= e($lv['level']) ?></span>
        <?php render_progress($lv['completed'], max(1, $lv['total']), $lv['percent'] >= 100 ? 'ok' : '', $lv['level']); ?>
        <span class="num"><?= (int)$lv['completed'] ?>/<?= (int)$lv['total'] ?></span>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="card">
    <p class="eyebrow">Kelime durumu</p>
    <div class="hairline-grid grid-3" style="margin-top: 10px;">
      <div style="padding: 14px;"><div class="num" style="font-size: 20px; color: var(--success);"><?= (int)($vocabStats['mastered'] ?? 0) ?></div><div class="small muted">mastered</div></div>
      <div style="padding: 14px;"><div class="num" style="font-size: 20px; color: var(--ink);"><?= (int)($vocabStats['learning'] ?? 0) ?></div><div class="small muted">öğreniyorsun</div></div>
      <div style="padding: 14px;"><div class="num" style="font-size: 20px; color: var(--ink);"><?= (int)($vocabStats['total'] ?? 0) ?></div><div class="small muted">toplam çalışılan</div></div>
    </div>
    <p class="small muted" style="margin-top: 14px;">
      Tekrar tamamlama: <?= pct((int)($reviewDone['correct'] ?? 0), max(1, (int)($reviewDone['total'] ?? 0))) ?>%
      (<?= (int)($reviewDone['total'] ?? 0) ?> tekrar cevabı)
    </p>
  </section>
</div>

<section class="card" style="margin-bottom: 24px;">
  <p class="eyebrow">Son 14 gün çalışma süresi (dakika)</p>
  <div style="display: flex; gap: 4px; align-items: flex-end; height: 120px; margin-top: 14px;">
    <?php foreach ($days as $d):
        $h = max(2, (int)round(($d['minutes'] / $maxMinutes) * 110)); ?>
      <div style="flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; gap: 6px;">
        <span class="mono" style="font-size: 10px; color: var(--ink-3);"><?= $d['minutes'] > 0 ? $d['minutes'] : '' ?></span>
        <span style="width: 100%; height: <?= $h ?>px; background: <?= $d['minutes'] > 0 ? 'var(--brass)' : 'var(--surface-3)' ?>;"
              title="<?= e(local_datetime($d['date'] . ' 12:00:00', 'd.m.Y', $tz)) ?>: <?= $d['minutes'] ?> dakika"></span>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="row-between small muted" style="margin-top: 8px;">
    <span><?= e(local_datetime($days[0]['date'] . ' 12:00:00', 'd.m', $tz)) ?></span>
    <span><?= e(local_datetime($days[count($days) - 1]['date'] . ' 12:00:00', 'd.m', $tz)) ?></span>
  </div>
</section>

<div class="grid grid-2">
  <section class="card card--flush">
    <div class="card__head"><strong>Güçlü olduğun konular</strong></div>
    <?php if ($strong === []): ?>
      <div style="padding: 18px;"><p class="small muted" style="margin: 0;">Henüz yeterli veri yok.</p></div>
    <?php else: ?>
      <?php foreach ($strong as $s): ?>
        <div class="plan-item is-done">
          <span class="plan-item__state" aria-hidden="true">✓</span>
          <span class="plan-item__title"><?= e((string)$s['name']) ?></span>
          <span class="plan-item__meta"><?= (int)$s['mastery_score'] ?>%</span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section class="card card--flush">
    <div class="card__head"><strong>Zayıf konular</strong></div>
    <?php if ($weak === []): ?>
      <div style="padding: 18px;"><p class="small muted" style="margin: 0;">Şu anda zayıf konun yok.</p></div>
    <?php else: ?>
      <?php foreach ($weak as $w): ?>
        <div class="plan-item">
          <span class="plan-item__state" aria-hidden="true">●</span>
          <span class="plan-item__title"><?= e((string)$w['name']) ?></span>
          <span class="plan-item__meta"><?= (int)$w['mastery_score'] ?>%</span>
          <a class="btn btn--sm btn--secondary btn--inline" href="/exercise.php?skill=<?= (int)$w['id'] ?>">ÇALIŞ</a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</div>

<?php if ($errorAreas !== []): ?>
  <section class="card card--flush" style="margin-top: 24px;">
    <div class="card__head"><strong>En çok yaptığın hata türleri</strong></div>
    <?php foreach ($errorAreas as $ea): ?>
      <div class="plan-item">
        <span class="plan-item__state" aria-hidden="true">●</span>
        <span class="plan-item__title"><?= e((string)$ea['name']) ?></span>
        <span class="plan-item__meta"><?= (int)$ea['error_count'] ?> hata</span>
      </div>
    <?php endforeach; ?>
    <div style="padding: 14px 18px; border-top: 1px solid var(--line);">
      <a class="small" href="/weak-areas.php">Zayıf alanlar sayfasına git →</a>
    </div>
  </section>
<?php endif; ?>

<p class="small muted" style="margin-top: 24px;">
  Bu rapordaki bütün sayılar gerçek çalışma verinden hesaplanır. Uygulama içi değerlendirmeler
  Goethe, telc veya ÖSD tarafından verilen resmi sertifika yerine geçmez.
</p>

<?php render_app_end(); ?>
