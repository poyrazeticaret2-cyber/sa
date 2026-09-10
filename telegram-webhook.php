<?php
/**
 * AlmancaPro - Telegram webhook.
 *
 * Gelen guncellemeleri isler: hesap baglama (/start TOKEN), komutlar ve
 * inline quiz cevaplari. Telegram'daki cevaplar web ile AYNI ogrenme
 * verisine yazilir.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/planner.php';
require_once __DIR__ . '/telegram-api.php';

/* Webhook her zaman 200 doner; aksi halde Telegram tekrar tekrar dener. */
function webhook_finish(string $note = ''): never
{
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => true, 'note' => $note]);
    exit;
}

try {
    ensure_installed();
} catch (Throwable $e) {
    webhook_finish('not_installed');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    webhook_finish('ready');
}

/* Gizli anahtar dogrulamasi */
if (!telegram_verify_secret($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null)) {
    app_log('Telegram webhook: geçersiz secret token');
    webhook_finish('bad_secret');
}

$raw = file_get_contents('php://input') ?: '';
if ($raw === '' || strlen($raw) > 200000) {
    webhook_finish('empty');
}
$update = json_decode($raw, true);
if (!is_array($update) || !isset($update['update_id'])) {
    webhook_finish('bad_payload');
}

$updateId = (int)$update['update_id'];
$kind = isset($update['callback_query']) ? 'callback_query' : (isset($update['message']) ? 'message' : 'other');
$chatId = (int)($update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? 0);

/* Tekrar gelen guncellemeleri isleme (idempotent) */
try {
    db_exec(
        'INSERT INTO telegram_updates (update_id, chat_id, kind, payload) VALUES (?, ?, ?, ?)',
        [$updateId, $chatId ?: null, $kind, mb_substr($raw, 0, 60000)]
    );
} catch (Throwable $e) {
    webhook_finish('duplicate');
}

setting_set('telegram_last_update', now_utc());

try {
    if ($kind === 'message') {
        handle_message($update['message']);
    } elseif ($kind === 'callback_query') {
        handle_callback($update['callback_query']);
    }
    db_exec('UPDATE telegram_updates SET processed_at = UTC_TIMESTAMP() WHERE update_id = ?', [$updateId]);
} catch (Throwable $e) {
    app_log('Telegram webhook hatası: ' . $e->getMessage());
    db_exec('UPDATE telegram_updates SET processed_at = UTC_TIMESTAMP(), error = ? WHERE update_id = ?',
        [mb_substr(scrub_secrets($e->getMessage()), 0, 500), $updateId]);
}

webhook_finish('processed');

/* ==================================================================
 * Mesaj isleyici
 * ================================================================== */
function handle_message(array $message): void
{
    $chatId = (int)($message['chat']['id'] ?? 0);
    $text = trim((string)($message['text'] ?? ''));
    $from = $message['from'] ?? [];
    if ($chatId === 0) {
        return;
    }

    /* /start TOKEN -> hesap baglama */
    if (preg_match('/^\/start(?:@\w+)?\s+([A-Za-z0-9]+)$/', $text, $m)) {
        link_account($chatId, $from, $m[1]);
        return;
    }
    if (preg_match('/^\/start(?:@\w+)?$/', $text)) {
        $conn = connection_by_chat($chatId);
        if ($conn !== null) {
            telegram_send_message($chatId, "AlmancaPro hesabın zaten bağlı.\n\nKomutlar için /yardim yazabilirsin.");
        } else {
            telegram_send_message(
                $chatId,
                "AlmancaPro'ya hoş geldin.\n\nHesabını bağlamak için siteye gir, <b>Telegram</b> sayfasını aç ve "
                . "<b>Telegram'ı Bağla</b> düğmesine bas. Sana özel bağlantı seni buraya geri getirecek.\n\n"
                . site_url('telegram.php')
            );
        }
        return;
    }

    $conn = connection_by_chat($chatId);
    if ($conn === null) {
        telegram_send_message(
            $chatId,
            "Bu Telegram hesabı bir AlmancaPro hesabına bağlı değil.\n\nBağlamak için: " . site_url('telegram.php')
        );
        return;
    }

    db_exec('UPDATE telegram_connections SET last_interaction_at = UTC_TIMESTAMP() WHERE id = ?', [(int)$conn['id']]);
    $user = db_row('SELECT * FROM users WHERE id = ?', [(int)$conn['user_id']]);
    if ($user === null) {
        return;
    }

    $command = strtolower(preg_replace('/@\w+$/', '', explode(' ', $text)[0] ?? ''));

    switch ($command) {
        case '/ders':      cmd_lesson($chatId, $user); break;
        case '/quiz':      cmd_quiz($chatId, $user, (int)$conn['id']); break;
        case '/tekrar':    cmd_review($chatId, $user); break;
        case '/durum':     cmd_status($chatId, $user); break;
        case '/seri':      cmd_streak($chatId, $user); break;
        case '/hatirlat':  cmd_reminders($chatId, $user, true); break;
        case '/durdur':    cmd_reminders($chatId, $user, false); break;
        case '/ayarlar':   cmd_settings($chatId, $user); break;
        case '/yardim':
        case '/help':      cmd_help($chatId); break;
        default:
            telegram_send_message($chatId, "Bu komutu tanımıyorum.\n\nKomut listesi için /yardim yaz.");
    }
}

function connection_by_chat(int $chatId): ?array
{
    return db_row('SELECT * FROM telegram_connections WHERE chat_id = ? AND is_active = 1 LIMIT 1', [$chatId]);
}

function link_account(int $chatId, array $from, string $token): void
{
    $user = telegram_consume_link($token);
    if ($user === null) {
        telegram_send_message(
            $chatId,
            "Bu bağlantı geçersiz veya süresi dolmuş.\n\nSiteden yeni bir bağlantı oluşturabilirsin:\n" . site_url('telegram.php')
        );
        return;
    }

    $telegramUserId = (int)($from['id'] ?? 0);
    $username = isset($from['username']) ? mb_substr((string)$from['username'], 0, 64) : null;
    $firstName = isset($from['first_name']) ? mb_substr((string)$from['first_name'], 0, 120) : null;
    $lang = isset($from['language_code']) ? mb_substr((string)$from['language_code'], 0, 8) : null;

    /* Ayni Telegram hesabi baska bir kullaniciya bagliysa eski baglanti kaldirilir. */
    db_exec('DELETE FROM telegram_connections WHERE telegram_user_id = ? OR user_id = ?', [$telegramUserId, (int)$user['id']]);
    db_exec(
        'INSERT INTO telegram_connections (user_id, telegram_user_id, chat_id, username, first_name, language_code, last_interaction_at)
         VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
        [(int)$user['id'], $telegramUserId, $chatId, $username, $firstName, $lang]
    );

    telegram_send_message(
        $chatId,
        "✅ <b>AlmancaPro hesabın başarıyla bağlandı.</b>\n\n"
        . "Merhaba " . htmlspecialchars((string)$user['name'], ENT_QUOTES) . "!\n\n"
        . "Buradan günlük hatırlatma, tekrar bildirimi ve mini quiz alacaksın. "
        . "Telegram'da verdiğin cevaplar sitedeki ilerlemene işlenir.\n\n"
        . "Komutlar için /yardim yaz."
    );
    log_notification((int)$user['id'], 'telegram', 'link_success', true);
}

/* ==================================================================
 * Komutlar
 * ================================================================== */
function cmd_help(int $chatId): void
{
    telegram_send_message($chatId,
        "<b>AlmancaPro komutları</b>\n\n"
        . "/ders — sıradaki dersin\n"
        . "/quiz — hızlı bir soru\n"
        . "/tekrar — tekrar zamanı gelenler\n"
        . "/durum — bugünkü hedefin\n"
        . "/seri — güncel serin\n"
        . "/hatirlat — hatırlatmaları aç\n"
        . "/durdur — hatırlatmaları kapat\n"
        . "/ayarlar — bildirim ayarların\n"
        . "/yardim — bu liste");
}

function cmd_lesson(int $chatId, array $user): void
{
    $lesson = next_available_lesson((int)$user['id'], $user);
    if ($lesson === null) {
        telegram_send_message($chatId, "Şu anda açık yeni ders yok. Tekrarlarını tamamlayarak devam edebilirsin.\n" . site_url('review.php'));
        return;
    }
    telegram_send_message($chatId,
        "<b>Sıradaki dersin</b>\n\n"
        . htmlspecialchars((string)$lesson['title'], ENT_QUOTES) . " (" . htmlspecialchars((string)$lesson['cefr_level'], ENT_QUOTES) . ")\n"
        . "Tahmini süre: " . (int)$lesson['estimated_minutes'] . " dakika\n\n"
        . site_url('lesson.php?id=' . (int)$lesson['id']));
}

function cmd_review(int $chatId, array $user): void
{
    $counts = due_review_counts((int)$user['id']);
    if ($counts['due'] === 0) {
        telegram_send_message($chatId, "✅ Şu anda tekrar zamanı gelen kart yok. Yeni konu öğrenmeye devam edebilirsin.\n" . site_url('course.php'));
        return;
    }
    telegram_send_message($chatId,
        "<b>Tekrar zamanı</b>\n\n"
        . $counts['due'] . " kart hazır (" . $counts['overdue'] . " tanesi gecikti).\n"
        . "Tahmini süre: " . $counts['est_minutes'] . " dakika\n\n"
        . site_url('review.php?start=1'));
}

function cmd_status(int $chatId, array $user): void
{
    $userId = (int)$user['id'];
    $plan = daily_plan($userId, $user);
    $percent = daily_plan_percent($plan);
    $counts = due_review_counts($userId);

    $lines = ["<b>Bugünkü durumun</b>\n"];
    $lines[] = "Günlük hedef: %" . $percent;
    foreach ($plan['items'] as $item) {
        $icon = (string)$item['status'] === 'done' ? '✓' : ((string)$item['status'] === 'in_progress' ? '●' : '○');
        $lines[] = $icon . ' ' . htmlspecialchars((string)$item['title'], ENT_QUOTES)
            . ' (' . (int)$item['done_count'] . '/' . (int)$item['target_count'] . ')';
    }
    $lines[] = "\nTekrar bekleyen: " . $counts['due'];
    $lines[] = "Seri: " . (int)$user['streak_count'] . " gün";
    $lines[] = "\n" . site_url('dashboard.php');

    telegram_send_message($chatId, implode("\n", $lines));
}

function cmd_streak(int $chatId, array $user): void
{
    telegram_send_message($chatId,
        "🔥 <b>" . (int)$user['streak_count'] . " günlük seri</b>\n"
        . "En uzun serin: " . (int)$user['longest_streak'] . " gün\n\n"
        . "Asıl hedef: düzenli ve doğru öğrenme. Seri yalnızca gerçek çalışmayla artar.");
}

function cmd_reminders(int $chatId, array $user, bool $enable): void
{
    db_exec('UPDATE notification_preferences SET telegram_enabled = ? WHERE user_id = ?', [$enable ? 1 : 0, (int)$user['id']]);
    telegram_send_message($chatId, $enable
        ? "✅ Telegram hatırlatmaları açıldı."
        : "Telegram hatırlatmaları kapatıldı. Tekrar açmak için /hatirlat yaz.");
}

function cmd_settings(int $chatId, array $user): void
{
    $p = db_row('SELECT * FROM notification_preferences WHERE user_id = ?', [(int)$user['id']]) ?? [];
    telegram_send_message($chatId,
        "<b>Bildirim ayarların</b>\n\n"
        . "Yoğunluk: " . htmlspecialchars((string)($p['mode'] ?? 'normal'), ENT_QUOTES) . "\n"
        . "Telegram: " . ((int)($p['telegram_enabled'] ?? 1) === 1 ? 'açık' : 'kapalı') . "\n"
        . "Sessiz saat: " . substr((string)($p['quiet_start'] ?? '22:00:00'), 0, 5) . " – " . substr((string)($p['quiet_end'] ?? '08:00:00'), 0, 5) . "\n"
        . "Günlük hedef: " . (int)($p['daily_target'] ?? 30) . " dakika\n\n"
        . "Ayrıntılı ayarlar: " . site_url('settings.php'));
}

/** Telegram'da inline quiz sorusu gonderir. */
function cmd_quiz(int $chatId, array $user, int $connectionId): void
{
    $userId = (int)$user['id'];
    $exercise = pick_telegram_exercise($userId, $user);
    if ($exercise === null) {
        telegram_send_message($chatId, "Şu anda uygun soru bulamadım. Önce sitede bir ders tamamla:\n" . site_url('course.php'));
        return;
    }

    $options = $exercise['options'];
    if ($options === []) {
        telegram_send_message($chatId,
            "<b>Soru</b>\n\n" . htmlspecialchars((string)$exercise['prompt'], ENT_QUOTES)
            . "\n\nBu soru yazılı cevap istiyor. Sitede cevaplayabilirsin:\n" . site_url('quiz.php?mode=mixed'));
        return;
    }

    $stateId = db_insert(
        'INSERT INTO telegram_quiz_state (user_id, chat_id, exercise_id, expires_at)
         VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 6 HOUR))',
        [$userId, $chatId, (int)$exercise['id']]
    );

    $keyboard = [];
    foreach ($options as $opt) {
        $keyboard[] = [[
            'text' => mb_substr((string)$opt['option_text'], 0, 60),
            'callback_data' => 'a:' . $stateId . ':' . (int)$opt['id'],
        ]];
    }

    $res = telegram_send_message($chatId, "<b>Soru</b>\n\n" . htmlspecialchars((string)$exercise['prompt'], ENT_QUOTES), $keyboard);
    if ($res['ok'] && isset($res['result']['message_id'])) {
        db_exec('UPDATE telegram_quiz_state SET message_id = ? WHERE id = ?', [(int)$res['result']['message_id'], $stateId]);
    }
    log_notification($userId, 'telegram', 'quiz', $res['ok']);
}

/** Telegram icin uygun bir coktan secmeli soru secer. */
function pick_telegram_exercise(int $userId, array $user): ?array
{
    $items = build_review_items($userId, 6);
    foreach ($items as $item) {
        if (!empty($item['options'])) {
            return $item;
        }
    }
    $items = build_mixed_quiz($userId, $user, 8);
    foreach ($items as $item) {
        if (!empty($item['options'])) {
            return $item;
        }
    }
    return null;
}

/* ==================================================================
 * Callback (inline quiz cevabi)
 * ================================================================== */
function handle_callback(array $callback): void
{
    $callbackId = (string)($callback['id'] ?? '');
    $chatId = (int)($callback['message']['chat']['id'] ?? 0);
    $messageId = (int)($callback['message']['message_id'] ?? 0);
    $data = (string)($callback['data'] ?? '');

    if (!preg_match('/^a:(\d+):(\d+)$/', $data, $m)) {
        telegram_answer_callback($callbackId, 'Bu buton artık geçerli değil.');
        return;
    }
    $stateId = (int)$m[1];
    $optionId = (int)$m[2];

    $state = db_row('SELECT * FROM telegram_quiz_state WHERE id = ? AND chat_id = ?', [$stateId, $chatId]);
    if ($state === null) {
        telegram_answer_callback($callbackId, 'Soru bulunamadı.');
        return;
    }
    if ($state['answered_at'] !== null) {
        telegram_answer_callback($callbackId, 'Bu soruyu zaten cevapladın.');
        return;
    }
    if (strtotime((string)$state['expires_at']) < time()) {
        telegram_answer_callback($callbackId, 'Bu sorunun süresi doldu.');
        return;
    }

    $exercise = db_row('SELECT * FROM exercises WHERE id = ?', [(int)$state['exercise_id']]);
    $option = db_row('SELECT * FROM exercise_options WHERE id = ? AND exercise_id = ?', [$optionId, (int)$state['exercise_id']]);
    if ($exercise === null || $option === null) {
        telegram_answer_callback($callbackId, 'Soru verisi bulunamadı.');
        return;
    }

    db_exec('UPDATE telegram_quiz_state SET answered_at = UTC_TIMESTAMP() WHERE id = ?', [$stateId]);

    $userId = (int)$state['user_id'];
    $exercise = hydrate_exercise($exercise);

    /* Ayni ogrenme motoru: mastery, SRS ve hata analizi web ile ayni. */
    $eval = record_attempt($userId, $exercise, (string)$option['option_text'], null, 'telegram', null);
    update_streak($userId);

    $ok = $eval['correct'];
    telegram_answer_callback($callbackId, $ok ? 'Doğru' : 'Henüz değil');

    $text = "<b>Soru</b>\n\n" . htmlspecialchars((string)$exercise['prompt'], ENT_QUOTES) . "\n\n";
    $text .= ($ok ? "✅ <b>Doğru</b>\n" : "❌ <b>Henüz değil</b>\n");
    $text .= "Senin cevabın: " . htmlspecialchars((string)$option['option_text'], ENT_QUOTES) . "\n";
    if (!$ok) {
        $text .= "Doğrusu: <b>" . htmlspecialchars((string)$eval['correct_answer'], ENT_QUOTES) . "</b>\n";
    }
    if (!empty($eval['explanation'])) {
        $text .= "\n" . htmlspecialchars((string)$eval['explanation'], ENT_QUOTES) . "\n";
    }
    if (!empty($eval['mastery']['score'])) {
        $text .= "\nMastery: %" . (int)$eval['mastery']['score'];
        if (!empty($eval['mastery']['missing'])) {
            $text .= " · eksik: " . htmlspecialchars(implode(', ', array_slice($eval['mastery']['missing'], 0, 2)), ENT_QUOTES);
        }
    }
    if (!$ok) {
        $text .= "\n\nBu konu tekrar kuyruğuna eklendi.";
    }
    $text .= "\n\nYeni soru için /quiz yaz.";

    if ($messageId > 0) {
        telegram_edit_message($chatId, $messageId, $text);
    } else {
        telegram_send_message($chatId, $text);
    }
}

function log_notification(?int $userId, string $channel, string $template, bool $ok, string $error = ''): void
{
    try {
        db_exec(
            'INSERT INTO notification_log (user_id, channel, template, status, error) VALUES (?, ?, ?, ?, ?)',
            [$userId, $channel, mb_substr($template, 0, 48), $ok ? 'sent' : 'failed', $ok ? null : mb_substr(scrub_secrets($error), 0, 500)]
        );
    } catch (Throwable $e) {
        /* yoksay */
    }
}
