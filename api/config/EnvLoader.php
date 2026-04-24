<?php

class EnvLoader {
    private static $env = [];
    private static $loaded = false;

    public static function load($path = __DIR__ . '/../.env') {
        if (self::$loaded) {
            return;
        }

        if (!file_exists($path)) {
            throw new Exception("Environment file not found: $path");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse key=value
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }
                
                self::$env[$key] = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        return self::$env[$key] ?? $default;
    }

    public static function getInt($key, $default = 0) {
        $value = self::get($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }

    public static function getFloat($key, $default = 0.0) {
        $value = self::get($key, $default);
        return is_numeric($value) ? (float)$value : $default;
    }

    public static function getBool($key, $default = false) {
        $value = strtolower(self::get($key, $default ? 'true' : 'false'));
        return in_array($value, ['true', '1', 'yes', 'on']);
    }

    public static function all() {
        if (!self::$loaded) {
            self::load();
        }
        return self::$env;
    }
}
