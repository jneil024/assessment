<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';
require_once 'includes.php';
$db = getDB();
$exams = $db->query("SELECT e.*,COUNT(q.id) as q_count FROM exams e LEFT JOIN questions q ON q.exam_id=e.id GROUP BY e.id ORDER BY e.created_at DESC")->fetchAll();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Exams — Southern Baptist College</title>
<?php adminCSS(); ?>
</head>
<body>
<?php adminSidebar('exams'); ?>
<main class="main">
  <div class="page-header">
    <div><div class="page-title">Exams</div><div class="page-sub"><?= count($exams) ?> total exams</div></div>
    <a href="create_exam.php" class="btn-primary">+ New Exam</a>
  </div>
  <?php if ($flash): ?><div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  <div class="card">
    <?php if (empty($exams)): ?>
    <div class="empty">No exams yet. <a href="create_exam.php" style="color:var(--primary);font-weight:600">Create one →</a></div>
    <?php else: ?>
    <table>
      <thead><tr><th>#</th><th>Title</th><th>Description</th><th>Questions</th><th>Duration</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($exams as $i => $e): ?>
        <tr>
          <td style="color:var(--text-3)"><?= $i+1 ?></td>
          <td><strong><?= htmlspecialchars($e['title']) ?></strong></td>
          <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($e['description']) ?></td>
          <td><?= $e['q_count'] ?></td>
          <td><?= $e['duration_minutes'] ?> min</td>
          <td><span class="badge <?= $e['is_active']?'badge-active':'badge-inactive' ?>"><?= $e['is_active']?'Active':'Inactive' ?></span></td>
          <td><div class="actions">
            <a href="questions.php?exam_id=<?= $e['id'] ?>" class="btn-sm btn-cyan">Questions</a>
            <a href="edit_exam.php?id=<?= $e['id'] ?>" class="btn-sm btn-blue">Edit</a>
            <a href="results.php?exam_id=<?= $e['id'] ?>" class="btn-sm btn-green">Results</a>
            <a href="delete_exam.php?id=<?= $e['id'] ?>" class="btn-sm btn-red" onclick="return confirm('Delete exam and all questions?')">Delete</a>
          </div></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
