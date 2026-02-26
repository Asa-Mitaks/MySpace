require('dotenv').config();
const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const cors = require('cors');
const config = require('./config/config');
const { authenticateSocket } = require('./middleware/auth');
const { RedisService } = require('./config/redis');
const ChatHandler = require('./handlers/chatHandler');

// Create Express app
const app = express();

// Enable CORS
app.use(cors({
  origin: [config.ALLOWED_ORIGINS],
  methods: ['GET', 'POST'],
  credentials: true
}));

// Create HTTP server
const server = http.createServer(app);

// Create Socket.IO server
const io = new Server(server, {
  cors: {
    origin: config.ALLOWED_ORIGINS,
    methods: ['GET', 'POST'],
    credentials: true
  },
  transports: ['websocket', 'polling'] // Allow fallback to polling
});

// Socket.IO authentication middleware
io.use(authenticateSocket);

// Handle Socket.IO connections
io.on('connection', (socket) => {
  console.log(`New connection: ${socket.id} from user ${socket.userId}`);
  
  // Create chat handler instance for this socket
  const chatHandler = new ChatHandler(io, socket);
  
  // Set user as online
  RedisService.setUserOnline(socket.userId, socket.id, socket.userInfo);
  
  // Notify other users about online status
  socket.broadcast.emit('user_online', {
    userId: socket.userId,
    userName: socket.userInfo.name,
    userInfo: socket.userInfo
  });
  
  // Send online users list to new user
  RedisService.getOnlineUsers().then(onlineUsers => {
    socket.emit('online_users', onlineUsers);
  });
  
  // === Chat Event Handlers ===
  
  // Join a room (public or private)
  socket.on('join_room', (data) => {
    chatHandler.handleJoinRoom(data);
  });
  
  // Send message
  socket.on('send_message', (data) => {
    chatHandler.handleSendMessage(data);
  });
  
  // Typing indicator
  socket.on('typing', (data) => {
    chatHandler.handleTyping(data);
  });
  
  // Leave room
  socket.on('leave_room', (data) => {
    chatHandler.handleLeaveRoom(data);
  });
  
  // Get friends list
  socket.on('get_friends', () => {
    chatHandler.handleGetFriends();
  });
  
  // Get online users
  socket.on('get_online_users', () => {
    RedisService.getOnlineUsers().then(onlineUsers => {
      socket.emit('online_users', onlineUsers);
    });
  });
  
  // Handle disconnect
  socket.on('disconnect', () => {
    chatHandler.handleDisconnect();
  });
  
  // Handle errors
  socket.on('error', (error) => {
    console.error(`Socket error for user ${socket.userId}:`, error);
  });
});

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({ 
    status: 'ok', 
    timestamp: new Date().toISOString(),
    uptime: process.uptime()
  });
});

// WebSocket token validation endpoint (for PHP to validate)
app.post('/validate-token', express.json(), (req, res) => {
  try {
    const { token } = req.body;
    
    if (!token) {
      return res.status(400).json({ valid: false, error: 'Token required' });
    }
    
    const jwt = require('jsonwebtoken');
    const decoded = jwt.verify(token, config.JWT_SECRET);
    
    res.json({ 
      valid: true, 
      userId: decoded.userId,
      userInfo: decoded.userInfo 
    });
    
  } catch (error) {
    res.status(401).json({ 
      valid: false, 
      error: 'Invalid token' 
    });
  }
});

// Start server
server.listen(config.WS_PORT, () => {
  console.log(`🚀 MySpace WebSocket Server running on port ${config.WS_PORT}`);
  console.log(`🔗 WebSocket endpoint: ws://localhost:${config.WS_PORT}`);
  console.log(`🌐 HTTP endpoint: http://localhost:${config.WS_PORT}`);
  console.log(`📊 Health check: http://localhost:${config.WS_PORT}/health`);
});

// Graceful shutdown
process.on('SIGTERM', () => {
  console.log('SIGTERM received, shutting down gracefully');
  server.close(() => {
    console.log('Server closed');
    process.exit(0);
  });
});

process.on('SIGINT', () => {
  console.log('SIGINT received, shutting down gracefully');
  server.close(() => {
    console.log('Server closed');
    process.exit(0);
  });
});

module.exports = { app, io, server };