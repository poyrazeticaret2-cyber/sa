<?php
/**
 * AlmancaPro - Opsiyonel AI ogretmen katmani.
 *
 * Sistem AI OLMADAN tam calisir. Admin API bilgilerini girerse
 * gelismis ogretmen devreye girer. API anahtari asla tarayiciya gonderilmez.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function ai_is_enabled(): bool
{
    return setting_bool('ai_enabled', false)
        && setting('ai_endpoint') !== null
        && setting('ai_api_key') !== null
        && setting('ai_model') !== null;
}

/** Kullanicinin gunluk AI kotasi dolmus mu? */
function ai_quota_left(int $userId): int
{
    $limit = setting_int('ai_daily_limit', 40);
    if ($limit <= 0) {
        return 0;
    }
    $used = (int)db_value(
        'SELECT COUNT(*) FROM answers a JOIN questions q ON q.id = a.question_id
         WHERE q.user_id = ? AND a.source = "ai" AND a.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)',
        [$userId], 0
    );
    return max(0, $limit - $used);
}

/**
 * Kullanicinin ogrenme baglamini hazirlar.
 */
function ai_build_context(int $userId, array $user): string
{
    $lines = [];
    $lines[] = 'Öğrencinin adı: ' . (string)$user['name'];
    $lines[] = 'CEFR seviyesi: ' . (string)$user['cefr_level'];

    $lesson = db_row(
        'SELECT l.title, l.cefr_level FROM user_lesson_progress p
         JOIN lessons l ON l.id = p.lesson_id
         WHERE p.user_id = ? AND p.status = "in_progress" ORDER BY p.updated_at DESC LIMIT 1',
        [$userId]
    );
    if ($lesson !== null) {
        $lines[] = 'Şu anda çalıştığı ders: ' . (string)$lesson['title'] . ' (' . (string)$lesson['cefr_level'] . ')';
    }

    $mastered = db_all(
        'SELECT s.name FROM user_skill_mastery m JOIN skills s ON s.id = m.skill_id
         WHERE m.user_id = ? AND m.status = "mastered" ORDER BY m.mastered_at DESC LIMIT 12',
        [$userId]
    );
    if ($mastered !== []) {
        $lines[] = 'Öğrendiği konular: ' . implode(', ', array_column($mastered, 'name'));
    }

    $weak = db_all(
        'SELECT s.name FROM user_skill_mastery m JOIN skills s ON s.id = m.skill_id
         WHERE m.user_id = ? AND m.status IN ("weak","learning") ORDER BY m.mastery_score ASC LIMIT 8',
        [$userId]
    );
    if ($weak !== []) {
        $lines[] = 'Zorlandığı konular: ' . implode(', ', array_column($weak, 'name'));
    }

    $errors = db_all(
        'SELECT ec.name, COUNT(*) c FROM answer_error_categories aec
         JOIN error_categories ec ON ec.id = aec.error_category_id
         WHERE aec.user_id = ? AND aec.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
         GROUP BY ec.id, ec.name ORDER BY c DESC LIMIT 5',
        [$userId]
    );
    if ($errors !== []) {
        $parts = [];
        foreach ($errors as $err) {
            $parts[] = (string)$err['name'] . ' (' . (int)$err['c'] . ')';
        }
        $lines[] = 'Son 30 günde en sık yaptığı hatalar: ' . implode(', ', $parts);
    }

    return implode("\n", $lines);
}

/**
 * AI ogretmene soru sorar.
 * @return array{ok:bool, answer:string, error:string}
 */
function ai_ask(int $userId, array $user, string $question): array
{
    if (!ai_is_enabled()) {
        return ['ok' => false, 'answer' => '', 'error' => 'ai_disabled'];
    }
    if (ai_quota_left($userId) <= 0) {
        return ['ok' => false, 'answer' => '', 'error' => 'quota_exceeded'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'answer' => '', 'error' => 'curl_missing'];
    }

    $endpoint = (string)setting('ai_endpoint');
    $apiKey = (string)setting('ai_api_key');
    $model = (string)setting('ai_model');
    $systemPrompt = (string)setting('ai_system_prompt', '');
    $maxTokens = setting_int('ai_max_tokens', 900);

    $payload = [
        'model' => $model,
        'max_tokens' => $maxTokens,
        'temperature' => 0.3,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt . "\n\nÖĞRENCİ BAĞLAMI:\n" . ai_build_context($userId, $user)],
            ['role' => 'user', 'content' => mb_substr($question, 0, 2000)],
        ],
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $status < 200 || $status >= 300) {
        app_log('AI isteği başarısız (HTTP ' . $status . '): ' . scrub_secrets($curlError !== '' ? $curlError : substr((string)$raw, 0, 200)));
        setting_set('ai_last_error', 'HTTP ' . $status . ' · ' . now_utc());
        return ['ok' => false, 'answer' => '', 'error' => 'request_failed'];
    }

    $data = json_decode((string)$raw, true);
    $answer = ai_extract_answer(is_array($data) ? $data : []);
    if ($answer === '') {
        setting_set('ai_last_error', 'Boş yanıt · ' . now_utc());
        return ['ok' => false, 'answer' => '', 'error' => 'empty_response'];
    }

    setting_set('ai_last_ok', now_utc());
    return ['ok' => true, 'answer' => $answer, 'error' => ''];
}

/** Farkli saglayici bicimlerinden metni cikarir. */
function ai_extract_answer(array $data): string
{
    /* OpenAI uyumlu */
    if (isset($data['choices'][0]['message']['content']) && is_string($data['choices'][0]['message']['content'])) {
        return trim($data['choices'][0]['message']['content']);
    }
    /* Anthropic Messages API */
    if (isset($data['content']) && is_array($data['content'])) {
        $parts = [];
        foreach ($data['content'] as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $parts[] = (string)$block['text'];
            }
        }
        if ($parts !== []) {
            return trim(implode("\n", $parts));
        }
    }
    /* Duz metin alanlari */
    foreach (['output_text', 'text', 'answer'] as $key) {
        if (isset($data[$key]) && is_string($data[$key])) {
            return trim($data[$key]);
        }
    }
    return '';
}

/**
 * AI kapaliyken bilgi tabani + dilbilgisi verisinden cevap uretir.
 * @return array{ok:bool, answer:string, source:string}
 */
function ai_fallback_answer(int $userId, array $user, string $question): array
{
    $q = mb_strtolower(tr_to_ascii($question), 'UTF-8');
    $words = array_values(array_filter(preg_split('/[^a-z0-9äöüß]+/u', $q) ?: [], static fn ($w) => mb_strlen($w) >= 3));

    /* 1) Bilgi tabani */
    $best = null;
    $bestScore = 0;
    foreach (db_all('SELECT * FROM knowledge_base ORDER BY sort_order') as $kb) {
        $haystack = mb_strtolower(tr_to_ascii((string)$kb['keywords'] . ' ' . (string)$kb['question']), 'UTF-8');
        $score = 0;
        foreach ($words as $w) {
            if (str_contains($haystack, $w)) {
                $score += 2;
            }
        }
        if (str_contains($haystack, $q)) {
            $score += 5;
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $kb;
        }
    }
    if ($best !== null && $bestScore >= 4) {
        return ['ok' => true, 'answer' => (string)$best['answer'], 'source' => 'knowledge_base'];
    }

    /* 2) Dilbilgisi konularinda ara */
    if ($words !== []) {
        $like = '%' . implode('%', array_slice($words, 0, 3)) . '%';
        $topic = db_row(
            'SELECT * FROM grammar_topics WHERE is_active = 1 AND (keywords LIKE ? OR title LIKE ?) ORDER BY sort_order LIMIT 1',
            [$like, '%' . $words[0] . '%']
        );
        if ($topic !== null) {
            $parts = [];
            $parts[] = $topic['title'] . "\n";
            foreach ([
                'what_is_it' => 'Bu nedir?',
                'why_used' => 'Neden kullanılır?',
                'tr_difference' => 'Türkçeden farkı',
                'rule' => 'Kural',
                'common_mistake' => 'En sık yapılan hata',
            ] as $field => $label) {
                $val = trim((string)($topic[$field] ?? ''));
                if ($val !== '') {
                    $parts[] = $label . ":\n" . $val;
                }
            }
            $examples = db_all('SELECT de, tr FROM grammar_examples WHERE grammar_topic_id = ? ORDER BY sort_order LIMIT 3', [(int)$topic['id']]);
            if ($examples !== []) {
                $ex = [];
                foreach ($examples as $e2) {
                    $ex[] = $e2['de'] . ' — ' . $e2['tr'];
                }
                $parts[] = "Örnekler:\n" . implode("\n", $ex);
            }
            return ['ok' => true, 'answer' => implode("\n\n", $parts), 'source' => 'knowledge_base'];
        }
    }

    /* 3) Kelime sorusu mu? */
    foreach ($words as $w) {
        $vocab = db_row(
            'SELECT * FROM vocabulary WHERE is_active = 1 AND (normalized_german = ? OR turkish LIKE ?) LIMIT 1',
            [de_normalize($w), $w . '%']
        );
        if ($vocab !== null) {
            $lines = [];
            $lines[] = vocab_headword($vocab) . ' = ' . (string)$vocab['turkish'];
            if (!empty($vocab['article'])) {
                $lines[] = 'Artikel: ' . (string)$vocab['article'] . ' · Çoğul: ' . (string)($vocab['plural'] ?? '—');
            }
            if ((string)$vocab['part_of_speech'] === 'verb') {
                $lines[] = 'Fiil biçimleri: ' . trim(
                    (string)$vocab['german'] . ' – ' . (string)($vocab['third_person'] ?? '') . ' – '
                    . (string)($vocab['preterite'] ?? '') . ' – ' . (string)($vocab['auxiliary'] ?? '') . ' ' . (string)($vocab['participle_ii'] ?? '')
                );
            }
            if (!empty($vocab['required_preposition']) || !empty($vocab['requires_case'])) {
                $lines[] = 'Yapı: ' . trim((string)($vocab['required_preposition'] ?? '') . ' ' . (!empty($vocab['requires_case']) ? '+ ' . ucfirst((string)$vocab['requires_case']) : ''));
            }
            if (!empty($vocab['example_de'])) {
                $lines[] = 'Örnek: ' . (string)$vocab['example_de'] . ' — ' . (string)($vocab['example_tr'] ?? '');
            }
            if (!empty($vocab['usage_notes'])) {
                $lines[] = 'Not: ' . (string)$vocab['usage_notes'];
            }
            return ['ok' => true, 'answer' => implode("\n", $lines), 'source' => 'knowledge_base'];
        }
    }

    return [
        'ok' => false,
        'answer' => "Bu soruya doğrudan bir cevap bulamadım.\n\n"
            . "Sorunu biraz daha açık yazmayı dene: hangi cümlede, hangi kelimede takıldığını yaz.\n"
            . "Örnek: \"Neden 'Ich helfe dem Kollegen' diyoruz, 'den' değil mi?\"\n\n"
            . "Sorun burada kayıtlı kaldı; öğretmen ekibi cevapladığında bu sayfada göreceksin.",
        'source' => 'knowledge_base',
    ];
}
