<?php
/**
 * AlmancaPro - Kurulum sihirbazi.
 *
 * Veritabani bilgileri Plesk uzerinde hazir oldugu icin BURADA SORULMAZ.
 * Yalnizca site ve harici servis ayarlari alinir; bunlar bos birakilirsa
 * site yine tam calisir, ilgili ozellikler admin panelinde "yapilandirilmadi"
 * olarak gorunur.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/installer.php';
require_once __DIR__ . '/admin-auth.php';

app_session_start();
send_security_headers();

$dbOk = Database::isAvailable();
$installed = $dbOk && is_installed();
$isAdmin = current_admin_id() !== null;

/* Kurulum tamamlandiysa yalnizca yonetici tekrar acabilir. */
$locked = $installed && !$isAdmin;

$result = null;
$saved = false;
$errors = [];

if (is_post() && !$locked) {
    csrf_require();
    $action = (string)input('action', '');

    if ($action === 'install') {
        $result = almancapro_run_install();
        $installed = $result['ok'];
    } elseif ($action === 'settings' && $installed) {
        $siteUrl = rtrim(trim((string)input('site_url', '')), '/');
        if ($siteUrl !== '' && !filter_var($siteUrl, FILTER_VALIDATE_URL)) {
            $errors['site_url'] = 'Geçerli bir site adresi gir (https://... biçiminde).';
        }
        $siteEmail = trim((string)input('site_email', ''));
        if ($siteEmail !== '' && !valid_email($siteEmail)) {
            $errors['site_email'] = 'Geçerli bir e-posta adresi gir.';
        }
        $mailFrom = trim((string)input('mail_from', ''));
        if ($mailFrom !== '' && !valid_email($mailFrom)) {
            $errors['mail_from'] = 'Geçerli bir gönderen adresi gir.';
        }
        $botToken = trim((string)($_POST['telegram_bot_token'] ?? ''));
        if ($botToken !== '' && !preg_match('/^\d{6,12}:[A-Za-z0-9_\-]{30,}$/', $botToken)) {
            $errors['telegram_bot_token'] = 'Bot token biçimi hatalı görünüyor.';
        }
        $aiEndpoint = trim((string)input('ai_endpoint', ''));
        if ($aiEndpoint !== '' && !filter_var($aiEndpoint, FILTER_VALIDATE_URL)) {
            $errors['ai_endpoint'] = 'Geçerli bir API adresi gir.';
        }

        if ($errors === []) {
            setting_set('site_url', $siteUrl);
            setting_set('site_name', trim((string)input('site_name', APP_NAME)) ?: APP_NAME);
            setting_set('site_email', $siteEmail);

            setting_set('smtp_host', trim((string)input('smtp_host', '')));
            setting_set('smtp_port', (string)max(1, min(65535, input_int('smtp_port', 587))));
            setting_set('smtp_username', trim((string)input('smtp_username', '')));
            $smtpPass = (string)($_POST['smtp_password'] ?? '');
            if ($smtpPass !== '') {
                setting_set('smtp_password', $smtpPass, true);
            }
            $enc = (string)input('smtp_encryption', 'tls');
            setting_set('smtp_encryption', in_array($enc, ['tls', 'ssl', 'none'], true) ? $enc : 'tls');
            setting_set('mail_from', $mailFrom);
            setting_set('mail_from_name', trim((string)input('mail_from_name', APP_NAME)) ?: APP_NAME);

            if ($botToken !== '') {
                setting_set('telegram_bot_token', $botToken, true);
            }
            setting_set('telegram_bot_username', ltrim(trim((string)input('telegram_bot_username', '')), '@'));

            $cronSecret = trim((string)($_POST['cron_secret'] ?? ''));
            if ($cronSecret !== '') {
                setting_set('cron_secret', $cronSecret, true);
            } elseif ((string)setting('cron_secret', '') === '') {
                setting_set('cron_secret', random_token(24), true);
            }

            setting_set('ai_endpoint', $aiEndpoint);
            setting_set('ai_model', trim((string)input('ai_model', '')));
            $aiKey = (string)($_POST['ai_api_key'] ?? '');
            if ($aiKey !== '') {
                setting_set('ai_api_key', $aiKey, true);
            }
            setting_set('ai_enabled', ($aiEndpoint !== '' && (string)setting('ai_api_key', '') !== '' && trim((string)input('ai_model', '')) !== '') ? '1' : '0');

            $saved = true;
        }
    }
}

$s = settings_all(true);
$cronSecret = (string)setting('cron_secret', '');
$siteBase = rtrim((string)setting('site_url', ''), '/');
if ($siteBase === '') {
    $siteBase = site_url();
}

render_head('Kurulum · ' . APP_NAME, ['noindex' => true]);
?>
<div class="page">
  <div class="focus-area">
    <?php render_logo('/'); ?>
    <p class="eyebrow" style="margin-top: 22px;">Kurulum</p>
    <h1>AlmancaPro kurulumu</h1>

    <?php if (!$dbOk): ?>
      <div class="alert alert--error"><span class="alert__icon" aria-hidden="true">✕</span>
        <span>Veritabanına bağlanılamadı. Sunucudaki veritabanı hesabının aktif olduğundan emin ol.
          Bağlantı bilgileri sunucu tarafında tanımlıdır ve burada gösterilmez.</span></div>

    <?php elseif ($locked): ?>
      <div class="alert alert--success"><span class="alert__icon" aria-hidden="true">✓</span>
        <span>Kurulum daha önce tamamlandı. Bu sayfa yeniden kurulum yapmaz.</span></div>
      <p>Ayarları güncellemek için yönetici paneline giriş yap.</p>
      <div class="row">
        <a class="btn btn--inline" href="/admin-login.php">YÖNETİCİ GİRİŞİ</a>
        <a class="btn btn--secondary btn--inline" href="/">SİTEYE GİT</a>
      </div>

    <?php else: ?>

      <?php if ($result !== null): ?>
        <div class="card card--flush" style="margin-bottom: 22px;">
          <div class="card__head"><strong>Kurulum adımları</strong></div>
          <?php foreach ($result['steps'] as $step): ?>
            <div class="plan-item <?= $step['status'] === 'ok' ? 'is-done' : 'is-pending' ?>">
              <span class="plan-item__state" aria-hidden="true"><?= $step['status'] === 'ok' ? '✓' : '✕' ?></span>
              <span class="plan-item__title"><?= e($step['name']) ?></span>
              <span class="plan-item__meta"><?= e($step['detail']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!$installed): ?>
        <p>Veritabanı bağlantısı hazır. Aşağıdaki düğme şunları yapar:</p>
        <ul class="small">
          <li>Tabloları, indeksleri ve foreign key'leri oluşturur</li>
          <li>Müfredatı, kelime hazinesini, dilbilgisi konularını ve alıştırmaları yükler</li>
          <li>Senaryoları ve bilgi tabanını yükler</li>
          <li>Varsayılan yönetici hesabını oluşturur</li>
        </ul>
        <p class="small muted">İşlem idempotenttir: ikinci kez çalıştırılırsa veriler tekrarlanmaz.</p>
        <form method="post" action="/install.php" data-guard>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="install">
          <button class="btn btn--lg btn--block" type="submit">KURULUMU BAŞLAT</button>
        </form>

      <?php else: ?>
        <?php if ($saved): ?>
          <div class="alert alert--success"><span class="alert__icon" aria-hidden="true">✓</span><span>Ayarlar kaydedildi.</span></div>
        <?php endif; ?>

        <div class="alert alert--info"><span class="alert__icon" aria-hidden="true">●</span>
          <span>Bu adımdaki alanların hepsi <strong>isteğe bağlıdır</strong>. Boş bırakırsan site yine tam çalışır;
            ilgili özellikler admin panelinde "yapılandırılmadı" olarak görünür ve sonradan tamamlanabilir.</span></div>

        <form method="post" action="/install.php" data-guard>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="settings">

          <h2 style="margin-top: 26px;">Site</h2>
          <div class="field">
            <label for="site_url">Site adresi</label>
            <input id="site_url" name="site_url" type="url" placeholder="https://ornek.com" value="<?= e((string)($s['site_url'] ?? '')) ?>">
            <?php if (isset($errors['site_url'])): ?><div class="hint text-danger">✕ <?= e($errors['site_url']) ?></div><?php endif; ?>
            <div class="hint">E-posta bağlantıları, Telegram deep link ve webhook adresi bu değerden üretilir.</div>
          </div>
          <div class="field">
            <label for="site_name">Site adı</label>
            <input id="site_name" name="site_name" type="text" value="<?= e((string)($s['site_name'] ?? APP_NAME)) ?>">
          </div>
          <div class="field">
            <label for="site_email">Site e-posta adresi</label>
            <input id="site_email" name="site_email" type="email" value="<?= e((string)($s['site_email'] ?? '')) ?>">
            <?php if (isset($errors['site_email'])): ?><div class="hint text-danger">✕ <?= e($errors['site_email']) ?></div><?php endif; ?>
          </div>

          <h2 style="margin-top: 26px;">SMTP (e-posta gönderimi)</h2>
          <div class="grid grid-2" style="gap: 12px;">
            <div class="field">
              <label for="smtp_host">SMTP sunucusu</label>
              <input id="smtp_host" name="smtp_host" type="text" value="<?= e((string)($s['smtp_host'] ?? '')) ?>">
            </div>
            <div class="field">
              <label for="smtp_port">Port</label>
              <input id="smtp_port" name="smtp_port" type="number" min="1" max="65535" value="<?= e((string)($s['smtp_port'] ?? '587')) ?>">
            </div>
            <div class="field">
              <label for="smtp_username">Kullanıcı adı</label>
              <input id="smtp_username" name="smtp_username" type="text" autocomplete="off" value="<?= e((string)($s['smtp_username'] ?? '')) ?>">
            </div>
            <div class="field">
              <label for="smtp_encryption">Şifreleme</label>
              <select id="smtp_encryption" name="smtp_encryption">
                <?php foreach (['tls' => 'STARTTLS (genelde 587)', 'ssl' => 'SSL/TLS (genelde 465)', 'none' => 'Yok'] as $k => $label): ?>
                  <option value="<?= e($k) ?>"<?= (string)($s['smtp_encryption'] ?? 'tls') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <?php render_password_field('smtp_password', 'smtp_password', 'SMTP şifresi', 'new-password', false, false); ?>
          <p class="hint" style="margin-top: -10px;">
            <?= (string)($s['smtp_password'] ?? '') !== '' ? 'Kayıtlı bir şifre var. Değiştirmek istemiyorsan boş bırak.' : 'Şifre yalnızca sunucuda saklanır ve hiçbir ekranda geri gösterilmez.' ?>
          </p>
          <div class="grid grid-2" style="gap: 12px;">
            <div class="field">
              <label for="mail_from">Gönderen adresi</label>
              <input id="mail_from" name="mail_from" type="email" value="<?= e((string)($s['mail_from'] ?? '')) ?>">
              <?php if (isset($errors['mail_from'])): ?><div class="hint text-danger">✕ <?= e($errors['mail_from']) ?></div><?php endif; ?>
            </div>
            <div class="field">
              <label for="mail_from_name">Gönderen adı</label>
              <input id="mail_from_name" name="mail_from_name" type="text" value="<?= e((string)($s['mail_from_name'] ?? APP_NAME)) ?>">
            </div>
          </div>

          <h2 style="margin-top: 26px;">Telegram</h2>
          <?php render_password_field('telegram_bot_token', 'telegram_bot_token', 'Bot token (BotFather)', 'off', false, false); ?>
          <?php if (isset($errors['telegram_bot_token'])): ?><div class="hint text-danger" style="margin-top:-10px;margin-bottom:12px;">✕ <?= e($errors['telegram_bot_token']) ?></div><?php endif; ?>
          <p class="hint" style="margin-top: -10px;">
            <?= (string)($s['telegram_bot_token'] ?? '') !== '' ? 'Kayıtlı bir token var (' . e(mask_secret((string)$s['telegram_bot_token'])) . '). Değiştirmek istemiyorsan boş bırak.' : 'Token yalnızca sunucuda saklanır.' ?>
          </p>
          <div class="field">
            <label for="telegram_bot_username">Bot kullanıcı adı</label>
            <input id="telegram_bot_username" name="telegram_bot_username" type="text" placeholder="almancapro_bot" value="<?= e((string)($s['telegram_bot_username'] ?? '')) ?>">
            <div class="hint">Başında @ olmadan yaz. Kullanıcıların bağlantı bağlantısı bundan üretilir.</div>
          </div>

          <h2 style="margin-top: 26px;">Cron</h2>
          <?php render_password_field('cron_secret', 'cron_secret', 'Cron gizli anahtarı', 'off', false, false); ?>
          <p class="hint" style="margin-top: -10px;">
            Boş bırakırsan güçlü bir anahtar otomatik üretilir. Mevcut anahtar admin panelinde gösterilir.
          </p>

          <h2 style="margin-top: 26px;">AI öğretmen (isteğe bağlı)</h2>
          <p class="small muted">Boş bırakılırsa "Öğretmene Sor" dilbilgisi kütüphanesi ve bilgi tabanı üzerinden çalışır.</p>
          <div class="field">
            <label for="ai_endpoint">API adresi</label>
            <input id="ai_endpoint" name="ai_endpoint" type="url" value="<?= e((string)($s['ai_endpoint'] ?? '')) ?>">
            <?php if (isset($errors['ai_endpoint'])): ?><div class="hint text-danger">✕ <?= e($errors['ai_endpoint']) ?></div><?php endif; ?>
          </div>
          <div class="field">
            <label for="ai_model">Model</label>
            <input id="ai_model" name="ai_model" type="text" value="<?= e((string)($s['ai_model'] ?? '')) ?>">
          </div>
          <?php render_password_field('ai_api_key', 'ai_api_key', 'API anahtarı', 'off', false, false); ?>
          <p class="hint" style="margin-top: -10px;">
            API anahtarı yalnızca sunucuda kullanılır ve hiçbir zaman tarayıcıya gönderilmez.
          </p>

          <button class="btn btn--lg btn--block" type="submit" style="margin-top: 20px;">AYARLARI KAYDET</button>
        </form>

        <div class="card" style="margin-top: 30px;">
          <h2 style="font-size: 18px;">Sonraki adımlar</h2>
          <ol class="small">
            <li>Yönetici paneline giriş yap: <a href="/admin-login.php">/admin-login.php</a><br>
              Kullanıcı adı <span class="mono">Admin</span> · İlk şifre <span class="mono">Admin12345!</span> —
              <strong>ilk girişte şifreyi değiştir.</strong></li>
            <li>Telegram için: Admin → Telegram sayfasından webhook kurulumunu yap.</li>
            <li>Cron için Plesk → Scheduled Tasks içine şunu ekle (günde birkaç kez):<br>
              <span class="mono small">php <?= e(APP_ROOT) ?>/cron.php</span><br>
              veya HTTP: <span class="mono small"><?= e($siteBase . '/cron.php?secret=' . ($cronSecret !== '' ? mask_secret($cronSecret, 6) : 'CRON_SECRET')) ?></span>
            </li>
            <li>SMTP ayarlarını test et: Admin → SMTP Ayarları → Test e-postası gönder.</li>
          </ol>
          <div class="row">
            <a class="btn btn--inline" href="/admin-login.php">YÖNETİCİ GİRİŞİ</a>
            <a class="btn btn--secondary btn--inline" href="/">SİTEYE GİT</a>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php render_foot(); ?>
