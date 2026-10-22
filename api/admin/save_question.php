<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';

$db = getDB();
$examId    = intval($_POST['exam_id'] ?? 0);
$type      = $_POST['type'] ?? '';
$tab       = $_POST['tab'] ?? $type;
$text      = trim($_POST['question_text'] ?? '');
$answer    = trim($_POST['correct_answer'] ?? '');
$points    = max(1, intval($_POST['points'] ?? 1));
$orderNum  = max(1, intval($_POST['order_num'] ?? 1));
$rubric    = trim($_POST['essay_rubric'] ?? '');
$minWords  = intval($_POST['min_words'] ?? 0) ?: null;
$blanksCount = intval($_POST['blanks_count'] ?? 1);

$validTypes = ['multiple_choice','enumeration','spelling','essay','identification','listening'];
$exam = $db->prepare("SELECT id FROM exams WHERE id=?"); $exam->execute([$examId]); $exam=$exam->fetch();
if (!$exam || !in_array($type,$validTypes) || !$text) {
    flash('error','Invalid request.'); header("Location: questions.php?exam_id=$examId&tab=$tab"); exit;
}

$audioFile = null;
$videoFile = null;
$videoUrl  = null;
$choices   = null;
$blanksAnswers = null;
$error = '';

// --- Audio upload (spelling) ---
if ($type==='spelling' && isset($_FILES['audio_file']) && $_FILES['audio_file']['error']===0) {
    $ext = strtolower(pathinfo($_FILES['audio_file']['name'],PATHINFO_EXTENSION));
    if (!in_array($ext,['mp3','wav','ogg','m4a'])) { $error='Invalid audio format.'; }
    else {
        $fn = uniqid('audio_').'.'.$ext;
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR,0775,true);
        if (!move_uploaded_file($_FILES['audio_file']['tmp_name'],UPLOAD_DIR.$fn)) $error='Audio upload failed.';
        else $audioFile=$fn;
    }
}

// --- Video upload or URL (listening) ---
if ($type==='listening') {
    $videoUrl = trim($_POST['video_url'] ?? '');
    if (empty($videoUrl)) $videoUrl = null;

    if (isset($_FILES['video_file']) && $_FILES['video_file']['error']===0) {
        $ext = strtolower(pathinfo($_FILES['video_file']['name'],PATHINFO_EXTENSION));
        if (!in_array($ext,['mp4','webm','mov','avi'])) { $error='Invalid video format. Use MP4, WebM, or MOV.'; }
        else {
            if (!is_dir(VIDEO_DIR)) mkdir(VIDEO_DIR,0775,true);
            $fn = uniqid('video_').'.'.$ext;
            if (!move_uploaded_file($_FILES['video_file']['tmp_name'],VIDEO_DIR.$fn)) $error='Video upload failed. Check uploads/video/ is writable.';
            else { $videoFile=$fn; $videoUrl=null; } // prefer uploaded file
        }
    }

    // Collect blank answers
    $blanksArr = [];
    for ($i=1; $i<=$blanksCount; $i++) {
        $ba = trim($_POST["blank_ans_$i"] ?? '');
        if ($ba!=='') $blanksArr[] = strtolower($ba);
        else $blanksArr[] = '';
    }
    $blanksAnswers = json_encode($blanksArr);
    $blanksCount = count($blanksArr);
    $answer = '(listening)';
    $points = max(1,$points);
}

// --- Multiple choice ---
if ($type==='multiple_choice') {
    $opts = array_values(array_filter(array_map('trim',[
        $_POST['choice_a']??'',$_POST['choice_b']??'',$_POST['choice_c']??'',$_POST['choice_d']??''
    ])));
    if (count($opts)<2) { $error='At least 2 choices required.'; }
    elseif (!in_array($answer,$opts)) { $error='Correct answer must exactly match one of the choices.'; }
    else { $choices=json_encode($opts); }
}

// --- Enumeration: collect individual items, 1pt each ---
if ($type==='enumeration') {
    $rawItems = $_POST['enum_item'] ?? [];
    $parts = array_values(array_filter(array_map('trim', $rawItems)));
    if (empty($parts)) { $error='Provide at least one enumeration item.'; }
    else {
        $answer = implode(',', array_map('strtolower', $parts));
        $points = count($parts); // 1 point per item
    }
}

if ($error) { flash('error',$error); header("Location: questions.php?exam_id=$examId&tab=$tab"); exit; }

$db->prepare("INSERT INTO questions
    (exam_id,type,question_text,audio_file,video_file,video_url,correct_answer,choices,essay_rubric,min_words,blanks_count,blanks_answers,points,order_num)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
  ->execute([$examId,$type,$text,$audioFile,$videoFile,$videoUrl,$answer,$choices,$rubric?:null,$minWords,$blanksCount,$blanksAnswers,$points,$orderNum]);

flash('success','Question added!');
header("Location: questions.php?exam_id=$examId&tab=$tab"); exit;
