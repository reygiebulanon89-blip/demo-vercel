const express = require('express');
const router = express.Router();
const jwt = require('jsonwebtoken');
const bcrypt = require('bcryptjs');
const db = require('../config/database');

// Register
router.post('/register', async (req, res) => {
  try {
    const { username, email, password, bio = '' } = req.body;

    // Validate required fields
    if (!username || !email || !password) {
      return res.status(400).json({ status: 'error', message: 'Missing required fields' });
    }

    // Validate username format
    if (!/^[a-zA-Z0-9_]{3,20}$/.test(username)) {
      return res.status(400).json({ status: 'error', message: 'Username must be alphanumeric, 3-20 characters' });
    }

    // Validate email
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      return res.status(400).json({ status: 'error', message: 'Invalid email address' });
    }

    // Validate password strength
    if (password.length < 8 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
      return res.status(400).json({ status: 'error', message: 'Password must be at least 8 characters with uppercase, lowercase, and number' });
    }

    // Check if email already exists
    const existing = await db.query('SELECT id FROM users WHERE email = $1', [email.toLowerCase()]);
    if (existing.rows.length > 0) {
      return res.status(400).json({ status: 'error', message: 'Email is already registered' });
    }

    // Hash password
    const hashedPassword = await bcrypt.hash(password, 10);

    // Insert user
    const result = await db.query(
      'INSERT INTO users (username, email, password, bio) VALUES ($1, $2, $3, $4) RETURNING id',
      [username, email.toLowerCase(), hashedPassword, bio]
    );

    // Generate token
    const token = jwt.sign(
      { id: result.rows[0].id, email },
      process.env.JWT_SECRET || 'your-secret-key',
      { expiresIn: '7d' }
    );

    res.status(201).json({
      status: 'success',
      message: 'User registered successfully',
      data: {
        user: {
          id: result.rows[0].id,
          username,
          email: email.toLowerCase(),
          bio
        },
        token
      }
    });
  } catch (error) {
    console.error('Register error:', error);
    res.status(500).json({ status: 'error', message: 'Error registering user' });
  }
});

// Login
router.post('/login', async (req, res) => {
  try {
    const { email, password } = req.body;

    if (!email || !password) {
      return res.status(400).json({ status: 'error', message: 'Email and password are required' });
    }

    // Find user
    const users = await db.query('SELECT * FROM users WHERE email = $1', [email.toLowerCase()]);
    if (users.rows.length === 0) {
      return res.status(401).json({ status: 'error', message: 'Invalid email or password' });
    }

    const user = users.rows[0];

    // Verify password
    const isValid = await bcrypt.compare(password, user.password);
    if (!isValid) {
      return res.status(401).json({ status: 'error', message: 'Invalid email or password' });
    }

    // Generate token
    const token = jwt.sign(
      { id: user.id, email: user.email },
      process.env.JWT_SECRET || 'your-secret-key',
      { expiresIn: '7d' }
    );

    res.json({
      status: 'success',
      message: 'Login successful',
      data: {
        user: {
          id: user.id,
          username: user.username,
          email: user.email,
          bio: user.bio,
          profile_pic: user.profile_pic
        },
        token
      }
    });
  } catch (error) {
    console.error('Login error:', error);
    res.status(500).json({ status: 'error', message: 'Error logging in' });
  }
});

module.exports = router;