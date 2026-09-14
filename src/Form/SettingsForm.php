<?php

declare(strict_types=1);

namespace Drupal\mave\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\mave\MaveClient;

/**
 * Configures the Mave connection and default player appearance.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mave_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['mave.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = \Drupal::config('mave.settings');
    $client = \Drupal::service('mave.client');
    $form['connection'] = [
      '#type' => 'item',
      '#title' => $this->t('Connection'),
      '#plain_text' => $client->apiKey() ? $this->t('An API key is configured.') : $this->t('No API key configured.'),
    ];
    $form['api_key'] = [
      '#type' => 'password',
      '#title' => $this->t('Mave API key'),
      '#description' => $this->t('Leave blank to keep the current key. Stored separately from exported configuration. For deployments, set $settings["mave_api_key"] in settings.php.'),
      '#disabled' => Settings::get('mave_api_key') !== NULL,
      '#attributes' => ['autocomplete' => 'new-password'],
    ];
    $form['upload_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Upload target'),
      '#default_value' => $config->get('upload_subject'),
      '#description' => $this->t('Optional space or collection ID. Leave blank to use the API key’s space.'),
    ];
    $form['player_theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Default player theme'),
      '#options' => ['default' => 'Default', 'dolphin' => 'Dolphin', 'synthwave' => 'Synthwave'],
      '#default_value' => $config->get('player_theme'),
    ];
    $form['player_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default player color'),
      '#default_value' => $config->get('player_color'),
      '#description' => $this->t('Optional hex color, for example #635bff.'),
    ];
    $form['endpoints'] = [
      '#type' => 'details',
      '#title' => $this->t('Service endpoints'),
      '#description' => $this->t('Use browser-accessible HTTPS endpoints for local development and production.'),
    ];
    foreach ($this->endpointLabels() as $key => $label) {
      $form['endpoints'][$key] = [
        '#type' => 'textfield',
        '#title' => $label,
        '#default_value' => $config->get($key),
        '#required' => TRUE,
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * Returns translatable labels for the configurable service endpoints.
   */
  private function endpointLabels(): array {
    return [
      'api_endpoint' => $this->t('API endpoint'),
      'components_src' => $this->t('Components module URL'),
      'cdn_endpoint' => $this->t('CDN endpoint'),
      'socket_endpoint' => $this->t('Upload WebSocket endpoint'),
      'upload_endpoint' => $this->t('Upload endpoint'),
      'metrics_endpoint' => $this->t('Metrics endpoint'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    foreach ($this->endpointLabels() as $key => $label) {
      $value = str_replace([
        '${this.spaceId}',
        '${spaceId}',
        '{spaceId}',
      ], 'example', trim($form_state->getValue($key)));
      $parts = parse_url($value);
      $schemes = $key === 'socket_endpoint' ? ['ws', 'wss'] : ['http', 'https'];
      if (!$parts || empty($parts['host']) || !in_array($parts['scheme'] ?? '', $schemes, TRUE) || isset($parts['user']) || isset($parts['pass'])) {
        $form_state->setErrorByName($key, $this->t('Enter a valid endpoint URL without credentials.'));
      }
    }
    $subject = trim($form_state->getValue('upload_subject'));
    if ($subject !== '' && !preg_match('/^(?:[A-Za-z0-9]{15}|[A-Za-z0-9]{22}|[a-fA-F0-9-]{36})$/D', $subject)) {
      $form_state->setErrorByName('upload_subject', $this->t('Use a space or collection ID, not a five-character public space hash.'));
    }
    $color = trim($form_state->getValue('player_color'));
    if ($color !== '' && !preg_match('/^#(?:[a-fA-F0-9]{3}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})$/D', $color)) {
      $form_state->setErrorByName('player_color', $this->t('Enter a hex color such as #635bff.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('mave.settings');
    foreach (array_merge(array_keys($this->endpointLabels()), [
      'upload_subject',
      'player_theme',
      'player_color',
    ]) as $key) {
      $config->set($key, trim($form_state->getValue($key)));
    }
    $config->save();
    if (Settings::get('mave_api_key') === NULL && ($key = trim($form_state->getValue('api_key')))) {
      \Drupal::state()->set('mave.api_key', MaveClient::normalizeKey($key));
    }
    parent::submitForm($form, $form_state);
  }

}
