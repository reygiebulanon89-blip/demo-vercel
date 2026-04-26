const { Pool } = require('pg');

let connectionString = process.env.DATABASE_URL;

// If no DATABASE_URL, build from individual values
if (!connectionString) {
  const { DB_HOST, DB_USER, DB_PASS, DB_NAME } = process.env;
  connectionString = `postgresql://${DB_USER || 'root'}:${DB_PASS || ''}@${DB_HOST || 'localhost'}:5432/${DB_NAME || 'wasteless'}`;
}

const pool = new Pool({
  connectionString,
  ssl: process.env.NODE_ENV === 'production' ? { rejectUnauthorized: false } : false
});

module.exports = pool;