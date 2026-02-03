<?php
session_start();

require_once './config/config.php';
require_once './src/controllers/AuthController.php';

// Handle logout
if (isset($_GET['logout'])) {
    $_SESSION = array();
    session_destroy();
    header('Location: index.php');
    exit;
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        header('Location: public/admin.php');
    } else {
        header('Location: public/dashboard.php');
    }
    exit;
}

$authController = new AuthController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $password = $_POST['password'] ?? '';

    $loginResult = $authController->login($name, $password);

    if ($loginResult) {
        if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
            header('Location: public/admin.php');
        } else {
            header('Location: public/dashboard.php');
        }
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Myspace - Chat Forum</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            min-height: 100vh;
        }

        /* Landing Navbar */
        .landing-navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 15px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        .landing-navbar .logo {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            text-decoration: none;
        }

        .landing-navbar .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .landing-navbar .nav-links a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }

        .landing-navbar .nav-links a:hover {
            color: white;
        }

        .landing-navbar .btn-login {
            background: white;
            color: #667eea;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .landing-navbar .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 100px 50px 50px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            max-width: 1200px;
            width: 100%;
            position: relative;
            z-index: 1;
        }

        .hero-text {
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .hero-text h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .hero-text p {
            font-size: 1.3rem;
            opacity: 0.9;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
        }

        .btn-hero {
            padding: 15px 35px;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
        }

        .btn-hero-primary {
            background: white;
            color: #667eea;
        }

        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .btn-hero-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-hero-secondary:hover {
            background: white;
            color: #667eea;
        }

        /* Login Box */
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 400px;
        }

        .login-box h2 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .login-box .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }

        .error {
            background: #fee;
            color: #c33;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            border: 1px solid #fcc;
        }

        .success {
            background: #efe;
            color: #363;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            border: 1px solid #cfc;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .login-box button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .login-box button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .register-link {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 0.95rem;
        }

        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        /* Features Section */
        .features {
            padding: 100px 50px;
            background: white;
        }

        .features-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 15px;
        }

        .section-title p {
            color: #666;
            font-size: 1.2rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 40px;
        }

        .feature-card {
            text-align: center;
            padding: 40px 30px;
            border-radius: 15px;
            background: #f8f9ff;
            transition: all 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.15);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 2rem;
        }

        .feature-card h3 {
            font-size: 1.4rem;
            color: #333;
            margin-bottom: 15px;
        }

        .feature-card p {
            color: #666;
            line-height: 1.7;
        }

        /* How It Works */
        .how-it-works {
            padding: 100px 50px;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
        }

        .how-it-works-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            margin-top: 60px;
        }

        .step {
            text-align: center;
            position: relative;
        }

        .step-number {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .step h3 {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 10px;
        }

        .step p {
            color: #666;
            font-size: 0.95rem;
        }

        /* CTA Section */
        .cta {
            padding: 100px 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            text-align: center;
            color: white;
        }

        .cta h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }

        .cta p {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta .btn-hero-primary {
            font-size: 1.2rem;
            padding: 18px 45px;
        }

        /* Footer */
        .landing-footer {
            background: #1a1a2e;
            color: white;
            padding: 60px 50px 30px;
            margin-top: 0;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px;
        }

        .footer-brand {
            padding-right: 20px;
        }

        .footer-brand h3 {
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: white;
        }

        .footer-brand p {
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.7;
            margin: 0;
        }

        .footer-links {
            padding: 0;
        }

        .footer-links h4 {
            font-size: 1.1rem;
            margin-bottom: 20px;
            color: white;
        }

        .footer-links ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: white;
        }

        .footer-bottom {
            max-width: 1200px;
            margin: 40px auto 0;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
        }

        .footer-bottom p {
            margin: 0;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .hero-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .login-box {
                margin: 0 auto;
            }

            .hero-buttons {
                justify-content: center;
            }

            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .steps {
                grid-template-columns: repeat(2, 1fr);
            }

            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .landing-navbar {
                padding: 15px 20px;
            }

            .landing-navbar .nav-links {
                display: none;
            }

            .hero {
                padding: 100px 20px 50px;
            }

            .hero-text h1 {
                font-size: 2.5rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .steps {
                grid-template-columns: 1fr;
            }

            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="landing-navbar">
        <a href="index.php" class="logo">Myspace</a>
        <div class="nav-links">
            <a href="#features">Funcionalidades</a>
            <a href="#how-it-works">Como Funciona</a>
            <a href="#login" class="btn-login">Entrar</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
            <div class="hero-text">
                <h1>Conecta-te e Partilha com a Comunidade</h1>
                <p>Uma plataforma completa de comunicação onde podes criar posts no blog, conversar em tempo real e fazer parte de uma comunidade ativa.</p>
                <div class="hero-buttons">
                    <a href="public/register.php" class="btn-hero btn-hero-primary">Começar Agora</a>
                    <a href="#features" class="btn-hero btn-hero-secondary">Saber Mais</a>
                </div>
            </div>
            <div class="login-box" id="login">
                <h2>Bem-vindo de volta!</h2>
                <p class="subtitle">Entra na tua conta para continuar</p>
                <?php if (isset($_GET['registered'])): ?>
                    <div class="success">Registo efetuado com sucesso! Faz login.</div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form action="index.php" method="POST">
                    <div class="form-group">
                        <label for="name">Nome de utilizador</label>
                        <input type="text" id="name" name="name" placeholder="O teu nome" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Palavra-passe</label>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>
                    <button type="submit">Entrar</button>
                </form>
                <p class="register-link">Ainda não tens conta? <a href="public/register.php">Regista-te aqui</a></p>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="features-container">
            <div class="section-title">
                <h2>O que oferecemos</h2>
                <p>Descobre todas as funcionalidades da nossa plataforma</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">📝</div>
                    <h3>Blog Interativo</h3>
                    <p>Cria e partilha os teus posts com toda a comunidade. Expressa as tuas ideias, partilha conhecimento e recebe feedback dos outros membros.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💬</div>
                    <h3>Chat em Tempo Real</h3>
                    <p>Conversa instantaneamente com outros utilizadores. Sistema de mensagens privadas e conversas em grupo para uma comunicação rápida e eficiente.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>Comunidade Ativa</h3>
                    <p>Faz parte de uma comunidade vibrante. Conecta-te com pessoas que partilham os teus interesses e expande a tua rede de contactos.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3>Seguro e Privado</h3>
                    <p>A tua privacidade é a nossa prioridade. Sistema de autenticação seguro e controlo total sobre as tuas informações pessoais.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3>Design Responsivo</h3>
                    <p>Acede de qualquer dispositivo. A plataforma adapta-se perfeitamente ao teu computador, tablet ou smartphone.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <h3>Rápido e Intuitivo</h3>
                    <p>Interface simples e fácil de usar. Navega sem complicações e encontra rapidamente o que procuras.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works" id="how-it-works">
        <div class="how-it-works-container">
            <div class="section-title">
                <h2>Como Funciona</h2>
                <p>Começa a usar em apenas 4 passos simples</p>
            </div>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Cria a tua conta</h3>
                    <p>Regista-te gratuitamente em poucos segundos</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Personaliza o perfil</h3>
                    <p>Adiciona informações sobre ti</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Explora a plataforma</h3>
                    <p>Descobre posts e utilizadores</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Começa a interagir</h3>
                    <p>Publica, comenta e conversa</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <h2>Pronto para começar?</h2>
        <p>Junta-te a milhares de utilizadores que já fazem parte da nossa comunidade. Regista-te gratuitamente hoje!</p>
        <a href="public/register.php" class="btn-hero btn-hero-primary">Criar Conta Grátis</a>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="footer-content">
            <div class="footer-brand">
                <h3>Myspace</h3>
                <p>Uma plataforma completa para comunicação e partilha. Conecta-te com a comunidade e partilha as tuas ideias.</p>
            </div>
            <div class="footer-links">
                <h4>Plataforma</h4>
                <ul>
                    <li><a href="#features">Funcionalidades</a></li>
                    <li><a href="#how-it-works">Como Funciona</a></li>
                    <li><a href="public/register.php">Registar</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Recursos</h4>
                <ul>
                    <li><a href="blog.php">Blog</a></li>
                    <li><a href="chat.php">Chat</a></li>
                    <li><a href="#">Ajuda</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Legal</h4>
                <ul>
                    <li><a href="#">Termos de Uso</a></li>
                    <li><a href="#">Privacidade</a></li>
                    <li><a href="#">Contacto</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 Myspace. Todos os direitos reservados.</p>
        </div>
    </footer>

    <script>
        // Smooth scroll para links da navbar
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar background change on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.landing-navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(102, 126, 234, 0.95)';
                navbar.style.backdropFilter = 'blur(10px)';
            } else {
                navbar.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                navbar.style.backdropFilter = 'none';
            }
        });
    </script>
</body>
</html>