<?php
/**
 * AlmancaPro - Ilk giris kurulumu.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_login();
if ((int)$user['onboarding_completed'] === 1) {
    redirect('/dashboard.php');
}

$step = max(1, min(5, input_int('step', 1)));
$error = '';

if (is_post()) {
    csrf_require();
    $step = max(1, min(5, input_int('step', 1)));

    switch ($step) {
        case 1:
            $level = (string)input('level', '');
            $valid = ['none', 'little', 'a1', 'a2', 'test'];
            if (!in_array($level, $valid, true)) {
                $error = 'Lütfen bir seçim yap.';
                break;
            }
            $_SESSION['onboard']['level'] = $level;
            if ($level === 'test') {
                redirect('/placement-test.php');
            }
            redirect('/onboarding.php?step=2');

        case 2:
            $reason = (string)input('goal_reason', '');
            $valid = ['is', 'egitim', 'yasam', 'aile', 'seyahat', 'diger'];
            if (!in_array($reason, $valid, true)) {
                $error = 'Lütfen bir seçim yap.';
                break;
            }
            $_SESSION['onboard']['goal_reason'] = $reason;
            redirect('/onboarding.php?step=3');

        case 3:
            $date = trim((string)input('departure_date', ''));
            if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $error = 'Tarihi gg.aa.yyyy biçiminde seç veya boş bırak.';
                break;
            }
            $_SESSION['onboard']['departure_date'] = $date !== '' ? $date : null;
            redirect('/onboarding.php?step=4');

        case 4:
            $minutes = input_int('daily_minutes', 30);
            if (!in_array($minutes, [15, 30, 45, 60, 90, 120], true)) {
                $error = 'Lütfen bir süre seç.';
                break;
            }
            $_SESSION['onboard']['daily_minutes'] = $minutes;
            redirect('/onboarding.php?step=5');

        case 5:
            $mode = (string)input('reminder_mode', 'normal');
            if (!in_array($mode, ['hafif', 'normal', 'yogun', 'ozel'], true)) {
                $mode = 'normal';
            }
            $timezone = (string)input('timezone', APP_DEFAULT_TIMEZONE);
            try {
                new DateTimeZone($timezone);
            } catch (Throwable $e) {
                $timezone = APP_DEFAULT_TIMEZONE;
            }

            $data = $_SESSION['onboard'] ?? [];
            $levelChoice = (string)($data['level'] ?? 'none');
            $startLevel = match ($levelChoice) {
                'little' => 'A1',
                'a1'     => 'A1',
                'a2'     => 'A2',
                default  => 'A0',
            };
            /* Seviye testi yapildiysa onun sonucu onceliklidir. */
            $placement = db_row('SELECT recommended_level FROM placement_tests WHERE user_id = ? ORDER BY id DESC LIMIT 1', [(int)$user['id']]);
            if ($placement !== null) {
                $startLevel = (string)$placement['recommended_level'];
            }

            db_exec(
                'UPDATE users SET cefr_level = ?, start_level = ?, goal_reason = ?, departure_date = ?,
                    daily_minutes = ?, timezone = ?, onboarding_completed = 1,
                    intensive_enabled = ?, intensive_started_on = ?
                 WHERE id = ?',
                [
                    $startLevel,
                    $startLevel,
                    $data['goal_reason'] ?? null,
                    $data['departure_date'] ?? null,
                    (int)($data['daily_minutes'] ?? 30),
                    $timezone,
                    !empty($data['departure_date']) ? 1 : 0,
                    !empty($data['departure_date']) ? local_date($timezone) : null,
                    (int)$user['id'],
                ]
            );

            db_exec(
                'INSERT INTO notification_preferences (user_id, mode, timezone, daily_target)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE mode = VALUES(mode), timezone = VALUES(timezone), daily_target = VALUES(daily_target)',
                [(int)$user['id'], $mode, $timezone, (int)($data['daily_minutes'] ?? 30)]
            );

            unset($_SESSION['onboard']);
            $fresh = current_user(true);
            if ($fresh !== null) {
                daily_plan((int)$fresh['id'], $fresh, true);
            }
            flash('success', 'Hazırsın. Bugünkü planın oluşturuldu.');
            redirect('/dashboard.php');
    }
}

$saved = $_SESSION['onboard'] ?? [];

render_head('Başlangıç · ' . APP_NAME, ['css' => ['auth.css'], 'noindex' => true]);
?>
<div class="auth-wrap">
  <div class="auth-card" style="width: 560px;">
    <div class="auth-card__head">
      <?php render_logo('/'); ?>
      <div class="auth-steps" aria-hidden="true" style="margin-top: 20px;">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <span class="auth-step <?= $i < $step ? 'is-done' : ($i === $step ? 'is-active' : '') ?>"></span>
        <?php endfor; ?>
      </div>
      <p class="eyebrow">Adım <?= $step ?> / 5</p>
    </div>

    <?php render_flashes(); ?>
    <?php if ($error !== ''): ?>
      <div class="alert alert--error"><span class="alert__icon" aria-hidden="true">✕</span><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <form method="post" action="/onboarding.php" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="step" value="<?= $step ?>">

      <?php if ($step === 1): ?>
        <h1>Almanca seviyen nedir?</h1>
        <p class="auth-card__sub" style="margin-bottom: 20px;">Doğru yerden başlamak, hızlı ilerlemekten daha önemlidir.</p>
        <div class="onboard-grid">
          <?php foreach ([
            'none'   => ['Hiç bilmiyorum', 'A0\'dan, alfabeden başlarız.'],
            'little' => ['Biraz biliyorum', 'Birkaç kelime ve kalıp biliyorsan buradan başla.'],
            'a1'     => ['A1 civarı', 'Temel cümle kurabiliyorsan.'],
            'a2'     => ['A2 civarı', 'Perfekt ve yan cümle biliyorsan.'],
            'test'   => ['Seviye testi yap', 'Emin değilsen 37 soruluk test seviyeni ölçer.'],
          ] as $key => [$label, $hint]): ?>
            <button class="onboard-option<?= ($saved['level'] ?? '') === $key ? ' is-selected' : '' ?>" type="submit" name="level" value="<?= e($key) ?>">
              <span>
                <strong><?= e($label) ?></strong>
                <span class="onboard-option__hint"><?= e($hint) ?></span>
              </span>
            </button>
          <?php endforeach; ?>
        </div>

      <?php elseif ($step === 2): ?>
        <h1>Almanya'ya neden gidiyorsun?</h1>
        <p class="auth-card__sub" style="margin-bottom: 20px;">Buna göre hangi kelime ve konulara öncelik vereceğimizi belirliyoruz.</p>
        <div class="onboard-grid">
          <?php foreach ([
            'is' => 'İş', 'egitim' => 'Eğitim', 'yasam' => 'Yaşam',
            'aile' => 'Aile', 'seyahat' => 'Seyahat', 'diger' => 'Diğer',
          ] as $key => $label): ?>
            <button class="onboard-option<?= ($saved['goal_reason'] ?? '') === $key ? ' is-selected' : '' ?>" type="submit" name="goal_reason" value="<?= e($key) ?>">
              <strong><?= e($label) ?></strong>
            </button>
          <?php endforeach; ?>
        </div>

      <?php elseif ($step === 3): ?>
        <h1>Almanya'ya tahmini gidiş tarihin?</h1>
        <p class="auth-card__sub" style="margin-bottom: 20px;">
          Tarih girersen günlük hedeflerin kalan süreye göre ayarlanır. Bilmiyorsan boş bırakabilirsin.
        </p>
        <div class="field">
          <label for="departure_date">Gidiş tarihi</label>
          <input id="departure_date" name="departure_date" type="date" value="<?= e((string)($saved['departure_date'] ?? '')) ?>">
          <div class="hint">Bu tarih yalnızca planını ayarlamak için kullanılır, kimseyle paylaşılmaz.</div>
        </div>
        <button class="btn btn--block btn--lg" type="submit">DEVAM ET</button>

      <?php elseif ($step === 4): ?>
        <h1>Günde ne kadar ayırabilirsin?</h1>
        <p class="auth-card__sub" style="margin-bottom: 20px;">Her gün 30 dakika, haftada bir 4 saatten çok daha etkilidir.</p>
        <div class="onboard-grid">
          <?php foreach ([15 => '15 dakika', 30 => '30 dakika', 45 => '45 dakika', 60 => '60 dakika', 90 => '90 dakika', 120 => '120+ dakika'] as $val => $label): ?>
            <button class="onboard-option<?= (int)($saved['daily_minutes'] ?? 0) === $val ? ' is-selected' : '' ?>" type="submit" name="daily_minutes" value="<?= $val ?>">
              <strong><?= e($label) ?></strong>
            </button>
          <?php endforeach; ?>
        </div>

      <?php else: ?>
        <h1>Hatırlatma tercihin</h1>
        <p class="auth-card__sub" style="margin-bottom: 20px;">Bunları daha sonra Ayarlar sayfasından değiştirebilirsin.</p>
        <div class="field">
          <label for="reminder_mode">Hatırlatma yoğunluğu</label>
          <select id="reminder_mode" name="reminder_mode">
            <option value="hafif">Hafif — sadece günlük tek hatırlatma</option>
            <option value="normal" selected>Normal — günlük hedef ve tekrar zamanı</option>
            <option value="yogun">Yoğun — mini quizler dahil</option>
            <option value="ozel">Özel — ayarları ben belirleyeceğim</option>
          </select>
        </div>
        <div class="field">
          <label for="timezone">Saat dilimin</label>
          <select id="timezone" name="timezone">
            <?php foreach (['Europe/Istanbul' => 'Türkiye (Europe/Istanbul)', 'Europe/Berlin' => 'Almanya (Europe/Berlin)', 'Europe/Vienna' => 'Avusturya (Europe/Vienna)', 'Europe/Zurich' => 'İsviçre (Europe/Zurich)', 'UTC' => 'UTC'] as $tz => $label): ?>
              <option value="<?= e($tz) ?>"<?= $tz === APP_DEFAULT_TIMEZONE ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Günlük hedefin ve hatırlatmalar bu saat dilimine göre hesaplanır.</div>
        </div>
        <button class="btn btn--block btn--lg" type="submit">ÖĞRENMEYE BAŞLA</button>
      <?php endif; ?>
    </form>

    <?php if ($step > 1): ?>
      <div class="auth-alt"><a href="/onboarding.php?step=<?= $step - 1 ?>">← Geri</a></div>
    <?php endif; ?>
  </div>
</div>
<?php render_foot(); ?>
