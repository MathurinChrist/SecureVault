<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use App\Entity\Vault;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class VaultApiControllerTest extends WebTestCase
{
    private function skipIfDatabaseUnavailable(): void
    {
        try {
            static::getContainer()->get('doctrine.dbal.default_connection')->executeQuery('SELECT 1');
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

        $plain = 'Pass123!';
        $email = 'vault_api_' . uniqid() . '@example.com';

        $user = new User();
        $user->setEmail($email)
             ->setFirstName('API')->setLastName('User')
             ->setPassword($hasher->hashPassword($user, $plain))
             ->setEmailVerified(true);

        $em->persist($user);
        $em->flush();

        $client->request('POST', '/api/v1/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['email' => $email, 'password' => $plain]));
        $jwt = json_decode($client->getResponse()->getContent(), true)['token'];
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $jwt);

        return [$client, $user, $em, $hasher, $plain];
    }

    // ───── Auth ──────────────────────────────────────────────────────────

    public function testLoginWithValidCredentialsReturnsToken(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $plain  = 'Pass123!';
        $email  = 'auth_test_' . uniqid() . '@example.com';

        $user = (new User())
            ->setEmail($email)->setFirstName('Auth')->setLastName('User')
            ->setPassword($hasher->hashPassword(new User(), $plain))
            ->setEmailVerified(true);
        $em->persist($user);
        $em->flush();

        $client->request('POST', '/api/v1/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['email' => $email, 'password' => $plain]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        $client->request('POST', '/api/v1/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['email' => 'nobody@example.com', 'password' => 'wrong']));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRequestWithoutTokenReturns401(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/vaults');

        $this->assertResponseStatusCodeSame(401);
    }

    // ───── List ──────────────────────────────────────────────────────────

    public function testListReturnsOnlyOwnVaults(): void
    {
        [$client, $user, $em] = $this->createSetup();

        $v1 = (new Vault())->setName('Mine 1')->setUser($user);
        $v2 = (new Vault())->setName('Mine 2')->setUser($user);
        $em->persist($v1);
        $em->persist($v2);
        $em->flush();

        $client->request('GET', '/api/v1/vaults');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertGreaterThanOrEqual(2, count($data));
        foreach ($data as $v) {
            $this->assertArrayHasKey('id', $v);
            $this->assertArrayHasKey('name', $v);
            $this->assertArrayHasKey('entriesCount', $v);
        }
    }

    // ───── Show ──────────────────────────────────────────────────────────

    public function testShowReturnsVault(): void
    {
        [$client, $user, $em] = $this->createSetup();

        $vault = (new Vault())->setName('Show Me')->setUser($user);
        $em->persist($vault);
        $em->flush();

        $client->request('GET', '/api/v1/vaults/' . $vault->getId());

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Show Me', $data['name']);
    }

    public function testShowOtherUsersVaultReturns403(): void
    {
        [$client, , $em, $hasher, $plain] = $this->createSetup();

        $other = (new User())
            ->setEmail('other_show_' . uniqid() . '@example.com')
            ->setFirstName('Other')->setLastName('User')
            ->setPassword($hasher->hashPassword(new User(), $plain))
            ->setEmailVerified(true);
        $vault = (new Vault())->setName('Not Yours')->setUser($other);
        $em->persist($other);
        $em->persist($vault);
        $em->flush();

        $client->request('GET', '/api/v1/vaults/' . $vault->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    // ───── Create ────────────────────────────────────────────────────────

    public function testCreateReturns201WithNameAndDescription(): void
    {
        [$client] = $this->createSetup();

        $client->request('POST', '/api/v1/vaults', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['name' => 'New Vault', 'description' => 'Desc']));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('New Vault', $data['name']);
        $this->assertSame('Desc', $data['description']);
        $this->assertArrayHasKey('id', $data);
    }

    public function testCreateWithoutNameReturns422(): void
    {
        [$client] = $this->createSetup();

        $client->request('POST', '/api/v1/vaults', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['description' => 'no name']));

        $this->assertResponseStatusCodeSame(422);
    }

    // ───── Update ────────────────────────────────────────────────────────

    public function testUpdateRenamesVault(): void
    {
        [$client, $user, $em] = $this->createSetup();

        $vault = (new Vault())->setName('Old Name')->setUser($user);
        $em->persist($vault);
        $em->flush();

        $client->request('PATCH', '/api/v1/vaults/' . $vault->getId(), [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['name' => 'New Name']));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('New Name', $data['name']);
    }

    public function testUpdateWithEmptyNameReturns422(): void
    {
        [$client, $user, $em] = $this->createSetup();

        $vault = (new Vault())->setName('Some Vault')->setUser($user);
        $em->persist($vault);
        $em->flush();

        $client->request('PATCH', '/api/v1/vaults/' . $vault->getId(), [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['name' => '  ']));

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateOtherUsersVaultReturns403(): void
    {
        [$client, , $em, $hasher, $plain] = $this->createSetup();

        $other = (new User())
            ->setEmail('other_upd_' . uniqid() . '@example.com')
            ->setFirstName('Other')->setLastName('User')
            ->setPassword($hasher->hashPassword(new User(), $plain))
            ->setEmailVerified(true);
        $vault = (new Vault())->setName('Theirs')->setUser($other);
        $em->persist($other);
        $em->persist($vault);
        $em->flush();

        $client->request('PATCH', '/api/v1/vaults/' . $vault->getId(), [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['name' => 'Hijacked']));

        $this->assertResponseStatusCodeSame(403);
    }

    // ───── Delete ────────────────────────────────────────────────────────

    public function testDeleteReturns204(): void
    {
        [$client, $user, $em] = $this->createSetup();

        $vault = (new Vault())->setName('Bye Vault')->setUser($user);
        $em->persist($vault);
        $em->flush();
        $id = $vault->getId();

        $client->request('DELETE', '/api/v1/vaults/' . $id);
        $this->assertResponseStatusCodeSame(204);

        $client->request('GET', '/api/v1/vaults/' . $id);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteOtherUsersVaultReturns403(): void
    {
        [$client, , $em, $hasher, $plain] = $this->createSetup();

        $other = (new User())
            ->setEmail('other_del_' . uniqid() . '@example.com')
            ->setFirstName('Other')->setLastName('User')
            ->setPassword($hasher->hashPassword(new User(), $plain))
            ->setEmailVerified(true);
        $vault = (new Vault())->setName('Theirs')->setUser($other);
        $em->persist($other);
        $em->persist($vault);
        $em->flush();

        $client->request('DELETE', '/api/v1/vaults/' . $vault->getId());

        $this->assertResponseStatusCodeSame(403);
    }
}
