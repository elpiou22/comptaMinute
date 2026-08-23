<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ApiKeySubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiKeySubscriberTest extends TestCase
{
    public function testPublicRouteIsIgnored(): void
    {
        $event = $this->createEvent('/');

        (new ApiKeySubscriber('secret'))->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testProtectedRouteRejectsMissingToken(): void
    {
        $event = $this->createEvent('/api/photos');

        (new ApiKeySubscriber('secret'))->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        self::assertInstanceOf(JsonResponse::class, $event->getResponse());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $event->getResponse()->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"result":"error","message":"Unauthorized"}',
            (string) $event->getResponse()->getContent()
        );
    }

    public function testProtectedRouteAcceptsValidToken(): void
    {
        $event = $this->createEvent('/api/photos', 'Bearer secret');

        (new ApiKeySubscriber('secret'))->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    private function createEvent(string $path, ?string $authorization = null): RequestEvent
    {
        $request = Request::create($path);
        if ($authorization !== null) {
            $request->headers->set('Authorization', $authorization);
        }

        return new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function createKernel(): HttpKernelInterface
    {
        return new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }
}
