<?php

require_once __DIR__ . '/EnvLoader.php';

// Load environment variables
EnvLoader::load(__DIR__ . '/../.env');

// Environment configuration
define('APP_ENV', EnvLoader::get('APP_ENV', 'development'));
define('APP_DEBUG', EnvLoader::getBool('APP_DEBUG', false));

// Error reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE);
    ini_set('display_errors', 0);
}

// API Configuration
define('API_VERSION', EnvLoader::get('API_VERSION', '1.0.0'));
define('API_BASE_URL', EnvLoader::get('API_BASE_URL', 'http://localhost/wasteless/api/'));

// JWT Configuration
define('JWT_SECRET', EnvLoader::get('JWT_SECRET'));
if (!JWT_SECRET || strlen(JWT_SECRET) < 32) {
    throw new Exception('JWT_SECRET must be set in .env and at least 32 characters long');
}
define('JWT_EXPIRATION', EnvLoader::getInt('JWT_EXPIRATION', 86400)); // 24 hours

// Database Configuration
define('DB_HOST', EnvLoader::get('DB_HOST', 'localhost'));
define('DB_USER', EnvLoader::get('DB_USER', 'root'));
define('DB_PASS', EnvLoader::get('DB_PASS', ''));
define('DB_NAME', EnvLoader::get('DB_NAME', 'wasteless'));

// File upload
define('UPLOAD_DIR', __DIR__ . '/../../' . EnvLoader::get('UPLOAD_DIR', 'uploads/'));
define('MAX_UPLOAD_SIZE', EnvLoader::getInt('MAX_UPLOAD_SIZE', 5242880)); // 5MB

// Pagination
define('DEFAULT_LIMIT', EnvLoader::getInt('DEFAULT_LIMIT', 20));
define('MAX_LIMIT', EnvLoader::getInt('MAX_LIMIT', 100));

// Rate Limiting
define('RATE_LIMIT_ENABLED', EnvLoader::getBool('RATE_LIMIT_ENABLED', true));
define('RATE_LIMIT_AUTH_ATTEMPTS', EnvLoader::getInt('RATE_LIMIT_AUTH_ATTEMPTS', 5));
define('RATE_LIMIT_AUTH_WINDOW', EnvLoader::getInt('RATE_LIMIT_AUTH_WINDOW', 60));

// CO2 Calculation Factors (configurable per waste type)
define('CO2_FACTORS', [
    'plastic' => EnvLoader::getFloat('CO2_FACTOR_PLASTIC', 2),
    'paper' => EnvLoader::getFloat('CO2_FACTOR_PAPER', 0.5),
    'food' => EnvLoader::getFloat('CO2_FACTOR_FOOD', 1.5),
    'metal' => EnvLoader::getFloat('CO2_FACTOR_METAL', 3),
    'glass' => EnvLoader::getFloat('CO2_FACTOR_GLASS', 1),
    'general' => EnvLoader::getFloat('CO2_FACTOR_GENERAL', 2)
]);
