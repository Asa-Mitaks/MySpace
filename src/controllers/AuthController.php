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

    /**
     * Generate JWT token for WebSocket authentication
     */
    public function generateWebSocketToken($userId) {
        // Get user info for token
        $stmt = $this->db->prepare("SELECT id, name, email, profile_image FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return null;
        }

        // Create JWT payload
        $payload = [
            'userId' => $user['id'],
            'userInfo' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'profile_image' => $user['profile_image']
            ],
            'type' => 'websocket',
            'timestamp' => time(),
            'exp' => time() + (24 * 60 * 60)
        ];

        // Simple JWT implementation (for development)
        return $this->generateSimpleJWT($payload);
    }

    /**
     * Simple JWT implementation (fallback)
     */
    private function generateSimpleJWT($payload) {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payloadJson = json_encode($payload);
        $secret = 'myspace_jwt_secret_key_2024_change_in_production_please_use_strong_random_string';
        
        $headerEncoded = $this->base64UrlEncode($header);
        $payloadEncoded = $this->base64UrlEncode($payloadJson);
        
        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $secret, true);
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Validate WebSocket token with Node.js server
     */
    public function validateWebSocketToken($token) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost:3002/validate-token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['token' => $token]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return false;
        }
        
        $data = json_decode($response, true);
        return $data['valid'] ?? false;
    }
}