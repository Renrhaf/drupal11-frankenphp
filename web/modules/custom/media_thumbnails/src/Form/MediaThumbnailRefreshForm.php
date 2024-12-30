<?php

namespace Drupal\media_thumbnails\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\media_thumbnails\Batch\RefreshBatch;

/**
 * Implements thumbnail refresh confirmation form.
 */
class MediaThumbnailRefreshForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'media_thumbnails_refresh_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    return $this->t('Refresh the thumbnails for all media entities?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->t('Are you sure you want to refresh the thumbnails for all media entities? Thumbnails for @count entities will be refreshed.', ['@count' => number_format(RefreshBatch::count())]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): string {
    return $this->t('Refresh');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('media_thumbnails.admin');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    batch_set(RefreshBatch::createBatch());
  }

}
