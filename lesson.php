<?php
/**
 * AlmancaPro - Ders ekrani (aciklama + kelime kartlari).
 * Kilit ve ilerleme sunucu tarafinda uygulanir.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];

$lessonId = input_int('id', 0);
$lesson = $lessonId > 0 ? db_row('SELECT * FROM lessons WHERE id = ? AND is_active = 1', [$lessonId]) : null;
if ($lesson === null) {
    http_response_code(404);
    flash('error', 'Ders bulunamadı.');
    redirect('/course.php');
}

/* --- Sunucu tarafi kilit kontrolu: URL ile atlanamaz --- */
if (!can_access_lesson($userId, $lesson)) {
    flash('warning', 'Bu derse henüz hazır değilsin. Eksik önkoşulları aşağıda görebilirsin.');
    redirect('/course.php?locked=' . $lessonId . '#lock');
}

$progress = lesson_progress($userId, $lessonId);
if ((string)$progress['status'] === 'not_started') {
    db_exec('UPDATE user_lesson_progress SET status = "in_progress", started_at = COALESCE(started_at, UTC_TIMESTAMP()), attempts = attempts + 1 WHERE user_id = ? AND lesson_id = ?', [$userId, $lessonId]);
}

$sections = db_all('SELECT * FROM lesson_sections WHERE lesson_id = ? ORDER BY sort_order, id', [$lessonId]);
$vocab = db_all(
    'SELECT v.*, COALESCE(m.status, "") AS user_status, COALESCE(m.mastery_score, 0) AS user_score
     FROM lesson_vocabulary lv
     JOIN vocabulary v ON v.id = lv.vocabulary_id
     LEFT JOIN user_vocabulary_mastery m ON m.vocabulary_id = v.id AND m.user_id = ?
     WHERE lv.lesson_id = ? AND v.is_active = 1
     ORDER BY lv.sort_order, v.id',
    [$userId, $lessonId]
);

$wordIndex = input_int('word', -1);
$totalSteps = 1 + count($vocab) + 1;
$currentStep = $wordIndex >= 0 ? min(count($vocab), $wordIndex + 1) + 1 : 1;

/* Kelime kartinda "zor geldi" isareti: tekrar araligi kisaltilir. */
if (is_post()) {
    csrf_require();
    $vocabId = input_int('vocabulary_id', 0);
    $difficulty = (string)input('difficulty', 'ok');
    if ($vocabId > 0) {
        vocab_mastery($userId, $vocabId);
        if ($difficulty === 'hard') {
            db_exec(
                'UPDATE user_vocabulary_mastery SET interval_minutes = 10, next_review_at = UTC_TIMESTAMP(),
                    status = IF(status = "mastered", status, "learning"), ease_factor = GREATEST(1.3, ease_factor - 0.15)
                 WHERE user_id = ? AND vocabulary_id = ?',
                [$userId, $vocabId]
            );
        } else {
            db_exec(
                'UPDATE user_vocabulary_mastery SET next_review_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)
                 WHERE user_id = ? AND vocabulary_id = ? AND attempts = 0',
                [$userId, $vocabId]
            );
        }
    }
    $nextWord = input_int('next_word', -1);
    if ($nextWord >= 0 && $nextWord < count($vocab)) {
        redirect('/lesson.php?id=' . $lessonId . '&word=' . $nextWord);
    }
    redirect('/quiz.php?lesson=' . $lessonId);
}

$levelLabel = strtoupper((string)$lesson['cefr_level']) . ' · ' . mb_strtoupper((string)$lesson['title'], 'UTF-8');

render_focus_start(e((string)$lesson['title']) . ' · ' . APP_NAME, []);
render_lesson_bar('/course.php', 'ÇIK', $currentStep, $totalSteps, str_limit($levelLabel, 40));
?>

<div class="lesson">
  <?php render_flashes(); ?>

  <?php if ($wordIndex < 0): ?>
    <p class="eyebrow"><?= e((string)$lesson['cefr_level']) ?> · <?= e(match ((string)$lesson['lesson_type']) {
        'grammar' => 'Dilbilgisi',
        'vocabulary' => 'Kelime',
        'pronunciation' => 'Telaffuz',
        'workplace' => 'İş Almancası',
        'daily_life' => 'Günlük hayat',
        default => 'Ders',
    }) ?></p>
    <h1 class="lesson__title"><?= e((string)$lesson['title']) ?></h1>
    <?php if (!empty($lesson['objective'])): ?>
      <p class="lesson__objective"><?= e((string)$lesson['objective']) ?></p>
    <?php endif; ?>

    <?php foreach ($sections as $sec):
        $type = (string)$sec['section_type'];
        $collapsible = (int)$sec['is_collapsible'] === 1;

        if ($type === 'example' || $type === 'dialogue'):
            $de = (string)($sec['example_de'] ?? '');
            $hl = (string)($sec['highlight'] ?? '');
            $deHtml = e($de);
            if ($hl !== '' && str_contains($de, $hl)) {
                $deHtml = str_replace(e($hl), '<mark>' . e($hl) . '</mark>', $deHtml);
            }
    ?>
      <div class="example-card">
        <?php if (!empty($sec['heading'])): ?><p class="eyebrow"><?= e((string)$sec['heading']) ?></p><?php endif; ?>
        <?php if (!empty($sec['body'])): ?><p class="small muted"><?= e((string)$sec['body']) ?></p><?php endif; ?>
        <?php if ($de !== ''): ?><div class="example-card__de"><?= $deHtml ?></div><?php endif; ?>
        <?php if (!empty($sec['example_tr'])): ?><div class="example-card__tr"><?= nl2br(e((string)$sec['example_tr'])) ?></div><?php endif; ?>
        <?php if ($de !== ''): ?>
          <button class="btn btn--sm btn--secondary btn--inline speak-btn" type="button" data-speak="<?= e($de) ?>" style="margin-top: 14px;">▶ DİNLE</button>
        <?php endif; ?>
      </div>

    <?php elseif ($collapsible): ?>
      <details class="acc">
        <summary><?= e((string)($sec['heading'] ?? 'Ayrıntı')) ?></summary>
        <div class="acc__body"><?= e_paragraphs((string)$sec['body']) ?></div>
      </details>

    <?php else: ?>
      <div class="lesson-section lesson-section--<?= e($type) ?>">
        <?php if (!empty($sec['heading'])): ?>
          <div class="lesson-section__head"><?= e((string)$sec['heading']) ?></div>
        <?php endif; ?>
        <div class="lesson-section__body"><?= e_paragraphs((string)$sec['body']) ?></div>
      </div>
    <?php endif; endforeach; ?>

    <div class="lesson-cta">
      <?php if ($vocab !== []): ?>
        <a class="btn btn--lg btn--inline" href="/lesson.php?id=<?= $lessonId ?>&amp;word=0">ANLADIM, KELİMELERE GEÇ</a>
      <?php else: ?>
        <a class="btn btn--lg btn--inline" href="/quiz.php?lesson=<?= $lessonId ?>">ANLADIM, TEST ET</a>
      <?php endif; ?>
    </div>
    <p class="lesson-note">
      Test atlanamaz. Bu konuyu gerçekten öğrendiğini göstermeden ders tamamlanmış sayılmaz.
    </p>

  <?php else:
    $index = max(0, min(count($vocab) - 1, $wordIndex));
    $v = $vocab[$index] ?? null;
    if ($v === null) {
        redirect('/quiz.php?lesson=' . $lessonId);
    }
    $headword = trim(((string)($v['article'] ?? '')) . ' ' . (string)$v['german']);
    $akkusativ = null;
    if (!empty($v['article'])) {
        $akkusativ = match ((string)$v['article']) {
            'der' => 'den ' . $v['german'],
            'die' => 'die ' . $v['german'],
            'das' => 'das ' . $v['german'],
            default => null,
        };
    }
  ?>
    <p class="eyebrow">Kelime <?= $index + 1 ?> / <?= count($vocab) ?></p>

    <div class="wordcard">
      <div class="wordcard__top">
        <?php if (!empty($v['article'])): ?>
          <?= artikel_badge((string)$v['article'], 'lg') ?>
          <span class="wordcard__gender"><?= e(match ((string)$v['article']) {
              'der' => 'eril (maskulin)',
              'die' => 'dişil (feminin)',
              'das' => 'nötr (neutrum)',
              default => '',
          }) ?></span>
        <?php else: ?>
          <span class="badge"><?= e(match ((string)$v['part_of_speech']) {
              'verb' => 'FİİL', 'adjective' => 'SIFAT', 'adverb' => 'ZARF',
              'preposition' => 'EDAT', 'conjunction' => 'BAĞLAÇ', 'pronoun' => 'ZAMİR',
              'numeral' => 'SAYI', 'phrase' => 'KALIP', 'article' => 'ARTİKEL',
              default => 'KELİME',
          }) ?></span>
        <?php endif; ?>
        <span class="badge badge--level"><?= e((string)$v['cefr_level']) ?></span>
        <?php if ((string)$v['user_status'] !== ''): ?>
          <?php render_mastery_badge((string)$v['user_status']); ?>
        <?php endif; ?>
      </div>

      <h1 class="wordcard__word"><?= e($headword) ?></h1>
      <p class="wordcard__tr"><?= e((string)$v['turkish']) ?></p>

      <div class="wordcard__meta">
        <?php if (!empty($v['pronunciation'])): ?>[<?= e((string)$v['pronunciation']) ?>]<?php endif; ?>
        <?php if (!empty($v['plural'])): ?> · çoğul: <?= e((string)$v['plural']) ?><?php endif; ?>
      </div>

      <button class="btn btn--sm btn--secondary btn--inline speak-btn" type="button" data-speak="<?= e($headword) ?>">▶ DİNLE</button>

      <?php if (!empty($v['article'])): ?>
        <dl class="wordcard__grid">
          <div class="wordcard__cell"><dt>Artikel</dt><dd><?= e((string)$v['article']) ?></dd></div>
          <div class="wordcard__cell"><dt>Çoğul</dt><dd><?= e((string)($v['plural'] ?? '—')) ?></dd></div>
          <div class="wordcard__cell"><dt>Akkusativ</dt><dd><?= e((string)($akkusativ ?? '—')) ?></dd></div>
        </dl>
      <?php elseif ((string)$v['part_of_speech'] === 'verb'): ?>
        <dl class="wordcard__grid">
          <div class="wordcard__cell"><dt>3. tekil</dt><dd><?= e((string)($v['third_person'] ?? '—')) ?></dd></div>
          <div class="wordcard__cell"><dt>Präteritum</dt><dd><?= e((string)($v['preterite'] ?? '—')) ?></dd></div>
          <div class="wordcard__cell"><dt>Partizip II</dt><dd><?= e(trim(((string)($v['auxiliary'] ?? '')) . ' ' . (string)($v['participle_ii'] ?? '')) ?: '—') ?></dd></div>
        </dl>
      <?php endif; ?>

      <?php if (!empty($v['requires_case']) || !empty($v['required_preposition'])): ?>
        <p class="small">
          <?php if (!empty($v['required_preposition'])): ?>
            <strong>Edat:</strong> <?= e((string)$v['required_preposition']) ?>
          <?php endif; ?>
          <?php if (!empty($v['requires_case'])): ?>
            <?= !empty($v['required_preposition']) ? ' · ' : '' ?><strong>İstediği hal:</strong> <?= e(ucfirst((string)$v['requires_case'])) ?>
          <?php endif; ?>
        </p>
      <?php endif; ?>

      <?php if (!empty($v['example_de'])): ?>
        <div class="wordcard__example">
          <div class="wordcard__example-de"><?= e((string)$v['example_de']) ?></div>
          <div class="wordcard__example-tr"><?= e((string)($v['example_tr'] ?? '')) ?></div>
        </div>
      <?php endif; ?>

      <?php if (!empty($v['memory_tip'])): ?>
        <details class="acc">
          <summary>Kolay hatırlama</summary>
          <div class="acc__body"><?= e((string)$v['memory_tip']) ?>
            <p class="small muted" style="margin-top: 10px;">Bu bir kural değil, yardımcı bir ezber yöntemidir.</p>
          </div>
        </details>
      <?php endif; ?>
      <?php if (!empty($v['usage_notes'])): ?>
        <details class="acc">
          <summary>Kullanım notu</summary>
          <div class="acc__body"><?= e((string)$v['usage_notes']) ?></div>
        </details>
      <?php endif; ?>
      <?php if (!empty($v['similar_word_note'])): ?>
        <details class="acc">
          <summary>Benzer kelimelerle farkı</summary>
          <div class="acc__body"><?= e((string)$v['similar_word_note']) ?></div>
        </details>
      <?php endif; ?>

      <form method="post" action="/lesson.php?id=<?= $lessonId ?>" class="wordcard__actions" data-guard>
        <?= csrf_field() ?>
        <input type="hidden" name="vocabulary_id" value="<?= (int)$v['id'] ?>">
        <input type="hidden" name="next_word" value="<?= $index + 1 < count($vocab) ? $index + 1 : -1 ?>">
        <button class="btn btn--secondary" type="submit" name="difficulty" value="hard">ZOR GELDİ</button>
        <button class="btn" type="submit" name="difficulty" value="ok">
          <?= $index + 1 < count($vocab) ? 'DEVAM ET' : 'TESTE GEÇ' ?>
        </button>
      </form>
    </div>

    <p class="lesson-note">
      "Zor geldi" dersen bu kelime tekrar kuyruğunda daha erken karşına çıkar.
    </p>
  <?php endif; ?>
</div>
<?php render_focus_end(); ?>
