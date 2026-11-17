<?php

namespace Tests\Unit\Services;

use App\Core\Env;
use App\Services\AIService;
use PHPUnit\Framework\TestCase;

class AIServiceTest extends TestCase
{
    private AIService $service;

    protected function setUp(): void
    {
        Env::load(__DIR__ . '/../../../.env');
        $this->service = new AIService();
    }

    public function testStructuredDataQueryDetectsSaldo(): void
    {
        $this->assertTrue($this->service->isStructuredDataQuery('Berapa saldo kas saat ini?'));
    }

    public function testStructuredDataQueryDetectsArrears(): void
    {
        $this->assertTrue($this->service->isStructuredDataQuery('Siapa saja anggota yang menunggak?'));
    }

    public function testUnknownQueryIsNotStructured(): void
    {
        $this->assertFalse($this->service->isStructuredDataQuery('Jelaskan rekomendasi pengelolaan kas secara naratif'));
    }

    public function testBotIdentityQueryDetection(): void
    {
        $this->assertTrue($this->service->isBotIdentityQuery('Kamu siapa?'));
        $this->assertTrue($this->service->isBotIdentityQuery('Siapa namamu?'));
        $this->assertFalse($this->service->isBotIdentityQuery('Berapa saldo kas?'));
    }

    public function testUserIdentityQueryDetection(): void
    {
        $this->assertTrue($this->service->isUserIdentityQuery('Saya siapa?'));
        $this->assertTrue($this->service->isUserIdentityQuery('Apa role saya?'));
        $this->assertFalse($this->service->isUserIdentityQuery('Berapa saldo kas?'));
    }

    public function testBotIdentityResponseIsNotEmpty(): void
    {
        $response = $this->service->generateBotIdentityResponse('super_user', 'Kamu siapa?');

        $this->assertNotEmpty($response);
        $this->assertStringContainsString('asisten AI', $response);
    }

    public function testUserIdentityResponseUsesProvidedIdentity(): void
    {
        $response = $this->service->generateUserIdentityResponse(
            [
                'username' => 'ahmad_rizky',
                'nama' => 'Ahmad Rizky',
                'role' => 'anggota',
                'kelas' => 'XII RPL 1',
                'nomor_identitas' => '20261001',
                'status' => 'active',
            ],
            'anggota',
            'Saya siapa?'
        );

        $this->assertStringContainsString('Ahmad Rizky', $response);
        $this->assertStringContainsString('XII RPL 1', $response);
        $this->assertStringContainsString('20261001', $response);
    }
}
