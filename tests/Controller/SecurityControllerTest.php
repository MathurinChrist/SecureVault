<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="email"]');
        $this->assertSelectorExists('input[name="password"]');
    }

    public function testUnauthenticatedAccessRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/dashboard');

        $this->assertResponseRedirects('/login');
    }

    public function testUnauthenticatedVaultsRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/vaults');

        $this->assertResponseRedirects('/login');
    }

    public function testInvalidLoginShowsError(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $client->request('GET', '/login');
        $client->submitForm('Se connecter', [
            'email'    => 'nobody@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('form');
    }

    public function testApiLoginWithoutCredentialsReturns401(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $client->request(
            'POST',
            '/api/v1/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'nobody@example.com', 'password' => 'wrong']),
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testApiVaultsWithoutTokenReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/vaults');

        $this->assertResponseStatusCodeSame(401);
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
