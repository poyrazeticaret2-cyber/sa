<?php
/**
 * AlmancaPro - Genel giris sayfasi (landing) govdesi.
 *
 * Bu dosya index.php tarafindan cagrilir. index.php once PHP surumunu
 * kontrol eder; boylece eski bir PHP surumunde ham 500 yerine
 * okunabilir bir mesaj gosterilir.
 */
declare(strict_types=1);

if (!defined('ALMANCAPRO_ENTRY')) {
    header('Location: /index.php');
    exit;
}

require_once __DIR__ . '/bootstrap.php';
app_boot();

$user = current_user();
$loggedIn = $user !== null;

/* Gercek platform sayilari - hicbiri sabit yazilmaz. */
try {
    $stats = [
        'lessons'   => (int)db_value('SELECT COUNT(*) FROM lessons WHERE is_active = 1', [], 0),
        'vocabulary'=> (int)db_value('SELECT COUNT(*) FROM vocabulary WHERE is_active = 1', [], 0),
        'exercises' => (int)db_value('SELECT COUNT(*) FROM exercises WHERE is_active = 1', [], 0),
        'skills'    => (int)db_value('SELECT COUNT(*) FROM skills WHERE is_active = 1', [], 0),
    ];
    $levels = db_all('SELECT cefr_level, COUNT(*) AS c FROM lessons WHERE is_active = 1 GROUP BY cefr_level');
} catch (Throwable $e) {
    $stats = ['lessons' => 0, 'vocabulary' => 0, 'exercises' => 0, 'skills' => 0];
    $levels = [];
}
$levelCounts = [];
foreach ($levels as $l) {
    $levelCounts[(string)$l['cefr_level']] = (int)$l['c'];
}

render_head(APP_NAME . ' – Almancayı Gerçekten Öğren');
render_public_header($loggedIn);
?>
<main id="main">
  <section class="page" style="padding-block: 56px 40px;">
    <div style="max-width: 760px;">
      <p class="eyebrow">A0 → B1 · Türkçe anlatım · Ücretsiz</p>
      <h1 style="font-size: clamp(38px, 7vw, 62px); letter-spacing: -0.035em; line-height: 1.02; margin-bottom: 18px;">
        Almancayı ezberleme.<br>Gerçekten öğren.
      </h1>
      <p style="font-size: 18px; color: var(--ink-2); max-width: 620px;">
        A0'dan B1'e kadar, öğrenmeden ilerlemene izin vermeyen ücretsiz Almanca eğitim sistemi.
        Bir konuyu gerçekten öğrendiğini kanıtlamadan sonraki konuya geçemezsin.
      </p>
      <div class="row" style="margin-top: 28px;">
        <?php if ($loggedIn): ?>
          <a class="btn btn--lg btn--inline" href="/dashboard.php">PANELE DEVAM ET</a>
        <?php else: ?>
          <a class="btn btn--lg btn--inline" href="/register.php">ÜCRETSİZ BAŞLA</a>
        <?php endif; ?>
        <a class="btn btn--lg btn--secondary btn--inline" href="#nasil">NASIL ÇALIŞIYOR?</a>
      </div>
    </div>
  </section>

  <section class="page" style="padding-block: 0 48px;">
    <div class="hairline-grid grid-4">
      <div style="padding: 22px;">
        <div class="num" style="font-size: 28px; color: var(--ink);"><?= (int)$stats['lessons'] ?></div>
        <div class="small muted">ders</div>
      </div>
      <div style="padding: 22px;">
        <div class="num" style="font-size: 28px; color: var(--ink);"><?= (int)$stats['vocabulary'] ?></div>
        <div class="small muted">kelime (artikel + çoğul ile)</div>
      </div>
      <div style="padding: 22px;">
        <div class="num" style="font-size: 28px; color: var(--ink);"><?= (int)$stats['exercises'] ?></div>
        <div class="small muted">alıştırma</div>
      </div>
      <div style="padding: 22px;">
        <div class="num" style="font-size: 28px; color: var(--ink);"><?= (int)$stats['skills'] ?></div>
        <div class="small muted">ayrı takip edilen beceri</div>
      </div>
    </div>
  </section>

  <section class="page" id="nasil" style="padding-block: 24px 48px;">
    <p class="eyebrow">Nasıl çalışıyor?</p>
    <h2 style="font-size: 30px; margin-bottom: 26px;">Öğrenmeden geçiş yok</h2>
    <div class="grid grid-3">
      <div class="card card--accent">
        <h3>Öğrenmeden geçiş yok</h3>
        <p class="small">Her kelime ve dilbilgisi konusu için tanıma, aktif hatırlama, üretim ve gecikmeli hatırlama ayrı ayrı ölçülür.
          Dördü tamamlanmadan konu tamamlanmış sayılmaz. URL değiştirerek de atlayamazsın; kilit sunucu tarafında.</p>
      </div>
      <div class="card card--accent">
        <h3>Akıllı tekrar sistemi</h3>
        <p class="small">Her doğru cevap tekrar aralığını uzatır, her hata kısaltır. Zayıf kalan konular tekrar kuyruğuna girer ve
          sana farklı biçimlerde yeniden sorulur. Aynı soruyu ezberleyerek geçemezsin.</p>
      </div>
      <div class="card card--accent">
        <h3>Hata analizi</h3>
        <p class="small">Yanlış cevabın türü belirlenir: artikel, hal, fiil çekimi, kelime sırası, yazım, büyük harf.
          Panelinde en çok zorlandığın alanları görür ve doğrudan o alana çalışırsın.</p>
      </div>
      <div class="card card--accent">
        <h3>İş Almancası</h3>
        <p class="small">İlk gün, vardiya, mola, hastalık bildirimi, izin talebi, toplantı dili, iş güvenliği talimatları ve
          sözleşme kelimeleri. Almanya'da çalışacaksan bunlar teoriden önce gelir.</p>
      </div>
      <div class="card card--accent">
        <h3>Telegram hatırlatmaları</h3>
        <p class="small">Hesabını Telegram'a bağlarsan günlük hedefin, tekrar zamanın ve mini quizler Telegram'a gelir.
          Telegram'da verdiğin cevaplar da aynı öğrenme verine yazılır.</p>
      </div>
      <div class="card card--accent">
        <h3>30 günlük yoğun program</h3>
        <p class="small">Almanya'ya gidiş tarihin yakınsa program günlük hedeflerini kalan süreye, mevcut seviyene ve
          zayıf alanlarına göre uyarlar. Bu bir sertifika garantisi değil, yoğun bir çalışma planıdır.</p>
      </div>
    </div>
  </section>

  <section class="page" style="padding-block: 0 48px;">
    <p class="eyebrow">Yol haritası</p>
    <h2 style="margin-bottom: 20px;">A0 → B1</h2>
    <div class="hairline-grid grid-4">
      <?php foreach (['A0' => 'Sıfırdan başlangıç: alfabe, sesler, ilk tanışma, sayılar',
                      'A1' => 'Temel cümle, artikel, Akkusativ, modal fiiller, günlük hayat',
                      'A2' => 'Dativ, edatlar, Perfekt, yan cümleler, resmi kurumlar',
                      'B1' => 'Sıfat çekimi, ilgi cümlesi, Passiv, Konjunktiv II, iş dünyası'] as $lvl => $desc): ?>
        <div style="padding: 22px;">
          <div class="row" style="gap: 10px; margin-bottom: 10px;">
            <span class="badge badge--level"><?= e($lvl) ?></span>
            <span class="num small"><?= (int)($levelCounts[$lvl] ?? 0) ?> ders</span>
          </div>
          <p class="small" style="margin: 0;"><?= e($desc) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="page" style="padding-block: 0 64px;">
    <div class="card card--strong" style="padding: 32px;">
      <div class="row-between" style="gap: 24px;">
        <div style="max-width: 560px;">
          <h2 style="margin-bottom: 10px;">Sistem herkese açık ve tamamen ücretsiz</h2>
          <p class="small" style="margin: 0;">
            Ücretli paket, abonelik veya ödeme sistemi yoktur. Bağış yapan ve yapmayan kullanıcı aynı platformu görür.
            Uygulama içi B1 değerlendirmesi Goethe, telc veya ÖSD sertifikası değildir.
          </p>
        </div>
        <?php if (!$loggedIn): ?>
          <a class="btn btn--lg btn--inline" href="/register.php">ÜCRETSİZ BAŞLA</a>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>
<?php
render_public_footer();
render_foot();
