<?php

declare(strict_types=1);

namespace Drupal\Tests\mave\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests configuration form persistence and the separate credential storage.
 */
#[Group('mave')]
#[RunTestsInSeparateProcesses]
final class SettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mave'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests defaults, config targets, normalization and retaining a stored key.
   */
  public function testSettingsPersistenceAndCredentialIsolation(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer mave']));
    $defaults = $this->config('mave.settings')->getRawData();
    unset($defaults['_core']);
    $this->drupalGet('admin/config/media/mave');
    foreach ($defaults as $name => $value) {
      $this->assertSession()->fieldValueEquals($name, $value);
    }

    $settings = [
      'api_endpoint' => 'https://api.example.test/api/v1',
      'components_src' => 'https://components.example.test/index.js',
      'cdn_endpoint' => 'https://space-${this.spaceId}.example.test',
      'socket_endpoint' => 'wss://socket.example.test/api/v1/socket',
      'upload_endpoint' => 'https://upload.example.test/files',
      'metrics_endpoint' => 'https://metrics.example.test/v1/events',
      'upload_subject' => 'abcdefghijklmnopqrstuv',
      'player_theme' => 'synthwave',
      'player_color' => '#123abc',
    ];
    $edit = array_map(static fn(string $value): string => '  ' . $value . '  ', $settings);
    // Select elements only accept one of the available option values.
    $edit['player_theme'] = $settings['player_theme'];
    $edit['api_key'] = 'synthetic-id:synthetic-secret';
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSavedSettings($settings);
    foreach ($settings as $name => $value) {
      $this->assertSession()->fieldValueEquals($name, $value);
    }

    $key = base64_encode('synthetic-id:synthetic-secret');
    $state = $this->container->get('state');
    $state->resetCache();
    self::assertSame($key, $state->get('mave.api_key'));
    $this->assertSession()->fieldValueEquals('api_key', '');
    $this->assertSession()->responseNotContains($key);
    $this->assertSession()->responseNotContains('synthetic-secret');

    // A subsequent save with an empty password must retain the stored key.
    $this->submitForm(['player_theme' => 'dolphin', 'api_key' => ''], 'Save configuration');
    $settings['player_theme'] = 'dolphin';
    $this->assertSavedSettings($settings);
    $state->resetCache();
    self::assertSame($key, $state->get('mave.api_key'));
  }

  /**
   * Tests rejected form values cannot save config or replace the credential.
   */
  public function testInvalidSettingsDoNotPersist(): void {
    $state = $this->container->get('state');
    $state->set('mave.api_key', 'synthetic-existing-key');
    $defaults = $this->config('mave.settings')->getRawData();
    unset($defaults['_core']);
    $this->drupalLogin($this->drupalCreateUser(['administer mave']));
    $this->drupalGet('admin/config/media/mave');
    $this->submitForm([
      'api_endpoint' => 'https://user:pass@api.example.test',
      'player_color' => 'invalid-color',
      'player_theme' => 'dolphin',
      'api_key' => 'synthetic-replacement-key',
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('Enter a valid endpoint URL without credentials.');
    $this->assertSession()->pageTextContains('Enter a hex color such as #635bff.');
    $this->assertSavedSettings($defaults);
    $state->resetCache();
    self::assertSame('synthetic-existing-key', $state->get('mave.api_key'));
  }

  /**
   * Checks persisted config independently of the form's request and cache.
   */
  private function assertSavedSettings(array $expected): void {
    $factory = $this->container->get('config.factory');
    $factory->reset('mave.settings');
    $config = $factory->get('mave.settings');
    foreach ($expected as $name => $value) {
      self::assertSame($value, $config->get($name), $name);
    }
    self::assertArrayNotHasKey('api_key', $config->getRawData());
  }

}
