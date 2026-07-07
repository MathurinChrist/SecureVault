<?php

namespace App\Tests\Controller\Api;

class ContactApiControllerTest extends ApiTestCase
{
    public function testSubmitReturns201WithoutAuthentication(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        $this->requestJson($client, 'POST', '/api/v1/contact', [
            'name'    => 'Jane Doe',
            'email'   => 'jane@example.com',
            'subject' => 'Question',
            'message' => 'This is a long enough message.',
        ]);

        $this->assertResponseStatusCodeSame(201);
    }

    public function testSubmitMissingFieldReturns422(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        $this->requestJson($client, 'POST', '/api/v1/contact', [
            'name'    => 'Jane',
            'email'   => 'jane@example.com',
            'message' => 'This is a long enough message.',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testSubmitInvalidEmailReturns422(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        $this->requestJson($client, 'POST', '/api/v1/contact', [
            'name'    => 'Jane',
            'email'   => 'not-an-email',
            'subject' => 'Hi',
            'message' => 'This is a long enough message.',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testSubmitShortMessageReturns422(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        $this->requestJson($client, 'POST', '/api/v1/contact', [
            'name'    => 'Jane',
            'email'   => 'jane@example.com',
            'subject' => 'Hi',
            'message' => 'short',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }
}
