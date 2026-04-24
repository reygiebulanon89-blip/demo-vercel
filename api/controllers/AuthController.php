<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Helper.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/RateLimiter.php';

class AuthController {
    public function register() {
        $data = json_decode(file_get_contents("php://input"), true);

        // Apply rate limiting
        $ip = $_SERVER['REMOTE_ADDR'];
        if (!RateLimiter::isAllowed("register_$ip")) {
            Response::error('Too many registration attempts. Please try again later.', 429);
        }

        // Validate required fields
        $validator = new Validator();
        if (!$validator->validate($data ?? [], [
            'username' => 'required|string|min:3|max:20',
            'email' => 'required|email',
            'password' => 'required|min:8'
        ])) {
            Response::badRequest($validator->getFirstError());
        }

        $username = Helper::sanitize($data['username']);
        $email = Helper::sanitize(strtolower($data['email']));
        $password = $data['password']; // Don't sanitize passwords

        // Validate username format
        if (!Helper::validateUsername($username)) {
            Response::badRequest('Username must be alphanumeric, 3-20 characters');
        }

        // Validate email
        if (!Helper::validateEmail($email)) {
            Response::badRequest('Invalid email address');
        }

        // Validate password strength
        if (!Helper::validatePassword($password)) {
            Response::badRequest('Password must be at least 8 characters with uppercase, lowercase, and number');
        }

        $user = new User();
        $user->username = $username;
        $user->email = $email;
        $user->password = Helper::hashPassword($password);
        $user->bio = Helper::sanitize($data['bio'] ?? '');
        $user->profile_pic = isset($data['profile_pic']) ? Helper::sanitize($data['profile_pic']) : null;

        // Check if email already exists
        if ($user->findByEmail()) {
            Response::error('Email is already registered.', 400);
        }

        if ($user->register()) {
            $user_data = $user->findByEmail();
            $token = JWT::encode(['id' => $user_data['id'], 'email' => $user_data['email']]);

            Response::created([
                'user' => [
                    'id' => $user_data['id'],
                    'username' => $user_data['username'],
                    'email' => $user_data['email'],
                    'bio' => $user_data['bio'],
                    'profile_pic' => $user_data['profile_pic']
                ],
                'token' => $token
            ], 'User registered successfully');
        }

        Response::serverError('Error registering user');
    }

    public function login() {
        $data = json_decode(file_get_contents("php://input"), true);

        // Apply rate limiting based on email or IP
        $ip = $_SERVER['REMOTE_ADDR'];
        $rate_key = !empty($data['email']) ? "login_" . strtolower($data['email']) : "login_$ip";
        
        if (!RateLimiter::isAllowed($rate_key)) {
            $remaining = RateLimiter::getRemaining($rate_key);
            Response::error('Too many login attempts. Please try again later.', 429);
        }

        // Validate required fields
        $validator = new Validator();
        if (!$validator->validate($data ?? [], [
            'email' => 'required|email',
            'password' => 'required'
        ])) {
            Response::badRequest($validator->getFirstError());
        }

        $email = Helper::sanitize(strtolower($data['email']));
        $password = $data['password'];

        $user = new User();
        $user->email = $email;
        $user_data = $user->findByEmail();

        if (!$user_data || !Helper::verifyPassword($password, $user_data['password'])) {
            Response::unauthorized('Invalid email or password.');
        }

        // Reset rate limit on successful login
        RateLimiter::reset($rate_key);

        $token = JWT::encode(['id' => $user_data['id'], 'email' => $user_data['email']]);

        Response::success([
            'user' => [
                'id' => $user_data['id'],
                'username' => $user_data['username'],
                'email' => $user_data['email'],
                'bio' => $user_data['bio'],
                'profile_pic' => $user_data['profile_pic']
            ],
            'token' => $token
        ], 'Login successful');
    }

    public function forgotPassword() {
        $data = json_decode(file_get_contents("php://input"), true);

        // Apply rate limiting
        $ip = $_SERVER['REMOTE_ADDR'];
        if (!RateLimiter::isAllowed("forgotpwd_$ip")) {
            Response::error('Too many requests. Please try again later.', 429);
        }

        // Validate email
        $validator = new Validator();
        if (!$validator->validate($data ?? [], [
            'email' => 'required|email'
        ])) {
            Response::badRequest($validator->getFirstError());
        }

        $email = Helper::sanitize(strtolower($data['email']));

        // Check if user exists
        $user = new User();
        $user->email = $email;
        $user_data = $user->findByEmail();

        if (!$user_data) {
            // Don't reveal if email exists or not for security
            Response::success(['message' => 'If the email exists, a reset link will be sent.']);
            return;
        }

        // Generate reset token
        $reset_token = bin2hex(random_bytes(32));
        $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Store reset token in database (you would need to add this column)
        // For now, we'll simulate the response
        $user->savePasswordResetToken($email, $reset_token, $reset_expires);

        // In production, send email with reset link
        // For demo purposes, we'll return success
        Response::success(['message' => 'Password reset link has been sent to your email.']);
    }

    public function resetPassword() {
        $data = json_decode(file_get_contents("php://input"), true);

        // Validate required fields
        $validator = new Validator();
        if (!$validator->validate($data ?? [], [
            'token' => 'required',
            'password' => 'required|min:8'
        ])) {
            Response::badRequest($validator->getFirstError());
        }

        $token = $data['token'];
        $password = $data['password'];

        // Validate password strength
        if (!Helper::validatePassword($password)) {
            Response::badRequest('Password must be at least 8 characters with uppercase, lowercase, and number');
        }

        // Verify reset token
        $user = new User();
        $user_data = $user->verifyPasswordResetToken($token);

        if (!$user_data) {
            Response::badRequest('Invalid or expired reset token.');
        }

        // Update password
        $hashed_password = Helper::hashPassword($password);
        $user->updatePassword($user_data['id'], $hashed_password);

        // Invalidate reset token
        $user->invalidatePasswordResetToken($token);

        Response::success(['message' => 'Password has been reset successfully.']);
    }

    public function logout() {
        // Logout is handled client-side by removing the token
        // But we can invalidate the token server-side if needed
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if ($token) {
            // Remove Bearer prefix if present
            $token = str_replace('Bearer ', '', $token);
            
            // Add token to blacklist (you would implement this)
            // For now, we'll just return success
        }

        Response::success(['message' => 'Logged out successfully.']);
    }

    public function changePassword() {
        // Get authenticated user from JWT
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (empty($auth_header)) {
            Response::unauthorized('No token provided.');
        }

        $token = str_replace('Bearer ', '', $auth_header);
        $decoded = JWT::decode($token);

        if (!$decoded) {
            Response::unauthorized('Invalid or expired token.');
        }

        $data = json_decode(file_get_contents("php://input"), true);

        // Validate required fields
        $validator = new Validator();
        if (!$validator->validate($data ?? [], [
            'current_password' => 'required',
            'new_password' => 'required|min:8'
        ])) {
            Response::badRequest($validator->getFirstError());
        }

        $current_password = $data['current_password'];
        $new_password = $data['new_password'];

        // Validate new password strength
        if (!Helper::validatePassword($new_password)) {
            Response::badRequest('Password must be at least 8 characters with uppercase, lowercase, and number');
        }

        // Get current user
        $user = new User();
        $user->id = $decoded->id;
        $user_data = $user->findById();

        if (!$user_data) {
            Response::notFound('User not found.');
        }

        // Verify current password
        if (!Helper::verifyPassword($current_password, $user_data['password'])) {
            Response::badRequest('Current password is incorrect.');
        }

        // Update password
        $hashed_password = Helper::hashPassword($new_password);
        $user->updatePassword($decoded->id, $hashed_password);

        Response::success(['message' => 'Password changed successfully.']);
    }
}
