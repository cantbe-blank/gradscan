<?php

// Database connection settings

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'B@walgalawin');
define('DB_NAME', 'gradscan');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

require_once __DIR__ . '/app.php';

?>