<?php

namespace Drupal\media_thumbnails\Commands;

use Drupal\media_thumbnails\Batch\RefreshBatch;
use Drush\Commands\DrushCommands;

/**
 * Drush integration for the media thumbnails module.
 */
class MediaThumbnailCommands extends DrushCommands {

  /**
   * Refresh the thumbnails for all media entities.
   *
   * @aliases thref,thumbnails-refresh
   * @command thumbnails:refresh
   * @usage drush thumbnails:refresh
   *   Refresh thumbnails for all media entities
   */
  public function refresh() {
    batch_set(RefreshBatch::createBatch());
    $batch =& batch_get();
    $batch['progressive'] = FALSE;
    drush_backend_batch_process();
  }

}
