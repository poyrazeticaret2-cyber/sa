<?php
/**
 * AlmancaPro - Ders listesi ve olusturma.
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

    if ($action === 'create') {
        $title = trim((string)input('title', ''));
        $slug = slugify((string)input('slug', '') !== '' ? (string)input('slug', '') : $title);
        $moduleId = input_int('module_id', 0);
        $type = (string)input('lesson_type', 'grammar');
        $level = (string)input('cefr_level', 'A1');

        if (mb_strlen($title) < 3) {
            $errors['title'] = 'Başlık en az 3 karakter olmalı.';
        }
        if ($slug === '') {
            $errors['slug'] = 'Geçerli bir kısa ad (slug) gerekli.';
        } elseif ((int)db_value('SELECT COUNT(*) FROM lessons WHERE slug = ?', [$slug], 0) > 0) {
            $errors['slug'] = 'Bu slug zaten kullanılıyor.';
        }
        if (!in_array($level, cefr_levels(), true)) {
            $errors['cefr_level'] = 'Geçersiz seviye.';
        }

        if ($errors === []) {
            $sort = (int)db_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lessons', [], 1);
            $id = db_insert(
                'INSERT INTO lessons (module_id, slug, title, cefr_level, lesson_type, objective, summary, estimated_minutes, sort_order, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
                [
                    $moduleId > 0 ? $moduleId : null, $slug, $title, $level, $type,
                    trim((string)input('objective', '')) ?: null,
                    trim((string)input('summary', '')) ?: null,
                    max(3, min(120, input_int('estimated_minutes', 12))),
                    $sort,
                ]
            );
            admin_log((int)$admin['id'], 'LESSON_CREATED', 'lesson', (string)$id, ['title' => $title]);
            flash('success', 'Ders oluşturuldu. Şimdi içeriğini ekleyebilirsin.');
            redirect('/admin-lesson.php?id=' . $id);
        }
    } elseif ($action === 'toggle') {
        $id = input_int('lesson_id', 0);
        db_exec('UPDATE lessons SET is_active = 1 - is_active WHERE id = ?', [$id]);
        admin_log((int)$admin['id'], 'LESSON_UPDATED', 'lesson', (string)$id, ['toggle_active' => true]);
        flash('success', 'Ders durumu değiştirildi.');
        redirect('/admin-lessons.php?' . http_build_query(array_filter(['level' => input('level'), 'q' => input('q')])));
    } elseif ($action === 'delete') {
        $id = input_int('lesson_id', 0);
        $title = (string)db_value('SELECT title FROM lessons WHERE id = ?', [$id], '');
        db_exec('DELETE FROM lessons WHERE id = ?', [$id]);
        admin_log((int)$admin['id'], 'LESSON_DELETED', 'lesson', (string)$id, ['title' => $title]);
        flash('success', 'Ders silindi.');
        redirect('/admin-lessons.php');
    }
}

$level = (string)input('level', '');
$q = trim((string)input('q', ''));
$page = max(1, input_int('page', 1));
$perPage = 30;

$where = ['1=1'];
$params = [];
if (in_array($level, cefr_levels(), true)) {
    $where[] = 'l.cefr_level = ?';
    $params[] = $level;
}
if ($q !== '') {
    $where[] = '(l.title LIKE ? OR l.slug LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$whereSql = implode(' AND ', $where);

$total = (int)db_value('SELECT COUNT(*) FROM lessons l WHERE ' . $whereSql, $params, 0);
$totalPages = (int)max(1, ceil($total / $perPage));
$page = min($page, $totalPages);
$listParams = $params;
$listParams[] = $perPage;
$listParams[] = ($page - 1) * $perPage;

$lessons = db_all(
    'SELECT l.*, m.title AS module_title,
            (SELECT COUNT(*) FROM lesson_sections s WHERE s.lesson_id = l.id) sections,
            (SELECT COUNT(*) FROM exercises e WHERE e.lesson_id = l.id) exercises,
            (SELECT COUNT(*) FROM lesson_vocabulary lv WHERE lv.lesson_id = l.id) vocab,
            (SELECT COUNT(*) FROM lesson_prerequisites lp WHERE lp.lesson_id = l.id) prereqs
     FROM lessons l LEFT JOIN modules m ON m.id = l.module_id
     WHERE ' . $whereSql . '
     ORDER BY FIELD(l.cefr_level,"A0","A1","A2","B1"), l.sort_order LIMIT ? OFFSET ?',
    $listParams
);
$modules = db_all('SELECT id, title, cefr_level FROM modules ORDER BY FIELD(cefr_level,"A0","A1","A2","B1"), sort_order');

render_admin_start($admin, 'Dersler');
?>
<h1>Dersler</h1>
<p style="color: var(--admin-ink-2);">Toplam <span class="mono"><?= $total ?></span> ders.</p>

<form method="get" action="/admin-lessons.php" class="filter-bar" style="margin: 18px 0;">
  <input name="q" type="search" value="<?= e($q) ?>" placeholder="Ders ara" style="max-width: 240px;" aria-label="Ders ara">
  <select name="level" style="max-width: 140px;" aria-label="Seviye">
    <option value="">Tüm seviyeler</option>
    <?php foreach (cefr_levels() as $lv): ?>
      <option value="<?= e($lv) ?>"<?= $level === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn--sm btn--inline" type="submit">FİLTRELE</button>
  <a class="btn btn--sm btn--secondary btn--inline" href="#yeni">YENİ DERS</a>
</form>

<div class="table-wrap">
  <table class="data">
    <thead><tr><th>ID</th><th>Başlık</th><th>Modül</th><th>Seviye</th><th>Tür</th><th>Bölüm</th><th>Kelime</th><th>Alıştırma</th><th>Önkoşul</th><th>Durum</th><th>İşlem</th></tr></thead>
    <tbody>
      <?php foreach ($lessons as $l): ?>
        <tr>
          <td data-label="ID" class="mono"><?= (int)$l['id'] ?></td>
          <td data-label="Başlık"><a href="/admin-lesson.php?id=<?= (int)$l['id'] ?>"><?= e((string)$l['title']) ?></a></td>
          <td data-label="Modül" class="small"><?= e((string)($l['module_title'] ?? '—')) ?></td>
          <td data-label="Seviye" class="mono"><?= e((string)$l['cefr_level']) ?></td>
          <td data-label="Tür" class="small"><?= e((string)$l['lesson_type']) ?></td>
          <td data-label="Bölüm" class="mono"><?= (int)$l['sections'] ?></td>
          <td data-label="Kelime" class="mono"><?= (int)$l['vocab'] ?></td>
          <td data-label="Alıştırma" class="mono"><?= (int)$l['exercises'] ?></td>
          <td data-label="Önkoşul" class="mono"><?= (int)$l['prereqs'] ?></td>
          <td data-label="Durum"><?php render_badge((int)$l['is_active'] === 1 ? '✓' : '○', (int)$l['is_active'] === 1 ? 'AKTİF' : 'PASİF', (int)$l['is_active'] === 1 ? 'ok' : 'muted'); ?></td>
          <td data-label="İşlem">
            <form method="post" action="/admin-lessons.php" style="display: inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="lesson_id" value="<?= (int)$l['id'] ?>">
              <input type="hidden" name="level" value="<?= e($level) ?>">
              <input type="hidden" name="q" value="<?= e($q) ?>">
              <button class="btn btn--sm btn--secondary btn--inline" type="submit" name="action" value="toggle">
                <?= (int)$l['is_active'] === 1 ? 'PASİF YAP' : 'AKTİF YAP' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($lessons === []): ?><tr><td colspan="11">Ders bulunamadı.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php render_pagination($page, $totalPages, '/admin-lessons.php?level=' . urlencode($level) . '&q=' . urlencode($q)); ?>

<section class="admin-card" id="yeni" style="margin-top: 26px; max-width: 720px;">
  <h2 style="font-size: 18px;">Yeni ders oluştur</h2>
  <form method="post" action="/admin-lessons.php" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="grid grid-2" style="gap: 12px;">
      <div class="field">
        <label for="title">Başlık</label>
        <input id="title" name="title" type="text" required>
        <?php if (isset($errors['title'])): ?><div class="hint text-danger">✕ <?= e($errors['title']) ?></div><?php endif; ?>
      </div>
      <div class="field">
        <label for="slug">Kısa ad (slug)</label>
        <input id="slug" name="slug" type="text" placeholder="boş bırakılırsa başlıktan üretilir">
        <?php if (isset($errors['slug'])): ?><div class="hint text-danger">✕ <?= e($errors['slug']) ?></div><?php endif; ?>
      </div>
      <div class="field">
        <label for="module_id">Modül</label>
        <select id="module_id" name="module_id">
          <option value="0">— modülsüz —</option>
          <?php foreach ($modules as $m): ?>
            <option value="<?= (int)$m['id'] ?>"><?= e((string)$m['cefr_level'] . ' · ' . (string)$m['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="cefr_level">Seviye</label>
        <select id="cefr_level" name="cefr_level">
          <?php foreach (cefr_levels() as $lv): ?><option value="<?= e($lv) ?>"><?= e($lv) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="lesson_type">Tür</label>
        <select id="lesson_type" name="lesson_type">
          <?php foreach (['grammar' => 'Dilbilgisi', 'vocabulary' => 'Kelime', 'pronunciation' => 'Telaffuz', 'workplace' => 'İş Almancası', 'daily_life' => 'Günlük hayat', 'review' => 'Tekrar'] as $k => $label): ?>
            <option value="<?= e($k) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="estimated_minutes">Tahmini süre (dk)</label>
        <input id="estimated_minutes" name="estimated_minutes" type="number" min="3" max="120" value="12">
      </div>
    </div>
    <div class="field">
      <label for="objective">Hedef</label>
      <input id="objective" name="objective" type="text">
    </div>
    <div class="field">
      <label for="summary">Özet</label>
      <input id="summary" name="summary" type="text">
    </div>
    <button class="btn btn--inline" type="submit">DERSİ OLUŞTUR</button>
  </form>
</section>
<?php render_admin_end(); ?>
