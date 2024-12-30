<?php

namespace Drupal\media_thumbnails\Plugin;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\media\MediaInterface;
use Drupal\media_thumbnails\Annotation\MediaThumbnail;

/**
 * Provides the Media thumbnail plugin manager.
 */
class MediaThumbnailManager extends DefaultPluginManager {

  /**
   * List of mime types and corresponding plugin ids.
   *
   * @var array
   */
  protected $plugins;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The file usage service.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerChannelFactory;

  /**
   * Constructs a new MediaThumbnailManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\file\FileUsage\FileUsageInterface $file_usage
   *   The file usage service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   The logger channel factory service.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory, FileUsageInterface $file_usage, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_channel_factory) {
    parent::__construct(
      'Plugin/MediaThumbnail',
      $namespaces,
      $module_handler,
      MediaThumbnailInterface::class,
      MediaThumbnail::class
    );
    $this->configFactory = $config_factory;
    $this->fileUsage = $file_usage;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerChannelFactory = $logger_channel_factory;

    $this->alterInfo('media_thumbnails_media_thumbnail_info');
    $this->setCacheBackend($cache_backend, 'media_thumbnails_media_thumbnail_plugins');
    $this->plugins = [];

    // Build a list of unique mime types supported by thumbnail plugins.
    $definitions = $this->getDefinitions();
    foreach ($definitions as $id => $definition) {
      foreach ($definition['mime'] as $mime) {
        $this->plugins[$mime] = $id;
      }
    }
  }

  /**
   * Create a new media thumbnail.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media object.
   */
  public function createThumbnail(MediaInterface $media) {
    // Get a thumbnail plugin id for supported media types.
    if (!$plugin = $this->getPluginId($media)) {
      return;
    }
    // Get the global configuration to pass it to the plugins.
    $config = $this->configFactory->get('media_thumbnails.settings')->get();

    // Create a plugin instance.
    try {
      /** @var \Drupal\media_thumbnails\Plugin\MediaThumbnailInterface $instance */
      $instance = $this->createInstance($plugin, $config);
    }
    catch (PluginException $e) {
      $this->loggerChannelFactory->get('media_thumbnails')->error('Error creating thumbnail plugin: @message', [
        '@message' => $e->getMessage(),
      ]);
      return;
    }

    // Create the thumbnail file using the plugin.
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->getSource($media);
    if (!$file = $instance->createThumbnail($file->getFileUri())) {
      return;
    }

    // Add this file to the media entity.
    $media->set('thumbnail', $file);
  }

  /**
   * Update a media thumbnail.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media object.
   */
  public function updateThumbnail(MediaInterface $media) {
    $this->deleteThumbnail($media);
    $this->createThumbnail($media);
  }

  /**
   * Delete a media thumbnail.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media object.
   */
  public function deleteThumbnail(MediaInterface $media) {

    // Get the thumbnail file object.
    /** @var \Drupal\file\FileInterface $thumbnail */
    $thumbnail = $this->getThumbnail($media);

    // Remove the thumbnail from the media entity.
    $media->set('thumbnail', NULL);

    // Early exit if thumbnail not found.
    if (!$thumbnail) {
      return;
    }

    // Don't delete thumbnails used in other places.
    $usage = $this->fileUsage->listUsage($thumbnail);
    $count = 0;
    array_walk_recursive($usage, static function () use (&$count) {
      $count++;
    });

    if ($count > 1) {
      return;
    }

    // Don't delete generic default thumbnails.
    $generic_thumbnails_dir = $this->configFactory->get('media.settings')
      ->get('icon_base_uri');
    $thumbnail_file_uri = $thumbnail->getFileUri();
    $has_generic_thumbnail = strpos($thumbnail_file_uri, $generic_thumbnails_dir) !== FALSE;
    if ($has_generic_thumbnail) {
      return;
    }

    // Delete the thumbnail file.
    try {
      $thumbnail->delete();
    }
    catch (EntityStorageException $e) {
      $this->loggerChannelFactory->get('media_thumbnails')->error('Error deleting thumbnail file: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Get the source file object for a media entity, if any.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The given media entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The loaded file object.
   */
  public function getSource(MediaInterface $media) {
    try {
      return $this->getFileObject($media, $media->getSource()
        ->getConfiguration()['source_field']);
    }
    catch (\Exception $e) {
      $this->loggerChannelFactory->get('media_thumbnails')->error('Error getting source file: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Get the thumbnail file object for a media entity, if any.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The given media entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The loaded file object.
   */
  public function getThumbnail(MediaInterface $media) {
    try {
      return $this->getFileObject($media, 'thumbnail');
    }
    catch (\Exception $e) {
      $this->loggerChannelFactory->get('media')->error('Error retrieving media storage: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Get a media file object, either source or thumbnail.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The given media entity.
   * @param string $field_name
   *   The field name of the source file, or 'thumbnail'.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The loaded file object.
   *
   * @throws \Exception
   *   Thrown when the target ID cannot be retrieved.
   */
  public function getFileObject(MediaInterface $media, $field_name) {
    // Fetch the thumbnail file id, if any.
    try {
      $fid = $media->get($field_name)->first()->getValue()['target_id'];
    }
    catch (\Exception $e) {
      $this->loggerChannelFactory->get('media_thumbnails')->error('Error getting file object for field "@field_name": @message', [
        '@field_name' => $field_name,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
    // Return the corresponding file object, if any.
    return $this->entityTypeManager
      ->getStorage('file')
      ->load($fid);
  }

  /**
   * Check if the media source is a local file.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return bool
   *   TRUE if there is a local file, FALSE otherwise.
   */
  public function isLocal(MediaInterface $media): bool {
    $source = $media->getSource()->getConfiguration()['source_field'];
    return $media->get($source) instanceof FileFieldItemList;
  }

  /**
   * Get the thumbnail plugin id for a media entity, if any.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return string|null
   *   Plugin id if there is a plugin, NULL otherwise.
   *
   * @throws \Exception
   *   Thrown when there is an issue retrieving the file or its data.
   */
  public function getPluginId(MediaInterface $media) {
    if (!$this->isLocal($media)) {
      return NULL;
    }
    $source = $media->getSource()->getConfiguration()['source_field'];
    try {
      $first = $media->get($source)->first();
      $file = $first ? $first->getValue() : NULL;
    }
    catch (MissingDataException $e) {
      $this->loggerChannelFactory->get('media_thumbnails')->error('Error retrieving plugin ID for media: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
    if (!isset($file['target_id'])) {
      return NULL;
    }
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityTypeManager
      ->getStorage('file')
      ->load($file['target_id']);
    if (!$file) {
      return NULL;
    }
    $mime = $file->getMimeType();
    return $this->plugins[$mime] ?? NULL;
  }

  /**
   * Check if the media source has a thumbnail plugin.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return bool
   *   TRUE if there is a plugin, FALSE otherwise.
   *
   * @throws \Exception
   *   Thrown when there is an issue retrieving the file or its data.
   */
  public function hasPlugin(MediaInterface $media): bool {
    return (bool) $this->getPluginId($media);
  }

}
