const { UserQueries, MessageQueries } = require('../config/database');
const { RedisService } = require('../config/redis');

class ChatHandler {
  constructor(io, socket) {
    this.io = io;
    this.socket = socket;
    this.userId = socket.userId;
    this.userInfo = socket.userInfo;
  }

  // Handle joining a room (public chat or private)
  async handleJoinRoom(data) {
    try {
      const { roomId, isPrivate = false } = data;
      
      if (isPrivate) {
        // Private chat between two users
        await this.socket.join(`private_${roomId}`);
        
        // Get chat history
        const messages = await MessageQueries.getPrivateMessages(
          this.userId, 
          roomId, 
          50
        );
        
        this.socket.emit('chat_history', {
          roomId,
          messages: this.formatMessages(messages)
        });
        
        console.log(`User ${this.userId} joined private room with ${roomId}`);
        
      } else {
        // Public room
        await this.socket.join(roomId);
        
        // Get recent messages
        const messages = await MessageQueries.getPublicMessages(50);
        
        this.socket.emit('chat_history', {
          roomId,
          messages: this.formatMessages(messages)
        });
        
        console.log(`User ${this.userId} joined public room: ${roomId}`);
      }
      
      // Notify others in room
      this.socket.to(roomId).emit('user_joined', {
        userId: this.userId,
        userName: this.userInfo.name,
        roomId
      });
      
    } catch (error) {
      console.error('Join room error:', error);
      this.socket.emit('error', { message: 'Failed to join room' });
    }
  }

  // Format messages for client (including media)
  formatMessages(messages) {
    return messages.map(msg => ({
      id: msg.id,
      content: msg.message,
      senderId: msg.sender_id,
      senderName: msg.sender_name,
      senderAvatar: msg.profile_image,
      timestamp: msg.created_at,
      messageType: msg.message_type || 'text',
      mediaUrl: msg.media_url || null,
      mediaThumbnail: msg.media_thumbnail || null,
      mediaSize: msg.media_size || null,
      mediaName: msg.media_name || null
    }));
  }

  // Handle sending text messages
  async handleSendMessage(data) {
    try {
      const { roomId, content, isPrivate = false } = data;
      
      if (!content || content.trim() === '') {
        return this.socket.emit('error', { message: 'Message cannot be empty' });
      }
      
      // Save message to database (text message)
      const messageId = await MessageQueries.create(
        this.userId, 
        isPrivate ? roomId : null, 
        content.trim()
      );
      
      // Prepare message object
      const messageData = {
        id: messageId,
        content: content.trim(),
        senderId: this.userId,
        senderName: this.userInfo.name,
        senderAvatar: this.userInfo.profile_image,
        timestamp: new Date().toISOString(),
        messageType: 'text',
        roomId
      };
      
      // Broadcast to room
      const roomName = isPrivate ? `private_${roomId}` : roomId;
      this.io.to(roomName).emit('new_message', messageData);
      
      console.log(`Message sent in ${isPrivate ? 'private' : 'public'} room ${roomId}`);
      
    } catch (error) {
      console.error('Send message error:', error);
      this.socket.emit('error', { message: 'Failed to send message' });
    }
  }

  // Handle sending media messages (image or video)
  async handleSendMediaMessage(data) {
    try {
      const { roomId, mediaData, caption = '', isPrivate = false } = data;
      
      if (!mediaData || !mediaData.url) {
        return this.socket.emit('error', { message: 'Media data is required' });
      }
      
      // Validate media type
      const validTypes = ['image', 'video'];
      if (!validTypes.includes(mediaData.type)) {
        return this.socket.emit('error', { message: 'Invalid media type' });
      }
      
      // Save media message to database
      const messageId = await MessageQueries.createMediaMessage(
        this.userId,
        isPrivate ? roomId : null,
        mediaData,
        caption.trim()
      );
      
      // Prepare message object
      const messageData = {
        id: messageId,
        content: caption.trim(),
        senderId: this.userId,
        senderName: this.userInfo.name,
        senderAvatar: this.userInfo.profile_image,
        timestamp: new Date().toISOString(),
        messageType: mediaData.type,
        mediaUrl: mediaData.url,
        mediaThumbnail: mediaData.thumbnail || null,
        mediaSize: mediaData.size || null,
        mediaName: mediaData.name || null,
        roomId
      };
      
      // Broadcast to room
      const roomName = isPrivate ? `private_${roomId}` : roomId;
      this.io.to(roomName).emit('new_message', messageData);
      
      // Also emit media_upload_ack to sender
      this.socket.emit('media_upload_ack', {
        messageId,
        status: 'success'
      });
      
      console.log(`Media message sent in ${isPrivate ? 'private' : 'public'} room ${roomId}`);
      
    } catch (error) {
      console.error('Send media message error:', error);
      this.socket.emit('error', { message: 'Failed to send media message' });
      this.socket.emit('media_upload_ack', {
        messageId: null,
        status: 'error',
        error: error.message
      });
    }
  }

  // Handle typing indicators
  async handleTyping(data) {
    try {
      const { roomId, isTyping, isPrivate = false } = data;
      
      const roomName = isPrivate ? `private_${roomId}` : roomId;
      
      // Update typing status in Redis
      await RedisService.setTyping(this.userId, roomName, isTyping);
      
      if (isTyping) {
        // Broadcast typing start
        this.socket.to(roomName).emit('user_typing', {
          userId: this.userId,
          userName: this.userInfo.name,
          roomId: roomName
        });
      } else {
        // Broadcast typing stop
        this.socket.to(roomName).emit('user_stop_typing', {
          userId: this.userId,
          userName: this.userInfo.name,
          roomId: roomName
        });
      }
      
    } catch (error) {
      console.error('Typing indicator error:', error);
    }
  }

  // Handle leaving a room
  async handleLeaveRoom(data) {
    try {
      const { roomId, isPrivate = false } = data;
      const roomName = isPrivate ? `private_${roomId}` : roomId;
      
      await this.socket.leave(roomName);
      
      // Notify others
      this.socket.to(roomName).emit('user_left', {
        userId: this.userId,
        userName: this.userInfo.name,
        roomId
      });
      
      console.log(`User ${this.userId} left room ${roomName}`);
      
    } catch (error) {
      console.error('Leave room error:', error);
    }
  }

  // Handle getting online friends
  async handleGetFriends() {
    try {
      const friends = await UserQueries.getFriends(this.userId);
      const onlineUsers = await RedisService.getOnlineUsers();
      
      // Add online status to friends
      const friendsWithStatus = friends.map(friend => ({
        ...friend,
        isOnline: onlineUsers.some(user => user.id == friend.id)
      }));
      
      this.socket.emit('friends_list', friendsWithStatus);
      
    } catch (error) {
      console.error('Get friends error:', error);
      this.socket.emit('error', { message: 'Failed to load friends' });
    }
  }

  // Handle disconnect
  async handleDisconnect() {
    try {
      console.log(`User ${this.userId} disconnected`);
      
      // Set user as offline in Redis
      await RedisService.setUserOffline(this.userId);
      
      // Leave all rooms and notify others
      const rooms = Array.from(this.socket.rooms);
      rooms.forEach(room => {
        if (room !== this.socket.id) {
          this.socket.to(room).emit('user_disconnected', {
            userId: this.userId,
            userName: this.userInfo.name,
            roomId: room
          });
        }
      });
      
    } catch (error) {
      console.error('Disconnect error:', error);
    }
  }
}

module.exports = ChatHandler;