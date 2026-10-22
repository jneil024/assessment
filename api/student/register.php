<?php
session_start();
require_once '../includes/config.php';
if (isset($_SESSION['student_id'])) { header('Location: dashboard.php'); exit; }
$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $sid  = strtoupper(trim($_POST['student_id'] ?? ''));
    $pass = $_POST['password'] ?? '';
    $conf = $_POST['confirm_password'] ?? '';
    if (!$name||!$sid||!$pass||!$conf) { $error = 'All fields are required.'; }
    elseif (strlen($name) < 2) { $error = 'Please enter your full name.'; }
    elseif (!preg_match('/^[A-Za-z0-9\-_]+$/', $sid)) { $error = 'Student ID: letters, numbers, hyphens only.'; }
    elseif (strlen($pass) < 6) { $error = 'Password must be at least 6 characters.'; }
    elseif ($pass !== $conf) { $error = 'Passwords do not match.'; }
    else {
        $db = getDB();
        $check = $db->prepare("SELECT id FROM students WHERE student_id=?"); $check->execute([$sid]);
        if ($check->fetch()) { $error = 'That Student ID is already registered.'; }
        else { $db->prepare("INSERT INTO students (name,student_id,password) VALUES (?,?,?)")->execute([$name,$sid,password_hash($pass,PASSWORD_DEFAULT)]); $success = "Account created for $name!"; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Southern Baptist College</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<style>
  :root{--primary:#0ea5e9;--primary-dark:#0284c7;--primary-light:#f0f9ff;--bg:#f5f6fa;--text:#0f172a;--text-2:#475569;--text-3:#94a3b8;--border:#e2e8f0;--border-2:#cbd5e1;--danger:#dc2626;--danger-bg:#fee2e2;--success:#16a34a;--success-bg:#dcfce7;}
  *{margin:0;padding:0;box-sizing:border-box;}
  body{font-family:'Inter',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
  .card{background:#fff;border:1px solid var(--border);border-radius:16px;padding:40px;width:100%;max-width:440px;box-shadow:0 4px 24px rgba(0,0,0,0.06);}
  .back{display:inline-flex;align-items:center;gap:4px;color:var(--text-3);text-decoration:none;font-size:0.82rem;margin-bottom:24px;}
  .back:hover{color:var(--primary);}
  .logo-row{display:flex;align-items:center;gap:8px;margin-bottom:6px;}
  .logo-icon{width:30px;height:30px;background:var(--primary);border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:1rem;}
  h2{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.4rem;font-weight:800;margin-bottom:4px;}
  .sub{color:var(--text-3);font-size:0.83rem;margin-bottom:24px;line-height:1.5;}
  .form-group{margin-bottom:14px;}
  label{display:block;font-size:0.79rem;font-weight:600;color:var(--text-2);margin-bottom:5px;}
  input{width:100%;padding:10px 12px;background:#fff;border:1px solid var(--border-2);border-radius:8px;color:var(--text);font-size:0.87rem;font-family:'Inter',sans-serif;outline:none;transition:border-color 0.15s,box-shadow 0.15s;}
  input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(14,165,233,0.12);}
  .error{background:var(--danger-bg);border:1px solid #fecaca;color:var(--danger);padding:10px 13px;border-radius:8px;font-size:0.82rem;margin-bottom:14px;}
  .success-box{background:var(--success-bg);border:1px solid #bbf7d0;color:var(--success);padding:16px;border-radius:10px;margin-bottom:14px;font-size:0.85rem;line-height:1.6;}
  .btn{width:100%;padding:11px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-size:0.88rem;font-weight:700;cursor:pointer;transition:background 0.15s;margin-top:4px;}
  .btn:hover{background:var(--primary-dark);}
  .btn-login{display:block;text-align:center;margin-top:10px;padding:11px;background:#fff;color:var(--primary);border:1px solid var(--primary);border-radius:8px;font-weight:700;font-size:0.88rem;text-decoration:none;}
  .link-row{text-align:center;margin-top:16px;padding-top:16px;border-top:1px solid var(--border);font-size:0.82rem;color:var(--text-3);}
  .link-row a{color:var(--primary);font-weight:600;text-decoration:none;}
  .hint{font-size:0.74rem;color:var(--text-3);margin-top:4px;}
  .strength-bar{height:3px;background:var(--border);border-radius:99px;margin-top:6px;overflow:hidden;}
  .strength-fill{height:100%;border-radius:99px;transition:all 0.3s;width:0;}
  .match-hint{font-size:0.74rem;margin-top:4px;}
</style>
</head>
<body>
<div class="card">
  <a href="../login.php?role=student" class="back">← Back to Login</a>
  <div class="logo-row"><div class="logo-icon">🎒</div><span style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:0.95rem">Southern Baptist College</span></div>
  <h2>Create Student Account</h2>
  <p class="sub">Register to access exams and track your progress.</p>
  <?php if ($error): ?><div class="error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?>
  <div class="success-box">✅ <?= htmlspecialchars($success) ?> You can now log in.</div>
  <a href="../login.php?role=student" class="btn-login">Go to Login →</a>
  <?php else: ?>
  <form method="POST">
    <div class="form-group"><label>Full Name</label><input type="text" name="name" value="<?= htmlspecialchars($_POST['name']??'') ?>" placeholder="Juan dela Cruz" required autofocus></div>
    <div class="form-group"><label>Student ID</label><input type="text" name="student_id" value="<?= htmlspecialchars($_POST['student_id']??'') ?>" placeholder="e.g. STU001" required oninput="this.value=this.value.toUpperCase()"><div class="hint">Letters, numbers, hyphens only. Saved in uppercase.</div></div>
    <div class="form-group"><label>Password</label><input type="password" name="password" placeholder="At least 6 characters" required oninput="checkStr(this.value);checkMatch()"><div class="strength-bar"><div class="strength-fill" id="sf"></div></div></div>
    <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" id="conf" placeholder="Re-enter password" required oninput="checkMatch()"><div class="match-hint" id="mh"></div></div>
    <button type="submit" class="btn">Register Now →</button>
  </form>
  <?php endif; ?>
  <div class="link-row">Already have an account? <a href="../login.php?role=student">Sign in</a></div>
</div>
<script>
function checkStr(v){const f=document.getElementById('sf');let s=0;if(v.length>=6)s++;if(v.length>=10)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;const c=['','#dc2626','#d97706','#eab308','#16a34a','#16a34a'][s]||'';f.style.width=[0,20,40,65,85,100][s]+'%';f.style.background=c;}
function checkMatch(){const p=document.querySelector('[name=password]').value,c=document.getElementById('conf').value,h=document.getElementById('mh');if(!c)return h.textContent='';if(c===p){h.textContent='✓ Passwords match';h.style.color='#16a34a';}else{h.textContent='✗ Does not match';h.style.color='#dc2626';}}
</script>
</body>
</html>
