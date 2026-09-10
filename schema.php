<?php
/**
 * AlmancaPro - Veritabani semasi (DDL).
 *
 * Bu dosya calistirilabilir bir sayfa degildir; sema tanimlarini dondurur.
 * Tum ifadeler idempotenttir (CREATE TABLE IF NOT EXISTS).
 */
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * @return string[] Sirali CREATE TABLE ifadeleri.
 */
function almancapro_schema_statements(): array
{
    $E = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $s = [];

    /* ---------------- Ayarlar & yonetim ---------------- */
    $s['site_settings'] = "CREATE TABLE IF NOT EXISTS site_settings (
        setting_key   VARCHAR(64) NOT NULL,
        setting_value MEDIUMTEXT NULL,
        is_secret     TINYINT(1) NOT NULL DEFAULT 0,
        updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (setting_key)
    ) $E";

    $s['admins'] = "CREATE TABLE IF NOT EXISTS admins (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        username VARCHAR(64) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        display_name VARCHAR(120) NOT NULL DEFAULT 'Yonetici',
        email VARCHAR(190) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        must_change_password TINYINT(1) NOT NULL DEFAULT 1,
        failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        locked_until DATETIME NULL,
        last_login_at DATETIME NULL,
        last_login_ip VARCHAR(45) NULL,
        password_changed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_admin_username (username)
    ) $E";

    $s['admin_logs'] = "CREATE TABLE IF NOT EXISTS admin_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        admin_id INT UNSIGNED NULL,
        action VARCHAR(64) NOT NULL,
        target_type VARCHAR(48) NULL,
        target_id VARCHAR(64) NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        metadata TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_admin_logs_admin (admin_id),
        KEY idx_admin_logs_action (action),
        KEY idx_admin_logs_created (created_at)
    ) $E";

    $s['app_locks'] = "CREATE TABLE IF NOT EXISTS app_locks (
        lock_name VARCHAR(64) NOT NULL,
        locked_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        owner VARCHAR(64) NULL,
        PRIMARY KEY (lock_name)
    ) $E";

    $s['rate_limits'] = "CREATE TABLE IF NOT EXISTS rate_limits (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        bucket VARCHAR(48) NOT NULL,
        identifier VARCHAR(190) NOT NULL,
        hits INT UNSIGNED NOT NULL DEFAULT 1,
        window_start DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_rate (bucket, identifier),
        KEY idx_rate_window (window_start)
    ) $E";

    /* ---------------- Kullanicilar ---------------- */
    $s['users'] = "CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        is_verified TINYINT(1) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        cefr_level VARCHAR(4) NOT NULL DEFAULT 'A0',
        start_level VARCHAR(4) NOT NULL DEFAULT 'A0',
        onboarding_completed TINYINT(1) NOT NULL DEFAULT 0,
        placement_status ENUM('none','skipped','completed') NOT NULL DEFAULT 'none',
        goal_reason VARCHAR(32) NULL,
        departure_date DATE NULL,
        daily_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
        timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Istanbul',
        intensive_enabled TINYINT(1) NOT NULL DEFAULT 0,
        intensive_started_on DATE NULL,
        streak_count INT UNSIGNED NOT NULL DEFAULT 0,
        longest_streak INT UNSIGNED NOT NULL DEFAULT 0,
        last_study_date DATE NULL,
        total_xp INT UNSIGNED NOT NULL DEFAULT 0,
        total_study_seconds INT UNSIGNED NOT NULL DEFAULT 0,
        terms_accepted_at DATETIME NULL,
        privacy_accepted_at DATETIME NULL,
        last_login_at DATETIME NULL,
        last_login_ip VARCHAR(45) NULL,
        password_changed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_users_email (email),
        KEY idx_users_verified (is_verified),
        KEY idx_users_active (is_active),
        KEY idx_users_level (cefr_level),
        KEY idx_users_created (created_at)
    ) $E";

    $s['email_verifications'] = "CREATE TABLE IF NOT EXISTS email_verifications (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        code_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        consumed_at DATETIME NULL,
        ip_address VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_ev_user (user_id),
        KEY idx_ev_expires (expires_at),
        CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $E";

    $s['password_resets'] = "CREATE TABLE IF NOT EXISTS password_resets (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        ip_address VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_pr_token (token_hash),
        KEY idx_pr_user (user_id),
        CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $E";

    $s['remember_tokens'] = "CREATE TABLE IF NOT EXISTS remember_tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        selector CHAR(24) NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        user_agent VARCHAR(190) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_rt_selector (selector),
        KEY idx_rt_user (user_id),
        CONSTRAINT fk_rt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $E";

    $s['login_attempts'] = "CREATE TABLE IF NOT EXISTS login_attempts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        identifier VARCHAR(190) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_la_ident (identifier, created_at),
        KEY idx_la_ip (ip_address, created_at)
    ) $E";

    $s['admin_login_attempts'] = "CREATE TABLE IF NOT EXISTS admin_login_attempts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        username VARCHAR(64) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_ala_user (username, created_at),
        KEY idx_ala_ip (ip_address, created_at)
    ) $E";

    /* ---------------- Mufredat ---------------- */
    $s['modules'] = "CREATE TABLE IF NOT EXISTS modules (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(80) NOT NULL,
        cefr_level VARCHAR(4) NOT NULL,
        title VARCHAR(160) NOT NULL,
        description VARCHAR(500) NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_modules_slug (slug),
        KEY idx_modules_level (cefr_level, sort_order)
    ) $E";

    $s['skills'] = "CREATE TABLE IF NOT EXISTS skills (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(64) NOT NULL,
        name VARCHAR(160) NOT NULL,
        category VARCHAR(48) NOT NULL DEFAULT 'grammar',
        cefr_level VARCHAR(4) NOT NULL DEFAULT 'A1',
        description VARCHAR(500) NULL,
        mastery_threshold TINYINT UNSIGNED NOT NULL DEFAULT 90,
        is_critical TINYINT(1) NOT NULL DEFAULT 1,
        requires_production TINYINT(1) NOT NULL DEFAULT 1,
        requires_spelling TINYINT(1) NOT NULL DEFAULT 0,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_skills_code (code),
        KEY idx_skills_level (cefr_level),
        KEY idx_skills_category (category)
    ) $E";

    $s['lessons'] = "CREATE TABLE IF NOT EXISTS lessons (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        module_id INT UNSIGNED NULL,
        slug VARCHAR(120) NOT NULL,
        title VARCHAR(200) NOT NULL,
        cefr_level VARCHAR(4) NOT NULL DEFAULT 'A1',
        lesson_type ENUM('grammar','vocabulary','pronunciation','workplace','daily_life','scenario','listening','review') NOT NULL DEFAULT 'grammar',
        objective VARCHAR(500) NULL,
        summary VARCHAR(500) NULL,
        estimated_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 12,
        completion_threshold TINYINT UNSIGNED NOT NULL DEFAULT 80,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_lessons_slug (slug),
        KEY idx_lessons_module (module_id, sort_order),
        KEY idx_lessons_level (cefr_level, sort_order),
        KEY idx_lessons_type (lesson_type),
        CONSTRAINT fk_lessons_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE SET NULL
    ) $E";

    $s['lesson_sections'] = "CREATE TABLE IF NOT EXISTS lesson_sections (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        lesson_id INT UNSIGNED NOT NULL,
        section_type ENUM('explanation','why','tr_contrast','rule','example','exception','common_mistake','memory_tip','usage','table','dialogue','pronunciation') NOT NULL DEFAULT 'explanation',
        heading VARCHAR(200) NULL,
        body MEDIUMTEXT NULL,
        example_de TEXT NULL,
        example_tr TEXT NULL,
        highlight VARCHAR(120) NULL,
        is_collapsible TINYINT(1) NOT NULL DEFAULT 0,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_ls_lesson (lesson_id, sort_order),
        CONSTRAINT fk_ls_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
    ) $E";

    $s['lesson_skills'] = "CREATE TABLE IF NOT EXISTS lesson_skills (
        lesson_id INT UNSIGNED NOT NULL,
        skill_id INT UNSIGNED NOT NULL,
        is_primary TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (lesson_id, skill_id),
        KEY idx_lsk_skill (skill_id),
        CONSTRAINT fk_lsk_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
        CONSTRAINT fk_lsk_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
    ) $E";

    $s['lesson_prerequisites'] = "CREATE TABLE IF NOT EXISTS lesson_prerequisites (
        lesson_id INT UNSIGNED NOT NULL,
        skill_id INT UNSIGNED NOT NULL,
        required_mastery TINYINT UNSIGNED NOT NULL DEFAULT 90,
        PRIMARY KEY (lesson_id, skill_id),
        KEY idx_lp_skill (skill_id),
        CONSTRAINT fk_lp_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
        CONSTRAINT fk_lp_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
    ) $E";

    $s['grammar_topics'] = "CREATE TABLE IF NOT EXISTS grammar_topics (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        skill_id INT UNSIGNED NULL,
        lesson_id INT UNSIGNED NULL,
        slug VARCHAR(120) NOT NULL,
        title VARCHAR(200) NOT NULL,
        cefr_level VARCHAR(4) NOT NULL DEFAULT 'A1',
        what_is_it TEXT NULL,
        why_used TEXT NULL,
        tr_difference TEXT NULL,
        sentence_role TEXT NULL,
        how_to_recognize TEXT NULL,
        rule TEXT NULL,
        exceptions TEXT NULL,
        common_mistake TEXT NULL,
        memory_tip TEXT NULL,
        keywords VARCHAR(500) NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_gt_slug (slug),
        KEY idx_gt_level (cefr_level, sort_order),
        KEY idx_gt_skill (skill_id),
        CONSTRAINT fk_gt_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE SET NULL,
        CONSTRAINT fk_gt_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL
    ) $E";

    $s['grammar_examples'] = "CREATE TABLE IF NOT EXISTS grammar_examples (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        grammar_topic_id INT UNSIGNED NOT NULL,
        de VARCHAR(400) NOT NULL,
        tr VARCHAR(400) NOT NULL,
        note VARCHAR(400) NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_ge_topic (grammar_topic_id, sort_order),
        CONSTRAINT fk_ge_topic FOREIGN KEY (grammar_topic_id) REFERENCES grammar_topics(id) ON DELETE CASCADE
    ) $E";

    /* ---------------- Kelime hazinesi ---------------- */
    $s['vocabulary'] = "CREATE TABLE IF NOT EXISTS vocabulary (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        german VARCHAR(120) NOT NULL,
        normalized_german VARCHAR(120) NOT NULL,
        article ENUM('der','die','das') NULL,
        gender ENUM('m','f','n') NULL,
        plural VARCHAR(120) NULL,
        turkish VARCHAR(240) NOT NULL,
        part_of_speech ENUM('noun','proper_noun','verb','adjective','adverb','preposition','pronoun','conjunction','numeral','phrase','article','particle') NOT NULL DEFAULT 'noun',
        pronunciation VARCHAR(160) NULL,
        ipa VARCHAR(160) NULL,
        cefr_level VARCHAR(4) NOT NULL DEFAULT 'A1',
        topic VARCHAR(64) NULL,
        example_de VARCHAR(400) NULL,
        example_tr VARCHAR(400) NULL,
        usage_notes VARCHAR(600) NULL,
        memory_tip VARCHAR(600) NULL,
        similar_word_note VARCHAR(600) NULL,
        separable_prefix VARCHAR(24) NULL,
        auxiliary ENUM('haben','sein') NULL,
        preterite VARCHAR(80) NULL,
        participle_ii VARCHAR(80) NULL,
        third_person VARCHAR(80) NULL,
        is_irregular TINYINT(1) NOT NULL DEFAULT 0,
        is_reflexive TINYINT(1) NOT NULL DEFAULT 0,
        requires_case ENUM('nominativ','akkusativ','dativ','genitiv') NULL,
        required_preposition VARCHAR(48) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_vocab (normalized_german, part_of_speech),
        KEY idx_vocab_level (cefr_level),
        KEY idx_vocab_topic (topic),
        KEY idx_vocab_turkish (turkish),
        KEY idx_vocab_pos (part_of_speech)
    ) $E";

    $s['vocabulary_examples'] = "CREATE TABLE IF NOT EXISTS vocabulary_examples (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        vocabulary_id INT UNSIGNED NOT NULL,
        de VARCHAR(400) NOT NULL,
        tr VARCHAR(400) NOT NULL,
        note VARCHAR(300) NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_ve_vocab (vocabulary_id, sort_order),
        CONSTRAINT fk_ve_vocab FOREIGN KEY (vocabulary_id) REFERENCES vocabulary(id) ON DELETE CASCADE
    ) $E";

    $s['lesson_vocabulary'] = "CREATE TABLE IF NOT EXISTS lesson_vocabulary (
        lesson_id INT UNSIGNED NOT NULL,
        vocabulary_id INT UNSIGNED NOT NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (lesson_id, vocabulary_id),
        KEY idx_lv_vocab (vocabulary_id),
        CONSTRAINT fk_lv_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
        CONSTRAINT fk_lv_vocab FOREIGN KEY (vocabulary_id) REFERENCES vocabulary(id) ON DELETE CASCADE
    ) $E";

    /* ---------------- Egzersizler ---------------- */
    $s['exercises'] = "CREATE TABLE IF NOT EXISTS exercises (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        lesson_id INT UNSIGNED NULL,
        skill_id INT UNSIGNED NULL,
        vocabulary_id INT UNSIGNED NULL,
        grammar_topic_id INT UNSIGNED NULL,
        exercise_type ENUM('multiple_choice','text_input','translation_tr_de','translation_de_tr','fill_blank','word_order','article','plural','conjugation','case_choice','sentence_correction','matching','true_false','scenario_response','listening') NOT NULL DEFAULT 'multiple_choice',
        mode ENUM('recognition','recall','production','spelling','delayed','application') NOT NULL DEFAULT 'recognition',
        prompt VARCHAR(600) NOT NULL,
        prompt_tr VARCHAR(600) NULL,
        context VARCHAR(600) NULL,
        correct_answer VARCHAR(400) NOT NULL,
        accepted_answers TEXT NULL,
        explanation VARCHAR(1000) NULL,
        memory_hint VARCHAR(600) NULL,
        cefr_level VARCHAR(4) NOT NULL DEFAULT 'A1',
        difficulty TINYINT UNSIGNED NOT NULL DEFAULT 2,
        variant_group VARCHAR(80) NULL,
        audio_text VARCHAR(400) NULL,
        is_generated TINYINT(1) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_ex_lesson (lesson_id, sort_order),
        KEY idx_ex_skill (skill_id),
        KEY idx_ex_vocab (vocabulary_id),
        KEY idx_ex_mode (mode),
        KEY idx_ex_type (exercise_type),
        KEY idx_ex_level (cefr_level),
        KEY idx_ex_variant (variant_group),
        CONSTRAINT fk_ex_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
        CONSTRAINT fk_ex_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE SET NULL,
        CONSTRAINT fk_ex_vocab FOREIGN KEY (vocabulary_id) REFERENCES vocabulary(id) ON DELETE CASCADE,
        CONSTRAINT fk_ex_gt FOREIGN KEY (grammar_topic_id) REFERENCES grammar_topics(id) ON DELETE SET NULL
    ) $E";

    $s['exercise_options'] = "CREATE TABLE IF NOT EXISTS exercise_options (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        exercise_id INT UNSIGNED NOT NULL,
        option_text VARCHAR(400) NOT NULL,
        is_correct TINYINT(1) NOT NULL DEFAULT 0,
        feedback VARCHAR(600) NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_eo_ex (exercise_id, sort_order),
        CONSTRAINT fk_eo_ex FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
    ) $E";

    $s['exercise_skill_map'] = "CREATE TABLE IF NOT EXISTS exercise_skill_map (
        exercise_id INT UNSIGNED NOT NULL,
        skill_id INT UNSIGNED NOT NULL,
        weight TINYINT UNSIGNED NOT NULL DEFAULT 1,
        PRIMARY KEY (exercise_id, skill_id),
        KEY idx_esm_skill (skill_id),
        CONSTRAINT fk_esm_ex FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
        CONSTRAINT fk_esm_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
    ) $E";

    $s['error_categories'] = "CREATE TABLE IF NOT EXISTS error_categories (
        id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(48) NOT NULL,
        name VARCHAR(120) NOT NULL,
        description VARCHAR(400) NULL,
        advice VARCHAR(600) NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_ec_code (code)
    ) $E";

    /* ---------------- Kullanici ilerlemesi ---------------- */
    $s['user_lesson_progress'] = "CREATE TABLE IF NOT EXISTS user_lesson_progress (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        lesson_id INT UNSIGNED NOT NULL,
        status ENUM('not_started','in_progress','needs_review','completed') NOT NULL DEFAULT 'not_started',
        current_step SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        total_steps SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        best_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_ulp (user_id, lesson_id),
        KEY idx_ulp_status (user_id, status),
        CONSTRAINT fk_ulp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_ulp_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
    ) $E";

    $s['user_skill_mastery'] = "CREATE TABLE IF NOT EXISTS user_skill_mastery (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        skill_id INT UNSIGNED NOT NULL,
        status ENUM('introduced','learning','weak','reviewing','strong','mastered','overdue') NOT NULL DEFAULT 'introduced',
        mastery_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
        attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        correct SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        incorrect SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        first_attempt_correct SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        first_attempt_total SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        recent_results VARCHAR(40) NOT NULL DEFAULT '',
        consecutive_correct SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        recognition_ok SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        recall_ok SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        production_ok SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        spelling_ok SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        delayed_ok SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        context_variants SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        lapses SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        repetitions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        ease_factor DECIMAL(4,2) NOT NULL DEFAULT 2.50,
        interval_minutes INT UNSIGNED NOT NULL DEFAULT 10,
        next_review_at DATETIME NULL,
        last_practiced_at DATETIME NULL,
        mastered_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_usm (user_id, skill_id),
        KEY idx_usm_due (user_id, next_review_at),
        KEY idx_usm_status (user_id, status),
        CONSTRAINT fk_usm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_usm_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
    ) $E";

    $s['user_vocabulary_mastery'] = "CREATE TABLE IF NOT EXISTS user_vocabulary_mastery (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        vocabulary_id INT UNSIGNED NOT NULL,
        status ENUM('introduced','learning','weak','reviewing','strong','mastered','overdue') NOT NULL DEFAULT 'introduced',
        mastery_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
        attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        correct SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        incorrect SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        recent_results VARCHAR(40) NOT NULL DEFAULT '',
        consecutive_correct SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        de_tr_ok SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        tr_de_ok SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        article_ok SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        plural_ok SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        spelling_ok SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        production_ok SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        delayed_ok SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        lapses SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        repetitions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        ease_factor DECIMAL(4,2) NOT NULL DEFAULT 2.50,
        interval_minutes INT UNSIGNED NOT NULL DEFAULT 10,
        next_review_at DATETIME NULL,
        last_practiced_at DATETIME NULL,
        mastered_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_uvm (user_id, vocabulary_id),
        KEY idx_uvm_due (user_id, next_review_at),
        KEY idx_uvm_status (user_id, status),
        CONSTRAINT fk_uvm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_uvm_vocab FOREIGN KEY (vocabulary_id) REFERENCES vocabulary(id) ON DELETE CASCADE
    ) $E";

    $s['study_sessions'] = "CREATE TABLE IF NOT EXISTS study_sessions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        session_type ENUM('lesson','quiz','review','remediation','checkpoint','placement','scenario','telegram','intensive') NOT NULL DEFAULT 'quiz',
        lesson_id INT UNSIGNED NULL,
        skill_id INT UNSIGNED NULL,
        status ENUM('active','completed','abandoned') NOT NULL DEFAULT 'active',
        items_total SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        correct_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        wrong_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
        source ENUM('web','telegram') NOT NULL DEFAULT 'web',
        payload MEDIUMTEXT NULL,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ended_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY idx_ss_user (user_id, started_at),
        KEY idx_ss_status (user_id, status, session_type),
        CONSTRAINT fk_ss_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_ss_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL
    ) $E";

    $s['session_items'] = "CREATE TABLE IF NOT EXISTS session_items (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id BIGINT UNSIGNED NOT NULL,
        exercise_id INT UNSIGNED NOT NULL,
        position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        status ENUM('pending','correct','wrong','requeued') NOT NULL DEFAULT 'pending',
        attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
        answered_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY idx_si_session (session_id, position),
        CONSTRAINT fk_si_session FOREIGN KEY (session_id) REFERENCES study_sessions(id) ON DELETE CASCADE,
        CONSTRAINT fk_si_ex FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
    ) $E";

    $s['exercise_attempts'] = "CREATE TABLE IF NOT EXISTS exercise_attempts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        exercise_id INT UNSIGNED NULL,
        session_id BIGINT UNSIGNED NULL,
        skill_id INT UNSIGNED NULL,
        vocabulary_id INT UNSIGNED NULL,
        lesson_id INT UNSIGNED NULL,
        user_answer VARCHAR(600) NULL,
        is_correct TINYINT(1) NOT NULL DEFAULT 0,
        score TINYINT UNSIGNED NOT NULL DEFAULT 0,
        mode ENUM('recognition','recall','production','spelling','delayed','application') NOT NULL DEFAULT 'recognition',
        response_ms INT UNSIGNED NULL,
        source ENUM('web','telegram') NOT NULL DEFAULT 'web',
        evaluation TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_ea_user (user_id, created_at),
        KEY idx_ea_skill (user_id, skill_id),
        KEY idx_ea_vocab (user_id, vocabulary_id),
        KEY idx_ea_ex (exercise_id),
        CONSTRAINT fk_ea_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_ea_ex FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE SET NULL
    ) $E";

    $s['answer_error_categories'] = "CREATE TABLE IF NOT EXISTS answer_error_categories (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        attempt_id BIGINT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        error_category_id SMALLINT UNSIGNED NOT NULL,
        skill_id INT UNSIGNED NULL,
        detail VARCHAR(400) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_aec_user (user_id, error_category_id),
        KEY idx_aec_attempt (attempt_id),
        CONSTRAINT fk_aec_attempt FOREIGN KEY (attempt_id) REFERENCES exercise_attempts(id) ON DELETE CASCADE,
        CONSTRAINT fk_aec_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_aec_cat FOREIGN KEY (error_category_id) REFERENCES error_categories(id) ON DELETE CASCADE
    ) $E";

    $s['daily_plans'] = "CREATE TABLE IF NOT EXISTS daily_plans (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        plan_date DATE NOT NULL,
        target_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
        intensity ENUM('hafif','normal','yogun','cok_yogun') NOT NULL DEFAULT 'normal',
        program_day SMALLINT UNSIGNED NULL,
        completed_at DATETIME NULL,
        generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_dp (user_id, plan_date),
        CONSTRAINT fk_dp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $E";

    $s['daily_plan_items'] = "CREATE TABLE IF NOT EXISTS daily_plan_items (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        plan_id BIGINT UNSIGNED NOT NULL,
        item_type ENUM('vocabulary','lesson','review','quiz','workplace','scenario','grammar','remediation','exam') NOT NULL,
        ref_id INT UNSIGNED NULL,
        title VARCHAR(200) NOT NULL,
        target_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        done_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        estimated_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 10,
        status ENUM('pending','in_progress','done') NOT NULL DEFAULT 'pending',
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_dpi_plan (plan_id, sort_order),
        CONSTRAINT fk_dpi_plan FOREIGN KEY (plan_id) REFERENCES daily_plans(id) ON DELETE CASCADE
    ) $E";

    $s['user_activity'] = "CREATE TABLE IF NOT EXISTS user_activity (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        activity_type VARCHAR(48) NOT NULL,
        ref_id INT UNSIGNED NULL,
        title VARCHAR(240) NULL,
        xp SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        seconds INT UNSIGNED NOT NULL DEFAULT 0,
        meta TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_ua_user (user_id, created_at),
        KEY idx_ua_type (activity_type),
        CONSTRAINT fk_ua_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $E";

    $s['placement_tests'] = "CREATE TABLE IF NOT EXISTS placement_tests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        answers MEDIUMTEXT NULL,
        score_vocabulary TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_grammar TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_reading TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_production TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_spelling TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_sentence TINYINT UNSIGNED NOT NULL DEFAULT 0,
        total_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
        recommended_level VARCHAR(4) NOT NULL DEFAULT 'A0',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_pt_user (user_id, created_at),
        CONSTRAINT fk_pt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $E";

    /* ---------------- Senaryolar ---------------- */
    $s['scenarios'] = "CREATE TABLE IF NOT EXISTS scenarios (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(80) NOT NULL,
        title VARCHAR(200) NOT NULL,
        cefr_level VARCHAR(4) NOT NULL DEFAULT 'A1',
        category VARCHAR(48) NOT NULL DEFAULT 'daily',
        setting_tr VARCHAR(600) NULL,
        role_user VARCHAR(120) NULL,
        role_partner VARCHAR(120) NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        UNIQUE KEY uq_sc_slug (slug),
        KEY idx_sc_level (cefr_level, sort_order)
    ) $E";

    $s['scenario_turns'] = "CREATE TABLE IF NOT EXISTS scenario_turns (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        scenario_id INT UNSIGNED NOT NULL,
        step SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        speaker_de VARCHAR(600) NOT NULL,
        speaker_tr VARCHAR(600) NOT NULL,
        instruction_tr VARCHAR(600) NULL,
        hint VARCHAR(600) NULL,
        PRIMARY KEY (id),
        KEY idx_st_sc (scenario_id, step),
        CONSTRAINT fk_st_sc FOREIGN KEY (scenario_id) REFERENCES scenarios(id) ON DELETE CASCADE
    ) $E";

    $s['scenario_options'] = "CREATE TABLE IF NOT EXISTS scenario_options (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        turn_id INT UNSIGNED NOT NULL,
        text_de VARCHAR(600) NOT NULL,
        quality ENUM('good','ok','bad') NOT NULL DEFAULT 'ok',
        feedback_tr VARCHAR(1000) NULL,
        better_alternative VARCHAR(600) NULL,
        score_clarity TINYINT UNSIGNED NOT NULL DEFAULT 3,
        score_grammar TINYINT UNSIGNED NOT NULL DEFAULT 3,
        score_naturalness TINYINT UNSIGNED NOT NULL DEFAULT 3,
        score_politeness TINYINT UNSIGNED NOT NULL DEFAULT 3,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_so_turn (turn_id, sort_order),
        CONSTRAINT fk_so_turn FOREIGN KEY (turn_id) REFERENCES scenario_turns(id) ON DELETE CASCADE
    ) $E";

    $s['user_scenario_runs'] = "CREATE TABLE IF NOT EXISTS user_scenario_runs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        scenario_id INT UNSIGNED NOT NULL,
        transcript MEDIUMTEXT NULL,
        score_clarity TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_grammar TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_naturalness TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_politeness TINYINT UNSIGNED NOT NULL DEFAULT 0,
        completed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_usr_user (user_id, created_at),
        CONSTRAINT fk_usr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_usr_sc FOREIGN KEY (scenario_id) REFERENCES scenarios(id) ON DELETE CASCADE
    ) $E";

    /* ---------------- Telegram ---------------- */
    $s['telegram_connections'] = "CREATE TABLE IF NOT EXISTS telegram_connections (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        telegram_user_id BIGINT NOT NULL,
        chat_id BIGINT NOT NULL,
        username VARCHAR(64) NULL,
        first_name VARCHAR(120) NULL,
        language_code VARCHAR(8) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        quiz_enabled TINYINT(1) NOT NULL DEFAULT 1,
        linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_interaction_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_tc_user (user_id),
        UNIQUE KEY uq_tc_tg (telegram_user_id),
        KEY idx_tc_chat (chat_id),
        CONSTRAINT fk_tc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $E";

    $s['telegram_link_tokens'] = "CREATE TABLE IF NOT EXISTS telegram_link_tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_tlt_token (token_hash),
        KEY idx_tlt_user (user_id),
        CONSTRAINT fk_tlt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $E";

    $s['telegram_updates'] = "CREATE TABLE IF NOT EXISTS telegram_updates (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        update_id BIGINT NOT NULL,
        chat_id BIGINT NULL,
        kind VARCHAR(32) NULL,
        payload MEDIUMTEXT NULL,
        processed_at DATETIME NULL,
        error VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_tu_update (update_id),
        KEY idx_tu_created (created_at)
    ) $E";

    $s['telegram_quiz_state'] = "CREATE TABLE IF NOT EXISTS telegram_quiz_state (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        chat_id BIGINT NOT NULL,
        message_id BIGINT NULL,
        exercise_id INT UNSIGNED NOT NULL,
        session_id BIGINT UNSIGNED NULL,
        answered_at DATETIME NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_tqs_user (user_id, created_at),
        KEY idx_tqs_msg (chat_id, message_id),
        CONSTRAINT fk_tqs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_tqs_ex FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
    ) $E";

    /* ---------------- Bildirimler ---------------- */
    $s['notification_preferences'] = "CREATE TABLE IF NOT EXISTS notification_preferences (
        user_id INT UNSIGNED NOT NULL,
        mode ENUM('hafif','normal','yogun','ozel') NOT NULL DEFAULT 'normal',
        daily_reminder TINYINT(1) NOT NULL DEFAULT 1,
        review_reminder TINYINT(1) NOT NULL DEFAULT 1,
        weak_vocabulary TINYINT(1) NOT NULL DEFAULT 1,
        mini_quiz TINYINT(1) NOT NULL DEFAULT 1,
        goal_reminder TINYINT(1) NOT NULL DEFAULT 1,
        streak_risk TINYINT(1) NOT NULL DEFAULT 1,
        unfinished_lesson TINYINT(1) NOT NULL DEFAULT 1,
        telegram_enabled TINYINT(1) NOT NULL DEFAULT 1,
        email_enabled TINYINT(1) NOT NULL DEFAULT 0,
        start_time TIME NOT NULL DEFAULT '08:00:00',
        end_time TIME NOT NULL DEFAULT '22:00:00',
        quiet_start TIME NOT NULL DEFAULT '22:00:00',
        quiet_end TIME NOT NULL DEFAULT '08:00:00',
        timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Istanbul',
        daily_target SMALLINT UNSIGNED NOT NULL DEFAULT 30,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id),
        CONSTRAINT fk_np_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $E";

    $s['notification_queue'] = "CREATE TABLE IF NOT EXISTS notification_queue (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NULL,
        channel ENUM('telegram','email') NOT NULL DEFAULT 'telegram',
        template VARCHAR(48) NOT NULL,
        subject VARCHAR(240) NULL,
        body MEDIUMTEXT NULL,
        payload TEXT NULL,
        dedupe_key VARCHAR(190) NOT NULL,
        scheduled_at DATETIME NOT NULL,
        status ENUM('pending','sending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        last_error VARCHAR(500) NULL,
        sent_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_nq_dedupe (dedupe_key),
        KEY idx_nq_due (status, scheduled_at),
        KEY idx_nq_user (user_id),
        CONSTRAINT fk_nq_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $E";

    $s['notification_log'] = "CREATE TABLE IF NOT EXISTS notification_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NULL,
        channel ENUM('telegram','email') NOT NULL,
        template VARCHAR(48) NOT NULL,
        status ENUM('sent','failed') NOT NULL,
        error VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_nl_user (user_id, created_at),
        KEY idx_nl_status (status, created_at)
    ) $E";

    $s['mail_log'] = "CREATE TABLE IF NOT EXISTS mail_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        to_email VARCHAR(190) NOT NULL,
        subject VARCHAR(240) NULL,
        template VARCHAR(48) NULL,
        status ENUM('sent','failed') NOT NULL,
        error VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_ml_created (created_at),
        KEY idx_ml_status (status)
    ) $E";

    /* ---------------- Ogretmene sor ---------------- */
    $s['questions'] = "CREATE TABLE IF NOT EXISTS questions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        body VARCHAR(2000) NOT NULL,
        context TEXT NULL,
        status ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
        source ENUM('web','telegram') NOT NULL DEFAULT 'web',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_q_user (user_id, created_at),
        KEY idx_q_status (status, created_at),
        CONSTRAINT fk_q_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $E";

    $s['answers'] = "CREATE TABLE IF NOT EXISTS answers (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        question_id BIGINT UNSIGNED NOT NULL,
        source ENUM('ai','admin','knowledge_base') NOT NULL DEFAULT 'knowledge_base',
        admin_id INT UNSIGNED NULL,
        body MEDIUMTEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_a_question (question_id, created_at),
        CONSTRAINT fk_a_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
    ) $E";

    $s['knowledge_base'] = "CREATE TABLE IF NOT EXISTS knowledge_base (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(120) NOT NULL,
        question VARCHAR(400) NOT NULL,
        answer MEDIUMTEXT NOT NULL,
        keywords VARCHAR(600) NOT NULL,
        cefr_level VARCHAR(4) NOT NULL DEFAULT 'A1',
        grammar_topic_id INT UNSIGNED NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY uq_kb_slug (slug),
        KEY idx_kb_level (cefr_level)
    ) $E";

    /* ---------------- Destek / bagis ---------------- */
    $s['donations'] = "CREATE TABLE IF NOT EXISTS donations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NULL,
        code CHAR(6) NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        sender_name VARCHAR(160) NULL,
        transfer_date DATE NULL,
        note VARCHAR(600) NULL,
        show_in_supporters TINYINT(1) NOT NULL DEFAULT 0,
        notify_email TINYINT(1) NOT NULL DEFAULT 1,
        status ENUM('beklemede','onaylandi','kod_eslesmedi','bulunamadi') NOT NULL DEFAULT 'beklemede',
        source ENUM('bildirim','otomatik','hesap_hareketi') NOT NULL DEFAULT 'bildirim',
        admin_note VARCHAR(600) NULL,
        confirmed_at DATETIME NULL,
        confirmed_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_don_code (code),
        KEY idx_don_user (user_id),
        KEY idx_don_status (status, created_at),
        CONSTRAINT fk_don_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) $E";

    $s['donation_expenses'] = "CREATE TABLE IF NOT EXISTS donation_expenses (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(160) NOT NULL,
        category VARCHAR(64) NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        period VARCHAR(32) NULL,
        note VARCHAR(400) NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) $E";

    return $s;
}
