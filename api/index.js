const express = require('express');
const cors = require('cors');
require('dotenv').config();

const app = express();

// Middleware
app.use(cors());
app.use(express.json());

// Routes
app.use('/auth', require('./routes/auth'));
app.use('/users', require('./routes/users'));
app.use('/feed', require('./routes/feed'));
app.use('/notifications', require('./routes/notifications'));
app.use('/challenges', require('./routes/challenges'));
app.use('/track', require('./routes/track'));

// Health check
app.get('/api/health', (req, res) => {
  res.json({ status: 'ok', message: 'WasteLess API is running' });
});

// Export for Vercel
module.exports = app;