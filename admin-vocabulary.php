<?php
/**
 * AlmancaPro - Kelime hazinesi yonetimi (tam CRUD).
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/seed.php';
app_boot();

$admin = require_admin();
$errors = [];

$fields = static function (array $src): array {
    $article = (string)($src['article'] ?? '');
    return [
        'german'               => trim((string)($src['german'] ?? '')),
        'article'              => in_array($article, ['der', 'die', 'das'], true) ? $article : null,
        'plural'               => trim((string)($src['plural'] ?? '')) ?: null,
        'turkish'              => trim((string)($src['turkish'] ?? '')),
        'part_of_speech'       => (string)($src['part_of_speech'] ?? 'noun'),
        'pronunciation'        => trim((string)($src['pronunciation'] ?? '')) ?: null,
        'ipa'                  => trim((string)($src['ipa'] ?? '')) ?: null,
        'cefr_level'           => in_array((string)($src['cefr_level'] ?? ''), cefr_levels(), true) ? (string)$src['cefr_level'] : 'A1',
        'topic'                => trim((string)($src['topic'] ?? '')) ?: null,
        'example_de'           => trim((string)($src['example_de'] ?? '')) ?: null,
        'example_tr'           => trim((string)($src['example_tr'] ?? '')) ?: null,
        'usage_notes'          => trim((string)($src['usage_notes'] ?? '')) ?: null,
        'memory_tip'           => trim((string)($src['memory_tip'] ?? '')) ?: null,
        'similar_word_note'    => trim((string)($src['similar_word_note'] ?? '')) ?: null,
        'separable_prefix'     => trim((string)($src['separable_prefix'] ?? '')) ?: null,
        'auxiliary'            => in_array((string)($src['auxiliary'] ?? ''), ['haben', 'sein'], true) ? (string)$src['auxiliary'] : null,
        'preterite'            => trim((string)($src['preterite'] ?? '')) ?: null,
        'participle_ii'        => trim((string)($src['participle_ii'] ?? '')) ?: null,
        'third_person'         => trim((string)($src['third_person'] ?? '')) ?: null,
        'is_irregular'         => !empty($src['is_irregular']) ? 1 : 0,
        'is_reflexive'         => !empty($src['is_reflexive']) ? 1 : 0,
        'requires_case'        => in_array((string)($src['requires_case'] ?? ''), ['nominativ', 'akkusativ', 'dativ', 'genitiv'], true) ? (string)$src['requires_case'] : null,
        'required_preposition' => trim((string)($src['required_preposition'] ?? '')) ?: null,
    ];
};

if (is_post()) {
    csrf_require();
    $action = (string)input('action', '');

    if ($action === 'create' || $action === 'update') {
        $f = $fields($_POST);
        if (mb_strlen($f['german']) < 1) {
            $errors['german'] = 'Almanca kelime gerekli.';
        }
        if (mb_strlen($f['turkish']) < 1) {
            $errors['turkish'] = 'Türkçe karşılık gerekli.';
        }
        if ($f['part_of_speech'] === 'noun' && $f['article'] === null) {
            $errors['article'] = 'İsimlerde artikel zorunludur; kelime artikeliyle öğretilir.';
        }
        $gender = $f['article'] === null ? null : ['der' => 'm', 'die' => 'f', 'das' => 'n'][$f['article']];

        if ($errors === []) {
            if ($action === 'create') {
                $exists = (int)db_value('SELECT COUNT(*) FROM vocabulary WHERE normalized_german = ? AND part_of_speech = ?',
                    [de_normalize($f['german']), $f['part_of_speech']], 0);
                if ($exists > 0) {
                    $errors['german'] = 'Bu kelime bu türde zaten kayıtlı.';
                } else {
                    $id = db_insert(
                        'INSERT INTO vocabulary (german, normalized_german, article, gender, plural, turkish, part_of_speech,
                            pronunciation, ipa, cefr_level, topic, example_de, example_tr, usage_notes, memory_tip, similar_word_note,
                            separable_prefix, auxiliary, preterite, participle_ii, third_person, is_irregular, is_reflexive,
                            requires_case, required_preposition)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                        array_merge([$f['german'], de_normalize($f['german']), $f['article'], $gender], [
                            $f['plural'], $f['turkish'], $f['part_of_speech'], $f['pronunciation'], $f['ipa'],
                            $f['cefr_level'], $f['topic'], $f['example_de'], $f['example_tr'], $f['usage_notes'],
                            $f['memory_tip'], $f['similar_word_note'], $f['separable_prefix'], $f['auxiliary'],
                            $f['preterite'], $f['participle_ii'], $f['third_person'], $f['is_irregular'],
                            $f['is_reflexive'], $f['requires_case'], $f['required_preposition'],
                        ])
                    );
                    $row = db_row('SELECT * FROM vocabulary WHERE id = ?', [$id]);
                    if ($row !== null) {
                        $pool = db_all('SELECT id, german, article, plural, turkish, part_of_speech, cefr_level, topic FROM vocabulary WHERE is_active = 1');
                        $byPos = [];
                        foreach ($pool as $p) {
                            $byPos[$p['part_of_speech']][] = $p;
                        }
                        generate_vocab_exercises($row, $byPos);
                    }
                    admin_log((int)$admin['id'], 'VOCAB_CREATED', 'vocabulary', (string)$id, ['german' => $f['german']]);
                    flash('success', 'Kelime eklendi ve alıştırmaları otomatik üretildi.');
                    redirect('/admin-vocabulary.php?edit=' . $id);
                }
            } else {
                $id = input_int('vocabulary_id', 0);
                db_exec(
                    'UPDATE vocabulary SET german = ?, normalized_german = ?, article = ?, gender = ?, plural = ?, turkish = ?,
                        part_of_speech = ?, pronunciation = ?, ipa = ?, cefr_level = ?, topic = ?, example_de = ?, example_tr = ?,
                        usage_notes = ?, memory_tip = ?, similar_word_note = ?, separable_prefix = ?, auxiliary = ?, preterite = ?,
                        participle_ii = ?, third_person = ?, is_irregular = ?, is_reflexive = ?, requires_case = ?,
                        required_preposition = ?, is_active = ? WHERE id = ?',
                    [
                        $f['german'], de_normalize($f['german']), $f['article'], $gender, $f['plural'], $f['turkish'],
                        $f['part_of_speech'], $f['pronunciation'], $f['ipa'], $f['cefr_level'], $f['topic'],
                        $f['example_de'], $f['example_tr'], $f['usage_notes'], $f['memory_tip'], $f['similar_word_note'],
                        $f['separable_prefix'], $f['auxiliary'], $f['preterite'], $f['participle_ii'], $f['third_person'],
                        $f['is_irregular'], $f['is_reflexive'], $f['requires_case'], $f['required_preposition'],
                        !empty($_POST['is_active']) ? 1 : 0, $id,
                    ]
                );
                admin_log((int)$admin['id'], 'VOCAB_UPDATED', 'vocabulary', (string)$id, ['german' => $f['german']]);
                flash('success', 'Kelime güncellendi.');
                redirect('/admin-vocabulary.php?edit=' . $id);
            }
        }
    } elseif ($action === 'delete') {
        $id = input_int('vocabulary_id', 0);
        $german = (string)db_value('SELECT german FROM vocabulary WHERE id = ?', [$id], '');
        db_exec('DELETE FROM vocabulary WHERE id = ?', [$id]);
        admin_log((int)$admin['id'], 'VOCAB_DELETED', 'vocabulary', (string)$id, ['german' => $german]);
        flash('success', 'Kelime silindi.');
        redirect('/admin-vocabulary.php');
    } elseif ($action === 'regenerate') {
        $id = input_int('vocabulary_id', 0);
        db_exec('DELETE FROM exercises WHERE vocabulary_id = ? AND is_generated = 1', [$id]);
        $row = db_row('SELECT * FROM vocabulary WHERE id = ?', [$id]);
        if ($row !== null) {
            $pool = db_all('SELECT id, german, article, plural, turkish, part_of_speech, cefr_level, topic FROM vocabulary WHERE is_active = 1');
            $byPos = [];
            foreach ($pool as $p) {
                $byPos[$p['part_of_speech']][] = $p;
            }
            $n = generate_vocab_exercises($row, $byPos);
            flash('success', $n . ' alıştırma yeniden üretildi.');
        }
        redirect('/admin-vocabulary.php?edit=' . $id);
    }
}

$editId = input_int('edit', 0);
$edit = $editId > 0 ? db_row('SELECT * FROM vocabulary WHERE id = ?', [$editId]) : null;

$q = trim((string)input('q', ''));
$level = (string)input('level', '');
$pos = (string)input('pos', '');
$page = max(1, input_int('page', 1));
$perPage = 30;

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(german LIKE ? OR turkish LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if (in_array($level, cefr_levels(), true)) {
    $where[] = 'cefr_level = ?';
    $params[] = $level;
}
if ($pos !== '') {
    $where[] = 'part_of_speech = ?';
    $params[] = $pos;
}
$whereSql = implode(' AND ', $where);
$total = (int)db_value('SELECT COUNT(*) FROM vocabulary WHERE ' . $whereSql, $params, 0);
$totalPages = (int)max(1, ceil($total / $perPage));
$page = min($page, $totalPages);
$listParams = $params;
$listParams[] = $perPage;
$listParams[] = ($page - 1) * $perPage;

$rows = db_all(
    'SELECT v.*, (SELECT COUNT(*) FROM exercises e WHERE e.vocabulary_id = v.id) exercises
     FROM vocabulary v WHERE ' . $whereSql . '
     ORDER BY FIELD(cefr_level,"A0","A1","A2","B1"), german LIMIT ? OFFSET ?',
    $listParams
);

$posOptions = [
    'noun' => 'İsim', 'verb' => 'Fiil', 'adjective' => 'Sıfat', 'adverb' => 'Zarf',
    'preposition' => 'Edat', 'pronoun' => 'Zamir', 'conjunction' => 'Bağlaç',
    'numeral' => 'Sayı', 'phrase' => 'Kalıp', 'article' => 'Artikel', 'particle' => 'Edat/parçacık',
];

render_admin_start($admin, 'Kelime Hazinesi');
?>
<h1>Kelime Hazinesi</h1>
<p style="color: var(--admin-ink-2);">Toplam <span class="mono"><?= $total ?></span> kayıt.
  İsim eklerken artikel zorunludur; kelime artikeliyle ve çoğuluyla öğretilir.</p>

<form method="get" action="/admin-vocabulary.php" class="filter-bar" style="margin: 18px 0;">
  <input name="q" type="search" value="<?= e($q) ?>" placeholder="Almanca veya Türkçe ara" style="max-width: 240px;" aria-label="Ara">
  <select name="level" style="max-width: 130px;" aria-label="Seviye">
    <option value="">Seviye</option>
    <?php foreach (cefr_levels() as $lv): ?><option value="<?= e($lv) ?>"<?= $level === $lv ? ' selected' : '' ?>><?= e($lv) ?></option><?php endforeach; ?>
  </select>
  <select name="pos" style="max-width: 150px;" aria-label="Tür">
    <option value="">Tür</option>
    <?php foreach ($posOptions as $k => $label): ?><option value="<?= e($k) ?>"<?= $pos === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn--sm btn--inline" type="submit">FİLTRELE</button>
  <a class="btn btn--sm btn--secondary btn--inline" href="/admin-vocabulary.php#form">YENİ KELİME</a>
</form>

<div class="table-wrap">
  <table class="data">
    <thead><tr><th>ID</th><th>Kelime</th><th>Çoğul</th><th>Türkçe</th><th>Tür</th><th>Seviye</th><th>Alıştırma</th><th>Durum</th><th>İşlem</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $v): ?>
        <tr>
          <td data-label="ID" class="mono"><?= (int)$v['id'] ?></td>
          <td data-label="Kelime"><a href="/admin-vocabulary.php?edit=<?= (int)$v['id'] ?>#form"><?= e(trim((string)($v['article'] ?? '') . ' ' . (string)$v['german'])) ?></a></td>
          <td data-label="Çoğul" class="small"><?= e((string)($v['plural'] ?? '—')) ?></td>
          <td data-label="Türkçe" class="small"><?= e((string)$v['turkish']) ?></td>
          <td data-label="Tür" class="small"><?= e($posOptions[(string)$v['part_of_speech']] ?? (string)$v['part_of_speech']) ?></td>
          <td data-label="Seviye" class="mono"><?= e((string)$v['cefr_level']) ?></td>
          <td data-label="Alıştırma" class="mono"><?= (int)$v['exercises'] ?></td>
          <td data-label="Durum"><?php render_badge((int)$v['is_active'] === 1 ? '✓' : '○', (int)$v['is_active'] === 1 ? 'AKTİF' : 'PASİF', (int)$v['is_active'] === 1 ? 'ok' : 'muted'); ?></td>
          <td data-label="İşlem">
            <form method="post" action="/admin-vocabulary.php" style="display: inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="vocabulary_id" value="<?= (int)$v['id'] ?>">
              <button class="btn btn--sm btn--danger btn--inline" type="submit" data-confirm="Kelime ve alıştırmaları silinecek. Emin misin?">SİL</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?><tr><td colspan="9">Kayıt bulunamadı.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php render_pagination($page, $totalPages, '/admin-vocabulary.php?q=' . urlencode($q) . '&level=' . urlencode($level) . '&pos=' . urlencode($pos)); ?>

<section class="admin-card" id="form" style="margin-top: 26px;">
  <h2 style="font-size: 18px;"><?= $edit !== null ? 'Kelimeyi düzenle: ' . e((string)$edit['german']) : 'Yeni kelime ekle' ?></h2>
  <form method="post" action="/admin-vocabulary.php" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $edit !== null ? 'update' : 'create' ?>">
    <?php if ($edit !== null): ?><input type="hidden" name="vocabulary_id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

    <div class="grid grid-3" style="gap: 12px;">
      <div class="field">
        <label for="german">Almanca</label>
        <input id="german" name="german" type="text" value="<?= e((string)($edit['german'] ?? '')) ?>" required>
        <?php if (isset($errors['german'])): ?><div class="hint text-danger">✕ <?= e($errors['german']) ?></div><?php endif; ?>
      </div>
      <div class="field">
        <label for="article">Artikel</label>
        <select id="article" name="article">
          <option value="">— yok —</option>
          <?php foreach (['der', 'die', 'das'] as $a): ?>
            <option value="<?= e($a) ?>"<?= (string)($edit['article'] ?? '') === $a ? ' selected' : '' ?>><?= e($a) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['article'])): ?><div class="hint text-danger">✕ <?= e($errors['article']) ?></div><?php endif; ?>
      </div>
      <div class="field">
        <label for="plural">Çoğul</label>
        <input id="plural" name="plural" type="text" value="<?= e((string)($edit['plural'] ?? '')) ?>" placeholder="die Tische">
      </div>
      <div class="field">
        <label for="turkish">Türkçe</label>
        <input id="turkish" name="turkish" type="text" value="<?= e((string)($edit['turkish'] ?? '')) ?>" required>
        <?php if (isset($errors['turkish'])): ?><div class="hint text-danger">✕ <?= e($errors['turkish']) ?></div><?php endif; ?>
      </div>
      <div class="field">
        <label for="part_of_speech">Tür</label>
        <select id="part_of_speech" name="part_of_speech">
          <?php foreach ($posOptions as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= (string)($edit['part_of_speech'] ?? 'noun') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="cefr_level_v">Seviye</label>
        <select id="cefr_level_v" name="cefr_level">
          <?php foreach (cefr_levels() as $lv): ?>
            <option value="<?= e($lv) ?>"<?= (string)($edit['cefr_level'] ?? 'A1') === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="pronunciation">Okunuş</label>
        <input id="pronunciation" name="pronunciation" type="text" value="<?= e((string)($edit['pronunciation'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="ipa">IPA</label>
        <input id="ipa" name="ipa" type="text" value="<?= e((string)($edit['ipa'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="topic">Konu etiketi</label>
        <input id="topic" name="topic" type="text" value="<?= e((string)($edit['topic'] ?? '')) ?>" placeholder="arbeit, essen, wohnen…">
      </div>
    </div>

    <div class="grid grid-2" style="gap: 12px;">
      <div class="field">
        <label for="example_de">Örnek cümle (Almanca)</label>
        <input id="example_de" name="example_de" type="text" value="<?= e((string)($edit['example_de'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="example_tr">Örnek cümle (Türkçe)</label>
        <input id="example_tr" name="example_tr" type="text" value="<?= e((string)($edit['example_tr'] ?? '')) ?>">
      </div>
    </div>

    <div class="grid grid-3" style="gap: 12px;">
      <div class="field">
        <label for="third_person">3. tekil (fiil)</label>
        <input id="third_person" name="third_person" type="text" value="<?= e((string)($edit['third_person'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="preterite">Präteritum</label>
        <input id="preterite" name="preterite" type="text" value="<?= e((string)($edit['preterite'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="participle_ii">Partizip II</label>
        <input id="participle_ii" name="participle_ii" type="text" value="<?= e((string)($edit['participle_ii'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="auxiliary">Yardımcı fiil</label>
        <select id="auxiliary" name="auxiliary">
          <option value="">—</option>
          <?php foreach (['haben', 'sein'] as $a): ?>
            <option value="<?= e($a) ?>"<?= (string)($edit['auxiliary'] ?? '') === $a ? ' selected' : '' ?>><?= e($a) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="separable_prefix">Ayrılabilir ön ek</label>
        <input id="separable_prefix" name="separable_prefix" type="text" value="<?= e((string)($edit['separable_prefix'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="required_preposition">İstediği edat</label>
        <input id="required_preposition" name="required_preposition" type="text" value="<?= e((string)($edit['required_preposition'] ?? '')) ?>" placeholder="auf + Akkusativ">
      </div>
      <div class="field">
        <label for="requires_case">İstediği hal</label>
        <select id="requires_case" name="requires_case">
          <option value="">—</option>
          <?php foreach (['nominativ', 'akkusativ', 'dativ', 'genitiv'] as $c): ?>
            <option value="<?= e($c) ?>"<?= (string)($edit['requires_case'] ?? '') === $c ? ' selected' : '' ?>><?= e(ucfirst($c)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="field">
      <label for="usage_notes">Kullanım notu</label>
      <textarea id="usage_notes" name="usage_notes" rows="2"><?= e((string)($edit['usage_notes'] ?? '')) ?></textarea>
    </div>
    <div class="field">
      <label for="memory_tip">Kolay hatırlama</label>
      <textarea id="memory_tip" name="memory_tip" rows="2"><?= e((string)($edit['memory_tip'] ?? '')) ?></textarea>
    </div>
    <div class="field">
      <label for="similar_word_note">Benzer kelime farkı</label>
      <textarea id="similar_word_note" name="similar_word_note" rows="2"><?= e((string)($edit['similar_word_note'] ?? '')) ?></textarea>
    </div>

    <div class="row">
      <span class="checkline"><input id="is_irregular" name="is_irregular" type="checkbox" value="1"<?= (int)($edit['is_irregular'] ?? 0) === 1 ? ' checked' : '' ?>><label for="is_irregular">Düzensiz fiil</label></span>
      <span class="checkline"><input id="is_reflexive" name="is_reflexive" type="checkbox" value="1"<?= (int)($edit['is_reflexive'] ?? 0) === 1 ? ' checked' : '' ?>><label for="is_reflexive">Dönüşlü fiil</label></span>
      <span class="checkline"><input id="is_active" name="is_active" type="checkbox" value="1"<?= (int)($edit['is_active'] ?? 1) === 1 ? ' checked' : '' ?>><label for="is_active">Aktif</label></span>
    </div>

    <div class="row" style="margin-top: 14px;">
      <button class="btn btn--inline" type="submit"><?= $edit !== null ? 'GÜNCELLE' : 'KELİMEYİ EKLE' ?></button>
      <?php if ($edit !== null): ?>
        <a class="btn btn--secondary btn--inline" href="/admin-vocabulary.php">YENİ KELİME</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($edit !== null): ?>
    <form method="post" action="/admin-vocabulary.php" style="margin-top: 16px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="regenerate">
      <input type="hidden" name="vocabulary_id" value="<?= (int)$edit['id'] ?>">
      <button class="btn btn--sm btn--secondary btn--inline" type="submit"
              data-confirm="Bu kelimenin otomatik alıştırmaları yeniden üretilecek. Devam edilsin mi?">ALIŞTIRMALARI YENİDEN ÜRET</button>
      <span class="small" style="color: var(--admin-ink-2); margin-left: 10px;">
        Tanıma, aktif hatırlama, artikel, çoğul ve bağlam alıştırmaları yeniden oluşturulur.
      </span>
    </form>
  <?php endif; ?>
</section>
<?php render_admin_end(); ?>
