<?php
/**
 * AlmancaPro - Quiz ve oturum analizi.
 * Gercek study_sessions / exercise_attempts verisinden beslenir.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-auth.php';
app_boot();

$admin = require_admin();

$type = (string)input('type', '');
$types = ['lesson', 'quiz', 'review', 'remediation', 'checkpoint', 'placement', 'scenario', 'telegram', 'intensive'];
if (!in_array($type, $types, true)) {
    $type = '';
}
$page = max(1, input_int('page', 1));
$perPage = 30;

$where = [];
$params = [];
if ($type !== '') {
    $where[] = 's.session_type = ?';
    $params[] = $type;
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

$total = (int)db_value('SELECT COUNT(*) FROM study_sessions s' . $whereSql, $params, 0);
$totalPages = (int)ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$sessions = db_all(
    'SELECT s.*, u.name AS user_name, u.email, l.title AS lesson_title
     FROM study_sessions s
     LEFT JOIN users u ON u.id = s.user_id
     LEFT JOIN lessons l ON l.id = s.lesson_id' . $whereSql . '
     ORDER BY s.started_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
    $params
);

/* Genel ozet */
$summary = db_row(
    'SELECT COUNT(*) AS total,
            SUM(status = "completed") AS completed,
            SUM(status = "active") AS active,
            SUM(status = "abandoned") AS abandoned,
            SUM(correct_count) AS correct,
            SUM(wrong_count) AS wrong,
            SUM(duration_seconds) AS seconds
     FROM study_sessions'
) ?? [];

$byType = db_all(
    'SELECT session_type, COUNT(*) AS cnt, SUM(correct_count) AS correct, SUM(wrong_count) AS wrong
     FROM study_sessions GROUP BY session_type ORDER BY cnt DESC'
);

/* En cok hata alinan alistirmalar */
$hardest = db_all(
    'SELECT e.id, e.exercise_type, e.prompt, e.cefr_level,
            COUNT(a.id) AS attempts,
            SUM(a.is_correct = 0) AS wrong,
            ROUND(100 * SUM(a.is_correct = 1) / COUNT(a.id)) AS accuracy
     FROM exercise_attempts a
     JOIN exercises e ON e.id = a.exercise_id
     GROUP BY e.id, e.exercise_type, e.prompt, e.cefr_level
     HAVING attempts >= 5
     ORDER BY accuracy ASC, attempts DESC
     LIMIT 20'
);

/* Hata kategorisi dagilimi */
$errorDist = db_all(
    'SELECT c.code, c.name, COUNT(*) AS cnt
     FROM answer_error_categories aec
     JOIN error_categories c ON c.id = aec.error_category_id
     GROUP BY c.code, c.name ORDER BY cnt DESC LIMIT 20'
);

/* Alistirma turu basina performans */
$byExerciseType = db_all(
    'SELECT e.exercise_type, COUNT(a.id) AS attempts,
            ROUND(100 * SUM(a.is_correct = 1) / GREATEST(COUNT(a.id),1)) AS accuracy
     FROM exercise_attempts a JOIN exercises e ON e.id = a.exercise_id
     GROUP BY e.exercise_type ORDER BY attempts DESC'
);

render_admin_start($admin, 'Quizler ve Oturumlar');
?>
<div class="stat-grid">
  <div class="card stat"><div class="stat__label">Toplam oturum</div><div class="stat__value"><?= (int)($summary['total'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Tamamlanan</div><div class="stat__value"><?= (int)($summary['completed'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Devam eden</div><div class="stat__value"><?= (int)($summary['active'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Doğru / Yanlış</div><div class="stat__value"><?= (int)($summary['correct'] ?? 0) ?> / <?= (int)($summary['wrong'] ?? 0) ?></div></div>
  <div class="card stat"><div class="stat__label">Toplam çalışma</div><div class="stat__value"><?= e(human_minutes((int)round(((int)($summary['seconds'] ?? 0)) / 60))) ?></div></div>
</div>

<div class="grid grid--2">
  <section class="card">
    <h2 class="card__title">Oturum türüne göre</h2>
    <?php if ($byType === []): ?>
      <?php render_empty('Henüz oturum yok', 'Kullanıcılar çalışmaya başladığında burada gerçek veriler görünür.'); ?>
    <?php else: ?>
    <table class="table">
      <thead><tr><th>Tür</th><th>Oturum</th><th>Doğru</th><th>Yanlış</th><th>Başarı</th></tr></thead>
      <tbody>
      <?php foreach ($byType as $r):
          $c = (int)$r['correct']; $w = (int)$r['wrong']; ?>
        <tr>
          <td><?= e((string)$r['session_type']) ?></td>
          <td class="num"><?= (int)$r['cnt'] ?></td>
          <td class="num"><?= $c ?></td>
          <td class="num"><?= $w ?></td>
          <td class="num"><?= pct($c, max(1, $c + $w)) ?>%</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2 class="card__title">Alıştırma türü performansı</h2>
    <?php if ($byExerciseType === []): ?>
      <?php render_empty('Veri yok', 'Henüz cevaplanmış alıştırma bulunmuyor.'); ?>
    <?php else: ?>
    <table class="table">
      <thead><tr><th>Tür</th><th>Deneme</th><th>Doğruluk</th></tr></thead>
      <tbody>
      <?php foreach ($byExerciseType as $r): ?>
        <tr>
          <td><?= e((string)$r['exercise_type']) ?></td>
          <td class="num"><?= (int)$r['attempts'] ?></td>
          <td><?php render_progress((int)$r['accuracy'], 100, (int)$r['accuracy'] >= 70 ? 'success' : 'warn'); ?> <span class="num"><?= (int)$r['accuracy'] ?>%</span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
</div>

<section class="card">
  <h2 class="card__title">En çok zorlanılan alıştırmalar</h2>
  <p class="small">Bu liste müfredat kalitesini iyileştirmek içindir: doğruluk oranı düşük sorular gözden geçirilmelidir.</p>
  <?php if ($hardest === []): ?>
    <?php render_empty('Yeterli veri yok', 'En az 5 kez cevaplanmış alıştırma bulunduğunda burada listelenir.'); ?>
  <?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>#</th><th>Soru</th><th>Tür</th><th>Seviye</th><th>Deneme</th><th>Doğruluk</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($hardest as $r): ?>
      <tr>
        <td class="num"><?= (int)$r['id'] ?></td>
        <td><?= e(str_limit((string)$r['prompt'], 90)) ?></td>
        <td><?= e((string)$r['exercise_type']) ?></td>
        <td><?= e((string)$r['cefr_level']) ?></td>
        <td class="num"><?= (int)$r['attempts'] ?></td>
        <td class="num"><?= (int)$r['accuracy'] ?>%</td>
        <td><a class="btn btn--sm btn--secondary btn--inline" href="/admin-exercises.php?edit=<?= (int)$r['id'] ?>">Düzenle</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>

<section class="card">
  <h2 class="card__title">Hata kategorisi dağılımı</h2>
  <?php if ($errorDist === []): ?>
    <?php render_empty('Hata kaydı yok', 'Kullanıcılar yanlış cevap verdikçe hata taksonomisi burada dolar.'); ?>
  <?php else:
      $maxErr = max(array_map(static fn(array $r): int => (int)$r['cnt'], $errorDist)); ?>
  <table class="table">
    <thead><tr><th>Kategori</th><th>Kod</th><th>Adet</th><th>Pay</th></tr></thead>
    <tbody>
    <?php foreach ($errorDist as $r): ?>
      <tr>
        <td><?= e((string)$r['name']) ?></td>
        <td><code><?= e((string)$r['code']) ?></code></td>
        <td class="num"><?= (int)$r['cnt'] ?></td>
        <td><?php render_progress((int)$r['cnt'], $maxErr, 'warn'); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>

<section class="card">
  <h2 class="card__title">Son oturumlar</h2>
  <form class="filters" method="get" action="/admin-quizzes.php">
    <label class="field">
      <span class="field__label">Tür</span>
      <select class="select" name="type" data-autosubmit>
        <option value="">Tümü</option>
        <?php foreach ($types as $t): ?>
          <option value="<?= e($t) ?>"<?= $type === $t ? ' selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <noscript><button class="btn btn--sm btn--inline" type="submit">Filtrele</button></noscript>
  </form>

  <?php if ($sessions === []): ?>
    <?php render_empty('Oturum bulunamadı', 'Seçilen filtreye uyan oturum yok.'); ?>
  <?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>#</th><th>Kullanıcı</th><th>Tür</th><th>Ders</th><th>Durum</th><th>D/Y</th><th>Süre</th><th>Başlangıç</th></tr></thead>
    <tbody>
    <?php foreach ($sessions as $s): ?>
      <tr>
        <td class="num"><?= (int)$s['id'] ?></td>
        <td><?php if ($s['user_id'] !== null): ?>
            <a href="/admin-user.php?id=<?= (int)$s['user_id'] ?>"><?= e((string)($s['user_name'] ?? '—')) ?></a>
          <?php else: ?>—<?php endif; ?></td>
        <td><?= e((string)$s['session_type']) ?></td>
        <td><?= e(str_limit((string)($s['lesson_title'] ?? '—'), 40)) ?></td>
        <td><?= e((string)$s['status']) ?></td>
        <td class="num"><?= (int)$s['correct_count'] ?>/<?= (int)$s['wrong_count'] ?></td>
        <td class="num"><?= (int)round((int)$s['duration_seconds'] / 60) ?> dk</td>
        <td class="num"><?= e(local_datetime((string)$s['started_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php render_pagination($page, $totalPages, '/admin-quizzes.php' . ($type !== '' ? '?type=' . urlencode($type) : '')); ?>
  <?php endif; ?>
</section>
<?php
render_admin_end();
