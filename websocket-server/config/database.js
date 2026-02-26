const mysql = require('mysql2/promise');
const config = require('./config');

// Database connection pool
const pool = mysql.createPool({
  host: config.DB_HOST,
  database: config.DB_NAME,
  user: config.DB_USER,
  password: config.DB_PASS,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  enableKeepAlive: true,
  keepAliveInitialDelay: 0
});

// Helper function to execute queries
async function query(sql, params = []) {
  try {
    const [rows] = await pool.execute(sql, params);
    return rows;
  } catch (error) {
    console.error('Database error:', error);
    throw error;
  }
}

// User-related queries
const UserQueries = {
  // Get user by ID
  getById: async (userId) => {
    const rows = await query(
      'SELECT id, name, email, is_admin, profile_image FROM users WHERE id = ?',
      [userId]
    );
    return rows[0] || null;
  },
  
  // Get user friends
  getFriends: async (userId) => {
    return await query(`
      SELECT u.id, u.name, u.profile_image
      FROM friendships f
      JOIN users u ON f.friend_id = u.id
      WHERE f.user_id = ?
      ORDER BY u.name ASC
    `, [userId]);
  },
  
  // Get users for online status
  getAllUsers: async () => {
    return await query(
      'SELECT id, name, profile_image FROM users ORDER BY name ASC'
    );
  }
};

// Message-related queries
const MessageQueries = {
  // Save new message
  create: async (senderId, receiverId, content, roomId = null) => {
    const result = await query(
      'INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())',
      [senderId, receiverId || null, content]
    );
    return result.insertId;
  },
  
  // Get messages between users (private chat)
  getPrivateMessages: async (userId1, userId2, limit = 50) => {
    return await query(`
      SELECT m.*, u.name as sender_name, u.profile_image
      FROM messages m
      JOIN users u ON m.sender_id = u.id
      WHERE (m.sender_id = ? AND m.receiver_id = ?) 
         OR (m.sender_id = ? AND m.receiver_id = ?)
      ORDER BY m.created_at ASC
      LIMIT ?
    `, [userId1, userId2, userId2, userId1, limit]);
  },
  
  // Get public messages (chat room)
  getPublicMessages: async (limit = 50) => {
    return await query(`
      SELECT m.*, u.name as sender_name, u.profile_image
      FROM messages m
      JOIN users u ON m.sender_id = u.id
      WHERE m.receiver_id IS NULL
      ORDER BY m.created_at DESC
      LIMIT ?
    `, [limit]);
  }
};

module.exports = {
  pool,
  query,
  UserQueries,
  MessageQueries
};