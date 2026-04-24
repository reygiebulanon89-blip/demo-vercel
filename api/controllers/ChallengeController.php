<?php

require_once __DIR__ . '/../models/Challenge.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Helper.php';
require_once __DIR__ . '/../utils/Validator.php';

class ChallengeController {
    public function getAllChallenges() {
        $limit = $_GET['limit'] ?? 20;
        $offset = $_GET['offset'] ?? 0;

        Helper::paginationValidate($limit, $offset);

        $challenge = new Challenge();
        $challenges = $challenge->getAllChallenges($limit, $offset);

        Response::success($challenges, 'Challenges retrieved');
    }

    public function getChallenge($id) {
        $id = (int)$id;
        
        if ($id <= 0) {
            Response::badRequest('Invalid challenge ID');
        }

        $challenge = new Challenge();
        $challenge_data = $challenge->getChallengeById($id);

        if (!$challenge_data) {
            Response::notFound('Challenge not found');
        }

        Response::success($challenge_data, 'Challenge retrieved');
    }

    public function createChallenge() {
        $decoded = AuthMiddleware::verify();
        $data = json_decode(file_get_contents("php://input"), true);

        // Validate input
        $validator = new Validator();
        if (!$validator->validate($data ?? [], [
            'title' => 'required|string|min:3|max:100',
            'description' => 'required|string|min:10|max:500',
            'target' => 'numeric',
            'target_unit' => 'in:kg,lbs,units',
            'start_date' => 'string',
            'end_date' => 'string'
        ])) {
            Response::badRequest($validator->getFirstError());
        }

        $challenge = new Challenge();
        $challenge->title = Helper::sanitize($data['title']);
        $challenge->description = Helper::sanitize($data['description']);
        $challenge->created_by = $decoded['id'];
        $challenge->target = isset($data['target']) ? (float)$data['target'] : 100;
        $challenge->target_unit = Helper::sanitize($data['target_unit'] ?? 'kg');
        $challenge->start_date = $data['start_date'] ?? date('Y-m-d');
        $challenge->end_date = $data['end_date'] ?? date('Y-m-d', strtotime('+30 days'));

        // Validate dates
        if (strtotime($challenge->start_date) > strtotime($challenge->end_date)) {
            Response::badRequest('Start date must be before end date');
        }

        $challenge_id = $challenge->create();

        if ($challenge_id) {
            $challenge_data = $challenge->getChallengeById($challenge_id);
            Response::created($challenge_data, 'Challenge created successfully');
        }

        Response::serverError('Error creating challenge');
    }

    public function updateChallenge($id) {
        $decoded = AuthMiddleware::verify();
        $id = (int)$id;
        
        if ($id <= 0) {
            Response::badRequest('Invalid challenge ID');
        }

        $data = json_decode(file_get_contents("php://input"), true);

        $challenge = new Challenge();
        
        // Check if challenge exists
        $challenge_data = $challenge->getChallengeById($id);
        if (!$challenge_data) {
            Response::notFound('Challenge not found');
        }

        // Check authorization (only creator can update)
        if (!$challenge->isCreator($id, $decoded['id'])) {
            Response::forbidden('You can only update challenges you created');
        }

        // Validate input
        if (isset($data['title'])) {
            $data['title'] = Helper::sanitize($data['title']);
            if (strlen($data['title']) < 3) {
                Response::badRequest('Title must be at least 3 characters');
            }
        }

        if (isset($data['description'])) {
            $data['description'] = Helper::sanitize($data['description']);
            if (strlen($data['description']) < 10) {
                Response::badRequest('Description must be at least 10 characters');
            }
        }

        if ($challenge->updateChallenge($id, $decoded['id'], $data)) {
            $updated_challenge = $challenge->getChallengeById($id);
            Response::success($updated_challenge, 'Challenge updated successfully');
        }

        Response::serverError('Error updating challenge');
    }

    public function deleteChallenge($id) {
        $decoded = AuthMiddleware::verify();
        $id = (int)$id;
        
        if ($id <= 0) {
            Response::badRequest('Invalid challenge ID');
        }

        $challenge = new Challenge();
        
        // Check if challenge exists
        $challenge_data = $challenge->getChallengeById($id);
        if (!$challenge_data) {
            Response::notFound('Challenge not found');
        }

        // Check authorization (only creator can delete)
        if (!$challenge->isCreator($id, $decoded['id'])) {
            Response::forbidden('You can only delete challenges you created');
        }

        if ($challenge->deleteChallenge($id, $decoded['id'])) {
            Response::success([], 'Challenge deleted successfully');
        }

        Response::serverError('Error deleting challenge');
    }

    public function joinChallenge($id) {
        $decoded = AuthMiddleware::verify();
        $id = (int)$id;
        
        if ($id <= 0) {
            Response::badRequest('Invalid challenge ID');
        }

        $challenge = new Challenge();
        
        // Check if challenge exists
        if (!$challenge->getChallengeById($id)) {
            Response::notFound('Challenge not found');
        }
        
        if ($challenge->isUserInChallenge($id, $decoded['id'])) {
            Response::error('Already joined this challenge', 400);
        }

        if ($challenge->joinChallenge($id, $decoded['id'])) {
            Response::success([], 'Joined challenge successfully');
        }

        Response::serverError('Error joining challenge');
    }

    public function leaveChallenge($id) {
        $decoded = AuthMiddleware::verify();
        $id = (int)$id;
        
        if ($id <= 0) {
            Response::badRequest('Invalid challenge ID');
        }

        $challenge = new Challenge();
        if ($challenge->leaveChallenge($id, $decoded['id'])) {
            Response::success([], 'Left challenge successfully');
        }

        Response::serverError('Error leaving challenge');
    }

    public function updateProgress($id) {
        $decoded = AuthMiddleware::verify();
        $id = (int)$id;
        
        if ($id <= 0) {
            Response::badRequest('Invalid challenge ID');
        }

        $data = json_decode(file_get_contents("php://input"), true);

        // Validate input
        $validator = new Validator();
        if (!$validator->validate($data ?? [], [
            'progress' => 'required|numeric'
        ])) {
            Response::badRequest($validator->getFirstError());
        }

        $progress = (float)$data['progress'];
        if ($progress < 0) {
            Response::badRequest('Progress cannot be negative');
        }

        $challenge = new Challenge();
        if ($challenge->updateProgress($id, $decoded['id'], $progress)) {
            Response::success([], 'Progress updated successfully');
        }

        Response::serverError('Error updating progress');
    }

    public function getUserChallenges() {
        $decoded = AuthMiddleware::verify();
        $limit = $_GET['limit'] ?? 20;
        $offset = $_GET['offset'] ?? 0;

        Helper::paginationValidate($limit, $offset);

        $challenge = new Challenge();
        $challenges = $challenge->getUserChallenges($decoded['id'], $limit, $offset);

        Response::success($challenges, 'User challenges retrieved');
    }
}
