<?php
/**
 * AlmancaPro - Seviye tespit sinavi.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/learning.php';
require_once __DIR__ . '/content-placement.php';
app_boot();

$user = require_login();
$questions = almancapro_placement_questions();
$total = count($questions);

$sectionLabels = [
    'vocabulary' => 'Kelime hazinesi',
    'grammar'    => 'Dilbilgisi',
    'reading'    => 'Okuma anlama',
    'spelling'   => 'Yazım',
    'production' => 'Aktif üretim',
    'sentence'   => 'Cümle kurma',
];

if (is_post()) {
    csrf_require();

    $answers = [];
    $sectionStats = [];
    $wrongSkills = [];

    foreach ($questions as $q) {
        $given = trim((string)($_POST['q_' . $q['id']] ?? ''));
        $accepted = array_merge([(string)$q['a']], $q['alt'] ?? []);
        $ok = false;
        foreach ($accepted as $acc) {
            if (answer_key($acc) === answer_key($given)) {
                $ok = true;
                break;
            }
        }
        $answers[$q['id']] = ['given' => mb_substr($given, 0, 300), 'ok' => $ok];

        $sec = (string)$q['section'];
        $sectionStats[$sec]['total'] = ($sectionStats[$sec]['total'] ?? 0) + 1;
        $sectionStats[$sec]['ok'] = ($sectionStats[$sec]['ok'] ?? 0) + ($ok ? 1 : 0);

        if (!$ok && !empty($q['skill'])) {
            $wrongSkills[] = (string)$q['skill'];
        }
    }

    $percents = [];
    $sumOk = 0;
    foreach ($sectionStats as $sec => $st) {
        $percents[$sec] = pct((int)$st['ok'], (int)$st['total']);
        $sumOk += (int)$st['ok'];
    }
    $totalPercent = pct($sumOk, $total);
    $level = almancapro_placement_level($percents, $totalPercent);

    db_insert(
        'INSERT INTO placement_tests
            (user_id, answers, score_vocabulary, score_grammar, score_reading, score_production, score_spelling, score_sentence, total_score, recommended_level)
         VALUES (?,?,?,?,?,?,?,?,?,?)',
        [
            (int)$user['id'],
            json_encode($answers, JSON_UNESCAPED_UNICODE),
            $percents['vocabulary'] ?? 0,
            $percents['grammar'] ?? 0,
            $percents['reading'] ?? 0,
            $percents['production'] ?? 0,
            $percents['spelling'] ?? 0,
            $percents['sentence'] ?? 0,
            $totalPercent,
            $level,
        ]
    );

    db_exec('UPDATE users SET placement_status = "completed", cefr_level = ?, start_level = ? WHERE id = ?',
        [$level, $level, (int)$user['id']]);

    /* Yanlis yapilan konular remediation icin isaretlenir. */
    $skillMap = [];
    foreach (db_all('SELECT id, code FROM skills') as $r) {
        $skillMap[$r['code']] = (int)$r['id'];
    }
    foreach (array_unique($wrongSkills) as $code) {
        if (!isset($skillMap[$code])) {
            continue;
        }
        db_exec(
            'INSERT INTO user_skill_mastery (user_id, skill_id, status, mastery_score, next_review_at)
             VALUES (?, ?, "weak", 0, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE status = IF(status = "mastered", status, "weak"), next_review_at = UTC_TIMESTAMP()',
            [(int)$user['id'], $skillMap[$code]]
        );
    }

    award_activity((int)$user['id'], 'placement_test', null, 'Seviye testi tamamlandı (' . $level . ')', 20, 0, ['score' => $totalPercent]);

    $_SESSION['placement_result'] = [
        'level' => $level,
        'total' => $totalPercent,
        'sections' => $percents,
        'weak' => array_values(array_unique($wrongSkills)),
    ];
    redirect('/placement-test.php?done=1');
}

$done = input_int('done', 0) === 1 && !empty($_SESSION['placement_result']);

render_head('Seviye testi · ' . APP_NAME, ['css' => ['auth.css', 'learning.css'], 'noindex' => true]);
?>
<div class="page">
  <div class="focus-area">
    <?php render_flashes(); ?>

    <?php if ($done):
        $r = $_SESSION['placement_result'];
        unset($_SESSION['placement_result']);
        $weakNames = [];
        if ($r['weak'] !== []) {
            $in = implode(',', array_fill(0, count($r['weak']), '?'));
            foreach (db_all('SELECT name FROM skills WHERE code IN (' . $in . ')', $r['weak']) as $s) {
                $weakNames[] = (string)$s['name'];
            }
        }
    ?>
      <p class="eyebrow">Seviye testi sonucu</p>
      <h1>Başlangıç seviyen: <?= e($r['level']) ?></h1>
      <p class="muted"><?= e(cefr_label((string)$r['level'])) ?> · Genel doğruluk: <span class="num"><?= (int)$r['total'] ?>%</span></p>

      <div class="card" style="margin: 22px 0;">
        <h2 style="font-size: 17px;">Eksen bazında sonuç</h2>
        <?php foreach ($sectionLabels as $key => $label):
            $p = (int)($r['sections'][$key] ?? 0); ?>
          <div class="progress-row" style="margin-bottom: 12px;">
            <span style="min-width: 140px; font-size: 14px;"><?= e($label) ?></span>
            <?php render_progress($p, 100, $p >= 70 ? 'ok' : ($p >= 40 ? '' : 'warn'), $label); ?>
            <span class="num"><?= $p ?>%</span>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($weakNames !== []): ?>
        <div class="card card--warn">
          <h2 style="font-size: 17px;">Önce şunları güçlendireceğiz</h2>
          <p class="small">Testte hata yaptığın konular tekrar kuyruğuna eklendi. Seviyeni yapay olarak atlamıyoruz;
            bu konular karşına düzenli olarak çıkacak.</p>
          <ul class="small" style="margin-bottom: 0;">
            <?php foreach (array_slice($weakNames, 0, 12) as $name): ?>
              <li><?= e($name) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="row" style="margin-top: 24px;">
        <a class="btn btn--lg btn--inline" href="<?= (int)$user['onboarding_completed'] === 1 ? '/dashboard.php' : '/onboarding.php?step=2' ?>">DEVAM ET</a>
      </div>

    <?php else: ?>
      <p class="eyebrow">Seviye tespit sınavı</p>
      <h1>Gerçek seviyeni ölçelim</h1>
      <p class="muted" style="margin-bottom: 26px;">
        <?= $total ?> soru · yaklaşık 12 dakika. Kelime, dilbilgisi, okuma, yazım, aktif üretim ve cümle kurma
        ayrı ayrı ölçülür. Bilmediğin soruyu boş bırakabilirsin; tahmin etmek sonucu bozar.
      </p>

      <form method="post" action="/placement-test.php" data-guard>
        <?= csrf_field() ?>
        <?php
        $lastSection = '';
        $n = 0;
        foreach ($questions as $q):
            $n++;
            if ($q['section'] !== $lastSection):
                $lastSection = (string)$q['section'];
                ?>
                <h2 style="margin-top: 34px;"><?= e($sectionLabels[$lastSection] ?? $lastSection) ?></h2>
            <?php endif; ?>

          <div class="card" style="margin-bottom: 14px;">
            <div class="eyebrow">Soru <?= $n ?> · <?= e((string)$q['level']) ?></div>
            <?php if (!empty($q['ctx'])): ?>
              <div class="quiz__context" style="margin-bottom: 14px;"><?= e((string)$q['ctx']) ?></div>
            <?php endif; ?>
            <p style="font-weight: 700; color: var(--ink);"><?= e((string)$q['q']) ?></p>

            <?php if (($q['type'] ?? '') === 'multiple_choice' && !empty($q['opts'])):
                $opts = $q['opts'];
                shuffle($opts); ?>
              <div class="stack" style="margin-top: 12px;">
                <?php foreach ($opts as $i => $opt): ?>
                  <span class="checkline">
                    <input type="radio" id="<?= e($q['id']) ?>_<?= $i ?>" name="q_<?= e($q['id']) ?>" value="<?= e((string)$opt) ?>">
                    <label for="<?= e($q['id']) ?>_<?= $i ?>"><?= e((string)$opt) ?></label>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="field" style="margin-top: 12px; margin-bottom: 0;">
                <label for="<?= e($q['id']) ?>" class="sr-only">Cevabın</label>
                <input id="<?= e($q['id']) ?>" name="q_<?= e($q['id']) ?>" type="text" autocomplete="off" spellcheck="false" placeholder="Cevabını yaz">
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <button class="btn btn--block btn--lg" type="submit" style="margin-top: 24px;">TESTİ BİTİR</button>
        <p class="small muted center" style="margin-top: 12px;">
          Sonuç yalnızca başlangıç noktanı belirler. İlerledikçe seviyen sistem tarafından yeniden değerlendirilir.
        </p>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php render_foot(); ?>
