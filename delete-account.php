<?php
/**
 * AlmancaPro - Hesap silme (yeniden kimlik dogrulama gerektirir).
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_login();
$userId = (int)$user['id'];
$error = '';

if (is_post()) {
    csrf_require();
    $password = (string)($_POST['password'] ?? '');
    $confirm = trim((string)input('confirm_text', ''));

    if (!rate_limit_hit('delete_account', (string)$userId, 5, 3600)) {
        $error = 'Çok fazla deneme yapıldı. Lütfen bir saat sonra tekrar dene.';
    } elseif (!password_verify($password, (string)$user['password_hash'])) {
        $error = 'Şifre hatalı. Hesap silinmedi.';
    } elseif (mb_strtoupper($confirm, 'UTF-8') !== 'SİL' && mb_strtoupper($confirm, 'UTF-8') !== 'SIL') {
        $error = 'Onay kutusuna SİL yazmalısın.';
    } else {
        try {
            db_transaction(function () use ($userId) {
                /* Bagis kayitlari muhasebe icin kullanicisiz kalir (FK: SET NULL). */
                db_exec('DELETE FROM users WHERE id = ?', [$userId]);
            });
            auth_logout();
            render_head('Hesap silindi · ' . APP_NAME, ['css' => ['auth.css'], 'noindex' => true]);
            echo '<div class="auth-wrap"><div class="auth-card">';
            echo '<div class="auth-card__head">';
            render_logo('/');
            echo '<h1 style="margin-top:20px">Hesabın silindi</h1>';
            echo '<p class="auth-card__sub">Öğrenme verilerin, ilerlemen ve Telegram bağlantın kalıcı olarak kaldırıldı. Bizi tercih ettiğin için teşekkürler.</p>';
            echo '</div>';
            echo '<a class="btn btn--block" href="/">ANA SAYFAYA DÖN</a>';
            echo '</div></div>';
            render_foot();
            exit;
        } catch (Throwable $e) {
            app_log('Hesap silinemedi: ' . $e->getMessage());
            $error = 'Hesap silinemedi. Lütfen daha sonra tekrar dene.';
        }
    }
}

$counts = due_review_counts($userId);
render_app_start($user, 'Hesabı sil · ' . APP_NAME, [], ['review.php' => $counts['due']]);
?>
<p class="eyebrow">Hesap silme</p>
<h1>Hesabını kalıcı olarak sil</h1>

<div class="alert alert--error" style="margin-top: 18px;">
  <span class="alert__icon" aria-hidden="true">✕</span>
  <span><strong>Bu işlem geri alınamaz.</strong> Silinen veriler: profil bilgilerin, bütün ders ve kelime ilerlemen,
    mastery kayıtların, tekrar kuyruğun, soruların, Telegram bağlantın ve bildirim tercihlerin.</span>
</div>

<div class="card" style="max-width: 560px;">
  <?php if ($error !== ''): ?>
    <div class="alert alert--error"><span class="alert__icon" aria-hidden="true">✕</span><span><?= e($error) ?></span></div>
  <?php endif; ?>

  <p class="small">Devam etmeden önce alternatifleri değerlendirebilirsin:</p>
  <ul class="small">
    <li>Yalnızca bildirimleri kapatmak istiyorsan <a href="/settings.php">Ayarlar</a> sayfasını kullan.</li>
    <li>Yalnızca Telegram bağlantısını kesmek istiyorsan <a href="/telegram.php">Telegram</a> sayfasını kullan.</li>
  </ul>

  <form method="post" action="/delete-account.php" data-guard>
    <?= csrf_field() ?>
    <?php render_password_field('password', 'password', 'Şifreni gir', 'current-password', false); ?>
    <div class="field">
      <label for="confirm_text">Onaylamak için kutuya <strong>SİL</strong> yaz</label>
      <input id="confirm_text" name="confirm_text" type="text" autocomplete="off" required>
    </div>
    <button class="btn btn--danger btn--block" type="submit"
            data-confirm="Hesabın ve bütün öğrenme verin kalıcı olarak silinecek. Emin misin?">HESABIMI KALICI OLARAK SİL</button>
  </form>
</div>

<?php render_app_end(); ?>
