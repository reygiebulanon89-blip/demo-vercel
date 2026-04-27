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

// Get tracking data
router.get('/', authenticate, async (req, res) => {
  try {
    const tracks = await db.query(
      'SELECT * FROM tracking WHERE user_id = $1 ORDER BY created_at DESC LIMIT 30',
      [req.user.id]
    );
    res.json({ status: 'success', data: { tracking: tracks.rows } });
  } catch (error) {
    console.error('Get tracking error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting tracking data' });
  }
});

// Add tracking entry
router.post('/', authenticate, async (req, res) => {
  try {
    const { food_item, quantity, unit, waste_type, notes } = req.body;
    const result = await db.query(
      'INSERT INTO tracking (user_id, food_item, quantity, unit, waste_type, notes) VALUES ($1, $2, $3, $4, $5, $6) RETURNING id',
      [req.user.id, food_item, quantity, unit, waste_type, notes || '']
    );
    res.status(201).json({ status: 'success', message: 'Tracking entry added', data: { id: result.rows[0].id } });
  } catch (error) {
    console.error('Add tracking error:', error);
    res.status(500).json({ status: 'error', message: 'Error adding tracking entry' });
  }
});

// Get tracking stats
router.get('/stats', authenticate, async (req, res) => {
  try {
    // Get total entries
    const entriesResult = await db.query(
      'SELECT COUNT(*) as total_entries FROM tracking WHERE user_id = $1',
      [req.user.id]
    );
    
    // Get total waste quantity
    const wasteResult = await db.query(
      'SELECT COALESCE(SUM(quantity), 0) as total_waste FROM tracking WHERE user_id = $1',
      [req.user.id]
    );
    
    // Get unique days with entries (for streak calculation)
    const streakResult = await db.query(
      `SELECT COUNT(DISTINCT DATE(created_at)) as days_count 
       FROM tracking 
       WHERE user_id = $1 
       AND created_at >= NOW() - INTERVAL '30 days'`,
      [req.user.id]
    );
    
    // Get challenges completed (placeholder - would need challenge tracking)
    const challengesResult = await db.query(
      `SELECT COUNT(DISTINCT cp.challenge_id) as challenges_completed 
       FROM challenge_participants cp 
       WHERE cp.user_id = $1`,
      [req.user.id]
    );
    
    // Calculate CO2 saved (rough estimate: 2.5kg CO2 per kg of food waste prevented)
    const totalWaste = parseFloat(wasteResult.rows[0].total_waste) || 0;
    const totalCO2Saved = totalWaste * 2.5;
    
    // Calculate current streak (consecutive days with entries in last 30 days)
    const daysResult = await db.query(
      `SELECT DATE(created_at) as entry_date 
       FROM tracking 
       WHERE user_id = $1 
       AND created_at >= NOW() - INTERVAL '30 days'
       GROUP BY DATE(created_at)
       ORDER BY entry_date DESC`,
      [req.user.id]
    );
    
    let currentStreak = 0;
    if (daysResult.rows.length > 0) {
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      
      let checkDate = new Date(today);
      for (const row of daysResult.rows) {
        const entryDate = new Date(row.entry_date);
        entryDate.setHours(0, 0, 0, 0);
        
        const diffDays = Math.floor((checkDate - entryDate) / (1000 * 60 * 60 * 24));
        if (diffDays <= 1) {
          currentStreak++;
          checkDate = entryDate;
        } else {
          break;
        }
      }
    }
    
    res.json({
      status: 'success',
      data: {
        total_entries: parseInt(entriesResult.rows[0].total_entries) || 0,
        total_waste: totalWaste,
        total_co2_saved: totalCO2Saved,
        current_streak: currentStreak,
        challenges_completed: parseInt(challengesResult.rows[0].challenges_completed) || 0
      }
    });
  } catch (error) {
    console.error('Get tracking stats error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting tracking stats' });
  }
});

module.exports = router;