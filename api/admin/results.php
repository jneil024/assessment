<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';
require_once 'includes.php';

$db     = getDB();
$examId = intval($_GET['exam_id'] ?? 0);
$exams  = $db->query("SELECT id,title FROM exams ORDER BY title")->fetchAll();

if ($examId) {
    $sessions = $db->prepare("
        SELECT es.*,s.name,s.student_id as sid,e.title
        FROM exam_sessions es
        JOIN students s ON s.id=es.student_id
        JOIN exams e ON e.id=es.exam_id
        WHERE es.exam_id=? AND es.status='submitted'
        ORDER BY es.score DESC
    ");
    $sessions->execute([$examId]); $sessions = $sessions->fetchAll();

    // Per-question accuracy for the selected exam
    $qAccuracy = $db->prepare("
        SELECT q.question_text, q.type, q.points,
               COUNT(sa.id) as attempts,
               SUM(sa.is_correct) as correct_count,
               ROUND(AVG(sa.points_earned),2) as avg_pts
        FROM questions q
        LEFT JOIN student_answers sa ON sa.question_id=q.id
        LEFT JOIN exam_sessions es ON es.id=sa.session_id AND es.status='submitted'
        WHERE q.exam_id=?
        GROUP BY q.id ORDER BY q.order_num,q.id
    ");
    $qAccuracy->execute([$examId]); $qAccuracy = $qAccuracy->fetchAll();

} else {
    $sessions = $db->query("
        SELECT es.*,s.name,s.student_id as sid,e.title
        FROM exam_sessions es
        JOIN students s ON s.id=es.student_id
        JOIN exams e ON e.id=es.exam_id
        WHERE es.status='submitted'
        ORDER BY es.submitted_at DESC
    ")->fetchAll();
    $qAccuracy = [];
}

// Compute stats from sessions
$totalSubs  = count($sessions);
$passCount  = 0; $failCount = 0;
$scores     = [];
foreach ($sessions as $s) {
    $pct = $s['total_points']>0 ? ($s['score']/$s['total_points'])*100 : 0;
    $scores[] = round($pct, 1);
    if ($pct >= 75) $passCount++; else $failCount++;
}
$avgPct  = $totalSubs > 0 ? round(array_sum($scores)/$totalSubs, 1) : 0;
$highPct = $totalSubs > 0 ? max($scores) : 0;
$lowPct  = $totalSubs > 0 ? min($scores) : 0;

// Score histogram buckets (10-point bands)
$buckets = array_fill(0, 10, 0);
foreach ($scores as $sc) {
    $b = min(9, floor($sc / 10));
    $buckets[$b]++;
}
$bucketLabels = ['0-9','10-19','20-29','30-39','40-49','50-59','60-69','70-79','80-89','90-100'];
$bucketColors = array_map(fn($i) => $i>=9?'rgba(79,70,229,0.8)':($i>=7?'rgba(22,163,74,0.75)':($i>=5?'rgba(217,119,6,0.7)':'rgba(220,38,38,0.65)')), array_keys($buckets));

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Results — Southern Baptist College</title>
<?php adminCSS(); ?>
<style>
.filter-bar{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:13px 18px;margin-bottom:18px;display:flex;align-items:center;gap:12px;box-shadow:var(--shadow);}
.filter-bar select{width:auto;padding:6px 11px;}
.stat-strip{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:18px;}
.strip-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:14px 16px;box-shadow:var(--shadow);text-align:center;}
.strip-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.55rem;font-weight:800;color:var(--text);}
.strip-lbl{font-size:0.7rem;font-weight:600;color:var(--text-3);text-transform:uppercase;letter-spacing:0.4px;margin-top:3px;}
.charts-row{display:grid;grid-template-columns:1.4fr 1fr;gap:14px;margin-bottom:16px;}
.chart-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:18px 20px;box-shadow:var(--shadow);}
.chart-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:0.87rem;font-weight:700;margin-bottom:3px;}
.chart-sub{font-size:0.72rem;color:var(--text-3);margin-bottom:14px;}
.q-acc-list{margin-top:4px;}
.q-acc-row{padding:9px 0;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
.q-acc-row:last-child{border-bottom:none;}
.q-acc-text{font-size:0.81rem;color:var(--text);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;}
.q-acc-bar{flex:1;height:6px;background:var(--border);border-radius:99px;overflow:hidden;}
.q-acc-fill{height:100%;border-radius:99px;}
.q-acc-pct{font-size:0.78rem;font-weight:700;min-width:34px;text-align:right;}
.score-bar-wrap{display:flex;align-items:center;gap:7px;}
.score-bar{height:6px;background:var(--border);border-radius:99px;width:70px;overflow:hidden;}
.score-fill{height:100%;border-radius:99px;}
.pass-chip{display:inline-block;padding:2px 7px;border-radius:4px;font-size:0.7rem;font-weight:700;}
.search-wrap{position:relative;flex:1;max-width:240px;}
.search-wrap input{padding-left:30px;}
.search-icon{position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--text-3);font-size:0.8rem;pointer-events:none;}
</style>
</head>
<body>
<?php adminSidebar('results'); ?>
<main class="main">

  <div class="page-header">
    <div><div class="page-title">Results & Analytics</div><div class="page-sub"><?= $totalSubs ?> submission<?= $totalSubs!=1?'s':'' ?></div></div>
  </div>
  <?php if ($flash): ?><div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>

  <!-- Filter -->
  <div class="filter-bar">
    <label style="margin:0;white-space:nowrap;font-size:0.8rem">Filter by Exam:</label>
    <form method="GET" style="display:flex;align-items:center;gap:8px;flex:1">
      <select name="exam_id" onchange="this.form.submit()" style="min-width:200px">
        <option value="">All Exams</option>
        <?php foreach ($exams as $e): ?>
        <option value="<?= $e['id'] ?>" <?= $examId==$e['id']?'selected':'' ?>><?= htmlspecialchars($e['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($examId): ?><a href="results.php" class="btn-sm btn-red" style="text-decoration:none">✕ Clear</a><?php endif; ?>
    </form>
    <div class="search-wrap">
      <span class="search-icon">🔍</span>
      <input type="text" id="searchBox" placeholder="Search student…" oninput="filterTable(this.value)">
    </div>
  </div>

  <!-- Stats strip -->
  <div class="stat-strip">
    <div class="strip-card"><div class="strip-val"><?= $totalSubs ?></div><div class="strip-lbl">Submissions</div></div>
    <div class="strip-card"><div class="strip-val"><?= $avgPct ?>%</div><div class="strip-lbl">Average</div></div>
    <div class="strip-card"><div class="strip-val" style="color:#16a34a"><?= $highPct ?>%</div><div class="strip-lbl">Highest</div></div>
    <div class="strip-card"><div class="strip-val" style="color:#dc2626"><?= $lowPct ?>%</div><div class="strip-lbl">Lowest</div></div>
    <div class="strip-card">
      <div class="strip-val"><?= $totalSubs>0?round(($passCount/$totalSubs)*100).'%':'-' ?></div>
      <div class="strip-lbl">Pass Rate (≥75%)</div>
    </div>
  </div>

  <?php if ($totalSubs > 0): ?>
  <!-- Charts row -->
  <div class="charts-row">
    <!-- Score histogram -->
    <div class="chart-card">
      <div class="chart-title">Score Distribution</div>
      <div class="chart-sub">Number of students in each score range</div>
      <canvas id="histChart" height="160"></canvas>
    </div>

    <?php if ($examId && !empty($qAccuracy)): ?>
    <!-- Question accuracy -->
    <div class="chart-card">
      <div class="chart-title">Question Accuracy</div>
      <div class="chart-sub">% of students who got each question right</div>
      <canvas id="qChart" height="160"></canvas>
    </div>
    <?php else: ?>
    <!-- Pass/Fail donut -->
    <div class="chart-card" style="display:flex;flex-direction:column;align-items:center">
      <div style="width:100%"><div class="chart-title">Pass / Fail</div><div class="chart-sub">Students scoring ≥ 75%</div></div>
      <canvas id="pfChart" width="160" height="160"></canvas>
      <div style="display:flex;gap:16px;margin-top:10px;font-size:0.78rem">
        <span style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:2px;background:#16a34a;display:inline-block"></span>Pass: <strong><?= $passCount ?></strong></span>
        <span style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:2px;background:#dc2626;display:inline-block"></span>Fail: <strong><?= $failCount ?></strong></span>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Results table -->
  <div class="chart-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
      <div><div class="chart-title">Student Results</div><div class="chart-sub">Ranked by score</div></div>
    </div>
    <?php if (empty($sessions)): ?>
    <div class="empty">No results yet<?= $examId?' for this exam':'' ?>.</div>
    <?php else: ?>
    <table id="resultsTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Student</th>
          <th>ID</th>
          <?php if (!$examId): ?><th>Exam</th><?php endif; ?>
          <th>Score</th>
          <th>Status</th>
          <th>Submitted</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sessions as $ri => $s):
          $pct   = $s['total_points']>0 ? round(($s['score']/$s['total_points'])*100) : 0;
          $color = $pct>=75?'#16a34a':($pct>=50?'#d97706':'#dc2626');
          $bg    = $pct>=75?'#dcfce7':($pct>=50?'#fef3c7':'#fee2e2');
        ?>
        <tr class="result-row">
          <td style="color:var(--text-3);font-weight:700;font-size:0.8rem"><?= $ri+1 ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:7px">
              <div style="width:26px;height:26px;border-radius:50%;background:var(--primary-light);color:var(--primary);font-size:0.68rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= strtoupper(substr($s['name'],0,1)) ?></div>
              <strong><?= htmlspecialchars($s['name']) ?></strong>
            </div>
          </td>
          <td style="font-family:monospace;font-size:0.82rem"><?= htmlspecialchars($s['sid']) ?></td>
          <?php if (!$examId): ?><td style="font-size:0.82rem;color:var(--text-2)"><?= htmlspecialchars($s['title']) ?></td><?php endif; ?>
          <td>
            <div class="score-bar-wrap">
              <div class="score-bar"><div class="score-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div></div>
              <span style="color:<?= $color ?>;font-weight:800;font-size:0.84rem"><?= $pct ?>%</span>
              <span style="color:var(--text-3);font-size:0.74rem">(<?= $s['score'] ?>/<?= $s['total_points'] ?>)</span>
            </div>
          </td>
          <td><span class="pass-chip" style="background:<?= $bg ?>;color:<?= $color ?>"><?= $pct>=75?'✓ Pass':'✗ Fail' ?></span></td>
          <td style="font-size:0.78rem;color:var(--text-3)"><?= date('M d, Y · g:i A', strtotime($s['submitted_at'])) ?></td>
          <td><a href="result_detail.php?session_id=<?= $s['id'] ?>" class="btn-sm btn-cyan">Detail →</a></td>
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

<?php if ($totalSubs > 0): ?>
// ── Score Histogram ──────────────────────────────────────────────────────────
new Chart(document.getElementById('histChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($bucketLabels) ?>,
    datasets: [{
      label: 'Students',
      data: <?= json_encode(array_values($buckets)) ?>,
      backgroundColor: <?= json_encode(array_values($bucketColors)) ?>,
      borderRadius: 5, borderSkipped: false
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: true,
    plugins: { legend: {display:false}, tooltip: {callbacks:{label: ctx=>`${ctx.parsed.y} student${ctx.parsed.y!==1?'s':''}`}}},
    scales: {
      x: {grid:{display:false}, border:{display:false}, ticks:{font:{size:10}}},
      y: {grid:{color:'#f1f5f9'}, border:{display:false}, beginAtZero:true, ticks:{precision:0, font:{size:11}}}
    }
  }
});

<?php if ($examId && !empty($qAccuracy)): ?>
// ── Question Accuracy Horizontal Bar ────────────────────────────────────────
const qLabels = <?= json_encode(array_map(fn($q)=>mb_substr($q['question_text'],0,28).(mb_strlen($q['question_text'])>28?'…':''), $qAccuracy)) ?>;
const qData   = <?= json_encode(array_map(fn($q)=>$q['attempts']>0?round(($q['correct_count']/$q['attempts'])*100,1):0, $qAccuracy)) ?>;
const qColors = qData.map(v => v>=75?'rgba(22,163,74,0.75)':v>=50?'rgba(217,119,6,0.7)':'rgba(220,38,38,0.65)');
new Chart(document.getElementById('qChart'), {
  type: 'bar',
  data: {
    labels: qLabels,
    datasets: [{label:'Accuracy %',data:qData,backgroundColor:qColors,borderRadius:4,borderSkipped:false}]
  },
  options: {
    indexAxis:'y', responsive:true, maintainAspectRatio:true,
    plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>`${ctx.parsed.x}% correct`}}},
    scales:{
      x:{grid:{color:'#f1f5f9'},border:{display:false},min:0,max:100,ticks:{callback:v=>v+'%',font:{size:10}}},
      y:{grid:{display:false},border:{display:false},ticks:{font:{size:10}}}
    }
  }
});
<?php else: ?>
// ── Pass/Fail Donut ──────────────────────────────────────────────────────────
new Chart(document.getElementById('pfChart'), {
  type: 'doughnut',
  data: {
    labels: ['Pass (≥75%)', 'Fail (<75%)'],
    datasets: [{
      data: [<?= $passCount ?>, <?= $failCount ?>],
      backgroundColor: ['rgba(22,163,74,0.8)','rgba(220,38,38,0.75)'],
      borderColor: ['#16a34a','#dc2626'],
      borderWidth: 2, hoverOffset: 5
    }]
  },
  options: {
    responsive: false, cutout: '65%',
    plugins: {
      legend:{display:false},
      tooltip:{callbacks:{label:ctx=>`${ctx.label}: ${ctx.parsed}`}}
    }
  },
  plugins:[{
    id:'centerText',
    beforeDraw(chart){
      const {ctx,chartArea:{width,height,top}} = chart;
      ctx.save();
      ctx.font = 'bold 1.3rem Plus Jakarta Sans, sans-serif';
      ctx.fillStyle = '#0f172a';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      const pct = <?= $totalSubs>0?round(($passCount/$totalSubs)*100):0 ?>;
      ctx.fillText(pct+'%', width/2, top+height/2-7);
      ctx.font = '0.62rem Inter, sans-serif';
      ctx.fillStyle = '#94a3b8';
      ctx.fillText('pass rate', width/2, top+height/2+11);
      ctx.restore();
    }
  }]
});
<?php endif; ?>
<?php endif; ?>

// ── Table search ─────────────────────────────────────────────────────────────
function filterTable(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#resultsTable .result-row').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
</script>
</body>
</html>
