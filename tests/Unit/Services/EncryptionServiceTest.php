<?php

namespace Tests\Unit\Services;

use App\Core\Env;
use App\Services\EncryptionService;
use PHPUnit\Framework\TestCase;

class EncryptionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        Env::load(__DIR__ . '/../../../.env');
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $plain = 'super-secret-api-key-123';

        $encrypted = EncryptionService::encrypt($plain);

        $this->assertNotSame('', $encrypted);
        $this->assertNotSame($plain, $encrypted);
        $this->assertSame($plain, EncryptionService::decrypt($encrypted));
    }

    public function testEmptyPlaintextReturnsEmptyString(): void
    {
        $this->assertSame('', EncryptionService::encrypt(''));
    }

    public function testDecryptInvalidPayloadReturnsNull(): void
    {
        $this->assertNull(EncryptionService::decrypt('not-base64!!!'));
        $this->assertNull(EncryptionService::decrypt(base64_encode('too-short')));
    }

    public function testMaskApiKeyForLongKey(): void
    {
        $masked = EncryptionService::maskApiKey('AQ.Ab8RN6Lo2v85YtorWh');

        $this->assertStringStartsWith('AQ.Ab8', $masked);
        $this->assertStringEndsWith('orWh', $masked);
        $this->assertStringContainsString('••••••••', $masked);
    }

    public function testMaskApiKeyForEmptyValue(): void
    {
        $this->assertSame('Belum Dikonfigurasi', EncryptionService::maskApiKey(null));
        $this->assertSame('Belum Dikonfigurasi', EncryptionService::maskApiKey(''));
    }
}
