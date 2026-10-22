<?php
/**
 * Student Top Navigation Bar
 * Usage: require_once '../includes/student_sidebar.php'; studentNav('dashboard');
 * Active keys: dashboard, results
 */
function studentNav(string $active = ''): void { ?>
<nav class="student-nav">
  <div class="nav-inner">
  <div class="sidebar-logo" style="display: flex; align-items: center; gap: 12px; margin-bottom: 30px;">
  <!-- ==================== -->
  <!-- LOGO IMAGE: from root/includes/ back to root/uploads/ -->
  <!-- ==================== -->
  <img src="../uploads/malinaw.jpg" alt="College Logo" style="width: 40px; height: 40px; object-fit: contain; display: block; margin-top: 25px;">
  <span style="font-size: 1.2rem; font-weight: 500; gap: 5px; margin-top: 25px; color: #1c3b4f;">Southern Baptist College</span>
</div>
    <div class="nav-links">
      <a href="dashboard.php" class="snav-link <?= $active==='dashboard'?'snav-active':'' ?>">My Exams</a>
      <!-- <a href="my_results.php" class="snav-link <?= $active==='results'?'snav-active':'' ?>"> Results</a> -->
    </div>
    <div class="nav-right">
      <div class="student-chip">
        <div class="student-av"><?= strtoupper(substr($_SESSION['student_name']??'S',0,1)) ?></div>
        <div>
          <div class="student-nm"><?= htmlspecialchars($_SESSION['student_name']??'Student') ?></div>
          <div class="student-id-lbl"><?= htmlspecialchars($_SESSION['student_sid']??'') ?></div>
        </div>
      </div>
      <a href="logout.php" class="snav-logout">Logout</a>
    </div>
  </div>
</nav>
<style>
.student-nav{background:#fff;border-bottom:1px solid #e2e8f0;position:sticky;top:0;z-index:100;box-shadow:0 1px 3px rgba(0,0,0,.06);}
.nav-inner{max-width:1100px;margin:0 auto;padding:0 24px;height:56px;display:flex;align-items:center;gap:24px;}
.nav-logo{display:flex;align-items:center;gap:8px;text-decoration:none;margin-right:8px;}
.logo-icon-s{width:28px;height:28px;background:#0ea5e9;border-radius:7px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.65rem;color:#fff;flex-shrink:0;}
.logo-text-s{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:0.9rem;color:#0f172a;}
.nav-links{display:flex;align-items:center;gap:4px;flex:1;}
.snav-link{padding:6px 12px;border-radius:7px;text-decoration:none;font-size:0.82rem;font-weight:600;color:#64748b;transition:all .14s;}
.snav-link:hover{background:#f1f5f9;color:#0f172a;}
.snav-active{background:#e0f2fe;color:#0ea5e9;}
.nav-right{display:flex;align-items:center;gap:12px;margin-left:auto;}
.student-chip{display:flex;align-items:center;gap:8px;}
.student-av{width:28px;height:28px;border-radius:50%;background:#0ea5e9;color:#fff;font-size:0.7rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.student-nm{font-size:0.8rem;font-weight:600;color:#0f172a;line-height:1.2;}
.student-id-lbl{font-size:0.68rem;color:#94a3b8;}
.snav-logout{font-size:0.78rem;color:#94a3b8;text-decoration:none;padding:5px 10px;border:1px solid #e2e8f0;border-radius:6px;font-weight:500;}
.snav-logout:hover{color:#dc2626;border-color:#fecaca;background:#fee2e2;}
@media(max-width:600px){.nav-links{display:none;}.student-chip div{display:none;}}
</style>
<?php }
