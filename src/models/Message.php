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

    public function createMessage($senderId, $receiverId, $content, $messageType = 'text', $mediaUrl = null, $mediaThumbnail = null, $mediaSize = null, $mediaName = null) {
        $stmt = $this->db->prepare("
            INSERT INTO messages (sender_id, receiver_id, message, message_type, media_url, media_thumbnail, media_size, media_name, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $senderId, 
            $receiverId, 
            $content,
            $messageType,
            $mediaUrl,
            $mediaThumbnail,
            $mediaSize,
            $mediaName
        ]);
    }

    public function getMessages($userId, $chatPartnerId = null) {
        if ($chatPartnerId) {
            // Get messages between two users
            $stmt = $this->db->prepare("
                SELECT m.*, m.message as content, u.name as username, u.profile_image 
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
                SELECT m.*, m.message as content, u.name as username, u.profile_image 
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

    public function getMessageById($messageId) {
        $stmt = $this->db->prepare("
            SELECT m.*, u.name as username, u.profile_image 
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE m.id = ?
        ");
        $stmt->execute([$messageId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteMessage($messageId, $userId) {
        // Get message first to check ownership and delete media
        $message = $this->getMessageById($messageId);
        
        if ($message && $message['sender_id'] == $userId) {
            // Delete associated media files
            if (!empty($message['media_url'])) {
                $this->deleteMediaFile($message['media_url']);
            }
            if (!empty($message['media_thumbnail'])) {
                $this->deleteMediaFile($message['media_thumbnail']);
            }
        }
        
        $stmt = $this->db->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?");
        return $stmt->execute([$messageId, $userId]);
    }

    public function updateMessage($messageId, $userId, $newContent) {
        // Only allow updating text messages, not media messages
        $message = $this->getMessageById($messageId);
        if ($message && $message['message_type'] !== 'text') {
            return false;
        }
        
        $stmt = $this->db->prepare("UPDATE messages SET message = ? WHERE id = ? AND sender_id = ?");
        return $stmt->execute([$newContent, $messageId, $userId]);
    }

    /**
     * Delete media file from filesystem
     */
    private function deleteMediaFile($filePath) {
        $fullPath = __DIR__ . '/../../public/' . $filePath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    /**
     * Create media message (image or video)
     */
    public function createMediaMessage($senderId, $receiverId, $mediaData, $caption = '') {
        return $this->createMessage(
            $senderId,
            $receiverId,
            $caption,
            $mediaData['type'],           // 'image' or 'video'
            $mediaData['url'],
            $mediaData['thumbnail'] ?? null,
            $mediaData['size'] ?? null,
            $mediaData['name'] ?? null
        );
    }
}
