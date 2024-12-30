<?php

namespace Drupal\media_thumbnails\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the media thumbnails config form.
 */
class MediaThumbnailConfigForm extends ConfigFormBase {

  /**
   * Stores a module manager.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typedConfigManager, ModuleHandlerInterface $module_handler) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_thumbnails_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('media_thumbnails.settings');
    $form['width'] = [
      '#default_value' => $config->get('width'),
      '#description' => $this->t('The width for the generated thumbnails. Height will be calculated automatically.'),
      '#title' => $this->t('Thumbnail width'),
      '#type' => 'number',
    ];
    $form['bgcolor'] = [
      '#collapsed' => FALSE,
      '#collapsible' => TRUE,
      '#title' => $this->t('Background color'),
      '#type' => 'fieldset',
    ];
    $form['bgcolor']['bgcolor_active'] = [
      '#default_value' => $config->get('bgcolor_active'),
      '#title' => $this->t('Add a custom background color. Uncheck to keep transparency.'),
      '#type' => 'checkbox',
    ];
    $form['bgcolor']['bgcolor_value'] = [
      '#default_value' => $config->get('bgcolor_value'),
      '#description' => $this->t('Background color for transparent thumbnails, for plugins supporting this feature.'),
      '#title' => $this->t('Background color'),
      '#type' => 'color',
    ];
    $form['no_thumbnail_update'] = [
      '#default_value' => $config->get('no_thumbnail_update'),
      '#description' => $this->t('Checking this box prevents thumbnails on Media entities from being overwritten when the Media entity is saved. When this box is checked, updating a thumbnail requires first deleting the existing thumbnail.'),
      '#title' => $this->t('Restrict thumbnail update'),
      '#type' => 'checkbox',
    ];
    $form['allow_thumbnail_edit'] = [
      '#default_value' => $config->get('allow_thumbnail_edit'),
      '#description' => $this->t("This will enable the Media thumbnail field to be added to the edit form.  This is disabled by default and the field must be added to the individual media type's form display settings."),
      '#title' => $this->t('Allow thumbnail editing'),
      '#type' => 'checkbox',
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('media_thumbnails.settings')
      ->set('width', $form_state->getValue('width'))
      ->set('bgcolor_active', $form_state->getValue('bgcolor_active'))
      ->set('bgcolor_value', $form_state->getValue('bgcolor_value'))
      ->set('no_thumbnail_update', $form_state->getValue('no_thumbnail_update'))
      ->set('allow_thumbnail_edit', $form_state->getValue('allow_thumbnail_edit'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['media_thumbnails.settings'];
  }

}
