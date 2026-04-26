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

// Get tracking data
router.get('/', authenticate, async (req, res) => {
  try {
    const [tracks] = await db.query(
      'SELECT * FROM tracking WHERE user_id = $1 ORDER BY created_at DESC LIMIT 30',
      [req.user.id]
    );
    res.json({ status: 'success', data: { tracking: tracks.rows } });
  } catch (error) {
    console.error('Get tracking error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting tracking data' });
  }
});

// Add tracking entry
router.post('/', authenticate, async (req, res) => {
  try {
    const { food_item, quantity, unit, waste_type, notes } = req.body;
    const [result] = await db.query(
      'INSERT INTO tracking (user_id, food_item, quantity, unit, waste_type, notes) VALUES ($1, $2, $3, $4, $5, $6) RETURNING id',
      [req.user.id, food_item, quantity, unit, waste_type, notes || '']
    );
    res.status(201).json({ status: 'success', message: 'Tracking entry added', data: { id: result.rows[0].id } });
  } catch (error) {
    console.error('Add tracking error:', error);
    res.status(500).json({ status: 'error', message: 'Error adding tracking entry' });
  }
});

module.exports = router;