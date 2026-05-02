const express = require('express');
const router = express.Router();
const jwt = require('jsonwebtoken');
const bcrypt = require('bcryptjs');
const db = require('../config/database');
const nodemailer = require('nodemailer');

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

// Forgot Password
router.post('/forgot-password', async (req, res) => {
  try {
    const { email } = req.body;

    if (!email) {
      return res.status(400).json({ status: 'error', message: 'Email is required' });
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      return res.status(400).json({ status: 'error', message: 'Invalid email format.' });
    }

    // Check if user exists
    const users = await db.query('SELECT * FROM users WHERE email = $1', [email.toLowerCase()]);
    if (users.rows.length === 0) {
      return res.json({ status: 'success', message: 'If the email exists, a reset link will be sent.' });
    }

    // Create password_resets table if not exists
    await db.query(`
      CREATE TABLE IF NOT EXISTS password_resets (
        id SERIAL PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    `);

    // Generate token
    const crypto = require('crypto');
    const token = crypto.randomBytes(32).toString('hex');
    
    const expires = new Date();
    expires.setHours(expires.getHours() + 1);

    // Save token
    await db.query(
      'INSERT INTO password_resets (email, token, expires) VALUES ($1, $2, $3)',
      [email.toLowerCase(), token, expires]
    );

    // Send email using Nodemailer
    const transporter = nodemailer.createTransport({
      service: 'gmail',
      auth: {
        user: process.env.SMTP_EMAIL,
        pass: process.env.SMTP_PASSWORD
      }
    });

    const resetLink = `${req.protocol}://${req.get('host')}/reset-password.html?token=${token}`;

    const mailOptions = {
      from: `"WasteLess" <${process.env.SMTP_EMAIL}>`,
      to: email.toLowerCase(),
      subject: 'WasteLess - Password Reset Request',
      html: `
        <h2>Password Reset Request</h2>
        <p>You requested to reset your password for WasteLess. Click the link below to set a new password:</p>
        <p><a href="${resetLink}">Reset Password</a></p>
        <p>This link will expire in 1 hour. If you did not request this, please ignore this email.</p>
      `
    };

    await transporter.sendMail(mailOptions);

    res.json({
      status: 'success',
      message: 'Password reset link has been sent to your email.'
    });

  } catch (error) {
    console.error('Forgot password error:', error);
    res.status(500).json({ status: 'error', message: 'Error processing request' });
  }
});

// Reset Password
router.post('/reset-password', async (req, res) => {
  try {
    const { token, newPassword } = req.body;

    if (!token || !newPassword) {
      return res.status(400).json({ status: 'error', message: 'Token and new password are required' });
    }

    if (newPassword.length < 8 || !/[A-Z]/.test(newPassword) || !/[a-z]/.test(newPassword) || !/[0-9]/.test(newPassword)) {
      return res.status(400).json({ status: 'error', message: 'Password must be at least 8 characters with uppercase, lowercase, and number' });
    }

    // Verify token
    const result = await db.query(
      'SELECT email FROM password_resets WHERE token = $1 AND expires > NOW()',
      [token]
    );

    if (result.rows.length === 0) {
      return res.status(400).json({ status: 'error', message: 'Invalid or expired reset token' });
    }

    const email = result.rows[0].email;

    // Hash new password
    const hashedPassword = await bcrypt.hash(newPassword, 10);

    // Update user password
    await db.query(
      'UPDATE users SET password = $1 WHERE email = $2',
      [hashedPassword, email]
    );

    // Delete used token
    await db.query('DELETE FROM password_resets WHERE email = $1', [email]);

    res.json({ status: 'success', message: 'Password has been reset successfully' });

  } catch (error) {
    console.error('Reset password error:', error);
    res.status(500).json({ status: 'error', message: 'Error processing request' });
  }
});

module.exports = router;