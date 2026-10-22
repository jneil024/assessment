<?php
/**
 * Admin Sidebar
 * Usage: require_once '../includes/admin_sidebar.php'; adminSidebar('dashboard');
 * Active keys: dashboard, exams, students, results, essays
 */
function adminSidebar(string $active = ''): void { 
?>
<!-- Font Awesome is now loaded with proper integrity and fallback -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<!-- Fallback in case CDN fails -->
<script>
(function() {
    var link = document.querySelector('link[href*="font-awesome"]');
    if (!link) {
        var fallback = document.createElement('link');
        fallback.rel = 'stylesheet';
        fallback.href = 'https://use.fontawesome.com/releases/v6.0.0/css/all.css';
        document.head.appendChild(fallback);
    }
})();
</script>

<style>
.sidebar {
  width: 260px;
  background: white;
  border-right: 1px solid #d0e2ef;
  padding: 30px 20px;
  display: flex;
  flex-direction: column;
  height: 100vh;
  position: sticky;
  top: 0;
  overflow-y: auto;
}

.sidebar-logo {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 40px;
  padding-bottom: 20px;
  border-bottom: 1px solid #d0e2ef;
}

.logo-icon {
  width: 40px;
  height: 40px;
  background: #7AAACE;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 0.9rem;
  color: white;
  overflow: hidden;
}

.logo-icon img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.sidebar-logo span {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 700;
  font-size: 1rem;
  color: #1c3b4f;
}

.sidebar nav {
  flex: 1;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 10px;
  color: #2e5266;
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
  margin-bottom: 4px;
  transition: all 0.2s;
}

.nav-link .ico {
  width: 20px;
  font-size: 1.1rem;
  color: #7AAACE;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.nav-link:hover {
  background: #F7F8F0;
  color: #1c3b4f;
}

.nav-link.active {
  background: #9CD5FF;
  color: #1c3b4f;
  font-weight: 600;
}

.nav-link.active .ico {
  color: #1c3b4f;
}

.sidebar-bottom {
  margin-top: auto;
  padding-top: 20px;
  border-top: 1px solid #d0e2ef;
}

.admin-info {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 15px;
  padding: 8px 12px;
}

.admin-avatar {
  width: 36px;
  height: 36px;
  background: #7AAACE;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  color: white;
  font-size: 0.9rem;
}

.admin-name {
  font-size: 0.85rem;
  font-weight: 600;
  color: #1c3b4f;
}

.admin-role {
  font-size: 0.7rem;
  color: #6a889b;
}

.logout-link {
  margin-top: 5px;
  color: #dc2626;
}

.logout-link .ico {
  color: #dc2626;
}

.logout-link:hover {
  background: #fee2e2;
}
</style>

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">
      <!-- Replace this with your actual logo image -->
      <!-- <img src="../uploads/malinaw.png" alt="College Logo"> -->
      <i class="fa-solid fa-building-columns" style="font-size: 1.2rem;"></i>
    </div>
    <span>Southern Baptist College</span>
  </div>
  <nav>
    <a href="dashboard.php" class="nav-link <?= $active==='dashboard'?'active':'' ?>">
      <span class="ico"><i class="fa-solid fa-chart-pie"></i></span> Dashboard
    </a>
    <a href="exams.php" class="nav-link <?= $active==='exams'?'active':'' ?>">
      <span class="ico"><i class="fa-regular fa-file-lines"></i></span> Exams
    </a>
    <a href="students.php" class="nav-link <?= $active==='students'?'active':'' ?>">
      <span class="ico"><i class="fa-solid fa-graduation-cap"></i></span> Students
    </a>
    <a href="results.php" class="nav-link <?= $active==='results'?'active':'' ?>">
      <span class="ico"><i class="fa-solid fa-chart-line"></i></span> Results
    </a>
    <a href="grade_essays.php" class="nav-link <?= $active==='essays'?'active':'' ?>">
      <span class="ico"><i class="fa-solid fa-pen"></i></span> Grade Essays
    </a>
  </nav>
  <div class="sidebar-bottom">
    <div class="admin-info">
      <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_name']??'A',0,1)) ?></div>
      <div>
        <div class="admin-name"><?= htmlspecialchars($_SESSION['admin_name']??'Admin') ?></div>
        <div class="admin-role">Administrator</div>
      </div>
    </div>
    <a href="logout.php" class="nav-link logout-link">
      <span class="ico"><i class="fa-solid fa-right-from-bracket"></i></span> Logout
    </a>
  </div>
</aside>
<?php } ?>