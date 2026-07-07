<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use App\Entity\UserSession;

class SessionApiControllerTest extends ApiTestCase
{
    private function addSession(User $user, bool $active = true): UserSession
    {
        $session = (new UserSession())
            ->setUser($user)
            ->setIpAddress('10.0.0.1')
            ->setIsActive($active);
        $this->em()->persist($session);
        $this->em()->flush();

        return $session;
    }

    public function testListRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/sessions');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testListReturnsOwnActiveSessions(): void
    {
        [$client, $user] = $this->createAuthenticatedClient();
        $this->addSession($user);

        $client->request('GET', '/api/v1/sessions');

        $this->assertResponseIsSuccessful();
        $data = $this->json($client);
        $this->assertGreaterThanOrEqual(1, count($data));
        $this->assertArrayHasKey('ipAddress', $data[0]);
        $this->assertArrayHasKey('isActive', $data[0]);
    }

    public function testRevokeOwnSessionReturns204AndMarksInactive(): void
    {
        [$client, $user] = $this->createAuthenticatedClient();
        $session = $this->addSession($user);
        $id = $session->getId();

        $client->request('DELETE', '/api/v1/sessions/' . $id);
        $this->assertResponseStatusCodeSame(204);

        $this->em()->clear();
        $this->assertFalse($this->em()->find(UserSession::class, $id)->isActive());
    }

    public function testRevokeOtherUsersSessionReturns403(): void
    {
        [$client] = $this->createAuthenticatedClient();

        $other   = $this->createUser();
        $session = $this->addSession($other);

        $client->request('DELETE', '/api/v1/sessions/' . $session->getId());

        $this->assertResponseStatusCodeSame(403);
    }
}
