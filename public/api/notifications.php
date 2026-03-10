<?php
session_start();
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create notification tracking table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        notification_type VARCHAR(50) NOT NULL,
        last_read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_type (user_id, notification_type),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $action = $_GET['action'] ?? 'check';

    if ($action === 'check') {
        // Get last read timestamp for messages
        $stmt = $pdo->prepare("SELECT last_read_at FROM notification_reads WHERE user_id = ? AND notification_type = 'message'");
        $stmt->execute([$userId]);
        $lastRead = $stmt->fetchColumn();
        $lastReadTime = $lastRead ?: '1970-01-01 00:00:00';

        // Get new private messages since last read
        $stmt = $pdo->prepare("
            SELECT m.id, m.sender_id, m.message, m.created_at, u.name as sender_name, u.profile_image as sender_avatar
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.receiver_id = ? 
              AND m.sender_id != ?
              AND m.created_at > ?
            ORDER BY m.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$userId, $userId, $lastReadTime]);
        $newMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'notifications' => array_map(function($msg) {
                return [
                    'id' => $msg['id'],
                    'type' => 'message',
                    'sender_name' => $msg['sender_name'],
                    'sender_avatar' => $msg['sender_avatar'],
                    'preview' => mb_substr($msg['message'], 0, 50) . (mb_strlen($msg['message']) > 50 ? '...' : ''),
                    'created_at' => $msg['created_at'],
                    'sender_id' => $msg['sender_id']
                ];
            }, $newMessages),
            'count' => count($newMessages)
        ]);

    } elseif ($action === 'mark_read') {
        $stmt = $pdo->prepare("
            INSERT INTO notification_reads (user_id, notification_type, last_read_at) 
            VALUES (?, 'message', NOW())
            ON DUPLICATE KEY UPDATE last_read_at = NOW()
        ");
        $stmt->execute([$userId]);
        echo json_encode(['status' => 'success']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
