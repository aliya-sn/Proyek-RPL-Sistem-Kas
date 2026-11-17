/**
 * authMiddleware.js
 * Protects Node.js AI Service endpoints from unauthorized public access.
 * Validates internal service secret token against configured secret.
 */

const config = require('../config');

function verifyInternalAuth(req, res, next) {
  const secretHeader = req.headers['x-service-secret'];
  const authHeader = req.headers['authorization'];

  let token = secretHeader;
  if (!token && authHeader && authHeader.startsWith('Bearer ')) {
    token = authHeader.substring(7).trim();
  }

  if (!token || token !== config.serviceSecret) {
    return res.status(401).json({
      success: false,
      error: 'Unauthorized: Invalid or missing internal service secret token.',
    });
  }

  next();
}

module.exports = {
  verifyInternalAuth,
};
