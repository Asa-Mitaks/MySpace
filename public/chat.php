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

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$userId = $_SESSION['user_id'];

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
    <title>Chat - Myspace</title>
    <style>
        /* Ensure chat takes full height */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
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
                <a href="profile.php" class="profile-btn">Perfil</a>
            </li>
            <li>
                <a href="logout.php" class="btn-logout">Sair</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="chat-page">
        <div class="chat-main-area">
            <div class="chat-container">
                <div class="chat-header">
                    <?php if ($chatPartnerId && $chatPartnerName): ?>
                        <h1>💬 Chat com <?php echo htmlspecialchars($chatPartnerName); ?></h1>
                        <p><a href="chat.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">← Voltar ao chat público</a></p>
                    <?php else: ?>
                        <h1>💬 Chat Room</h1>
                        <p>Conversa com a comunidade em tempo real</p>
                    <?php endif; ?>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($messages)): ?>
                        <div class="no-messages">
                            <span>💭</span>
                            <p>Ainda não há mensagens. Sê o primeiro a escrever!</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $displayMessages = $chatPartnerId ? $messages : array_reverse($messages);
                        foreach ($displayMessages as $msg): 
                        ?>
                            <div class="chat-message <?php echo ($msg['sender_id'] == $userId) ? 'own-message' : ''; ?>">
                                <div class="message-avatar">
                                    <?php if (!empty($msg['profile_image']) && file_exists($msg['profile_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($msg['profile_image']); ?>" alt="Avatar">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($msg['username'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="message-content">
                                    <div class="message-header">
                                        <span class="message-author"><?php echo htmlspecialchars($msg['username']); ?></span>
                                        <span class="message-time"><?php echo date('H:i', strtotime($msg['created_at'])); ?></span>
                                    </div>
                                    <div class="message-text" id="msg-text-<?php echo $msg['id']; ?>"><?php echo htmlspecialchars($msg['content']); ?></div>
                                </div>
                                <?php if ($msg['sender_id'] == $userId): ?>
                                <div class="message-menu">
                                    <button type="button" class="menu-dots" onclick="toggleMenu(<?php echo $msg['id']; ?>, event)">
                                        <span></span><span></span><span></span>
                                    </button>
                                    <div class="menu-dropdown" id="menu-<?php echo $msg['id']; ?>">
                                        <button type="button" onclick="editMessage(<?php echo $msg['id']; ?>, '<?php echo addslashes(htmlspecialchars($msg['content'], ENT_QUOTES)); ?>')">
                                            Editar
                                        </button>
                                        <form method="POST" action="chat.php<?php echo $chatPartnerId ? '?with=' . $chatPartnerId : ''; ?>" onsubmit="return confirm('Tens a certeza que queres eliminar esta mensagem?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                            <?php if ($chatPartnerId): ?>
                                                <input type="hidden" name="receiver_id" value="<?php echo $chatPartnerId; ?>">
                                            <?php endif; ?>
                                            <button type="submit">Eliminar</button>
                                        </form>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <form method="POST" action="chat.php<?php echo $chatPartnerId ? '?with=' . $chatPartnerId : ''; ?>" class="chat-form">
                    <?php if ($chatPartnerId): ?>
                        <input type="hidden" name="receiver_id" value="<?php echo $chatPartnerId; ?>">
                    <?php endif; ?>
                    <div class="chat-input-group">
                        <input type="text" name="message" placeholder="Escreve a tua mensagem..." required autocomplete="off">
                        <button type="submit" class="btn-send">
                            <span>Enviar</span>
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
                <h3>👥 Amigos</h3>
            </div>
            <div class="friends-chat-list">
                <?php if (empty($friends)): ?>
                    <div class="no-friends">
                        <p>Ainda não tens amigos.</p>
                        <a href="blog.php">Encontrar amigos</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($friends as $friend): ?>
                        <a href="chat.php?with=<?php echo $friend['id']; ?>" class="friend-chat-item <?php echo ($chatPartnerId == $friend['id']) ? 'active' : ''; ?>">
                            <div class="friend-chat-avatar">
                                <?php if (!empty($friend['profile_image']) && file_exists($friend['profile_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($friend['profile_image']); ?>" alt="Avatar">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($friend['name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="friend-chat-info">
                                <span class="friend-chat-name"><?php echo htmlspecialchars($friend['name']); ?></span>
                                <span class="friend-chat-status">Clica para conversar</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>
    </div>
    
    <script src="js/app.js"></script>
    <script>
        // Scroll to bottom of messages
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Load saved theme preference (Dark Mode)
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('darkMode');
            if (savedTheme === 'true') {
                document.body.classList.add('dark-mode');
            }
        });

        // Toggle menu dropdown
        function toggleMenu(messageId, event) {
            // Stop event propagation to prevent document click handler from firing
            if (event) {
                event.stopPropagation();
            }
            
            const menu = document.getElementById('menu-' + messageId);
            const isCurrentlyOpen = menu.classList.contains('show');
            
            // Close all menus first
            document.querySelectorAll('.menu-dropdown.show').forEach(m => {
                m.classList.remove('show');
            });
            
            // If this menu was closed, open it
            if (!isCurrentlyOpen) {
                menu.classList.add('show');
            }
        }

        // Close menus when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.message-menu')) {
                document.querySelectorAll('.menu-dropdown.show').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });

        // Edit message function
        function editMessage(messageId, currentContent) {
            // Decode HTML entities
            const textarea = document.createElement('textarea');
            textarea.innerHTML = currentContent;
            const decodedContent = textarea.value;
            
            const newContent = prompt('Editar mensagem:', decodedContent);
            if (newContent !== null && newContent.trim() !== '' && newContent !== decodedContent) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.href;
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'edit';
                form.appendChild(actionInput);
                
                const messageIdInput = document.createElement('input');
                messageIdInput.type = 'hidden';
                messageIdInput.name = 'message_id';
                messageIdInput.value = messageId;
                form.appendChild(messageIdInput);
                
                const contentInput = document.createElement('input');
                contentInput.type = 'hidden';
                contentInput.name = 'new_content';
                contentInput.value = newContent;
                form.appendChild(contentInput);
                
                // Add receiver_id if in private chat
                const urlParams = new URLSearchParams(window.location.search);
                const withParam = urlParams.get('with');
                if (withParam) {
                    const receiverInput = document.createElement('input');
                    receiverInput.type = 'hidden';
                    receiverInput.name = 'receiver_id';
                    receiverInput.value = withParam;
                    form.appendChild(receiverInput);
                }
                
                document.body.appendChild(form);
                form.submit();
            }
            
            // Close the menu
            document.querySelectorAll('.menu-dropdown.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    </script>
</body>
</html>