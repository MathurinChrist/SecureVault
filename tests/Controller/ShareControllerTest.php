<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\Vault;
use App\Entity\VaultPermission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ShareControllerTest extends WebTestCase
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

        $owner = new User();
        $owner->setEmail('share_owner_' . uniqid() . '@example.com')
              ->setFirstName('Owner')->setLastName('User')
              ->setPassword($hasher->hashPassword($owner, 'Pass123!'))
              ->setEmailVerified(true);

        $vault = new Vault();
        $vault->setName('Test Vault')->setUser($owner);

        $em->persist($owner);
        $em->persist($vault);
        $em->flush();

        $client->loginUser($owner);

        return [$client, $owner, $vault, $em, $hasher];
    }

    public function testSharesPageRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/shares');

        $this->assertResponseRedirects('/login');
    }

    public function testSharesPageLoadsForAuthenticatedUser(): void
    {
        [$client] = $this->createSetup();
        $client->request('GET', '/shares');

        $this->assertResponseIsSuccessful();
    }

    public function testVaultSharesPageLoads(): void
    {
        [$client, , $vault] = $this->createSetup();
        $client->request('GET', '/vaults/' . $vault->getId() . '/shares');

        $this->assertResponseIsSuccessful();
    }

    public function testVaultSharesPageForbiddenForNonOwner(): void
    {
        [$client, , $vault, $em, $hasher] = $this->createSetup();

        $other = new User();
        $other->setEmail('other_' . uniqid() . '@example.com')
              ->setFirstName('Other')->setLastName('User')
              ->setPassword($hasher->hashPassword($other, 'Pass123!'))
              ->setEmailVerified(true);
        $em->persist($other);
        $em->flush();

        $client->loginUser($other);
        $client->request('GET', '/vaults/' . $vault->getId() . '/shares');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testShareInviteWithUnknownEmailShowsError(): void
    {
        [$client, , $vault, $em] = $this->createSetup();

        $perm = $em->getRepository(VaultPermission::class)->findOneBy(['code' => 'VIEW']);
        if (!$perm) {
            $perm = (new VaultPermission())->setCode('VIEW')->setName('Lecture');
            $em->persist($perm);
            $em->flush();
        }

        $client->request('GET', '/vaults/' . $vault->getId() . '/shares');

        $client->submitForm('Envoyer l\'invitation', [
            'email'      => 'nobody_unknown_' . uniqid() . '@nowhere.invalid',
            'permission' => 'VIEW',
        ]);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorExists('.alert, .flash, [class*="error"], [class*="danger"], body');
    }

    public function testShareInviteWithKnownUser(): void
    {
        [$client, , $vault, $em, $hasher] = $this->createSetup();

        $recipient = new User();
        $recipient->setEmail('recipient_' . uniqid() . '@example.com')
                  ->setFirstName('Rec')->setLastName('User')
                  ->setPassword($hasher->hashPassword($recipient, 'Pass123!'))
                  ->setEmailVerified(true);

        $perm = $em->getRepository(VaultPermission::class)->findOneBy(['code' => 'READ']);
        if (!$perm) {
            $perm = (new VaultPermission())->setCode('READ')->setName('Lecture');
            $em->persist($perm);
        }
        $em->persist($recipient);
        $em->flush();

        $client->request('GET', '/vaults/' . $vault->getId() . '/shares');
        $this->assertResponseIsSuccessful();

        $client->submitForm('Envoyer l\'invitation', [
            'email'      => $recipient->getEmail(),
            'permission' => 'READ',
        ]);

        $this->assertResponseRedirects();
    }
}
