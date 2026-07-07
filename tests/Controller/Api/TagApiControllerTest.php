<?php

namespace App\Tests\Controller\Api;

use App\Entity\Tag;

class TagApiControllerTest extends ApiTestCase
{
    public function testListRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/tags');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateReturns201(): void
    {
        [$client] = $this->createAuthenticatedClient();

        $this->requestJson($client, 'POST', '/api/v1/tags', ['name' => 'urgent', 'color' => '#c00']);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->json($client);
        $this->assertSame('urgent', $data['name']);
        $this->assertArrayHasKey('id', $data);
    }

    public function testCreateWithoutNameReturns422(): void
    {
        [$client] = $this->createAuthenticatedClient();

        $this->requestJson($client, 'POST', '/api/v1/tags', ['color' => '#fff']);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testShowReturnsTag(): void
    {
        [$client] = $this->createAuthenticatedClient();

        $tag = (new Tag())->setName('Show ' . uniqid());
        $this->em()->persist($tag);
        $this->em()->flush();

        $client->request('GET', '/api/v1/tags/' . $tag->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSame($tag->getName(), $this->json($client)['name']);
    }

    public function testUpdateRenamesTag(): void
    {
        [$client] = $this->createAuthenticatedClient();

        $tag = (new Tag())->setName('Old ' . uniqid());
        $this->em()->persist($tag);
        $this->em()->flush();

        $this->requestJson($client, 'PATCH', '/api/v1/tags/' . $tag->getId(), ['name' => 'Renamed']);

        $this->assertResponseIsSuccessful();
        $this->assertSame('Renamed', $this->json($client)['name']);
    }

    public function testDeleteReturns204(): void
    {
        [$client] = $this->createAuthenticatedClient();

        $tag = (new Tag())->setName('Bye ' . uniqid());
        $this->em()->persist($tag);
        $this->em()->flush();
        $id = $tag->getId();

        $client->request('DELETE', '/api/v1/tags/' . $id);
        $this->assertResponseStatusCodeSame(204);

        $client->request('GET', '/api/v1/tags/' . $id);
        $this->assertResponseStatusCodeSame(404);
    }
}
