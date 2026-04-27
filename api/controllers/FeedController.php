<?php

require_once __DIR__ . '/../models/Post.php';
require_once __DIR__ . '/../models/Comment.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Helper.php';
require_once __DIR__ . '/../utils/Validator.php';

class FeedController {
    public function getFeed() {
        $decoded = AuthMiddleware::verifyOptional();
        $limit = $_GET['limit'] ?? 20;
        $offset = $_GET['offset'] ?? 0;

        Helper::paginationValidate($limit, $offset);

        $post = new Post();
        $viewer_user_id = $decoded['id'] ?? null;
        $posts = $post->getFeed($limit, $offset, $viewer_user_id);

        Response::success($posts, 'Feed retrieved');
    }

    public function createPost() {
        $decoded = AuthMiddleware::verify();
        $data = json_decode(file_get_contents("php://input"), true);

        // Validate input
        $validator = new Validator();
        if (!$validator->validate($data ?? [], [
            'content' => 'required|string|min:1|max:5000'
        ])) {
            Response::badRequest($validator->getFirstError());
        }

        $content = Helper::sanitize($data['content']);

        $post = new Post();
        $post->user_id = $decoded['id'];
        $post->content = $content;
        $post->image = isset($data['image']) ? Helper::sanitize($data['image']) : null;

        $post_id = $post->create();

        if ($post_id) {
            $post_data = $post->getPostById($post_id);
            Response::created($post_data, 'Post created successfully');
        }

        Response::serverError('Error creating post');
    }

    public function getPost($id) {
        $id = (int)$id;
        
        if ($id <= 0) {
            Response::badRequest('Invalid post ID');
        }

        $post = new Post();
        $decoded = AuthMiddleware::verifyOptional();
        $viewer_user_id = $decoded['id'] ?? null;
        $post_data = $post->getPostById($id, $viewer_user_id);

        if (!$post_data) {
            Response::notFound('Post not found');
        }

        Response::success($post_data, 'Post retrieved');
    }

    public function getUserPosts($user_id) {
        $user_id = (int)$user_id;
        
        if ($user_id <= 0) {
            Response::badRequest('Invalid user ID');
        }

        $limit = $_GET['limit'] ?? 20;
        $offset = $_GET['offset'] ?? 0;

        Helper::paginationValidate($limit, $offset);

        $post = new Post();
        $decoded = AuthMiddleware::verifyOptional();
        $viewer_user_id = $decoded['id'] ?? null;
        $posts = $post->getUserPosts($user_id, $limit, $offset, $viewer_user_id);

        Response::success($posts, 'User posts retrieved');
    }

    public function updatePost($id) {
        $decoded = AuthMiddleware::verify();
        $id = (int)$id;
        
        if ($id <= 0) {
            Response::badRequest('Invalid post ID');
        }

        $data = json_decode(file_get_contents("php://input"), true);

        // Validate input
        $validator = new Validator();
        if (!$validator->validate($data ?? [], [
            'content' => 'required|string|min:1|max:5000'
        ])) {
            Response::badRequest($validator->getFirstError());
        }

        $content = Helper::sanitize($data['content']);

        $post = new Post();
        if ($post->updatePost($id, $decoded['id'], $content)) {
            $post_data = $post->getPostById($id);
            Response::success($post_data, 'Post updated successfully');
        }

        Response::serverError('Error updating post');
    }

    public function deletePost($id) {
        $decoded = AuthMiddleware::verify();
        $id = (int)$id;
        
        if ($id <= 0) {
            Response::badRequest('Invalid post ID');
        }

        $post = new Post();
        if ($post->deletePost($id, $decoded['id'])) {
            Response::success([], 'Post deleted successfully');
        }

        Response::serverError('Error deleting post');
    }

    public function likePost($id) {
        $decoded = AuthMiddleware::verify();
        $id = (int)$id;
        
        if ($id <= 0) {
            Response::badRequest('Invalid post ID');
        }

        $post = new Post();
        if ($post->likePost($id, $decoded['id'])) {
            $post_owner_id = $post->getPostOwnerId($id);
            if ($post_owner_id && (int)$post_owner_id !== (int)$decoded['id']) {
                try {
                    // Get actor username for notification
                    $userModel = new User();
                    $actor = $userModel->findById($decoded['id']);
                    $actorUsername = $actor ? $actor['username'] : 'Someone';
                    
                    $notification = new Notification();
                    $notification->create(
                        (int)$post_owner_id,
                        (int)$decoded['id'],
                        (int)$id,
                        'like',
                        $actorUsername . ' liked your post'
                    );
                } catch (Throwable $e) {
                    error_log('Notification create failed (like): ' . $e->getMessage());
                }
            }
            Response::success([], 'Post liked');
        }

        Response::serverError('Error liking post');
    }

    public function unlikePost($id) {
        $decoded = AuthMiddleware::verify();
        $id = (int)$id;
        
        if ($id <= 0) {
            Response::badRequest('Invalid post ID');
        }

        $post = new Post();
        if ($post->unlikePost($id, $decoded['id'])) {
            Response::success([], 'Post unliked');
        }

        Response::serverError('Error unliking post');
    }

    public function createComment($post_id) {
        $decoded = AuthMiddleware::verify();
        $post_id = (int)$post_id;
        
        if ($post_id <= 0) {
            Response::badRequest('Invalid post ID');
        }

        $data = json_decode(file_get_contents("php://input"), true);

        // Validate input
        $validator = new Validator();
        if (!$validator->validate($data ?? [], [
            'comment' => 'required|string|min:1|max:1000'
        ])) {
            Response::badRequest($validator->getFirstError());
        }

        $comment_text = Helper::sanitize($data['comment']);
        $parent_comment_id = isset($data['parent_comment_id']) ? (int)$data['parent_comment_id'] : null;
        if ($parent_comment_id !== null && $parent_comment_id <= 0) {
            Response::badRequest('Invalid parent_comment_id');
        }

        $comment = new Comment();
        if ($parent_comment_id !== null) {
            $parent_comment = $comment->getCommentById($parent_comment_id);
            if (!$parent_comment || (int)$parent_comment['post_id'] !== $post_id) {
                Response::badRequest('Parent comment not found');
            }
        }

        $comment->post_id = $post_id;
        $comment->user_id = $decoded['id'];
        $comment->comment = $comment_text;
        $comment->parent_comment_id = $parent_comment_id;

        $comment_id = $comment->create();

        if ($comment_id) {
            // Get actor username for notifications
            $userModel = new User();
            $actor = $userModel->findById($decoded['id']);
            $actorUsername = $actor ? $actor['username'] : 'Someone';
            
            $post = new Post();
            $post_owner_id = $post->getPostOwnerId($post_id);
            if ($post_owner_id && (int)$post_owner_id !== (int)$decoded['id']) {
                try {
                    $notification = new Notification();
                    $comment_preview = substr($comment_text, 0, 120);
                    if (strlen($comment_text) > 120) {
                        $comment_preview .= '...';
                    }
                    $notification->create(
                        (int)$post_owner_id,
                        (int)$decoded['id'],
                        (int)$post_id,
                        'comment',
                        $actorUsername . ' commented: "' . $comment_preview . '"'
                    );
                } catch (Throwable $e) {
                    error_log('Notification create failed (comment): ' . $e->getMessage());
                }
            }

            if ($parent_comment_id) {
                $parent_comment = $comment->getCommentById($parent_comment_id);
                if ($parent_comment && (int)$parent_comment['post_id'] === (int)$post_id) {
                    $parent_comment_owner_id = (int)$parent_comment['user_id'];
                    if ($parent_comment_owner_id !== (int)$decoded['id']) {
                        try {
                            $notification = new Notification();
                            $comment_preview = substr($comment_text, 0, 120);
                            if (strlen($comment_text) > 120) {
                                $comment_preview .= '...';
                            }
                            $notification->create(
                                $parent_comment_owner_id,
                                (int)$decoded['id'],
                                (int)$post_id,
                                'reply',
                                $actorUsername . ' replied: "' . $comment_preview . '"'
                            );
                        } catch (Throwable $e) {
                            error_log('Notification create failed (reply): ' . $e->getMessage());
                        }
                    }
                }
            }

            $created_comment = $comment->getCommentById((int)$comment_id);
            Response::created($created_comment ?: [], 'Comment created successfully');
        }

        Response::serverError('Error creating comment');
    }

    public function getComments($post_id) {
        $post_id = (int)$post_id;
        
        if ($post_id <= 0) {
            Response::badRequest('Invalid post ID');
        }

        $limit = $_GET['limit'] ?? 20;
        $offset = $_GET['offset'] ?? 0;

        Helper::paginationValidate($limit, $offset);

        $comment = new Comment();
        $comments = $comment->getComments($post_id, $limit, $offset);

        Response::success($comments, 'Comments retrieved');
    }

    public function deleteComment($id) {
        $decoded = AuthMiddleware::verify();
        $id = (int)$id;
        
        if ($id <= 0) {
            Response::badRequest('Invalid comment ID');
        }

        $comment = new Comment();
        if ($comment->deleteComment($id, $decoded['id'])) {
            Response::success([], 'Comment deleted successfully');
        }

        Response::serverError('Error deleting comment');
    }

    public function updateComment($id) {
        $decoded = AuthMiddleware::verify();
        $id = (int)$id;
        
        if ($id <= 0) {
            Response::badRequest('Invalid comment ID');
        }

        $data = json_decode(file_get_contents("php://input"), true);

        // Validate input
        $validator = new Validator();
        if (!$validator->validate($data ?? [], [
            'comment' => 'required|string|min:1|max:1000'
        ])) {
            Response::badRequest($validator->getFirstError());
        }

        $comment_text = Helper::sanitize($data['comment']);

        $comment = new Comment();
        if ($comment->updateComment($id, $decoded['id'], $comment_text)) {
            Response::success([], 'Comment updated successfully');
        }

        Response::serverError('Error updating comment');
    }
}
