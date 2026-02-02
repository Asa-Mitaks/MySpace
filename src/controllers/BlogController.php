<?php

class BlogController {
    private $postModel;

    public function __construct($postModel) {
        $this->postModel = $postModel;
    }

    public function index() {
        $posts = $this->postModel->getAllPosts();
        include '../views/blog/index.php';
    }

    public function create($data) {
        if ($this->postModel->createPost($data)) {
            header('Location: /blog.php');
        } else {
            // Handle error
        }
    }

    public function update($id, $data) {
        if ($this->postModel->updatePost($id, $data)) {
            header('Location: /blog.php');
        } else {
            // Handle error
        }
    }

    public function delete($id) {
        if ($this->postModel->deletePost($id)) {
            header('Location: /blog.php');
        } else {
            // Handle error
        }
    }
}