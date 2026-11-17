<?php

namespace Tests\Unit\Core;

use App\Core\Env;
use PHPUnit\Framework\TestCase;

class EnvTest extends TestCase
{
    private string $envFile;

    protected function setUp(): void
    {
        $this->envFile = sys_get_temp_dir() . '/sistem_kas_test_' . uniqid() . '.env';
        file_put_contents($this->envFile, "APP_NAME=\"Test App\"\nAPP_DEBUG=true\nDB_PASSWORD=\n");
    }

    protected function tearDown(): void
    {
        if (file_exists($this->envFile)) {
            unlink($this->envFile);
        }
    }

    public function testLoadAndGetEnvironmentVariable(): void
    {
        Env::load($this->envFile);

        $this->assertSame('Test App', Env::get('APP_NAME'));
        $this->assertTrue(Env::get('APP_DEBUG'));
        $this->assertSame('', Env::get('DB_PASSWORD'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        Env::load($this->envFile);

        $this->assertSame('fallback', Env::get('MISSING_KEY', 'fallback'));
        $this->assertNull(Env::get('MISSING_KEY'));
    }
}
