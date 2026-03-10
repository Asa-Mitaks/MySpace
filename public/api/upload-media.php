<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';

// Configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/chat/');
define('THUMBNAIL_DIR', __DIR__ . '/../uploads/chat/thumbnails/');

// Allowed file types
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo']);

// Max file sizes (in bytes)
define('MAX_IMAGE_SIZE', 10 * 1024 * 1024);  // 10MB
define('MAX_VIDEO_SIZE', 100 * 1024 * 1024);  // 100MB

// Response helper function
function respond($success, $message = '', $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    respond(false, 'Authentication required');
}

$userId = $_SESSION['user_id'];

// Create upload directories if they don't exist
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!is_dir(THUMBNAIL_DIR)) {
    mkdir(THUMBNAIL_DIR, 0755, true);
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $error = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server maximum size',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum size',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    respond(false, $errorMessages[$error] ?? 'Unknown upload error');
}

$file = $_FILES['file'];
$fileName = $file['name'];
$fileSize = $file['size'];
$fileType = $file['type'];
$fileTmpName = $file['tmp_name'];

// Determine file category
$isVideo = in_array($fileType, ALLOWED_VIDEO_TYPES);
$isImage = in_array($fileType, ALLOWED_IMAGE_TYPES);

if (!$isImage && !$isVideo) {
    respond(false, 'Invalid file type. Allowed: images (JPEG, PNG, GIF, WebP) and videos (MP4, WebM, MOV)');
}

// Check file size
$maxSize = $isVideo ? MAX_VIDEO_SIZE : MAX_IMAGE_SIZE;
if ($fileSize > $maxSize) {
    $maxMB = $isVideo ? 100 : 10;
    respond(false, "File too large. Maximum size: {$maxMB}MB");
}

// Generate unique filename
$extension = pathinfo($fileName, PATHINFO_EXTENSION);
$baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
$uniqueName = $baseName . '_' . time() . '_' . bin2hex(random_bytes(4));
$fullFileName = $uniqueName . '.' . $extension;
$targetPath = UPLOAD_DIR . $fullFileName;

// Move uploaded file
if (!move_uploaded_file($fileTmpName, $targetPath)) {
    respond(false, 'Failed to save uploaded file');
}

// Generate thumbnail for images (skip if GD not available)
$thumbnailPath = null;
if ($isImage && extension_loaded('gd')) {
    try {
        $thumbnailPath = generateThumbnail($targetPath, $uniqueName, $extension);
    } catch (Exception $e) {
        // Continue without thumbnail
        $thumbnailPath = null;
    }
}

// Get receiver_id and caption from request
$receiverId = isset($_POST['receiver_id']) && $_POST['receiver_id'] !== '' ? (int)$_POST['receiver_id'] : null;
$caption = isset($_POST['caption']) ? trim($_POST['caption']) : '';

// Save to database
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $mediaUrl = 'uploads/chat/' . $fullFileName;
    $mediaThumbnail = $thumbnailPath ? 'uploads/chat/thumbnails/' . $thumbnailPath : null;
    $mediaType = $isVideo ? 'video' : 'image';
    
    // Insert into chat_media table
    $stmt = $pdo->prepare("
        INSERT INTO chat_media (sender_id, file_name, file_type, file_size, file_path, thumbnail_path, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $userId,
        $fileName,
        $fileType,
        $fileSize,
        $mediaUrl,
        $mediaThumbnail
    ]);
    
    $mediaId = $pdo->lastInsertId();
    
    // Also create a message in the messages table
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, message, message_type, media_url, media_thumbnail, media_size, media_name, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $userId,
        $receiverId,
        $caption,
        $mediaType,
        $mediaUrl,
        $mediaThumbnail,
        $fileSize,
        $fileName
    ]);
    
    respond(true, 'File uploaded successfully', [
        'media_id' => $mediaId,
        'file_name' => $fileName,
        'file_type' => $mediaType,
        'file_url' => $mediaUrl,
        'thumbnail_url' => $mediaThumbnail,
        'file_size' => $fileSize
    ]);
    
} catch (PDOException $e) {
    // Remove uploaded file on database error
    if (file_exists($targetPath)) {
        unlink($targetPath);
    }
    if ($thumbnailPath && file_exists(THUMBNAIL_DIR . $thumbnailPath)) {
        unlink(THUMBNAIL_DIR . $thumbnailPath);
    }
    respond(false, 'Database error: ' . $e->getMessage());
}

/**
 * Generate thumbnail for image (requires GD extension)
 */
function generateThumbnail($imagePath, $baseName, $extension) {
    if (!extension_loaded('gd')) {
        return null;
    }
    
    $thumbnailWidth = 300;
    $thumbnailHeight = 300;
    
    $imageInfo = getimagesize($imagePath);
    if (!$imageInfo) {
        return null;
    }
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $type = $imageInfo[2];
    
    $ratio = min($thumbnailWidth / $width, $thumbnailHeight / $height);
    $newWidth = (int)($width * $ratio);
    $newHeight = (int)($height * $ratio);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($imagePath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($imagePath);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($imagePath);
            break;
        case IMAGETYPE_WEBP:
            $source = imagecreatefromwebp($imagePath);
            break;
        default:
            return null;
    }
    
    if (!$source) {
        return null;
    }
    
    $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
    
    if ($type === IMAGETYPE_PNG) {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
        imagefill($thumbnail, 0, 0, $transparent);
    }
    
    imagecopyresampled(
        $thumbnail, $source,
        0, 0, 0, 0,
        $newWidth, $newHeight, $width, $height
    );
    
    $thumbnailName = $baseName . '_thumb.' . $extension;
    $thumbnailPathFull = THUMBNAIL_DIR . $thumbnailName;
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($thumbnail, $thumbnailPathFull, 80);
            break;
        case IMAGETYPE_PNG:
            imagepng($thumbnail, $thumbnailPathFull);
            break;
        case IMAGETYPE_GIF:
            imagegif($thumbnail, $thumbnailPathFull);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($thumbnail, $thumbnailPathFull, 80);
            break;
    }
    
    imagedestroy($source);
    imagedestroy($thumbnail);
    
    return $thumbnailName;
}
