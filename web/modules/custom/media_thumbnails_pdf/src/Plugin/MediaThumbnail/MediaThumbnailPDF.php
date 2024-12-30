<?php

namespace Drupal\media_thumbnails_pdf\Plugin\MediaThumbnail;

use Drupal\media_thumbnails\Plugin\MediaThumbnailBase;
use Exception;

/**
 * Media thumbnail plugin for pdf documents.
 *
 * @MediaThumbnail(
 *   id = "media_thumbnail_pdf",
 *   label = @Translation("Media Thumbnail PDF"),
 *   mime = {
 *     "application/pdf",
 *   }
 * )
 */
class MediaThumbnailPDF extends MediaThumbnailBase {

  /**
   * Creates a managed thumbnail file using the passed source file uri.
   *
   * {@inheritdoc}
   */
  public function createThumbnail($sourceUri) {
    // Check the required php extension.
    // Make sure Imagick is installed and available.
    if (!extension_loaded('imagick')) {
      $this->logger->warning('Imagick php extension is not loaded.');
      return NULL;
    }

    try {
      // Because Imagick does not support stream wrappers, we need to copy the file
      // to the system's temporary directory and work from the local copy.
      $source_temp = $this->fileSystem->tempnam('temporary://', 'imagemagick_');

      // Remove the temporary, extensionless file created by tempnam().
      unlink($source_temp);

      // Append the appropriate file extension onto the temporary
      // source filename.
      $source_temp .= '.' . pathinfo($sourceUri, PATHINFO_EXTENSION);
      $source_temp = $this->fileSystem->copy($sourceUri, $source_temp);
      $path = $this->fileSystem->realpath($source_temp);
      if (!$path) {
        throw new Exception('File could not be copied to the temporary directory.');
      }
    }
    catch (Exception $e) {
      $this->logger->error('Error while preparing temporary file : @error', [
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }

    // Read the pdf.
    $im = new \Imagick();
    $im->setColorSpace(\Imagick::COLORSPACE_SRGB);
    try {
      $im->readimage($path . '[0]');
    }
    catch (\ImagickException $e) {
      $this->logger->warning('ImagickException: error while reading the image @path : @error', [
        '@path' => $path,
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }

    try {
      // Handle transparency stuff.
      $im->setImageBackgroundColor('white');
      $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
      $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
    }
    catch (\ImagickException $e) {
      $this->logger->warning('ImagickException: error while handling transparency : @error', [
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }

    // Resize the thumbnail to the globally configured width.
    $width = $this->configuration['width'] ?? 500;
    if ($im->getImageWidth() > $width) {
      try {
        $im->scaleImage($width, 0);
      }
      catch (\ImagickException $e) {
        $this->logger->warning('ImagickException: error while scaling image to width @width : @error', [
          '@width' => $width,
          '@error' => $e->getMessage(),
        ]);
        return NULL;
      }
    }

    // Convert the image to JPG.
    $im->setImageFormat('jpg');
    $image = $im->getImageBlob();
    $im->clear();
    $im->destroy();

    // Create a managed file object using the generated thumbnail.
    $file = \Drupal::service('file.repository')
      ->writeData($image, $sourceUri . '.jpg');

    // Remove the temporary file.
    unlink($source_temp);

    return $file;
  }

}
