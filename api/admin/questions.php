<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';
require_once 'includes.php';

$db = getDB();
$examId = intval($_GET['exam_id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM exams WHERE id=?"); $stmt->execute([$examId]); $exam = $stmt->fetch();
if (!$exam) { header('Location: exams.php'); exit; }

$flash = getFlash();

$allQ = $db->prepare("SELECT * FROM questions WHERE exam_id=? ORDER BY order_num,id");
$allQ->execute([$examId]); $allQ = $allQ->fetchAll();

$byType = ['multiple_choice'=>[],'enumeration'=>[],'spelling'=>[],'essay'=>[],'identification'=>[],'listening'=>[]];
foreach ($allQ as $q) { if (isset($byType[$q['type']])) $byType[$q['type']][] = $q; }

$typeInfo = [
    'multiple_choice' => ['label'=>'Multiple Choice','icon'=>'<i class="fa-regular fa-circle-dot"></i>','color'=>'#2563eb','bg'=>'#dbeafe','border'=>'#93c5fd','desc'=>'Pick one correct answer from 2–4 choices.'],
    'enumeration'     => ['label'=>'Enumeration',   'icon'=>'<i class="fa-solid fa-list"></i>','color'=>'#16a34a','bg'=>'#dcfce7','border'=>'#86efac','desc'=>'List multiple items separated by commas. Partial scoring.'],
    'spelling'        => ['label'=>'Spelling',      'icon'=>'<i class="fa-solid fa-headphones"></i>','color'=>'#d97706','bg'=>'#fef3c7','border'=>'#fcd34d','desc'=>'Audio plays; students type the word they hear.'],
    'essay'           => ['label'=>'Essay',         'icon'=>'<i class="fa-regular fa-pen-to-square"></i>','color'=>'#7c3aed','bg'=>'#ede9fe','border'=>'#c4b5fd','desc'=>'Open-ended writing. Graded manually by admin.'],
    'identification'  => ['label'=>'Identification','icon'=>'<i class="fa-regular fa-keyboard"></i>','color'=>'#ea580c','bg'=>'#ffedd5','border'=>'#fdba74','desc'=>'Students type a short free-text answer.'],
    'listening'       => ['label'=>'Listening',     'icon'=>'<i class="fa-solid fa-film"></i>','color'=>'#0d9488','bg'=>'#ccfbf1','border'=>'#5eead4','desc'=>'Watch a video then fill in the blanks. Each blank is worth equal points.'],
];

$activeTab = $_GET['tab'] ?? 'multiple_choice';
if (!array_key_exists($activeTab, $typeInfo)) $activeTab = 'multiple_choice';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Questions — <?= htmlspecialchars($exam['title']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php adminCSS(); ?>
<style>
.tab-bar{display:flex;gap:5px;margin-bottom:20px;flex-wrap:wrap;background:#fff;padding:8px;border-radius:var(--radius-lg);border:1px solid var(--border);box-shadow:var(--shadow);}
.tab-btn{display:flex;align-items:center;gap:5px;padding:7px 13px;border-radius:7px;border:1px solid transparent;font-size:0.8rem;font-weight:600;text-decoration:none;color:var(--text-2);transition:all 0.12s;}
.tab-btn:hover{background:var(--bg);}
.tab-btn.active{color:#fff;}
.tab-btn.active i{color:#fff;}
.tab-count{font-size:0.65rem;padding:1px 5px;border-radius:999px;background:rgba(255,255,255,0.25);}
.tab-btn:not(.active) .tab-count{background:rgba(0,0,0,0.06);color:var(--text-3);}
.panel-layout{display:grid;grid-template-columns:1fr 350px;gap:18px;align-items:start;}
.q-item{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:15px 17px;margin-bottom:9px;transition:box-shadow 0.15s;}
.q-item:hover{box-shadow:var(--shadow-md);}
.q-item-head{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:7px;}
.q-num{font-size:0.66rem;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:2px;}
.q-text{font-size:0.86rem;font-weight:500;color:var(--text);line-height:1.5;}
.q-meta{display:flex;flex-wrap:wrap;gap:8px;margin-top:9px;padding-top:9px;border-top:1px solid var(--border);font-size:0.73rem;color:var(--text-3);}
.q-meta strong{color:var(--text-2);}
.choices-preview{display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-top:7px;}
.choice-item{padding:4px 8px;background:var(--bg);border:1px solid var(--border);border-radius:5px;font-size:0.76rem;color:var(--text-2);}
.choice-item.correct{background:var(--success-bg);border-color:#86efac;color:var(--success);font-weight:600;}
.choice-item.correct i{color:var(--success);margin-left:2px;}
audio{width:100%;height:32px;border-radius:6px;margin-top:6px;}
.order-btns{display:flex;flex-direction:column;gap:2px;flex-shrink:0;}
.arr-btn{width:20px;height:18px;border-radius:4px;border:1px solid var(--border-2);background:#fff;color:var(--text-3);cursor:pointer;font-size:0.58rem;display:flex;align-items:center;justify-content:center;text-decoration:none;transition:all 0.12s;}
.arr-btn:hover{border-color:var(--primary);color:var(--primary);background:var(--primary-light);}
.form-panel{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:18px;position:sticky;top:20px;box-shadow:var(--shadow);}
.form-panel-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:0.85rem;font-weight:700;margin-bottom:13px;padding-bottom:11px;border-bottom:1px solid var(--border);}
.form-panel-title i{margin-right:5px;color:var(--primary);}
.choices-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;}
.file-drop{background:var(--bg);border:2px dashed var(--border-2);border-radius:7px;padding:12px;text-align:center;cursor:pointer;transition:all 0.15s;}
.file-drop:hover{border-color:var(--primary);background:var(--primary-light);}
.file-drop input{display:none;}
.file-chosen{color:var(--primary);font-size:0.73rem;margin-top:3px;font-weight:500;}
.type-desc{background:var(--bg);border:1px solid var(--border);border-radius:7px;padding:9px 11px;margin-bottom:14px;font-size:0.78rem;color:var(--text-3);line-height:1.5;}
.type-desc i{margin-right:3px;}
.empty-state{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:36px;text-align:center;color:var(--text-3);}
.empty-state i{font-size:2rem;color:var(--text-3);opacity:0.3;margin-bottom:10px;display:block;}
.rubric-preview{background:#fef9c3;border:1px solid #fde68a;border-radius:6px;padding:7px 10px;font-size:0.75rem;color:#713f12;line-height:1.5;margin-top:7px;}
.rubric-preview i{color:#d97706;margin-right:3px;}
/* Video preview in question list */
.video-badge{display:inline-flex;align-items:center;gap:4px;background:var(--teal-bg);color:var(--teal);padding:3px 8px;border-radius:5px;font-size:0.72rem;font-weight:600;margin-top:5px;}
.video-badge i{color:var(--teal);}
.blanks-list{display:flex;flex-wrap:wrap;gap:5px;margin-top:7px;}
.blank-chip{background:var(--bg);border:1px solid var(--border);border-radius:5px;padding:3px 8px;font-size:0.75rem;color:var(--text-2);}
.blank-chip strong{color:var(--text);}

.enum-row{display:flex;align-items:center;gap:6px;}
.enum-num{width:22px;height:22px;border-radius:50%;background:var(--success-bg);color:var(--success);font-size:0.7rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid #bbf7d0;}
.enum-del{width:22px;height:22px;border-radius:5px;border:1px solid var(--border);background:#fff;color:var(--text-3);cursor:pointer;font-size:0.65rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;padding:0;}
.enum-del:hover{background:var(--danger-bg);border-color:#fecaca;color:var(--danger);}
.enum-row input{flex:1;}
/* Video source tabs */
.vsrc-tabs{display:flex;gap:4px;margin-bottom:9px;}
.vsrc-tab{padding:5px 11px;border-radius:6px;border:1px solid var(--border);font-size:0.77rem;font-weight:600;cursor:pointer;background:#fff;color:var(--text-2);transition:all 0.12s;display:flex;align-items:center;gap:4px;}
.vsrc-tab i{font-size:0.7rem;}
.vsrc-tab.active{background:var(--teal-bg);border-color:#5eead4;color:var(--teal);}
.vsrc-tab.active i{color:var(--teal);}
.blanks-builder{background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:11px;margin-top:4px;}
.blank-row{display:flex;align-items:center;gap:7px;margin-bottom:7px;}
.blank-num{width:22px;height:22px;border-radius:50%;background:var(--teal-bg);color:var(--teal);font-size:0.7rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
</style>
</head>
<body>
<?php adminSidebar('exams'); ?>
<main class="main">
  <div class="breadcrumb"><a href="exams.php"><i class="fa-regular fa-file-lines" style="margin-right:3px;"></i>Exams</a> / <?= htmlspecialchars($exam['title']) ?></div>
  <div class="page-header">
    <div>
      <div class="page-title"><i class="fa-regular fa-rectangle-list" style="margin-right:8px;color:var(--primary);"></i>Manage Questions</div>
      <div class="page-sub"><i class="fa-regular fa-circle-check" style="color:var(--primary);margin-right:3px;"></i><?= count($allQ) ?> total · <?= $exam['duration_minutes'] ?>min · <span class="badge <?= $exam['is_active']?'badge-active':'badge-inactive' ?>"><?= $exam['is_active']?'Active':'Inactive' ?></span></div>
    </div>
    <a href="edit_exam.php?id=<?= $examId ?>" class="btn-outline"><i class="fa-regular fa-pen-to-square" style="margin-right:3px;"></i> Edit Exam</a>
  </div>

  <?php if ($flash): ?><div class="flash flash-<?= $flash['type'] ?>"><i class="fa-solid fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>" style="margin-right:5px;"></i><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>

  <div class="tab-bar">
    <?php foreach ($typeInfo as $type => $ti):
      $cnt = count($byType[$type]);
      $isActive = ($activeTab === $type);
    ?>
    <a href="?exam_id=<?= $examId ?>&tab=<?= $type ?>"
       class="tab-btn <?= $isActive?'active':'' ?>"
       style="<?= $isActive ? "background:{$ti['color']};border-color:{$ti['color']}" : '' ?>">
      <?= $ti['icon'] ?> <?= $ti['label'] ?>
      <span class="tab-count"><?= $cnt ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <?php $cur=$activeTab; $ti=$typeInfo[$cur]; $curList=$byType[$cur]; ?>
  <div class="panel-layout">
    <!-- Left: list -->
    <div>
      <div class="type-desc"><?= $ti['icon'] ?> <strong><?= $ti['label'] ?>:</strong> <?= $ti['desc'] ?></div>

      <?php if (empty($curList)): ?>
      <div class="empty-state">
        <i class="fa-regular fa-file-lines"></i>
        No <?= strtolower($ti['label']) ?> questions yet.<br><span style="font-size:0.8rem;margin-top:5px;display:inline-block;opacity:.6">Use the form on the right to add one.</span>
      </div>
      <?php else: ?>
        <?php foreach ($curList as $i => $q): ?>
        <div class="q-item">
          <div class="q-item-head">
            <div style="flex:1">
              <div class="q-num"><i class="fa-regular fa-circle-question" style="margin-right:3px;"></i>Question <?= $i+1 ?></div>
              <div class="q-text"><?= htmlspecialchars($q['question_text']) ?></div>
            </div>
            <div style="display:flex;align-items:flex-start;gap:4px">
              <div class="order-btns">
                <?php if ($i>0): ?><a href="reorder_question.php?id=<?= $q['id'] ?>&dir=up&exam_id=<?= $examId ?>&tab=<?= $cur ?>" class="arr-btn" title="Move up"><i class="fa-solid fa-chevron-up"></i></a><?php endif; ?>
                <?php if ($i<count($curList)-1): ?><a href="reorder_question.php?id=<?= $q['id'] ?>&dir=down&exam_id=<?= $examId ?>&tab=<?= $cur ?>" class="arr-btn" title="Move down"><i class="fa-solid fa-chevron-down"></i></a><?php endif; ?>
              </div>
              <a href="edit_question.php?id=<?= $q['id'] ?>&exam_id=<?= $examId ?>&tab=<?= $cur ?>" class="btn-sm btn-blue"><i class="fa-regular fa-pen-to-square"></i> Edit</a>
              <a href="delete_question.php?id=<?= $q['id'] ?>&exam_id=<?= $examId ?>&tab=<?= $cur ?>" class="btn-sm btn-red" onclick="return confirm('Delete this question?')"><i class="fa-regular fa-trash-can"></i> Delete</a>
            </div>
          </div>

          <?php if ($cur==='multiple_choice' && $q['choices']): ?>
            <?php $chs=json_decode($q['choices'],true)??[]; ?>
            <div class="choices-preview">
              <?php foreach ($chs as $ci=>$c): ?>
              <div class="choice-item <?= ($c===$q['correct_answer'])?'correct':'' ?>"><?= chr(65+$ci) ?>. <?= htmlspecialchars($c) ?><?= ($c===$q['correct_answer'])?' <i class="fa-regular fa-circle-check"></i>':'' ?></div>
              <?php endforeach; ?>
            </div>

          <?php elseif ($cur==='enumeration'): ?>
            <?php $eitems = $q['correct_answer'] ? array_map('trim', explode(',', $q['correct_answer'])) : []; ?>
            <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:7px">
              <?php foreach($eitems as $eii=>$eiv): ?>
              <span style="background:var(--success-bg);border:1px solid #bbf7d0;color:var(--success);padding:2px 8px;border-radius:4px;font-size:0.74rem;font-weight:600"><i class="fa-regular fa-circle-check" style="margin-right:3px;"></i><?= $eii+1 ?>. <?= htmlspecialchars($eiv) ?></span>
              <?php endforeach; ?>
            </div>

          <?php elseif ($cur==='spelling'): ?>
            <?php if ($q['audio_file']): ?>
            <audio controls><source src="<?= UPLOAD_URL.htmlspecialchars($q['audio_file']) ?>"></audio>
            <?php else: ?><div style="font-size:0.75rem;color:#d97706;background:#fef3c7;padding:5px 9px;border-radius:5px;margin-top:6px"><i class="fa-solid fa-triangle-exclamation" style="margin-right:3px;"></i> No audio uploaded</div><?php endif; ?>

          <?php elseif ($cur==='essay' && $q['essay_rubric']): ?>
            <div class="rubric-preview"><i class="fa-regular fa-note-sticky"></i> <strong>Rubric:</strong> <?= nl2br(htmlspecialchars($q['essay_rubric'])) ?></div>

          <?php elseif ($cur==='listening'): ?>
            <?php if ($q['video_file'] || $q['video_url']): ?>
            <div class="video-badge"><i class="fa-solid fa-film"></i> <?= $q['video_file'] ? 'Uploaded video: '.htmlspecialchars($q['video_file']) : 'YouTube/URL link' ?></div>
            <?php else: ?><div style="font-size:0.75rem;color:#d97706;background:#fef3c7;padding:5px 9px;border-radius:5px;margin-top:6px"><i class="fa-solid fa-triangle-exclamation"></i> No video set</div><?php endif; ?>
            <?php
              $blanksAnswers = $q['blanks_answers'] ? json_decode($q['blanks_answers'],true) : [];
              if (!empty($blanksAnswers)):
            ?>
            <div class="blanks-list">
              <?php foreach ($blanksAnswers as $bi => $ba): ?>
              <div class="blank-chip"><i class="fa-regular fa-keyboard" style="margin-right:2px;"></i>Blank <?= $bi+1 ?>: <strong><?= htmlspecialchars($ba) ?></strong></div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          <?php endif; ?>

          <div class="q-meta">
            <?php if ($cur==='essay'): ?>
              <span style="color:var(--purple);font-weight:500"><i class="fa-regular fa-clock" style="margin-right:2px;"></i>Manual grading</span>
            <?php elseif ($cur==='enumeration'): ?>
              <?php $enumItems = $q['correct_answer'] ? array_map('trim', explode(',', $q['correct_answer'])) : []; ?>
              <span><i class="fa-regular fa-rectangle-list"></i> <?= count($enumItems) ?> item<?= count($enumItems)!=1?'s':'' ?></span>
              <span><i class="fa-regular fa-star"></i> Points: <strong><?= $q['points'] ?></strong> (1 each)</span>
            <?php elseif ($cur==='listening'): ?>
              <span><i class="fa-regular fa-closed-captioning"></i> <?= $q['blanks_count'] ?> blank<?= $q['blanks_count']!=1?'s':'' ?></span>
              <span><i class="fa-regular fa-star"></i> Points: <strong><?= $q['points'] ?></strong> (<?= $q['blanks_count']>0?round($q['points']/$q['blanks_count'],1):0 ?> each)</span>
            <?php else: ?>
              <span><i class="fa-regular fa-circle-check"></i> Answer: <strong><?= htmlspecialchars(mb_substr($q['correct_answer'],0,40)).(mb_strlen($q['correct_answer'])>40?'…':'') ?></strong></span>
              <span><i class="fa-regular fa-star"></i> Points: <strong><?= $q['points'] ?></strong></span>
            <?php endif; ?>
            <?php if ($cur==='essay' && $q['min_words']): ?><span><i class="fa-regular fa-file-lines"></i> Min words: <strong><?= $q['min_words'] ?></strong></span><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Right: add form -->
    <div class="form-panel">
      <div class="form-panel-title"><?= $ti['icon'] ?> Add <?= $ti['label'] ?> Question</div>
      <form method="POST" action="save_question.php" enctype="multipart/form-data" id="qForm">
        <input type="hidden" name="exam_id" value="<?= $examId ?>">
        <input type="hidden" name="type" value="<?= $cur ?>">
        <input type="hidden" name="tab" value="<?= $cur ?>">

        <div class="form-group">
          <label><i class="fa-regular fa-circle-question" style="margin-right:3px;"></i>Question / Instruction *</label>
          <textarea name="question_text" rows="<?= $cur==='listening'?2:3 ?>" placeholder="<?= [
            'multiple_choice'=>'Which word is spelled correctly?',
            'enumeration'=>'Name the 3 branches of government.',
            'spelling'=>'Listen and type the correct spelling.',
            'essay'=>'Write a paragraph about your favorite subject.',
            'identification'=>'What is the capital city of Japan?',
            'listening'=>'Watch the video and fill in the missing words.',
          ][$cur] ?>" required></textarea>
        </div>

        <?php if ($cur==='multiple_choice'): ?>
        <div class="form-group">
          <label><i class="fa-regular fa-rectangle-list"></i> Answer Choices</label>
          <div class="choices-grid">
            <input type="text" name="choice_a" placeholder="A. Choice 1">
            <input type="text" name="choice_b" placeholder="B. Choice 2">
            <input type="text" name="choice_c" placeholder="C. Choice 3">
            <input type="text" name="choice_d" placeholder="D. Choice 4">
          </div>
        </div>
        <div class="form-group">
          <label><i class="fa-regular fa-circle-check"></i> Correct Answer *</label>
          <input type="text" name="correct_answer" placeholder="Copy-paste from choices above" required>
          <div class="hint"><i class="fa-regular fa-lightbulb"></i> Must exactly match one of the choices.</div>
        </div>

        <?php elseif ($cur==='enumeration'): ?>
        <div class="form-group">
          <label><i class="fa-regular fa-rectangle-list"></i> Enumeration Items <span style="font-weight:400;color:var(--text-3)">(1 pt each)</span></label>
          <div id="enumItems" style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px">
            <div class="enum-row"><span class="enum-num">1</span><input type="text" name="enum_item[]" placeholder="Item 1" required><button type="button" class="enum-del" onclick="delEnum(this)" title="Remove"><i class="fa-regular fa-trash-can"></i></button></div>
            <div class="enum-row"><span class="enum-num">2</span><input type="text" name="enum_item[]" placeholder="Item 2"><button type="button" class="enum-del" onclick="delEnum(this)" title="Remove"><i class="fa-regular fa-trash-can"></i></button></div>
            <div class="enum-row"><span class="enum-num">3</span><input type="text" name="enum_item[]" placeholder="Item 3"><button type="button" class="enum-del" onclick="delEnum(this)" title="Remove"><i class="fa-regular fa-trash-can"></i></button></div>
          </div>
          <button type="button" onclick="addEnum()" style="background:var(--success-bg);color:var(--success);border:1px solid #bbf7d0;border-radius:6px;padding:5px 12px;font-size:0.78rem;font-weight:600;cursor:pointer;width:100%"><i class="fa-regular fa-plus" style="margin-right:3px;"></i> Add Item</button>
          <div class="hint"><i class="fa-regular fa-lightbulb"></i> Each item = 1 point. Total points = number of items. Case-insensitive.</div>
        </div>
        <input type="hidden" name="correct_answer" value="(enumeration)">

        <?php elseif ($cur==='spelling'): ?>
        <div class="form-group">
          <label><i class="fa-solid fa-headphones"></i> Upload Audio (MP3 / WAV / OGG)</label>
          <div class="file-drop" onclick="document.getElementById('audioNew').click()">
            <input type="file" id="audioNew" name="audio_file" accept=".mp3,.wav,.ogg,.m4a,audio/*" onchange="showFile(this,'audioNewName')">
            <div style="font-size:0.78rem;color:var(--text-3)"><i class="fa-solid fa-cloud-upload-alt"></i> Click to upload audio</div>
            <div class="file-chosen" id="audioNewName">No file chosen</div>
          </div>
        </div>
        <div class="form-group">
          <label><i class="fa-regular fa-circle-check"></i> Correct Spelling *</label>
          <input type="text" name="correct_answer" placeholder="e.g. necessary" required>
        </div>

        <?php elseif ($cur==='essay'): ?>
        <div class="form-group">
          <label><i class="fa-regular fa-note-sticky"></i> Grading Rubric <span style="font-weight:400;color:var(--text-3)">(optional)</span></label>
          <textarea name="essay_rubric" rows="3" placeholder="e.g. 5pts – Thesis, 3pts – Evidence, 2pts – Grammar"></textarea>
          <div class="hint"><i class="fa-regular fa-lightbulb"></i> Visible only to you when grading.</div>
        </div>
        <div class="form-group">
          <label><i class="fa-regular fa-file-lines"></i> Minimum Word Count <span style="font-weight:400;color:var(--text-3)">(optional)</span></label>
          <input type="number" name="min_words" placeholder="e.g. 100" min="0">
        </div>
        <input type="hidden" name="correct_answer" value="(manual grading)">

        <?php elseif ($cur==='identification'): ?>
        <div class="form-group">
          <label><i class="fa-regular fa-circle-check"></i> Correct Answer *</label>
          <input type="text" name="correct_answer" placeholder="e.g. Tokyo" required>
          <div class="hint"><i class="fa-regular fa-lightbulb"></i> Case-insensitive exact match.</div>
        </div>

        <?php elseif ($cur==='listening'): ?>
        <!-- Video source -->
        <div class="form-group">
          <label><i class="fa-solid fa-film"></i> Video Source</label>
          <div class="vsrc-tabs">
            <button type="button" class="vsrc-tab active" onclick="switchSrc('url',this)"><i class="fa-solid fa-link"></i> YouTube / URL</button>
            <button type="button" class="vsrc-tab" onclick="switchSrc('file',this)"><i class="fa-solid fa-cloud-upload-alt"></i> Upload Video</button>
          </div>
          <div id="srcUrl">
            <input type="url" name="video_url" placeholder="https://www.youtube.com/watch?v=... or any video URL">
            <div class="hint"><i class="fa-regular fa-lightbulb"></i> YouTube, Vimeo, or direct .mp4 link.</div>
          </div>
          <div id="srcFile" style="display:none">
            <div class="file-drop" onclick="document.getElementById('videoFile').click()">
              <input type="file" id="videoFile" name="video_file" accept=".mp4,.webm,.mov,video/*" onchange="showFile(this,'videoFileName')">
              <div style="font-size:0.78rem;color:var(--text-3)"><i class="fa-solid fa-cloud-upload-alt"></i> Click to upload video (MP4/WebM)</div>
              <div class="file-chosen" id="videoFileName">No file chosen</div>
            </div>
          </div>
        </div>

        <!-- Fill-in-the-blank builder -->
        <div class="form-group">
          <label><i class="fa-regular fa-keyboard"></i> Number of Blanks</label>
          <div style="display:flex;align-items:center;gap:8px">
            <input type="number" name="blanks_count" id="blanksCountInput" value="3" min="1" max="20" style="width:80px" oninput="buildBlanks(this.value)">
            <span style="font-size:0.77rem;color:var(--text-3)">blanks</span>
          </div>
        </div>
        <div class="form-group">
          <label><i class="fa-regular fa-circle-check"></i> Correct Answers for Each Blank</label>
          <div class="blanks-builder" id="blanksBuilder">
            <!-- generated by JS -->
          </div>
          <div class="hint"><i class="fa-regular fa-lightbulb"></i> Students watch the video and fill in each blank. Case-insensitive.</div>
        </div>
        <input type="hidden" name="correct_answer" value="(listening)">
        <?php endif; ?>

        <?php if ($cur !== 'listening'): ?>
        <div class="form-row form-row-2" style="margin-bottom:12px">
          <div class="form-group" style="margin:0"><label><i class="fa-regular fa-star"></i> Points</label><input type="number" name="points" value="1" min="1" max="100" required></div>
          <div class="form-group" style="margin:0"><label><i class="fa-regular fa-arrow-up-1-9"></i> Order #</label><input type="number" name="order_num" value="<?= count($curList)+1 ?>" min="1"></div>
        </div>
        <?php else: ?>
        <div class="form-row form-row-2" style="margin-bottom:12px">
          <div class="form-group" style="margin:0"><label><i class="fa-regular fa-star"></i> Total Points</label><input type="number" name="points" value="3" min="1" max="100" id="listenPts" required></div>
          <div class="form-group" style="margin:0"><label><i class="fa-regular fa-arrow-up-1-9"></i> Order #</label><input type="number" name="order_num" value="<?= count($curList)+1 ?>" min="1"></div>
        </div>
        <div style="background:var(--teal-bg);border:1px solid #5eead4;border-radius:6px;padding:8px 10px;font-size:0.75rem;color:var(--teal);margin-bottom:12px">
          <i class="fa-regular fa-lightbulb" style="margin-right:3px;"></i> Points will be split equally across all blanks. Partial credit awarded per correct blank.
        </div>
        <?php endif; ?>

        <button type="submit" class="btn-primary" style="width:100%"><i class="fa-regular fa-plus" style="margin-right:5px;"></i> Add Question</button>
      </form>
    </div>
  </div>
</main>

<script>
function addEnum(){
  const c=document.getElementById('enumItems');
  const n=c.children.length+1;
  const d=document.createElement('div');
  d.className='enum-row';
  d.innerHTML=`<span class="enum-num">${n}</span><input type="text" name="enum_item[]" placeholder="Item ${n}"><button type="button" class="enum-del" onclick="delEnum(this)" title="Remove"><i class="fa-regular fa-trash-can"></i></button>`;
  c.appendChild(d);
  renumEnum();
}
function delEnum(btn){
  const row=btn.closest('.enum-row');
  const c=document.getElementById('enumItems');
  if(c.children.length>1){row.remove();renumEnum();}
}
function renumEnum(){
  const rows=document.querySelectorAll('#enumItems .enum-row');
  rows.forEach((r,i)=>{r.querySelector('.enum-num').text