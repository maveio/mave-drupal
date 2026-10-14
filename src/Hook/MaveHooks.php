<?php

declare(strict_types=1);

namespace Drupal\mave\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\mave\MaveClient;

/**
 * Registers the player and attaches it to text editors.
 */
final class MaveHooks {

  public function __construct(
    private readonly MaveClient $client,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path): array {
    return [
      'mave_player' => [
        'variables' => [
          'embed_id' => '',
          'player_theme' => 'default',
          'color' => '',
        ],
      ],
    ];
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    // CKEditor loads media previews asynchronously, without their attachments.
    $type = $context['items']->getFieldDefinition()->getType();
    if (in_array($type, ['text', 'text_long', 'text_with_summary'], TRUE)) {
      $element['#attributes']['data-mave-editor'] = 'true';
      $element['#attached']['library'][] = 'mave/player';
      $element['#attached']['drupalSettings']['mave'] = $this->client->publicSettings();
      $element['#cache']['tags'][] = 'config:mave.settings';
    }
  }

}
