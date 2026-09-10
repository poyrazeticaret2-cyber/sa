<?php
/**
 * AlmancaPro - Ogrenme yolu (A0 -> B1).
 *
 * Kilit durumu her zaman sunucuda hesaplanir; buradaki gorsel durum yalnizca
 * o hesabin yansimasidir. Adres cubugundan ders acmak mumkun degildir.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/learning.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];

$path = learning_path($userId);
$levels = level_summary($userId);
$next = next_activity($userId, $user);
$dueCounts = due_review_counts($userId);

/* Kilitli ders ayrintisi (lesson.php veya quiz.php buraya yonlendirir). */
$lockedId = input_int('locked', 0);
$lockedLesson = null;
$lockState = null;
if ($lockedId > 0) {
    $lockedLesson = db_row('SELECT * FROM lessons WHERE id = ? AND is_active = 1', [$lockedId]);
    if ($lockedLesson !== null) {
        $lockState = lesson_lock_state($userId, $lockedId);
    }
}

/* Seviyeye gore grupla */
$byLevel = [];
foreach ($path as $module) {
    $byLevel[(string)$module['cefr_level']][] = $module;
}

$totalLessons = 0;
$doneLessons = 0;
foreach ($path as $m) {
    $totalLessons += (int)$m['lesson_count'];
    $doneLessons += (int)$m['completed_count'];
}

$stateLabel = static function (string $state): array {
    return match ($state) {
        'completed' => ['✓', 'Tamamlandı', 'success'],
        'active'    => ['▸', 'Aktif', 'info'],
        'review'    => ['↻', 'Tekrar gerekiyor', 'warn'],
        'locked'    => ['🔒', 'Kilitli', ''],
        default     => ['○', 'Hazır', ''],
    };
};

render_app_start($user, 'Öğrenme Yolu', ['css' => ['learning.css']], ['due' => $dueCounts['due']]);
?>
<h1>Öğrenme Yolu</h1>
<p class="lead">A0'dan B1'e kadar sıralı bir yol. Bir dersi açmak için önceki konuları
  <strong>gerçekten öğrendiğini kanıtlaman</strong> gerekir; ileri atlamak mümkün değildir.</p>

<div class="card card--accent">
  <div class="row row--between">
    <div>
      <div class="card__label">SIRADAKİ ADIM</div>
      <div class="h3"><?= e((string)$next['title']) ?></div>
      <p class="small"><?= e((string)$next['reason']) ?> · Tahmini <?= (int)$next['minutes'] ?> dakika</p>
    </div>
    <a class="btn btn--lg" href="<?= e((string)$next['url']) ?>">DEVAM ET</a>
  </div>
</div>

<div class="stat-grid">
  <div class="card stat"><div class="stat__label">Tamamlanan ders</div><div class="stat__value"><?= $doneLessons ?>/<?= $totalLessons ?></div></div>
  <div class="card stat"><div class="stat__label">Mevcut seviye</div><div class="stat__value"><?= e((string)$user['cefr_level']) ?></div></div>
  <div class="card stat"><div class="stat__label">Tekrar bekleyen</div><div class="stat__value"><?= (int)$dueCounts['due'] ?></div></div>
  <div class="card stat"><div class="stat__label">Geciken</div><div class="stat__value"><?= (int)$dueCounts['overdue'] ?></div></div>
</div>

<?php if ($lockedLesson !== null && $lockState !== null && !$lockState['unlocked']): ?>
<section class="card card--warn" id="lock" tabindex="-1">
  <div class="card__label">🔒 KİLİTLİ DERS</div>
  <h2 class="card__title">Bu derse henüz hazır değilsin</h2>
  <p><strong><?= e((string)$lockedLesson['title']) ?></strong> dersini açmak için aşağıdaki konuları
    yeterli düzeyde öğrenmen gerekiyor. Bu bir ceza değil: temel eksikken üzerine konu koymak
    kalıcı öğrenmeyi engeller.</p>

  <div class="lock-reasons">
    <?php foreach ($lockState['requirements'] as $r): ?>
      <div class="lock-req <?= $r['ok'] ? 'is-ok' : 'is-missing' ?>">
        <span class="lock-req__state" aria-hidden="true"><?= $r['ok'] ? '✓' : '●' ?></span>
        <span class="lock-req__name"><?= e($r['name']) ?></span>
        <?php render_progress($r['score'], 100, $r['ok'] ? 'success' : 'warn', $r['name'] . ' hakimiyeti'); ?>
        <span class="lock-req__val"><?= (int)$r['score'] ?>% / <?= (int)$r['required'] ?>%</span>
        <span class="visually-hidden"><?= $r['ok'] ? 'Yeterli' : 'Eksik' ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <p class="small">Gereken eşik: <strong><?= (int)($lockState['requirements'][0]['required'] ?? MASTERY_UNLOCK_THRESHOLD) ?>%</strong>.
    Eksik <?= (int)$lockState['missing_count'] ?> konu var.</p>

  <div class="row">
    <a class="btn" href="/exercise.php?lesson=<?= (int)$lockedLesson['id'] ?>&amp;mode=remediation">EKSİKLERİ ÇALIŞ</a>
    <a class="btn btn--secondary" href="/course.php">YOL HARİTASINA DÖN</a>
  </div>
</section>
<?php endif; ?>

<section class="card">
  <h2 class="card__title">Seviye özeti</h2>
  <div class="grid grid--4">
    <?php foreach ($levels as $lv): ?>
      <div class="level-card">
        <div class="level-card__code mono"><?= e($lv['level']) ?></div>
        <div class="small"><?= e(cefr_label($lv['level'])) ?></div>
        <?php render_progress($lv['completed'], max(1, $lv['total']), $lv['percent'] >= 100 ? 'success' : '', $lv['level'] . ' ilerlemesi'); ?>
        <div class="small mono"><?= (int)$lv['completed'] ?>/<?= (int)$lv['total'] ?> ders · <?= (int)$lv['percent'] ?>%</div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php foreach (cefr_levels() as $level): ?>
  <?php $modules = $byLevel[$level] ?? []; ?>
  <?php if ($modules === []) { continue; } ?>
  <section class="path-level">
    <div class="path-level__head">
      <h2 class="h3"><?= e($level) ?> · <?= e(cefr_label($level)) ?></h2>
      <span class="small mono"><?= count($modules) ?> modül</span>
    </div>

    <?php foreach ($modules as $m): [$mi, $mt, $mv] = $stateLabel((string)$m['state']); ?>
      <article class="path-module">
        <header class="path-module__head">
          <div>
            <div class="path-module__title"><?= e((string)$m['title']) ?></div>
            <?php if (!empty($m['description'])): ?>
              <p class="path-module__desc"><?= e((string)$m['description']) ?></p>
            <?php endif; ?>
          </div>
          <div class="row">
            <?php render_badge($mi, $mt, $mv); ?>
            <span class="small mono"><?= (int)$m['completed_count'] ?>/<?= (int)$m['lesson_count'] ?></span>
            <?php render_progress((int)$m['completed_count'], max(1, (int)$m['lesson_count']), (int)$m['percent'] >= 100 ? 'success' : '', (string)$m['title'] . ' ilerlemesi'); ?>
          </div>
        </header>

        <div class="path-lessons">
          <?php foreach ($m['lessons'] as $i => $l):
              [$li, $lt, $lv2] = $stateLabel((string)$l['state']);
              $locked = (bool)$l['locked'];
              $href = $locked
                  ? '/course.php?locked=' . (int)$l['id'] . '#lock'
                  : '/lesson.php?id=' . (int)$l['id'];
          ?>
            <a class="path-lesson<?= $locked ? ' path-lesson--locked' : '' ?>" href="<?= e($href) ?>">
              <span class="path-lesson__num" aria-hidden="true"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
              <span class="path-lesson__body">
                <span class="path-lesson__title"><?= e((string)$l['title']) ?></span>
                <span class="path-lesson__meta">
                  <?= e((string)$l['cefr_level']) ?> ·
                  <?= (int)$l['estimated_minutes'] ?> dk
                  <?php if ($locked): ?>
                    · <?= (int)$l['missing_count'] ?> eksik ön koşul
                  <?php elseif ((string)$l['state'] === 'completed'): ?>
                    · En iyi skor <?= (int)$l['best_score'] ?>%
                  <?php endif; ?>
                </span>
              </span>
              <?php render_badge($li, $lt, $lv2); ?>
            </a>
          <?php endforeach; ?>
          <?php if ($m['lessons'] === []): ?>
            <div class="path-lesson"><span class="small">Bu modülde henüz ders yok.</span></div>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>

<?php if ($path === []): ?>
  <?php render_empty('Müfredat yüklenmedi', 'Yönetici panelinden içerik kurulumunu tamamlayın.'); ?>
<?php endif; ?>

<p class="small">Durumlar renkten bağımsız olarak ikon ve yazıyla da gösterilir:
  ✓ Tamamlandı · ▸ Aktif · ↻ Tekrar gerekiyor · 🔒 Kilitli · ○ Hazır.</p>
<?php
render_app_end();
