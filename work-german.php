<?php
/**
 * AlmancaPro - Is Almancasi modulu.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];
$counts = due_review_counts($userId);

$lessons = db_all(
    'SELECT l.*, COALESCE(p.status, "not_started") AS progress_status, COALESCE(p.best_score, 0) AS best_score
     FROM lessons l
     LEFT JOIN user_lesson_progress p ON p.lesson_id = l.id AND p.user_id = ?
     WHERE l.is_active = 1 AND l.lesson_type IN ("workplace","daily_life")
     ORDER BY FIELD(l.cefr_level,"A0","A1","A2","B1"), l.sort_order',
    [$userId]
);

$lockMap = [];
$ids = array_column($lessons, 'id');
if ($ids !== []) {
    $rows = db_all(
        'SELECT lp.lesson_id, COUNT(*) AS missing
         FROM lesson_prerequisites lp
         LEFT JOIN user_skill_mastery m ON m.skill_id = lp.skill_id AND m.user_id = ?
         WHERE lp.lesson_id IN (' . implode(',', array_map('intval', $ids)) . ')
           AND COALESCE(m.mastery_score, 0) < lp.required_mastery
         GROUP BY lp.lesson_id',
        [$userId]
    );
    foreach ($rows as $r) {
        $lockMap[(int)$r['lesson_id']] = (int)$r['missing'];
    }
}

$workVocab = db_all(
    'SELECT v.id, v.german, v.article, v.plural, v.turkish, v.cefr_level,
            COALESCE(m.status, "") AS user_status, COALESCE(m.mastery_score, 0) AS score
     FROM vocabulary v
     LEFT JOIN user_vocabulary_mastery m ON m.vocabulary_id = v.id AND m.user_id = ?
     WHERE v.is_active = 1 AND v.topic IN ("arbeit","beruf","meeting","telefon","behoerde")
     ORDER BY FIELD(v.cefr_level,"A0","A1","A2","B1"), v.german
     LIMIT 60',
    [$userId]
);

$scenarios = db_all(
    'SELECT id, slug, title, cefr_level FROM scenarios WHERE is_active = 1 AND category = "workplace" ORDER BY sort_order'
);

$byLevel = [];
foreach ($lessons as $l) {
    $byLevel[(string)$l['cefr_level']][] = $l;
}

render_app_start($user, 'İş Almancası · ' . APP_NAME, [], ['review.php' => $counts['due']]);
?>
<p class="eyebrow">İş Almancası</p>
<h1>İş yerinde Almanca</h1>
<p class="muted">
  İlk gün, çalışma saatleri, vardiya, mola, hastalık bildirimi, izin talebi, toplantı dili, iş güvenliği ve sözleşme kelimeleri.
</p>

<div class="alert alert--info" style="margin-top: 18px;">
  <span class="alert__icon" aria-hidden="true">●</span>
  <span>Bu bölüm <strong>dil öğretir</strong>. Sözleşme, izin hakkı veya iş hukuku konularında hukuki tavsiye vermez;
    hukuki durumun için iş sözleşmene bak veya yetkili bir danışmana sor.</span>
</div>

<?php foreach (cefr_levels() as $lv):
    if (empty($byLevel[$lv])) { continue; } ?>
  <section style="margin-top: 28px;">
    <div class="row" style="gap: 12px; margin-bottom: 12px;">
      <span class="badge badge--level"><?= e($lv) ?></span>
      <h2 style="margin: 0; font-size: 20px;">İş ve günlük hayat üniteleri</h2>
    </div>
    <div class="card card--flush">
      <?php foreach ($byLevel[$lv] as $l):
          $missing = $lockMap[(int)$l['id']] ?? 0;
          $done = (string)$l['progress_status'] === 'completed';
          $href = $missing > 0 ? '/course.php?locked=' . (int)$l['id'] . '#lock' : '/lesson.php?id=' . (int)$l['id'];
      ?>
        <a class="path-lesson<?= $missing > 0 ? ' path-lesson--locked' : '' ?>" href="<?= e($href) ?>">
          <span class="path-lesson__body">
            <span class="path-lesson__title"><?= e((string)$l['title']) ?></span>
            <span class="path-lesson__meta">
              <?= (int)$l['estimated_minutes'] ?> dk
              <?php if ($missing > 0): ?> · <?= $missing ?> önkoşul eksik
              <?php elseif ($done): ?> · mastery <?= (int)$l['best_score'] ?>%
              <?php endif; ?>
            </span>
          </span>
          <?php if ($done): ?>
            <span class="badge badge--ok"><span aria-hidden="true">✓</span>TAMAMLANDI</span>
          <?php elseif ($missing > 0): ?>
            <span class="badge badge--muted"><span aria-hidden="true">○</span>KİLİTLİ</span>
          <?php else: ?>
            <span class="badge"><span aria-hidden="true">○</span>HAZIR</span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>

<?php if ($scenarios !== []): ?>
  <section style="margin-top: 28px;">
    <h2>İş senaryoları</h2>
    <div class="grid grid-2">
      <?php foreach ($scenarios as $sc): ?>
        <a class="card" href="/scenario.php?id=<?= (int)$sc['id'] ?>">
          <div class="row" style="gap: 10px; margin-bottom: 8px;">
            <span class="badge badge--level"><?= e((string)$sc['cefr_level']) ?></span>
          </div>
          <h3 style="font-size: 16px; margin-bottom: 6px;"><?= e((string)$sc['title']) ?></h3>
          <p class="small muted" style="margin: 0;">Gerçek bir iş yeri diyaloğunda doğru cevabı seç, geri bildirim al.</p>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($workVocab !== []): ?>
  <section style="margin-top: 28px;">
    <h2>İş Almancası kelimeleri</h2>
    <div class="card card--flush">
      <div class="vocab-list">
        <?php foreach ($workVocab as $v): ?>
          <div class="vocab-row">
            <?= artikel_badge((string)($v['article'] ?? '')) ?>
            <a class="vocab-row__word" href="/vocabulary.php?word=<?= (int)$v['id'] ?>"><?= e((string)$v['german']) ?></a>
            <?php if (!empty($v['plural'])): ?><span class="vocab-row__plural"><?= e((string)$v['plural']) ?></span><?php endif; ?>
            <span class="vocab-row__tr"><?= e((string)$v['turkish']) ?></span>
            <span class="badge badge--level"><?= e((string)$v['cefr_level']) ?></span>
            <?php if ((string)$v['user_status'] !== ''): ?>
              <?php render_mastery_badge((string)$v['user_status']); ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php render_app_end(); ?>
