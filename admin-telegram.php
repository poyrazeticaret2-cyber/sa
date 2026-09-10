<?php
/**
 * AlmancaPro - Telegram bot yonetim paneli.
 * Bot token asla tam metin gosterilmez; yalnizca maskeli ozet ve degistirme alani vardir.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/telegram-api.php';
app_boot();

$admin = require_admin();
$errors = [];
$webhookInfo = null;
$botInfo = null;

if (is_post()) {
    csrf_require();
    $action = (string)input('action', '');

    if ($action === 'save') {
        $username = ltrim(trim((string)input('telegram_bot_username', '')), '@');
        if ($username !== '' && !preg_match('/^[A-Za-z0-9_]{5,32}$/', $username)) {
            $errors['telegram_bot_username'] = 'Bot kullanıcı adı 5-32 karakter, yalnızca harf, rakam ve alt çizgi olabilir.';
        }
        $newToken = (string)input('telegram_bot_token', '');
        if ($newToken !== '' && !preg_match('/^\d{5,}:[A-Za-z0-9_\-]{20,}$/', $newToken)) {
            $errors['telegram_bot_token'] = 'Token biçimi geçersiz. BotFather formatı: 123456789:AA...';
        }
        if ($errors === []) {
            setting_set('telegram_bot_username', $username);
            if ($newToken !== '') {
                setting_set('telegram_bot_token', $newToken, true);
                admin_log((int)$admin['id'], 'TELEGRAM_TOKEN_UPDATED', 'settings', 'telegram_bot_token');
            }
            if (!empty($_POST['clear_token'])) {
                setting_set('telegram_bot_token', '', true);
                admin_log((int)$admin['id'], 'TELEGRAM_TOKEN_CLEARED', 'settings', 'telegram_bot_token');
            }
            admin_log((int)$admin['id'], 'TELEGRAM_SETTINGS_UPDATED', 'settings', 'telegram');
            flash('success', 'Telegram ayarları kaydedildi.');
            redirect('/admin-telegram.php');
        }
    }

    if ($action === 'set_webhook') {
        $url = trim((string)input('webhook_url', ''));
        if ($url === '') {
            $url = site_url('telegram-webhook.php');
        }
        if (!str_starts_with($url, 'https://')) {
            flash('error', 'Telegram yalnızca HTTPS webhook adresi kabul eder.');
            redirect('/admin-telegram.php');
        }
        $res = telegram_set_webhook($url);
        admin_log((int)$admin['id'], 'TELEGRAM_WEBHOOK_SET', 'telegram', null, ['ok' => $res['ok']]);
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Webhook ayarlandı.' : ('Webhook ayarlanamadı: ' . $res['error']));
        redirect('/admin-telegram.php');
    }

    if ($action === 'delete_webhook') {
        $res = telegram_delete_webhook();
        admin_log((int)$admin['id'], 'TELEGRAM_WEBHOOK_DELETED', 'telegram');
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Webhook kaldırıldı.' : ('İşlem başarısız: ' . $res['error']));
        redirect('/admin-telegram.php');
    }

    if ($action === 'test') {
        $res = telegram_get_me();
        flash(
            $res['ok'] ? 'success' : 'error',
            $res['ok']
                ? ('Bot bağlantısı çalışıyor: @' . (string)($res['result']['username'] ?? '?'))
                : ('Bot bağlantısı başarısız: ' . $res['error'])
        );
        redirect('/admin-telegram.php');
    }

    if ($action === 'retry_failed') {
        $n = db_exec(
            'UPDATE notification_queue SET status = "pending", attempts = 0, last_error = NULL
             WHERE channel = "telegram" AND status = "failed"'
        );
        admin_log((int)$admin['id'], 'TELEGRAM_QUEUE_RETRY', 'notification_queue', null, ['count' => $n]);
        flash('success', $n . ' başarısız mesaj yeniden kuyruğa alındı.');
        redirect('/admin-telegram.php');
    }
}

$hasToken = (string)setting('telegram_bot_token', '') !== '';
$botUsername = telegram_bot_username();

if ($hasToken && input('probe') === '1') {
    $botInfo = telegram_get_me();
    $webhookInfo = telegram_webhook_info();
}

$connCount = (int)db_value('SELECT COUNT(*) FROM telegram_connections WHERE is_active = 1', [], 0);
$quizOn = (int)db_value('SELECT COUNT(*) FROM telegram_connections WHERE is_active = 1 AND quiz_enabled = 1', [], 0);
$lastUpdate = db_row('SELECT * FROM telegram_updates ORDER BY id DESC LIMIT 1');
$updateCount = (int)db_value('SELECT COUNT(*) FROM telegram_updates', [], 0);
$queue = db_row(
    'SELECT SUM(status="pending") AS pending, SUM(status="sent") AS sent, SUM(status="failed") AS failed
     FROM notification_queue WHERE channel = "telegram"'
) ?? [];
$failedMessages = db_all(
    'SELECT q.*, u.name AS user_name FROM notification_queue q LEFT JOIN users u ON u.id = q.user_id
     WHERE q.channel = "telegram" AND q.status = "failed" ORDER BY q.id DESC LIMIT 15'
);
$recentConnections = db_all(
    'SELECT c.*, u.name AS user_name, u.email FROM telegram_connections c
     JOIN users u ON u.id = c.user_id ORDER BY c.linked_at DESC LIMIT 15'
);
$recentUpdates = db_all('SELECT id, update_id, chat_id, kind, processed_at, error, created_at FROM telegram_updates ORDER BY id DESC LIMIT 15');

$statusRows = [
    ['Bot token', $hasToken, $hasToken ? 'Tanımlı (' . mask_secret((string)setting('telegram_bot_token', '')) . ')' : 'Tanımlı değil'],
    ['Bot kullanıcı adı', $botUsername !== '', $botUsername !== '' ? '@' . $botUsername : 'Tanımlı değil'],
    ['Webhook', setting_bool('telegram_webhook_set'), setting_bool('telegram_webhook_set') ? (string)setting('telegram_webhook_url', '—') : 'Ayarlanmadı'],
    ['cURL eklentisi', function_exists('curl_init'), function_exists('curl_init') ? 'Mevcut' : 'Yok'],
    ['Site URL (HTTPS)', str_starts_with(site_url(), 'https://'), site_url()],
];

render_admin_start($admin, 'Telegram');
?>
<div class="stat-grid">
  <div class="card stat"><div class="stat__label">Bağlı kullanıcı</div><div class="stat__value"><?= $connCount ?></div></div>
  <div class="card stat"><div class="stat__label">Quiz açık</div><div class="stat__value"><?= $quizOn ?></div></div>
  <div class="card stat"><div class="stat__label">Kuyrukta bekleyen</div><div class="stat__value"><?= (int)($queue['pending'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Gönderilen</div><div class="stat__value"><?= (int)($queue['sent'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Başarısız</div><div class="stat__value"><?= (int)($queue['failed'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Gelen update</div><div class="stat__value"><?= $updateCount ?></div></div>
</div>

<section class="card">
  <h2 class="card__title">Durum</h2>
  <table class="table">
    <tbody>
    <?php foreach ($statusRows as [$label, $ok, $value]): ?>
      <tr>
        <td><?= e($label) ?></td>
        <td><?php render_badge($ok ? '✓' : '✕', $ok ? 'Hazır' : 'Eksik', $ok ? 'success' : 'danger'); ?></td>
        <td class="small"><?= e((string)$value) ?></td>
      </tr>
    <?php endforeach; ?>
      <tr>
        <td>Son başarılı gönderim</td>
        <td colspan="2" class="small"><?= e((string)setting('telegram_last_ok', '') ?: 'Kayıt yok') ?></td>
      </tr>
      <tr>
        <td>Son hata</td>
        <td colspan="2" class="small"><?= e((string)setting('telegram_last_error', '') ?: 'Kayıt yok') ?></td>
      </tr>
      <tr>
        <td>Son gelen update</td>
        <td colspan="2" class="small">
          <?= $lastUpdate !== null ? e(local_datetime((string)$lastUpdate['created_at']) . ' · ' . (string)($lastUpdate['kind'] ?? '—')) : 'Kayıt yok' ?>
        </td>
      </tr>
    </tbody>
  </table>

  <div class="row mt-16">
    <a class="btn btn--sm btn--secondary btn--inline" href="/admin-telegram.php?probe=1">Telegram'a bağlanıp doğrula</a>
    <form method="post" action="/admin-telegram.php">
      <?= csrf_field() ?><input type="hidden" name="action" value="test">
      <button class="btn btn--sm btn--secondary btn--inline" type="submit">getMe testi</button>
    </form>
  </div>

  <?php if ($botInfo !== null): ?>
    <div class="alert <?= $botInfo['ok'] ? 'alert--ok' : 'alert--error' ?>">
      <span class="alert__icon" aria-hidden="true"><?= $botInfo['ok'] ? '✓' : '✕' ?></span>
      <span><?= $botInfo['ok']
          ? 'Bot: @' . e((string)($botInfo['result']['username'] ?? '?')) . ' · ' . e((string)($botInfo['result']['first_name'] ?? ''))
          : e($botInfo['error']) ?></span>
    </div>
  <?php endif; ?>
  <?php if ($webhookInfo !== null && $webhookInfo['ok'] && is_array($webhookInfo['result'])): $w = $webhookInfo['result']; ?>
    <table class="table">
      <tbody>
        <tr><td>Webhook URL</td><td class="small"><?= e((string)($w['url'] ?? '—')) ?></td></tr>
        <tr><td>Bekleyen update</td><td class="num"><?= (int)($w['pending_update_count'] ?? 0) ?></td></tr>
        <tr><td>Son hata</td><td class="small"><?= e((string)($w['last_error_message'] ?? 'Yok')) ?></td></tr>
        <tr><td>Gizli başlık</td><td><?= !empty($w['has_custom_certificate']) ? 'Özel sertifika' : 'Standart' ?></td></tr>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="card">
  <h2 class="card__title">Bot ayarları</h2>
  <p class="small">BotFather'dan aldığınız token yalnızca sunucuda saklanır; panelde tam metin olarak gösterilmez.
    Değiştirmek için yeni token yazmanız yeterlidir, mevcut değeri okumanız gerekmez.</p>
  <form method="post" action="/admin-telegram.php" class="stack" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <div class="grid grid--2">
      <label class="field">
        <span class="field__label" for="tg-user">Bot kullanıcı adı (@ olmadan)</span>
        <input class="input<?= isset($errors['telegram_bot_username']) ? ' is-invalid' : '' ?>" id="tg-user"
          name="telegram_bot_username" value="<?= e($botUsername) ?>" placeholder="AlmancaProBot" autocomplete="off">
        <?php if (isset($errors['telegram_bot_username'])): ?><span class="field__error">✕ <?= e($errors['telegram_bot_username']) ?></span><?php endif; ?>
      </label>
      <label class="field">
        <span class="field__label" for="tg-token">Bot token (yeni değer)</span>
        <input class="input<?= isset($errors['telegram_bot_token']) ? ' is-invalid' : '' ?>" id="tg-token" type="password"
          name="telegram_bot_token" value="" autocomplete="new-password"
          placeholder="<?= $hasToken ? e(mask_secret((string)setting('telegram_bot_token', ''))) : 'Tanımlı değil' ?>">
        <span class="field__hint">Boş bırakırsanız mevcut token korunur.</span>
        <?php if (isset($errors['telegram_bot_token'])): ?><span class="field__error">✕ <?= e($errors['telegram_bot_token']) ?></span><?php endif; ?>
      </label>
    </div>
    <?php if ($hasToken): ?>
      <label class="check"><input type="checkbox" name="clear_token" value="1"> <span>Mevcut token'ı sil (botu devre dışı bırakır)</span></label>
    <?php endif; ?>
    <div class="row"><button class="btn btn--inline" type="submit">Kaydet</button></div>
  </form>
</section>

<section class="card">
  <h2 class="card__title">Webhook</h2>
  <ol class="steps small">
    <li>Telegram'da <strong>@BotFather</strong> ile <code>/newbot</code> komutunu çalıştırın.</li>
    <li>Bot adını ve kullanıcı adını belirleyin; BotFather size bir token verir.</li>
    <li>Token ve kullanıcı adını yukarıdaki forma girip kaydedin.</li>
    <li>Aşağıdan <strong>Webhook'u Ayarla</strong> butonuna basın (site HTTPS olmalıdır).</li>
    <li>Kullanıcılar <em>Telegram</em> sayfasından "Telegram'ı Bağla" ile tek kullanımlık bağlantı alır.</li>
  </ol>
  <form method="post" action="/admin-telegram.php" class="stack" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="set_webhook">
    <label class="field">
      <span class="field__label" for="wh-url">Webhook adresi</span>
      <input class="input" id="wh-url" name="webhook_url" value="<?= e((string)setting('telegram_webhook_url', site_url('telegram-webhook.php'))) ?>">
    </label>
    <div class="row">
      <button class="btn btn--inline" type="submit"<?= $hasToken ? '' : ' disabled' ?>>Webhook'u Ayarla</button>
    </div>
  </form>
  <form method="post" action="/admin-telegram.php" class="mt-8" data-confirm="Webhook kaldırılsın mı? Bot mesajları almayı durdurur.">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete_webhook">
    <button class="btn btn--sm btn--secondary btn--inline" type="submit"<?= $hasToken ? '' : ' disabled' ?>>Webhook'u Kaldır</button>
  </form>
</section>

<section class="card">
  <h2 class="card__title">Bağlı kullanıcılar</h2>
  <?php if ($recentConnections === []): ?>
    <?php render_empty('Bağlı kullanıcı yok', 'Kullanıcılar Telegram sayfasından hesaplarını bağladığında burada listelenir.'); ?>
  <?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Kullanıcı</th><th>Telegram</th><th>Quiz</th><th>Bağlanma</th><th>Son etkileşim</th></tr></thead>
    <tbody>
    <?php foreach ($recentConnections as $c): ?>
      <tr>
        <td><a href="/admin-user.php?id=<?= (int)$c['user_id'] ?>"><?= e((string)$c['user_name']) ?></a></td>
        <td class="small"><?= e($c['username'] !== null ? '@' . (string)$c['username'] : (string)$c['first_name']) ?></td>
        <td><?= (int)$c['quiz_enabled'] === 1 ? '✓ Açık' : '○ Kapalı' ?></td>
        <td class="num"><?= e(local_datetime((string)$c['linked_at'])) ?></td>
        <td class="num"><?= e($c['last_interaction_at'] !== null ? local_datetime((string)$c['last_interaction_at']) : '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>

<div class="grid grid--2">
  <section class="card">
    <h2 class="card__title">Son gelen update'ler</h2>
    <?php if ($recentUpdates === []): ?>
      <?php render_empty('Update yok', 'Webhook çalıştığında gelen mesajlar burada görünür.'); ?>
    <?php else: ?>
    <table class="table">
      <thead><tr><th>update_id</th><th>Tür</th><th>İşlendi</th><th>Hata</th></tr></thead>
      <tbody>
      <?php foreach ($recentUpdates as $u): ?>
        <tr>
          <td class="num"><?= (int)$u['update_id'] ?></td>
          <td><?= e((string)($u['kind'] ?? '—')) ?></td>
          <td class="small"><?= $u['processed_at'] !== null ? '✓ ' . e(local_datetime((string)$u['processed_at'])) : '○ Bekliyor' ?></td>
          <td class="small"><?= e(str_limit((string)($u['error'] ?? ''), 40)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2 class="card__title">Başarısız mesajlar</h2>
    <?php if ($failedMessages === []): ?>
      <?php render_empty('Başarısız mesaj yok', 'Telegram kuyruğunda hata alan mesaj bulunmuyor.'); ?>
    <?php else: ?>
    <table class="table">
      <thead><tr><th>Kullanıcı</th><th>Şablon</th><th>Hata</th><th>Deneme</th></tr></thead>
      <tbody>
      <?php foreach ($failedMessages as $m): ?>
        <tr>
          <td><?= e((string)($m['user_name'] ?? '—')) ?></td>
          <td class="small"><?= e((string)$m['template']) ?></td>
          <td class="small"><?= e(str_limit((string)($m['last_error'] ?? ''), 50)) ?></td>
          <td class="num"><?= (int)$m['attempts'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <form method="post" action="/admin-telegram.php" class="mt-8">
      <?= csrf_field() ?><input type="hidden" name="action" value="retry_failed">
      <button class="btn btn--sm btn--secondary btn--inline" type="submit">Başarısızları yeniden kuyruğa al</button>
    </form>
    <?php endif; ?>
  </section>
</div>
<?php
render_admin_end();
