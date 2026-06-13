<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use App\Entity\Vault;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordApiControllerTest extends WebTestCase
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

    private function createSetup(): array
    {
        $client = static::createClient();
        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('api_pw_' . uniqid() . '@example.com')
             ->setFirstName('API')->setLastName('User')
             ->setPassword($hasher->hashPassword($user, 'Pass123!'));

        $vault = new Vault();
        $vault->setName('API Test Vault')->setUser($user);

        $em->persist($user);
        $em->persist($vault);
        $em->flush();

        $client->loginUser($user);

        return [$client, $user, $vault];
    }

    public function testPasswordListReturnsEmptyArray(): void
    {
        $this->skipIfDatabaseUnavailable();
        [$client, , $vault] = $this->createSetup();

        $client->request('GET', '/api/v1/vaults/' . $vault->getId() . '/passwords');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    public function testCreatePasswordReturns201(): void
    {
        $this->skipIfDatabaseUnavailable();
        [$client, , $vault] = $this->createSetup();

        $client->request(
            'POST',
            '/api/v1/vaults/' . $vault->getId() . '/passwords',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'title'    => 'GitHub',
                'username' => 'johndoe',
                'password' => 'S3cr3t!',
                'url'      => 'https://github.com',
            ]),
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('GitHub', $data['title']);
        $this->assertSame('johndoe', $data['username']);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayNotHasKey('password', $data, 'Plain password must not appear in create response');
    }

    public function testCreatePasswordWithoutTitleReturns422(): void
    {
        $this->skipIfDatabaseUnavailable();
        [$client, , $vault] = $this->createSetup();

        $client->request(
            'POST',
            '/api/v1/vaults/' . $vault->getId() . '/passwords',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'foo', 'password' => 'bar']),
        );

        $this->assertResponseStatusCodeSame(422);
    }

    public function testShowPasswordReturnsDecryptedValue(): void
    {
        $this->skipIfDatabaseUnavailable();
        [$client, , $vault] = $this->createSetup();

        $client->request(
            'POST',
            '/api/v1/vaults/' . $vault->getId() . '/passwords',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'ShowTest', 'password' => 'MyPlainPass!']),
        );

        $id = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('GET', '/api/v1/vaults/' . $vault->getId() . '/passwords/' . $id);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('password', $data);
        $this->assertSame('MyPlainPass!', $data['password']);
    }

    public function testDeletePasswordReturns204(): void
    {
        $this->skipIfDatabaseUnavailable();
        [$client, , $vault] = $this->createSetup();

        $client->request(
            'POST',
            '/api/v1/vaults/' . $vault->getId() . '/passwords',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'ToDelete', 'password' => 'bye']),
        );

        $id = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('DELETE', '/api/v1/vaults/' . $vault->getId() . '/passwords/' . $id);
        $this->assertResponseStatusCodeSame(204);

        $client->request('GET', '/api/v1/vaults/' . $vault->getId() . '/passwords/' . $id);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCannotAccessAnotherUsersVaultPasswords(): void
    {
        $this->skipIfDatabaseUnavailable();
        [$client, , $vault] = $this->createSetup();

        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $other = new User();
        $other->setEmail('other_pw_' . uniqid() . '@example.com')
              ->setFirstName('Other')->setLastName('User')
              ->setPassword($hasher->hashPassword($other, 'Pass123!'));
        $em->persist($other);
        $em->flush();

        $otherClient = static::createClient();
        $otherClient->loginUser($other);

        $otherClient->request('GET', '/api/v1/vaults/' . $vault->getId() . '/passwords');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testPasswordListWithoutAuthReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/vaults/1/passwords');

        $this->assertResponseStatusCodeSame(401);
    }
}
