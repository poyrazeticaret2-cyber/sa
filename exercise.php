<?php
/**
 * AlmancaPro - Tek bir beceriye odakli calisma (remediation).
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];

$skillId = input_int('skill', 0);
$skill = $skillId > 0 ? db_row('SELECT * FROM skills WHERE id = ? AND is_active = 1', [$skillId]) : null;
if ($skill === null) {
    flash('error', 'Konu bulunamadı.');
    redirect('/weak-areas.php');
}

$mastery = db_row('SELECT * FROM user_skill_mastery WHERE user_id = ? AND skill_id = ?', [$userId, $skillId]);
$calc = $mastery !== null ? compute_skill_mastery($mastery, $skill) : ['score' => 0, 'status' => 'introduced', 'missing' => ['Bu konuda henüz test edilmedin.']];

/* Konuyu anlatan dersler */
$lessons = db_all(
    'SELECT l.id, l.title, l.cefr_level FROM lesson_skills ls
     JOIN lessons l ON l.id = ls.lesson_id
     WHERE ls.skill_id = ? AND l.is_active = 1 ORDER BY l.sort_order LIMIT 4',
    [$skillId]
);
$topic = db_row('SELECT * FROM grammar_topics WHERE skill_id = ? LIMIT 1', [$skillId]);

if (is_post()) {
    csrf_require();
    $open = db_row('SELECT id FROM study_sessions WHERE user_id = ? AND skill_id = ? AND status = "active" ORDER BY id DESC LIMIT 1', [$userId, $skillId]);
    if ($open !== null) {
        redirect('/quiz.php?session=' . (int)$open['id']);
    }
    $items = build_remediation($userId, $skillId, 8);
    if ($items === []) {
        flash('info', 'Bu konu için şu anda alıştırma bulunamadı.');
        redirect('/weak-areas.php');
    }
    $sessionId = create_session($userId, 'remediation', $items, null, $skillId);
    redirect('/quiz.php?session=' . $sessionId);
}

$axes = [
    ['Tanıma', (int)($mastery['recognition_ok'] ?? 0), 1],
    ['Aktif hatırlama', (int)($mastery['recall_ok'] ?? 0), 2],
    ['Cümle üretme', (int)($mastery['production_ok'] ?? 0), (int)$skill['requires_production'] === 1 ? 1 : 0],
    ['Yazım', (int)($mastery['spelling_ok'] ?? 0), (int)$skill['requires_spelling'] === 1 ? 1 : 0],
    ['Gecikmeli hatırlama', (int)($mastery['delayed_ok'] ?? 0), 1],
    ['Farklı bağlam', (int)($mastery['context_variants'] ?? 0), 2],
];

render_app_start($user, e((string)$skill['name']) . ' · ' . APP_NAME);
?>
<p class="eyebrow"><?= e((string)$skill['cefr_level']) ?> · Konu çalışması</p>
<h1><?= e((string)$skill['name']) ?></h1>
<?php if (!empty($skill['description'])): ?>
  <p class="muted"><?= e((string)$skill['description']) ?></p>
<?php endif; ?>

<div class="grid grid-main" style="margin-top: 22px;">
  <div class="stack">
    <div class="mastery">
      <div class="mastery__head">
        <div>
          <p class="eyebrow" style="margin: 0;">Mastery</p>
          <?php render_mastery_badge((string)$calc['status']); ?>
        </div>
        <span class="mastery__score"><?= (int)$calc['score'] ?>%</span>
      </div>
      <div class="mastery__axes">
        <?php foreach ($axes as [$label, $have, $need]):
            if ($need === 0) { continue; }
            $state = $have >= $need ? 'is-strong' : ($have > 0 ? 'is-progress' : 'is-untested');
            $text = $have >= $need ? '✓ GÜÇLÜ' : ($have > 0 ? '● GELİŞİYOR' : '○ TEST EDİLMEDİ');
        ?>
          <div class="mastery__axis <?= $state ?>">
            <span class="mastery__axis-label"><?= e($label) ?></span>
            <span class="num small"><?= $have ?>/<?= $need ?></span>
            <span class="mastery__axis-state"><?= $text ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($calc['missing'] !== []): ?>
        <div class="mastery__missing">
          <strong>Mastered olması için gerekenler:</strong>
          <ul><?php foreach ($calc['missing'] as $m): ?><li><?= e((string)$m) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($mastery !== null && (int)$mastery['attempts'] > 0): ?>
      <div class="hairline-grid grid-4">
        <div style="padding: 16px;"><div class="num" style="font-size: 20px; color: var(--ink);"><?= (int)$mastery['attempts'] ?></div><div class="small muted">deneme</div></div>
        <div style="padding: 16px;"><div class="num" style="font-size: 20px; color: var(--success);"><?= (int)$mastery['correct'] ?></div><div class="small muted">doğru</div></div>
        <div style="padding: 16px;"><div class="num" style="font-size: 20px; color: var(--danger);"><?= (int)$mastery['incorrect'] ?></div><div class="small muted">hata</div></div>
        <div style="padding: 16px;"><div class="num" style="font-size: 20px; color: var(--ink);"><?= (int)$mastery['consecutive_correct'] ?></div><div class="small muted">üst üste doğru</div></div>
      </div>
    <?php endif; ?>

    <form method="post" action="/exercise.php?skill=<?= $skillId ?>" data-guard>
      <?= csrf_field() ?>
      <button class="btn btn--lg btn--block" type="submit">BU KONUYU ÇALIŞ (8 SORU)</button>
    </form>
  </div>

  <div class="stack">
    <?php if ($topic !== null): ?>
      <div class="card">
        <p class="eyebrow">Kural hatırlatması</p>
        <h3 style="font-size: 16px;"><?= e((string)$topic['title']) ?></h3>
        <?php if (!empty($topic['rule'])): ?>
          <div class="small" style="white-space: pre-wrap;"><?= e(str_limit((string)$topic['rule'], 420)) ?></div>
        <?php endif; ?>
        <a class="btn btn--sm btn--secondary btn--inline" style="margin-top: 12px;" href="/grammar.php?topic=<?= (int)$topic['id'] ?>">KONUYU AÇ</a>
      </div>
    <?php endif; ?>

    <?php if ($lessons !== []): ?>
      <div class="card card--flush">
        <div class="card__head"><strong>İlgili dersler</strong></div>
        <?php foreach ($lessons as $l): ?>
          <a class="path-lesson" href="/lesson.php?id=<?= (int)$l['id'] ?>">
            <span class="path-lesson__body">
              <span class="path-lesson__title"><?= e((string)$l['title']) ?></span>
              <span class="path-lesson__meta"><?= e((string)$l['cefr_level']) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php render_app_end(); ?>
