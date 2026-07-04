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
        $this->skipIfDatabaseUnavailable();
        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $plainPassword = 'Pass123!';
        $email = 'api_pw_' . uniqid() . '@example.com';

        $user = new User();
        $user->setEmail($email)
             ->setFirstName('API')->setLastName('User')
             ->setPassword($hasher->hashPassword($user, $plainPassword))
             ->setEmailVerified(true);

        $vault = new Vault();
        $vault->setName('API Test Vault')->setUser($user);

        $em->persist($user);
        $em->persist($vault);
        $em->flush();

        // Obtain JWT so all requests to the stateless API firewall stay authenticated
        $client->request('POST', '/api/v1/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['email' => $email, 'password' => $plainPassword]));
        $jwt = json_decode($client->getResponse()->getContent(), true)['token'];
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $jwt);

        return [$client, $user, $vault];
    }

    public function testPasswordListReturnsEmptyArray(): void
    {
        [$client, , $vault] = $this->createSetup();

        $client->request('GET', '/api/v1/vaults/' . $vault->getId() . '/passwords');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    public function testCreatePasswordReturns201(): void
    {
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
        [$client, , $vault] = $this->createSetup();

        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $plainPassword = 'Pass123!';
        $other = new User();
        $other->setEmail('other_pw_' . uniqid() . '@example.com')
              ->setFirstName('Other')->setLastName('User')
              ->setPassword($hasher->hashPassword($other, $plainPassword))
              ->setEmailVerified(true);
        $em->persist($other);
        $em->flush();

        // Switch to the other user's JWT
        $client->request('POST', '/api/v1/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['email' => $other->getEmail(), 'password' => $plainPassword]));
        $otherJwt = json_decode($client->getResponse()->getContent(), true)['token'];
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $otherJwt);

        $client->request('GET', '/api/v1/vaults/' . $vault->getId() . '/passwords');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testPasswordListWithoutAuthReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/vaults/1/passwords');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUpdatePasswordTitle(): void
    {
        [$client, , $vault] = $this->createSetup();

        $client->request('POST', '/api/v1/vaults/' . $vault->getId() . '/passwords', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['title' => 'Original', 'password' => 'Secret1']));
        $id = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('PATCH', '/api/v1/vaults/' . $vault->getId() . '/passwords/' . $id, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['title' => 'Updated']));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Updated', $data['title']);
    }

    public function testUpdatePasswordValue(): void
    {
        [$client, , $vault] = $this->createSetup();

        $client->request('POST', '/api/v1/vaults/' . $vault->getId() . '/passwords', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['title' => 'PwdChange', 'password' => 'OldPass!']));
        $id = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('PATCH', '/api/v1/vaults/' . $vault->getId() . '/passwords/' . $id, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['password' => 'NewPass!']));
        $this->assertResponseIsSuccessful();

        // Show endpoint should return new decrypted value
        $client->request('GET', '/api/v1/vaults/' . $vault->getId() . '/passwords/' . $id);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('NewPass!', $data['password']);
    }

    public function testUpdatePasswordWithEmptyTitleReturns422(): void
    {
        [$client, , $vault] = $this->createSetup();

        $client->request('POST', '/api/v1/vaults/' . $vault->getId() . '/passwords', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['title' => 'ToUpdate', 'password' => 'pass']));
        $id = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('PATCH', '/api/v1/vaults/' . $vault->getId() . '/passwords/' . $id, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['title' => '']));

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdatePasswordOnAnotherUsersVaultReturns403(): void
    {
        [$client, , $vault] = $this->createSetup();

        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $plain  = 'Pass123!';

        $other = (new User())
            ->setEmail('other_patch_' . uniqid() . '@example.com')
            ->setFirstName('Other')->setLastName('User')
            ->setPassword($hasher->hashPassword(new User(), $plain))
            ->setEmailVerified(true);
        $em->persist($other);
        $em->flush();

        // Login as other user
        $client->request('POST', '/api/v1/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['email' => $other->getEmail(), 'password' => $plain]));
        $otherJwt = json_decode($client->getResponse()->getContent(), true)['token'];
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $otherJwt);

        $client->request('PATCH', '/api/v1/vaults/' . $vault->getId() . '/passwords/1', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['title' => 'Hijacked']));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeletePasswordOnAnotherUsersVaultReturns403(): void
    {
        [$client, , $vault] = $this->createSetup();

        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $plain  = 'Pass123!';

        $other = (new User())
            ->setEmail('other_del2_' . uniqid() . '@example.com')
            ->setFirstName('Other')->setLastName('User')
            ->setPassword($hasher->hashPassword(new User(), $plain))
            ->setEmailVerified(true);
        $em->persist($other);
        $em->flush();

        $client->request('POST', '/api/v1/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['email' => $other->getEmail(), 'password' => $plain]));
        $otherJwt = json_decode($client->getResponse()->getContent(), true)['token'];
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $otherJwt);

        $client->request('DELETE', '/api/v1/vaults/' . $vault->getId() . '/passwords/1');

        $this->assertResponseStatusCodeSame(403);
    }
}
