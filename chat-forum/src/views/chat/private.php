<?php
session_start();
require_once '../../config/config.php';
require_once '../controllers/ChatController.php';
require_once '../models/Message.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$messageModel = new Message($userId, null, null);
$chatController = new ChatController($messageModel);
$messages = $chatController->getMessages($userId, null) ?? [];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/css/styles.css">
    <title>Private Chat</title>
</head>
<body>
    <div class="chat-container">
        <h1>Private Chat</h1>
        <div class="messages">
            <?php foreach ($messages as $message): ?>
                <div class="message">
                    <strong><?php echo htmlspecialchars($message['sender_name']); ?>:</strong>
                    <p><?php echo htmlspecialchars($message['content']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <form action="send_message.php" method="POST">
            <input type="text" name="message" placeholder="Type your message..." required>
            <button type="submit">Send</button>
        </form>
    </div>
    <script src="/js/app.js"></script>
</body>
</html>