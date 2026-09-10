<?php
/**
 * AlmancaPro - Bagis bildirimleri ve seffaflik giderleri.
 * Uygulama tamamen ucretsizdir; bagis gonullu ve yalnizca banka havalesiyledir.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
app_boot();

$admin = require_admin();
$errors = [];

if (is_post()) {
    csrf_require();
    $action = (string)input('action', '');

    if ($action === 'status') {
        $id = input_int('id', 0);
        $status = (string)input('status', '');
        if (!in_array($status, ['beklemede', 'onaylandi', 'kod_eslesmedi', 'bulunamadi'], true)) {
            flash('error', 'Geçersiz durum.');
            redirect('/admin-donations.php');
        }
        $note = trim((string)input('admin_note', ''));
        db_exec(
            'UPDATE donations SET status = ?, admin_note = ?,
                confirmed_at = IF(? = "onaylandi", UTC_TIMESTAMP(), NULL),
                confirmed_by = IF(? = "onaylandi", ?, NULL)
             WHERE id = ?',
            [$status, $note !== '' ? $note : null, $status, $status, (int)$admin['id'], $id]
        );
        admin_log((int)$admin['id'], 'DONATION_STATUS_CHANGED', 'donation', (string)$id, ['status' => $status]);
        flash('success', 'Bağış durumu güncellendi.');
        redirect('/admin-donations.php');
    }

    if ($action === 'settings') {
        $recipient = trim((string)input('donation_recipient', ''));
        $bank = trim((string)input('donation_bank', ''));
        $iban = strtoupper(preg_replace('/\s+/', '', (string)input('donation_iban', '')) ?? '');
        $template = trim((string)input('donation_template', ''));
        if ($iban !== '' && !valid_tr_iban($iban)) {
            $errors['donation_iban'] = 'Geçerli bir TR IBAN girin (TR + 24 hane).';
        }
        if ($template !== '' && !str_contains($template, '{KOD}')) {
            $errors['donation_template'] = 'Şablon {KOD} yer tutucusunu içermelidir.';
        }
        if ($errors === []) {
            setting_set('donation_recipient', $recipient);
            setting_set('donation_bank', $bank);
            setting_set('donation_iban', $iban);
            setting_set('donation_template', $template !== '' ? $template : 'ALMANCAPRO BAGIS · {AD_SOYAD} · {TUTAR} TL · {KOD}');
            setting_set('donation_mask_name', !empty($_POST['donation_mask_name']) ? '1' : '0');
            admin_log((int)$admin['id'], 'DONATION_SETTINGS_UPDATED', 'settings', 'donations');
            flash('success', 'Bağış ayarları kaydedildi.');
            redirect('/admin-donations.php');
        }
    }

    if ($action === 'expense_save') {
        $id = input_int('expense_id', 0);
        $title = trim((string)input('title', ''));
        $category = trim((string)input('category', ''));
        $amount = (float)str_replace(',', '.', (string)input('amount', '0'));
        $period = trim((string)input('period', ''));
        $note = trim((string)input('note', ''));
        $sort = max(0, input_int('sort_order', 0));
        if (mb_strlen($title) < 2) {
            $errors['title'] = 'Gider başlığı gerekli.';
        }
        if ($errors === []) {
            if ($id > 0) {
                db_exec(
                    'UPDATE donation_expenses SET title=?, category=?, amount=?, period=?, note=?, sort_order=? WHERE id=?',
                    [$title, $category ?: null, $amount, $period ?: null, $note ?: null, $sort, $id]
                );
                admin_log((int)$admin['id'], 'EXPENSE_UPDATED', 'donation_expense', (string)$id);
            } else {
                $id = db_insert(
                    'INSERT INTO donation_expenses (title, category, amount, period, note, sort_order) VALUES (?,?,?,?,?,?)',
                    [$title, $category ?: null, $amount, $period ?: null, $note ?: null, $sort]
                );
                admin_log((int)$admin['id'], 'EXPENSE_CREATED', 'donation_expense', (string)$id);
            }
            flash('success', 'Gider kaydı kaydedildi.');
            redirect('/admin-donations.php#expenses');
        }
    }

    if ($action === 'expense_delete') {
        $id = input_int('expense_id', 0);
        db_exec('DELETE FROM donation_expenses WHERE id = ?', [$id]);
        admin_log((int)$admin['id'], 'EXPENSE_DELETED', 'donation_expense', (string)$id);
        flash('success', 'Gider kaydı silindi.');
        redirect('/admin-donations.php#expenses');
    }
}

$status = (string)input('status', 'beklemede');
if (!in_array($status, ['beklemede', 'onaylandi', 'kod_eslesmedi', 'bulunamadi', 'all'], true)) {
    $status = 'beklemede';
}
$page = max(1, input_int('page', 1));
$perPage = 30;
$where = $status === 'all' ? '' : ' WHERE d.status = ?';
$params = $status === 'all' ? [] : [$status];
$total = (int)db_value('SELECT COUNT(*) FROM donations d' . $where, $params, 0);
$totalPages = (int)ceil($total / $perPage);
$rows = db_all(
    'SELECT d.*, u.name AS user_name, u.email FROM donations d LEFT JOIN users u ON u.id = d.user_id' . $where . '
     ORDER BY d.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
    $params
);

$stats = db_row(
    'SELECT COUNT(*) AS total,
            SUM(status="beklemede") AS pending,
            SUM(status="onaylandi") AS approved,
            COALESCE(SUM(CASE WHEN status="onaylandi" THEN amount ELSE 0 END), 0) AS total_amount,
            COALESCE(SUM(CASE WHEN status="onaylandi" AND confirmed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
                THEN amount ELSE 0 END), 0) AS month_amount
     FROM donations'
) ?? [];

$expenses = db_all('SELECT * FROM donation_expenses ORDER BY sort_order, id');
$expenseTotal = array_sum(array_map(static fn(array $r): float => (float)$r['amount'], $expenses));
$expenseEditId = input_int('expense', 0);
$expenseEdit = $expenseEditId > 0 ? db_row('SELECT * FROM donation_expenses WHERE id = ?', [$expenseEditId]) : null;

render_admin_start($admin, 'Bağışlar');
?>
<div class="alert alert--info">
  <span class="alert__icon" aria-hidden="true">i</span>
  <span>AlmancaPro <strong>tamamen ücretsizdir</strong>. Bağış tamamen gönüllüdür, hiçbir dersi, özelliği veya
  içeriği açmaz. Ödeme yalnızca banka havalesiyle yapılır; sistemde kart bilgisi tutulmaz.</span>
</div>

<div class="stat-grid">
  <div class="card stat"><div class="stat__label">Bildirim</div><div class="stat__value"><?= (int)($stats['total'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Bekleyen</div><div class="stat__value"><?= (int)($stats['pending'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Onaylanan</div><div class="stat__value"><?= (int)($stats['approved'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Onaylı toplam</div><div class="stat__value"><?= number_format((float)($stats['total_amount'] ?? 0), 0, ',', '.') ?> TL</div></div>
  <div class="card stat"><div class="stat__label">Son 30 gün</div><div class="stat__value"><?= number_format((float)($stats['month_amount'] ?? 0), 0, ',', '.') ?> TL</div></div>
  <div class="card stat"><div class="stat__label">Aylık gider</div><div class="stat__value"><?= number_format($expenseTotal, 0, ',', '.') ?> TL</div></div>
</div>

<section class="card">
  <h2 class="card__title">Bildirimler</h2>
  <nav class="tabs" aria-label="Durum filtresi">
    <?php foreach (['beklemede' => 'Bekleyen', 'onaylandi' => 'Onaylanan', 'kod_eslesmedi' => 'Kod eşleşmedi', 'bulunamadi' => 'Bulunamadı', 'all' => 'Tümü'] as $k => $label): ?>
      <a class="tab<?= $status === $k ? ' is-active' : '' ?>" href="/admin-donations.php?status=<?= e($k) ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($rows === []): ?>
    <?php render_empty('Bildirim yok', 'Bu durumda bağış bildirimi bulunmuyor.'); ?>
  <?php else: ?>
    <?php foreach ($rows as $d): ?>
      <details class="accordion">
        <summary class="accordion__head">
          <span class="mono"><?= e((string)$d['code']) ?></span>
          <span><?= e((string)($d['sender_name'] ?? $d['user_name'] ?? '—')) ?> · <?= number_format((float)$d['amount'], 2, ',', '.') ?> TL</span>
          <span class="small"><?= e((string)$d['status']) ?> · <?= e(local_datetime((string)$d['created_at'])) ?></span>
        </summary>
        <div class="accordion__body">
          <table class="table">
            <tbody>
              <tr><td>Kullanıcı</td><td><?php if ($d['user_id'] !== null): ?><a href="/admin-user.php?id=<?= (int)$d['user_id'] ?>"><?= e((string)($d['user_name'] ?? '—')) ?></a><?php else: ?>Anonim / silinmiş<?php endif; ?></td></tr>
              <tr><td>Gönderen adı (beyan)</td><td><?= e((string)($d['sender_name'] ?? '—')) ?></td></tr>
              <tr><td>Havale tarihi</td><td><?= e((string)($d['transfer_date'] ?? '—')) ?></td></tr>
              <tr><td>Not</td><td class="small"><?= e((string)($d['note'] ?? '—')) ?></td></tr>
              <tr><td>Destekçilerde göster</td><td><?= (int)$d['show_in_supporters'] === 1 ? '✓ Evet' : '○ Hayır' ?></td></tr>
              <tr><td>Kaynak</td><td><?= e((string)$d['source']) ?></td></tr>
            </tbody>
          </table>
          <form method="post" action="/admin-donations.php" class="stack" data-guard>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <div class="grid grid--2">
              <label class="field">
                <span class="field__label" for="ds-<?= (int)$d['id'] ?>">Durum</span>
                <select class="select" id="ds-<?= (int)$d['id'] ?>" name="status">
                  <?php foreach (['beklemede' => 'Beklemede', 'onaylandi' => 'Onaylandı', 'kod_eslesmedi' => 'Kod eşleşmedi', 'bulunamadi' => 'Hesap hareketi bulunamadı'] as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= (string)$d['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="field">
                <span class="field__label" for="dn-<?= (int)$d['id'] ?>">Yönetici notu</span>
                <input class="input" id="dn-<?= (int)$d['id'] ?>" name="admin_note" value="<?= e((string)($d['admin_note'] ?? '')) ?>">
              </label>
            </div>
            <div class="row"><button class="btn btn--sm btn--inline" type="submit">Güncelle</button></div>
          </form>
        </div>
      </details>
    <?php endforeach; ?>
    <?php render_pagination($page, $totalPages, '/admin-donations.php?status=' . urlencode($status)); ?>
  <?php endif; ?>
</section>

<section class="card">
  <h2 class="card__title">Havale bilgileri</h2>
  <form method="post" action="/admin-donations.php" class="stack" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="settings">
    <div class="grid grid--2">
      <label class="field">
        <span class="field__label" for="dr-name">Alıcı adı</span>
        <input class="input" id="dr-name" name="donation_recipient" value="<?= e((string)setting('donation_recipient', '')) ?>">
      </label>
      <label class="field">
        <span class="field__label" for="dr-bank">Banka</span>
        <input class="input" id="dr-bank" name="donation_bank" value="<?= e((string)setting('donation_bank', '')) ?>">
      </label>
    </div>
    <label class="field">
      <span class="field__label" for="dr-iban">IBAN</span>
      <input class="input<?= isset($errors['donation_iban']) ? ' is-invalid' : '' ?>" id="dr-iban" name="donation_iban"
        value="<?= e(format_iban((string)setting('donation_iban', ''))) ?>" placeholder="TR00 0000 0000 0000 0000 0000 00">
      <?php if (isset($errors['donation_iban'])): ?><span class="field__error">✕ <?= e($errors['donation_iban']) ?></span><?php endif; ?>
    </label>
    <label class="field">
      <span class="field__label" for="dr-tpl">Havale açıklaması şablonu</span>
      <input class="input<?= isset($errors['donation_template']) ? ' is-invalid' : '' ?>" id="dr-tpl" name="donation_template"
        value="<?= e((string)setting('donation_template', '')) ?>">
      <span class="field__hint">Yer tutucular: {AD_SOYAD} {TUTAR} {KOD} {KULLANICI_ID} {TARIH}. {KOD} zorunludur.</span>
      <?php if (isset($errors['donation_template'])): ?><span class="field__error">✕ <?= e($errors['donation_template']) ?></span><?php endif; ?>
    </label>
    <label class="check">
      <input type="checkbox" name="donation_mask_name" value="1"<?= setting_bool('donation_mask_name', true) ? ' checked' : '' ?>>
      <span>Alıcı adını sitede maskeli göster</span>
    </label>
    <div class="row"><button class="btn btn--inline" type="submit">Kaydet</button></div>
  </form>
</section>

<section class="card" id="expenses">
  <h2 class="card__title">Şeffaflık: giderler</h2>
  <p class="small">Bu kalemler destek sayfasında herkese açık gösterilir.</p>

  <form method="post" action="/admin-donations.php#expenses" class="stack" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="expense_save">
    <input type="hidden" name="expense_id" value="<?= (int)($expenseEdit['id'] ?? 0) ?>">
    <div class="grid grid--3">
      <label class="field">
        <span class="field__label" for="ex-title">Başlık</span>
        <input class="input<?= isset($errors['title']) ? ' is-invalid' : '' ?>" id="ex-title" name="title" required
          value="<?= e((string)($expenseEdit['title'] ?? '')) ?>">
        <?php if (isset($errors['title'])): ?><span class="field__error">✕ <?= e($errors['title']) ?></span><?php endif; ?>
      </label>
      <label class="field">
        <span class="field__label" for="ex-cat">Kategori</span>
        <input class="input" id="ex-cat" name="category" value="<?= e((string)($expenseEdit['category'] ?? '')) ?>">
      </label>
      <label class="field">
        <span class="field__label" for="ex-amount">Tutar (TL)</span>
        <input class="input" id="ex-amount" name="amount" inputmode="decimal" value="<?= e((string)($expenseEdit['amount'] ?? '0')) ?>">
      </label>
    </div>
    <div class="grid grid--3">
      <label class="field">
        <span class="field__label" for="ex-period">Dönem</span>
        <input class="input" id="ex-period" name="period" placeholder="aylık" value="<?= e((string)($expenseEdit['period'] ?? '')) ?>">
      </label>
      <label class="field">
        <span class="field__label" for="ex-note">Not</span>
        <input class="input" id="ex-note" name="note" value="<?= e((string)($expenseEdit['note'] ?? '')) ?>">
      </label>
      <label class="field">
        <span class="field__label" for="ex-sort">Sıra</span>
        <input class="input" id="ex-sort" type="number" name="sort_order" min="0" value="<?= (int)($expenseEdit['sort_order'] ?? 0) ?>">
      </label>
    </div>
    <div class="row">
      <button class="btn btn--inline" type="submit"><?= $expenseEdit !== null ? 'Güncelle' : 'Gider Ekle' ?></button>
      <?php if ($expenseEdit !== null): ?><a class="btn btn--secondary btn--inline" href="/admin-donations.php#expenses">Yeni kayıt</a><?php endif; ?>
    </div>
  </form>

  <?php if ($expenses !== []): ?>
  <div class="table-wrap mt-16">
  <table class="table">
    <thead><tr><th>Başlık</th><th>Kategori</th><th>Tutar</th><th>Dönem</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($expenses as $x): ?>
      <tr>
        <td><?= e((string)$x['title']) ?></td>
        <td><?= e((string)($x['category'] ?? '—')) ?></td>
        <td class="num"><?= number_format((float)$x['amount'], 2, ',', '.') ?> TL</td>
        <td><?= e((string)($x['period'] ?? '—')) ?></td>
        <td class="row">
          <a class="btn btn--sm btn--secondary btn--inline" href="/admin-donations.php?expense=<?= (int)$x['id'] ?>#expenses">Düzenle</a>
          <form method="post" action="/admin-donations.php#expenses" data-confirm="Bu gider kaydı silinsin mi?">
            <?= csrf_field() ?><input type="hidden" name="action" value="expense_delete"><input type="hidden" name="expense_id" value="<?= (int)$x['id'] ?>">
            <button class="btn btn--sm btn--danger btn--inline" type="submit">Sil</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
<?php
render_admin_end();
