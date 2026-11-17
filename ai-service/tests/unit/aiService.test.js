const config = require('../../src/config');
config.aiApiKey = 'test-key';
config.aiModel = 'gemini-1.5-flash';
config.aiProvider = 'gemini';

const { processQuery } = require('../../src/services/aiService');

describe('processQuery', () => {
  beforeEach(() => {
    global.fetch = jest.fn();
    config.aiProvider = 'gemini';
    config.aiModel = 'gemini-1.5-flash';
    config.aiApiKey = 'test-key';
  });

  test('structured query uses local analytical engine without calling API', async () => {
    const res = await processQuery({
      question: 'Berapa saldo kas saat ini?',
      role: 'super_user',
      userContext: { username: 'admin', role: 'super_user' },
      financialContext: { ringkasan_kas: { saldo_kas_saat_ini: 1000 } },
    });

    expect(res.success).toBe(true);
    expect(res.provider).toBe('nodejs_ai_engine (analytical_core)');
    expect(global.fetch).not.toHaveBeenCalled();
  });

  test('bot identity query returns local identity response', async () => {
    const res = await processQuery({
      question: 'Kamu siapa?',
      role: 'super_user',
    });

    expect(res.answer).toContain('asisten AI Sistem Kas');
    expect(global.fetch).not.toHaveBeenCalled();
  });

  test('gemini provider uses mocked API response', async () => {
    global.fetch.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        candidates: [{ content: { parts: [{ text: 'Jawaban Gemini mock' }] } }],
      }),
    });

    const res = await processQuery({
      question: 'Jelaskan rekomendasi pengelolaan kas secara naratif',
      role: 'super_user',
      userContext: { username: 'admin', role: 'super_user' },
      financialContext: { ringkasan_kas: { saldo_kas_saat_ini: 1000 } },
    });

    expect(res.success).toBe(true);
    expect(res.answer).toBe('Jawaban Gemini mock');
    expect(res.provider).toContain('gemini');
    expect(global.fetch).toHaveBeenCalledTimes(1);
  });

  test('openai provider uses mocked API response', async () => {
    config.aiProvider = 'openai';
    config.aiModel = 'gpt-3.5-turbo';

    global.fetch.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        choices: [{ message: { content: 'Jawaban OpenAI mock' } }],
      }),
    });

    const res = await processQuery({
      question: 'Jelaskan rekomendasi pengelolaan kas secara naratif',
      role: 'super_user',
      userContext: { username: 'admin', role: 'super_user' },
      financialContext: { ringkasan_kas: { saldo_kas_saat_ini: 1000 } },
    });

    expect(res.success).toBe(true);
    expect(res.answer).toBe('Jawaban OpenAI mock');
    expect(res.provider).toContain('openai');
  });

  test('groq provider uses mocked API response', async () => {
    config.aiProvider = 'groq';
    config.aiModel = 'llama-3.3-70b-versatile';

    global.fetch.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        choices: [{ message: { content: 'Jawaban Groq mock' }, finish_reason: 'stop' }],
      }),
    });

    const res = await processQuery({
      question: 'Jelaskan rekomendasi pengelolaan kas secara naratif',
      role: 'super_user',
      userContext: { username: 'admin', role: 'super_user' },
      financialContext: { ringkasan_kas: { saldo_kas_saat_ini: 1000 } },
    });

    expect(res.success).toBe(true);
    expect(res.answer).toBe('Jawaban Groq mock');
    expect(res.provider).toContain('groq');
  });

  test('falls back to local engine when API call fails', async () => {
    global.fetch.mockRejectedValue(new Error('network fail'));

    const res = await processQuery({
      question: 'Jelaskan rekomendasi pengelolaan kas secara naratif',
      role: 'super_user',
      userContext: { username: 'admin', role: 'super_user' },
      financialContext: { ringkasan_kas: { saldo_kas_saat_ini: 1000 } },
    });

    expect(res.success).toBe(true);
    expect(res.provider).toBe('nodejs_ai_engine (analytical_core)');
  });
});
