<?php
class Post {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function createPost($title, $content, $authorId) {
        $stmt = $this->db->prepare("INSERT INTO posts (title, content, author_id) VALUES (?, ?, ?)");
        return $stmt->execute([$title, $content, $authorId]);
    }

    public function getPost($id) {
        $stmt = $this->db->prepare("SELECT * FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getAllPosts() {
        $stmt = $this->db->query("SELECT * FROM posts ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function updatePost($id, $title, $content) {
        $stmt = $this->db->prepare("UPDATE posts SET title = ?, content = ? WHERE id = ?");
        return $stmt->execute([$title, $content, $id]);
    }

    public function deletePost($id) {
        $stmt = $this->db->prepare("DELETE FROM posts WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
?>