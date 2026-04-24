<?php

require_once __DIR__ . '/../config/Config.php';

class Helper {
    public static function formatDate($date) {
        return date('Y-m-d H:i:s', strtotime($date));
    }

    public static function getFileName($path) {
        return basename($path);
    }

    /**
     * Sanitize input to prevent XSS attacks
     */
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        
        $input = trim($input);
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize HTML content (more permissive than sanitize)
     */
    public static function sanitizeHtml($input) {
        return strip_tags($input, '<b><i><strong><em><p><br><u><a><img>');
    }

    /**
     * Validate email format
     */
    public static function validateEmail($email) {
        $email = strtolower(trim($email));
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Additional validation: check DNS record for MX
        if (function_exists('checkdnsrr')) {
            $domain = substr(strrchr($email, "@"), 1);
            return checkdnsrr($domain, "MX");
        }
        
        return true;
    }

    /**
     * Validate username (alphanumeric and underscore only)
     */
    public static function validateUsername($username) {
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
    }

    /**
     * Validate password strength
     */
    public static function validatePassword($password) {
        // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
    }

    /**
     * Validate URL
     */
    public static function validateUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL);
    }

    /**
     * Validate numeric range
     */
    public static function validateRange($value, $min, $max) {
        return is_numeric($value) && $value >= $min && $value <= $max;
    }

    /**
     * Generate random string
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Calculate CO2 savings based on waste type
     */
    public static function calculateCO2Savings($waste_quantity, $waste_type = 'general') {
        if (!is_numeric($waste_quantity) || $waste_quantity < 0) {
            return 0;
        }

        $co2_factors = CO2_FACTORS;
        $factor = $co2_factors[$waste_type] ?? $co2_factors['general'];
        
        return round($waste_quantity * $factor, 2);
    }

    /**
     * Validate and sanitize pagination parameters
     */
    public static function paginationValidate(&$limit, &$offset) {
        if (!is_numeric($limit) || $limit < 1) {
            $limit = DEFAULT_LIMIT;
        }
        if ($limit > MAX_LIMIT) {
            $limit = MAX_LIMIT;
        }
        
        if (!is_numeric($offset) || $offset < 0) {
            $offset = 0;
        }

        $limit = (int)$limit;
        $offset = (int)$offset;
    }

    /**
     * Check if waste type is valid
     */
    public static function isValidWasteType($waste_type) {
        $valid_types = array_keys(CO2_FACTORS);
        return in_array($waste_type, $valid_types);
    }

    /**
     * Hash password securely
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Get safe JSON response
     */
    public static function jsonEncode($data, $options = JSON_UNESCAPED_SLASHES) {
        return json_encode($data, $options);
    }

    /**
     * Truncate text with ellipsis
     */
    public static function truncate($text, $length = 100, $ellipsis = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length - strlen($ellipsis)) . $ellipsis;
    }
}
