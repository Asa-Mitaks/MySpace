const jwt = require('jsonwebtoken');
const config = require('../config');

// JWT authentication middleware for Socket.IO
const authenticateSocket = async (socket, next) => {
  try {
    // Get token from handshake auth
    const token = socket.handshake.auth.token || socket.handshake.query.token;
    
    if (!token) {
      return next(new Error('Authentication token required'));
    }
    
    // Verify JWT token
    const decoded = jwt.verify(token, config.JWT_SECRET);
    
    // Attach user info to socket
    socket.userId = decoded.userId;
    socket.userInfo = decoded.userInfo;
    
    console.log(`User ${decoded.userId} authenticated successfully`);
    next();
    
  } catch (error) {
    console.error('Socket authentication error:', error.message);
    next(new Error('Invalid authentication token'));
  }
};

// Function to generate JWT for WebSocket connections
const generateWebSocketToken = (userId, userInfo) => {
  return jwt.sign(
    { 
      userId, 
      userInfo,
      type: 'websocket',
      timestamp: Date.now()
    },
    config.JWT_SECRET,
    { expiresIn: config.JWT_EXPIRES_IN }
  );
};

// Middleware to validate user session with PHP
const validatePHPSession = async (userId, sessionId) => {
  // This would typically validate against PHP session storage
  // For now, we'll implement basic validation
  try {
    // In a real implementation, you might:
    // 1. Check Redis for session validity
    // 2. Query PHP session storage
    // 3. Validate session timestamp
    
    const sessionKey = `php_session:${userId}:${sessionId}`;
    // Implement session validation logic here
    return true; // Placeholder
  } catch (error) {
    console.error('Session validation error:', error);
    return false;
  }
};

module.exports = {
  authenticateSocket,
  generateWebSocketToken,
  validatePHPSession
};