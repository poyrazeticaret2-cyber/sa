<?php
/**
 * AlmancaPro - Otomatik kurulum.
 *
 * Site ilk acildiginda sema olusturulur, gercek icerik yuklenir ve
 * varsayilan yonetici hesabi acilir. Islem idempotenttir.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/seed.php';

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Mevcut kurulumlarda sema farklarini kapatir.
 * Her adim once kontrol edilir, bu sayede tekrar tekrar calistirilabilir.
 *
 * @return int Uygulanan guncelleme sayisi.
 */
/**
 * Sitenin calistigi alan adini www olmadan dondurur.
 * Plesk'te giden/gelen posta sunucusu bu alan adiyla aynidir.
 */
function almancapro_mail_host(): string
{
    $host = (string)setting('site_url', '');
    if ($host !== '') {
        $host = (string)parse_url($host, PHP_URL_HOST);
    }
    if ($host === '' && isset($_SERVER['HTTP_HOST'])) {
        $host = (string)$_SERVER['HTTP_HOST'];
    }
    if ($host === '' && isset($_SERVER['SERVER_NAME'])) {
        $host = (string)$_SERVER['SERVER_NAME'];
    }
    $host = strtolower(trim(explode(':', $host)[0]));
    $host = preg_replace('/^www\./', '', $host) ?? $host;

    /* Gecerli bir alan adi degilse bos don; kurulum sihirbazi sorar. */
    if ($host === '' || !preg_match('/^[a-z0-9.\-]+\.[a-z]{2,}$/', $host)) {
        return '';
    }
    return $host;
}

/** Dogrulama ve sifre sifirlama e-postalarinin gonderen adresi. */
function almancapro_noreply_address(): string
{
    $host = almancapro_mail_host();
    return $host === '' ? '' : 'noreply@' . $host;
}

function almancapro_migrations(): int
{
    $applied = 0;

    /* vocabulary.part_of_speech: proper_noun degeri eklendi. */
    if (db_table_exists('vocabulary')) {
        $col = db_row("SHOW COLUMNS FROM vocabulary LIKE 'part_of_speech'");
        if ($col !== null && !str_contains((string)$col['Type'], "'proper_noun'")) {
            db()->exec(
                "ALTER TABLE vocabulary MODIFY part_of_speech
                 ENUM('noun','proper_noun','verb','adjective','adverb','preposition','pronoun',
                      'conjunction','numeral','phrase','article','particle')
                 NOT NULL DEFAULT 'noun'"
            );
            $applied++;
        }
    }

    /* Ulke ve dil adlari 'noun' yerine 'proper_noun' olarak siniflandirildi;
       eski siniflandirmayla kalmis kayitlari temizle (uq_vocab pos'u da kapsiyor). */
    if (db_table_exists('vocabulary')) {
        $reclassified = ['deutschland', 'oesterreich', 'tuerkei', 'schweiz', 'deutsch', 'tuerkisch', 'englisch'];
        $placeholders = implode(',', array_fill(0, count($reclassified), '?'));
        $stale = (int)db_value(
            "SELECT COUNT(*) FROM vocabulary WHERE part_of_speech = 'noun' AND normalized_german IN ($placeholders)",
            $reclassified,
            0
        );
        if ($stale > 0) {
            db_exec(
                "DELETE FROM vocabulary WHERE part_of_speech = 'noun' AND normalized_german IN ($placeholders)",
                $reclassified
            );
            $applied++;
        }
    }

    return $applied;
}

/**
 * @return array{ok:bool, steps:array<int,array{name:string,status:string,detail:string}>, error:string}
 */
function almancapro_run_install(bool $forceContentRefresh = false): array
{
    $steps = [];
    $add = static function (array &$steps, string $name, string $status, string $detail = ''): void {
        $steps[] = ['name' => $name, 'status' => $status, 'detail' => $detail];
    };

    /* 1) Baglanti */
    try {
        db();
        $add($steps, 'Veritabanı bağlantısı', 'ok', 'Bağlantı kuruldu.');
    } catch (Throwable $e) {
        $add($steps, 'Veritabanı bağlantısı', 'error', 'Bağlantı kurulamadı.');
        return ['ok' => false, 'steps' => $steps, 'error' => 'database_unavailable'];
    }

    /* 2) Şema */
    try {
        $created = 0;
        foreach (almancapro_schema_statements() as $table => $sql) {
            $existed = db_table_exists($table);
            db()->exec($sql);
            if (!$existed) {
                $created++;
            }
        }
        $total = count(almancapro_schema_statements());
        $add($steps, 'Tablolar', 'ok', $created > 0 ? $created . ' yeni tablo oluşturuldu (toplam ' . $total . ').' : $total . ' tablo mevcut.');
    } catch (Throwable $e) {
        app_log('Şema oluşturulamadı: ' . $e->getMessage());
        $add($steps, 'Tablolar', 'error', 'Şema oluşturulamadı.');
        return ['ok' => false, 'steps' => $steps, 'error' => 'schema_failed'];
    }

    /* 2b) Şema güncellemeleri (mevcut kurulumlar için, idempotent) */
    try {
        $applied = almancapro_migrations();
        if ($applied > 0) {
            $add($steps, 'Şema güncellemeleri', 'ok', $applied . ' güncelleme uygulandı.');
        }
    } catch (Throwable $e) {
        app_log('Şema güncellemesi uygulanamadı: ' . $e->getMessage());
        $add($steps, 'Şema güncellemeleri', 'warn', 'Bazı güncellemeler uygulanamadı.');
    }

    /* 3) İçerik sürümü kontrolü */
    $installedVersion = db_value("SELECT setting_value FROM site_settings WHERE setting_key = 'content_version'");
    $refresh = $forceContentRefresh || ($installedVersion !== ALMANCAPRO_CONTENT_VERSION);

    try {
        $n = seed_error_categories();
        $add($steps, 'Hata kategorileri', 'ok', $n . ' kategori hazır.');

        $n = seed_skills();
        $add($steps, 'Skill tanımları', 'ok', $n . ' skill hazır.');

        $n = seed_vocabulary();
        $add($steps, 'Kelime hazinesi', 'ok', $n . ' kelime işlendi.');

        $c = seed_curriculum($refresh);
        $add($steps, 'Müfredat', 'ok', sprintf(
            '%d modül, %d ders, %d bölüm, %d alıştırma.',
            $c['modules'], $c['lessons'], $c['sections'], $c['exercises']
        ));

        $n = seed_grammar_topics($refresh);
        $add($steps, 'Dilbilgisi kütüphanesi', 'ok', $n . ' konu hazır.');

        $n = seed_generated_exercises();
        $add($steps, 'Kelime alıştırmaları', 'ok', $n . ' alıştırma üretildi.');

        $tf = seed_true_false_exercises();
        $mt = seed_matching_exercises();
        $add($steps, 'Ek alıştırma türleri', 'ok', $tf . ' doğru/yanlış, ' . $mt . ' eşleştirme.');

        $n = seed_scenarios($refresh);
        $add($steps, 'Senaryolar', 'ok', $n . ' senaryo hazır.');

        $sr = seed_scenario_response_exercises();
        $add($steps, 'Senaryo alıştırmaları', 'ok', $sr . ' senaryo cevabı alıştırması.');

        $n = seed_knowledge_base();
        $add($steps, 'Bilgi tabanı', 'ok', $n . ' kayıt hazır.');

        seed_donation_expenses();
        $add($steps, 'Destek sayfası verileri', 'ok', 'Gider tablosu hazır.');
    } catch (Throwable $e) {
        app_log('İçerik yüklenemedi: ' . $e->getMessage());
        $add($steps, 'İçerik', 'error', 'İçerik yüklenirken hata oluştu.');
        return ['ok' => false, 'steps' => $steps, 'error' => 'seed_failed'];
    }

    /* 4) Varsayılan ayarlar */
    almancapro_seed_settings();
    $add($steps, 'Site ayarları', 'ok', 'Varsayılan ayarlar hazır.');

    /* 5) Yönetici hesabı */
    $adminCount = (int)db_value('SELECT COUNT(*) FROM admins', [], 0);
    if ($adminCount === 0) {
        db_exec(
            'INSERT INTO admins (username, password_hash, display_name, is_active, must_change_password, password_changed_at)
             VALUES (?, ?, ?, 1, 1, UTC_TIMESTAMP())',
            [DEFAULT_ADMIN_USERNAME, password_hash_app(DEFAULT_ADMIN_PASSWORD), 'Yönetici']
        );
        $add($steps, 'Yönetici hesabı', 'ok', 'Varsayılan yönetici oluşturuldu. İlk girişte şifre değiştirilmelidir.');
    } else {
        $add($steps, 'Yönetici hesabı', 'ok', $adminCount . ' yönetici mevcut.');
    }

    /* 6) Kurulum kilidi */
    setting_set('content_version', ALMANCAPRO_CONTENT_VERSION);
    setting_set('install_completed', '1');
    setting_set('installed_at', now_utc());
    setting_set('app_version', APP_VERSION);
    $add($steps, 'Kurulum kilidi', 'ok', 'Kurulum tamamlandı olarak işaretlendi.');

    /* Dosya kilidi (yazilabilirse) */
    $lockFile = __DIR__ . '/.install.lock';
    if (!file_exists($lockFile)) {
        @file_put_contents($lockFile, 'installed ' . now_utc() . "\n");
    }

    settings_all(true);
    return ['ok' => true, 'steps' => $steps, 'error' => ''];
}

/** Varsayilan site ayarlarini olusturur (mevcut degerleri ezmez). */
function almancapro_seed_settings(): void
{
    $defaults = [
        'site_name'            => APP_NAME,
        'site_tagline'         => 'Almancayı Gerçekten Öğren',
        'site_url'             => '',
        'site_email'           => '',
        'maintenance_mode'     => '0',
        'registration_open'    => '1',
        'default_daily_goal'   => '30',
        'mastery_threshold'    => (string)MASTERY_THRESHOLD,
        'unlock_threshold'     => (string)MASTERY_UNLOCK_THRESHOLD,
        'default_timezone'     => APP_DEFAULT_TIMEZONE,

        /* SMTP: Plesk'te posta alan adiyla ayni sunucuda durur.
           Alan adi ve noreply@ adresi kurulumda otomatik doldurulur;
           yoneticinin girmesi gereken tek deger posta kutusu sifresidir. */
        'smtp_host'            => almancapro_mail_host(),
        'smtp_port'            => '465',
        'smtp_username'        => almancapro_noreply_address(),
        'smtp_encryption'      => 'ssl',
        'mail_from'            => almancapro_noreply_address(),
        'mail_from_name'       => APP_NAME,

        'telegram_bot_username'=> '',
        'telegram_webhook_set' => '0',
        'telegram_last_update' => '',
        'telegram_last_error'  => '',

        'ai_enabled'           => '0',
        'ai_endpoint'          => '',
        'ai_model'             => '',
        'ai_system_prompt'     => almancapro_default_ai_prompt(),
        'ai_max_tokens'        => '900',
        'ai_daily_limit'       => '40',

        'donations_enabled'    => '1',
        'donation_recipient'   => '',
        'donation_bank'        => '',
        'donation_iban'        => '',
        'donation_mask_name'   => '1',
        'donation_template'    => 'ALMANCAPRO BAGIS · {AD_SOYAD} · {TUTAR} TL · {KOD}',

        'cron_last_run'        => '',
        'cron_last_summary'    => '',
    ];

    foreach ($defaults as $key => $value) {
        $exists = db_value('SELECT COUNT(*) FROM site_settings WHERE setting_key = ?', [$key], 0);
        if ((int)$exists === 0) {
            db_exec('INSERT INTO site_settings (setting_key, setting_value, is_secret) VALUES (?, ?, 0)', [$key, $value]);
        }
    }

    /* Sirlar: yalnizca yoksa bos olarak olusturulur, asla ekrana basilmaz. */
    foreach (['smtp_password', 'telegram_bot_token', 'ai_api_key', 'cron_secret'] as $secret) {
        $exists = db_value('SELECT COUNT(*) FROM site_settings WHERE setting_key = ?', [$secret], 0);
        if ((int)$exists === 0) {
            $value = $secret === 'cron_secret' ? random_token(24) : '';
            db_exec('INSERT INTO site_settings (setting_key, setting_value, is_secret) VALUES (?, ?, 1)', [$secret, $value]);
        }
    }
    /* Alan adina bagli varsayilanlar: daha once bos kaldiysa simdi doldur.
       (Ilk kurulum CLI'dan yapildiysa alan adi bilinmiyor olabilir.) */
    $noreply = almancapro_noreply_address();
    if ($noreply !== '') {
        foreach (['smtp_host' => almancapro_mail_host(),
                  'smtp_username' => $noreply,
                  'mail_from' => $noreply] as $key => $value) {
            $current = db_value('SELECT setting_value FROM site_settings WHERE setting_key = ?', [$key]);
            if ($current === null || trim((string)$current) === '') {
                db_exec('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?', [$value, $key]);
            }
        }
    }

    settings_all(true);
}

function almancapro_default_ai_prompt(): string
{
    return implode("\n", [
        'Sen AlmancaPro adlı platformun Almanca öğretmenisin. Öğrencin ana dili Türkçe olan bir yetişkin.',
        'Kurallar:',
        '- Açıklamalarını her zaman Türkçe yap, örnekleri Almanca ver.',
        '- Öğrencinin CEFR seviyesine uygun konuş; seviyesinin çok üstünde konu açma.',
        '- Bir isimden bahsediyorsan artikelini ve çoğulunu birlikte ver (der Tisch – die Tische).',
        '- Bir fiilden bahsediyorsan önemli biçimlerini ver (fahren – fährt – fuhr – ist gefahren).',
        '- Bir edattan bahsediyorsan istediği hali belirt (mit + Dativ).',
        '- Dilbilgisi kuralının NEDENİNİ açıkla, sadece kuralı söyleme.',
        '- Türkçe ile farkı varsa bunu açıkça belirt.',
        '- Öğrencinin cümlesinde hata varsa önce doğrusunu yaz, sonra hatanın türünü açıkla.',
        '- Emin olmadığın bir kuralı uydurma; emin değilsen bunu söyle.',
        '- Cevabın sonunda öğrencinin hatasına yönelik 1-2 soruluk kısa bir mini test ver.',
        '- Hakaret etme, küçümseme, korkutma. Disiplinli ama saygılı ol.',
        '- Hukuki, tıbbi veya finansal tavsiye verme; yalnızca dil öğret.',
    ]);
}
