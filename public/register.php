<?php
// This file handles user registration
session_start();

require_once '../config/config.php';
require_once '../src/controllers/AuthController.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$authController = new AuthController();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');

    // Validation
    if (empty($name) || empty($password) || empty($email)) {
        $error = 'All fields are required.';
    } elseif (strlen($name) < 3) {
        $error = 'Name must be at least 3 characters.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $registrationSuccess = $authController->register($name, $password, $email);

        if ($registrationSuccess) {
            header('Location: ../index.php?registered=1');
            exit;
        } else {
            $error = 'Registration failed. Name or email may already be taken.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .register-box {
            background: #fff;
            padding: 35px 40px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            width: 100%;
            max-width: 340px;
        }

        .register-box h2 {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .error {
            background: #fee;
            color: #c33;
            padding: 10px 12px;
            border-radius: 4px;
            margin-bottom: 18px;
            font-size: 0.85rem;
            border: 1px solid #fcc;
        }

        .success {
            background: #efe;
            color: #363;
            padding: 10px 12px;
            border-radius: 4px;
            margin-bottom: 18px;
            font-size: 0.85rem;
            border: 1px solid #cfc;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.95rem;
        }

        .form-group input:focus {
            outline: none;
            border-color: #333;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #333;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 5px;
        }

        button:hover {
            background: #555;
        }

        .login-link {
            text-align: center;
            margin-top: 18px;
            color: #666;
            font-size: 0.85rem;
        }

        .login-link a {
            color: #333;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-box">
        <h2>Register</h2>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit">Register</button>
        </form>
        <p class="login-link">Already have an account? <a href="index.php">Login</a></p>
    </div>
</body>
</html>