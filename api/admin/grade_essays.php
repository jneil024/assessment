<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';
require_once 'includes.php';

$db = getDB();

// Handle grade submission
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['grade_answer'])) {
    $answerId = intval($_POST['answer_id']);
    $pts      = floatval($_POST['points_awarded']);
    $notes    = trim($_POST['grader_notes']??'');
    // Clamp
    $maxQ = $db->prepare("SELECT q.points FROM student_answers sa JOIN questions q ON q.id=sa.question_id WHERE sa.id=?");
    $maxQ->execute([$answerId]); $maxPts = floatval($maxQ->fetchColumn());
    $pts = min(max(0,$pts), $maxPts);
    $isCorrect = ($pts>0)?1:0;
    $db->prepare("UPDATE student_answers SET points_earned=?,is_correct=?,needs_manual_grading=0,grader_notes=? WHERE id=?")
       ->execute([$pts,$isCorrect,$notes,$answerId]);
    // Recalc session score
    $sessId = $db->prepare("SELECT session_id FROM student_answers WHERE id=?"); $sessId->execute([$answerId]); $sessId=$sessId->fetchColumn();
    $total  = $db->prepare("SELECT SUM(points_earned) FROM student_answers WHERE session_id=?"); $total->execute([$sessId]);
    $db->prepare("UPDATE exam_sessions SET score=? WHERE id=?")->execute([$total->fetchColumn(),$sessId]);
    flash('success','Essay graded and score updated!');
    header('Location: grade_essays.php'); exit;
}

$pending = $db->query("
    SELECT sa.id as answer_id, sa.answer, sa.session_id,
           q.question_text, q.essay_rubric, q.points as max_points, q.min_words,
           s.name as student_name, s.student_id as sid,
           e.title as exam_title, es.submitted_at
    FROM student_answers sa
    JOIN questions q ON q.id=sa.question_id
    JOIN exam_sessions es ON es.id=sa.session_id
    JOIN students s ON s.id=es.student_id
    JOIN exams e ON e.id=es.exam_id
    WHERE q.type='essay' AND sa.needs_manual_grading=1
    ORDER BY es.submitted_at ASC
")->fetchAll();

$graded = $db->query("
    SELECT sa.id as answer_id, sa.answer, sa.points_earned, sa.grader_notes,
           q.question_text, q.points as max_points,
           s.name as student_name, e.title as exam_title, es.submitted_at
    FROM student_answers sa
    JOIN questions q ON q.id=sa.question_id
    JOIN exam_sessions es ON es.id=sa.session_id
    JOIN students s ON s.id=es.student_id
    JOIN exams e ON e.id=es.exam_id
    WHERE q.type='essay' AND sa.needs_manual_grading=0
    ORDER BY es.submitted_at DESC LIMIT 30
")->fetchAll();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Grade Essays — Southern Baptist College</title>
<?php adminCSS(); ?>
<style>
.essay-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);margin-bottom:18px;overflow:hidden;box-shadow:var(--shadow);}
.essay-head{background:#f8fafc;border-bottom:1px solid var(--border);padding:13px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
.student-chip{display:flex;align-items:center;gap:8px;}
.avatar{width:30px;height:30px;border-radius:50%;background:var(--primary);color:#fff;font-weight:700;font-size:0.75rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.student-name{font-weight:600;font-size:0.86rem;}
.student-meta{font-size:0.73rem;color:var(--text-3);}
.exam-badge{background:var(--primary-light);color:var(--primary);padding:3px 9px;border-radius:4px;font-size:0.72rem;font-weight:600;}
.essay-body{padding:18px;}
.q-label{font-size:0.7rem;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:5px;}
.q-text{font-size:0.88rem;font-weight:600;color:var(--text);margin-bottom:12px;line-height:1.5;}
.rubric-box{background:#fefce8;border:1px solid #fde68a;border-radius:7px;padding:10px 13px;margin-bottom:12px;font-size:0.8rem;color:#713f12;line-height:1.6;}
.answer-box{background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:13px 15px;font-size:0.88rem;line-height:1.75;color:var(--text);white-space:pre-wrap;word-break:break-word;max-height:260px;overflow-y:auto;margin-bottom:9px;}
.wc-row{display:flex;align-items:center;gap:10px;margin-bottom:14px;font-size:0.76rem;color:var(--text-3);}
.wc-ok{color:var(--success);font-weight:700;}
.wc-fail{color:var(--danger);font-weight:700;}
.grade-panel{background:#f1f5f9;border:1px solid var(--border);border-radius:var(--radius);padding:14px;}
.grade-row{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;}
.pts-group{flex:0 0 130px;}
.pts-group input{font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:700;text-align:center;color:var(--primary);}
.notes-group{flex:1;min-width:180px;}
.btn-grade{background:var(--primary);color:#fff;padding:9px 20px;border-radius:7px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:0.83rem;border:none;cursor:pointer;white-space:nowrap;}
.btn-grade:hover{background:var(--primary-dark);}
.quick-btns{display:flex;gap:5px;margin-top:6px;flex-wrap:wrap;}
.quick-btn{background:#e0e7ff;color:var(--primary);border:1px solid #c7d2fe;padding:3px 8px;border-radius:4px;font-size:0.72rem;font-weight:600;cursor:pointer;}
.quick-btn:hover{background:#c7d2fe;}
.graded-row{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:14px 17px;margin-bottom:8px;display:flex;align-items:flex-start;gap:12px;box-shadow:var(--shadow);}
.graded-score{flex-shrink:0;text-align:center;background:var(--success-bg);border:1px solid #bbf7d0;border-radius:7px;padding:7px 12px;min-width:64px;}
.graded-score .pts{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:1.05rem;color:var(--success);}
.graded-score .of{font-size:0.68rem;color:var(--text-3);}
.graded-info{flex:1;}
.graded-q{font-size:0.83rem;font-weight:600;margin-bottom:2px;}
.graded-meta{font-size:0.73rem;color:var(--text-3);}
.graded-note{font-size:0.76rem;color:var(--success);margin-top:3px;}
.sec-head{display:flex;align-items:center;gap:8px;margin-bottom:14px;}
.sec-head h2{font-family:'Plus Jakarta Sans',sans-serif;font-size:0.95rem;font-weight:700;}
.pill{background:var(--primary-light);color:var(--primary);padding:2px 8px;border-radius:999px;font-size:0.72rem;font-weight:700;}
.pill-warn{background:var(--warning-bg);color:var(--warning);}
</style>
</head>
<body>
<?php adminSidebar('essays'); ?>
<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Grade Essays</div>
      <div class="page-sub"><?= count($pending) ?> pending &nbsp;·&nbsp; <?= count($graded) ?> recently graded</div>
    </div>
  </div>
  <?php if ($flash): ?><div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>

  <!-- PENDING -->
  <div class="sec-head">
    <h2>Pending Review</h2>
    <?php if (count($pending)>0): ?><span class="pill pill-warn"><?= count($pending) ?> to grade</span><?php endif; ?>
  </div>

  <?php if (empty($pending)): ?>
  <div class="card" style="margin-bottom:26px"><div class="empty">🎉 All essays have been graded.</div></div>
  <?php else: ?>
    <?php foreach ($pending as $a):
      $wc = $a['answer'] ? str_word_count(strip_tags($a['answer'])) : 0;
      $minOk = !$a['min_words'] || $wc >= $a['min_words'];
    ?>
    <div class="essay-card">
      <div class="essay-head">
        <div class="student-chip">
          <div class="avatar"><?= strtoupper(substr($a['student_name'],0,1)) ?></div>
          <div>
            <div class="student-name"><?= htmlspecialchars($a['student_name']) ?></div>
            <div class="student-meta">ID: <?= htmlspecialchars($a['sid']) ?> &nbsp;·&nbsp; <?= $a['submitted_at'] ? date('M d, Y g:i A',strtotime($a['submitted_at'])) : '—' ?></div>
          </div>
        </div>
        <span class="exam-badge"><?= htmlspecialchars($a['exam_title']) ?></span>
      </div>
      <div class="essay-body">
        <div class="q-label">Question</div>
        <div class="q-text"><?= htmlspecialchars($a['question_text']) ?></div>
        <?php if ($a['essay_rubric']): ?>
        <div class="rubric-box">📋 <strong>Grading Rubric:</strong><br><?= nl2br(htmlspecialchars($a['essay_rubric'])) ?></div>
        <?php endif; ?>
        <div class="q-label">Student's Answer</div>
        <div class="answer-box"><?= $a['answer'] ? nl2br(htmlspecialchars($a['answer'])) : '<em style="color:var(--text-3)">(No answer submitted)</em>' ?></div>
        <div class="wc-row">
          <span>Word count: <span class="<?= $minOk?'wc-ok':'wc-fail' ?>"><?= $wc ?></span></span>
          <?php if ($a['min_words']): ?><span>Required: <strong><?= $a['min_words'] ?></strong></span><?php if (!$minOk): ?><span class="wc-fail">⚠️ Below minimum</span><?php endif; ?><?php endif; ?>
          <span style="margin-left:auto">Max: <strong><?= $a['max_points'] ?> pts</strong></span>
        </div>
        <div class="grade-panel">
          <div style="font-size:0.77rem;font-weight:700;color:var(--text-2);margin-bottom:10px;text-transform:uppercase;letter-spacing:0.4px">Assign Grade</div>
          <form method="POST">
            <input type="hidden" name="grade_answer" value="1">
            <input type="hidden" name="answer_id" value="<?= $a['answer_id'] ?>">
            <div class="grade-row">
              <div class="pts-group">
                <label>Points (0 – <?= $a['max_points'] ?>)</label>
                <input type="number" name="points_awarded" id="pts_<?= $a['answer_id'] ?>" min="0" max="<?= $a['max_points'] ?>" step="0.5" placeholder="0" required>
                <div class="quick-btns">
                  <?php $mp=$a['max_points']; foreach(array_unique([0,round($mp*.25),round($mp*.5),round($mp*.75),$mp]) as $qv): ?>
                  <button type="button" class="quick-btn" onclick="document.getElementById('pts_<?= $a['answer_id'] ?>').value=<?= $qv ?>"><?= $qv ?>pt<?= $qv!=1?'s':'' ?></button>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="notes-group">
                <label>Feedback / Notes <span style="font-weight:400;color:var(--text-3)">(optional)</span></label>
                <input type="text" name="grader_notes" placeholder="e.g. Good thesis, needs more supporting details">
              </div>
              <button type="submit" class="btn-grade">Submit Grade ✓</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- GRADED -->
  <?php if (!empty($graded)): ?>
  <div class="sec-head" style="margin-top:26px">
    <h2>Recently Graded</h2>
    <span class="pill"><?= count($graded) ?></span>
  </div>
  <?php foreach ($graded as $a): ?>
  <div class="graded-row">
    <div class="graded-score"><div class="pts"><?= $a['points_earned'] ?></div><div class="of">/ <?= $a['max_points'] ?> pts</div></div>
    <div class="graded-info">
      <div class="graded-q"><?= htmlspecialchars(mb_substr($a['question_text'],0,90)) ?><?= mb_strlen($a['question_text'])>90?'…':'' ?></div>
      <div class="graded-meta">👤 <?= htmlspecialchars($a['student_name']) ?> &nbsp;·&nbsp; 📝 <?= htmlspecialchars($a['exam_title']) ?></div>
      <?php if ($a['grader_notes']): ?><div class="graded-note">💬 <?= htmlspecialchars($a['grader_notes']) ?></div><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</main>
</body>
</html>
