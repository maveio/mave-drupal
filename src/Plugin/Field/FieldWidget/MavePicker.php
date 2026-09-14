<?php

declare(strict_types=1);

namespace Drupal\mave\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Selects and validates Mave video references.
 */
#[FieldWidget(id: 'mave_picker', label: new TranslatableMarkup('Mave video picker'), field_types: ['string'])]
final class MavePicker extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element['#type'] = 'container';
    $element['#attributes']['class'][] = 'mave-picker';
    $element['#attributes']['data-selected-name'] = $items->getEntity()->label() ?? '';
    $element['value'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Mave video'),
      '#default_value' => $items[$delta]->value ?? '',
      '#required' => $element['#required'] ?? FALSE,
      '#required_error' => $this->t('Choose a video or upload one before saving.'),
      '#attributes' => ['class' => ['mave-embed-input']],
      '#element_validate' => [[self::class, 'validateVideo']],
    ];
    self::attachPicker($element);
    return $element;
  }

  /**
   * Attaches picker assets and permission-dependent browser settings.
   */
  public static function attachPicker(array &$element): void {
    $account = \Drupal::currentUser();
    $element['#attributes']['data-can-browse'] = $account->hasPermission('browse mave videos') ? '1' : '0';
    $element['#attributes']['data-can-upload'] = $account->hasPermission('upload mave videos') ? '1' : '0';
    $element['#attached']['library'][] = 'mave/picker';
    $element['#attached']['drupalSettings']['mave'] = \Drupal::service('mave.client')->pickerSettings();
    $element['#cache']['contexts'][] = 'user.permissions';
    $element['#cache']['tags'][] = 'config:mave.settings';
  }

  /**
   * Rejects references unavailable through the configured Mave API key.
   */
  public static function validateVideo(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $id = trim((string) $element['#value']);
    if ($id === '') {
      return;
    }
    try {
      $video = \Drupal::service('mave.client')->video($id);
      if (empty($video['last_upload']) && empty($video['renditions'])) {
        throw new \RuntimeException('Wait until Mave has finished processing the video.');
      }
    }
    catch (\RuntimeException | \InvalidArgumentException $e) {
      $form_state->setError($element, $e->getMessage());
    }
  }

}
