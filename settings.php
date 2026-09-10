<?php
/**
 * AlmancaPro - Ogrenme ve bildirim ayarlari.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];
$counts = due_review_counts($userId);

db_exec('INSERT IGNORE INTO notification_preferences (user_id, timezone, daily_target) VALUES (?, ?, ?)',
    [$userId, user_timezone($user), (int)$user['daily_minutes']]);
$prefs = db_row('SELECT * FROM notification_preferences WHERE user_id = ?', [$userId]) ?? [];

$errors = [];

if (is_post()) {
    csrf_require();
    $action = (string)input('action', '');

    if ($action === 'learning') {
        $minutes = input_int('daily_minutes', 30);
        if (!in_array($minutes, [15, 30, 45, 60, 90, 120], true)) {
            $minutes = 30;
        }
        $date = trim((string)input('departure_date', ''));
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors['departure_date'] = 'Tarihi geçerli biçimde seç veya boş bırak.';
        }
        $timezone = (string)input('timezone', APP_DEFAULT_TIMEZONE);
        try {
            new DateTimeZone($timezone);
        } catch (Throwable $e) {
            $timezone = APP_DEFAULT_TIMEZONE;
        }
        $goal = (string)input('goal_reason', '');
        if (!in_array($goal, ['is', 'egitim', 'yasam', 'aile', 'seyahat', 'diger'], true)) {
            $goal = (string)($user['goal_reason'] ?? 'diger');
        }

        if ($errors === []) {
            db_exec(
                'UPDATE users SET daily_minutes = ?, departure_date = ?, timezone = ?, goal_reason = ? WHERE id = ?',
                [$minutes, $date !== '' ? $date : null, $timezone, $goal, $userId]
            );
            db_exec('UPDATE notification_preferences SET timezone = ?, daily_target = ? WHERE user_id = ?', [$timezone, $minutes, $userId]);
            $user = current_user(true) ?? $user;
            daily_plan($userId, $user, true);
            flash('success', 'Öğrenme ayarların güncellendi ve bugünkü plan yeniden oluşturuldu.');
            redirect('/settings.php');
        }
    } elseif ($action === 'notifications') {
        $mode = (string)input('mode', 'normal');
        if (!in_array($mode, ['hafif', 'normal', 'yogun', 'ozel'], true)) {
            $mode = 'normal';
        }
        $timeOr = static function (string $key, string $default): string {
            $v = (string)input($key, $default);
            return preg_match('/^\d{2}:\d{2}$/', $v) ? $v . ':00' : $default;
        };
        db_exec(
            'UPDATE notification_preferences SET
                mode = ?, daily_reminder = ?, review_reminder = ?, weak_vocabulary = ?, mini_quiz = ?,
                goal_reminder = ?, streak_risk = ?, unfinished_lesson = ?, telegram_enabled = ?, email_enabled = ?,
                start_time = ?, end_time = ?, quiet_start = ?, quiet_end = ?
             WHERE user_id = ?',
            [
                $mode,
                !empty($_POST['daily_reminder']) ? 1 : 0,
                !empty($_POST['review_reminder']) ? 1 : 0,
                !empty($_POST['weak_vocabulary']) ? 1 : 0,
                !empty($_POST['mini_quiz']) ? 1 : 0,
                !empty($_POST['goal_reminder']) ? 1 : 0,
                !empty($_POST['streak_risk']) ? 1 : 0,
                !empty($_POST['unfinished_lesson']) ? 1 : 0,
                !empty($_POST['telegram_enabled']) ? 1 : 0,
                !empty($_POST['email_enabled']) ? 1 : 0,
                $timeOr('start_time', '08:00:00'),
                $timeOr('end_time', '22:00:00'),
                $timeOr('quiet_start', '22:00:00'),
                $timeOr('quiet_end', '08:00:00'),
                $userId,
            ]
        );
        flash('success', 'Bildirim tercihlerin kaydedildi.');
        redirect('/settings.php');
    }
}

$timezones = ['Europe/Istanbul', 'Europe/Berlin', 'Europe/Vienna', 'Europe/Zurich', 'UTC'];

render_app_start($user, 'Ayarlar · ' . APP_NAME, [], ['review.php' => $counts['due']]);
?>
<p class="eyebrow">Ayarlar</p>
<h1>Öğrenme ve bildirim ayarları</h1>

<div class="grid grid-2" style="margin-top: 22px;">
  <section class="card">
    <h2 style="font-size: 18px;">Öğrenme</h2>
    <form method="post" action="/settings.php" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="learning">

      <div class="field">
        <label for="daily_minutes">Günlük hedef süre</label>
        <select id="daily_minutes" name="daily_minutes">
          <?php foreach ([15, 30, 45, 60, 90, 120] as $m): ?>
            <option value="<?= $m ?>"<?= (int)$user['daily_minutes'] === $m ? ' selected' : '' ?>><?= $m ?> dakika</option>
          <?php endforeach; ?>
        </select>
        <div class="hint">Günlük plan ve hedefler bu süreye göre hesaplanır.</div>
      </div>

      <div class="field">
        <label for="departure_date">Almanya'ya gidiş tarihi</label>
        <input id="departure_date" name="departure_date" type="date" value="<?= e((string)($user['departure_date'] ?? '')) ?>">
        <?php if (isset($errors['departure_date'])): ?><div class="hint text-danger">✕ <?= e($errors['departure_date']) ?></div><?php endif; ?>
        <div class="hint">Boş bırakabilirsin. Tarih girersen hedefler kalan süreye göre yoğunlaşır.</div>
      </div>

      <div class="field">
        <label for="goal_reason">Almanya'ya gidiş amacın</label>
        <select id="goal_reason" name="goal_reason">
          <?php foreach (['is' => 'İş', 'egitim' => 'Eğitim', 'yasam' => 'Yaşam', 'aile' => 'Aile', 'seyahat' => 'Seyahat', 'diger' => 'Diğer'] as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= (string)($user['goal_reason'] ?? '') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="timezone">Saat dilimi</label>
        <select id="timezone" name="timezone">
          <?php foreach ($timezones as $tzOpt): ?>
            <option value="<?= e($tzOpt) ?>"<?= (string)$user['timezone'] === $tzOpt ? ' selected' : '' ?>><?= e($tzOpt) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint">Günlük hedefin ve hatırlatmalar bu saat dilimine göre çalışır.</div>
      </div>

      <button class="btn btn--inline" type="submit">KAYDET</button>
    </form>
  </section>

  <section class="card">
    <h2 style="font-size: 18px;">Bildirimler</h2>
    <form method="post" action="/settings.php" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="notifications">

      <div class="field">
        <label for="mode">Hatırlatma yoğunluğu</label>
        <select id="mode" name="mode">
          <?php foreach (['hafif' => 'Hafif', 'normal' => 'Normal', 'yogun' => 'Yoğun', 'ozel' => 'Özel'] as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= (string)($prefs['mode'] ?? 'normal') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <fieldset>
        <legend>Hangi bildirimleri almak istersin?</legend>
        <?php foreach ([
          'daily_reminder' => 'Günlük çalışma hatırlatması',
          'review_reminder' => 'Tekrar zamanı geldiğinde',
          'weak_vocabulary' => 'Zayıf kelimeler için hatırlatma',
          'mini_quiz' => 'Telegram mini quiz',
          'goal_reminder' => 'Günlük hedef durumu',
          'streak_risk' => 'Seri riski uyarısı',
          'unfinished_lesson' => 'Yarım kalan ders hatırlatması',
        ] as $key => $label): ?>
          <div class="checkline">
            <input id="<?= e($key) ?>" name="<?= e($key) ?>" type="checkbox" value="1"<?= (int)($prefs[$key] ?? 1) === 1 ? ' checked' : '' ?>>
            <label for="<?= e($key) ?>"><?= e($label) ?></label>
          </div>
        <?php endforeach; ?>
      </fieldset>

      <fieldset>
        <legend>Kanallar</legend>
        <div class="checkline">
          <input id="telegram_enabled" name="telegram_enabled" type="checkbox" value="1"<?= (int)($prefs['telegram_enabled'] ?? 1) === 1 ? ' checked' : '' ?>>
          <label for="telegram_enabled">Telegram bildirimleri</label>
        </div>
        <div class="checkline">
          <input id="email_enabled" name="email_enabled" type="checkbox" value="1"<?= (int)($prefs['email_enabled'] ?? 0) === 1 ? ' checked' : '' ?>>
          <label for="email_enabled">E-posta hatırlatmaları</label>
        </div>
      </fieldset>

      <div class="grid grid-2" style="gap: 12px;">
        <div class="field">
          <label for="start_time">Bildirim başlangıcı</label>
          <input id="start_time" name="start_time" type="time" value="<?= e(substr((string)($prefs['start_time'] ?? '08:00:00'), 0, 5)) ?>">
        </div>
        <div class="field">
          <label for="end_time">Bildirim bitişi</label>
          <input id="end_time" name="end_time" type="time" value="<?= e(substr((string)($prefs['end_time'] ?? '22:00:00'), 0, 5)) ?>">
        </div>
        <div class="field">
          <label for="quiet_start">Sessiz saat başlangıcı</label>
          <input id="quiet_start" name="quiet_start" type="time" value="<?= e(substr((string)($prefs['quiet_start'] ?? '22:00:00'), 0, 5)) ?>">
        </div>
        <div class="field">
          <label for="quiet_end">Sessiz saat bitişi</label>
          <input id="quiet_end" name="quiet_end" type="time" value="<?= e(substr((string)($prefs['quiet_end'] ?? '08:00:00'), 0, 5)) ?>">
        </div>
      </div>

      <button class="btn btn--inline" type="submit">BİLDİRİMLERİ KAYDET</button>
    </form>
  </section>
</div>

<section class="card" style="margin-top: 24px;">
  <h2 style="font-size: 18px;">Verilerin ve gizliliğin</h2>
  <p class="small">
    Telegram bağlantını istediğin zaman kesebilir, bildirimleri tamamen kapatabilir veya hesabını kalıcı olarak silebilirsin.
  </p>
  <div class="row">
    <a class="btn btn--sm btn--secondary btn--inline" href="/telegram.php">TELEGRAM AYARLARI</a>
    <a class="btn btn--sm btn--secondary btn--inline" href="/privacy.php">GİZLİLİK POLİTİKASI</a>
    <a class="btn btn--sm btn--danger btn--inline" href="/delete-account.php">HESABI SİL</a>
  </div>
</section>

<?php render_app_end(); ?>
