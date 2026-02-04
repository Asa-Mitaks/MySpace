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
    
    // Add profile_image column if not exists
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_image VARCHAR(500) DEFAULT NULL");
    } catch (PDOException $e) {}
    
    // Handle profile image upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
        if ($_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = $_FILES['profile_image']['type'];
            
            if (in_array($fileType, $allowedTypes)) {
                $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $fileName = 'avatar_' . $userId . '_' . time() . '.' . $extension;
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
                    // Delete old avatar if exists
                    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $oldImage = $stmt->fetchColumn();
                    if ($oldImage && file_exists($oldImage)) {
                        unlink($oldImage);
                    }
                    
                    // Update database
                    $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                    $stmt->execute([$targetPath, $userId]);
                }
            }
        }
        header("Location: profile.php");
        exit;
    }
    
    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user's post count
    $stmt = $pdo->prepare("SELECT COUNT(*) as post_count FROM posts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $postCount = $stmt->fetch(PDO::FETCH_ASSOC)['post_count'];
    
    // Get user's comment count
    $stmt = $pdo->prepare("SELECT COUNT(*) as comment_count FROM comments WHERE user_id = ?");
    $stmt->execute([$userId]);
    $commentCount = $stmt->fetch(PDO::FETCH_ASSOC)['comment_count'];
    
    // Get likes given by user (only count if post still exists)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as likes_given 
        FROM post_likes 
        INNER JOIN posts ON post_likes.post_id = posts.id 
        WHERE post_likes.user_id = ?
    ");
    $stmt->execute([$userId]);
    $likesGiven = $stmt->fetch(PDO::FETCH_ASSOC)['likes_given'];
    
    // Get likes received on user's posts (only existing posts)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as likes_received 
        FROM post_likes 
        INNER JOIN posts ON post_likes.post_id = posts.id 
        WHERE posts.user_id = ?
    ");
    $stmt->execute([$userId]);
    $likesReceived = $stmt->fetch(PDO::FETCH_ASSOC)['likes_received'];
    
    // Create friendships table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS friendships (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        friend_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_friendship (user_id, friend_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Get user's friends
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.profile_image, f.created_at as friendship_date
        FROM friendships f
        JOIN users u ON f.friend_id = u.id
        WHERE f.user_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$userId]);
    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $friendCount = count($friends);
    
    // Handle remove friend
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_friend') {
        $friendId = (int) $_POST['friend_id'];
        $stmt = $pdo->prepare("DELETE FROM friendships WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
        $stmt->execute([$userId, $friendId, $friendId, $userId]);
        header("Location: profile.php");
        exit;
    }
    
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$profileImage = $user['profile_image'] ?? null;
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil - Myspace</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body {
            background: #f0f2f5;
        }

        body.dark-mode {
            background: #202225;
        }

        .profile-main {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            padding-top: 90px;
        }

        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            position: relative;
        }

        .settings-gear {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            text-decoration: none;
            opacity: 0.8;
            transition: all 0.3s;
            z-index: 10;
        }

        .settings-gear:hover {
            opacity: 1;
            transform: rotate(45deg);
        }

        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            text-align: center;
            color: white;
        }

        .profile-avatar-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            cursor: pointer;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: white;
            color: #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            overflow: hidden;
            transition: all 0.3s;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-avatar-container:hover .profile-avatar {
            filter: brightness(0.8);
        }

        .avatar-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .profile-avatar-container:hover .avatar-overlay {
            opacity: 1;
        }

        .avatar-overlay span {
            color: white;
            font-size: 2rem;
        }

        .profile-name {
            font-size: 2rem;
            margin-bottom: 5px;
        }

        .profile-email {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            padding: 30px;
            border-bottom: 1px solid #eee;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #667eea;
        }

        .stat-label {
            color: #666;
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .stat-item.likes-given .stat-value {
            color: #e74c3c;
        }

        .stat-item.likes-received .stat-value {
            color: #27ae60;
        }

        .profile-info {
            padding: 30px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-icon {
            font-size: 1.5rem;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 0.85rem;
            color: #666;
        }

        .info-value {
            font-weight: 600;
            color: #333;
        }

        /* Friends Section */
        .friends-section {
            padding: 25px;
            border-top: 1px solid #eee;
        }

        .friends-title {
            font-size: 1.1rem;
            color: #333;
            margin-bottom: 15px;
        }

        .friends-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .friend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 30px;
            transition: all 0.3s;
        }

        .friend-item:hover {
            background: #f0f0f0;
        }

        .friend-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            overflow: hidden;
        }

        .friend-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .friend-name {
            font-weight: 500;
            color: #333;
        }

        .btn-remove-friend {
            background: none;
            border: none;
            color: #999;
            font-size: 1rem;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .btn-remove-friend:hover {
            background: #ffebee;
            color: #e53935;
        }

        .stat-item.friends .stat-value {
            color: #667eea;
        }

        /* Dark mode */
        body.dark-mode .profile-card {
            background: #36393f;
        }

        body.dark-mode .profile-stats {
            background: #2f3136;
            border-bottom-color: #40444b;
        }

        body.dark-mode .info-item {
            border-bottom-color: #40444b;
        }

        body.dark-mode .info-value,
        body.dark-mode .profile-name {
            color: #eee;
        }

        body.dark-mode .stat-label,
        body.dark-mode .info-label {
            color: #b9bbbe;
        }

        body.dark-mode .profile-email {
            color: #b9bbbe;
        }

        body.dark-mode .stat-value {
            color: #eee;
        }

        body.dark-mode .friends-section {
            border-top-color: #40444b;
        }

        body.dark-mode .friends-title {
            color: #eee;
        }

        body.dark-mode .friend-item {
            background: #40444b;
        }

        body.dark-mode .friend-item:hover {
            background: #4a4d52;
        }

        body.dark-mode .friend-name {
            color: #eee;
        }

        body.dark-mode .btn-remove-friend {
            color: #72767d;
        }

        body.dark-mode .btn-remove-friend:hover {
            background: rgba(229, 57, 53, 0.2);
            color: #ff6b6b;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .profile-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .friends-list {
                flex-direction: column;
            }

            .friend-item {
                width: 100%;
            }
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

        .btn-confirm-remove {
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

        .btn-confirm-remove:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
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
            <li>
                <a href="logout.php" class="btn-logout">Sair</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="profile-main">
        <div class="profile-card">
            <a href="settings.php" class="settings-gear" title="Settings">⚙️</a>
            <div class="profile-header">
                <form id="avatarForm" action="profile.php" method="POST" enctype="multipart/form-data">
                    <div class="profile-avatar-container" onclick="document.getElementById('avatarInput').click()">
                        <div class="profile-avatar">
                            <?php if ($profileImage && file_exists($profileImage)): ?>
                                <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile">
                            <?php else: ?>
                                <?php echo strtoupper(substr($username, 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="avatar-overlay">
                            <span>📷</span>
                        </div>
                    </div>
                    <input type="file" id="avatarInput" name="profile_image" accept="image/*" style="display: none;" onchange="document.getElementById('avatarForm').submit()">
                </form>
                <h1 class="profile-name"><?php echo htmlspecialchars($user['name']); ?></h1>
                <p class="profile-email"><?php echo htmlspecialchars($user['email']); ?></p>
            </div>

            <div class="profile-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $postCount; ?></div>
                    <div class="stat-label">Posts</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $commentCount; ?></div>
                    <div class="stat-label">Comentários</div>
                </div>
                <div class="stat-item friends">
                    <div class="stat-value"><?php echo $friendCount; ?></div>
                    <div class="stat-label">👥 Amigos</div>
                </div>
                <div class="stat-item likes-given">
                    <div class="stat-value"><?php echo $likesGiven; ?></div>
                    <div class="stat-label">❤️ Likes Dados</div>
                </div>
                <div class="stat-item likes-received">
                    <div class="stat-value"><?php echo $likesReceived; ?></div>
                    <div class="stat-label">💚 Likes Recebidos</div>
                </div>
            </div>

            <div class="profile-info">
                <div class="info-item">
                    <span class="info-icon">👤</span>
                    <div class="info-content">
                        <div class="info-label">Nome de utilizador</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['name']); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <span class="info-icon">📧</span>
                    <div class="info-content">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <span class="info-icon">📅</span>
                    <div class="info-content">
                        <div class="info-label">Membro desde</div>
                        <div class="info-value"><?php echo date('d M Y', strtotime($user['created_at'])); ?></div>
                    </div>
                </div>
            </div>

            <!-- Friends Section -->
            <?php if ($friendCount > 0): ?>
            <div class="friends-section">
                <h3 class="friends-title">👥 Amigos (<?php echo $friendCount; ?>)</h3>
                <div class="friends-list">
                    <?php foreach ($friends as $friend): ?>
                    <div class="friend-item">
                        <div class="friend-avatar">
                            <?php if (!empty($friend['profile_image']) && file_exists($friend['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($friend['profile_image']); ?>" alt="Avatar">
                            <?php else: ?>
                                <?php echo strtoupper(substr($friend['name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="friend-info">
                            <div class="friend-name"><?php echo htmlspecialchars($friend['name']); ?></div>
                        </div>
                        <button type="button" class="btn-remove-friend" title="Remover amigo" onclick="openRemoveFriendModal(<?php echo $friend['id']; ?>, '<?php echo htmlspecialchars(addslashes($friend['name'])); ?>')">✕</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Remove Friend Modal -->
    <div class="modal-overlay" id="removeFriendModal">
        <div class="modal-content">
            <div class="modal-icon">👥</div>
            <h3>Remover Amigo</h3>
            <p>Tens a certeza que queres remover <strong id="friendNameToRemove"></strong> dos teus amigos?</p>
            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closeRemoveFriendModal()">Cancelar</button>
                <form id="removeFriendForm" action="profile.php" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="remove_friend">
                    <input type="hidden" name="friend_id" id="friendIdToRemove" value="">
                    <button type="submit" class="btn-confirm-remove">Sim, Remover</button>
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
            
            const themeBtn = document.querySelector('.theme-toggle span');
            if (themeBtn) {
                themeBtn.textContent = isDark ? '☀️' : '🌙';
            }
        }

        // Load saved theme preference
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('darkMode');
            if (savedTheme === 'true') {
                document.body.classList.add('dark-mode');
                const themeBtn = document.querySelector('.theme-toggle span');
                if (themeBtn) {
                    themeBtn.textContent = '☀️';
                }
            }
        });

        // Remove Friend Modal
        function openRemoveFriendModal(friendId, friendName) {
            document.getElementById('friendIdToRemove').value = friendId;
            document.getElementById('friendNameToRemove').textContent = friendName;
            document.getElementById('removeFriendModal').classList.add('show');
        }

        function closeRemoveFriendModal() {
            document.getElementById('removeFriendModal').classList.remove('show');
        }

        // Close modal when clicking outside
        document.getElementById('removeFriendModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRemoveFriendModal();
            }
        });
    </script>
</body>
</html>
