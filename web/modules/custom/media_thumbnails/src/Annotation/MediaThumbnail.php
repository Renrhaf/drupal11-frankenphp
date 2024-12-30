<?php

namespace Drupal\media_thumbnails\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Media thumbnail item annotation object.
 *
 * @see \Drupal\media_thumbnails\Plugin\MediaThumbnailManager
 * @see plugin_api
 *
 * @Annotation
 */
class MediaThumbnail extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The mime types handled by the plugin.
   *
   * @var array
   */
  public $mime;

}
