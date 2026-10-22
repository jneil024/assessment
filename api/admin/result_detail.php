<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';
require_once 'includes.php';

$db = getDB();
$sid = intval($_GET['session_id'] ?? 0);
$session = $db->prepare("SELECT es.*,s.name,s.student_id as sid,e.title FROM exam_sessions es JOIN students s ON s.id=es.student_id JOIN exams e ON e.id=es.exam_id WHERE es.id=?");
$session->execute([$sid]); $session = $session->fetch();
if (!$session) { header('Location: results.php'); exit; }

$answers = $db->prepare("SELECT sa.*,q.question_text,q.type,q.choices,q.audio_file,q.video_file,q.video_url,q.blanks_count,q.blanks_answers,q.points as max_points,q.correct_answer FROM student_answers sa JOIN questions q ON q.id=sa.question_id WHERE sa.session_id=? ORDER BY q.order_num,q.id");
$answers->execute([$sid]); $answers = $answers->fetchAll();

$pct   = $session['total_points']>0 ? round(($session['score']/$session['total_points'])*100) : 0;
$color = $pct>=75?'#16a34a':($pct>=50?'#d97706':'#dc2626');
$bg    = $pct>=75?'#dcfce7':($pct>=50?'#fef3c7':'#fee2e2');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Result Detail — Southern Baptist College</title>
<?php adminCSS(); ?>
<style>
.detail-wrap{margin-left:220px;}
.top-bar{background:#fff;border-bottom:1px solid var(--border);padding:12px 28px;display:flex;align-items:center;gap:14px;}
.content{max-width:760px;margin:0 auto;padding:24px 20px 60px;}
.result-hero{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;text-align:center;margin-bottom:20px;box-shadow:var(--shadow);}
.student-name{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.15rem;font-weight:800;margin-bottom:3px;}
.exam-sub{color:var(--text-3);font-size:0.82rem;margin-bottom:16px;}
.score-big{font-family:'Plus Jakarta Sans',sans-serif;font-size:3.2rem;font-weight:800;line-height:1;}
.score-label{color:var(--text-3);font-size:0.78rem;margin-top:4px;margin-bottom:12px;}
.pass-badge{display:inline-block;padding:5px 16px;border-radius:999px;font-weight:700;font-size:0.8rem;}
.q-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px 18px;margin-bottom:10px;box-shadow:var(--shadow);}
.q-head{display:flex;align-items:center;gap:7px;margin-bottom:9px;flex-wrap:wrap;}
.q-num-b{font-size:0.68rem;font-weight:700;padding:2px 7px;background:var(--bg);border:1px solid var(--border);border-radius:4px;color:var(--text-3);}
.ans-row{display:flex;align-items:flex-start;gap:7px;margin-bottom:4px;font-size:0.82rem;}
.albl{color:var(--text-3);min-width:80px;font-size:0.73rem;padding-top:1px;}
.correct{color:var(--success);font-weight:600;} .wrong{color:var(--danger);}
audio{width:100%;height:32px;border-radius:6px;margin-bottom:7px;}
.essay-ans{background:var(--bg);border:1px solid var(--border);border-radius:7px;padding:11px 13px;font-size:0.84rem;line-height:1.7;white-space:pre-wrap;color:var(--text-2);max-height:150px;overflow-y:auto;margin-bottom:5px;}
/* Listening */
.video-wrap{position:relative;padding-top:56.25%;background:#0f172a;border-radius:8px;overflow:hidden;margin-bottom:10px;}
.video-wrap iframe,.video-wrap video{position:absolute;top:0;left:0;width:100%;height:100%;border:none;}
.blank-item{display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:0.82rem;}
.blank-badge{width:20px;height:20px;border-radius:50%;font-size:0.66rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.blank-badge.ok{background:var(--success-bg);color:var(--success);}
.blank-badge.bad{background:var(--danger-bg);color:var(--danger);}
.corr-ans{color:var(--success);font-size:0.77rem;margin-left:4px;}
</style>
</head>
<body>
<div class="detail-wrap">
  <div class="top-bar">
    <a href="results.php" style="color:var(--primary);text-decoration:none;font-size:0.82rem;font-weight:600">← Results</a>
    <span style="color:var(--border-2)">|</span>
    <span style="font-size:0.83rem;color:var(--text-2)">Detailed Result</span>
  </div>
  <div class="content">
    <div class="result-hero">
      <div class="student-name"><?= htmlspecialchars($session['name']) ?></div>
      <div class="exam-sub"><?= htmlspecialchars($session['title']) ?> &nbsp;·&nbsp; ID: <?= htmlspecialchars($session['sid']) ?></div>
      <div class="score-big" style="color:<?= $color ?>"><?= $pct ?>%</div>
      <div class="score-label"><?= $session['score'] ?> / <?= $session['total_points'] ?> pts</div>
      <span class="pass-badge" style="background:<?= $bg ?>;color:<?= $color ?>">
        <?= $pct>=75?'✅ PASSED':($pct>=50?'⚠️ AVERAGE':'❌ NEEDS IMPROVEMENT') ?>
      </span>
    </div>

    <?php foreach ($answers as $i => $a):
      $blanksCount   = max(1,intval($a['blanks_count']??1));
      $studentBlanks = ($a['type']==='listening'&&$a['answer']) ? json_decode($a['answer'],true) : [];
      $correctBlanks = ($a['type']==='listening'&&$a['blanks_answers']) ? json_decode($a['blanks_answers'],true) : [];
    ?>
    <div class="q-card">
      <div class="q-head">
        <span class="q-num-b">Q<?= $i+1 ?></span>
        <span class="badge <?= ['multiple_choice'=>'badge-mc','enumeration'=>'badge-enum','spelling'=>'badge-spelling','essay'=>'badge-essay','identification'=>'badge-ident','listening'=>'badge-listening'][$a['type']]??'badge-mc' ?>"><?= strtoupper(str_replace('_',' ',$a['type'])) ?></span>

        <?php if ($a['needs_manual_grading']??false): ?>
          <span class="badge" style="background:#fef3c7;color:#92400e">⏳ Pending</span>
        <?php elseif ($a['type']==='listening'): ?>
          <?php $cb=0; foreach($studentBlanks as $bi=>$sb){if(strtolower($sb)===strtolower($correctBlanks[$bi]??''))$cb++;} ?>
          <span style="font-size:0.78rem;font-weight:600;color:<?= $cb===$blanksCount?'var(--success)':($cb>0?'var(--warning)':'var(--danger)') ?>"><?= $cb ?>/<?= $blanksCount ?> correct</span>
        <?php elseif ($a['type']==='essay'): ?>
          <span style="color:var(--success);font-size:0.78rem;font-weight:600">✅ Graded</span>
        <?php else: ?>
          <?= $a['is_correct']?'<span style="color:var(--success);font-size:0.78rem;font-weight:600">✓ Correct</span>':'<span style="color:var(--danger);font-size:0.78rem;font-weight:600">✗ Incorrect</span>' ?>
        <?php endif; ?>
        <span style="margin-left:auto;font-size:0.72rem;color:var(--text-3)"><?= ($a['needs_manual_grading']??0)?'—':$a['points_earned'] ?>/<?= $a['max_points'] ?> pts</span>
      </div>

      <div style="font-size:0.87rem;font-weight:500;line-height:1.5;margin-bottom:9px"><?= htmlspecialchars($a['question_text']) ?></div>

      <?php if ($a['type']==='essay'): ?>
        <div class="essay-ans"><?= htmlspecialchars($a['answer']?:'(no answer)') ?></div>
        <?php if (!empty($a['grader_notes'])): ?><div style="font-size:0.76rem;color:var(--success)">💬 <?= htmlspecialchars($a['grader_notes']) ?></div><?php endif; ?>

      <?php elseif ($a['type']==='listening'): ?>
        <?php
          $vidSrc=''; $isEmbed=false;
          if (!empty($a['video_file'])) { $vidSrc = VIDEO_URL.htmlspecialchars($a['video_file']); }
          elseif (!empty($a['video_url'])) {
            $url=$a['video_url'];
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_\-]{11})/',$url,$m)){ $vidSrc='https://www.youtube.com/embed/'.$m[1]; $isEmbed=true; }
            elseif (preg_match('/vimeo\.com\/(\d+)/',$url,$m)){ $vidSrc='https://player.vimeo.com/video/'.$m[1]; $isEmbed=true; }
            else { $vidSrc=htmlspecialchars($url); }
          }
        ?>
        <?php if ($vidSrc): ?>
        <div class="video-wrap">
          <?php if ($isEmbed): ?><iframe src="<?= $vidSrc ?>" allowfullscreen></iframe>
          <?php else: ?><video controls><source src="<?= $vidSrc ?>"></video><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php for ($b=0;$b<$blanksCount;$b++):
          $sa=strtolower($studentBlanks[$b]??''); $ca=strtolower($correctBlanks[$b]??''); $ok=($sa===$ca&&$sa!=='');
        ?>
        <div class="blank-item">
          <div class="blank-badge <?= $ok?'ok':'bad' ?>"><?= $b+1 ?></div>
          <span style="font-weight:500;<?= $ok?'color:var(--success)':'color:var(--danger)' ?>"><?= htmlspecialchars($studentBlanks[$b]??'(blank)') ?></span>
          <?php if (!$ok&&$ca): ?><span class="corr-ans">→ <?= htmlspecialchars($correctBlanks[$b]) ?></span><?php endif; ?>
        </div>
        <?php endfor; ?>

      <?php elseif ($a['type']==='enumeration'): ?>
        <?php
          $enumC = array_map('trim', explode(',', $a['correct_answer']));
          $enumS = $a['answer'] ? array_map('trim', explode(',', $a['answer'])) : [];
        ?>
        <div style="display:flex;flex-direction:column;gap:6px">
          <?php foreach($enumC as $ei=>$cv):
            $sv = $enumS[$ei] ?? '';
            $ok = $sv!==''&&strtolower($sv)===strtolower($cv);
          ?>
          <div style="display:flex;align-items:center;gap:7px;font-size:0.82rem">
            <div style="width:20px;height:20px;border-radius:50%;font-size:0.65rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:<?= $ok?'var(--success-bg)':'var(--danger-bg)' ?>;color:<?= $ok?'var(--success)':'var(--danger)' ?>"><?= $ei+1 ?></div>
            <span style="font-weight:500;color:<?= $ok?'var(--success)':'var(--danger)' ?>"><?= htmlspecialchars($sv?:'(blank)') ?></span>
            <?php if(!$ok&&$cv): ?><span style="font-size:0.76rem;color:var(--success)">→ <?= htmlspecialchars($cv) ?></span><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <?php if ($a['audio_file']): ?><audio controls><source src="<?= UPLOAD_URL.htmlspecialchars($a['audio_file']) ?>"></audio><?php endif; ?>
        <div class="ans-row"><span class="albl">Student:</span><span class="<?= $a['is_correct']?'correct':'wrong' ?>"><?= htmlspecialchars($a['answer']?:'(no answer)') ?></span></div>
        <?php if (!$a['is_correct']&&!in_array($a['correct_answer'],['(manual grading)','(listening)','(enumeration)'])): ?>
        <div class="ans-row"><span class="albl">Correct:</span><span class="correct"><?= htmlspecialchars($a['correct_answer']??'') ?></span></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
