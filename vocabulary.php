<?php
/**
 * AlmancaPro - Kelime hazinem.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner.php';
app_boot();

$user = require_onboarded();
$userId = (int)$user['id'];
$tz = user_timezone($user);
$counts = due_review_counts($userId);

$filter = (string)input('filter', 'all');
$search = trim((string)input('q', ''));
$level = (string)input('level', '');
$page = max(1, input_int('page', 1));
$perPage = 25;

$where = ['v.is_active = 1'];
$params = [$userId];

switch ($filter) {
    case 'mastered':  $where[] = 'm.status = "mastered"'; break;
    case 'learning':  $where[] = 'm.status IN ("introduced","learning","reviewing","strong")'; break;
    case 'weak':      $where[] = 'm.status = "weak"'; break;
    case 'due':       $where[] = 'm.next_review_at <= UTC_TIMESTAMP() AND m.status <> "mastered"'; break;
    case 'new':       $where[] = 'm.id IS NULL'; break;
}
if ($search !== '') {
    $where[] = '(v.german LIKE ? OR v.turkish LIKE ? OR v.normalized_german LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = de_normalize($search) . '%';
}
if (in_array($level, cefr_levels(), true)) {
    $where[] = 'v.cefr_level = ?';
    $params[] = $level;
}

$whereSql = implode(' AND ', $where);
$total = (int)db_value(
    'SELECT COUNT(*) FROM vocabulary v LEFT JOIN user_vocabulary_mastery m ON m.vocabulary_id = v.id AND m.user_id = ? WHERE ' . $whereSql,
    $params, 0
);
$totalPages = (int)max(1, ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$rowsParams = $params;
$rowsParams[] = $perPage;
$rowsParams[] = $offset;
$rows = db_all(
    'SELECT v.*, m.status AS user_status, m.mastery_score, m.next_review_at, m.attempts
     FROM vocabulary v
     LEFT JOIN user_vocabulary_mastery m ON m.vocabulary_id = v.id AND m.user_id = ?
     WHERE ' . $whereSql . '
     ORDER BY FIELD(v.cefr_level,"A0","A1","A2","B1"), v.german
     LIMIT ? OFFSET ?',
    $rowsParams
);

$summary = db_row(
    'SELECT
        SUM(CASE WHEN status = "mastered" THEN 1 ELSE 0 END) mastered,
        SUM(CASE WHEN status IN ("introduced","learning","reviewing","strong") THEN 1 ELSE 0 END) learning,
        SUM(CASE WHEN status = "weak" THEN 1 ELSE 0 END) weak,
        SUM(CASE WHEN next_review_at <= UTC_TIMESTAMP() AND status <> "mastered" THEN 1 ELSE 0 END) due
     FROM user_vocabulary_mastery WHERE user_id = ?',
    [$userId]
) ?? [];

/* Kelime detayi */
$detailId = input_int('word', 0);
$detail = null;
if ($detailId > 0) {
    $detail = db_row(
        'SELECT v.*, m.status AS user_status, m.mastery_score, m.attempts, m.correct, m.incorrect,
                m.de_tr_ok, m.tr_de_ok, m.article_ok, m.plural_ok, m.spelling_ok, m.delayed_ok, m.next_review_at
         FROM vocabulary v
         LEFT JOIN user_vocabulary_mastery m ON m.vocabulary_id = v.id AND m.user_id = ?
         WHERE v.id = ? AND v.is_active = 1',
        [$userId, $detailId]
    );
}

$baseUrl = '/vocabulary.php?filter=' . urlencode($filter) . '&q=' . urlencode($search) . '&level=' . urlencode($level);

render_app_start($user, 'Kelime Hazinem · ' . APP_NAME, [], ['review.php' => $counts['due']]);
?>
<p class="eyebrow">Kelime hazinem</p>
<h1>Kelimeler</h1>
<p class="muted">Her isim artikeli ve çoğuluyla birlikte öğretilir. Artikeli bilmeden kelime öğrenilmiş sayılmaz.</p>

<div class="hairline-grid grid-4" style="margin: 22px 0;">
  <div style="padding: 16px;"><div class="num" style="font-size: 22px; color: var(--success);"><?= (int)($summary['mastered'] ?? 0) ?></div><div class="small muted">mastered</div></div>
  <div style="padding: 16px;"><div class="num" style="font-size: 22px; color: var(--ink);"><?= (int)($summary['learning'] ?? 0) ?></div><div class="small muted">öğreniyorsun</div></div>
  <div style="padding: 16px;"><div class="num" style="font-size: 22px; color: var(--warn);"><?= (int)($summary['weak'] ?? 0) ?></div><div class="small muted">zayıf</div></div>
  <div style="padding: 16px;"><div class="num" style="font-size: 22px; color: var(--ink);"><?= (int)($summary['due'] ?? 0) ?></div><div class="small muted">tekrar zamanı</div></div>
</div>

<form method="get" action="/vocabulary.php" class="filter-bar">
  <input type="hidden" name="filter" value="<?= e($filter) ?>">
  <label class="sr-only" for="q">Kelime ara</label>
  <input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Almanca veya Türkçe ara" style="max-width: 260px;">
  <label class="sr-only" for="level">Seviye</label>
  <select id="level" name="level" data-autosubmit style="max-width: 140px;">
    <option value="">Tüm seviyeler</option>
    <?php foreach (cefr_levels() as $lv): ?>
      <option value="<?= e($lv) ?>"<?= $level === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn--sm btn--secondary btn--inline" type="submit">ARA</button>
</form>

<div class="filter-bar">
  <?php foreach (['all' => 'Tümü', 'mastered' => 'Mastered', 'learning' => 'Öğreniyorum', 'weak' => 'Zayıf', 'due' => 'Tekrar zamanı', 'new' => 'Hiç çalışılmamış'] as $key => $label): ?>
    <a class="filter-chip<?= $filter === $key ? ' is-active' : '' ?>"
       href="/vocabulary.php?filter=<?= e($key) ?>&amp;q=<?= e(urlencode($search)) ?>&amp;level=<?= e($level) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($detail !== null): ?>
  <div class="card card--strong" style="margin-bottom: 22px;">
    <div class="row-between" style="align-items: flex-start;">
      <div>
        <div class="row" style="gap: 10px; margin-bottom: 10px;">
          <?= artikel_badge((string)($detail['article'] ?? ''), 'lg') ?>
          <span class="badge badge--level"><?= e((string)$detail['cefr_level']) ?></span>
          <?php if (!empty($detail['user_status'])): ?><?php render_mastery_badge((string)$detail['user_status']); ?><?php endif; ?>
        </div>
        <h2 style="font-size: 30px; margin-bottom: 4px;"><?= e(vocab_headword($detail)) ?></h2>
        <p style="margin-bottom: 10px;"><?= e((string)$detail['turkish']) ?></p>
        <p class="mono small muted">
          <?php if (!empty($detail['pronunciation'])): ?>[<?= e((string)$detail['pronunciation']) ?>]<?php endif; ?>
          <?php if (!empty($detail['third_person'])): ?> · <?= e((string)$detail['third_person']) ?><?php endif; ?>
          <?php if (!empty($detail['preterite'])): ?> · <?= e((string)$detail['preterite']) ?><?php endif; ?>
          <?php if (!empty($detail['participle_ii'])): ?> · <?= e(trim((string)($detail['auxiliary'] ?? '') . ' ' . (string)$detail['participle_ii'])) ?><?php endif; ?>
        </p>
      </div>
      <div class="row">
        <button class="btn btn--sm btn--secondary btn--inline" type="button" data-speak="<?= e(vocab_headword($detail)) ?>">▶ DİNLE</button>
        <a class="btn btn--sm btn--secondary btn--inline" href="<?= e($baseUrl) ?>">KAPAT</a>
      </div>
    </div>

    <?php if (!empty($detail['example_de'])): ?>
      <div class="wordcard__example" style="margin-top: 18px;">
        <div class="wordcard__example-de"><?= e((string)$detail['example_de']) ?></div>
        <div class="wordcard__example-tr"><?= e((string)($detail['example_tr'] ?? '')) ?></div>
      </div>
    <?php endif; ?>

    <?php if (!empty($detail['usage_notes'])): ?><p class="small"><strong>Kullanım:</strong> <?= e((string)$detail['usage_notes']) ?></p><?php endif; ?>
    <?php if (!empty($detail['similar_word_note'])): ?><p class="small"><strong>Benzer kelime farkı:</strong> <?= e((string)$detail['similar_word_note']) ?></p><?php endif; ?>
    <?php if (!empty($detail['memory_tip'])): ?>
      <p class="small"><strong>Kolay hatırlama:</strong> <?= e((string)$detail['memory_tip']) ?>
        <span class="muted">(Bu bir kural değil, yardımcı bir ezber yöntemidir.)</span></p>
    <?php endif; ?>
    <?php if (!empty($detail['required_preposition']) || !empty($detail['requires_case'])): ?>
      <p class="small"><strong>Yapı:</strong>
        <?= e(trim((string)($detail['required_preposition'] ?? '') . ' ' . (!empty($detail['requires_case']) ? '+ ' . ucfirst((string)$detail['requires_case']) : ''))) ?></p>
    <?php endif; ?>

    <?php if (!empty($detail['attempts'])): ?>
      <div class="mastery" style="margin-top: 18px;">
        <div class="mastery__head">
          <p class="eyebrow" style="margin: 0;">Bu kelimede durumun</p>
          <span class="mastery__score"><?= (int)$detail['mastery_score'] ?>%</span>
        </div>
        <div class="mastery__axes">
          <?php foreach ([
            ['Almanca → Türkçe', (int)$detail['de_tr_ok'], 1],
            ['Türkçe → Almanca', (int)$detail['tr_de_ok'], 2],
            ['Artikel', (int)$detail['article_ok'], !empty($detail['article']) ? 1 : 0],
            ['Çoğul', (int)$detail['plural_ok'], !empty($detail['plural']) ? 1 : 0],
            ['Yazım', (int)$detail['spelling_ok'], 1],
            ['Gecikmeli hatırlama', (int)$detail['delayed_ok'], 1],
          ] as [$label, $have, $need]):
              if ($need === 0) { continue; }
              $st = $have >= $need ? 'is-strong' : ($have > 0 ? 'is-progress' : 'is-untested');
              $txt = $have >= $need ? '✓ GÜÇLÜ' : ($have > 0 ? '● GELİŞİYOR' : '○ TEST EDİLMEDİ');
          ?>
            <div class="mastery__axis <?= $st ?>">
              <span class="mastery__axis-label"><?= e($label) ?></span>
              <span class="num small"><?= $have ?>/<?= $need ?></span>
              <span class="mastery__axis-state"><?= $txt ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($rows === []): ?>
  <?php render_empty('Sonuç bulunamadı', 'Farklı bir filtre veya arama kelimesi dene.', '/vocabulary.php', 'FİLTREYİ TEMİZLE'); ?>
<?php else: ?>
  <div class="card card--flush">
    <div class="vocab-list">
      <?php foreach ($rows as $v): ?>
        <div class="vocab-row">
          <?= artikel_badge((string)($v['article'] ?? '')) ?>
          <a class="vocab-row__word" href="<?= e($baseUrl . '&page=' . $page . '&word=' . (int)$v['id']) ?>"><?= e((string)$v['german']) ?></a>
          <?php if (!empty($v['plural'])): ?><span class="vocab-row__plural"><?= e((string)$v['plural']) ?></span><?php endif; ?>
          <span class="vocab-row__tr"><?= e((string)$v['turkish']) ?></span>
          <span class="badge badge--level"><?= e((string)$v['cefr_level']) ?></span>
          <?php if (!empty($v['user_status'])): ?>
            <?php render_mastery_badge((string)$v['user_status']); ?>
            <span class="num small" style="min-width: 42px; text-align: right;"><?= (int)$v['mastery_score'] ?>%</span>
          <?php else: ?>
            <span class="badge badge--muted"><span aria-hidden="true">○</span>BAŞLAMADIN</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <p class="small muted" style="margin-top: 12px;"><?= $total ?> kelime · sayfa <?= $page ?>/<?= $totalPages ?></p>
  <?php render_pagination($page, $totalPages, $baseUrl); ?>
<?php endif; ?>

<?php render_app_end(); ?>
