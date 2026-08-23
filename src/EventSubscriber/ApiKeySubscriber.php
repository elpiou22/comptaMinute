<?php


namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiKeySubscriber implements EventSubscriberInterface
{
  public function __construct(private readonly string $apiKey)
  {
  }

  public static function getSubscribedEvents(): array
  {
    return [
        KernelEvents::REQUEST => ['onKernelRequest', 8],
    ];
  }

  public function onKernelRequest(RequestEvent $event): void
  {
    $request = $event->getRequest();
    $path = $request->getPathInfo();

    // Protège uniquement l'API
    if (!str_starts_with($path, '/api') && $path !== '/upload') {
      return;
    }

    $auth = $request->headers->get('Authorization', '');
    $token = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';

    if ($this->apiKey === '' || !hash_equals($this->apiKey, $token)) {
      $event->setResponse(new JsonResponse([
          'result' => 'error',
          'message' => 'Unauthorized',
      ], 401));
    }
  }
}
