const express = require('express');
const router = express.Router();
const db = require('../config/database');
const jwt = require('jsonwebtoken');

const authenticate = (req, res, next) => {
  const authHeader = req.headers.authorization;
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return res.status(401).json({ status: 'error', message: 'No token provided' });
  }
  const token = authHeader.split(' ')[1];
  try {
    const decoded = jwt.verify(token, process.env.JWT_SECRET || 'your-secret-key');
    req.user = decoded;
    next();
  } catch (error) {
    return res.status(401).json({ status: 'error', message: 'Invalid token' });
  }
};

// Get all challenges
router.get('/', async (req, res) => {
  try {
    const limit = parseInt(req.query.limit) || 20;
    const offset = parseInt(req.query.offset) || 0;
    const challenges = await db.query('SELECT id, title, description, start_date, end_date, goal as target, \'units\' as target_unit, created_at FROM challenges ORDER BY created_at DESC LIMIT $1 OFFSET $2', [limit, offset]);
    res.json({ status: 'success', data: challenges.rows });
  } catch (error) {
    console.error('Get challenges error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting challenges' });
  }
});

// Get challenge by ID
router.get('/:id', async (req, res) => {
  try {
    const challenges = await db.query('SELECT id, title, description, start_date, end_date, goal as target, \'units\' as target_unit, created_at FROM challenges WHERE id = $1', [req.params.id]);
    if (challenges.rows.length === 0) {
      return res.status(404).json({ status: 'error', message: 'Challenge not found' });
    }
    res.json({ status: 'success', data: challenges.rows[0] });
  } catch (error) {
    console.error('Get challenge error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting challenge' });
  }
});

// Create challenge (admin or auto-seed)
router.post('/', authenticate, async (req, res) => {
  try {
    const { title, description, target, target_unit, start_date, end_date } = req.body;
    const result = await db.query(
      'INSERT INTO challenges (title, description, goal, start_date, end_date) VALUES ($1, $2, $3, $4, $5) RETURNING id, title, description, start_date, end_date, goal as target, \'units\' as target_unit, created_at',
      [title, description, target || 1, start_date || null, end_date || null]
    );
    res.status(201).json({ status: 'success', data: result.rows[0] });
  } catch (error) {
    console.error('Create challenge error:', error);
    res.status(500).json({ status: 'error', message: 'Error creating challenge' });
  }
});

// Join challenge
router.post('/:id/join', authenticate, async (req, res) => {
  try {
    // Check if already joined
    let participant = await db.query(
      'SELECT * FROM challenge_participants WHERE challenge_id = $1 AND user_id = $2',
      [req.params.id, req.user.id]
    );
    if (participant.rows.length === 0) {
      await db.query(
        'INSERT INTO challenge_participants (challenge_id, user_id) VALUES ($1, $2)',
        [req.params.id, req.user.id]
      );
      participant = await db.query(
        'SELECT * FROM challenge_participants WHERE challenge_id = $1 AND user_id = $2',
        [req.params.id, req.user.id]
      );
    }

    // Get challenge details
    const challenge = await db.query('SELECT id, title, description, start_date, end_date, goal as target, \'units\' as target_unit, created_at FROM challenges WHERE id = $1', [req.params.id]);
    if (challenge.rows.length === 0) {
      return res.status(404).json({ status: 'error', message: 'Challenge not found' });
    }

    // Merge participant and challenge info
    const joined = { ...participant.rows[0], ...challenge.rows[0] };
    res.json({ status: 'success', data: joined, message: 'Joined challenge' });
  } catch (error) {
    console.error('Join challenge error:', error);
    res.status(500).json({ status: 'error', message: 'Error joining challenge' });
  }
});

// Leave challenge
router.delete('/:id/leave', authenticate, async (req, res) => {
  try {
    await db.query(
      'DELETE FROM challenge_participants WHERE challenge_id = $1 AND user_id = $2',
      [req.params.id, req.user.id]
    );
    res.json({ status: 'success', message: 'Left challenge' });
  } catch (error) {
    console.error('Leave challenge error:', error);
    res.status(500).json({ status: 'error', message: 'Error leaving challenge' });
  }
});

// Update challenge progress
router.put('/:id/progress', authenticate, async (req, res) => {
  try {
    const { progress } = req.body;
    
    // Update or insert progress
    const existing = await db.query(
      'SELECT * FROM challenge_participants WHERE challenge_id = $1 AND user_id = $2',
      [req.params.id, req.user.id]
    );
    
    if (existing.rows.length > 0) {
      await db.query(
        'UPDATE challenge_participants SET progress = $1 WHERE challenge_id = $2 AND user_id = $3',
        [progress, req.params.id, req.user.id]
      );
    } else {
      await db.query(
        'INSERT INTO challenge_participants (challenge_id, user_id, progress) VALUES ($1, $2, $3)',
        [req.params.id, req.user.id, progress]
      );
    }
    
    res.json({ status: 'success', message: 'Progress updated' });
  } catch (error) {
    console.error('Update progress error:', error);
    res.status(500).json({ status: 'error', message: 'Error updating progress: ' + error.message });
  }
});

// Get user challenges (challenges user has joined)
router.get('/user/challenges', authenticate, async (req, res) => {
  try {
    const limit = parseInt(req.query.limit) || 50;
    const offset = parseInt(req.query.offset) || 0;
    
    const result = await db.query(
      `SELECT cp.*, c.title, c.description, c.goal as target, 'units' as target_unit, c.start_date, c.end_date 
       FROM challenge_participants cp
       JOIN challenges c ON cp.challenge_id = c.id
       WHERE cp.user_id = $1
       ORDER BY cp.id DESC
       LIMIT $2 OFFSET $3`,
      [req.user.id, limit, offset]
    );
    
    res.json({ status: 'success', data: result.rows });
  } catch (error) {
    console.error('Get user challenges error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting user challenges: ' + error.message });
  }
});

module.exports = router;