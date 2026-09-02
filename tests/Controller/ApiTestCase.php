<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected const API_TOKEN = 'test-token';
    protected const USER_KEY = 'test-user';

    private static ?string $photosDir = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::configureTestEnvironment();
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::configureTestEnvironment();
        self::removeDirectory(self::photosDir());
        mkdir(self::photosDir(), 0777, true);
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        self::removeDirectory(self::photosDir());
        parent::tearDown();
    }

    /**
     * @param array<string, string> $extra
     *
     * @return array<string, string>
     */
    protected function authenticatedServer(array $extra = []): array
    {
        return array_replace([
            'HTTP_AUTHORIZATION' => 'Bearer '.self::API_TOKEN,
            'HTTP_X_USER_KEY' => self::USER_KEY,
            'HTTP_ACCEPT' => 'application/json',
        ], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonResponse(KernelBrowser $client): array
    {
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    private static function configureTestEnvironment(): void
    {
        self::setEnv('APP_ENV', 'test');
        self::setEnv('APP_SECRET', 'test-secret');
        self::setEnv('API_KEY', self::API_TOKEN);
        self::setEnv('USER_KEYS', self::USER_KEY.',legacy');
        self::setEnv('PHOTOS_DIR', self::photosDir());
    }

    private static function photosDir(): string
    {
        if (self::$photosDir === null) {
            self::$photosDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'comptaminute-api-tests';
        }

        return self::$photosDir;
    }

    private static function setEnv(string $name, string $value): void
    {
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv($name.'='.$value);
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }
}
