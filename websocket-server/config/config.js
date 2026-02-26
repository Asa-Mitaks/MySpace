// Environment variables configuration
const config = {
  // Server configuration
  PORT: process.env.PORT || 3001,
  WS_PORT: process.env.WS_PORT || 3002,
  
  // Database configuration (matches PHP config)
  DB_HOST: process.env.DB_HOST || 'localhost',
  DB_NAME: process.env.DB_NAME || 'myspace',
  DB_USER: process.env.DB_USER || 'root',
  DB_PASS: process.env.DB_PASS || '',
  
  // Redis configuration
  REDIS_HOST: process.env.REDIS_HOST || 'localhost',
  REDIS_PORT: process.env.REDIS_PORT || 6379,
  REDIS_PASSWORD: process.env.REDIS_PASSWORD || '',
  
  // JWT configuration
  JWT_SECRET: process.env.JWT_SECRET || 'myspace_jwt_secret_key_2024_change_in_production',
  JWT_EXPIRES_IN: process.env.JWT_EXPIRES_IN || '24h',
  
  // CORS configuration
  ALLOWED_ORIGINS: process.env.ALLOWED_ORIGINS ? 
    process.env.ALLOWED_ORIGINS.split(',') : 
    ['0.0.0.0']
    //['http://localhost', 'http://localhost:0000', 'http://127.0.0.1:8000']
};

module.exports = config;