const { buildSystemPrompt } = require('../../src/utils/promptBuilder');

describe('buildSystemPrompt', () => {
  const context = {
    ringkasan_kas: { saldo_kas_saat_ini: 1500000 },
  };

  test('includes database context JSON and user identity', () => {
    const prompt = buildSystemPrompt(context, 'super_user', { username: 'admin' }, false);

    expect(prompt).toContain('=== DATA DATABASE ===');
    expect(prompt).toContain('1500000');
    expect(prompt).toContain('PENGGUNA LOGIN: admin | Role: super_user');
    expect(prompt).toContain('ANTI-HALUSINASI');
  });

  test('includes role access guidance for anggota', () => {
    const prompt = buildSystemPrompt(context, 'anggota', { username: 'ahmad' }, false);

    expect(prompt).toContain('AKSES: Anggota biasa');
    expect(prompt).toContain('Dilarang membocorkan data anggota lain');
  });

  test('compact prompt is shorter than full prompt', () => {
    const full = buildSystemPrompt(context, 'super_user', { username: 'admin' }, false);
    const compact = buildSystemPrompt(context, 'super_user', { username: 'admin' }, true);

    expect(compact.length).toBeLessThan(full.length);
  });
});
