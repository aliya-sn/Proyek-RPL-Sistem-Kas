/**
 * server.js
 * Main entry point for Sistem Kas AI Node.js Backend Service.
 */

const express = require('express');
const cors = require('cors');
const config = require('./src/config');
const { verifyInternalAuth } = require('./src/middleware/authMiddleware');
const { handleChat, handleHealth, handleUpdateConfig, handleGetConfig } = require('./src/controllers/chatController');

const app = express();

// Security and utility middleware
app.use(cors());
app.use(express.json({ limit: '1mb' }));
app.use(express.urlencoded({ extended: true }));

// Request logging in development
if (config.nodeEnv === 'development') {
  app.use((req, res, next) => {
    console.log(`[${new Date().toISOString()}] ${req.method} ${req.url}`);
    next();
  });
}

// 1. Healthcheck endpoint (Public)
app.get('/health', handleHealth);
app.get('/', (req, res) => {
  res.json({
    status: 'online',
    message: 'Sistem Kas AI Node.js Backend Service is running.',
    healthEndpoint: '/health',
  });
});

// 2. AI Chat & Configuration endpoints (Protected by internal auth secret)
app.post('/api/chat', verifyInternalAuth, handleChat);
app.get('/api/status', verifyInternalAuth, handleHealth);
app.post('/api/config', verifyInternalAuth, handleUpdateConfig);
app.get('/api/config', verifyInternalAuth, handleGetConfig);


// 3. Fallback 404 handler
app.use((req, res) => {
  res.status(404).json({
    success: false,
    error: 'Endpoint not found on AI Node.js service.',
  });
});

// 4. Global Error Handler
app.use((err, req, res, next) => {
  console.error('[Unhandled Express Error]', err);
  res.status(500).json({
    success: false,
    error: 'Internal server error in AI Node.js service.',
  });
});

// Start Server
const server = app.listen(config.port, '0.0.0.0', () => {
  console.log(`=======================================================`);
  console.log(`🤖 Sistem Kas AI Node.js Backend Service`);
  console.log(`📡 Listening on: http://127.0.0.1:${config.port}`);
  console.log(`⚙️  Environment : ${config.nodeEnv}`);
  console.log(`🧠 AI Provider : ${config.aiProvider} (${config.aiModel})`);
  console.log(`🔒 Auth Guard  : Active (Internal Secret Protected)`);
  console.log(`=======================================================`);
});

// Handle graceful shutdown
process.on('SIGTERM', () => {
  console.log('SIGTERM signal received. Shutting down gracefully...');
  server.close(() => {
    console.log('HTTP server closed.');
    process.exit(0);
  });
});

module.exports = app;
