<?php
/**
 * AlmancaPro - Bildirim kuyrugu.
 *
 * Ayni bildirim iki kez gonderilmez: dedupe_key benzersizdir ve
 * kuyruk islenirken satirlar kilitlenir.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/planner.php';
require_once __DIR__ . '/telegram-api.php';
require_once __DIR__ . '/mailer.php';

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Kuyruga bildirim ekler. Ayni dedupe_key ile ikinci kayit olusmaz.
 */
function notify_enqueue(int $userId, string $channel, string $template, string $body, string $dedupeKey, ?string $scheduledAt = null, ?string $subject = null, array $payload = []): bool
{
    try {
        db_exec(
            'INSERT INTO notification_queue (user_id, channel, template, subject, body, payload, dedupe_key, scheduled_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId, $channel, mb_substr($template, 0, 48), $subject !== null ? mb_substr($subject, 0, 240) : null,
                $body, $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
                mb_substr($dedupeKey, 0, 190), $scheduledAt ?? now_utc(),
            ]
        );
        return true;
    } catch (Throwable $e) {
        /* Benzersiz anahtar catismasi: zaten kuyrukta. */
        return false;
    }
}

/** Kullanicinin su anda bildirim alabilir durumda olup olmadigini kontrol eder. */
function notify_allowed(array $prefs, string $type, string $channel): bool
{
    if ($channel === 'telegram' && (int)($prefs['telegram_enabled'] ?? 1) !== 1) {
        return false;
    }
    if ($channel === 'email' && (int)($prefs['email_enabled'] ?? 0) !== 1) {
        return false;
    }

    $mode = (string)($prefs['mode'] ?? 'normal');
    if ($mode === 'hafif' && !in_array($type, ['daily_reminder', 'streak_risk'], true)) {
        return false;
    }
    if ($mode !== 'yogun' && $type === 'mini_quiz') {
        return false;
    }

    $map = [
        'daily_reminder'    => 'daily_reminder',
        'review_reminder'   => 'review_reminder',
        'weak_vocabulary'   => 'weak_vocabulary',
        'mini_quiz'         => 'mini_quiz',
        'goal_reminder'     => 'goal_reminder',
        'streak_risk'       => 'streak_risk',
        'unfinished_lesson' => 'unfinished_lesson',
    ];
    if (isset($map[$type]) && (int)($prefs[$map[$type]] ?? 1) !== 1) {
        return false;
    }
    return true;
}

/** Sessiz saat kontrolu (kullanicinin kendi saat diliminde). */
function notify_in_quiet_hours(array $prefs): bool
{
    $tz = (string)($prefs['timezone'] ?? APP_DEFAULT_TIMEZONE);
    try {
        $now = new DateTime('now', new DateTimeZone($tz));
    } catch (Throwable $e) {
        $now = new DateTime('now', new DateTimeZone(APP_DEFAULT_TIMEZONE));
    }
    $current = (int)$now->format('Hi');
    $start = (int)str_replace(':', '', substr((string)($prefs['quiet_start'] ?? '22:00:00'), 0, 5));
    $end = (int)str_replace(':', '', substr((string)($prefs['quiet_end'] ?? '08:00:00'), 0, 5));

    if ($start === $end) {
        return false;
    }
    if ($start < $end) {
        return $current >= $start && $current < $end;
    }
    return $current >= $start || $current < $end;
}

/** Kullanicinin yerel saatine gore bildirim penceresinde mi? */
function notify_in_window(array $prefs): bool
{
    $tz = (string)($prefs['timezone'] ?? APP_DEFAULT_TIMEZONE);
    try {
        $now = new DateTime('now', new DateTimeZone($tz));
    } catch (Throwable $e) {
        return true;
    }
    $current = (int)$now->format('Hi');
    $start = (int)str_replace(':', '', substr((string)($prefs['start_time'] ?? '08:00:00'), 0, 5));
    $end = (int)str_replace(':', '', substr((string)($prefs['end_time'] ?? '22:00:00'), 0, 5));
    if ($start >= $end) {
        return true;
    }
    return $current >= $start && $current <= $end;
}

/**
 * Gunluk bildirimleri planlar.
 * @return int Kuyruga eklenen bildirim sayisi
 */
function notify_plan_daily(): int
{
    $added = 0;
    $today = gmdate('Y-m-d');

    $rows = db_all(
        'SELECT u.*, p.mode, p.daily_reminder, p.review_reminder, p.weak_vocabulary, p.mini_quiz,
                p.goal_reminder, p.streak_risk, p.unfinished_lesson, p.telegram_enabled, p.email_enabled,
                p.start_time, p.end_time, p.quiet_start, p.quiet_end, p.timezone AS pref_tz,
                tc.chat_id, tc.quiz_enabled
         FROM users u
         JOIN notification_preferences p ON p.user_id = u.id
         LEFT JOIN telegram_connections tc ON tc.user_id = u.id AND tc.is_active = 1
         WHERE u.is_active = 1 AND u.is_verified = 1 AND u.onboarding_completed = 1
           AND (tc.chat_id IS NOT NULL OR p.email_enabled = 1)
         LIMIT 2000'
    );

    foreach ($rows as $row) {
        $userId = (int)$row['id'];
        $prefs = $row;
        $prefs['timezone'] = (string)($row['pref_tz'] ?? $row['timezone']);
        $tz = (string)$prefs['timezone'];
        $localDate = local_date($tz);

        if (notify_in_quiet_hours($prefs) || !notify_in_window($prefs)) {
            continue;
        }

        $channel = !empty($row['chat_id']) ? 'telegram' : 'email';
        $counts = due_review_counts($userId);

        /* 1) Tekrar hatirlatmasi */
        if ($counts['due'] >= 5 && notify_allowed($prefs, 'review_reminder', $channel)) {
            $body = "🔁 <b>Tekrar zamanı</b>\n\n" . $counts['due'] . " kartın tekrar zamanı geldi"
                . ($counts['overdue'] > 0 ? " (" . $counts['overdue'] . " tanesi gecikti)" : '') . ".\n"
                . "Tahmini süre: " . $counts['est_minutes'] . " dakika.\n\n" . site_url('review.php?start=1');
            if (notify_enqueue($userId, $channel, 'review_reminder', $body, 'rev|' . $userId . '|' . $localDate, null, 'Tekrar zamanı geldi')) {
                $added++;
            }
        }

        /* 2) Gunluk hedef hatirlatmasi */
        if (notify_allowed($prefs, 'daily_reminder', $channel)) {
            $user = db_row('SELECT * FROM users WHERE id = ?', [$userId]);
            if ($user !== null) {
                $plan = daily_plan($userId, $user);
                $percent = daily_plan_percent($plan);
                if ($percent < 100) {
                    $next = next_activity($userId, $user);
                    $body = "📚 <b>Bugünkü hedefin</b>\n\nTamamlanan: %" . $percent . "\n"
                        . "Sıradaki: " . htmlspecialchars($next['title'], ENT_QUOTES) . " (~" . (int)$next['minutes'] . " dk)\n\n"
                        . site_url(ltrim($next['url'], '/'));
                    if (notify_enqueue($userId, $channel, 'daily_reminder', $body, 'day|' . $userId . '|' . $localDate, null, 'Bugünkü çalışma hedefin')) {
                        $added++;
                    }
                }
            }
        }

        /* 3) Seri riski */
        if (notify_allowed($prefs, 'streak_risk', $channel) && (int)$row['streak_count'] > 0) {
            $lastStudy = (string)($row['last_study_date'] ?? '');
            $yesterday = date('Y-m-d', strtotime($localDate . ' -1 day'));
            if ($lastStudy === $yesterday) {
                $body = "🔥 <b>Serin risk altında</b>\n\n" . (int)$row['streak_count'] . " günlük serini korumak için bugün "
                    . "en az 5 soruyu doğru cevaplaman gerekiyor.\n\n" . site_url('review.php?start=1');
                if (notify_enqueue($userId, $channel, 'streak_risk', $body, 'streak|' . $userId . '|' . $localDate, null, 'Serin risk altında')) {
                    $added++;
                }
            }
        }

        /* 4) Yarim kalan ders */
        if (notify_allowed($prefs, 'unfinished_lesson', $channel)) {
            $unfinished = db_row(
                'SELECT l.id, l.title FROM user_lesson_progress p JOIN lessons l ON l.id = p.lesson_id
                 WHERE p.user_id = ? AND p.status = "in_progress" AND p.updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)
                 ORDER BY p.updated_at ASC LIMIT 1',
                [$userId]
            );
            if ($unfinished !== null) {
                $body = "⏸ <b>Yarım kalan ders</b>\n\n" . htmlspecialchars((string)$unfinished['title'], ENT_QUOTES)
                    . " dersini tamamlamadın. Kaldığın yerden devam edebilirsin.\n\n"
                    . site_url('lesson.php?id=' . (int)$unfinished['id']);
                if (notify_enqueue($userId, $channel, 'unfinished_lesson', $body, 'unf|' . $userId . '|' . $localDate, null, 'Yarım kalan dersin var')) {
                    $added++;
                }
            }
        }

        /* 5) Zayif kelime hatirlatmasi */
        if (notify_allowed($prefs, 'weak_vocabulary', $channel)) {
            $weak = db_all(
                'SELECT v.german, v.article, v.turkish FROM user_vocabulary_mastery m
                 JOIN vocabulary v ON v.id = m.vocabulary_id
                 WHERE m.user_id = ? AND m.status = "weak" ORDER BY m.incorrect DESC LIMIT 3',
                [$userId]
            );
            if (count($weak) >= 3) {
                $lines = [];
                foreach ($weak as $w) {
                    $lines[] = '· ' . trim((string)($w['article'] ?? '') . ' ' . (string)$w['german']) . ' = ' . (string)$w['turkish'];
                }
                $body = "🎯 <b>Zorlandığın kelimeler</b>\n\n" . implode("\n", $lines) . "\n\n" . site_url('review.php?start=1');
                if (notify_enqueue($userId, $channel, 'weak_vocabulary', $body, 'weak|' . $userId . '|' . $localDate, null, 'Zorlandığın kelimeler')) {
                    $added++;
                }
            }
        }
    }

    return $added;
}

/**
 * Kuyrugu isler ve gonderir.
 * @return array{sent:int, failed:int}
 */
function notify_process_queue(int $limit = 50): array
{
    $sent = 0;
    $failed = 0;

    $rows = db_all(
        'SELECT * FROM notification_queue
         WHERE status = "pending" AND scheduled_at <= UTC_TIMESTAMP() AND attempts < 3
         ORDER BY id LIMIT ?',
        [$limit]
    );

    foreach ($rows as $row) {
        $id = (int)$row['id'];
        /* Kilitleme: yalnizca hala pending ise sending yap. */
        $locked = db_exec('UPDATE notification_queue SET status = "sending", attempts = attempts + 1 WHERE id = ? AND status = "pending"', [$id]);
        if ($locked === 0) {
            continue;
        }

        $userId = $row['user_id'] !== null ? (int)$row['user_id'] : null;
        $channel = (string)$row['channel'];
        $ok = false;
        $error = '';

        if ($channel === 'telegram') {
            $conn = $userId !== null ? db_row('SELECT * FROM telegram_connections WHERE user_id = ? AND is_active = 1', [$userId]) : null;
            if ($conn === null) {
                $error = 'Telegram bağlantısı yok';
            } else {
                $res = telegram_send_message((int)$conn['chat_id'], (string)$row['body']);
                $ok = $res['ok'];
                $error = $res['error'];
            }
        } elseif ($channel === 'email') {
            $user = $userId !== null ? db_row('SELECT * FROM users WHERE id = ?', [$userId]) : null;
            if ($user === null) {
                $error = 'Kullanıcı bulunamadı';
            } else {
                $html = mail_layout((string)($row['subject'] ?? 'AlmancaPro'), '<p>' . nl2br(strip_tags((string)$row['body'], '<b><br>')) . '</p>');
                $res = send_mail((string)$user['email'], (string)($row['subject'] ?? 'AlmancaPro'), $html, strip_tags((string)$row['body']), (string)$row['template']);
                $ok = $res['ok'];
                $error = $res['error'];
            }
        }

        if ($ok) {
            db_exec('UPDATE notification_queue SET status = "sent", sent_at = UTC_TIMESTAMP(), last_error = NULL WHERE id = ?', [$id]);
            $sent++;
        } else {
            $final = (int)$row['attempts'] + 1 >= 3;
            db_exec('UPDATE notification_queue SET status = ?, last_error = ? WHERE id = ?',
                [$final ? 'failed' : 'pending', mb_substr(scrub_secrets($error), 0, 500), $id]);
            $failed++;
        }

        notify_log($userId, $channel, (string)$row['template'], $ok, $error);
    }

    return ['sent' => $sent, 'failed' => $failed];
}

function notify_log(?int $userId, string $channel, string $template, bool $ok, string $error = ''): void
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
