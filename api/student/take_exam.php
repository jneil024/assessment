<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';
$db = getDB();

$examId = intval($_GET['exam_id'] ?? 0);
$exam = $db->prepare("SELECT * FROM exams WHERE id=? AND is_active=1");
$exam->execute([$examId]); $exam = $exam->fetch();
if (!$exam) { header('Location: dashboard.php'); exit; }

// Already submitted?
$done = $db->prepare("SELECT id FROM exam_sessions WHERE student_id=? AND exam_id=? AND status='submitted'");
$done->execute([$_SESSION['student_id'], $examId]);
if ($done->fetch()) { header('Location: dashboard.php'); exit; }

// ── Handle submission ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $questions = $db->prepare("SELECT * FROM questions WHERE exam_id=? ORDER BY order_num,id");
    $questions->execute([$examId]); $questions = $questions->fetchAll();

    $db->prepare("INSERT INTO exam_sessions (student_id,exam_id,status,submitted_at) VALUES (?,?,'submitted',NOW())")
       ->execute([$_SESSION['student_id'], $examId]);
    $sessionId = $db->lastInsertId();
    $totalPoints = 0; $earned = 0;

    foreach ($questions as $q) {
        $totalPoints += $q['points'];
        $correct = false; $needsManual = 0; $pts = 0; $savedAns = '';

        if ($q['type'] === 'listening') {
            // Collect each blank answer as JSON array
            $blanksAnswers = $q['blanks_answers'] ? json_decode($q['blanks_answers'], true) : [];
            $blanksCount   = max(1, intval($q['blanks_count']));
            $studentBlanks = [];
            for ($b = 1; $b <= $blanksCount; $b++) {
                $studentBlanks[] = trim($_POST["q_{$q['id']}_blank_{$b}"] ?? '');
            }
            $savedAns = json_encode($studentBlanks);

            // Grade each blank equally
            $correctCount = 0;
            foreach ($studentBlanks as $bi => $sb) {
                $expected = strtolower($blanksAnswers[$bi] ?? '');
                if ($expected !== '' && strtolower($sb) === $expected) $correctCount++;
            }
            $ptsPerBlank = $blanksCount > 0 ? $q['points'] / $blanksCount : 0;
            $pts   = round($ptsPerBlank * $correctCount, 2);
            $correct = ($correctCount === $blanksCount);
            $earned += $pts;

        } elseif ($q['type'] === 'essay') {
            $savedAns    = trim($_POST["q_{$q['id']}"] ?? '');
            $needsManual = 1; $pts = 0;

        } elseif ($q['type'] === 'enumeration') {
            // Collect per-item inputs: q_{id}_item_{n}
            $cl = array_map('trim', explode(',', strtolower($q['correct_answer'])));
            $studentItems = [];
            foreach ($cl as $ci => $cv) {
                $studentItems[] = strtolower(trim($_POST["q_{$q['id']}_item_" . ($ci+1)] ?? ''));
            }
            $savedAns = implode(',', $studentItems);
            $correctCount = 0;
            foreach ($cl as $ci => $cv) {
                if ($cv !== '' && ($studentItems[$ci] ?? '') === $cv) $correctCount++;
            }
            $pts     = $correctCount; // 1 pt per correct item
            $correct = ($correctCount === count($cl));
            $earned += $pts;

        } else {
            // multiple_choice, identification, spelling
            $savedAns = trim($_POST["q_{$q['id']}"] ?? '');
            $correct  = (strtolower($savedAns) === strtolower($q['correct_answer']));
            $pts      = $correct ? $q['points'] : 0;
            $earned  += $pts;
        }

        $db->prepare("INSERT INTO student_answers (session_id,question_id,answer,is_correct,points_earned,needs_manual_grading) VALUES (?,?,?,?,?,?)")
           ->execute([$sessionId, $q['id'], $savedAns, $correct ? 1 : 0, $pts, $needsManual]);
    }

    $db->prepare("UPDATE exam_sessions SET score=?,total_points=? WHERE id=?")
       ->execute([$earned, $totalPoints, $sessionId]);
    header("Location: my_result.php?session_id=$sessionId"); exit;
}

// ── Start / resume session ────────────────────────────────────────────────────
$active = $db->prepare("SELECT * FROM exam_sessions WHERE student_id=? AND exam_id=? AND status='in_progress'");
$active->execute([$_SESSION['student_id'], $examId]); $active = $active->fetch();
if (!$active) {
    $db->prepare("INSERT INTO exam_sessions (student_id,exam_id,status) VALUES (?,?,'in_progress')")
       ->execute([$_SESSION['student_id'], $examId]);
    $startedAt = time();
} else {
    $startedAt = strtotime($active['started_at']);
}
$endTime = $startedAt + ($exam['duration_minutes'] * 60);

$questions = $db->prepare("SELECT * FROM questions WHERE exam_id=? ORDER BY order_num,id");
$questions->execute([$examId]); $questions = $questions->fetchAll();
$totalQ = count($questions);

$typeOrder = ['multiple_choice','enumeration','spelling','essay','identification','listening'];
$typeInfo  = [
    'multiple_choice' => ['label'=>'Multiple Choice', 'icon'=>'🔘', 'color'=>'#2563eb', 'bg'=>'#dbeafe'],
    'enumeration'     => ['label'=>'Enumeration',     'icon'=>'📋', 'color'=>'#16a34a', 'bg'=>'#dcfce7'],
    'spelling'        => ['label'=>'Spelling',        'icon'=>'🔊', 'color'=>'#d97706', 'bg'=>'#fef3c7'],
    'essay'           => ['label'=>'Essay',           'icon'=>'✍️',  'color'=>'#7c3aed', 'bg'=>'#ede9fe'],
    'identification'  => ['label'=>'Identification',  'icon'=>'✏️',  'color'=>'#ea580c', 'bg'=>'#ffedd5'],
    'listening'       => ['label'=>'Listening',       'icon'=>'🎬', 'color'=>'#0d9488', 'bg'=>'#ccfbf1'],
];
$groups = [];
foreach ($questions as $q) { $groups[$q['type']][] = $q; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($exam['title']) ?> — Southern Baptist College</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f8f9fc; --white:#fff; --text:#0f172a; --text-2:#475569; --text-3:#94a3b8;
  --border:#e2e8f0; --border-2:#cbd5e1;
  --primary:#0ea5e9; --primary-dark:#0284c7;
  --teal:#0d9488; --teal-light:#ccfbf1; --teal-border:#5eead4;
  --shadow:0 1px 3px rgba(0,0,0,0.07);
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}

/* ── TOP BAR ── */
.exam-header{position:sticky;top:0;z-index:100;background:#fff;border-bottom:1px solid var(--border);padding:11px 32px;display:flex;align-items:center;justify-content:space-between;box-shadow:var(--shadow);}
.exam-title-h{font-family:'Plus Jakarta Sans',sans-serif;font-size:0.93rem;font-weight:800;}
.exam-sub{font-size:0.73rem;color:var(--text-3);}
.timer-wrap{display:flex;align-items:center;gap:7px;}
.timer-label{font-size:0.7rem;color:var(--text-3);}
.timer{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.35rem;font-weight:800;color:var(--primary);}
.timer.warning{color:#d97706;}
.timer.danger{color:#dc2626;animation:pulse .6s infinite;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
.prog-bar-top{height:3px;background:var(--border);position:fixed;top:0;left:0;right:0;z-index:200;}
.prog-fill{height:100%;background:var(--primary);transition:width .4s;}

/* ── LAYOUT ── */
.content{max-width:780px;margin:0 auto;padding:26px 18px 120px;}
.q-section{margin-bottom:30px;}
.section-head{display:flex;align-items:center;gap:7px;margin-bottom:13px;padding-bottom:9px;border-bottom:2px solid;}
.section-head h3{font-family:'Plus Jakarta Sans',sans-serif;font-size:0.88rem;font-weight:800;}
.sec-count{font-size:0.68rem;background:var(--bg);border:1px solid var(--border);padding:2px 7px;border-radius:4px;color:var(--text-3);font-weight:600;}

/* ── QUESTION CARD ── */
.q-card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:18px 20px;margin-bottom:11px;box-shadow:var(--shadow);transition:box-shadow .15s;}
.q-card:focus-within{box-shadow:0 0 0 2px rgba(14,165,233,.18),var(--shadow);}
.q-head{display:flex;align-items:center;gap:7px;margin-bottom:11px;}
.q-num{font-size:0.69rem;font-weight:700;padding:2px 7px;border-radius:4px;}
.q-pts{font-size:0.69rem;color:var(--text-3);margin-left:auto;}
.q-text{font-size:0.89rem;font-weight:500;line-height:1.6;margin-bottom:13px;}

/* ── MULTIPLE CHOICE ── */
.choices{display:grid;grid-template-columns:1fr 1fr;gap:7px;}
@media(max-width:500px){.choices{grid-template-columns:1fr;}}
.choice-lbl{display:flex;align-items:center;gap:8px;padding:9px 13px;background:var(--bg);border:1px solid var(--border);border-radius:7px;cursor:pointer;font-size:0.85rem;transition:all .15s;}
.choice-lbl:hover{border-color:#bae6fd;background:#f0f9ff;}
.choice-lbl:has(input:checked){border-color:var(--primary);background:#f0f9ff;}
.choice-lbl input{display:none;}
.choice-dot{width:14px;height:14px;border-radius:50%;border:2px solid var(--border-2);flex-shrink:0;transition:all .15s;}
.choice-lbl:has(input:checked) .choice-dot{background:var(--primary);border-color:var(--primary);}

/* ── SPELLING AUDIO ── */
.audio-box{background:#fffbeb;border:1px solid #fde68a;border-radius:9px;padding:13px;margin-bottom:11px;}
.audio-instr{font-size:0.78rem;color:#92400e;margin-bottom:7px;font-weight:500;}
audio{width:100%;height:36px;border-radius:6px;}

/* ── ESSAY ── */
.essay-note{background:#faf5ff;border:1px solid #ddd6fe;border-radius:7px;padding:9px 12px;font-size:0.77rem;color:#6d28d9;margin-bottom:9px;line-height:1.5;}
.word-counter{font-size:0.72rem;color:var(--text-3);text-align:right;margin-top:4px;}

/* ── INPUTS ── */
input[type=text],textarea{width:100%;padding:9px 12px;background:#fff;border:1px solid var(--border-2);border-radius:7px;color:var(--text);font-size:0.87rem;font-family:'Inter',sans-serif;outline:none;transition:border-color .15s,box-shadow .15s;}
input[type=text]:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(14,165,233,.1);}
textarea:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.1);}
textarea{resize:vertical;}
.hint{font-size:0.72rem;color:var(--text-3);margin-top:4px;}

/* ── LISTENING / VIDEO ── */
.listening-card{background:#fff;border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:11px;box-shadow:var(--shadow);}
.video-wrap{position:relative;padding-top:56.25%;background:#0f172a;border-radius:0;}
.video-wrap iframe,.video-wrap video{position:absolute;top:0;left:0;width:100%;height:100%;border:none;}
.video-note{background:var(--teal-light);border-bottom:1px solid var(--teal-border);padding:9px 14px;font-size:0.77rem;color:var(--teal);font-weight:500;display:flex;align-items:center;gap:6px;}
.blanks-grid{padding:15px;}
.blanks-instruction{font-size:0.83rem;font-weight:600;color:var(--text);margin-bottom:12px;}
.blank-item{display:flex;align-items:center;gap:10px;margin-bottom:9px;}
.blank-badge{width:26px;height:26px;border-radius:50%;background:var(--teal-light);border:1px solid var(--teal-border);color:var(--teal);font-size:0.72rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.blank-label{font-size:0.8rem;color:var(--text-2);min-width:56px;}
.blank-item input[type=text]{border-color:var(--teal-border);}
.blank-item input[type=text]:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(13,148,136,.1);}

/* ── SUBMIT BAR ── */
.submit-bar{position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid var(--border);padding:13px 32px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 -2px 10px rgba(0,0,0,.06);}
.prog-text{font-size:0.8rem;color:var(--text-3);}
.btn-submit{background:var(--primary);color:#fff;padding:10px 28px;border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:0.88rem;border:none;cursor:pointer;transition:background .15s;}
.btn-submit:hover{background:var(--primary-dark);}
</style>
</head>
<body>
<div class="prog-bar-top"><div class="prog-fill" id="prog" style="width:0%"></div></div>

<div class="exam-header">
  <div>
    <div class="exam-title-h"><?= htmlspecialchars($exam['title']) ?></div>
    <div class="exam-sub"><?= $totalQ ?> question<?= $totalQ!=1?'s':'' ?></div>
  </div>
  <div class="timer-wrap">
    <span class="timer-label">Time Left</span>
    <div class="timer" id="timer">--:--</div>
  </div>
</div>

<div class="content">
  <form method="POST" id="examForm">
    <?php
    $globalNum = 0;
    foreach ($typeOrder as $type):
      if (empty($groups[$type])) continue;
      $ti = $typeInfo[$type];
    ?>
    <div class="q-section">
      <div class="section-head" style="border-bottom-color:<?= $ti['color'] ?>30">
        <span><?= $ti['icon'] ?></span>
        <h3 style="color:<?= $ti['color'] ?>"><?= $ti['label'] ?></h3>
        <span class="sec-count"><?= count($groups[$type]) ?></span>
      </div>

      <?php foreach ($groups[$type] as $q):
        $globalNum++;
        $chs = $q['choices'] ? json_decode($q['choices'], true) : [];
        $blanksAnswers = ($type==='listening' && $q['blanks_answers']) ? json_decode($q['blanks_answers'],true) : [];
        $blanksCount   = max(1, intval($q['blanks_count'] ?? 1));
      ?>
      <div class="q-card" id="qc-<?= $q['id'] ?>">
        <div class="q-head">
          <span class="q-num" style="color:<?= $ti['color'] ?>;border:1px solid <?= $ti['color'] ?>30;background:<?= $ti['bg'] ?>"><?= $globalNum ?></span>
          <span class="q-pts"><?= $q['points'] ?> pt<?= $q['points']!=1?'s':'' ?></span>
        </div>
        <div class="q-text"><?= htmlspecialchars($q['question_text']) ?></div>

        <?php if ($type==='multiple_choice'): ?>
          <div class="choices">
            <?php foreach ($chs as $ci=>$ch): ?>
            <label class="choice-lbl">
              <input type="radio" name="q_<?= $q['id'] ?>" value="<?= htmlspecialchars($ch) ?>">
              <span class="choice-dot"></span>
              <span><?= chr(65+$ci) ?>. <?= htmlspecialchars($ch) ?></span>
            </label>
            <?php endforeach; ?>
          </div>

        <?php elseif ($type==='spelling'): ?>
          <?php if ($q['audio_file']): ?>
          <div class="audio-box">
            <div class="audio-instr">🔊 Listen carefully, then type the word you hear:</div>
            <audio controls><source src="<?= UPLOAD_URL.htmlspecialchars($q['audio_file']) ?>"></audio>
          </div>
          <?php endif; ?>
          <input type="text" name="q_<?= $q['id'] ?>" placeholder="Type the correct spelling…" autocomplete="off" autocorrect="off" spellcheck="false">

        <?php elseif ($type==='enumeration'): ?>
          <?php
            $enumCorrects = array_map('trim', explode(',', $q['correct_answer']));
            $enumCount = count($enumCorrects);
          ?>
          <div class="enum-student-list" style="display:flex;flex-direction:column;gap:8px">
            <?php for ($ei=1; $ei<=$enumCount; $ei++): ?>
            <div style="display:flex;align-items:center;gap:9px">
              <div style="width:24px;height:24px;border-radius:50%;background:#dcfce7;border:1px solid #86efac;color:#16a34a;font-size:0.7rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= $ei ?></div>
              <input type="text" name="q_<?= $q['id'] ?>_item_<?= $ei ?>" placeholder="Item <?= $ei ?>…" style="flex:1" data-enum="1">
            </div>
            <?php endfor; ?>
          </div>
          <div class="hint" style="margin-top:6px">Fill in each item. <?= $enumCount ?> item<?= $enumCount!=1?'s':'' ?> · 1 pt each.</div>

        <?php elseif ($type==='essay'): ?>
          <div class="essay-note">✍️ Write your answer below.<?= $q['min_words']?' Minimum <strong>'.$q['min_words'].' words</strong> required.':'' ?> This will be graded manually by your teacher.</div>
          <textarea name="q_<?= $q['id'] ?>" rows="6" placeholder="Write your answer here…" id="essay_<?= $q['id'] ?>" oninput="countWords(this,'wc_<?= $q['id'] ?>')"></textarea>
          <div class="word-counter" id="wc_<?= $q['id'] ?>">0 words</div>

        <?php elseif ($type==='identification'): ?>
          <input type="text" name="q_<?= $q['id'] ?>" placeholder="Your answer…">

        <?php elseif ($type==='listening'): ?>
          <div class="listening-card">
            <div class="video-note">🎬 Watch the video carefully, then fill in the blanks below.</div>
            <?php
            // Determine video source
            $vidSrc  = '';
            $isYT    = false;
            $isEmbed = false;
            if (!empty($q['video_file'])) {
                $vidSrc = VIDEO_URL . htmlspecialchars($q['video_file']);
            } elseif (!empty($q['video_url'])) {
                $url = $q['video_url'];
                // YouTube detection
                if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_\-]{11})/', $url, $m)) {
                    $vidSrc  = 'https://www.youtube.com/embed/'.$m[1].'?rel=0&modestbranding=1';
                    $isYT    = true;
                    $isEmbed = true;
                } elseif (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
                    $vidSrc  = 'https://player.vimeo.com/video/'.$m[1];
                    $isYT    = true;
                    $isEmbed = true;
                } else {
                    $vidSrc = htmlspecialchars($url);
                }
            }
            ?>
            <?php if ($vidSrc): ?>
            <div class="video-wrap">
              <?php if ($isEmbed): ?>
                <iframe src="<?= $vidSrc ?>" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
              <?php else: ?>
                <video controls controlsList="nodownload">
                  <source src="<?= $vidSrc ?>">
                  Your browser does not support the video tag.
                </video>
              <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="background:#f8fafc;padding:28px;text-align:center;color:var(--text-3);font-size:0.85rem;">⚠️ No video available for this question.</div>
            <?php endif; ?>

            <div class="blanks-grid">
              <div class="blanks-instruction">Fill in the <?= $blanksCount ?> blank<?= $blanksCount!=1?'s':'' ?> — <?= round($q['points']/$blanksCount,1) ?> pt<?= $blanksCount!=1?'s':'' ?> each:</div>
              <?php for ($b = 1; $b <= $blanksCount; $b++): ?>
              <div class="blank-item">
                <div class="blank-badge"><?= $b ?></div>
                <span class="blank-label">Blank <?= $b ?>:</span>
                <input type="text" name="q_<?= $q['id'] ?>_blank_<?= $b ?>" placeholder="Answer <?= $b ?>…" autocomplete="off" data-listening="1">
              </div>
              <?php endfor; ?>
            </div>
          </div>

        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </form>
</div>

<div class="submit-bar">
  <div class="prog-text" id="ans-progress">0 / <?= $totalQ ?> answered</div>
  <button class="btn-submit" onclick="submitExam()">Submit Exam ✓</button>
</div>

<script>
const endTime = <?= $endTime * 1000 ?>, totalQ = <?= $totalQ ?>;

function updateTimer() {
  const rem = Math.max(0, Math.floor((endTime - Date.now()) / 1000));
  const m = String(Math.floor(rem/60)).padStart(2,'0'), s = String(rem%60).padStart(2,'0');
  const el = document.getElementById('timer');
  el.textContent = `${m}:${s}`;
  el.className = 'timer' + (rem<=60?' danger':rem<=300?' warning':'');
  if (rem <= 0) document.getElementById('examForm').submit();
}
setInterval(updateTimer, 1000); updateTimer();

function countWords(el, id) {
  const w = el.value.trim() ? el.value.trim().split(/\s+/).length : 0;
  document.getElementById(id).textContent = w + ' word' + (w!==1?'s':'');
}

function countAnswered() {
  let c = 0, done = {};
  document.querySelectorAll('[name^="q_"]').forEach(el => {
    if (el.dataset.listening) {
      const cardId = el.closest('.q-card')?.id;
      if (cardId && !done[cardId]) {
        const blanks = document.querySelectorAll(`#${cardId} [data-listening]`);
        const filled = [...blanks].filter(b => b.value.trim()).length;
        if (filled === blanks.length && blanks.length > 0) { c++; done[cardId] = 1; }
      }
    } else if (el.dataset.enum) {
      // Count enum question as answered if ALL items filled
      const cardId = el.closest('.q-card')?.id;
      if (cardId && !done[cardId]) {
        const items = document.querySelectorAll(`#${cardId} [data-enum]`);
        const filled = [...items].filter(i => i.value.trim()).length;
        if (filled === items.length && items.length > 0) { c++; done[cardId] = 1; }
      }
    } else if (el.type === 'radio') {
      if (!done[el.name] && document.querySelector(`[name="${el.name}"]:checked`)) { c++; done[el.name] = 1; }
    } else if (el.tagName === 'TEXTAREA' || el.type === 'text') {
      if (el.value.trim()) c++;
    }
  });
  document.getElementById('ans-progress').textContent = `${c} / ${totalQ} answered`;
  document.getElementById('prog').style.width = `${(c / totalQ) * 100}%`;
}
document.addEventListener('input', countAnswered);
document.addEventListener('change', countAnswered);

function submitExam() {
  countAnswered();
  const answered = parseInt(document.getElementById('ans-progress').textContent);
  const msg = answered < totalQ
    ? `You have ${totalQ - answered} unanswered question(s). Submit anyway?`
    : 'Submit your exam? This cannot be undone.';
  if (confirm(msg)) document.getElementById('examForm').submit();
}
</script>
</body>
</html>
