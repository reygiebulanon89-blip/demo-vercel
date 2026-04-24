<?php

require_once __DIR__ . '/../models/Track.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Helper.php';
require_once __DIR__ . '/../utils/Validator.php';

class TrackController {
    public function logWaste() {
        $decoded = AuthMiddleware::verify();
        $data = json_decode(file_get_contents("php://input"), true);

        // Validate input
        $validator = new Validator();
        if (!$validator->validate($data ?? [], [
            'waste_type' => 'required|string',
            'quantity' => 'required|numeric'
        ])) {
            Response::badRequest($validator->getFirstError());
        }

        $waste_type = Helper::sanitize($data['waste_type']);
        $quantity = (float)$data['quantity'];
        $unit = Helper::sanitize($data['unit'] ?? 'kg');
        $date = isset($data['date']) ? Helper::sanitize($data['date']) : null;

        // Validate waste type
        if (!Helper::isValidWasteType($waste_type)) {
            Response::badRequest('Invalid waste type: ' . implode(', ', array_keys(CO2_FACTORS)));
        }

        // Validate quantity
        if ($quantity <= 0) {
            Response::badRequest('Quantity must be greater than 0');
        }

        // Optional backdated entry support:
        // - date must be YYYY-MM-DD
        // - no future dates
        // - max 7 days back (inclusive)
        $createdAt = null;
        if ($date !== null && $date !== '') {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
            $errors = DateTimeImmutable::getLastErrors();
            $warningCount = (is_array($errors) && isset($errors['warning_count'])) ? (int)$errors['warning_count'] : 0;
            $errorCount = (is_array($errors) && isset($errors['error_count'])) ? (int)$errors['error_count'] : 0;
            if (!$dt || $warningCount > 0 || $errorCount > 0) {
                Response::badRequest('Invalid date format (use YYYY-MM-DD)');
            }

            $today = new DateTimeImmutable('today');
            // Timezone grace: client "today" can be server "tomorrow" near day boundaries.
            $maxAllowed = $today->add(new DateInterval('P1D'));
            if ($dt > $maxAllowed) {
                Response::badRequest('Date cannot be in the future');
            }

            $min = $today->sub(new DateInterval('P7D'));
            if ($dt < $min) {
                Response::badRequest('Date can only be up to 7 days in the past');
            }

            // Store at noon to avoid DST edge cases; streak logic uses DATE(created_at) anyway.
            $createdAt = $dt->format('Y-m-d') . ' 12:00:00';
        }

        // Calculate CO2 savings using configurable factors
        $co2_saved = Helper::calculateCO2Savings($quantity, $waste_type);

        $track = new Track();
        $track->user_id = $decoded['id'];
        $track->waste_type = $waste_type;
        $track->quantity = $quantity;
        $track->unit = $unit;
        $track->co2_saved = $co2_saved;
        if ($createdAt) {
            $track->created_at = $createdAt;
        }

        $log_id = $track->create();

        if ($log_id) {
            Response::created(['id' => $log_id, 'co2_saved' => $co2_saved], 'Waste logged successfully');
        }

        Response::serverError('Error logging waste');
    }

    public function getUserTracking() {
        $decoded = AuthMiddleware::verify();
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;

        Helper::paginationValidate($limit, $offset);

        $track = new Track();
        $logs = $track->getUserTracking($decoded['id'], $limit, $offset);

        Response::success($logs, 'Tracking logs retrieved');
    }

    public function getUserStats() {
        $decoded = AuthMiddleware::verify();

        $track = new Track();
        // Keep legacy fields (total_logs/total_waste/...) while also returning
        // the keys expected by `profile.html`.
        $legacy = $track->getUserStats($decoded['id']) ?: [];
        $profile = $track->getUserProfileStats($decoded['id']) ?: [];
        $stats = array_merge($legacy, $profile);

        Response::success($stats, 'User stats retrieved');
    }

    public function getWasteByType() {
        $decoded = AuthMiddleware::verify();

        $track = new Track();
        $breakdown = $track->getWasteByType($decoded['id']);

        Response::success($breakdown, 'Waste breakdown retrieved');
    }

    public function getGlobalStats() {
        $track = new Track();
        $stats = $track->getGlobalStats();

        Response::success($stats, 'Global stats retrieved');
    }

    public function deleteLog($id) {
        $decoded = AuthMiddleware::verify();
        $id = (int)$id;
        
        if ($id <= 0) {
            Response::badRequest('Invalid log ID');
        }

        $track = new Track();
        if ($track->deleteLog($id, $decoded['id'])) {
            Response::success([], 'Log deleted successfully');
        }

        Response::serverError('Error deleting log');
    }
}
