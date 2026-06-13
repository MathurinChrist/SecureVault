<?php

namespace App\Tests\E2E;

class RegistrationE2ETest extends AbstractE2ETest
{
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

        $client = static::createPantherClient();
        $this->logoutUser($client);

        $email = static::generateEmail();
        $this->registerUser($client, $email);

        $this->assertStringContainsString('/login', $client->getCurrentURL());
    }

    public function testRegistrationWithWeakPasswordShowsError(): void
    {
        $this->skipIfUnavailable();

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

        $this->assertStringContainsString('/register', $client->getCurrentURL());
    }

    public function testDuplicateEmailShowsError(): void
    {
        $this->skipIfUnavailable();

        $client = static::createPantherClient();
        $email  = static::generateEmail();

        $this->logoutUser($client);
        $this->registerUser($client, $email);

        // Register again with the same email
        $this->registerUser($client, $email);

        // Should stay on /register with a validation error
        $this->assertStringContainsString('/register', $client->getCurrentURL());
    }
}
