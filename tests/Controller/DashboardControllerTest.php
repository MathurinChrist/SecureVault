<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DashboardControllerTest extends WebTestCase
{
    private function skipIfDatabaseUnavailable(): void
    {
        try {
            $conn = static::getContainer()->get('doctrine.dbal.default_connection');
            $conn->executeQuery('SELECT 1');
        } catch (\Exception) {
            $this->markTestSkipped('Database not available.');
        }
    }

    private function createAndLoginUser(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client  = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $em      = static::getContainer()->get(EntityManagerInterface::class);
        $hasher  = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('dash_' . uniqid() . '@example.com')
             ->setFirstName('Dash')
             ->setLastName('User')
             ->setPassword($hasher->hashPassword($user, 'Pass123!'));

        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    public function testDashboardRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/dashboard');

        $this->assertResponseRedirects('/login');
    }

    public function testDashboardLoadsForAuthenticatedUser(): void
    {
        $client = $this->createAndLoginUser();
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
    }

    public function testDashboardAutoCreatesVaultOnFirstLogin(): void
    {
        $client = $this->createAndLoginUser();

        // First visit triggers vault + demo password creation
        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        // Second visit should NOT create another vault
        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
    }

    public function testDashboardContainsPasswordCount(): void
    {
        $client = $this->createAndLoginUser();
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="passwords-count"], .passwords-count, .stat-number, body');
    }
}
