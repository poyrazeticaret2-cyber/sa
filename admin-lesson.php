<?php
/**
 * AlmancaPro - Ders duzenleyici (bolumler, skill, onkosul, kelime, alistirma).
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
app_boot();

$admin = require_admin();
$lessonId = input_int('id', 0);
$lesson = $lessonId > 0 ? db_row('SELECT * FROM lessons WHERE id = ?', [$lessonId]) : null;
if ($lesson === null) {
    flash('error', 'Ders bulunamadı.');
    redirect('/admin-lessons.php');
}

if (is_post()) {
    csrf_require();
    $action = (string)input('action', '');

    switch ($action) {
        case 'meta':
            $title = trim((string)input('title', ''));
            if (mb_strlen($title) >= 3) {
                db_exec(
                    'UPDATE lessons SET title = ?, module_id = ?, cefr_level = ?, lesson_type = ?, objective = ?, summary = ?,
                        estimated_minutes = ?, completion_threshold = ?, sort_order = ?, is_active = ? WHERE id = ?',
                    [
                        $title,
                        input_int('module_id', 0) > 0 ? input_int('module_id', 0) : null,
                        in_array((string)input('cefr_level', ''), cefr_levels(), true) ? (string)input('cefr_level') : (string)$lesson['cefr_level'],
                        (string)input('lesson_type', (string)$lesson['lesson_type']),
                        trim((string)input('objective', '')) ?: null,
                        trim((string)input('summary', '')) ?: null,
                        max(3, min(120, input_int('estimated_minutes', 12))),
                        max(50, min(100, input_int('completion_threshold', 80))),
                        max(0, input_int('sort_order', 0)),
                        !empty($_POST['is_active']) ? 1 : 0,
                        $lessonId,
                    ]
                );
                admin_log((int)$admin['id'], 'LESSON_UPDATED', 'lesson', (string)$lessonId, ['title' => $title]);
                flash('success', 'Ders bilgileri güncellendi.');
            } else {
                flash('error', 'Başlık en az 3 karakter olmalı.');
            }
            break;

        case 'section_add':
            $body = trim((string)input('body', ''));
            $type = (string)input('section_type', 'explanation');
            if ($body !== '' || trim((string)input('example_de', '')) !== '') {
                $sort = (int)db_value('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM lesson_sections WHERE lesson_id = ?', [$lessonId], 0);
                db_exec(
                    'INSERT INTO lesson_sections (lesson_id, section_type, heading, body, example_de, example_tr, highlight, is_collapsible, sort_order)
                     VALUES (?,?,?,?,?,?,?,?,?)',
                    [
                        $lessonId, $type,
                        trim((string)input('heading', '')) ?: null,
                        $body ?: null,
                        trim((string)input('example_de', '')) ?: null,
                        trim((string)input('example_tr', '')) ?: null,
                        trim((string)input('highlight', '')) ?: null,
                        !empty($_POST['is_collapsible']) ? 1 : 0,
                        $sort,
                    ]
                );
                admin_log((int)$admin['id'], 'LESSON_SECTION_ADDED', 'lesson', (string)$lessonId);
                flash('success', 'Bölüm eklendi.');
            } else {
                flash('error', 'Bölüm içeriği boş olamaz.');
            }
            break;

        case 'section_update':
            $sectionId = input_int('section_id', 0);
            db_exec(
                'UPDATE lesson_sections SET section_type = ?, heading = ?, body = ?, example_de = ?, example_tr = ?,
                    highlight = ?, is_collapsible = ?, sort_order = ? WHERE id = ? AND lesson_id = ?',
                [
                    (string)input('section_type', 'explanation'),
                    trim((string)input('heading', '')) ?: null,
                    trim((string)input('body', '')) ?: null,
                    trim((string)input('example_de', '')) ?: null,
                    trim((string)input('example_tr', '')) ?: null,
                    trim((string)input('highlight', '')) ?: null,
                    !empty($_POST['is_collapsible']) ? 1 : 0,
                    max(0, input_int('sort_order', 0)),
                    $sectionId, $lessonId,
                ]
            );
            admin_log((int)$admin['id'], 'LESSON_SECTION_UPDATED', 'lesson', (string)$lessonId, ['section' => $sectionId]);
            flash('success', 'Bölüm güncellendi.');
            break;

        case 'section_delete':
            db_exec('DELETE FROM lesson_sections WHERE id = ? AND lesson_id = ?', [input_int('section_id', 0), $lessonId]);
            admin_log((int)$admin['id'], 'LESSON_SECTION_DELETED', 'lesson', (string)$lessonId);
            flash('success', 'Bölüm silindi.');
            break;

        case 'skill_add':
            $skillId = input_int('skill_id', 0);
            if ($skillId > 0) {
                db_exec('INSERT IGNORE INTO lesson_skills (lesson_id, skill_id, is_primary) VALUES (?, ?, ?)',
                    [$lessonId, $skillId, !empty($_POST['is_primary']) ? 1 : 0]);
                flash('success', 'Skill bağlandı.');
            }
            break;

        case 'skill_remove':
            db_exec('DELETE FROM lesson_skills WHERE lesson_id = ? AND skill_id = ?', [$lessonId, input_int('skill_id', 0)]);
            flash('success', 'Skill bağlantısı kaldırıldı.');
            break;

        case 'prereq_add':
            $skillId = input_int('skill_id', 0);
            $threshold = max(10, min(100, input_int('required_mastery', 90)));
            if ($skillId > 0) {
                db_exec('INSERT INTO lesson_prerequisites (lesson_id, skill_id, required_mastery) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE required_mastery = VALUES(required_mastery)',
                    [$lessonId, $skillId, $threshold]);
                admin_log((int)$admin['id'], 'LESSON_PREREQ_CHANGED', 'lesson', (string)$lessonId, ['skill' => $skillId, 'threshold' => $threshold]);
                flash('success', 'Önkoşul kaydedildi.');
            }
            break;

        case 'prereq_remove':
            db_exec('DELETE FROM lesson_prerequisites WHERE lesson_id = ? AND skill_id = ?', [$lessonId, input_int('skill_id', 0)]);
            admin_log((int)$admin['id'], 'LESSON_PREREQ_CHANGED', 'lesson', (string)$lessonId, ['removed' => input_int('skill_id', 0)]);
            flash('success', 'Önkoşul kaldırıldı.');
            break;

        case 'vocab_add':
            $vocabId = input_int('vocabulary_id', 0);
            if ($vocabId > 0) {
                $sort = (int)db_value('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM lesson_vocabulary WHERE lesson_id = ?', [$lessonId], 0);
                db_exec('INSERT IGNORE INTO lesson_vocabulary (lesson_id, vocabulary_id, sort_order) VALUES (?, ?, ?)', [$lessonId, $vocabId, $sort]);
                flash('success', 'Kelime derse eklendi.');
            }
            break;

        case 'vocab_remove':
            db_exec('DELETE FROM lesson_vocabulary WHERE lesson_id = ? AND vocabulary_id = ?', [$lessonId, input_int('vocabulary_id', 0)]);
            flash('success', 'Kelime dersten çıkarıldı.');
            break;
    }
    redirect('/admin-lesson.php?id=' . $lessonId);
}

$sections = db_all('SELECT * FROM lesson_sections WHERE lesson_id = ? ORDER BY sort_order, id', [$lessonId]);
$modules = db_all('SELECT id, title, cefr_level FROM modules ORDER BY FIELD(cefr_level,"A0","A1","A2","B1"), sort_order');
$skills = db_all('SELECT id, code, name, cefr_level FROM skills WHERE is_active = 1 ORDER BY sort_order');
$lessonSkills = db_all('SELECT ls.*, s.name, s.code FROM lesson_skills ls JOIN skills s ON s.id = ls.skill_id WHERE ls.lesson_id = ?', [$lessonId]);
$prereqs = db_all('SELECT lp.*, s.name, s.code FROM lesson_prerequisites lp JOIN skills s ON s.id = lp.skill_id WHERE lp.lesson_id = ?', [$lessonId]);
$vocab = db_all('SELECT lv.*, v.german, v.article, v.turkish FROM lesson_vocabulary lv JOIN vocabulary v ON v.id = lv.vocabulary_id WHERE lv.lesson_id = ? ORDER BY lv.sort_order', [$lessonId]);
$exercises = db_all('SELECT * FROM exercises WHERE lesson_id = ? ORDER BY sort_order, id LIMIT 60', [$lessonId]);
$vocabSearch = trim((string)input('vs', ''));
$vocabOptions = $vocabSearch !== ''
    ? db_all('SELECT id, german, article, turkish FROM vocabulary WHERE german LIKE ? OR turkish LIKE ? ORDER BY german LIMIT 30', ['%' . $vocabSearch . '%', '%' . $vocabSearch . '%'])
    : [];

$sectionTypes = [
    'explanation' => 'Açıklama', 'why' => 'Neden?', 'tr_contrast' => 'Türkçeden farkı',
    'rule' => 'Kural', 'table' => 'Tablo', 'example' => 'Örnek', 'dialogue' => 'Diyalog',
    'exception' => 'İstisna', 'common_mistake' => 'Sık hata', 'memory_tip' => 'Kolay hatırlama',
    'usage' => 'Kullanım', 'pronunciation' => 'Telaffuz',
];

render_admin_start($admin, 'Ders: ' . (string)$lesson['title']);
?>
<a class="small" href="/admin-lessons.php">← Dersler</a>
<h1 style="margin-top: 12px;"><?= e((string)$lesson['title']) ?></h1>
<p style="color: var(--admin-ink-2);">
  <span class="mono">#<?= $lessonId ?></span> · <span class="mono"><?= e((string)$lesson['slug']) ?></span> ·
  <a href="/lesson.php?id=<?= $lessonId ?>" target="_blank" rel="noopener">Sitede gör</a>
</p>

<section class="admin-card" style="margin-top: 18px;">
  <h2 style="font-size: 18px;">Ders bilgileri</h2>
  <form method="post" action="/admin-lesson.php?id=<?= $lessonId ?>" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="meta">
    <div class="grid grid-3" style="gap: 12px;">
      <div class="field">
        <label for="title">Başlık</label>
        <input id="title" name="title" type="text" value="<?= e((string)$lesson['title']) ?>" required>
      </div>
      <div class="field">
        <label for="module_id">Modül</label>
        <select id="module_id" name="module_id">
          <option value="0">— modülsüz —</option>
          <?php foreach ($modules as $m): ?>
            <option value="<?= (int)$m['id'] ?>"<?= (int)($lesson['module_id'] ?? 0) === (int)$m['id'] ? ' selected' : '' ?>>
              <?= e((string)$m['cefr_level'] . ' · ' . (string)$m['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="cefr_level">Seviye</label>
        <select id="cefr_level" name="cefr_level">
          <?php foreach (cefr_levels() as $lv): ?>
            <option value="<?= e($lv) ?>"<?= (string)$lesson['cefr_level'] === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="lesson_type">Tür</label>
        <select id="lesson_type" name="lesson_type">
          <?php foreach (['grammar' => 'Dilbilgisi', 'vocabulary' => 'Kelime', 'pronunciation' => 'Telaffuz', 'workplace' => 'İş Almancası', 'daily_life' => 'Günlük hayat', 'scenario' => 'Senaryo', 'listening' => 'Dinleme', 'review' => 'Tekrar'] as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= (string)$lesson['lesson_type'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="estimated_minutes">Süre (dk)</label>
        <input id="estimated_minutes" name="estimated_minutes" type="number" min="3" max="120" value="<?= (int)$lesson['estimated_minutes'] ?>">
      </div>
      <div class="field">
        <label for="completion_threshold">Tamamlama eşiği (%)</label>
        <input id="completion_threshold" name="completion_threshold" type="number" min="50" max="100" value="<?= (int)$lesson['completion_threshold'] ?>">
      </div>
      <div class="field">
        <label for="sort_order">Sıra</label>
        <input id="sort_order" name="sort_order" type="number" min="0" value="<?= (int)$lesson['sort_order'] ?>">
      </div>
    </div>
    <div class="field">
      <label for="objective">Hedef</label>
      <input id="objective" name="objective" type="text" value="<?= e((string)($lesson['objective'] ?? '')) ?>">
    </div>
    <div class="field">
      <label for="summary">Özet</label>
      <input id="summary" name="summary" type="text" value="<?= e((string)($lesson['summary'] ?? '')) ?>">
    </div>
    <div class="checkline">
      <input id="is_active" name="is_active" type="checkbox" value="1"<?= (int)$lesson['is_active'] === 1 ? ' checked' : '' ?>>
      <label for="is_active">Ders aktif (kullanıcılara görünür)</label>
    </div>
    <button class="btn btn--inline" type="submit">KAYDET</button>
  </form>
</section>

<section class="admin-card" style="margin-top: 20px;">
  <h2 style="font-size: 18px;">Bölümler (<?= count($sections) ?>)</h2>
  <?php foreach ($sections as $sec): ?>
    <details class="acc" style="background: var(--admin-surface-2); margin-bottom: 8px;">
      <summary><?= e($sectionTypes[(string)$sec['section_type']] ?? (string)$sec['section_type']) ?>
        — <?= e(str_limit((string)($sec['heading'] ?? (string)$sec['body']), 60)) ?></summary>
      <div class="acc__body">
        <form method="post" action="/admin-lesson.php?id=<?= $lessonId ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="section_update">
          <input type="hidden" name="section_id" value="<?= (int)$sec['id'] ?>">
          <div class="grid grid-3" style="gap: 12px;">
            <div class="field">
              <label for="st<?= (int)$sec['id'] ?>">Tür</label>
              <select id="st<?= (int)$sec['id'] ?>" name="section_type">
                <?php foreach ($sectionTypes as $k => $label): ?>
                  <option value="<?= e($k) ?>"<?= (string)$sec['section_type'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="sh<?= (int)$sec['id'] ?>">Başlık</label>
              <input id="sh<?= (int)$sec['id'] ?>" name="heading" type="text" value="<?= e((string)($sec['heading'] ?? '')) ?>">
            </div>
            <div class="field">
              <label for="so<?= (int)$sec['id'] ?>">Sıra</label>
              <input id="so<?= (int)$sec['id'] ?>" name="sort_order" type="number" min="0" value="<?= (int)$sec['sort_order'] ?>">
            </div>
          </div>
          <div class="field">
            <label for="sb<?= (int)$sec['id'] ?>">İçerik</label>
            <textarea id="sb<?= (int)$sec['id'] ?>" name="body" rows="6"><?= e((string)($sec['body'] ?? '')) ?></textarea>
          </div>
          <div class="grid grid-2" style="gap: 12px;">
            <div class="field">
              <label for="sde<?= (int)$sec['id'] ?>">Örnek (Almanca)</label>
              <textarea id="sde<?= (int)$sec['id'] ?>" name="example_de" rows="2"><?= e((string)($sec['example_de'] ?? '')) ?></textarea>
            </div>
            <div class="field">
              <label for="str<?= (int)$sec['id'] ?>">Örnek (Türkçe)</label>
              <textarea id="str<?= (int)$sec['id'] ?>" name="example_tr" rows="2"><?= e((string)($sec['example_tr'] ?? '')) ?></textarea>
            </div>
          </div>
          <div class="field">
            <label for="shl<?= (int)$sec['id'] ?>">Vurgulanacak ifade</label>
            <input id="shl<?= (int)$sec['id'] ?>" name="highlight" type="text" value="<?= e((string)($sec['highlight'] ?? '')) ?>">
          </div>
          <div class="checkline">
            <input id="sc<?= (int)$sec['id'] ?>" name="is_collapsible" type="checkbox" value="1"<?= (int)$sec['is_collapsible'] === 1 ? ' checked' : '' ?>>
            <label for="sc<?= (int)$sec['id'] ?>">Katlanabilir (accordion) olarak göster</label>
          </div>
          <div class="row">
            <button class="btn btn--sm btn--inline" type="submit">GÜNCELLE</button>
            <button class="btn btn--sm btn--danger btn--inline" type="submit" name="action" value="section_delete"
                    data-confirm="Bölüm silinsin mi?">SİL</button>
          </div>
        </form>
      </div>
    </details>
  <?php endforeach; ?>
  <?php if ($sections === []): ?>
    <p class="small" style="color: var(--admin-ink-2);">Bu derste henüz bölüm yok.</p>
  <?php endif; ?>

  <details class="acc" style="background: var(--admin-surface-2); margin-top: 12px;">
    <summary>+ Yeni bölüm ekle</summary>
    <div class="acc__body">
      <form method="post" action="/admin-lesson.php?id=<?= $lessonId ?>" data-guard>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="section_add">
        <div class="grid grid-2" style="gap: 12px;">
          <div class="field">
            <label for="new_type">Tür</label>
            <select id="new_type" name="section_type">
              <?php foreach ($sectionTypes as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="new_heading">Başlık</label>
            <input id="new_heading" name="heading" type="text">
          </div>
        </div>
        <div class="field">
          <label for="new_body">İçerik</label>
          <textarea id="new_body" name="body" rows="5"></textarea>
        </div>
        <div class="grid grid-2" style="gap: 12px;">
          <div class="field">
            <label for="new_de">Örnek (Almanca)</label>
            <textarea id="new_de" name="example_de" rows="2"></textarea>
          </div>
          <div class="field">
            <label for="new_tr">Örnek (Türkçe)</label>
            <textarea id="new_tr" name="example_tr" rows="2"></textarea>
          </div>
        </div>
        <div class="checkline">
          <input id="new_coll" name="is_collapsible" type="checkbox" value="1">
          <label for="new_coll">Katlanabilir olarak göster</label>
        </div>
        <button class="btn btn--sm btn--inline" type="submit">BÖLÜM EKLE</button>
      </form>
    </div>
  </details>
</section>

<div class="grid grid-2" style="margin-top: 20px;">
  <section class="admin-card">
    <h2 style="font-size: 18px;">Skill bağlantıları</h2>
    <?php foreach ($lessonSkills as $ls): ?>
      <div class="health-row">
        <span class="health-row__name"><?= e((string)$ls['name']) ?>
          <?php if ((int)$ls['is_primary'] === 1): ?><span class="badge badge--active">BİRİNCİL</span><?php endif; ?></span>
        <form method="post" action="/admin-lesson.php?id=<?= $lessonId ?>" style="margin: 0;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="skill_remove">
          <input type="hidden" name="skill_id" value="<?= (int)$ls['skill_id'] ?>">
          <button class="btn btn--sm btn--danger btn--inline" type="submit">KALDIR</button>
        </form>
      </div>
    <?php endforeach; ?>
    <?php if ($lessonSkills === []): ?><p class="small" style="color: var(--admin-ink-2);">Bağlı skill yok.</p><?php endif; ?>

    <form method="post" action="/admin-lesson.php?id=<?= $lessonId ?>" style="margin-top: 14px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="skill_add">
      <div class="field">
        <label for="skill_add">Skill ekle</label>
        <select id="skill_add" name="skill_id">
          <?php foreach ($skills as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= e((string)$s['cefr_level'] . ' · ' . (string)$s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="checkline">
        <input id="is_primary" name="is_primary" type="checkbox" value="1" checked>
        <label for="is_primary">Birincil skill (ders tamamlama bu skill'e bakar)</label>
      </div>
      <button class="btn btn--sm btn--inline" type="submit">EKLE</button>
    </form>
  </section>

  <section class="admin-card">
    <h2 style="font-size: 18px;">Önkoşullar</h2>
    <p class="small" style="color: var(--admin-ink-2);">Kullanıcı bu eşiklere ulaşmadan ders açılmaz. Kilit sunucu tarafında uygulanır.</p>
    <?php foreach ($prereqs as $p): ?>
      <div class="health-row">
        <span class="health-row__name"><?= e((string)$p['name']) ?></span>
        <span class="health-row__detail mono">≥ <?= (int)$p['required_mastery'] ?>%</span>
        <form method="post" action="/admin-lesson.php?id=<?= $lessonId ?>" style="margin: 0;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="prereq_remove">
          <input type="hidden" name="skill_id" value="<?= (int)$p['skill_id'] ?>">
          <button class="btn btn--sm btn--danger btn--inline" type="submit">KALDIR</button>
        </form>
      </div>
    <?php endforeach; ?>
    <?php if ($prereqs === []): ?><p class="small" style="color: var(--admin-ink-2);">Önkoşul yok — ders herkese açık.</p><?php endif; ?>

    <form method="post" action="/admin-lesson.php?id=<?= $lessonId ?>" style="margin-top: 14px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="prereq_add">
      <div class="grid grid-2" style="gap: 12px;">
        <div class="field">
          <label for="prereq_skill">Skill</label>
          <select id="prereq_skill" name="skill_id">
            <?php foreach ($skills as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= e((string)$s['cefr_level'] . ' · ' . (string)$s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="required_mastery">Eşik (%)</label>
          <input id="required_mastery" name="required_mastery" type="number" min="10" max="100" value="90">
        </div>
      </div>
      <button class="btn btn--sm btn--inline" type="submit">ÖNKOŞUL EKLE</button>
    </form>
  </section>
</div>

<section class="admin-card" style="margin-top: 20px;">
  <h2 style="font-size: 18px;">Ders kelimeleri (<?= count($vocab) ?>)</h2>
  <div class="table-wrap" style="border: 0;">
    <table class="data">
      <thead><tr><th>Kelime</th><th>Türkçe</th><th>İşlem</th></tr></thead>
      <tbody>
        <?php foreach ($vocab as $v): ?>
          <tr>
            <td data-label="Kelime"><?= e(trim((string)($v['article'] ?? '') . ' ' . (string)$v['german'])) ?></td>
            <td data-label="Türkçe"><?= e((string)$v['turkish']) ?></td>
            <td data-label="İşlem">
              <form method="post" action="/admin-lesson.php?id=<?= $lessonId ?>" style="margin: 0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="vocab_remove">
                <input type="hidden" name="vocabulary_id" value="<?= (int)$v['vocabulary_id'] ?>">
                <button class="btn btn--sm btn--danger btn--inline" type="submit">ÇIKAR</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($vocab === []): ?><tr><td colspan="3">Bu derse bağlı kelime yok.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <form method="get" action="/admin-lesson.php" class="filter-bar" style="margin-top: 14px;">
    <input type="hidden" name="id" value="<?= $lessonId ?>">
    <input name="vs" type="search" value="<?= e($vocabSearch) ?>" placeholder="Kelime ara" style="max-width: 240px;" aria-label="Kelime ara">
    <button class="btn btn--sm btn--secondary btn--inline" type="submit">ARA</button>
  </form>
  <?php if ($vocabOptions !== []): ?>
    <form method="post" action="/admin-lesson.php?id=<?= $lessonId ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="vocab_add">
      <div class="field">
        <label for="vocabulary_id">Eklenecek kelime</label>
        <select id="vocabulary_id" name="vocabulary_id">
          <?php foreach ($vocabOptions as $v): ?>
            <option value="<?= (int)$v['id'] ?>"><?= e(trim((string)($v['article'] ?? '') . ' ' . (string)$v['german']) . ' — ' . (string)$v['turkish']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn--sm btn--inline" type="submit">DERSE EKLE</button>
    </form>
  <?php endif; ?>
</section>

<section class="admin-card" style="margin-top: 20px;">
  <h2 style="font-size: 18px;">Alıştırmalar (<?= count($exercises) ?>)</h2>
  <div class="table-wrap" style="border: 0;">
    <table class="data">
      <thead><tr><th>ID</th><th>Tip</th><th>Mod</th><th>Soru</th><th>Doğru cevap</th><th>Düzenle</th></tr></thead>
      <tbody>
        <?php foreach ($exercises as $ex): ?>
          <tr>
            <td data-label="ID" class="mono"><?= (int)$ex['id'] ?></td>
            <td data-label="Tip" class="small"><?= e((string)$ex['exercise_type']) ?></td>
            <td data-label="Mod" class="small"><?= e((string)$ex['mode']) ?></td>
            <td data-label="Soru" class="small"><?= e(str_limit((string)$ex['prompt'], 70)) ?></td>
            <td data-label="Doğru cevap" class="small"><?= e(str_limit((string)$ex['correct_answer'], 40)) ?></td>
            <td data-label="Düzenle"><a class="btn btn--sm btn--secondary btn--inline" href="/admin-exercises.php?edit=<?= (int)$ex['id'] ?>">AÇ</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($exercises === []): ?><tr><td colspan="6">Bu derste alıştırma yok.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <a class="btn btn--sm btn--inline" style="margin-top: 12px;" href="/admin-exercises.php?lesson=<?= $lessonId ?>#yeni">BU DERSE ALIŞTIRMA EKLE</a>
</section>
<?php render_admin_end(); ?>
