/**
 * chat.test.js
 * Automated test suite for Node.js AI Service.
 * Run via: npm test (or node test/chat.test.js)
 */

const assert = require('assert');
const { isBotIdentityQuery, isUserIdentityQuery, generateBotIdentityResponse, generateUserIdentityResponse } = require('../src/utils/identityRecognizer');
const { processQuery } = require('../src/services/aiService');
const { buildSystemPrompt } = require('../src/utils/promptBuilder');

let passCount = 0;
let failCount = 0;

function test(name, fn) {
  try {
    fn();
    console.log(`  [PASS] ${name}`);
    passCount++;
  } catch (err) {
    console.error(`  [FAIL] ${name} -> ${err.message}`);
    failCount++;
  }
}

async function asyncTest(name, fn) {
  try {
    await fn();
    console.log(`  [PASS] ${name}`);
    passCount++;
  } catch (err) {
    console.error(`  [FAIL] ${name} -> ${err.message}`);
    failCount++;
  }
}

async function runTests() {
  console.log('=======================================================');
  console.log('🧪 NODE.JS AI SERVICE — TEST SUITE');
  console.log('=======================================================');

  console.log('\n── 1. Bot Identity Recognition ──');
  test('Detects "Kamu siapa?" as bot identity query', () => {
    assert.strictEqual(isBotIdentityQuery('Kamu siapa?'), true);
    assert.strictEqual(isBotIdentityQuery('Siapa namamu?'), true);
    assert.strictEqual(isBotIdentityQuery('Apa tugas kamu di sistem ini?'), true);
  });

  test('Rejects "Berapa saldo kas?" from bot identity', () => {
    assert.strictEqual(isBotIdentityQuery('Berapa saldo kas saat ini?'), false);
    assert.strictEqual(isBotIdentityQuery('Siapa yang belum membayar?'), false);
  });

  console.log('\n── 2. Authenticated User Identity Recognition ──');
  test('Detects "Saya siapa?" as user identity query', () => {
    assert.strictEqual(isUserIdentityQuery('Saya siapa?'), true);
    assert.strictEqual(isUserIdentityQuery('Kamu tahu siapa saya?'), true);
    assert.strictEqual(isUserIdentityQuery('Apa role saya?'), true);
  });

  test('Generates user response without hallucinating', () => {
    const userInfo = {
      username: 'ahmad_rizky',
      nama: 'Ahmad Rizky',
      role: 'anggota',
      kelas: 'XII RPL 1',
      nomor_identitas: '20261001',
      status: 'active',
    };
    const resp = generateUserIdentityResponse(userInfo, 'anggota', 'Saya siapa?');
    assert.ok(resp.includes('Ahmad Rizky'));
    assert.ok(resp.includes('XII RPL 1'));
    assert.ok(resp.includes('20261001'));
    assert.ok(resp.includes('Anggota'));
  });

  console.log('\n── 3. Role Financial Query Processing ──');
  await asyncTest('Super User financial summary query', async () => {
    const res = await processQuery({
      question: 'Berapa saldo kas saat ini?',
      role: 'super_user',
      userContext: { username: 'admin', role: 'super_user' },
      financialContext: {
        ringkasan_kas: {
          saldo_kas_saat_ini: 15500000,
          total_pemasukan_semua: 20000000,
          total_pengeluaran_semua: 4500000,
        },
      },
    });

    assert.strictEqual(res.success, true);
    assert.ok(res.answer.includes('15.500.000') || res.answer.includes('15500000'));
  });

  await asyncTest('Anggota personal financial isolation query', async () => {
    const res = await processQuery({
      question: 'Berapa tunggakan saya?',
      role: 'anggota',
      userContext: { username: 'user1', nama: 'Budi', role: 'anggota' },
      financialContext: {
        status_pembayaran_saya: {
          status_bulan_ini: 'tunggak',
          minggu_belum_bayar: 2,
          estimasi_tunggakan: 20000,
        },
      },
    });

    assert.strictEqual(res.success, true);
    assert.ok(res.answer.includes('20.000') || res.answer.includes('20000'));
    assert.ok(res.answer.includes('2 minggu'));
  });

  console.log('\n── 4. Precise AI Identity & Brief Response Tests ──');
  const expectedBotIdentity = 'Saya adalah asisten AI Sistem Kas yang siap membantu Anda memahami informasi anggota, pembayaran, pemasukan, dan pengeluaran kas.';
  const expectedBotCapability = 'Saya membantu Anda mendapatkan informasi Sistem Kas dan memahami data keuangan sesuai hak akses akun Anda.';

  await asyncTest('Query "Siapa Anda?" returns natural informative 1-sentence identity', async () => {
    const res = await processQuery({ question: 'Siapa Anda?', role: 'super_user' });
    assert.strictEqual(res.answer, expectedBotIdentity);
  });

  await asyncTest('Query "Kamu siapa?" returns natural informative 1-sentence identity', async () => {
    const res = await processQuery({ question: 'Kamu siapa?', role: 'super_user' });
    assert.strictEqual(res.answer, expectedBotIdentity);
  });

  await asyncTest('Query "Apa kamu?" returns natural informative 1-sentence identity', async () => {
    const res = await processQuery({ question: 'Apa kamu?', role: 'super_user' });
    assert.strictEqual(res.answer, expectedBotIdentity);
  });

  await asyncTest('Query "Perkenalkan dirimu." returns natural informative 1-sentence identity', async () => {
    const res = await processQuery({ question: 'Perkenalkan dirimu.', role: 'super_user' });
    assert.strictEqual(res.answer, expectedBotIdentity);
  });

  await asyncTest('Query "Apa tugas Anda?" returns natural informative 1-sentence capability', async () => {
    const res = await processQuery({ question: 'Apa tugas Anda?', role: 'super_user' });
    assert.strictEqual(res.answer, expectedBotCapability);
  });

  await asyncTest('Query "Ada berapa anggota?" returns brief actual count', async () => {
    const res = await processQuery({
      question: 'Ada berapa anggota?',
      role: 'super_user',
      financialContext: {
        informasi_anggota: {
          total_anggota: 12,
          total_anggota_aktif: 10,
        },
      },
    });
    assert.strictEqual(res.answer, 'Total anggota terdaftar adalah 12 orang (10 aktif dan 2 tidak aktif).');
  });

  await asyncTest('Query "Siapa saja anggota yang menunggak?" returns brief list', async () => {
    const res = await processQuery({
      question: 'Siapa saja anggota yang menunggak?',
      role: 'super_user',
      financialContext: {
        ringkasan_tunggakan: {
          total_estimasi_tunggakan: 60000,
          jumlah_anggota_menunggak: 2,
          daftar_anggota_menunggak: [
            { nama: 'Budi Santoso', estimasi_tunggakan: 40000 },
            { nama: 'Siti Aminah', estimasi_tunggakan: 20000 },
          ],
        },
      },
    });
    assert.ok(res.answer.includes('Budi Santoso'));
    assert.ok(res.answer.includes('Siti Aminah'));
    assert.ok(res.answer.includes('60.000'));
  });

  await asyncTest('Query with unavailable data returns brief notice without hallucination', async () => {
    const res = await processQuery({
      question: 'Berapa luas lapangan sepak bola sekolah?',
      role: 'super_user',
      financialContext: {},
    });
    assert.strictEqual(res.answer, 'Mohon maaf, data tersebut belum tersedia dalam sistem kas.');
  });

  console.log('\n── 5. Member + Period Payment Filter ──');
  await asyncTest('Combined member and period query returns only that member\'s payment in that period', async () => {
    const res = await processQuery({
      question: 'Pada bulan November, Ahmad Rizky melakukan berapa pembayaran?',
      role: 'super_user',
      userContext: { username: 'admin', role: 'super_user' },
      financialContext: {
        query_tertarget: {
          periode: {
            nama_bulan: 'November',
            tahun: 2026,
            total_nominal: 1000000,
            jumlah_transaksi: 5,
            jumlah_anggota_bayar: 3,
            daftar_pembayar: [],
            daftar_menunggak: [],
          },
          anggota_terpilih: {
            profil: { nama: 'Ahmad Rizky' },
            pembayaran: {
              total_nominal: 300000,
              jumlah_transaksi: 2,
              rincian: [
                { periode: 'November 2026', nominal: 100000, tanggal_bayar: '2026-11-10' },
                { periode: 'Oktober 2026', nominal: 200000, tanggal_bayar: '2026-10-10' },
              ],
            },
          },
        },
      },
    });
    assert.strictEqual(res.success, true);
    assert.ok(res.answer.includes('Ahmad Rizky'));
    assert.ok(res.answer.includes('1 pembayaran'));
    assert.ok(res.answer.includes('November 2026'));
    assert.ok(res.answer.includes('100.000'));
  });

  await asyncTest('Period-only query still returns all members payment total for that period', async () => {
    const res = await processQuery({
      question: 'Total pembayaran kas pada bulan November 2026 berapa?',
      role: 'super_user',
      userContext: { username: 'admin', role: 'super_user' },
      financialContext: {
        query_tertarget: {
          periode: {
            nama_bulan: 'November',
            tahun: 2026,
            total_nominal: 1000000,
            jumlah_transaksi: 5,
            jumlah_anggota_bayar: 3,
            daftar_pembayar: [],
            daftar_menunggak: [],
          },
        },
        ringkasan_kas: { saldo_kas_saat_ini: 0, total_pemasukan_semua: 0, total_pengeluaran_semua: 0 },
      },
    });
    assert.strictEqual(res.success, true);
    assert.ok(res.answer.includes('Total pembayaran kas pada periode November 2026'));
    assert.ok(res.answer.includes('1.000.000'));
  });

  await asyncTest('Multi-member arrears returns tunggakan for each requested member', async () => {
    const res = await processQuery({
      question: 'Berapa nominal tunggakan Diva Nayla dan Siti Nurhaliza pada November 2026?',
      role: 'super_user',
      userContext: { username: 'admin', role: 'super_user' },
      financialContext: {
        query_tertarget: {
          periode: {
            nama_bulan: 'November',
            tahun: 2026,
            total_nominal: 810000,
            jumlah_transaksi: 6,
            jumlah_anggota_bayar: 6,
            daftar_pembayar: [],
            daftar_menunggak: [
              { nama: 'Diva Nayla', kelas: 'XII TKJ 2', minggu_belum_bayar: 4, estimasi_tunggakan: 80000 },
              { nama: 'Siti Nurhaliza', kelas: 'XII TKJ 2', minggu_belum_bayar: 4, estimasi_tunggakan: 100000 },
            ],
          },
          anggota_terpilih: { profil: { nama: 'Diva Nayla' }, pembayaran: { total_nominal: 90000, jumlah_transaksi: 2, rincian: [] } },
          anggota_terpilih_list: [
            { profil: { nama: 'Diva Nayla' }, pembayaran: { total_nominal: 90000, jumlah_transaksi: 2, rincian: [] } },
            { profil: { nama: 'Siti Nurhaliza' }, pembayaran: { total_nominal: 0, jumlah_transaksi: 0, rincian: [] } },
          ],
        },
      },
    });
    assert.strictEqual(res.success, true);
    assert.ok(res.answer.includes('Diva Nayla: Rp 80.000'));
    assert.ok(res.answer.includes('Siti Nurhaliza: Rp 100.000'));
  });

  await asyncTest('Single-member arrears returns that member tunggakan', async () => {
    const res = await processQuery({
      question: 'Berapa tunggakan Diva Nayla November 2026?',
      role: 'super_user',
      userContext: { username: 'admin', role: 'super_user' },
      financialContext: {
        query_tertarget: {
          periode: {
            nama_bulan: 'November',
            tahun: 2026,
            total_nominal: 810000,
            jumlah_transaksi: 6,
            jumlah_anggota_bayar: 6,
            daftar_pembayar: [],
            daftar_menunggak: [
              { nama: 'Diva Nayla', kelas: 'XII TKJ 2', minggu_belum_bayar: 4, estimasi_tunggakan: 80000 },
            ],
          },
          anggota_terpilih: { profil: { nama: 'Diva Nayla' }, pembayaran: { total_nominal: 90000, jumlah_transaksi: 2, rincian: [] } },
          anggota_terpilih_list: [
            { profil: { nama: 'Diva Nayla' }, pembayaran: { total_nominal: 90000, jumlah_transaksi: 2, rincian: [] } },
          ],
        },
      },
    });
    assert.strictEqual(res.success, true);
    assert.ok(res.answer.includes('Diva Nayla: Rp 80.000'));
  });

  console.log('\n── 6. Groq Prompt Length ──');
  test('Compact system prompt is shorter than full prompt', () => {
    const context = {
      role_akses: 'super_user',
      database_status: 'terhubung',
      informasi_anggota: { total_anggota: 8, total_anggota_aktif: 8 },
      ringkasan_kas: { saldo_kas_saat_ini: 1000 },
    };
    const fullPrompt = buildSystemPrompt(context, 'super_user', { username: 'admin' }, false);
    const compactPrompt = buildSystemPrompt(context, 'super_user', { username: 'admin' }, true);
    assert.ok(compactPrompt.length < fullPrompt.length);
  });

  test('Compact system prompt retains database context marker', () => {
    const context = { role_akses: 'super_user', database_status: 'terhubung' };
    const compactPrompt = buildSystemPrompt(context, 'super_user', { username: 'admin' }, true);
    assert.ok(compactPrompt.includes('=== DATA DATABASE ===') || compactPrompt.includes('=== KONTEKS DATA DATABASE SISTEM KAS ===') || compactPrompt.includes('DATA DATABASE'));
  });

  console.log('\n── 7. Hybrid Groq Routing ──');
  await asyncTest('Structured payment query uses local analytical engine', async () => {
    const res = await processQuery({
      question: 'Total pembayaran kas pada bulan November 2026 berapa?',
      role: 'super_user',
      userContext: { username: 'admin', role: 'super_user' },
      financialContext: {
        query_tertarget: {
          periode: {
            nama_bulan: 'November',
            tahun: 2026,
            total_nominal: 1000000,
            jumlah_transaksi: 5,
            jumlah_anggota_bayar: 3,
            daftar_pembayar: [],
            daftar_menunggak: [],
          },
        },
      },
    });
    assert.strictEqual(res.success, true);
    assert.strictEqual(res.provider, 'nodejs_ai_engine (analytical_core)');
  });

  await asyncTest('Structured arrears query uses local analytical engine', async () => {
    const res = await processQuery({
      question: 'Berapa nominal tunggakan Diva Nayla dan Siti Nurhaliza pada November 2026?',
      role: 'super_user',
      userContext: { username: 'admin', role: 'super_user' },
      financialContext: {
        query_tertarget: {
          periode: {
            nama_bulan: 'November',
            tahun: 2026,
            total_nominal: 810000,
            jumlah_transaksi: 6,
            jumlah_anggota_bayar: 6,
            daftar_pembayar: [],
            daftar_menunggak: [
              { nama: 'Diva Nayla', kelas: 'XII TKJ 2', minggu_belum_bayar: 4, estimasi_tunggakan: 80000 },
              { nama: 'Siti Nurhaliza', kelas: 'XII TKJ 2', minggu_belum_bayar: 4, estimasi_tunggakan: 100000 },
            ],
          },
          anggota_terpilih: { profil: { nama: 'Diva Nayla' }, pembayaran: { total_nominal: 90000, jumlah_transaksi: 2, rincian: [] } },
          anggota_terpilih_list: [
            { profil: { nama: 'Diva Nayla' }, pembayaran: { total_nominal: 90000, jumlah_transaksi: 2, rincian: [] } },
            { profil: { nama: 'Siti Nurhaliza' }, pembayaran: { total_nominal: 0, jumlah_transaksi: 0, rincian: [] } },
          ],
        },
      },
    });
    assert.strictEqual(res.success, true);
    assert.ok(res.answer.includes('Diva Nayla: Rp 80.000'));
    assert.ok(res.answer.includes('Siti Nurhaliza: Rp 100.000'));
  });

  console.log('\n=======================================================');
  console.log(` RESULTS: ${passCount} PASSED | ${failCount} FAILED`);
  console.log('=======================================================');

  if (failCount > 0) {
    process.exit(1);
  }
}

runTests();
