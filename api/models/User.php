<?php

require_once __DIR__ . '/../config/Database.php';

class User {
    private $conn;
    private $table = 'users';

    public $id;
    public $username;
    public $email;
    public $password;
    public $bio;
    public $profile_pic;
    public $created_at;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function register() {
        $query = "INSERT INTO " . $this->table . " 
                  (username, email, password, bio, profile_pic, created_at) 
                  VALUES (:username, :email, :password, :bio, :profile_pic, NOW())";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password', $this->password);
        $stmt->bindParam(':bio', $this->bio);
        $stmt->bindParam(':profile_pic', $this->profile_pic);

        return $stmt->execute();
    }

    public function findByEmail() {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $query = "SELECT id, username, email, bio, profile_pic, created_at FROM " . $this->table . " WHERE id = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateProfile() {
        $query = "UPDATE " . $this->table . " 
                  SET username = :username, bio = :bio, profile_pic = :profile_pic 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':bio', $this->bio);
        $stmt->bindParam(':profile_pic', $this->profile_pic);

        return $stmt->execute();
    }

    public function getAllUsers($limit = 50) {
        $query = "SELECT id, username, email, bio, profile_pic, created_at FROM " . $this->table . " LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function follow($follower_id, $following_id) {
        if ($follower_id === $following_id) {
            return false;
        }

        $query = "INSERT IGNORE INTO user_follows (follower_id, following_id, created_at) 
                  VALUES (:follower_id, :following_id, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':follower_id', $follower_id);
        $stmt->bindParam(':following_id', $following_id);

        return $stmt->execute();
    }

    public function unfollow($follower_id, $following_id) {
        $query = "DELETE FROM user_follows WHERE follower_id = :follower_id AND following_id = :following_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':follower_id', $follower_id);
        $stmt->bindParam(':following_id', $following_id);

        return $stmt->execute();
    }

    public function getFollowers($user_id) {
        $query = "SELECT u.id, u.username, u.profile_pic FROM " . $this->table . " u
                  INNER JOIN user_follows uf ON u.id = uf.follower_id
                  WHERE uf.following_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFollowing($user_id) {
        $query = "SELECT u.id, u.username, u.profile_pic FROM " . $this->table . " u
                  INNER JOIN user_follows uf ON u.id = uf.following_id
                  WHERE uf.follower_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updatePassword($user_id, $new_password) {
        $query = "UPDATE " . $this->table . " SET password = :password WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':password', $new_password);
        $stmt->bindParam(':id', $user_id);
        
        return $stmt->execute();
    }

    public function savePasswordResetToken($email, $token, $expires) {
        // Check if password_resets table exists, if not create it
        try {
            $query = "INSERT INTO password_resets (email, token, expires) VALUES (:email, :token, :expires)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':expires', $expires);
            return $stmt->execute();
        } catch (Exception $e) {
            // Table might not exist, try to create it
            $this->createPasswordResetsTable();
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':expires', $expires);
            return $stmt->execute();
        }
    }

    public function verifyPasswordResetToken($token) {
        $query = "SELECT u.* FROM " . $this->table . " u
                  INNER JOIN password_resets pr ON u.email = pr.email
                  WHERE pr.token = :token AND pr.expires > NOW() LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function invalidatePasswordResetToken($token) {
        $query = "DELETE FROM password_resets WHERE token = :token";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        
        return $stmt->execute();
    }

    private function createPasswordResetsTable() {
        $query = "CREATE TABLE IF NOT EXISTS password_resets (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
    }
}
