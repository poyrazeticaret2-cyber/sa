<?php
/**
 * AlmancaPro - Araliki tekrar (SRS) sayfasi.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];
$tz = user_timezone($user);

$counts = due_review_counts($userId);

/* Acik tekrar oturumu varsa dogrudan devam. */
$open = db_row('SELECT id FROM study_sessions WHERE user_id = ? AND session_type = "review" AND status = "active" ORDER BY id DESC LIMIT 1', [$userId]);

if (is_post() || input_int('start', 0) === 1) {
    if (is_post()) {
        csrf_require();
    }
    if ($open !== null) {
        redirect('/quiz.php?session=' . (int)$open['id']);
    }
    $items = build_review_items($userId, 20);
    if ($items === []) {
        flash('info', 'Şu anda tekrar zamanı gelen kart yok. Yeni konu öğrenerek devam edebilirsin.');
        redirect('/review.php');
    }
    $sessionId = create_session($userId, 'review', $items);
    redirect('/quiz.php?session=' . $sessionId);
}

/* Son tekrar oturumlari */
$recent = db_all(
    'SELECT id, correct_count, wrong_count, duration_seconds, ended_at
     FROM study_sessions WHERE user_id = ? AND session_type = "review" AND status = "completed"
     ORDER BY id DESC LIMIT 5',
    [$userId]
);

$nextReview = db_value(
    'SELECT MIN(next_review_at) FROM (
        SELECT next_review_at FROM user_vocabulary_mastery WHERE user_id = ? AND status <> "mastered" AND next_review_at > UTC_TIMESTAMP()
        UNION ALL
        SELECT next_review_at FROM user_skill_mastery WHERE user_id = ? AND status <> "mastered" AND next_review_at > UTC_TIMESTAMP()
     ) t',
    [$userId, $userId]
);

render_app_start($user, 'Tekrar · ' . APP_NAME, ['js' => ['review.js']], ['review.php' => $counts['due']]);
?>
<p class="eyebrow">Aralıklı tekrar</p>
<h1>Tekrar</h1>
<p class="muted">Doğru cevapladıkça aralık uzar, hata yaptıkça kısalır. Amaç unutmadan hemen önce hatırlamak.</p>

<div class="review-stats" style="margin: 24px 0;">
  <div class="review-stats__cell">
    <div class="review-stats__num"><?= (int)$counts['due'] ?></div>
    <div class="review-stats__label">bugün tekrar zamanı gelen</div>
  </div>
  <div class="review-stats__cell">
    <div class="review-stats__num"><?= (int)$counts['overdue'] ?></div>
    <div class="review-stats__label">çok geciken</div>
  </div>
  <div class="review-stats__cell">
    <div class="review-stats__num"><?= (int)$counts['weak'] ?></div>
    <div class="review-stats__label">zayıf</div>
  </div>
  <div class="review-stats__cell">
    <div class="review-stats__num"><?= (int)$counts['est_minutes'] ?></div>
    <div class="review-stats__label">tahmini dakika</div>
  </div>
</div>

<?php if ($open !== null): ?>
  <div class="alert alert--info"><span class="alert__icon" aria-hidden="true">●</span>
    <span>Yarım kalan bir tekrar oturumun var. Kaldığın yerden devam edebilirsin.</span></div>
  <a class="btn btn--lg btn--inline" href="/quiz.php?session=<?= (int)$open['id'] ?>">OTURUMA DEVAM ET</a>

<?php elseif ($counts['due'] > 0): ?>
  <form method="post" action="/review.php" data-guard>
    <?= csrf_field() ?>
    <button class="btn btn--lg btn--block" type="submit" data-review-start>TEKRARA BAŞLA</button>
  </form>
  <p class="small muted" style="margin-top: 12px;">
    Oturumda en fazla 20 kart gösterilir. Yanlış cevapladığın kart aynı oturumda 2 soru sonra yeniden karşına çıkar.
  </p>

<?php else: ?>
  <?php render_empty(
      'Bugünkü tekrarlarını tamamladın.',
      $nextReview !== null
        ? 'Bir sonraki tekrar zamanı: ' . local_datetime((string)$nextReview, 'd.m.Y H:i', $tz) . '. Yeni konu öğrenmeye devam edebilirsin.'
        : 'Henüz tekrar kartın yok. İlk dersini tamamladığında kartların oluşacak.',
      '/course.php',
      'DERSLERE DEVAM ET'
  ); ?>
<?php endif; ?>

<?php if ($recent !== []): ?>
  <section class="card card--flush" style="margin-top: 28px;">
    <div class="card__head"><strong>Son tekrar oturumların</strong></div>
    <?php foreach ($recent as $r): ?>
      <div class="plan-item">
        <span class="plan-item__state" aria-hidden="true">✓</span>
        <span class="plan-item__title">
          <?= (int)$r['correct_count'] ?> doğru · <?= (int)$r['wrong_count'] ?> hata
        </span>
        <span class="plan-item__meta">
          <?= (int)round((int)$r['duration_seconds'] / 60) ?> dk · <?= e(local_datetime((string)$r['ended_at'], 'd.m H:i', $tz)) ?>
        </span>
      </div>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<?php render_app_end(); ?>
