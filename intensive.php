<?php
/**
 * AlmancaPro - 30 Gunde Almanya'ya Hazirlik programi.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];
$tz = user_timezone($user);
$counts = due_review_counts($userId);

if (is_post()) {
    csrf_require();
    $action = (string)input('action', '');
    if ($action === 'start') {
        db_exec('UPDATE users SET intensive_enabled = 1, intensive_started_on = ? WHERE id = ?', [local_date($tz), $userId]);
        $user = current_user(true) ?? $user;
        daily_plan($userId, $user, true);
        flash('success', 'Yoğun program başlatıldı. Bugünkü planın buna göre yeniden oluşturuldu.');
    } elseif ($action === 'stop') {
        db_exec('UPDATE users SET intensive_enabled = 0 WHERE id = ?', [$userId]);
        $user = current_user(true) ?? $user;
        daily_plan($userId, $user, true);
        flash('info', 'Yoğun program durduruldu. Normal planına döndün.');
    } elseif ($action === 'regenerate') {
        daily_plan($userId, $user, true);
        flash('success', 'Bugünkü plan yeniden oluşturuldu.');
    }
    redirect('/intensive.php');
}

$enabled = (int)$user['intensive_enabled'] === 1;
$plan = daily_plan($userId, $user);
$planPercent = daily_plan_percent($plan);
$programDay = $plan['program_day'] !== null ? (int)$plan['program_day'] : null;
$daysLeft = days_until_departure($user['departure_date'] ?? null, $tz);

$intensityLabel = match ((string)$plan['intensity']) {
    'hafif' => 'Hafif',
    'normal' => 'Normal',
    'yogun' => 'Yoğun',
    default => 'Çok yoğun',
};

$estimatedMinutes = 0;
foreach ($plan['items'] as $it) {
    $estimatedMinutes += (int)$it['estimated_minutes'];
}

/* Programin son 7 gunu */
$history = db_all(
    'SELECT dp.plan_date, dp.program_day, dp.target_minutes,
            (SELECT SUM(done_count) FROM daily_plan_items WHERE plan_id = dp.id) done,
            (SELECT SUM(target_count) FROM daily_plan_items WHERE plan_id = dp.id) target
     FROM daily_plans dp WHERE dp.user_id = ? ORDER BY dp.plan_date DESC LIMIT 7',
    [$userId]
);

render_app_start($user, '30 Günlük Program · ' . APP_NAME, [], ['review.php' => $counts['due'], 'intensive.php' => $programDay ?? 0]);
?>
<p class="eyebrow">Yoğun program</p>
<h1>30 Günde Almanya'ya Hazırlık</h1>
<p class="muted">
  Bu program günlük hedeflerini kalan süreye, mevcut mastery durumuna ve zayıf alanlarına göre uyarlar.
</p>

<div class="alert alert--warn" style="margin-top: 18px;">
  <span class="alert__icon" aria-hidden="true">●</span>
  <span>Bu bir <strong>garanti değildir</strong>. 30 günde B1 seviyesine ulaşacağın iddia edilmiyor.
    Program, elindeki süreyi en verimli sırayla kullanmanı sağlar; sonuç çalışma yoğunluğuna ve başlangıç seviyene bağlıdır.</span>
</div>

<?php if (!$enabled): ?>
  <div class="card" style="margin-top: 22px;">
    <h2>Programı başlat</h2>
    <p class="small">
      Program başladığında günlük kelime, ders, tekrar ve quiz hedeflerin artar; her 5 günde bir mini sınav eklenir.
      İstediğin zaman durdurabilirsin.
      <?php if ($daysLeft !== null && $daysLeft > 0): ?>
        Gidiş tarihine <strong><?= (int)$daysLeft ?> gün</strong> var.
      <?php endif; ?>
    </p>
    <form method="post" action="/intensive.php" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="start">
      <button class="btn btn--lg btn--inline" type="submit">PROGRAMI BAŞLAT</button>
    </form>
  </div>

<?php else: ?>
  <div class="grid grid-main" style="margin-top: 22px;">
    <section class="card card--strong">
      <div class="row-between" style="margin-bottom: 16px;">
        <div>
          <p class="eyebrow" style="margin: 0;">Gün</p>
          <div class="num" style="font-size: 40px; color: var(--ink); line-height: 1;">
            <?= $programDay !== null ? $programDay : 1 ?><span style="font-size: 20px; color: var(--ink-3);">/30</span>
          </div>
        </div>
        <div style="text-align: right;">
          <p class="eyebrow" style="margin: 0;">Yoğunluk</p>
          <strong><?= e($intensityLabel) ?></strong>
          <div class="small muted">Tahmini <?= e(human_minutes($estimatedMinutes)) ?></div>
        </div>
      </div>

      <div class="progress-row" style="margin-bottom: 20px;">
        <?php render_progress($planPercent, 100, $planPercent >= 100 ? 'ok' : '', 'Bugünkü program'); ?>
        <span class="num"><?= $planPercent ?>%</span>
      </div>

      <div class="card card--flush" style="border: 1px solid var(--line);">
        <?php foreach ($plan['items'] as $item):
            $st = (string)$item['status'];
            $icon = $st === 'done' ? '✓' : ($st === 'in_progress' ? '●' : '○');
            $cls = $st === 'done' ? 'is-done' : ($st === 'in_progress' ? 'is-progress' : 'is-pending');
            $href = match ((string)$item['item_type']) {
                'vocabulary' => '/course.php',
                'lesson', 'workplace' => $item['ref_id'] !== null ? '/lesson.php?id=' . (int)$item['ref_id'] : '/course.php',
                'review' => '/review.php',
                'quiz', 'exam' => '/quiz.php?mode=mixed',
                'remediation' => '/exercise.php?skill=' . (int)$item['ref_id'],
                'scenario' => '/scenario.php' . ($item['ref_id'] !== null ? '?id=' . (int)$item['ref_id'] : ''),
                default => '/dashboard.php',
            };
        ?>
          <a class="plan-item <?= $cls ?>" href="<?= e($href) ?>" style="text-decoration: none; color: inherit;">
            <span class="plan-item__state" aria-hidden="true"><?= $icon ?></span>
            <span class="plan-item__title"><?= e((string)$item['title']) ?></span>
            <span class="plan-item__meta"><?= (int)$item['done_count'] ?>/<?= (int)$item['target_count'] ?> · <?= (int)$item['estimated_minutes'] ?> dk</span>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="row" style="margin-top: 18px;">
        <form method="post" action="/intensive.php" style="margin: 0;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="regenerate">
          <button class="btn btn--secondary btn--inline" type="submit">PLANI YENİLE</button>
        </form>
        <form method="post" action="/intensive.php" style="margin: 0;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="stop">
          <button class="btn btn--danger btn--inline" type="submit" data-confirm="Yoğun programı durdurmak istediğine emin misin?">PROGRAMI DURDUR</button>
        </form>
      </div>
    </section>

    <div class="stack">
      <section class="card">
        <p class="eyebrow">Kalan süre</p>
        <?php if ($daysLeft !== null && $daysLeft > 0): ?>
          <div class="num" style="font-size: 30px; color: var(--ink);"><?= (int)$daysLeft ?></div>
          <p class="small muted">gün kaldı · <?= e(local_datetime((string)$user['departure_date'] . ' 12:00:00', 'd.m.Y', $tz)) ?></p>
        <?php else: ?>
          <p class="small">Gidiş tarihi girilmemiş. Ayarlar sayfasından ekleyebilirsin.</p>
          <a class="btn btn--sm btn--secondary btn--inline" href="/settings.php">TARİH EKLE</a>
        <?php endif; ?>
      </section>

      <section class="card card--flush">
        <div class="card__head"><strong>Son 7 gün</strong></div>
        <?php if ($history === []): ?>
          <div style="padding: 18px;"><p class="small muted" style="margin: 0;">Henüz geçmiş yok.</p></div>
        <?php else: ?>
          <?php foreach ($history as $h):
              $p = pct((int)($h['done'] ?? 0), max(1, (int)($h['target'] ?? 1))); ?>
            <div class="plan-item <?= $p >= 100 ? 'is-done' : ($p > 0 ? 'is-progress' : 'is-pending') ?>">
              <span class="plan-item__state" aria-hidden="true"><?= $p >= 100 ? '✓' : ($p > 0 ? '●' : '○') ?></span>
              <span class="plan-item__title">
                <?= e(local_datetime((string)$h['plan_date'] . ' 12:00:00', 'd.m.Y', $tz)) ?>
                <?php if ($h['program_day'] !== null): ?><span class="small muted">· Gün <?= (int)$h['program_day'] ?></span><?php endif; ?>
              </span>
              <span class="plan-item__meta"><?= $p ?>%</span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    </div>
  </div>
<?php endif; ?>

<?php render_app_end(); ?>
