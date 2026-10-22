<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';

$db    = getDB();
$qid   = intval($_GET['id'] ?? 0);
$dir   = $_GET['dir'] ?? '';
$examId= intval($_GET['exam_id'] ?? 0);
$tab   = $_GET['tab'] ?? '';

if ($dir === 'up' || $dir === 'down') {
    $q = $db->prepare("SELECT * FROM questions WHERE id=? AND exam_id=? AND type=?");
    $q->execute([$qid,$examId,$tab]); $q = $q->fetch();
    if ($q) {
        if ($dir === 'up') {
            $swap = $db->prepare("SELECT * FROM questions WHERE exam_id=? AND type=? AND order_num < ? ORDER BY order_num DESC LIMIT 1");
            $swap->execute([$examId,$tab,$q['order_num']]);
        } else {
            $swap = $db->prepare("SELECT * FROM questions WHERE exam_id=? AND type=? AND order_num > ? ORDER BY order_num ASC LIMIT 1");
            $swap->execute([$examId,$tab,$q['order_num']]);
        }
        $swap = $swap->fetch();
        if ($swap) {
            $db->prepare("UPDATE questions SET order_num=? WHERE id=?")->execute([$swap['order_num'],$q['id']]);
            $db->prepare("UPDATE questions SET order_num=? WHERE id=?")->execute([$q['order_num'],$swap['id']]);
        }
    }
}
header("Location: questions.php?exam_id=$examId&tab=$tab"); exit;
