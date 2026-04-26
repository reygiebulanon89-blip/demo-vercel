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

// Get feed posts
router.get('/', async (req, res) => {
  try {
    const [posts] = await db.query(`
      SELECT p.*, u.username, u.profile_pic 
      FROM posts p 
      JOIN users u ON p.user_id = u.id 
      ORDER BY p.created_at DESC 
      LIMIT 50
    `);
    res.json({ status: 'success', data: { posts: posts.rows } });
  } catch (error) {
    console.error('Get feed error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting feed' });
  }
});

// Create post
router.post('/', authenticate, async (req, res) => {
  try {
    const { content, image_url } = req.body;
    const [result] = await db.query(
      'INSERT INTO posts (user_id, content, image_url) VALUES ($1, $2, $3) RETURNING id',
      [req.user.id, content, image_url || null]
    );
    res.status(201).json({ status: 'success', message: 'Post created', data: { id: result.rows[0].id } });
  } catch (error) {
    console.error('Create post error:', error);
    res.status(500).json({ status: 'error', message: 'Error creating post' });
  }
});

module.exports = router;