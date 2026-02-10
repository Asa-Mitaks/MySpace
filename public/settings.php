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
                $message = 'Profile updated successfully!';
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
                        $message = 'Password changed successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'New password must be at least 6 characters.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Passwords do not match.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Current password is incorrect.';
                $messageType = 'error';
            }
        }
        
        // Delete account
        if ($_POST['action'] === 'delete_account') {
            // Delete user's messages
            $stmt = $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?");
            $stmt->execute([$userId, $userId]);
            
            // Delete user's friendships
            $stmt = $pdo->prepare("DELETE FROM friendships WHERE user_id = ? OR friend_id = ?");
            $stmt->execute([$userId, $userId]);
            
            // Delete user's comments
            $stmt = $pdo->prepare("DELETE FROM comments WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete user's post likes
            $stmt = $pdo->prepare("DELETE FROM post_likes WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete user's posts
            $stmt = $pdo->prepare("DELETE FROM posts WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete user account
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            // Destroy session and redirect
            session_destroy();
            header('Location: ../index.php?deleted=1');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
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

        /* Danger Zone Styles */
        .danger-card {
            border: 2px solid #ff6b6b;
        }

        .danger-header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%) !important;
        }

        .danger-text {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        body.dark-mode .danger-text {
            color: #aaa;
        }

        .btn-delete-account {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-delete-account:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px 40px;
            border-radius: 16px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        body.dark-mode .modal-content {
            background: #2d2d44;
        }

        .modal-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .modal-content h3 {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 15px;
        }

        body.dark-mode .modal-content h3 {
            color: #eee;
        }

        .modal-content p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        body.dark-mode .modal-content p {
            color: #aaa;
        }

        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-cancel {
            background: #e0e0e0;
            color: #333;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #d0d0d0;
        }

        body.dark-mode .btn-cancel {
            background: #40444b;
            color: #eee;
        }

        body.dark-mode .btn-cancel:hover {
            background: #4a4e57;
        }

        .btn-confirm-delete {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-confirm-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
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
                <a href="profile.php" class="profile-btn">Profile</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="settings-main">
        <div class="settings-header">
            <h1>⚙️ Settings</h1>
            <div class="dark-mode-toggle" onclick="toggleDarkMode()">
                <span class="toggle-label">Dark Mode</span>
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
                <h2>👤 Profile Information</h2>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="settings.php">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <button type="submit" class="btn-save">Save Changes</button>
                </form>
            </div>
        </div>

        <!-- Password Settings -->
        <div class="settings-card">
            <div class="settings-card-header">
                <h2>🔐 Change Password</h2>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="settings.php">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn-save">Change Password</button>
                </form>
            </div>
        </div>

        <!-- Delete Account Button -->
        <div style="text-align: center; margin-top: 30px;">
            <button type="button" class="btn-delete-account" onclick="openDeleteModal()">Delete Account</button>
        </div>
    </main>

    <!-- Delete Account Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content">
            <div class="modal-icon">⚠️</div>
            <h3>Delete Account</h3>
            <p>By deleting your account, all your data will be permanently removed, including posts, comments, messages and friendships.</p>
            <p><strong>This action cannot be undone.</strong></p>
            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <form method="POST" action="settings.php" style="display: inline;">
                    <input type="hidden" name="action" value="delete_account">
                    <button type="submit" class="btn-confirm-delete">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>

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

        // Delete Account Modal
        function openDeleteModal() {
            document.getElementById('deleteModal').classList.add('show');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>
