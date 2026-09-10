<?php
/**
 * AlmancaPro - Ogrenme motoru: cevap degerlendirme, hata taksonomisi,
 * mastery hesabi ve araliklı tekrar (SRS).
 *
 * Tum ogrenme kisitlari sunucu tarafinda uygulanir.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/* ==================================================================
 * Normalizasyon ve karsilastirma
 * ================================================================== */

/** Karsilastirma icin sadelestirir (buyuk/kucuk harf, umlaut, noktalama). */
function answer_key(string $text): string
{
    $t = trim($text);
    $t = mb_strtolower($t, 'UTF-8');
    $t = strtr($t, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    $t = preg_replace('/[.,;:!?"„“”\'()\-]/u', ' ', $t) ?? $t;
    $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
    return trim($t);
}

/** Yalnizca bosluk ve noktalama farkini yok sayar, harf buyuklugunu korur. */
function answer_key_case_sensitive(string $text): string
{
    $t = trim($text);
    $t = preg_replace('/[.,;:!?"„“”\']/u', ' ', $t) ?? $t;
    $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
    return trim($t);
}

function answer_tokens(string $text): array
{
    $k = answer_key($text);
    return $k === '' ? [] : explode(' ', $k);
}

function levenshtein_utf8(string $a, string $b): int
{
    $a = mb_substr($a, 0, 120);
    $b = mb_substr($b, 0, 120);
    return levenshtein($a, $b);
}

/** Bir kelime Almanca isim gibi mi (buyuk harfle baslamali)? */
function looks_like_german_noun(string $word): bool
{
    return mb_strlen($word) > 1 && preg_match('/^[A-ZÄÖÜ]/u', $word) === 1;
}

/* ==================================================================
 * Hata taksonomisi
 * ================================================================== */

function error_category_id(string $code): ?int
{
    static $map = null;
    if ($map === null) {
        $map = [];
        try {
            foreach (db_all('SELECT id, code FROM error_categories') as $r) {
                $map[$r['code']] = (int)$r['id'];
            }
        } catch (Throwable $e) {
            $map = [];
        }
    }
    return $map[$code] ?? null;
}

function error_category_label(string $code): string
{
    return match ($code) {
        'article_error'          => 'Artikel hatası',
        'gender_error'           => 'Cinsiyet hatası',
        'case_error'             => 'Hal (Kasus) hatasi',
        'conjugation_error'      => 'Fiil çekimi hatası',
        'word_order_error'       => 'Kelime sırası hatası',
        'spelling_error'         => 'Yazım hatası',
        'capitalization_error'   => 'Büyük harf hatası',
        'plural_error'           => 'Çoğul hatası',
        'vocabulary_recall_error'=> 'Kelime hatırlama hatası',
        'preposition_error'      => 'Edat hatası',
        'auxiliary_error'        => 'Yardımcı fiil hatası',
        'participle_error'       => 'Partizip II hatası',
        'separable_verb_error'   => 'Ayrılabilir fiil hatası',
        'pronoun_error'          => 'Zamir hatası',
        'adjective_ending_error' => 'Sıfat takısı hatası',
        'semantic_error'         => 'Anlam hatası',
        default                  => 'Hata',
    };
}

/* ==================================================================
 * Cevap degerlendirme
 * ================================================================== */

/**
 * Cevabi coklu eksende degerlendirir.
 *
 * @return array{
 *   correct:bool, score:int, breakdown:array<int,array{label:string,ok:bool,note:string}>,
 *   errors:array<int,string>, correct_answer:string, explanation:string, note:string
 * }
 */
function evaluate_answer(array $exercise, string $userAnswer): array
{
    $userAnswer = trim($userAnswer);
    $correctAnswer = (string)$exercise['correct_answer'];
    $type = (string)$exercise['exercise_type'];

    $accepted = [$correctAnswer];
    if (!empty($exercise['accepted_answers'])) {
        $extra = json_decode((string)$exercise['accepted_answers'], true);
        if (is_array($extra)) {
            foreach ($extra as $a) {
                if (is_string($a) && $a !== '') {
                    $accepted[] = $a;
                }
            }
        }
    }

    $result = [
        'correct'        => false,
        'score'          => 0,
        'breakdown'      => [],
        'errors'         => [],
        'correct_answer' => $correctAnswer,
        'explanation'    => (string)($exercise['explanation'] ?? ''),
        'note'           => '',
    ];

    if ($userAnswer === '') {
        $result['breakdown'][] = ['label' => 'Cevap', 'ok' => false, 'note' => 'Boş bırakıldı.'];
        $result['errors'][] = 'semantic_error';
        return $result;
    }

    /* Secmeli tipler: birebir esitlik yeterli. */
    if (in_array($type, ['multiple_choice', 'true_false', 'matching', 'case_choice', 'article', 'scenario_response', 'listening'], true)) {
        $ok = false;
        foreach ($accepted as $a) {
            if (answer_key($a) === answer_key($userAnswer)) {
                $ok = true;
                break;
            }
        }
        $result['correct'] = $ok;
        $result['score'] = $ok ? 100 : 0;
        $result['breakdown'][] = ['label' => 'Seçim', 'ok' => $ok, 'note' => ''];
        if (!$ok) {
            $result['errors'][] = match ($type) {
                'article'     => 'article_error',
                'case_choice' => 'case_error',
                default       => 'semantic_error',
            };
        }
        return $result;
    }

    /* Metin tabanli tipler: coklu eksen analizi. */
    $ukey = answer_key($userAnswer);
    $exactMatch = false;
    $caseInsensitiveMatch = false;
    $matchedAnswer = $correctAnswer;

    foreach ($accepted as $a) {
        if (answer_key_case_sensitive($a) === answer_key_case_sensitive($userAnswer)) {
            $exactMatch = true;
            $matchedAnswer = $a;
            break;
        }
    }
    if (!$exactMatch) {
        foreach ($accepted as $a) {
            if (answer_key($a) === $ukey) {
                $caseInsensitiveMatch = true;
                $matchedAnswer = $a;
                break;
            }
        }
    }

    if ($exactMatch) {
        $result['correct'] = true;
        $result['score'] = 100;
        $result['breakdown'] = evaluate_breakdown_all_ok($type);
        return $result;
    }

    /* Anlam dogru ama yazim/buyuk harf sorunlu. */
    if ($caseInsensitiveMatch) {
        $capProblem = false;
        $umlautProblem = false;

        $expTokens = preg_split('/\s+/u', trim($matchedAnswer)) ?: [];
        $usrTokens = preg_split('/\s+/u', trim($userAnswer)) ?: [];
        if (count($expTokens) === count($usrTokens)) {
            foreach ($expTokens as $i => $exp) {
                $usr = $usrTokens[$i] ?? '';
                if ($exp === $usr) {
                    continue;
                }
                if (mb_strtolower($exp, 'UTF-8') === mb_strtolower($usr, 'UTF-8')) {
                    $capProblem = true;
                } else {
                    $umlautProblem = true;
                }
            }
        } else {
            $umlautProblem = true;
        }

        $result['correct'] = false;
        $result['score'] = 60;
        $result['breakdown'] = [
            ['label' => 'Anlam', 'ok' => true, 'note' => ''],
            ['label' => 'Cümle yapısı', 'ok' => true, 'note' => ''],
            ['label' => 'Yazim', 'ok' => !$umlautProblem, 'note' => $umlautProblem ? 'Umlaut / harf farkı var.' : ''],
            ['label' => 'Büyük harf', 'ok' => !$capProblem, 'note' => $capProblem ? 'Almancada isimler büyük harfle başlar.' : ''],
        ];
        if ($capProblem) {
            $result['errors'][] = 'capitalization_error';
            $result['note'] = 'Almancada isimler her zaman büyük harfle yazılır. Özel isimler de büyük harfle başlar.';
        }
        if ($umlautProblem) {
            $result['errors'][] = 'spelling_error';
            if ($result['note'] === '') {
                $result['note'] = 'Harfler tam dogru degil. Umlaut (ä, ö, ü) ve ß dogru yazilmali.';
            }
        }
        return $result;
    }

    /* Token bazli derin analiz. */
    $expTok = answer_tokens($matchedAnswer);
    $usrTok = answer_tokens($userAnswer);

    $meaningOk = false;
    $orderProblem = false;
    $articleProblem = false;
    $conjugationProblem = false;
    $spellingProblem = false;
    $prepositionProblem = false;
    $missingWord = false;

    $sortedExp = $expTok;
    $sortedUsr = $usrTok;
    sort($sortedExp);
    sort($sortedUsr);
    if ($sortedExp === $sortedUsr && $expTok !== $usrTok) {
        $orderProblem = true;
        $meaningOk = true;
    }

    $articles = ['der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einen', 'einem', 'einer', 'eines'];
    $preps = ['mit', 'nach', 'aus', 'zu', 'von', 'bei', 'seit', 'gegenuber', 'in', 'an', 'auf', 'uber', 'unter', 'vor', 'hinter', 'neben', 'zwischen', 'fuer', 'ohne', 'gegen', 'um', 'durch'];

    if (!$orderProblem) {
        $diffPositions = [];
        $len = max(count($expTok), count($usrTok));
        for ($i = 0; $i < $len; $i++) {
            $e2 = $expTok[$i] ?? null;
            $u2 = $usrTok[$i] ?? null;
            if ($e2 === $u2) {
                continue;
            }
            $diffPositions[] = [$e2, $u2];
        }
        if (count($expTok) !== count($usrTok)) {
            $missingWord = true;
        }
        foreach ($diffPositions as [$e2, $u2]) {
            if ($e2 === null || $u2 === null) {
                continue;
            }
            if (in_array($e2, $articles, true) && in_array($u2, $articles, true)) {
                $articleProblem = true;
                continue;
            }
            if (in_array($e2, $preps, true) || in_array($u2, $preps, true)) {
                $prepositionProblem = true;
                continue;
            }
            $dist = levenshtein_utf8($e2, $u2);
            if ($dist > 0 && $dist <= 2 && mb_strlen($e2) > 3) {
                /* Fiil sonu farkiysa cekim, degilse yazim. */
                $stemE = mb_substr($e2, 0, max(2, mb_strlen($e2) - 2));
                $stemU = mb_substr($u2, 0, max(2, mb_strlen($u2) - 2));
                if ($stemE === $stemU) {
                    $conjugationProblem = true;
                } else {
                    $spellingProblem = true;
                }
            }
        }
        $sameCount = 0;
        foreach ($expTok as $t) {
            if (in_array($t, $usrTok, true)) {
                $sameCount++;
            }
        }
        $meaningOk = count($expTok) > 0 && ($sameCount / count($expTok)) >= 0.6;
    }

    $score = 0;
    if ($meaningOk) {
        $score = 40;
    }
    if (!$orderProblem && !$articleProblem && !$conjugationProblem && !$prepositionProblem && !$spellingProblem && !$missingWord) {
        $score = max($score, 50);
    }

    $result['correct'] = false;
    $result['score'] = $score;
    $result['breakdown'] = [
        ['label' => 'Anlam', 'ok' => $meaningOk, 'note' => $meaningOk ? '' : 'Beklenen anlam karşılanmadı.'],
        ['label' => 'Cümle yapısı', 'ok' => !$orderProblem && !$missingWord, 'note' => $orderProblem ? 'Kelime sırası yanlış.' : ($missingWord ? 'Eksik veya fazla kelime var.' : '')],
        ['label' => 'Artikel', 'ok' => !$articleProblem, 'note' => $articleProblem ? 'Artikel/hâl seçimi yanlış.' : ''],
        ['label' => 'Fiil', 'ok' => !$conjugationProblem, 'note' => $conjugationProblem ? 'Fiil çekimi yanlış.' : ''],
        ['label' => 'Yazim', 'ok' => !$spellingProblem, 'note' => $spellingProblem ? 'Yazım hatası var.' : ''],
    ];

    if ($orderProblem) {
        $result['errors'][] = 'word_order_error';
    }
    if ($articleProblem) {
        $result['errors'][] = in_array($type, ['plural'], true) ? 'plural_error' : 'article_error';
    }
    if ($conjugationProblem) {
        $result['errors'][] = 'conjugation_error';
    }
    if ($prepositionProblem) {
        $result['errors'][] = 'preposition_error';
    }
    if ($spellingProblem) {
        $result['errors'][] = 'spelling_error';
    }
    if ($missingWord && !$orderProblem) {
        $result['errors'][] = 'semantic_error';
    }
    if ($result['errors'] === []) {
        $result['errors'][] = $type === 'plural' ? 'plural_error' : 'vocabulary_recall_error';
    }
    if ($type === 'conjugation' && !in_array('conjugation_error', $result['errors'], true)) {
        $result['errors'][] = 'conjugation_error';
    }
    if ($type === 'plural' && !in_array('plural_error', $result['errors'], true)) {
        $result['errors'][] = 'plural_error';
    }

    return $result;
}

function evaluate_breakdown_all_ok(string $type): array
{
    $items = [
        ['label' => 'Anlam', 'ok' => true, 'note' => ''],
        ['label' => 'Cümle yapısı', 'ok' => true, 'note' => ''],
        ['label' => 'Yazim', 'ok' => true, 'note' => ''],
        ['label' => 'Büyük harf', 'ok' => true, 'note' => ''],
    ];
    if ($type === 'conjugation') {
        $items[] = ['label' => 'Fiil çekimi', 'ok' => true, 'note' => ''];
    }
    if ($type === 'plural') {
        $items[] = ['label' => 'Cogul', 'ok' => true, 'note' => ''];
    }
    return $items;
}

/* ==================================================================
 * Mastery satirlari
 * ================================================================== */

function skill_mastery(int $userId, int $skillId): array
{
    $row = db_row('SELECT * FROM user_skill_mastery WHERE user_id = ? AND skill_id = ?', [$userId, $skillId]);
    if ($row !== null) {
        return $row;
    }
    db_exec(
        'INSERT IGNORE INTO user_skill_mastery (user_id, skill_id, status, next_review_at)
         VALUES (?, ?, "introduced", UTC_TIMESTAMP())',
        [$userId, $skillId]
    );
    return db_row('SELECT * FROM user_skill_mastery WHERE user_id = ? AND skill_id = ?', [$userId, $skillId]) ?? [];
}

function vocab_mastery(int $userId, int $vocabId): array
{
    $row = db_row('SELECT * FROM user_vocabulary_mastery WHERE user_id = ? AND vocabulary_id = ?', [$userId, $vocabId]);
    if ($row !== null) {
        return $row;
    }
    db_exec(
        'INSERT IGNORE INTO user_vocabulary_mastery (user_id, vocabulary_id, status, next_review_at)
         VALUES (?, ?, "introduced", UTC_TIMESTAMP())',
        [$userId, $vocabId]
    );
    return db_row('SELECT * FROM user_vocabulary_mastery WHERE user_id = ? AND vocabulary_id = ?', [$userId, $vocabId]) ?? [];
}

/** Son sonuc dizisini (1/0) gunceller, en fazla 20 kayit tutar. */
function push_recent(string $recent, bool $correct): string
{
    $recent .= $correct ? '1' : '0';
    return substr($recent, -20);
}

function recent_accuracy(string $recent, int $window = 8): float
{
    $tail = substr($recent, -$window);
    $len = strlen($tail);
    if ($len === 0) {
        return 0.0;
    }
    return substr_count($tail, '1') / $len;
}

function has_recent_failure(string $recent, int $window = 4): bool
{
    return str_contains(substr($recent, -$window), '0');
}

/* ==================================================================
 * SRS
 * ================================================================== */

/**
 * Yeni tekrar araligini (dakika) ve ease faktorunu hesaplar.
 * @return array{interval:int, ease:float, repetitions:int, lapses:int}
 */
function srs_next(array $row, bool $correct, int $quality = 4): array
{
    $ease = (float)($row['ease_factor'] ?? 2.5);
    $interval = (int)($row['interval_minutes'] ?? 10);
    $reps = (int)($row['repetitions'] ?? 0);
    $lapses = (int)($row['lapses'] ?? 0);

    if (!$correct) {
        $lapses++;
        $reps = 0;
        $ease = max(1.3, $ease - 0.20);
        $interval = 10;                     /* ayni oturumda geri gelir */
        return ['interval' => $interval, 'ease' => $ease, 'repetitions' => $reps, 'lapses' => $lapses];
    }

    $reps++;
    $ease = min(3.0, $ease + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02)));
    $ease = max(1.3, $ease);

    $interval = match (true) {
        $reps <= 1 => 30,          /* 30 dakika */
        $reps === 2 => 8 * 60,     /* ayni gun icinde */
        $reps === 3 => 24 * 60,    /* ertesi gun */
        $reps === 4 => 3 * 24 * 60,
        $reps === 5 => 7 * 24 * 60,
        default => (int)round(max($interval, 7 * 24 * 60) * $ease),
    };
    $interval = min($interval, 180 * 24 * 60); /* en fazla 180 gun */

    return ['interval' => $interval, 'ease' => $ease, 'repetitions' => $reps, 'lapses' => $lapses];
}

/* ==================================================================
 * Mastery hesabi
 * ================================================================== */

/**
 * Skill mastery yuzdesi. Tek dogru cevap asla mastered yapmaz.
 */
function compute_skill_mastery(array $m, array $skill): array
{
    $attempts = (int)$m['attempts'];
    if ($attempts === 0) {
        return ['score' => 0, 'status' => 'introduced', 'missing' => ['Bu konuda henüz test edilmedin.']];
    }

    $recent = (string)$m['recent_results'];
    $acc = recent_accuracy($recent, 8);
    $firstAcc = (int)$m['first_attempt_total'] > 0
        ? (int)$m['first_attempt_correct'] / (int)$m['first_attempt_total']
        : $acc;

    $requiresProduction = (int)($skill['requires_production'] ?? 1) === 1;
    $requiresSpelling = (int)($skill['requires_spelling'] ?? 0) === 1;

    /* 1) Dogruluk sinyali (0-45) */
    $accuracyPart = ($acc * 0.65 + $firstAcc * 0.35) * 45;

    /* 2) Yetenek kapsami (0-35) */
    $needed = ['recognition_ok' => 1, 'recall_ok' => 2, 'delayed_ok' => 1];
    if ($requiresProduction) {
        $needed['production_ok'] = 1;
    }
    if ($requiresSpelling) {
        $needed['spelling_ok'] = 1;
    }
    $coverSum = 0.0;
    foreach ($needed as $field => $req) {
        $have = (int)($m[$field] ?? 0);
        $coverSum += min(1.0, $have / max(1, $req));
    }
    $coveragePart = ($coverSum / count($needed)) * 35;

    /* 3) Tutarlilik ve baglam cesitliligi (0-20) */
    $consec = min(4, (int)$m['consecutive_correct']) / 4;
    $variants = min(3, (int)$m['context_variants']) / 3;
    $consistencyPart = ($consec * 0.6 + $variants * 0.4) * 20;

    $score = (int)round($accuracyPart + $coveragePart + $consistencyPart);

    /* Tavanlar: eksik kanit varken yuksek skor verilmez. */
    $missing = [];
    if ((int)$m['recognition_ok'] < 1) {
        $score = min($score, 45);
        $missing[] = 'Tanıyarak doğru cevaplama';
    }
    if ((int)$m['recall_ok'] < 2) {
        $score = min($score, 70);
        $missing[] = 'Aktif hatırlama (' . (int)$m['recall_ok'] . '/2)';
    }
    if ($requiresProduction && (int)$m['production_ok'] < 1) {
        $score = min($score, 75);
        $missing[] = 'Kendi cümleni üretme';
    }
    if ($requiresSpelling && (int)$m['spelling_ok'] < 1) {
        $score = min($score, 80);
        $missing[] = 'Doğru yazım';
    }
    if ((int)$m['delayed_ok'] < 1) {
        $score = min($score, 85);
        $missing[] = 'Gecikmeli hatırlama (en az 1 gün sonra)';
    }
    if ((int)$m['context_variants'] < 2) {
        $score = min($score, 88);
        $missing[] = 'Farklı bağlamda doğru kullanım';
    }
    if (has_recent_failure($recent, 3)) {
        $score = min($score, 74);
        $missing[] = 'Son denemelerde hatasız seri';
    }
    if ((int)$m['consecutive_correct'] < 3) {
        $score = min($score, 88);
        $missing[] = 'Üst üste 3 doğru (' . (int)$m['consecutive_correct'] . '/3)';
    }

    $threshold = (int)($skill['mastery_threshold'] ?? MASTERY_THRESHOLD);
    $status = match (true) {
        $score >= $threshold && $missing === [] => 'mastered',
        $score >= MASTERY_STRONG => 'strong',
        $score >= MASTERY_WEAK => 'learning',
        default => 'weak',
    };
    if ($status !== 'mastered' && (int)$m['lapses'] >= 3 && $score < MASTERY_STRONG) {
        $status = 'weak';
    }

    return ['score' => max(0, min(100, $score)), 'status' => $status, 'missing' => $missing];
}

function compute_vocab_mastery(array $m, array $vocab): array
{
    $attempts = (int)$m['attempts'];
    if ($attempts === 0) {
        return ['score' => 0, 'status' => 'introduced', 'missing' => ['Bu kelime henüz test edilmedi.']];
    }
    $recent = (string)$m['recent_results'];
    $acc = recent_accuracy($recent, 8);

    $isNoun = ($vocab['part_of_speech'] ?? '') === 'noun';
    $hasArticle = !empty($vocab['article']);
    $hasPlural = !empty($vocab['plural']);

    $needed = ['de_tr_ok' => 1, 'tr_de_ok' => 2, 'spelling_ok' => 1, 'delayed_ok' => 1];
    if ($isNoun && $hasArticle) {
        $needed['article_ok'] = 1;
    }
    if ($isNoun && $hasPlural) {
        $needed['plural_ok'] = 1;
    }

    $coverSum = 0.0;
    foreach ($needed as $field => $req) {
        $coverSum += min(1.0, (int)($m[$field] ?? 0) / max(1, $req));
    }
    $coveragePart = ($coverSum / count($needed)) * 45;
    $accuracyPart = $acc * 40;
    $consistencyPart = (min(3, (int)$m['consecutive_correct']) / 3) * 15;

    $score = (int)round($coveragePart + $accuracyPart + $consistencyPart);

    $missing = [];
    if ((int)$m['de_tr_ok'] < 1) {
        $score = min($score, 50);
        $missing[] = 'Almanca → Türkçe tanıma';
    }
    if ((int)$m['tr_de_ok'] < 2) {
        $score = min($score, 72);
        $missing[] = 'Türkçe → Almanca hatırlama (' . (int)$m['tr_de_ok'] . '/2)';
    }
    if ($isNoun && $hasArticle && (int)$m['article_ok'] < 1) {
        $score = min($score, 74);
        $missing[] = 'Doğru artikel';
    }
    if ($isNoun && $hasPlural && (int)$m['plural_ok'] < 1) {
        $score = min($score, 80);
        $missing[] = 'Doğru çoğul';
    }
    if ((int)$m['spelling_ok'] < 1) {
        $score = min($score, 84);
        $missing[] = 'Doğru yazım';
    }
    if ((int)$m['delayed_ok'] < 1) {
        $score = min($score, 88);
        $missing[] = 'Gecikmeli hatırlama';
    }
    if (has_recent_failure($recent, 3)) {
        $score = min($score, 70);
        $missing[] = 'Son denemelerde hatasız seri';
    }
    if ((int)$m['consecutive_correct'] < 3) {
        $score = min($score, 88);
        $missing[] = 'Üst üste 3 doğru (' . (int)$m['consecutive_correct'] . '/3)';
    }

    $status = match (true) {
        $score >= MASTERY_THRESHOLD && $missing === [] => 'mastered',
        $score >= MASTERY_STRONG => 'strong',
        $score >= MASTERY_WEAK => 'learning',
        default => 'weak',
    };

    return ['score' => max(0, min(100, $score)), 'status' => $status, 'missing' => $missing];
}

/* ==================================================================
 * Deneme kaydi (tum ogrenme yollarinin tek girisi)
 * ================================================================== */

/**
 * Bir cevabi kaydeder; mastery ve SRS'i gunceller.
 *
 * @return array Degerlendirme sonucu + guncel mastery bilgisi.
 */
function record_attempt(
    int $userId,
    array $exercise,
    string $userAnswer,
    ?int $sessionId = null,
    string $source = 'web',
    ?int $responseMs = null
): array {
    $eval = evaluate_answer($exercise, $userAnswer);
    $correct = $eval['correct'];
    $mode = (string)$exercise['mode'];

    $skillId = $exercise['skill_id'] !== null ? (int)$exercise['skill_id'] : null;
    $vocabId = $exercise['vocabulary_id'] !== null ? (int)$exercise['vocabulary_id'] : null;
    $lessonId = $exercise['lesson_id'] !== null ? (int)$exercise['lesson_id'] : null;

    /* Gecikmeli hatırlama: son calismadan 20+ saat gectiyse. */
    $isDelayed = ($mode === 'delayed');

    $attemptId = db_insert(
        'INSERT INTO exercise_attempts
            (user_id, exercise_id, session_id, skill_id, vocabulary_id, lesson_id, user_answer, is_correct, score, mode, response_ms, source, evaluation)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $userId,
            (int)$exercise['id'],
            $sessionId,
            $skillId,
            $vocabId,
            $lessonId,
            mb_substr($userAnswer, 0, 600),
            $correct ? 1 : 0,
            $eval['score'],
            $mode,
            $responseMs,
            $source,
            json_encode(['breakdown' => $eval['breakdown'], 'errors' => $eval['errors']], JSON_UNESCAPED_UNICODE),
        ]
    );

    foreach (array_unique($eval['errors']) as $code) {
        $catId = error_category_id($code);
        if ($catId !== null) {
            db_exec(
                'INSERT INTO answer_error_categories (attempt_id, user_id, error_category_id, skill_id, detail) VALUES (?, ?, ?, ?, ?)',
                [$attemptId, $userId, $catId, $skillId, mb_substr(error_category_label($code), 0, 400)]
            );
        }
    }

    $masteryInfo = null;

    if ($skillId !== null) {
        $masteryInfo = update_skill_mastery($userId, $skillId, $exercise, $correct, $isDelayed);
    }
    if ($vocabId !== null) {
        $vinfo = update_vocab_mastery($userId, $vocabId, $exercise, $correct, $isDelayed);
        if ($masteryInfo === null) {
            $masteryInfo = $vinfo;
        }
    }
    /* Egzersize bagli ek skill'ler */
    foreach (db_all('SELECT skill_id FROM exercise_skill_map WHERE exercise_id = ?', [(int)$exercise['id']]) as $r) {
        if ((int)$r['skill_id'] !== $skillId) {
            update_skill_mastery($userId, (int)$r['skill_id'], $exercise, $correct, $isDelayed);
        }
    }

    $eval['mastery'] = $masteryInfo;
    $eval['attempt_id'] = $attemptId;
    return $eval;
}

function update_skill_mastery(int $userId, int $skillId, array $exercise, bool $correct, bool $isDelayed): array
{
    $skill = db_row('SELECT * FROM skills WHERE id = ?', [$skillId]);
    if ($skill === null) {
        return ['score' => 0, 'status' => 'introduced', 'missing' => []];
    }
    $m = skill_mastery($userId, $skillId);
    if ($m === []) {
        return ['score' => 0, 'status' => 'introduced', 'missing' => []];
    }

    $mode = (string)$exercise['mode'];
    $variantGroup = (string)($exercise['variant_group'] ?? '');

    $recent = push_recent((string)$m['recent_results'], $correct);
    $attempts = (int)$m['attempts'] + 1;
    $correctCount = (int)$m['correct'] + ($correct ? 1 : 0);
    $incorrect = (int)$m['incorrect'] + ($correct ? 0 : 1);
    $consec = $correct ? (int)$m['consecutive_correct'] + 1 : 0;

    /* Ilk deneme dogrulugu: bu egzersiz daha once denendi mi? */
    $seenBefore = (int)db_value(
        'SELECT COUNT(*) FROM exercise_attempts WHERE user_id = ? AND exercise_id = ? AND id < (SELECT MAX(id) FROM exercise_attempts WHERE user_id = ?)',
        [$userId, (int)$exercise['id'], $userId],
        0
    );
    $firstTotal = (int)$m['first_attempt_total'];
    $firstCorrect = (int)$m['first_attempt_correct'];
    if ($seenBefore === 0) {
        $firstTotal++;
        if ($correct) {
            $firstCorrect++;
        }
    }

    $recognition = (int)$m['recognition_ok'];
    $recall = (int)$m['recall_ok'];
    $production = (int)$m['production_ok'];
    $spelling = (int)$m['spelling_ok'];
    $delayed = (int)$m['delayed_ok'];
    $variants = (int)$m['context_variants'];

    if ($correct) {
        switch ($mode) {
            case 'recognition': $recognition++; break;
            case 'recall':      $recall++; break;
            case 'production':  $production++; $recall++; break;
            case 'spelling':    $spelling++; break;
            case 'application': $production++; break;
            case 'delayed':     $delayed++; $recall++; break;
        }
        if ($isDelayed) {
            $delayed++;
        }
        if ($variantGroup !== '') {
            $seenVariant = (int)db_value(
                'SELECT COUNT(DISTINCT e.variant_group) FROM exercise_attempts a
                 JOIN exercises e ON e.id = a.exercise_id
                 WHERE a.user_id = ? AND a.skill_id = ? AND a.is_correct = 1 AND e.variant_group IS NOT NULL',
                [$userId, $skillId],
                0
            );
            $variants = max($variants, $seenVariant);
        }
    } else {
        /* Yanlista kanit sayaclari bir kademe geriler. */
        switch ($mode) {
            case 'recall':
            case 'delayed':     $recall = max(0, $recall - 1); break;
            case 'production':
            case 'application': $production = max(0, $production - 1); break;
            case 'spelling':    $spelling = max(0, $spelling - 1); break;
        }
    }

    $srs = srs_next($m, $correct);

    $tmp = $m;
    $tmp['recent_results'] = $recent;
    $tmp['attempts'] = $attempts;
    $tmp['correct'] = $correctCount;
    $tmp['incorrect'] = $incorrect;
    $tmp['consecutive_correct'] = $consec;
    $tmp['first_attempt_total'] = $firstTotal;
    $tmp['first_attempt_correct'] = $firstCorrect;
    $tmp['recognition_ok'] = $recognition;
    $tmp['recall_ok'] = $recall;
    $tmp['production_ok'] = $production;
    $tmp['spelling_ok'] = $spelling;
    $tmp['delayed_ok'] = $delayed;
    $tmp['context_variants'] = $variants;
    $tmp['lapses'] = $srs['lapses'];

    $calc = compute_skill_mastery($tmp, $skill);

    db_exec(
        'UPDATE user_skill_mastery SET
            status = ?, mastery_score = ?, attempts = ?, correct = ?, incorrect = ?,
            first_attempt_correct = ?, first_attempt_total = ?, recent_results = ?, consecutive_correct = ?,
            recognition_ok = ?, recall_ok = ?, production_ok = ?, spelling_ok = ?, delayed_ok = ?, context_variants = ?,
            lapses = ?, repetitions = ?, ease_factor = ?, interval_minutes = ?,
            next_review_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE),
            last_practiced_at = UTC_TIMESTAMP(),
            mastered_at = CASE WHEN ? = "mastered" AND mastered_at IS NULL THEN UTC_TIMESTAMP() ELSE mastered_at END
         WHERE user_id = ? AND skill_id = ?',
        [
            $calc['status'], $calc['score'], $attempts, $correctCount, $incorrect,
            $firstCorrect, $firstTotal, $recent, $consec,
            $recognition, $recall, $production, $spelling, $delayed, $variants,
            $srs['lapses'], $srs['repetitions'], $srs['ease'], $srs['interval'],
            $srs['interval'], $calc['status'], $userId, $skillId,
        ]
    );

    $calc['skill'] = $skill;
    return $calc;
}

function update_vocab_mastery(int $userId, int $vocabId, array $exercise, bool $correct, bool $isDelayed): array
{
    $vocab = db_row('SELECT * FROM vocabulary WHERE id = ?', [$vocabId]);
    if ($vocab === null) {
        return ['score' => 0, 'status' => 'introduced', 'missing' => []];
    }
    $m = vocab_mastery($userId, $vocabId);
    if ($m === []) {
        return ['score' => 0, 'status' => 'introduced', 'missing' => []];
    }

    $type = (string)$exercise['exercise_type'];
    $mode = (string)$exercise['mode'];

    $recent = push_recent((string)$m['recent_results'], $correct);
    $attempts = (int)$m['attempts'] + 1;
    $correctCount = (int)$m['correct'] + ($correct ? 1 : 0);
    $incorrect = (int)$m['incorrect'] + ($correct ? 0 : 1);
    $consec = $correct ? (int)$m['consecutive_correct'] + 1 : 0;

    $deTr = (int)$m['de_tr_ok'];
    $trDe = (int)$m['tr_de_ok'];
    $article = (int)$m['article_ok'];
    $plural = (int)$m['plural_ok'];
    $spelling = (int)$m['spelling_ok'];
    $production = (int)$m['production_ok'];
    $delayed = (int)$m['delayed_ok'];

    if ($correct) {
        switch ($type) {
            case 'translation_de_tr': $deTr++; break;
            case 'translation_tr_de': $trDe++; $spelling++; break;
            case 'article':           $article++; break;
            case 'plural':            $plural++; break;
            case 'text_input':        $trDe++; $spelling++; break;
            case 'fill_blank':        $production++; break;
            case 'multiple_choice':   $deTr++; break;
        }
        if ($mode === 'production' || $mode === 'application') {
            $production++;
        }
        if ($mode === 'delayed' || $isDelayed) {
            $delayed++;
        }
    } else {
        switch ($type) {
            case 'translation_tr_de':
            case 'text_input':  $trDe = max(0, $trDe - 1); break;
            case 'article':     $article = max(0, $article - 1); break;
            case 'plural':      $plural = max(0, $plural - 1); break;
        }
    }

    $srs = srs_next($m, $correct);

    $tmp = $m;
    $tmp['recent_results'] = $recent;
    $tmp['attempts'] = $attempts;
    $tmp['consecutive_correct'] = $consec;
    $tmp['de_tr_ok'] = $deTr;
    $tmp['tr_de_ok'] = $trDe;
    $tmp['article_ok'] = $article;
    $tmp['plural_ok'] = $plural;
    $tmp['spelling_ok'] = $spelling;
    $tmp['production_ok'] = $production;
    $tmp['delayed_ok'] = $delayed;

    $calc = compute_vocab_mastery($tmp, $vocab);

    db_exec(
        'UPDATE user_vocabulary_mastery SET
            status = ?, mastery_score = ?, attempts = ?, correct = ?, incorrect = ?,
            recent_results = ?, consecutive_correct = ?,
            de_tr_ok = ?, tr_de_ok = ?, article_ok = ?, plural_ok = ?, spelling_ok = ?, production_ok = ?, delayed_ok = ?,
            lapses = ?, repetitions = ?, ease_factor = ?, interval_minutes = ?,
            next_review_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE),
            last_practiced_at = UTC_TIMESTAMP(),
            mastered_at = CASE WHEN ? = "mastered" AND mastered_at IS NULL THEN UTC_TIMESTAMP() ELSE mastered_at END
         WHERE user_id = ? AND vocabulary_id = ?',
        [
            $calc['status'], $calc['score'], $attempts, $correctCount, $incorrect,
            $recent, $consec,
            $deTr, $trDe, $article, $plural, $spelling, $production, $delayed,
            $srs['lapses'], $srs['repetitions'], $srs['ease'], $srs['interval'],
            $srs['interval'], $calc['status'], $userId, $vocabId,
        ]
    );

    $calc['vocabulary'] = $vocab;
    return $calc;
}

/* ==================================================================
 * Ders kilidi (sunucu tarafi otorite)
 * ================================================================== */

/**
 * @return array{unlocked:bool, requirements:array<int,array>, missing_count:int}
 */
function lesson_lock_state(int $userId, int $lessonId): array
{
    $reqs = db_all(
        'SELECT lp.required_mastery, s.id AS skill_id, s.code, s.name, s.mastery_threshold,
                COALESCE(m.mastery_score, 0) AS score, COALESCE(m.status, "introduced") AS status
         FROM lesson_prerequisites lp
         JOIN skills s ON s.id = lp.skill_id
         LEFT JOIN user_skill_mastery m ON m.skill_id = s.id AND m.user_id = ?
         WHERE lp.lesson_id = ?
         ORDER BY s.sort_order, s.id',
        [$userId, $lessonId]
    );

    $missing = 0;
    $out = [];
    foreach ($reqs as $r) {
        $need = (int)$r['required_mastery'];
        $have = (int)$r['score'];
        $ok = $have >= $need;
        if (!$ok) {
            $missing++;
        }
        $out[] = [
            'skill_id' => (int)$r['skill_id'],
            'code'     => (string)$r['code'],
            'name'     => (string)$r['name'],
            'required' => $need,
            'score'    => $have,
            'status'   => (string)$r['status'],
            'ok'       => $ok,
        ];
    }

    return ['unlocked' => $missing === 0, 'requirements' => $out, 'missing_count' => $missing];
}

/** Kullanicinin derse erisim hakki var mi? URL ile atlanamaz. */
function can_access_lesson(int $userId, array $lesson): bool
{
    if ((int)$lesson['is_active'] !== 1) {
        return false;
    }
    return lesson_lock_state($userId, (int)$lesson['id'])['unlocked'];
}

/* ==================================================================
 * Ders tamamlama
 * ================================================================== */

function lesson_progress(int $userId, int $lessonId): array
{
    $row = db_row('SELECT * FROM user_lesson_progress WHERE user_id = ? AND lesson_id = ?', [$userId, $lessonId]);
    if ($row !== null) {
        return $row;
    }
    db_exec(
        'INSERT IGNORE INTO user_lesson_progress (user_id, lesson_id, status, started_at) VALUES (?, ?, "not_started", NULL)',
        [$userId, $lessonId]
    );
    return db_row('SELECT * FROM user_lesson_progress WHERE user_id = ? AND lesson_id = ?', [$userId, $lessonId]) ?? [];
}

/**
 * Dersin tamamlanip tamamlanmadigini birincil skill mastery'lerine bakarak belirler.
 * "Ileri" tusuna basmak dersi bitirmez.
 */
function lesson_completion_state(int $userId, int $lessonId): array
{
    $lesson = db_row('SELECT * FROM lessons WHERE id = ?', [$lessonId]);
    if ($lesson === null) {
        return ['complete' => false, 'skills' => [], 'average' => 0];
    }
    $threshold = (int)$lesson['completion_threshold'];

    $skills = db_all(
        'SELECT s.id, s.code, s.name, s.mastery_threshold,
                COALESCE(m.mastery_score, 0) AS score, COALESCE(m.status, "introduced") AS status,
                COALESCE(m.recognition_ok,0) recognition_ok, COALESCE(m.recall_ok,0) recall_ok,
                COALESCE(m.production_ok,0) production_ok, COALESCE(m.delayed_ok,0) delayed_ok
         FROM lesson_skills ls
         JOIN skills s ON s.id = ls.skill_id
         LEFT JOIN user_skill_mastery m ON m.skill_id = s.id AND m.user_id = ?
         WHERE ls.lesson_id = ? AND ls.is_primary = 1
         ORDER BY s.sort_order, s.id',
        [$userId, $lessonId]
    );

    $vocabRows = db_all(
        'SELECT COUNT(*) total, SUM(CASE WHEN COALESCE(m.mastery_score,0) >= ? THEN 1 ELSE 0 END) ok
         FROM lesson_vocabulary lv
         LEFT JOIN user_vocabulary_mastery m ON m.vocabulary_id = lv.vocabulary_id AND m.user_id = ?
         WHERE lv.lesson_id = ?',
        [$threshold, $userId, $lessonId]
    );
    $vocabTotal = (int)($vocabRows[0]['total'] ?? 0);
    $vocabOk = (int)($vocabRows[0]['ok'] ?? 0);

    $sum = 0;
    $complete = true;
    foreach ($skills as $s) {
        $sum += (int)$s['score'];
        if ((int)$s['score'] < $threshold) {
            $complete = false;
        }
    }
    if ($vocabTotal > 0 && $vocabOk < (int)ceil($vocabTotal * 0.8)) {
        $complete = false;
    }
    if ($skills === [] && $vocabTotal === 0) {
        $complete = false;
    }
    $avg = $skills === [] ? ($vocabTotal > 0 ? pct($vocabOk, $vocabTotal) : 0) : (int)round($sum / count($skills));

    return [
        'complete'     => $complete,
        'skills'       => $skills,
        'average'      => $avg,
        'vocab_total'  => $vocabTotal,
        'vocab_ok'     => $vocabOk,
        'threshold'    => $threshold,
    ];
}

function mark_lesson_completed_if_ready(int $userId, int $lessonId): bool
{
    $state = lesson_completion_state($userId, $lessonId);
    if (!$state['complete']) {
        db_exec(
            'UPDATE user_lesson_progress SET status = IF(status = "completed", "completed", "in_progress"), best_score = GREATEST(best_score, ?) WHERE user_id = ? AND lesson_id = ?',
            [$state['average'], $userId, $lessonId]
        );
        return false;
    }
    db_exec(
        'UPDATE user_lesson_progress SET status = "completed", completed_at = COALESCE(completed_at, UTC_TIMESTAMP()), best_score = GREATEST(best_score, ?) WHERE user_id = ? AND lesson_id = ?',
        [$state['average'], $userId, $lessonId]
    );
    return true;
}

/* ==================================================================
 * Tekrar kuyrugu (SRS)
 * ================================================================== */

function due_review_counts(int $userId): array
{
    $vocab = db_row(
        'SELECT
            SUM(CASE WHEN next_review_at <= UTC_TIMESTAMP() THEN 1 ELSE 0 END) due,
            SUM(CASE WHEN next_review_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY) THEN 1 ELSE 0 END) overdue,
            SUM(CASE WHEN status IN ("weak","learning") THEN 1 ELSE 0 END) weak
         FROM user_vocabulary_mastery WHERE user_id = ? AND status <> "mastered"',
        [$userId]
    ) ?? [];
    $skill = db_row(
        'SELECT
            SUM(CASE WHEN next_review_at <= UTC_TIMESTAMP() THEN 1 ELSE 0 END) due,
            SUM(CASE WHEN next_review_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY) THEN 1 ELSE 0 END) overdue,
            SUM(CASE WHEN status IN ("weak","learning") THEN 1 ELSE 0 END) weak
         FROM user_skill_mastery WHERE user_id = ? AND status <> "mastered"',
        [$userId]
    ) ?? [];

    $due = (int)($vocab['due'] ?? 0) + (int)($skill['due'] ?? 0);
    $overdue = (int)($vocab['overdue'] ?? 0) + (int)($skill['overdue'] ?? 0);
    $weak = (int)($vocab['weak'] ?? 0) + (int)($skill['weak'] ?? 0);

    return [
        'due'         => $due,
        'overdue'     => $overdue,
        'weak'        => $weak,
        'today'       => max(0, $due - $overdue),
        'est_minutes' => (int)max(1, ceil($due * 0.45)),
    ];
}

/** Tekrar oturumu icin egzersiz listesi uretir. */
function build_review_items(int $userId, int $limit = 20): array
{
    $items = [];

    $vocabDue = db_all(
        'SELECT m.vocabulary_id, m.interval_minutes, m.status, m.tr_de_ok, m.article_ok, m.plural_ok, m.de_tr_ok
         FROM user_vocabulary_mastery m
         WHERE m.user_id = ? AND m.next_review_at <= UTC_TIMESTAMP() AND m.status <> "mastered"
         ORDER BY m.next_review_at ASC LIMIT ?',
        [$userId, $limit]
    );
    foreach ($vocabDue as $v) {
        $mode = ((int)$v['interval_minutes'] >= 1440) ? 'delayed' : null;
        $ex = pick_exercise_for_vocabulary($userId, (int)$v['vocabulary_id'], $v, $mode);
        if ($ex !== null) {
            $items[] = $ex;
        }
    }

    $remaining = $limit - count($items);
    if ($remaining > 0) {
        $skillDue = db_all(
            'SELECT m.skill_id, m.interval_minutes
             FROM user_skill_mastery m
             WHERE m.user_id = ? AND m.next_review_at <= UTC_TIMESTAMP() AND m.status <> "mastered"
             ORDER BY m.next_review_at ASC LIMIT ?',
            [$userId, $remaining]
        );
        foreach ($skillDue as $s) {
            $mode = ((int)$s['interval_minutes'] >= 1440) ? 'delayed' : null;
            $ex = pick_exercise_for_skill($userId, (int)$s['skill_id'], $mode);
            if ($ex !== null) {
                $items[] = $ex;
            }
        }
    }

    return $items;
}

/**
 * Bir kelime icin en uygun egzersizi secer (anti-cheat: son gorulen tekrar edilmez).
 */
function pick_exercise_for_vocabulary(int $userId, int $vocabId, array $m = [], ?string $forceMode = null): ?array
{
    /* Hangi kanit eksikse o tur oncelikli. */
    $preferred = [];
    if ((int)($m['de_tr_ok'] ?? 0) < 1) {
        $preferred[] = 'translation_de_tr';
    }
    if ((int)($m['tr_de_ok'] ?? 0) < 2) {
        $preferred[] = 'translation_tr_de';
    }
    if ((int)($m['article_ok'] ?? 0) < 1) {
        $preferred[] = 'article';
    }
    if ((int)($m['plural_ok'] ?? 0) < 1) {
        $preferred[] = 'plural';
    }
    $preferred[] = 'fill_blank';
    $preferred[] = 'multiple_choice';

    foreach ($preferred as $type) {
        $row = db_row(
            'SELECT e.* FROM exercises e
             WHERE e.vocabulary_id = ? AND e.exercise_type = ? AND e.is_active = 1
               AND NOT EXISTS (
                   SELECT 1 FROM exercise_attempts a
                   WHERE a.user_id = ? AND a.exercise_id = e.id AND a.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 20 MINUTE)
               )
             ORDER BY RAND() LIMIT 1',
            [$vocabId, $type, $userId]
        );
        if ($row !== null) {
            if ($forceMode !== null) {
                $row['mode'] = $forceMode;
            }
            return hydrate_exercise($row);
        }
    }

    $row = db_row(
        'SELECT e.* FROM exercises e WHERE e.vocabulary_id = ? AND e.is_active = 1 ORDER BY RAND() LIMIT 1',
        [$vocabId]
    );
    if ($row === null) {
        return null;
    }
    if ($forceMode !== null) {
        $row['mode'] = $forceMode;
    }
    return hydrate_exercise($row);
}

function pick_exercise_for_skill(int $userId, int $skillId, ?string $forceMode = null, array $excludeIds = []): ?array
{
    $m = db_row('SELECT * FROM user_skill_mastery WHERE user_id = ? AND skill_id = ?', [$userId, $skillId]);
    $modeOrder = ['recognition', 'recall', 'production', 'application', 'spelling'];
    if ($m !== null) {
        $modeOrder = [];
        if ((int)$m['recognition_ok'] < 1) {
            $modeOrder[] = 'recognition';
        }
        if ((int)$m['recall_ok'] < 2) {
            $modeOrder[] = 'recall';
        }
        if ((int)$m['production_ok'] < 1) {
            $modeOrder[] = 'production';
        }
        $modeOrder[] = 'application';
        $modeOrder[] = 'recall';
        $modeOrder[] = 'recognition';
    }

    $notIn = '';
    $params = [];
    if ($excludeIds !== []) {
        $notIn = ' AND e.id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')';
    }

    foreach (array_unique($modeOrder) as $mode) {
        $p = array_merge([$skillId, $mode, $userId], $excludeIds);
        $row = db_row(
            'SELECT e.* FROM exercises e
             WHERE e.skill_id = ? AND e.mode = ? AND e.is_active = 1
               AND NOT EXISTS (
                   SELECT 1 FROM exercise_attempts a
                   WHERE a.user_id = ? AND a.exercise_id = e.id AND a.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 20 MINUTE)
               )' . $notIn . '
             ORDER BY RAND() LIMIT 1',
            $p
        );
        if ($row !== null) {
            if ($forceMode !== null) {
                $row['mode'] = $forceMode;
            }
            return hydrate_exercise($row);
        }
    }

    $p = array_merge([$skillId], $excludeIds);
    $row = db_row(
        'SELECT e.* FROM exercises e WHERE e.skill_id = ? AND e.is_active = 1' . $notIn . ' ORDER BY RAND() LIMIT 1',
        $p
    );
    if ($row === null) {
        return null;
    }
    if ($forceMode !== null) {
        $row['mode'] = $forceMode;
    }
    return hydrate_exercise($row);
}

/** Egzersize secenekleri ve ilgili kelimeyi ekler; secenek sirasi karistirilir. */
function hydrate_exercise(array $exercise): array
{
    $options = db_all(
        'SELECT id, option_text, is_correct, feedback FROM exercise_options WHERE exercise_id = ? ORDER BY sort_order, id',
        [(int)$exercise['id']]
    );
    if ($options !== []) {
        shuffle($options);
    }
    $exercise['options'] = $options;
    $exercise['vocabulary'] = null;
    if (!empty($exercise['vocabulary_id'])) {
        $exercise['vocabulary'] = db_row('SELECT * FROM vocabulary WHERE id = ?', [(int)$exercise['vocabulary_id']]);
    }
    return $exercise;
}

/* ==================================================================
 * Zayif alanlar
 * ================================================================== */

function weak_error_areas(int $userId, int $limit = 8): array
{
    return db_all(
        'SELECT ec.code, ec.name, ec.advice, COUNT(*) AS error_count
         FROM answer_error_categories aec
         JOIN error_categories ec ON ec.id = aec.error_category_id
         WHERE aec.user_id = ? AND aec.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 DAY)
         GROUP BY ec.id, ec.code, ec.name, ec.advice
         ORDER BY error_count DESC LIMIT ?',
        [$userId, $limit]
    );
}

function weak_skills(int $userId, int $limit = 8): array
{
    return db_all(
        'SELECT s.id, s.code, s.name, s.cefr_level, m.mastery_score, m.status, m.incorrect
         FROM user_skill_mastery m
         JOIN skills s ON s.id = m.skill_id
         WHERE m.user_id = ? AND m.status IN ("weak","learning","overdue") AND m.attempts > 0
         ORDER BY m.mastery_score ASC, m.incorrect DESC LIMIT ?',
        [$userId, $limit]
    );
}

/* ==================================================================
 * XP ve seri
 * ================================================================== */

function award_activity(int $userId, string $type, ?int $refId, string $title, int $xp = 0, int $seconds = 0, array $meta = []): void
{
    db_exec(
        'INSERT INTO user_activity (user_id, activity_type, ref_id, title, xp, seconds, meta) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$userId, $type, $refId, mb_substr($title, 0, 240), $xp, $seconds, $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE)]
    );
    if ($xp > 0 || $seconds > 0) {
        db_exec('UPDATE users SET total_xp = total_xp + ?, total_study_seconds = total_study_seconds + ? WHERE id = ?', [$xp, $seconds, $userId]);
    }
}

/**
 * Seri yalnizca gercek calisma ile kazanilir (en az 5 dogru cevaplanmis soru
 * veya tamamlanmis bir ders/tekrar oturumu).
 */
function update_streak(int $userId): void
{
    $user = db_row('SELECT timezone, last_study_date, streak_count, longest_streak FROM users WHERE id = ?', [$userId]);
    if ($user === null) {
        return;
    }
    $tz = user_timezone($user);
    $today = local_date($tz);

    $todayAttempts = (int)db_value(
        'SELECT COUNT(*) FROM exercise_attempts WHERE user_id = ? AND is_correct = 1 AND created_at >= ?',
        [$userId, gmdate('Y-m-d H:i:s', strtotime($today . ' 00:00:00 ' . $tz) ?: time())],
        0
    );
    if ($todayAttempts < 5) {
        return;
    }
    if ((string)$user['last_study_date'] === $today) {
        return;
    }
    $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
    $streak = ((string)$user['last_study_date'] === $yesterday) ? (int)$user['streak_count'] + 1 : 1;
    $longest = max((int)$user['longest_streak'], $streak);
    db_exec('UPDATE users SET streak_count = ?, longest_streak = ?, last_study_date = ? WHERE id = ?', [$streak, $longest, $today, $userId]);
}
