const redis = require('redis');
const config = require('./config');

// Create Redis client
const redisClient = redis.createClient({
  host: config.REDIS_HOST,
  port: config.REDIS_PORT,
  password: config.REDIS_PASSWORD || undefined,
  retry_delay_on_failover: 100,
  enable_offline_queue: false
});

// Handle Redis connection events
redisClient.on('connect', () => {
  console.log('Connected to Redis successfully');
});

redisClient.on('error', (err) => {
  console.error('Redis connection error:', err);
});

redisClient.on('ready', () => {
  console.log('Redis client ready');
});

// Connect to Redis
redisClient.connect().catch(console.error);

// Redis helper functions
const RedisService = {
  // Set user as online
  setUserOnline: async (userId, socketId, userInfo) => {
    const userKey = `user:${userId}`;
    const onlineKey = 'online_users';
    
    // Store user info with socket ID
    await redisClient.hSet(userKey, {
      socketId,
      ...userInfo,
      lastSeen: Date.now()
    });
    
    // Add to online users set
    await redisClient.sAdd(onlineKey, userId);
    
    // Set expiry (24 hours)
    await redisClient.expire(userKey, 86400);
    await redisClient.expire(onlineKey, 86400);
  },
  
  // Set user as offline
  setUserOffline: async (userId) => {
    const userKey = `user:${userId}`;
    const onlineKey = 'online_users';
    
    // Remove from online users
    await redisClient.sRem(onlineKey, userId);
    
    // Remove user data
    await redisClient.del(userKey);
  },
  
  // Get user socket ID
  getUserSocketId: async (userId) => {
    const userKey = `user:${userId}`;
    const userData = await redisClient.hGetAll(userKey);
    return userData ? userData.socketId : null;
  },
  
  // Get all online users
  getOnlineUsers: async () => {
    const onlineKey = 'online_users';
    const userIds = await redisClient.sMembers(onlineKey);
    
    const users = [];
    for (const userId of userIds) {
      const userKey = `user:${userId}`;
      const userData = await redisClient.hGetAll(userKey);
      if (userData) {
        users.push({ id: userId, ...userData });
      }
    }
    
    return users;
  },
  
  // Store typing indicator
  setTyping: async (userId, roomId, isTyping) => {
    const typingKey = `typing:${roomId}`;
    
    if (isTyping) {
      await redisClient.hSet(typingKey, userId, Date.now());
      await redisClient.expire(typingKey, 30); // 30 seconds expiry
    } else {
      await redisClient.hDel(typingKey, userId);
    }
  },
  
  // Get typing users in a room
  getTypingUsers: async (roomId) => {
    const typingKey = `typing:${roomId}`;
    const typingData = await redisClient.hGetAll(typingKey);
    
    return Object.keys(typingData).map(userId => ({
      userId,
      timestamp: parseInt(typingData[userId])
    }));
  },
  
  // Generic set/get operations
  set: async (key, value, expiry = null) => {
    if (expiry) {
      await redisClient.setEx(key, expiry, JSON.stringify(value));
    } else {
      await redisClient.set(key, JSON.stringify(value));
    }
  },
  
  get: async (key) => {
    const value = await redisClient.get(key);
    return value ? JSON.parse(value) : null;
  },
  
  delete: async (key) => {
    await redisClient.del(key);
  }
};

module.exports = {
  redisClient,
  RedisService
};