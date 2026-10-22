<?php
// --- Supabase Database Connection ---
define('DB_HOST', 'aws-0-ap-south-1.pooler.supabase.com'); // Supabase Mumbai Pooler
define('DB_PORT', '5432'); 
define('DB_USER', 'southbaptistcollege'); 
define('DB_PASS', 'DDNT2NuHQvPijv0l'); // Your Supabase Password
define('DB_NAME', 'postgres');

// --- Paths & URLs ---
define('BASE_URL', ''); 
define('UPLOAD_DIR', __DIR__ . '/uploads/audio/');
define('UPLOAD_URL', BASE_URL . '/uploads/audio/');
define('VIDEO_DIR',  __DIR__ . '/uploads/video/');
define('VIDEO_URL',  BASE_URL . '/uploads/video/');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // Using pgsql for Supabase, with sslmode=require
            $dsn = "pgsql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";sslmode=require";
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        } catch (PDOException $e) {
            die("<div style='font-family:sans-serif;padding:40px;background:#fee2e2;border:2px solid #dc2626;border-radius:8px;margin:40px auto;max-width:500px'>
                <h2>⚠️ Database Connection Failed</h2>
                <p>Check your Supabase connection details and ensure sslmode=require is in the DSN.</p>
                <p><small>Error: ".htmlspecialchars($e->getMessage())."</small></p></div>");
        }
    }
    return $pdo;
}
function flash($type,$msg){$_SESSION['flash']=['type'=>$type,'msg'=>$msg];}
function getFlash(){if(isset($_SESSION['flash'])){$f=$_SESSION['flash'];unset($_SESSION['flash']);return $f;}return null;}
?>