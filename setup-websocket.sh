#!/bin/bash

# MySpace WebSocket Server Setup Script
# This script helps set up and run the real-time chat server

echo "🚀 MySpace WebSocket Server Setup"
echo "=================================="

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed. Please install Node.js 16+ first."
    echo "   Visit: https://nodejs.org/"
    exit 1
fi

NODE_VERSION=$(node --version | cut -d'v' -f2 | cut -d'.' -f1)
if [ "$NODE_VERSION" -lt 16 ]; then
    echo "❌ Node.js version 16+ required. Current version: $(node --version)"
    exit 1
fi

# Check if Redis is installed
if ! command -v redis-cli &> /dev/null; then
    echo "❌ Redis is not installed. Please install Redis first."
    echo "   Visit: https://redis.io/download"
    exit 1
fi

# Check if Redis is running
if ! redis-cli ping &> /dev/null; then
    echo "⚠️  Redis is not running. Starting Redis..."
    redis-server --daemonize yes --daemonize yes
    sleep 2
    
    if ! redis-cli ping &> /dev/null; then
        echo "❌ Failed to start Redis. Please start it manually:"
        echo "   redis-server"
        exit 1
    else
        echo "✅ Redis started successfully"
    fi
fi

# Navigate to WebSocket server directory
cd websocket-server

# Install dependencies if node_modules doesn't exist
if [ ! -d "node_modules" ]; then
    echo "📦 Installing Node.js dependencies..."
    npm install
    if [ $? -ne 0 ]; then
        echo "❌ Failed to install dependencies"
        exit 1
    fi
    echo "✅ Dependencies installed"
fi

# Create .env file if it doesn't exist
if [ ! -f ".env" ]; then
    echo "📝 Creating .env configuration file..."
    cp .env.example .env
    echo "✅ .env file created. Please edit it with your database credentials."
    echo ""
    echo "Important: Update the following in .env:"
    echo "  - DB_HOST, DB_NAME, DB_USER, DB_PASS"
    echo "  - REDIS_HOST, REDIS_PORT (if different from default)"
    echo "  - JWT_SECRET (use a strong random string)"
    echo ""
fi

# Check database connection
echo "🔍 Testing database connection..."
php -r "
require_once '../config/config.php';
try {
    \$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
    echo 'Database connection: SUCCESS';
} catch (PDOException \$e) {
    echo 'Database connection: FAILED - ' . \$e->getMessage();
    exit(1);
}
"

if [ $? -ne 0 ]; then
    echo "❌ Database connection failed. Please check your .env configuration."
    exit 1
fi

# Ask user what to do
echo ""
echo "What would you like to do?"
echo "1) Start WebSocket server (development mode)"
echo "2) Start WebSocket server (production mode)"
echo "3) Test WebSocket server"
echo "4) Stop WebSocket server"
echo "5) View logs"
echo ""
read -p "Enter choice (1-5): " choice

case $choice in
    1)
        echo "🔧 Starting WebSocket server in development mode..."
        npm run dev
        ;;
    2)
        echo "🚀 Starting WebSocket server in production mode..."
        npm start
        ;;
    3)
        echo "🧪 Testing WebSocket server..."
        node -e "
const http = require('http');
http.get('http://localhost:3002/health', (res) => {
    let data = '';
    res.on('data', chunk => data += chunk);
    res.on('end', () => {
        const health = JSON.parse(data);
        console.log('Health check result:');
        console.log('Status:', health.status);
        console.log('Uptime:', health.uptime + 's');
        console.log('Timestamp:', health.timestamp);
        if (health.status === 'ok') {
            console.log('✅ WebSocket server is running correctly!');
        } else {
            console.log('❌ WebSocket server has issues');
        }
    });
}).on('error', (err) => {
    console.log('❌ Cannot connect to WebSocket server:', err.message);
    console.log('Please make sure the server is running on port 3002');
});
        "
        ;;
    4)
        echo "🛑 Stopping WebSocket server..."
        pkill -f "node.*server.js" || pkill -f "nodemon.*server.js"
        if [ $? -eq 0 ]; then
            echo "✅ WebSocket server stopped"
        else
            echo "ℹ️  WebSocket server was not running"
        fi
        ;;
    5)
        echo "📋 Recent server logs:"
        if [ -f "logs/server.log" ]; then
            tail -20 logs/server.log
        else
            echo "No log file found"
        fi
        ;;
    *)
        echo "❌ Invalid choice. Please enter 1-5."
        exit 1
        ;;
esac