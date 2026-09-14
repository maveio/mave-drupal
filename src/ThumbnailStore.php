<?php

declare(strict_types=1);

namespace Drupal\mave;

use GuzzleHttp\Exception\GuzzleException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use GuzzleHttp\ClientInterface;

/**
 * Makes remote thumbnails available to Drupal's local image styles.
 */
final class ThumbnailStore {

  private const MAX_BYTES = 2 * 1024 * 1024;

  private const MAX_PIXELS = 16 * 1024 * 1024;

  public function __construct(private readonly ClientInterface $http, private readonly FileSystemInterface $files) {}

  /**
   * Downloads a bounded thumbnail or reuses the cached public image.
   */
  public function localUri(string $url, string $id): ?string {
    if (!MaveClient::validEmbedId($id)) {
      return NULL;
    }
    $directory = 'public://mave-thumbnails/' . substr(hash('sha256', $url), 0, 16);
    $uri = $directory . '/' . $id . '.jpg';
    if (file_exists($uri)) {
      return $uri;
    }
    $body = NULL;
    try {
      // Bound buffered data, including responses without a Content-Length.
      $response = $this->http->request('GET', $url, [
        'timeout' => 10,
        'connect_timeout' => 5,
        'read_timeout' => 5,
        'allow_redirects' => FALSE,
        'http_errors' => FALSE,
        'stream' => TRUE,
      ]);
      $body = $response->getBody();
      if ($response->getStatusCode() !== 200) {
        return NULL;
      }
      if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
        return NULL;
      }
      $bytes = '';
      $deadline = microtime(TRUE) + 10;
      while (!$body->eof()) {
        $chunk = $body->read(min(8192, self::MAX_BYTES + 1 - strlen($bytes)));
        $bytes .= $chunk;
        if (strlen($bytes) > self::MAX_BYTES || microtime(TRUE) > $deadline || ($chunk === '' && !$body->eof())) {
          return NULL;
        }
      }
      $image = @getimagesizefromstring($bytes);
      if (!$image || $image[0] * $image[1] > self::MAX_PIXELS || !$this->files->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        return NULL;
      }
      return $this->files->saveData($bytes, $uri, FileExists::Replace);
    }
    catch (GuzzleException | \RuntimeException) {
      return NULL;
    }
    finally {
      if ($body !== NULL) {
        $body->close();
      }
    }
  }

}
