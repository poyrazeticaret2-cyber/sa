<?php
/**
 * AlmancaPro - Zayif alanlar ve hata analizi.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];
$counts = due_review_counts($userId);

$errorAreas = db_all(
    'SELECT ec.id, ec.code, ec.name, ec.description, ec.advice, COUNT(*) AS error_count,
            MAX(aec.created_at) AS last_error
     FROM answer_error_categories aec
     JOIN error_categories ec ON ec.id = aec.error_category_id
     WHERE aec.user_id = ?
     GROUP BY ec.id, ec.code, ec.name, ec.description, ec.advice
     ORDER BY error_count DESC',
    [$userId]
);

$weakSkills = db_all(
    'SELECT s.id, s.code, s.name, s.cefr_level, m.mastery_score, m.status, m.incorrect, m.attempts
     FROM user_skill_mastery m
     JOIN skills s ON s.id = m.skill_id
     WHERE m.user_id = ? AND m.attempts > 0 AND m.status IN ("weak","learning","overdue")
     ORDER BY m.mastery_score ASC, m.incorrect DESC
     LIMIT 20',
    [$userId]
);

$weakVocab = db_all(
    'SELECT v.id, v.german, v.article, v.plural, v.turkish, m.mastery_score, m.incorrect
     FROM user_vocabulary_mastery m
     JOIN vocabulary v ON v.id = m.vocabulary_id
     WHERE m.user_id = ? AND m.status = "weak"
     ORDER BY m.incorrect DESC, m.mastery_score ASC
     LIMIT 15',
    [$userId]
);

/* Bir hata turune bagli skill'ler */
$focusCode = (string)input('focus', '');
$focusSkills = [];
if ($focusCode !== '') {
    $focusSkills = db_all(
        'SELECT s.id, s.name, COUNT(*) AS errors
         FROM answer_error_categories aec
         JOIN error_categories ec ON ec.id = aec.error_category_id
         JOIN skills s ON s.id = aec.skill_id
         WHERE aec.user_id = ? AND ec.code = ?
         GROUP BY s.id, s.name ORDER BY errors DESC LIMIT 8',
        [$userId, $focusCode]
    );
}

render_app_start($user, 'Zayıf Alanlar · ' . APP_NAME, [], ['review.php' => $counts['due']]);
?>
<p class="eyebrow">Hata analizi</p>
<h1>En çok zorlandığın alanlar</h1>
<p class="muted">Her yanlış cevabın türü belirlenir. Aynı hatayı tekrarlıyorsan sebebi burada görünür.</p>

<?php if ($errorAreas === [] && $weakSkills === []): ?>
  <div style="margin-top: 22px;">
    <?php render_empty('Henüz zayıf alan yok', 'Birkaç alıştırma çözdükten sonra hata türlerin burada listelenecek.', '/course.php', 'DERSLERE DEVAM ET'); ?>
  </div>
<?php else: ?>

  <?php if ($errorAreas !== []): ?>
    <section style="margin-top: 24px;">
      <h2>Hata türleri</h2>
      <div class="card card--flush">
        <?php foreach ($errorAreas as $ea): ?>
          <div class="plan-item" style="align-items: flex-start; padding: 16px 18px;">
            <span class="plan-item__state" aria-hidden="true">●</span>
            <span class="plan-item__title">
              <strong><?= e((string)$ea['name']) ?></strong>
              <span class="small muted" style="display: block; margin-top: 4px;"><?= e((string)$ea['advice']) ?></span>
            </span>
            <span class="plan-item__meta"><?= (int)$ea['error_count'] ?> hata</span>
            <a class="btn btn--sm btn--secondary btn--inline" href="/weak-areas.php?focus=<?= e((string)$ea['code']) ?>#focus">İLGİLİ KONULAR</a>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($focusSkills !== []): ?>
    <section id="focus" class="card card--warn" style="margin-top: 22px;">
      <p class="eyebrow">Bu hata türünün geçtiği konular</p>
      <?php foreach ($focusSkills as $fs): ?>
        <div class="row-between" style="padding: 10px 0; border-bottom: 1px solid var(--line);">
          <span><?= e((string)$fs['name']) ?> <span class="small muted">· <?= (int)$fs['errors'] ?> hata</span></span>
          <a class="btn btn--sm btn--secondary btn--inline" href="/exercise.php?skill=<?= (int)$fs['id'] ?>">ÇALIŞ</a>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <?php if ($weakSkills !== []): ?>
    <section style="margin-top: 26px;">
      <h2>Güçlendirilmesi gereken konular</h2>
      <div class="card card--flush">
        <?php foreach ($weakSkills as $ws): ?>
          <div class="plan-item">
            <span class="plan-item__state" aria-hidden="true">●</span>
            <span class="plan-item__title"><?= e((string)$ws['name']) ?>
              <span class="badge badge--level" style="margin-left: 8px;"><?= e((string)$ws['cefr_level']) ?></span>
            </span>
            <span class="plan-item__meta"><?= (int)$ws['mastery_score'] ?>% · <?= (int)$ws['incorrect'] ?> hata</span>
            <a class="btn btn--sm btn--inline" href="/exercise.php?skill=<?= (int)$ws['id'] ?>">ÇALIŞ</a>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($weakVocab !== []): ?>
    <section style="margin-top: 26px;">
      <h2>Zorlandığın kelimeler</h2>
      <div class="card card--flush">
        <div class="vocab-list">
          <?php foreach ($weakVocab as $v): ?>
            <div class="vocab-row">
              <?= artikel_badge((string)($v['article'] ?? '')) ?>
              <a class="vocab-row__word" href="/vocabulary.php?word=<?= (int)$v['id'] ?>"><?= e((string)$v['german']) ?></a>
              <?php if (!empty($v['plural'])): ?><span class="vocab-row__plural"><?= e((string)$v['plural']) ?></span><?php endif; ?>
              <span class="vocab-row__tr"><?= e((string)$v['turkish']) ?></span>
              <span class="num small"><?= (int)$v['mastery_score'] ?>%</span>
              <span class="small muted"><?= (int)$v['incorrect'] ?> hata</span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="row" style="margin-top: 16px;">
        <a class="btn btn--inline" href="/review.php?start=1">BU KELİMELERİ TEKRAR ET</a>
      </div>
    </section>
  <?php endif; ?>
<?php endif; ?>

<?php render_app_end(); ?>
