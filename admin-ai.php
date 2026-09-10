<?php
/**
 * AlmancaPro - AI ayarlari.
 * API anahtari yalnizca sunucuda tutulur; panelde maskeli gosterilir ve tarayiciya gonderilmez.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/ai.php';
app_boot();

$admin = require_admin();
$errors = [];
$testResult = null;

if (is_post()) {
    csrf_require();
    $action = (string)input('action', '');

    if ($action === 'save') {
        $endpoint = trim((string)input('ai_endpoint', ''));
        $model = trim((string)input('ai_model', ''));
        $prompt = trim((string)input('ai_system_prompt', ''));
        $maxTokens = max(200, min(4000, input_int('ai_max_tokens', 900)));
        $dailyLimit = max(0, min(500, input_int('ai_daily_limit', 40)));
        $enabled = !empty($_POST['ai_enabled']);
        $newKey = (string)input('ai_api_key', '');

        if ($endpoint !== '' && !filter_var($endpoint, FILTER_VALIDATE_URL)) {
            $errors['ai_endpoint'] = 'Geçerli bir URL girin.';
        }
        if ($endpoint !== '' && !str_starts_with($endpoint, 'https://')) {
            $errors['ai_endpoint'] = 'API adresi HTTPS olmalıdır.';
        }
        if (mb_strlen($prompt) < 40) {
            $errors['ai_system_prompt'] = 'Sistem talimatı en az 40 karakter olmalı.';
        }

        $keyPresent = (string)setting('ai_api_key', '') !== '' || $newKey !== '';
        if ($enabled && ($endpoint === '' || $model === '' || !$keyPresent)) {
            $errors['ai_enabled'] = 'AI açık olabilmesi için adres, model ve API anahtarı gerekli.';
        }

        if ($errors === []) {
            setting_set('ai_endpoint', $endpoint);
            setting_set('ai_model', $model);
            setting_set('ai_system_prompt', $prompt);
            setting_set('ai_max_tokens', (string)$maxTokens);
            setting_set('ai_daily_limit', (string)$dailyLimit);
            setting_set('ai_enabled', $enabled ? '1' : '0');
            if ($newKey !== '') {
                setting_set('ai_api_key', $newKey, true);
                admin_log((int)$admin['id'], 'AI_KEY_UPDATED', 'settings', 'ai_api_key');
            }
            if (!empty($_POST['clear_key'])) {
                setting_set('ai_api_key', '', true);
                setting_set('ai_enabled', '0');
                admin_log((int)$admin['id'], 'AI_KEY_CLEARED', 'settings', 'ai_api_key');
            }
            admin_log((int)$admin['id'], 'AI_SETTINGS_UPDATED', 'settings', 'ai', [
                'enabled' => $enabled ? 1 : 0,
                'model' => $model,
            ]);
            flash('success', 'AI ayarları kaydedildi.');
            redirect('/admin-ai.php');
        }
    }

    if ($action === 'reset_prompt') {
        require_once __DIR__ . '/installer.php';
        setting_set('ai_system_prompt', almancapro_default_ai_prompt());
        admin_log((int)$admin['id'], 'AI_PROMPT_RESET', 'settings', 'ai_system_prompt');
        flash('success', 'Sistem talimatı varsayılana döndürüldü.');
        redirect('/admin-ai.php');
    }

    if ($action === 'test') {
        if (!ai_is_enabled()) {
            flash('error', 'AI yapılandırılmadı veya kapalı. Önce ayarları kaydedin.');
            redirect('/admin-ai.php');
        }
        $fakeUser = ['id' => 0, 'name' => 'Test', 'cefr_level' => 'A1'];
        $res = ai_ask(0, $fakeUser, 'Almancada "mit" edatı hangi durumu ister? Kısaca açıkla.');
        $testResult = $res;
        admin_log((int)$admin['id'], 'AI_TEST', 'settings', 'ai', ['ok' => !empty($res['ok'])]);
    }
}

$hasKey = (string)setting('ai_api_key', '') !== '';
$enabled = setting_bool('ai_enabled');

$usage = db_row(
    'SELECT COUNT(*) AS total, SUM(DATE(created_at) = UTC_DATE()) AS today
     FROM answers WHERE source = "ai"'
) ?? [];
$fallbackUsage = (int)db_value('SELECT COUNT(*) FROM answers WHERE source = "knowledge_base"', [], 0);

render_admin_start($admin, 'AI Ayarları');
?>
<div class="alert alert--info">
  <span class="alert__icon" aria-hidden="true">i</span>
  <span>AlmancaPro <strong>AI olmadan da tam çalışır</strong>. AI kapalıyken "Öğretmene Sor" dilbilgisi veritabanı,
  ders içerikleri ve bilgi bankasından cevap üretir. API anahtarı hiçbir zaman tarayıcıya gönderilmez.</span>
</div>

<div class="stat-grid">
  <div class="card stat"><div class="stat__label">Durum</div><div class="stat__value"><?= $enabled ? 'Açık' : 'Kapalı' ?></div></div>
  <div class="card stat"><div class="stat__label">API anahtarı</div><div class="stat__value"><?= $hasKey ? 'Tanımlı' : 'Yok' ?></div></div>
  <div class="card stat"><div class="stat__label">AI cevabı (toplam)</div><div class="stat__value"><?= (int)($usage['total'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Bugün</div><div class="stat__value"><?= (int)($usage['today'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Bilgi bankası cevabı</div><div class="stat__value"><?= $fallbackUsage ?></div></div>
</div>

<?php if ($testResult !== null): ?>
  <div class="alert <?= !empty($testResult['ok']) ? 'alert--ok' : 'alert--error' ?>">
    <span class="alert__icon" aria-hidden="true"><?= !empty($testResult['ok']) ? '✓' : '✕' ?></span>
    <span><?= !empty($testResult['ok']) ? 'AI bağlantısı çalışıyor.' : 'AI bağlantısı başarısız: ' . e((string)($testResult['error'] ?? 'bilinmeyen hata')) ?></span>
  </div>
  <?php if (!empty($testResult['answer'])): ?>
    <section class="card"><h2 class="card__title">Test cevabı</h2><?= e_paragraphs((string)$testResult['answer']) ?></section>
  <?php endif; ?>
<?php endif; ?>

<section class="card">
  <h2 class="card__title">Bağlantı ayarları</h2>
  <form method="post" action="/admin-ai.php" class="stack" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <label class="check">
      <input type="checkbox" name="ai_enabled" value="1"<?= $enabled ? ' checked' : '' ?>>
      <span>AI destekli cevapları etkinleştir</span>
    </label>
    <?php if (isset($errors['ai_enabled'])): ?><span class="field__error">✕ <?= e($errors['ai_enabled']) ?></span><?php endif; ?>

    <div class="grid grid--2">
      <label class="field">
        <span class="field__label" for="ai-endpoint">API adresi (chat completions uyumlu)</span>
        <input class="input<?= isset($errors['ai_endpoint']) ? ' is-invalid' : '' ?>" id="ai-endpoint" name="ai_endpoint"
          value="<?= e((string)setting('ai_endpoint', '')) ?>" placeholder="https://api.example.com/v1/chat/completions" autocomplete="off">
        <?php if (isset($errors['ai_endpoint'])): ?><span class="field__error">✕ <?= e($errors['ai_endpoint']) ?></span><?php endif; ?>
      </label>
      <label class="field">
        <span class="field__label" for="ai-model">Model</span>
        <input class="input" id="ai-model" name="ai_model" value="<?= e((string)setting('ai_model', '')) ?>" autocomplete="off">
      </label>
    </div>

    <div class="grid grid--3">
      <label class="field">
        <span class="field__label" for="ai-key">API anahtarı (yeni değer)</span>
        <input class="input" id="ai-key" type="password" name="ai_api_key" value="" autocomplete="new-password"
          placeholder="<?= $hasKey ? e(mask_secret((string)setting('ai_api_key', ''))) : 'Tanımlı değil' ?>">
        <span class="field__hint">Boş bırakırsanız mevcut anahtar korunur.</span>
      </label>
      <label class="field">
        <span class="field__label" for="ai-tokens">Maksimum token</span>
        <input class="input" id="ai-tokens" type="number" name="ai_max_tokens" min="200" max="4000"
          value="<?= e((string)setting_int('ai_max_tokens', 900)) ?>">
      </label>
      <label class="field">
        <span class="field__label" for="ai-limit">Kullanıcı başına günlük soru limiti</span>
        <input class="input" id="ai-limit" type="number" name="ai_daily_limit" min="0" max="500"
          value="<?= e((string)setting_int('ai_daily_limit', 40)) ?>">
        <span class="field__hint">0 = sınırsız.</span>
      </label>
    </div>

    <?php if ($hasKey): ?>
      <label class="check"><input type="checkbox" name="clear_key" value="1"> <span>Mevcut API anahtarını sil (AI'ı kapatır)</span></label>
    <?php endif; ?>

    <label class="field">
      <span class="field__label" for="ai-prompt">Sistem talimatı</span>
      <textarea class="input<?= isset($errors['ai_system_prompt']) ? ' is-invalid' : '' ?>" id="ai-prompt"
        name="ai_system_prompt" rows="14"><?= e((string)setting('ai_system_prompt', '')) ?></textarea>
      <span class="field__hint">Türkçe açıklama, Almanca örnek, artikel + çoğul, fiil üç hâli, durum/edat ilişkisi ve
        seviyeye uygunluk kuralları burada tanımlanır.</span>
      <?php if (isset($errors['ai_system_prompt'])): ?><span class="field__error">✕ <?= e($errors['ai_system_prompt']) ?></span><?php endif; ?>
    </label>

    <div class="row">
      <button class="btn btn--inline" type="submit">Kaydet</button>
    </div>
  </form>

  <div class="row mt-16">
    <form method="post" action="/admin-ai.php">
      <?= csrf_field() ?><input type="hidden" name="action" value="test">
      <button class="btn btn--sm btn--secondary btn--inline" type="submit">Bağlantıyı test et</button>
    </form>
    <form method="post" action="/admin-ai.php" data-confirm="Sistem talimatı varsayılana dönsün mü?">
      <?= csrf_field() ?><input type="hidden" name="action" value="reset_prompt">
      <button class="btn btn--sm btn--secondary btn--inline" type="submit">Talimatı varsayılana döndür</button>
    </form>
  </div>

  <table class="table mt-16">
    <tbody>
      <tr><td>Son başarılı çağrı</td><td class="small"><?= e((string)setting('ai_last_ok', '') ?: 'Kayıt yok') ?></td></tr>
      <tr><td>Son hata</td><td class="small"><?= e((string)setting('ai_last_error', '') ?: 'Kayıt yok') ?></td></tr>
      <tr><td>cURL eklentisi</td><td><?= function_exists('curl_init') ? '✓ Mevcut' : '✕ Yok' ?></td></tr>
    </tbody>
  </table>
</section>
<?php
render_admin_end();
