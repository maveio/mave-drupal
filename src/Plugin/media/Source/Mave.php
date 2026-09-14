<?php

declare(strict_types=1);

namespace Drupal\mave\Plugin\media\Source;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\media\Attribute\MediaSource;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaTypeInterface;
use Drupal\mave\Form\MediaLibraryAddForm;
use Drupal\mave\MaveClient;
use Drupal\mave\ThumbnailStore;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Mave video metadata and thumbnails to Drupal Media.
 */
#[MediaSource(
  id: 'mave',
  label: new TranslatableMarkup('Mave Video'),
  description: new TranslatableMarkup('Videos hosted on Mave.'),
  allowed_field_types: ['string'],
  default_thumbnail_filename: 'no-thumbnail.png',
  forms: ['media_library_add' => MediaLibraryAddForm::class],
)]
final class Mave extends MediaSourceBase {

  /**
   * The authenticated Mave API client.
   */
  protected MaveClient $client;

  /**
   * The local thumbnail cache.
   */
  protected ThumbnailStore $thumbnails;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->client = $container->get('mave.client');
    $instance->thumbnails = $container->get('mave.thumbnails');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes(): array {
    return [
      'default_name' => $this->t('Video title'),
      'thumbnail_uri' => $this->t('Thumbnail'),
      'duration' => $this->t('Duration in seconds'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    $id = (string) $this->getSourceFieldValue($media);
    if (!MaveClient::validEmbedId($id)) {
      return parent::getMetadata($media, $attribute_name);
    }
    if ($attribute_name === 'thumbnail_uri') {
      return $this->thumbnails->localUri($this->client->thumbnail($id), $id) ?: parent::getMetadata($media, $attribute_name);
    }
    if (in_array($attribute_name, ['default_name', 'duration'], TRUE)) {
      try {
        $video = $this->client->video($id);
        return $attribute_name === 'duration' ? ($video['duration'] ?? NULL) : ($video['name'] ?? $id);
      }
      catch (\RuntimeException | \InvalidArgumentException) {
        return $attribute_name === 'default_name' ? $id : NULL;
      }
    }
    return parent::getMetadata($media, $attribute_name);
  }

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) {
    return parent::createSourceField($type)->set('label', 'Mave video')->set('description', 'Select a Mave video or upload a new one.');
  }

}
