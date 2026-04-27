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

// Get all posts
router.get('/', async (req, res) => {
  try {
    const limit = parseInt(req.query.limit) || 20;
    const offset = parseInt(req.query.offset) || 0;
    const posts = await db.query(`
      SELECT p.*, u.username, u.profile_pic 
      FROM posts p 
      JOIN users u ON p.user_id = u.id 
      ORDER BY p.created_at DESC 
      LIMIT $1 OFFSET $2
    `, [limit, offset]);
    res.json({ status: 'success', data: { posts: posts.rows } });
  } catch (error) {
    console.error('Get posts error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting posts' });
  }
});

// Create post
router.post('/', authenticate, async (req, res) => {
  try {
    const { content, image } = req.body;
    const result = await db.query(
      'INSERT INTO posts (user_id, content, image_url) VALUES ($1, $2, $3) RETURNING *',
      [req.user.id, content, image || null]
    );
    res.status(201).json({ status: 'success', data: result.rows[0] });
  } catch (error) {
    console.error('Create post error:', error);
    res.status(500).json({ status: 'error', message: 'Error creating post' });
  }
});

// Get post by ID
router.get('/:id', async (req, res) => {
  try {
    const posts = await db.query(`
      SELECT p.*, u.username, u.profile_pic 
      FROM posts p 
      JOIN users u ON p.user_id = u.id 
      WHERE p.id = $1
    `, [req.params.id]);
    if (posts.rows.length === 0) {
      return res.status(404).json({ status: 'error', message: 'Post not found' });
    }
    res.json({ status: 'success', data: posts.rows[0] });
  } catch (error) {
    console.error('Get post error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting post' });
  }
});

// Update post
router.put('/:id', authenticate, async (req, res) => {
  try {
    const { content } = req.body;
    const result = await db.query(
      'UPDATE posts SET content = $1 WHERE id = $2 AND user_id = $3 RETURNING *',
      [content, req.params.id, req.user.id]
    );
    if (result.rows.length === 0) {
      return res.status(404).json({ status: 'error', message: 'Post not found or unauthorized' });
    }
    res.json({ status: 'success', data: result.rows[0] });
  } catch (error) {
    console.error('Update post error:', error);
    res.status(500).json({ status: 'error', message: 'Error updating post' });
  }
});

// Delete post
router.delete('/:id', authenticate, async (req, res) => {
  try {
    await db.query('DELETE FROM posts WHERE id = $1 AND user_id = $2', [req.params.id, req.user.id]);
    res.json({ status: 'success', message: 'Post deleted' });
  } catch (error) {
    console.error('Delete post error:', error);
    res.status(500).json({ status: 'error', message: 'Error deleting post' });
  }
});

// Like post
router.post('/:id/like', authenticate, async (req, res) => {
  try {
    // Create a like (you might want a separate likes table)
    await db.query(
      'INSERT INTO notifications (user_id, type, message) VALUES ($1, $2, $3)',
      [req.user.id, 'like', `User liked your post`]
    );
    res.json({ status: 'success', message: 'Post liked' });
  } catch (error) {
    console.error('Like post error:', error);
    res.status(500).json({ status: 'error', message: 'Error liking post' });
  }
});

// Unlike post
router.delete('/:id/like', authenticate, async (req, res) => {
  res.json({ status: 'success', message: 'Post unliked' });
});

// Get comments for a post
router.get('/:id/comments', async (req, res) => {
  try {
    const limit = parseInt(req.query.limit) || 20;
    const offset = parseInt(req.query.offset) || 0;
    const comments = await db.query(`
      SELECT c.*, u.username, u.profile_pic 
      FROM comments c 
      JOIN users u ON c.user_id = u.id 
      WHERE c.post_id = $1
      ORDER BY c.created_at DESC 
      LIMIT $2 OFFSET $3
    `, [req.params.id, limit, offset]);
    res.json({ status: 'success', data: { comments: comments.rows } });
  } catch (error) {
    console.error('Get comments error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting comments' });
  }
});

// Create comment on a post
router.post('/:id/comments', authenticate, async (req, res) => {
  try {
    const { comment, parent_comment_id } = req.body;
    const result = await db.query(
      'INSERT INTO comments (post_id, user_id, content) VALUES ($1, $2, $3) RETURNING *',
      [req.params.id, req.user.id, comment]
    );
    res.status(201).json({ status: 'success', data: result.rows[0] });
  } catch (error) {
    console.error('Create comment error:', error);
    res.status(500).json({ status: 'error', message: 'Error creating comment' });
  }
});

module.exports = router;