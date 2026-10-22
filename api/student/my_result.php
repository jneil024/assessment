<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';
require_once '../includes/student_sidebar.php';
$db = getDB();

$sid = intval($_GET['session_id'] ?? 0);
$session = $db->prepare("SELECT es.*,e.title FROM exam_sessions es JOIN exams e ON e.id=es.exam_id WHERE es.id=? AND es.student_id=?");
$session->execute([$sid, $_SESSION['student_id']]); $session = $session->fetch();
if (!$session) { header('Location: dashboard.php'); exit; }

$answers = $db->prepare("SELECT sa.*,q.question_text,q.type,q.audio_file,q.video_file,q.video_url,q.blanks_count,q.blanks_answers,q.correct_answer,q.points as max_points FROM student_answers sa JOIN questions q ON q.id=sa.question_id WHERE sa.session_id=? ORDER BY q.order_num,q.id");
$answers->execute([$sid]); $answers = $answers->fetchAll();

$pct        = $session['total_points'] > 0 ? round(($session['score'] / $session['total_points']) * 100) : 0;
$scoreColor = $pct>=75 ? '#16a34a' : ($pct>=50 ? '#d97706' : '#dc2626');
$scoreBg    = $pct>=75 ? '#dcfce7' : ($pct>=50 ? '#fef3c7' : '#fee2e2');
$hasPending = count(array_filter($answers, fn($a) => $a['needs_manual_grading'])) > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Southern Baptist College</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f8f9fc; --white:#fff; --text:#0f172a; --text-2:#475569; --text-3:#94a3b8;
  --border:#e2e8f0; --border-2:#cbd5e1; --primary:#0ea5e9;
  --success:#16a34a; --success-bg:#dcfce7;
  --danger:#dc2626; --danger-bg:#fee2e2;
  --warning:#d97706; --warning-bg:#fef3c7;
  --teal:#0d9488; --teal-light:#ccfbf1; --teal-border:#5eead4;
  --shadow:0 1px 3px rgba(0,0,0,0.07);
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}

/* topbar replaced by studentNav */
.logo{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:0.9rem;display:flex;align-items:center;gap:7px;}
.logo-icon{width:26px;height:26px;background:var(--primary);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:0.65rem;color:#fff;font-weight:800;}



.content{max-width:720px;margin:0 auto;padding:26px 18px 60px;}

/* ── RESULT HERO ── */
.result-hero{background:#fff;border:1px solid var(--border);border-radius:16px;padding:32px;text-align:center;margin-bottom:20px;box-shadow:var(--shadow);}
.result-emoji{font-size:2.8rem;margin-bottom:10px;}
.result-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.25rem;font-weight:800;margin-bottom:3px;}
.result-exam{color:var(--text-3);font-size:0.82rem;margin-bottom:20px;}
.score-pct{font-family:'Plus Jakarta Sans',sans-serif;font-size:4.2rem;font-weight:800;line-height:1;}
.score-pts{color:var(--text-3);font-size:0.8rem;margin-top:5px;margin-bottom:14px;}
.pass-pill{display:inline-block;padding:6px 20px;border-radius:999px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:0.8rem;}
.pending-notice{background:var(--warning-bg);border:1px solid #fcd34d;border-radius:9px;padding:11px 15px;font-size:0.81rem;color:#92400e;margin-top:14px;text-align:left;line-height:1.55;}
.btn-dash{display:inline-block;margin-top:18px;padding:9px 22px;background:var(--primary);color:#fff;border-radius:7px;font-weight:700;font-size:0.83rem;text-decoration:none;}

/* ── ANSWER CARDS ── */
.section-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:0.93rem;font-weight:700;margin-bottom:11px;color:var(--text);}
.q-card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:16px 18px;margin-bottom:10px;box-shadow:var(--shadow);}
.q-head{display:flex;align-items:center;gap:7px;margin-bottom:10px;flex-wrap:wrap;}
.q-num{font-size:0.68rem;font-weight:700;padding:2px 7px;background:var(--bg);border:1px solid var(--border);border-radius:4px;color:var(--text-3);}
.type-chip{font-size:0.67rem;font-weight:700;padding:2px 7px;border-radius:4px;}
.status-chip{font-size:0.76rem;font-weight:600;margin-left:auto;}
.pts-chip{font-size:0.7rem;color:var(--text-3);background:var(--bg);border:1px solid var(--border);padding:2px 7px;border-radius:4px;}
.q-text{font-size:0.87rem;font-weight:500;line-height:1.55;margin-bottom:10px;}
.ans-row{display:flex;align-items:baseline;gap:7px;margin-bottom:4px;font-size:0.82rem;}
.ans-lbl{font-size:0.71rem;color:var(--text-3);min-width:88px;}
.correct-txt{color:var(--success);font-weight:600;}
.wrong-txt{color:var(--danger);}
.essay-ans{background:#f8fafc;border:1px solid var(--border);border-radius:7px;padding:11px 13px;font-size:0.84rem;line-height:1.7;color:var(--text-2);white-space:pre-wrap;max-height:150px;overflow-y:auto;margin-bottom:5px;}
.teacher-note{font-size:0.76rem;color:var(--success);margin-top:4px;}
.pending-pill{background:var(--warning-bg);color:var(--warning);padding:2px 8px;border-radius:4px;font-size:0.69rem;font-weight:700;}
audio{width:100%;height:32px;border-radius:6px;margin-bottom:7px;}

/* ── LISTENING RESULT ── */
.listening-result{background:var(--bg);border:1px solid var(--border);border-radius:9px;overflow:hidden;}
.listening-video-wrap{position:relative;padding-top:56.25%;background:#0f172a;}
.listening-video-wrap iframe,.listening-video-wrap video{position:absolute;top:0;left:0;width:100%;height:100%;border:none;}
.blanks-review{padding:13px 15px;}
.blank-review-item{display:flex;align-items:center;gap:9px;margin-bottom:7px;font-size:0.83rem;}
.blank-num-badge{width:22px;height:22px;border-radius:50%;font-size:0.68rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.blank-num-badge.ok{background:var(--success-bg);color:var(--success);}
.blank-num-badge.bad{background:var(--danger-bg);color:var(--danger);}
.blank-student{font-weight:500;}
.blank-correct{color:var(--success);font-size:0.78rem;margin-left:4px;}
.blank-wrong{color:var(--danger);text-decoration:line-through;opacity:.7;}
</style>
</head>
<body>
<?php studentNav('results'); ?>

<div class="content">
  <!-- ── Score Hero ── -->
  <div class="result-hero">
    <div class="result-emoji"><?= $pct>=75?'🏆':($pct>=50?'👍':'📚') ?></div>
    <div class="result-title">Exam Complete!</div>
    <div class="result-exam"><?= htmlspecialchars($session['title']) ?></div>
    <div class="score-pct" style="color:<?= $scoreColor ?>"><?= $pct ?>%</div>
    <div class="score-pts"><?= $session['score'] ?> / <?= $session['total_points'] ?> points</div>
    <span class="pass-pill" style="background:<?= $scoreBg ?>;color:<?= $scoreColor ?>">
      <?= $pct>=75 ? '✅ PASSED' : ($pct>=50 ? '⚠️ AVERAGE' : '❌ NEEDS IMPROVEMENT') ?>
    </span>
    <?php if ($hasPending): ?>
    <div class="pending-notice">
      ⏳ <strong>Your score may change.</strong> One or more essay questions are pending manual grading by your teacher. Check back later for your final score.
    </div>
    <?php endif; ?>
    <div><a href="dashboard.php" class="btn-dash">← Back to Dashboard</a></div>
  </div>

  <!-- ── Answer Review ── -->
  <div class="section-title">Answer Review</div>
  <?php foreach ($answers as $i => $a):
    $typeBadge = [
      'multiple_choice'=>['mc',  '#dbeafe','#1d4ed8'],
      'enumeration'    =>['enum','#dcfce7','#16a34a'],
      'spelling'       =>['spell','#fef3c7','#92400e'],
      'essay'          =>['essay','#ede9fe','#7c3aed'],
      'identification' =>['id',  '#ffedd5','#9a3412'],
      'listening'      =>['listen','#ccfbf1','#0d9488'],
    ][$a['type']] ?? ['?','#f1f5f9','#475569'];

    // Parse listening blanks
    $studentBlanks  = ($a['type']==='listening' && $a['answer']) ? json_decode($a['answer'],true) : [];
    $correctBlanks  = ($a['type']==='listening' && $a['blanks_answers']) ? json_decode($a['blanks_answers'],true) : [];
    $blanksCount    = max(1, intval($a['blanks_count'] ?? 1));
  ?>
  <div class="q-card">
    <div class="q-head">
      <span class="q-num">Q<?= $i+1 ?></span>
      <span class="type-chip" style="background:<?= $typeBadge[1] ?>;color:<?= $typeBadge[2] ?>">
        <?= strtoupper(str_replace('_',' ',$a['type'])) ?>
      </span>

      <?php if ($a['needs_manual_grading']): ?>
        <span class="pending-pill">⏳ Pending</span>
      <?php elseif ($a['type']==='essay'): ?>
        <span class="status-chip" style="color:var(--success)">✅ Graded</span>
      <?php elseif ($a['type']==='listening'): ?>
        <?php
          $cb = 0;
          foreach ($studentBlanks as $bi=>$sb) { if (strtolower($sb)===strtolower($correctBlanks[$bi]??'')) $cb++; }
        ?>
        <span class="status-chip" style="color:<?= $cb===$blanksCount?'var(--success)':($cb>0?'var(--warning)':'var(--danger)') ?>">
          <?= $cb ?>/<?= $blanksCount ?> correct
        </span>
      <?php else: ?>
        <span class="status-chip" style="color:<?= $a['is_correct']?'var(--success)':'var(--danger)' ?>">
          <?= $a['is_correct'] ? '✓ Correct' : '✗ Incorrect' ?>
        </span>
      <?php endif; ?>

      <span class="pts-chip" style="margin-left:auto">
        <?= $a['needs_manual_grading'] ? '—' : $a['points_earned'] ?> / <?= $a['max_points'] ?> pts
      </span>
    </div>

    <div class="q-text"><?= htmlspecialchars($a['question_text']) ?></div>

    <?php if ($a['type']==='essay'): ?>
      <div class="essay-ans"><?= htmlspecialchars($a['answer'] ?: '(no answer submitted)') ?></div>
      <?php if ($a['needs_manual_grading']): ?>
        <div style="font-size:0.77rem;color:var(--warning)">⏳ Your teacher will grade this soon.</div>
      <?php elseif (!empty($a['grader_notes'])): ?>
        <div class="teacher-note">💬 Teacher: <?= htmlspecialchars($a['grader_notes']) ?></div>
      <?php endif; ?>

    <?php elseif ($a['type']==='listening'): ?>
      <div class="listening-result">
        <?php
          $vidSrc  = ''; $isEmbed = false;
          if (!empty($a['video_file'])) { $vidSrc = VIDEO_URL . htmlspecialchars($a['video_file']); }
          elseif (!empty($a['video_url'])) {
            $url = $a['video_url'];
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_\-]{11})/',$url,$m)) {
                $vidSrc='https://www.youtube.com/embed/'.$m[1]; $isEmbed=true;
            } elseif (preg_match('/vimeo\.com\/(\d+)/',$url,$m)) {
                $vidSrc='https://player.vimeo.com/video/'.$m[1]; $isEmbed=true;
            } else { $vidSrc=htmlspecialchars($url); }
          }
        ?>
        <?php if ($vidSrc): ?>
        <div class="listening-video-wrap">
          <?php if ($isEmbed): ?>
            <iframe src="<?= $vidSrc ?>" allowfullscreen></iframe>
          <?php else: ?>
            <video controls><source src="<?= $vidSrc ?>"></video>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="blanks-review">
          <?php for ($b=0; $b<$blanksCount; $b++):
            $sa = $studentBlanks[$b] ?? '';
            $ca = $correctBlanks[$b] ?? '';
            $ok = strtolower($sa) === strtolower($ca);
          ?>
          <div class="blank-review-item">
            <div class="blank-num-badge <?= $ok?'ok':'bad' ?>"><?= $b+1 ?></div>
            <span class="blank-student <?= $ok?'correct-txt':'wrong-txt' ?>"><?= htmlspecialchars($sa ?: '(blank)') ?></span>
            <?php if (!$ok && $ca): ?>
              <span class="blank-correct">→ <?= htmlspecialchars($ca) ?></span>
            <?php endif; ?>
          </div>
          <?php endfor; ?>
        </div>
      </div>

    <?php elseif ($a['type']==='enumeration'): ?>
      <?php
        $enumCorrects = array_map('trim', explode(',', $a['correct_answer']));
        $enumStudent  = $a['answer'] ? array_map('trim', explode(',', $a['answer'])) : [];
        $enumCount    = count($enumCorrects);
      ?>
      <div style="display:flex;flex-direction:column;gap:7px">
        <?php for ($ei=0; $ei<$enumCount; $ei++):
          $sa = $enumStudent[$ei] ?? '';
          $ca = $enumCorrects[$ei] ?? '';
          $ok = $sa !== '' && strtolower($sa) === strtolower($ca);
        ?>
        <div style="display:flex;align-items:center;gap:8px;font-size:0.83rem">
          <div style="width:22px;height:22px;border-radius:50%;font-size:0.68rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:<?= $ok?'var(--success-bg)':'var(--danger-bg)' ?>;color:<?= $ok?'var(--success)':'var(--danger)' ?>;border:1px solid <?= $ok?'#bbf7d0':'#fecaca' ?>"><?= $ei+1 ?></div>
          <span style="font-weight:500;color:<?= $ok?'var(--success)':'var(--danger)' ?>"><?= htmlspecialchars($sa ?: '(blank)') ?></span>
          <?php if (!$ok && $ca): ?><span style="font-size:0.77rem;color:var(--success)">→ <?= htmlspecialchars($ca) ?></span><?php endif; ?>
        </div>
        <?php endfor; ?>
      </div>
    <?php else: ?>
      <?php if ($a['audio_file']): ?>
        <audio controls><source src="<?= UPLOAD_URL.htmlspecialchars($a['audio_file']) ?>"></audio>
      <?php endif; ?>
      <div class="ans-row"><span class="ans-lbl">Your answer:</span><span class="<?= $a['is_correct']?'correct-txt':'wrong-txt' ?>"><?= htmlspecialchars($a['answer'] ?: '(no answer)') ?></span></div>
      <?php if (!$a['is_correct'] && $a['correct_answer'] && !in_array($a['correct_answer'],['(manual grading)','(listening)','(enumeration)'])): ?>
      <div class="ans-row"><span class="ans-lbl">Correct answer:</span><span class="correct-txt"><?= htmlspecialchars($a['correct_answer']) ?></span></div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
</body>
</html>
