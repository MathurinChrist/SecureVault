<?php

namespace App\Tests\Controller\Api;

use App\Entity\Category;

class CategoryApiControllerTest extends ApiTestCase
{
    public function testListRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/categories');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testListReturnsCategories(): void
    {
        [$client] = $this->createAuthenticatedClient();

        $cat = (new Category())->setName('Cat ' . uniqid());
        $this->em()->persist($cat);
        $this->em()->flush();

        $client->request('GET', '/api/v1/categories');

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThanOrEqual(1, count($this->json($client)));
    }

    public function testCreateReturns201(): void
    {
        [$client] = $this->createAuthenticatedClient();

        $this->requestJson($client, 'POST', '/api/v1/categories', ['name' => 'Banking', 'color' => '#2f7d5b']);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->json($client);
        $this->assertSame('Banking', $data['name']);
        $this->assertSame('#2f7d5b', $data['color']);
        $this->assertArrayHasKey('id', $data);
    }

    public function testCreateWithoutNameReturns422(): void
    {
        [$client] = $this->createAuthenticatedClient();

        $this->requestJson($client, 'POST', '/api/v1/categories', ['color' => '#fff']);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testShowReturnsCategory(): void
    {
        [$client] = $this->createAuthenticatedClient();

        $cat = (new Category())->setName('Show ' . uniqid());
        $this->em()->persist($cat);
        $this->em()->flush();

        $client->request('GET', '/api/v1/categories/' . $cat->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSame($cat->getName(), $this->json($client)['name']);
    }

    public function testUpdateRenamesCategory(): void
    {
        [$client] = $this->createAuthenticatedClient();

        $cat = (new Category())->setName('Old ' . uniqid());
        $this->em()->persist($cat);
        $this->em()->flush();

        $this->requestJson($client, 'PATCH', '/api/v1/categories/' . $cat->getId(), ['name' => 'Renamed']);

        $this->assertResponseIsSuccessful();
        $this->assertSame('Renamed', $this->json($client)['name']);
    }

    public function testUpdateWithEmptyNameReturns422(): void
    {
        [$client] = $this->createAuthenticatedClient();

        $cat = (new Category())->setName('X ' . uniqid());
        $this->em()->persist($cat);
        $this->em()->flush();

        $this->requestJson($client, 'PATCH', '/api/v1/categories/' . $cat->getId(), ['name' => '  ']);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testDeleteReturns204(): void
    {
        [$client] = $this->createAuthenticatedClient();

        $cat = (new Category())->setName('Bye ' . uniqid());
        $this->em()->persist($cat);
        $this->em()->flush();
        $id = $cat->getId();

        $client->request('DELETE', '/api/v1/categories/' . $id);
        $this->assertResponseStatusCodeSame(204);

        $client->request('GET', '/api/v1/categories/' . $id);
        $this->assertResponseStatusCodeSame(404);
    }
}
