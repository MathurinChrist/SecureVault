<?php

namespace App\Tests\E2E;

use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

abstract class AbstractE2ETest extends PantherTestCase
{
    private const DEFAULT_PASSWORD = 'E2eTest123!';

    protected function skipIfUnavailable(): void
    {
        try {
            static::createPantherClient()->request('GET', '/');
        } catch (\Throwable $e) {
            $this->markTestSkipped('E2E environment not available: ' . $e->getMessage());
        }
    }

    protected static function generateEmail(): string
    {
        return 'e2e_' . uniqid() . '@example.com';
    }

    /**
     * Register a new user via the registration form UI.
     * Required because the Panther HTTP server runs against the `panther` database,
     * while static::getContainer() targets the `test` database — they don't share state.
     */
    protected function registerUser(Client $client, string $email, string $password = self::DEFAULT_PASSWORD): void
    {
        $crawler = $client->request('GET', '/register');
        $client->waitFor('form');

        $form = $crawler->selectButton('Créer mon compte')->form([
            'registration_form[firstName]'     => 'E2E',
            'registration_form[lastName]'      => 'User',
            'registration_form[email]'         => $email,
            'registration_form[plainPassword]' => $password,
            'registration_form[agreeTerms]'    => true,
        ]);
        $client->submit($form);
        $client->waitFor('body');
    }

    protected function loginUser(Client $client, string $email, string $password = self::DEFAULT_PASSWORD): void
    {
        $crawler = $client->request('GET', '/login');
        $client->waitFor('form');

        $form = $crawler->selectButton('Se connecter')->form([
            'email'    => $email,
            'password' => $password,
        ]);
        $client->submit($form);
        $client->waitFor('body');
    }

    protected function logoutUser(Client $client): void
    {
        try {
            $client->request('GET', '/logout');
            $client->waitFor('body');
        } catch (\Throwable) {
        }
    }

    /**
     * Bypass email verification via the test-helper endpoint.
     * Works because the Panther server runs in the 'panther' env (non-prod).
     */
    protected function verifyEmailForE2E(Client $client, string $email): void
    {
        $client->request('GET', '/test/verify-email?email=' . urlencode($email));
        $client->waitFor('body');
    }

    /**
     * Register a fresh user via UI, verify their email, and log in. Returns [client, email].
     */
    protected function registerAndLogin(string $password = self::DEFAULT_PASSWORD): array
    {
        $client = static::createPantherClient();
        $email  = static::generateEmail();

        $this->logoutUser($client);
        $this->registerUser($client, $email, $password);
        $this->verifyEmailForE2E($client, $email);
        $this->loginUser($client, $email, $password);

        return [$client, $email];
    }
}
