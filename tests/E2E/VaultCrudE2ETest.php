<?php

namespace App\Tests\E2E;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Panther\PantherTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class VaultCrudE2ETest extends PantherTestCase
{
    private static string $pass = 'E2eVault123!';

    private function skipIfUnavailable(): void
    {
        try {
            $conn = static::getContainer()->get('doctrine.dbal.default_connection');
            $conn->executeQuery('SELECT 1');
        } catch (\Exception) {
            $this->markTestSkipped('Database not available for E2E.');
        }
    }

    private function loginAs(string $email): \Symfony\Component\Panther\Client
    {
        $client  = static::createPantherClient();
        $crawler = $client->request('GET', '/login');
        $form    = $crawler->selectButton('Se connecter')->form([
            'email'    => $email,
            'password' => static::$pass,
        ]);
        $client->submit($form);
        $client->waitFor('body');

        return $client;
    }

    private function createTestUser(): User
    {
        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('vault_e2e_' . uniqid() . '@example.com')
             ->setFirstName('Vault')->setLastName('E2E')
             ->setPassword($hasher->hashPassword($user, static::$pass));
        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testVaultsPageLoadsAfterLogin(): void
    {
        $this->skipIfUnavailable();

        $user   = $this->createTestUser();
        $client = $this->loginAs($user->getEmail());

        $client->request('GET', '/vaults');
        $client->waitFor('body');

        $this->assertResponseIsSuccessful();
    }

    public function testCreateVaultFormExists(): void
    {
        $this->skipIfUnavailable();

        $user   = $this->createTestUser();
        $client = $this->loginAs($user->getEmail());

        $client->request('GET', '/vaults/new');
        $client->waitFor('body');

        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name*="name"]');
    }

    public function testCreateAndDisplayVault(): void
    {
        $this->skipIfUnavailable();

        $user    = $this->createTestUser();
        $client  = $this->loginAs($user->getEmail());
        $vaultName = 'E2E Vault ' . uniqid();

        $crawler = $client->request('GET', '/vaults/new');
        $client->waitFor('form');

        $form = $crawler->selectButton('Créer le coffre')->form([
            'vault[name]'        => $vaultName,
            'vault[description]' => 'Created by E2E test',
        ]);
        $client->submit($form);
        $client->waitFor('body');

        // Should redirect to vaults list or vault show
        $this->assertStringNotContainsString('/new', $client->getCurrentURL());
    }
}
