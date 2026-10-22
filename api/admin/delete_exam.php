<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';

$id = intval($_GET['id'] ?? 0);
$db = getDB();

// Delete uploaded audio & video files first
$questions = $db->prepare("SELECT audio_file, video_file FROM questions WHERE exam_id=?");
$questions->execute([$id]);
foreach ($questions->fetchAll() as $q) {
    if ($q['audio_file'] && file_exists(UPLOAD_DIR . $q['audio_file']))
        unlink(UPLOAD_DIR . $q['audio_file']);
    if ($q['video_file'] && file_exists(VIDEO_DIR . $q['video_file']))
        unlink(VIDEO_DIR . $q['video_file']);
}

// Delete in correct order to satisfy foreign key constraints
$sessions = $db->prepare("SELECT id FROM exam_sessions WHERE exam_id=?");
$sessions->execute([$id]);
foreach ($sessions->fetchAll() as $s) {
    $db->prepare("DELETE FROM student_answers WHERE session_id=?")->execute([$s['id']]);
}
$db->prepare("DELETE FROM exam_sessions WHERE exam_id=?")->execute([$id]);
$db->prepare("DELETE FROM questions WHERE exam_id=?")->execute([$id]);
$db->prepare("DELETE FROM exams WHERE id=?")->execute([$id]);

flash('success', 'Exam deleted.');
header('Location: exams.php'); exit;
