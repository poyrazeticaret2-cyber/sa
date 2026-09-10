<?php
/**
 * AlmancaPro - Zamanlanmis gorevler.
 *
 * CLI:  php /path/to/httpdocs/cron.php
 * HTTP: https://site/cron.php?secret=CRON_SECRET  (?key=... da kabul edilir)
 *
 * Ayni anda iki kez calisirsa ikinci calisma kilit nedeniyle hicbir sey yapmaz;
 * bu sayede bildirimler iki kez gonderilmez.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/planner.php';
require_once __DIR__ . '/notify.php';

$isCli = PHP_SAPI === 'cli';

try {
    ensure_installed();
} catch (Throwable $e) {
    if ($isCli) {
        fwrite(STDERR, "Veritabanına bağlanılamadı.\n");
        exit(1);
    }
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Veritabanına bağlanılamadı.']);
    exit;
}

/* --- Yetkilendirme --- */
if (!$isCli) {
    $secret = (string)setting('cron_secret', '');
    $given = (string)($_GET['secret'] ?? ($_GET['key'] ?? ($_SERVER['HTTP_X_CRON_SECRET'] ?? '')));
    if ($secret === '' || !hash_equals($secret, $given)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Yetkisiz.']);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

/* --- Kilit: es zamanli calismayi engeller --- */
if (!acquire_lock('cron_main', 600)) {
    $msg = 'Başka bir cron çalışması devam ediyor; bu çalışma atlandı.';
    if ($isCli) {
        echo $msg . "\n";
        exit(0);
    }
    echo json_encode(['success' => true, 'message' => $msg, 'data' => (object)[], 'errors' => (object)[]]);
    exit;
}

$startedAt = microtime(true);
$report = [];

try {
    /* 1) Suresi dolmus kayitlari temizle */
    $report['expired_verifications'] = db_exec(
        'DELETE FROM email_verifications WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 DAY)'
    );
    $report['expired_resets'] = db_exec(
        'DELETE FROM password_resets WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 DAY)'
    );
    $report['expired_remember'] = db_exec(
        'DELETE FROM remember_tokens WHERE expires_at < UTC_TIMESTAMP()'
    );
    $report['expired_link_tokens'] = db_exec(
        'DELETE FROM telegram_link_tokens WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)'
    );
    $report['old_rate_limits'] = db_exec(
        'DELETE FROM rate_limits WHERE window_start < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)'
    );
    $report['old_updates'] = db_exec(
        'DELETE FROM telegram_updates WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)'
    );
    $report['old_quiz_state'] = db_exec(
        'DELETE FROM telegram_quiz_state WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)'
    );
    $report['old_locks'] = db_exec('DELETE FROM app_locks WHERE expires_at < UTC_TIMESTAMP()');

    /* 2) Terk edilmis oturumlari kapat */
    $report['abandoned_sessions'] = db_exec(
        'UPDATE study_sessions SET status = "abandoned", ended_at = UTC_TIMESTAMP()
         WHERE status = "active" AND started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 12 HOUR)'
    );

    /* 3) Geciken tekrarlari isaretle */
    $report['overdue_vocab'] = db_exec(
        'UPDATE user_vocabulary_mastery SET status = "overdue"
         WHERE status IN ("learning","reviewing","strong") AND next_review_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 DAY)'
    );
    $report['overdue_skills'] = db_exec(
        'UPDATE user_skill_mastery SET status = "overdue"
         WHERE status IN ("learning","reviewing","strong") AND next_review_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 DAY)'
    );

    /* 4) Serisi kopanlari sifirla (kullanicinin kendi saat diliminde) */
    $broken = 0;
    foreach (db_all('SELECT id, timezone, last_study_date, streak_count FROM users WHERE streak_count > 0 AND is_active = 1 LIMIT 5000') as $u) {
        $tz = user_timezone($u);
        $today = local_date($tz);
        $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
        $last = (string)($u['last_study_date'] ?? '');
        if ($last !== '' && $last !== $today && $last !== $yesterday) {
            db_exec('UPDATE users SET streak_count = 0 WHERE id = ?', [(int)$u['id']]);
            $broken++;
        }
    }
    $report['streaks_reset'] = $broken;

    /* 5) Gunluk planlari olustur (yalnizca son 3 gunde aktif olan kullanicilar) */
    $plans = 0;
    foreach (db_all(
        'SELECT u.* FROM users u
         WHERE u.is_active = 1 AND u.is_verified = 1 AND u.onboarding_completed = 1
           AND (u.last_login_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY) OR u.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 DAY))
         LIMIT 1000'
    ) as $u) {
        try {
            $tz = user_timezone($u);
            $exists = (int)db_value('SELECT COUNT(*) FROM daily_plans WHERE user_id = ? AND plan_date = ?', [(int)$u['id'], local_date($tz)], 0);
            if ($exists === 0) {
                daily_plan((int)$u['id'], $u);
                $plans++;
            }
        } catch (Throwable $e) {
            app_log('Günlük plan oluşturulamadı (user ' . (int)$u['id'] . '): ' . $e->getMessage());
        }
    }
    $report['daily_plans_created'] = $plans;

    /* 6) Bildirimleri planla ve gonder */
    $report['notifications_queued'] = notify_plan_daily();
    $queue = notify_process_queue(60);
    $report['notifications_sent'] = $queue['sent'];
    $report['notifications_failed'] = $queue['failed'];

    /* 7) Eski loglari budama */
    $report['old_notification_logs'] = db_exec(
        'DELETE FROM notification_log WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)'
    );
    $report['old_login_attempts'] = db_exec(
        'DELETE FROM login_attempts WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)'
    );
    $report['old_admin_attempts'] = db_exec(
        'DELETE FROM admin_login_attempts WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)'
    );
    $report['old_mail_logs'] = db_exec(
        'DELETE FROM mail_log WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 DAY)'
    );

    $duration = round(microtime(true) - $startedAt, 2);
    $summary = [];
    foreach ($report as $k => $v) {
        if ((int)$v > 0) {
            $summary[] = $k . '=' . (int)$v;
        }
    }
    $summaryText = ($summary === [] ? 'değişiklik yok' : implode(' ', $summary)) . ' · ' . $duration . 's';

    setting_set('cron_last_run', now_utc());
    setting_set('cron_last_summary', $summaryText);
} catch (Throwable $e) {
    app_log('Cron hatası: ' . $e->getMessage());
    setting_set('cron_last_summary', 'HATA: ' . mb_substr(scrub_secrets($e->getMessage()), 0, 200));
    release_lock('cron_main');
    if ($isCli) {
        fwrite(STDERR, "Cron hatası.\n");
        exit(1);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Cron çalışırken hata oluştu.']);
    exit;
}

release_lock('cron_main');

if ($isCli) {
    echo "AlmancaPro cron tamamlandı (" . $duration . "s)\n";
    foreach ($report as $k => $v) {
        printf("  %-26s %d\n", $k, (int)$v);
    }
    exit(0);
}

echo json_encode([
    'success' => true,
    'message' => 'Cron tamamlandı.',
    'data' => ['duration' => $duration, 'report' => $report],
    'errors' => (object)[],
], JSON_UNESCAPED_UNICODE);
