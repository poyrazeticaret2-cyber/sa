<?php
/**
 * AlmancaPro - SMTP ayarlari ve gercek test e-postasi.
 * SMTP sifresi panelde okunamaz; yalnizca maskeli ozet ve degistirme alani vardir.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/mailer.php';
app_boot();

$admin = require_admin();
$errors = [];

if (is_post()) {
    csrf_require();
    $action = (string)input('action', '');

    if ($action === 'save') {
        $host = trim((string)input('smtp_host', ''));
        $port = input_int('smtp_port', 587);
        $username = trim((string)input('smtp_username', ''));
        $encryption = (string)input('smtp_encryption', 'tls');
        $from = trim((string)input('mail_from', ''));
        $fromName = trim((string)input('mail_from_name', APP_NAME));
        $newPass = (string)input('smtp_password', '');

        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $encryption = 'tls';
        }
        if ($host !== '' && !preg_match('/^[A-Za-z0-9._\-]+$/', $host)) {
            $errors['smtp_host'] = 'Geçerli bir sunucu adresi girin.';
        }
        if ($port < 1 || $port > 65535) {
            $errors['smtp_port'] = 'Port 1-65535 arasında olmalı.';
        }
        if ($from !== '' && !valid_email($from)) {
            $errors['mail_from'] = 'Geçerli bir gönderen e-posta adresi girin.';
        }

        if ($errors === []) {
            setting_set('smtp_host', $host);
            setting_set('smtp_port', (string)$port);
            setting_set('smtp_username', $username);
            setting_set('smtp_encryption', $encryption);
            setting_set('mail_from', $from);
            setting_set('mail_from_name', $fromName !== '' ? $fromName : APP_NAME);
            if ($newPass !== '') {
                setting_set('smtp_password', $newPass, true);
                admin_log((int)$admin['id'], 'SMTP_PASSWORD_UPDATED', 'settings', 'smtp_password');
            }
            if (!empty($_POST['clear_password'])) {
                setting_set('smtp_password', '', true);
                admin_log((int)$admin['id'], 'SMTP_PASSWORD_CLEARED', 'settings', 'smtp_password');
            }
            admin_log((int)$admin['id'], 'SMTP_SETTINGS_UPDATED', 'settings', 'smtp', [
                'host' => $host, 'port' => $port, 'encryption' => $encryption,
            ]);
            flash('success', 'SMTP ayarları kaydedildi.');
            redirect('/admin-smtp.php');
        }
    }

    if ($action === 'test') {
        $to = trim((string)input('test_email', ''));
        if (!valid_email($to)) {
            flash('error', 'Geçerli bir test adresi girin.');
            redirect('/admin-smtp.php');
        }
        if (!smtp_is_configured()) {
            flash('error', 'SMTP yapılandırılmadı. Sunucu, gönderen adresi ve kimlik bilgilerini kaydedin.');
            redirect('/admin-smtp.php');
        }
        $html = mail_layout(
            'SMTP Testi',
            '<p>Bu bir AlmancaPro SMTP test e-postasıdır.</p>'
            . '<p>Bu mesajı görüyorsanız e-posta gönderimi doğru yapılandırılmıştır: '
            . 'doğrulama kodları, şifre sıfırlama bağlantıları ve isteğe bağlı hatırlatmalar çalışacaktır.</p>'
        );
        $res = send_mail($to, 'AlmancaPro SMTP testi', $html, "AlmancaPro SMTP test e-postası.\nBu mesajı görüyorsanız e-posta gönderimi çalışıyor.", 'smtp_test');
        admin_log((int)$admin['id'], 'SMTP_TEST', 'settings', 'smtp', ['ok' => $res['ok'] ? 1 : 0]);
        flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? ('Test e-postası gönderildi: ' . $to)
            : ('Gönderilemedi: ' . $res['error']));
        redirect('/admin-smtp.php');
    }
}

$configured = smtp_is_configured();
$hasPassword = (string)setting('smtp_password', '') !== '';
$mailStats = db_row(
    'SELECT COUNT(*) AS total, SUM(status="sent") AS sent, SUM(status="failed") AS failed,
            SUM(DATE(created_at) = UTC_DATE()) AS today FROM mail_log'
) ?? [];
$recent = db_all('SELECT * FROM mail_log ORDER BY id DESC LIMIT 25');

render_admin_start($admin, 'SMTP Ayarları');
?>
<?php if (!$configured): ?>
  <div class="alert alert--warn">
    <span class="alert__icon" aria-hidden="true">●</span>
    <span><strong>SMTP yapılandırılmadı.</strong> Site çalışmaya devam eder, ancak doğrulama kodu ve şifre sıfırlama
    e-postaları gönderilemez. Kullanıcılara bu durum açıkça bildirilir.
    <?php $miss = smtp_missing_fields(); if ($miss !== []): ?>
      <br>Eksik alan<?= count($miss) > 1 ? 'lar' : '' ?>: <strong><?= e(implode(', ', $miss)) ?></strong>.
    <?php endif; ?></span>
  </div>
<?php endif; ?>

<div class="alert alert--info">
  <span class="alert__icon" aria-hidden="true">i</span>
  <span>Doğrulama ve şifre sıfırlama e-postaları <strong><?= e((string)setting('mail_from', '') ?: 'noreply@' . (string)($_SERVER['HTTP_HOST'] ?? 'alanadiniz.com')) ?></strong>
  adresinden gönderilir. Plesk'te <em>Mail</em> bölümünden bu adreste bir posta kutusu oluşturup şifresini
  aşağıdaki <strong>Şifre</strong> alanına yazmanız yeterlidir. Sunucu, port ve şifreleme alanları
  Plesk'in verdiği değerlerle önceden dolduruldu.</span>
</div>

<div class="stat-grid">
  <div class="card stat"><div class="stat__label">Durum</div><div class="stat__value"><?= $configured ? 'Hazır' : 'Eksik' ?></div></div>
  <div class="card stat"><div class="stat__label">Toplam e-posta</div><div class="stat__value"><?= (int)($mailStats['total'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Bugün</div><div class="stat__value"><?= (int)($mailStats['today'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Başarısız</div><div class="stat__value"><?= (int)($mailStats['failed'] ?? 0) ?></div></div>
</div>

<section class="card">
  <h2 class="card__title">Sunucu ayarları</h2>
  <form method="post" action="/admin-smtp.php" class="stack" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <div class="grid grid--3">
      <label class="field">
        <span class="field__label" for="s-host">SMTP sunucusu</span>
        <input class="input<?= isset($errors['smtp_host']) ? ' is-invalid' : '' ?>" id="s-host" name="smtp_host"
          value="<?= e((string)setting('smtp_host', '')) ?>" placeholder="mail.alanadiniz.com" autocomplete="off">
        <?php if (isset($errors['smtp_host'])): ?><span class="field__error">✕ <?= e($errors['smtp_host']) ?></span><?php endif; ?>
      </label>
      <label class="field">
        <span class="field__label" for="s-port">Port</span>
        <input class="input<?= isset($errors['smtp_port']) ? ' is-invalid' : '' ?>" id="s-port" type="number" name="smtp_port"
          min="1" max="65535" value="<?= e((string)setting_int('smtp_port', 587)) ?>">
        <span class="field__hint">TLS için genelde 587, SSL için 465.</span>
        <?php if (isset($errors['smtp_port'])): ?><span class="field__error">✕ <?= e($errors['smtp_port']) ?></span><?php endif; ?>
      </label>
      <label class="field">
        <span class="field__label" for="s-enc">Şifreleme</span>
        <select class="select" id="s-enc" name="smtp_encryption">
          <?php foreach (['tls' => 'STARTTLS (önerilen)', 'ssl' => 'SSL/TLS', 'none' => 'Yok'] as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= (string)setting('smtp_encryption', 'tls') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <div class="grid grid--2">
      <label class="field">
        <span class="field__label" for="s-user">Kullanıcı adı</span>
        <input class="input" id="s-user" name="smtp_username" value="<?= e((string)setting('smtp_username', '')) ?>" autocomplete="off">
      </label>
      <label class="field">
        <span class="field__label" for="s-pass">Şifre (yeni değer)</span>
        <input class="input" id="s-pass" type="password" name="smtp_password" value="" autocomplete="new-password"
          placeholder="<?= $hasPassword ? e(mask_secret((string)setting('smtp_password', ''))) : 'Tanımlı değil' ?>">
        <span class="field__hint">Mevcut şifre panelde okunamaz. Boş bırakırsanız değişmez.</span>
      </label>
    </div>

    <div class="grid grid--2">
      <label class="field">
        <span class="field__label" for="s-from">Gönderen e-posta</span>
        <input class="input<?= isset($errors['mail_from']) ? ' is-invalid' : '' ?>" id="s-from" type="email" name="mail_from"
          value="<?= e((string)setting('mail_from', '')) ?>" autocomplete="off">
        <?php if (isset($errors['mail_from'])): ?><span class="field__error">✕ <?= e($errors['mail_from']) ?></span><?php endif; ?>
      </label>
      <label class="field">
        <span class="field__label" for="s-fromname">Gönderen adı</span>
        <input class="input" id="s-fromname" name="mail_from_name" value="<?= e((string)setting('mail_from_name', APP_NAME)) ?>">
      </label>
    </div>

    <?php if ($hasPassword): ?>
      <label class="check"><input type="checkbox" name="clear_password" value="1"> <span>Mevcut SMTP şifresini sil</span></label>
    <?php endif; ?>

    <div class="row"><button class="btn btn--inline" type="submit">Kaydet</button></div>
  </form>
</section>

<section class="card">
  <h2 class="card__title">Test e-postası</h2>
  <form method="post" action="/admin-smtp.php" class="stack" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="test">
    <label class="field">
      <span class="field__label" for="s-test">Test adresi</span>
      <input class="input" id="s-test" type="email" name="test_email" required
        value="<?= e((string)setting('site_email', '')) ?>">
    </label>
    <div class="row"><button class="btn btn--inline" type="submit"<?= $configured ? '' : ' disabled' ?>>Test E-postası Gönder</button></div>
  </form>
  <p class="small">Gönderim sonucu <code>mail_log</code> tablosuna yazılır. SMTP hataları loglanır, şifre asla loglanmaz.</p>
</section>

<section class="card">
  <h2 class="card__title">Son gönderimler</h2>
  <?php if ($recent === []): ?>
    <?php render_empty('Kayıt yok', 'Henüz e-posta gönderilmedi.'); ?>
  <?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Tarih</th><th>Alıcı</th><th>Konu</th><th>Şablon</th><th>Sonuç</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $m): ?>
      <tr>
        <td class="num small"><?= e(local_datetime((string)$m['created_at'])) ?></td>
        <td class="small"><?= e((string)$m['to_email']) ?></td>
        <td class="small"><?= e(str_limit((string)($m['subject'] ?? ''), 40)) ?></td>
        <td class="small"><?= e((string)($m['template'] ?? '—')) ?></td>
        <td><?= (string)$m['status'] === 'sent' ? '✓ Gönderildi' : '✕ ' . e(str_limit((string)($m['error'] ?? 'Hata'), 60)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
<?php
render_admin_end();
