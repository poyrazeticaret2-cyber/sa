<?php
/**
 * AlmancaPro - Rol yapma senaryolari.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];
$tz = user_timezone($user);
$counts = due_review_counts($userId);

$scenarioId = input_int('id', 0);

/* ---------------- Liste ---------------- */
if ($scenarioId === 0) {
    $scenarios = db_all(
        'SELECT s.*, (SELECT COUNT(*) FROM scenario_turns t WHERE t.scenario_id = s.id) AS turns,
                (SELECT COUNT(*) FROM user_scenario_runs r WHERE r.scenario_id = s.id AND r.user_id = ? AND r.completed_at IS NOT NULL) AS done
         FROM scenarios s WHERE s.is_active = 1 ORDER BY FIELD(s.cefr_level,"A0","A1","A2","B1"), s.sort_order',
        [$userId]
    );

    render_app_start($user, 'Senaryolar · ' . APP_NAME, [], ['review.php' => $counts['due']]);
    ?>
    <p class="eyebrow">Konuşma senaryoları</p>
    <h1>Gerçek durumlarda Almanca</h1>
    <p class="muted">Her senaryoda karşındaki kişi konuşur, sen cevabını seçersin. Her seçim için anlaşılırlık,
      dilbilgisi, doğallık ve nezaket ayrı ayrı değerlendirilir.</p>

    <div class="grid grid-2" style="margin-top: 24px;">
      <?php foreach ($scenarios as $sc): ?>
        <a class="card" href="/scenario.php?id=<?= (int)$sc['id'] ?>">
          <div class="row" style="gap: 10px; margin-bottom: 10px;">
            <span class="badge badge--level"><?= e((string)$sc['cefr_level']) ?></span>
            <?php if ((int)$sc['done'] > 0): ?>
              <span class="badge badge--ok"><span aria-hidden="true">✓</span>TAMAMLANDI</span>
            <?php endif; ?>
            <span class="small muted"><?= (int)$sc['turns'] ?> tur</span>
          </div>
          <h3 style="font-size: 17px; margin-bottom: 6px;"><?= e((string)$sc['title']) ?></h3>
          <p class="small muted" style="margin: 0;"><?= e(str_limit((string)$sc['setting_tr'], 140)) ?></p>
        </a>
      <?php endforeach; ?>
    </div>
    <?php
    render_app_end();
    exit;
}

/* ---------------- Senaryo calistirma ---------------- */
$scenario = db_row('SELECT * FROM scenarios WHERE id = ? AND is_active = 1', [$scenarioId]);
if ($scenario === null) {
    flash('error', 'Senaryo bulunamadı.');
    redirect('/scenario.php');
}
$turns = db_all('SELECT * FROM scenario_turns WHERE scenario_id = ? ORDER BY step', [$scenarioId]);
if ($turns === []) {
    flash('error', 'Bu senaryoda henüz diyalog yok.');
    redirect('/scenario.php');
}

$stateKey = 'scenario_' . $scenarioId;
if (!isset($_SESSION[$stateKey]) || input_int('restart', 0) === 1) {
    $_SESSION[$stateKey] = ['step' => 0, 'transcript' => [], 'scores' => ['clarity' => 0, 'grammar' => 0, 'naturalness' => 0, 'politeness' => 0]];
}
$state = &$_SESSION[$stateKey];

$lastFeedback = null;

if (is_post()) {
    csrf_require();
    $optionId = input_int('option_id', 0);
    $turnIndex = input_int('turn_index', 0);

    $turn = $turns[$turnIndex] ?? null;
    if ($turn === null) {
        redirect('/scenario.php?id=' . $scenarioId);
    }
    $option = db_row('SELECT * FROM scenario_options WHERE id = ? AND turn_id = ?', [$optionId, (int)$turn['id']]);
    if ($option === null) {
        flash('error', 'Seçim doğrulanamadı.');
        redirect('/scenario.php?id=' . $scenarioId);
    }

    $state['transcript'][] = [
        'step' => $turnIndex + 1,
        'partner_de' => (string)$turn['speaker_de'],
        'partner_tr' => (string)$turn['speaker_tr'],
        'answer' => (string)$option['text_de'],
        'quality' => (string)$option['quality'],
        'feedback' => (string)$option['feedback_tr'],
        'better' => (string)($option['better_alternative'] ?? ''),
        'scores' => [
            'clarity' => (int)$option['score_clarity'],
            'grammar' => (int)$option['score_grammar'],
            'naturalness' => (int)$option['score_naturalness'],
            'politeness' => (int)$option['score_politeness'],
        ],
    ];
    foreach (['clarity', 'grammar', 'naturalness', 'politeness'] as $k) {
        $state['scores'][$k] += (int)$option['score_' . ($k === 'clarity' ? 'clarity' : ($k === 'grammar' ? 'grammar' : ($k === 'naturalness' ? 'naturalness' : 'politeness')))];
    }
    $state['step'] = $turnIndex + 1;
    $lastFeedback = end($state['transcript']);

    /* Senaryo bitti mi? */
    if ($state['step'] >= count($turns)) {
        $n = max(1, count($state['transcript']));
        $avg = [];
        foreach (['clarity', 'grammar', 'naturalness', 'politeness'] as $k) {
            $avg[$k] = (int)round(($state['scores'][$k] / $n) * 20); /* 5 uzerinden -> 100 */
        }
        db_insert(
            'INSERT INTO user_scenario_runs (user_id, scenario_id, transcript, score_clarity, score_grammar, score_naturalness, score_politeness, completed_at)
             VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP())',
            [$userId, $scenarioId, json_encode($state['transcript'], JSON_UNESCAPED_UNICODE),
             $avg['clarity'], $avg['grammar'], $avg['naturalness'], $avg['politeness']]
        );
        award_activity($userId, 'scenario_completed', $scenarioId, 'Senaryo tamamlandı: ' . (string)$scenario['title'], 15, 0);
        update_streak($userId);
        $_SESSION[$stateKey . '_result'] = ['avg' => $avg, 'transcript' => $state['transcript']];
        unset($_SESSION[$stateKey]);
        redirect('/scenario.php?id=' . $scenarioId . '&done=1');
    }
}

$done = input_int('done', 0) === 1 && !empty($_SESSION[$stateKey . '_result']);

render_app_start($user, e((string)$scenario['title']) . ' · ' . APP_NAME, [], ['review.php' => $counts['due']]);
?>
<a class="small" href="/scenario.php">← Senaryolar</a>

<?php if ($done):
    $result = $_SESSION[$stateKey . '_result'];
    unset($_SESSION[$stateKey . '_result']);
    $overall = (int)round(array_sum($result['avg']) / 4);
?>
  <p class="eyebrow" style="margin-top: 14px;">Senaryo sonucu</p>
  <h1><?= e((string)$scenario['title']) ?></h1>

  <div class="scenario-score">
    <div class="scenario-score__cell"><div class="scenario-score__num"><?= (int)$result['avg']['clarity'] ?>%</div><div class="scenario-score__label">Anlaşılırlık</div></div>
    <div class="scenario-score__cell"><div class="scenario-score__num"><?= (int)$result['avg']['grammar'] ?>%</div><div class="scenario-score__label">Dilbilgisi</div></div>
    <div class="scenario-score__cell"><div class="scenario-score__num"><?= (int)$result['avg']['naturalness'] ?>%</div><div class="scenario-score__label">Doğallık</div></div>
    <div class="scenario-score__cell"><div class="scenario-score__num"><?= (int)$result['avg']['politeness'] ?>%</div><div class="scenario-score__label">Nezaket</div></div>
  </div>

  <div class="card card--<?= $overall >= 80 ? 'ok' : ($overall >= 55 ? 'warn' : 'bad') ?>" style="margin-bottom: 22px;">
    <p style="margin: 0;">
      <?= $overall >= 80
        ? 'Bu diyaloğu gerçek hayatta rahatlıkla yürütebilirsin.'
        : ($overall >= 55
            ? 'Anlaşılırsın, ancak bazı yerlerde daha doğal ve kibar bir ifade seçebilirdin.'
            : 'Bu senaryoyu tekrar etmelisin. Aşağıdaki alternatifleri çalış.') ?>
    </p>
  </div>

  <h2>Diyalog dökümü</h2>
  <?php foreach ($result['transcript'] as $t): ?>
    <div class="card" style="margin-bottom: 14px;">
      <p class="eyebrow">Tur <?= (int)$t['step'] ?></p>
      <p><strong><?= e((string)$scenario['role_partner']) ?>:</strong> <?= e((string)$t['partner_de']) ?>
        <span class="small muted">(<?= e((string)$t['partner_tr']) ?>)</span></p>
      <p><strong>Sen:</strong> <?= e((string)$t['answer']) ?>
        <?php
        $q = (string)$t['quality'];
        render_badge($q === 'good' ? '✓' : ($q === 'ok' ? '●' : '✕'),
            $q === 'good' ? 'İYİ' : ($q === 'ok' ? 'ANLAŞILIR' : 'SORUNLU'),
            $q === 'good' ? 'ok' : ($q === 'ok' ? 'warn' : 'bad'));
        ?>
      </p>
      <p class="small"><?= e((string)$t['feedback']) ?></p>
      <?php if (!empty($t['better'])): ?>
        <p class="small"><strong>Daha iyi alternatif:</strong> <?= e((string)$t['better']) ?></p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="row" style="margin-top: 20px;">
    <a class="btn btn--inline" href="/scenario.php?id=<?= $scenarioId ?>&amp;restart=1">TEKRAR DENE</a>
    <a class="btn btn--secondary btn--inline" href="/scenario.php">BAŞKA SENARYO</a>
  </div>

<?php else:
    $index = (int)$state['step'];
    $turn = $turns[$index] ?? null;
    if ($turn === null) {
        redirect('/scenario.php?id=' . $scenarioId . '&restart=1');
    }
    $options = db_all('SELECT * FROM scenario_options WHERE turn_id = ? ORDER BY sort_order', [(int)$turn['id']]);
    shuffle($options);
?>
  <p class="eyebrow" style="margin-top: 14px;"><?= e((string)$scenario['cefr_level']) ?> · Tur <?= $index + 1 ?> / <?= count($turns) ?></p>
  <h1><?= e((string)$scenario['title']) ?></h1>
  <p class="muted"><?= e((string)$scenario['setting_tr']) ?></p>

  <?php if ($lastFeedback !== null): ?>
    <div class="feedback feedback--<?= $lastFeedback['quality'] === 'bad' ? 'bad' : 'ok' ?>" style="margin: 20px 0;">
      <div class="feedback__head">
        <span aria-hidden="true"><?= $lastFeedback['quality'] === 'good' ? '✓' : ($lastFeedback['quality'] === 'ok' ? '●' : '✕') ?></span>
        <span>Önceki cevabın: <?= e((string)$lastFeedback['answer']) ?></span>
      </div>
      <div class="feedback__body">
        <p><?= e((string)$lastFeedback['feedback']) ?></p>
        <?php if (!empty($lastFeedback['better'])): ?>
          <div class="feedback__label">Daha iyi alternatif</div>
          <p><?= e((string)$lastFeedback['better']) ?></p>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="scenario-turn" style="margin-top: 20px;">
    <p class="eyebrow"><?= e((string)$scenario['role_partner']) ?></p>
    <div class="scenario-turn__de"><?= e((string)$turn['speaker_de']) ?></div>
    <div class="scenario-turn__tr"><?= e((string)$turn['speaker_tr']) ?></div>
    <button class="btn btn--sm btn--secondary btn--inline speak-btn" type="button" data-speak="<?= e((string)$turn['speaker_de']) ?>" style="margin-top: 12px;">▶ DİNLE</button>
  </div>

  <?php if (!empty($turn['instruction_tr'])): ?>
    <div class="alert alert--info"><span class="alert__icon" aria-hidden="true">●</span><span><?= e((string)$turn['instruction_tr']) ?></span></div>
  <?php endif; ?>
  <?php if (!empty($turn['hint'])): ?>
    <details class="acc" style="margin-bottom: 18px;">
      <summary>İpucu</summary>
      <div class="acc__body"><?= e((string)$turn['hint']) ?></div>
    </details>
  <?php endif; ?>

  <div class="quiz__options">
    <?php foreach ($options as $i => $opt): ?>
      <form method="post" action="/scenario.php?id=<?= $scenarioId ?>" style="margin: 0;">
        <?= csrf_field() ?>
        <input type="hidden" name="turn_index" value="<?= $index ?>">
        <input type="hidden" name="option_id" value="<?= (int)$opt['id'] ?>">
        <button class="quiz-option" type="submit">
          <span class="quiz-option__letter" aria-hidden="true"><?= e(chr(65 + $i)) ?></span>
          <span class="quiz-option__text"><?= e((string)$opt['text_de']) ?></span>
        </button>
      </form>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php render_app_end(); ?>
