<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationControllerTest extends WebTestCase
{
    public function testRegisterPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testRegisterFormContainsRequiredFields(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        $this->assertSelectorExists('input[name*="email"]');
        $this->assertSelectorExists('input[name*="firstName"]');
        $this->assertSelectorExists('input[name*="lastName"]');
        $this->assertSelectorExists('input[name*="plainPassword"]');
    }

    public function testSuccessfulRegistrationRedirectsToLogin(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $client->request('GET', '/register');

        $email = 'newuser_' . uniqid() . '@example.com';

        $client->submitForm('S\'inscrire', [
            'registration_form[email]'         => $email,
            'registration_form[firstName]'     => 'Test',
            'registration_form[lastName]'      => 'User',
            'registration_form[plainPassword]' => 'SecurePass123!',
            'registration_form[agreeTerms]'    => true,
        ]);

        $this->assertResponseRedirects('/login');
    }

    public function testRegistrationWithTooShortPasswordShowsError(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $client->request('GET', '/register');

        $client->submitForm('S\'inscrire', [
            'registration_form[email]'         => 'test_' . uniqid() . '@example.com',
            'registration_form[firstName]'     => 'Test',
            'registration_form[lastName]'      => 'User',
            'registration_form[plainPassword]' => '123',
            'registration_form[agreeTerms]'    => true,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    private function skipIfDatabaseUnavailable(): void
    {
        try {
            $conn = static::getContainer()->get('doctrine.dbal.default_connection');
            $conn->executeQuery('SELECT 1');
        } catch (\Exception) {
            $this->markTestSkipped('Database not available.');
        }
    }
}
