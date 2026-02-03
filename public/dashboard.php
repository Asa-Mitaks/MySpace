<?php
session_start();
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Chat Forum</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Navigation -->
        <nav class="navbar">
            <div class="navbar-brand">
                <a href="dashboard.php" style="text-decoration: none; color: inherit;"><h1>Myspace</h1></a>
            </div>
            <ul class="navbar-menu">
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="blog.php">Blog</a></li>
                <li><a href="chat.php">Chat</a></li>
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
        <div class="dashboard-content">
            <!-- Single Beautiful Landing Box -->
            <div class="landing-box">
                <!-- Header -->
                <div class="landing-header">
                    <div class="header-content">
                        <h1>Bem-vindo, <?php echo htmlspecialchars($username); ?>! 👋</h1>
                        <p>A tua plataforma de comunicação e partilha</p>
                    </div>
                    <div class="header-decoration"></div>
                </div>

                <!-- About Section -->
                <div class="landing-section">
                    <h2 class="section-heading">
                        <span class="heading-icon">✨</span>
                        Sobre a Aplicação
                    </h2>
                    <div class="about-text">
                        <p>O <strong>Myspace</strong> é uma plataforma de comunicação e partilha criada para conectar pessoas. Aqui podes expressar as tuas ideias através do nosso <strong>blog interativo</strong>, onde tens a liberdade de criar e publicar posts sobre qualquer tema que te interesse.</p>
                        <p>Além disso, oferecemos um sistema de <strong>chat em tempo real</strong> que te permite conversar instantaneamente com outros membros da comunidade. Seja para trocar ideias, fazer novos amigos ou simplesmente socializar, o Myspace é o lugar certo para ti.</p>
                        <p>A nossa <strong>comunidade</strong> está sempre a crescer, e cada membro contribui para tornar este espaço mais rico e diversificado. Junta-te a nós e faz parte desta experiência!</p>
                    </div>
                </div>

                <!-- Divider -->
                <div class="section-divider"></div>

                <!-- How It Works -->
                <div class="landing-section">
                    <h2 class="section-heading">
                        <span class="heading-icon">🚀</span>
                        Como funciona
                    </h2>
                    <div class="steps-row">
                        <div class="step-item">
                            <div class="step-num">1</div>
                            <span>Explora o blog</span>
                        </div>
                        <div class="step-connector"></div>
                        <div class="step-item">
                            <div class="step-num">2</div>
                            <span>Cria conteúdo</span>
                        </div>
                        <div class="step-connector"></div>
                        <div class="step-item">
                            <div class="step-num">3</div>
                            <span>Usa o chat</span>
                        </div>
                        <div class="step-connector"></div>
                        <div class="step-item">
                            <div class="step-num">4</div>
                            <span>Interage!</span>
                        </div>
                    </div>
                </div>

                <!-- Divider -->
                <div class="section-divider"></div>

                <!-- Quick Actions -->
                <div class="landing-section actions-section">
                    <h2 class="section-heading">
                        <span class="heading-icon">⚡</span>
                        Começar agora
                    </h2>
                    <div class="quick-btns">
                        <a href="blog.php" class="quick-btn primary">
                            <span>✍️</span> Criar Post
                        </a>
                        <a href="chat.php" class="quick-btn secondary">
                            <span>💬</span> Iniciar Chat
                        </a>
                        <a href="blog.php" class="quick-btn tertiary">
                            <span>📖</span> Ver Blog
                        </a>
                    </div>
                </div>

                <!-- Footer inside box -->
                <div class="box-footer">
                    <p>&copy; 2026 Myspace. Todos os direitos reservados.</p>
                </div>
            </div>
        </div>
    </div>

    <style>
        body.dark-mode {
            background: #202225;
        }

        body.dark-mode .landing-box {
            background: #36393f;
        }

        body.dark-mode .about-text {
            background: #2f3136;
        }

        body.dark-mode .about-text p {
            color: #dcddde;
        }

        body.dark-mode .landing-header {
            background: linear-gradient(135deg, #5865f2 0%, #7289da 100%);
        }

        body.dark-mode .section-heading,
        body.dark-mode .step-item span {
            color: #eee;
        }

        body.dark-mode .box-footer p {
            color: #b9bbbe;
        }

        body.dark-mode .actions-section {
            background: #2f3136;
        }

        body.dark-mode .quick-btn.secondary {
            background: #40444b;
            border-color: #5865f2;
            color: #eee;
        }

        body.dark-mode .quick-btn.secondary:hover {
            background: #5865f2;
            color: white;
        }

        .dashboard-content {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 30px;
            padding-bottom: 50px;
            min-height: calc(100vh - 70px);
        }

        /* Main Landing Box */
        .landing-box {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(102, 126, 234, 0.15);
            width: 100%;
            max-width: 1000px;
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }

        /* About Text */
        .about-text {
            background: #f8f9ff;
            padding: 30px;
            border-radius: 16px;
            border-left: 4px solid #667eea;
        }

        .about-text p {
            color: #555;
            font-size: 1.05rem;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .about-text p:last-child {
            margin-bottom: 0;
        }

        .about-text strong {
            color: #667eea;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Header */
        .landing-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 50px 40px;
            position: relative;
            overflow: hidden;
        }

        .header-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: white;
        }

        .header-content h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .header-content p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .header-decoration {
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .header-decoration::after {
            content: '';
            position: absolute;
            top: 50%;
            left: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
        }

        /* Sections */
        .landing-section {
            padding: 35px 40px;
        }

        .section-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 25px;
        }

        .heading-icon {
            font-size: 1.4rem;
        }

        .section-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e0e0e0, transparent);
            margin: 0 40px;
        }

        /* Features Row */
        .features-row {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 18px 20px;
            background: #f8f9ff;
            border-radius: 16px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .feature-item:hover {
            background: white;
            border-color: #667eea;
            transform: translateX(8px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }

        .feature-icon-box {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .feature-info {
            flex: 1;
        }

        .feature-info h3 {
            color: #333;
            font-size: 1.1rem;
            margin-bottom: 4px;
        }

        .feature-info p {
            color: #666;
            font-size: 0.9rem;
        }

        .feature-arrow {
            color: #667eea;
            font-size: 1.4rem;
            font-weight: 600;
            opacity: 0;
            transition: all 0.3s;
        }

        .feature-item:hover .feature-arrow {
            opacity: 1;
            transform: translateX(5px);
        }

        /* Steps Row */
        .steps-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .step-num {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .step-item span {
            color: #555;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .step-connector {
            flex: 1;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 2px;
            min-width: 30px;
            max-width: 60px;
        }

        /* Quick Actions */
        .actions-section {
            background: #fafbff;
            text-align: center;
        }

        .quick-btns {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .quick-btn {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 18px 40px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.15rem;
            text-decoration: none;
            transition: all 0.3s ease;
            min-width: 180px;
            justify-content: center;
        }

        .quick-btn span {
            font-size: 1.3rem;
        }

        .quick-btn.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .quick-btn.primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .quick-btn.secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .quick-btn.secondary:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
        }

        .quick-btn.tertiary {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .quick-btn.tertiary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(17, 153, 142, 0.4);
        }

        .quick-btn.admin {
            background: #1a1a2e;
            color: white;
        }

        .quick-btn.admin:hover {
            background: #16213e;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(26, 26, 46, 0.3);
        }

        /* Box Footer */
        .box-footer {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 25px 40px;
            text-align: center;
        }

        .box-footer p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin: 0;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .dashboard-content {
                padding: 15px;
            }

            .landing-header {
                padding: 35px 25px;
            }

            .header-content h1 {
                font-size: 1.6rem;
            }

            .landing-section {
                padding: 25px 20px;
            }

            .section-divider {
                margin: 0 20px;
            }

            .steps-row {
                flex-direction: column;
                gap: 15px;
            }

            .step-connector {
                width: 3px;
                height: 20px;
                min-width: 3px;
            }

            .quick-btns {
                flex-direction: column;
            }

            .quick-btn {
                justify-content: center;
            }

            .box-footer {
                padding: 20px;
            }
        }
    </style>

    <script src="js/app.js"></script>
    <script>
        // User Dropdown Toggle
        function toggleUserDropdown() {
            const dropdown = document.querySelector('.user-dropdown');
            dropdown.classList.toggle('active');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.querySelector('.user-dropdown');
            if (dropdown && !dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });

        // Dark Mode Toggle
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('darkMode', isDark);
            
            // Update button text
            const themeBtn = document.querySelector('.theme-toggle span');
            if (themeBtn) {
                themeBtn.textContent = isDark ? '☀️' : '🌙';
            }
        }

        // Load saved theme preference
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('darkMode');
            if (savedTheme === 'true') {
                document.body.classList.add('dark-mode');
                const themeBtn = document.querySelector('.theme-toggle span');
                if (themeBtn) {
                    themeBtn.textContent = '☀️';
                }
            }
        });
    </script>
</body>
</html>
