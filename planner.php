<?php
/**
 * AlmancaPro - Gunluk plan, ogrenme yolu ve "siradaki dogru aktivite" motoru.
 */
declare(strict_types=1);

require_once __DIR__ . '/learning.php';

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/* ==================================================================
 * Ogrenme yolu
 * ================================================================== */

/** Kullanicinin tum modul/ders agacini durumlariyla dondurur. */
function learning_path(int $userId): array
{
    $modules = db_all('SELECT * FROM modules WHERE is_active = 1 ORDER BY FIELD(cefr_level,"A0","A1","A2","B1"), sort_order, id');
    $lessons = db_all(
        'SELECT l.*, COALESCE(p.status, "not_started") AS progress_status, COALESCE(p.best_score,0) AS best_score
         FROM lessons l
         LEFT JOIN user_lesson_progress p ON p.lesson_id = l.id AND p.user_id = ?
         WHERE l.is_active = 1
         ORDER BY l.sort_order, l.id',
        [$userId]
    );

    /* Onkosullari tek sorguda topla (N+1 yok). */
    $prereqRows = db_all(
        'SELECT lp.lesson_id, lp.required_mastery, s.id AS skill_id, s.code, s.name,
                COALESCE(m.mastery_score,0) AS score, COALESCE(m.status,"introduced") AS status
         FROM lesson_prerequisites lp
         JOIN skills s ON s.id = lp.skill_id
         LEFT JOIN user_skill_mastery m ON m.skill_id = s.id AND m.user_id = ?',
        [$userId]
    );
    $prereqs = [];
    foreach ($prereqRows as $r) {
        $prereqs[(int)$r['lesson_id']][] = [
            'skill_id' => (int)$r['skill_id'],
            'code'     => (string)$r['code'],
            'name'     => (string)$r['name'],
            'required' => (int)$r['required_mastery'],
            'score'    => (int)$r['score'],
            'status'   => (string)$r['status'],
            'ok'       => (int)$r['score'] >= (int)$r['required_mastery'],
        ];
    }

    $byModule = [];
    foreach ($lessons as $l) {
        $reqs = $prereqs[(int)$l['id']] ?? [];
        $missing = 0;
        foreach ($reqs as $r) {
            if (!$r['ok']) {
                $missing++;
            }
        }
        $l['requirements'] = $reqs;
        $l['locked'] = $missing > 0;
        $l['missing_count'] = $missing;
        $l['state'] = match (true) {
            $l['progress_status'] === 'completed' => 'completed',
            $missing > 0 => 'locked',
            $l['progress_status'] === 'in_progress' => 'active',
            $l['progress_status'] === 'needs_review' => 'review',
            default => 'available',
        };
        $byModule[(int)$l['module_id']][] = $l;
    }

    $out = [];
    foreach ($modules as $m) {
        $ml = $byModule[(int)$m['id']] ?? [];
        $done = 0;
        foreach ($ml as $l) {
            if ($l['state'] === 'completed') {
                $done++;
            }
        }
        $m['lessons'] = $ml;
        $m['lesson_count'] = count($ml);
        $m['completed_count'] = $done;
        $m['percent'] = pct($done, max(1, count($ml)));
        $m['state'] = match (true) {
            $ml === [] => 'empty',
            $done === count($ml) => 'completed',
            $done > 0 => 'active',
            default => 'available',
        };
        $out[] = $m;
    }
    return $out;
}

function level_summary(int $userId): array
{
    $rows = db_all(
        'SELECT l.cefr_level,
                COUNT(*) AS total,
                SUM(CASE WHEN p.status = "completed" THEN 1 ELSE 0 END) AS completed
         FROM lessons l
         LEFT JOIN user_lesson_progress p ON p.lesson_id = l.id AND p.user_id = ?
         WHERE l.is_active = 1
         GROUP BY l.cefr_level',
        [$userId]
    );
    $map = [];
    foreach (cefr_levels() as $lvl) {
        $map[$lvl] = ['level' => $lvl, 'total' => 0, 'completed' => 0, 'percent' => 0];
    }
    foreach ($rows as $r) {
        $lvl = (string)$r['cefr_level'];
        if (!isset($map[$lvl])) {
            continue;
        }
        $map[$lvl]['total'] = (int)$r['total'];
        $map[$lvl]['completed'] = (int)$r['completed'];
        $map[$lvl]['percent'] = pct((int)$r['completed'], max(1, (int)$r['total']));
    }
    return array_values($map);
}

/* ==================================================================
 * Siradaki aktivite
 * ================================================================== */

/**
 * Pedagojik olarak siradaki en dogru aktiviteyi belirler.
 * @return array{type:string,title:string,url:string,minutes:int,reason:string}
 */
function next_activity(int $userId, array $user): array
{
    /* 1) Cok geciken tekrarlar her seyin onunde. */
    $counts = due_review_counts($userId);
    if ($counts['overdue'] >= 5) {
        return [
            'type' => 'review',
            'title' => 'Geciken tekrarlar',
            'url' => '/review.php?start=1',
            'minutes' => max(3, (int)ceil($counts['overdue'] * 0.5)),
            'reason' => $counts['overdue'] . ' kart çok gecikti. Önce bunları sağlamlaştıralım.',
        ];
    }

    /* 2) Devam eden bir oturum varsa ona don. */
    $active = db_row(
        'SELECT id, session_type, lesson_id FROM study_sessions WHERE user_id = ? AND status = "active" ORDER BY id DESC LIMIT 1',
        [$userId]
    );
    if ($active !== null) {
        return [
            'type' => 'session',
            'title' => 'Yarım kalan oturuma devam et',
            'url' => '/quiz.php?session=' . (int)$active['id'],
            'minutes' => 5,
            'reason' => 'Başlattığın oturum tamamlanmadı.',
        ];
    }

    /* 3) Zayif skill varsa remediation. */
    $weak = db_row(
        'SELECT s.id, s.name FROM user_skill_mastery m JOIN skills s ON s.id = m.skill_id
         WHERE m.user_id = ? AND m.status = "weak" AND m.attempts >= 3
         ORDER BY m.mastery_score ASC LIMIT 1',
        [$userId]
    );
    if ($weak !== null) {
        return [
            'type' => 'remediation',
            'title' => $weak['name'] . ' konusunu güçlendir',
            'url' => '/exercise.php?skill=' . (int)$weak['id'],
            'minutes' => 6,
            'reason' => 'Bu konuda hataların devam ediyor.',
        ];
    }

    /* 4) Zamani gelen tekrarlar. */
    if ($counts['due'] >= 8) {
        return [
            'type' => 'review',
            'title' => 'Günün tekrarı',
            'url' => '/review.php?start=1',
            'minutes' => $counts['est_minutes'],
            'reason' => $counts['due'] . ' kartın tekrar zamanı geldi.',
        ];
    }

    /* 5) Yarim kalan ders. */
    $inProgress = db_row(
        'SELECT l.id, l.title, l.estimated_minutes FROM user_lesson_progress p
         JOIN lessons l ON l.id = p.lesson_id
         WHERE p.user_id = ? AND p.status = "in_progress" AND l.is_active = 1
         ORDER BY p.updated_at DESC LIMIT 1',
        [$userId]
    );
    if ($inProgress !== null) {
        return [
            'type' => 'lesson',
            'title' => $inProgress['title'],
            'url' => '/lesson.php?id=' . (int)$inProgress['id'],
            'minutes' => (int)$inProgress['estimated_minutes'],
            'reason' => 'Bu derse başladın ama henüz kanıtlamadın.',
        ];
    }

    /* 6) Acik olan ilk yeni ders. */
    $lesson = next_available_lesson($userId, $user);
    if ($lesson !== null) {
        return [
            'type' => 'lesson',
            'title' => $lesson['title'],
            'url' => '/lesson.php?id=' . (int)$lesson['id'],
            'minutes' => (int)$lesson['estimated_minutes'],
            'reason' => 'Sıradaki yeni konu.',
        ];
    }

    /* 7) Tekrar kalmadiysa serbest quiz. */
    if ($counts['due'] > 0) {
        return [
            'type' => 'review',
            'title' => 'Tekrar',
            'url' => '/review.php?start=1',
            'minutes' => $counts['est_minutes'],
            'reason' => 'Küçük bir tekrar seti hazır.',
        ];
    }

    return [
        'type' => 'quiz',
        'title' => 'Karma quiz',
        'url' => '/quiz.php?mode=mixed',
        'minutes' => 8,
        'reason' => 'Bugünlük her şey tamam. Karma quiz ile pekiştir.',
    ];
}

function next_available_lesson(int $userId, array $user): ?array
{
    $levelOrder = ['A0' => 0, 'A1' => 1, 'A2' => 2, 'B1' => 3];
    $userLevel = $levelOrder[(string)$user['cefr_level']] ?? 0;

    $candidates = db_all(
        'SELECT l.* FROM lessons l
         LEFT JOIN user_lesson_progress p ON p.lesson_id = l.id AND p.user_id = ?
         WHERE l.is_active = 1 AND COALESCE(p.status, "not_started") <> "completed"
         ORDER BY FIELD(l.cefr_level,"A0","A1","A2","B1"), l.sort_order, l.id
         LIMIT 60',
        [$userId]
    );
    foreach ($candidates as $l) {
        $lvl = $levelOrder[(string)$l['cefr_level']] ?? 0;
        if ($lvl > $userLevel + 1) {
            continue;
        }
        if (lesson_lock_state($userId, (int)$l['id'])['unlocked']) {
            return $l;
        }
    }
    return null;
}

/* ==================================================================
 * Gunluk plan
 * ================================================================== */

function intensity_for(int $minutes): string
{
    return match (true) {
        $minutes <= 20 => 'hafif',
        $minutes <= 45 => 'normal',
        $minutes <= 90 => 'yogun',
        default => 'cok_yogun',
    };
}

function days_until_departure(?string $departureDate, string $tz = APP_DEFAULT_TIMEZONE): ?int
{
    if ($departureDate === null || $departureDate === '') {
        return null;
    }
    $today = new DateTime(local_date($tz));
    try {
        $target = new DateTime($departureDate);
    } catch (Throwable $e) {
        return null;
    }
    $diff = (int)$today->diff($target)->format('%r%a');
    return $diff;
}

/** Bugunun planini getirir; yoksa uretir. */
function daily_plan(int $userId, array $user, bool $regenerate = false): array
{
    $tz = user_timezone($user);
    $today = local_date($tz);

    $plan = db_row('SELECT * FROM daily_plans WHERE user_id = ? AND plan_date = ?', [$userId, $today]);
    if ($plan !== null && !$regenerate) {
        $plan['items'] = db_all('SELECT * FROM daily_plan_items WHERE plan_id = ? ORDER BY sort_order, id', [(int)$plan['id']]);
        refresh_plan_progress($userId, $plan);
        $plan['items'] = db_all('SELECT * FROM daily_plan_items WHERE plan_id = ? ORDER BY sort_order, id', [(int)$plan['id']]);
        return $plan;
    }

    $minutes = max(10, (int)$user['daily_minutes']);
    $intensity = intensity_for($minutes);
    $daysLeft = days_until_departure($user['departure_date'] ?? null, $tz);
    $programDay = null;
    if ((int)($user['intensive_enabled'] ?? 0) === 1 && !empty($user['intensive_started_on'])) {
        $start = new DateTime((string)$user['intensive_started_on']);
        $now = new DateTime($today);
        $programDay = min(30, max(1, (int)$start->diff($now)->format('%a') + 1));
    }

    if ($plan === null) {
        $planId = db_insert(
            'INSERT INTO daily_plans (user_id, plan_date, target_minutes, intensity, program_day) VALUES (?, ?, ?, ?, ?)',
            [$userId, $today, $minutes, $intensity, $programDay]
        );
    } else {
        $planId = (int)$plan['id'];
        db_exec('UPDATE daily_plans SET target_minutes = ?, intensity = ?, program_day = ? WHERE id = ?', [$minutes, $intensity, $programDay, $planId]);
        db_exec('DELETE FROM daily_plan_items WHERE plan_id = ?', [$planId]);
    }

    $items = build_plan_items($userId, $user, $minutes, $daysLeft, $programDay);
    $order = 0;
    foreach ($items as $it) {
        db_exec(
            'INSERT INTO daily_plan_items (plan_id, item_type, ref_id, title, target_count, estimated_minutes, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$planId, $it['type'], $it['ref_id'], $it['title'], $it['target'], $it['minutes'], $order++]
        );
    }

    $plan = db_row('SELECT * FROM daily_plans WHERE id = ?', [$planId]) ?? [];
    refresh_plan_progress($userId, $plan);
    $plan['items'] = db_all('SELECT * FROM daily_plan_items WHERE plan_id = ? ORDER BY sort_order, id', [$planId]);
    return $plan;
}

function build_plan_items(int $userId, array $user, int $minutes, ?int $daysLeft, ?int $programDay): array
{
    $counts = due_review_counts($userId);
    $items = [];

    /* Yogunluk katsayisi: az zaman kalmissa hedefler artar. */
    $pressure = 1.0;
    if ($daysLeft !== null && $daysLeft > 0 && $daysLeft <= 45) {
        $pressure = 1.35;
    } elseif ($daysLeft !== null && $daysLeft <= 0) {
        $pressure = 1.15;
    }
    if ($programDay !== null) {
        $pressure = max($pressure, 1.4);
    }

    $wordTarget = (int)round(($minutes / 30) * 10 * $pressure);
    $wordTarget = max(5, min(45, $wordTarget));
    $reviewTarget = max(5, min(60, (int)round($counts['due'] > 0 ? $counts['due'] : $minutes / 2)));
    $lessonTarget = max(1, (int)floor($minutes / 25));

    $items[] = ['type' => 'vocabulary', 'ref_id' => null, 'title' => 'Yeni kelime çalışması', 'target' => $wordTarget, 'minutes' => (int)max(4, round($wordTarget * 0.4))];

    $lesson = next_available_lesson($userId, $user);
    $items[] = [
        'type' => 'lesson',
        'ref_id' => $lesson !== null ? (int)$lesson['id'] : null,
        'title' => $lesson !== null ? 'Ders: ' . $lesson['title'] : 'Sıradaki ders',
        'target' => $lessonTarget,
        'minutes' => $lesson !== null ? (int)$lesson['estimated_minutes'] : 12,
    ];

    $items[] = ['type' => 'review', 'ref_id' => null, 'title' => 'Tekrar (SRS)', 'target' => $reviewTarget, 'minutes' => max(4, $counts['est_minutes'])];

    $weak = weak_skills($userId, 1);
    if ($weak !== []) {
        $items[] = [
            'type' => 'remediation',
            'ref_id' => (int)$weak[0]['id'],
            'title' => 'Zayıf konu: ' . $weak[0]['name'],
            'target' => 6,
            'minutes' => 6,
        ];
    }

    if ($minutes >= 45) {
        $wp = db_row(
            'SELECT l.id, l.title FROM lessons l
             LEFT JOIN user_lesson_progress p ON p.lesson_id = l.id AND p.user_id = ?
             WHERE l.lesson_type = "workplace" AND l.is_active = 1 AND COALESCE(p.status,"not_started") <> "completed"
             ORDER BY l.sort_order LIMIT 1',
            [$userId]
        );
        if ($wp !== null) {
            $items[] = ['type' => 'workplace', 'ref_id' => (int)$wp['id'], 'title' => 'İş Almancası: ' . $wp['title'], 'target' => 1, 'minutes' => 10];
        }
    }

    if ($minutes >= 60) {
        $sc = db_row(
            'SELECT id, title FROM scenarios WHERE is_active = 1 ORDER BY RAND() LIMIT 1'
        );
        if ($sc !== null) {
            $items[] = ['type' => 'scenario', 'ref_id' => (int)$sc['id'], 'title' => 'Konuşma senaryosu: ' . $sc['title'], 'target' => 1, 'minutes' => 8];
        }
    }

    $items[] = ['type' => 'quiz', 'ref_id' => null, 'title' => 'Günlük quiz', 'target' => 10, 'minutes' => 6];

    if ($programDay !== null && $programDay % 5 === 0) {
        $items[] = ['type' => 'exam', 'ref_id' => null, 'title' => 'Mini sınav', 'target' => 15, 'minutes' => 10];
    }

    return $items;
}

/** Plan kalemlerinin gercek ilerlemesini DB'den hesaplar. */
function refresh_plan_progress(int $userId, array $plan): void
{
    if ($plan === []) {
        return;
    }
    $planId = (int)$plan['id'];
    $date = (string)$plan['plan_date'];
    $user = db_row('SELECT timezone FROM users WHERE id = ?', [$userId]);
    $tz = user_timezone($user ?? []);
    $startUtc = gmdate('Y-m-d H:i:s', strtotime($date . ' 00:00:00 ' . $tz) ?: time());
    $endUtc = gmdate('Y-m-d H:i:s', strtotime($date . ' 23:59:59 ' . $tz) ?: time());

    $items = db_all('SELECT * FROM daily_plan_items WHERE plan_id = ?', [$planId]);
    foreach ($items as $item) {
        $done = 0;
        switch ($item['item_type']) {
            case 'vocabulary':
                $done = (int)db_value(
                    'SELECT COUNT(DISTINCT vocabulary_id) FROM exercise_attempts WHERE user_id = ? AND vocabulary_id IS NOT NULL AND is_correct = 1 AND created_at BETWEEN ? AND ?',
                    [$userId, $startUtc, $endUtc], 0
                );
                break;
            case 'review':
                $done = (int)db_value(
                    'SELECT COUNT(*) FROM exercise_attempts a JOIN study_sessions s ON s.id = a.session_id
                     WHERE a.user_id = ? AND s.session_type = "review" AND a.created_at BETWEEN ? AND ?',
                    [$userId, $startUtc, $endUtc], 0
                );
                break;
            case 'lesson':
            case 'workplace':
                if ($item['ref_id'] !== null) {
                    $done = (int)db_value(
                        'SELECT COUNT(*) FROM user_lesson_progress WHERE user_id = ? AND lesson_id = ? AND status = "completed"',
                        [$userId, (int)$item['ref_id']], 0
                    );
                } else {
                    $done = (int)db_value(
                        'SELECT COUNT(*) FROM user_lesson_progress WHERE user_id = ? AND status = "completed" AND completed_at BETWEEN ? AND ?',
                        [$userId, $startUtc, $endUtc], 0
                    );
                }
                break;
            case 'quiz':
            case 'exam':
                $done = (int)db_value(
                    'SELECT COUNT(*) FROM exercise_attempts a JOIN study_sessions s ON s.id = a.session_id
                     WHERE a.user_id = ? AND s.session_type IN ("quiz","checkpoint") AND a.created_at BETWEEN ? AND ?',
                    [$userId, $startUtc, $endUtc], 0
                );
                break;
            case 'remediation':
                $done = (int)db_value(
                    'SELECT COUNT(*) FROM exercise_attempts WHERE user_id = ? AND skill_id = ? AND created_at BETWEEN ? AND ?',
                    [$userId, (int)$item['ref_id'], $startUtc, $endUtc], 0
                );
                break;
            case 'scenario':
                $done = (int)db_value(
                    'SELECT COUNT(*) FROM user_scenario_runs WHERE user_id = ? AND completed_at BETWEEN ? AND ?',
                    [$userId, $startUtc, $endUtc], 0
                );
                break;
        }
        $target = max(1, (int)$item['target_count']);
        $status = $done >= $target ? 'done' : ($done > 0 ? 'in_progress' : 'pending');
        db_exec('UPDATE daily_plan_items SET done_count = ?, status = ? WHERE id = ?', [min($done, $target), $status, (int)$item['id']]);
    }

    $agg = db_row('SELECT SUM(done_count) d, SUM(target_count) t FROM daily_plan_items WHERE plan_id = ?', [$planId]);
    if ($agg !== null && (int)$agg['t'] > 0 && (int)$agg['d'] >= (int)$agg['t']) {
        db_exec('UPDATE daily_plans SET completed_at = COALESCE(completed_at, UTC_TIMESTAMP()) WHERE id = ?', [$planId]);
    }
}

function daily_plan_percent(array $plan): int
{
    $items = $plan['items'] ?? [];
    $done = 0;
    $target = 0;
    foreach ($items as $i) {
        $done += (int)$i['done_count'];
        $target += (int)$i['target_count'];
    }
    return pct($done, max(1, $target));
}

/* ==================================================================
 * Oturum kurma
 * ================================================================== */

/**
 * Yeni bir calisma oturumu olusturur ve egzersizleri kuyruga koyar.
 * @param array<int,array> $exercises
 */
function create_session(int $userId, string $type, array $exercises, ?int $lessonId = null, ?int $skillId = null, string $source = 'web'): int
{
    $sessionId = db_insert(
        'INSERT INTO study_sessions (user_id, session_type, lesson_id, skill_id, items_total, source) VALUES (?, ?, ?, ?, ?, ?)',
        [$userId, $type, $lessonId, $skillId, count($exercises), $source]
    );
    $pos = 0;
    foreach ($exercises as $ex) {
        db_exec(
            'INSERT INTO session_items (session_id, exercise_id, position) VALUES (?, ?, ?)',
            [$sessionId, (int)$ex['id'], $pos++]
        );
    }
    return $sessionId;
}

/** Oturumdaki siradaki bekleyen soruyu getirir. */
function session_next_item(int $sessionId): ?array
{
    $item = db_row(
        'SELECT * FROM session_items WHERE session_id = ? AND status IN ("pending","requeued") ORDER BY position, id LIMIT 1',
        [$sessionId]
    );
    if ($item === null) {
        return null;
    }
    $ex = db_row('SELECT * FROM exercises WHERE id = ?', [(int)$item['exercise_id']]);
    if ($ex === null) {
        db_exec('UPDATE session_items SET status = "correct" WHERE id = ?', [(int)$item['id']]);
        return session_next_item($sessionId);
    }
    $ex = hydrate_exercise($ex);
    $ex['session_item_id'] = (int)$item['id'];
    $ex['session_position'] = (int)$item['position'];
    return $ex;
}

function session_progress(int $sessionId): array
{
    $row = db_row(
        'SELECT COUNT(*) total, SUM(CASE WHEN status IN ("correct","wrong") THEN 1 ELSE 0 END) answered,
                SUM(CASE WHEN status = "correct" THEN 1 ELSE 0 END) correct,
                SUM(CASE WHEN status = "wrong" THEN 1 ELSE 0 END) wrong
         FROM session_items WHERE session_id = ?',
        [$sessionId]
    ) ?? [];
    return [
        'total'    => (int)($row['total'] ?? 0),
        'answered' => (int)($row['answered'] ?? 0),
        'correct'  => (int)($row['correct'] ?? 0),
        'wrong'    => (int)($row['wrong'] ?? 0),
    ];
}

/** Yanlis cevaplanan karti kuyrugun ilerisine yeniden ekler. */
function session_requeue(int $sessionId, int $exerciseId, int $afterCards = 2): void
{
    $maxPos = (int)db_value('SELECT COALESCE(MAX(position),0) FROM session_items WHERE session_id = ?', [$sessionId], 0);
    $current = (int)db_value(
        'SELECT COALESCE(MIN(position), 0) FROM session_items WHERE session_id = ? AND status IN ("pending","requeued")',
        [$sessionId], 0
    );
    $newPos = min($maxPos + 1, $current + $afterCards);
    db_exec(
        'INSERT INTO session_items (session_id, exercise_id, position, status) VALUES (?, ?, ?, "requeued")',
        [$sessionId, $exerciseId, $newPos]
    );
    db_exec('UPDATE study_sessions SET items_total = items_total + 1 WHERE id = ?', [$sessionId]);
}

function close_session(int $sessionId): void
{
    $p = session_progress($sessionId);
    db_exec(
        'UPDATE study_sessions SET status = "completed", ended_at = UTC_TIMESTAMP(),
            correct_count = ?, wrong_count = ?,
            duration_seconds = TIMESTAMPDIFF(SECOND, started_at, UTC_TIMESTAMP())
         WHERE id = ? AND status = "active"',
        [$p['correct'], $p['wrong'], $sessionId]
    );
}

/* ==================================================================
 * Oturum kurucular
 * ================================================================== */

/** Ders quizi: dersin egzersizleri + ders kelimeleri. */
function build_lesson_quiz(int $userId, int $lessonId, int $limit = 10): array
{
    $exercises = db_all(
        'SELECT * FROM exercises WHERE lesson_id = ? AND is_active = 1 AND is_generated = 0 ORDER BY sort_order, id LIMIT ?',
        [$lessonId, $limit]
    );
    if (count($exercises) < $limit) {
        $need = $limit - count($exercises);
        $ids = array_column($exercises, 'id');
        $notIn = $ids === [] ? '' : ' AND e.id NOT IN (' . implode(',', array_map('intval', $ids)) . ')';
        $extra = db_all(
            'SELECT e.* FROM exercises e
             JOIN lesson_vocabulary lv ON lv.vocabulary_id = e.vocabulary_id
             WHERE lv.lesson_id = ? AND e.is_active = 1' . $notIn . '
             ORDER BY RAND() LIMIT ?',
            [$lessonId, $need]
        );
        $exercises = array_merge($exercises, $extra);
    }
    return array_map('hydrate_exercise', $exercises);
}

/** Mastery checkpoint: farkli modlarda, atlanamaz test. */
function build_checkpoint(int $userId, int $lessonId, int $limit = 12): array
{
    $skillIds = array_column(db_all('SELECT skill_id FROM lesson_skills WHERE lesson_id = ? AND is_primary = 1', [$lessonId]), 'skill_id');
    $items = [];
    $used = [];
    foreach ($skillIds as $sid) {
        foreach (['recognition', 'recall', 'production'] as $mode) {
            $ex = db_row(
                'SELECT * FROM exercises WHERE skill_id = ? AND mode = ? AND is_active = 1'
                . ($used === [] ? '' : ' AND id NOT IN (' . implode(',', array_map('intval', $used)) . ')')
                . ' ORDER BY RAND() LIMIT 1',
                [(int)$sid, $mode]
            );
            if ($ex !== null) {
                $used[] = (int)$ex['id'];
                $items[] = hydrate_exercise($ex);
            }
        }
    }
    $need = $limit - count($items);
    if ($need > 0) {
        $extra = db_all(
            'SELECT e.* FROM exercises e
             JOIN lesson_vocabulary lv ON lv.vocabulary_id = e.vocabulary_id
             WHERE lv.lesson_id = ? AND e.is_active = 1 AND e.exercise_type IN ("translation_tr_de","article","plural","fill_blank")'
            . ($used === [] ? '' : ' AND e.id NOT IN (' . implode(',', array_map('intval', $used)) . ')')
            . ' ORDER BY RAND() LIMIT ?',
            [$lessonId, $need]
        );
        foreach ($extra as $e2) {
            $items[] = hydrate_exercise($e2);
        }
    }
    return $items;
}

/** Zayif skill icin remediation seti. */
function build_remediation(int $userId, int $skillId, int $limit = 8): array
{
    $items = [];
    $used = [];
    for ($i = 0; $i < $limit; $i++) {
        $ex = pick_exercise_for_skill($userId, $skillId, null, $used);
        if ($ex === null) {
            break;
        }
        $used[] = (int)$ex['id'];
        $items[] = $ex;
    }
    return $items;
}

/** Karma quiz: kullanicinin seviyesine uygun karisik sorular. */
function build_mixed_quiz(int $userId, array $user, int $limit = 10): array
{
    $levels = [];
    foreach (cefr_levels() as $lvl) {
        $levels[] = $lvl;
        if ($lvl === (string)$user['cefr_level']) {
            break;
        }
    }
    $in = implode(',', array_fill(0, count($levels), '?'));
    $params = $levels;
    $params[] = $userId;
    $params[] = $limit;
    $rows = db_all(
        'SELECT e.* FROM exercises e
         WHERE e.is_active = 1 AND e.cefr_level IN (' . $in . ')
           AND (e.skill_id IS NOT NULL OR e.vocabulary_id IS NOT NULL)
           AND NOT EXISTS (
               SELECT 1 FROM exercise_attempts a
               WHERE a.user_id = ? AND a.exercise_id = e.id AND a.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 45 MINUTE)
           )
         ORDER BY RAND() LIMIT ?',
        $params
    );
    return array_map('hydrate_exercise', $rows);
}

/** Yeni kelime ogrenme seti: henuz calisilmamis ders kelimeleri. */
function build_vocabulary_set(int $userId, array $user, int $limit = 10, ?int $lessonId = null): array
{
    $params = [$userId];
    $where = 'v.is_active = 1 AND m.id IS NULL';
    if ($lessonId !== null) {
        $where .= ' AND lv.lesson_id = ?';
        $params[] = $lessonId;
        $join = 'JOIN lesson_vocabulary lv ON lv.vocabulary_id = v.id';
    } else {
        $join = 'LEFT JOIN lesson_vocabulary lv ON lv.vocabulary_id = v.id';
        $where .= ' AND FIND_IN_SET(v.cefr_level, ?) > 0';
        $levels = [];
        foreach (cefr_levels() as $lvl) {
            $levels[] = $lvl;
            if ($lvl === (string)$user['cefr_level']) {
                break;
            }
        }
        $params[] = implode(',', $levels);
    }
    $params[] = $limit;

    return db_all(
        'SELECT DISTINCT v.* FROM vocabulary v
         ' . $join . '
         LEFT JOIN user_vocabulary_mastery m ON m.vocabulary_id = v.id AND m.user_id = ?
         WHERE ' . $where . '
         ORDER BY FIELD(v.cefr_level,"A0","A1","A2","B1"), lv.sort_order, v.id
         LIMIT ?',
        $params
    );
}
