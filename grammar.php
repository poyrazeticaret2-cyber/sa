<?php
/**
 * AlmancaPro - Dilbilgisi kutuphanesi.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];
$counts = due_review_counts($userId);

$topicId = input_int('topic', 0);
$level = (string)input('level', '');
$search = trim((string)input('q', ''));

if ($topicId > 0) {
    $topic = db_row(
        'SELECT gt.*, s.name AS skill_name, s.code AS skill_code, s.id AS skill_id,
                COALESCE(m.mastery_score, 0) AS mastery_score, COALESCE(m.status, "") AS mastery_status
         FROM grammar_topics gt
         LEFT JOIN skills s ON s.id = gt.skill_id
         LEFT JOIN user_skill_mastery m ON m.skill_id = gt.skill_id AND m.user_id = ?
         WHERE gt.id = ? AND gt.is_active = 1',
        [$userId, $topicId]
    );
    if ($topic === null) {
        flash('error', 'Konu bulunamadı.');
        redirect('/grammar.php');
    }
    $examples = db_all('SELECT * FROM grammar_examples WHERE grammar_topic_id = ? ORDER BY sort_order, id', [$topicId]);
    $lesson = !empty($topic['lesson_id']) ? db_row('SELECT * FROM lessons WHERE id = ?', [(int)$topic['lesson_id']]) : null;
    $lockState = $lesson !== null ? lesson_lock_state($userId, (int)$lesson['id']) : null;

    render_app_start($user, e((string)$topic['title']) . ' · ' . APP_NAME, [], ['review.php' => $counts['due']]);
    ?>
    <a class="small" href="/grammar.php">← Dilbilgisi kütüphanesi</a>
    <div class="row" style="gap: 10px; margin: 14px 0 10px;">
      <span class="badge badge--level"><?= e((string)$topic['cefr_level']) ?></span>
      <?php if ((string)$topic['mastery_status'] !== ''): ?>
        <?php render_mastery_badge((string)$topic['mastery_status']); ?>
        <span class="num small"><?= (int)$topic['mastery_score'] ?>%</span>
      <?php endif; ?>
    </div>
    <h1><?= e((string)$topic['title']) ?></h1>

    <div class="focus-area" style="margin: 0;">
      <?php
      $blocks = [
          ['what_is_it', 'Bu nedir?', 'explanation', false],
          ['why_used', 'Neden kullanılır?', 'why', false],
          ['tr_difference', 'Türkçeden farkı nedir?', 'tr_contrast', false],
          ['sentence_role', 'Cümledeki görevi', 'usage', true],
          ['how_to_recognize', 'Nasıl anlaşılır?', 'explanation', true],
          ['rule', 'Kural', 'rule', false],
          ['exceptions', 'İstisnalar', 'exception', true],
          ['common_mistake', 'En sık yapılan hata', 'common_mistake', true],
          ['memory_tip', 'Kolay hatırlama', 'memory_tip', true],
      ];
      foreach ($blocks as [$field, $heading, $cls, $collapsible]):
          $val = trim((string)($topic[$field] ?? ''));
          if ($val === '') { continue; }
          if ($collapsible): ?>
            <details class="acc">
              <summary><?= e($heading) ?></summary>
              <div class="acc__body"><?= e_paragraphs($val) ?></div>
            </details>
          <?php else: ?>
            <div class="lesson-section lesson-section--<?= e($cls) ?>" style="margin-bottom: 20px;">
              <div class="lesson-section__head"><?= e($heading) ?></div>
              <div class="lesson-section__body"><?= e_paragraphs($val) ?></div>
            </div>
          <?php endif;
      endforeach; ?>

      <?php if ($examples !== []): ?>
        <h2 style="margin-top: 26px;">Örnekler</h2>
        <?php foreach ($examples as $ex): ?>
          <div class="example-card">
            <div class="example-card__de"><?= e((string)$ex['de']) ?></div>
            <div class="example-card__tr"><?= e((string)$ex['tr']) ?></div>
            <?php if (!empty($ex['note'])): ?><p class="small muted" style="margin-top: 10px;"><?= e((string)$ex['note']) ?></p><?php endif; ?>
            <button class="btn btn--sm btn--secondary btn--inline speak-btn" type="button" data-speak="<?= e((string)$ex['de']) ?>" style="margin-top: 12px;">▶ DİNLE</button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <div class="row" style="margin-top: 26px;">
        <?php if (!empty($topic['skill_id'])): ?>
          <a class="btn btn--inline" href="/exercise.php?skill=<?= (int)$topic['skill_id'] ?>">MİNİ TEST</a>
        <?php endif; ?>
        <?php if ($lesson !== null && ($lockState === null || $lockState['unlocked'])): ?>
          <a class="btn btn--secondary btn--inline" href="/lesson.php?id=<?= (int)$lesson['id'] ?>">DERSİ AÇ</a>
        <?php elseif ($lesson !== null): ?>
          <a class="btn btn--secondary btn--inline" href="/course.php?locked=<?= (int)$lesson['id'] ?>#lock">DERS NEDEN KİLİTLİ?</a>
        <?php endif; ?>
      </div>
    </div>
    <?php
    render_app_end();
    exit;
}

/* --- Liste --- */
$params = [$userId];
$where = ['gt.is_active = 1'];
if (in_array($level, cefr_levels(), true)) {
    $where[] = 'gt.cefr_level = ?';
    $params[] = $level;
}
if ($search !== '') {
    $where[] = '(gt.title LIKE ? OR gt.keywords LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . mb_strtolower($search) . '%';
}

$topics = db_all(
    'SELECT gt.id, gt.title, gt.cefr_level, gt.lesson_id, gt.skill_id,
            COALESCE(m.mastery_score, 0) AS mastery_score, COALESCE(m.status, "") AS mastery_status
     FROM grammar_topics gt
     LEFT JOIN user_skill_mastery m ON m.skill_id = gt.skill_id AND m.user_id = ?
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY FIELD(gt.cefr_level,"A0","A1","A2","B1"), gt.sort_order',
    $params
);

/* Kilit durumlarini tek sorguda al */
$lockedLessons = [];
$lessonIds = array_filter(array_column($topics, 'lesson_id'));
if ($lessonIds !== []) {
    $rows = db_all(
        'SELECT lp.lesson_id, COUNT(*) AS missing
         FROM lesson_prerequisites lp
         LEFT JOIN user_skill_mastery m ON m.skill_id = lp.skill_id AND m.user_id = ?
         WHERE lp.lesson_id IN (' . implode(',', array_map('intval', $lessonIds)) . ')
           AND COALESCE(m.mastery_score, 0) < lp.required_mastery
         GROUP BY lp.lesson_id',
        [$userId]
    );
    foreach ($rows as $r) {
        $lockedLessons[(int)$r['lesson_id']] = (int)$r['missing'];
    }
}

$byLevel = [];
foreach ($topics as $t) {
    $byLevel[(string)$t['cefr_level']][] = $t;
}

render_app_start($user, 'Dilbilgisi · ' . APP_NAME, [], ['review.php' => $counts['due']]);
?>
<p class="eyebrow">Dilbilgisi kütüphanesi</p>
<h1>Dilbilgisi</h1>
<p class="muted">Her konu aynı yapıda anlatılır: bu nedir, neden kullanılır, Türkçeden farkı, kural, istisnalar, sık hata ve kolay hatırlama.</p>

<form method="get" action="/grammar.php" class="filter-bar" style="margin-top: 20px;">
  <label class="sr-only" for="q">Konu ara</label>
  <input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Konu ara" style="max-width: 260px;">
  <label class="sr-only" for="level">Seviye</label>
  <select id="level" name="level" data-autosubmit style="max-width: 140px;">
    <option value="">Tüm seviyeler</option>
    <?php foreach (cefr_levels() as $lv): ?>
      <option value="<?= e($lv) ?>"<?= $level === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn--sm btn--secondary btn--inline" type="submit">ARA</button>
</form>

<?php if ($topics === []): ?>
  <?php render_empty('Konu bulunamadı', 'Farklı bir arama veya seviye dene.', '/grammar.php', 'FİLTREYİ TEMİZLE'); ?>
<?php else: ?>
  <?php foreach (cefr_levels() as $lv):
      if (empty($byLevel[$lv])) { continue; } ?>
    <section style="margin-bottom: 30px;">
      <div class="row" style="gap: 12px; margin-bottom: 12px;">
        <span class="badge badge--level"><?= e($lv) ?></span>
        <h2 style="margin: 0; font-size: 20px;"><?= e(cefr_label($lv)) ?></h2>
      </div>
      <div class="card card--flush">
        <?php foreach ($byLevel[$lv] as $t):
            $missing = !empty($t['lesson_id']) ? ($lockedLessons[(int)$t['lesson_id']] ?? 0) : 0; ?>
          <a class="path-lesson" href="/grammar.php?topic=<?= (int)$t['id'] ?>">
            <span class="path-lesson__body">
              <span class="path-lesson__title"><?= e((string)$t['title']) ?></span>
              <span class="path-lesson__meta">
                <?php if ($missing > 0): ?>
                  Bu konunun dersi kilitli · <?= $missing ?> önkoşul eksik
                <?php elseif ((string)$t['mastery_status'] !== ''): ?>
                  Mastery <?= (int)$t['mastery_score'] ?>%
                <?php else: ?>
                  Henüz çalışılmadı
                <?php endif; ?>
              </span>
            </span>
            <?php if ((string)$t['mastery_status'] !== ''): ?>
              <?php render_mastery_badge((string)$t['mastery_status']); ?>
            <?php elseif ($missing > 0): ?>
              <span class="badge badge--muted"><span aria-hidden="true">○</span>DERS KİLİTLİ</span>
            <?php else: ?>
              <span class="badge"><span aria-hidden="true">○</span>OKUNABİLİR</span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php render_app_end(); ?>
