<?php
session_start();
require_once '../config/config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$currentUserId = $isLoggedIn ? $_SESSION['user_id'] : null;
$currentUsername = $isLoggedIn ? $_SESSION['username'] : null;
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create likes table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (post_id, user_id)
    )");
    
    // Add image_url and description columns if not exist
    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN image_url VARCHAR(500) DEFAULT NULL");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN description TEXT DEFAULT NULL");
    } catch (PDOException $e) {}
    
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Create post
    if ($_POST['action'] === 'create_post' && $isLoggedIn) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $description = trim($_POST['description'] ?? '');
        $imageUrl = '';
        
        // Handle file upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = $_FILES['image']['type'];
            
            if (in_array($fileType, $allowedTypes)) {
                $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $fileName = uniqid('post_') . '.' . $extension;
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $imageUrl = $targetPath;
                }
            }
        }
        
        if (!empty($title) && !empty($content)) {
            $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, content, description, image_url) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$currentUserId, $title, $content, $description, $imageUrl]);
        }
        header("Location: blog.php");
        exit;
    }
    
    // Toggle like
    if ($_POST['action'] === 'toggle_like' && $isLoggedIn) {
        $postId = (int) $_POST['post_id'];
        
        // Check if already liked
        $stmt = $pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$postId, $currentUserId]);
        
        if ($stmt->fetch()) {
            // Remove like
            $stmt = $pdo->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$postId, $currentUserId]);
        } else {
            // Add like
            $stmt = $pdo->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
            $stmt->execute([$postId, $currentUserId]);
        }
        
        header("Location: blog.php#post-" . $postId);
        exit;
    }
    
    // Add comment
    if ($_POST['action'] === 'add_comment' && $isLoggedIn) {
        $postId = (int) $_POST['post_id'];
        $comment = trim($_POST['comment']);
        
        if (!empty($comment)) {
            $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$postId, $currentUserId, $comment]);
        }
        header("Location: blog.php#post-" . $postId);
        exit;
    }
    
    // Delete post
    if ($_POST['action'] === 'delete_post' && $isLoggedIn) {
        $postId = (int) $_POST['post_id'];
        if ($isAdmin) {
            $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
            $stmt->execute([$postId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
            $stmt->execute([$postId, $currentUserId]);
        }
        header("Location: blog.php");
        exit;
    }
    
    // Edit post
    if ($_POST['action'] === 'edit_post' && $isLoggedIn) {
        $postId = (int) $_POST['post_id'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description'] ?? '');
        $content = trim($_POST['content']);
        
        if (!empty($title) && !empty($content)) {
            if ($isAdmin) {
                $stmt = $pdo->prepare("UPDATE posts SET title = ?, description = ?, content = ? WHERE id = ?");
                $stmt->execute([$title, $description, $content, $postId]);
            } else {
                $stmt = $pdo->prepare("UPDATE posts SET title = ?, description = ?, content = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$title, $description, $content, $postId, $currentUserId]);
            }
        }
        header("Location: blog.php#post-" . $postId);
        exit;
    }
    
    // Delete comment
    if ($_POST['action'] === 'delete_comment' && $isLoggedIn) {
        $commentId = (int) $_POST['comment_id'];
        $postId = (int) $_POST['post_id'];
        if ($isAdmin) {
            $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
            $stmt->execute([$commentId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?");
            $stmt->execute([$commentId, $currentUserId]);
        }
        header("Location: blog.php#post-" . $postId);
        exit;
    }
}

// Fetch all posts with author info and like count
$stmt = $pdo->query("
    SELECT posts.*, users.name as username, users.profile_image as author_image,
    (SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) as like_count
    FROM posts 
    JOIN users ON posts.user_id = users.id 
    ORDER BY posts.created_at DESC
");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to check if user liked a post
function hasUserLiked($pdo, $postId, $userId) {
    if (!$userId) return false;
    $stmt = $pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$postId, $userId]);
    return $stmt->fetch() !== false;
}

// Function to get comments for a post
function getComments($pdo, $postId) {
    $stmt = $pdo->prepare("
        SELECT comments.*, users.name as username, users.profile_image as commenter_image 
        FROM comments 
        JOIN users ON comments.user_id = users.id 
        WHERE comments.post_id = ? 
        ORDER BY comments.created_at ASC
    ");
    $stmt->execute([$postId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to format date
function formatDate($date) {
    $timestamp = strtotime($date);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'agora mesmo';
    if ($diff < 3600) return floor($diff / 60) . ' min';
    if ($diff < 86400) return floor($diff / 3600) . ' h';
    if ($diff < 604800) return floor($diff / 86400) . ' d';
    return date('d M Y', $timestamp);
}

// Fetch suggested users (newest users, excluding current user)
$suggestedUsers = [];
if ($isLoggedIn) {
    $stmt = $pdo->prepare("
        SELECT id, name, profile_image, created_at 
        FROM users 
        WHERE id != ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$currentUserId]);
    $suggestedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog - Myspace</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body {
            background: #f0f2f5;
        }

        body.dark-mode {
            background: #202225;
        }

        body.dark-mode .post-card {
            background: #36393f;
        }

        body.dark-mode .post-title {
            color: #eee;
        }

        body.dark-mode .post-description,
        body.dark-mode .post-body {
            color: #dcddde;
        }

        body.dark-mode .author-name {
            color: #eee;
        }

        body.dark-mode .post-date {
            color: #b9bbbe;
        }

        body.dark-mode .post-actions {
            border-top-color: #40444b;
        }

        body.dark-mode .action-btn {
            color: #b9bbbe;
        }

        body.dark-mode .action-btn:hover {
            background: #40444b;
            color: #eee;
        }

        body.dark-mode .comments-section {
            background: #36393f;
            border-top-color: #40444b;
        }

        body.dark-mode .comments-list {
            background: #36393f;
        }

        body.dark-mode .comment {
            border-bottom-color: #40444b;
        }

        body.dark-mode .comment-bubble {
            background: #40444b;
        }

        body.dark-mode .comment-author {
            color: #eee;
        }

        body.dark-mode .comment-text {
            color: #dcddde;
        }

        body.dark-mode .comment-date,
        body.dark-mode .comment-time {
            color: #72767d;
        }

        body.dark-mode .comment-input-wrapper input {
            background: #40444b;
            border-color: #202225;
            color: #eee;
        }

        body.dark-mode .comment-form input {
            background: #40444b;
            border-color: #202225;
            color: #eee;
        }

        body.dark-mode .comment-form input::placeholder {
            color: #72767d;
        }

        body.dark-mode .comment-form textarea {
            background: #40444b;
            border-color: #202225;
            color: #eee;
        }

        body.dark-mode .comment-form textarea::placeholder {
            color: #72767d;
        }

        body.dark-mode .comment-form {
            border-top-color: #40444b;
        }

        body.dark-mode .empty-state {
            background: #36393f;
            color: #eee;
        }

        body.dark-mode .comment-avatar {
            color: #eee;
        }

        body.dark-mode .post-header {
            background: #36393f;
        }

        body.dark-mode .login-prompt {
            background: #36393f;
            color: #dcddde;
        }

        body.dark-mode .login-prompt a {
            color: #5865f2;
        }

        body.dark-mode .post-menu-btn {
            color: #b9bbbe;
        }

        body.dark-mode .post-menu-btn:hover {
            background: #40444b;
        }

        body.dark-mode .comments-toggle {
            color: #b9bbbe;
        }

        body.dark-mode .comments-toggle:hover {
            color: #eee;
        }

        body.dark-mode .btn-cancel {
            background: #40444b;
            color: #eee;
        }

        body.dark-mode .btn-cancel:hover {
            background: #4f545c;
        }

        body.dark-mode .modal-content {
            background: #36393f;
        }

        body.dark-mode .modal {
            background: #36393f;
        }

        body.dark-mode .modal-header {
            border-bottom-color: #40444b;
        }

        body.dark-mode .modal-header h2 {
            color: #eee;
        }

        body.dark-mode .form-group label {
            color: #eee;
        }

        body.dark-mode .form-group input,
        body.dark-mode .form-group textarea {
            background: #40444b;
            border-color: #202225;
            color: #eee;
        }

        body.dark-mode .post-options-btn:hover {
            background: #40444b;
        }

        body.dark-mode .post-options-dropdown {
            background: #36393f;
        }

        body.dark-mode .post-options-dropdown button {
            color: #eee;
        }

        body.dark-mode .post-options-dropdown button:hover {
            background: #40444b;
        }

        body.dark-mode .image-preview-area {
            background: #2f3136;
            border-color: #40444b;
        }

        /* Main Container */
        .blog-wrapper {
            display: flex;
            max-width: 1250px;
            margin: 0 auto;
            padding: 20px;
            padding-top: 90px;
            gap: 30px;
        }

        .blog-main {
            flex: 1;
            max-width: 780px;
        }

        /* Sidebar */
        .blog-sidebar {
            width: 340px;
            flex-shrink: 0;
        }

        .sidebar-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            position: sticky;
            top: 90px;
        }

        .sidebar-title {
            font-size: 1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .suggested-users {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .suggested-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border-radius: 12px;
            transition: background 0.2s;
        }

        .suggested-user:hover {
            background: #f5f5f5;
        }

        .suggested-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            overflow: hidden;
            flex-shrink: 0;
        }

        .suggested-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .suggested-info {
            flex: 1;
            min-width: 0;
        }

        .suggested-name {
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .suggested-joined {
            font-size: 0.8rem;
            color: #888;
        }

        .btn-add-friend {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-add-friend:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .no-suggestions {
            color: #888;
            font-size: 0.9rem;
            text-align: center;
            padding: 20px 0;
        }

        /* Dark mode sidebar */
        body.dark-mode .sidebar-card {
            background: #36393f;
        }

        body.dark-mode .sidebar-title {
            color: #eee;
        }

        body.dark-mode .suggested-user:hover {
            background: #40444b;
        }

        body.dark-mode .suggested-name {
            color: #eee;
        }

        body.dark-mode .suggested-joined {
            color: #b9bbbe;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .blog-wrapper {
                flex-direction: column;
            }
            
            .blog-sidebar {
                width: 100%;
                order: -1;
            }
            
            .sidebar-card {
                position: relative;
                top: 0;
            }
        }

        /* Create Post Button (Fixed) */
        .create-post-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 50%;
            color: white;
            font-size: 28px;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.5);
            transition: all 0.3s;
            z-index: 99;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .create-post-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 30px rgba(102, 126, 234, 0.6);
        }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        /* Modal */
        .modal {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 550px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlide 0.3s ease;
        }

        @keyframes modalSlide {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 1.3rem;
            color: #333;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            color: #666;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }

        .modal-close:hover {
            color: #333;
        }

        .modal-body {
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

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1rem;
            transition: border-color 0.3s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        /* Drop Zone Styles */
        .drop-zone {
            border: 2px dashed #ccc;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
            position: relative;
        }

        .drop-zone:hover,
        .drop-zone.drag-over {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .drop-zone-content {
            pointer-events: none;
        }

        .drop-zone-icon {
            font-size: 3rem;
            display: block;
            margin-bottom: 10px;
        }

        .drop-zone p {
            color: #666;
            margin: 5px 0;
        }

        .browse-link {
            color: #667eea;
            font-weight: 600;
        }

        .drop-zone-hint {
            font-size: 0.85rem;
            color: #999;
        }

        .drop-zone-preview {
            position: relative;
        }

        .drop-zone-preview img {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }

        .remove-image-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 30px;
            height: 30px;
            background: rgba(0, 0, 0, 0.6);
            border: none;
            border-radius: 50%;
            color: white;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }

        .remove-image-btn:hover {
            background: rgba(231, 76, 60, 0.9);
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel {
            background: #f0f0f0;
            color: #666;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        .btn-publish {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-publish:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        /* Posts Feed */
        .posts-feed {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Post Card */
        .post-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .post-header {
            padding: 20px 20px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: none;
        }

        .post-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .author-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            overflow: hidden;
        }

        .author-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .author-info {
            display: flex;
            flex-direction: column;
        }

        .author-name {
            font-weight: 600;
            color: #333;
        }

        .post-date {
            font-size: 0.85rem;
            color: #666;
        }

        .post-menu {
            position: relative;
        }

        .post-menu-btn {
            background: none;
            border: none;
            font-size: 20px;
            color: #666;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 50%;
        }

        .post-menu-btn:hover {
            background: #f0f0f0;
        }

        .post-image {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
        }

        .post-content {
            padding: 0 20px 16px;
        }

        .post-title {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 8px;
        }

        .post-description {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 10px;
            font-style: italic;
        }

        .post-body {
            color: #333;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        /* Post Actions */
        .post-actions {
            padding: 12px 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 20px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            background: none;
            border: none;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 0.85rem;
            color: #666;
            cursor: pointer;
            transition: all 0.2s;
        }

        .action-btn:hover {
            background: #f0f2f5;
        }

        .action-btn.liked {
            color: #e74c3c;
        }

        .action-btn.liked .like-icon {
            animation: likeAnim 0.3s ease;
        }

        @keyframes likeAnim {
            50% { transform: scale(1.3); }
        }

        .like-icon {
            font-size: 1.2rem;
        }

        /* Comments */
        .comments-section {
            border-top: 1px solid #eee;
            padding: 16px 20px;
        }

        .comments-toggle {
            background: none;
            border: none;
            color: #667eea;
            font-weight: 600;
            cursor: pointer;
            padding: 8px 0;
            font-size: 0.95rem;
        }

        .comments-list {
            margin-top: 15px;
            display: none;
        }

        .comments-list.show {
            display: block;
        }

        .comment {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .comment:last-child {
            border-bottom: none;
        }

        .comment-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
            color: #333;
            flex-shrink: 0;
            overflow: hidden;
        }

        .comment-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .comment-content {
            flex: 1;
        }

        .comment-bubble {
            background: #f0f2f5;
            padding: 10px 14px;
            border-radius: 12px;
            display: inline-block;
        }

        .comment-author {
            font-weight: 600;
            font-size: 0.9rem;
            color: #333;
        }

        .comment-text {
            color: #333;
            font-size: 0.95rem;
            margin-top: 2px;
        }

        .comment-meta {
            display: flex;
            gap: 15px;
            margin-top: 5px;
            padding-left: 14px;
        }

        .comment-time {
            font-size: 0.8rem;
            color: #666;
        }

        .comment-delete {
            font-size: 0.8rem;
            color: #e74c3c;
            background: none;
            border: none;
            cursor: pointer;
        }

        /* Comment Form */
        .comment-form {
            display: flex;
            gap: 12px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }

        .comment-form textarea {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 20px;
            resize: none;
            font-family: inherit;
            font-size: 0.95rem;
        }

        .comment-form textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .comment-form button {
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .comment-form button:hover {
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #666;
        }

        /* Login Prompt */
        .login-prompt {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 20px;
        }

        .login-prompt a {
            color: white;
            font-weight: 600;
            text-decoration: underline;
        }

        /* Post Options (Three-dot menu) */
        .post-options {
            position: relative;
        }

        .post-options-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #666;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 5px;
            letter-spacing: 2px;
        }

        .post-options-btn:hover {
            background: #f0f0f0;
            color: #333;
        }

        .post-options-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            min-width: 120px;
            z-index: 100;
            overflow: hidden;
        }

        .post-options-dropdown.show {
            display: block;
        }

        .post-options-dropdown button {
            display: block;
            width: 100%;
            padding: 12px 16px;
            background: none;
            border: none;
            text-align: left;
            cursor: pointer;
            font-size: 0.9rem;
            color: #333;
            transition: background 0.2s;
        }

        .post-options-dropdown button:hover {
            background: #f5f5f5;
        }

        .post-options-dropdown .btn-delete-option {
            color: #e74c3c;
        }

        .post-options-dropdown .btn-delete-option:hover {
            background: #fee;
        }

        /* Delete Form */
        .delete-form {
            display: block;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .blog-main {
                padding: 15px;
            }

            .create-post-btn {
                bottom: 20px;
                right: 20px;
                width: 55px;
                height: 55px;
            }

            .modal {
                border-radius: 15px;
            }

            .navbar-menu {
                gap: 10px;
            }

            .navbar-menu a {
                padding: 6px 12px;
                font-size: 0.9rem;
            }
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
            <li><a href="blog.php" class="active">Blog</a></li>
            <li><a href="chat.php">Chat</a></li>
            <?php if ($isAdmin): ?>
            <li><a href="admin.php" class="btn-admin">🛡️ Admin</a></li>
            <?php endif; ?>
            <?php if ($isLoggedIn): ?>
            <li>
                <a href="profile.php" class="profile-btn">Perfil</a>
            </li>
            <?php else: ?>
            <li><a href="index.php">Entrar</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Main Content Wrapper -->
    <div class="blog-wrapper">
        <!-- Main Content -->
        <main class="blog-main">
            <?php if (!$isLoggedIn): ?>
            <div class="login-prompt">
                <p>🔐 <a href="index.php">Entra na tua conta</a> para criar posts e comentar.</p>
            </div>
            <?php endif; ?>

            <!-- Posts Feed -->
            <div class="posts-feed">
            <?php if (empty($posts)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <h3>Ainda não há posts</h3>
                <p>Sê o primeiro a partilhar algo com a comunidade!</p>
            </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                <?php 
                    $userLiked = hasUserLiked($pdo, $post['id'], $currentUserId);
                    $comments = getComments($pdo, $post['id']);
                    $commentCount = count($comments);
                ?>
                <article class="post-card" id="post-<?php echo $post['id']; ?>">
                    <!-- Post Header -->
                    <div class="post-header">
                        <div class="post-author">
                            <div class="author-avatar">
                                <?php if (!empty($post['author_image']) && file_exists($post['author_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($post['author_image']); ?>" alt="Avatar">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($post['username'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="author-info">
                                <span class="author-name"><?php echo htmlspecialchars($post['username']); ?></span>
                                <span class="post-date"><?php echo formatDate($post['created_at']); ?></span>
                            </div>
                        </div>
                        <?php if ($isLoggedIn && ($post['user_id'] == $currentUserId || $isAdmin)): ?>
                        <div class="post-options">
                            <button class="post-options-btn" onclick="togglePostMenu(<?php echo $post['id']; ?>)">•••</button>
                            <div class="post-options-dropdown" id="postMenu-<?php echo $post['id']; ?>">
                                <button onclick="openEditModal(<?php echo $post['id']; ?>, '<?php echo addslashes(htmlspecialchars($post['title'])); ?>', '<?php echo addslashes(htmlspecialchars($post['description'] ?? '')); ?>', '<?php echo addslashes(htmlspecialchars($post['content'])); ?>')">Editar</button>
                                <form action="blog.php" method="POST" class="delete-form">
                                    <input type="hidden" name="action" value="delete_post">
                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                    <button type="submit" class="btn-delete-option" onclick="return confirm('Tens a certeza que queres apagar este post?')">Apagar</button>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Post Image -->
                    <?php if (!empty($post['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="Post image" class="post-image">
                    <?php endif; ?>

                    <!-- Post Content -->
                    <div class="post-content">
                        <h3 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                        <?php if (!empty($post['description'])): ?>
                        <p class="post-description"><?php echo htmlspecialchars($post['description']); ?></p>
                        <?php endif; ?>
                        <p class="post-body"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                    </div>

                    <!-- Post Actions -->
                    <div class="post-actions">
                        <?php if ($isLoggedIn): ?>
                        <form action="blog.php" method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_like">
                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                            <button type="submit" class="action-btn <?php echo $userLiked ? 'liked' : ''; ?>">
                                <span class="like-icon"><?php echo $userLiked ? '❤️' : '🤍'; ?></span>
                                <span><?php echo $post['like_count']; ?> <?php echo $post['like_count'] == 1 ? 'Like' : 'Likes'; ?></span>
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="action-btn">
                            <span class="like-icon">🤍</span>
                            <span><?php echo $post['like_count']; ?> Likes</span>
                        </span>
                        <?php endif; ?>
                        
                        <button class="action-btn" onclick="toggleComments(<?php echo $post['id']; ?>)">
                            <span>💬</span>
                            <span><?php echo $commentCount; ?> <?php echo $commentCount == 1 ? 'Comentário' : 'Comentários'; ?></span>
                        </button>
                    </div>

                    <!-- Comments Section -->
                    <div class="comments-section">
                        <div class="comments-list" id="comments-<?php echo $post['id']; ?>">
                            <?php foreach ($comments as $comment): ?>
                            <div class="comment">
                                <div class="comment-avatar">
                                    <?php if (!empty($comment['commenter_image']) && file_exists($comment['commenter_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($comment['commenter_image']); ?>" alt="Avatar">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($comment['username'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="comment-content">
                                    <div class="comment-bubble">
                                        <span class="comment-author"><?php echo htmlspecialchars($comment['username']); ?></span>
                                        <p class="comment-text"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                                    </div>
                                    <div class="comment-meta">
                                        <span class="comment-time"><?php echo formatDate($comment['created_at']); ?></span>
                                        <?php if ($isLoggedIn && ($comment['user_id'] == $currentUserId || $isAdmin)): ?>
                                        <form action="blog.php" method="POST" class="delete-form">
                                            <input type="hidden" name="action" value="delete_comment">
                                            <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                            <button type="submit" class="comment-delete" onclick="return confirm('Apagar comentário?')">Apagar</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <?php if ($isLoggedIn): ?>
                            <form action="blog.php" method="POST" class="comment-form">
                                <input type="hidden" name="action" value="add_comment">
                                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                <textarea name="comment" placeholder="Escreve um comentário..." rows="1" required></textarea>
                                <button type="submit">Enviar</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Sidebar -->
    <?php if ($isLoggedIn): ?>
    <aside class="blog-sidebar">
        <div class="sidebar-card">
            <h3 class="sidebar-title">👥 Sugestões para ti</h3>
            <?php if (!empty($suggestedUsers)): ?>
            <div class="suggested-users">
                <?php foreach ($suggestedUsers as $user): ?>
                <div class="suggested-user">
                    <div class="suggested-avatar">
                        <?php if (!empty($user['profile_image']) && file_exists($user['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="suggested-info">
                        <div class="suggested-name"><?php echo htmlspecialchars($user['name']); ?></div>
                        <div class="suggested-joined">Juntou-se <?php echo formatDate($user['created_at']); ?></div>
                    </div>
                    <button class="btn-add-friend" title="Adicionar amigo">+ Amigo</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="no-suggestions">Sem sugestões de momento</p>
            <?php endif; ?>
        </div>
    </aside>
    <?php endif; ?>
    </div>

    <!-- Create Post Button -->
    <?php if ($isLoggedIn): ?>
    <button class="create-post-btn" onclick="openModal()">+</button>
    <?php endif; ?>

    <!-- Create Post Modal -->
    <div class="modal-overlay" id="createPostModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Criar Novo Post</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form action="blog.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_post">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="title">Título</label>
                        <input type="text" id="title" name="title" placeholder="Escreve um título apelativo..." required>
                    </div>
                    <div class="form-group">
                        <label for="description">Descrição (opcional)</label>
                        <input type="text" id="description" name="description" placeholder="Uma breve descrição do teu post...">
                    </div>
                    <div class="form-group">
                        <label>Imagem (opcional)</label>
                        <div class="drop-zone" id="dropZone">
                            <div class="drop-zone-content" id="dropZoneContent">
                                <span class="drop-zone-icon">📷</span>
                                <p>Arrasta uma imagem ou <span class="browse-link">clica aqui</span></p>
                                <p class="drop-zone-hint">PNG, JPG, GIF ou WEBP (máx. 5MB)</p>
                            </div>
                            <div class="drop-zone-preview" id="dropZonePreview" style="display: none;">
                                <img id="previewImg" src="" alt="Preview">
                                <button type="button" class="remove-image-btn" onclick="removeImage()">✕</button>
                            </div>
                            <input type="file" id="imageInput" name="image" accept="image/*" style="display: none;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="content">Conteúdo</label>
                        <textarea id="content" name="content" rows="5" placeholder="Partilha as tuas ideias..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-publish">Publicar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Post Modal -->
    <div class="modal-overlay" id="editPostModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Editar Post</h2>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form action="blog.php" method="POST">
                <input type="hidden" name="action" value="edit_post">
                <input type="hidden" name="post_id" id="edit_post_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_title">Título</label>
                        <input type="text" id="edit_title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Descrição (opcional)</label>
                        <input type="text" id="edit_description" name="description">
                    </div>
                    <div class="form-group">
                        <label for="edit_content">Conteúdo</label>
                        <textarea id="edit_content" name="content" rows="5" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeEditModal()">Cancelar</button>
                    <button type="submit" class="btn btn-publish">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal() {
            document.getElementById('createPostModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('createPostModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Post options menu toggle
        function togglePostMenu(postId) {
            // Close all other menus first
            document.querySelectorAll('.post-options-dropdown').forEach(menu => {
                if (menu.id !== 'postMenu-' + postId) {
                    menu.classList.remove('show');
                }
            });
            
            const menu = document.getElementById('postMenu-' + postId);
            menu.classList.toggle('show');
        }

        // Close post menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.post-options')) {
                document.querySelectorAll('.post-options-dropdown').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });

        // Edit Modal functions
        function openEditModal(postId, title, description, content) {
            document.getElementById('edit_post_id').value = postId;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_content').value = content;
            document.getElementById('editPostModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editPostModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close edit modal on outside click
        document.getElementById('editPostModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Close modal on outside click
        document.getElementById('createPostModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Drag and Drop Image Upload
        const dropZone = document.getElementById('dropZone');
        const imageInput = document.getElementById('imageInput');
        const dropZoneContent = document.getElementById('dropZoneContent');
        const dropZonePreview = document.getElementById('dropZonePreview');
        const previewImg = document.getElementById('previewImg');

        // Click to upload
        dropZone.addEventListener('click', function() {
            imageInput.click();
        });

        // Handle file selection
        imageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                handleFile(this.files[0]);
            }
        });

        // Drag and drop events
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });

        dropZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
        });

        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                const file = e.dataTransfer.files[0];
                
                // Validate file type
                if (!file.type.match(/image\/(jpeg|png|gif|webp)/)) {
                    alert('Por favor, seleciona uma imagem válida (PNG, JPG, GIF ou WEBP)');
                    return;
                }
                
                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    alert('A imagem é demasiado grande. O limite é 5MB.');
                    return;
                }
                
                // Update input
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                imageInput.files = dataTransfer.files;
                
                handleFile(file);
            }
        });

        // Handle file preview
        function handleFile(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                dropZoneContent.style.display = 'none';
                dropZonePreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }

        // Remove image
        function removeImage() {
            imageInput.value = '';
            previewImg.src = '';
            dropZoneContent.style.display = 'block';
            dropZonePreview.style.display = 'none';
        }

        // Toggle comments
        function toggleComments(postId) {
            const comments = document.getElementById('comments-' + postId);
            comments.classList.toggle('show');
        }

        // Auto-resize comment textarea
        document.querySelectorAll('.comment-form textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            });
        });

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
            
            // Update button text
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
    </script>
</body>
</html>