<?php
class Message {
    private $db;

    public function __construct($db = null) {
        if ($db) {
            $this->db = $db;
        } else {
            try {
                $this->db = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                    DB_USER,
                    DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }
    }

    public function createMessage($senderId, $receiverId, $content) {
        $stmt = $this->db->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
        return $stmt->execute([$senderId, $receiverId, $content]);
    }

    public function getMessages($userId, $chatPartnerId = null) {
        if ($chatPartnerId) {
            // Get messages between two users
            $stmt = $this->db->prepare("
                SELECT m.*, m.message as content, u.name as username 
                FROM messages m 
                JOIN users u ON m.sender_id = u.id 
                WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                   OR (m.sender_id = ? AND m.receiver_id = ?)
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$userId, $chatPartnerId, $chatPartnerId, $userId]);
        } else {
            // Get all messages (for public chat room - where receiver_id is NULL)
            $stmt = $this->db->prepare("
                SELECT m.*, m.message as content, u.name as username 
                FROM messages m 
                JOIN users u ON m.sender_id = u.id 
                WHERE m.receiver_id IS NULL
                ORDER BY m.created_at DESC
                LIMIT 50
            ");
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteMessage($messageId, $userId) {
        $stmt = $this->db->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?");
        return $stmt->execute([$messageId, $userId]);
    }
}
?>