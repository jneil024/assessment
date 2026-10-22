<?php
if (!isset($_SESSION['student_id'])) {
    header('Location: ../login.php?role=student'); exit;
}
