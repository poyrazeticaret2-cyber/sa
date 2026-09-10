<?php
/**
 * AlmancaPro - Site ayarlari.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
app_boot();

$admin = require_admin();
$errors = [];

$timezones = ['Europe/Istanbul', 'Europe/Berlin', 'Europe/London', 'UTC'];

if (is_post()) {
    csrf_require();
    $action = (string)input('action', '');

    if ($action === 'save') {
        $siteName = trim((string)input('site_name', APP_NAME));
        $tagline = trim((string)input('site_tagline', ''));
        $siteUrl = rtrim(trim((string)input('site_url', '')), '/');
        $siteEmail = trim((string)input('site_email', ''));
        $tz = (string)input('default_timezone', APP_DEFAULT_TIMEZONE);
        $goal = max(5, min(240, input_int('default_daily_goal', 30)));
        $mastery = max(60, min(100, input_int('mastery_threshold', MASTERY_THRESHOLD)));
        $unlock = max(50, min(100, input_int('unlock_threshold', MASTERY_UNLOCK_THRESHOLD)));

        if (mb_strlen($siteName) < 2) {
            $errors['site_name'] = 'Site adı en az 2 karakter olmalı.';
        }
        if ($siteUrl !== '' && !filter_var($siteUrl, FILTER_VALIDATE_URL)) {
            $errors['site_url'] = 'Geçerli bir adres girin (https://... ).';
        }
        if ($siteEmail !== '' && !valid_email($siteEmail)) {
            $errors['site_email'] = 'Geçerli bir e-posta adresi girin.';
        }
        if (!in_array($tz, timezone_identifiers_list(), true)) {
            $errors['default_timezone'] = 'Geçerli bir zaman dilimi seçin.';
        }
        if ($unlock > $mastery) {
            $errors['unlock_threshold'] = 'Kilit açma eşiği hakimiyet eşiğinden büyük olamaz.';
        }

        if ($errors === []) {
            setting_set('site_name', $siteName);
            setting_set('site_tagline', $tagline);
            setting_set('site_url', $siteUrl);
            setting_set('site_email', $siteEmail);
            setting_set('default_timezone', $tz);
            setting_set('default_daily_goal', (string)$goal);
            setting_set('mastery_threshold', (string)$mastery);
            setting_set('unlock_threshold', (string)$unlock);
            setting_set('maintenance_mode', !empty($_POST['maintenance_mode']) ? '1' : '0');
            setting_set('registration_open', !empty($_POST['registration_open']) ? '1' : '0');
            setting_set('donations_enabled', !empty($_POST['donations_enabled']) ? '1' : '0');
            admin_log((int)$admin['id'], 'SETTINGS_UPDATED', 'settings', 'site');
            flash('success', 'Site ayarları kaydedildi.');
            redirect('/admin-settings.php');
        }
    }

    if ($action === 'rotate_cron') {
        setting_set('cron_secret', random_token(24), true);
        admin_log((int)$admin['id'], 'CRON_SECRET_ROTATED', 'settings', 'cron_secret');
        flash('success', 'Cron anahtarı yenilendi. Plesk görevindeki adresi güncellemeyi ve Telegram webhook\'unu yeniden ayarlamayı unutmayın.');
        redirect('/admin-settings.php');
    }
}

$cronSecret = (string)setting('cron_secret', '');
$cronUrl = site_url('cron.php') . '?secret=' . $cronSecret;

render_admin_start($admin, 'Site Ayarları');
?>
<section class="card">
  <h2 class="card__title">Genel</h2>
  <form method="post" action="/admin-settings.php" class="stack" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <div class="grid grid--2">
      <label class="field">
        <span class="field__label" for="st-name">Site adı</span>
        <input class="input<?= isset($errors['site_name']) ? ' is-invalid' : '' ?>" id="st-name" name="site_name"
          value="<?= e((string)setting('site_name', APP_NAME)) ?>" required>
        <?php if (isset($errors['site_name'])): ?><span class="field__error">✕ <?= e($errors['site_name']) ?></span><?php endif; ?>
      </label>
      <label class="field">
        <span class="field__label" for="st-tag">Slogan</span>
        <input class="input" id="st-tag" name="site_tagline" value="<?= e((string)setting('site_tagline', '')) ?>">
      </label>
    </div>

    <div class="grid grid--2">
      <label class="field">
        <span class="field__label" for="st-url">Site adresi</span>
        <input class="input<?= isset($errors['site_url']) ? ' is-invalid' : '' ?>" id="st-url" name="site_url"
          value="<?= e((string)setting('site_url', '')) ?>" placeholder="https://alanadiniz.com">
        <span class="field__hint">E-posta bağlantıları ve Telegram webhook adresi bu değeri kullanır.</span>
        <?php if (isset($errors['site_url'])): ?><span class="field__error">✕ <?= e($errors['site_url']) ?></span><?php endif; ?>
      </label>
      <label class="field">
        <span class="field__label" for="st-email">İletişim e-postası</span>
        <input class="input<?= isset($errors['site_email']) ? ' is-invalid' : '' ?>" id="st-email" type="email"
          name="site_email" value="<?= e((string)setting('site_email', '')) ?>">
        <?php if (isset($errors['site_email'])): ?><span class="field__error">✕ <?= e($errors['site_email']) ?></span><?php endif; ?>
      </label>
    </div>

    <div class="grid grid--3">
      <label class="field">
        <span class="field__label" for="st-tz">Varsayılan zaman dilimi</span>
        <select class="select" id="st-tz" name="default_timezone">
          <?php
          $current = (string)setting('default_timezone', APP_DEFAULT_TIMEZONE);
          $list = $timezones;
          if (!in_array($current, $list, true)) {
              array_unshift($list, $current);
          }
          foreach ($list as $tz): ?>
            <option value="<?= e($tz) ?>"<?= $current === $tz ? ' selected' : '' ?>><?= e($tz) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="field__hint">Veritabanı saatleri UTC tutulur, gösterim bu dilime göre yapılır.</span>
      </label>
      <label class="field">
        <span class="field__label" for="st-goal">Varsayılan günlük hedef (dakika)</span>
        <input class="input" id="st-goal" type="number" name="default_daily_goal" min="5" max="240"
          value="<?= e((string)setting_int('default_daily_goal', 30)) ?>">
      </label>
      <label class="field">
        <span class="field__label" for="st-mastery">Hakimiyet eşiği (%)</span>
        <input class="input" id="st-mastery" type="number" name="mastery_threshold" min="60" max="100"
          value="<?= e((string)setting_int('mastery_threshold', MASTERY_THRESHOLD)) ?>">
        <span class="field__hint">Bir skill'in "öğrenildi" sayılması için gereken puan.</span>
      </label>
    </div>

    <label class="field" style="max-width:320px">
      <span class="field__label" for="st-unlock">Ders açma eşiği (%)</span>
      <input class="input<?= isset($errors['unlock_threshold']) ? ' is-invalid' : '' ?>" id="st-unlock" type="number"
        name="unlock_threshold" min="50" max="100" value="<?= e((string)setting_int('unlock_threshold', MASTERY_UNLOCK_THRESHOLD)) ?>">
      <span class="field__hint">Ön koşul skill'lerin bir sonraki derse geçmek için ulaşması gereken puan.</span>
      <?php if (isset($errors['unlock_threshold'])): ?><span class="field__error">✕ <?= e($errors['unlock_threshold']) ?></span><?php endif; ?>
    </label>

    <fieldset class="fieldset">
      <legend class="field__label">Anahtarlar</legend>
      <label class="check"><input type="checkbox" name="registration_open" value="1"<?= setting_bool('registration_open', true) ? ' checked' : '' ?>>
        <span>Yeni kayıtlara açık</span></label>
      <label class="check"><input type="checkbox" name="donations_enabled" value="1"<?= setting_bool('donations_enabled', true) ? ' checked' : '' ?>>
        <span>Bağış sayfası açık (uygulama tamamen ücretsizdir; bağış zorunlu değildir)</span></label>
      <label class="check"><input type="checkbox" name="maintenance_mode" value="1"<?= setting_bool('maintenance_mode') ? ' checked' : '' ?>>
        <span>Bakım modu (yalnızca yöneticiler girebilir)</span></label>
    </fieldset>

    <div class="row"><button class="btn btn--inline" type="submit">Kaydet</button></div>
  </form>
</section>

<section class="card">
  <h2 class="card__title">Cron görevi</h2>
  <p class="small">Cron; tekrar zamanlarını işler, bildirim kuyruğunu gönderir, günlük planları oluşturur,
    seri durumunu günceller ve süresi dolmuş tokenları temizler. Eşzamanlı çalışsa bile kilit mekanizması
    sayesinde çift gönderim yapmaz.</p>

  <h3 class="h4">Plesk · Zamanlanmış Görev (komut satırı)</h3>
  <pre class="code"><code>/opt/plesk/php/8.2/bin/php <?= e(__DIR__) ?>/cron.php</code></pre>

  <h3 class="h4">Alternatif · HTTP tetikleyici</h3>
  <pre class="code"><code><?= e($cronUrl) ?></code></pre>
  <p class="small">Önerilen çalışma sıklığı: <strong>5 dakikada bir</strong>.</p>

  <form method="post" action="/admin-settings.php" data-confirm="Cron anahtarı yenilensin mi? Mevcut görev adresi geçersiz olur.">
    <?= csrf_field() ?><input type="hidden" name="action" value="rotate_cron">
    <button class="btn btn--sm btn--secondary btn--inline" type="submit">Cron anahtarını yenile</button>
  </form>

  <table class="table mt-16">
    <tbody>
      <tr><td>Son çalışma</td><td class="small"><?= e((string)setting('cron_last_run', '') ?: 'Hiç çalışmadı') ?></td></tr>
      <tr><td>Son özet</td><td class="small"><?= e((string)setting('cron_last_summary', '') ?: '—') ?></td></tr>
    </tbody>
  </table>
</section>

<section class="card">
  <h2 class="card__title">Sürüm</h2>
  <table class="table">
    <tbody>
      <tr><td>Uygulama sürümü</td><td class="mono"><?= e((string)setting('app_version', APP_VERSION)) ?></td></tr>
      <tr><td>İçerik sürümü</td><td class="mono"><?= e((string)setting('content_version', '—')) ?></td></tr>
      <tr><td>Kurulum tarihi</td><td class="mono"><?= e((string)setting('installed_at', '—')) ?></td></tr>
    </tbody>
  </table>
</section>
<?php
render_admin_end();
