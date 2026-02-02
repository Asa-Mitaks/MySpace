<?php
// This is the main entry point for the application

// Include configuration file
require_once '../config/config.php';

// Include necessary controllers
require_once '../src/controllers/AuthController.php';
require_once '../src/controllers/BlogController.php';
require_once '../src/controllers/ChatController.php';

// Start session
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Redirect to dashboard if already logged in
if ($isLoggedIn && !isset($_GET['page'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

// Handle routing
$page = isset($_GET['page']) ? $_GET['page'] : 'register';

switch ($page) {
    case 'register':
        // Show register page with login
        include 'register.php';
        break;
    case 'blog':
        // Load blog page
        if (!$isLoggedIn) {
            header('Location: index.php');
            exit;
        }
        $blogController = new BlogController();
        $blogController->index();
        break;
    case 'chat':
        // Load chat page
        if (!$isLoggedIn) {
            header('Location: index.php');
            exit;
        }
        $chatController = new ChatController();
        $chatController->privateChat();
        break;
    case 'login':
        // Load login page
        $authController = new AuthController();
        $authController->login();
        break;
    case 'register':
        // Load registration page
        $authController = new AuthController();
        $authController->register();
        break;
    case 'dashboard':
        // Load dashboard page
        if (!$isLoggedIn) {
            header('Location: index.php');
            exit;
        }
        include 'dashboard.php';
        break;
    default:
        // Load home or default page
        include 'register.php';
        break;
}
?>