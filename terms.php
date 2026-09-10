<?php
/**
 * AlmancaPro - Kullanim kosullari.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
app_boot();

$loggedIn = is_logged_in();
render_head('Kullanım Koşulları · ' . APP_NAME);
render_public_header($loggedIn);
?>
<main id="main" class="page">
  <div class="focus-area">
    <p class="eyebrow">Yasal</p>
    <h1>Kullanım Koşulları</h1>
    <p class="muted">Son güncelleme: <?= e(local_datetime((string)(setting('installed_at') ?? now_utc()), 'd.m.Y')) ?></p>

    <h2>Hizmetin kapsamı</h2>
    <p>AlmancaPro, ana dili Türkçe olan yetişkinlere A0'dan B1 seviyesine kadar Almanca öğreten
      <strong>ücretsiz</strong> bir eğitim platformudur. Abonelik, ücretli paket veya ödeme sistemi yoktur.
      Bağış yapan ve yapmayan kullanıcılar tamamen aynı içeriği görür.</p>

    <h2>Hesabın</h2>
    <ul>
      <li>Kayıt olurken doğru bir e-posta adresi vermelisin; doğrulama kodu bu adrese gönderilir.</li>
      <li>Şifreni gizli tutmak senin sorumluluğundadır.</li>
      <li>Bir kişi tek hesap açmalıdır. Hesabını başkasıyla paylaşırsan öğrenme verilerin bozulur.</li>
      <li>Hesabını istediğin zaman kalıcı olarak silebilirsin.</li>
    </ul>

    <h2>Kabul edilmeyen kullanım</h2>
    <ul>
      <li>Sistemi otomatik araçlarla aşırı yüklemek veya güvenlik önlemlerini aşmaya çalışmak.</li>
      <li>İçeriği izinsiz çoğaltıp ticari amaçla dağıtmak.</li>
      <li>Başka kullanıcıların hesaplarına erişmeye çalışmak.</li>
    </ul>
    <p>Bu kurallara aykırı kullanım tespit edilirse hesap askıya alınabilir.</p>

    <h2>Öğrenme sonuçları hakkında</h2>
    <p>AlmancaPro belirli bir sürede belirli bir seviyeye ulaşacağını <strong>garanti etmez</strong>.
      "30 Günde Almanya'ya Hazırlık" bir çalışma programıdır; sonuç kişinin çalışma yoğunluğuna,
      başlangıç seviyesine ve düzenliliğine bağlıdır.</p>
    <p>Uygulama içinde yapılan seviye değerlendirmeleri <strong>AlmancaPro'ya özgüdür</strong> ve
      Goethe-Institut, telc veya ÖSD tarafından verilen resmi sertifikaların yerine geçmez.</p>

    <h2>İçeriğin niteliği</h2>
    <p>İş Almancası, resmi kurumlar, kira sözleşmesi ve sağlık sistemiyle ilgili bölümler
      <strong>dil öğretmek</strong> içindir. Bunlar hukuki, tıbbi veya finansal tavsiye değildir.
      Hukuki durumun için sözleşmene bakmalı veya yetkili bir danışmana başvurmalısın.
      Kurumların kuralları ve süreçleri zamanla değişebilir; güncel bilgiyi ilgili kurumdan doğrula.</p>

    <h2>Hizmetin sürekliliği</h2>
    <p>AlmancaPro gönüllü bir projedir. Bakım, teknik sorun veya kaynak yetersizliği nedeniyle
      hizmet geçici olarak kesilebilir. Verilerin düzenli olarak yedeklenir, ancak kesintisiz hizmet
      taahhüdü verilmez.</p>

    <h2>Değişiklikler</h2>
    <p>Bu koşullar zaman zaman güncellenebilir. Önemli değişiklikler platformda duyurulur.</p>
  </div>
</main>
<?php
render_public_footer();
render_foot();
