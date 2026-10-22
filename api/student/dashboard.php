<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';
require_once '../includes/student_sidebar.php';
$db = getDB();
$exams = $db->query("SELECT * FROM exams WHERE is_active=1 ORDER BY created_at DESC")->fetchAll();
$myResults = $db->prepare("SELECT es.*,e.title FROM exam_sessions es JOIN exams e ON e.id=es.exam_id WHERE es.student_id=? AND es.status='submitted' ORDER BY es.submitted_at DESC");
$myResults->execute([$_SESSION['student_id']]); $myResults=$myResults->fetchAll();
$takenIds = array_column($myResults,'exam_id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Southern Baptist College</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
:root{--primary:#0ea5e9;--primary-dark:#0284c7;--primary-light:#f0f9ff;--bg:#f5f6fa;--white:#fff;--text:#0f172a;--text-2:#475569;--text-3:#94a3b8;--border:#e2e8f0;--success:#16a34a;--success-bg:#dcfce7;--danger:#dc2626;--warning:#d97706;--shadow:0 1px 3px rgba(0,0,0,0.08);}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
/* nav injected by student_sidebar.php */
.content{max-width:1000px;margin:0 auto;padding:28px 24px;}
.section-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:700;margin-bottom:16px;color:var(--text);}
.exam-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:16px;margin-bottom:36px;}
.exam-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:20px;box-shadow:var(--shadow);transition:box-shadow 0.15s,transform 0.15s;}
.exam-card:hover{box-shadow:0 4px 16px rgba(0,0,0,0.1);transform:translateY(-2px);}
.exam-card.taken{opacity:0.6;}
.exam-icon{font-size:1.8rem;margin-bottom:10px;}
.exam-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:0.95rem;font-weight:700;margin-bottom:6px;}
.exam-desc{font-size:0.82rem;color:var(--text-3);margin-bottom:12px;line-height:1.5;}
.exam-meta{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;}
.meta-tag{font-size:0.72rem;color:var(--text-3);background:var(--bg);padding:3px 9px;border-radius:5px;border:1px solid var(--border);}
.btn-take{display:block;text-align:center;padding:9px;background:var(--primary);color:#fff;border-radius:8px;font-weight:700;font-size:0.85rem;text-decoration:none;transition:background 0.15s;}
.btn-take:hover{background:var(--primary-dark);}
.btn-taken{display:block;text-align:center;padding:9px;background:var(--bg);color:var(--text-3);border-radius:8px;font-size:0.85rem;border:1px solid var(--border);}
.results-card{background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:var(--shadow);}
table{width:100%;border-collapse:collapse;}
th{padding:11px 16px;text-align:left;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-3);border-bottom:1px solid var(--border);background:#f8fafc;font-weight:600;}
td{padding:13px 16px;font-size:0.85rem;border-bottom:1px solid var(--border);}
tr:last-child td{border-bottom:none;}
.score-val{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;}
.btn-view{padding:4px 12px;border-radius:6px;font-size:0.77rem;font-weight:600;text-decoration:none;background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;}
</style>
</head>
<body>
<?php studentNav('dashboard'); ?>
<div class="content">
<div class="section-title" style="display: flex; align-items: center; gap: 8px; font-size: 2rem; font-weight: 500; color: #1c3b4f; margin-bottom: 15px;">
  <i class="fa-solid fa-book" style="color:rgb(64, 105, 218);"></i>
  Available Exams
</div>
  <?php if (empty($exams)): ?>
  <div style="background:#fff;border:1px solid var(--border);border-radius:14px;padding:40px;text-align:center;color:var(--text-3);font-size:0.88rem;margin-bottom:32px">No exams available yet. Check back later.</div>
  <?php else: ?>
  <div class="exam-grid">
    <?php foreach ($exams as $e): $taken=in_array($e['id'],$takenIds); ?>
    <div class="exam-card <?= $taken?'taken':'' ?>" style="background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 12px rgba(122,170,206,0.1); border: 1px solid #e0edf5; margin-bottom: 16px; transition: all 0.2s ease;">
  
  <div style="display: flex; align-items: flex-start; gap: 12px;">
    <i class="fa-solid fa-pen" style="color:rgb(64, 105, 218); font-size: 1.5rem; background: #F7F8F0; padding: 10px; border-radius: 12px;"></i>
    
    <div style="flex: 1;">
      <div class="exam-title" style="font-size: 1.2rem; font-weight: 600; color: #1c3b4f; margin-bottom: 6px;"><?= htmlspecialchars($e['title']) ?></div>
      
      <div class="exam-desc" style="color: #4a6579; font-size: 0.95rem; margin-bottom: 12px;"><?= htmlspecialchars($e['description']?:'Take this exam to test your knowledge.') ?></div>
      
      <div class="exam-meta" style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px;">
        <span class="meta-tag" style="display: flex; align-items: center; gap: 4px; color: #2e5266; font-size: 0.9rem;">
          <i class="fa-regular fa-clock" style="color: #7AAACE;"></i> <?= $e['duration_minutes'] ?> min
        </span>
      </div>
      
      <?php if ($taken): ?>
      <div class="btn-taken" style="display: inline-flex; align-items: center; gap: 8px; background: #e8f4e8; color: #2e7d32; padding: 8px 20px; border-radius: 30px; font-weight: 500; font-size: 0.95rem; border: 1px solid #c8e6c9;">
        <i class="fa-solid fa-check-circle"></i> Already Taken
      </div>
      <?php else: ?>
      <a href="take_exam.php?exam_id=<?= $e['id'] ?>" class="btn-take" style="display: inline-flex; align-items: center; gap: 8px; background: #7AAACE; color: white; padding: 8px 20px; border-radius: 30px; text-decoration: none; font-weight: 500; font-size: 0.95rem; transition: background 0.2s; border: none; cursor: pointer;">
        Start Exam <i class="fa-solid fa-arrow-right" style="font-size: 0.9rem;"></i>
      </a>
      <?php endif; ?>
      
    </div>
  </div>
</div>

<!-- Add hover effect style for the button -->
<style>
  .btn-take:hover {
    background: #5f93b9 !important;
    transform: translateX(2px);
  }
  .exam-card:hover {
    box-shadow: 0 8px 20px rgba(122,170,206,0.15) !important;
    border-color: #9CD5FF !important;
  }
</style>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="section-title" style="display: flex; align-items: center; gap: 10px; font-size: 1.3rem; font-weight: 500; color: #1c3b4f; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #9CD5FF;">
  <i class="fa-solid fa-chart-line" style="color: #7AAACE; font-size: 1.4rem;"></i>
  <span>My Results</span>
</div>
  <?php if (empty($myResults)): ?>
  <div style="background:#fff;border:1px solid var(--border);border-radius:14px;padding:32px;text-align:center;color:var(--text-3);font-size:0.88rem">No exams taken yet.</div>
  <?php else: ?>
  <div class="results-card">
    <table>
      <thead><tr><th>Exam</th><th>Score</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($myResults as $r):
          $pct=$r['total_points']>0?round(($r['score']/$r['total_points'])*100):0;
          $color=$pct>=75?'var(--success)':($pct>=50?'var(--warning)':'var(--danger)');
        ?>
        <tr>
          <td><strong><?= htmlspecialchars($r['title']) ?></strong></td>
          <td><span class="score-val" style="color:<?= $color ?>"><?= $pct ?>%</span> <span style="color:var(--text-3);font-size:0.78rem">(<?= $r['score'] ?>/<?= $r['total_points'] ?>)</span></td>
          <td style="color:var(--text-3);font-size:0.82rem"><?= date('M d, Y', strtotime($r['submitted_at'])) ?></td>
          <td><a href="my_result.php?session_id=<?= $r['id'] ?>" class="btn-view">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
