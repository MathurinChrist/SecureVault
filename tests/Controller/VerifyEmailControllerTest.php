<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class VerifyEmailControllerTest extends WebTestCase
{
    private function skipIfDatabaseUnavailable(): void
    {
        try {
            static::getContainer()->get('doctrine.dbal.default_connection')->executeQuery('SELECT 1');
        } catch (\Exception) {
            $this->markTestSkipped('Database not available.');
        }
    }

    private function createUnverifiedUser(string $email = ''): User
    {
        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email ?: 'verify_' . uniqid() . '@example.com')
             ->setFirstName('Verify')
             ->setLastName('Test')
             ->setPassword($hasher->hashPassword($user, 'Pass123!'))
             ->setEmailVerified(false);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createVerifiedUser(string $email = ''): User
    {
        $user = $this->createUnverifiedUser($email);
        $user->setEmailVerified(true);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        return $user;
    }

    // ── /verify/pending ──────────────────────────────────────────────────────

    public function testVerifyPendingRedirectsToLoginWhenGuest(): void
    {
        $client = static::createClient();
        $client->request('GET', '/verify/pending');

        $this->assertResponseRedirects('/login');
    }

    public function testVerifyPendingShowsPageForUnverifiedUser(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $user = $this->createUnverifiedUser();
        $client->loginUser($user);

        $client->request('GET', '/verify/pending');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Confirmez');
        $this->assertSelectorExists('button[type="submit"]');
    }

    // ── /verify/email ─────────────────────────────────────────────────────────

    public function testVerifyEmailWithoutIdShowsError(): void
    {
        $client = static::createClient();
        $client->request('GET', '/verify/email');

        $this->assertResponseRedirects('/login');
    }

    public function testVerifyEmailWithUnknownIdShowsError(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $client->request('GET', '/verify/email?id=99999999');

        $this->assertResponseRedirects('/login');
    }

    public function testVerifyEmailWithInvalidTokenShowsError(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $user = $this->createUnverifiedUser();

        $client->request('GET', '/verify/email?id=' . $user->getId() . '&token=bad&expires=9999999999');

        // Invalid signature → redirect to pending page with error flash
        $this->assertResponseRedirects('/verify/pending');
    }

    public function testVerifyEmailWithValidTokenSetsEmailVerified(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $user = $this->createUnverifiedUser();
        $client->loginUser($user);

        $helper    = static::getContainer()->get(VerifyEmailHelperInterface::class);
        $signature = $helper->generateSignature(
            'app_verify_email',
            (string) $user->getId(),
            $user->getEmail(),
            ['id' => $user->getId()],
        );

        $userId    = $user->getId();
        $parsedUrl = parse_url($signature->getSignedUrl());
        $uri       = $parsedUrl['path'] . '?' . ($parsedUrl['query'] ?? '');

        $client->request('GET', $uri);

        // Determine which redirect path was taken
        $location = $client->getResponse()->headers->get('Location');
        $this->assertSame('/login', $location, 'Expected redirect to /login — got ' . $location);

        // Use DBAL to bypass any ORM identity-map caching
        $conn     = static::getContainer()->get('doctrine.dbal.default_connection');
        $verified = $conn->fetchOne('SELECT email_verified FROM "user" WHERE id = ?', [$userId]);
        $this->assertTrue((bool) $verified, 'email_verified should be TRUE in DB after confirmation');
    }

    public function testVerifyEmailAlreadyVerifiedShowsSuccess(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $user = $this->createVerifiedUser();
        $client->loginUser($user);

        $helper    = static::getContainer()->get(VerifyEmailHelperInterface::class);
        $signature = $helper->generateSignature(
            'app_verify_email',
            (string) $user->getId(),
            $user->getEmail(),
            ['id' => $user->getId()],
        );

        $parts = parse_url($signature->getSignedUrl());
        $uri   = $parts['path'] . '?' . $parts['query'];

        $client->request('GET', $uri);

        $this->assertResponseRedirects('/login');
    }

    // ── EmailVerificationSubscriber ──────────────────────────────────────────

    public function testUnverifiedUserIsRedirectedFromDashboard(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $user = $this->createUnverifiedUser();
        $client->loginUser($user);

        $client->request('GET', '/dashboard');

        $this->assertResponseRedirects('/verify/pending');
    }

    public function testVerifiedUserCanAccessDashboard(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $user = $this->createVerifiedUser();
        $client->loginUser($user);

        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
    }

    public function testUnverifiedUserCanAccessVerifyPendingRoute(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $user = $this->createUnverifiedUser();
        $client->loginUser($user);

        $client->request('GET', '/verify/pending');

        $this->assertResponseIsSuccessful();
    }

    public function testUnverifiedUserCanLogout(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $user = $this->createUnverifiedUser();
        $client->loginUser($user);

        $client->request('GET', '/logout');

        // logout redirects somewhere, just not to an error
        $this->assertResponseRedirects();
    }

    // ── /verify/resend ───────────────────────────────────────────────────────

    public function testResendVerificationRequiresPost(): void
    {
        $client = static::createClient();
        $client->request('GET', '/verify/resend');

        // The catch-all app_not_found route (no method restriction) intercepts
        // GET requests to this POST-only route before Symfony can return a 405.
        $this->assertResponseStatusCodeSame(404);
    }

    public function testResendVerificationWithInvalidCsrfShowsError(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $user = $this->createUnverifiedUser();
        $client->loginUser($user);

        $client->request('POST', '/verify/resend', ['_token' => 'invalid']);

        $this->assertResponseRedirects('/verify/pending');
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'CSRF');
    }

    public function testResendVerificationSendsEmailForLoggedInUser(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $user = $this->createUnverifiedUser();
        $client->loginUser($user);

        // GET the pending page first to establish session and render the CSRF form
        $client->request('GET', '/verify/pending');
        $this->assertResponseIsSuccessful();

        // Submit the resend form (CSRF token is in the rendered form)
        $client->submitForm('Renvoyer l\'e-mail de confirmation');

        $this->assertResponseRedirects('/verify/pending');
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'e-mail');
    }
}
