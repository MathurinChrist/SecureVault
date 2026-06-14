<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
{
    public function testHomepageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }

    public function testFeaturesPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/features');

        $this->assertResponseIsSuccessful();
    }

    public function testSecurityPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/security');

        $this->assertResponseIsSuccessful();
    }

    public function testPricingPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/pricing');

        $this->assertResponseIsSuccessful();
    }

    public function testHomepageIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseStatusCodeSame(200);
    }
}
