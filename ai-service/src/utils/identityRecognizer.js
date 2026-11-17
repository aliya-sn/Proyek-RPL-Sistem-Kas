/**
 * identityRecognizer.js
 * Detects AI bot identity questions and authenticated user identity questions.
 * Generates accurate, non-hallucinatory identity responses.
 */

function cleanQuery(query) {
  if (!query || typeof query !== 'string') return '';
  const lower = query.toLowerCase().trim();
  const cleaned = lower.replace(/[^\p{L}\p{N}\s]/gu, ' ');
  return ' ' + cleaned.replace(/\s+/g, ' ').trim() + ' ';
}

/**
 * Check if the user query is asking about the bot's own identity.
 * Targets: "Siapa Anda?", "Kamu siapa?", "Apa kamu?", "Perkenalkan dirimu.", etc.
 */
function isBotIdentityQuery(query) {
  const clean = cleanQuery(query);

  // If query is specifically about user identity (saya/aku/diriku) without mentioning bot (kamu/anda/dirimu)
  if (/\b(saya|aku|diriku|namaku)\b/u.test(clean) && !/\b(kamu|anda|dirimu)\b/u.test(clean)) {
    return false;
  }

  // Financial/transaction questions are not bot identity queries
  if (/\b(transaksi|belum bayar|belum membayar|menunggak|pembayaran|iuran|saldo)\b/u.test(clean)) {
    return false;
  }

  const botPatterns = [
    /\b(siapa|siapakah)\s+(kamu|anda|dirimu|namamu)\b/u,
    /\b(kamu|anda|dirimu)\s+(siapa|siapakah)\b/u,
    /\b(apa|apakah)\s+(kamu|anda|dirimu)\b/u,
    /\b(kamu|anda|dirimu)\s+(apa|apakah)\b/u,
    /\b(kamu|anda)\s+ini\s+(apa|siapa)\b/u,
    /\b(perkenalkan|kenalkan)\s+(dirimu|diri\s+kamu|diri\s+anda)\b/u,
    /\b(nama\s+kamu|nama\s+anda|namamu)\s+(siapa|siapakah|apa)\b/u,
    /\b(apa|apakah)\s+(nama\s+kamu|nama\s+anda|namamu)\b/u,
    /\b(apa|apakah)\s+(tugasmu|tugas\s+kamu|tugas\s+anda|peranmu|peran\s+kamu|peran\s+anda|fungsimu|fungsi\s+kamu|fungsi\s+anda|kemampuanmu|kemampuan\s+kamu)\b/u,
    /\b(tugasmu|tugas\s+kamu|peranmu|peran\s+kamu|fungsimu|fungsi\s+kamu|kemampuanmu|kemampuan\s+kamu)\s+(apa|apakah)\b/u,
    /\b(kamu|anda)\s+(bisa|dapat)\s+apa(\s+saja)?\b/u,
    /\bapa\s+yang\s+(bisa|dapat)\s+(kamu|anda)\s+(lakukan|bantu)\b/u,
    /\b(kamu|anda)\s+ai\b/u,
    /\b(apakah\s+kamu|apakah\s+anda)\s+(seorang\s+)?ai\b/u,
  ];

  return botPatterns.some((pattern) => pattern.test(clean));
}

/**
 * Check if the user query is asking about bot tasks or capabilities.
 */
function isBotCapabilityQuery(query) {
  const clean = cleanQuery(query);

  if (/\b(transaksi|belum bayar|belum membayar|menunggak|saldo)\b/u.test(clean)) {
    return false;
  }

  const capPatterns = [
    /\b(apa|apakah)\s+(tugasmu|tugas\s+kamu|tugas\s+anda|peranmu|peran\s+kamu|peran\s+anda|fungsimu|fungsi\s+kamu|fungsi\s+anda|kemampuanmu|kemampuan\s+kamu)\b/u,
    /\b(tugasmu|tugas\s+kamu|peranmu|peran\s+kamu|fungsimu|fungsi\s+kamu|kemampuanmu|kemampuan\s+kamu)\s+(apa|apakah)\b/u,
    /\b(kamu|anda)\s+(bisa|dapat)\s+apa(\s+saja)?\b/u,
    /\bapa\s+yang\s+(bisa|dapat)\s+(kamu|anda)\s+(lakukan|bantu)\b/u,
  ];

  return capPatterns.some((pattern) => pattern.test(clean));
}

/**
 * Check if the user query is asking about the authenticated user's own identity, role, or credentials.
 */
function isUserIdentityQuery(query) {
  const clean = cleanQuery(query);

  // Financial query guard
  if (/\b(transaksi|pembayaran|bayar|tunggakan|menunggak|pengeluaran|pemasukan|saldo)\b/u.test(clean)) {
    return false;
  }

  const userPatterns = [
    /\b(saya|aku|diriku)\s+(siapa|siapakah)\b/u,
    /\b(siapa|siapakah)\s+(saya|aku|diriku)\b/u,
    /\b(kamu|anda)?\s*(tahu|kenal|mengetahui)\s+(siapa\s+saya|saya\s+siapa|siapa\s+aku|aku\s+siapa)\b/u,
    /\b(apakah\s+kamu|apakah\s+anda)?\s*(tahu|kenal)\s+(siapa\s+saya|saya\s+siapa)\b/u,
    /\b(kamu|anda)\s+(tahu|kenal)\s+(saya|aku)\b/u,
    /\b(siapa|siapakah)\s+(nama\s+saya|nama\s+aku|namaku)\b/u,
    /\b(nama\s+saya|nama\s+aku|namaku)\s+(siapa|siapakah|apa)\b/u,
    /\b(apa|apakah)\s+(nama\s+saya|nama\s+aku|namaku)\b/u,
    /\b(saya|aku)\s+(punya|memiliki)?\s*role\s+(apa|apakah)\b/u,
    /\b(apa|apakah)\s+(role\s+saya|role\s+aku|peran\s+saya|peran\s+aku|hak\s+akses\s+saya)\b/u,
    /\b(role\s+saya|role\s+aku|peran\s+saya|peran\s+aku|hak\s+akses\s+saya)\s+(apa|apakah)\b/u,
    /\b(apa|apakah)\s+(jabatan\s+saya|jabatan\s+aku|posisi\s+saya|posisi\s+aku)(\s+di\s+sistem(\s+ini)?)?\b/u,
    /\b(jabatan\s+saya|jabatan\s+aku|posisi\s+saya|posisi\s+aku)\s+(apa|apakah)(\s+di\s+sistem(\s+ini)?)?\b/u,
    /\b(profil\s+saya|profil\s+aku|identitas\s+saya|identitas\s+aku)\b/u,
    /\bsiapa\s+(yang\s+)?(sedang\s+)?login\b/u,
  ];

  return userPatterns.some((pattern) => pattern.test(clean));
}

/**
 * Generate bot identity response.
 * Informative, natural, and concise 1-sentence answer.
 */
function generateBotIdentityResponse(role, query = '') {
  if (query && isBotCapabilityQuery(query)) {
    return 'Saya membantu Anda mendapatkan informasi Sistem Kas dan memahami data keuangan sesuai hak akses akun Anda.';
  }
  return 'Saya adalah asisten AI Sistem Kas yang siap membantu Anda memahami informasi anggota, pembayaran, pemasukan, dan pengeluaran kas.';
}

/**
 * Generate bot capability response.
 * Informative, natural, and concise 1-sentence answer.
 */
function generateBotCapabilityResponse() {
  return 'Saya membantu Anda mendapatkan informasi Sistem Kas dan memahami data keuangan sesuai hak akses akun Anda.';
}

/**
 * Generate authenticated user identity response.
 * Rule: Short, 1 sentence direct answer without unsolicited explanations.
 */
function generateUserIdentityResponse(userInfo, role, query = '') {
  const username = userInfo && userInfo.username ? userInfo.username : '-';
  const nama = userInfo && userInfo.nama ? userInfo.nama : null;

  let roleLabel = 'Anggota';
  if (role === 'super_user') {
    roleLabel = 'Super User (Administrator)';
  } else if (role === 'bendahara') {
    roleLabel = 'Bendahara';
  }

  const q = (query || '').toLowerCase();
  const userDesc = nama && nama.trim() !== '' ? `${nama} (${username})` : username;
  let extra = '';
  if (userInfo && userInfo.kelas) {
    extra += ` kelas ${userInfo.kelas}`;
  }
  if (userInfo && userInfo.nomor_identitas) {
    extra += ` (NIS: ${userInfo.nomor_identitas})`;
  }

  // Specific role inquiry
  if (q.includes('role') || q.includes('jabatan') || q.includes('posisi') || q.includes('hak akses') || q.includes('peran')) {
    return `Role Anda di sistem adalah ${roleLabel} (${userDesc}).`;
  }

  // Specific name inquiry
  if (q.includes('nama')) {
    if (nama && nama.trim() !== '') {
      return `Nama Anda yang terdaftar di sistem adalah ${nama} (username: ${username}).`;
    }
    return `Nama profil belum diset untuk akun Anda (username: ${username}).`;
  }

  // General user identity inquiry
  const prefix = (q.includes('tahu') || q.includes('kenal')) ? 'Ya, ' : '';
  return `${prefix}Anda masuk sebagai ${userDesc} dengan role ${roleLabel}${extra}.`;
}

module.exports = {
  isBotIdentityQuery,
  isBotCapabilityQuery,
  isUserIdentityQuery,
  generateBotIdentityResponse,
  generateBotCapabilityResponse,
  generateUserIdentityResponse,
};
