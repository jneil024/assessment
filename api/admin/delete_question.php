<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';

$db     = getDB();
$qid    = intval($_GET['id'] ?? 0);
$examId = intval($_GET['exam_id'] ?? 0);
$tab    = $_GET['tab'] ?? '';

$q = $db->prepare("SELECT * FROM questions WHERE id=? AND exam_id=?");
$q->execute([$qid, $examId]); $q = $q->fetch();

if ($q) {
    if ($q['audio_file'] && file_exists(UPLOAD_DIR . $q['audio_file']))
        unlink(UPLOAD_DIR . $q['audio_file']);
    if ($q['video_file'] && file_exists(VIDEO_DIR . $q['video_file']))
        unlink(VIDEO_DIR . $q['video_file']);
    $db->prepare("DELETE FROM questions WHERE id=?")->execute([$qid]);
    flash('success', 'Question deleted.');
}
header("Location: questions.php?exam_id=$examId&tab=$tab"); exit;
