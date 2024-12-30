<?php

namespace Drupal\media_thumbnails\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Media thumbnail plugins.
 */
interface MediaThumbnailInterface extends PluginInspectionInterface {

  /**
   * Create a thumbnail file using the passed source uri.
   *
   * @param string $sourceUri
   *   The uri of the source file, like 'public://invoices/inv001.pdf'.
   *
   * @return \Drupal\file\Entity\File|null
   *   The new managed file object for the generated thumbnail.
   */
  public function createThumbnail($sourceUri);

}
