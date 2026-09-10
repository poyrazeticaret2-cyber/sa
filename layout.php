<?php
/**
 * AlmancaPro - Ortak gorunum bilesenleri.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * @param array{css?:array<int,string>, js?:array<int,string>, body_class?:string, description?:string, noindex?:bool} $opts
 */
function render_head(string $title, array $opts = []): void
{
    send_security_headers();
    $css = array_merge(['main.css'], $opts['css'] ?? []);
    $js = $opts['js'] ?? [];
    $bodyClass = $opts['body_class'] ?? '';
    $desc = $opts['description'] ?? 'A0\'dan B1\'e kadar, öğrenmeden ilerlemene izin vermeyen ücretsiz Almanca eğitim sistemi.';
    ?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="theme-color" content="#111110">
<?php if (!empty($opts['noindex'])): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<?php foreach ($css as $file): ?>
<link rel="stylesheet" href="/assets/css/<?= e($file) ?>?v=<?= e(APP_VERSION) ?>">
<?php endforeach; ?>
</head>
<body<?= $bodyClass !== '' ? ' class="' . e($bodyClass) . '"' : '' ?>>
<a class="skip-link" href="#main">İçeriğe geç</a>
<?php
    $GLOBALS['__almancapro_js'] = $js;
}

function render_foot(): void
{
    $js = $GLOBALS['__almancapro_js'] ?? [];
    $js = array_merge(['app.js'], $js);
    foreach ($js as $file) {
        echo '<script src="/assets/js/' . e($file) . '?v=' . e(APP_VERSION) . '" defer></script>' . "\n";
    }
    echo "</body>\n</html>\n";
}

function render_logo(string $href = '/'): void
{
    ?>
<a class="logo" href="<?= e($href) ?>" aria-label="AlmancaPro ana sayfa">
  <span class="logo__mark" aria-hidden="true"></span>
  <span class="logo__text">ALMANCA<span>PRO</span></span>
</a>
    <?php
}

function render_flashes(): void
{
    foreach (flash_take() as $f) {
        $type = (string)$f['type'];
        $cls = match ($type) {
            'success' => 'alert--success',
            'error'   => 'alert--error',
            'warning' => 'alert--warn',
            default   => 'alert--info',
        };
        $icon = match ($type) {
            'success' => '✓',
            'error'   => '✕',
            'warning' => '●',
            default   => '●',
        };
        echo '<div class="alert ' . $cls . '" role="status"><span class="alert__icon" aria-hidden="true">' . $icon . '</span><span>' . e((string)$f['message']) . '</span></div>';
    }
}

/* ==================================================================
 * Public site
 * ================================================================== */

function render_public_header(bool $loggedIn = false): void
{
    ?>
<header class="site-header">
  <div class="site-header__inner">
    <?php render_logo('/'); ?>
    <nav class="site-nav" aria-label="Site menüsü">
      <a href="/index.php#nasil">Nasıl çalışıyor?</a>
      <a href="/support.php">Destek ol</a>
      <?php if ($loggedIn): ?>
        <a class="btn btn--sm btn--inline" href="/dashboard.php">PANELE GİT</a>
      <?php else: ?>
        <a href="/login.php">Giriş yap</a>
        <a class="btn btn--sm btn--inline" href="/register.php">ÜCRETSİZ BAŞLA</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
    <?php
}

function render_public_footer(): void
{
    ?>
<footer class="site-footer">
  <div class="site-footer__inner">
    <div>© <?= date('Y') ?> <?= e(site_name()) ?> · Ücretsiz Almanca eğitim platformu</div>
    <nav aria-label="Alt menü">
      <a href="/privacy.php">Gizlilik</a> ·
      <a href="/terms.php">Kullanım koşulları</a> ·
      <a href="/support.php">Destek ol</a>
    </nav>
  </div>
</footer>
    <?php
}

/* ==================================================================
 * Uygulama kabugu
 * ================================================================== */

/** Kullanici menusu tanimi. */
function app_nav_items(): array
{
    return [
        ['dashboard.php', 'Ana Sayfa', '01'],
        ['course.php', 'Öğren', '02'],
        ['review.php', 'Tekrar', '03'],
        ['vocabulary.php', 'Kelime Hazinem', '04'],
        ['grammar.php', 'Dilbilgisi', '05'],
        ['quiz.php', 'Quiz', '06'],
        ['intensive.php', '30 Günlük Program', '07'],
        ['work-german.php', 'İş Almancası', '08'],
        ['scenario.php', 'Senaryolar', '09'],
        ['ask.php', 'Öğretmene Sor', '10'],
        ['progress.php', 'İlerlemem', '11'],
    ];
}

function app_nav_secondary(): array
{
    return [
        ['telegram.php', 'Telegram', '12'],
        ['profile.php', 'Profil', '13'],
        ['settings.php', 'Ayarlar', '14'],
    ];
}

/**
 * @param array<string,int> $counts Rozet sayaclari (dosya adi => sayi)
 */
function render_app_start(array $user, string $title, array $opts = [], array $counts = []): void
{
    $opts['css'] = array_merge(['learning.css'], $opts['css'] ?? []);
    render_head($title, $opts);
    $active = current_path();
    ?>
<div class="app">
  <aside class="sidebar" aria-label="Ana menü">
    <div class="sidebar__brand"><?php render_logo('/dashboard.php'); ?></div>
    <nav class="sidebar__nav">
      <?php foreach (app_nav_items() as [$href, $label, $num]): ?>
        <a class="navitem" href="/<?= e($href) ?>"<?= $active === $href ? ' aria-current="page"' : '' ?>>
          <span class="navitem__num" aria-hidden="true"><?= e($num) ?></span>
          <span class="navitem__label"><?= e($label) ?></span>
          <?php if (!empty($counts[$href])): ?>
            <span class="navitem__count"><?= (int)$counts[$href] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
      <div class="sidebar__sep"></div>
      <?php foreach (app_nav_secondary() as [$href, $label, $num]): ?>
        <a class="navitem" href="/<?= e($href) ?>"<?= $active === $href ? ' aria-current="page"' : '' ?>>
          <span class="navitem__num" aria-hidden="true"><?= e($num) ?></span>
          <span class="navitem__label"><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
      <div class="sidebar__sep"></div>
      <a class="navitem" href="/logout.php">
        <span class="navitem__num" aria-hidden="true">→</span>
        <span class="navitem__label">Çıkış</span>
      </a>
    </nav>
  </aside>

  <div class="app__main">
    <div class="topbar">
      <?php render_logo('/dashboard.php'); ?>
      <a class="btn btn--sm btn--secondary btn--inline" href="/profile.php">PROFİL</a>
    </div>
    <main class="app__inner" id="main">
      <?php render_flashes(); ?>
    <?php
}

function render_app_end(): void
{
    $active = current_path();
    $tabs = [
        ['dashboard.php', 'Ana Sayfa'],
        ['course.php', 'Öğren'],
        ['review.php', 'Tekrar'],
        ['ask.php', 'Sor'],
        ['profile.php', 'Profil'],
    ];
    ?>
    </main>
  </div>
</div>
<nav class="bottomnav" aria-label="Mobil menü">
  <div class="bottomnav__list">
    <?php foreach ($tabs as [$href, $label]): ?>
      <a class="bottomnav__item" href="/<?= e($href) ?>"<?= $active === $href ? ' aria-current="page"' : '' ?>>
        <span class="bottomnav__dot" aria-hidden="true"></span>
        <span><?= e($label) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</nav>
    <?php
    render_foot();
}

/* ==================================================================
 * Odak modu (ders / quiz) - sidebar yok
 * ================================================================== */

function render_focus_start(string $title, array $opts = []): void
{
    $opts['css'] = array_merge(['learning.css'], $opts['css'] ?? []);
    render_head($title, $opts);
    echo '<main id="main">';
}

function render_focus_end(): void
{
    echo '</main>';
    render_foot();
}

/** Ders/quiz ust bari. */
function render_lesson_bar(string $exitHref, string $exitLabel, ?int $step, ?int $total, string $rightLabel): void
{
    $pct = ($total !== null && $total > 0 && $step !== null) ? pct($step, $total) : 0;
    ?>
<div class="lesson-bar">
  <div class="lesson-bar__inner">
    <a class="lesson-bar__exit" href="<?= e($exitHref) ?>" data-session-exit>← <?= e($exitLabel) ?></a>
    <?php if ($step !== null && $total !== null && $total > 0): ?>
      <div class="lesson-bar__mid">
        <span class="lesson-bar__step"><?= (int)$step ?> / <?= (int)$total ?></span>
        <span class="progress progress--thin" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="İlerleme">
          <span class="progress__fill" style="width: <?= $pct ?>%"></span>
        </span>
      </div>
    <?php endif; ?>
    <span class="lesson-bar__level"><?= e($rightLabel) ?></span>
  </div>
</div>
    <?php
}

/* ==================================================================
 * Admin kabugu
 * ================================================================== */

function admin_nav_items(): array
{
    return [
        ['admin.php', 'Dashboard', '01'],
        ['admin-users.php', 'Kullanıcılar', '02'],
        ['admin-lessons.php', 'Dersler', '03'],
        ['admin-curriculum.php', 'Müfredat', '04'],
        ['admin-vocabulary.php', 'Kelime Hazinesi', '05'],
        ['admin-grammar.php', 'Dilbilgisi', '06'],
        ['admin-skills.php', 'Skills', '07'],
        ['admin-exercises.php', 'Alıştırmalar', '08'],
        ['admin-quizzes.php', 'Quizler', '09'],
        ['admin-telegram.php', 'Telegram', '10'],
        ['admin-notifications.php', 'Bildirimler', '11'],
        ['admin-questions.php', 'Kullanıcı Soruları', '12'],
        ['admin-donations.php', 'Bağışlar', '13'],
        ['admin-ai.php', 'AI Ayarları', '14'],
        ['admin-smtp.php', 'SMTP Ayarları', '15'],
        ['admin-settings.php', 'Site Ayarları', '16'],
        ['admin-health.php', 'Sistem Durumu', '17'],
        ['admin-logs.php', 'Loglar', '18'],
    ];
}

function render_admin_start(array $admin, string $title, array $opts = []): void
{
    $opts['css'] = array_merge(['admin.css'], $opts['css'] ?? []);
    $opts['body_class'] = trim('admin ' . ($opts['body_class'] ?? ''));
    $opts['noindex'] = true;
    render_head($title, $opts);
    $active = current_path();
    ?>
<div class="admin-shell">
  <aside class="admin-side" aria-label="Yönetim menüsü">
    <div class="admin-side__brand">
      <?php render_logo('/admin.php'); ?>
      <div class="admin-side__role">Yönetim Paneli</div>
    </div>
    <nav class="admin-side__nav">
      <?php foreach (admin_nav_items() as [$href, $label, $num]): ?>
        <a class="admin-nav" href="/<?= e($href) ?>"<?= $active === $href ? ' aria-current="page"' : '' ?>>
          <span class="admin-nav__num" aria-hidden="true"><?= e($num) ?></span><span><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
      <div class="admin-side__sep"></div>
      <a class="admin-nav" href="/admin-profile.php"<?= $active === 'admin-profile.php' ? ' aria-current="page"' : '' ?>>
        <span class="admin-nav__num" aria-hidden="true">19</span><span>Profil</span>
      </a>
      <a class="admin-nav" href="/admin-password.php"<?= $active === 'admin-password.php' ? ' aria-current="page"' : '' ?>>
        <span class="admin-nav__num" aria-hidden="true">20</span><span>Şifre Değiştir</span>
      </a>
      <a class="admin-nav" href="/admin-logout.php">
        <span class="admin-nav__num" aria-hidden="true">→</span><span>Çıkış</span>
      </a>
    </nav>
  </aside>
  <div class="admin-main">
    <div class="admin-topbar">
      <div>
        <strong><?= e($title) ?></strong>
      </div>
      <div class="row">
        <span class="small"><?= e((string)$admin['display_name']) ?> · <?= e((string)$admin['username']) ?></span>
        <a class="btn btn--sm btn--secondary btn--inline" href="/" target="_blank" rel="noopener">Siteyi gör</a>
        <a class="btn btn--sm btn--secondary btn--inline" href="/admin-logout.php">Çıkış</a>
      </div>
    </div>
    <main class="admin-inner" id="main">
      <?php render_flashes(); ?>
      <?php if ((int)$admin['must_change_password'] === 1 && current_path() !== 'admin-password.php'): ?>
        <div class="alert alert--warn"><span class="alert__icon" aria-hidden="true">●</span>
          <span>Güvenlik için varsayılan admin şifresini değiştirmeniz önerilir.
          <a href="/admin-password.php">Şimdi değiştir</a>.</span></div>
      <?php endif; ?>
    <?php
}

function render_admin_end(): void
{
    ?>
    </main>
  </div>
</div>
    <?php
    render_foot();
}

/* ==================================================================
 * Kucuk bilesenler
 * ================================================================== */

function render_progress(int $value, int $max = 100, string $variant = '', ?string $label = null): void
{
    $p = pct($value, max(1, $max));
    $cls = $variant !== '' ? ' progress__fill--' . $variant : '';
    echo '<span class="progress" role="progressbar" aria-valuenow="' . $p . '" aria-valuemin="0" aria-valuemax="100"'
        . ($label !== null ? ' aria-label="' . e($label) . '"' : '')
        . '><span class="progress__fill' . $cls . '" style="width:' . $p . '%"></span></span>';
}

function render_badge(string $icon, string $text, string $variant = ''): void
{
    $cls = $variant !== '' ? ' badge--' . $variant : '';
    echo '<span class="badge' . $cls . '"><span aria-hidden="true">' . e($icon) . '</span>' . e($text) . '</span>';
}

function render_mastery_badge(string $status): void
{
    [$icon, $text, $variant] = mastery_status_label($status);
    render_badge($icon, $text, $variant);
}

function render_empty(string $title, string $text, ?string $ctaHref = null, ?string $ctaLabel = null): void
{
    ?>
<div class="empty">
  <div class="empty__title">✓ <?= e($title) ?></div>
  <p class="small"><?= e($text) ?></p>
  <?php if ($ctaHref !== null && $ctaLabel !== null): ?>
    <a class="btn btn--inline" href="<?= e($ctaHref) ?>"><?= e($ctaLabel) ?></a>
  <?php endif; ?>
</div>
    <?php
}

function render_pagination(int $page, int $totalPages, string $baseUrl): void
{
    if ($totalPages <= 1) {
        return;
    }
    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    echo '<nav class="pagination" aria-label="Sayfalama">';
    if ($page > 1) {
        echo '<a href="' . e($baseUrl . $sep . 'page=' . ($page - 1)) . '" rel="prev">←</a>';
    }
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    if ($start > 1) {
        echo '<a href="' . e($baseUrl . $sep . 'page=1') . '">1</a>';
        if ($start > 2) {
            echo '<span>…</span>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $page) {
            echo '<span class="is-current" aria-current="page">' . $i . '</span>';
        } else {
            echo '<a href="' . e($baseUrl . $sep . 'page=' . $i) . '">' . $i . '</a>';
        }
    }
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            echo '<span>…</span>';
        }
        echo '<a href="' . e($baseUrl . $sep . 'page=' . $totalPages) . '">' . $totalPages . '</a>';
    }
    if ($page < $totalPages) {
        echo '<a href="' . e($baseUrl . $sep . 'page=' . ($page + 1)) . '" rel="next">→</a>';
    }
    echo '</nav>';
}

/** Sifre alani (GOSTER/GIZLE her zaman bulunur). */
function render_password_field(string $id, string $name, string $label, string $autocomplete = 'current-password', bool $showByDefault = false, bool $required = true): void
{
    ?>
<div class="field">
  <label for="<?= e($id) ?>"><?= e($label) ?></label>
  <span class="pw-field">
    <input id="<?= e($id) ?>" name="<?= e($name) ?>" type="<?= $showByDefault ? 'text' : 'password' ?>"
           autocomplete="<?= e($autocomplete) ?>"<?= $required ? ' required' : '' ?>>
    <button type="button" class="pw-toggle" aria-pressed="<?= $showByDefault ? 'true' : 'false' ?>"
            aria-label="<?= $showByDefault ? 'Şifreyi gizle' : 'Şifreyi göster' ?>"><?= $showByDefault ? 'GİZLE' : 'GÖSTER' ?></button>
  </span>
</div>
    <?php
}

function render_password_strength(string $forId): void
{
    ?>
<div class="pw-strength" data-strength-for="<?= e($forId) ?>" aria-hidden="true">
  <span class="pw-strength__bar">
    <span class="pw-strength__seg"></span><span class="pw-strength__seg"></span>
    <span class="pw-strength__seg"></span><span class="pw-strength__seg"></span>
  </span>
  <span class="pw-strength__label"></span>
</div>
    <?php
}
