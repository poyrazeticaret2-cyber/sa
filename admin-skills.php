<?php
/**
 * AlmancaPro - Skill yonetimi ve hakimiyet istatistikleri.
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

    if ($action === 'create' || $action === 'update') {
        $name = trim((string)input('name', ''));
        $code = slugify((string)input('code', '') !== '' ? (string)input('code', '') : $name);
        $code = str_replace('-', '_', $code);
        if (mb_strlen($name) < 3) {
            $errors['name'] = 'Skill adı en az 3 karakter olmalı.';
        }
        if ($code === '') {
            $errors['code'] = 'Geçerli bir kod gerekli.';
        }

        if ($errors === []) {
            $params = [
                $name,
                (string)input('category', 'grammar'),
                in_array((string)input('cefr_level', ''), cefr_levels(), true) ? (string)input('cefr_level') : 'A1',
                trim((string)input('description', '')) ?: null,
                max(50, min(100, input_int('mastery_threshold', 90))),
                !empty($_POST['is_critical']) ? 1 : 0,
                !empty($_POST['requires_production']) ? 1 : 0,
                !empty($_POST['requires_spelling']) ? 1 : 0,
                max(0, input_int('sort_order', 0)),
            ];
            if ($action === 'create') {
                if ((int)db_value('SELECT COUNT(*) FROM skills WHERE code = ?', [$code], 0) > 0) {
                    $errors['code'] = 'Bu kod zaten kullanılıyor.';
                } else {
                    array_unshift($params, $code);
                    $id = db_insert(
                        'INSERT INTO skills (code, name, category, cefr_level, description, mastery_threshold,
                            is_critical, requires_production, requires_spelling, sort_order, is_active)
                         VALUES (?,?,?,?,?,?,?,?,?,?,1)',
                        $params
                    );
                    admin_log((int)$admin['id'], 'SKILL_CREATED', 'skill', (string)$id, ['code' => $code]);
                    flash('success', 'Skill oluşturuldu.');
                    redirect('/admin-skills.php?edit=' . $id);
                }
            } else {
                $id = input_int('skill_id', 0);
                $params[] = !empty($_POST['is_active']) ? 1 : 0;
                $params[] = $id;
                db_exec(
                    'UPDATE skills SET name = ?, category = ?, cefr_level = ?, description = ?, mastery_threshold = ?,
                        is_critical = ?, requires_production = ?, requires_spelling = ?, sort_order = ?, is_active = ?
                     WHERE id = ?',
                    $params
                );
                admin_log((int)$admin['id'], 'SKILL_UPDATED', 'skill', (string)$id, ['name' => $name]);
                flash('success', 'Skill güncellendi.');
                redirect('/admin-skills.php?edit=' . $id);
            }
        }
    }
}

$editId = input_int('edit', 0);
$edit = $editId > 0 ? db_row('SELECT * FROM skills WHERE id = ?', [$editId]) : null;

$level = (string)input('level', '');
$q = trim((string)input('q', ''));
$where = ['1=1'];
$params = [];
if (in_array($level, cefr_levels(), true)) {
    $where[] = 's.cefr_level = ?';
    $params[] = $level;
}
if ($q !== '') {
    $where[] = '(s.name LIKE ? OR s.code LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

$skills = db_all(
    'SELECT s.*,
            (SELECT COUNT(*) FROM exercises e WHERE e.skill_id = s.id) exercises,
            (SELECT COUNT(*) FROM user_skill_mastery m WHERE m.skill_id = s.id AND m.attempts > 0) learners,
            (SELECT COALESCE(AVG(m.mastery_score), 0) FROM user_skill_mastery m WHERE m.skill_id = s.id AND m.attempts > 0) avg_mastery,
            (SELECT COUNT(*) FROM answer_error_categories a WHERE a.skill_id = s.id) errors
     FROM skills s WHERE ' . implode(' AND ', $where) . '
     ORDER BY FIELD(s.cefr_level,"A0","A1","A2","B1"), s.sort_order',
    $params
);

render_admin_start($admin, 'Skills');
?>
<h1>Skills</h1>
<p style="color: var(--admin-ink-2);">Her öğrenilebilir unsur bağımsız bir skill olarak takip edilir.
  Ders kilitleri bu skill'lerin mastery değerine bakar.</p>

<form method="get" action="/admin-skills.php" class="filter-bar" style="margin: 18px 0;">
  <input name="q" type="search" value="<?= e($q) ?>" placeholder="Skill ara" style="max-width: 240px;" aria-label="Ara">
  <select name="level" style="max-width: 140px;" aria-label="Seviye">
    <option value="">Tüm seviyeler</option>
    <?php foreach (cefr_levels() as $lv): ?><option value="<?= e($lv) ?>"<?= $level === $lv ? ' selected' : '' ?>><?= e($lv) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn--sm btn--inline" type="submit">FİLTRELE</button>
  <a class="btn btn--sm btn--secondary btn--inline" href="/admin-skills.php#form">YENİ SKILL</a>
</form>

<div class="table-wrap">
  <table class="data">
    <thead><tr><th>ID</th><th>Skill</th><th>Kod</th><th>Seviye</th><th>Eşik</th><th>Alıştırma</th><th>Öğrenen</th><th>Ort. mastery</th><th>Hata</th><th>Durum</th></tr></thead>
    <tbody>
      <?php foreach ($skills as $s): ?>
        <tr>
          <td data-label="ID" class="mono"><?= (int)$s['id'] ?></td>
          <td data-label="Skill"><a href="/admin-skills.php?edit=<?= (int)$s['id'] ?>#form"><?= e((string)$s['name']) ?></a></td>
          <td data-label="Kod" class="mono small"><?= e((string)$s['code']) ?></td>
          <td data-label="Seviye" class="mono"><?= e((string)$s['cefr_level']) ?></td>
          <td data-label="Eşik" class="mono"><?= (int)$s['mastery_threshold'] ?>%</td>
          <td data-label="Alıştırma" class="mono"><?= (int)$s['exercises'] ?></td>
          <td data-label="Öğrenen" class="mono"><?= (int)$s['learners'] ?></td>
          <td data-label="Ort. mastery" class="mono"><?= (int)round((float)$s['avg_mastery']) ?>%</td>
          <td data-label="Hata" class="mono"><?= (int)$s['errors'] ?></td>
          <td data-label="Durum"><?php render_badge((int)$s['is_active'] === 1 ? '✓' : '○', (int)$s['is_active'] === 1 ? 'AKTİF' : 'PASİF', (int)$s['is_active'] === 1 ? 'ok' : 'muted'); ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($skills === []): ?><tr><td colspan="10">Skill bulunamadı.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<section class="admin-card" id="form" style="margin-top: 26px; max-width: 760px;">
  <h2 style="font-size: 18px;"><?= $edit !== null ? 'Skill düzenle: ' . e((string)$edit['name']) : 'Yeni skill' ?></h2>
  <form method="post" action="/admin-skills.php" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $edit !== null ? 'update' : 'create' ?>">
    <?php if ($edit !== null): ?><input type="hidden" name="skill_id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

    <div class="grid grid-2" style="gap: 12px;">
      <div class="field">
        <label for="name">Ad</label>
        <input id="name" name="name" type="text" value="<?= e((string)($edit['name'] ?? '')) ?>" required>
        <?php if (isset($errors['name'])): ?><div class="hint text-danger">✕ <?= e($errors['name']) ?></div><?php endif; ?>
      </div>
      <div class="field">
        <label for="code">Kod</label>
        <input id="code" name="code" type="text" value="<?= e((string)($edit['code'] ?? '')) ?>"<?= $edit !== null ? ' readonly' : '' ?>>
        <?php if (isset($errors['code'])): ?><div class="hint text-danger">✕ <?= e($errors['code']) ?></div><?php endif; ?>
      </div>
      <div class="field">
        <label for="category">Kategori</label>
        <select id="category" name="category">
          <?php foreach (['grammar' => 'Dilbilgisi', 'vocabulary' => 'Kelime', 'pronunciation' => 'Telaffuz', 'function' => 'İşlevsel dil', 'workplace' => 'İş Almancası', 'daily_life' => 'Günlük hayat', 'skill' => 'Beceri'] as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= (string)($edit['category'] ?? 'grammar') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="cefr_level_s">Seviye</label>
        <select id="cefr_level_s" name="cefr_level">
          <?php foreach (cefr_levels() as $lv): ?>
            <option value="<?= e($lv) ?>"<?= (string)($edit['cefr_level'] ?? 'A1') === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="mastery_threshold">Mastery eşiği (%)</label>
        <input id="mastery_threshold" name="mastery_threshold" type="number" min="50" max="100" value="<?= (int)($edit['mastery_threshold'] ?? 90) ?>">
      </div>
      <div class="field">
        <label for="sort_order">Sıra</label>
        <input id="sort_order" name="sort_order" type="number" min="0" value="<?= (int)($edit['sort_order'] ?? 0) ?>">
      </div>
    </div>

    <div class="field">
      <label for="description">Açıklama</label>
      <textarea id="description" name="description" rows="2"><?= e((string)($edit['description'] ?? '')) ?></textarea>
    </div>

    <div class="row">
      <span class="checkline"><input id="is_critical" name="is_critical" type="checkbox" value="1"<?= (int)($edit['is_critical'] ?? 1) === 1 ? ' checked' : '' ?>><label for="is_critical">Kritik skill</label></span>
      <span class="checkline"><input id="requires_production" name="requires_production" type="checkbox" value="1"<?= (int)($edit['requires_production'] ?? 1) === 1 ? ' checked' : '' ?>><label for="requires_production">Üretim kanıtı gerekli</label></span>
      <span class="checkline"><input id="requires_spelling" name="requires_spelling" type="checkbox" value="1"<?= (int)($edit['requires_spelling'] ?? 0) === 1 ? ' checked' : '' ?>><label for="requires_spelling">Yazım kanıtı gerekli</label></span>
      <span class="checkline"><input id="is_active" name="is_active" type="checkbox" value="1"<?= (int)($edit['is_active'] ?? 1) === 1 ? ' checked' : '' ?>><label for="is_active">Aktif</label></span>
    </div>

    <button class="btn btn--inline" type="submit" style="margin-top: 12px;"><?= $edit !== null ? 'GÜNCELLE' : 'SKILL OLUŞTUR' ?></button>
  </form>
  <p class="small" style="color: var(--admin-ink-2); margin-top: 14px;">
    "Üretim kanıtı gerekli" işaretliyse kullanıcı yalnızca çoktan seçmeli sorularla bu skill'i mastered yapamaz.
  </p>
</section>
<?php render_admin_end(); ?>
