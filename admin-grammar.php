<?php
/**
 * AlmancaPro - Dilbilgisi konulari yonetimi.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
app_boot();

$admin = require_admin();
$errors = [];

$textFields = ['what_is_it', 'why_used', 'tr_difference', 'sentence_role', 'how_to_recognize', 'rule', 'exceptions', 'common_mistake', 'memory_tip'];
$labels = [
    'what_is_it' => 'Bu nedir?', 'why_used' => 'Neden kullanılır?', 'tr_difference' => 'Türkçeden farkı nedir?',
    'sentence_role' => 'Cümledeki görevi nedir?', 'how_to_recognize' => 'Nasıl anlaşılır?', 'rule' => 'Kural nedir?',
    'exceptions' => 'İstisnalar nelerdir?', 'common_mistake' => 'En sık hata nedir?', 'memory_tip' => 'Kolay hatırlama yolu nedir?',
];

if (is_post()) {
    csrf_require();
    $action = (string)input('action', '');

    if ($action === 'create' || $action === 'update') {
        $title = trim((string)input('title', ''));
        $slug = slugify((string)input('slug', '') !== '' ? (string)input('slug', '') : $title);
        if (mb_strlen($title) < 3) {
            $errors['title'] = 'Başlık en az 3 karakter olmalı.';
        }

        if ($errors === []) {
            $values = [
                input_int('skill_id', 0) > 0 ? input_int('skill_id', 0) : null,
                input_int('lesson_id', 0) > 0 ? input_int('lesson_id', 0) : null,
                $title,
                in_array((string)input('cefr_level', ''), cefr_levels(), true) ? (string)input('cefr_level') : 'A1',
            ];
            foreach ($textFields as $f) {
                $values[] = trim((string)input($f, '')) ?: null;
            }
            $values[] = mb_substr(mb_strtolower(trim((string)input('keywords', $title))), 0, 500);
            $values[] = max(0, input_int('sort_order', 0));

            if ($action === 'create') {
                if ($slug === '' || (int)db_value('SELECT COUNT(*) FROM grammar_topics WHERE slug = ?', [$slug], 0) > 0) {
                    $errors['slug'] = 'Bu slug kullanılamıyor.';
                } else {
                    array_splice($values, 2, 0, [$slug]);
                    $id = db_insert(
                        'INSERT INTO grammar_topics (skill_id, lesson_id, slug, title, cefr_level, what_is_it, why_used,
                            tr_difference, sentence_role, how_to_recognize, rule, exceptions, common_mistake, memory_tip,
                            keywords, sort_order, is_active)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)',
                        $values
                    );
                    admin_log((int)$admin['id'], 'GRAMMAR_CREATED', 'grammar_topic', (string)$id, ['title' => $title]);
                    flash('success', 'Dilbilgisi konusu oluşturuldu.');
                    redirect('/admin-grammar.php?edit=' . $id);
                }
            } else {
                $id = input_int('topic_id', 0);
                $values[] = !empty($_POST['is_active']) ? 1 : 0;
                $values[] = $id;
                db_exec(
                    'UPDATE grammar_topics SET skill_id = ?, lesson_id = ?, title = ?, cefr_level = ?, what_is_it = ?,
                        why_used = ?, tr_difference = ?, sentence_role = ?, how_to_recognize = ?, rule = ?, exceptions = ?,
                        common_mistake = ?, memory_tip = ?, keywords = ?, sort_order = ?, is_active = ? WHERE id = ?',
                    $values
                );
                admin_log((int)$admin['id'], 'GRAMMAR_UPDATED', 'grammar_topic', (string)$id, ['title' => $title]);
                flash('success', 'Konu güncellendi.');
                redirect('/admin-grammar.php?edit=' . $id);
            }
        }
    } elseif ($action === 'example_add') {
        $topicId = input_int('topic_id', 0);
        $de = trim((string)input('de', ''));
        $tr = trim((string)input('tr', ''));
        if ($de !== '' && $tr !== '') {
            $sort = (int)db_value('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM grammar_examples WHERE grammar_topic_id = ?', [$topicId], 0);
            db_exec('INSERT INTO grammar_examples (grammar_topic_id, de, tr, note, sort_order) VALUES (?,?,?,?,?)',
                [$topicId, mb_substr($de, 0, 400), mb_substr($tr, 0, 400), trim((string)input('note', '')) ?: null, $sort]);
            flash('success', 'Örnek eklendi.');
        }
        redirect('/admin-grammar.php?edit=' . $topicId);
    } elseif ($action === 'example_delete') {
        $topicId = input_int('topic_id', 0);
        db_exec('DELETE FROM grammar_examples WHERE id = ? AND grammar_topic_id = ?', [input_int('example_id', 0), $topicId]);
        flash('success', 'Örnek silindi.');
        redirect('/admin-grammar.php?edit=' . $topicId);
    } elseif ($action === 'delete') {
        $id = input_int('topic_id', 0);
        db_exec('DELETE FROM grammar_topics WHERE id = ?', [$id]);
        admin_log((int)$admin['id'], 'GRAMMAR_DELETED', 'grammar_topic', (string)$id);
        flash('success', 'Konu silindi.');
        redirect('/admin-grammar.php');
    }
}

$editId = input_int('edit', 0);
$edit = $editId > 0 ? db_row('SELECT * FROM grammar_topics WHERE id = ?', [$editId]) : null;
$examples = $edit !== null ? db_all('SELECT * FROM grammar_examples WHERE grammar_topic_id = ? ORDER BY sort_order', [$editId]) : [];

$level = (string)input('level', '');
$where = in_array($level, cefr_levels(), true) ? 'WHERE gt.cefr_level = ?' : '';
$params = $where !== '' ? [$level] : [];
$topics = db_all(
    'SELECT gt.*, s.name AS skill_name,
            (SELECT COUNT(*) FROM grammar_examples ge WHERE ge.grammar_topic_id = gt.id) examples
     FROM grammar_topics gt LEFT JOIN skills s ON s.id = gt.skill_id ' . $where . '
     ORDER BY FIELD(gt.cefr_level,"A0","A1","A2","B1"), gt.sort_order',
    $params
);
$skills = db_all('SELECT id, name, cefr_level FROM skills WHERE is_active = 1 ORDER BY sort_order');
$lessons = db_all('SELECT id, title, cefr_level FROM lessons ORDER BY FIELD(cefr_level,"A0","A1","A2","B1"), sort_order');

render_admin_start($admin, 'Dilbilgisi');
?>
<h1>Dilbilgisi konuları</h1>
<p style="color: var(--admin-ink-2);">Her konu aynı şablonla anlatılır. Boş bırakılan bölümler sitede gösterilmez.</p>

<form method="get" action="/admin-grammar.php" class="filter-bar" style="margin: 18px 0;">
  <select name="level" style="max-width: 140px;" aria-label="Seviye" data-autosubmit>
    <option value="">Tüm seviyeler</option>
    <?php foreach (cefr_levels() as $lv): ?><option value="<?= e($lv) ?>"<?= $level === $lv ? ' selected' : '' ?>><?= e($lv) ?></option><?php endforeach; ?>
  </select>
  <a class="btn btn--sm btn--secondary btn--inline" href="/admin-grammar.php#form">YENİ KONU</a>
</form>

<div class="table-wrap">
  <table class="data">
    <thead><tr><th>ID</th><th>Konu</th><th>Seviye</th><th>Skill</th><th>Örnek</th><th>Durum</th></tr></thead>
    <tbody>
      <?php foreach ($topics as $t): ?>
        <tr>
          <td data-label="ID" class="mono"><?= (int)$t['id'] ?></td>
          <td data-label="Konu"><a href="/admin-grammar.php?edit=<?= (int)$t['id'] ?>#form"><?= e((string)$t['title']) ?></a></td>
          <td data-label="Seviye" class="mono"><?= e((string)$t['cefr_level']) ?></td>
          <td data-label="Skill" class="small"><?= e((string)($t['skill_name'] ?? '—')) ?></td>
          <td data-label="Örnek" class="mono"><?= (int)$t['examples'] ?></td>
          <td data-label="Durum"><?php render_badge((int)$t['is_active'] === 1 ? '✓' : '○', (int)$t['is_active'] === 1 ? 'AKTİF' : 'PASİF', (int)$t['is_active'] === 1 ? 'ok' : 'muted'); ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($topics === []): ?><tr><td colspan="6">Konu bulunamadı.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<section class="admin-card" id="form" style="margin-top: 26px;">
  <h2 style="font-size: 18px;"><?= $edit !== null ? 'Konuyu düzenle: ' . e((string)$edit['title']) : 'Yeni dilbilgisi konusu' ?></h2>
  <form method="post" action="/admin-grammar.php" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $edit !== null ? 'update' : 'create' ?>">
    <?php if ($edit !== null): ?><input type="hidden" name="topic_id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

    <div class="grid grid-3" style="gap: 12px;">
      <div class="field">
        <label for="title">Başlık</label>
        <input id="title" name="title" type="text" value="<?= e((string)($edit['title'] ?? '')) ?>" required>
        <?php if (isset($errors['title'])): ?><div class="hint text-danger">✕ <?= e($errors['title']) ?></div><?php endif; ?>
      </div>
      <?php if ($edit === null): ?>
        <div class="field">
          <label for="slug">Slug</label>
          <input id="slug" name="slug" type="text">
          <?php if (isset($errors['slug'])): ?><div class="hint text-danger">✕ <?= e($errors['slug']) ?></div><?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="field">
        <label for="cefr_level_g">Seviye</label>
        <select id="cefr_level_g" name="cefr_level">
          <?php foreach (cefr_levels() as $lv): ?>
            <option value="<?= e($lv) ?>"<?= (string)($edit['cefr_level'] ?? 'A1') === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="skill_id">Skill</label>
        <select id="skill_id" name="skill_id">
          <option value="0">— yok —</option>
          <?php foreach ($skills as $s): ?>
            <option value="<?= (int)$s['id'] ?>"<?= (int)($edit['skill_id'] ?? 0) === (int)$s['id'] ? ' selected' : '' ?>>
              <?= e((string)$s['cefr_level'] . ' · ' . (string)$s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="lesson_id">İlgili ders</label>
        <select id="lesson_id" name="lesson_id">
          <option value="0">— yok —</option>
          <?php foreach ($lessons as $l): ?>
            <option value="<?= (int)$l['id'] ?>"<?= (int)($edit['lesson_id'] ?? 0) === (int)$l['id'] ? ' selected' : '' ?>>
              <?= e((string)$l['cefr_level'] . ' · ' . str_limit((string)$l['title'], 36)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="sort_order">Sıra</label>
        <input id="sort_order" name="sort_order" type="number" min="0" value="<?= (int)($edit['sort_order'] ?? 0) ?>">
      </div>
    </div>

    <?php foreach ($textFields as $f): ?>
      <div class="field">
        <label for="<?= e($f) ?>"><?= e($labels[$f]) ?></label>
        <textarea id="<?= e($f) ?>" name="<?= e($f) ?>" rows="3"><?= e((string)($edit[$f] ?? '')) ?></textarea>
      </div>
    <?php endforeach; ?>

    <div class="field">
      <label for="keywords">Arama anahtar kelimeleri</label>
      <input id="keywords" name="keywords" type="text" value="<?= e((string)($edit['keywords'] ?? '')) ?>">
      <div class="hint">"Öğretmene Sor" bölümündeki arama bu kelimeleri kullanır.</div>
    </div>
    <div class="checkline">
      <input id="is_active" name="is_active" type="checkbox" value="1"<?= (int)($edit['is_active'] ?? 1) === 1 ? ' checked' : '' ?>>
      <label for="is_active">Aktif</label>
    </div>

    <button class="btn btn--inline" type="submit" style="margin-top: 12px;"><?= $edit !== null ? 'GÜNCELLE' : 'KONUYU OLUŞTUR' ?></button>
  </form>

  <?php if ($edit !== null): ?>
    <h3 style="margin-top: 24px; font-size: 16px;">Örnekler (<?= count($examples) ?>)</h3>
    <div class="table-wrap" style="border: 0;">
      <table class="data">
        <thead><tr><th>Almanca</th><th>Türkçe</th><th>Not</th><th>İşlem</th></tr></thead>
        <tbody>
          <?php foreach ($examples as $ex): ?>
            <tr>
              <td data-label="Almanca"><?= e((string)$ex['de']) ?></td>
              <td data-label="Türkçe"><?= e((string)$ex['tr']) ?></td>
              <td data-label="Not" class="small"><?= e((string)($ex['note'] ?? '—')) ?></td>
              <td data-label="İşlem">
                <form method="post" action="/admin-grammar.php" style="margin: 0;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="example_delete">
                  <input type="hidden" name="topic_id" value="<?= (int)$edit['id'] ?>">
                  <input type="hidden" name="example_id" value="<?= (int)$ex['id'] ?>">
                  <button class="btn btn--sm btn--danger btn--inline" type="submit">SİL</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if ($examples === []): ?><tr><td colspan="4">Örnek yok.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <form method="post" action="/admin-grammar.php" style="margin-top: 14px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="example_add">
      <input type="hidden" name="topic_id" value="<?= (int)$edit['id'] ?>">
      <div class="grid grid-3" style="gap: 12px;">
        <div class="field"><label for="ex_de">Almanca</label><input id="ex_de" name="de" type="text" required></div>
        <div class="field"><label for="ex_tr">Türkçe</label><input id="ex_tr" name="tr" type="text" required></div>
        <div class="field"><label for="ex_note">Not</label><input id="ex_note" name="note" type="text"></div>
      </div>
      <button class="btn btn--sm btn--inline" type="submit">ÖRNEK EKLE</button>
    </form>

    <form method="post" action="/admin-grammar.php" style="margin-top: 18px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="topic_id" value="<?= (int)$edit['id'] ?>">
      <button class="btn btn--sm btn--danger btn--inline" type="submit" data-confirm="Konu ve örnekleri silinecek. Emin misin?">KONUYU SİL</button>
    </form>
  <?php endif; ?>
</section>
<?php render_admin_end(); ?>
