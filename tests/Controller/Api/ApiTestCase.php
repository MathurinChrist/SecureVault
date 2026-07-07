<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Shared base for JSON API functional tests. Boots a real HTTP client, performs
 * the genuine JWT login flow and attaches the bearer token to the client.
 *
 * Test isolation relies on unique emails (no DB rollback); the DB is provisioned
 * externally via `make test-db-setup`.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected function skipIfDatabaseUnavailable(): void
    {
        try {
            static::getContainer()->get('doctrine.dbal.default_connection')->executeQuery('SELECT 1');
        } catch (\Exception) {
            $this->markTestSkipped('Database not available.');
        }
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function hasher(): UserPasswordHasherInterface
    {
        return static::getContainer()->get(UserPasswordHasherInterface::class);
    }

    /**
     * Persist a verified user with a hashed password.
     */
    protected function createUser(string $plain = 'Pass123!', ?string $email = null): User
    {
        $user = (new User())
            ->setEmail($email ?? ('api_' . uniqid() . '@example.com'))
            ->setFirstName('API')
            ->setLastName('User')
            ->setEmailVerified(true);
        $user->setPassword($this->hasher()->hashPassword($user, $plain));

        $em = $this->em();
        $em->persist($user);
        $em->flush();

        return $user;
    }

    /**
     * Log the given user in via /api/v1/auth/login and attach the JWT to the client.
     */
    protected function authenticate(KernelBrowser $client, User $user, string $plain = 'Pass123!'): void
    {
        $client->request('POST', '/api/v1/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email'    => $user->getEmail(),
            'password' => $plain,
        ]));
        $token = json_decode($client->getResponse()->getContent(), true)['token'] ?? null;
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
    }

    /**
     * Create a client authenticated as a fresh user. Returns [client, user].
     *
     * @return array{0: KernelBrowser, 1: User}
     */
    protected function createAuthenticatedClient(string $plain = 'Pass123!'): array
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        $user = $this->createUser($plain);
        $this->authenticate($client, $user, $plain);

        return [$client, $user];
    }

    /**
     * Decode the last JSON response body.
     */
    protected function json(KernelBrowser $client): array
    {
        return json_decode($client->getResponse()->getContent(), true) ?? [];
    }

    protected function requestJson(KernelBrowser $client, string $method, string $uri, ?array $body = null): void
    {
        $client->request($method, $uri, [], [], ['CONTENT_TYPE' => 'application/json'], $body === null ? '' : json_encode($body));
    }
}
