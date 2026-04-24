<?php

require_once __DIR__ . '/../config/Database.php';

class Challenge {
    private $conn;
    private $table = 'challenges';

    public $id;
    public $title;
    public $description;
    public $created_by;
    public $target;
    public $target_unit;
    public $start_date;
    public $end_date;
    public $created_at;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (title, description, created_by, target, target_unit, start_date, end_date, created_at) 
                  VALUES (:title, :description, :created_by, :target, :target_unit, :start_date, :end_date, NOW())";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':created_by', $this->created_by);
        $stmt->bindParam(':target', $this->target);
        $stmt->bindParam(':target_unit', $this->target_unit);
        $stmt->bindParam(':start_date', $this->start_date);
        $stmt->bindParam(':end_date', $this->end_date);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function getAllChallenges($limit = 20, $offset = 0) {
        $query = "SELECT c.id, c.title, c.description, c.created_by, c.target, c.target_unit, 
                         c.start_date, c.end_date, c.created_at,
                         u.username, u.profile_pic,
                         COUNT(DISTINCT uc.id) as participants
                  FROM " . $this->table . " c
                  LEFT JOIN users u ON c.created_by = u.id
                  LEFT JOIN user_challenges uc ON c.id = uc.challenge_id
                  GROUP BY c.id
                  ORDER BY c.created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getChallengeById($id) {
        $query = "SELECT c.id, c.title, c.description, c.created_by, c.target, c.target_unit, 
                         c.start_date, c.end_date, c.created_at,
                         u.username, u.profile_pic,
                         COUNT(DISTINCT uc.id) as participants
                  FROM " . $this->table . " c
                  LEFT JOIN users u ON c.created_by = u.id
                  LEFT JOIN user_challenges uc ON c.id = uc.challenge_id
                  WHERE c.id = :id
                  GROUP BY c.id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function joinChallenge($challenge_id, $user_id) {
        $query = "INSERT IGNORE INTO user_challenges (challenge_id, user_id, progress, joined_at) 
                  VALUES (:challenge_id, :user_id, 0, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':challenge_id', $challenge_id);
        $stmt->bindParam(':user_id', $user_id);

        return $stmt->execute();
    }

    public function leaveChallenge($challenge_id, $user_id) {
        $query = "DELETE FROM user_challenges WHERE challenge_id = :challenge_id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':challenge_id', $challenge_id);
        $stmt->bindParam(':user_id', $user_id);

        return $stmt->execute();
    }

    public function updateProgress($challenge_id, $user_id, $progress) {
        $query = "UPDATE user_challenges SET progress = :progress WHERE challenge_id = :challenge_id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':challenge_id', $challenge_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':progress', $progress);

        return $stmt->execute();
    }

    public function getUserChallenges($user_id, $limit = 20, $offset = 0) {
        $query = "SELECT c.id, c.title, c.description, c.target, c.target_unit, 
                         c.start_date, c.end_date, uc.progress, uc.joined_at
                  FROM " . $this->table . " c
                  INNER JOIN user_challenges uc ON c.id = uc.challenge_id
                  WHERE uc.user_id = :user_id
                  ORDER BY uc.joined_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isUserInChallenge($challenge_id, $user_id) {
        $query = "SELECT id FROM user_challenges WHERE challenge_id = :challenge_id AND user_id = :user_id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':challenge_id', $challenge_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * Check if user is the creator of the challenge
     */
    public function isCreator($challenge_id, $user_id) {
        $query = "SELECT created_by FROM " . $this->table . " WHERE id = :id AND created_by = :user_id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $challenge_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * Update challenge (only by creator)
     */
    public function updateChallenge($id, $user_id, $data) {
        $query = "UPDATE " . $this->table . " SET title = :title, description = :description, 
                  target = :target, target_unit = :target_unit, 
                  start_date = :start_date, end_date = :end_date 
                  WHERE id = :id AND created_by = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':title', $data['title'] ?? $this->title);
        $stmt->bindParam(':description', $data['description'] ?? $this->description);
        $stmt->bindParam(':target', $data['target'] ?? $this->target);
        $stmt->bindParam(':target_unit', $data['target_unit'] ?? $this->target_unit);
        $stmt->bindParam(':start_date', $data['start_date'] ?? $this->start_date);
        $stmt->bindParam(':end_date', $data['end_date'] ?? $this->end_date);

        return $stmt->execute();
    }

    /**
     * Delete challenge (only by creator)
     */
    public function deleteChallenge($id, $user_id) {
        // First delete all user_challenges records
        $query1 = "DELETE FROM user_challenges WHERE challenge_id = :id";
        $stmt1 = $this->conn->prepare($query1);
        $stmt1->bindParam(':id', $id);
        $stmt1->execute();

        // Then delete the challenge
        $query2 = "DELETE FROM " . $this->table . " WHERE id = :id AND created_by = :user_id";
        $stmt2 = $this->conn->prepare($query2);
        $stmt2->bindParam(':id', $id);
        $stmt2->bindParam(':user_id', $user_id);

        return $stmt2->execute();
    }
}
