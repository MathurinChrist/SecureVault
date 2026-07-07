<?php

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OpenApiDocTest extends WebTestCase
{
    public function testSpecIsPublicAndValid(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/doc.json');

        $this->assertResponseIsSuccessful();
        $spec = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('openapi', $spec);
        $this->assertArrayHasKey('bearerAuth', $spec['components']['securitySchemes'] ?? []);
        $this->assertArrayHasKey('/api/v1/shares', $spec['paths']);
        $this->assertArrayHasKey('/api/v1/categories', $spec['paths']);
    }

    public function testSwaggerUiIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/doc');

        $this->assertResponseIsSuccessful();
    }
}
