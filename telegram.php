<?php
/**
 * AlmancaPro - Telegram baglama sayfasi.
 * Kullanicidan chat_id, telefon numarasi veya API bilgisi ISTENMEZ.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
require_once __DIR__ . '/telegram-api.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];
$tz = user_timezone($user);
$counts = due_review_counts($userId);

$connection = telegram_connection($userId);
$link = null;
$error = '';

if (is_post()) {
    csrf_require();
    $action = (string)input('action', '');

    if ($action === 'link') {
        if (!telegram_is_configured()) {
            $error = 'Telegram botu henüz yapılandırılmadı. Yönetici ayarları tamamladığında bağlanabileceksin.';
        } elseif (!rate_limit_hit('tg_link', (string)$userId, 10, 3600)) {
            $error = 'Çok fazla bağlantı isteği oluşturdun. Lütfen bir süre bekle.';
        } else {
            $link = telegram_create_link($userId);
            if ($link['deep_link'] === '') {
                $error = 'Bot kullanıcı adı ayarlanmamış. Yönetici ayarları tamamlamalı.';
                $link = null;
            }
        }
    } elseif ($action === 'unlink') {
        db_exec('DELETE FROM telegram_connections WHERE user_id = ?', [$userId]);
        db_exec('UPDATE telegram_link_tokens SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND used_at IS NULL', [$userId]);
        flash('success', 'Telegram bağlantısı kaldırıldı. Artık Telegram üzerinden bildirim gönderilmeyecek.');
        redirect('/telegram.php');
    } elseif ($action === 'quiz_toggle') {
        db_exec('UPDATE telegram_connections SET quiz_enabled = ? WHERE user_id = ?', [!empty($_POST['quiz_enabled']) ? 1 : 0, $userId]);
        flash('success', 'Telegram quiz tercihin güncellendi.');
        redirect('/telegram.php');
    }
}

$prefs = db_row('SELECT * FROM notification_preferences WHERE user_id = ?', [$userId]) ?? [];
$recentSends = db_all(
    'SELECT channel, template, status, created_at FROM notification_log WHERE user_id = ? AND channel = "telegram" ORDER BY id DESC LIMIT 5',
    [$userId]
);

render_app_start($user, 'Telegram · ' . APP_NAME, [], ['review.php' => $counts['due']]);
?>
<p class="eyebrow">Telegram</p>
<h1>Telegram bağlantısı</h1>

<?php if ($error !== ''): ?>
  <div class="alert alert--error"><span class="alert__icon" aria-hidden="true">✕</span><span><?= e($error) ?></span></div>
<?php endif; ?>

<?php if ($connection !== null): ?>
  <div class="card card--ok" style="margin-top: 20px;">
    <div class="row-between">
      <div>
        <p style="margin: 0 0 6px;">
          <span class="badge badge--ok"><span aria-hidden="true">✓</span>TELEGRAM BAĞLI</span>
          <?php if (!empty($connection['username'])): ?>
            <span class="mono small">@<?= e((string)$connection['username']) ?></span>
          <?php endif; ?>
        </p>
        <p class="small muted" style="margin: 0;">
          Bağlantı tarihi: <?= e(local_datetime((string)$connection['linked_at'], 'd.m.Y H:i', $tz)) ?>
          <?php if (!empty($connection['last_interaction_at'])): ?>
            · Son etkileşim: <?= e(local_datetime((string)$connection['last_interaction_at'], 'd.m.Y H:i', $tz)) ?>
          <?php endif; ?>
        </p>
      </div>
      <form method="post" action="/telegram.php" style="margin: 0;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="unlink">
        <button class="btn btn--danger btn--inline" type="submit"
                data-confirm="Telegram bağlantısı kaldırılacak. Emin misin?">BAĞLANTIYI KES</button>
      </form>
    </div>
  </div>

  <div class="grid grid-2" style="margin-top: 22px;">
    <section class="card">
      <h2 style="font-size: 18px;">Telegram tercihleri</h2>
      <form method="post" action="/telegram.php" data-guard>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="quiz_toggle">
        <div class="checkline">
          <input id="quiz_enabled" name="quiz_enabled" type="checkbox" value="1"<?= (int)$connection['quiz_enabled'] === 1 ? ' checked' : '' ?>>
          <label for="quiz_enabled">Telegram'da mini quiz gönder</label>
        </div>
        <p class="small muted">
          Telegram'da verdiğin cevaplar web sitesindeki öğrenme verinle aynı yere yazılır:
          mastery, tekrar kuyruğu ve hata analizi birlikte güncellenir.
        </p>
        <button class="btn btn--sm btn--inline" type="submit">KAYDET</button>
      </form>
      <p class="small muted" style="margin-top: 14px;">
        Bildirim saatleri ve sessiz saatler <a href="/settings.php">Ayarlar</a> sayfasından yönetilir.
        Sessiz saat: <?= e(substr((string)($prefs['quiet_start'] ?? '22:00:00'), 0, 5)) ?> – <?= e(substr((string)($prefs['quiet_end'] ?? '08:00:00'), 0, 5)) ?>
      </p>
    </section>

    <section class="card card--flush">
      <div class="card__head"><strong>Bot komutları</strong></div>
      <div style="padding: 18px;">
        <ul class="small" style="margin: 0; padding-left: 18px;">
          <li><span class="mono">/ders</span> — sıradaki dersi gönderir</li>
          <li><span class="mono">/quiz</span> — hızlı bir soru sorar</li>
          <li><span class="mono">/tekrar</span> — tekrar zamanı gelen kartları gösterir</li>
          <li><span class="mono">/durum</span> — bugünkü hedefin ve ilerlemen</li>
          <li><span class="mono">/seri</span> — güncel serin</li>
          <li><span class="mono">/hatirlat</span> — hatırlatmaları açar</li>
          <li><span class="mono">/ayarlar</span> — bildirim tercihleri</li>
          <li><span class="mono">/durdur</span> — bildirimleri kapatır</li>
          <li><span class="mono">/yardim</span> — komut listesi</li>
        </ul>
      </div>
    </section>
  </div>

  <?php if ($recentSends !== []): ?>
    <section class="card card--flush" style="margin-top: 22px;">
      <div class="card__head"><strong>Son gönderimler</strong></div>
      <?php foreach ($recentSends as $s): ?>
        <div class="plan-item">
          <span class="plan-item__state" aria-hidden="true"><?= (string)$s['status'] === 'sent' ? '✓' : '✕' ?></span>
          <span class="plan-item__title"><?= e((string)$s['template']) ?></span>
          <span class="plan-item__meta"><?= e(local_datetime((string)$s['created_at'], 'd.m H:i', $tz)) ?></span>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

<?php elseif ($link !== null): ?>
  <div class="card card--strong" style="margin-top: 20px; max-width: 560px;">
    <p class="eyebrow">Son adım</p>
    <h2 style="font-size: 20px;">Botu aç ve Başlat'a bas</h2>
    <p class="small">
      Aşağıdaki bağlantı yalnızca senin hesabın için üretildi ve 30 dakika geçerli.
      Telegram açıldığında <strong>Başlat / Start</strong> düğmesine basman yeterli.
    </p>
    <a class="btn btn--lg btn--block" href="<?= e($link['deep_link']) ?>" target="_blank" rel="noopener">TELEGRAM'DA AÇ</a>
    <p class="small muted" style="margin-top: 14px;">
      Bağlantı açılmazsa şu adresi tarayıcına yapıştırabilirsin:
    </p>
    <p class="mono small" style="word-break: break-all;"><?= e($link['deep_link']) ?></p>
    <button class="btn btn--sm btn--secondary btn--inline" type="button" data-copy="<?= e($link['deep_link']) ?>">BAĞLANTIYI KOPYALA</button>
    <p class="small muted" style="margin-top: 16px;">
      Başlat'a bastıktan sonra bu sayfayı yenile; bağlantı kurulduğunda "✓ Telegram bağlı" göreceksin.
    </p>
  </div>

<?php else: ?>
  <div class="card" style="margin-top: 20px; max-width: 640px;">
    <h2 style="font-size: 18px;">Telegram'ı bağlarsan ne olur?</h2>
    <ul class="small">
      <li>Günlük çalışma hatırlatması gelir</li>
      <li>Tekrar zamanı geldiğinde haber verilir</li>
      <li>Mini quizleri doğrudan Telegram'da çözebilirsin</li>
      <li>Telegram'daki cevapların mastery ve tekrar sistemine işlenir</li>
      <li>Seri riskin olduğunda uyarılırsın</li>
    </ul>
    <p class="small muted">
      Senden telefon numarası, chat ID veya herhangi bir teknik bilgi istenmez.
      Bağlantı, sana özel tek kullanımlık bir bağlantıyla kurulur.
    </p>

    <?php if (!telegram_is_configured()): ?>
      <div class="alert alert--warn"><span class="alert__icon" aria-hidden="true">●</span>
        <span>Telegram botu henüz yapılandırılmadı. Yönetici ayarları tamamladığında bu bölüm aktif olacak.</span></div>
    <?php else: ?>
      <form method="post" action="/telegram.php" data-guard>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="link">
        <button class="btn btn--lg btn--inline" type="submit">TELEGRAM'I BAĞLA</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php render_app_end(); ?>
