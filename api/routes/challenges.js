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
    const [challenges] = await db.query('SELECT * FROM challenges ORDER BY created_at DESC');
    res.json({ status: 'success', data: { challenges: challenges.rows } });
  } catch (error) {
    console.error('Get challenges error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting challenges' });
  }
});

// Join challenge
router.post('/:id/join', authenticate, async (req, res) => {
  try {
    await db.query('INSERT INTO challenge_participants (challenge_id, user_id) VALUES ($1, $2)', [req.params.id, req.user.id]);
    res.json({ status: 'success', message: 'Joined challenge' });
  } catch (error) {
    console.error('Join challenge error:', error);
    res.status(500).json({ status: 'error', message: 'Error joining challenge' });
  }
});

module.exports = router;