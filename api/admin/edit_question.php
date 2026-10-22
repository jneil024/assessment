<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';
require_once 'includes.php';

$db = getDB();
$qid=$intval_qid=intval($_GET['id']??0); $examId=intval($_GET['exam_id']??0); $tab=$_GET['tab']??'';
$q=$db->prepare("SELECT * FROM questions WHERE id=? AND exam_id=?"); $q->execute([$qid,$examId]); $q=$q->fetch();
if (!$q){header("Location: questions.php?exam_id=$examId&tab=$tab");exit;}
$choices=$q['choices']?json_decode($q['choices'],true):['','','',''];
while(count($choices)<4) $choices[]='';
$blanksAnswers = $q['blanks_answers']?json_decode($q['blanks_answers'],true):[];

$error='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
    $text=trim($_POST['question_text']??''); $answer=trim($_POST['correct_answer']??'');
    $points=max(1,intval($_POST['points']??1)); $orderNum=max(1,intval($_POST['order_num']??1));
    $rubric=trim($_POST['essay_rubric']??''); $minWords=intval($_POST['min_words']??0)?:null;
    $blanksCount=intval($_POST['blanks_count']??1);
    $newAudio=$q['audio_file']; $newVideoFile=$q['video_file']; $newVideoUrl=trim($_POST['video_url']??'');

    // Audio
    if(isset($_FILES['audio_file'])&&$_FILES['audio_file']['error']===0){
        $ext=strtolower(pathinfo($_FILES['audio_file']['name'],PATHINFO_EXTENSION));
        if(in_array($ext,['mp3','wav','ogg','m4a'])){
            $fn=uniqid('audio_').'.'.$ext;
            if(!is_dir(UPLOAD_DIR))mkdir(UPLOAD_DIR,0775,true);
            if(move_uploaded_file($_FILES['audio_file']['tmp_name'],UPLOAD_DIR.$fn)){
                if($q['audio_file']&&file_exists(UPLOAD_DIR.$q['audio_file']))unlink(UPLOAD_DIR.$q['audio_file']);
                $newAudio=$fn;
            }
        }else $error='Invalid audio format.';
    }
    if(isset($_POST['remove_audio'])&&$_POST['remove_audio']==='1'){
        if($q['audio_file']&&file_exists(UPLOAD_DIR.$q['audio_file']))unlink(UPLOAD_DIR.$q['audio_file']);
        $newAudio=null;
    }

    // Video
    if($q['type']==='listening'){
        if(isset($_FILES['video_file'])&&$_FILES['video_file']['error']===0){
            $ext=strtolower(pathinfo($_FILES['video_file']['name'],PATHINFO_EXTENSION));
            if(in_array($ext,['mp4','webm','mov','avi'])){
                if(!is_dir(VIDEO_DIR))mkdir(VIDEO_DIR,0775,true);
                $fn=uniqid('video_').'.'.$ext;
                if(move_uploaded_file($_FILES['video_file']['tmp_name'],VIDEO_DIR.$fn)){
                    if($q['video_file']&&file_exists(VIDEO_DIR.$q['video_file']))unlink(VIDEO_DIR.$q['video_file']);
                    $newVideoFile=$fn; $newVideoUrl=null;
                }
            }else $error='Invalid video format.';
        }
        if(isset($_POST['remove_video'])&&$_POST['remove_video']==='1'){
            if($q['video_file']&&file_exists(VIDEO_DIR.$q['video_file']))unlink(VIDEO_DIR.$q['video_file']);
            $newVideoFile=null;
        }
        // Blank answers
        $ba=[];
        for($i=1;$i<=$blanksCount;$i++){$v=trim($_POST["blank_ans_$i"]??'');$ba[]=strtolower($v);}
        $newBlanksAnswers=json_encode($ba);
        $answer='(listening)';
    }

    $newChoices=null;
    if($q['type']==='multiple_choice'){
        $opts=array_values(array_filter(array_map('trim',[$_POST['choice_a']??'',$_POST['choice_b']??'',$_POST['choice_c']??'',$_POST['choice_d']??''])));
        if(count($opts)<2){$error='At least 2 choices required.';}
        elseif(!in_array($answer,$opts)){$error='Correct answer must match a choice.';}
        else $newChoices=json_encode($opts);
    }
    if($q['type']==='enumeration'){
        $rawItems = $_POST['enum_item'] ?? [];
        $parts = array_values(array_filter(array_map('trim', $rawItems)));
        if(empty($parts)){ $error='Provide at least one enumeration item.'; }
        else {
            $answer = implode(',', array_map('strtolower', $parts));
            $points = count($parts); // 1 pt per item
        }
    }

    if(!$error){
        $db->prepare("UPDATE questions SET question_text=?,audio_file=?,video_file=?,video_url=?,correct_answer=?,choices=?,essay_rubric=?,min_words=?,blanks_count=?,blanks_answers=?,points=?,order_num=? WHERE id=?")
          ->execute([$text,$newAudio,$newVideoFile??null,$newVideoUrl??null,$answer,$newChoices,$rubric?:null,$minWords,
                     $blanksCount??$q['blanks_count'],$newBlanksAnswers??$q['blanks_answers'],$points,$orderNum,$qid]);
        flash('success','Question updated!');
        header("Location: questions.php?exam_id=$examId&tab=$tab");exit;
    }
    $choices=[$_POST['choice_a']??'',$_POST['choice_b']??'',$_POST['choice_c']??'',$_POST['choice_d']??''];
}

$typeInfo=['multiple_choice'=>['label'=>'Multiple Choice','icon'=>'🔘'],'enumeration'=>['label'=>'Enumeration','icon'=>'📋'],'spelling'=>['label'=>'Spelling','icon'=>'🔊'],'essay'=>['label'=>'Essay','icon'=>'✍️'],'identification'=>['label'=>'Identification','icon'=>'✏️'],'listening'=>['label'=>'Listening','icon'=>'🎬']];
$ti=$typeInfo[$q['type']]??['label'=>$q['type'],'icon'=>'❓'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Question — Southern Baptist College</title>
<?php adminCSS(); ?>
<style>
.edit-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:26px;max-width:640px;box-shadow:var(--shadow);}
.type-label{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:6px;font-size:0.78rem;font-weight:700;margin-bottom:16px;}
.choices-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px;}
.enum-row{display:flex;align-items:center;gap:6px;}
.enum-num{width:22px;height:22px;border-radius:50%;background:var(--success-bg);color:var(--success);font-size:0.7rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid #bbf7d0;}
.enum-del{width:22px;height:22px;border-radius:5px;border:1px solid var(--border);background:#fff;color:var(--text-3);cursor:pointer;font-size:0.65rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;padding:0;}
.enum-del:hover{background:var(--danger-bg);border-color:#fecaca;color:var(--danger);}
.enum-row input{flex:1;}

.file-drop{background:var(--bg);border:2px dashed var(--border-2);border-radius:7px;padding:12px;text-align:center;cursor:pointer;transition:all 0.15s;}
.file-drop:hover{border-color:var(--primary);background:var(--primary-light);}
.file-drop input{display:none;}
.existing-media{background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 12px;margin-bottom:8px;}
.blanks-builder{background:var(--bg);border:1px solid var(--border);border-radius:7px;padding:10px;}
.blank-row{display:flex;align-items:center;gap:6px;margin-bottom:6px;}
.blank-num{width:20px;height:20px;border-radius:50%;background:#ccfbf1;color:#0d9488;font-size:0.68rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.vsrc-tabs{display:flex;gap:4px;margin-bottom:8px;}
.vsrc-tab{padding:4px 10px;border-radius:5px;border:1px solid var(--border);font-size:0.75rem;font-weight:600;cursor:pointer;background:#fff;color:var(--text-2);}
.vsrc-tab.active{background:#ccfbf1;border-color:#5eead4;color:#0d9488;}
.btn-row{display:flex;gap:8px;margin-top:5px;}
</style>
</head>
<body>
<?php adminSidebar('exams'); ?>
<main class="main">
  <div class="breadcrumb"><a href="exams.php">Exams</a> / <a href="questions.php?exam_id=<?= $examId ?>&tab=<?= $tab ?>">Questions</a> / Edit</div>
  <div class="page-header"><div class="page-title">Edit Question</div></div>
  <?php if ($error): ?><div class="flash flash-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <div class="edit-card">
    <div class="type-label <?php echo ['multiple_choice'=>'badge-mc','enumeration'=>'badge-enum','spelling'=>'badge-spelling','essay'=>'badge-essay','identification'=>'badge-ident','listening'=>'badge-listening'][$q['type']]??'badge-mc' ?>"><?= $ti['icon'] ?> <?= $ti['label'] ?></div>
    <form method="POST" enctype="multipart/form-data">
      <div class="form-group"><label>Question / Instruction *</label><textarea name="question_text" rows="3" required><?= htmlspecialchars($q['question_text']) ?></textarea></div>

      <?php if ($q['type']==='multiple_choice'): ?>
      <div class="form-group"><label>Choices</label><div class="choices-grid"><?php for($i=0;$i<4;$i++): $l=['a','b','c','d'][$i]; ?><input type="text" name="choice_<?= $l ?>" placeholder="<?= strtoupper($l) ?>." value="<?= htmlspecialchars($choices[$i]??'') ?>"><?php endfor; ?></div></div>
      <div class="form-group"><label>Correct Answer *</label><input type="text" name="correct_answer" value="<?= htmlspecialchars($q['correct_answer']) ?>" required><div class="hint">Must exactly match one choice.</div></div>

      <?php elseif ($q['type']==='spelling'): ?>
      <div class="form-group"><label>Audio File</label>
        <?php if ($q['audio_file']): ?>
        <div class="existing-media"><div style="font-size:0.75rem;color:#0369a1;margin-bottom:6px">🎵 Current: <?= htmlspecialchars($q['audio_file']) ?></div><audio controls style="width:100%;height:32px;border-radius:6px"><source src="<?= UPLOAD_URL.htmlspecialchars($q['audio_file']) ?>"></audio><label style="display:flex;align-items:center;gap:6px;margin-top:8px;cursor:pointer;font-size:0.78rem;font-weight:500;color:var(--danger)"><input type="checkbox" name="remove_audio" value="1" style="width:13px;height:13px;accent-color:var(--danger)"> Remove this audio</label></div>
        <?php endif; ?>
        <div class="file-drop" onclick="document.getElementById('audioEdit').click()"><input type="file" id="audioEdit" name="audio_file" accept=".mp3,.wav,.ogg,.m4a,audio/*" onchange="sf(this,'aeN')"><div style="font-size:0.78rem;color:var(--text-3)">🔊 Upload new audio to replace</div><div style="font-size:0.72rem;color:var(--primary);margin-top:2px" id="aeN">No file chosen</div></div>
      </div>
      <div class="form-group"><label>Correct Spelling *</label><input type="text" name="correct_answer" value="<?= htmlspecialchars($q['correct_answer']) ?>" required></div>

      <?php elseif ($q['type']==='enumeration'): ?>
      <?php $enumItems = $q['correct_answer'] ? array_map('trim', explode(',', $q['correct_answer'])) : ['']; ?>
      <div class="form-group">
        <label>Enumeration Items <span style="font-weight:400;color:var(--text-3)">(1 pt each)</span></label>
        <div id="enumItemsEdit" style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px">
          <?php foreach($enumItems as $ei=>$ev): ?>
          <div class="enum-row">
            <span class="enum-num"><?= $ei+1 ?></span>
            <input type="text" name="enum_item[]" value="<?= htmlspecialchars($ev) ?>" placeholder="Item <?= $ei+1 ?>" <?= $ei===0?'required':'' ?>>
            <button type="button" class="enum-del" onclick="delEnumE(this)" title="Remove">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" onclick="addEnumE()" style="background:var(--success-bg);color:var(--success);border:1px solid #bbf7d0;border-radius:6px;padding:5px 12px;font-size:0.78rem;font-weight:600;cursor:pointer;width:100%">+ Add Item</button>
        <div class="hint">Each item = 1 point. Total points auto-calculated.</div>
      </div>
      <input type="hidden" name="correct_answer" value="(enumeration)">

      <?php elseif ($q['type']==='essay'): ?>
      <div class="form-group"><label>Grading Rubric <span style="font-weight:400;color:var(--text-3)">(optional)</span></label><textarea name="essay_rubric" rows="4"><?= htmlspecialchars($q['essay_rubric']??'') ?></textarea></div>
      <div class="form-group"><label>Minimum Word Count</label><input type="number" name="min_words" value="<?= $q['min_words'] ?>" min="0"></div>
      <input type="hidden" name="correct_answer" value="(manual grading)">

      <?php elseif ($q['type']==='identification'): ?>
      <div class="form-group"><label>Correct Answer *</label><input type="text" name="correct_answer" value="<?= htmlspecialchars($q['correct_answer']) ?>" required></div>

      <?php elseif ($q['type']==='listening'): ?>
      <!-- Video -->
      <div class="form-group"><label>Video</label>
        <?php if ($q['video_file']): ?>
        <div class="existing-media"><div style="font-size:0.75rem;color:#0369a1;margin-bottom:6px">🎬 Current file: <?= htmlspecialchars($q['video_file']) ?></div><video controls style="width:100%;border-radius:7px;max-height:140px"><source src="<?= VIDEO_URL.htmlspecialchars($q['video_file']) ?>"></video><label style="display:flex;align-items:center;gap:6px;margin-top:8px;cursor:pointer;font-size:0.78rem;font-weight:500;color:var(--danger)"><input type="checkbox" name="remove_video" value="1" style="width:13px;height:13px;accent-color:var(--danger)"> Remove video file</label></div>
        <?php elseif ($q['video_url']): ?>
        <div class="existing-media"><div style="font-size:0.75rem;color:#0369a1;margin-bottom:4px">🔗 Current URL:</div><div style="font-size:0.78rem;color:var(--text-2);word-break:break-all"><?= htmlspecialchars($q['video_url']) ?></div></div>
        <?php endif; ?>
        <div class="vsrc-tabs">
          <button type="button" class="vsrc-tab active" onclick="swS('url',this)">🔗 URL</button>
          <button type="button" class="vsrc-tab" onclick="swS('file',this)">📁 Upload</button>
        </div>
        <div id="srcUrl"><input type="url" name="video_url" value="<?= htmlspecialchars($q['video_url']??'') ?>" placeholder="https://youtube.com/watch?v=..."><div class="hint">Leave empty to keep existing.</div></div>
        <div id="srcFile" style="display:none"><div class="file-drop" onclick="document.getElementById('vidEdit').click()"><input type="file" id="vidEdit" name="video_file" accept=".mp4,.webm,.mov,video/*" onchange="sf(this,'vfN')"><div style="font-size:0.78rem;color:var(--text-3)">🎬 Upload new video</div><div style="font-size:0.72rem;color:var(--primary);margin-top:2px" id="vfN">No file chosen</div></div></div>
      </div>
      <!-- Blanks -->
      <div class="form-group">
        <label>Number of Blanks</label>
        <div style="display:flex;align-items:center;gap:7px"><input type="number" name="blanks_count" id="bcInput" value="<?= $q['blanks_count'] ?>" min="1" max="20" style="width:75px" oninput="buildBlanks(this.value)"><span style="font-size:0.75rem;color:var(--text-3)">blanks</span></div>
      </div>
      <div class="form-group">
        <label>Blank Answers</label>
        <div class="blanks-builder" id="blanksBuilder"></div>
      </div>
      <input type="hidden" name="correct_answer" value="(listening)">
      <?php endif; ?>

      <div class="form-row form-row-2" style="margin-bottom:16px">
        <div class="form-group" style="margin:0"><label>Points</label><input type="number" name="points" value="<?= $q['points'] ?>" min="1" max="100" required></div>
        <div class="form-group" style="margin:0"><label>Order #</label><input type="number" name="order_num" value="<?= $q['order_num'] ?>" min="1"></div>
      </div>
      <div class="btn-row">
        <button type="submit" class="btn-primary">💾 Save Changes</button>
        <a href="questions.php?exam_id=<?= $examId ?>&tab=<?= $tab ?>" class="btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</main>
<script>
function addEnumE(){
  const c=document.getElementById('enumItemsEdit');
  const n=c.children.length+1;
  const d=document.createElement('div');d.className='enum-row';
  d.innerHTML=`<span class="enum-num">${n}</span><input type="text" name="enum_item[]" placeholder="Item ${n}" style="flex:1"><button type="button" class="enum-del" onclick="delEnumE(this)">✕</button>`;
  c.appendChild(d);renumEnumE();
}
function delEnumE(btn){
  const c=document.getElementById('enumItemsEdit');
  if(c.children.length>1){btn.closest('.enum-row').remove();renumEnumE();}
}
function renumEnumE(){
  document.querySelectorAll('#enumItemsEdit .enum-row').forEach((r,i)=>{r.querySelector('.enum-num').textContent=i+1;});
}

function sf(i,id){document.getElementById(id).textContent=i.files[0]?'📎 '+i.files[0].name:'No file chosen';}
function swS(m,btn){document.querySelectorAll('.vsrc-tab').forEach(b=>b.classList.remove('active'));btn.classList.add('active');document.getElementById('srcUrl').style.display=m==='url'?'':'none';document.getElementById('srcFile').style.display=m==='file'?'':'none';}
const existingBlanks = <?= json_encode($blanksAnswers) ?>;
function buildBlanks(n){
  n=Math.max(1,Math.min(20,parseInt(n)||1));
  const el=document.getElementById('blanksBuilder');if(!el)return;
  let h='';
  for(let i=1;i<=n;i++){
    const v=existingBlanks[i-1]||'';
    h+=`<div class="blank-row"><div class="blank-num">${i}</div><input type="text" name="blank_ans_${i}" value="${v.replace(/"/g,'&quot;')}" placeholder="Answer for blank ${i}" style="flex:1"></div>`;
  }
  el.innerHTML=h;
}
<?php if ($q['type']==='listening'): ?>buildBlanks(<?= $q['blanks_count'] ?>);<?php endif; ?>
</script>
</body>
</html>
