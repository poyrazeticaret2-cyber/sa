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
 * Varsayilan yonetici hesabini olusturur (yoksa).
 * Sifre asla duz metin saklanmaz; ilk giriste degistirilmesi zorunludur.
 */
function almancapro_seed_admin(): string
{
    $adminCount = (int)db_value('SELECT COUNT(*) FROM admins', [], 0);
    if ($adminCount > 0) {
        return $adminCount . ' yönetici mevcut.';
    }
    db_exec(
        'INSERT INTO admins (username, password_hash, display_name, is_active, must_change_password, password_changed_at)
         VALUES (?, ?, ?, 1, 1, UTC_TIMESTAMP())',
        [DEFAULT_ADMIN_USERNAME, password_hash_app(DEFAULT_ADMIN_PASSWORD), 'Yönetici']
    );
    return 'Varsayılan yönetici oluşturuldu. İlk girişte şifre değiştirilmelidir.';
}

/* ==================================================================
 * Parcali (adim adim) kurulum
 *
 * Ilk kurulum ~10.000 satir yazar. Paylasimli sunucularda bu is tek
 * istekte bitmeyip PHP zaman asimina takilabilir; o zaman kurulum yarim
 * kalir ve sayfalar "eksik tablo" hatasi verir.
 *
 * Cozum: is kucuk adimlara bolunur. Her istek YALNIZCA BIR adim calistirir
 * ve nerede kalindigi kaydedilir. Zaman asimi bir adimi keserse bile
 * bir sonraki istek kaldigi yerden devam eder. Butun adimlar idempotenttir.
 * ================================================================== */

/**
 * Kurulum adimlari, sirasiyla.
 *
 * @return array<int, array{key:string, label:string, run:callable}>
 */
function almancapro_install_plan(): array
{
    $plan = [];

    $plan[] = ['key' => 'schema', 'label' => 'Tablolar', 'run' => static function (): string {
        $created = 0;
        foreach (almancapro_schema_statements() as $table => $sql) {
            $existed = db_table_exists($table);
            db()->exec($sql);
            if (!$existed) {
                $created++;
            }
        }
        $total = count(almancapro_schema_statements());
        return $created > 0
            ? $created . ' yeni tablo oluşturuldu (toplam ' . $total . ').'
            : $total . ' tablo mevcut.';
    }];

    $plan[] = ['key' => 'migrations', 'label' => 'Şema güncellemeleri', 'run' => static function (): string {
        $n = almancapro_migrations();
        return $n > 0 ? $n . ' güncelleme uygulandı.' : 'Güncelleme gerekmiyor.';
    }];

    $plan[] = ['key' => 'settings', 'label' => 'Site ayarları', 'run' => static function (): string {
        almancapro_seed_settings();
        return 'Varsayılan ayarlar hazır.';
    }];

    $plan[] = ['key' => 'errors', 'label' => 'Hata kategorileri', 'run' => static function (): string {
        return seed_error_categories() . ' kategori hazır.';
    }];

    $plan[] = ['key' => 'skills', 'label' => 'Skill tanımları', 'run' => static function (): string {
        return seed_skills() . ' skill hazır.';
    }];

    foreach (cefr_levels() as $level) {
        $plan[] = [
            'key'   => 'vocab_' . strtolower($level),
            'label' => 'Kelime hazinesi · ' . $level,
            'run'   => static function () use ($level): string {
                return seed_vocabulary($level) . ' kelime işlendi.';
            },
        ];
    }

    foreach (cefr_levels() as $level) {
        $plan[] = [
            'key'   => 'curriculum_' . strtolower($level),
            'label' => 'Müfredat · ' . $level,
            'run'   => static function () use ($level): string {
                $c = seed_curriculum(false, $level);
                return sprintf('%d modül, %d ders, %d bölüm, %d alıştırma.',
                    $c['modules'], $c['lessons'], $c['sections'], $c['exercises']);
            },
        ];
    }

    $plan[] = ['key' => 'grammar', 'label' => 'Dilbilgisi kütüphanesi', 'run' => static function (): string {
        return seed_grammar_topics(false) . ' konu hazır.';
    }];

    /* Kelime alistirmalari en agir adim: 60 kelimelik gruplara bolunur.
       seed_generated_exercises() yalnizca alistirmasi olmayan kelimeleri
       isledigi icin tekrar cagrildikca kaldigi yerden devam eder. */
    for ($i = 1; $i <= 12; $i++) {
        $plan[] = [
            'key'   => 'genex_' . $i,
            'label' => 'Kelime alıştırmaları (' . $i . '/12)',
            'run'   => static function (): string {
                $n = seed_generated_exercises(60);
                return $n > 0 ? $n . ' alıştırma üretildi.' : 'Tamamlandı.';
            },
        ];
    }

    $plan[] = ['key' => 'truefalse', 'label' => 'Doğru/yanlış alıştırmaları', 'run' => static function (): string {
        return seed_true_false_exercises() . ' alıştırma hazır.';
    }];

    $plan[] = ['key' => 'matching', 'label' => 'Eşleştirme alıştırmaları', 'run' => static function (): string {
        return seed_matching_exercises() . ' alıştırma hazır.';
    }];

    $plan[] = ['key' => 'scenarios', 'label' => 'Senaryolar', 'run' => static function (): string {
        return seed_scenarios(false) . ' senaryo hazır.';
    }];

    $plan[] = ['key' => 'scenario_ex', 'label' => 'Senaryo alıştırmaları', 'run' => static function (): string {
        return seed_scenario_response_exercises() . ' alıştırma hazır.';
    }];

    $plan[] = ['key' => 'kb', 'label' => 'Bilgi bankası', 'run' => static function (): string {
        return seed_knowledge_base() . ' kayıt hazır.';
    }];

    $plan[] = ['key' => 'expenses', 'label' => 'Destek sayfası verileri', 'run' => static function (): string {
        seed_donation_expenses();
        return 'Hazır.';
    }];

    $plan[] = ['key' => 'admin', 'label' => 'Yönetici hesabı', 'run' => static function (): string {
        return almancapro_seed_admin();
    }];

    $plan[] = ['key' => 'finish', 'label' => 'Kurulum kilidi', 'run' => static function (): string {
        setting_set('content_version', ALMANCAPRO_CONTENT_VERSION);
        setting_set('app_version', APP_VERSION);
        setting_set('installed_at', now_utc());
        setting_set('install_completed', '1');
        @file_put_contents(APP_ROOT . '/.install.lock', 'installed ' . now_utc());
        settings_all(true);
        return 'Kurulum tamamlandı.';
    }];

    return $plan;
}

/** Kurulumun nerede kaldigini okur. */
function almancapro_install_position(): int
{
    if (!db_table_exists('site_settings')) {
        return 0;
    }
    $v = db_value("SELECT setting_value FROM site_settings WHERE setting_key = 'install_position'");
    return $v === null ? 0 : max(0, (int)$v);
}

/** Kurulumun nerede kaldigini kaydeder. */
function almancapro_install_position_set(int $pos): void
{
    if (!db_table_exists('site_settings')) {
        return;
    }
    db_exec(
        "INSERT INTO site_settings (setting_key, setting_value, is_secret) VALUES ('install_position', ?, 0)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
        [(string)$pos]
    );
}

/**
 * TEK bir kurulum adimini calistirir.
 *
 * @return array{ok:bool, done:bool, index:int, total:int, percent:int,
 *                label:string, detail:string, error:string}
 */
function almancapro_install_chunk(): array
{
    $plan = almancapro_install_plan();
    $total = count($plan);
    $pos = almancapro_install_position();

    if ($pos >= $total) {
        return ['ok' => true, 'done' => true, 'index' => $total, 'total' => $total,
                'percent' => 100, 'label' => 'Tamamlandı', 'detail' => '', 'error' => ''];
    }

    $step = $plan[$pos];
    try {
        $detail = (string)($step['run'])();
    } catch (Throwable $e) {
        app_log('Kurulum adimi basarisiz (' . $step['key'] . '): ' . $e->getMessage());
        return ['ok' => false, 'done' => false, 'index' => $pos, 'total' => $total,
                'percent' => (int)round(100 * $pos / $total), 'label' => $step['label'],
                'detail' => '', 'error' => almancapro_install_hint($e)];
    }

    almancapro_install_position_set($pos + 1);

    return ['ok' => true, 'done' => ($pos + 1) >= $total, 'index' => $pos + 1, 'total' => $total,
            'percent' => (int)round(100 * ($pos + 1) / $total),
            'label' => $step['label'], 'detail' => $detail, 'error' => ''];
}

/** Veritabani hatasini yoneticinin anlayacagi bir oneriye cevirir. */
function almancapro_install_hint(Throwable $e): string
{
    $code = (string)$e->getCode();
    $msg = $e->getMessage();

    if (str_contains($msg, 'command denied') || str_contains($msg, '1142')) {
        return 'Veritabanı kullanıcısının tablo oluşturma yetkisi yok. '
             . 'Plesk > Veritabanları > Kullanıcılar bölümünden bu kullanıcıya tam yetki verin.';
    }
    if ($code === '1045') {
        return 'Veritabanı kullanıcı adı veya şifresi hatalı.';
    }
    if ($code === '1049') {
        return 'Bu adda bir veritabanı yok. Plesk > Veritabanları bölümünden oluşturun.';
    }
    if ($code === '2002' || str_contains($msg, 'gone away') || str_contains($msg, 'Lost connection')) {
        return 'Veritabanı bağlantısı koptu. Sayfayı yenileyin; kurulum kaldığı yerden devam eder.';
    }
    if (str_contains($msg, 'Disk full') || str_contains($msg, 'No space')) {
        return 'Sunucuda disk alanı dolu.';
    }
    return 'Veritabanı hatası (' . $code . '). Sayfayı yenileyin; kurulum kaldığı yerden devam eder.';
}

/**
 * @return array{ok:bool, steps:array<int,array{name:string,status:string,detail:string}>, error:string}
 */
function almancapro_run_install(bool $forceContentRefresh = false): array
{
    $steps = [];

    /* 1) Baglanti */
    try {
        db();
        $steps[] = ['name' => 'Veritabanı bağlantısı', 'status' => 'ok', 'detail' => 'Bağlantı kuruldu.'];
    } catch (Throwable $e) {
        $steps[] = ['name' => 'Veritabanı bağlantısı', 'status' => 'error', 'detail' => 'Bağlantı kurulamadı.'];
        return ['ok' => false, 'steps' => $steps, 'error' => 'database_unavailable'];
    }

    /* Icerik surumu degistiyse veya zorlanıyorsa bastan kur. */
    $installedVersion = db_table_exists('site_settings')
        ? db_value("SELECT setting_value FROM site_settings WHERE setting_key = 'content_version'")
        : null;
    if ($forceContentRefresh || ($installedVersion !== null && $installedVersion !== ALMANCAPRO_CONTENT_VERSION)) {
        almancapro_install_position_set(0);
    }

    /* 2) Adimlari sirayla calistir. Her adim kendi basina idempotenttir;
          yarida kesilse bile bir sonraki calisma kaldigi yerden devam eder. */
    $guard = count(almancapro_install_plan()) + 5;
    while ($guard-- > 0) {
        $res = almancapro_install_chunk();
        if (!$res['ok']) {
            $steps[] = ['name' => $res['label'], 'status' => 'error', 'detail' => $res['error']];
            return ['ok' => false, 'steps' => $steps, 'error' => 'step_failed:' . $res['label']];
        }
        if ($res['detail'] !== '') {
            $steps[] = ['name' => $res['label'], 'status' => 'ok', 'detail' => $res['detail']];
        }
        if ($res['done']) {
            break;
        }
    }

    if (!is_installed_fresh()) {
        return ['ok' => false, 'steps' => $steps, 'error' => 'incomplete'];
    }

    return ['ok' => true, 'steps' => $steps, 'error' => ''];
}

/** Onbellege takilmadan kurulum durumunu okur. */
function is_installed_fresh(): bool
{
    if (!db_table_exists('site_settings')) {
        return false;
    }
    return db_value("SELECT setting_value FROM site_settings WHERE setting_key = 'install_completed'") === '1';
}

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
