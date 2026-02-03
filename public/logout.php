<?php
session_start();
require_once '../config/config.php';
require_once '../src/controllers/AuthController.php';

$authController = new AuthController();
$authController->logout();

header('Location: ../index.php');
exit;
?>
