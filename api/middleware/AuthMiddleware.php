<?php

require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Response.php';

class AuthMiddleware {
    public static function verify() {
        $token = JWT::getToken();
        
        if (!$token) {
            Response::unauthorized('No token provided');
        }

        $decoded = JWT::decode($token);
        
        if (!$decoded) {
            Response::unauthorized('Invalid or expired token');
        }

        return $decoded;
    }

    public static function verifyOptional() {
        $token = JWT::getToken();
        
        if (!$token) {
            return null;
        }

        $decoded = JWT::decode($token);
        
        if (!$decoded) {
            return null;
        }

        return $decoded;
    }
}
