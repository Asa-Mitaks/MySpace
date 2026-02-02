<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Forum</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <header>
        <h1>Welcome to the Chat Forum</h1>
        <nav>
            <ul>
                <li><a href="/index.php">Home</a></li>
                <li><a href="/blog.php">Blog</a></li>
                <li><a href="/chat.php">Chat</a></li>
                <li><a href="/login.php">Login</a></li>
                <li><a href="/register.php">Register</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <?php
        // This is where the content from the specific view will be included
        if (isset($content)) {
            include $content;
        }
        ?>
    </main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Chat Forum. All rights reserved.</p>
    </footer>
    <script src="/js/app.js"></script>
</body>
</html>