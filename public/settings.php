<?php
session_start();
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'];
$userId = $_SESSION['user_id'];
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // Update profile
        if ($_POST['action'] === 'update_profile') {
            $newName = trim($_POST['name']);
            $newEmail = trim($_POST['email']);
            
            if (!empty($newName) && !empty($newEmail)) {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt->execute([$newName, $newEmail, $userId]);
                $_SESSION['username'] = $newName;
                $username = $newName;
                $message = 'Perfil atualizado com sucesso!';
                $messageType = 'success';
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        
        // Change password
        if ($_POST['action'] === 'change_password') {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            if (password_verify($currentPassword, $user['password'])) {
                if ($newPassword === $confirmPassword) {
                    if (strlen($newPassword) >= 6) {
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashedPassword, $userId]);
                        $message = 'Palavra-passe alterada com sucesso!';
                        $messageType = 'success';
                    } else {
                        $message = 'A nova palavra-passe deve ter pelo menos 6 caracteres.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'As palavras-passe não coincidem.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Palavra-passe atual incorreta.';
                $messageType = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Myspace</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body {
            background: #f0f2f5;
        }

        body.dark-mode {
            background: #202225;
        }

        .settings-main {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            padding-top: 90px;
        }

        .settings-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .settings-header h1 {
            font-size: 2rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dark-mode-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 12px 20px;
            border-radius: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s;
        }

        .dark-mode-toggle:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        .toggle-icon {
            font-size: 1.2rem;
        }

        .toggle-label {
            font-weight: 500;
            color: #333;
        }

        .toggle-switch {
            width: 50px;
            height: 26px;
            background: #ddd;
            border-radius: 13px;
            position: relative;
            transition: background 0.3s;
        }

        .toggle-slider {
            width: 22px;
            height: 22px;
            background: white;
            border-radius: 50%;
            position: absolute;
            top: 2px;
            left: 2px;
            transition: transform 0.3s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        body.dark-mode .toggle-switch {
            background: #667eea;
        }

        body.dark-mode .toggle-slider {
            transform: translateX(24px);
        }

        body.dark-mode .dark-mode-toggle {
            background: #2d2d44;
        }

        body.dark-mode .toggle-label {
            color: #eee;
        }

        .settings-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .settings-card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
        }

        .settings-card-header h2 {
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .settings-card-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Dark mode */
        body.dark-mode .settings-header h1 {
            color: #eee;
        }

        body.dark-mode .settings-card {
            background: #36393f;
        }

        body.dark-mode .settings-card-header {
            background: linear-gradient(135deg, #5865f2 0%, #7289da 100%);
        }

        body.dark-mode .form-group label {
            color: #eee;
        }

        body.dark-mode .form-group input {
            background: #40444b;
            border-color: #202225;
            color: #eee;
        }

        body.dark-mode .message.success {
            background: #3ba55d;
            color: #fff;
            border-color: #3ba55d;
        }

        body.dark-mode .message.error {
            background: #ed4245;
            color: #fff;
            border-color: #ed4245;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-brand">
            <a href="dashboard.php" style="text-decoration: none; color: inherit;"><h1>Myspace</h1></a>
        </div>
        <ul class="navbar-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="blog.php">Blog</a></li>
            <li><a href="chat.php">Chat</a></li>
            <?php if ($isAdmin): ?>
            <li><a href="admin.php" class="btn-admin">🛡️ Admin</a></li>
            <?php endif; ?>
            <li>
                <a href="profile.php" class="profile-btn">Perfil</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="settings-main">
        <div class="settings-header">
            <h1>⚙️ Settings</h1>
            <div class="dark-mode-toggle" onclick="toggleDarkMode()">
                <span class="toggle-label">Modo Escuro</span>
                <div class="toggle-switch">
                    <div class="toggle-slider" id="darkModeSlider"></div>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- Profile Settings -->
        <div class="settings-card">
            <div class="settings-card-header">
                <h2>👤 Informações do Perfil</h2>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="settings.php">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label for="name">Nome</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <button type="submit" class="btn-save">Guardar Alterações</button>
                </form>
            </div>
        </div>

        <!-- Password Settings -->
        <div class="settings-card">
            <div class="settings-card-header">
                <h2>🔐 Alterar Palavra-passe</h2>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="settings.php">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label for="current_password">Palavra-passe Atual</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">Nova Palavra-passe</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirmar Nova Palavra-passe</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn-save">Alterar Palavra-passe</button>
                </form>
            </div>
        </div>
    </main>

    <script>
        // User Dropdown Toggle
        function toggleUserDropdown() {
            const dropdown = document.querySelector('.user-dropdown');
            dropdown.classList.toggle('active');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.querySelector('.user-dropdown');
            if (dropdown && !dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });

        // Dark Mode Toggle
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('darkMode', isDark);
        }

        // Load saved theme preference
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('darkMode');
            if (savedTheme === 'true') {
                document.body.classList.add('dark-mode');
            }
        });
    </script>
</body>
</html>
