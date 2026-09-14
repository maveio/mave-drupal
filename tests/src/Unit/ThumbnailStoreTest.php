<?php

declare(strict_types=1);

namespace Drupal\Tests\mave\Unit;

use Drupal\Core\File\FileSystemInterface;
use Drupal\mave\ThumbnailStore;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Guards network-to-public-file thumbnail handling using synthetic image data.
 */
#[CoversClass(ThumbnailStore::class)]
#[Group('mave')]
final class ThumbnailStoreTest extends UnitTestCase {

  private const IMAGE = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aH1sAAAAASUVORK5CYII=';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    stream_wrapper_register('public', MissingPublicFile::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    stream_wrapper_unregister('public');
    parent::tearDown();
  }

  /**
   * Tests small image is streamed to expected path.
   */
  public function testSmallImageIsStreamedToExpectedPath(): void {
    $transactions = [];
    $stack = HandlerStack::create(new MockHandler([new Response(200, [], base64_decode(self::IMAGE))]));
    $stack->push(Middleware::history($transactions));
    $files = $this->createMock(FileSystemInterface::class);
    $files->method('prepareDirectory')->willReturn(TRUE);
    $url = 'https://cdn.example.test/thumbnail.jpg';
    $uri = 'public://mave-thumbnails/' . substr(hash('sha256', $url), 0, 16) . '/aaaaabbbbbbbbbb.jpg';
    $files->expects(self::once())->method('saveData')->with(base64_decode(self::IMAGE), $uri)->willReturn($uri);
    $store = new ThumbnailStore(new Client(['handler' => $stack]), $files);
    self::assertSame($uri, $store->localUri($url, 'aaaaabbbbbbbbbb'));
    self::assertTrue($transactions[0]['options']['stream']);
    self::assertFalse($transactions[0]['options']['allow_redirects']);
    self::assertFalse($transactions[0]['request']->hasHeader('Authorization'));
  }

  /**
   * Tests unsafe responses are not saved.
   */
  #[DataProvider('unsafeResponses')]
  public function testUnsafeResponsesAreNotSaved(int $status, array $headers, string $bytes): void {
    $files = $this->createMock(FileSystemInterface::class);
    $files->expects(self::never())->method('saveData');
    $response = new Response($status, $headers, $bytes);
    $store = new ThumbnailStore(new Client(['handler' => HandlerStack::create(new MockHandler([$response]))]), $files);
    self::assertNull($store->localUri('https://cdn.example.test/thumbnail.jpg', 'aaaaabbbbbbbbbb'));
    self::assertFalse($response->getBody()->isReadable(), 'The response stream must be closed on failure.');
  }

  /**
   * Provides images and responses that must never be saved.
   */
  public static function unsafeResponses(): array {
    $image = base64_decode(self::IMAGE);
    return [
      'redirect' => [302, ['Location' => 'https://other.example.test/'], $image],
      'html' => [200, [], '<script>alert(1)</script>'],
      'empty' => [200, [], ''],
      'unavailable' => [503, [], $image],
      'declared oversized' => [200, ['Content-Length' => (string) (3 * 1024 * 1024)], $image],
      'chunked oversized' => [200, [], $image . str_repeat('x', 2 * 1024 * 1024)],
      'excessive dimensions' => [200, [], substr_replace($image, pack('NN', 100000, 100000), 16, 8)],
    ];
  }

  /**
   * Tests invalid id cannot trigger download.
   */
  public function testInvalidIdCannotTriggerDownload(): void {
    $store = new ThumbnailStore(new Client(['handler' => new MockHandler()]), $this->createMock(FileSystemInterface::class));
    self::assertNull($store->localUri('https://cdn.example.test/thumbnail.jpg', '../../etc/passwd'));
  }

}

/**
 * Models an empty public filesystem; writes use a FileSystemInterface mock.
 */
final class MissingPublicFile {

  /**
   * Stream context supplied by PHP.
   *
   * @var resource|null
   */
  public $context;

  /**
   * Reports absent files through the PHP stream-wrapper interface.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
  public function url_stat(string $path, int $flags): false {
    return FALSE;
  }

}
