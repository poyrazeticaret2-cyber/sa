<?php
/**
 * AlmancaPro - Kurulum teshis araci.
 *
 * Bu dosya BILEREK eski PHP soz dizimiyle yazilmistir (PHP 5.4+ calisir).
 * Boylece sunucuda PHP surumu eski olsa bile calisir ve sorunu soyler.
 * Hicbir uygulama dosyasini require etmez, hicbir sir yazdirmaz.
 *
 * Kullanim: https://alanadiniz.com/tani.php
 * Sorun cozuldukten sonra bu dosyayi SUNUCUDAN SILIN.
 */

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$ok = array();
$warn = array();
$err = array();

/* ---------- 1) PHP surumu ---------- */
if (version_compare(PHP_VERSION, '8.2.0', '>=')) {
    $ok[] = 'PHP surumu: ' . PHP_VERSION;
} elseif (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    $err[] = 'PHP surumu ' . PHP_VERSION . ' - AlmancaPro 8.2 veya uzeri ister. '
        . 'Plesk > PHP Ayarlari bolumunden 8.2+ secin. (500 hatasinin en sik nedeni budur.)';
} else {
    $err[] = 'PHP surumu ' . PHP_VERSION . ' COK ESKI. Uygulama dosyalari bu surumde ayristirilamaz '
        . 've sunucu 500 doner. Plesk > PHP Ayarlari > PHP 8.2 veya uzeri secin.';
}
$ok[] = 'PHP calisma bicimi (SAPI): ' . PHP_SAPI;

/* ---------- 2) Eklentiler ---------- */
$required = array('pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl');
$optional = array('curl', 'zip', 'intl');
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        $ok[] = 'PHP eklentisi yuklu: ' . $ext;
    } else {
        $err[] = 'ZORUNLU PHP eklentisi eksik: ' . $ext;
    }
}
foreach ($optional as $ext) {
    if (extension_loaded($ext)) {
        $ok[] = 'PHP eklentisi yuklu: ' . $ext . ' (istege bagli)';
    } else {
        $warn[] = 'Istege bagli eklenti yok: ' . $ext
            . ($ext === 'curl' ? ' - Telegram ve AI ozellikleri calismaz, geri kalan her sey calisir.' : '');
    }
}

/* ---------- 3) Kaynak dosyalar ---------- */
$mustExist = array('config.php', 'db.php', 'functions.php', 'bootstrap.php', 'schema.php',
                   'seed.php', 'installer.php', 'index.php', 'layout.php',
                   'assets/css/main.css', 'assets/js/app.js');
$missing = array();
foreach ($mustExist as $f) {
    if (!file_exists(__DIR__ . '/' . $f)) {
        $missing[] = $f;
    }
}
if (count($missing) === 0) {
    $ok[] = 'Butun cekirdek dosyalar yerinde (' . count($mustExist) . ' kontrol edildi).';
} else {
    $err[] = 'Eksik dosya(lar): ' . implode(', ', $missing)
        . ' - ZIP tam ayiklanmamis olabilir. Dosyalarin httpdocs altinda DOGRUDAN durdugundan emin olun '
        . '(fazladan bir AlmancaPro/ klasoru olmamali).';
}

/* ---------- 4) Soz dizimi denetimi (PHP 8.2 ozellikleri) ---------- */
$syntaxNote = '';
if (function_exists('token_get_all') && version_compare(PHP_VERSION, '8.0.0', '<')) {
    $syntaxNote = 'PHP 8 oncesi surumde uygulama dosyalari ayristirilamaz.';
}

/* ---------- 5) .htaccess ---------- */
if (file_exists(__DIR__ . '/.htaccess')) {
    $ht = file_get_contents(__DIR__ . '/.htaccess');
    $risky = array();
    foreach (preg_split('/\r?\n/', $ht) as $line) {
        $t = ltrim($line);
        if ($t === '' || $t[0] === '#') { continue; }
        if (preg_match('/^(Options|php_flag|php_value|php_admin_value|php_admin_flag)\b/i', $t, $mm)) {
            $risky[] = trim($t);
        }
    }
    if (count($risky) > 0) {
        $warn[] = '.htaccess icinde sunucu tarafindan reddedilebilecek satir(lar) var: "'
            . implode('", "', $risky) . '". Bazi Plesk kurulumlarinda bunlar TUM istekleri 500 yapar. '
            . 'Test icin .htaccess adini gecici olarak .htaccess.bak yapin; hata kayboluyorsa neden budur.';
    } else {
        $ok[] = '.htaccess mevcut ve riskli yapilandirma satiri icermiyor.';
    }
} else {
    $ok[] = '.htaccess yok (nginx ortaminda normaldir).';
}

/* ---------- 6) Limitler ---------- */
$maxTime = (int)ini_get('max_execution_time');
if ($maxTime > 0 && $maxTime < 120) {
    $warn[] = 'max_execution_time = ' . $maxTime . ' sn. Ilk kurulum ~10.000 satir yazar; '
        . 'yavas sunucuda bu sure yetmeyip 500 verebilir. Kurulumu /install.php uzerinden yapin.';
} else {
    $ok[] = 'max_execution_time = ' . ($maxTime === 0 ? 'sinirsiz' : $maxTime . ' sn');
}
$ok[] = 'memory_limit = ' . ini_get('memory_limit');

/* ---------- 7) Yazma izni ---------- */
if (is_writable(__DIR__)) {
    $ok[] = 'Kok dizin yazilabilir (kurulum kilidi olusturulabilir).';
} else {
    $warn[] = 'Kok dizin yazilamiyor. Kurulum kilidi dosyasi olusturulamaz; '
        . 'kurulum yine de veritabanina yazilir.';
}

/* ---------- 8) Veritabani ---------- */
$dbInfo = array();
$dbHost = 'localhost';
$dbPort = 3306;
$dbName = '';
$dbUser = '';
$dbPass = '';

$cfg = @file_get_contents(__DIR__ . '/config.php');
if ($cfg !== false) {
    if (preg_match("/define\('DB_HOST',\s*'([^']*)'/", $cfg, $m)) { $dbHost = $m[1]; }
    if (preg_match("/define\('DB_PORT',\s*(\d+)/", $cfg, $m))     { $dbPort = (int)$m[1]; }
    if (preg_match("/define\('DB_NAME',\s*'([^']*)'/", $cfg, $m)) { $dbName = $m[1]; }
    if (preg_match("/define\('DB_USER',\s*'([^']*)'/", $cfg, $m)) { $dbUser = $m[1]; }
    if (preg_match("/define\('DB_PASSWORD',\s*'(.*)'\)/", $cfg, $m)) { $dbPass = $m[1]; }
}
$activeConfig = 'config.php';
if (file_exists(__DIR__ . '/config.local.php')) {
    /* config.php bu dosyayi once yukler, yani ETKIN degerler buradadir. */
    $local = @file_get_contents(__DIR__ . '/config.local.php');
    if ($local !== false) {
        if (preg_match("/define\('DB_HOST',\s*'([^']*)'/", $local, $m)) { $dbHost = $m[1]; }
        if (preg_match("/define\('DB_PORT',\s*(\d+)/", $local, $m))     { $dbPort = (int)$m[1]; }
        if (preg_match("/define\('DB_NAME',\s*'([^']*)'/", $local, $m)) { $dbName = $m[1]; }
        if (preg_match("/define\('DB_USER',\s*'([^']*)'/", $local, $m)) { $dbUser = $m[1]; }
        if (preg_match("/define\('DB_PASSWORD',\s*'(.*)'\)/", $local, $m)) { $dbPass = $m[1]; }
        $activeConfig = 'config.local.php';
    }
    $ok[] = 'config.local.php bulundu ve config.php yerine ETKIN. '
        . 'Bu dosya kurulum sihirbazinin kaydettigi sunucuya ozel veritabani ayarlarini tutar. '
        . 'Baska bir veritabanina baglanmak isterseniz dosyayi silip siteyi yeniden acin.';
}
$ok[] = 'Etkin veritabani yapilandirmasi: ' . $activeConfig;

if ($dbName === '') {
    $err[] = 'config.php icinden veritabani adi okunamadi.';
} elseif (!extension_loaded('pdo_mysql')) {
    $err[] = 'pdo_mysql olmadan veritabani testi yapilamaz.';
} else {
    try {
        $dsn = 'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $dbUser, $dbPass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        $ok[] = 'Veritabani baglantisi BASARILI (' . $dbName . ' @ ' . $dbHost . ').';

        $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
        $ok[] = 'Veritabani surumu: ' . $ver;

        $charset = $pdo->query('SELECT @@character_set_database')->fetchColumn();
        if (strpos($charset, 'utf8mb4') === 0) {
            $ok[] = 'Karakter seti: ' . $charset;
        } else {
            $err[] = 'Karakter seti ' . $charset . ' - utf8mb4 olmali. '
                . 'Plesk > Veritabanlari bolumunden duzeltin.';
        }

        /* CREATE yetkisi gercekten var mi? Kurulum bu yetki olmadan tamamlanamaz. */
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS almancapro_yetki_testi (id INT) ENGINE=InnoDB');
            $pdo->exec('DROP TABLE IF EXISTS almancapro_yetki_testi');
            $ok[] = 'Veritabani kullanicisinin CREATE/DROP yetkisi var.';
        } catch (Exception $ex) {
            $err[] = 'Veritabani kullanicisinin TABLO OLUSTURMA yetkisi YOK (' . $ex->getCode() . '). '
                . 'Kurulum bu yuzden tamamlanamaz ve sayfalar hata verir. '
                . 'Plesk > Veritabanlari > Kullanicilar bolumunden bu kullaniciya tam yetki verin '
                . 'veya veritabanina "Tum ayricaliklar" atayin.';
        }

        $st = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()");
        $tableCount = (int)$st->fetchColumn();
        if ($tableCount === 0) {
            $err[] = 'Veritabani BOS (0 tablo). Kurulum hic yapilmamis. '
                . 'https://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'alanadiniz.com')
                . '/install.php adresini acip "KURULUMU BASLAT" deyin.';
        } else {
            /* Beklenen tablolarin hepsi var mi? */
            $expected = array('site_settings','admins','users','modules','lessons','lesson_sections',
                'lesson_prerequisites','skills','vocabulary','exercises','exercise_options',
                'grammar_topics','error_categories','user_skill_mastery','user_vocabulary_mastery',
                'study_sessions','session_items','exercise_attempts','daily_plans','daily_plan_items',
                'donations','donation_expenses','scenarios','scenario_turns','scenario_options',
                'knowledge_base','notification_queue','telegram_connections','questions','answers');
            $have = array();
            $rs = $pdo->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE()");
            while ($r = $rs->fetch(PDO::FETCH_NUM)) { $have[strtolower($r[0])] = true; }
            $lack = array();
            foreach ($expected as $t) {
                if (!isset($have[$t])) { $lack[] = $t; }
            }
            if (count($lack) > 0) {
                $err[] = 'Veritabaninda ' . $tableCount . ' tablo var ama ' . count($lack)
                    . ' tanesi EKSIK: ' . implode(', ', array_slice($lack, 0, 8))
                    . (count($lack) > 8 ? ' ...' : '')
                    . '  >>> Kurulum yarim kalmis. /install.php adresini acip kurulumu tamamlayin. '
                    . '(Bu, bazi sayfalarin acilip bazilarinin 500 vermesinin tipik nedenidir.)';
            } else {
                $ok[] = 'Veritabaninda ' . $tableCount . ' tablo var; beklenen tablolarin hepsi mevcut.';
            }

            /* Kurulum bayragi */
            if (isset($have['site_settings'])) {
                try {
                    $q = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'install_completed'");
                    $q->execute();
                    $flag = $q->fetchColumn();
                    if ($flag === '1') {
                        $ok[] = 'Kurulum tamamlandi olarak isaretli.';
                    } else {
                        $err[] = 'Kurulum TAMAMLANMAMIS olarak isaretli (install_completed = '
                            . ($flag === false ? 'yok' : var_export($flag, true)) . '). '
                            . '/install.php adresini acin.';
                    }
                } catch (Exception $ex) {
                    $warn[] = 'Kurulum bayragi okunamadi: ' . $ex->getMessage();
                }
            }

            if (count($lack) === 0) {
                try {
                    $c = (int)$pdo->query('SELECT COUNT(*) FROM lessons')->fetchColumn();
                    $v = (int)$pdo->query('SELECT COUNT(*) FROM vocabulary')->fetchColumn();
                    $e = (int)$pdo->query('SELECT COUNT(*) FROM exercises')->fetchColumn();
                    $a = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
                    $ok[] = 'Icerik: ' . $c . ' ders, ' . $v . ' kelime, ' . $e . ' alistirma, ' . $a . ' yonetici.';
                    if ($c === 0 || $e === 0) {
                        $err[] = 'Tablolar var ama EGITIM ICERIGI YUKLENMEMIS (' . $c . ' ders, ' . $e
                            . ' alistirma). /install.php adresini acip kurulumu tamamlayin.';
                    }
                    if ($a === 0) {
                        $warn[] = 'Yonetici hesabi yok. /install.php adresini acin; hesap otomatik olusturulur.';
                    }
                } catch (Exception $ex) {
                    $warn[] = 'Icerik sayilari okunamadi: ' . $ex->getMessage();
                }
            }
        }
    } catch (Exception $ex) {
        $code = $ex->getCode();
        $hint = 'Plesk > Veritabanlari bolumunde bu ad ve kullanicinin var oldugunu dogrulayin.';
        if ($code == 1045) {
            $hint = 'Kullanici adi veya sifre yanlis. Plesk > Veritabanlari > Kullanicilar bolumunden sifreyi yenileyin '
                  . 've config.php icindeki DB_PASSWORD degerini guncelleyin.';
        } elseif ($code == 1049) {
            $hint = 'Bu adda bir veritabani YOK. Plesk > Veritabanlari > Veritabani Ekle ile "' . $dbName . '" olusturun.';
        } elseif ($code == 2002) {
            $hint = 'Veritabani sunucusuna ulasilamiyor. DB_HOST degeri "localhost" olmali.';
        }
        $err[] = 'Veritabani baglantisi BASARISIZ (kod: ' . $code . '). ' . $hint;
    }
}

/* ---------- 9) AlmancaPro hata gunlugu ---------- */
$appLog = '';
$appLogFiles = array(
    __DIR__ . '/storage/almancapro-log.php',
    sys_get_temp_dir() . '/almancapro-log.php',
);
foreach ($appLogFiles as $lf) {
    if (@is_readable($lf)) {
        $lines = @file($lf);
        if ($lines && count($lines) > 1) {
            /* Ilk satir koruma satiridir, gosterme. */
            if (count($lines) && strpos($lines[0], '<?php exit;') === 0) {
                array_shift($lines);
            }
            $appLog = count($lines)
                ? htmlspecialchars(implode('', array_slice($lines, -25)), ENT_QUOTES, 'UTF-8')
                : '';
            $ok[] = 'Uygulama hata gunlugu bulundu: ' . str_replace(__DIR__ . '/', '', $lf)
                . ' (' . count($lines) . ' satir)';
            break;
        }
    }
}
if ($appLog === '') {
    $ok[] = 'Uygulama hata gunlugunde kayit yok.';
}

/* ---------- 10) Son PHP hatasi ---------- */
$lastError = '';
$logCandidates = array(
    dirname(dirname(__DIR__)) . '/logs/error_log',
    dirname(__DIR__) . '/logs/error_log',
    ini_get('error_log'),
);
foreach ($logCandidates as $lg) {
    if ($lg && is_string($lg) && @is_readable($lg)) {
        $lines = @file($lg);
        if ($lines && count($lines) > 1) {
            $tail = array_slice($lines, -12);
            $lastError = htmlspecialchars(implode('', $tail), ENT_QUOTES, 'UTF-8');
            break;
        }
    }
}

function ap_box($title, $items, $color, $icon) {
    if (count($items) === 0) { return; }
    echo '<h2 style="font-size:16px;margin:26px 0 10px;color:' . $color . '">' . $icon . ' ' . $title
       . ' (' . count($items) . ')</h2><ul style="margin:0;padding-left:20px;line-height:1.7">';
    foreach ($items as $i) {
        echo '<li style="margin-bottom:6px">' . htmlspecialchars($i, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul>';
}
?><!doctype html>
<html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>AlmancaPro Teşhis</title>
<style>
body{font:15px/1.6 -apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;color:#111110;
     background:#F4F2ED;margin:0;padding:24px}
.wrap{max-width:820px;margin:0 auto;background:#fff;border:1px solid #DCD8CF;padding:28px}
h1{font-size:22px;margin:0 0 6px}
.sub{color:#77746B;font-size:14px;margin:0 0 20px}
pre{background:#FAF9F6;border:1px solid #DCD8CF;padding:12px;overflow-x:auto;font-size:12px}
.note{background:#F6EDD4;border-left:3px solid #B8860B;padding:12px 16px;margin-top:24px;font-size:14px}
</style></head><body><div class="wrap">
<h1>AlmancaPro · Kurulum Teşhisi</h1>
<p class="sub">Sunucu: <?php echo htmlspecialchars(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '-', ENT_QUOTES, 'UTF-8'); ?>
 · Tarih: <?php echo date('d.m.Y H:i'); ?></p>

<?php
ap_box('ÇÖZÜLMESİ GEREKENLER', $err, '#C0271F', '✕');
ap_box('UYARILAR', $warn, '#A8621A', '●');
ap_box('UYGUN', $ok, '#1E7A4C', '✓');
?>

<?php if ($appLog !== ''): ?>
  <h2 style="font-size:16px;margin:26px 0 10px">AlmancaPro hata günlüğü (son kayıtlar)</h2>
  <pre><?php echo $appLog; ?></pre>
<?php endif; ?>

<?php if ($lastError !== ''): ?>
  <h2 style="font-size:16px;margin:26px 0 10px">Sunucu hata günlüğü (son satırlar)</h2>
  <pre><?php echo $lastError; ?></pre>
<?php endif; ?>

<div class="note">
  <strong>500 hatası hâlâ sürüyorsa sırayla deneyin:</strong><br>
  1) Plesk &gt; PHP Ayarları &gt; PHP sürümünü <strong>8.2 veya üzeri</strong> yapın.<br>
  2) Dosya yöneticisinde <code>.htaccess</code> adını geçici olarak <code>.htaccess.bak</code> yapın ve siteyi yenileyin.
     Hata kayboluyorsa sorun <code>.htaccess</code> içindeki <code>Options</code> satırıdır.<br>
  3) Yukarıdaki veritabanı satırlarını kontrol edin.<br>
  4) Kurulumu ana sayfa yerine <code>/install.php</code> üzerinden başlatın (zaman aşımına takılmaz).<br><br>
  <strong>Sorun çözülünce bu dosyayı (<code>tani.php</code>) sunucudan silin.</strong>
</div>
</div></body></html>
