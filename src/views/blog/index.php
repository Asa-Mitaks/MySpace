
<?php
require_once '../../../config/config.php';
require_once '../../../src/models/Post.php';

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$postModel = new Post($pdo);
$posts = $postModel->getAllPosts();

include '../../layouts/main.php';
?>

<div class="blog-container">
    <h1>Blog Posts</h1>
    <?php if (empty($posts)): ?>
        <p>No blog posts available.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div class="blog-post">
                <h2><?php echo htmlspecialchars($post['title']); ?></h2>
                <p><?php echo htmlspecialchars($post['content']); ?></p>
                <p><em>Posted on <?php echo htmlspecialchars($post['created_at']); ?></em></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>