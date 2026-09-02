<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\TestDox;

final class FileControllerTest extends ApiTestCase
{
    #[TestDox('API rejects upload without file')]
    public function testApiRejectsUploadWithoutFile(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/upload',
            ['date' => '2026-07-01'],
            [],
            $this->authenticatedServer()
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'result' => 'error',
            'message' => 'No files provided',
        ], $this->jsonResponse($client));
    }

    #[TestDox('API rejects invalid month')]
    public function testApiRejectsInvalidMonth(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/photos/month?month=2026/07',
            [],
            [],
            $this->authenticatedServer()
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'result' => 'error',
            'message' => 'Missing/invalid month (YYYY-MM)',
        ], $this->jsonResponse($client));
    }

    #[TestDox('API rejects invalid date')]
    public function testApiRejectsInvalidDate(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/photos?date=not-a-date',
            [],
            [],
            $this->authenticatedServer()
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'result' => 'error',
            'message' => 'Invalid date format, expected YYYY-MM-DD',
        ], $this->jsonResponse($client));
    }

    #[TestDox('API rejects invalid photo path (ex ../../.env)')]
    public function testApiRejectsInvalidPhotoPath(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/photo?path='.rawurlencode('../../.env'),
            [],
            [],
            $this->authenticatedServer()
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'result' => 'error',
            'message' => 'Invalid path',
        ], $this->jsonResponse($client));
    }
}
