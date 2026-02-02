<?php
class ChatController {
    private $messageModel;

    public function __construct($messageModel) {
        $this->messageModel = $messageModel;
    }

    public function sendMessage($senderId, $receiverId, $messageContent) {
        // Validate input
        if (empty($messageContent)) {
            return ['status' => 'error', 'message' => 'Message cannot be empty.'];
        }

        // Save message to the database
        $result = $this->messageModel->createMessage($senderId, $receiverId, $messageContent);
        if ($result) {
            return ['status' => 'success', 'message' => 'Message sent successfully.'];
        } else {
            return ['status' => 'error', 'message' => 'Failed to send message.'];
        }
    }

    public function getMessages($userId, $chatPartnerId) {
        // Fetch messages between the two users
        return $this->messageModel->getMessages($userId, $chatPartnerId);
    }
}
?>