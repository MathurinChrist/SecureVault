<?php

namespace App\Tests\E2E;

class EmailVerificationE2ETest extends AbstractE2ETest
{
    // ── Registration flow ────────────────────────────────────────────────────

    public function testRegistrationShowsCheckEmailPage(): void
    {
        $this->skipIfUnavailable();
        $client = static::createPantherClient();
        $email  = static::generateEmail();

        $this->logoutUser($client);

        $crawler = $client->request('GET', '/register');
        $client->waitFor('form');

        $form = $crawler->selectButton("S'inscrire")->form([
            'registration_form[firstName]'     => 'Test',
            'registration_form[lastName]'      => 'Verify',
            'registration_form[email]'         => $email,
            'registration_form[plainPassword]' => 'E2eTest123!',
            'registration_form[agreeTerms]'    => true,
        ]);
        $client->submit($form);
        $client->waitFor('h1');

        $this->assertSelectorTextContains('h1', 'Vérifiez');
        $this->assertSelectorTextContains('body', $email);
    }

    public function testCheckEmailPageShowsResendButton(): void
    {
        $this->skipIfUnavailable();
        $client = static::createPantherClient();
        $email  = static::generateEmail();

        $this->logoutUser($client);

        $crawler = $client->request('GET', '/register');
        $client->waitFor('form');

        $form = $crawler->selectButton("S'inscrire")->form([
            'registration_form[firstName]'     => 'Test',
            'registration_form[lastName]'      => 'Verify',
            'registration_form[email]'         => $email,
            'registration_form[plainPassword]' => 'E2eTest123!',
            'registration_form[agreeTerms]'    => true,
        ]);
        $client->submit($form);
        $client->waitFor('body');

        $this->assertSelectorExists('button[type="submit"]');
    }

    // ── Subscriber: unverified user blocked ──────────────────────────────────

    public function testUnverifiedUserIsRedirectedToPendingPageFromDashboard(): void
    {
        $this->skipIfUnavailable();
        $client = static::createPantherClient();
        $email  = static::generateEmail();

        $this->logoutUser($client);

        // Register but do NOT verify
        $crawler = $client->request('GET', '/register');
        $client->waitFor('form');
        $form = $crawler->selectButton("S'inscrire")->form([
            'registration_form[firstName]'     => 'Test',
            'registration_form[lastName]'      => 'Unverified',
            'registration_form[email]'         => $email,
            'registration_form[plainPassword]' => 'E2eTest123!',
            'registration_form[agreeTerms]'    => true,
        ]);
        $client->submit($form);
        $client->waitFor('body');

        // Log in
        $this->loginUser($client, $email);

        // Try to access dashboard → should land on pending page
        $client->request('GET', '/dashboard');
        $client->waitFor('h1');

        $this->assertSelectorTextContains('h1', 'Confirmez');
    }

    public function testUnverifiedUserSeesEmailOnPendingPage(): void
    {
        $this->skipIfUnavailable();
        $client = static::createPantherClient();
        $email  = static::generateEmail();

        $this->logoutUser($client);

        $crawler = $client->request('GET', '/register');
        $client->waitFor('form');
        $form = $crawler->selectButton("S'inscrire")->form([
            'registration_form[firstName]'     => 'Test',
            'registration_form[lastName]'      => 'Unverified',
            'registration_form[email]'         => $email,
            'registration_form[plainPassword]' => 'E2eTest123!',
            'registration_form[agreeTerms]'    => true,
        ]);
        $client->submit($form);
        $client->waitFor('body');

        $this->loginUser($client, $email);

        $client->request('GET', '/dashboard');
        $client->waitFor('body');

        $this->assertSelectorTextContains('body', $email);
    }

    // ── Verified user has full access ────────────────────────────────────────

    public function testVerifiedUserCanAccessDashboard(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin(); // registerAndLogin auto-verifies

        $client->request('GET', '/dashboard');
        $client->waitFor('body');

        $this->assertSelectorNotExists('h1:contains("Confirmez")');
        $this->assertSelectorNotExists('h1:contains("Vérifiez")');
    }

    public function testVerifiedUserCanAccessVaults(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();

        $client->request('GET', '/vaults');
        $client->waitFor('body');

        // Should not be on the pending page
        $title = $client->getCrawler()->filter('h1')->first()->text('');
        $this->assertStringNotContainsString('Confirmez', $title);
    }

    // ── Pending page actions ─────────────────────────────────────────────────

    public function testPendingPageHasResendButton(): void
    {
        $this->skipIfUnavailable();
        $client = static::createPantherClient();
        $email  = static::generateEmail();

        $this->logoutUser($client);

        $crawler = $client->request('GET', '/register');
        $client->waitFor('form');
        $form = $crawler->selectButton("S'inscrire")->form([
            'registration_form[firstName]'     => 'Test',
            'registration_form[lastName]'      => 'Pending',
            'registration_form[email]'         => $email,
            'registration_form[plainPassword]' => 'E2eTest123!',
            'registration_form[agreeTerms]'    => true,
        ]);
        $client->submit($form);
        $client->waitFor('body');

        $this->loginUser($client, $email);

        $client->request('GET', '/verify/pending');
        $client->waitFor('body');

        $this->assertSelectorExists('button[type="submit"]');
        $this->assertSelectorExists('a[href*="logout"]');
    }

    // ── Test helper endpoint ─────────────────────────────────────────────────

    public function testQuickVerifyEndpointVerifiesUser(): void
    {
        $this->skipIfUnavailable();
        $client = static::createPantherClient();
        $email  = static::generateEmail();

        $this->logoutUser($client);
        $this->registerUser($client, $email);
        $this->verifyEmailForE2E($client, $email);
        $this->loginUser($client, $email);

        $client->request('GET', '/dashboard');
        $client->waitFor('body');

        // Should be on the dashboard, not the pending page
        $this->assertStringNotContainsStringIgnoringCase(
            'confirmez',
            strtolower($client->getCrawler()->filter('h1')->first()->text(''))
        );
    }
}
