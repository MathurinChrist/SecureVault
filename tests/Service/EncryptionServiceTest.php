<?php

namespace App\Tests\Service;

use App\Service\EncryptionService;
use PHPUnit\Framework\TestCase;

class EncryptionServiceTest extends TestCase
{
    private EncryptionService $service;
    private string $key;

    protected function setUp(): void
    {
        $this->service = new EncryptionService();
        $this->key     = hash('sha256', 'test-secret-key', true);
    }

    public function testEncryptReturnsBase64String(): void
    {
        $result = $this->service->encrypt('hello', $this->key);

        $this->assertIsString($result);
        $this->assertNotEmpty(base64_decode($result, true));
    }

    public function testDecryptRoundtrip(): void
    {
        $plaintext  = 'my secret password';
        $encrypted  = $this->service->encrypt($plaintext, $this->key);
        $decrypted  = $this->service->decrypt($encrypted, $this->key);

        $this->assertSame($plaintext, $decrypted);
    }

    public function testEncryptProducesUniqueOutputEachTime(): void
    {
        $a = $this->service->encrypt('same', $this->key);
        $b = $this->service->encrypt('same', $this->key);

        $this->assertNotSame($a, $b, 'AES-GCM uses random IV so two encryptions must differ.');
    }

    public function testDecryptThrowsOnTamperedCiphertext(): void
    {
        $encrypted = $this->service->encrypt('data', $this->key);
        $tampered  = base64_encode(str_repeat('x', strlen(base64_decode($encrypted))));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $this->service->decrypt($tampered, $this->key);
    }

    public function testDecryptThrowsOnWrongKey(): void
    {
        $encrypted = $this->service->encrypt('data', $this->key);
        $wrongKey  = hash('sha256', 'wrong-key', true);

        $this->expectException(\RuntimeException::class);

        $this->service->decrypt($encrypted, $wrongKey);
    }

    public function testEncryptEmptyString(): void
    {
        $encrypted = $this->service->encrypt('', $this->key);
        $decrypted = $this->service->decrypt($encrypted, $this->key);

        $this->assertSame('', $decrypted);
    }

    public function testEncryptUnicodeContent(): void
    {
        $plaintext = 'Pàssw0rd! 🔐 €100';
        $decrypted = $this->service->decrypt($this->service->encrypt($plaintext, $this->key), $this->key);

        $this->assertSame($plaintext, $decrypted);
    }
}
