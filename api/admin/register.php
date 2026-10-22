<?php
/**
 * Admin Registration — Secret URL
 * This page is intentionally NOT linked from anywhere public.
 * Access it directly: /assessment/admin/register.php
 */
session_start();
require_once '../includes/config.php';
if (isset($_SESSION['admin_id'])) { header('Location: dashboard.php'); exit; }
$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $secret   = trim($_POST['secret_key'] ?? '');
    define('ADMIN_SECRET', 'EduAssess2024');
    if (!$username || !$password || !$confirm || !$secret) { $error = 'All fields are required.'; }
    elseif (strlen($username) < 3) { $error = 'Username must be at least 3 characters.'; }
    elseif (strlen($password) < 6) { $error = 'Password must be at least 6 characters.'; }
    elseif ($password !== $confirm) { $error = 'Passwords do not match.'; }
    elseif ($secret !== ADMIN_SECRET) { $error = 'Invalid admin secret key.'; }
    else {
        $db = getDB();
        $check = $db->prepare("SELECT id FROM admins WHERE username=?"); $check->execute([$username]);
        if ($check->fetch()) { $error = 'Username already taken.'; }
        else { $db->prepare("INSERT INTO admins (username,password) VALUES (?,?)")->execute([$username, password_hash($password, PASSWORD_DEFAULT)]); $success = 'Account created! You can now log in.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Admin Register — Southern Baptist College</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<style>
  :root{--primary:#4f46e5;--primary-light:#ede9fe;--bg:#f5f6fa;--text:#0f172a;--text-2:#475569;--text-3:#94a3b8;--border:#e2e8f0;--border-2:#cbd5e1;--danger:#dc2626;--danger-bg:#fee2e2;--success:#16a34a;--success-bg:#dcfce7;}
  *{margin:0;padding:0;box-sizing:border-box;}
  body{font-family:'Inter',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
  .card{background:#fff;border:1px solid var(--border);border-radius:16px;padding:40px;width:100%;max-width:440px;box-shadow:0 4px 24px rgba(0,0,0,0.06);}
  .back{display:inline-flex;align-items:center;gap:4px;color:var(--text-3);text-decoration:none;font-size:0.82rem;margin-bottom:24px;}
  .back:hover{color:var(--primary);}
  .logo-row{display:flex;align-items:center;gap:8px;margin-bottom:6px;}
  .logo-icon{width:30px;height:30px;background:var(--primary);border-radius:7px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.68rem;color:#fff;}
  h2{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.4rem;font-weight:800;margin-bottom:4px;}
  .sub{color:var(--text-3);font-size:0.83rem;margin-bottom:24px;line-height:1.5;}
  .form-group{margin-bottom:14px;}
  label{display:block;font-size:0.79rem;font-weight:600;color:var(--text-2);margin-bottom:5px;}
  input{width:100%;padding:10px 12px;background:#fff;border:1px solid var(--border-2);border-radius:8px;color:var(--text);font-size:0.87rem;font-family:'Inter',sans-serif;outline:none;transition:border-color 0.15s,box-shadow 0.15s;}
  input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(79,70,229,0.1);}
  .error{background:var(--danger-bg);border:1px solid #fecaca;color:var(--danger);padding:10px 13px;border-radius:8px;font-size:0.82rem;margin-bottom:14px;}
  .success{background:var(--success-bg);border:1px solid #bbf7d0;color:var(--success);padding:12px 14px;border-radius:8px;font-size:0.84rem;margin-bottom:14px;}
  .secret-note{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 13px;font-size:0.78rem;color:#92400e;margin-bottom:14px;line-height:1.5;}
  .secret-note code{background:#fef3c7;padding:1px 5px;border-radius:3px;font-weight:600;}
  .btn{width:100%;padding:11px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-size:0.88rem;font-weight:700;cursor:pointer;transition:background 0.15s;margin-top:4px;}
  .btn:hover{background:#3730a3;}
  .link-row{text-align:center;margin-top:16px;padding-top:16px;border-top:1px solid var(--border);font-size:0.82rem;color:var(--text-3);}
  .link-row a{color:var(--primary);font-weight:600;text-decoration:none;}
  .hint{font-size:0.74rem;color:var(--text-3);margin-top:4px;}
  .strength-bar{height:3px;background:var(--border);border-radius:99px;margin-top:6px;overflow:hidden;}
  .strength-fill{height:100%;border-radius:99px;transition:all 0.3s;width:0;}
</style>
</head>
<body>
<div class="card">
  <a href="login.php" class="back">← Back to Login</a>
  <div class="logo-row"><div class="logo-icon">EA</div><span style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:0.95rem">EduAssess</span></div>
  <h2>Create Admin Account</h2>
  <p class="sub">Register a new administrator account. You need the admin secret key.</p>
  <?php if ($error): ?><div class="error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success">✅ <?= htmlspecialchars($success) ?> <a href="login.php" style="color:var(--success);font-weight:700">Login →</a></div><?php endif; ?>
  <?php if (!$success): ?>
  <form method="POST">
    <div class="form-group"><label>Username</label><input type="text" name="username" value="<?= htmlspecialchars($_POST['username']??'') ?>" placeholder="Choose a username" required autofocus><div class="hint">At least 3 characters.</div></div>
    <div class="form-group"><label>Password</label><input type="password" name="password" placeholder="Create a password" required oninput="checkStr(this.value)"><div class="strength-bar"><div class="strength-fill" id="sf"></div></div></div>
    <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" placeholder="Re-enter password" required></div>
    <div class="secret-note">🔑 An <strong>Admin Secret Key</strong> is required. Default for testing: <code>Southern Baptist College</code></div>
    <div class="form-group"><label>Admin Secret Key</label><input type="password" name="secret_key" placeholder="Enter the secret key" required></div>
    <button type="submit" class="btn">Create Account →</button>
  </form>
  <?php endif; ?>
  <div class="link-row">Already have an account? <a href="login.php">Sign in</a></div>
</div>
<script>
function checkStr(v){const f=document.getElementById('sf');let s=0;if(v.length>=6)s++;if(v.length>=10)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;const c=['','#dc2626','#d97706','#eab308','#16a34a','#16a34a'][s]||'';const w=[0,20,40,65,85,100][s]||0;f.style.width=w+'%';f.style.background=c;}
</script>
</body>
</html>
