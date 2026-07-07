<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\Vault;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class VaultControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    private function skipIfDatabaseUnavailable(): void
    {
        try {
            $conn = static::getContainer()->get('doctrine.dbal.default_connection');
            $conn->executeQuery('SELECT 1');
        } catch (\Exception) {
            $this->markTestSkipped('Database not available.');
        }
    }

    private function createAuthenticatedClient(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        /** @var EntityManagerInterface $em */
        $em     = static::getContainer()->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $plainPassword = 'TestPass123!';
        $email = 'vaulttest_' . uniqid() . '@example.com';

        $user = new User();
        $user->setEmail($email)
             ->setFirstName('Test')
             ->setLastName('User')
             ->setPassword($hasher->hashPassword($user, $plainPassword))
             ->setEmailVerified(true);

        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        // Obtain JWT so multi-request API tests stay authenticated across kernel reboots
        $client->request('POST', '/api/v1/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['email' => $email, 'password' => $plainPassword]));
        $jwt = json_decode($client->getResponse()->getContent(), true)['token'];
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $jwt);

        return $client;
    }

    public function testVaultsIndexRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/vaults');

        $this->assertResponseRedirects('/login');
    }

    public function testVaultsIndexLoadsWhenAuthenticated(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/vaults');

        $this->assertResponseIsSuccessful();
    }

    public function testCreateVaultSucceeds(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request('GET', '/vaults');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/vaults/new"]')->form([
            'vault[name]'        => 'Test Vault',
            'vault[description]' => 'A test vault',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testVaultShowRequiresOwnership(): void
    {
        $client = $this->createAuthenticatedClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $otherUser = new User();
        $otherUser->setEmail('other_' . uniqid() . '@example.com')
                  ->setFirstName('Other')
                  ->setLastName('User')
                  ->setPassword('hashed')
                  ->setEmailVerified(true);

        $vault = new Vault();
        $vault->setName('Private Vault')->setUser($otherUser);

        $em->persist($otherUser);
        $em->persist($vault);
        $em->flush();

        $client->request('GET', '/vaults/' . $vault->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testApiVaultListReturnsJson(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request(
            'GET',
            '/api/v1/vaults',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJson($client->getResponse()->getContent());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testApiCreateVaultReturns201(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request(
            'POST',
            '/api/v1/vaults',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'API Vault', 'description' => 'via API']),
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('API Vault', $data['name']);
        $this->assertArrayHasKey('id', $data);
    }

    public function testApiCreateVaultWithoutNameReturns422(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request(
            'POST',
            '/api/v1/vaults',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['description' => 'no name']),
        );

        $this->assertResponseStatusCodeSame(422);
    }

    public function testApiDeleteVault(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'POST',
            '/api/v1/vaults',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'To Delete']),
        );

        $id = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('DELETE', '/api/v1/vaults/' . $id);

        $this->assertResponseStatusCodeSame(204);
    }
}
