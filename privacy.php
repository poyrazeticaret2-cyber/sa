<?php
/**
 * AlmancaPro - Gizlilik politikasi.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
app_boot();

$loggedIn = is_logged_in();
render_head('Gizlilik Politikası · ' . APP_NAME);
render_public_header($loggedIn);
?>
<main id="main" class="page">
  <div class="focus-area">
    <p class="eyebrow">Yasal</p>
    <h1>Gizlilik Politikası</h1>
    <p class="muted">Son güncelleme: <?= e(local_datetime((string)(setting('installed_at') ?? now_utc()), 'd.m.Y')) ?></p>

    <h2>Hangi verileri topluyoruz?</h2>
    <p>AlmancaPro yalnızca hizmetin çalışması için gereken verileri toplar:</p>
    <ul>
      <li><strong>Hesap bilgileri:</strong> ad, e-posta adresi ve şifrenin geri döndürülemez şekilde hashlenmiş hali.</li>
      <li><strong>Öğrenme verileri:</strong> hangi dersleri tamamladığın, hangi soruları doğru/yanlış cevapladığın,
        mastery durumların, tekrar zamanların ve hata türlerin.</li>
      <li><strong>Tercihler:</strong> günlük hedef süren, saat dilimin, bildirim ayarların, varsa Almanya'ya gidiş tarihin.</li>
      <li><strong>Teknik kayıtlar:</strong> giriş denemelerinde IP adresi ve tarayıcı bilgisi (güvenlik amacıyla, sınırlı süreyle).</li>
      <li><strong>Telegram:</strong> yalnızca sen bağlarsan Telegram kullanıcı kimliğin ve sohbet kimliğin.</li>
    </ul>

    <h2>Şifren nasıl saklanıyor?</h2>
    <p>Şifreler <strong>hiçbir zaman düz metin olarak saklanmaz</strong>. PHP'nin güvenli hash fonksiyonları
      (mümkünse Argon2id) kullanılır. Yöneticiler dahil hiç kimse şifreni göremez.</p>

    <h2>Verilerini kimlerle paylaşıyoruz?</h2>
    <p>Öğrenme verilerin satılmaz ve reklam amacıyla paylaşılmaz. Veriler yalnızca şu durumlarda üçüncü taraflara ulaşır:</p>
    <ul>
      <li>E-posta göndermek için kullanılan SMTP sağlayıcısı (yalnızca e-posta adresin ve mesaj içeriği).</li>
      <li>Telegram'ı bağladıysan, sana mesaj göndermek için Telegram Bot API.</li>
      <li>Yönetici gelişmiş öğretmen özelliğini açtıysa, "Öğretmene Sor" bölümünde sorduğun soru ve seviye bilgin
        ilgili yapay zeka sağlayıcısına gönderilir. Bu özellik varsayılan olarak kapalıdır.</li>
    </ul>

    <h2>Çerezler</h2>
    <p>Yalnızca işlevsel çerezler kullanılır:</p>
    <ul>
      <li><strong>Oturum çerezi:</strong> giriş yaptığında oturumunu tanımak için.</li>
      <li><strong>"Beni hatırla" çerezi:</strong> yalnızca sen işaretlersen, 30 gün süreyle. İçinde şifren veya
        kimlik bilgin bulunmaz; yalnızca rastgele bir doğrulayıcı tutulur.</li>
    </ul>
    <p>Reklam veya takip çerezi kullanılmaz.</p>

    <h2>Ne kadar süre saklıyoruz?</h2>
    <p>Hesabın açık olduğu sürece öğrenme verilerin saklanır. Hesabını sildiğinde bu veriler kalıcı olarak silinir.
      Güvenlik kayıtları (giriş denemeleri) 30 gün, e-posta gönderim kayıtları 60 gün sonra otomatik olarak temizlenir.</p>

    <h2>Haklarını nasıl kullanırsın?</h2>
    <ul>
      <li>Bildirimleri tamamen kapatabilirsin: <a href="/settings.php">Ayarlar</a>.</li>
      <li>Telegram bağlantını istediğin an kesebilirsin: <a href="/telegram.php">Telegram</a>.</li>
      <li>Hesabını ve bütün verilerini kalıcı olarak silebilirsin: <a href="/delete-account.php">Hesabı sil</a>.</li>
    </ul>

    <h2>İletişim</h2>
    <p>Gizlilikle ilgili sorular için site yöneticisine yazabilirsin:
      <?php $mail = setting('site_email'); ?>
      <?= $mail !== null ? '<a href="mailto:' . e($mail) . '">' . e($mail) . '</a>' : 'iletişim adresi site ayarlarından tanımlanmalıdır.' ?>
    </p>
  </div>
</main>
<?php
render_public_footer();
render_foot();
