<?php

require_once __DIR__ . '/../config/Config.php';

class RateLimiter {
    private static $storage_dir = __DIR__ . '/../../data/rate_limit';

    /**
     * Initialize storage directory
     */
    private static function initStorage() {
        if (!is_dir(self::$storage_dir)) {
            mkdir(self::$storage_dir, 0755, true);
        }
    }

    /**
     * Get rate limit key filename
     */
    private static function getKeyFile($key) {
        return self::$storage_dir . '/' . md5($key) . '.json';
    }

    /**
     * Check if request is allowed
     */
    public static function isAllowed($key, $limit = null, $window = null) {
        if (!RATE_LIMIT_ENABLED) {
            return true;
        }

        $limit = $limit ?? RATE_LIMIT_AUTH_ATTEMPTS;
        $window = $window ?? RATE_LIMIT_AUTH_WINDOW;

        self::initStorage();
        $file = self::getKeyFile($key);
        $current_time = time();

        // Get or create rate limit data
        $data = [];
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $data = json_decode($content, true) ?? [];
        }

        // Clean old requests outside the window
        $data['requests'] = array_filter(
            $data['requests'] ?? [],
            function ($time) use ($current_time, $window) {
                return ($current_time - $time) < $window;
            }
        );

        // Check if limit exceeded
        if (count($data['requests']) >= $limit) {
            return false;
        }

        // Add current request
        $data['requests'][] = $current_time;
        $data['last_request'] = $current_time;

        // Save updated data
        file_put_contents($file, json_encode($data));

        return true;
    }

    /**
     * Get remaining attempts
     */
    public static function getRemaining($key, $limit = null, $window = null) {
        if (!RATE_LIMIT_ENABLED) {
            return PHP_INT_MAX;
        }

        $limit = $limit ?? RATE_LIMIT_AUTH_ATTEMPTS;
        $window = $window ?? RATE_LIMIT_AUTH_WINDOW;

        self::initStorage();
        $file = self::getKeyFile($key);
        $current_time = time();

        if (!file_exists($file)) {
            return $limit;
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true) ?? [];

        // Clean old requests
        $valid_requests = array_filter(
            $data['requests'] ?? [],
            function ($time) use ($current_time, $window) {
                return ($current_time - $time) < $window;
            }
        );

        return max(0, $limit - count($valid_requests));
    }

    /**
     * Get time until next attempt is allowed
     */
    public static function getResetTime($key, $window = null) {
        $window = $window ?? RATE_LIMIT_AUTH_WINDOW;
        
        self::initStorage();
        $file = self::getKeyFile($key);

        if (!file_exists($file)) {
            return 0;
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true) ?? [];

        if (empty($data['requests'])) {
            return 0;
        }

        $oldest_request = min($data['requests']);
        $reset_time = $oldest_request + $window;
        $current_time = time();

        return max(0, $reset_time - $current_time);
    }

    /**
     * Reset rate limit for key
     */
    public static function reset($key) {
        self::initStorage();
        $file = self::getKeyFile($key);

        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Clear all rate limit data
     */
    public static function clearAll() {
        self::initStorage();
        $files = glob(self::$storage_dir . '/*.json');

        foreach ($files as $file) {
            unlink($file);
        }
    }
}
