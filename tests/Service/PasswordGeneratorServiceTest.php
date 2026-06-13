<?php

namespace App\Tests\Service;

use App\Service\PasswordGeneratorService;
use PHPUnit\Framework\TestCase;

class PasswordGeneratorServiceTest extends TestCase
{
    private PasswordGeneratorService $service;

    protected function setUp(): void
    {
        $this->service = new PasswordGeneratorService();
    }

    // --- generate() ---

    public function testGenerateReturnsCorrectLength(): void
    {
        for ($len = 8; $len <= 32; $len += 4) {
            $this->assertSame($len, strlen($this->service->generate($len)));
        }
    }

    public function testGenerateWithAllCharSets(): void
    {
        $pw = $this->service->generate(64);

        $this->assertMatchesRegularExpression('/[a-z]/', $pw);
        $this->assertMatchesRegularExpression('/[A-Z]/', $pw);
        $this->assertMatchesRegularExpression('/[0-9]/', $pw);
        $this->assertMatchesRegularExpression('/[!@#$%^&*\-_=+]/', $pw);
    }

    public function testGenerateOnlyLowercase(): void
    {
        $pw = $this->service->generate(20, false, true, false, false);

        $this->assertMatchesRegularExpression('/^[a-z]+$/', $pw);
    }

    public function testGenerateOnlyUppercase(): void
    {
        $pw = $this->service->generate(20, true, false, false, false);

        $this->assertMatchesRegularExpression('/^[A-Z]+$/', $pw);
    }

    public function testGenerateOnlyNumbers(): void
    {
        $pw = $this->service->generate(16, false, false, true, false);

        $this->assertMatchesRegularExpression('/^[0-9]+$/', $pw);
    }

    public function testGenerateOnlySymbols(): void
    {
        $pw = $this->service->generate(16, false, false, false, true);

        $this->assertMatchesRegularExpression('/^[!@#$%^&*\-_=+]+$/', $pw);
    }

    public function testGenerateAlwaysContainsAtLeastOneFromEachSelectedSet(): void
    {
        // Run 50 times to ensure statistical guarantee (required chars are injected)
        for ($i = 0; $i < 50; $i++) {
            $pw = $this->service->generate(8, true, true, true, true);
            $this->assertMatchesRegularExpression('/[a-z]/', $pw, "Missing lowercase at iteration $i");
            $this->assertMatchesRegularExpression('/[A-Z]/', $pw, "Missing uppercase at iteration $i");
            $this->assertMatchesRegularExpression('/[0-9]/', $pw, "Missing digit at iteration $i");
            $this->assertMatchesRegularExpression('/[!@#$%^&*\-_=+]/', $pw, "Missing symbol at iteration $i");
        }
    }

    public function testGenerateThrowsWhenNoCharSetSelected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->generate(16, false, false, false, false);
    }

    public function testGenerateProducesUniquePasswords(): void
    {
        $results = [];
        for ($i = 0; $i < 20; $i++) {
            $results[] = $this->service->generate(32);
        }

        $this->assertGreaterThan(1, count(array_unique($results)));
    }

    // --- strength() ---

    public function testStrengthReturnsExpectedKeys(): void
    {
        $result = $this->service->strength('Abc123!x');

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('max', $result);
        $this->assertArrayHasKey('label', $result);
        $this->assertSame(7, $result['max']);
    }

    public function testStrengthVeryWeakForShortLowerOnly(): void
    {
        $result = $this->service->strength('abc');

        $this->assertLessThanOrEqual(2, $result['score']);
        $this->assertSame('Très faible', $result['label']);
    }

    public function testStrengthVeryStrongForLongMixed(): void
    {
        $result = $this->service->strength('Abc123!@#SecurePass99');

        $this->assertSame(7, $result['score']);
        $this->assertSame('Très fort', $result['label']);
    }

    public function testStrengthScoreIncrementsWithLength(): void
    {
        $short  = $this->service->strength('abcde')['score'];
        $medium = $this->service->strength('abcdefgh')['score'];
        $long   = $this->service->strength('abcdefghijklmnop')['score'];

        $this->assertLessThan($medium, $short);
        $this->assertLessThan($long, $medium);
    }

    public function testStrengthLabels(): void
    {
        // score breakdown: len>=8(+1), len>=12(+1), len>=16(+1), lower(+1), upper(+1), digit(+1), symbol(+1)
        $cases = [
            ['abc', 'Très faible'],          // score 1 (lowercase only, len<8)
            ['abcdefgh1', 'Faible'],          // score 3 (len>=8, lower, digit)
            ['abcdefghij12', 'Moyen'],        // score 4 (len>=8, len>=12, lower, digit)
            ['Abcdefghij12', 'Fort'],         // score 5 (len>=8, len>=12, lower, upper, digit)
            ['Abc123!SecureP@ss', 'Très fort'], // score 7 (all)
        ];

        foreach ($cases as [$pw, $expectedLabel]) {
            $result = $this->service->strength($pw);
            $this->assertSame($expectedLabel, $result['label'], "Failed for password: $pw");
        }
    }

    public function testStrengthEmptyPasswordIsVeryWeak(): void
    {
        $result = $this->service->strength('');

        $this->assertSame(0, $result['score']);
        $this->assertSame('Très faible', $result['label']);
    }
}
