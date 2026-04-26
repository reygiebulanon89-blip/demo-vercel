const express = require('express');
const router = express.Router();
const db = require('../config/database');
const jwt = require('jsonwebtoken');

// Middleware to verify token
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

// Get current user profile
router.get('/profile', authenticate, async (req, res) => {
  try {
    const users = await db.query('SELECT id, username, email, bio, profile_pic, created_at FROM users WHERE id = $1', [req.user.id]);
    if (users.rows.length === 0) {
      return res.status(404).json({ status: 'error', message: 'User not found' });
    }
    res.json({ status: 'success', data: { user: users.rows[0] } });
  } catch (error) {
    console.error('Get profile error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting profile' });
  }
});

// Get user by ID
router.get('/:id', async (req, res) => {
  try {
    const users = await db.query('SELECT id, username, bio, profile_pic, created_at FROM users WHERE id = $1', [req.params.id]);
    if (users.rows.length === 0) {
      return res.status(404).json({ status: 'error', message: 'User not found' });
    }
    res.json({ status: 'success', data: { user: users.rows[0] } });
  } catch (error) {
    console.error('Get user error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting user' });
  }
});

// Update profile
router.put('/profile', authenticate, async (req, res) => {
  try {
    const { username, bio, profile_pic } = req.body;
    await db.query('UPDATE users SET username = $1, bio = $2, profile_pic = $3 WHERE id = $4', [username, bio, profile_pic, req.user.id]);
    res.json({ status: 'success', message: 'Profile updated' });
  } catch (error) {
    console.error('Update profile error:', error);
    res.status(500).json({ status: 'error', message: 'Error updating profile' });
  }
});

module.exports = router;