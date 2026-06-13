<?php

namespace App\Tests\E2E;

use Symfony\Component\Panther\PantherTestCase;

class RegistrationE2ETest extends PantherTestCase
{
    private function skipIfUnavailable(): void
    {
        try {
            $conn = static::getContainer()->get('doctrine.dbal.default_connection');
            $conn->executeQuery('SELECT 1');
        } catch (\Exception) {
            $this->markTestSkipped('Database not available for E2E.');
        }
    }

    public function testRegistrationPageLoads(): void
    {
        $this->skipIfUnavailable();

        $client  = static::createPantherClient();
        $crawler = $client->request('GET', '/register');

        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name*="email"]');
        $this->assertSelectorExists('input[name*="plainPassword"]');
    }

    public function testSuccessfulRegistrationRedirectsToLogin(): void
    {
        $this->skipIfUnavailable();

        $client  = static::createPantherClient();
        $crawler = $client->request('GET', '/register');
        $client->waitFor('form');

        $email = 'e2e_reg_' . uniqid() . '@example.com';

        $form = $crawler->selectButton('S\'inscrire')->form([
            'registration_form[email]'         => $email,
            'registration_form[firstName]'     => 'E2E',
            'registration_form[lastName]'      => 'Register',
            'registration_form[plainPassword]' => 'StrongPass123!',
            'registration_form[agreeTerms]'    => true,
        ]);
        $client->submit($form);
        $client->waitFor('body');

        $this->assertStringContainsString('/login', $client->getCurrentURL());
    }

    public function testRegistrationWithWeakPasswordShowsError(): void
    {
        $client  = static::createPantherClient();
        $crawler = $client->request('GET', '/register');
        $client->waitFor('form');

        $form = $crawler->selectButton('S\'inscrire')->form([
            'registration_form[email]'         => 'weak_' . uniqid() . '@test.com',
            'registration_form[firstName]'     => 'Test',
            'registration_form[lastName]'      => 'User',
            'registration_form[plainPassword]' => '123',
            'registration_form[agreeTerms]'    => true,
        ]);
        $client->submit($form);
        $client->waitFor('body');

        // Should stay on /register with validation error
        $this->assertStringContainsString('/register', $client->getCurrentURL());
    }
}
