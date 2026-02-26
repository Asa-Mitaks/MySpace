/**
 * MySpace WebSocket Client
 * Handles real-time communication with WebSocket server
 */

class MySpaceWebSocket {
  constructor() {
    this.socket = null;
    this.userId = null;
    this.userInfo = null;
    this.currentRoom = null;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = 5;
    this.reconnectDelay = 1000;
    
    // Event callbacks
    this.callbacks = {
      onMessage: null,
      onUserJoined: null,
      onUserLeft: null,
      onTyping: null,
      onStopTyping: null,
      onUserOnline: null,
      onUserOffline: null,
      onFriendsList: null,
      onOnlineUsers: null,
      onError: null,
      onConnected: null,
      onDisconnected: null
    };
  }

  /**
   * Initialize WebSocket connection
   * @param {string} token - JWT authentication token
   */
  connect(token) {
    if (!token) {
      console.error('WebSocket: Authentication token required');
      return false;
    }

    // Check if Socket.IO is loaded
    if (typeof io === 'undefined') {
      console.error('WebSocket: Socket.IO not loaded');
      return false;
    }

    this.socket = io('ws://localhost:3002', {
      auth: { token },
      transports: ['websocket', 'polling'],
      timeout: 20000,
      forceNew: true
    });

    this.setupEventHandlers();
    return true;
  }

  /**
   * Setup Socket.IO event handlers
   */
  setupEventHandlers() {
    if (!this.socket) return;

    // Connection events
    this.socket.on('connect', () => {
      console.log('WebSocket connected');
      this.reconnectAttempts = 0;
      this.reconnectDelay = 1000;
      
      if (this.callbacks.onConnected) {
        this.callbacks.onConnected();
      }
    });

    this.socket.on('disconnect', (reason) => {
      console.log('WebSocket disconnected:', reason);
      
      if (this.callbacks.onDisconnected) {
        this.callbacks.onDisconnected(reason);
      }
      
      this.handleReconnect();
    });

    this.socket.on('connect_error', (error) => {
      console.error('WebSocket connection error:', error);
      
      if (this.callbacks.onError) {
        this.callbacks.onError(error);
      }
      
      this.handleReconnect();
    });

    // Chat events
    this.socket.on('chat_history', (data) => {
      this.handleChatHistory(data);
    });

    this.socket.on('new_message', (message) => {
      if (this.callbacks.onMessage) {
        this.callbacks.onMessage(message);
      }
    });

    this.socket.on('user_joined', (data) => {
      if (this.callbacks.onUserJoined) {
        this.callbacks.onUserJoined(data);
      }
    });

    this.socket.on('user_left', (data) => {
      if (this.callbacks.onUserLeft) {
        this.callbacks.onUserLeft(data);
      }
    });

    this.socket.on('user_typing', (data) => {
      if (this.callbacks.onTyping) {
        this.callbacks.onTyping(data);
      }
    });

    this.socket.on('user_stop_typing', (data) => {
      if (this.callbacks.onStopTyping) {
        this.callbacks.onStopTyping(data);
      }
    });

    this.socket.on('user_online', (data) => {
      if (this.callbacks.onUserOnline) {
        this.callbacks.onUserOnline(data);
      }
    });

    this.socket.on('user_offline', (data) => {
      if (this.callbacks.onUserOffline) {
        this.callbacks.onUserOffline(data);
      }
    });

    this.socket.on('friends_list', (friends) => {
      if (this.callbacks.onFriendsList) {
        this.callbacks.onFriendsList(friends);
      }
    });

    this.socket.on('online_users', (users) => {
      if (this.callbacks.onOnlineUsers) {
        this.callbacks.onOnlineUsers(users);
      }
    });

    this.socket.on('error', (error) => {
      console.error('WebSocket error:', error);
      
      if (this.callbacks.onError) {
        this.callbacks.onError(error);
      }
    });
  }

  /**
   * Handle chat history received from server
   */
  handleChatHistory(data) {
    const { roomId, messages } = data;
    
    // Display messages in UI
    messages.forEach(message => {
      this.displayMessage(message);
    });
    
    // Scroll to bottom
    this.scrollToBottom();
  }

  /**
   * Join a chat room
   * @param {string|number} roomId 
   * @param {boolean} isPrivate 
   */
  joinRoom(roomId, isPrivate = false) {
    if (!this.socket || !this.socket.connected) {
      console.error('WebSocket: Not connected');
      return false;
    }

    this.currentRoom = roomId;
    this.socket.emit('join_room', {
      roomId,
      isPrivate
    });
    
    return true;
  }

  /**
   * Send a message
   * @param {string} content 
   * @param {string|number} roomId 
   * @param {boolean} isPrivate 
   */
  sendMessage(content, roomId, isPrivate = false) {
    if (!this.socket || !this.socket.connected) {
      console.error('WebSocket: Not connected');
      return false;
    }

    if (!content || content.trim() === '') {
      console.error('WebSocket: Message cannot be empty');
      return false;
    }

    this.socket.emit('send_message', {
      content: content.trim(),
      roomId,
      isPrivate
    });
    
    return true;
  }

  /**
   * Set typing indicator
   * @param {boolean} isTyping 
   * @param {string|number} roomId 
   * @param {boolean} isPrivate 
   */
  setTyping(isTyping, roomId, isPrivate = false) {
    if (!this.socket || !this.socket.connected) return;

    this.socket.emit('typing', {
      isTyping,
      roomId,
      isPrivate
    });
  }

  /**
   * Leave current room
   */
  leaveRoom() {
    if (!this.socket || !this.socket.connected || !this.currentRoom) {
      return;
    }

    this.socket.emit('leave_room', {
      roomId: this.currentRoom
    });
    
    this.currentRoom = null;
  }

  /**
   * Get friends list
   */
  getFriends() {
    if (!this.socket || !this.socket.connected) return;
    
    this.socket.emit('get_friends');
  }

  /**
   * Get online users
   */
  getOnlineUsers() {
    if (!this.socket || !this.socket.connected) return;
    
    this.socket.emit('get_online_users');
  }

  /**
   * Handle automatic reconnection
   */
  handleReconnect() {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      console.error('WebSocket: Max reconnection attempts reached');
      return;
    }

    this.reconnectAttempts++;
    this.reconnectDelay *= 2; // Exponential backoff
    
    console.log(`WebSocket: Reconnecting in ${this.reconnectDelay}ms (attempt ${this.reconnectAttempts})`);
    
    setTimeout(() => {
      if (this.socket) {
        this.socket.connect();
      }
    }, this.reconnectDelay);
  }

  /**
   * Display message in chat UI
   * @param {Object} message 
   */
  displayMessage(message) {
    const messagesContainer = document.getElementById('chatMessages');
    if (!messagesContainer) return;

    const messageElement = document.createElement('div');
    messageElement.className = `chat-message ${message.senderId == this.userId ? 'own-message' : ''}`;
    
    messageElement.innerHTML = `
      <div class="message-avatar">
        ${message.senderAvatar ? 
          `<img src="${message.senderAvatar}" alt="Avatar">` : 
          message.senderName ? message.senderName.charAt(0).toUpperCase() : '?'
        }
      </div>
      <div class="message-content">
        <div class="message-header">
          <span class="message-author">${message.senderName || 'Unknown'}</span>
          <span class="message-time">${new Date(message.timestamp).toLocaleTimeString()}</span>
        </div>
        <div class="message-text">${this.escapeHtml(message.content)}</div>
      </div>
    `;

    messagesContainer.appendChild(messageElement);
  }

  /**
   * Show typing indicator
   * @param {Object} data 
   */
  showTypingIndicator(data) {
    const messagesContainer = document.getElementById('chatMessages');
    if (!messagesContainer) return;

    // Remove existing typing indicator
    const existing = document.getElementById('typing-indicator');
    if (existing) existing.remove();

    const typingElement = document.createElement('div');
    typingElement.id = 'typing-indicator';
    typingElement.className = 'typing-indicator';
    typingElement.innerHTML = `
      <div class="typing-avatar">${data.userName.charAt(0).toUpperCase()}</div>
      <div class="typing-content">
        <span>${data.userName} está digitando</span>
        <div class="typing-dots">
          <span></span>
          <span></span>
          <span></span>
        </div>
      </div>
    `;

    messagesContainer.appendChild(typingElement);
    this.scrollToBottom();
  }

  /**
   * Hide typing indicator
   */
  hideTypingIndicator() {
    const indicator = document.getElementById('typing-indicator');
    if (indicator) {
      indicator.remove();
    }
  }

  /**
   * Scroll chat to bottom
   */
  scrollToBottom() {
    const messagesContainer = document.getElementById('chatMessages');
    if (messagesContainer) {
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
  }

  /**
   * Escape HTML to prevent XSS
   * @param {string} text 
   */
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Disconnect from WebSocket
   */
  disconnect() {
    if (this.socket) {
      this.socket.disconnect();
      this.socket = null;
    }
  }

  /**
   * Set event callbacks
   * @param {Object} callbacks 
   */
  on(callbacks) {
    this.callbacks = { ...this.callbacks, ...callbacks };
  }
}

// Global instance
window.mySpaceWS = new MySpaceWebSocket();