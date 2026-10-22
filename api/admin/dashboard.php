<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';
require_once 'includes.php';

$db = getDB();

$examCount    = $db->query("SELECT COUNT(*) FROM exams")->fetchColumn();
$studentCount = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$sessionCount = $db->query("SELECT COUNT(*) FROM exam_sessions WHERE status='submitted'")->fetchColumn();
try {
    $pendingEssays = $db->query("SELECT COUNT(*) FROM student_answers WHERE needs_manual_grading=1")->fetchColumn();
    $needsUpgrade  = false;
} catch (PDOException $e) { $pendingEssays = 0; $needsUpgrade = true; }

// Average score across all submitted sessions
$avgScore = $db->query("SELECT AVG(CASE WHEN total_points>0 THEN (score/total_points)*100 ELSE 0 END) FROM exam_sessions WHERE status='submitted'")->fetchColumn();
$avgScore = round($avgScore ?? 0, 1);

// Submissions per day – last 14 days
$daily = $db->query("
    SELECT DATE(submitted_at) as day, COUNT(*) as cnt
    FROM exam_sessions WHERE status='submitted' AND submitted_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY DATE(submitted_at) ORDER BY day ASC
")->fetchAll();
$dailyLabels = []; $dailyData = [];
$period = new DatePeriod(new DateTime('-13 days'), new DateInterval('P1D'), new DateTime('tomorrow'));
$dailyMap = array_column($daily, 'cnt', 'day');
foreach ($period as $d) {
    $k = $d->format('Y-m-d');
    $dailyLabels[] = $d->format('M d');
    $dailyData[]   = intval($dailyMap[$k] ?? 0);
}

// Score distribution buckets: 0-49, 50-74, 75-89, 90-100
$dist = $db->query("
    SELECT
      SUM(CASE WHEN total_points>0 AND (score/total_points)*100 < 50  THEN 1 ELSE 0 END) as d0,
      SUM(CASE WHEN total_points>0 AND (score/total_points)*100 >= 50 AND (score/total_points)*100 < 75 THEN 1 ELSE 0 END) as d1,
      SUM(CASE WHEN total_points>0 AND (score/total_points)*100 >= 75 AND (score/total_points)*100 < 90 THEN 1 ELSE 0 END) as d2,
      SUM(CASE WHEN total_points>0 AND (score/total_points)*100 >= 90 THEN 1 ELSE 0 END) as d3
    FROM exam_sessions WHERE status='submitted'
")->fetch();

// Per-exam stats
$examStats = $db->query("
    SELECT e.id, e.title, e.is_active, COUNT(q.id) as q_count,
           COUNT(es.id) as submissions,
           ROUND(AVG(CASE WHEN es.total_points>0 THEN (es.score/es.total_points)*100 ELSE 0 END),1) as avg_pct
    FROM exams e
    LEFT JOIN questions q ON q.exam_id=e.id
    LEFT JOIN exam_sessions es ON es.exam_id=e.id AND es.status='submitted'
    GROUP BY e.id ORDER BY e.created_at DESC LIMIT 8
")->fetchAll();

// Top scorers
$topScorers = $db->query("
    SELECT s.name, s.student_id as sid, e.title,
           ROUND((es.score/es.total_points)*100,1) as pct, es.score, es.total_points
    FROM exam_sessions es
    JOIN students s ON s.id=es.student_id
    JOIN exams e ON e.id=es.exam_id
    WHERE es.status='submitted' AND es.total_points>0
    ORDER BY pct DESC LIMIT 5
")->fetchAll();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — Southern Baptist College</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php adminCSS(); ?>
<style>
/* ── KPI CARDS ── */
.kpi-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:22px;}
.kpi-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:18px 20px;box-shadow:var(--shadow);position:relative;overflow:hidden;}
.kpi-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
.kpi-c1::before{background:linear-gradient(90deg,#4f46e5,#818cf8);}
.kpi-c2::before{background:linear-gradient(90deg,#0ea5e9,#38bdf8);}
.kpi-c3::before{background:linear-gradient(90deg,#16a34a,#4ade80);}
.kpi-c4::before{background:linear-gradient(90deg,#d97706,#fbbf24);}
.kpi-c5::before{background:linear-gradient(90deg,#7c3aed,#a78bfa);}
.kpi-label{font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-3);margin-bottom:8px;}
.kpi-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:2rem;font-weight:800;color:var(--text);line-height:1;}
.kpi-sub{font-size:0.72rem;color:var(--text-3);margin-top:4px;}
.kpi-icon{position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:1.8rem;opacity:.12;}

/* ── GRID LAYOUT ── */
.dash-row{display:grid;gap:16px;margin-bottom:16px;}
.dash-row-2{grid-template-columns:2fr 1fr;}
.dash-row-3{grid-template-columns:1fr 1fr 1fr;}
.dash-row-half{grid-template-columns:1fr 1fr;}

/* ── CHART CARD ── */
.chart-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;box-shadow:var(--shadow);}
.chart-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:0.88rem;font-weight:700;color:var(--text);margin-bottom:4px;}
.chart-sub{font-size:0.73rem;color:var(--text-3);margin-bottom:16px;}
.chart-wrap{position:relative;}

/* ── EXAM TABLE ── */
.exam-row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border);}
.exam-row:last-child{border-bottom:none;}
.exam-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.exam-title-t{font-size:0.84rem;font-weight:600;color:var(--text);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.exam-meta{font-size:0.72rem;color:var(--text-3);}
.mini-bar{height:5px;background:var(--border);border-radius:99px;width:60px;overflow:hidden;display:inline-block;margin-right:4px;vertical-align:middle;}
.mini-fill{height:100%;border-radius:99px;}

/* ── TOP SCORERS ── */
.scorer-row{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border);}
.scorer-row:last-child{border-bottom:none;}
.scorer-rank{width:20px;height:20px;border-radius:50%;font-size:0.66rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.rank-1{background:#fef3c7;color:#d97706;}
.rank-2{background:#f1f5f9;color:#64748b;}
.rank-3{background:#ffedd5;color:#c2410c;}
.rank-other{background:var(--bg);color:var(--text-3);}
.scorer-name{font-size:0.83rem;font-weight:600;flex:1;}
.scorer-exam{font-size:0.71rem;color:var(--text-3);}
.score-pill{font-family:'Plus Jakarta Sans',sans-serif;font-size:0.78rem;font-weight:800;padding:2px 8px;border-radius:5px;}

/* ── PENDING ALERT ── */
.pending-alert{background:#fffbeb;border:1px solid #fcd34d;border-radius:var(--radius);padding:11px 15px;display:flex;align-items:center;gap:10px;margin-bottom:18px;font-size:0.83rem;color:#92400e;}
.pending-alert a{color:var(--primary);font-weight:700;text-decoration:none;margin-left:auto;padding:4px 10px;background:#fff;border:1px solid var(--border);border-radius:5px;font-size:0.78rem;}

/* ── UPGRADE BANNER ── */
.upgrade-banner{background:#fef3c7;border:1px solid #fcd34d;border-radius:10px;padding:13px 17px;margin-bottom:18px;font-size:0.83rem;color:#92400e;line-height:1.6;}
</style>
</head>
<body>
<?php adminSidebar('dashboard'); ?>
<main class="main">

  <div class="page-header">
    <div>
      <div class="page-title">Dashboard</div>
      <div class="page-sub">Welcome back, <?= htmlspecialchars($_SESSION['admin_name']) ?>!</div>
    </div>
    <a href="create_exam.php" class="btn-primary">+ New Exam</a>
  </div>

  <?php if ($needsUpgrade): ?>
  <div class="upgrade-banner"><i class="fa-solid fa-triangle-exclamation" style="margin-right:5px;"></i> <strong>Database upgrade required.</strong> Run <code>upgrade.sql</code> in phpMyAdmin to enable all features.</div>
  <?php endif; ?>
  <?php if ($flash): ?><div class="flash flash-<?= $flash['type'] ?>"><i class="fa-solid fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>" style="margin-right:5px;"></i><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  <?php if ($pendingEssays > 0): ?>
  <div class="pending-alert">
    <i class="fa-solid fa-pen" style="margin-right:5px;"></i> <strong><?= $pendingEssays ?> essay<?= $pendingEssays>1?'s':'' ?></strong> waiting for manual grading.
    <a href="grade_essays.php">Grade now <i class="fa-solid fa-arrow-right" style="margin-left:3px;"></i></a>
  </div>
  <?php endif; ?>

  <!-- KPI Row -->
  <div class="kpi-grid">
    <div class="kpi-card kpi-c1"><div class="kpi-label">Total Exams</div><div class="kpi-val"><?= $examCount ?></div><div class="kpi-sub">created</div><div class="kpi-icon"><i class="fa-regular fa-file-lines"></i></div></div>
    <div class="kpi-card kpi-c2"><div class="kpi-label">Students</div><div class="kpi-val"><?= $studentCount ?></div><div class="kpi-sub">registered</div><div class="kpi-icon"><i class="fa-solid fa-graduation-cap"></i></div></div>
    <div class="kpi-card kpi-c3"><div class="kpi-label">Submissions</div><div class="kpi-val"><?= $sessionCount ?></div><div class="kpi-sub">completed</div><div class="kpi-icon"><i class="fa-regular fa-circle-check"></i></div></div>
    <div class="kpi-card kpi-c4"><div class="kpi-label">Avg Score</div><div class="kpi-val"><?= $avgScore ?>%</div><div class="kpi-sub">across all exams</div><div class="kpi-icon"><i class="fa-solid fa-chart-line"></i></div></div>
    <div class="kpi-card kpi-c5" style="<?= $pendingEssays>0?'border-color:#fcd34d':'' ?>">
      <div class="kpi-label">Pending Essays</div>
      <div class="kpi-val" style="color:<?= $pendingEssays>0?'#d97706':'var(--text)' ?>"><?= $pendingEssays ?></div>
      <div class="kpi-sub">to grade</div>
      <div class="kpi-icon"><i class="fa-solid fa-pen"></i></div>
    </div>
  </div>

  <!-- Row 1: Activity line + Score donut -->
  <div class="dash-row dash-row-2">
    <div class="chart-card">
      <div class="chart-title">Submission Activity</div>
      <div class="chart-sub">Exams submitted over the last 14 days</div>
      <div class="chart-wrap"><canvas id="activityChart" height="110"></canvas></div>
    </div>
    <div class="chart-card">
      <div class="chart-title">Score Distribution</div>
      <div class="chart-sub">Students by performance band</div>
      <div class="chart-wrap" style="display:flex;align-items:center;justify-content:center"><canvas id="distChart" height="160" width="160"></canvas></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-top:10px">
        <?php
        $bands = [['0–49%','#dc2626',$dist['d0']??0],['50–74%','#d97706',$dist['d1']??0],['75–89%','#16a34a',$dist['d2']??0],['90–100%','#4f46e5',$dist['d3']??0]];
        foreach ($bands as [$lbl,$col,$cnt]): ?>
        <div style="display:flex;align-items:center;gap:5px;font-size:0.72rem;color:var(--text-2)">
          <span style="width:9px;height:9px;border-radius:2px;background:<?= $col ?>;flex-shrink:0;display:inline-block"></span>
          <?= $lbl ?> <strong style="margin-left:auto;color:var(--text)"><?= $cnt ?></strong>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Row 2: Exam performance bar + Top scorers -->
  <div class="dash-row dash-row-half">
    <div class="chart-card">
      <div class="chart-title">Exam Performance</div>
      <div class="chart-sub">Average score % per exam</div>
      <div class="chart-wrap"><canvas id="examChart" height="180"></canvas></div>
    </div>
    <div class="chart-card">
      <div class="chart-title"><i class="fa-solid fa-trophy" style="margin-right:5px;"></i> Top Scorers</div>
      <div class="chart-sub">Highest scores across all exams</div>
      <?php if (empty($topScorers)): ?>
      <div class="empty">No submissions yet.</div>
      <?php else: foreach ($topScorers as $ri => $r):
        $rColor = $ri===0?'#d97706':($ri===1?'#64748b':($ri===2?'#c2410c':''));
        $scoreColor = $r['pct']>=75?'#16a34a':($r['pct']>=50?'#d97706':'#dc2626');
        $scoreBg    = $r['pct']>=75?'#dcfce7':($r['pct']>=50?'#fef3c7':'#fee2e2');
      ?>
      <div class="scorer-row">
        <div class="scorer-rank rank-<?= $ri<3?$ri+1:'other' ?>"><?= $ri+1 ?></div>
        <div style="flex:1;min-width:0">
          <div class="scorer-name"><?= htmlspecialchars($r['name']) ?></div>
          <div class="scorer-exam"><?= htmlspecialchars(mb_substr($r['title'],0,30)) ?></div>
        </div>
        <span class="score-pill" style="background:<?= $scoreBg ?>;color:<?= $scoreColor ?>"><?= $r['pct'] ?>%</span>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Row 3: Exam list table -->
  <div class="chart-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
      <div><div class="chart-title">Recent Exams</div><div class="chart-sub">Overview of all created exams</div></div>
      <a href="exams.php" class="btn-outline" style="font-size:0.78rem;padding:5px 12px">View All <i class="fa-solid fa-arrow-right" style="margin-left:3px;"></i></a>
    </div>
    <?php if (empty($examStats)): ?>
    <div class="empty">No exams yet. <a href="create_exam.php" style="color:var(--primary);font-weight:600">Create your first <i class="fa-solid fa-arrow-right"></i></a></div>
    <?php else: ?>
    <table>
      <thead><tr><th>Exam</th><th>Questions</th><th>Submissions</th><th>Avg Score</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($examStats as $e):
          $ap = floatval($e['avg_pct']??0);
          $ac = $ap>=75?'#16a34a':($ap>=50?'#d97706':'#dc2626');
        ?>
        <tr>
          <td><strong><?= htmlspecialchars($e['title']) ?></strong></td>
          <td><?= $e['q_count'] ?></td>
          <td><?= $e['submissions'] ?></td>
          <td>
            <?php if ($e['submissions']>0): ?>
            <div style="display:flex;align-items:center;gap:6px">
              <div class="mini-bar"><div class="mini-fill" style="width:<?= min(100,$ap) ?>%;background:<?= $ac ?>"></div></div>
              <span style="color:<?= $ac ?>;font-weight:700;font-size:0.82rem"><?= $ap ?>%</span>
            </div>
            <?php else: ?><span style="color:var(--text-3);font-size:0.78rem">No data</span><?php endif; ?>
          </td>
          <td><span class="badge <?= $e['is_active']?'badge-active':'badge-inactive' ?>"><?= $e['is_active']?'Active':'Inactive' ?></span></td>
          <td><div class="actions">
            <a href="questions.php?exam_id=<?= $e['id'] ?>" class="btn-sm btn-cyan">Questions</a>
            <a href="results.php?exam_id=<?= $e['id'] ?>" class="btn-sm btn-purple">Results</a>
            <a href="edit_exam.php?id=<?= $e['id'] ?>" class="btn-sm btn-blue">Edit</a>
            <a href="delete_exam.php?id=<?= $e['id'] ?>" class="btn-sm btn-red" onclick="return confirm('Delete this exam and all its data?')">Delete</a>
          </div></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = '#94a3b8';

// ── Activity Line Chart ──────────────────────────────────────────────────────
new Chart(document.getElementById('activityChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($dailyLabels) ?>,
    datasets: [{
      label: 'Submissions',
      data: <?= json_encode($dailyData) ?>,
      borderColor: '#4f46e5',
      backgroundColor: 'rgba(79,70,229,0.08)',
      borderWidth: 2.5,
      pointBackgroundColor: '#4f46e5',
      pointRadius: 3,
      pointHoverRadius: 5,
      fill: true,
      tension: 0.4
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: true,
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.y} submission${ctx.parsed.y!==1?'s':''}` }}},
    scales: {
      x: { grid: { display: false }, border: { display: false }, ticks: { maxTicksLimit: 7, font: { size: 11 }}},
      y: { grid: { color: '#f1f5f9' }, border: { display: false }, beginAtZero: true, ticks: { precision: 0, font: { size: 11 }}}
    }
  }
});

// ── Score Distribution Donut ─────────────────────────────────────────────────
const distTotal = <?= array_sum([$dist['d0']??0,$dist['d1']??0,$dist['d2']??0,$dist['d3']??0]) ?>;
new Chart(document.getElementById('distChart'), {
  type: 'doughnut',
  data: {
    labels: ['0–49%', '50–74%', '75–89%', '90–100%'],
    datasets: [{
      data: [<?= ($dist['d0']??0).','.(($dist['d1']??0)).','.(($dist['d2']??0)).','.(($dist['d3']??0)) ?>],
      backgroundColor: ['#fecaca','#fed7aa','#bbf7d0','#c7d2fe'],
      borderColor: ['#dc2626','#d97706','#16a34a','#4f46e5'],
      borderWidth: 2, hoverOffset: 6
    }]
  },
  options: {
    responsive: false, cutout: '68%',
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} student${ctx.parsed!==1?'s':''}` }},
    }
  },
  plugins: [{
    id: 'centerText',
    beforeDraw(chart) {
      const {ctx, chartArea: {width, height, top}} = chart;
      ctx.save();
      ctx.font = 'bold 1.4rem Plus Jakarta Sans, sans-serif';
      ctx.fillStyle = '#0f172a';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(distTotal, width/2, top + height/2 - 8);
      ctx.font = '0.65rem Inter, sans-serif';
      ctx.fillStyle = '#94a3b8';
      ctx.fillText('students', width/2, top + height/2 + 12);
      ctx.restore();
    }
  }]
});

// ── Exam Avg Score Bar Chart ─────────────────────────────────────────────────
<?php
$eLabels = array_map(fn($e) => mb_substr($e['title'],0,20).(mb_strlen($e['title'])>20?'…':''), $examStats);
$eData   = array_map(fn($e) => floatval($e['avg_pct']??0), $examStats);
$eBg     = array_map(fn($v) => $v>=75?'rgba(22,163,74,0.75)':($v>=50?'rgba(217,119,6,0.75)':'rgba(220,38,38,0.75)'), $eData);
$eBorder = array_map(fn($v) => $v>=75?'#16a34a':($v>=50?'#d97706':'#dc2626'), $eData);
?>
new Chart(document.getElementById('examChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_values($eLabels)) ?>,
    datasets: [{
      label: 'Avg Score %',
      data: <?= json_encode(array_values($eData)) ?>,
      backgroundColor: <?= json_encode(array_values($eBg)) ?>,
      borderColor: <?= json_encode(array_values($eBorder)) ?>,
      borderWidth: 1.5, borderRadius: 6, borderSkipped: false
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: true,
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` Avg: ${ctx.parsed.y}%` }}},
    scales: {
      x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11 }}},
      y: { grid: { color: '#f1f5f9' }, border: { display: false }, min: 0, max: 100,
           ticks: { callback: v => v+'%', font: { size: 11 }}}
    }
  }
});
</script>
</body>
</html>