-- Add tables for real-time features
-- Online users tracking
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    socket_id VARCHAR(255) NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_online TINYINT(1) DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_socket (socket_id),
    INDEX idx_user_online (user_id, is_online)
);

-- Chat rooms (servers/channels)
CREATE TABLE IF NOT EXISTS chat_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    is_private TINYINT(1) DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Room members
CREATE TABLE IF NOT EXISTS room_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('admin', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_room_member (room_id, user_id)
);

-- Update messages table to support rooms
ALTER TABLE messages 
ADD COLUMN IF NOT EXISTS room_id INT NULL AFTER receiver_id,
ADD FOREIGN KEY IF NOT EXISTS (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE;

-- Create index for room messages
CREATE INDEX IF NOT EXISTS idx_messages_room ON messages(room_id, created_at);
CREATE INDEX IF NOT EXISTS idx_messages_private ON messages(sender_id, receiver_id, created_at);

-- Typing indicators (optional - could be Redis-only)
CREATE TABLE IF NOT EXISTS typing_indicators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    room_id VARCHAR(100) NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_room (user_id, room_id),
    INDEX idx_room_typing (room_id, started_at)
);

-- Insert default public room
INSERT IGNORE INTO chat_rooms (name, description, is_private, created_by) 
VALUES ('general', 'Sala pública principal para todos os usuários', 0, 1);

-- Room permissions (future feature)
CREATE TABLE IF NOT EXISTS room_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    can_send_message TINYINT(1) DEFAULT 1,
    can_read_messages TINYINT(1) DEFAULT 1,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    granted_by INT NOT NULL,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_room_user_permission (room_id, user_id)
);