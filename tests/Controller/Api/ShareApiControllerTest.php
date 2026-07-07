<?php

namespace App\Tests\Controller\Api;

use App\Entity\SharedVault;
use App\Entity\User;
use App\Entity\Vault;
use App\Entity\VaultPermission;

class ShareApiControllerTest extends ApiTestCase
{
    private function permission(string $code = VaultPermission::READ): VaultPermission
    {
        $perm = $this->em()->getRepository(VaultPermission::class)->findOneBy(['code' => $code]);
        if ($perm === null) {
            $perm = (new VaultPermission())->setCode($code)->setName($code);
            $this->em()->persist($perm);
            $this->em()->flush();
        }

        return $perm;
    }

    private function vaultOwnedBy(User $user): Vault
    {
        $vault = (new Vault())->setName('Vault ' . uniqid())->setUser($user);
        $this->em()->persist($vault);
        $this->em()->flush();

        return $vault;
    }

    private function share(Vault $vault, User $sender, User $recipient): SharedVault
    {
        $share = (new SharedVault())
            ->setVault($vault)
            ->setSender($sender)
            ->setRecipient($recipient)
            ->setPermission($this->permission());
        $this->em()->persist($share);
        $this->em()->flush();

        return $share;
    }

    public function testCreateShareReturns201(): void
    {
        [$client, $sender] = $this->createAuthenticatedClient();
        $this->permission();
        $recipient = $this->createUser();
        $vault     = $this->vaultOwnedBy($sender);

        $this->requestJson($client, 'POST', '/api/v1/vaults/' . $vault->getId() . '/share', [
            'email'      => $recipient->getEmail(),
            'permission' => 'READ',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->json($client);
        $this->assertSame($recipient->getEmail(), $data['recipient']['email']);
        $this->assertFalse($data['accepted']);
    }

    public function testCreateShareUnknownEmailReturns404(): void
    {
        [$client, $sender] = $this->createAuthenticatedClient();
        $vault = $this->vaultOwnedBy($sender);

        $this->requestJson($client, 'POST', '/api/v1/vaults/' . $vault->getId() . '/share', [
            'email' => 'nobody_' . uniqid() . '@example.com',
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateShareWithOwnerReturns422(): void
    {
        [$client, $sender] = $this->createAuthenticatedClient();
        $vault = $this->vaultOwnedBy($sender);

        $this->requestJson($client, 'POST', '/api/v1/vaults/' . $vault->getId() . '/share', [
            'email' => $sender->getEmail(),
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateShareOnOtherUsersVaultReturns403(): void
    {
        [$client] = $this->createAuthenticatedClient();
        $owner    = $this->createUser();
        $target   = $this->createUser();
        $vault    = $this->vaultOwnedBy($owner);

        $this->requestJson($client, 'POST', '/api/v1/vaults/' . $vault->getId() . '/share', [
            'email' => $target->getEmail(),
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testListReturnsSentShares(): void
    {
        [$client, $sender] = $this->createAuthenticatedClient();
        $recipient = $this->createUser();
        $vault     = $this->vaultOwnedBy($sender);
        $this->share($vault, $sender, $recipient);

        $client->request('GET', '/api/v1/shares');

        $this->assertResponseIsSuccessful();
        $data = $this->json($client);
        $this->assertArrayHasKey('sent', $data);
        $this->assertGreaterThanOrEqual(1, count($data['sent']));
    }

    public function testAcceptByRecipientReturns200(): void
    {
        // createClient() must run before the kernel is booted via the container.
        [$client, $recipient] = $this->createAuthenticatedClient();
        $sender = $this->createUser();
        $vault  = $this->vaultOwnedBy($sender);
        $share  = $this->share($vault, $sender, $recipient);

        $client->request('POST', '/api/v1/shares/' . $share->getId() . '/accept');

        $this->assertResponseIsSuccessful();
        $this->assertTrue($this->json($client)['accepted']);
    }

    public function testAcceptByNonRecipientReturns403(): void
    {
        // Authenticated as an unrelated third user.
        [$client] = $this->createAuthenticatedClient();
        $sender    = $this->createUser();
        $recipient = $this->createUser();
        $vault     = $this->vaultOwnedBy($sender);
        $share     = $this->share($vault, $sender, $recipient);

        $client->request('POST', '/api/v1/shares/' . $share->getId() . '/accept');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRevokeBySenderReturns204(): void
    {
        [$client, $sender] = $this->createAuthenticatedClient();
        $recipient = $this->createUser();
        $vault     = $this->vaultOwnedBy($sender);
        $share     = $this->share($vault, $sender, $recipient);
        $id        = $share->getId();

        $client->request('DELETE', '/api/v1/shares/' . $id);
        $this->assertResponseStatusCodeSame(204);

        $this->em()->clear();
        $this->assertNull($this->em()->find(SharedVault::class, $id));
    }
}
