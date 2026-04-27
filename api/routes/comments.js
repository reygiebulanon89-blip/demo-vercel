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

// Update comment
router.put('/:id', authenticate, async (req, res) => {
  try {
    const { comment } = req.body;
    const result = await db.query(
      'UPDATE comments SET content = $1 WHERE id = $2 AND user_id = $3 RETURNING *',
      [comment, req.params.id, req.user.id]
    );
    if (result.rows.length === 0) {
      return res.status(404).json({ status: 'error', message: 'Comment not found or unauthorized' });
    }
    res.json({ status: 'success', data: result.rows[0] });
  } catch (error) {
    console.error('Update comment error:', error);
    res.status(500).json({ status: 'error', message: 'Error updating comment' });
  }
});

// Delete comment
router.delete('/:id', authenticate, async (req, res) => {
  try {
    await db.query('DELETE FROM comments WHERE id = $1 AND user_id = $2', [req.params.id, req.user.id]);
    res.json({ status: 'success', message: 'Comment deleted' });
  } catch (error) {
    console.error('Delete comment error:', error);
    res.status(500).json({ status: 'error', message: 'Error deleting comment' });
  }
});

module.exports = router;