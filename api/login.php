<?php
session_start();
require_once 'includes/config.php';

// Already logged in?
if (isset($_SESSION['admin_id'])) {
  header('Location: admin/dashboard.php');
  exit;
}
if (isset($_SESSION['student_id'])) {
  header('Location: student/dashboard.php');
  exit;
}

$error = '';
$role = $_POST['role'] ?? ($_GET['role'] ?? 'student');
if (!in_array($role, ['student', 'admin']))
  $role = 'student';

// Registration handling
$reg_error = '';
$reg_success = '';
$show_registration = isset($_GET['register']) ? true : false;

// File upload configuration
$upload_dir = 'uploads/payments/';
$allowed_image_types = ['image/jpeg', 'image/jpg', 'image/png'];
$max_file_size = 5 * 1024 * 1024; // 5MB

// Create upload directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_submit'])) {
    $db = getDB();
    
    // Get all form fields
    $name = trim($_POST['reg_name'] ?? '');
    $lname = trim($_POST['reg_lname'] ?? '');
    $mname = trim($_POST['reg_mname'] ?? '');
    $sid  = strtoupper(trim($_POST['reg_student_id'] ?? ''));
    $age = intval($_POST['reg_age'] ?? 0);
    $address = trim($_POST['reg_address'] ?? '');
    $sex = $_POST['reg_sex'] ?? '';
    $email = trim($_POST['reg_email'] ?? '');
    $pass = $_POST['reg_password'] ?? '';
    $conf = $_POST['reg_confirm_password'] ?? '';
    
    // Validate required fields
    $errors = [];
    
    if (!$name) $errors[] = 'First name is required.';
    if (!$lname) $errors[] = 'Last name is required.';
    if (!$sid) $errors[] = 'Student ID is required.';
    if (!$age || $age < 1 || $age > 100) $errors[] = 'Valid age is required.';
    if (!$address) $errors[] = 'Address is required.';
    if (!$sex) $errors[] = 'Sex is required.';
    if (!$email) $errors[] = 'Email is required.';
    if (!$pass) $errors[] = 'Password is required.';
    
    // Validate formats
    if (empty($errors)) {
        if (strlen($name) < 2) $errors[] = 'Please enter a valid first name.';
        if (strlen($lname) < 2) $errors[] = 'Please enter a valid last name.';
        if (!preg_match('/^[A-Za-z0-9\-_]+$/', $sid)) $errors[] = 'Student ID: letters, numbers, hyphens only.'; 
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        
        if (strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($pass !== $conf) $errors[] = 'Passwords do not match.';
        
        // Validate receipt proof (required)
        if (isset($_FILES['reg_receipt_proof']) && $_FILES['reg_receipt_proof']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['reg_receipt_proof'];
            $file_type = $file['type'];
            $file_size = $file['size'];
            
            // Check file type (images only)
            if (!in_array($file_type, $allowed_image_types)) {
                $errors[] = 'Receipt proof must be JPG or PNG.';
            }
            
            // Check file size
            if ($file_size > $max_file_size) {
                $errors[] = 'Receipt proof file size must be less than 5MB.';
            }
            
            // Generate unique filename
            if (empty($errors)) {
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'receipt_' . $sid . '_' . time() . '.' . $extension;
                $receipt_photo_path = $upload_dir . $filename;
            }
        } else {
            $errors[] = 'Receipt proof is required.';
        }
        
        // Check for existing records
        if (empty($errors)) {
            $check_sid = $db->prepare("SELECT id FROM students WHERE student_id=?");
            $check_sid->execute([$sid]);
            
            $check_email = $db->prepare("SELECT id FROM students WHERE email=?");
            $check_email->execute([$email]);
            
            if ($check_sid->fetch()) { 
                $errors[] = 'That Student ID is already registered.'; 
            }
            elseif ($check_email->fetch()) {
                $errors[] = 'That email address is already registered.';
            }
        }
    }
    
    // If no errors, upload file and insert the record
    if (empty($errors)) {
        try {
            // Upload receipt proof
            if (move_uploaded_file($_FILES['reg_receipt_proof']['tmp_name'], $receipt_photo_path)) {
                
                // DB column remains `payment_photo` — only the form field/label changed to "receipt"
                $sql = "INSERT INTO students (
                    name, lname, mname, student_id, age, address, sex, email, 
                    payment_photo, password, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
                
                $db->prepare($sql)->execute([
                    $name,
                    $lname,
                    $mname ?: null,
                    $sid,
                    $age,
                    $address,
                    $sex,
                    $email,
                    $receipt_photo_path,
                    password_hash($pass, PASSWORD_DEFAULT)
                ]); 
                
                $reg_success = "Account created successfully! Your account is pending approval. You will be able to log in once an admin approves your registration.";
                $role = 'student';
                $show_registration = false;
            } else {
                $errors[] = 'Failed to upload receipt proof.';
                $reg_error = implode(' ', $errors);
            }
            
        } catch (PDOException $e) {
            $reg_error = 'Registration failed. Please try again.';
            // Delete uploaded file if database insert fails
            if (isset($receipt_photo_path) && file_exists($receipt_photo_path)) {
                unlink($receipt_photo_path);
            }
        }
    } else {
        $reg_error = implode(' ', $errors);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['register_submit'])) {
  $db = getDB();
  $pass = $_POST['password'] ?? '';

  if ($role === 'admin') {
    $username = trim($_POST['username'] ?? '');
    $stmt = $db->prepare("SELECT * FROM admins WHERE username=?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($pass, $admin['password'])) {
      $_SESSION['admin_id'] = $admin['id'];
      $_SESSION['admin_name'] = $admin['username'];
      header('Location: admin/dashboard.php');
      exit;
    }
    $error = 'Invalid username or password.';
  } else {
    $sid = strtoupper(trim($_POST['student_id'] ?? ''));
    $stmt = $db->prepare("SELECT * FROM students WHERE student_id=?");
    $stmt->execute([$sid]);
    $student = $stmt->fetch();
    
    if ($student && password_verify($pass, $student['password'])) {
        // Check if account is approved
        if ($student['status'] !== 'Active') {
            $error = 'Your account is ' . $student['status'] . '. Please wait for admin approval.';
        } else {
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['student_name'] = $student['name'];
            $_SESSION['student_lname'] = $student['lname'];
            $_SESSION['student_sid'] = $student['student_id'];
            $_SESSION['student_email'] = $student['email'];
            header('Location: student/dashboard.php');
            exit;
        }
    } else {
        $error = 'Invalid Student ID or password.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sign In — Southern Baptist College</title>
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root {
      --primary: #4f46e5;
      --primary-d: #3730a3;
      --primary-light: #ede9fe;
      --sky: #0ea5e9;
      --sky-d: #0284c7;
      --sky-light: #e0f2fe;
      --bg: #f8f9fc;
      --white: #fff;
      --text: #0f172a;
      --text-2: #475569;
      --text-3: #94a3b8;
      --border: #e2e8f0;
      --border-2: #cbd5e1;
      --danger: #dc2626;
      --danger-bg: #fee2e2;
      --success: #16a34a;
      --success-bg: #dcfce7;
      --warning: #d97706;
      --warning-bg: #fef3c7;
      --shadow: 0 4px 24px rgba(0, 0, 0, 0.07);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── SPLIT LAYOUT ── */
    .layout {
      flex: 1;
      display: flex;
      min-height: 100vh;
      position: relative;
      overflow: hidden;
    }

    /* ── LEFT PANELS CONTAINER (fixed width) ── */
    .left-panels-container {
      position: relative;
      width: 500px;
      height: 100vh;
      flex-shrink: 0;
      overflow: hidden;
    }

    /* ── LEFT: Login form ── */
    .left-panel {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: #fff;
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      padding: 40px 44px;
      transition: transform 0.5s ease-in-out;
      z-index: 2;
      transform: translateX(0);
      overflow-y: auto;
    }

    .left-panel.register-active {
      transform: translateX(-100%);
    }

    /* ── Registration Panel ── */
    .registration-panel {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: #fff;
      border-right: 1px solid var(--border);
      padding: 30px 44px;
      transition: transform 0.5s ease-in-out;
      transform: translateX(100%);
      z-index: 1;
      overflow-y: auto;
    }

    .registration-panel.active {
      transform: translateX(0);
      z-index: 3;
    }

    .right-panel {
      flex: 1;
      background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #1e40af 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 48px;
      position: relative;
      overflow: hidden;
      min-height: 100vh;
    }

    @keyframes slideInLeft {
      from {
        opacity: 0;
        transform: translateX(-30px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes slideInRight {
      from {
        opacity: 0;
        transform: translateX(30px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes pulse {
      0% {
        transform: scale(1);
      }
      50% {
        transform: scale(1.05);
      }
      100% {
        transform: scale(1);
      }
    }

    @keyframes float {
      0% {
        transform: translateY(0px);
      }
      50% {
        transform: translateY(-10px);
      }
      100% {
        transform: translateY(0px);
      }
    }

    @media(max-width:720px) {
      .layout {
        flex-direction: column;
      }

      .left-panels-container {
        width: 100%;
        height: auto;
        min-height: 100vh;
      }

      .left-panel, .registration-panel {
        position: relative;
        width: 100%;
        height: auto;
        min-height: 100vh;
      }

      .right-panel {
        min-height: auto;
      }
    }

    /* ── LEFT: FORM ── */
    .logo-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 30px;
      animation: fadeInUp 0.5s ease-out;
    }

    .logo-icon {
      width: 34px;
      height: 34px;
      background: var(--primary);
      border-radius: 9px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 0.72rem;
      color: #fff;
    }

    .logo-text {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-weight: 800;
      font-size: 1rem;
      color: var(--text);
    }

    /* Role toggle */
    .role-toggle {
      display: grid;
      grid-template-columns: 1fr 1fr;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 4px;
      margin-bottom: 28px;
      animation: fadeInUp 0.5s ease-out 0.1s both;
    }

    .role-btn {
      padding: 9px;
      border-radius: 7px;
      border: none;
      background: transparent;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-weight: 700;
      font-size: 0.82rem;
      cursor: pointer;
      color: var(--text-3);
      transition: all .15s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .role-btn i {
      font-size: 0.9rem;
    }

    .role-btn.student.active {
      background: var(--sky);
      color: #fff;
      transform: scale(1.02);
    }

    .role-btn.admin.active {
      background: var(--primary);
      color: #fff;
      transform: scale(1.02);
    }

    .form-heading {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 1.55rem;
      font-weight: 800;
      color: var(--text);
      margin-bottom: 4px;
      animation: fadeInUp 0.5s ease-out 0.2s both;
    }

    .form-sub {
      color: var(--text-3);
      font-size: 0.84rem;
      margin-bottom: 26px;
      animation: fadeInUp 0.5s ease-out 0.3s both;
    }

    .form-group {
      margin-bottom: 15px;
      animation: fadeInUp 0.5s ease-out 0.4s both;
    }

    label {
      display: block;
      font-size: 0.78rem;
      font-weight: 700;
      color: var(--text-2);
      margin-bottom: 5px;
      text-transform: uppercase;
      letter-spacing: .3px;
    }

    label i {
      margin-right: 5px;
      color: var(--sky);
    }

    .required:after {
      content: " *";
      color: var(--danger);
    }

    input[type=text],
    input[type=password],
    input[type=email],
    input[type=number],
    select,
    textarea,
    input[type=file] {
      width: 100%;
      padding: 11px 13px;
      background: #fff;
      border: 1px solid var(--border-2);
      border-radius: 9px;
      color: var(--text);
      font-size: 0.9rem;
      font-family: 'Inter', sans-serif;
      outline: none;
      transition: border-color .15s, box-shadow .15s, transform .15s;
    }

    input[type=file] {
      padding: 8px 13px;
      background: var(--bg);
    }

    textarea {
      min-height: 80px;
      resize: vertical;
    }

    input:focus, select:focus, textarea:focus, input[type=file]:focus {
      border-color: var(--focus-color, var(--sky));
      box-shadow: 0 0 0 3px var(--focus-glow, rgba(14, 165, 233, .1));
      transform: translateY(-2px);
    }

    .btn-login {
      width: 100%;
      padding: 12px;
      border: none;
      border-radius: 9px;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 0.92rem;
      font-weight: 800;
      color: #fff;
      cursor: pointer;
      transition: all .15s;
      margin-top: 6px;
      animation: fadeInUp 0.5s ease-out 0.5s both;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }

    .btn-login:active {
      transform: translateY(0);
    }

    .btn-student {
      background: var(--sky);
    }

    .btn-student:hover {
      background: var(--sky-d);
    }

    .btn-admin {
      background: var(--primary);
    }

    .btn-admin:hover {
      background: var(--primary-d);
    }

    .btn-register {
      width: 100%;
      padding: 12px;
      border: 2px solid var(--sky);
      border-radius: 9px;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 0.92rem;
      font-weight: 800;
      color: var(--sky);
      background: transparent;
      cursor: pointer;
      transition: all .15s;
      margin-top: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn-register:hover {
      background: var(--sky);
      color: #fff;
      transform: translateY(-2px);
    }

    .btn-back {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      border: 1px solid var(--border);
      border-radius: 8px;
      background: transparent;
      color: var(--text-2);
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      margin-bottom: 20px;
      text-decoration: none;
    }

    .btn-back:hover {
      background: var(--bg);
      border-color: var(--primary);
      color: var(--primary);
    }

    .error-box {
      background: var(--danger-bg);
      border: 1px solid #fecaca;
      color: var(--danger);
      padding: 10px 13px;
      border-radius: 8px;
      font-size: 0.82rem;
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      gap: 7px;
      animation: shake 0.5s ease-in-out;
    }

    .success-box {
      background: var(--success-bg);
      border: 1px solid #bbf7d0;
      color: var(--success);
      padding: 10px 13px;
      border-radius: 8px;
      font-size: 0.82rem;
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      gap: 7px;
    }

    .warning-box {
      background: var(--warning-bg);
      border: 1px solid #fde68a;
      color: var(--warning);
      padding: 10px 13px;
      border-radius: 8px;
      font-size: 0.82rem;
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      gap: 7px;
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
      20%, 40%, 60%, 80% { transform: translateX(5px); }
    }

    .register-row {
      margin-top: 20px;
      padding-top: 18px;
      border-top: 1px solid var(--border);
      text-align: center;
      font-size: 0.83rem;
      color: var(--text-3);
      animation: fadeInUp 0.5s ease-out 0.7s both;
    }

    .register-row a {
      font-weight: 700;
      text-decoration: none;
      transition: all 0.2s;
    }

    .register-row a:hover {
      transform: translateX(5px);
    }

    .register-row a.student-link {
      color: var(--sky);
    }

    /* Registration form specific */
    .hint {
      font-size: 0.74rem;
      color: var(--text-3);
      margin-top: 4px;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .strength-bar {
      height: 3px;
      background: var(--border);
      border-radius: 99px;
      margin-top: 6px;
      overflow: hidden;
    }

    .strength-fill {
      height: 100%;
      border-radius: 99px;
      transition: all 0.3s;
      width: 0;
    }

    .match-hint {
      font-size: 0.74rem;
      margin-top: 4px;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .file-name {
      font-size: 0.8rem;
      margin-top: 5px;
      color: var(--text-2);
    }

    /* ── RIGHT: HERO ── */
    .right-panel::before {
      content: '';
      position: absolute;
      width: 400px;
      height: 400px;
      border-radius: 50%;
      background: rgba(255, 255, 255, .04);
      top: -80px;
      right: -80px;
      animation: float 8s ease-in-out infinite;
    }

    .right-panel::after {
      content: '';
      position: absolute;
      width: 300px;
      height: 300px;
      border-radius: 50%;
      background: rgba(255, 255, 255, .04);
      bottom: -60px;
      left: -60px;
      animation: float 6s ease-in-out infinite reverse;
    }

    .hero-badge {
      background: rgba(255, 255, 255, .12);
      border: 1px solid rgba(255, 255, 255, .18);
      color: #fff;
      padding: 5px 14px;
      border-radius: 999px;
      font-size: 0.73rem;
      font-weight: 700;
      letter-spacing: .5px;
      margin-bottom: 22px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      animation: fadeInUp 0.5s ease-out, float 4s ease-in-out infinite;
    }

    .hero-title {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 2.4rem;
      font-weight: 800;
      color: #fff;
      line-height: 1.2;
      margin-bottom: 14px;
      text-align: center;
      animation: fadeInUp 0.5s ease-out 0.1s both;
    }

    .hero-title span {
      color: #a5b4fc;
      display: inline-block;
      animation: pulse 2s ease-in-out infinite;
    }

    .hero-desc {
      color: rgba(255, 255, 255, .65);
      font-size: 0.9rem;
      line-height: 1.7;
      text-align: center;
      max-width: 360px;
      margin-bottom: 36px;
      animation: fadeInUp 0.5s ease-out 0.2s both;
    }

    .features {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      width: 100%;
      max-width: 380px;
    }

    .feat {
      background: rgba(255, 255, 255, .08);
      border: 1px solid rgba(255, 255, 255, .1);
      border-radius: 12px;
      padding: 14px 16px;
      display: flex;
      align-items: flex-start;
      gap: 10px;
      transition: all 0.3s;
      animation: fadeInUp 0.5s ease-out calc(0.3s + var(--delay, 0s)) both;
    }

    .feat:nth-child(1) { --delay: 0.1s; }
    .feat:nth-child(2) { --delay: 0.2s; }
    .feat:nth-child(3) { --delay: 0.3s; }
    .feat:nth-child(4) { --delay: 0.4s; }
    .feat:nth-child(5) { --delay: 0.5s; }
    .feat:nth-child(6) { --delay: 0.6s; }

    .feat:hover {
      transform: translateY(-5px);
      background: rgba(255, 255, 255, .12);
      border-color: rgba(255, 255, 255, .2);
    }

    .feat-icon {
      font-size: 1.2rem;
      flex-shrink: 0;
      color: #a5b4fc;
      animation: pulse 2s ease-in-out infinite;
    }

    .feat-label {
      font-size: 0.78rem;
      font-weight: 600;
      color: #fff;
      margin-bottom: 2px;
    }

    .feat-desc {
      font-size: 0.7rem;
      color: rgba(255, 255, 255, .5);
    }
  </style>
</head>

<body>
  <div class="layout">
    
    <!-- ── LEFT PANELS CONTAINER (fixed width) ── -->
    <div class="left-panels-container">
      
      <!-- ── Login form ── -->
      <div class="left-panel <?= $show_registration ? 'register-active' : '' ?>">
        <div class="logo-row" style="display: flex; align-items: center; gap: 12px; margin-bottom: 40px;">
          <img src="./uploads/malinaw.jpg" alt="College Logo" style="width: auto; height: 70px; display: block; animation: float 4s ease-in-out infinite;">
          <span class="logo-text" style="font-size: 1.5rem; font-weight: 500; color: #1c3b4f;">Southern Baptist College</span>
        </div>

        <!-- Role toggle -->
        <div class="role-toggle">
          <button type="button" class="role-btn student <?= $role === 'student' ? 'active' : '' ?>"
            onclick="switchRole('student')"><i class="fa-solid fa-graduation-cap"></i> Student</button>
          <button type="button" class="role-btn admin <?= $role === 'admin' ? 'active' : '' ?>" onclick="switchRole('admin')"><i class="fa-solid fa-shield-halved"></i> Admin</button>
        </div>

        <?php if ($role === 'student'): ?>
          <div class="form-heading"><i class="fa-solid fa-hand-wave" style="color: var(--sky); margin-right: 8px;"></i>Welcome back!</div>
          <div class="form-sub"><i class="fa-regular fa-circle-check" style="color: var(--sky);"></i> Sign in with your Student ID to take exams.</div>
        <?php else: ?>
          <div class="form-heading"><i class="fa-solid fa-lock" style="color: var(--primary); margin-right: 8px;"></i>Admin Access</div>
          <div class="form-sub"><i class="fa-regular fa-circle-check" style="color: var(--primary);"></i> Sign in to manage exams and students.</div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="error-box"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($reg_success): ?>
          <div class="success-box"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($reg_success) ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
          <input type="hidden" name="role" value="<?= $role ?>">

          <?php if ($role === 'student'): ?>
            <div class="form-group" style="--focus-color:var(--sky);--focus-glow:rgba(14,165,233,.12)">
              <label><i class="fa-solid fa-id-card"></i> Student ID</label>
              <input type="text" name="student_id" placeholder="e.g. STU001"
                value="<?= htmlspecialchars(strtoupper($_POST['student_id'] ?? '')) ?>" required autofocus
                autocomplete="username" oninput="this.value=this.value.toUpperCase()">
            </div>
          <?php else: ?>
            <div class="form-group">
              <label><i class="fa-solid fa-user"></i> Username</label>
              <input type="text" name="username" placeholder="admin" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                required autofocus autocomplete="username">
            </div>
          <?php endif; ?>

          <div class="form-group">
            <label><i class="fa-solid fa-key"></i> Password</label>
            <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
          </div>

          <button type="submit" class="btn-login <?= $role === 'student' ? 'btn-student' : 'btn-admin' ?>">
            Sign In <i class="fa-solid fa-arrow-right"></i>
          </button>
        </form>

        <?php if ($role === 'student'): ?>
          <div class="register-row">
            <i class="fa-regular fa-circle-question"></i> Don't have an account?
            <a href="javascript:void(0)" onclick="showRegistration()" class="student-link">Register as Student <i class="fa-solid fa-arrow-right"></i></a>
          </div>
          <button onclick="showRegistration()" class="btn-register">
            <i class="fa-regular fa-pen-to-square"></i> Create New Account
          </button>
        <?php endif; ?>
      </div>

      <!-- ── Registration Panel ── -->
      <div class="registration-panel <?= $show_registration ? 'active' : '' ?>">
        <button onclick="hideRegistration()" class="btn-back">
          <i class="fa-solid fa-arrow-left"></i> Back to Login
        </button>

        <div class="logo-row" style="margin-bottom: 20px;">
          <div class="logo-icon"><i class="fa-solid fa-building-columns"></i></div>
          <span style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:0.95rem">Southern Baptist College</span>
        </div>

        <h2 style="font-family:'Plus Jakarta Sans',sans-serif; font-size:1.4rem; font-weight:800; margin-bottom:4px;">Create Student Account</h2>
        <p style="color:var(--text-3); font-size:0.83rem; margin-bottom:20px;">Fill in your details to register. Account will be pending admin approval.</p>

        <?php if ($reg_error): ?>
          <div class="error-box"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($reg_error) ?></div>
        <?php endif; ?>

        <?php if ($reg_success): ?>
          <div class="warning-box"><i class="fa-solid fa-clock"></i> <?= htmlspecialchars($reg_success) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="register_submit" value="1">
          
          <!-- Name Fields (3 columns) -->
          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 15px;">
            <div class="form-group" style="margin-bottom: 0;">
              <label class="required"><i class="fa-regular fa-user"></i> First Name</label>
              <input type="text" name="reg_name" value="<?= htmlspecialchars($_POST['reg_name'] ?? '') ?>" placeholder="Juan" required>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
              <label><i class="fa-regular fa-user"></i> Middle Name</label>
              <input type="text" name="reg_mname" value="<?= htmlspecialchars($_POST['reg_mname'] ?? '') ?>" placeholder="Dela Cruz">
              <div class="hint">Optional</div>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
              <label class="required"><i class="fa-regular fa-user"></i> Last Name</label>
              <input type="text" name="reg_lname" value="<?= htmlspecialchars($_POST['reg_lname'] ?? '') ?>" placeholder="Dela Cruz" required>
            </div>
          </div>
          
          <!-- Student ID and Age -->
          <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px; margin-bottom: 15px;">
            <div class="form-group" style="margin-bottom: 0;">
              <label class="required"><i class="fa-solid fa-id-card"></i> Student ID</label>
              <input type="text" name="reg_student_id" value="<?= htmlspecialchars($_POST['reg_student_id'] ?? '') ?>" placeholder="e.g. STU001" required oninput="this.value=this.value.toUpperCase()">
              <div class="hint"><i class="fa-regular fa-lightbulb"></i> Letters, numbers, hyphens only</div>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
              <label class="required"><i class="fa-solid fa-cake-candles"></i> Age</label>
              <input type="number" name="reg_age" value="<?= htmlspecialchars($_POST['reg_age'] ?? '') ?>" placeholder="18" min="1" max="100" required>
            </div>
          </div>
          
          <!-- Address -->
          <div class="form-group">
            <label class="required"><i class="fa-solid fa-location-dot"></i> Address</label>
            <textarea name="reg_address" placeholder="Complete address" required><?= htmlspecialchars($_POST['reg_address'] ?? '') ?></textarea>
          </div>
          
          <!-- Sex and Email -->
          <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 10px; margin-bottom: 15px;">
            <div class="form-group" style="margin-bottom: 0;">
              <label class="required"><i class="fa-solid fa-venus-mars"></i> Sex</label>
              <select name="reg_sex" required>
                <option value="">Select</option>
                <option value="Male" <?= (($_POST['reg_sex'] ?? '') == 'Male') ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= (($_POST['reg_sex'] ?? '') == 'Female') ? 'selected' : '' ?>>Female</option>
                <option value="Other" <?= (($_POST['reg_sex'] ?? '') == 'Other') ? 'selected' : '' ?>>Other</option>
              </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
              <label class="required"><i class="fa-regular fa-envelope"></i> Email</label>
              <input type="email" name="reg_email" value="<?= htmlspecialchars($_POST['reg_email'] ?? '') ?>" placeholder="juan@example.com" required>
            </div>
          </div>
          
          <!-- Receipt Proof Upload -->
          <div class="form-group">
            <label class="required"><i class="fa-solid fa-receipt"></i> Receipt Proof</label>
            <input type="file" name="reg_receipt_proof" id="receipt_proof" accept="image/jpeg,image/jpg,image/png" required onchange="updateFileName(this)">
            <div class="hint"><i class="fa-regular fa-image"></i> Upload a photo/screenshot of your receipt (JPG or PNG, max 5MB)</div>
            <div class="file-name" id="file_name"></div>
          </div>
          
          <!-- Password Fields -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
            <div class="form-group" style="margin-bottom: 0;">
              <label class="required"><i class="fa-solid fa-lock"></i> Password</label>
              <input type="password" name="reg_password" id="reg_password" placeholder="At least 6 characters" required oninput="checkStrength(this.value); checkMatch()">
              <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
              <label class="required"><i class="fa-solid fa-lock"></i> Confirm</label>
              <input type="password" name="reg_confirm_password" id="reg_confirm" placeholder="Re-enter password" required oninput="checkMatch()">
              <div class="match-hint" id="matchHint"></div>
            </div>
          </div>
          
          <div class="warning-box" style="margin-bottom: 15px;">
            <i class="fa-solid fa-clock"></i> After registration, your account will be pending approval. You'll be able to log in once an admin activates your account.
          </div>
          
          <button type="submit" class="btn-login btn-student">
            <i class="fa-regular fa-pen-to-square"></i> Register Now <i class="fa-solid fa-arrow-right"></i>
          </button>
        </form>

        <div style="text-align:center; margin-top:16px; padding-top:16px; border-top:1px solid var(--border); font-size:0.82rem; color:var(--text-3);">
          Already have an account? <a href="javascript:void(0)" onclick="hideRegistration()" style="color:var(--sky); font-weight:600; text-decoration:none;">Sign in</a>
        </div>
      </div>
    </div>

    <!-- ── RIGHT: Hero panel ── -->
    <div class="right-panel">
      <span class="hero-badge"><i class="fa-solid fa-laptop"></i> Online Assessment Platform</span>
      <div class="hero-title">Learn, Test,<br>and <span>Grow</span></div>
      <div class="hero-desc"><i class="fa-regular fa-star" style="margin-right: 5px;"></i> A complete exam system with multiple question types, audio spelling, video listening comprehension, essay grading, and real-time analytics.</div>
      <div class="features">
        <div class="feat">
          <div class="feat-icon"><i class="fa-regular fa-circle-dot"></i></div>
          <div>
            <div class="feat-label">Multiple Choice</div>
            <div class="feat-desc">Auto-graded instantly</div>
          </div>
        </div>
        <div class="feat">
          <div class="feat-icon"><i class="fa-solid fa-film"></i></div>
          <div>
            <div class="feat-label">Video Listening</div>
            <div class="feat-desc">Fill-in-the-blank</div>
          </div>
        </div>
        <div class="feat">
          <div class="feat-icon"><i class="fa-solid fa-headphones"></i></div>
          <div>
            <div class="feat-label">Audio Spelling</div>
            <div class="feat-desc">Listen & type</div>
          </div>
        </div>
        <div class="feat">
          <div class="feat-icon"><i class="fa-regular fa-pen-to-square"></i></div>
          <div>
            <div class="feat-label">Essay Grading</div>
            <div class="feat-desc">Manual teacher review</div>
          </div>
        </div>
        <div class="feat">
          <div class="feat-icon"><i class="fa-solid fa-chart-line"></i></div>
          <div>
            <div class="feat-label">Analytics</div>
            <div class="feat-desc">Charts & insights</div>
          </div>
        </div>
        <div class="feat">
          <div class="feat-icon"><i class="fa-solid fa-list"></i></div>
          <div>
            <div class="feat-label">Enumeration</div>
            <div class="feat-desc">Partial scoring</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function switchRole(r) {
      const url = new URL(window.location);
      url.searchParams.set('role', r);
      url.searchParams.delete('register');
      window.location = url;
    }

    function showRegistration() {
      const url = new URL(window.location);
      url.searchParams.set('register', '1');
      window.location = url;
    }

    function hideRegistration() {
      const url = new URL(window.location);
      url.searchParams.delete('register');
      window.location = url;
    }

    function updateFileName(input) {
      const fileName = input.files[0]?.name;
      const display = document.getElementById('file_name');
      if (fileName) {
        display.innerHTML = '<i class="fa-regular fa-circle-check" style="color:#16a34a"></i> Selected: ' + fileName;
      } else {
        display.innerHTML = '';
      }
    }

    function checkStrength(password) {
      const fill = document.getElementById('strengthFill');
      let strength = 0;
      
      if (password.length >= 6) strength++;
      if (password.length >= 10) strength++;
      if (/[A-Z]/.test(password)) strength++;
      if (/[0-9]/.test(password)) strength++;
      if (/[^A-Za-z0-9]/.test(password)) strength++;
      
      const widths = [0, 20, 40, 65, 85, 100];
      const colors = ['', '#dc2626', '#d97706', '#eab308', '#16a34a', '#16a34a'];
      
      fill.style.width = widths[strength] + '%';
      fill.style.background = colors[strength];
    }

    function checkMatch() {
      const password = document.getElementById('reg_password').value;
      const confirm = document.getElementById('reg_confirm').value;
      const hint = document.getElementById('matchHint');
      
      if (!confirm) {
        hint.innerHTML = '';
        return;
      }
      
      if (password === confirm) {
        hint.innerHTML = '<i class="fa-regular fa-circle-check" style="color:#16a34a"></i> <span style="color:#16a34a">Passwords match</span>';
      } else {
        hint.innerHTML = '<i class="fa-regular fa-circle-xmark" style="color:#dc2626"></i> <span style="color:#dc2626">Passwords do not match</span>';
      }
    }

    // Initialize password strength if there are values
    <?php if (!empty($_POST['reg_password'])): ?>
      window.addEventListener('load', function() {
        checkStrength('<?= addslashes($_POST['reg_password']) ?>');
        checkMatch();
      });
    <?php endif; ?>
  </script>
</body>
</html>