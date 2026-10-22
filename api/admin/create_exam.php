<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';
require_once 'includes.php';
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $title=trim($_POST['title']??''); $desc=trim($_POST['description']??''); $dur=intval($_POST['duration_minutes']??30); $active=isset($_POST['is_active'])?1:0;
    if (!$title) { $error='Title is required.'; }
    else { $db=getDB(); $db->prepare("INSERT INTO exams (title,description,duration_minutes,is_active,created_by) VALUES (?,?,?,?,?)")->execute([$title,$desc,$dur,$active,$_SESSION['admin_id']]); $eid=$db->lastInsertId(); flash('success','Exam created!'); header("Location: questions.php?exam_id=$eid"); exit; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create Exam — Southern Baptist College</title>
<?php adminCSS(); ?>
<style>
.form-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:28px;max-width:580px;box-shadow:var(--shadow);}
.toggle-wrap{display:flex;align-items:center;gap:8px;}
input[type=checkbox]{width:16px;height:16px;accent-color:var(--primary);}
.btn-actions{display:flex;gap:10px;margin-top:6px;}
.btn-cancel{padding:10px 20px;border-radius:8px;border:1px solid var(--border-2);background:#fff;color:var(--text-2);font-size:0.85rem;font-weight:600;text-decoration:none;transition:all 0.15s;}
.btn-cancel:hover{border-color:var(--primary);color:var(--primary);}
</style>
</head>
<body>
<?php adminSidebar('exams'); ?>
<main class="main">
  <div class="breadcrumb"><a href="exams.php">Exams</a> / New</div>
  <div class="page-header"><div class="page-title">Create New Exam</div></div>
  <?php if ($error): ?><div class="flash flash-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <div class="form-card">
    <form method="POST">
      <div class="form-group"><label>Exam Title *</label><input type="text" name="title" value="<?= htmlspecialchars($_POST['title']??'') ?>" placeholder="e.g. English Assessment Test" required autofocus></div>
      <div class="form-group"><label>Description</label><textarea name="description" rows="3" placeholder="Brief description..."><?= htmlspecialchars($_POST['description']??'') ?></textarea></div>
      <div class="form-row form-row-2">
        <div class="form-group"><label>Duration (minutes)</label><input type="number" name="duration_minutes" value="<?= htmlspecialchars($_POST['duration_minutes']??'30') ?>" min="1" max="300"></div>
        <div class="form-group"><label>Status</label><div class="toggle-wrap" style="margin-top:8px"><input type="checkbox" name="is_active" id="active" checked><label for="active" style="margin:0;cursor:pointer;font-weight:400;font-size:0.85rem">Active (students can take this)</label></div></div>
      </div>
      <div class="btn-actions">
        <button type="submit" class="btn-primary">Create &amp; Add Questions →</button>
        <a href="exams.php" class="btn-cancel">Cancel</a>
      </div>
    </form>
  </div>
</main>
</body>
</html>
