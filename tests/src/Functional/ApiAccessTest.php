<?php

declare(strict_types=1);

namespace Drupal\Tests\mave\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Exercises real Drupal installation, route permissions and CSRF without Mave.
 */
#[Group('mave')]
#[RunTestsInSeparateProcesses]
final class ApiAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mave'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests installation creates media integration.
   */
  public function testInstallationCreatesMediaIntegration(): void {
    self::assertSame('mave', MediaType::load('mave_video')->getSource()->getPluginId());
    self::assertSame('mave_picker', EntityFormDisplay::load('media.mave_video.default')->getComponent('field_mave_id')['type']);
    self::assertSame('mave_player', EntityViewDisplay::load('media.mave_video.default')->getComponent('field_mave_id')['type']);
    self::assertArrayNotHasKey('api_key', $this->config('mave.settings')->getRawData());
  }

  /**
   * Tests cached media plugins reconnect their services after serialization.
   */
  public function testMediaPluginsSurviveSerialization(): void {
    $media = Media::create([
      'bundle' => 'mave_video',
      'field_mave_id' => 'abcdefghijklmno',
    ]);
    $source = unserialize(serialize(MediaType::load('mave_video')->getSource()));
    // Without an API key the source falls back to the ID, without a request.
    self::assertSame('abcdefghijklmno', $source->getMetadata($media, 'default_name'));

    $display = EntityViewDisplay::load('media.mave_video.default');
    $formatter = unserialize(serialize($display->getRenderer('field_mave_id')));
    $elements = $formatter->viewElements($media->get('field_mave_id'), 'en');
    self::assertSame('abcdefghijklmno', $elements[0]['#embed_id']);
    self::assertSame(['mave/player'], $elements[0]['#attached']['library']);
    self::assertArrayNotHasKey('api_key', $elements[0]['#attached']['drupalSettings']['mave']);
  }

  /**
   * Tests route permission and csrf matrix.
   */
  public function testRoutePermissionAndCsrfMatrix(): void {
    $this->config('mave.settings')->set('upload_subject', 'synthetic-space-id')->save();
    $this->drupalGet('mave/api/videos');
    $this->assertSession()->statusCodeEquals(403);
    $this->postToken();
    $this->assertSession()->statusCodeEquals(403);

    $ordinary = $this->drupalCreateUser([]);
    $this->drupalLogin($ordinary);
    $this->drupalGet('admin/config/media/mave');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('mave/api/videos');
    $this->assertSession()->statusCodeEquals(403);
    $this->postToken($this->csrfToken());
    $this->assertSession()->statusCodeEquals(403);

    $browser = $this->drupalCreateUser(['browse mave videos']);
    $this->drupalLogin($browser);
    $this->drupalGet('mave/api/videos', ['query' => ['collection' => '../../secret']]);
    $this->assertSession()->statusCodeEquals(400);
    $this->assertSession()->responseContains('Invalid collection ID.');
    $this->drupalGet('mave/api/videos');
    $this->assertSession()->statusCodeEquals(400);
    $this->assertSession()->responseContains('Configure a Mave API key');
    $this->postToken($this->csrfToken());
    $this->assertSession()->statusCodeEquals(403);

    $uploader = $this->drupalCreateUser(['upload mave videos']);
    $this->drupalLogin($uploader);
    $this->drupalGet('mave/api/videos');
    $this->assertSession()->statusCodeEquals(403);
    $this->postToken();
    $this->assertSession()->statusCodeEquals(403);
    $this->postToken('invalid-token');
    $this->assertSession()->statusCodeEquals(403);
    $this->container->get('state')->set('mave.api_key', 'synthetic-functional-key');
    $this->postToken($this->csrfToken());
    $this->assertSession()->statusCodeEquals(200);
    $body = $this->getSession()->getPage()->getContent();
    self::assertStringNotContainsString('synthetic-functional-key', $body);
    $issued = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
    self::assertSame('synthetic-space-id', $issued['subject']);
    self::assertStringContainsString('no-store', $this->getSession()->getResponseHeader('Cache-Control'));
    $this->drupalGet('mave/api/upload-token');
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Retrieves a session-bound CSRF token from Drupal.
   */
  private function csrfToken(): string {
    $this->drupalGet('session/token');
    return $this->getSession()->getPage()->getContent();
  }

  /**
   * Requests an upload token with an untrusted target in the request body.
   */
  private function postToken(string $csrf = ''): void {
    $headers = $csrf === '' ? [] : ['HTTP_X_CSRF_TOKEN' => $csrf];
    $this->getSession()->getDriver()->getClient()->request('POST', $this->buildUrl('mave/api/upload-token'), ['subject' => 'attacker-chosen-target'], [], $headers);
  }

}
