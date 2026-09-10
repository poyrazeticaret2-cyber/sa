<?php
/**
 * AlmancaPro - Kullanici paneli. Tek soruya cevap verir: "Bugun ne yapmaliyim?"
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];
$tz = user_timezone($user);

$plan = daily_plan($userId, $user);
$planPercent = daily_plan_percent($plan);
$next = next_activity($userId, $user);
$counts = due_review_counts($userId);
$daysLeft = days_until_departure($user['departure_date'] ?? null, $tz);

/* Kelime durumu */
$vocabStats = db_row(
    'SELECT
        SUM(CASE WHEN status = "mastered" THEN 1 ELSE 0 END) mastered,
        SUM(CASE WHEN status IN ("learning","reviewing","strong","introduced") THEN 1 ELSE 0 END) learning,
        SUM(CASE WHEN status = "weak" THEN 1 ELSE 0 END) weak,
        COUNT(*) total
     FROM user_vocabulary_mastery WHERE user_id = ?',
    [$userId]
) ?? [];
$vocabTotal = (int)db_value('SELECT COUNT(*) FROM vocabulary WHERE is_active = 1', [], 0);

/* Genel mastery */
$overall = db_row(
    'SELECT AVG(mastery_score) avg_score, COUNT(*) total,
            SUM(CASE WHEN status = "mastered" THEN 1 ELSE 0 END) mastered
     FROM user_skill_mastery WHERE user_id = ? AND attempts > 0',
    [$userId]
) ?? [];
$overallMastery = (int)round((float)($overall['avg_score'] ?? 0));

/* Aktif modul */
$activeModule = db_row(
    'SELECT m.title, m.cefr_level, COUNT(l.id) total,
            SUM(CASE WHEN p.status = "completed" THEN 1 ELSE 0 END) done
     FROM modules m
     JOIN lessons l ON l.module_id = m.id AND l.is_active = 1
     LEFT JOIN user_lesson_progress p ON p.lesson_id = l.id AND p.user_id = ?
     WHERE m.is_active = 1
     GROUP BY m.id, m.title, m.cefr_level, m.sort_order
     HAVING done < total
     ORDER BY FIELD(m.cefr_level,"A0","A1","A2","B1"), m.sort_order
     LIMIT 1',
    [$userId]
);

/* Bugunku calisma suresi */
$todayStart = gmdate('Y-m-d H:i:s', strtotime(local_date($tz) . ' 00:00:00 ' . $tz) ?: time());
$todaySeconds = (int)db_value(
    'SELECT COALESCE(SUM(duration_seconds), 0) FROM study_sessions WHERE user_id = ? AND started_at >= ?',
    [$userId, $todayStart], 0
);
$weekSeconds = (int)db_value(
    'SELECT COALESCE(SUM(duration_seconds), 0) FROM study_sessions WHERE user_id = ? AND started_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)',
    [$userId], 0
);

/* Zayif alanlar */
$weakAreas = weak_error_areas($userId, 4);
$weakSkillList = weak_skills($userId, 4);

/* Telegram */
$telegram = db_row('SELECT * FROM telegram_connections WHERE user_id = ? AND is_active = 1', [$userId]);

/* Yaklasan tekrar */
$nextReview = db_value(
    'SELECT MIN(next_review_at) FROM (
        SELECT next_review_at FROM user_vocabulary_mastery WHERE user_id = ? AND status <> "mastered" AND next_review_at > UTC_TIMESTAMP()
        UNION ALL
        SELECT next_review_at FROM user_skill_mastery WHERE user_id = ? AND status <> "mastered" AND next_review_at > UTC_TIMESTAMP()
     ) t',
    [$userId, $userId]
);

/* Son etkinlikler */
$activity = db_all(
    'SELECT activity_type, title, xp, created_at FROM user_activity WHERE user_id = ? ORDER BY id DESC LIMIT 6',
    [$userId]
);

$navCounts = ['review.php' => $counts['due']];
if ((int)$user['intensive_enabled'] === 1 && $plan['program_day'] !== null) {
    $navCounts['intensive.php'] = (int)$plan['program_day'];
}

$hour = (int)local_datetime(now_utc(), 'H', $tz);
$greeting = $hour < 11 ? 'Günaydın' : ($hour < 18 ? 'Merhaba' : 'İyi akşamlar');

render_app_start($user, 'Ana Sayfa · ' . APP_NAME, ['js' => ['review.js']], $navCounts);
?>

<div class="row-between" style="margin-bottom: 26px; align-items: flex-start;">
  <div>
    <h1><?= e($greeting) ?>, <?= e((string)$user['name']) ?></h1>
    <p class="muted" style="margin: 0;">
      <?php if ($daysLeft !== null && $daysLeft > 0): ?>
        Almanya'ya gitmene <span class="num"><?= (int)$daysLeft ?></span> gün kaldı ·
      <?php elseif ($daysLeft !== null && $daysLeft <= 0): ?>
        Almanya'dasın ·
      <?php endif; ?>
      Mevcut seviye <strong><?= e((string)$user['cefr_level']) ?></strong>
    </p>
  </div>
  <div class="card" style="padding: 14px 18px; min-width: 180px;">
    <div class="row" style="gap: 10px;">
      <span class="num" style="font-size: 24px; color: var(--ink);">🔥 <?= (int)$user['streak_count'] ?></span>
      <span class="small">günlük seri</span>
    </div>
    <p class="small muted" style="margin: 6px 0 0;">Asıl hedef: düzenli ve doğru öğrenme.</p>
  </div>
</div>

<div class="grid grid-main" style="margin-bottom: 24px;">
  <section class="card card--strong">
    <p class="eyebrow">Bugünkü hedef</p>
    <div class="row" style="align-items: baseline; gap: 12px;">
      <span class="num" style="font-size: 52px; line-height: 1; color: var(--ink);"><?= $planPercent ?>%</span>
      <span class="small muted"><?= e(human_minutes((int)$plan['target_minutes'])) ?> hedef ·
        bugün <?= e(human_minutes((int)round($todaySeconds / 60))) ?> çalıştın</span>
    </div>
    <div style="margin: 16px 0 20px;">
      <?php render_progress($planPercent, 100, $planPercent >= 100 ? 'ok' : '', 'Bugünkü hedef'); ?>
    </div>

    <div class="hairline-grid grid-4" style="margin-bottom: 20px;">
      <?php
      $metricMap = ['vocabulary' => 'kelime', 'lesson' => 'ders', 'review' => 'tekrar', 'quiz' => 'quiz'];
      foreach ($metricMap as $type => $label):
          $item = null;
          foreach ($plan['items'] as $it) {
              if ($it['item_type'] === $type) { $item = $it; break; }
          }
          $doneN = $item !== null ? (int)$item['done_count'] : 0;
          $targetN = $item !== null ? (int)$item['target_count'] : 0;
      ?>
        <div style="padding: 14px;">
          <div class="num" style="font-size: 18px; color: var(--ink);"><?= $doneN ?>/<?= $targetN ?></div>
          <div class="small muted"><?= e($label) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <a class="btn btn--block btn--lg" href="<?= e($next['url']) ?>">DEVAM ET</a>
    <p class="small muted" style="margin: 12px 0 0;">
      Sıradaki: <strong><?= e($next['title']) ?></strong> · yaklaşık <?= (int)$next['minutes'] ?> dk<br>
      <?= e($next['reason']) ?>
    </p>
  </section>

  <div class="stack">
    <section class="card card--flush">
      <div class="card__head"><strong>Bugünün programı</strong></div>
      <?php if (empty($plan['items'])): ?>
        <div style="padding: 18px;"><?php render_empty('Plan hazırlanıyor', 'Birkaç saniye içinde günlük planın oluşturulacak.', '/dashboard.php', 'YENİLE'); ?></div>
      <?php else: ?>
        <?php foreach ($plan['items'] as $item):
            $st = (string)$item['status'];
            $icon = $st === 'done' ? '✓' : ($st === 'in_progress' ? '●' : '○');
            $cls = $st === 'done' ? 'is-done' : ($st === 'in_progress' ? 'is-progress' : 'is-pending');
        ?>
          <div class="plan-item <?= $cls ?>">
            <span class="plan-item__state" aria-hidden="true"><?= $icon ?></span>
            <span class="plan-item__title"><?= e((string)$item['title']) ?></span>
            <span class="plan-item__meta"><?= (int)$item['done_count'] ?>/<?= (int)$item['target_count'] ?> · <?= (int)$item['estimated_minutes'] ?> dk</span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <section class="card card--flush">
      <div class="card__head"><strong>En çok zorlandığın konular</strong></div>
      <?php if ($weakAreas === [] && $weakSkillList === []): ?>
        <div style="padding: 18px;">
          <p class="small muted" style="margin: 0;">Henüz yeterli veri yok. Birkaç alıştırma çözdükten sonra burada hata türlerini göreceksin.</p>
        </div>
      <?php else: ?>
        <?php foreach ($weakAreas as $wa): ?>
          <div class="plan-item">
            <span class="plan-item__state" aria-hidden="true">●</span>
            <span class="plan-item__title"><?= e((string)$wa['name']) ?></span>
            <span class="plan-item__meta"><?= (int)$wa['error_count'] ?> hata</span>
          </div>
        <?php endforeach; ?>
        <?php foreach ($weakSkillList as $ws): ?>
          <div class="plan-item">
            <span class="plan-item__state" aria-hidden="true">●</span>
            <span class="plan-item__title"><?= e((string)$ws['name']) ?></span>
            <a class="btn btn--sm btn--secondary btn--inline" href="/exercise.php?skill=<?= (int)$ws['id'] ?>">ÇALIŞ</a>
          </div>
        <?php endforeach; ?>
        <div style="padding: 14px 18px; border-top: 1px solid var(--line);">
          <a class="small" href="/weak-areas.php">Bütün zayıf alanları gör →</a>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<div class="grid grid-4" style="margin-bottom: 24px;">
  <div class="card">
    <p class="eyebrow">Genel mastery</p>
    <div class="num" style="font-size: 30px; color: var(--ink);"><?= $overallMastery ?>%</div>
    <p class="small muted" style="margin: 6px 0 0;"><?= (int)($overall['mastered'] ?? 0) ?> konu mastered</p>
  </div>
  <div class="card">
    <p class="eyebrow">Kelime</p>
    <div class="num" style="font-size: 30px; color: var(--ink);"><?= (int)($vocabStats['mastered'] ?? 0) ?></div>
    <p class="small muted" style="margin: 6px 0 0;">
      mastered · <?= (int)($vocabStats['learning'] ?? 0) ?> öğreniyorsun · toplam <?= $vocabTotal ?>
    </p>
  </div>
  <div class="card">
    <p class="eyebrow">Tekrar</p>
    <div class="num" style="font-size: 30px; color: var(--ink);"><?= (int)$counts['due'] ?></div>
    <p class="small muted" style="margin: 6px 0 0;">
      <?= (int)$counts['overdue'] ?> geciken · yaklaşık <?= (int)$counts['est_minutes'] ?> dk
    </p>
  </div>
  <div class="card">
    <p class="eyebrow">Bu hafta</p>
    <div class="num" style="font-size: 30px; color: var(--ink);"><?= (int)round($weekSeconds / 60) ?></div>
    <p class="small muted" style="margin: 6px 0 0;">dakika çalışma</p>
  </div>
</div>

<div class="grid grid-3">
  <section class="card">
    <p class="eyebrow">Mevcut modül</p>
    <?php if ($activeModule !== null): ?>
      <h3 style="margin-bottom: 8px;"><?= e((string)$activeModule['title']) ?></h3>
      <div class="progress-row">
        <?php render_progress((int)$activeModule['done'], max(1, (int)$activeModule['total']), '', 'Modül ilerlemesi'); ?>
        <span class="num"><?= (int)$activeModule['done'] ?>/<?= (int)$activeModule['total'] ?></span>
      </div>
      <a class="btn btn--sm btn--secondary btn--inline" style="margin-top: 14px;" href="/course.php">YOL HARİTASI</a>
    <?php else: ?>
      <p class="small">Bütün modülleri tamamladın. Tekrar ve karma quizlerle seviyeni koru.</p>
      <a class="btn btn--sm btn--secondary btn--inline" href="/quiz.php?mode=mixed">KARMA QUIZ</a>
    <?php endif; ?>
  </section>

  <section class="card">
    <p class="eyebrow">Telegram</p>
    <?php if ($telegram !== null): ?>
      <p class="small" style="margin-bottom: 12px;">
        <span class="badge badge--ok"><span aria-hidden="true">✓</span>BAĞLI</span>
        <?php if (!empty($telegram['username'])): ?>
          <span class="mono small">@<?= e((string)$telegram['username']) ?></span>
        <?php endif; ?>
      </p>
      <p class="small muted" style="margin: 0;">Günlük hatırlatmalar ve mini quizler Telegram'a gönderiliyor.</p>
    <?php else: ?>
      <p class="small">Hatırlatmaları ve mini quizleri Telegram'dan almak için hesabını bağla.</p>
      <a class="btn btn--sm btn--secondary btn--inline" href="/telegram.php">TELEGRAM'I BAĞLA</a>
    <?php endif; ?>
  </section>

  <section class="card">
    <p class="eyebrow">Sıradaki tekrar</p>
    <?php if ($counts['due'] > 0): ?>
      <p class="small"><strong><?= (int)$counts['due'] ?> kart</strong> şu anda hazır.</p>
      <a class="btn btn--sm btn--inline" href="/review.php?start=1" data-review-start>TEKRARA BAŞLA</a>
    <?php elseif ($nextReview !== null): ?>
      <p class="small">Bir sonraki tekrar: <strong><?= e(local_datetime((string)$nextReview, 'd.m.Y H:i', $tz)) ?></strong></p>
      <p class="small muted" style="margin: 0;">Aralıklı tekrar, unutmadan hemen önce hatırlatır.</p>
    <?php else: ?>
      <p class="small">Henüz tekrar kartın yok. İlk dersini tamamladığında kartların oluşacak.</p>
    <?php endif; ?>
  </section>
</div>

<section class="card card--flush" style="margin-top: 24px;">
  <div class="card__head"><strong>Son etkinlikler</strong></div>
  <?php if ($activity === []): ?>
    <div style="padding: 18px;">
      <p class="small muted" style="margin: 0;">Henüz etkinlik yok. İlk dersini tamamladığında burada görünecek.</p>
    </div>
  <?php else: ?>
    <?php foreach ($activity as $a): ?>
      <div class="plan-item">
        <span class="plan-item__state" aria-hidden="true">✓</span>
        <span class="plan-item__title"><?= e((string)$a['title']) ?></span>
        <span class="plan-item__meta"><?= e(local_datetime((string)$a['created_at'], 'd.m H:i', $tz)) ?><?= (int)$a['xp'] > 0 ? ' · +' . (int)$a['xp'] . ' XP' : '' ?></span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php if (setting_bool('donations_enabled', true)): ?>
  <div class="card" data-dismiss-key="support" data-dismiss-days="60" hidden style="margin-top: 24px; padding: 14px 18px;">
    <div class="row-between">
      <span class="small">AlmancaPro ücretsiz ve gönüllü bir projedir. İstersen destek olabilirsin.</span>
      <span class="row">
        <a class="btn btn--sm btn--secondary btn--inline" href="/support.php">DESTEK OL</a>
        <button class="btn btn--ghost btn--inline" type="button" data-dismiss-btn aria-label="Bu hatırlatmayı kapat">✕</button>
      </span>
    </div>
  </div>
<?php endif; ?>

<?php render_app_end(); ?>
