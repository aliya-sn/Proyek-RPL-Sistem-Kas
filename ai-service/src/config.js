/**
 * config.js
 * Centralized configuration loader for Node.js AI Service.
 */

const path = require('path');
const dotenv = require('dotenv');

// Load environment variables from .env in ai-service folder
dotenv.config({ path: path.resolve(__dirname, '..', '.env') });

module.exports = {
  port: parseInt(process.env.PORT || '5000', 10),
  nodeEnv: process.env.NODE_ENV || 'development',
  serviceSecret: process.env.AI_SERVICE_SECRET || 'sistem_kas_ai_node_secret_key_2026_auth',
  aiProvider: (process.env.AI_PROVIDER || 'gemini').toLowerCase(),
  aiApiKey: process.env.AI_API_KEY || '',
  aiModel: process.env.AI_MODEL || 'gemini-1.5-flash',
  requestTimeoutMs: parseInt(process.env.REQUEST_TIMEOUT_MS || '15000', 10),
  maxQuestionLength: parseInt(process.env.MAX_QUESTION_LENGTH || '500', 10),
};
