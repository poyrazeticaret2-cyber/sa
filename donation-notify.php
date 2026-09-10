<?php
/**
 * AlmancaPro - Havale bildirimi.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
app_boot();

$user = require_login();
$userId = (int)$user['id'];

if (!setting_bool('donations_enabled', true)) {
    redirect('/dashboard.php');
}

$code = strtoupper(trim((string)input('code', '')));
$donation = null;
if ($code !== '') {
    $donation = db_row('SELECT * FROM donations WHERE code = ? AND user_id = ?', [$code, $userId]);
}
if ($donation === null) {
    $donation = db_row('SELECT * FROM donations WHERE user_id = ? AND status = "beklemede" ORDER BY id DESC LIMIT 1', [$userId]);
}
if ($donation === null) {
    flash('info', 'Önce destek sayfasından bir tutar seçip eşleştirme kodu oluşturmalısın.');
    redirect('/support.php');
}

$errors = [];
$done = input_int('done', 0) === 1;

if (is_post()) {
    csrf_require();
    $senderName = trim((string)input('sender_name', ''));
    $amountRaw = str_replace(',', '.', trim((string)input('amount', '')));
    $transferDate = trim((string)input('transfer_date', ''));
    $note = trim((string)input('note', ''));
    $showSupporter = !empty($_POST['show_in_supporters']);
    $notifyEmail = !empty($_POST['notify_email']);

    if (mb_strlen($senderName) < 3) {
        $errors['sender_name'] = 'Gönderen ad soyadı yaz (bankada kayıtlı isimle aynı olmalı).';
    }
    if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) {
        $errors['amount'] = 'Geçerli bir tutar gir.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transferDate)) {
        $errors['transfer_date'] = 'Havale tarihini seç.';
    }
    if (mb_strlen($note) > 600) {
        $errors['note'] = 'Not çok uzun.';
    }
    if (!rate_limit_hit('donation_notify', (string)$userId, 10, 3600)) {
        $errors['form'] = 'Çok fazla bildirim gönderdin. Lütfen bir süre bekle.';
    }

    if ($errors === []) {
        db_exec(
            'UPDATE donations SET sender_name = ?, amount = ?, transfer_date = ?, note = ?,
                show_in_supporters = ?, notify_email = ?, source = "bildirim", status = "beklemede"
             WHERE id = ? AND user_id = ?',
            [
                mb_substr($senderName, 0, 160), (float)$amountRaw, $transferDate,
                $note !== '' ? mb_substr($note, 0, 600) : null,
                $showSupporter ? 1 : 0, $notifyEmail ? 1 : 0,
                (int)$donation['id'], $userId,
            ]
        );
        redirect('/donation-notify.php?code=' . urlencode((string)$donation['code']) . '&done=1');
    }
}

render_head('Havale bildirimi · ' . APP_NAME, ['noindex' => true]);
render_public_header(true);
?>
<main id="main" class="page">
  <div class="focus-area">
    <p class="eyebrow">Destek</p>
    <h1><?= $done ? 'Bildirimin alındı' : 'Havaleyi bildir' ?></h1>

    <?php if ($done):
      $fresh = db_row('SELECT * FROM donations WHERE id = ?', [(int)$donation['id']]);
      $status = (string)($fresh['status'] ?? 'beklemede');
    ?>
      <div class="card card--flush" style="margin-top: 22px;">
        <div class="plan-item is-done">
          <span class="plan-item__state" aria-hidden="true">✓</span>
          <span class="plan-item__title">Bildirimin kaydedildi</span>
        </div>
        <div class="plan-item is-progress">
          <span class="plan-item__state" aria-hidden="true">●</span>
          <span class="plan-item__title">Hesap hareketleri kontrol ediliyor</span>
          <span class="plan-item__meta">1–2 iş günü</span>
        </div>
        <div class="plan-item is-pending">
          <span class="plan-item__state" aria-hidden="true">○</span>
          <span class="plan-item__title">Onaylandığında e-posta gönderilecek</span>
        </div>
      </div>

      <div class="card" style="margin-top: 18px;">
        <p class="eyebrow">Bildirim özeti</p>
        <p class="small" style="margin: 0;">
          Kod: <span class="mono"><?= e((string)$fresh['code']) ?></span><br>
          Tutar: <span class="mono"><?= e(number_format((float)$fresh['amount'], 2, ',', '.')) ?> ₺</span><br>
          Gönderen: <?= e((string)($fresh['sender_name'] ?? '—')) ?><br>
          Tarih: <?= e((string)($fresh['transfer_date'] ?? '—')) ?><br>
          Durum: <?= e(match ($status) {
              'onaylandi' => '✓ Bağışın ulaştı',
              'kod_eslesmedi' => '● Kod eşleşmedi',
              'bulunamadi' => '✕ Bildirim bulunamadı',
              default => '● Bildirimin beklemede',
          }) ?>
        </p>
      </div>

      <div class="row" style="margin-top: 22px;">
        <a class="btn btn--inline" href="/dashboard.php">PANELE DÖN</a>
        <a class="btn btn--secondary btn--inline" href="/profile.php">DESTEK GEÇMİŞİM</a>
      </div>

    <?php else: ?>
      <p class="muted">
        Bildirim zorunlu değildir; hesap hareketleri elle kontrol edilir. Bildirirsen eşleştirme daha hızlı olur.
        <strong>Dekont veya ekran görüntüsü yüklenmez.</strong>
      </p>

      <?php if (isset($errors['form'])): ?>
        <div class="alert alert--error"><span class="alert__icon" aria-hidden="true">✕</span><span><?= e($errors['form']) ?></span></div>
      <?php endif; ?>

      <form method="post" action="/donation-notify.php" data-guard style="max-width: 560px;">
        <?= csrf_field() ?>
        <input type="hidden" name="code" value="<?= e((string)$donation['code']) ?>">

        <div class="field">
          <label for="code_display">Eşleştirme kodun</label>
          <input id="code_display" type="text" class="mono" value="<?= e((string)$donation['code']) ?>" readonly>
          <div class="hint">Bu kod banka açıklamasında yazmalı; havaleyi hesabına bağlayan tek bilgi budur.</div>
        </div>

        <div class="field">
          <label for="sender_name">Gönderen ad soyad</label>
          <input id="sender_name" name="sender_name" type="text" autocomplete="name"
                 value="<?= e((string)($donation['sender_name'] ?? $user['name'])) ?>" required>
          <?php if (isset($errors['sender_name'])): ?><div class="hint text-danger">✕ <?= e($errors['sender_name']) ?></div><?php endif; ?>
          <div class="hint">Bankada kayıtlı isimle aynı olmalı.</div>
        </div>

        <div class="field">
          <label for="amount">Tutar (₺)</label>
          <input id="amount" name="amount" type="text" inputmode="decimal"
                 value="<?= e(number_format((float)$donation['amount'], 2, '.', '')) ?>" required>
          <?php if (isset($errors['amount'])): ?><div class="hint text-danger">✕ <?= e($errors['amount']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="transfer_date">Havale tarihi</label>
          <input id="transfer_date" name="transfer_date" type="date"
                 value="<?= e((string)($donation['transfer_date'] ?? date('Y-m-d'))) ?>" required>
          <?php if (isset($errors['transfer_date'])): ?><div class="hint text-danger">✕ <?= e($errors['transfer_date']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="note">Not (isteğe bağlı)</label>
          <textarea id="note" name="note" rows="3"><?= e((string)($donation['note'] ?? '')) ?></textarea>
          <?php if (isset($errors['note'])): ?><div class="hint text-danger">✕ <?= e($errors['note']) ?></div><?php endif; ?>
        </div>

        <div class="checkline">
          <input id="show_in_supporters" name="show_in_supporters" type="checkbox" value="1"<?= (int)$donation['show_in_supporters'] === 1 ? ' checked' : '' ?>>
          <label for="show_in_supporters">Adım destekçiler listesinde görünsün</label>
        </div>
        <div class="checkline">
          <input id="notify_email" name="notify_email" type="checkbox" value="1"<?= (int)$donation['notify_email'] === 1 ? ' checked' : '' ?>>
          <label for="notify_email">Onaylandığında bana e-posta gönder</label>
        </div>

        <button class="btn btn--lg btn--block" type="submit" style="margin-top: 14px;">BİLDİRİMİ GÖNDER</button>
      </form>
    <?php endif; ?>
  </div>
</main>
<?php
render_public_footer();
render_foot();
