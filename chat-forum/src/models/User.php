<?php
class User {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function register($name, $password, $email) {
        // Hash password with bcrypt using cost factor of 12 for stronger security
        $options = ['cost' => 12];
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, $options);
        
        $stmt = $this->db->prepare("INSERT INTO users (name, password, email) VALUES (?, ?, ?)");
        return $stmt->execute([$name, $hashedPassword, $email]);
    }

    public function login($name, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE name = ?");
        $stmt->execute([$name]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    public function getUserById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateUser($id, $name, $email) {
        $stmt = $this->db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        return $stmt->execute([$name, $email, $id]);
    }

    public function deleteUser($id) {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
?>