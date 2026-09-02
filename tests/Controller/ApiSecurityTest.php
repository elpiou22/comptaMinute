<?php

namespace App\Tests\Controller;

final class ApiSecurityTest extends ApiTestCase
{
    public function testApiRejectsRequestWithoutBearerToken(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/photos/month?month=2026-07', [], [], [
            'HTTP_X_USER_KEY' => self::USER_KEY,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame([
            'result' => 'error',
            'message' => 'Unauthorized',
        ], $this->jsonResponse($client));
    }

    public function testApiRejectsUnknownUserKey(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/photos/month?month=2026-07', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.self::API_TOKEN,
            'HTTP_X_USER_KEY' => 'unknown-user',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame([
            'result' => 'error',
            'message' => 'Unauthorized user key',
        ], $this->jsonResponse($client));
    }

    public function testApiRejectsMissingUserKey(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/photos/month?month=2026-07', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.self::API_TOKEN,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame([
            'result' => 'error',
            'message' => 'Missing user key',
        ], $this->jsonResponse($client));
    }
}
