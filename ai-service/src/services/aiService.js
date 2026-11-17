/**
 * aiService.js
 * Core intelligence service supporting Google Gemini, OpenAI, and local analytical engine.
 */

const config = require('../config');
const {
  isBotIdentityQuery,
  isBotCapabilityQuery,
  isUserIdentityQuery,
  generateBotIdentityResponse,
  generateBotCapabilityResponse,
  generateUserIdentityResponse,
} = require('../utils/identityRecognizer');
const { buildSystemPrompt, generateLocalAnalysis } = require('../utils/promptBuilder');

/**
 * Call Google Gemini REST API using native fetch.
 */
async function callGeminiApi(systemPrompt, userQuery) {
  const url = `https://generativelanguage.googleapis.com/v1beta/models/${encodeURIComponent(
    config.aiModel || 'gemini-1.5-flash'
  )}:generateContent?key=${encodeURIComponent(config.aiApiKey)}`;

  const payload = {
    system_instruction: {
      parts: [{ text: systemPrompt }],
    },
    contents: [
      {
        role: 'user',
        parts: [{ text: userQuery }],
      },
    ],
    generationConfig: {
      temperature: 0.2,
      maxOutputTokens: 1024,
    },
  };

  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), config.requestTimeoutMs);

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
      signal: controller.signal,
    });

    clearTimeout(timeoutId);

    if (!response.ok) {
      throw new Error(`Gemini API returned HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();
    if (data.candidates && data.candidates[0] && data.candidates[0].content && data.candidates[0].content.parts) {
      return data.candidates[0].content.parts.map((p) => p.text).join('').trim();
    }
    return null;
  } catch (error) {
    clearTimeout(timeoutId);
    throw error;
  }
}

/**
 * Call OpenAI REST API using native fetch.
 */
async function callOpenAiApi(systemPrompt, userQuery) {
  const url = 'https://api.openai.com/v1/chat/completions';

  const payload = {
    model: config.aiModel || 'gpt-3.5-turbo',
    messages: [
      { role: 'system', content: systemPrompt },
      { role: 'user', content: userQuery },
    ],
    temperature: 0.2,
  };

  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), config.requestTimeoutMs);

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${config.aiApiKey}`,
      },
      body: JSON.stringify(payload),
      signal: controller.signal,
    });

    clearTimeout(timeoutId);

    if (!response.ok) {
      throw new Error(`OpenAI API returned HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();
    if (data.choices && data.choices[0] && data.choices[0].message) {
      return data.choices[0].message.content.trim();
    }
    return null;
  } catch (error) {
    clearTimeout(timeoutId);
    throw error;
  }
}

/**
 * Detect whether a query is a structured data query that should be
 * handled directly by the local analytical engine without external AI.
 * This prevents Gemini/OpenAI from misinterpreting data-driven questions.
 */
function isStructuredDataQuery(query) {
  const q = (query || '').toLowerCase();

  // Member-related structured queries
  if (/(berapa|jumlah|total|ada\s+berapa)\s+.*anggota|anggota\s+.*(jumlah|total|berapa)/.test(q)) return true;
  if (/berapa\s+anggota|ada\s+berapa\s+anggota|jumlah\s+anggota|total\s+anggota/.test(q)) return true;
  if (/anggota\s+terdaftar/.test(q)) return true;
  if (/anggota\s+(yang\s+)?(sudah\s+)?(lunas|menunggak|nunggak|belum\s+bayar|belum\s+membayar)/.test(q)) return true;
  if (/berapa\s+anggota\s+(yang\s+)?(lunas|menunggak|nunggak)/.test(q)) return true;
  if (/(lunas|menunggak)\s+dan\s+(menunggak|lunas)/.test(q)) return true;
  if (/(siapa\s+saja|daftar)\s+anggota\s+(yang\s+)?(menunggak|tunggak|nunggak|belum)/.test(q)) return true;
  if (/(siapa|siapa\s+saja)\s+(yang\s+)?(masih\s+)?(menunggak|tunggak|nunggak|belum\s+bayar)/.test(q)) return true;

  // Financial structured queries
  if (/total\s+pemasukan|total\s+pengeluaran|saldo\s+kas/.test(q)) return true;
  if (/(pemasukan|pengeluaran|saldo)\s+(saat\s+ini|berdasarkan\s+database)/.test(q)) return true;
  if (/berapa\s+(total\s+)?(pemasukan|pengeluaran|saldo)/.test(q)) return true;
  if (/(pemasukan|pengeluaran).*dan.*(saldo|pengeluaran)/.test(q)) return true;

  // Payment total (explicit)
  if (/(total|seluruh|berapa)\s+(pembayaran|setoran)/.test(q)) return true;
  if (/pembayaran\s+(anggota|kas|iuran)\s+(berapa|total|seluruh|periode)/.test(q)) return true;
  if (/berapa\s+(total\s+)?pembayaran/.test(q)) return true;

  // Transaction queries
  if (/(transaksi|pembayaran)\s+(terbaru|terakhir|lima|5)/.test(q)) return true;
  if (/(tampilkan|lihat|daftar)\s+.*(transaksi|pembayaran)/.test(q)) return true;
  if (/(riwayat\s+)(transaksi|pembayaran)/.test(q)) return true;

  // Period-specific queries
  if (/(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)/.test(q) &&
      /(pembayaran|tunggak|pemasukan|pengeluaran|bayar)/.test(q)) return true;

  // Arrears / tunggakan queries
  if (/tunggakan|menunggak|nunggak|belum\s+(membayar|bayar)/.test(q)) return true;

  // Void / transaction status queries
  if (/(void|batal|dibatalkan)\s*(transaksi)?/.test(q)) return true;

  // Reconciliation queries (saldo vs pembayaran/iuran)
  if (/(mengapa|kenapa|jelaskan|apakah|sebab|alasan).*saldo.*(beda|berbeda|selisih|iuran|pembayaran)/.test(q)) return true;
  if (/saldo.*(beda|berbeda|selisih).*pembayaran/.test(q)) return true;
  if (/(beda|berbeda|selisih).*saldo.*(pembayaran|iuran)/.test(q)) return true;

  // Multi-table Expense queries (purpose, creator, largest, detail, comparison, follow-up)
  if (/(pengeluaran|uang\s+keluar|biaya|transaksi).*(buat\s+apa|untuk\s+apa|tujuan|keperluan|kegunaan|detail|rincian|keterangan|terbesar|paling\s+besar|paling\s+banyak|terbanyak|siapa\s+yang\s+mencatat|dicatat\s+oleh|pencatat)/.test(q)) return true;
  if (/(buat\s+apa|untuk\s+apa|keperluan\s+apa|siapa\s+yang\s+mencatat|siapa\s+pencatat|kapan\s+transaksi).*(pengeluaran|transaksi|uang\s+keluar)/.test(q)) return true;
  if (/(pengeluaran|uang\s+keluar)\s+(\d+|rp)/.test(q)) return true;
  if (/(pengeluaran|transaksi)\s+(itu|tadi|tersebut)/.test(q)) return true;
  if (/(siapa\s+yang\s+mencatatnya|kapan\s+transaksi\s+itu|pengeluaran\s+itu\s+untuk\s+apa)/.test(q)) return true;
  if (/banding.*(pengeluaran|pemasukan)/.test(q)) return true;
  if (/(pengeluaran|pemasukan).*banding/.test(q)) return true;
  if (/(transaksi|pengeluaran|pemasukan)\s+mana\s+yang\s+(paling\s+besar|terbesar)/.test(q)) return true;
  if (/(rincian|detail)\s+saldo/.test(q)) return true;
  if (/apakah\s+total\s+pembayaran.*cocok.*pemasukan/.test(q)) return true;

  // Year-specific financial queries (misal: pemasukan tahun 2026)
  if (/\b(202[0-9])\b/.test(q) && /(pemasukan|pengeluaran|saldo|pembayaran|transaksi|tunggak)/.test(q)) return true;

  return false;
}

/**
 * Call Groq REST API using native fetch (OpenAI-compatible endpoint).
 */
async function callGroqApi(systemPrompt, userQuery) {
  const url = 'https://api.groq.com/openai/v1/chat/completions';

  const payload = {
    model: config.aiModel && config.aiModel.trim() !== '' ? config.aiModel : 'llama-3.3-70b-versatile',
    messages: [
      { role: 'system', content: systemPrompt },
      { role: 'user', content: userQuery },
    ],
    temperature: 0.2,
    max_tokens: 512,
  };

  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 10000);

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${config.aiApiKey}`,
      },
      body: JSON.stringify(payload),
      signal: controller.signal,
    });

    clearTimeout(timeoutId);

    if (!response.ok) {
      throw new Error(`Groq API returned HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();
    if (data.choices && data.choices[0] && data.choices[0].message && data.choices[0].message.content) {
      return {
        answer: data.choices[0].message.content.trim(),
        finishReason: data.choices[0].finish_reason || null,
      };
    }
    return null;
  } catch (error) {
    clearTimeout(timeoutId);
    throw error;
  }
}

/**
 * Compact financial context for Groq to avoid HTTP 400 (prompt too long).
 */
function compactContextForGroq(context, query) {
  const q = (query || '').toLowerCase();
  const ctx = context || {};
  const compact = {};

  const simpleKeys = ['role_akses', 'database_status', 'periode_laporan', 'tanggal_analisis', 'pengguna_login', 'query_tertarget'];
  for (const key of simpleKeys) {
    if (ctx[key] !== undefined) compact[key] = ctx[key];
  }

  if (ctx.ringkasan_kas && typeof ctx.ringkasan_kas === 'object') {
    compact.ringkasan_kas = ctx.ringkasan_kas;
  }

  const memberRelated = q.includes('anggota') || q.includes('member') || q.includes('kelas') || q.includes('gender') || q.includes('laki') || q.includes('perempuan');
  if (memberRelated && ctx.informasi_anggota && typeof ctx.informasi_anggota === 'object') {
    const info = ctx.informasi_anggota;
    const keepMembers = q.includes('daftar anggota') || q.includes('siapa saja anggota') || q.includes('nama anggota') || q.includes('anggota terdaftar');
    compact.informasi_anggota = {
      total_anggota: info.total_anggota ?? null,
      total_anggota_aktif: info.total_anggota_aktif ?? null,
      total_anggota_nonaktif: info.total_anggota_nonaktif ?? null,
      rincian_gender: info.rincian_gender ?? null,
      daftar_kelas: info.daftar_kelas ?? null,
    };
    if (keepMembers && Array.isArray(info.daftar_anggota)) {
      compact.informasi_anggota.daftar_anggota = info.daftar_anggota.slice(0, 20);
    }
  }

  const arrearsRelated = q.includes('tunggak') || q.includes('nunggak') || q.includes('belum') || q.includes('lunas') || q.includes('menunggak');
  if (arrearsRelated && ctx.ringkasan_tunggakan && typeof ctx.ringkasan_tunggakan === 'object') {
    compact.ringkasan_tunggakan = ctx.ringkasan_tunggakan;
  }

  const paymentRelated = q.includes('bayar') || q.includes('pembayaran') || q.includes('setoran') || q.includes('pemasukan') || q.includes('iuran');
  if (paymentRelated && ctx.ringkasan_pembayaran_anggota && typeof ctx.ringkasan_pembayaran_anggota === 'object') {
    const pay = ctx.ringkasan_pembayaran_anggota;
    compact.ringkasan_pembayaran_anggota = {
      total_semua_pembayaran_nominal: pay.total_semua_pembayaran_nominal ?? null,
      total_semua_pembayaran_transaksi: pay.total_semua_pembayaran_transaksi ?? null,
      total_pembayaran_bulan_ini: pay.total_pembayaran_bulan_ini ?? null,
      jumlah_transaksi_pembayaran: pay.jumlah_transaksi_pembayaran ?? null,
      daftar_pembayaran_bulan_ini: pay.daftar_pembayaran_bulan_ini ?? null,
    };
    if (q.includes('per anggota') || q.includes('masing-masing') || q.includes('pembayaran anggota')) {
      if (pay.pembayaran_per_anggota !== undefined) compact.ringkasan_pembayaran_anggota.pembayaran_per_anggota = pay.pembayaran_per_anggota;
      if (pay.rincian_per_periode !== undefined) compact.ringkasan_pembayaran_anggota.rincian_per_periode = pay.rincian_per_periode;
    }
  }

  const transactionRelated = q.includes('transaksi') || q.includes('riwayat') || q.includes('kategori') || q.includes('void') || q.includes('batal');
  if (transactionRelated && ctx.ringkasan_transaksi && typeof ctx.ringkasan_transaksi === 'object') {
    const trx = ctx.ringkasan_transaksi;
    compact.ringkasan_transaksi = {
      total_transaksi_aktif: trx.total_transaksi_aktif ?? null,
      total_transaksi_void: trx.total_transaksi_void ?? null,
      total_nominal_void: trx.total_nominal_void ?? null,
      kategori_transaksi: trx.kategori_transaksi ?? null,
    };
    if (q.includes('tanggal') || /\d{4}-\d{2}-\d{2}/.test(q)) {
      if (trx.transaksi_per_tanggal !== undefined) compact.ringkasan_transaksi.transaksi_per_tanggal = trx.transaksi_per_tanggal;
    }
  }

  const expenseRelated = q.includes('pengeluaran') || q.includes('keluar') || q.includes('biaya') || q.includes('detail') || q.includes('rincian') || q.includes('keterangan') || q.includes('pencatat') || q.includes('terbesar');
  if (expenseRelated && Array.isArray(ctx.daftar_transaksi_pengeluaran)) {
    compact.daftar_transaksi_pengeluaran = ctx.daftar_transaksi_pengeluaran.slice(0, 15);
  }
  if (ctx.pengeluaran_terbesar !== undefined) compact.pengeluaran_terbesar = ctx.pengeluaran_terbesar;

  const reconciliationRelated = q.includes('saldo') && (q.includes('pembayaran') || q.includes('iuran')) && (q.includes('beda') || q.includes('berbeda') || q.includes('selisih') || q.includes('mengapa') || q.includes('kenapa'));
  if (reconciliationRelated && ctx.rekonsiliasi_kas && typeof ctx.rekonsiliasi_kas === 'object') {
    compact.rekonsiliasi_kas = ctx.rekonsiliasi_kas;
  }

  const systemRelated = q.includes('user') || q.includes('pengguna') || q.includes('akun') || q.includes('audit') || q.includes('aktivitas') || q.includes('log');
  if (systemRelated && ctx.ringkasan_sistem && typeof ctx.ringkasan_sistem === 'object') {
    compact.ringkasan_sistem = ctx.ringkasan_sistem;
  }

  const notificationRelated = q.includes('notifikasi') || q.includes('whatsapp') || q.includes('wa');
  if (notificationRelated && ctx.ringkasan_notifikasi_whatsapp && typeof ctx.ringkasan_notifikasi_whatsapp === 'object') {
    compact.ringkasan_notifikasi_whatsapp = ctx.ringkasan_notifikasi_whatsapp;
  }

  const recentPaymentRelated = q.includes('terbaru') || q.includes('terakhir') || q.includes('lima');
  if (recentPaymentRelated && ctx.transaksi_pembayaran_terbaru !== undefined) compact.transaksi_pembayaran_terbaru = ctx.transaksi_pembayaran_terbaru;
  if (recentPaymentRelated && ctx.sampel_transaksi_pemasukan !== undefined) compact.sampel_transaksi_pemasukan = ctx.sampel_transaksi_pemasukan;
  if (recentPaymentRelated && ctx.sampel_transaksi_pengeluaran !== undefined) compact.sampel_transaksi_pengeluaran = ctx.sampel_transaksi_pengeluaran;

  for (const key of Object.keys(compact)) {
    if (compact[key] === null || compact[key] === undefined) delete compact[key];
  }

  return compact;
}

function isLowQualityGroqResponse(answer, query) {
  const text = (answer || '').trim();
  if (text.length < 10) return true;

  const lines = text.split(/\r?\n/).map((line) => line.trim()).filter((line) => line !== '');
  const counts = {};
  for (const line of lines) {
    counts[line] = (counts[line] || 0) + 1;
  }
  for (const count of Object.values(counts)) {
    if (count > 2) return true;
  }

  return false;
}

/**
 * Process a user query through the AI Service pipeline.
 */
async function processQuery({ question, role, userContext, financialContext }) {
  const query = (question || '').trim();
  const userRole = role || 'anggota';
  const userInfo = userContext || {};
  const dataContext = financialContext || {};

  // 1. Fast-Path Local Recognition: Bot capability queries ("Apa tugas Anda?", "Apa tugasmu?", etc.)
  if (isBotCapabilityQuery(query)) {
    return {
      success: true,
      answer: generateBotCapabilityResponse(),
      provider: 'nodejs_ai_engine (capability_core)',
    };
  }

  // 1b. Fast-Path Local Recognition: Bot identity queries ("Siapa Anda?", "Kamu siapa?", etc.)
  if (isBotIdentityQuery(query)) {
    return {
      success: true,
      answer: generateBotIdentityResponse(userRole, query),
      provider: 'nodejs_ai_engine (identity_core)',
    };
  }

  // 2. Fast-Path Local Recognition: Authenticated user identity queries
  if (isUserIdentityQuery(query)) {
    return {
      success: true,
      answer: generateUserIdentityResponse(userInfo, userRole, query),
      provider: 'nodejs_ai_engine (session_auth_core)',
    };
  }

  // 2b. Hybrid Route: Structured financial queries use deterministic local calculations as source of truth.
  if (isStructuredDataQuery(query)) {
    const localAnswer = generateLocalAnalysis(query, dataContext, userRole, userInfo);
    return {
      success: true,
      answer: localAnswer,
      provider: 'nodejs_ai_engine (analytical_core)',
    };
  }

  // 3b. Build the system prompt before any external provider call.
  // The prompt carries database context, authenticated user identity, role,
  // and strict read-only/anti-hallucination instructions.
  const systemPrompt = buildSystemPrompt(dataContext, userRole, userInfo);

  // 4. If AI API Key is configured, prioritize live provider call (Gemini or OpenAI)
  if (config.aiApiKey && config.aiApiKey.trim() !== '') {
    try {
      let liveResponse = null;
      if (config.aiProvider === 'gemini') {
        liveResponse = await callGeminiApi(systemPrompt, query);
      } else if (config.aiProvider === 'openai') {
        liveResponse = await callOpenAiApi(systemPrompt, query);
      } else if (config.aiProvider === 'groq') {
        const compactContext = compactContextForGroq(dataContext, query);
        const compactPrompt = buildSystemPrompt(compactContext, userRole, userInfo, true);
        const groqResult = await callGroqApi(compactPrompt, query);
        const groqAnswer = groqResult && groqResult.answer ? String(groqResult.answer).trim() : '';
        const groqAccepted = (
          !!groqAnswer &&
          groqResult.finishReason !== 'length' &&
          !isLowQualityGroqResponse(groqAnswer, query)
        );

        if (groqAccepted) {
          liveResponse = groqAnswer;
          // store provider override
          // We'll return directly below
          return {
            success: true,
            answer: groqAnswer,
            provider: `${config.aiProvider} (${config.aiModel}) via Node.js Service`,
          };
        }
      }

      if (liveResponse && liveResponse.trim() !== '') {
        return {
          success: true,
          answer: liveResponse,
          provider: `${config.aiProvider} (${config.aiModel}) via Node.js Service`,
        };
      }
    } catch (err) {
      // Log warning and gracefully fall back to analytical engine
      console.warn(`[Node.js AI Service] External provider call failed (${err.message}), falling back to local analytical engine.`);
    }
  }

  // 5. Deterministic Local Analytical Fallback Engine (when API Key is absent or external call failed)
  const localAnswer = generateLocalAnalysis(query, dataContext, userRole, userInfo);
  return {
    success: true,
    answer: localAnswer,
    provider: 'nodejs_ai_engine (analytical_core)',
  };
}

module.exports = {
  processQuery,
};
