<?php
/**
 * AlmancaPro - Destek / bagis (YALNIZCA banka havalesi).
 *
 * Sitede kart numarasi, CVV, son kullanma tarihi alani veya odeme saglayici
 * entegrasyonu YOKTUR. Otomatik cekim yoktur.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
app_boot();

$user = current_user();
$loggedIn = $user !== null;

if (!setting_bool('donations_enabled', true)) {
    flash('info', 'Destek alanı şu anda kapalı.');
    redirect($loggedIn ? '/dashboard.php' : '/');
}

$recipient = (string)setting('donation_recipient', '');
$bank = (string)setting('donation_bank', '');
$iban = (string)setting('donation_iban', '');
$maskName = setting_bool('donation_mask_name', true);
$template = (string)setting('donation_template', 'ALMANCAPRO BAGIS · {AD_SOYAD} · {TUTAR} TL · {KOD}');
$configured = $iban !== '' && $recipient !== '';

/** Alici adini maskeler: "Ahmet Kaya" -> "A**** K****" */
function mask_person_name(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $out = [];
    foreach ($parts as $p) {
        if ($p === '') {
            continue;
        }
        $out[] = mb_substr($p, 0, 1) . str_repeat('*', max(1, mb_strlen($p) - 1));
    }
    return implode(' ', $out);
}

/** Havale aciklamasi uretir: Turkce karakterler ASCII'ye cevrilir, tamami buyuk harf. */
function build_donation_reference(string $template, string $name, float $amount, string $code, int $userId): string
{
    $ascii = static fn (string $s): string => mb_strtoupper(tr_to_ascii($s), 'UTF-8');
    $text = strtr($template, [
        '{AD_SOYAD}'    => $ascii($name),
        '{TUTAR}'       => number_format($amount, 0, ',', ''),
        '{KOD}'         => $code,
        '{KULLANICI_ID}'=> (string)$userId,
        '{TARIH}'       => date('d.m.Y'),
    ]);
    $text = mb_strtoupper(tr_to_ascii($text), 'UTF-8');
    return preg_replace('/\s+/u', ' ', trim($text)) ?? $text;
}

/* Kullanicinin aktif bagis kodu */
$donation = null;
$amount = (float)input_int('amount', 0);
$customAmount = trim((string)input('custom_amount', ''));
if ($customAmount !== '' && is_numeric(str_replace(',', '.', $customAmount))) {
    $amount = (float)str_replace(',', '.', $customAmount);
}
$amount = max(0.0, min(1000000.0, $amount));

if ($loggedIn && $amount > 0) {
    $donation = db_row(
        'SELECT * FROM donations WHERE user_id = ? AND status = "beklemede" AND amount = ? ORDER BY id DESC LIMIT 1',
        [(int)$user['id'], $amount]
    );
    if ($donation === null) {
        /* Benzersiz kod uret */
        for ($i = 0; $i < 12; $i++) {
            $code = random_donation_code(6);
            try {
                db_insert(
                    'INSERT INTO donations (user_id, code, amount, status, source) VALUES (?, ?, ?, "beklemede", "bildirim")',
                    [(int)$user['id'], $code, $amount]
                );
                $donation = db_row('SELECT * FROM donations WHERE code = ?', [$code]);
                break;
            } catch (Throwable $e) {
                continue;
            }
        }
    }
}

$expenses = db_all('SELECT * FROM donation_expenses ORDER BY sort_order, id');
$monthlyTotal = (float)db_value(
    'SELECT COALESCE(SUM(amount), 0) FROM donations WHERE status = "onaylandi" AND confirmed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)',
    [], 0
);
$expenseTotal = 0.0;
foreach ($expenses as $ex) {
    $expenseTotal += (float)$ex['amount'];
}

$presets = [
    50 => 'Bir aylık alan adı masrafının bir bölümü',
    100 => 'Yaklaşık bir haftalık e-posta gönderimi',
    250 => 'Bir günlük sunucu ve yedekleme masrafı',
    500 => 'Yeni bir dersin hazırlanması',
    1000 => 'Bir modülün ses kayıtları',
    2500 => 'Bir aylık sunucu masrafının tamamı',
];

render_head('Destek ol · ' . APP_NAME);
render_public_header($loggedIn);
?>
<main id="main" class="page">
  <div class="focus-area">
    <p class="eyebrow">Destek</p>
    <h1>AlmancaPro'ya destek ol</h1>
    <p style="font-size: 17px;">
      ekibimizi ve sayenizde bu siteyi keşif edip öğrenenlerin çoğalması için bağış yapabilirsiniz.
    </p>

    <div class="alert alert--info">
      <span class="alert__icon" aria-hidden="true">●</span>
      <span>AlmancaPro bir şirket değil, gönüllü bir projedir. Bu yüzden sitede
        <strong>kart alanı, sanal POS veya ödeme sağlayıcısı yoktur</strong>. Tek yöntem banka havalesi/EFT'dir.
        Otomatik çekim yapılmaz. Bağış hiçbir dersi veya özelliği açmaz; bağış yapan ve yapmayan kullanıcı aynı platformu görür.</span>
    </div>

    <?php if (!$configured): ?>
      <div class="alert alert--warn" style="margin-top: 18px;">
        <span class="alert__icon" aria-hidden="true">●</span>
        <span>Havale bilgileri henüz tanımlanmadı. Yönetici IBAN ve alıcı bilgilerini girdiğinde bu bölüm aktif olacak.</span>
      </div>
    <?php else: ?>

      <!-- Adım 1: tutar -->
      <h2 style="margin-top: 30px;">1. Tutarı seç</h2>
      <form method="get" action="/support.php" data-guard>
        <div class="grid grid-3">
          <?php foreach ($presets as $val => $note): ?>
            <button class="onboard-option<?= (int)$amount === $val ? ' is-selected' : '' ?>" type="submit" name="amount" value="<?= $val ?>">
              <span>
                <strong><?= e(number_format((float)$val, 0, ',', '.')) ?> ₺</strong>
                <span class="onboard-option__hint"><?= e($note) ?></span>
              </span>
            </button>
          <?php endforeach; ?>
        </div>
        <div class="field" style="margin-top: 16px; max-width: 320px;">
          <label for="custom_amount">Serbest tutar (₺)</label>
          <input id="custom_amount" name="custom_amount" type="text" inputmode="decimal"
                 value="<?= e($customAmount) ?>" placeholder="Örn. 150">
        </div>
        <button class="btn btn--inline" type="submit">TUTARI ONAYLA</button>
      </form>

      <?php if ($amount > 0): ?>
        <!-- Adım 2: havale bilgileri -->
        <h2 style="margin-top: 34px;">2. Havale bilgileri</h2>
        <div class="card card--flush">
          <?php
          $rows = [
              ['Alıcı', $maskName ? mask_person_name($recipient) : $recipient, $maskName ? $recipient : $recipient],
              ['Banka', $bank, $bank],
              ['IBAN', format_iban($iban), preg_replace('/\s+/', '', $iban)],
              ['Tutar', number_format($amount, 2, ',', '.') . ' ₺', number_format($amount, 2, '.', '')],
          ];
          foreach ($rows as [$label, $display, $copy]):
              if ($display === '') { continue; } ?>
            <div class="plan-item">
              <span class="plan-item__title">
                <span class="eyebrow" style="margin: 0 0 4px;"><?= e($label) ?></span>
                <span class="mono" style="font-size: 15px; color: var(--ink);"><?= e($display) ?></span>
              </span>
              <button class="btn btn--sm btn--secondary btn--inline" type="button" data-copy="<?= e((string)$copy) ?>">KOPYALA</button>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($loggedIn && $donation !== null):
            $code = (string)$donation['code'];
            $reference = build_donation_reference($template, (string)$user['name'], $amount, $code, (int)$user['id']);
            $shortReference = 'BAGIS ' . $code . ' ' . number_format($amount, 0, ',', '') . 'TL';
        ?>
          <!-- Adım 3: açıklama satırı -->
          <h2 style="margin-top: 34px;">3. Açıklama satırı</h2>
          <p class="small">
            Bankanın <strong>açıklama / referans</strong> alanına aşağıdaki metni yaz. Bu metin olmadan havalen
            hesabınla eşleştirilemez.
          </p>

          <div style="border: 2px solid var(--brass); background: var(--surface); padding: 20px; margin-bottom: 16px;">
            <p class="eyebrow" style="margin: 0 0 8px;">Açıklama metni</p>
            <p class="mono" style="font-size: 16px; color: var(--ink); word-break: break-word; margin-bottom: 14px;"><?= e($reference) ?></p>
            <button class="btn btn--inline" type="button" data-copy="<?= e($reference) ?>">AÇIKLAMAYI KOPYALA</button>
          </div>

          <div class="card" style="margin-bottom: 16px;">
            <p class="eyebrow">Kısa varyant (açıklama alanı kısaysa)</p>
            <p class="mono" style="font-size: 15px; color: var(--ink);"><?= e($shortReference) ?></p>
            <button class="btn btn--sm btn--secondary btn--inline" type="button" data-copy="<?= e($shortReference) ?>">KISA METNİ KOPYALA</button>
            <p class="small muted" style="margin: 12px 0 0;">
              Bankanın açıklama alanı kısaysa en az <strong>kod ve tutar</strong> kalmalıdır.
            </p>
          </div>

          <div class="table-wrap">
            <table class="data">
              <caption class="sr-only">Açıklama metnindeki parçaların anlamı</caption>
              <thead><tr><th>Parça</th><th>Ne bildirir</th><th>Not</th></tr></thead>
              <tbody>
                <tr><td data-label="Parça" class="mono">ALMANCAPRO</td><td data-label="Ne bildirir">Ne için</td><td data-label="Not">Havalenin AlmancaPro bağışı olduğunu gösterir</td></tr>
                <tr><td data-label="Parça" class="mono">BAGIS</td><td data-label="Ne bildirir">Ne sebeple</td><td data-label="Not">Bağış olduğunu belirtir — borç veya ödeme değil</td></tr>
                <tr><td data-label="Parça" class="mono"><?= e(mb_strtoupper(tr_to_ascii((string)$user['name']), 'UTF-8')) ?></td><td data-label="Ne bildirir">Kim</td><td data-label="Not">Bankada kayıtlı isimle aynı olmalı</td></tr>
                <tr><td data-label="Parça" class="mono"><?= e(number_format($amount, 0, ',', '')) ?> TL</td><td data-label="Ne bildirir">Ne kadar</td><td data-label="Not">Hesaba geçen tutarla eşleşmeli</td></tr>
                <tr><td data-label="Parça" class="mono"><?= e($code) ?></td><td data-label="Ne bildirir">Eşleştirme kodu</td><td data-label="Not">Havaleyi hesabına bağlayan tek bilgi</td></tr>
              </tbody>
            </table>
          </div>

          <!-- Adım 4: bildirim -->
          <h2 style="margin-top: 34px;">4. Havaleyi yaptıysan bildir (isteğe bağlı)</h2>
          <p class="small">
            Bildirim zorunlu değildir; hesap hareketleri elle kontrol edilir. Bildirirsen eşleştirme daha hızlı olur.
            <strong>Dekont veya ekran görüntüsü yüklenmez.</strong>
          </p>
          <a class="btn btn--inline" href="/donation-notify.php?code=<?= e($code) ?>">HAVALEYİ YAPTIM, BİLDİR</a>

        <?php elseif (!$loggedIn): ?>
          <div class="alert alert--warn" style="margin-top: 22px;">
            <span class="alert__icon" aria-hidden="true">●</span>
            <span>Açıklama metnindeki eşleştirme kodu hesabına özeldir.
              <a href="/login.php">Giriş yaparsan</a> sana özel kod üretilir ve bağışın profilinde görünür.
              Giriş yapmadan da havale gönderebilirsin, ancak eşleştirme elle yapılır.</span>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Şeffaflık -->
    <h2 style="margin-top: 40px;">Gelen destek nereye gidiyor?</h2>
    <div class="table-wrap">
      <table class="data">
        <caption class="sr-only">Aylık gider tablosu</caption>
        <thead><tr><th>Kalem</th><th>Dönem</th><th>Tutar</th><th>Not</th></tr></thead>
        <tbody>
          <?php foreach ($expenses as $ex): ?>
            <tr>
              <td data-label="Kalem"><?= e((string)$ex['title']) ?></td>
              <td data-label="Dönem"><?= e((string)($ex['period'] ?? '-')) ?></td>
              <td data-label="Tutar" class="mono"><?= e(number_format((float)$ex['amount'], 2, ',', '.')) ?> ₺</td>
              <td data-label="Not" class="small muted"><?= e((string)($ex['note'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="small muted" style="margin-top: 12px;">
      Aylık gider toplamı: <span class="mono"><?= e(number_format($expenseTotal, 2, ',', '.')) ?> ₺</span> ·
      Son 30 günde onaylanan destek: <span class="mono"><?= e(number_format($monthlyTotal, 2, ',', '.')) ?> ₺</span>
    </p>

    <h2 style="margin-top: 34px;">Düzenli destek</h2>
    <p class="small">
      Sitede otomatik çekim olmadığı için iptal edilecek bir talimat da yoktur. Düzenli destek vermek istersen
      bankanın mobil uygulamasından <strong>aynı IBAN ve aynı açıklama</strong> ile düzenli ödeme talimatı verebilirsin.
      Talimatı yine kendi bankandan durdurursun.
    </p>
  </div>
</main>
<?php
render_public_footer();
render_foot();
