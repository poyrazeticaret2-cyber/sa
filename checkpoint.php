<?php
/**
 * AlmancaPro - Mastery kontrol noktasi.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];

$lessonId = input_int('lesson', 0);
$lesson = $lessonId > 0 ? db_row('SELECT * FROM lessons WHERE id = ? AND is_active = 1', [$lessonId]) : null;
if ($lesson === null) {
    flash('error', 'Ders bulunamadı.');
    redirect('/course.php');
}
if (!can_access_lesson($userId, $lesson)) {
    redirect('/course.php?locked=' . $lessonId . '#lock');
}

$state = lesson_completion_state($userId, $lessonId);

if (is_post()) {
    csrf_require();
    $items = build_checkpoint($userId, $lessonId, 12);
    if ($items === []) {
        flash('info', 'Bu ders için kontrol sorusu bulunamadı.');
        redirect('/lesson.php?id=' . $lessonId);
    }
    $sessionId = create_session($userId, 'checkpoint', $items, $lessonId);
    redirect('/quiz.php?session=' . $sessionId);
}

render_app_start($user, 'Mastery kontrolü · ' . APP_NAME);
?>
<p class="eyebrow"><?= e((string)$lesson['cefr_level']) ?> · Mastery kontrolü</p>
<h1><?= e((string)$lesson['title']) ?></h1>
<p class="muted">Bu kontrol, konuyu farklı biçimlerde gerçekten kullanabildiğini doğrular. Soyut bir puan değil, hangi yeteneğin hangi durumda olduğu ölçülür.</p>

<div class="mastery" style="margin: 24px 0;">
  <div class="mastery__head">
    <div>
      <p class="eyebrow" style="margin: 0;">Ders ortalaması</p>
      <strong><?= $state['complete'] ? 'Tamamlandı' : 'Devam ediyor' ?></strong>
    </div>
    <span class="mastery__score"><?= (int)$state['average'] ?>%</span>
  </div>
  <div class="mastery__axes">
    <?php foreach ($state['skills'] as $sk):
        $score = (int)$sk['score'];
        $threshold = (int)$state['threshold'];
        $st = $score >= $threshold ? 'is-strong' : ($score > 0 ? 'is-progress' : 'is-untested');
        $txt = $score >= $threshold ? '✓ GÜÇLÜ' : ($score > 0 ? '● GELİŞİYOR' : '○ TEST EDİLMEDİ');
    ?>
      <div class="mastery__axis <?= $st ?>">
        <span class="mastery__axis-label"><?= e((string)$sk['name']) ?></span>
        <span class="num small"><?= $score ?>%</span>
        <span class="mastery__axis-state"><?= $txt ?></span>
      </div>
    <?php endforeach; ?>
    <?php if ((int)$state['vocab_total'] > 0): ?>
      <div class="mastery__axis <?= (int)$state['vocab_ok'] >= (int)ceil((int)$state['vocab_total'] * 0.8) ? 'is-strong' : 'is-progress' ?>">
        <span class="mastery__axis-label">Ders kelimeleri</span>
        <span class="num small"><?= (int)$state['vocab_ok'] ?>/<?= (int)$state['vocab_total'] ?></span>
        <span class="mastery__axis-state">
          <?= (int)$state['vocab_ok'] >= (int)ceil((int)$state['vocab_total'] * 0.8) ? '✓ GÜÇLÜ' : '● GELİŞİYOR' ?>
        </span>
      </div>
    <?php endif; ?>
  </div>
  <?php if (!$state['complete']): ?>
    <div class="mastery__missing">
      Mastered olması için birincil becerilerin <strong>%<?= (int)$state['threshold'] ?></strong> eşiğini geçmesi ve
      ders kelimelerinin en az %80'inin bu eşiğe ulaşması gerekiyor. Kontrol testi bu kanıtları toplar.
    </div>
  <?php endif; ?>
</div>

<form method="post" action="/checkpoint.php?lesson=<?= $lessonId ?>" data-guard>
  <?= csrf_field() ?>
  <button class="btn btn--lg btn--block" type="submit">MASTERY KONTROLÜNÜ BAŞLAT (12 SORU)</button>
</form>
<p class="small muted" style="margin-top: 12px;">
  Kontrol testi tanıma, aktif hatırlama ve üretim sorularını birlikte içerir. Tek bir doğru cevap mastered anlamına gelmez.
</p>

<?php render_app_end(); ?>
