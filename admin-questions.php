<?php
/**
 * AlmancaPro - Kullanici sorulari ve bilgi bankasi yonetimi.
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

    if ($action === 'answer') {
        $qid = input_int('question_id', 0);
        $body = trim((string)input('body', ''));
        $q = db_row('SELECT * FROM questions WHERE id = ?', [$qid]);
        if ($q === null) {
            flash('error', 'Soru bulunamadı.');
        } elseif (mb_strlen($body) < 5) {
            flash('error', 'Cevap en az 5 karakter olmalı.');
        } else {
            db_exec(
                'INSERT INTO answers (question_id, source, admin_id, body) VALUES (?, "admin", ?, ?)',
                [$qid, (int)$admin['id'], $body]
            );
            db_exec('UPDATE questions SET status = "answered" WHERE id = ?', [$qid]);
            admin_log((int)$admin['id'], 'QUESTION_ANSWERED', 'question', (string)$qid);
            flash('success', 'Cevap kaydedildi.');
        }
        redirect('/admin-questions.php?open=' . $qid);
    }

    if ($action === 'close') {
        $qid = input_int('question_id', 0);
        db_exec('UPDATE questions SET status = "closed" WHERE id = ?', [$qid]);
        admin_log((int)$admin['id'], 'QUESTION_CLOSED', 'question', (string)$qid);
        flash('success', 'Soru kapatıldı.');
        redirect('/admin-questions.php');
    }

    if ($action === 'kb_save') {
        $id = input_int('kb_id', 0);
        $question = trim((string)input('question', ''));
        $answer = trim((string)input('answer', ''));
        $keywords = trim((string)input('keywords', ''));
        $level = in_array((string)input('cefr_level', ''), cefr_levels(), true) ? (string)input('cefr_level') : 'A1';
        $slug = trim((string)input('slug', ''));
        if ($slug === '') {
            $slug = slugify($question);
        }
        if (mb_strlen($question) < 5) {
            $errors['question'] = 'Soru en az 5 karakter olmalı.';
        }
        if (mb_strlen($answer) < 10) {
            $errors['answer'] = 'Cevap en az 10 karakter olmalı.';
        }
        if ($keywords === '') {
            $errors['keywords'] = 'Anahtar kelimeler gerekli (virgülle ayır).';
        }
        if ($errors === []) {
            if ($id > 0) {
                db_exec(
                    'UPDATE knowledge_base SET slug = ?, question = ?, answer = ?, keywords = ?, cefr_level = ? WHERE id = ?',
                    [$slug, $question, $answer, $keywords, $level, $id]
                );
                admin_log((int)$admin['id'], 'KB_UPDATED', 'knowledge_base', (string)$id);
                flash('success', 'Bilgi bankası kaydı güncellendi.');
            } else {
                if ((int)db_value('SELECT COUNT(*) FROM knowledge_base WHERE slug = ?', [$slug], 0) > 0) {
                    $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 5);
                }
                $id = db_insert(
                    'INSERT INTO knowledge_base (slug, question, answer, keywords, cefr_level, sort_order) VALUES (?,?,?,?,?,0)',
                    [$slug, $question, $answer, $keywords, $level]
                );
                admin_log((int)$admin['id'], 'KB_CREATED', 'knowledge_base', (string)$id);
                flash('success', 'Bilgi bankası kaydı eklendi.');
            }
            redirect('/admin-questions.php?kb=' . $id . '#kb');
        }
    }

    if ($action === 'kb_delete') {
        $id = input_int('kb_id', 0);
        db_exec('DELETE FROM knowledge_base WHERE id = ?', [$id]);
        admin_log((int)$admin['id'], 'KB_DELETED', 'knowledge_base', (string)$id);
        flash('success', 'Kayıt silindi.');
        redirect('/admin-questions.php#kb');
    }
}

$status = (string)input('status', 'open');
if (!in_array($status, ['open', 'answered', 'closed', 'all'], true)) {
    $status = 'open';
}
$page = max(1, input_int('page', 1));
$perPage = 20;

$where = $status === 'all' ? '' : ' WHERE q.status = ?';
$params = $status === 'all' ? [] : [$status];
$total = (int)db_value('SELECT COUNT(*) FROM questions q' . $where, $params, 0);
$totalPages = (int)ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$questions = db_all(
    'SELECT q.*, u.name AS user_name, u.email, u.cefr_level
     FROM questions q LEFT JOIN users u ON u.id = q.user_id' . $where . '
     ORDER BY q.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
    $params
);

$answers = [];
if ($questions !== []) {
    $ids = array_map('intval', array_column($questions, 'id'));
    foreach (db_all('SELECT * FROM answers WHERE question_id IN (' . implode(',', $ids) . ') ORDER BY id') as $a) {
        $answers[(int)$a['question_id']][] = $a;
    }
}

$openId = input_int('open', 0);

$kbEditId = input_int('kb', 0);
$kbEdit = $kbEditId > 0 ? db_row('SELECT * FROM knowledge_base WHERE id = ?', [$kbEditId]) : null;
$kbList = db_all('SELECT * FROM knowledge_base ORDER BY cefr_level, sort_order, id');

$counts = db_row(
    'SELECT SUM(status="open") AS o, SUM(status="answered") AS a, SUM(status="closed") AS c FROM questions'
) ?? [];

render_admin_start($admin, 'Kullanıcı Soruları');
?>
<div class="stat-grid">
  <div class="card stat"><div class="stat__label">Açık</div><div class="stat__value"><?= (int)($counts['o'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Cevaplanmış</div><div class="stat__value"><?= (int)($counts['a'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Kapatılmış</div><div class="stat__value"><?= (int)($counts['c'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Bilgi bankası</div><div class="stat__value"><?= count($kbList) ?></div></div>
</div>

<section class="card">
  <h2 class="card__title">Sorular</h2>
  <nav class="tabs" aria-label="Durum filtresi">
    <?php foreach (['open' => 'Açık', 'answered' => 'Cevaplanmış', 'closed' => 'Kapalı', 'all' => 'Tümü'] as $k => $label): ?>
      <a class="tab<?= $status === $k ? ' is-active' : '' ?>" href="/admin-questions.php?status=<?= e($k) ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($questions === []): ?>
    <?php render_empty('Soru yok', 'Bu filtreye uyan kullanıcı sorusu bulunmuyor.'); ?>
  <?php else: ?>
    <?php foreach ($questions as $q): $qid = (int)$q['id']; ?>
      <details class="accordion"<?= $openId === $qid || $status === 'open' ? ' open' : '' ?>>
        <summary class="accordion__head">
          <span class="mono">#<?= $qid ?></span>
          <span><?= e(str_limit((string)$q['body'], 90)) ?></span>
          <span class="small"><?= e((string)($q['user_name'] ?? 'Silinmiş kullanıcı')) ?> · <?= e((string)($q['cefr_level'] ?? '—')) ?> · <?= e(local_datetime((string)$q['created_at'])) ?></span>
        </summary>
        <div class="accordion__body">
          <p><strong>Soru:</strong></p>
          <?= e_paragraphs((string)$q['body']) ?>
          <?php if (!empty($q['context'])): ?>
            <p class="small"><strong>Bağlam:</strong> <?= e(str_limit((string)$q['context'], 400)) ?></p>
          <?php endif; ?>

          <?php foreach ($answers[$qid] ?? [] as $a): ?>
            <div class="card card--inset">
              <div class="small mono"><?= e(match ((string)$a['source']) {
                  'ai' => 'AI cevabı',
                  'admin' => 'Yönetici cevabı',
                  default => 'Bilgi bankası',
              }) ?> · <?= e(local_datetime((string)$a['created_at'])) ?></div>
              <?= e_paragraphs((string)$a['body']) ?>
            </div>
          <?php endforeach; ?>

          <?php if ((string)$q['status'] !== 'closed'): ?>
          <form method="post" action="/admin-questions.php" class="stack" data-guard>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="answer">
            <input type="hidden" name="question_id" value="<?= $qid ?>">
            <label class="field">
              <span class="field__label" for="ans-<?= $qid ?>">Yönetici cevabı</span>
              <textarea class="input" id="ans-<?= $qid ?>" name="body" rows="5" required
                placeholder="Türkçe açıkla, Almanca örnek ver. Artikel, çoğul ve durum bilgisini unutma."></textarea>
            </label>
            <div class="row">
              <button class="btn btn--inline" type="submit">Cevabı Kaydet</button>
            </div>
          </form>
          <form method="post" action="/admin-questions.php" class="mt-8">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="close">
            <input type="hidden" name="question_id" value="<?= $qid ?>">
            <button class="btn btn--sm btn--secondary btn--inline" type="submit">Soruyu kapat</button>
          </form>
          <?php endif; ?>
        </div>
      </details>
    <?php endforeach; ?>
    <?php render_pagination($page, $totalPages, '/admin-questions.php?status=' . urlencode($status)); ?>
  <?php endif; ?>
</section>

<section class="card" id="kb">
  <h2 class="card__title">Bilgi Bankası (AI kapalıyken kullanılır)</h2>
  <p class="small">AI yapılandırılmadığında "Öğretmene Sor" bu kayıtlardan anahtar kelime eşleşmesiyle cevap üretir.</p>

  <form method="post" action="/admin-questions.php#kb" class="stack" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="kb_save">
    <input type="hidden" name="kb_id" value="<?= (int)($kbEdit['id'] ?? 0) ?>">
    <div class="grid grid--2">
      <label class="field">
        <span class="field__label" for="kb-question">Soru</span>
        <input class="input<?= isset($errors['question']) ? ' is-invalid' : '' ?>" id="kb-question" name="question" required
          value="<?= e((string)($kbEdit['question'] ?? '')) ?>">
        <?php if (isset($errors['question'])): ?><span class="field__error">✕ <?= e($errors['question']) ?></span><?php endif; ?>
      </label>
      <label class="field">
        <span class="field__label" for="kb-level">CEFR seviyesi</span>
        <select class="select" id="kb-level" name="cefr_level">
          <?php foreach (cefr_levels() as $lv): ?>
            <option value="<?= e($lv) ?>"<?= (string)($kbEdit['cefr_level'] ?? 'A1') === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <label class="field">
      <span class="field__label" for="kb-answer">Cevap</span>
      <textarea class="input<?= isset($errors['answer']) ? ' is-invalid' : '' ?>" id="kb-answer" name="answer" rows="7" required><?= e((string)($kbEdit['answer'] ?? '')) ?></textarea>
      <?php if (isset($errors['answer'])): ?><span class="field__error">✕ <?= e($errors['answer']) ?></span><?php endif; ?>
    </label>
    <div class="grid grid--2">
      <label class="field">
        <span class="field__label" for="kb-keywords">Anahtar kelimeler (virgülle)</span>
        <input class="input<?= isset($errors['keywords']) ? ' is-invalid' : '' ?>" id="kb-keywords" name="keywords" required
          value="<?= e((string)($kbEdit['keywords'] ?? '')) ?>">
        <?php if (isset($errors['keywords'])): ?><span class="field__error">✕ <?= e($errors['keywords']) ?></span><?php endif; ?>
      </label>
      <label class="field">
        <span class="field__label" for="kb-slug">Slug (boş bırakılırsa otomatik)</span>
        <input class="input" id="kb-slug" name="slug" value="<?= e((string)($kbEdit['slug'] ?? '')) ?>">
      </label>
    </div>
    <div class="row">
      <button class="btn btn--inline" type="submit"><?= $kbEdit !== null ? 'Kaydı Güncelle' : 'Kayıt Ekle' ?></button>
      <?php if ($kbEdit !== null): ?>
        <a class="btn btn--secondary btn--inline" href="/admin-questions.php#kb">Yeni kayıt</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="table-wrap mt-16">
  <table class="table">
    <thead><tr><th>#</th><th>Soru</th><th>Seviye</th><th>Anahtar kelimeler</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($kbList as $kb): ?>
      <tr>
        <td class="num"><?= (int)$kb['id'] ?></td>
        <td><?= e(str_limit((string)$kb['question'], 70)) ?></td>
        <td><?= e((string)$kb['cefr_level']) ?></td>
        <td class="small"><?= e(str_limit((string)$kb['keywords'], 60)) ?></td>
        <td class="row">
          <a class="btn btn--sm btn--secondary btn--inline" href="/admin-questions.php?kb=<?= (int)$kb['id'] ?>#kb">Düzenle</a>
          <form method="post" action="/admin-questions.php#kb" data-confirm="Bu kayıt silinsin mi?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="kb_delete">
            <input type="hidden" name="kb_id" value="<?= (int)$kb['id'] ?>">
            <button class="btn btn--sm btn--danger btn--inline" type="submit">Sil</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php
render_admin_end();
