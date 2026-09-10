<?php
/**
 * AlmancaPro - Icerik yukleyici (idempotent).
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/learning.php';

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const ALMANCAPRO_CONTENT_VERSION = '1.0.0';

/* ==================================================================
 * Hata kategorileri
 * ================================================================== */
function seed_error_categories(): int
{
    require_once __DIR__ . '/content-skills.php';
    $n = 0;
    foreach (almancapro_error_categories() as [$code, $name, $desc, $advice]) {
        db_exec(
            'INSERT INTO error_categories (code, name, description, advice) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), advice = VALUES(advice)',
            [$code, $name, $desc, $advice]
        );
        $n++;
    }
    return $n;
}

/* ==================================================================
 * Skills
 * ================================================================== */
function seed_skills(): int
{
    require_once __DIR__ . '/content-skills.php';
    $n = 0;
    foreach (almancapro_skills() as $s) {
        db_exec(
            'INSERT INTO skills (code, name, category, cefr_level, mastery_threshold, is_critical, requires_production, requires_spelling, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category), cefr_level = VALUES(cefr_level),
                mastery_threshold = VALUES(mastery_threshold), is_critical = VALUES(is_critical),
                requires_production = VALUES(requires_production), requires_spelling = VALUES(requires_spelling),
                sort_order = VALUES(sort_order)',
            [$s['code'], $s['name'], $s['category'], $s['cefr_level'], $s['mastery_threshold'],
             $s['is_critical'], $s['requires_production'], $s['requires_spelling'], $s['sort_order']]
        );
        $n++;
    }
    return $n;
}

function skill_id_map(bool $fresh = false): array
{
    static $map = null;
    if ($map === null || $fresh) {
        $map = [];
        foreach (db_all('SELECT id, code FROM skills') as $r) {
            $map[$r['code']] = (int)$r['id'];
        }
    }
    return $map;
}

/* ==================================================================
 * Kelime hazinesi
 * ================================================================== */
function seed_vocabulary(?string $onlyLevel = null): int
{
    require_once __DIR__ . '/content-vocab-a0.php';
    require_once __DIR__ . '/content-vocab-a1.php';
    require_once __DIR__ . '/content-vocab-a2.php';
    require_once __DIR__ . '/content-vocab-b1.php';

    $sets = [
        'A0' => almancapro_vocab_a0(),
        'A1' => almancapro_vocab_a1(),
        'A2' => almancapro_vocab_a2(),
        'B1' => almancapro_vocab_b1(),
    ];

    $n = 0;
    foreach ($sets as $level => $rows) {
        if ($onlyLevel !== null && $level !== $onlyLevel) {
            continue;
        }
        foreach ($rows as $v) {
            $german = (string)$v['g'];
            $pos = (string)($v['pos'] ?? 'noun');
            $article = $v['a'] ?? null;
            $gender = $article === null ? null : ['der' => 'm', 'die' => 'f', 'das' => 'n'][$article];

            db_exec(
                'INSERT INTO vocabulary
                    (german, normalized_german, article, gender, plural, turkish, part_of_speech, pronunciation, ipa,
                     cefr_level, topic, example_de, example_tr, usage_notes, memory_tip, similar_word_note,
                     separable_prefix, auxiliary, preterite, participle_ii, third_person, is_irregular, is_reflexive,
                     requires_case, required_preposition)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                    german = VALUES(german), article = VALUES(article), gender = VALUES(gender), plural = VALUES(plural),
                    turkish = VALUES(turkish), part_of_speech = VALUES(part_of_speech),
                    pronunciation = VALUES(pronunciation), ipa = VALUES(ipa), cefr_level = VALUES(cefr_level),
                    topic = VALUES(topic), example_de = VALUES(example_de), example_tr = VALUES(example_tr),
                    usage_notes = VALUES(usage_notes), memory_tip = VALUES(memory_tip), similar_word_note = VALUES(similar_word_note),
                    separable_prefix = VALUES(separable_prefix), auxiliary = VALUES(auxiliary), preterite = VALUES(preterite),
                    participle_ii = VALUES(participle_ii), third_person = VALUES(third_person),
                    is_irregular = VALUES(is_irregular), is_reflexive = VALUES(is_reflexive),
                    requires_case = VALUES(requires_case), required_preposition = VALUES(required_preposition)',
                [
                    $german,
                    de_normalize($german),
                    $article,
                    $gender,
                    $v['pl'] ?? null,
                    (string)$v['tr'],
                    $pos,
                    $v['pr'] ?? null,
                    $v['ipa'] ?? null,
                    (string)($v['lvl'] ?? $level),
                    $v['top'] ?? null,
                    $v['ex'] ?? null,
                    $v['ext'] ?? null,
                    $v['note'] ?? null,
                    $v['tip'] ?? null,
                    $v['sim'] ?? null,
                    $v['sep'] ?? null,
                    $v['aux'] ?? null,
                    $v['pret'] ?? null,
                    $v['p2'] ?? null,
                    $v['p3'] ?? null,
                    !empty($v['irr']) ? 1 : 0,
                    !empty($v['refl']) ? 1 : 0,
                    $v['case'] ?? null,
                    $v['prep'] ?? null,
                ]
            );
            $n++;

            if (!empty($v['ex']) && !empty($v['ext'])) {
                $vid = (int)db_value('SELECT id FROM vocabulary WHERE normalized_german = ? AND part_of_speech = ?', [de_normalize($german), $pos], 0);
                if ($vid > 0) {
                    $exists = (int)db_value('SELECT COUNT(*) FROM vocabulary_examples WHERE vocabulary_id = ? AND de = ?', [$vid, $v['ex']], 0);
                    if ($exists === 0) {
                        db_exec('INSERT INTO vocabulary_examples (vocabulary_id, de, tr, sort_order) VALUES (?, ?, ?, 0)', [$vid, $v['ex'], $v['ext']]);
                    }
                }
            }
        }
    }
    return $n;
}

function vocab_id_map(bool $fresh = false): array
{
    static $map = null;
    if ($map === null || $fresh) {
        $map = [];
        foreach (db_all('SELECT id, german, normalized_german FROM vocabulary') as $r) {
            $map[$r['german']] = (int)$r['id'];
            if (!isset($map[$r['normalized_german']])) {
                $map[$r['normalized_german']] = (int)$r['id'];
            }
        }
    }
    return $map;
}

/* ==================================================================
 * Mufredat: modul, ders, bolum, egzersiz
 * ================================================================== */
/**
 * Mufredati yukler.
 *
 * $onlyLevel verilirse yalnizca o seviye islenir; sira sayaclari yine de
 * butun mufredat uzerinden ilerler, boylece parcali kurulumda ders sirasi
 * tek seferlik kurulumla birebir ayni olur.
 */
function seed_curriculum(bool $refresh, ?string $onlyLevel = null): array
{
    require_once __DIR__ . '/content-a0.php';
    require_once __DIR__ . '/content-a1.php';
    require_once __DIR__ . '/content-a2.php';
    require_once __DIR__ . '/content-b1.php';

    $curriculum = array_merge(
        almancapro_curriculum_a0(),
        almancapro_curriculum_a1(),
        almancapro_curriculum_a2(),
        almancapro_curriculum_b1()
    );

    $skills = skill_id_map(true);
    $vocab = vocab_id_map(true);

    $moduleOrder = 0;
    $lessonOrder = 0;
    $counts = ['modules' => 0, 'lessons' => 0, 'sections' => 0, 'exercises' => 0];

    /* Once tum dersleri olustur (onkosullar icin id gerekiyor). */
    foreach ($curriculum as $module) {
        if ($onlyLevel !== null && (string)$module['level'] !== $onlyLevel) {
            /* Bu seviye simdi islenmiyor ama sira numaralari kaymamali. */
            $moduleOrder++;
            $lessonOrder += count($module['lessons']);
            continue;
        }
        db_exec(
            'INSERT INTO modules (slug, cefr_level, title, description, sort_order)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE cefr_level = VALUES(cefr_level), title = VALUES(title),
                description = VALUES(description), sort_order = VALUES(sort_order)',
            [$module['slug'], $module['level'], $module['title'], $module['description'] ?? null, $moduleOrder++]
        );
        $moduleId = (int)db_value('SELECT id FROM modules WHERE slug = ?', [$module['slug']], 0);
        $counts['modules']++;

        foreach ($module['lessons'] as $lesson) {
            db_exec(
                'INSERT INTO lessons (module_id, slug, title, cefr_level, lesson_type, objective, summary, estimated_minutes, completion_threshold, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE module_id = VALUES(module_id), title = VALUES(title), cefr_level = VALUES(cefr_level),
                    lesson_type = VALUES(lesson_type), objective = VALUES(objective), summary = VALUES(summary),
                    estimated_minutes = VALUES(estimated_minutes), sort_order = VALUES(sort_order)',
                [
                    $moduleId, $lesson['slug'], $lesson['title'], $module['level'],
                    $lesson['type'] ?? 'grammar', $lesson['objective'] ?? null, $lesson['summary'] ?? null,
                    (int)($lesson['minutes'] ?? 12), 80, $lessonOrder++,
                ]
            );
            $counts['lessons']++;
        }
    }

    $lessonIds = [];
    foreach (db_all('SELECT id, slug FROM lessons') as $r) {
        $lessonIds[$r['slug']] = (int)$r['id'];
    }

    /* Ders detaylari */
    foreach ($curriculum as $module) {
        if ($onlyLevel !== null && (string)$module['level'] !== $onlyLevel) {
            continue;
        }
        foreach ($module['lessons'] as $lesson) {
            $lessonId = $lessonIds[$lesson['slug']] ?? 0;
            if ($lessonId === 0) {
                continue;
            }

            $hasDetails = (int)db_value('SELECT COUNT(*) FROM lesson_sections WHERE lesson_id = ?', [$lessonId], 0) > 0;

            if ($refresh) {
                db_exec('DELETE FROM lesson_sections WHERE lesson_id = ?', [$lessonId]);
                db_exec('DELETE FROM exercises WHERE lesson_id = ? AND is_generated = 0', [$lessonId]);
                db_exec('DELETE FROM lesson_skills WHERE lesson_id = ?', [$lessonId]);
                db_exec('DELETE FROM lesson_prerequisites WHERE lesson_id = ?', [$lessonId]);
                db_exec('DELETE FROM lesson_vocabulary WHERE lesson_id = ?', [$lessonId]);
                $hasDetails = false;
            }

            /* Baglantilar her zaman guvenle tazelenir (INSERT IGNORE / ON DUPLICATE). */
            foreach ($lesson['skills'] ?? [] as $code) {
                if (isset($skills[$code])) {
                    db_exec('INSERT IGNORE INTO lesson_skills (lesson_id, skill_id, is_primary) VALUES (?, ?, 1)', [$lessonId, $skills[$code]]);
                }
            }
            foreach ($lesson['prereq'] ?? [] as $code => $threshold) {
                if (isset($skills[$code])) {
                    db_exec(
                        'INSERT INTO lesson_prerequisites (lesson_id, skill_id, required_mastery) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE required_mastery = VALUES(required_mastery)',
                        [$lessonId, $skills[$code], (int)$threshold]
                    );
                }
            }
            $vOrder = 0;
            foreach ($lesson['vocab'] ?? [] as $word) {
                $vid = $vocab[$word] ?? ($vocab[de_normalize($word)] ?? null);
                if ($vid !== null) {
                    db_exec('INSERT IGNORE INTO lesson_vocabulary (lesson_id, vocabulary_id, sort_order) VALUES (?, ?, ?)', [$lessonId, $vid, $vOrder++]);
                }
            }

            /* Bolum ve alistirmalar yalnizca yoksa (veya tazeleme istendiyse) yazilir. */
            if ($hasDetails) {
                continue;
            }

            /* Bolumler */
            $order = 0;
            foreach ($lesson['sections'] ?? [] as $sec) {
                $type = (string)($sec['t'] ?? 'explanation');
                $type = match ($type) {
                    'how_recognize' => 'explanation',
                    default => $type,
                };
                $allowed = ['explanation','why','tr_contrast','rule','example','exception','common_mistake','memory_tip','usage','table','dialogue','pronunciation'];
                if (!in_array($type, $allowed, true)) {
                    $type = 'explanation';
                }
                db_exec(
                    'INSERT INTO lesson_sections (lesson_id, section_type, heading, body, example_de, example_tr, highlight, is_collapsible, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$lessonId, $type, $sec['h'] ?? null, $sec['b'] ?? null, $sec['de'] ?? null, $sec['tr'] ?? null,
                     $sec['hl'] ?? null, !empty($sec['c']) ? 1 : 0, $order++]
                );
                $counts['sections']++;
            }

            /* Egzersizler */
            $eOrder = 0;
            foreach ($lesson['exercises'] ?? [] as $ex) {
                $skillId = isset($ex['skill'], $skills[$ex['skill']]) ? $skills[$ex['skill']] : null;
                $accepted = !empty($ex['alt']) ? json_encode($ex['alt'], JSON_UNESCAPED_UNICODE) : null;

                $exId = db_insert(
                    'INSERT INTO exercises
                        (lesson_id, skill_id, exercise_type, mode, prompt, context, correct_answer, accepted_answers,
                         explanation, memory_hint, cefr_level, variant_group, is_generated, sort_order)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,?)',
                    [
                        $lessonId, $skillId,
                        (string)($ex['type'] ?? 'multiple_choice'),
                        (string)($ex['mode'] ?? 'recognition'),
                        (string)$ex['q'],
                        $ex['ctx'] ?? null,
                        (string)$ex['a'],
                        $accepted,
                        $ex['exp'] ?? null,
                        $ex['hint'] ?? null,
                        $module['level'],
                        $ex['vg'] ?? null,
                        $eOrder++,
                    ]
                );
                $counts['exercises']++;

                if ($skillId !== null) {
                    db_exec('INSERT IGNORE INTO exercise_skill_map (exercise_id, skill_id, weight) VALUES (?, ?, 1)', [$exId, $skillId]);
                }

                if (!empty($ex['opts']) && is_array($ex['opts'])) {
                    $oOrder = 0;
                    foreach ($ex['opts'] as $opt) {
                        db_exec(
                            'INSERT INTO exercise_options (exercise_id, option_text, is_correct, sort_order) VALUES (?, ?, ?, ?)',
                            [$exId, (string)$opt, answer_key((string)$opt) === answer_key((string)$ex['a']) ? 1 : 0, $oOrder++]
                        );
                    }
                }
            }
        }
    }

    return $counts;
}

/* ==================================================================
 * Dilbilgisi kutuphanesi (ders bolumlerinden uretilir)
 * ================================================================== */
function seed_grammar_topics(bool $refresh): int
{
    $lessons = db_all(
        "SELECT id, slug, title, cefr_level, sort_order FROM lessons WHERE lesson_type IN ('grammar','pronunciation') AND is_active = 1 ORDER BY sort_order"
    );
    $n = 0;
    foreach ($lessons as $lesson) {
        $lessonId = (int)$lesson['id'];
        $sections = db_all('SELECT * FROM lesson_sections WHERE lesson_id = ? ORDER BY sort_order', [$lessonId]);
        if ($sections === []) {
            continue;
        }

        $fields = [
            'what_is_it' => [], 'why_used' => [], 'tr_difference' => [], 'sentence_role' => [],
            'how_to_recognize' => [], 'rule' => [], 'exceptions' => [], 'common_mistake' => [], 'memory_tip' => [],
        ];
        $examples = [];
        foreach ($sections as $s) {
            $body = trim((string)$s['body']);
            $head = trim((string)$s['heading']);
            $text = ($head !== '' ? $head . "\n" : '') . $body;
            switch ($s['section_type']) {
                case 'explanation':   $fields['what_is_it'][] = $text; break;
                case 'why':           $fields['why_used'][] = $text; break;
                case 'tr_contrast':   $fields['tr_difference'][] = $text; break;
                case 'rule':
                case 'table':         $fields['rule'][] = $text; break;
                case 'exception':     $fields['exceptions'][] = $text; break;
                case 'common_mistake':$fields['common_mistake'][] = $text; break;
                case 'memory_tip':    $fields['memory_tip'][] = $text; break;
                case 'usage':         $fields['sentence_role'][] = $text; break;
                case 'example':
                case 'dialogue':
                    if (!empty($s['example_de'])) {
                        $examples[] = ['de' => (string)$s['example_de'], 'tr' => (string)($s['example_tr'] ?? ''), 'note' => $body !== '' ? $body : null];
                    }
                    break;
            }
        }

        $skillId = db_value('SELECT skill_id FROM lesson_skills WHERE lesson_id = ? AND is_primary = 1 ORDER BY skill_id LIMIT 1', [$lessonId]);
        $keywords = mb_strtolower($lesson['title'] . ' ' . $lesson['slug']);

        db_exec(
            'INSERT INTO grammar_topics
                (skill_id, lesson_id, slug, title, cefr_level, what_is_it, why_used, tr_difference, sentence_role,
                 how_to_recognize, rule, exceptions, common_mistake, memory_tip, keywords, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                skill_id = VALUES(skill_id), lesson_id = VALUES(lesson_id), title = VALUES(title), cefr_level = VALUES(cefr_level),
                what_is_it = VALUES(what_is_it), why_used = VALUES(why_used), tr_difference = VALUES(tr_difference),
                sentence_role = VALUES(sentence_role), how_to_recognize = VALUES(how_to_recognize), rule = VALUES(rule),
                exceptions = VALUES(exceptions), common_mistake = VALUES(common_mistake), memory_tip = VALUES(memory_tip),
                keywords = VALUES(keywords), sort_order = VALUES(sort_order)',
            [
                $skillId !== null ? (int)$skillId : null,
                $lessonId,
                'gt-' . $lesson['slug'],
                $lesson['title'],
                $lesson['cefr_level'],
                implode("\n\n", $fields['what_is_it']) ?: null,
                implode("\n\n", $fields['why_used']) ?: null,
                implode("\n\n", $fields['tr_difference']) ?: null,
                implode("\n\n", $fields['sentence_role']) ?: null,
                implode("\n\n", $fields['how_to_recognize']) ?: null,
                implode("\n\n", $fields['rule']) ?: null,
                implode("\n\n", $fields['exceptions']) ?: null,
                implode("\n\n", $fields['common_mistake']) ?: null,
                implode("\n\n", $fields['memory_tip']) ?: null,
                mb_substr($keywords, 0, 500),
                (int)$lesson['sort_order'],
            ]
        );

        $topicId = (int)db_value('SELECT id FROM grammar_topics WHERE slug = ?', ['gt-' . $lesson['slug']], 0);
        if ($topicId > 0) {
            if ($refresh) {
                db_exec('DELETE FROM grammar_examples WHERE grammar_topic_id = ?', [$topicId]);
            }
            $o = 0;
            foreach ($examples as $ex) {
                $exists = (int)db_value('SELECT COUNT(*) FROM grammar_examples WHERE grammar_topic_id = ? AND de = ?', [$topicId, $ex['de']], 0);
                if ($exists === 0) {
                    db_exec('INSERT INTO grammar_examples (grammar_topic_id, de, tr, note, sort_order) VALUES (?, ?, ?, ?, ?)',
                        [$topicId, mb_substr($ex['de'], 0, 400), mb_substr($ex['tr'], 0, 400), $ex['note'] !== null ? mb_substr($ex['note'], 0, 400) : null, $o++]);
                }
            }
        }
        $n++;
    }
    return $n;
}

/* ==================================================================
 * Kelimelerden otomatik egzersiz uretimi
 * ================================================================== */
function seed_generated_exercises(int $limit = 0): int
{
    $sql = 'SELECT v.* FROM vocabulary v
            WHERE v.is_active = 1
              AND NOT EXISTS (SELECT 1 FROM exercises e WHERE e.vocabulary_id = v.id AND e.is_generated = 1)
            ORDER BY v.id';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int)$limit;
    }
    $rows = db_all($sql);
    if ($rows === []) {
        return 0;
    }

    $pool = db_all('SELECT id, german, article, plural, turkish, part_of_speech, cefr_level, topic FROM vocabulary WHERE is_active = 1');
    $byPos = [];
    foreach ($pool as $p) {
        $byPos[$p['part_of_speech']][] = $p;
    }

    $created = 0;
    foreach ($rows as $v) {
        $created += generate_vocab_exercises($v, $byPos);
    }
    return $created;
}

/** Bir kelime icin gercek egzersizler uretir. */
function generate_vocab_exercises(array $v, array $byPos): int
{
    $vid = (int)$v['id'];
    $german = (string)$v['german'];
    $turkish = (string)$v['turkish'];
    $article = $v['article'] ?? null;
    $plural = $v['plural'] ?? null;
    $pos = (string)$v['part_of_speech'];
    $level = (string)$v['cefr_level'];
    $headword = trim(((string)$article) . ' ' . $german);
    $headword = trim($headword);
    $full = $article !== null && $plural !== null ? $headword . ' – ' . $plural : $headword;

    $created = 0;

    /* 1) Almanca → Türkçe tanıma (coktan secmeli) */
    $distractors = pick_distractors($byPos, $pos, $turkish, 3, (string)($v['topic'] ?? ''), $level);
    if (count($distractors) === 3) {
        $exId = db_insert(
            'INSERT INTO exercises (vocabulary_id, exercise_type, mode, prompt, correct_answer, explanation, cefr_level, variant_group, is_generated)
             VALUES (?, "translation_de_tr", "recognition", ?, ?, ?, ?, ?, 1)',
            [
                $vid,
                '„' . $full . '" ne anlama gelir?',
                $turkish,
                'Doğru karşılık: ' . $full . ' = ' . $turkish,
                $level,
                'vocab-de-tr',
            ]
        );
        $opts = array_merge([$turkish], $distractors);
        shuffle($opts);
        $o = 0;
        foreach ($opts as $opt) {
            db_exec('INSERT INTO exercise_options (exercise_id, option_text, is_correct, sort_order) VALUES (?, ?, ?, ?)',
                [$exId, $opt, $opt === $turkish ? 1 : 0, $o++]);
        }
        $created++;
    }

    /* 2) Turkce → Almanca aktif hatirlama (metin girisi) */
    $answer = $article !== null ? $article . ' ' . $german : $german;
    $alts = [$german];
    $asciiAnswer = strtr($answer, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    if ($asciiAnswer !== $answer) {
        $alts[] = $asciiAnswer;
        $alts[] = strtr($german, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    }
    $promptTr = $article !== null
        ? '„' . $turkish . '" kelimesini artikeliyle birlikte Almanca yaz.'
        : '„' . $turkish . '" kelimesini Almanca yaz.';
    db_insert(
        'INSERT INTO exercises (vocabulary_id, exercise_type, mode, prompt, correct_answer, accepted_answers, explanation, cefr_level, variant_group, is_generated)
         VALUES (?, "translation_tr_de", "recall", ?, ?, ?, ?, ?, ?, 1)',
        [
            $vid,
            $promptTr,
            $answer,
            json_encode(array_values(array_unique($alts)), JSON_UNESCAPED_UNICODE),
            'Doğrusu: ' . $full,
            $level,
            'vocab-tr-de',
        ]
    );
    $created++;

    /* 3) Artikel */
    if ($article !== null && $pos === 'noun') {
        $exId = db_insert(
            'INSERT INTO exercises (vocabulary_id, exercise_type, mode, prompt, correct_answer, explanation, memory_hint, cefr_level, variant_group, is_generated)
             VALUES (?, "article", "recall", ?, ?, ?, ?, ?, ?, 1)',
            [
                $vid,
                '„' . $german . '" kelimesinin artikeli nedir?',
                $article,
                'Doğrusu: ' . $full,
                'İsimleri her zaman artikeliyle öğren.',
                $level,
                'vocab-article',
            ]
        );
        $o = 0;
        foreach (['der', 'die', 'das'] as $opt) {
            db_exec('INSERT INTO exercise_options (exercise_id, option_text, is_correct, sort_order) VALUES (?, ?, ?, ?)',
                [$exId, $opt, $opt === $article ? 1 : 0, $o++]);
        }
        $created++;
    }

    /* 4) Cogul */
    if ($plural !== null && $pos === 'noun' && $article !== null) {
        $wrong = plural_distractors($german, (string)$plural);
        if (count($wrong) >= 3) {
            $exId = db_insert(
                'INSERT INTO exercises (vocabulary_id, exercise_type, mode, prompt, correct_answer, explanation, cefr_level, variant_group, is_generated)
                 VALUES (?, "plural", "recall", ?, ?, ?, ?, ?, 1)',
                [
                    $vid,
                    '„' . $headword . '" kelimesinin çoğulu nedir?',
                    (string)$plural,
                    'Doğrusu: ' . $full,
                    $level,
                    'vocab-plural',
                ]
            );
            $opts = array_merge([(string)$plural], array_slice($wrong, 0, 3));
            shuffle($opts);
            $o = 0;
            foreach ($opts as $opt) {
                db_exec('INSERT INTO exercise_options (exercise_id, option_text, is_correct, sort_order) VALUES (?, ?, ?, ?)',
                    [$exId, $opt, $opt === (string)$plural ? 1 : 0, $o++]);
            }
            $created++;
        }
    }

    /* 5) Ornek cumleden bosluk doldurma (uretim) */
    if (!empty($v['example_de']) && mb_strpos((string)$v['example_de'], $german) !== false) {
        $sentence = (string)$v['example_de'];
        $blanked = str_replace($german, '______', $sentence);
        if ($blanked !== $sentence) {
            db_insert(
                'INSERT INTO exercises (vocabulary_id, exercise_type, mode, prompt, context, correct_answer, accepted_answers, explanation, cefr_level, variant_group, is_generated)
                 VALUES (?, "fill_blank", "production", ?, ?, ?, ?, ?, ?, ?, 1)',
                [
                    $vid,
                    'Boşluğu doldur: ' . $blanked,
                    !empty($v['example_tr']) ? (string)$v['example_tr'] : null,
                    $german,
                    json_encode([strtr($german, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss'])], JSON_UNESCAPED_UNICODE),
                    'Tam cümle: ' . $sentence,
                    $level,
                    'vocab-context',
                ]
            );
            $created++;
        }
    }

    return $created;
}

/** Ayni tur ve seviyeden makul celdiriciler secer. */
function pick_distractors(array $byPos, string $pos, string $correct, int $count, string $topic, string $level): array
{
    $candidates = $byPos[$pos] ?? [];
    if (count($candidates) < $count + 1) {
        $candidates = [];
        foreach ($byPos as $group) {
            $candidates = array_merge($candidates, $group);
        }
    }

    $sameTopic = [];
    $sameLevel = [];
    $others = [];
    foreach ($candidates as $c) {
        if ($c['turkish'] === $correct) {
            continue;
        }
        if ($topic !== '' && (string)($c['topic'] ?? '') === $topic) {
            $sameTopic[] = (string)$c['turkish'];
        } elseif ((string)$c['cefr_level'] === $level) {
            $sameLevel[] = (string)$c['turkish'];
        } else {
            $others[] = (string)$c['turkish'];
        }
    }
    shuffle($sameTopic);
    shuffle($sameLevel);
    shuffle($others);

    $pool = array_values(array_unique(array_merge($sameTopic, $sameLevel, $others)));
    return array_slice($pool, 0, $count);
}

/** Gercekci yanlis cogul bicimleri uretir. */
function plural_distractors(string $german, string $plural): array
{
    $stem = $german;
    $umlaut = strtr($stem, ['a' => 'ä', 'o' => 'ö', 'u' => 'ü']);
    $candidates = [
        'die ' . $stem . 'e',
        'die ' . $stem . 'en',
        'die ' . $stem . 'er',
        'die ' . $stem . 's',
        'die ' . $stem,
        'die ' . $umlaut . 'e',
        'die ' . $umlaut . 'er',
        'der ' . $stem . 'e',
        'das ' . $stem . 'e',
    ];
    $out = [];
    foreach ($candidates as $c) {
        if ($c !== $plural && !in_array($c, $out, true)) {
            $out[] = $c;
        }
    }
    shuffle($out);
    return $out;
}

/* ==================================================================
 * Senaryolar
 * ================================================================== */
function seed_scenarios(bool $refresh): int
{
    require_once __DIR__ . '/content-scenarios.php';
    $n = 0;
    $order = 0;
    foreach (almancapro_scenarios() as $sc) {
        db_exec(
            'INSERT INTO scenarios (slug, title, cefr_level, category, setting_tr, role_user, role_partner, sort_order)
             VALUES (?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE title = VALUES(title), cefr_level = VALUES(cefr_level), category = VALUES(category),
                setting_tr = VALUES(setting_tr), role_user = VALUES(role_user), role_partner = VALUES(role_partner),
                sort_order = VALUES(sort_order)',
            [$sc['slug'], $sc['title'], $sc['level'], $sc['category'], $sc['setting'], $sc['role_user'], $sc['role_partner'], $order++]
        );
        $scId = (int)db_value('SELECT id FROM scenarios WHERE slug = ?', [$sc['slug']], 0);
        if ($scId === 0) {
            continue;
        }
        $existing = (int)db_value('SELECT COUNT(*) FROM scenario_turns WHERE scenario_id = ?', [$scId], 0);
        if ($existing > 0 && !$refresh) {
            $n++;
            continue;
        }
        db_exec('DELETE FROM scenario_turns WHERE scenario_id = ?', [$scId]);

        $step = 1;
        foreach ($sc['turns'] as $turn) {
            $turnId = db_insert(
                'INSERT INTO scenario_turns (scenario_id, step, speaker_de, speaker_tr, instruction_tr, hint) VALUES (?,?,?,?,?,?)',
                [$scId, $step++, $turn['de'], $turn['tr'], $turn['instruction'] ?? null, $turn['hint'] ?? null]
            );
            $o = 0;
            foreach ($turn['options'] as $opt) {
                db_exec(
                    'INSERT INTO scenario_options (turn_id, text_de, quality, feedback_tr, better_alternative, score_clarity, score_grammar, score_naturalness, score_politeness, sort_order)
                     VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [$turnId, $opt['de'], $opt['q'], $opt['fb'], $opt['alt'] ?? null,
                     $opt['sc'][0], $opt['sc'][1], $opt['sc'][2], $opt['sc'][3], $o++]
                );
            }
        }
        $n++;
    }
    return $n;
}

/* ==================================================================
 * Bilgi tabani
 * ================================================================== */
function seed_knowledge_base(): int
{
    require_once __DIR__ . '/content-kb.php';
    $n = 0;
    $order = 0;
    foreach (almancapro_knowledge_base() as $kb) {
        db_exec(
            'INSERT INTO knowledge_base (slug, question, answer, keywords, cefr_level, sort_order)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE question = VALUES(question), answer = VALUES(answer),
                keywords = VALUES(keywords), cefr_level = VALUES(cefr_level), sort_order = VALUES(sort_order)',
            [$kb['slug'], $kb['q'], $kb['a'], $kb['kw'], $kb['level'], $order++]
        );
        $n++;
    }
    return $n;
}

/* ==================================================================
 * Varsayilan gider tablosu (destek sayfasi icin)
 * ================================================================== */
function seed_donation_expenses(): int
{
    $rows = [
        ['Sunucu ve alan adı', 'altyapi', 450.00, 'aylık', 'Barındırma, yedekleme ve alan adı yenileme'],
        ['E-posta gönderimi', 'altyapi', 150.00, 'aylık', 'Doğrulama ve bildirim e-postaları'],
        ['İçerik üretimi', 'icerik', 1200.00, 'aylık', 'Yeni ders, alıştırma ve senaryo hazırlığı'],
        ['Ses kayıtları', 'icerik', 600.00, 'aylık', 'Kelime ve diyalog seslendirmeleri'],
    ];
    $n = 0;
    $order = 0;
    foreach ($rows as [$title, $cat, $amount, $period, $note]) {
        $exists = (int)db_value('SELECT COUNT(*) FROM donation_expenses WHERE title = ?', [$title], 0);
        if ($exists === 0) {
            db_exec('INSERT INTO donation_expenses (title, category, amount, period, note, sort_order) VALUES (?,?,?,?,?,?)',
                [$title, $cat, $amount, $period, $note, $order]);
            $n++;
        }
        $order++;
    }
    return $n;
}

/* ==================================================================
 * Ek alistirma turleri: dogru/yanlis, eslestirme, senaryo cevabi
 *
 * Hepsi gercek dilbilgisi ve kelime verisinden uretilir; sabit/sahte
 * icerik yoktur. Idempotenttir: ayni variant_group ile daha once
 * uretilmis kayit varsa yeniden uretilmez.
 * ================================================================== */

/** Artikel dogru/yanlis ifadeleri. */
function seed_true_false_exercises(int $perLevel = 60): int
{
    $existing = (int)db_value('SELECT COUNT(*) FROM exercises WHERE exercise_type = "true_false" AND is_generated = 1', [], 0);
    if ($existing > 0) {
        return $existing;
    }

    $created = 0;
    foreach (cefr_levels() as $level) {
        $rows = db_all(
            'SELECT * FROM vocabulary
             WHERE is_active = 1 AND part_of_speech = "noun" AND article IS NOT NULL AND cefr_level = ?
             ORDER BY id LIMIT ' . (int)$perLevel,
            [$level]
        );
        $i = 0;
        foreach ($rows as $v) {
            $german = (string)$v['german'];
            $article = (string)$v['article'];
            /* Yari yariya dogru ve yanlis ifade; sira sabit oldugu icin tekrar uretilebilir. */
            $makeTrue = ($i % 2 === 0);
            $shown = $makeTrue ? $article : (['der' => 'die', 'die' => 'das', 'das' => 'der'][$article]);
            $answer = $makeTrue ? 'Doğru' : 'Yanlış';
            $prompt = 'Şu ifade doğru mu? „' . $shown . ' ' . $german . '"';
            $explanation = $makeTrue
                ? 'Doğru. Bu ismin artikeli ' . $article . ': ' . $article . ' ' . $german . '.'
                : 'Yanlış. Doğrusu ' . $article . ' ' . $german . '. Almancada artikel ismin ayrılmaz parçasıdır.';

            $exId = db_insert(
                'INSERT INTO exercises (vocabulary_id, exercise_type, mode, prompt, correct_answer, explanation,
                    memory_hint, cefr_level, variant_group, is_generated)
                 VALUES (?, "true_false", "recognition", ?, ?, ?, ?, ?, "vocab-true-false", 1)',
                [
                    (int)$v['id'],
                    $prompt,
                    $answer,
                    $explanation,
                    'İsmi her zaman artikeliyle birlikte ezberle; artikel sonradan eklenmez.',
                    (string)$v['cefr_level'],
                ]
            );
            $o = 0;
            foreach (['Doğru', 'Yanlış'] as $opt) {
                db_exec(
                    'INSERT INTO exercise_options (exercise_id, option_text, is_correct, sort_order) VALUES (?, ?, ?, ?)',
                    [$exId, $opt, $opt === $answer ? 1 : 0, $o++]
                );
            }
            $created++;
            $i++;
        }
    }
    return $created;
}

/**
 * Eslestirme alistirmalari.
 * Ayni konudan dort kelime alinir; secenekler dort farkli eslestirme kumesidir,
 * yalnizca biri bastan sona dogrudur.
 */
function seed_matching_exercises(int $maxPerLevel = 20): int
{
    $existing = (int)db_value('SELECT COUNT(*) FROM exercises WHERE exercise_type = "matching" AND is_generated = 1', [], 0);
    if ($existing > 0) {
        return $existing;
    }

    $created = 0;
    foreach (cefr_levels() as $level) {
        $topics = db_all(
            'SELECT topic, COUNT(*) AS c FROM vocabulary
             WHERE is_active = 1 AND cefr_level = ? AND topic IS NOT NULL AND topic <> ""
             GROUP BY topic HAVING c >= 4 ORDER BY topic',
            [$level]
        );
        $made = 0;
        foreach ($topics as $t) {
            if ($made >= $maxPerLevel) {
                break;
            }
            $words = db_all(
                'SELECT * FROM vocabulary WHERE is_active = 1 AND cefr_level = ? AND topic = ? ORDER BY id LIMIT 4',
                [$level, (string)$t['topic']]
            );
            if (count($words) < 4) {
                continue;
            }

            $de = [];
            $tr = [];
            foreach ($words as $w) {
                $de[] = vocab_headword($w);
                $tr[] = (string)$w['turkish'];
            }

            $prompt = "Aşağıdaki kelimeleri Türkçe karşılıklarıyla eşleştir:\n"
                . '1) ' . $de[0] . "   2) " . $de[1] . "   3) " . $de[2] . "   4) " . $de[3]
                . "\nHangi eşleştirme baştan sona doğrudur?";

            $format = static function (array $order) use ($de, $tr): string {
                $parts = [];
                foreach ($order as $pos => $idx) {
                    $parts[] = ($pos + 1) . '-' . $tr[$idx];
                }
                return implode(' · ', $parts);
            };

            $correct = $format([0, 1, 2, 3]);
            $wrongOrders = [[1, 0, 3, 2], [0, 2, 1, 3], [3, 1, 2, 0]];
            $options = [$correct];
            foreach ($wrongOrders as $wo) {
                $options[] = $format($wo);
            }
            $options = array_values(array_unique($options));
            if (count($options) < 3) {
                continue;
            }

            $exId = db_insert(
                'INSERT INTO exercises (exercise_type, mode, prompt, correct_answer, explanation, cefr_level,
                    variant_group, is_generated)
                 VALUES ("matching", "recognition", ?, ?, ?, ?, "vocab-matching", 1)',
                [
                    $prompt,
                    $correct,
                    'Doğru eşleştirme: ' . $correct . '. İsimleri artikelleriyle birlikte hatırlamak eşleştirmeyi kolaylaştırır.',
                    $level,
                ]
            );
            $o = 0;
            foreach ($options as $opt) {
                db_exec(
                    'INSERT INTO exercise_options (exercise_id, option_text, is_correct, sort_order) VALUES (?, ?, ?, ?)',
                    [$exId, $opt, $opt === $correct ? 1 : 0, $o++]
                );
            }
            $created++;
            $made++;
        }
    }
    return $created;
}

/**
 * Senaryo cevabi alistirmalari.
 * Gercek senaryo diyaloglarindan uretilir: durum + karsi tarafin sozu verilir,
 * en uygun Almanca cevap secilir.
 */
function seed_scenario_response_exercises(): int
{
    $existing = (int)db_value('SELECT COUNT(*) FROM exercises WHERE exercise_type = "scenario_response" AND is_generated = 1', [], 0);
    if ($existing > 0) {
        return $existing;
    }

    $created = 0;
    $turns = db_all(
        'SELECT t.*, s.title AS scenario_title, s.setting_tr, s.cefr_level, s.role_partner
         FROM scenario_turns t JOIN scenarios s ON s.id = t.scenario_id
         ORDER BY t.scenario_id, t.step'
    );

    foreach ($turns as $t) {
        $options = db_all(
            'SELECT * FROM scenario_options WHERE turn_id = ? ORDER BY sort_order, id',
            [(int)$t['id']]
        );
        if (count($options) < 3) {
            continue;
        }
        $best = null;
        foreach ($options as $o) {
            if ((string)$o['quality'] === 'good') {
                $best = $o;
                break;
            }
        }
        if ($best === null) {
            continue;
        }

        $prompt = (string)$t['scenario_title'] . ' — ' . (string)$t['setting_tr'] . "\n"
            . (string)$t['role_partner'] . ': „' . (string)$t['speaker_de'] . '" ('
            . (string)$t['speaker_tr'] . ")\n"
            . 'Görev: ' . (string)$t['instruction_tr'];

        $exId = db_insert(
            'INSERT INTO exercises (exercise_type, mode, prompt, context, correct_answer, explanation, memory_hint,
                cefr_level, variant_group, is_generated)
             VALUES ("scenario_response", "application", ?, ?, ?, ?, ?, ?, "scenario-response", 1)',
            [
                $prompt,
                (string)$t['hint'],
                (string)$best['text_de'],
                (string)$best['feedback_tr'],
                'Doğal ve kibar biçim: ' . (string)$best['text_de'],
                (string)$t['cefr_level'],
            ]
        );
        $o = 0;
        foreach ($options as $opt) {
            db_exec(
                'INSERT INTO exercise_options (exercise_id, option_text, is_correct, feedback, sort_order) VALUES (?, ?, ?, ?, ?)',
                [
                    $exId,
                    (string)$opt['text_de'],
                    (int)$opt['id'] === (int)$best['id'] ? 1 : 0,
                    (string)$opt['feedback_tr'],
                    $o++,
                ]
            );
        }
        $created++;
    }

    return $created;
}
