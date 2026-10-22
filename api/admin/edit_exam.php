<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';
require_once 'includes.php';
$db=getDB(); $id=intval($_GET['id']??0);
$exam=$db->prepare("SELECT * FROM exams WHERE id=?"); $exam->execute([$id]); $exam=$exam->fetch();
if (!$exam) { header('Location: exams.php'); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $title=trim($_POST['title']??''); $desc=trim($_POST['description']??''); $dur=intval($_POST['duration_minutes']??30); $active=isset($_POST['is_active'])?1:0;
    if (!$title) { $error='Title required.'; }
    else { $db->prepare("UPDATE exams SET title=?,description=?,duration_minutes=?,is_active=? WHERE id=?")->execute([$title,$desc,$dur,$active,$id]); flash('success','Exam updated!'); header('Location: exams.php'); exit; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Exam — Southern Baptist College</title>
<?php adminCSS(); ?>
<style>
.form-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:28px;max-width:580px;box-shadow:var(--shadow);}
input[type=checkbox]{width:16px;height:16px;accent-color:var(--primary);}
.btn-actions{display:flex;gap:10px;margin-top:6px;}
.btn-cancel{padding:10px 20px;border-radius:8px;border:1px solid var(--border-2);background:#fff;color:var(--text-2);font-size:0.85rem;font-weight:600;text-decoration:none;}
.btn-cancel:hover{border-color:var(--primary);color:var(--primary);}
</style>
</head>
<body>
<?php adminSidebar('exams'); ?>
<main class="main">
  <div class="breadcrumb"><a href="exams.php">Exams</a> / Edit</div>
  <div class="page-header"><div class="page-title">Edit Exam</div></div>
  <?php if ($error): ?><div class="flash flash-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <div class="form-card">
    <form method="POST">
      <div class="form-group"><label>Exam Title *</label><input type="text" name="title" value="<?= htmlspecialchars($exam['title']) ?>" required></div>
      <div class="form-group"><label>Description</label><textarea name="description" rows="3"><?= htmlspecialchars($exam['description']) ?></textarea></div>
      <div class="form-row form-row-2">
        <div class="form-group"><label>Duration (minutes)</label><input type="number" name="duration_minutes" value="<?= $exam['duration_minutes'] ?>" min="1"></div>
        <div class="form-group"><label>Status</label><div style="display:flex;align-items:center;gap:8px;margin-top:8px"><input type="checkbox" name="is_active" id="active" <?= $exam['is_active']?'checked':'' ?>><label for="active" style="margin:0;cursor:pointer;font-weight:400;font-size:0.85rem">Active</label></div></div>
      </div>
      <div class="btn-actions">
        <button type="submit" class="btn-primary">💾 Save Changes</button>
        <a href="exams.php" class="btn-cancel">Cancel</a>
      </div>
    </form>
  </div>
</main>
</body>
</html>
