<?php
/**
 * AlmancaPro - Bulunamadi sayfasi.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
app_boot();

http_response_code(404);
$loggedIn = is_logged_in();

render_head('Sayfa bulunamadı · ' . APP_NAME, ['noindex' => true]);
render_public_header($loggedIn);
?>
<main id="main" class="page">
  <div class="focus-area" style="padding-block: 48px;">
    <p class="eyebrow">404</p>
    <h1>Bu sayfayı bulamadık</h1>
    <p>Aradığın sayfa taşınmış veya hiç var olmamış olabilir. Hiçbir ilerlemen kaybolmadı.</p>
    <div class="row" style="margin-top: 22px;">
      <a class="btn btn--inline" href="<?= $loggedIn ? '/dashboard.php' : '/' ?>">
        <?= $loggedIn ? 'PANELE DÖN' : 'ANA SAYFAYA DÖN' ?>
      </a>
      <?php if ($loggedIn): ?>
        <a class="btn btn--secondary btn--inline" href="/course.php">DERSLERE GİT</a>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php
render_public_footer();
render_foot();
