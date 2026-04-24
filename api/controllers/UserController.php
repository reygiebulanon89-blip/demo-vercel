<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Helper.php';

class UserController {
    public function getProfile($id = null) {
        if ($id === null) {
            $decoded = AuthMiddleware::verify();
            $id = $decoded['id'];
        }

        $user = new User();
        $user_data = $user->findById($id);

        if (!$user_data) {
            Response::notFound('User not found');
        }

        Response::success($user_data, 'User found');
    }

    public function updateProfile() {
        $decoded = AuthMiddleware::verify();
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $data = null;

        // Support both JSON and multipart/form-data (for profile picture uploads)
        if (stripos($contentType, 'application/json') !== false) {
            $data = json_decode(file_get_contents("php://input"), true);
            if (!$data) {
                Response::badRequest('Invalid JSON data');
            }
        } else {
            $data = $_POST ?? [];
        }

        $user = new User();
        $user->id = $decoded['id'];
        $user->username = Helper::sanitize($data['username'] ?? '');
        $user->bio = Helper::sanitize($data['bio'] ?? '');
        $user->profile_pic = isset($data['profile_pic']) ? Helper::sanitize($data['profile_pic']) : null;

        // Handle uploaded file (multipart/form-data)
        if (isset($_FILES['profile_pic_file']) && is_array($_FILES['profile_pic_file'])) {
            $file = $_FILES['profile_pic_file'];

            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                Response::badRequest('Upload failed');
            }

            $maxBytes = 2 * 1024 * 1024; // 2MB
            if (($file['size'] ?? 0) > $maxBytes) {
                Response::badRequest('Profile picture must be 2MB or less');
            }

            $tmpPath = $file['tmp_name'] ?? '';
            if (!$tmpPath || !is_uploaded_file($tmpPath)) {
                Response::badRequest('Invalid upload');
            }

            $imageInfo = @getimagesize($tmpPath);
            if (!$imageInfo) {
                Response::badRequest('Profile picture must be a valid image');
            }

            $mime = $imageInfo['mime'] ?? '';
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];
            if (!isset($allowed[$mime])) {
                Response::badRequest('Unsupported image type (use JPG, PNG, WEBP, or GIF)');
            }

            $ext = $allowed[$mime];
            $uploadDir = __DIR__ . '/../uploads/profile_pics';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }
            if (!is_dir($uploadDir)) {
                Response::serverError('Could not create upload directory');
            }

            $filename = 'user_' . (int)$decoded['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destPath = $uploadDir . '/' . $filename;

            if (!move_uploaded_file($tmpPath, $destPath)) {
                Response::serverError('Could not save uploaded file');
            }

            // Store a web path that the frontend can use directly.
            $user->profile_pic = '/wasteless/api/uploads/profile_pics/' . $filename;
        }

        if ($user->updateProfile()) {
            $updated_user = $user->findById($decoded['id']);
            Response::success($updated_user, 'Profile updated successfully');
        }

        Response::serverError('Error updating profile');
    }

    public function getAllUsers() {
        $limit = $_GET['limit'] ?? 50;

        $user = new User();
        $users = $user->getAllUsers($limit);

        Response::success($users, 'Users retrieved');
    }

    public function follow($following_id) {
        $decoded = AuthMiddleware::verify();

        $user = new User();
        if ($user->follow($decoded['id'], $following_id)) {
            Response::success([], 'Followed successfully');
        }

        Response::serverError('Error following user');
    }

    public function unfollow($following_id) {
        $decoded = AuthMiddleware::verify();

        $user = new User();
        if ($user->unfollow($decoded['id'], $following_id)) {
            Response::success([], 'Unfollowed successfully');
        }

        Response::serverError('Error unfollowing user');
    }

    public function getFollowers($user_id) {
        $user = new User();
        $followers = $user->getFollowers($user_id);

        Response::success($followers, 'Followers retrieved');
    }

    public function getFollowing($user_id) {
        $user = new User();
        $following = $user->getFollowing($user_id);

        Response::success($following, 'Following retrieved');
    }
}
