<?php

require_once __DIR__ . '/../config/Database.php';

class Comment {
    private $conn;
    private $table = 'post_comments';

    public $id;
    public $post_id;
    public $user_id;
    public $comment;
    public $parent_comment_id;
    public $created_at;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (post_id, user_id, comment, parent_comment_id, created_at) 
                  VALUES (:post_id, :user_id, :comment, :parent_comment_id, NOW())";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':post_id', $this->post_id);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':comment', $this->comment);
        if ($this->parent_comment_id === null) {
            $stmt->bindValue(':parent_comment_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':parent_comment_id', (int)$this->parent_comment_id, PDO::PARAM_INT);
        }

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function getComments($post_id, $limit = 20, $offset = 0) {
        $query = "SELECT pc.id, pc.post_id, pc.user_id, pc.comment, pc.parent_comment_id, pc.created_at,
                         u.username, u.profile_pic
                  FROM " . $this->table . " pc
                  LEFT JOIN users u ON pc.user_id = u.id
                  WHERE pc.post_id = :post_id
                  ORDER BY pc.created_at ASC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteComment($id, $user_id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $user_id);

        return $stmt->execute();
    }

    public function updateComment($id, $user_id, $comment) {
        $query = "UPDATE " . $this->table . " SET comment = :comment WHERE id = :id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':comment', $comment);

        return $stmt->execute();
    }

    public function getCommentById($comment_id) {
        $query = "SELECT pc.id, pc.post_id, pc.user_id, pc.comment, pc.parent_comment_id, pc.created_at,
                         u.username, u.profile_pic
                  FROM " . $this->table . " pc
                  LEFT JOIN users u ON pc.user_id = u.id
                  WHERE pc.id = :comment_id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':comment_id', $comment_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
