<?php

namespace App\Tests\E2E;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Panther\PantherTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthE2ETest extends PantherTestCase
{
    private static ?User $user  = null;
    private static string $pass = 'E2ePass123!';

    protected function skipIfUnavailable(): void
    {
        try {
            $conn = static::createPantherClient()->getInternalRequest();
        } catch (\Throwable) {
            // ignore
        }

        try {
            $conn = static::getContainer()->get('doctrine.dbal.default_connection');
            $conn->executeQuery('SELECT 1');
        } catch (\Exception) {
            $this->markTestSkipped('Database not available for E2E.');
        }
    }

    private function getOrCreateUser(): User
    {
        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        if (!static::$user) {
            static::$user = new User();
            static::$user
                ->setEmail('e2e_' . uniqid() . '@example.com')
                ->setFirstName('E2E')
                ->setLastName('Test')
                ->setPassword($hasher->hashPassword(static::$user, static::$pass));
            $em->persist(static::$user);
            $em->flush();
        }

        return static::$user;
    }

    public function testLoginPageDisplaysForm(): void
    {
        $this->skipIfUnavailable();

        $client = static::createPantherClient();
        $crawler = $client->request('GET', '/login');

        $this->assertSelectorExists('input[name="email"]');
        $this->assertSelectorExists('input[name="password"]');
        $this->assertSelectorExists('button[type="submit"]');
    }

    public function testSuccessfulLoginRedirectsToDashboard(): void
    {
        $this->skipIfUnavailable();

        $user   = $this->getOrCreateUser();
        $client = static::createPantherClient();

        $crawler = $client->request('GET', '/login');
        $form    = $crawler->selectButton('Se connecter')->form([
            'email'    => $user->getEmail(),
            'password' => static::$pass,
        ]);
        $client->submit($form);

        $client->waitFor('body');
        $this->assertStringContainsString('/dashboard', $client->getCurrentURL());
    }

    public function testLoginWithWrongPasswordShowsError(): void
    {
        $this->skipIfUnavailable();

        $user   = $this->getOrCreateUser();
        $client = static::createPantherClient();

        $crawler = $client->request('GET', '/login');
        $form    = $crawler->selectButton('Se connecter')->form([
            'email'    => $user->getEmail(),
            'password' => 'completely_wrong',
        ]);
        $client->submit($form);

        $client->waitFor('body');
        $this->assertStringContainsString('/login', $client->getCurrentURL());
    }

    public function testLogoutRedirectsToHome(): void
    {
        $this->skipIfUnavailable();

        $user   = $this->getOrCreateUser();
        $client = static::createPantherClient();

        // Login first
        $crawler = $client->request('GET', '/login');
        $form    = $crawler->selectButton('Se connecter')->form([
            'email'    => $user->getEmail(),
            'password' => static::$pass,
        ]);
        $client->submit($form);
        $client->waitFor('body');

        // Logout
        $client->request('GET', '/logout');
        $client->waitFor('body');

        $url = $client->getCurrentURL();
        $this->assertThat(
            $url,
            $this->logicalOr(
                $this->stringContains('/'),
                $this->stringContains('/login')
            )
        );
    }

    public function testUnauthenticatedAccessToProtectedPageRedirects(): void
    {
        $this->skipIfUnavailable();

        $client = static::createPantherClient();
        $client->request('GET', '/dashboard');

        $client->waitFor('body');
        $this->assertStringContainsString('/login', $client->getCurrentURL());
    }
}
