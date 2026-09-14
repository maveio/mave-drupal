<?php

declare(strict_types=1);

namespace Drupal\mave\Form;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\media_library\Form\AddFormBase;
use Drupal\mave\Plugin\Field\FieldWidget\MavePicker;

/**
 * Selects remote Mave videos through the core Media Library workflow.
 */
final class MediaLibraryAddForm extends AddFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return $this->getBaseFormId() . '_mave';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $form['#attributes']['class'][] = 'mave-library-add-form';
    $form['#attached']['library'][] = 'mave/dialog';
    if (isset($form['description']['#context']['text'])) {
      $form['description']['#context']['text'] = $this->t('Check the video details, then insert it into your content.');
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildInputElement(array $form, FormStateInterface $form_state): array {
    $form['picker'] = ['#type' => 'container', '#attributes' => ['class' => ['mave-picker']]];
    $form['picker']['mave_id'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Mave video'),
      '#required' => TRUE,
      '#required_error' => $this->t('Choose a video or upload one before continuing.'),
      '#attributes' => ['class' => ['mave-embed-input']],
      '#element_validate' => [[MavePicker::class, 'validateVideo']],
    ];
    MavePicker::attachPicker($form['picker']);
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['continue'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['mave-continue']],
      '#submit' => ['::addButtonSubmit'],
      '#ajax' => [
        'callback' => '::updateFormCallback',
        'wrapper' => 'media-library-wrapper',
        'progress' => ['type' => 'throbber', 'message' => $this->t('Loading video details…')],
        'url' => Url::fromRoute('media_library.ui'),
        'options' => ['query' => $this->getMediaLibraryState($form_state)->all() + [FormBuilderInterface::AJAX_FORM_REQUEST => TRUE]],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildActions(array $form, FormStateInterface $form_state): array {
    $actions = parent::buildActions($form, $form_state);
    $actions['save_select']['#value'] = $this->t('Insert video');
    $actions['save_select']['#ajax']['callback'] = '::updateWidget';
    unset($actions['save_insert']);
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function addButtonSubmit(array $form, FormStateInterface $form_state): void {
    $this->processInputValues([$form_state->getValue('mave_id')], $form, $form_state);
  }

}
