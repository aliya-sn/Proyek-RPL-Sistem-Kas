/**
 * chatController.js
 * Handles chat incoming requests, validation, and responses.
 */

const config = require('../config');
const { processQuery } = require('../services/aiService');

function formatTimestamp() {
  const now = new Date();
  return now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false });
}

async function handleChat(req, res) {
  try {
    const { question, role, user_context, financial_context, active_config } = req.body || {};

    // Dynamic config injection from PHP backend (zero-restart sync)
    if (active_config && typeof active_config === 'object') {
      if (active_config.model) {
        config.aiModel = active_config.model;
      }
      if (active_config.apiKey) {
        config.aiApiKey = active_config.apiKey;
      }
      if (active_config.provider) {
        config.aiProvider = active_config.provider.toLowerCase();
      }
    }

    // 1. Validation: Question must not be empty
    if (!question || typeof question !== 'string' || question.trim() === '') {
      return res.status(400).json({
        success: false,
        error: 'Pertanyaan tidak boleh kosong.',
      });
    }

    const trimmedQuestion = question.trim();

    // 2. Validation: Maximum question length
    if (trimmedQuestion.length > config.maxQuestionLength) {
      return res.status(400).json({
        success: false,
        error: `Pertanyaan terlalu panjang. Maksimal ${config.maxQuestionLength} karakter.`,
      });
    }

    // 3. Validation: Role
    const validRoles = ['super_user', 'bendahara', 'anggota'];
    const userRole = validRoles.includes(role) ? role : 'anggota';

    // 4. Process Query
    const result = await processQuery({
      question: trimmedQuestion,
      role: userRole,
      userContext: user_context || {},
      financialContext: financial_context || {},
    });

    return res.status(200).json({
      success: true,
      question: trimmedQuestion,
      answer: result.answer,
      provider: result.provider,
      timestamp: formatTimestamp(),
    });
  } catch (err) {
    console.error('[Node.js AI Service Error]', err);
    return res.status(500).json({
      success: false,
      error: 'Terjadi kendala internal pada layanan AI Node.js. Silakan coba lagi.',
    });
  }
}

function handleHealth(req, res) {
  return res.status(200).json({
    status: 'healthy',
    service: 'Sistem Kas AI Node.js Backend',
    version: '1.0.0',
    provider: config.aiProvider,
    model: config.aiModel,
    uptime: process.uptime(),
    timestamp: new Date().toISOString(),
  });
}

function handleUpdateConfig(req, res) {
  try {
    const { provider, model, apiKey } = req.body || {};

    if (provider) {
      config.aiProvider = String(provider).toLowerCase();
    }
    if (model) {
      config.aiModel = String(model).replace(/^models\//, '');
    }
    if (apiKey) {
      config.aiApiKey = String(apiKey).trim();
    }

    return res.status(200).json({
      success: true,
      message: 'Konfigurasi runtime AI Node.js berhasil diperbarui tanpa restart.',
      provider: config.aiProvider,
      model: config.aiModel,
      hasApiKey: Boolean(config.aiApiKey),
    });
  } catch (err) {
    return res.status(500).json({
      success: false,
      error: 'Gagal memperbarui konfigurasi runtime: ' + err.message,
    });
  }
}

function handleGetConfig(req, res) {
  return res.status(200).json({
    success: true,
    provider: config.aiProvider,
    model: config.aiModel,
    hasApiKey: Boolean(config.aiApiKey),
  });
}

module.exports = {
  handleChat,
  handleHealth,
  handleUpdateConfig,
  handleGetConfig,
};

