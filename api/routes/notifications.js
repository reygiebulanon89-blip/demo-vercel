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

// Get notifications
router.get('/', authenticate, async (req, res) => {
  try {
    const notifications = await db.query(
      'SELECT * FROM notifications WHERE user_id = $1 ORDER BY created_at DESC LIMIT 50',
      [req.user.id]
    );
    res.json({ status: 'success', data: { notifications: notifications.rows } });
  } catch (error) {
    console.error('Get notifications error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting notifications' });
  }
});

// Mark as read
router.put('/:id/read', authenticate, async (req, res) => {
  try {
    await db.query('UPDATE notifications SET is_read = true WHERE id = $1 AND user_id = $2', [req.params.id, req.user.id]);
    res.json({ status: 'success', message: 'Notification marked as read' });
  } catch (error) {
    console.error('Mark read error:', error);
    res.status(500).json({ status: 'error', message: 'Error marking notification as read' });
  }
});

// Mark all as read
router.post('/read', authenticate, async (req, res) => {
  try {
    await db.query('UPDATE notifications SET is_read = true WHERE user_id = $1', [req.user.id]);
    res.json({ status: 'success', message: 'All notifications marked as read' });
  } catch (error) {
    console.error('Mark all read error:', error);
    res.status(500).json({ status: 'error', message: 'Error marking notifications as read' });
  }
});

// Delete notification
router.delete('/:id', authenticate, async (req, res) => {
  try {
    await db.query('DELETE FROM notifications WHERE id = $1 AND user_id = $2', [req.params.id, req.user.id]);
    res.json({ status: 'success', message: 'Notification deleted' });
  } catch (error) {
    console.error('Delete notification error:', error);
    res.status(500).json({ status: 'error', message: 'Error deleting notification' });
  }
});

module.exports = router;