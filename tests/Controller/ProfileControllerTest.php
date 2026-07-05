<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProfileControllerTest extends WebTestCase
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

    private function createAndLoginUser(string $suffix = ''): array
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('profile_' . $suffix . uniqid() . '@example.com')
             ->setFirstName('John')
             ->setLastName('Doe')
             ->setPassword($hasher->hashPassword($user, 'Pass123!'))
             ->setEmailVerified(true);

        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return [$client, $user];
    }

    public function testProfileRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile');

        $this->assertResponseRedirects('/login');
    }

    public function testProfileLoadsForAuthenticatedUser(): void
    {
        [$client] = $this->createAndLoginUser();
        $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
    }

    public function testProfileContainsProfileForm(): void
    {
        [$client] = $this->createAndLoginUser();
        $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testProfileContainsPasswordForm(): void
    {
        [$client] = $this->createAndLoginUser();
        $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(3, 'form');
    }

    public function testProfileUpdateRedirectsBack(): void
    {
        [$client, $user] = $this->createAndLoginUser('upd');
        $client->request('GET', '/profile');

        $client->submitForm('Sauvegarder les modifications', [
            'user_profile[firstName]' => 'UpdatedFirst',
            'user_profile[lastName]'  => 'UpdatedLast',
        ]);

        $this->assertResponseRedirects('/profile');
    }
}
