<?php
/**
 * AlmancaPro - Mufredat: modul agaci, sira ve onkosul esikleri.
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

    if ($action === 'module_create') {
        $title = trim((string)input('title', ''));
        $slug = slugify((string)input('slug', '') !== '' ? (string)input('slug', '') : $title);
        $level = (string)input('cefr_level', 'A1');
        if (mb_strlen($title) < 3) {
            $errors['title'] = 'Modül adı en az 3 karakter olmalı.';
        } elseif ($slug === '' || (int)db_value('SELECT COUNT(*) FROM modules WHERE slug = ?', [$slug], 0) > 0) {
            $errors['slug'] = 'Bu slug kullanılamıyor.';
        } elseif (!in_array($level, cefr_levels(), true)) {
            $errors['cefr_level'] = 'Geçersiz seviye.';
        } else {
            $sort = (int)db_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM modules', [], 1);
            $id = db_insert(
                'INSERT INTO modules (slug, cefr_level, title, description, sort_order, is_active) VALUES (?,?,?,?,?,1)',
                [$slug, $level, $title, trim((string)input('description', '')) ?: null, $sort]
            );
            admin_log((int)$admin['id'], 'MODULE_CREATED', 'module', (string)$id, ['title' => $title]);
            flash('success', 'Modül oluşturuldu.');
            redirect('/admin-curriculum.php');
        }
    } elseif ($action === 'module_update') {
        $id = input_int('module_id', 0);
        db_exec(
            'UPDATE modules SET title = ?, cefr_level = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?',
            [
                trim((string)input('title', '')),
                in_array((string)input('cefr_level', ''), cefr_levels(), true) ? (string)input('cefr_level') : 'A1',
                trim((string)input('description', '')) ?: null,
                max(0, input_int('sort_order', 0)),
                !empty($_POST['is_active']) ? 1 : 0,
                $id,
            ]
        );
        admin_log((int)$admin['id'], 'MODULE_UPDATED', 'module', (string)$id);
        flash('success', 'Modül güncellendi.');
        redirect('/admin-curriculum.php');
    } elseif ($action === 'lesson_order') {
        $orders = input_array('order');
        $n = 0;
        foreach ($orders as $lessonId => $value) {
            db_exec('UPDATE lessons SET sort_order = ? WHERE id = ?', [max(0, (int)$value), (int)$lessonId]);
            $n++;
        }
        admin_log((int)$admin['id'], 'CURRICULUM_REORDERED', 'lesson', null, ['count' => $n]);
        flash('success', $n . ' dersin sırası güncellendi.');
        redirect('/admin-curriculum.php');
    } elseif ($action === 'threshold_bulk') {
        $threshold = max(10, min(100, input_int('required_mastery', 90)));
        $level = (string)input('level_scope', '');
        if (in_array($level, cefr_levels(), true)) {
            $n = db_exec(
                'UPDATE lesson_prerequisites lp JOIN lessons l ON l.id = lp.lesson_id
                 SET lp.required_mastery = ? WHERE l.cefr_level = ?',
                [$threshold, $level]
            );
            admin_log((int)$admin['id'], 'CURRICULUM_THRESHOLD_CHANGED', 'lesson', null, ['level' => $level, 'threshold' => $threshold]);
            flash('success', $level . ' seviyesindeki ' . $n . ' önkoşul eşiği %' . $threshold . ' yapıldı.');
        }
        redirect('/admin-curriculum.php');
    }
}

$modules = db_all(
    'SELECT m.*,
            (SELECT COUNT(*) FROM lessons l WHERE l.module_id = m.id) lessons,
            (SELECT COUNT(*) FROM lessons l WHERE l.module_id = m.id AND l.is_active = 1) active_lessons
     FROM modules m ORDER BY FIELD(m.cefr_level,"A0","A1","A2","B1"), m.sort_order'
);
$lessonsByModule = [];
foreach (db_all(
    'SELECT l.id, l.title, l.module_id, l.sort_order, l.cefr_level, l.is_active,
            (SELECT COUNT(*) FROM lesson_prerequisites lp WHERE lp.lesson_id = l.id) prereqs
     FROM lessons l ORDER BY l.sort_order'
) as $l) {
    $lessonsByModule[(int)($l['module_id'] ?? 0)][] = $l;
}

$prereqStats = db_all(
    'SELECT l.cefr_level, COUNT(*) c, AVG(lp.required_mastery) avg_threshold
     FROM lesson_prerequisites lp JOIN lessons l ON l.id = lp.lesson_id
     GROUP BY l.cefr_level'
);

render_admin_start($admin, 'Müfredat');
?>
<h1>Müfredat</h1>
<p style="color: var(--admin-ink-2);">Seviye → modül → ders ağacı ve önkoşul eşikleri.
  Sıra değeri küçük olan ders önce gelir; kilit mantığı bu sıraya değil, önkoşul mastery değerine bakar.</p>

<div class="grid grid-4" style="margin: 20px 0;">
  <?php foreach (cefr_levels() as $lv):
      $stat = null;
      foreach ($prereqStats as $ps) {
          if ((string)$ps['cefr_level'] === $lv) { $stat = $ps; break; }
      } ?>
    <div class="admin-stat">
      <div class="admin-stat__num"><?= $stat !== null ? (int)round((float)$stat['avg_threshold']) . '%' : '—' ?></div>
      <div class="admin-stat__label"><?= e($lv) ?> ortalama önkoşul eşiği (<?= $stat !== null ? (int)$stat['c'] : 0 ?> kayıt)</div>
    </div>
  <?php endforeach; ?>
</div>

<section class="admin-card" style="margin-bottom: 22px;">
  <h2 style="font-size: 18px;">Toplu eşik değiştirme</h2>
  <form method="post" action="/admin-curriculum.php" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="threshold_bulk">
    <div class="row">
      <div class="field" style="margin: 0; max-width: 160px;">
        <label for="level_scope">Seviye</label>
        <select id="level_scope" name="level_scope">
          <?php foreach (cefr_levels() as $lv): ?><option value="<?= e($lv) ?>"><?= e($lv) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin: 0; max-width: 160px;">
        <label for="required_mastery">Eşik (%)</label>
        <input id="required_mastery" name="required_mastery" type="number" min="10" max="100" value="90">
      </div>
      <button class="btn btn--sm btn--inline" type="submit"
              data-confirm="Seçilen seviyedeki bütün önkoşul eşikleri değiştirilecek. Devam edilsin mi?">UYGULA</button>
    </div>
  </form>
</section>

<form method="post" action="/admin-curriculum.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="lesson_order">
  <?php foreach (cefr_levels() as $lv):
      $levelModules = array_values(array_filter($modules, static fn ($m) => (string)$m['cefr_level'] === $lv));
      if ($levelModules === []) { continue; } ?>
    <h2 style="margin-top: 26px;"><?= e($lv) ?> — <?= count($levelModules) ?> modül</h2>
    <?php foreach ($levelModules as $m): ?>
      <section class="admin-card" style="margin-bottom: 14px;">
        <details class="acc" style="background: transparent; border: 0;">
          <summary style="padding: 0 0 10px;">
            <?= e((string)$m['title']) ?>
            <span class="small" style="color: var(--admin-ink-2);">
              · <?= (int)$m['active_lessons'] ?>/<?= (int)$m['lessons'] ?> aktif ders · sıra <?= (int)$m['sort_order'] ?>
            </span>
          </summary>
          <div class="acc__body" style="padding: 0;">
            <div class="table-wrap" style="border: 0;">
              <table class="data">
                <thead><tr><th>Sıra</th><th>Ders</th><th>Önkoşul</th><th>Durum</th><th>Düzenle</th></tr></thead>
                <tbody>
                  <?php foreach ($lessonsByModule[(int)$m['id']] ?? [] as $l): ?>
                    <tr>
                      <td data-label="Sıra"><input name="order[<?= (int)$l['id'] ?>]" type="number" min="0" value="<?= (int)$l['sort_order'] ?>" style="max-width: 90px;" aria-label="Sıra"></td>
                      <td data-label="Ders"><?= e((string)$l['title']) ?></td>
                      <td data-label="Önkoşul" class="mono"><?= (int)$l['prereqs'] ?></td>
                      <td data-label="Durum"><?php render_badge((int)$l['is_active'] === 1 ? '✓' : '○', (int)$l['is_active'] === 1 ? 'AKTİF' : 'PASİF', (int)$l['is_active'] === 1 ? 'ok' : 'muted'); ?></td>
                      <td data-label="Düzenle"><a class="btn btn--sm btn--secondary btn--inline" href="/admin-lesson.php?id=<?= (int)$l['id'] ?>">AÇ</a></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (empty($lessonsByModule[(int)$m['id']])): ?><tr><td colspan="5">Bu modülde ders yok.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </details>
      </section>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <?php if (!empty($lessonsByModule[0])): ?>
    <h2 style="margin-top: 26px;">Modülsüz dersler</h2>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Sıra</th><th>Ders</th><th>Seviye</th><th>Düzenle</th></tr></thead>
        <tbody>
          <?php foreach ($lessonsByModule[0] as $l): ?>
            <tr>
              <td data-label="Sıra"><input name="order[<?= (int)$l['id'] ?>]" type="number" min="0" value="<?= (int)$l['sort_order'] ?>" style="max-width: 90px;" aria-label="Sıra"></td>
              <td data-label="Ders"><?= e((string)$l['title']) ?></td>
              <td data-label="Seviye" class="mono"><?= e((string)$l['cefr_level']) ?></td>
              <td data-label="Düzenle"><a class="btn btn--sm btn--secondary btn--inline" href="/admin-lesson.php?id=<?= (int)$l['id'] ?>">AÇ</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <button class="btn btn--inline" type="submit" style="margin-top: 18px;">SIRALAMAYI KAYDET</button>
</form>

<section class="admin-card" style="margin-top: 26px; max-width: 720px;">
  <h2 style="font-size: 18px;">Yeni modül</h2>
  <form method="post" action="/admin-curriculum.php" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="module_create">
    <div class="grid grid-3" style="gap: 12px;">
      <div class="field">
        <label for="title">Modül adı</label>
        <input id="title" name="title" type="text" required>
        <?php if (isset($errors['title'])): ?><div class="hint text-danger">✕ <?= e($errors['title']) ?></div><?php endif; ?>
      </div>
      <div class="field">
        <label for="slug">Slug</label>
        <input id="slug" name="slug" type="text">
        <?php if (isset($errors['slug'])): ?><div class="hint text-danger">✕ <?= e($errors['slug']) ?></div><?php endif; ?>
      </div>
      <div class="field">
        <label for="cefr_level">Seviye</label>
        <select id="cefr_level" name="cefr_level">
          <?php foreach (cefr_levels() as $lv): ?><option value="<?= e($lv) ?>"><?= e($lv) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field">
      <label for="description">Açıklama</label>
      <textarea id="description" name="description" rows="2"></textarea>
    </div>
    <button class="btn btn--inline" type="submit">MODÜL OLUŞTUR</button>
  </form>
</section>
<?php render_admin_end(); ?>
