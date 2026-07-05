<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Service\TwoFactorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TwoFactorControllerTest extends WebTestCase
{
    private function skipIfDatabaseUnavailable(): void
    {
        try {
            static::getContainer()->get('doctrine.dbal.default_connection')->executeQuery('SELECT 1');
        } catch (\Exception) {
            $this->markTestSkipped('Database not available.');
        }
    }

    /**
     * Fetching a CSRF token from the container requires a "current request" on the
     * request stack (SessionTokenStorage resolves the session through it), which no
     * longer exists once $client->request() has returned. Push the last request back
     * on temporarily to generate the token, then save the session again — writing the
     * token adds a new key that the earlier $session->save() call didn't persist.
     */
    private function csrfToken(KernelBrowser $client, string $tokenId): string
    {
        $requestStack = $client->getContainer()->get('request_stack');
        $requestStack->push($client->getRequest());
        try {
            $token = $client->getContainer()->get('security.csrf.token_manager')->getToken($tokenId)->getValue();
            $client->getRequest()->getSession()->save();

            return $token;
        } finally {
            $requestStack->pop();
        }
    }

    private function createUser(string $suffix = '', bool $with2fa = false): array
    {
        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $plain = 'Pass123!';
        $email = 'twofa_' . $suffix . uniqid() . '@example.com';

        $user = new User();
        $user->setEmail($email)
             ->setFirstName('Test')
             ->setLastName('2FA')
             ->setPassword($hasher->hashPassword($user, $plain))
             ->setEmailVerified(true)
             ->setIs2faEnabled($with2fa);

        $em->persist($user);
        $em->flush();

        return [$user, $plain];
    }

    // ───── /2fa/verify GET — redirect if no pending session ─────────────

    public function testVerifyPageRedirectsToDashboardWhenNoPending(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        [$user] = $this->createUser();
        $client->loginUser($user);

        $client->request('GET', '/2fa/verify');
        $this->assertResponseRedirects('/dashboard');
    }

    public function testVerifyPageRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/2fa/verify');
        $this->assertResponseRedirects('/login');
    }

    // ───── POST /2fa/verify — code validation ────────────────────────────

    public function testValidCodeClearsPendingAndRedirects(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        [$user] = $this->createUser();
        $client->loginUser($user);

        // Inject pending session manually
        $client->request('GET', '/dashboard'); // boots kernel and session
        $session = $client->getRequest()->getSession();
        $session->set('2fa_code', '123456');
        $session->set('2fa_expires_at', time() + 600);
        $session->set('2fa_pending', true);
        $session->save();

        $client->request('POST', '/2fa/verify', [
            '_token' => $this->csrfToken($client, '2fa_verify'),
            'code'   => '123456',
        ]);

        $this->assertResponseRedirects('/dashboard');

        // Pending should be cleared
        $client->followRedirect();
        $client->request('GET', '/2fa/verify');
        $this->assertResponseRedirects('/dashboard');
    }

    public function testWrongCodeShowsError(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        [$user] = $this->createUser();
        $client->loginUser($user);

        $client->request('GET', '/dashboard');
        $session = $client->getRequest()->getSession();
        $session->set('2fa_code', '999999');
        $session->set('2fa_expires_at', time() + 600);
        $session->set('2fa_pending', true);
        $session->save();

        $client->request('POST', '/2fa/verify', [
            '_token' => $this->csrfToken($client, '2fa_verify'),
            'code'   => '000000',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.text-warn', 'Code incorrect');
    }

    public function testExpiredCodeShowsError(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        [$user] = $this->createUser();
        $client->loginUser($user);

        $client->request('GET', '/dashboard');
        $session = $client->getRequest()->getSession();
        $session->set('2fa_code', '111111');
        $session->set('2fa_expires_at', time() - 1); // already expired
        $session->set('2fa_pending', true);
        $session->save();

        $client->request('POST', '/2fa/verify', [
            '_token' => $this->csrfToken($client, '2fa_verify'),
            'code'   => '111111',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.text-warn', 'Code incorrect');
    }

    public function testInvalidCsrfTokenReturnsError(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        [$user] = $this->createUser();
        $client->loginUser($user);

        $client->request('GET', '/dashboard');
        $session = $client->getRequest()->getSession();
        $session->set('2fa_pending', true);
        $session->save();

        $client->request('POST', '/2fa/verify', [
            '_token' => 'invalid',
            'code'   => '123456',
        ]);

        $this->assertResponseRedirects('/2fa/verify');
    }

    // ───── Subscriber blocks dashboard when 2FA pending ──────────────────

    public function testSubscriberBlocksDashboardWhenPending(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        [$user] = $this->createUser();
        $client->loginUser($user);

        $client->request('GET', '/dashboard');
        $session = $client->getRequest()->getSession();
        $session->set('2fa_pending', true);
        $session->save();

        $client->request('GET', '/dashboard');
        $this->assertResponseRedirects('/2fa/verify');
    }

    // ───── 2FA toggle setup/teardown ─────────────────────────────────────

    public function testEnable2faSetIs2faEnabledTrue(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        [$user] = $this->createUser('enable_');
        $client->loginUser($user);

        $client->request('GET', '/alerts');
        $client->submitForm('Activer maintenant');

        $this->assertResponseRedirects('/alerts');

        $conn    = static::getContainer()->get('doctrine.dbal.default_connection');
        $enabled = $conn->fetchOne('SELECT is2fa_enabled FROM "user" WHERE id = ?', [$user->getId()]);
        $this->assertTrue((bool) $enabled);
    }

    public function testDisable2faSetIs2faEnabledFalse(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        [$user] = $this->createUser('disable_', true);
        $client->loginUser($user);

        $client->request('GET', '/alerts');
        $client->submitForm('Désactiver');

        $this->assertResponseRedirects('/alerts');

        $conn    = static::getContainer()->get('doctrine.dbal.default_connection');
        $enabled = $conn->fetchOne('SELECT is2fa_enabled FROM "user" WHERE id = ?', [$user->getId()]);
        $this->assertFalse((bool) $enabled);
    }

    // ───── POST /2fa/resend ───────────────────────────────────────────────

    public function testResendRedirectsToVerifyIfPending(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        [$user] = $this->createUser();
        $client->loginUser($user);

        $client->request('GET', '/dashboard');
        $session = $client->getRequest()->getSession();
        $session->set('2fa_pending', true);
        $session->set('2fa_code', '000000');
        $session->set('2fa_expires_at', time() + 600);
        $session->save();

        $client->request('POST', '/2fa/resend', [
            '_token' => $this->csrfToken($client, '2fa_resend'),
        ]);

        $this->assertResponseRedirects('/2fa/verify');
    }

    public function testResendRedirectsToDashboardIfNoPending(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        [$user] = $this->createUser();
        $client->loginUser($user);

        $client->request('GET', '/dashboard');

        $client->request('POST', '/2fa/resend', [
            '_token' => $this->csrfToken($client, '2fa_resend'),
        ]);

        $this->assertResponseRedirects('/dashboard');
    }
}
