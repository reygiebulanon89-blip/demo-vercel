<?php

require_once __DIR__ . '/../config/Config.php';

class JWT {
    private static $algorithm = 'HS256';

    private static function getSecretKey() {
        return JWT_SECRET;
    }

    public static function encode($data, $expiration = null) {
        $expiration = $expiration ?? JWT_EXPIRATION;
        
        $headers = [
            'alg' => self::$algorithm,
            'typ' => 'JWT'
        ];

        $payload = array_merge($data, [
            'iat' => time(),
            'exp' => time() + $expiration
        ]);

        $header = base64_encode(json_encode($headers));
        $payload_encoded = base64_encode(json_encode($payload));

        $signature = hash_hmac(
            'sha256',
            $header . '.' . $payload_encoded,
            self::getSecretKey(),
            true
        );
        $signature_encoded = base64_encode($signature);

        return $header . '.' . $payload_encoded . '.' . $signature_encoded;
    }

    public static function decode($token) {
        try {
            $parts = explode('.', $token);
            
            if (count($parts) !== 3) {
                return false;
            }

            $header = json_decode(base64_decode($parts[0]), true);
            $payload = json_decode(base64_decode($parts[1]), true);
            $signature = $parts[2];

            $valid_signature = hash_hmac(
                'sha256',
                $parts[0] . '.' . $parts[1],
                self::getSecretKey(),
                true
            );
            $valid_signature_encoded = base64_encode($valid_signature);

            if ($signature !== $valid_signature_encoded) {
                return false;
            }

            if ($payload['exp'] < time()) {
                return false;
            }

            return $payload;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function getToken() {
        $headers = getallheaders();
        
        if (isset($headers['Authorization'])) {
            $auth_header = $headers['Authorization'];
            
            if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
}
