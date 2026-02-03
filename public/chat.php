<?php
session_start();
require_once '../config/config.php';
require_once '../src/models/Message.php';
require_once '../src/controllers/ChatController.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

$messageModel = new Message();
$chatController = new ChatController($messageModel);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = $_POST['message'] ?? '';
    $userId = $_SESSION['user_id'];
    $receiverId = $_POST['receiver_id'] ?? null;
    if (!empty($message)) {
        $chatController->sendMessage($userId, $receiverId, $message);
    }
    header('Location: chat.php');
    exit;
}

$messages = $chatController->getMessages($_SESSION['user_id'], null) ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">
    <title>Chat - Myspace</title>
    <style>
        /* Ensure chat takes full height */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-brand">
            <a href="dashboard.php" style="text-decoration: none; color: inherit;"><h1>Myspace</h1></a>
        </div>
        <ul class="navbar-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="blog.php">Blog</a></li>
            <li><a href="chat.php" class="active">Chat</a></li>
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
    <div class="chat-page">
        <div class="chat-container">
            <div class="chat-header">
                <h1>💬 Chat Room</h1>
                <p>Conversa com a comunidade em tempo real</p>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <?php if (empty($messages)): ?>
                    <div class="no-messages">
                        <span>💭</span>
                        <p>Ainda não há mensagens. Sê o primeiro a escrever!</p>
                    </div>
                <?php else: ?>
                    <?php foreach (array_reverse($messages) as $msg): ?>
                        <div class="chat-message <?php echo ($msg['sender_id'] == $_SESSION['user_id']) ? 'own-message' : ''; ?>">
                            <div class="message-avatar">
                                <?php echo strtoupper(substr($msg['username'], 0, 1)); ?>
                            </div>
                            <div class="message-content">
                                <div class="message-header">
                                    <span class="message-author"><?php echo htmlspecialchars($msg['username']); ?></span>
                                    <span class="message-time"><?php echo date('H:i', strtotime($msg['created_at'])); ?></span>
                                </div>
                                <div class="message-text"><?php echo htmlspecialchars($msg['content']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="chat.php" class="chat-form">
                <div class="chat-input-group">
                    <input type="text" name="message" placeholder="Escreve a tua mensagem..." required autocomplete="off">
                    <button type="submit" class="btn-send">
                        <span>Enviar</span>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="js/app.js"></script>
    <script>
        // Scroll to bottom of messages
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Load saved theme preference (Dark Mode)
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('darkMode');
            if (savedTheme === 'true') {
                document.body.classList.add('dark-mode');
            }
        });
    </script>
</body>
</html>