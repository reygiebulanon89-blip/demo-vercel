<?php

require_once __DIR__ . '/../config/Database.php';

class Track {
    private $conn;
    private $table = 'waste_tracking';

    public $id;
    public $user_id;
    public $waste_type;
    public $quantity;
    public $unit;
    public $co2_saved;
    public $created_at;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function create() {
        $useCustomCreatedAt = !empty($this->created_at);
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, waste_type, quantity, unit, co2_saved, created_at) 
                  VALUES (:user_id, :waste_type, :quantity, :unit, :co2_saved, " . ($useCustomCreatedAt ? ":created_at" : "NOW()") . ")";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':waste_type', $this->waste_type);
        $stmt->bindParam(':quantity', $this->quantity);
        $stmt->bindParam(':unit', $this->unit);
        $stmt->bindParam(':co2_saved', $this->co2_saved);
        if ($useCustomCreatedAt) {
            $stmt->bindParam(':created_at', $this->created_at);
        }

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function getUserTracking($user_id, $limit = 50, $offset = 0) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE user_id = :user_id
                  ORDER BY created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserStats($user_id) {
        $query = "SELECT 
                    COUNT(*) as total_logs,
                    SUM(quantity) as total_waste,
                    SUM(co2_saved) as total_co2_saved,
                    AVG(quantity) as avg_waste
                  FROM " . $this->table . " 
                  WHERE user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Stats shaped for the Profile UI (`profile.html`).
     * - total_entries: number of waste_tracking rows for user
     * - challenges_completed: number of joined challenges where progress >= target
     * - current_streak: consecutive-day streak ending today (or yesterday if no entry today)
     */
    public function getUserProfileStats($user_id) {
        $user_id = (int)$user_id;

        // Total entries
        $stmtEntries = $this->conn->prepare(
            "SELECT COUNT(*) AS total_entries
             FROM " . $this->table . "
             WHERE user_id = :user_id"
        );
        $stmtEntries->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmtEntries->execute();
        $entriesRow = $stmtEntries->fetch(PDO::FETCH_ASSOC) ?: ['total_entries' => 0];

        // Challenges completed (progress >= target)
        $stmtChallenges = $this->conn->prepare(
            "SELECT COUNT(*) AS challenges_completed
             FROM user_challenges uc
             INNER JOIN challenges c ON c.id = uc.challenge_id
             WHERE uc.user_id = :user_id
               AND uc.progress >= c.target"
        );
        $stmtChallenges->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmtChallenges->execute();
        $challengesRow = $stmtChallenges->fetch(PDO::FETCH_ASSOC) ?: ['challenges_completed' => 0];

        // Streak (consecutive days with at least one entry)
        $stmtDays = $this->conn->prepare(
            "SELECT DISTINCT DATE(created_at) AS day
             FROM " . $this->table . "
             WHERE user_id = :user_id
             ORDER BY day DESC"
        );
        $stmtDays->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmtDays->execute();
        $days = $stmtDays->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $current_streak = $this->calculateCurrentStreak($days);

        return [
            'total_entries' => (int)($entriesRow['total_entries'] ?? 0),
            'challenges_completed' => (int)($challengesRow['challenges_completed'] ?? 0),
            'current_streak' => (int)$current_streak,
        ];
    }

    private function calculateCurrentStreak($daysDesc) {
        if (!$daysDesc || !is_array($daysDesc)) {
            return 0;
        }

        // Normalize to YYYY-MM-DD strings.
        $set = [];
        foreach ($daysDesc as $d) {
            if ($d) $set[$d] = true;
        }
        if (!$set) return 0;

        $today = new DateTimeImmutable('today');
        $yesterday = $today->sub(new DateInterval('P1D'));

        $start = null;
        $todayStr = $today->format('Y-m-d');
        $yesterdayStr = $yesterday->format('Y-m-d');

        if (isset($set[$todayStr])) {
            $start = $today;
        } elseif (isset($set[$yesterdayStr])) {
            $start = $yesterday;
        } else {
            return 0;
        }

        $streak = 0;
        $cursor = $start;
        while (true) {
            $key = $cursor->format('Y-m-d');
            if (!isset($set[$key])) break;
            $streak++;
            $cursor = $cursor->sub(new DateInterval('P1D'));
        }

        return $streak;
    }

    public function getWasteByType($user_id) {
        $query = "SELECT waste_type, SUM(quantity) as total, SUM(co2_saved) as co2_saved
                  FROM " . $this->table . " 
                  WHERE user_id = :user_id
                  GROUP BY waste_type";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getGlobalStats() {
        $query = "SELECT 
                    COUNT(*) as total_logs,
                    SUM(quantity) as total_waste,
                    SUM(co2_saved) as total_co2_saved,
                    COUNT(DISTINCT user_id) as total_users
                  FROM " . $this->table;

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteLog($id, $user_id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $user_id);

        return $stmt->execute();
    }
}
