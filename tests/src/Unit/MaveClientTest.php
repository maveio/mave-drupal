<?php

declare(strict_types=1);

namespace Drupal\Tests\mave\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Drupal\mave\MaveClient;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies the credential, validation and pagination boundary without a SaaS.
 */
#[CoversClass(MaveClient::class)]
#[Group('mave')]
final class MaveClientTest extends UnitTestCase {

  private const KEY = 'synthetic-test-signing-key';

  /**
   * Captured synthetic HTTP transactions.
   *
   * @var array
   */
  private array $history = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    new Settings([]);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    new Settings([]);
    parent::tearDown();
  }

  /**
   * Creates a client using synthetic credentials and mocked HTTP responses.
   */
  private function client(array|callable $responses = [], array $settings = [], string $key = self::KEY): MaveClient {
    $stack = HandlerStack::create(is_array($responses) ? new MockHandler($responses) : $responses);
    $stack->push(Middleware::history($this->history));
    $state = $this->createMock(StateInterface::class);
    $state->method('get')->with('mave.api_key', '')->willReturn($key);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(1700000000);
    $data = $settings + [
      'api_endpoint' => 'https://api.example.test/api/v1',
      'components_src' => 'https://components.example.test/index.js',
      'cdn_endpoint' => 'https://space-${this.spaceId}.example.test',
      'upload_endpoint' => 'https://upload.example.test/files',
      'socket_endpoint' => 'wss://api.example.test/socket',
      'metrics_endpoint' => 'https://metrics.example.test/events',
      'upload_subject' => 'synthetic-space-id',
      'player_theme' => 'default',
      'player_color' => '',
    ];
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('getRawData')->willReturn($data);
    $config->method('get')->willReturnCallback(static fn($name) => $data[$name] ?? NULL);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('mave.settings')->willReturn($config);
    return new MaveClient(new Client(['handler' => $stack]), $factory, $state, $time);
  }

  /**
   * Tests credential stays server side.
   */
  public function testCredentialStaysServerSide(): void {
    new Settings(['mave_api_key' => 'override-test-key']);
    $client = $this->client([new Response(200, [], '{"data":[]}')]);
    $client->listVideos();
    self::assertSame('Bearer override-test-key', $this->history[0]['request']->getHeaderLine('Authorization'));
    self::assertFalse($this->history[0]['options']['allow_redirects']);
    self::assertStringNotContainsString('override-test-key', json_encode($client->publicSettings()));
    self::assertStringNotContainsString(self::KEY, json_encode($client->settings()));
  }

  /**
   * Tests missing credential makes no request.
   */
  public function testMissingCredentialMakesNoRequest(): void {
    try {
      $this->client(key: '')->listVideos();
      self::fail('An unconfigured client must not call the API.');
    }
    catch (\RuntimeException $exception) {
      self::assertStringContainsString('Configure a Mave API key', $exception->getMessage());
      self::assertSame([], $this->history);
    }
  }

  /**
   * Tests invalid ids never reach http.
   */
  #[DataProvider('invalidIds')]
  public function testInvalidIdsNeverReachHttp(string $id): void {
    $client = $this->client();
    self::assertSame('', $client->thumbnail($id));
    try {
      $client->video($id);
      self::fail('Invalid ID accepted.');
    }
    catch (\InvalidArgumentException) {
      self::assertSame([], $this->history);
    }
  }

  /**
   * Provides invalid identifiers that must never reach the API.
   */
  public static function invalidIds(): array {
    return [
      [''],
      ['../../etc/passwd'],
      ['aaaaabbbbbbbbbb/'],
      ["aaaaabbbbbbbbbb\n"],
      ['<script>bad</script>'],
      ['aaaaabbbbbbbbbbé'],
    ];
  }

  /**
   * Tests response identity and caching.
   */
  public function testResponseIdentityAndCaching(): void {
    $id = 'aaaaabbbbbbbbbb';
    $client = $this->client([new Response(200, [], json_encode([
      'object' => 'video',
      'id' => $id,
      'name' => 'Example',
    ])),
    ]);
    self::assertSame($client->video($id), $client->video($id));
    self::assertCount(1, $this->history);
    self::assertSame('/api/v1/videos/' . $id, $this->history[0]['request']->getUri()->getPath());
  }

  /**
   * Tests wrong object or id rejected.
   */
  #[DataProvider('wrongVideos')]
  public function testWrongObjectOrIdRejected(array $video): void {
    $this->expectException(\RuntimeException::class);
    $this->client([new Response(200, [], json_encode($video))])->video('aaaaabbbbbbbbbb');
  }

  /**
   * Provides responses with a mismatched object type or identifier.
   */
  public static function wrongVideos(): array {
    return [
      [['object' => 'collection', 'id' => 'aaaaabbbbbbbbbb']],
      [['object' => 'video', 'id' => 'aaaaacccccccccc']],
    ];
  }

  /**
   * Tests upstream failures never expose sensitive details.
   */
  #[DataProvider('failures')]
  public function testUpstreamFailuresNeverExposeSensitiveDetails(int $status, string $body): void {
    try {
      $this->client([new Response($status, ['Location' => 'https://sensitive.example.test/'], $body)])->listVideos();
      self::fail('Bad upstream response accepted.');
    }
    catch (\RuntimeException $exception) {
      self::assertStringNotContainsString('sensitive', $exception->getMessage());
      self::assertStringNotContainsString(self::KEY, $exception->getMessage());
      self::assertCount(1, $this->history);
    }
  }

  /**
   * Provides upstream failures containing sensitive synthetic details.
   */
  public static function failures(): array {
    return [
      [302, 'sensitive'],
      [403, '{"error":"sensitive"}'],
      [500, 'sensitive'],
      [200, 'sensitive-invalid-json'],
      [200, 'null'],
    ];
  }

  /**
   * Tests transport error is sanitized.
   */
  public function testTransportErrorIsSanitized(): void {
    $client = $this->client([new ConnectException('sensitive ' . self::KEY, new Request('GET', 'https://api.example.test'))]);
    $this->expectExceptionMessage('Could not reach Mave. Check the API endpoint and connection.');
    $client->listVideos();
  }

  /**
   * Tests upload token has server chosen scope and expiry.
   */
  public function testUploadTokenHasServerChosenScopeAndExpiry(): void {
    $client = $this->client();
    [$header, $payload, $signature] = explode('.', $client->uploadToken()['token']);
    $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), TRUE);
    self::assertSame(['sub' => 'synthetic-space-id', 'iat' => 1700000000, 'exp' => 1700007200], $claims);
    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload", self::KEY, TRUE)), '+/', '-_'), '=');
    self::assertTrue(hash_equals($expected, $signature));
    self::assertSame(['alg' => 'HS256', 'typ' => 'JWT'], json_decode(base64_decode(strtr($header, '-_', '+/')), TRUE));
    self::assertSame([], $this->history);
  }

  /**
   * Tests upload target falls back to authenticated space.
   */
  public function testUploadTargetFallsBackToAuthenticatedSpace(): void {
    $client = $this->client([new Response(200, [], '{"space_id":"synthetic-api-space","data":[]}')], ['upload_subject' => '']);
    self::assertSame('synthetic-api-space', $client->uploadToken()['subject']);
  }

  /**
   * Tests folders precede videos across page boundaries.
   */
  #[DataProvider('folderCounts')]
  public function testFoldersPrecedeVideosAcrossPageBoundaries(int $folderCount): void {
    $folders = array_map(static fn($i) => [
      'id' => "folder-$i",
      'object' => 'collection',
    ], $folderCount ? range(1, $folderCount) : []);
    $videos = array_map(static fn($i) => ['id' => "video-$i", 'object' => 'video'], range(1, 6));
    $handler = static function ($request) use ($folders, $videos) {
      parse_str($request->getUri()->getQuery(), $query);
      self::assertSame('aaaaabbbbbbbbbb', $query['collection']);
      $isFolder = str_ends_with($request->getUri()->getPath(), '/collections');
      if (!$isFolder) {
        self::assertSame('false', $query['show_collections']);
        self::assertSame('true', $query['uploaded']);
      }
      $items = $isFolder ? $folders : $videos;
      return Create::promiseFor(new Response(200, [], json_encode([
        'data' => array_slice($items, ($query['page'] - 1) * $query['per_page'], (int) $query['per_page']),
        'total_items' => count($items),
      ])));
    };
    $client = $this->client($handler);
    $combined = [];
    $pages = (int) ceil(($folderCount + 6) / 3);
    for ($page = 1; $page <= $pages; $page++) {
      $result = $client->libraryPage(['page' => $page, 'per_page' => 3, 'collection' => 'aaaaabbbbbbbbbb']);
      self::assertSame($pages, $result['total_pages']);
      self::assertSame($page < $pages, $result['has_more']);
      $combined = array_merge($combined, $result['data']);
    }
    self::assertSame(array_merge($folders, $videos), $combined);
  }

  /**
   * Provides folder counts on both sides of a combined page boundary.
   */
  public static function folderCounts(): array {
    return [[0], [2], [4], [7]];
  }

}
