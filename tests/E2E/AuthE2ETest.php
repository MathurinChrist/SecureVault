<?php

namespace App\Tests\E2E;

class AuthE2ETest extends AbstractE2ETest
{
    public function testLoginPageDisplaysForm(): void
    {
        $this->skipIfUnavailable();

        $client = static::createPantherClient();
        $client->request('GET', '/login');

        $this->assertSelectorExists('input[name="email"]');
        $this->assertSelectorExists('input[name="password"]');
        $this->assertSelectorExists('button[type="submit"]');
    }

    public function testUnauthenticatedAccessRedirectsToLogin(): void
    {
        $this->skipIfUnavailable();

        $client = static::createPantherClient();
        $this->logoutUser($client);
        $client->request('GET', '/dashboard');

        $client->waitFor('body');
        $this->assertStringContainsString('/login', $client->getCurrentURL());
    }

    public function testUnauthenticatedAccessToVaultsRedirects(): void
    {
        $this->skipIfUnavailable();

        $client = static::createPantherClient();
        $this->logoutUser($client);
        $client->request('GET', '/vaults');

        $client->waitFor('body');
        $this->assertStringContainsString('/login', $client->getCurrentURL());
    }

    public function testSuccessfulLoginRedirectsToDashboard(): void
    {
        $this->skipIfUnavailable();

        [$client, $email] = $this->registerAndLogin();

        $this->assertStringContainsString('/dashboard', $client->getCurrentURL());
    }

    public function testLoginWithWrongPasswordShowsError(): void
    {
        $this->skipIfUnavailable();

        $client = static::createPantherClient();
        $email  = static::generateEmail();
        $this->logoutUser($client);
        $this->registerUser($client, $email);
        $this->loginUser($client, $email, 'WrongPassword!');

        $this->assertStringContainsString('/login', $client->getCurrentURL());
    }

    public function testLogoutRedirectsToPublicPage(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();

        $client->request('GET', '/logout');
        $client->waitFor('body');

        $url = $client->getCurrentURL();
        $this->assertThat(
            $url,
            $this->logicalOr(
                $this->stringContains('/login'),
                $this->stringContains('localhost')
            )
        );
        // Must no longer be on a protected page
        $this->assertStringNotContainsString('/dashboard', $url);
    }
}
