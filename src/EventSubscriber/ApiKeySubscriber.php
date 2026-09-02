<?php


namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiKeySubscriber implements EventSubscriberInterface
{
  /**
   * Reçoit la clé API attendue depuis la configuration Symfony.
   *
   * @param string $apiKey Clé API stockée côté serveur.
   */
  public function __construct(private readonly string $apiKey)
  {
  }

  /**
   * Déclare les événements Symfony écoutés par ce subscriber.
   *
   * @return array<string, array{string, int}> Evénement et méthode appelée.
   */
  public static function getSubscribedEvents(): array
  {
    return [
        KernelEvents::REQUEST => ['onKernelRequest', 8],
    ];
  }

  /**
   * Contrôle le token Bearer avant l'accès aux routes protégées.
   *
   * @param RequestEvent $event Evénement contenant la requête HTTP.
   * @return void
   */
  public function onKernelRequest(RequestEvent $event): void
  {
    // Lecture requête API
    $request = $event->getRequest();
    $path = $request->getPathInfo();

    // Protège uniquement l'API
    if (!str_starts_with($path, '/api') && $path !== '/upload') {
      return;
    }

    // Lecture token Bearer
    $auth = $request->headers->get('Authorization', '');
    $token = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';

    // Contrôle token API
    if ($this->apiKey === '' || !hash_equals($this->apiKey, $token)) {
      // Retour erreur JSON
      $event->setResponse(new JsonResponse([
          'result' => 'error',
          'message' => 'Unauthorized',
      ], 401));
    }
  }
}
