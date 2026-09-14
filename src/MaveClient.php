<?php

declare(strict_types=1);

namespace Drupal\mave;

use GuzzleHttp\Exception\GuzzleException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;

/**
 * Connects to Mave without sending the API credential to the browser.
 */
final class MaveClient {

  /**
   * Video metadata cached for the lifetime of this client.
   *
   * @var array
   */
  private array $videoCache = [];

  public function __construct(
    private readonly ClientInterface $http,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly StateInterface $state,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Returns configuration with deployment overrides applied.
   */
  public function settings(): array {
    $config = $this->configFactory->get('mave.settings');
    $settings = [];
    foreach (array_keys($config->getRawData()) as $key) {
      $settings[$key] = $config->get($key);
    }
    return $settings;
  }

  /**
   * Returns the server-side credential, preferring settings.php.
   */
  public function apiKey(): string {
    return self::normalizeKey((string) Settings::get('mave_api_key', $this->state->get('mave.api_key', '')));
  }

  /**
   * Normalizes a raw credential pair or an already encoded API key.
   */
  public static function normalizeKey(string $value): string {
    $value = trim($value);
    return str_contains($value, ':') ? base64_encode($value) : $value;
  }

  /**
   * Checks the public video and collection identifier format.
   */
  public static function validEmbedId(string $value): bool {
    return (bool) preg_match('/^[A-Za-z0-9]{15}$/D', $value);
  }

  /**
   * Retrieves a page from the authenticated video API.
   */
  public function listVideos(array $query = []): array {
    return $this->request('/videos', $query + [
      'show_collections' => 'true',
      'archived' => 'false',
      'per_page' => 24,
      'page' => 1,
    ]);
  }

  /**
   * Paginate folders before videos without downloading the whole library.
   */
  public function libraryPage(array $query = []): array {
    $page = max(1, (int) ($query['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($query['per_page'] ?? 24)));
    $scope = ['archived' => 'false'];
    if (!empty($query['collection'])) {
      $scope['collection'] = $query['collection'];
    }
    $folders = $this->request('/collections', $scope + ['page' => $page, 'per_page' => $perPage]);
    $folderCount = (int) ($folders['total_items'] ?? count($folders['data'] ?? []));
    $offset = ($page - 1) * $perPage;
    $items = $offset < $folderCount ? ($folders['data'] ?? []) : [];
    $remaining = $perPage - count($items);
    $videoOffset = max(0, $offset - $folderCount);
    $videoPage = intdiv($videoOffset, $perPage) + 1;
    $videoSkip = $videoOffset % $perPage;
    $videoQuery = $scope + ['show_collections' => 'false', 'uploaded' => 'true'];
    // Even a page containing only folders needs the video count for its pager.
    $videos = $this->listVideos($videoQuery + [
      'page' => $remaining ? $videoPage : 1,
      'per_page' => $remaining ? $perPage : 1,
    ]);
    $videoCount = (int) ($videos['total_items'] ?? count($videos['data'] ?? []));
    if ($remaining > 0) {
      $items = array_merge($items, array_slice($videos['data'] ?? [], $videoSkip, $remaining));
      // A combined page can straddle two pages of the videos-only endpoint.
      $needed = $perPage - count($items);
      if ($needed > 0 && $videoSkip > 0 && $videoPage * $perPage < $videoCount) {
        $next = $this->listVideos($videoQuery + ['page' => $videoPage + 1, 'per_page' => $perPage]);
        $items = array_merge($items, array_slice($next['data'] ?? [], 0, $needed));
      }
    }
    $total = $folderCount + $videoCount;
    return [
      'data' => $items,
      'total_items' => $total,
      'total_pages' => (int) ceil($total / $perPage),
      'current_page' => $page,
      'has_more' => $offset + $perPage < $total,
    ];
  }

  /**
   * Loads a matching video, caching it for the lifetime of this client.
   */
  public function video(string $id): array {
    if (!self::validEmbedId($id)) {
      throw new \InvalidArgumentException('Select a valid Mave video.');
    }
    if (!isset($this->videoCache[$id])) {
      $video = $this->request('/videos/' . rawurlencode($id));
      if (($video['object'] ?? '') !== 'video' || ($video['id'] ?? '') !== $id) {
        throw new \RuntimeException('This video is not available in the configured Mave space.');
      }
      $this->videoCache[$id] = $video;
    }
    return $this->videoCache[$id];
  }

  /**
   * Calls the API without redirects and sanitizes upstream failures.
   */
  private function request(string $path, array $query = []): array {
    $key = $this->apiKey();
    if ($key === '') {
      throw new \RuntimeException('Configure a Mave API key in Administration → Configuration → Media → Mave Video.');
    }
    try {
      $response = $this->http->request('GET', rtrim($this->settings()['api_endpoint'], '/') . $path, [
        'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $key],
        'query' => $query,
        'timeout' => 20,
        'connect_timeout' => 5,
        'http_errors' => FALSE,
        'allow_redirects' => FALSE,
      ]);
    }
    catch (GuzzleException) {
      // Exception messages can contain sensitive request details.
      throw new \RuntimeException('Could not reach Mave. Check the API endpoint and connection.');
    }
    $status = $response->getStatusCode();
    if ($status < 200 || $status >= 300) {
      throw new \RuntimeException(match ($status) {
        401, 403 => 'Mave refused access. Check the API key and its permissions.',
        404 => 'This Mave video or API endpoint could not be found.',
        default => 'Mave is temporarily unavailable. Try again later.',
      });
    }
    try {
      $data = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      throw new \RuntimeException('Mave returned an invalid response.');
    }
    if (!is_array($data)) {
      throw new \RuntimeException('Mave returned an invalid response.');
    }
    return $data;
  }

  /**
   * Issues an upload token for the configured server-side target.
   */
  public function uploadToken(): array {
    $key = $this->apiKey();
    if ($key === '') {
      throw new \RuntimeException('Configure a Mave API key before uploading.');
    }
    $subject = trim($this->settings()['upload_subject'] ?? '');
    if ($subject === '') {
      $subject = $this->listVideos(['per_page' => 1])['space_id'] ?? '';
    }
    if ($subject === '' || strlen($subject) <= 5) {
      throw new \RuntimeException('Configure a space or collection ID as the upload target. A public space hash cannot be used.');
    }
    $now = $this->time->getCurrentTime();
    $base64 = static fn(string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $input = $base64(json_encode([
      'alg' => 'HS256',
      'typ' => 'JWT',
    ], JSON_THROW_ON_ERROR)) . '.' . $base64(json_encode([
      'sub' => $subject,
      'iat' => $now,
      'exp' => $now + 7200,
    ], JSON_THROW_ON_ERROR));
    return ['token' => $input . '.' . $base64(hash_hmac('sha256', $input, $key, TRUE)), 'subject' => $subject];
  }

  /**
   * Builds a CDN thumbnail URL from a validated public identifier.
   */
  public function thumbnail(string $id): string {
    if (!self::validEmbedId($id)) {
      return '';
    }
    $endpoint = str_replace([
      '${this.spaceId}',
      '${spaceId}',
      '{spaceId}',
    ], substr($id, 0, 5), $this->settings()['cdn_endpoint']);
    return rtrim($endpoint, '/') . '/' . substr($id, 5) . '/thumbnail.jpg';
  }

  /**
   * Only public endpoint settings belong in rendered HTML and Drupal caches.
   */
  public function publicSettings(): array {
    $s = $this->settings();
    return [
      'componentsSrc' => $s['components_src'],
      'componentsConfig' => [
        'api' => ['endpoint' => $s['api_endpoint']],
        'cdn' => ['endpoint' => $s['cdn_endpoint']],
        'upload' => ['endpoint' => $s['upload_endpoint'], 'socket' => $s['socket_endpoint']],
        'metrics' => ['endpoint' => $s['metrics_endpoint']],
      ],
      'playerTheme' => $s['player_theme'],
      'playerColor' => $s['player_color'],
    ];
  }

  /**
   * Adds editor-only route URLs and initial folder navigation.
   */
  public function pickerSettings(): array {
    $subject = $this->settings()['upload_subject'] ?? '';
    return $this->publicSettings() + [
      'videosUrl' => Url::fromRoute('mave.videos')->toString(),
      'tokenUrl' => Url::fromRoute('mave.upload_token')->toString(),
      'csrfUrl' => Url::fromRoute('system.csrftoken')->toString(),
      'rootCollection' => self::validEmbedId($subject) ? $subject : '',
    ];
  }

}
