<?php
require_once __DIR__ . '/../models/User.php';

class AuthController {
    private $db;
    private $userModel;

    public function __construct() {
        try {
            $this->db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $this->userModel = new User($this->db);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public function register($name, $password, $email = null) {
        // Validate inputs
        if (empty($name) || empty($password)) {
            return false;
        }

        // Check if name already exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            return false; // Name already taken
        }

        // Check if email already exists (if provided)
        if ($email) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return false; // Email already taken
            }
        }

        // Register the user
        return $this->userModel->register($name, $password, $email);
    }

    public function login($name, $password) {
        // Validate inputs
        if (empty($name) || empty($password)) {
            return false;
        }

        $user = $this->userModel->login($name, $password);
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['name'];
            $_SESSION['is_admin'] = isset($user['is_admin']) ? (bool)$user['is_admin'] : false;
            return true;
        }
        return false;
    }

    public function logout() {
        session_unset();
        session_destroy();
    }

    public function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }
}
?>