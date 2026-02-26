<?php
session_start();
require_once '../config/config.php';
require_once '../src/models/Message.php';
require_once '../src/controllers/ChatController.php';
require_once '../src/controllers/AuthController.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$userId = $_SESSION['user_id'];

// Initialize auth controller
$authController = new AuthController();

// Generate WebSocket token for the current user
$wsToken = $authController->generateWebSocketToken($userId);

// Database connection for friends
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get user's friends
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.profile_image
        FROM friendships f
        JOIN users u ON f.friend_id = u.id
        WHERE f.user_id = ?
        ORDER BY u.name ASC
    ");
    $stmt->execute([$userId]);
    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $friends = [];
}

// Check if viewing private chat
$chatPartnerId = isset($_GET['with']) ? (int)$_GET['with'] : null;
$chatPartnerName = null;

if ($chatPartnerId) {
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$chatPartnerId]);
    $partner = $stmt->fetch(PDO::FETCH_ASSOC);
    $chatPartnerName = $partner ? $partner['name'] : null;
}

$messageModel = new Message();
$chatController = new ChatController($messageModel);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'send';
    $receiverId = $_POST['receiver_id'] ?? null;
    
    if ($action === 'delete') {
        $messageId = $_POST['message_id'] ?? null;
        if ($messageId) {
            $messageModel->deleteMessage($messageId, $userId);
        }
    } elseif ($action === 'edit') {
        $messageId = $_POST['message_id'] ?? null;
        $newContent = $_POST['new_content'] ?? '';
        if ($messageId && !empty($newContent)) {
            $messageModel->updateMessage($messageId, $userId, $newContent);
        }
    } else {
        $message = $_POST['message'] ?? '';
        if (!empty($message)) {
            $chatController->sendMessage($userId, $receiverId, $message);
        }
    }
    
    if ($receiverId) {
        header('Location: chat.php?with=' . $receiverId);
    } else {
        header('Location: chat.php');
    }
    exit;
}

$messages = $chatController->getMessages($userId, $chatPartnerId) ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">
    <title>Real-time Chat - Myspace</title>
    <script src="https://cdn.socket.io/4.8.3/socket.io.min.js"></script>
    <style>
        /* Enhanced real-time chat styles */
        .typing-indicator {
            display: flex;
            align-items: center;
            padding: 10px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
            margin: 10px 0;
            animation: fadeIn 0.3s ease;
        }

        .typing-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
            font-size: 12px;
        }

        .typing-content {
            display: flex;
            flex-direction: column;
        }

        .typing-content span {
            color: #667eea;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .typing-dots {
            display: flex;
            gap: 4px;
        }

        .typing-dots span {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #667eea;
            animation: typingDot 1.4s infinite ease-in-out;
        }

        .typing-dots span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dots span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typingDot {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.6; }
            30% { transform: translateY(-10px); opacity: 1; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .connection-status {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .connection-status.connected {
            background: #28a745;
            color: white;
        }

        .connection-status.connecting {
            background: #ffc107;
            color: #000;
        }

        .connection-status.disconnected {
            background: #dc3545;
            color: white;
        }

        .online-status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 5px;
        }

        .online-status-indicator.online {
            background: #28a745;
        }

        .online-status-indicator.offline {
            background: #6c757d;
        }

        .friend-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            text-decoration: none;
            color: inherit;
            border-bottom: 1px solid #eee;
            transition: background 0.2s ease;
        }

        .friend-item:hover {
            background: #f8f9fa;
        }

        .friend-item.active {
            background: #667eea;
            color: white;
        }

        .friend-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-right: 12px;
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        .friend-info {
            flex: 1;
        }

        .friend-name {
            font-weight: 600;
            margin-bottom: 2px;
        }

        .friend-status {
            font-size: 12px;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <!-- Connection Status Indicator -->
    <div id="connectionStatus" class="connection-status connecting">
        Connecting...
    </div>

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
                <a href="profile.php" class="profile-btn">Profile</a>
            </li>
            <li>
                <a href="logout.php" class="btn-logout">Logout</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="chat-page">
        <div class="chat-main-area">
            <div class="chat-container">
                <div class="chat-header">
                    <?php if ($chatPartnerId && $chatPartnerName): ?>
                        <h1>💬 Chat with <?php echo htmlspecialchars($chatPartnerName); ?></h1>
                        <p><a href="chat.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">← Back to public chat</a></p>
                    <?php else: ?>
                        <h1>💬 Real-time Chat Room</h1>
                        <p>Chat with community in real time - messages appear instantly!</p>
                    <?php endif; ?>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <div class="no-messages">
                        <span>🔄</span>
                        <p>Connecting to real-time server...</p>
                    </div>
                </div>
                
                <form id="chatForm" class="chat-form">
                    <?php if ($chatPartnerId): ?>
                        <input type="hidden" id="receiverId" value="<?php echo $chatPartnerId; ?>">
                    <?php endif; ?>
                    <div class="chat-input-group">
                        <input type="text" id="messageInput" placeholder="Write your message..." required autocomplete="off">
                        <button type="submit" class="btn-send">
                            <span>Send</span>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Friends Sidebar -->
        <aside class="chat-sidebar">
            <div class="sidebar-header">
                <h3>👥 Friends</h3>
            </div>
            <div class="friends-chat-list" id="friendsList">
                <div class="no-friends">
                    <p>Loading friends...</p>
                </div>
            </div>
        </aside>
    </div>

    <!-- Include original chat functionality for fallback -->
    <script src="js/app.js"></script>
    
    <!-- WebSocket Client Script -->
    <script src="js/websocket-client.js"></script>
    <script>
        // Initialize WebSocket connection
        document.addEventListener('DOMContentLoaded', function() {
            // Get current user info from PHP
            const currentUserId = <?php echo $userId; ?>;
            const currentUsername = '<?php echo htmlspecialchars($username); ?>;
            const wsToken = '<?php echo htmlspecialchars($wsToken); ?>';
            const chatPartnerId = <?php echo $chatPartnerId ?: 'null'; ?>;
            
            // Setup WebSocket event handlers
            mySpaceWS.on({
                onConnected: function() {
                    console.log('WebSocket connected successfully');
                    updateConnectionStatus('connected');
                    
                    // Join appropriate room
                    if (chatPartnerId) {
                        // Private chat
                        mySpaceWS.joinRoom(chatPartnerId, true);
                    } else {
                        // Public chat room
                        mySpaceWS.joinRoom('general', false);
                    }
                    
                    // Load friends list
                    mySpaceWS.getFriends();
                },
                
                onDisconnected: function(reason) {
                    console.log('WebSocket disconnected:', reason);
                    updateConnectionStatus('disconnected');
                },
                
                onMessage: function(message) {
                    console.log('New message received:', message);
                    
                    // Hide typing indicator if it was this user
                    hideTypingIndicator();
                    
                    // Add message to UI
                    mySpaceWS.displayMessage(message);
                    mySpaceWS.scrollToBottom();
                },
                
                onUserJoined: function(data) {
                    console.log('User joined:', data);
                    showNotification(`${data.userName} joined the chat`);
                },
                
                onUserLeft: function(data) {
                    console.log('User left:', data);
                    showNotification(`${data.userName} left the chat`);
                },
                
                onTyping: function(data) {
                    console.log('User typing:', data);
                    mySpaceWS.showTypingIndicator(data);
                },
                
                onStopTyping: function(data) {
                    console.log('User stopped typing:', data);
                    hideTypingIndicator();
                },
                
                onUserOnline: function(data) {
                    console.log('User online:', data);
                    updateFriendsList();
                },
                
                onUserOffline: function(data) {
                    console.log('User offline:', data);
                    updateFriendsList();
                },
                
                onFriendsList: function(friends) {
                    console.log('Friends list received:', friends);
                    displayFriendsList(friends);
                },
                
                onOnlineUsers: function(users) {
                    console.log('Online users:', users);
                    updateOnlineStatus(users);
                },
                
                onError: function(error) {
                    console.error('WebSocket error:', error);
                    showNotification('Connection error: ' + error.message);
                    updateConnectionStatus('disconnected');
                }
            });
            
            // Set user info and connect
            mySpaceWS.userId = currentUserId;
            mySpaceWS.userInfo = {
                id: currentUserId,
                name: currentUsername
            };
            
            // Connect to WebSocket
            if (mySpaceWS.connect(wsToken)) {
                console.log('WebSocket connection initiated');
            } else {
                updateConnectionStatus('disconnected');
                showNotification('Failed to connect to chat server');
            }
            
            // Setup chat form
            const chatForm = document.getElementById('chatForm');
            const messageInput = document.getElementById('messageInput');
            let typingTimer = null;
            
            chatForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const message = messageInput.value.trim();
                if (!message) return;
                
                // Send via WebSocket
                const roomId = chatPartnerId || 'general';
                const isPrivate = !!chatPartnerId;
                
                if (mySpaceWS.sendMessage(message, roomId, isPrivate)) {
                    messageInput.value = '';
                    // Stop typing indicator
                    mySpaceWS.setTyping(false, roomId, isPrivate);
                }
            });
            
            // Setup typing indicators
            messageInput.addEventListener('input', function() {
                const roomId = chatPartnerId || 'general';
                const isPrivate = !!chatPartnerId;
                
                // Start typing indicator
                mySpaceWS.setTyping(true, roomId, isPrivate);
                
                // Clear existing timer
                if (typingTimer) {
                    clearTimeout(typingTimer);
                }
                
                // Stop typing after 2 seconds of inactivity
                typingTimer = setTimeout(() => {
                    mySpaceWS.setTyping(false, roomId, isPrivate);
                }, 2000);
            });
        });
        
        // Helper functions
        function updateConnectionStatus(status) {
            const statusElement = document.getElementById('connectionStatus');
            statusElement.className = 'connection-status ' + status;
            
            switch(status) {
                case 'connected':
                    statusElement.textContent = '🟢 Connected';
                    break;
                case 'connecting':
                    statusElement.textContent = '🟡 Connecting...';
                    break;
                case 'disconnected':
                    statusElement.textContent = '🔴 Disconnected';
                    break;
            }
        }
        
        function displayFriendsList(friends) {
            const friendsList = document.getElementById('friendsList');
            if (!friends || friends.length === 0) {
                friendsList.innerHTML = `
                    <div class="no-friends">
                        <p>You don't have any friends yet.</p>
                        <a href="blog.php">Find friends</a>
                    </div>
                `;
                return;
            }
            
            const currentPartnerId = <?php echo $chatPartnerId ?: 'null'; ?>;
            
            friendsList.innerHTML = friends.map(friend => `
                <a href="chat.php?with=${friend.id}" class="friend-item ${currentPartnerId == friend.id ? 'active' : ''}">
                    <div class="friend-avatar">
                        ${friend.profile_image ? 
                            `<img src="${friend.profile_image}" alt="Avatar">` : 
                            friend.name.charAt(0).toUpperCase()
                        }
                    </div>
                    <div class="friend-info">
                        <div class="friend-name">
                            ${friend.name}
                            <span class="online-status-indicator ${friend.isOnline ? 'online' : 'offline'}"></span>
                        </div>
                        <div class="friend-status">
                            ${friend.isOnline ? 'Online' : 'Offline'}
                        </div>
                    </div>
                </a>
            `).join('');
        }
        
        function updateFriendsList() {
            // Request fresh friends list
            if (mySpaceWS.socket && mySpaceWS.socket.connected) {
                mySpaceWS.getFriends();
            }
        }
        
        function updateOnlineStatus(users) {
            // Update any UI elements showing online status
            console.log('Online users updated:', users.length, 'users');
        }
        
        function hideTypingIndicator() {
            const indicator = document.getElementById('typing-indicator');
            if (indicator) {
                indicator.remove();
            }
        }
        
        function showNotification(message) {
            // Simple notification - could be enhanced with toast library
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                background: #667eea;
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                z-index: 1001;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideIn 0.3s ease;
                max-width: 300px;
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 5000);
        }
        
        // Add slideIn animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
        
        // Load saved theme preference (Dark Mode)
        const savedTheme = localStorage.getItem('darkMode');
        if (savedTheme === 'true') {
            document.body.classList.add('dark-mode');
        }
    </script>
</body>
</html>