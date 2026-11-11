<?php

declare(strict_types=1);

namespace Drupal\mave\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mave\MaveClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders a Mave player for a video reference.
 */
#[FieldFormatter(id: 'mave_player', label: new TranslatableMarkup('Mave player'), field_types: ['string'])]
final class MavePlayer extends FormatterBase {

  /**
   * The Mave connection and public player settings.
   */
  protected MaveClient $client;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->client = $container->get('mave.client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return ['theme' => 'default', 'color' => ''] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    $elements['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#options' => [
        'default' => $this->t('Default'),
        'dolphin' => $this->t('Dolphin'),
        'synthwave' => $this->t('Synthwave'),
      ],
      '#default_value' => MaveClient::playerTheme($this->getSetting('theme')),
    ];
    $elements['color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Color'),
      '#default_value' => $this->getSetting('color'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [$this->t('Theme: @theme', ['@theme' => MaveClient::playerTheme($this->getSetting('theme'))])];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $settings = $this->client->settings();
    $elements = [];
    foreach ($items as $delta => $item) {
      if (!MaveClient::validEmbedId((string) $item->value)) {
        continue;
      }
      $entity = $items->getEntity();
      $theme = ($entity->hasField('field_mave_theme') ? $entity->get('field_mave_theme')->value : '') ?: $this->getSetting('theme') ?: $settings['player_theme'];
      $color = ($entity->hasField('field_mave_color') ? $entity->get('field_mave_color')->value : '') ?: $this->getSetting('color') ?: $settings['player_color'];
      $elements[$delta] = [
        '#theme' => 'mave_player',
        '#embed_id' => $item->value,
        '#player_theme' => MaveClient::playerTheme($theme),
        '#color' => $color,
        '#attached' => ['library' => ['mave/player'], 'drupalSettings' => ['mave' => $this->client->publicSettings()]],
        '#cache' => ['tags' => ['config:mave.settings']],
      ];
    }
    return $elements;
  }

}
