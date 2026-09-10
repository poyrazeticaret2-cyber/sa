<?php
/**
 * AlmancaPro - Quiz / alistirma motoru.
 *
 * Butun oturum turleri (ders quizi, tekrar, remediation, checkpoint, karma quiz)
 * bu tek motor uzerinden calisir. Puanlama ve mastery kararlari sunucudadir.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];

/* ==================================================================
 * 1) Cevap gonderimi
 * ================================================================== */
if (is_post() && (string)input('action', '') === 'answer') {
    csrf_require();

    $sessionId = input_int('session_id', 0);
    $exerciseId = input_int('exercise_id', 0);
    $itemId = input_int('item_id', 0);
    $answer = (string)($_POST['answer'] ?? '');
    $responseMs = input_int('response_ms', 0);

    $session = db_row('SELECT * FROM study_sessions WHERE id = ? AND user_id = ?', [$sessionId, $userId]);
    if ($session === null) {
        if (wants_json()) {
            json_response(false, 'Oturum bulunamadı.', [], [], 404);
        }
        redirect('/dashboard.php');
    }
    if ((string)$session['status'] !== 'active') {
        if (wants_json()) {
            json_response(false, 'Bu oturum tamamlanmış.', ['next_url' => '/quiz.php?session=' . $sessionId], [], 409);
        }
        redirect('/quiz.php?session=' . $sessionId);
    }

    $item = db_row('SELECT * FROM session_items WHERE id = ? AND session_id = ?', [$itemId, $sessionId]);
    if ($item === null || (int)$item['exercise_id'] !== $exerciseId) {
        if (wants_json()) {
            json_response(false, 'Soru eşleşmedi. Sayfayı yenile.', [], [], 409);
        }
        redirect('/quiz.php?session=' . $sessionId);
    }
    if (!in_array((string)$item['status'], ['pending', 'requeued'], true)) {
        /* Cift gonderim korumasi */
        if (wants_json()) {
            json_response(false, 'Bu soru zaten cevaplandı.', ['next_url' => '/quiz.php?session=' . $sessionId], [], 409);
        }
        redirect('/quiz.php?session=' . $sessionId);
    }

    $exercise = db_row('SELECT * FROM exercises WHERE id = ?', [$exerciseId]);
    if ($exercise === null) {
        db_exec('UPDATE session_items SET status = "correct" WHERE id = ?', [$itemId]);
        redirect('/quiz.php?session=' . $sessionId);
    }

    $eval = record_attempt($userId, $exercise, $answer, $sessionId, 'web', $responseMs > 0 ? $responseMs : null);

    db_exec(
        'UPDATE session_items SET status = ?, attempt_count = attempt_count + 1, answered_at = UTC_TIMESTAMP() WHERE id = ?',
        [$eval['correct'] ? 'correct' : 'wrong', $itemId]
    );

    $queueNote = '';
    if (!$eval['correct']) {
        $repeats = (int)db_value('SELECT COUNT(*) FROM session_items WHERE session_id = ? AND exercise_id = ?', [$sessionId, $exerciseId], 0);
        if ($repeats < 3) {
            session_requeue($sessionId, $exerciseId, 2);
            $queueNote = 'Bu soru tekrar kuyruğuna eklendi. Bu oturumda 2 soru sonra tekrar karşına çıkacak.';
        } else {
            $queueNote = 'Bu konu zayıf alanlarına eklendi; sonraki tekrarlarda daha sık karşına çıkacak.';
        }
    }

    /* Mastery aciklamasi */
    $masteryNote = '';
    if (!empty($eval['mastery'])) {
        $m = $eval['mastery'];
        $status = (string)($m['status'] ?? '');
        $score = (int)($m['score'] ?? 0);
        $missing = $m['missing'] ?? [];
        if ($status === 'mastered') {
            $masteryNote = 'Bu konu artık mastered (%' . $score . '). Yine de uzun aralıklarla tekrar karşına çıkacak.';
        } elseif ($missing !== []) {
            $masteryNote = 'Mastery %' . $score . '. Tamamlanması için gereken: ' . implode(', ', array_slice($missing, 0, 3)) . '.';
        } else {
            $masteryNote = 'Mastery %' . $score . '.';
        }
    }

    $progress = session_progress($sessionId);
    $data = [
        'correct'        => $eval['correct'],
        'score'          => $eval['score'],
        'correct_answer' => $eval['correct_answer'],
        'explanation'    => $eval['explanation'],
        'note'           => $eval['note'],
        'memory_hint'    => (string)($exercise['memory_hint'] ?? ''),
        'breakdown'      => $eval['breakdown'],
        'mastery_note'   => $masteryNote,
        'queue_note'     => $queueNote,
        'progress'       => $progress,
        'next_url'       => '/quiz.php?session=' . $sessionId,
    ];

    update_streak($userId);

    if (wants_json()) {
        json_response(true, '', $data);
    }

    /* JS olmadan: geri bildirimi oturumda saklayip yonlendir. */
    $data['user_answer'] = mb_substr($answer, 0, 300);
    $_SESSION['quiz_feedback'][$sessionId] = $data;
    redirect('/quiz.php?session=' . $sessionId . '&fb=1');
}

/* ==================================================================
 * 2) Oturum secimi / olusturma
 * ================================================================== */
$sessionId = input_int('session', 0);
$session = null;

if ($sessionId > 0) {
    $session = db_row('SELECT * FROM study_sessions WHERE id = ? AND user_id = ?', [$sessionId, $userId]);
    if ($session === null) {
        flash('error', 'Oturum bulunamadı.');
        redirect('/dashboard.php');
    }
} else {
    $lessonId = input_int('lesson', 0);
    $skillId = input_int('skill', 0);
    $mode = (string)input('mode', '');

    if ($lessonId > 0) {
        $lesson = db_row('SELECT * FROM lessons WHERE id = ? AND is_active = 1', [$lessonId]);
        if ($lesson === null || !can_access_lesson($userId, $lesson)) {
            flash('warning', 'Bu derse henüz hazır değilsin.');
            redirect('/course.php?locked=' . $lessonId . '#lock');
        }
        /* Ayni ders icin acik oturum varsa devam et. */
        $open = db_row('SELECT id FROM study_sessions WHERE user_id = ? AND lesson_id = ? AND status = "active" ORDER BY id DESC LIMIT 1', [$userId, $lessonId]);
        if ($open !== null) {
            redirect('/quiz.php?session=' . (int)$open['id']);
        }
        $items = build_lesson_quiz($userId, $lessonId, 10);
        if ($items === []) {
            flash('info', 'Bu ders için alıştırma bulunamadı.');
            redirect('/lesson.php?id=' . $lessonId);
        }
        $sessionId = create_session($userId, 'lesson', $items, $lessonId);
        redirect('/quiz.php?session=' . $sessionId);
    }

    if ($skillId > 0) {
        redirect('/exercise.php?skill=' . $skillId);
    }

    if ($mode === 'mixed' || $mode === '') {
        $open = db_row('SELECT id FROM study_sessions WHERE user_id = ? AND session_type = "quiz" AND status = "active" ORDER BY id DESC LIMIT 1', [$userId]);
        if ($open !== null) {
            redirect('/quiz.php?session=' . (int)$open['id']);
        }
        $items = build_mixed_quiz($userId, $user, 10);
        if ($items === []) {
            flash('info', 'Şu anda uygun soru bulunamadı. Önce bir ders tamamla.');
            redirect('/course.php');
        }
        $sessionId = create_session($userId, 'quiz', $items);
        redirect('/quiz.php?session=' . $sessionId);
    }

    redirect('/dashboard.php');
}

/* ==================================================================
 * 3) Oturum durumu
 * ================================================================== */
$progress = session_progress($sessionId);
$feedback = null;
if (input_int('fb', 0) === 1 && !empty($_SESSION['quiz_feedback'][$sessionId])) {
    $feedback = $_SESSION['quiz_feedback'][$sessionId];
    unset($_SESSION['quiz_feedback'][$sessionId]);
}

$exercise = $feedback === null ? session_next_item($sessionId) : null;

/* Oturum bitti mi? */
if ($exercise === null && $feedback === null) {
    if ((string)$session['status'] === 'active') {
        close_session($sessionId);
        $session = db_row('SELECT * FROM study_sessions WHERE id = ?', [$sessionId]);

        $xp = max(5, $progress['correct'] * 2);
        award_activity($userId, 'session_completed', (int)($session['lesson_id'] ?? 0) ?: null,
            match ((string)$session['session_type']) {
                'lesson' => 'Ders quizi tamamlandı',
                'review' => 'Tekrar oturumu tamamlandı',
                'remediation' => 'Zayıf konu çalışması tamamlandı',
                'checkpoint' => 'Mastery kontrolü tamamlandı',
                default => 'Quiz tamamlandı',
            },
            $xp, (int)$session['duration_seconds']);
        update_streak($userId);

        if (!empty($session['lesson_id'])) {
            mark_lesson_completed_if_ready($userId, (int)$session['lesson_id']);
        }
    }
    render_quiz_summary($user, $session, $progress);
    exit;
}

/* ==================================================================
 * 4) Soru ekrani
 * ================================================================== */
$sessionLabel = match ((string)$session['session_type']) {
    'lesson' => 'DERS TESTİ',
    'review' => 'TEKRAR',
    'remediation' => 'ZAYIF KONU',
    'checkpoint' => 'MASTERY KONTROLÜ',
    'intensive' => 'YOĞUN PROGRAM',
    default => 'QUIZ',
};
$exitHref = !empty($session['lesson_id']) ? '/lesson.php?id=' . (int)$session['lesson_id'] : '/dashboard.php';
$step = $progress['answered'] + 1;

render_focus_start('Quiz · ' . APP_NAME, ['js' => ['quiz.js'], 'noindex' => true]);
render_lesson_bar($exitHref, 'ÇIK', min($step, $progress['total']), $progress['total'], $sessionLabel);
?>

<div class="quiz" data-quiz
     data-session="<?= (int)$sessionId ?>"
     data-exercise="<?= $exercise !== null ? (int)$exercise['id'] : 0 ?>"
     data-item="<?= $exercise !== null ? (int)$exercise['session_item_id'] : 0 ?>"
     data-endpoint="/quiz.php">

  <?php render_flashes(); ?>

  <?php if ($feedback !== null): ?>
    <?php render_static_feedback($feedback); ?>

  <?php else:
      $type = (string)$exercise['exercise_type'];
      $isChoice = $exercise['options'] !== [];
      $vocab = $exercise['vocabulary'] ?? null;
  ?>
    <p class="eyebrow">
      Soru <?= (int)$step ?> / <?= (int)$progress['total'] ?>
      · <?= e(match ((string)$exercise['mode']) {
          'recognition' => 'Tanıma',
          'recall' => 'Aktif hatırlama',
          'production' => 'Üretim',
          'spelling' => 'Yazım',
          'delayed' => 'Gecikmeli hatırlama',
          default => 'Uygulama',
      }) ?>
    </p>

    <h1 class="quiz__question"><?= e((string)$exercise['prompt']) ?></h1>

    <?php if (!empty($exercise['context'])): ?>
      <div class="quiz__context"><?= e((string)$exercise['context']) ?></div>
    <?php endif; ?>

    <?php if ($vocab !== null && !empty($vocab['article']) && in_array($type, ['plural', 'fill_blank'], true)): ?>
      <div style="margin-bottom: 16px;"><?= artikel_badge((string)$vocab['article']) ?></div>
    <?php endif; ?>

    <?php if ($isChoice): ?>
      <div class="quiz__options" data-quiz-options role="group" aria-label="Cevap seçenekleri">
        <?php foreach ($exercise['options'] as $i => $opt): ?>
          <form method="post" action="/quiz.php" style="margin: 0;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="answer">
            <input type="hidden" name="session_id" value="<?= (int)$sessionId ?>">
            <input type="hidden" name="exercise_id" value="<?= (int)$exercise['id'] ?>">
            <input type="hidden" name="item_id" value="<?= (int)$exercise['session_item_id'] ?>">
            <input type="hidden" name="answer" value="<?= e((string)$opt['option_text']) ?>">
            <button class="quiz-option" type="submit" data-value="<?= e((string)$opt['option_text']) ?>">
              <span class="quiz-option__letter" aria-hidden="true"><?= e(chr(65 + $i)) ?></span>
              <span class="quiz-option__text"><?= e((string)$opt['option_text']) ?></span>
            </button>
          </form>
        <?php endforeach; ?>
      </div>

    <?php else: ?>
      <form method="post" action="/quiz.php" data-quiz-form>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="answer">
        <input type="hidden" name="session_id" value="<?= (int)$sessionId ?>">
        <input type="hidden" name="exercise_id" value="<?= (int)$exercise['id'] ?>">
        <input type="hidden" name="item_id" value="<?= (int)$exercise['session_item_id'] ?>">
        <div class="field">
          <label for="answer" class="sr-only">Cevabın</label>
          <input id="answer" name="answer" class="quiz__input" type="text" autocomplete="off"
                 autocapitalize="off" spellcheck="false" data-quiz-input required
                 placeholder="Cevabını yaz">
          <div class="hint">Almanca özel karakter yazamıyorsan ae, oe, ue ve ss kullanabilirsin.</div>
        </div>
        <div class="quiz__submit-row">
          <button class="btn btn--lg" type="submit" data-quiz-submit>CEVAPLA</button>
        </div>
      </form>
    <?php endif; ?>

    <div data-quiz-feedback aria-live="polite"></div>
  <?php endif; ?>
</div>

<?php
render_focus_end();

/* ==================================================================
 * Yardimci gorunumler
 * ================================================================== */

/** JS kapaliyken geri bildirim blogu. */
function render_static_feedback(array $d): void
{
    $ok = !empty($d['correct']);
    ?>
    <div class="feedback <?= $ok ? 'feedback--ok' : 'feedback--bad' ?>">
      <div class="feedback__head">
        <span aria-hidden="true"><?= $ok ? '✓' : '✕' ?></span>
        <span><?= $ok ? 'Doğru' : 'Henüz değil' ?></span>
      </div>
      <div class="feedback__body">
        <?php if (!$ok): ?>
          <div class="feedback__compare">
            <div class="feedback__cell feedback__cell--yours"><dt>Senin cevabın</dt><dd><?= e((string)($d['user_answer'] ?? '—')) ?></dd></div>
            <div class="feedback__cell feedback__cell--right"><dt>Doğrusu</dt><dd><?= e((string)$d['correct_answer']) ?></dd></div>
          </div>
        <?php endif; ?>

        <?php if (!empty($d['breakdown']) && count($d['breakdown']) > 1): ?>
          <div class="breakdown">
            <?php foreach ($d['breakdown'] as $b): ?>
              <div class="breakdown__row <?= !empty($b['ok']) ? 'is-ok' : 'is-bad' ?>">
                <span class="breakdown__mark" aria-hidden="true"><?= !empty($b['ok']) ? '✓' : '✕' ?></span>
                <span class="breakdown__label"><?= e((string)$b['label']) ?></span>
                <span class="breakdown__note"><?= e((string)($b['note'] ?? '')) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($d['explanation'])): ?>
          <div class="feedback__label">Neden?</div><p><?= e((string)$d['explanation']) ?></p>
        <?php endif; ?>
        <?php if (!empty($d['note'])): ?><p><?= e((string)$d['note']) ?></p><?php endif; ?>
        <?php if (!empty($d['memory_hint'])): ?>
          <div class="feedback__label">Hatırlama</div><p><?= e((string)$d['memory_hint']) ?></p>
        <?php endif; ?>
        <?php if (!empty($d['mastery_note'])): ?>
          <div class="feedback__label">Mastery durumu</div><p><?= e((string)$d['mastery_note']) ?></p>
        <?php endif; ?>
        <?php if (!empty($d['queue_note'])): ?>
          <div class="feedback__queue"><?= e((string)$d['queue_note']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="quiz__submit-row">
      <a class="btn btn--lg" href="<?= e((string)$d['next_url']) ?>"><?= $ok ? 'DEVAM ET' : 'ANLADIM, DEVAM ET' ?></a>
    </div>
    <?php
}

/** Oturum sonu ozeti. */
function render_quiz_summary(array $user, array $session, array $progress): void
{
    $userId = (int)$user['id'];
    $sessionId = (int)$session['id'];
    $accuracy = pct($progress['correct'], max(1, $progress['answered']));

    /* Bu oturumda guclenen ve zayiflayan konular */
    $strengthened = db_all(
        'SELECT DISTINCT v.german, v.article FROM exercise_attempts a
         JOIN vocabulary v ON v.id = a.vocabulary_id
         WHERE a.session_id = ? AND a.is_correct = 1 LIMIT 8',
        [$sessionId]
    );
    $weakened = db_all(
        'SELECT DISTINCT s.name FROM exercise_attempts a
         JOIN skills s ON s.id = a.skill_id
         WHERE a.session_id = ? AND a.is_correct = 0 LIMIT 6',
        [$sessionId]
    );

    $lesson = !empty($session['lesson_id'])
        ? db_row('SELECT * FROM lessons WHERE id = ?', [(int)$session['lesson_id']])
        : null;
    $completion = $lesson !== null ? lesson_completion_state($userId, (int)$lesson['id']) : null;

    render_focus_start('Oturum tamamlandı · ' . APP_NAME, ['noindex' => true]);
    ?>
    <div class="quiz">
      <p class="eyebrow">Oturum tamamlandı</p>
      <h1 style="font-size: 30px;">
        <?= $accuracy >= 80 ? 'İyi iş. Devam.' : ($accuracy >= 50 ? 'İlerleme var. Devam edelim.' : 'Bu konu tekrar gerektiriyor.') ?>
      </h1>

      <div class="review-stats" style="margin: 22px 0;">
        <div class="review-stats__cell">
          <div class="review-stats__num"><?= (int)$progress['correct'] ?></div>
          <div class="review-stats__label">doğru</div>
        </div>
        <div class="review-stats__cell">
          <div class="review-stats__num"><?= (int)$progress['wrong'] ?></div>
          <div class="review-stats__label">hata</div>
        </div>
        <div class="review-stats__cell">
          <div class="review-stats__num"><?= count($strengthened) ?></div>
          <div class="review-stats__label">kelime güçlendi</div>
        </div>
        <div class="review-stats__cell">
          <div class="review-stats__num"><?= count($weakened) ?></div>
          <div class="review-stats__label">konu tekrar listesinde</div>
        </div>
      </div>

      <?php if ($weakened !== []): ?>
        <div class="card card--warn" style="margin-bottom: 18px;">
          <p class="eyebrow">Tekrar listesine eklenenler</p>
          <ul class="small" style="margin: 0;">
            <?php foreach ($weakened as $w): ?><li><?= e((string)$w['name']) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($completion !== null): ?>
        <div class="mastery" style="margin-bottom: 18px;">
          <div class="mastery__head">
            <div>
              <p class="eyebrow" style="margin: 0;">Ders durumu</p>
              <strong><?= e((string)$lesson['title']) ?></strong>
            </div>
            <span class="mastery__score"><?= (int)$completion['average'] ?>%</span>
          </div>
          <div class="mastery__axes">
            <?php foreach ($completion['skills'] as $sk):
                $score = (int)$sk['score'];
                $state = $score >= (int)$completion['threshold'] ? 'is-strong' : ($score > 0 ? 'is-progress' : 'is-untested');
                $stateText = $score >= (int)$completion['threshold'] ? '✓ GÜÇLÜ' : ($score > 0 ? '● GELİŞİYOR' : '○ TEST EDİLMEDİ');
            ?>
              <div class="mastery__axis <?= $state ?>">
                <span class="mastery__axis-label"><?= e((string)$sk['name']) ?></span>
                <span class="num small"><?= $score ?>%</span>
                <span class="mastery__axis-state"><?= $stateText ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if (!$completion['complete']): ?>
            <div class="mastery__missing">
              Bu ders henüz tamamlanmış sayılmıyor. Tamamlanması için birincil becerilerin
              <strong>%<?= (int)$completion['threshold'] ?></strong> eşiğini geçmesi ve ders kelimelerinin en az %80'inin
              bu eşiğe ulaşması gerekiyor.
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="quiz__submit-row">
        <?php if ($completion !== null && !$completion['complete']): ?>
          <a class="btn btn--lg" href="/quiz.php?lesson=<?= (int)$lesson['id'] ?>">TEKRAR ÇALIŞ</a>
          <a class="btn btn--lg btn--secondary" href="/lesson.php?id=<?= (int)$lesson['id'] ?>">DERSİ YENİDEN OKU</a>
        <?php elseif ($completion !== null): ?>
          <a class="btn btn--lg" href="/course.php">YOL HARİTASINA DÖN</a>
          <a class="btn btn--lg btn--secondary" href="/checkpoint.php?lesson=<?= (int)$lesson['id'] ?>">MASTERY KONTROLÜ</a>
        <?php else: ?>
          <a class="btn btn--lg" href="/dashboard.php">PANELE DÖN</a>
          <a class="btn btn--lg btn--secondary" href="/review.php">TEKRARA GİT</a>
        <?php endif; ?>
      </div>
    </div>
    <?php
    render_focus_end();
}
