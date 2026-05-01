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
    res.json({ status: 'success', data: tracks.rows });
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

// Log waste entry (alternative endpoint)
router.post('/log', authenticate, async (req, res) => {
  try {
    const { waste_type, quantity, unit, date } = req.body;
    const result = await db.query(
      'INSERT INTO tracking (user_id, food_item, waste_type, quantity, unit) VALUES ($1, $2, $3, $4, $5) RETURNING *',
      [req.user.id, waste_type, waste_type, quantity, unit || 'kg']
    );
    res.status(201).json({ status: 'success', data: result.rows[0] });
  } catch (error) {
    console.error('Log waste error:', error);
    res.status(500).json({ status: 'error', message: 'Error logging waste' });
  }
});

// Get tracking logs (alternative endpoint)
router.get('/logs', authenticate, async (req, res) => {
  try {
    const limit = parseInt(req.query.limit) || 50;
    const offset = parseInt(req.query.offset) || 0;
    const tracks = await db.query(
      'SELECT * FROM tracking WHERE user_id = $1 ORDER BY created_at DESC LIMIT $2 OFFSET $3',
      [req.user.id, limit, offset]
    );
    res.json({ status: 'success', data: tracks.rows });
  } catch (error) {
    console.error('Get tracking logs error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting tracking logs' });
  }
});

// Delete tracking log
router.delete('/logs/:id', authenticate, async (req, res) => {
  try {
    await db.query('DELETE FROM tracking WHERE id = $1 AND user_id = $2', [req.params.id, req.user.id]);
    res.json({ status: 'success', message: 'Tracking entry deleted' });
  } catch (error) {
    console.error('Delete tracking log error:', error);
    res.status(500).json({ status: 'error', message: 'Error deleting tracking log' });
  }
});

// Get waste breakdown by type
router.get('/breakdown', authenticate, async (req, res) => {
  try {
    const breakdown = await db.query(`
      SELECT waste_type, SUM(quantity) as total, COUNT(*) as count 
      FROM tracking 
      WHERE user_id = $1 
      GROUP BY waste_type
    `, [req.user.id]);
    res.json({ status: 'success', data: breakdown.rows });
  } catch (error) {
    console.error('Get breakdown error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting breakdown' });
  }
});

// Get global stats
router.get('/global-stats', async (req, res) => {
  try {
    const totalEntries = await db.query('SELECT COUNT(*) as count FROM tracking');
    const totalWaste = await db.query('SELECT COALESCE(SUM(quantity), 0) as total FROM tracking');
    const uniqueUsers = await db.query('SELECT COUNT(DISTINCT user_id) as count FROM tracking');
    
    res.json({
      status: 'success',
      data: {
        total_entries: parseInt(totalEntries.rows[0].count) || 0,
        total_waste: parseFloat(totalWaste.rows[0].total) || 0,
        active_users: parseInt(uniqueUsers.rows[0].count) || 0
      }
    });
  } catch (error) {
    console.error('Get global stats error:', error);
    res.status(500).json({ status: 'error', message: 'Error getting global stats' });
  }
});

module.exports = router;