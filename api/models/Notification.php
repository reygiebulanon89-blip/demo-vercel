<?php

require_once __DIR__ . '/../config/Database.php';

class Notification {
    private $conn;
    private $table = 'notifications';

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function create($user_id, $actor_id, $post_id, $type, $message) {
        $query = "INSERT INTO " . $this->table . "
                  (user_id, actor_id, post_id, type, message, is_read, created_at)
                  VALUES (:user_id, :actor_id, :post_id, :type, :message, 0, NOW())";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':actor_id', $actor_id, PDO::PARAM_INT);
        $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':message', $message);

        if ($stmt->execute()) {
            return (int)$this->conn->lastInsertId();
        }

        return false;
    }

    public function getForUser($user_id, $limit = 30, $after_id = 0) {
        $query = "SELECT n.id, n.user_id, n.actor_id, n.post_id, n.type, n.message, n.is_read, n.created_at,
                         actor.username as actor_username
                  FROM " . $this->table . " n
                  LEFT JOIN users actor ON actor.id = n.actor_id
                  WHERE n.user_id = :user_id
                    AND n.id > :after_id
                  ORDER BY n.id DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':after_id', $after_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markAllRead($user_id) {
        $query = "UPDATE " . $this->table . " SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deleteForUser($notification_id, $user_id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $notification_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
