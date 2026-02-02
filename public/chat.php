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
    <link rel="stylesheet" href="css/styles.css">
    <title>Chat Forum</title>
</head>
<body>
    <div class="chat-container">
        <h1>Chat Room</h1>
        <div class="messages">
            <?php foreach ($messages as $msg): ?>
                <div class="message">
                    <strong><?php echo htmlspecialchars($msg['username']); ?>:</strong>
                    <span><?php echo htmlspecialchars($msg['content']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <form method="POST" action="chat.php">
            <input type="text" name="message" placeholder="Type your message..." required>
            <button type="submit">Send</button>
        </form>
    </div>
    <script src="js/app.js"></script>
</body>
</html>