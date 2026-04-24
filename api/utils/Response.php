<?php

class Response {
    private static $should_exit = true;

    /**
     * Set whether Response should call exit() after sending response
     * Useful for testing
     */
    public static function setAutoExit($should_exit) {
        self::$should_exit = $should_exit;
    }

    /**
     * Send success response
     */
    public static function success($data, $message = 'Success', $code = 200) {
        return self::send([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Send error response
     */
    public static function error($message = 'Error', $code = 400, $data = null) {
        return self::send([
            'status' => 'error',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Send created response (201)
     */
    public static function created($data, $message = 'Created successfully') {
        return self::success($data, $message, 201);
    }

    /**
     * Send not found response (404)
     */
    public static function notFound($message = 'Resource not found') {
        return self::error($message, 404);
    }

    /**
     * Send unauthorized response (401)
     */
    public static function unauthorized($message = 'Unauthorized') {
        return self::error($message, 401);
    }

    /**
     * Send forbidden response (403)
     */
    public static function forbidden($message = 'Forbidden') {
        return self::error($message, 403);
    }

    /**
     * Send bad request response (400)
     */
    public static function badRequest($message = 'Bad request') {
        return self::error($message, 400);
    }

    /**
     * Send server error response (500)
     */
    public static function serverError($message = 'Server error') {
        return self::error($message, 500);
    }

    /**
     * Send validation error response
     */
    public static function validationError($errors) {
        return self::send([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $errors
        ], 422);
    }

    /**
     * Internal method to send response
     */
    private static function send($response, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        $json = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo $json;
        
        if (self::$should_exit) {
            exit;
        }
        
        return $json;
    }
}
