<?php
/**
 * AlmancaPro - Ogretmene Sor.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
require_once __DIR__ . '/ai.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];
$tz = user_timezone($user);
$counts = due_review_counts($userId);

$error = '';

if (is_post()) {
    csrf_require();
    $body = trim((string)input('question', ''));

    if (mb_strlen($body) < 5) {
        $error = 'Sorunu biraz daha açık yaz (en az 5 karakter).';
    } elseif (mb_strlen($body) > 2000) {
        $error = 'Soru çok uzun. Lütfen 2000 karakterin altında tut.';
    } elseif (!rate_limit_hit('ask_user', (string)$userId, 30, 3600)) {
        $error = 'Bir saat içinde çok fazla soru sordun. Lütfen biraz bekle.';
    } else {
        $context = [
            'cefr' => (string)$user['cefr_level'],
            'page' => 'ask',
        ];
        $questionId = db_insert(
            'INSERT INTO questions (user_id, body, context, status, source) VALUES (?, ?, ?, "open", "web")',
            [$userId, $body, json_encode($context, JSON_UNESCAPED_UNICODE)]
        );

        $answered = false;
        if (ai_is_enabled()) {
            $res = ai_ask($userId, $user, $body);
            if ($res['ok']) {
                db_exec('INSERT INTO answers (question_id, source, body) VALUES (?, "ai", ?)', [$questionId, $res['answer']]);
                db_exec('UPDATE questions SET status = "answered" WHERE id = ?', [$questionId]);
                $answered = true;
            }
        }

        if (!$answered) {
            $fb = ai_fallback_answer($userId, $user, $body);
            db_exec('INSERT INTO answers (question_id, source, body) VALUES (?, "knowledge_base", ?)', [$questionId, $fb['answer']]);
            if ($fb['ok']) {
                db_exec('UPDATE questions SET status = "answered" WHERE id = ?', [$questionId]);
            }
        }

        award_activity($userId, 'question_asked', null, 'Öğretmene soru soruldu', 2, 0);
        redirect('/ask.php#q' . $questionId);
    }
}

$questions = db_all(
    'SELECT * FROM questions WHERE user_id = ? ORDER BY id DESC LIMIT 20',
    [$userId]
);
$answers = [];
if ($questions !== []) {
    $ids = array_map('intval', array_column($questions, 'id'));
    foreach (db_all('SELECT * FROM answers WHERE question_id IN (' . implode(',', $ids) . ') ORDER BY id', []) as $a) {
        $answers[(int)$a['question_id']][] = $a;
    }
}

$examples = [
    'Neden "der" yerine "den" oldu?',
    'mit neden Dativ istiyor?',
    'zu ile nach farkı ne?',
    'Neden bazı cümlelerde fiil sonda?',
    'kein mi nicht mi kullanmalıyım?',
];

render_app_start($user, 'Öğretmene Sor · ' . APP_NAME, [], ['review.php' => $counts['due']]);
?>
<p class="eyebrow">Öğretmene sor</p>
<h1>Takıldığın yeri sor</h1>
<p class="muted">
  Cevaplar seviyene, öğrendiğin konulara ve son hatalarına göre hazırlanır.
  <?php if (ai_is_enabled()): ?>
    Gelişmiş öğretmen aktif · bugün kalan soru hakkın: <span class="num"><?= ai_quota_left($userId) ?></span>
  <?php else: ?>
    Şu anda dilbilgisi kütüphanesi ve bilgi tabanı üzerinden yanıtlanıyor.
  <?php endif; ?>
</p>

<?php if ($error !== ''): ?>
  <div class="alert alert--error" style="margin-top: 16px;"><span class="alert__icon" aria-hidden="true">✕</span><span><?= e($error) ?></span></div>
<?php endif; ?>

<form method="post" action="/ask.php" data-guard style="margin: 22px 0;">
  <?= csrf_field() ?>
  <div class="field">
    <label for="question">Sorun</label>
    <textarea id="question" name="question" required placeholder="Örnek: Neden &quot;Ich helfe dem Kollegen&quot; diyoruz, &quot;den&quot; değil mi?"></textarea>
  </div>
  <div class="filter-bar" style="margin-bottom: 14px;">
    <?php foreach ($examples as $ex): ?>
      <button class="filter-chip" type="button" data-fill-target="question" data-q="<?= e($ex) ?>"><?= e($ex) ?></button>
    <?php endforeach; ?>
  </div>
  <button class="btn btn--lg btn--inline" type="submit">SOR</button>
</form>

<?php if ($questions === []): ?>
  <?php render_empty('Henüz soru sormadın', 'Bir kuralda takıldığında buraya yaz. Cevaplar geçmişinde kalır.', '/grammar.php', 'DİLBİLGİSİNE GÖZ AT'); ?>
<?php else: ?>
  <h2 style="margin-top: 30px;">Geçmiş sorular</h2>
  <div class="chat">
    <?php foreach ($questions as $q): ?>
      <div id="q<?= (int)$q['id'] ?>" class="chat-msg chat-msg--user">
        <div class="chat-msg__meta">Sen · <?= e(local_datetime((string)$q['created_at'], 'd.m.Y H:i', $tz)) ?></div>
        <?= nl2br(e((string)$q['body'])) ?>
      </div>
      <?php foreach ($answers[(int)$q['id']] ?? [] as $a): ?>
        <div class="chat-msg chat-msg--teacher">
          <div class="chat-msg__meta">
            Öğretmen ·
            <?= e(match ((string)$a['source']) {
                'ai' => 'gelişmiş öğretmen',
                'admin' => 'eğitmen',
                default => 'bilgi tabanı',
            }) ?>
            · <?= e(local_datetime((string)$a['created_at'], 'd.m.Y H:i', $tz)) ?>
          </div>
          <?= nl2br(e((string)$a['body'])) ?>
        </div>
      <?php endforeach; ?>
      <?php if (empty($answers[(int)$q['id']])): ?>
        <div class="chat-msg chat-msg--teacher">
          <div class="chat-msg__meta">Öğretmen</div>
          Bu soru kaydedildi ve inceleniyor. Cevaplandığında burada görünecek.
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php render_app_end(); ?>
