<?php

define('DB_HOST', getenv('MYSQLHOST')     ?: getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', (int)(getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: 3306));
define('DB_USER', getenv('MYSQLUSER')     ?: getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'railway');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if (!$conn) {
    die("
        <div style='font-family:sans-serif;padding:40px;color:#7f1d1d;background:#fff1f2;border-left:4px solid #fca5a5;margin:40px;border-radius:12px;'>
            <h2>❌ Database connection failed</h2>
            <p>" . mysqli_connect_error() . "</p>
            <p>Check your <code>.env</code> credentials.</p>
        </div>
    ");
}

mysqli_set_charset($conn, 'utf8mb4');
mysqli_query($conn, "SET time_zone = '+08:00'");
?>