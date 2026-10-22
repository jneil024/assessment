<?php
function adminSidebar($active = '') { 
// Load Font Awesome
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
/* Make sure icons are visible */
.nav-link .ico i {
  font-size: 1rem;
  width: 20px;
  text-align: center;
}
</style>
<aside class="sidebar">
  <div class="sidebar-logo">
  <div class="logo-icon">
  <img src="../uploads/malinaw.jpg" alt="Southern Baptist College" style="width: 100%; height: 100%; border-radius: 45px; ">
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
    <a href="logout.php" class="nav-link logout-link">
      <span class="ico"><i class="fa-solid fa-right-from-bracket"></i></span> Logout
    </a>
  </div>
</aside>
<?php }

function adminCSS() { ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap');
:root{
  --bg:#f8f9fc;
  --white:#ffffff;
  --sidebar:#1e293b;
  --primary:#4f46e5;
  --primary-light:#ede9fe;
  --primary-dark:#3730a3;
  --success:#16a34a;
  --success-bg:#dcfce7;
  --danger:#dc2626;
  --danger-bg:#fee2e2;
  --warning:#d97706;
  --warning-bg:#fef3c7;
  --purple:#7c3aed;
  --purple-bg:#ede9fe;
  --teal:#0d9488;
  --teal-bg:#ccfbf1;
  --text:#0f172a;
  --text-2:#475569;
  --text-3:#94a3b8;
  --border:#e2e8f0;
  --border-2:#cbd5e1;
  --shadow:0 1px 3px rgba(0,0,0,0.07),0 1px 2px rgba(0,0,0,0.04);
  --shadow-md:0 4px 12px rgba(0,0,0,0.08);
  --radius:10px;
  --radius-lg:14px;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;font-size:14px;}

.sidebar{width:220px;min-height:100vh;background:var(--sidebar);flex-shrink:0;position:fixed;top:0;left:0;bottom:0;z-index:50;display:flex;flex-direction:column;}
.sidebar-logo{padding:20px 18px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,0.08);}
.logo-icon{
  width:50px;
  height:50px;
  border-radius:7px;
  display:flex;
  align-items:center;
  justify-content:center;
  flex-shrink:0;
  overflow:hidden; /* This ensures the image stays within the rounded corners */
}
.sidebar-logo span{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:0.95rem;color:#fff;}
.sidebar nav{padding:10px 8px;flex:1;overflow-y:auto;}
.nav-link{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:7px;text-decoration:none;color:rgba(255,255,255,0.6);font-size:0.83rem;font-weight:500;transition:all 0.15s;margin-bottom:2px;}
.nav-link:hover{background:rgba(255,255,255,0.08);color:#fff;}
.nav-link.active{background:var(--primary);color:#fff;}
.nav-link .ico{width:20px;text-align:center;display:inline-flex;align-items:center;justify-content:center;}
.nav-link .ico i{font-size:1rem;color:inherit;}
.sidebar-bottom{padding:8px;border-top:1px solid rgba(255,255,255,0.08);}
.logout-link:hover{background:rgba(220,38,38,0.25);color:#fca5a5;}
.logout-link:hover i{color:#fca5a5;}

.main{margin-left:220px;flex:1;padding:26px 30px;min-height:100vh;}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;flex-wrap:wrap;}
.page-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.35rem;font-weight:800;color:var(--text);}
.page-sub{color:var(--text-3);font-size:0.8rem;margin-top:3px;}

.card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow);}
.card-header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border);}
.card-header h3{font-family:'Plus Jakarta Sans',sans-serif;font-size:0.88rem;font-weight:700;}

table{width:100%;border-collapse:collapse;}
th{padding:10px 15px;text-align:left;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-3);border-bottom:1px solid var(--border);background:#f8fafc;font-weight:600;}
td{padding:12px 15px;font-size:0.84rem;border-bottom:1px solid var(--border);vertical-align:middle;color:var(--text-2);}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#fafbfc;}
td strong{color:var(--text);font-weight:600;}

.badge{display:inline-flex;align-items:center;padding:2px 7px;border-radius:4px;font-size:0.68rem;font-weight:600;}
.badge-active{background:var(--success-bg);color:var(--success);}
.badge-inactive{background:var(--danger-bg);color:var(--danger);}
.badge-mc{background:#dbeafe;color:#1d4ed8;}
.badge-spelling{background:#fef3c7;color:#92400e;}
.badge-enum{background:var(--success-bg);color:var(--success);}
.badge-essay{background:var(--purple-bg);color:var(--purple);}
.badge-ident{background:#ffedd5;color:#9a3412;}
.badge-listening{background:var(--teal-bg);color:var(--teal);}

.actions{display:flex;gap:4px;flex-wrap:wrap;}
.btn-sm{padding:4px 10px;border-radius:5px;font-size:0.75rem;font-weight:600;text-decoration:none;border:1px solid transparent;cursor:pointer;transition:all 0.12s;display:inline-flex;align-items:center;gap:3px;}
.btn-blue{background:#dbeafe;color:#1d4ed8;border-color:#bfdbfe;} .btn-blue:hover{background:#bfdbfe;}
.btn-cyan{background:#e0f2fe;color:#0369a1;border-color:#bae6fd;} .btn-cyan:hover{background:#bae6fd;}
.btn-red{background:var(--danger-bg);color:var(--danger);border-color:#fecaca;} .btn-red:hover{background:#fecaca;}
.btn-green{background:var(--success-bg);color:var(--success);border-color:#bbf7d0;} .btn-green:hover{background:#bbf7d0;}
.btn-purple{background:var(--purple-bg);color:var(--purple);border-color:#ddd6fe;} .btn-purple:hover{background:#ddd6fe;}
.btn-teal{background:var(--teal-bg);color:var(--teal);border-color:#99f6e4;} .btn-teal:hover{background:#99f6e4;}
.btn-primary{background:var(--primary);color:#fff;padding:8px 16px;border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:0.83rem;text-decoration:none;border:none;cursor:pointer;transition:background 0.15s;display:inline-flex;align-items:center;gap:5px;}
.btn-primary:hover{background:var(--primary-dark);}
.btn-outline{background:#fff;color:var(--text-2);padding:8px 16px;border-radius:8px;font-weight:600;font-size:0.83rem;text-decoration:none;border:1px solid var(--border-2);cursor:pointer;transition:all 0.15s;display:inline-flex;align-items:center;gap:5px;}
.btn-outline:hover{border-color:var(--primary);color:var(--primary);}

.flash{padding:11px 14px;border-radius:8px;margin-bottom:16px;font-size:0.83rem;display:flex;align-items:center;gap:8px;}
.flash-success{background:var(--success-bg);border:1px solid #bbf7d0;color:var(--success);}
.flash-error{background:var(--danger-bg);border:1px solid #fecaca;color:var(--danger);}

label{display:block;font-size:0.78rem;font-weight:600;color:var(--text-2);margin-bottom:4px;}
input[type=text],input[type=number],input[type=password],input[type=url],input[type=email],textarea,select{
  width:100%;padding:8px 11px;background:#fff;border:1px solid var(--border-2);border-radius:7px;
  color:var(--text);font-size:0.855rem;font-family:'Inter',sans-serif;outline:none;transition:border-color 0.15s,box-shadow 0.15s;}
input:focus,textarea:focus,select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(79,70,229,0.1);}
select option{background:#fff;}
textarea{resize:vertical;}
.form-group{margin-bottom:13px;}
.form-row{display:grid;gap:12px;}
.form-row-2{grid-template-columns:1fr 1fr;}
.form-row-3{grid-template-columns:1fr 1fr 1fr;}
.hint{font-size:0.73rem;color:var(--text-3);margin-top:3px;}

.breadcrumb{font-size:0.78rem;color:var(--text-3);margin-bottom:5px;}
.breadcrumb a{color:var(--primary);text-decoration:none;font-weight:500;}
.breadcrumb a:hover{text-decoration:underline;}
.empty{text-align:center;padding:44px 20px;color:var(--text-3);font-size:0.86rem;}
</style>
<?php }
?>