<?php
/**
 * AlmancaPro - Alistirma yonetimi (tam CRUD + secenekler).
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
app_boot();

$admin = require_admin();
$errors = [];

$types = [
    'multiple_choice' => 'Çoktan seçmeli', 'text_input' => 'Metin girişi',
    'translation_tr_de' => 'Çeviri TR→DE', 'translation_de_tr' => 'Çeviri DE→TR',
    'fill_blank' => 'Boşluk doldurma', 'word_order' => 'Kelime sırası',
    'article' => 'Artikel', 'plural' => 'Çoğul', 'conjugation' => 'Fiil çekimi',
    'case_choice' => 'Hal seçimi', 'sentence_correction' => 'Cümle düzeltme',
    'matching' => 'Eşleştirme', 'true_false' => 'Doğru/Yanlış',
    'scenario_response' => 'Senaryo cevabı', 'listening' => 'Dinleme',
];
$modes = [
    'recognition' => 'Tanıma', 'recall' => 'Aktif hatırlama', 'production' => 'Üretim',
    'spelling' => 'Yazım', 'delayed' => 'Gecikmeli hatırlama', 'application' => 'Uygulama',
];

if (is_post()) {
    csrf_require();
    $action = (string)input('action', '');

    if ($action === 'create' || $action === 'update') {
        $prompt = trim((string)input('prompt', ''));
        $answer = trim((string)input('correct_answer', ''));
        $type = (string)input('exercise_type', 'multiple_choice');
        $mode = (string)input('mode', 'recognition');
        $optionsRaw = trim((string)($_POST['options'] ?? ''));
        $altRaw = trim((string)($_POST['accepted_answers'] ?? ''));

        if (mb_strlen($prompt) < 3) {
            $errors['prompt'] = 'Soru metni en az 3 karakter olmalı.';
        }
        if ($answer === '') {
            $errors['correct_answer'] = 'Doğru cevap gerekli.';
        }
        if (!isset($types[$type])) {
            $errors['exercise_type'] = 'Geçersiz alıştırma tipi.';
        }
        if (!isset($modes[$mode])) {
            $errors['mode'] = 'Geçersiz mod.';
        }

        $optionList = [];
        if ($optionsRaw !== '') {
            foreach (preg_split('/\r?\n/', $optionsRaw) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $optionList[] = mb_substr($line, 0, 400);
                }
            }
        }
        if ($optionList !== [] && $errors === []) {
            $hasCorrect = false;
            foreach ($optionList as $opt) {
                if (mb_strtolower(trim($opt)) === mb_strtolower(trim($answer))) {
                    $hasCorrect = true;
                    break;
                }
            }
            if (!$hasCorrect) {
                $errors['options'] = 'Seçenekler arasında doğru cevap yer almalı.';
            }
        }

        $accepted = [];
        if ($altRaw !== '') {
            foreach (preg_split('/\r?\n/', $altRaw) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $accepted[] = mb_substr($line, 0, 400);
                }
            }
        }

        if ($errors === []) {
            $data = [
                input_int('lesson_id', 0) > 0 ? input_int('lesson_id', 0) : null,
                input_int('skill_id', 0) > 0 ? input_int('skill_id', 0) : null,
                input_int('vocabulary_id', 0) > 0 ? input_int('vocabulary_id', 0) : null,
                $type, $mode, $prompt,
                trim((string)input('context', '')) ?: null,
                $answer,
                $accepted === [] ? null : json_encode($accepted, JSON_UNESCAPED_UNICODE),
                trim((string)input('explanation', '')) ?: null,
                trim((string)input('memory_hint', '')) ?: null,
                in_array((string)input('cefr_level', ''), cefr_levels(), true) ? (string)input('cefr_level') : 'A1',
                trim((string)input('variant_group', '')) ?: null,
            ];

            if ($action === 'create') {
                $id = db_insert(
                    'INSERT INTO exercises (lesson_id, skill_id, vocabulary_id, exercise_type, mode, prompt, context,
                        correct_answer, accepted_answers, explanation, memory_hint, cefr_level, variant_group, is_generated, is_active)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0,1)',
                    $data
                );
            } else {
                $id = input_int('exercise_id', 0);
                $data[] = !empty($_POST['is_active']) ? 1 : 0;
                $data[] = $id;
                db_exec(
                    'UPDATE exercises SET lesson_id = ?, skill_id = ?, vocabulary_id = ?, exercise_type = ?, mode = ?,
                        prompt = ?, context = ?, correct_answer = ?, accepted_answers = ?, explanation = ?, memory_hint = ?,
                        cefr_level = ?, variant_group = ?, is_active = ? WHERE id = ?',
                    $data
                );
                db_exec('DELETE FROM exercise_options WHERE exercise_id = ?', [$id]);
            }

            $order = 0;
            foreach ($optionList as $opt) {
                db_exec('INSERT INTO exercise_options (exercise_id, option_text, is_correct, sort_order) VALUES (?, ?, ?, ?)',
                    [$id, $opt, mb_strtolower(trim($opt)) === mb_strtolower(trim($answer)) ? 1 : 0, $order++]);
            }

            $skillId = input_int('skill_id', 0);
            db_exec('DELETE FROM exercise_skill_map WHERE exercise_id = ?', [$id]);
            if ($skillId > 0) {
                db_exec('INSERT IGNORE INTO exercise_skill_map (exercise_id, skill_id, weight) VALUES (?, ?, 1)', [$id, $skillId]);
            }

            admin_log((int)$admin['id'], $action === 'create' ? 'EXERCISE_CREATED' : 'EXERCISE_UPDATED', 'exercise', (string)$id);
            flash('success', $action === 'create' ? 'Alıştırma eklendi.' : 'Alıştırma güncellendi.');
            redirect('/admin-exercises.php?edit=' . $id);
        }
    } elseif ($action === 'delete') {
        $id = input_int('exercise_id', 0);
        db_exec('DELETE FROM exercises WHERE id = ?', [$id]);
        admin_log((int)$admin['id'], 'EXERCISE_DELETED', 'exercise', (string)$id);
        flash('success', 'Alıştırma silindi.');
        redirect('/admin-exercises.php');
    } elseif ($action === 'toggle') {
        $id = input_int('exercise_id', 0);
        db_exec('UPDATE exercises SET is_active = 1 - is_active WHERE id = ?', [$id]);
        flash('success', 'Alıştırma durumu değiştirildi.');
        redirect('/admin-exercises.php?' . http_build_query(array_filter(['q' => input('q'), 'lesson' => input_int('lesson', 0), 'type' => input('type'), 'page' => input_int('page', 1)])));
    }
}

$editId = input_int('edit', 0);
$edit = $editId > 0 ? db_row('SELECT * FROM exercises WHERE id = ?', [$editId]) : null;
$editOptions = $edit !== null ? db_all('SELECT * FROM exercise_options WHERE exercise_id = ? ORDER BY sort_order', [$editId]) : [];

$q = trim((string)input('q', ''));
$lessonFilter = input_int('lesson', 0);
$typeFilter = (string)input('type', '');
$page = max(1, input_int('page', 1));
$perPage = 30;

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(e.prompt LIKE ? OR e.correct_answer LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($lessonFilter > 0) {
    $where[] = 'e.lesson_id = ?';
    $params[] = $lessonFilter;
}
if (isset($types[$typeFilter])) {
    $where[] = 'e.exercise_type = ?';
    $params[] = $typeFilter;
}
$whereSql = implode(' AND ', $where);

$total = (int)db_value('SELECT COUNT(*) FROM exercises e WHERE ' . $whereSql, $params, 0);
$totalPages = (int)max(1, ceil($total / $perPage));
$page = min($page, $totalPages);
$listParams = $params;
$listParams[] = $perPage;
$listParams[] = ($page - 1) * $perPage;

$rows = db_all(
    'SELECT e.*, l.title AS lesson_title, s.name AS skill_name,
            (SELECT COUNT(*) FROM exercise_attempts a WHERE a.exercise_id = e.id) attempts,
            (SELECT COALESCE(AVG(a.is_correct) * 100, 0) FROM exercise_attempts a WHERE a.exercise_id = e.id) accuracy
     FROM exercises e
     LEFT JOIN lessons l ON l.id = e.lesson_id
     LEFT JOIN skills s ON s.id = e.skill_id
     WHERE ' . $whereSql . '
     ORDER BY e.id DESC LIMIT ? OFFSET ?',
    $listParams
);

$lessons = db_all('SELECT id, title, cefr_level FROM lessons ORDER BY FIELD(cefr_level,"A0","A1","A2","B1"), sort_order');
$skills = db_all('SELECT id, name, cefr_level FROM skills WHERE is_active = 1 ORDER BY sort_order');
$prefillLesson = input_int('lesson', 0);

render_admin_start($admin, 'Alıştırmalar');
?>
<h1>Alıştırmalar</h1>
<p style="color: var(--admin-ink-2);">Toplam <span class="mono"><?= $total ?></span> alıştırma.
  Doğruluk oranı düşük sorular gözden geçirilmelidir.</p>

<form method="get" action="/admin-exercises.php" class="filter-bar" style="margin: 18px 0;">
  <input name="q" type="search" value="<?= e($q) ?>" placeholder="Soru ara" style="max-width: 240px;" aria-label="Ara">
  <select name="lesson" style="max-width: 220px;" aria-label="Ders">
    <option value="0">Tüm dersler</option>
    <?php foreach ($lessons as $l): ?>
      <option value="<?= (int)$l['id'] ?>"<?= $lessonFilter === (int)$l['id'] ? ' selected' : '' ?>><?= e((string)$l['cefr_level'] . ' · ' . str_limit((string)$l['title'], 40)) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="type" style="max-width: 180px;" aria-label="Tip">
    <option value="">Tüm tipler</option>
    <?php foreach ($types as $k => $label): ?><option value="<?= e($k) ?>"<?= $typeFilter === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn--sm btn--inline" type="submit">FİLTRELE</button>
  <a class="btn btn--sm btn--secondary btn--inline" href="#yeni">YENİ ALIŞTIRMA</a>
</form>

<div class="table-wrap">
  <table class="data">
    <thead><tr><th>ID</th><th>Soru</th><th>Tip</th><th>Mod</th><th>Ders / Skill</th><th>Deneme</th><th>Doğruluk</th><th>Durum</th><th>İşlem</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $ex): ?>
        <tr>
          <td data-label="ID" class="mono"><?= (int)$ex['id'] ?></td>
          <td data-label="Soru" class="small"><a href="/admin-exercises.php?edit=<?= (int)$ex['id'] ?>#yeni"><?= e(str_limit((string)$ex['prompt'], 60)) ?></a></td>
          <td data-label="Tip" class="small"><?= e($types[(string)$ex['exercise_type']] ?? (string)$ex['exercise_type']) ?></td>
          <td data-label="Mod" class="small"><?= e($modes[(string)$ex['mode']] ?? (string)$ex['mode']) ?></td>
          <td data-label="Ders / Skill" class="small"><?= e(str_limit((string)($ex['lesson_title'] ?? $ex['skill_name'] ?? '—'), 30)) ?></td>
          <td data-label="Deneme" class="mono"><?= (int)$ex['attempts'] ?></td>
          <td data-label="Doğruluk" class="mono"><?= (int)$ex['attempts'] > 0 ? (int)round((float)$ex['accuracy']) . '%' : '—' ?></td>
          <td data-label="Durum"><?php render_badge((int)$ex['is_active'] === 1 ? '✓' : '○', (int)$ex['is_active'] === 1 ? 'AKTİF' : 'PASİF', (int)$ex['is_active'] === 1 ? 'ok' : 'muted'); ?></td>
          <td data-label="İşlem">
            <form method="post" action="/admin-exercises.php" style="display: inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="exercise_id" value="<?= (int)$ex['id'] ?>">
              <input type="hidden" name="q" value="<?= e($q) ?>">
              <input type="hidden" name="lesson" value="<?= $lessonFilter ?>">
              <input type="hidden" name="type" value="<?= e($typeFilter) ?>">
              <input type="hidden" name="page" value="<?= $page ?>">
              <button class="btn btn--sm btn--secondary btn--inline" type="submit" name="action" value="toggle">
                <?= (int)$ex['is_active'] === 1 ? 'PASİF' : 'AKTİF' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?><tr><td colspan="9">Alıştırma bulunamadı.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php render_pagination($page, $totalPages, '/admin-exercises.php?q=' . urlencode($q) . '&lesson=' . $lessonFilter . '&type=' . urlencode($typeFilter)); ?>

<section class="admin-card" id="yeni" style="margin-top: 26px;">
  <h2 style="font-size: 18px;"><?= $edit !== null ? 'Alıştırmayı düzenle #' . (int)$edit['id'] : 'Yeni alıştırma' ?></h2>
  <form method="post" action="/admin-exercises.php" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $edit !== null ? 'update' : 'create' ?>">
    <?php if ($edit !== null): ?><input type="hidden" name="exercise_id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

    <div class="grid grid-3" style="gap: 12px;">
      <div class="field">
        <label for="exercise_type">Tip</label>
        <select id="exercise_type" name="exercise_type">
          <?php foreach ($types as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= (string)($edit['exercise_type'] ?? '') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="mode">Mod</label>
        <select id="mode" name="mode">
          <?php foreach ($modes as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= (string)($edit['mode'] ?? '') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint">Mod, mastery kanıt türünü belirler.</div>
      </div>
      <div class="field">
        <label for="cefr_level_e">Seviye</label>
        <select id="cefr_level_e" name="cefr_level">
          <?php foreach (cefr_levels() as $lv): ?>
            <option value="<?= e($lv) ?>"<?= (string)($edit['cefr_level'] ?? 'A1') === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="lesson_id">Ders</label>
        <select id="lesson_id" name="lesson_id">
          <option value="0">— yok —</option>
          <?php foreach ($lessons as $l): ?>
            <option value="<?= (int)$l['id'] ?>"<?= (int)($edit['lesson_id'] ?? $prefillLesson) === (int)$l['id'] ? ' selected' : '' ?>>
              <?= e((string)$l['cefr_level'] . ' · ' . str_limit((string)$l['title'], 40)) ?></option>
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
        <label for="variant_group">Varyant grubu</label>
        <input id="variant_group" name="variant_group" type="text" value="<?= e((string)($edit['variant_group'] ?? '')) ?>">
        <div class="hint">Aynı gruptaki sorular farklı bağlam sayılır (ezber engelleme).</div>
      </div>
    </div>

    <div class="field">
      <label for="prompt">Soru metni</label>
      <textarea id="prompt" name="prompt" rows="2" required><?= e((string)($edit['prompt'] ?? '')) ?></textarea>
      <?php if (isset($errors['prompt'])): ?><div class="hint text-danger">✕ <?= e($errors['prompt']) ?></div><?php endif; ?>
    </div>
    <div class="field">
      <label for="context">Bağlam / metin (isteğe bağlı)</label>
      <textarea id="context" name="context" rows="2"><?= e((string)($edit['context'] ?? '')) ?></textarea>
    </div>
    <div class="field">
      <label for="correct_answer">Doğru cevap</label>
      <input id="correct_answer" name="correct_answer" type="text" value="<?= e((string)($edit['correct_answer'] ?? '')) ?>" required>
      <?php if (isset($errors['correct_answer'])): ?><div class="hint text-danger">✕ <?= e($errors['correct_answer']) ?></div><?php endif; ?>
    </div>
    <div class="field">
      <label for="options">Seçenekler (her satıra bir seçenek; boş bırakılırsa metin girişi olur)</label>
      <textarea id="options" name="options" rows="4"><?= e(implode("\n", array_column($editOptions, 'option_text'))) ?></textarea>
      <?php if (isset($errors['options'])): ?><div class="hint text-danger">✕ <?= e($errors['options']) ?></div><?php endif; ?>
    </div>
    <div class="field">
      <label for="accepted_answers">Kabul edilen alternatif cevaplar (her satıra bir tane)</label>
      <textarea id="accepted_answers" name="accepted_answers" rows="3"><?php
        $alts = [];
        if ($edit !== null && !empty($edit['accepted_answers'])) {
            $decoded = json_decode((string)$edit['accepted_answers'], true);
            if (is_array($decoded)) { $alts = $decoded; }
        }
        echo e(implode("\n", $alts));
      ?></textarea>
    </div>
    <div class="field">
      <label for="explanation">Açıklama (Neden?)</label>
      <textarea id="explanation" name="explanation" rows="2"><?= e((string)($edit['explanation'] ?? '')) ?></textarea>
    </div>
    <div class="field">
      <label for="memory_hint">Hatırlama ipucu</label>
      <input id="memory_hint" name="memory_hint" type="text" value="<?= e((string)($edit['memory_hint'] ?? '')) ?>">
    </div>
    <div class="checkline">
      <input id="is_active" name="is_active" type="checkbox" value="1"<?= (int)($edit['is_active'] ?? 1) === 1 ? ' checked' : '' ?>>
      <label for="is_active">Aktif</label>
    </div>

    <div class="row" style="margin-top: 12px;">
      <button class="btn btn--inline" type="submit"><?= $edit !== null ? 'GÜNCELLE' : 'ALIŞTIRMAYI EKLE' ?></button>
      <?php if ($edit !== null): ?>
        <a class="btn btn--secondary btn--inline" href="/admin-exercises.php#yeni">YENİ</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($edit !== null): ?>
    <form method="post" action="/admin-exercises.php" style="margin-top: 14px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="exercise_id" value="<?= (int)$edit['id'] ?>">
      <button class="btn btn--sm btn--danger btn--inline" type="submit" data-confirm="Alıştırma silinsin mi?">ALIŞTIRMAYI SİL</button>
    </form>
  <?php endif; ?>
</section>
<?php render_admin_end(); ?>
