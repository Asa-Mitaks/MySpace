// Environment variables configuration

// Parse DATABASE_URL for Heroku (JAWSDB_URL, CLEARDB_DATABASE_URL, etc.)
function parseDatabaseUrl() {
  const dbUrl = process.env.DATABASE_URL || process.env.JAWSDB_URL || process.env.CLEARDB_DATABASE_URL;
  if (dbUrl) {
    const url = new URL(dbUrl);
    return {
      host: url.hostname,
      name: url.pathname.replace('/', ''),
      user: url.username,
      pass: url.password,
      port: url.port || 3306
    };
  }
  return null;
}

// Parse REDIS_URL for Heroku (Heroku Redis add-on)
function parseRedisUrl() {
  const redisUrl = process.env.REDIS_URL || process.env.REDIS_TLS_URL;
  if (redisUrl) {
    const url = new URL(redisUrl);
    return {
      host: url.hostname,
      port: url.port || 6379,
      password: url.password || ''
    };
  }
  return null;
}

const dbConfig = parseDatabaseUrl();
const redisConfig = parseRedisUrl();

const config = {
  // Server configuration
  PORT: process.env.PORT || 3001,
  WS_PORT: process.env.WS_PORT || 3002,
  
  // Database configuration (matches PHP config)
  DB_HOST: dbConfig ? dbConfig.host : (process.env.DB_HOST || 'localhost'),
  DB_NAME: dbConfig ? dbConfig.name : (process.env.DB_NAME || 'myspace'),
  DB_USER: dbConfig ? dbConfig.user : (process.env.DB_USER || 'root'),
  DB_PASS: dbConfig ? dbConfig.pass : (process.env.DB_PASS || ''),
  DB_PORT: dbConfig ? dbConfig.port : (process.env.DB_PORT || 3306),
  
  // Redis configuration
  REDIS_HOST: redisConfig ? redisConfig.host : (process.env.REDIS_HOST || 'localhost'),
  REDIS_PORT: redisConfig ? redisConfig.port : (process.env.REDIS_PORT || 6379),
  REDIS_PASSWORD: redisConfig ? redisConfig.password : (process.env.REDIS_PASSWORD || ''),
  REDIS_URL: process.env.REDIS_URL || process.env.REDIS_TLS_URL || null,
  
  // JWT configuration
  JWT_SECRET: process.env.JWT_SECRET || 'myspace_jwt_secret_key_2024_change_in_production_please_use_strong_random_string',
  JWT_EXPIRES_IN: process.env.JWT_EXPIRES_IN || '24h',
  
  // CORS configuration
  ALLOWED_ORIGINS: process.env.ALLOWED_ORIGINS ? 
    process.env.ALLOWED_ORIGINS.split(',') : 
    ['http://localhost', 'http://localhost:8000', 'http://127.0.0.1:8000']
};

module.exports = config;