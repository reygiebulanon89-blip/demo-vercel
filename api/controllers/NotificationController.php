<?php

require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Helper.php';

class NotificationController {
    public function getNotifications() {
        $decoded = AuthMiddleware::verify();
        $limit = $_GET['limit'] ?? 30;
        $after_id = $_GET['after_id'] ?? 0;

        $offset = 0;
        Helper::paginationValidate($limit, $offset);
        $after_id = (int)$after_id;
        if ($after_id < 0) {
            Response::badRequest('Invalid after_id');
        }

        try {
            $notification = new Notification();
            $items = $notification->getForUser((int)$decoded['id'], (int)$limit, $after_id);
            Response::success($items, 'Notifications retrieved');
        } catch (Throwable $e) {
            // Keep feed usable even if migration is not yet applied.
            error_log('Notification fetch failed: ' . $e->getMessage());
            Response::success([], 'Notifications unavailable');
        }
    }

    public function markAllRead() {
        $decoded = AuthMiddleware::verify();
        try {
            $notification = new Notification();
            $notification->markAllRead((int)$decoded['id']);
        } catch (Throwable $e) {
            error_log('Notification markAllRead failed: ' . $e->getMessage());
        }
        Response::success([], 'Notifications marked as read');
    }

    public function deleteNotification($id) {
        $decoded = AuthMiddleware::verify();
        $id = (int)$id;
        if ($id <= 0) {
            Response::badRequest('Invalid notification id');
        }

        try {
            $notification = new Notification();
            $ok = $notification->deleteForUser($id, (int)$decoded['id']);
            if ($ok) {
                Response::success([], 'Notification deleted');
            }
            Response::serverError('Error deleting notification');
        } catch (Throwable $e) {
            error_log('Notification delete failed: ' . $e->getMessage());
            Response::success([], 'Notification deleted');
        }
    }
}
