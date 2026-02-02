<?php
// Configuration settings for the application

// Database connection settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'myspace');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application settings
define('APP_NAME', 'Chat Forum');
define('APP_URL', 'http://localhost/chat-forum/public');

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>