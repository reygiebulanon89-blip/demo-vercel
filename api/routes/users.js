const express = require('express');
const router = express.Router();
const db = require('../config/database');
const jwt = require('jsonwebtoken');
const multer = require('multer');
const path = require('path');
const fs = require('fs');

// Configure multer for file uploads
const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    const uploadDir = path.join(__dirname, '../uploads/profile_pics');
    if (!fs.existsSync(uploadDir)) {
      fs.mkdirSync(uploadDir, { recursive: true });
    }
    cb(null, uploadDir);
  },
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase();
    cb(null, 'user_' + req.user.id + '_' + Date.now() + ext);
  }
});

const upload = multer({
  storage,
  limits: { fileSize: 2 * 1024 * 1024 }, // 2MB
  fileFilter: (req, file, cb) => {
    const allowed = /jpeg|jpg|png|webp|gif/;
    const ext = allowed.test(path.extname(file.originalname).toLowerCase());
    const mime = allowed.test(file.mimetype);
    if (ext && mime) {
      cb(null, true);
    } else {
      cb(new Error('Only image files are allowed (JPG, PNG, WEBP, GIF)'));
    }
  }
});

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
router.post('/profile', authenticate, upload.single('profile_pic_file'), async (req, res) => {
  try {
    const { username, bio, profile_pic } = req.body;
    
    let profilePicPath = profile_pic || null;
    
    // If a file was uploaded, set the path
    if (req.file) {
      profilePicPath = '/api/uploads/profile_pics/' + req.file.filename;
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

// Error handling middleware for multer
router.use((err, req, res, next) => {
  if (err instanceof multer.MulterError) {
    if (err.code === 'LIMIT_FILE_SIZE') {
      return res.status(400).json({ status: 'error', message: 'Profile picture must be 2MB or less' });
    }
    return res.status(400).json({ status: 'error', message: err.message });
  } else if (err) {
    return res.status(400).json({ status: 'error', message: err.message });
  }
  next();
});

module.exports = router;