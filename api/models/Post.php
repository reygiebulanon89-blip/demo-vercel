<?php

require_once __DIR__ . '/../config/Database.php';

class Post {
    private $conn;
    private $table = 'posts';

    public $id;
    public $user_id;
    public $content;
    public $image;
    public $created_at;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, content, image, created_at) 
                  VALUES (:user_id, :content, :image, NOW())";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':content', $this->content);
        $stmt->bindParam(':image', $this->image);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    private function buildLikedByUserSelect($viewer_user_id) {
        if (!$viewer_user_id) {
            return "0 as liked_by_user";
        }

        return "EXISTS(
                    SELECT 1
                    FROM post_likes viewer_like
                    WHERE viewer_like.post_id = p.id AND viewer_like.user_id = :viewer_user_id
                ) as liked_by_user";
    }

    public function getFeed($limit = 20, $offset = 0, $viewer_user_id = null) {
        $query = "SELECT p.id, p.user_id, p.content, p.image, p.created_at,
                         u.username, u.profile_pic,
                         COUNT(DISTINCT pl.id) as likes,
                         COUNT(DISTINCT pc.id) as comments,
                         " . $this->buildLikedByUserSelect($viewer_user_id) . "
                  FROM " . $this->table . " p
                  LEFT JOIN users u ON p.user_id = u.id
                  LEFT JOIN post_likes pl ON p.id = pl.post_id
                  LEFT JOIN post_comments pc ON p.id = pc.post_id
                  GROUP BY p.id
                  ORDER BY p.created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        if ($viewer_user_id) {
            $stmt->bindParam(':viewer_user_id', $viewer_user_id, PDO::PARAM_INT);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserPosts($user_id, $limit = 20, $offset = 0, $viewer_user_id = null) {
        $query = "SELECT p.id, p.user_id, p.content, p.image, p.created_at,
                         u.username, u.profile_pic,
                         COUNT(DISTINCT pl.id) as likes,
                         COUNT(DISTINCT pc.id) as comments,
                         " . $this->buildLikedByUserSelect($viewer_user_id) . "
                  FROM " . $this->table . " p
                  LEFT JOIN users u ON p.user_id = u.id
                  LEFT JOIN post_likes pl ON p.id = pl.post_id
                  LEFT JOIN post_comments pc ON p.id = pc.post_id
                  WHERE p.user_id = :user_id
                  GROUP BY p.id
                  ORDER BY p.created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        if ($viewer_user_id) {
            $stmt->bindParam(':viewer_user_id', $viewer_user_id, PDO::PARAM_INT);
        }
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPostById($id, $viewer_user_id = null) {
        $query = "SELECT p.id, p.user_id, p.content, p.image, p.created_at,
                         u.username, u.profile_pic,
                         COUNT(DISTINCT pl.id) as likes,
                         COUNT(DISTINCT pc.id) as comments,
                         " . $this->buildLikedByUserSelect($viewer_user_id) . "
                  FROM " . $this->table . " p
                  LEFT JOIN users u ON p.user_id = u.id
                  LEFT JOIN post_likes pl ON p.id = pl.post_id
                  LEFT JOIN post_comments pc ON p.id = pc.post_id
                  WHERE p.id = :id
                  GROUP BY p.id";

        $stmt = $this->conn->prepare($query);
        if ($viewer_user_id) {
            $stmt->bindParam(':viewer_user_id', $viewer_user_id, PDO::PARAM_INT);
        }
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deletePost($id, $user_id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $user_id);

        return $stmt->execute();
    }

    public function updatePost($id, $user_id, $content) {
        $query = "UPDATE " . $this->table . " SET content = :content WHERE id = :id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':content', $content);

        return $stmt->execute();
    }

    public function likePost($post_id, $user_id) {
        $query = "INSERT IGNORE INTO post_likes (post_id, user_id, created_at) VALUES (:post_id, :user_id, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->bindParam(':user_id', $user_id);

        return $stmt->execute();
    }

    public function unlikePost($post_id, $user_id) {
        $query = "DELETE FROM post_likes WHERE post_id = :post_id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->bindParam(':user_id', $user_id);

        return $stmt->execute();
    }

    public function isLikedByUser($post_id, $user_id) {
        $query = "SELECT id FROM post_likes WHERE post_id = :post_id AND user_id = :user_id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function getPostOwnerId($post_id) {
        $query = "SELECT user_id FROM " . $this->table . " WHERE id = :post_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['user_id'] : null;
    }
}
