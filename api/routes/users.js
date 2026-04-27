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
    res.json({ status: 'success', data: users.rows[0] });
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

// Update profile with file upload (POST for multipart/form-data)
router.post('/profile', authenticate, async (req, res) => {
  try {
    const { username, bio, profile_pic, profile_pic_data } = req.body;
    
    let profilePicPath = profile_pic || null;
    
    // If base64 image data is provided, store it directly
    if (profile_pic_data) {
      // Validate base64 data URL format
      if (profile_pic_data.startsWith('data:image/')) {
        profilePicPath = profile_pic_data;
      }
    }
    
    await db.query('UPDATE users SET username = $1, bio = $2, profile_pic = $3 WHERE id = $4', 
      [username, bio, profilePicPath, req.user.id]);
    
    // Get updated user
    const users = await db.query('SELECT id, username, email, bio, profile_pic, created_at FROM users WHERE id = $1', [req.user.id]);
    
    res.json({ status: 'success', data: users.rows[0], message: 'Profile updated successfully' });
  } catch (error) {
    console.error('Update profile error:', error);
    res.status(500).json({ status: 'error', message: 'Error updating profile' });
  }
});

// Get all users
router.get('/', async (req, res) => {
  try {
    const limit = parseInt(req.query.limit) || 50;
    const users = await db.query('SELECT id, username, bio, profile_pic, created_at FROM users LIMIT $1', [limit]);
    res.json({ status: 'success', data: { users: users.rows } });
  } catch (error) {
    console.error('Get users error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting users' });
  }
});

// Follow a user
router.post('/follow/:id', authenticate, async (req, res) => {
  try {
    const targetUserId = req.params.id;
    if (targetUserId == req.user.id) {
      return res.status(400).json({ status: 'error', message: 'Cannot follow yourself' });
    }
    // Create notification for the followed user
    await db.query(
      'INSERT INTO notifications (user_id, type, message) VALUES ($1, $2, $3)',
      [targetUserId, 'follow', `User is now following you`]
    );
    res.json({ status: 'success', message: 'Now following user' });
  } catch (error) {
    console.error('Follow user error:', error);
    res.status(500).json({ status: 'error', message: 'Error following user' });
  }
});

// Unfollow a user
router.delete('/follow/:id', authenticate, async (req, res) => {
  res.json({ status: 'success', message: 'Unfollowed user' });
});

// Get user posts
router.get('/:id/posts', async (req, res) => {
  try {
    const limit = parseInt(req.query.limit) || 20;
    const offset = parseInt(req.query.offset) || 0;
    const posts = await db.query(`
      SELECT p.*, u.username, u.profile_pic 
      FROM posts p 
      JOIN users u ON p.user_id = u.id 
      WHERE p.user_id = $1
      ORDER BY p.created_at DESC 
      LIMIT $2 OFFSET $3
    `, [req.params.id, limit, offset]);
    res.json({ status: 'success', data: { posts: posts.rows } });
  } catch (error) {
    console.error('Get user posts error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting user posts' });
  }
});

module.exports = router;