<?php

declare(strict_types=1);

namespace Drupal\mave\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mave\MaveClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves authenticated library requests and short-lived upload credentials.
 */
final class ApiController extends ControllerBase {

  public function __construct(private readonly MaveClient $client) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('mave.client'));
  }

  /**
   * Returns a bounded library page with validated collection navigation.
   */
  public function videos(Request $request): JsonResponse {
    return $this->respond(function () use ($request): array {
      $query = [
        'page' => max(1, $request->query->getInt('page', 1)),
        'per_page' => max(1, min(100, $request->query->getInt('per_page', 24))),
      ];
      $collection = $request->query->getString('collection');
      if ($collection !== '') {
        if (!MaveClient::validEmbedId($collection)) {
          throw new \InvalidArgumentException('Invalid collection ID.');
        }
        $query['collection'] = $collection;
      }
      return $this->client->libraryPage($query);
    });
  }

  /**
   * Issues an upload token for the configured server-side target.
   */
  public function uploadToken(): JsonResponse {
    return $this->respond(fn(): array => $this->client->uploadToken());
  }

  /**
   * Returns uncached JSON and controlled errors.
   */
  private function respond(callable $callback): JsonResponse {
    try {
      $response = new JsonResponse($callback());
    }
    catch (\RuntimeException | \InvalidArgumentException $e) {
      $response = new JsonResponse(['error' => $e->getMessage()], 400);
    }
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    return $response;
  }

}
