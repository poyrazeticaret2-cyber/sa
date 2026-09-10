<?php
/**
 * AlmancaPro - Merkezi yapilandirma.
 *
 * Bu dosya dogrudan cagrildiginda hicbir cikti uretmez ve hicbir deger sizdirmaz.
 */
declare(strict_types=1);

if (!defined('ALMANCAPRO')) {
    define('ALMANCAPRO', true);
}

/* Dogrudan erisim engeli: hicbir sey yazdirmadan kapan. */
if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/* ------------------------------------------------------------------
 * Veritabani (Plesk uzerinde hazir olusturulmus hesap)
 *
 * Bu degerler sabittir. Yerel gelistirme icin bu dosyanin yanina
 * config.local.php eklenip ayni sabitler onceden tanimlanabilir;
 * dosya yoksa asagidaki uretim degerleri kullanilir.
 * ------------------------------------------------------------------ */
if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
if (!defined('DB_HOST'))    define('DB_HOST', 'localhost');
if (!defined('DB_PORT'))    define('DB_PORT', 3306);
if (!defined('DB_NAME'))    define('DB_NAME', 'lxsadauz_almanca');
if (!defined('DB_USER'))    define('DB_USER', 'lxsadauz_almanca');
if (!defined('DB_PASSWORD'))define('DB_PASSWORD', 'ZX5sr#1e6nZIjr$o');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/* ------------------------------------------------------------------
 * Uygulama sabitleri
 * ------------------------------------------------------------------ */
define('APP_NAME', 'AlmancaPro');
define('APP_TAGLINE', 'Almancayı Gerçekten Öğren');
define('APP_VERSION', '1.0.0');
define('APP_ROOT', __DIR__);
define('APP_DEFAULT_TIMEZONE', 'Europe/Istanbul');

/* Varsayilan admin (yalnizca ilk kurulumda kullanilir, DB'ye hash olarak yazilir) */
define('DEFAULT_ADMIN_USERNAME', 'Admin');
define('DEFAULT_ADMIN_PASSWORD', 'Admin12345!');

/* Guvenlik / oturum */
define('SESSION_NAME', 'almancapro_sid');
define('REMEMBER_COOKIE', 'almancapro_rmb');
define('REMEMBER_DAYS', 30);
define('CSRF_TOKEN_NAME', 'csrf_token');

/* Ogrenme motoru esikleri */
define('MASTERY_THRESHOLD', 90);          // yuzde
define('MASTERY_UNLOCK_THRESHOLD', 90);   // onkosul esigi
define('MASTERY_STRONG', 75);
define('MASTERY_WEAK', 45);

/* Hata gosterimi: uretimde asla ekrana basma */
define('APP_DEBUG', (getenv('ALMANCAPRO_DEBUG') === '1'));

ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(APP_DEBUG ? E_ALL : (E_ALL & ~E_DEPRECATED & ~E_NOTICE));

date_default_timezone_set('UTC');

mb_internal_encoding('UTF-8');
