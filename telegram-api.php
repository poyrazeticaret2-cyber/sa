<?php
/**
 * AlmancaPro - Telegram Bot API istemcisi.
 *
 * Bot token asla tarayiciya gonderilmez ve loglanmaz.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function telegram_is_configured(): bool
{
    return setting('telegram_bot_token') !== null && setting('telegram_bot_username') !== null;
}

function telegram_bot_username(): string
{
    return ltrim((string)setting('telegram_bot_username', ''), '@');
}

/**
 * Telegram Bot API cagrisi.
 * @return array{ok:bool, result:mixed, error:string}
 */
function telegram_call(string $method, array $params = []): array
{
    $token = (string)setting('telegram_bot_token', '');
    if ($token === '') {
        return ['ok' => false, 'result' => null, 'error' => 'not_configured'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'result' => null, 'error' => 'curl_missing'];
    }

    $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        $msg = 'Bağlantı hatası (' . substr($curlErr, 0, 80) . ')';
        setting_set('telegram_last_error', $msg . ' · ' . now_utc());
        return ['ok' => false, 'result' => null, 'error' => $msg];
    }

    $data = json_decode((string)$raw, true);
    if (!is_array($data) || empty($data['ok'])) {
        $msg = is_array($data) ? (string)($data['description'] ?? 'Bilinmeyen hata') : ('HTTP ' . $status);
        setting_set('telegram_last_error', scrub_secrets($msg) . ' · ' . now_utc());
        return ['ok' => false, 'result' => null, 'error' => scrub_secrets($msg)];
    }

    setting_set('telegram_last_ok', now_utc());
    return ['ok' => true, 'result' => $data['result'] ?? null, 'error' => ''];
}

/** Metin mesaji gonderir. */
function telegram_send_message(int|string $chatId, string $text, ?array $keyboard = null, bool $html = true): array
{
    $params = [
        'chat_id' => $chatId,
        'text' => mb_substr($text, 0, 4000),
        'disable_web_page_preview' => true,
    ];
    if ($html) {
        $params['parse_mode'] = 'HTML';
    }
    if ($keyboard !== null) {
        $params['reply_markup'] = ['inline_keyboard' => $keyboard];
    }
    return telegram_call('sendMessage', $params);
}

/** Callback sorgusuna cevap verir (butondaki yukleniyor gostergesini kapatir). */
function telegram_answer_callback(string $callbackId, string $text = ''): array
{
    return telegram_call('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text' => mb_substr($text, 0, 190),
        'show_alert' => false,
    ]);
}

/** Gonderilmis mesajin butonlarini kaldirir/gunceller. */
function telegram_edit_message(int|string $chatId, int $messageId, string $text, ?array $keyboard = null): array
{
    $params = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => mb_substr($text, 0, 4000),
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];
    if ($keyboard !== null) {
        $params['reply_markup'] = ['inline_keyboard' => $keyboard];
    }
    return telegram_call('editMessageText', $params);
}

/** Webhook adresini ayarlar. */
function telegram_set_webhook(?string $url = null): array
{
    $url ??= site_url('telegram-webhook.php');
    $secret = (string)setting('cron_secret', '');
    $res = telegram_call('setWebhook', [
        'url' => $url,
        'allowed_updates' => ['message', 'callback_query'],
        'secret_token' => substr(hash('sha256', $secret), 0, 32),
        'drop_pending_updates' => false,
    ]);
    setting_set('telegram_webhook_set', $res['ok'] ? '1' : '0');
    if ($res['ok']) {
        setting_set('telegram_webhook_url', $url);
    }
    return $res;
}

function telegram_delete_webhook(): array
{
    $res = telegram_call('deleteWebhook', ['drop_pending_updates' => false]);
    setting_set('telegram_webhook_set', '0');
    return $res;
}

function telegram_webhook_info(): array
{
    return telegram_call('getWebhookInfo');
}

function telegram_get_me(): array
{
    return telegram_call('getMe');
}

/** Webhook secret dogrulamasi. */
function telegram_verify_secret(?string $given): bool
{
    $secret = (string)setting('cron_secret', '');
    if ($secret === '') {
        return true; /* Henuz kurulmadiysa engelleme yapma; token dogrulamasi yine calisir. */
    }
    $expected = substr(hash('sha256', $secret), 0, 32);
    return is_string($given) && hash_equals($expected, $given);
}

/* ==================================================================
 * Baglanti tokenlari
 * ================================================================== */

/** Kullanici icin tek kullanimlik baglanti tokeni uretir ve deep link doner. */
function telegram_create_link(int $userId): array
{
    db_exec('UPDATE telegram_link_tokens SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND used_at IS NULL', [$userId]);
    $token = strtoupper(bin2hex(random_bytes(8)));
    db_exec(
        'INSERT INTO telegram_link_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))',
        [$userId, hash_token($token)]
    );
    $username = telegram_bot_username();
    return [
        'token' => $token,
        'deep_link' => $username !== '' ? 'https://t.me/' . $username . '?start=' . $token : '',
    ];
}

/** Token dogrular ve kullaniciyi doner. */
function telegram_consume_link(string $token): ?array
{
    $token = strtoupper(trim($token));
    if (!preg_match('/^[A-F0-9]{16}$/', $token)) {
        return null;
    }
    $row = db_row(
        'SELECT * FROM telegram_link_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP() LIMIT 1',
        [hash_token($token)]
    );
    if ($row === null) {
        return null;
    }
    db_exec('UPDATE telegram_link_tokens SET used_at = UTC_TIMESTAMP() WHERE id = ?', [(int)$row['id']]);
    return db_row('SELECT * FROM users WHERE id = ? AND is_active = 1', [(int)$row['user_id']]);
}

/** Kullanicinin aktif Telegram baglantisi. */
function telegram_connection(int $userId): ?array
{
    return db_row('SELECT * FROM telegram_connections WHERE user_id = ? AND is_active = 1', [$userId]);
}
