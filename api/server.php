<?php

// HANDLE PREFLIGHT FIRST
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit;
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Get request method and path
$request_method = $_SERVER['REQUEST_METHOD'];
$request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// REMOVE /api/ prefix
$request_path = preg_replace('#^/api/#', '', $request_path);

// Clean slashes
$request_path = trim($request_path, '/');

// Parse URL segments
$segments = explode('/', $request_path);

// Route handlers
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/FeedController.php';
require_once __DIR__ . '/controllers/NotificationController.php';
require_once __DIR__ . '/controllers/ChallengeController.php';
require_once __DIR__ . '/controllers/TrackController.php';
require_once __DIR__ . '/utils/Response.php';

// Routes
try {
    // Auth routes
    if ($request_path === 'auth/register' && $request_method === 'POST') {
        $controller = new AuthController();
        $controller->register();
    } 
    elseif ($request_path === 'auth/login' && $request_method === 'POST') {
        $controller = new AuthController();
        $controller->login();
    }
    elseif ($request_path === 'auth/forgot-password' && $request_method === 'POST') {
        $controller = new AuthController();
        $controller->forgotPassword();
    }
    elseif ($request_path === 'auth/reset-password' && $request_method === 'POST') {
        $controller = new AuthController();
        $controller->resetPassword();
    }
    elseif ($request_path === 'auth/logout' && $request_method === 'POST') {
        $controller = new AuthController();
        $controller->logout();
    }
    elseif ($request_path === 'auth/change-password' && $request_method === 'POST') {
        $controller = new AuthController();
        $controller->changePassword();
    }
    // User routes
    elseif ($request_path === 'users' && $request_method === 'GET') {
        $controller = new UserController();
        $controller->getAllUsers();
    }
    elseif (preg_match('/^users\/(\d+)$/', $request_path, $matches) && $request_method === 'GET') {
        $controller = new UserController();
        $controller->getProfile($matches[1]);
    }
    elseif ($request_path === 'users/profile' && $request_method === 'GET') {
        $controller = new UserController();
        $controller->getProfile();
    }
    // NOTE: PHP handles multipart/form-data uploads reliably with POST, not PUT.
    // We support POST here so profile updates with image uploads work.
    elseif ($request_path === 'users/profile' && $request_method === 'POST') {
        $controller = new UserController();
        $controller->updateProfile();
    }
    elseif ($request_path === 'users/profile' && $request_method === 'PUT') {
        $controller = new UserController();
        $controller->updateProfile();
    }
    elseif (preg_match('/^users\/follow\/(\d+)$/', $request_path, $matches) && $request_method === 'POST') {
        $controller = new UserController();
        $controller->follow($matches[1]);
    }
    elseif (preg_match('/^users\/unfollow\/(\d+)$/', $request_path, $matches) && $request_method === 'DELETE') {
        $controller = new UserController();
        $controller->unfollow($matches[1]);
    }
    elseif (preg_match('/^users\/followers\/(\d+)$/', $request_path, $matches) && $request_method === 'GET') {
        $controller = new UserController();
        $controller->getFollowers($matches[1]);
    }
    elseif (preg_match('/^users\/following\/(\d+)$/', $request_path, $matches) && $request_method === 'GET') {
        $controller = new UserController();
        $controller->getFollowing($matches[1]);
    }
    // Feed routes
    elseif ($request_path === 'feed' && $request_method === 'GET') {
        $controller = new FeedController();
        $controller->getFeed();
    }
    elseif ($request_path === 'posts' && $request_method === 'POST') {
        $controller = new FeedController();
        $controller->createPost();
    }
    elseif (preg_match('/^posts\/(\d+)$/', $request_path, $matches) && $request_method === 'GET') {
        $controller = new FeedController();
        $controller->getPost($matches[1]);
    }
    elseif (preg_match('/^posts\/(\d+)$/', $request_path, $matches) && $request_method === 'PUT') {
        $controller = new FeedController();
        $controller->updatePost($matches[1]);
    }
    elseif (preg_match('/^posts\/(\d+)$/', $request_path, $matches) && $request_method === 'DELETE') {
        $controller = new FeedController();
        $controller->deletePost($matches[1]);
    }
    elseif (preg_match('/^users\/(\d+)\/posts$/', $request_path, $matches) && $request_method === 'GET') {
        $controller = new FeedController();
        $controller->getUserPosts($matches[1]);
    }
    elseif (preg_match('/^posts\/(\d+)\/like$/', $request_path, $matches) && $request_method === 'POST') {
        $controller = new FeedController();
        $controller->likePost($matches[1]);
    }
    elseif (preg_match('/^posts\/(\d+)\/unlike$/', $request_path, $matches) && $request_method === 'DELETE') {
        $controller = new FeedController();
        $controller->unlikePost($matches[1]);
    }
    elseif (preg_match('/^posts\/(\d+)\/comments$/', $request_path, $matches) && $request_method === 'GET') {
        $controller = new FeedController();
        $controller->getComments($matches[1]);
    }
    elseif (preg_match('/^posts\/(\d+)\/comments$/', $request_path, $matches) && $request_method === 'POST') {
        $controller = new FeedController();
        $controller->createComment($matches[1]);
    }
    elseif (preg_match('/^comments\/(\d+)$/', $request_path, $matches) && $request_method === 'DELETE') {
        $controller = new FeedController();
        $controller->deleteComment($matches[1]);
    }
    elseif (preg_match('/^comments\/(\d+)$/', $request_path, $matches) && $request_method === 'PUT') {
        $controller = new FeedController();
        $controller->updateComment($matches[1]);
    }
    elseif ($request_path === 'notifications' && $request_method === 'GET') {
        $controller = new NotificationController();
        $controller->getNotifications();
    }
    elseif ($request_path === 'notifications/read' && $request_method === 'POST') {
        $controller = new NotificationController();
        $controller->markAllRead();
    }
    elseif (preg_match('/^notifications\/(\d+)$/', $request_path, $matches) && $request_method === 'DELETE') {
        $controller = new NotificationController();
        $controller->deleteNotification($matches[1]);
    }
    // Challenge routes
    elseif ($request_path === 'challenges' && $request_method === 'GET') {
        $controller = new ChallengeController();
        $controller->getAllChallenges();
    }
    elseif ($request_path === 'challenges' && $request_method === 'POST') {
        $controller = new ChallengeController();
        $controller->createChallenge();
    }
    elseif (preg_match('/^challenges\/(\d+)$/', $request_path, $matches) && $request_method === 'GET') {
        $controller = new ChallengeController();
        $controller->getChallenge($matches[1]);
    }
    elseif (preg_match('/^challenges\/(\d+)\/join$/', $request_path, $matches) && $request_method === 'POST') {
        $controller = new ChallengeController();
        $controller->joinChallenge($matches[1]);
    }
    elseif (preg_match('/^challenges\/(\d+)\/leave$/', $request_path, $matches) && $request_method === 'DELETE') {
        $controller = new ChallengeController();
        $controller->leaveChallenge($matches[1]);
    }
    elseif (preg_match('/^challenges\/(\d+)\/progress$/', $request_path, $matches) && $request_method === 'PUT') {
        $controller = new ChallengeController();
        $controller->updateProgress($matches[1]);
    }
    elseif ($request_path === 'user/challenges' && $request_method === 'GET') {
        $controller = new ChallengeController();
        $controller->getUserChallenges();
    }
    // Tracking routes
    elseif ($request_path === 'tracking/log' && $request_method === 'POST') {
        $controller = new TrackController();
        $controller->logWaste();
    }
    elseif ($request_path === 'tracking/logs' && $request_method === 'GET') {
        $controller = new TrackController();
        $controller->getUserTracking();
    }
    elseif ($request_path === 'tracking/stats' && $request_method === 'GET') {
        $controller = new TrackController();
        $controller->getUserStats();
    }
    elseif ($request_path === 'tracking/breakdown' && $request_method === 'GET') {
        $controller = new TrackController();
        $controller->getWasteByType();
    }
    elseif ($request_path === 'tracking/global-stats' && $request_method === 'GET') {
        $controller = new TrackController();
        $controller->getGlobalStats();
    }
    elseif (preg_match('/^tracking\/logs\/(\d+)$/', $request_path, $matches) && $request_method === 'DELETE') {
        $controller = new TrackController();
        $controller->deleteLog($matches[1]);
    }
    else {
        Response::notFound('Route not found');
    }
} catch (Exception $e) {
    Response::serverError('Server error: ' . $e->getMessage());
}
