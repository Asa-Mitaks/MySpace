# Image & Video Sharing in Chat - Implementation Guide

## ✅ What's Been Implemented

This guide documents the image and video sharing functionality added to the MySpace chat system.

## 📁 Files Modified/Created

### Backend (PHP)
- **`public/api/upload-media.php`** (NEW)
  - Handles file uploads (images & videos)
  - Validates file types and sizes
  - Generates thumbnails for images
  - Stores file info in database

- **`src/models/Message.php`** (MODIFIED)
  - Added `createMediaMessage()` method
  - Supports `message_type` field (text/image/video)
  - Media URL and metadata storage

- **`scripts/migrate.sql`** (MODIFIED)
  - Added columns: `message_type`, `media_url`, `media_thumbnail`, `media_size`, `media_name`
  - Created `chat_media` table for media file tracking

### Real-time Server (Node.js)
- **`websocket-server/config/database.js`** (MODIFIED)
  - Added `createMediaMessage()` query

- **`websocket-server/handlers/chatHandler.js`** (MODIFIED)
  - Added `handleSendMediaMessage()` method
  - Updated `formatMessages()` to include media data

- **`websocket-server/server.js`** (MODIFIED)
  - Added `send_media_message` event handler

### Frontend (JavaScript)
- **`public/js/websocket-client.js`** (MODIFIED)
  - Added `uploadFile()` method
  - Added `sendMediaMessage()` method
  - Updated `displayMessage()` for media rendering

- **`public/chat-realtime.php`** (MODIFIED)
  - Added image/video upload buttons
  - Added media preview functionality
  - Added CSS styles for media elements

## 🛠️ Database Setup

Run the migration to add the new columns:

```sql
-- Add columns to messages table (if not already added)
ALTER TABLE messages 
ADD COLUMN message_type ENUM('text', 'image', 'video') DEFAULT 'text',
ADD COLUMN media_url VARCHAR(500) DEFAULT NULL,
ADD COLUMN media_thumbnail VARCHAR(500) DEFAULT NULL,
ADD COLUMN media_size INT DEFAULT NULL,
ADD COLUMN media_name VARCHAR(255) DEFAULT NULL;

-- Create chat_media table
CREATE TABLE IF NOT EXISTS chat_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT,
    sender_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    thumbnail_path VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## 📤 How It Works

### 1. File Upload Flow
```
User selects file → PHP validates → Saves to filesystem → 
Creates thumbnail (for images) → Stores in database → 
Returns file URL → Sends via WebSocket
```

### 2. Sending Media
1. User clicks image/video button
2. File picker opens
3. User selects file
4. Preview shows in chat input
5. User optionally adds caption
6. User clicks send
7. File uploads to server
8. WebSocket broadcasts to recipients
9. Recipients see media instantly

## 🔧 Configuration

### Upload Settings (in `upload-media.php`)
```php
// Max file sizes
define('MAX_IMAGE_SIZE', 10 * 1024 * 1024);  // 10MB
define('MAX_VIDEO_SIZE', 100 * 1024 * 1024); // 100MB

// Allowed types
ALLOWED_IMAGE_TYPES: ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
ALLOWED_VIDEO_TYPES: ['video/mp4', 'video/webm', 'video/quicktime']

// Storage paths
UPLOAD_DIR: public/uploads/chat/
THUMBNAIL_DIR: public/uploads/chat/thumbnails/
```

## 🎨 Frontend Features

### Upload Buttons
- 📷 Image button (supports JPEG, PNG, GIF, WebP)
- 🎥 Video button (supports MP4, WebM, MOV)

### Preview
- Shows thumbnail before sending
- Allows removing selected media
- Optional caption field

### Display
- Images: Clickable, opens in new tab
- Videos: Native HTML5 video player with controls
- Messages with captions: Show caption above media

## 🔒 Security Features

1. **File Type Validation** - Whitelist only
2. **File Size Limits** - 10MB images, 100MB videos
3. **Unique Filenames** - Prevents overwrites
4. **XSS Prevention** - HTML escaping in messages
5. **Session Authentication** - User must be logged in
6. **SQL Injection Prevention** - Prepared statements

## 🚀 Usage

1. Ensure database is updated (run migrations)
2. Start WebSocket server: `cd websocket-server && npm start`
3. Access chat: `http://localhost/chat-realtime.php`
4. Login and start chatting
5. Click 📷 or 🎥 to send media

## 📊 API Endpoints

### POST `/api/upload-media.php`
**Request:**
```
Content-Type: multipart/form-data
Authorization: Session-based (requires login)
```

**Response:**
```json
{
  "success": true,
  "message": "File uploaded successfully",
  "media_id": 1,
  "file_name": "image_123.jpg",
  "file_type": "image",
  "file_url": "uploads/chat/image_123.jpg",
  "thumbnail_url": "uploads/chat/thumbnails/image_123_thumb.jpg",
  "file_size": 1024000
}
```

## 🔧 Troubleshooting

### File Upload Issues
- Check upload directory permissions: `chmod 755 uploads/chat/`
- Check PHP `upload_max_filesize` in php.ini
- Check PHP `post_max_size` in php.ini

### WebSocket Issues
- Ensure Node.js server is running on port 3002
- Check WebSocket connection in browser console
- Verify JWT token is valid

### Database Issues
- Verify columns exist in messages table
- Check MySQL user has proper permissions
- Review error logs

## 🔄 Future Enhancements

Possible improvements:
- [ ] Video compression before upload
- [ ] Image resizing for large images
- [ ] Drag and drop upload
- [ ] Multiple file selection
- [ ] Upload progress bar
- [ ] Cloud storage integration (S3, Cloudinary)
- [ ] Message reactions
- [ ] Read receipts
