<?php
session_start();
require_once '../config/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: index.php');
    exit;
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle admin actions BEFORE fetching data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_user' && isset($_POST['user_id'])) {
        $userId = (int) $_POST['user_id'];
        // Don't allow deleting yourself
        if ($userId != (int)$_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
        }
        header("Location: admin.php");
        exit;
    }
    
    if ($_POST['action'] === 'delete_post' && isset($_POST['post_id'])) {
        $postId = (int) $_POST['post_id'];
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        header("Location: admin.php");
        exit;
    }
    
    if ($_POST['action'] === 'toggle_admin' && isset($_POST['user_id'])) {
        $userId = (int) $_POST['user_id'];
        // Don't allow removing your own admin status
        if ($userId != (int)$_SESSION['user_id']) {
            $stmt = $pdo->prepare("UPDATE users SET is_admin = NOT is_admin WHERE id = ?");
            $stmt->execute([$userId]);
        }
        header("Location: admin.php");
        exit;
    }
}

// Get statistics
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Check if posts table exists and get count
try {
    $totalPosts = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
} catch (PDOException $e) {
    $totalPosts = 0;
}

// Check if comments table exists and get count
try {
    $totalComments = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
} catch (PDOException $e) {
    $totalComments = 0;
}

// Get recent users (last 10)
$recentUsers = $pdo->query("
    SELECT id, name, email, is_admin, created_at 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent posts (last 10)
$recentPosts = $pdo->query("
    SELECT posts.*, users.name as author_name 
    FROM posts 
    JOIN users ON posts.user_id = users.id 
    ORDER BY posts.created_at DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Function to format date
function formatDate($date) {
    return date('M d, Y \a\t g:i A', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Chat Forum</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            padding-top: 90px;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-header-text h1 {
            margin: 0 0 10px 0;
            font-size: 2rem;
        }
        
        .admin-header-text p {
            margin: 0;
            opacity: 0.8;
        }

        .dark-mode-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,255,255,0.1);
            padding: 12px 20px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .dark-mode-toggle:hover {
            background: rgba(255,255,255,0.2);
        }

        .toggle-label {
            font-weight: 500;
            color: white;
        }

        .toggle-switch {
            width: 50px;
            height: 26px;
            background: rgba(255,255,255,0.3);
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
        
        /* Dark Mode Styles */
        body.dark-mode {
            background: #202225;
        }

        body.dark-mode .stat-card {
            background: #36393f;
        }

        body.dark-mode .stat-card .stat-number {
            color: #eee;
        }

        body.dark-mode .stat-card .stat-label {
            color: #b9bbbe;
        }

        body.dark-mode .admin-section {
            background: #36393f;
        }

        body.dark-mode .section-header {
            background: #2f3136;
            border-bottom-color: #40444b;
        }

        body.dark-mode .section-header h2 {
            color: #eee;
        }

        body.dark-mode .admin-table th {
            background: #2f3136;
            color: #b9bbbe;
        }

        body.dark-mode .admin-table td {
            color: #dcddde;
            border-bottom-color: #40444b;
        }

        body.dark-mode .admin-table tr:hover {
            background: #40444b;
        }

        body.dark-mode .blog-footer {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-card .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        
        .stat-card .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .admin-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .section-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .section-header h2 {
            margin: 0;
            font-size: 1.3rem;
            color: #333;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .admin-table th,
        .admin-table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .admin-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        
        .admin-table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-admin {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-user {
            background: #f5f5f5;
            color: #666;
        }
        
        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            margin-right: 5px;
        }
        
        .btn-danger {
            background: #ffebee;
            color: #c62828;
        }
        
        .btn-danger:hover {
            background: #ffcdd2;
        }
        
        .btn-toggle {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .btn-toggle:hover {
            background: #c8e6c9;
        }
        
        .post-title-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .post-content-preview {
            color: #666;
            font-size: 0.85rem;
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .admin-navbar {
            background: #1a1a2e;
        }
        
        .admin-navbar .navbar-brand h1 {
            color: white;
        }
        
        .admin-navbar .navbar-menu a {
            color: rgba(255,255,255,0.8);
        }
        
        .admin-navbar .navbar-menu a:hover {
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar admin-navbar">
        <div class="navbar-brand">
            <a href="dashboard.php" style="text-decoration: none; color: inherit;"><h1>🛡️ Admin Panel</h1></a>
        </div>
        <ul class="navbar-menu">
            <li><a href="admin.php" class="active">Dashboard</a></li>
            <li><a href="dashboard.php">User View</a></li>
            <li><a href="blog.php">Blog</a></li>
            <li><span class="user-greeting" style="color: rgba(255,255,255,0.8);">Admin: <?php echo htmlspecialchars($_SESSION['username']); ?></span></li>
            <li><a href="index.php?logout=1" class="btn-logout">Logout</a></li>
        </ul>
    </nav>

    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <div class="admin-header-text">
                <h1>📊 Admin Dashboard</h1>
                <p>Manage users, posts, and monitor activity</p>
            </div>
            <div class="dark-mode-toggle" onclick="toggleDarkMode()">
                <span class="toggle-label">Modo Escuro</span>
                <div class="toggle-switch">
                    <div class="toggle-slider" id="darkModeSlider"></div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?php echo $totalUsers; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-number"><?php echo $totalPosts; ?></div>
                <div class="stat-label">Total Posts</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💬</div>
                <div class="stat-number"><?php echo $totalComments; ?></div>
                <div class="stat-label">Total Comments</div>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="admin-section">
            <div class="section-header">
                <h2>👥 Recently Created Accounts</h2>
            </div>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentUsers)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #666;">No users found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($recentUsers as $user): ?>
                        <tr>
                            <td>#<?php echo $user['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php if ($user['is_admin']): ?>
                                    <span class="badge badge-admin">Admin</span>
                                <?php else: ?>
                                    <span class="badge badge-user">User</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatDate($user['created_at']); ?></td>
                            <td>
                                <?php if ((int)$user['id'] != (int)$_SESSION['user_id']): ?>
                                <form action="admin.php" method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_admin">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn-action btn-toggle">
                                        <?php echo $user['is_admin'] ? 'Remove Admin' : 'Make Admin'; ?>
                                    </button>
                                </form>
                                <form action="admin.php" method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn-action btn-danger" onclick="return confirm('Delete this user? This will also delete all their posts and comments.')">
                                        Delete
                                    </button>
                                </form>
                                <?php else: ?>
                                <span style="color: #999; font-size: 0.85rem;">Current user</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Posts -->
        <div class="admin-section">
            <div class="section-header">
                <h2>📝 Most Recent Blog Posts</h2>
            </div>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Content Preview</th>
                        <th>Author</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentPosts)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #666;">No posts found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($recentPosts as $post): ?>
                        <tr>
                            <td>#<?php echo $post['id']; ?></td>
                            <td class="post-title-cell"><strong><?php echo htmlspecialchars($post['title']); ?></strong></td>
                            <td class="post-content-preview"><?php echo htmlspecialchars(substr($post['content'], 0, 100)); ?>...</td>
                            <td><?php echo htmlspecialchars($post['author_name']); ?></td>
                            <td><?php echo formatDate($post['created_at']); ?></td>
                            <td>
                                <a href="blog.php#post-<?php echo $post['id']; ?>" class="btn-action btn-toggle">View</a>
                                <form action="admin.php" method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_post">
                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                    <button type="submit" class="btn-action btn-danger" onclick="return confirm('Delete this post?')">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer -->
    <footer class="blog-footer">
        <p>&copy; <?php echo date("Y"); ?> Chat Forum Admin Panel. All rights reserved.</p>
    </footer>

    <script>
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
