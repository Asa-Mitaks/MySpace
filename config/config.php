<?php
// Configuration settings for the application

// Load .env file if available (local development)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
if (class_exists('Dotenv\Dotenv') && file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Database connection settings (use environment variables on Heroku)
// For Heroku, parse DATABASE_URL if available
$databaseUrl = getenv('DATABASE_URL') ?: getenv('JAWSDB_URL') ?: getenv('CLEARDB_DATABASE_URL');
if ($databaseUrl) {
    $dbParts = parse_url($databaseUrl);
    define('DB_HOST', $dbParts['host']);
    define('DB_NAME', ltrim($dbParts['path'], '/'));
    define('DB_USER', $dbParts['user']);
    define('DB_PASS', $dbParts['pass'] ?? '');
    if (isset($dbParts['port'])) {
        define('DB_PORT', $dbParts['port']);
    }
} else {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_NAME', getenv('DB_NAME') ?: 'myspace');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
}

if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ?: 3306);
}

// Application settings
define('APP_NAME', getenv('APP_NAME') ?: 'Chat Forum');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/chat-forum/public');

// Error reporting (disable in production)
if (getenv('APP_ENV') === 'production') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}
?>