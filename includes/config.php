<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'anpr_system');

// Application configuration
define('APP_NAME', 'ANPR Residential System');
define('APP_URL', 'http://localhost/anpr_system');
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');

// Session configuration
session_start();

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Kuala_Lumpur');
?>