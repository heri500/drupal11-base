<?php

namespace Drupal\datatables\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\datatables\DatatablesManagerInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;

/**
 * Configure DataTables settings for this site.
 */
class DatatablesAdminForm extends ConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The datatables manager.
   *
   * @var \Drupal\datatables\DatatablesManagerInterface
   */
  protected $datatablesManager;

  /**
   * Constructs a new DatatablesAdminForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\datatables\DatatablesManagerInterface $datatables_manager
   *   The datatables manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config_manager, ModuleHandlerInterface $module_handler, DatatablesManagerInterface $datatables_manager) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->moduleHandler = $module_handler;
    $this->datatablesManager = $datatables_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('module_handler'),
      $container->get('datatables.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'datatables_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['datatables.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('datatables.settings');
    $form['library'] = [
      '#type' => 'details',
      '#title' => $this->t('Library settings'),
      '#open' => TRUE,
    ];

    $form['library']['library_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DataTables library path'),
      '#description' => $this->t('The path to the DataTables library relative to the Drupal root (usually <code>libraries/datatables</code> or handled by Composer).'),
      '#default_value' => $config->get('library_path') ?: 'libraries/datatables',
    ];

    $form['library']['cdn'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use CDN'),
      '#description' => $this->t('Use the DataTables CDN instead of a local copy of the library.'),
      '#default_value' => $config->get('cdn') ?: FALSE,
    ];

    $form['library']['cdn_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CDN URL'),
      '#description' => $this->t('The URL to the DataTables CDN. Leave empty to use the default CDN.'),
      '#default_value' => $config->get('cdn_url') ?: 'https://cdn.datatables.net/2.0.1/js/dataTables.min.js',
      '#states' => [
        'visible' => [
          ':input[name="cdn"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['defaults'] = [
      '#type' => 'details',
      '#title' => $this->t('Default settings'),
      '#open' => TRUE,
    ];

    $form['defaults']['page_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Default page length'),
      '#description' => $this->t('The default number of rows to display per page.'),
      '#default_value' => $config->get('page_length') ?: 10,
      '#min' => 1,
    ];

    $form['defaults']['state_save'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Save state'),
      '#description' => $this->t('Save the state of the table (paging position, ordering state, etc) so that it can be restored when the page is reloaded.'),
      '#default_value' => $config->get('state_save') !== NULL ? $config->get('state_save') : TRUE,
    ];

    $form['defaults']['responsive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Responsive by default'),
      '#description' => $this->t('Enable responsive mode by default for all tables.'),
      '#default_value' => $config->get('responsive') !== NULL ? $config->get('responsive') : TRUE,
    ];

    $form['defaults']['auto_width'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto width'),
      '#description' => $this->t('Enable or disable automatic column width calculation.'),
      '#default_value' => $config->get('auto_width') !== NULL ? $config->get('auto_width') : TRUE,
    ];

    $form['extensions'] = [
      '#type' => 'details',
      '#title' => $this->t('Extensions'),
      '#open' => TRUE,
    ];

    // Get available extensions
    $extensions = $this->datatablesManager->getAvailableExtensions();
    $enabled_extensions = $config->get('extensions') ?: [];
    foreach($enabled_extensions as $key => $value){
      if ($value){
        $enabled_extensions[$key] = $key;
      }
    }
    $form['extensions']['enabled_extensions'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled extensions'),
      '#description' => $this->t('Select which DataTables extensions to enable.'),
      '#options' => $extensions,
      '#default_value' => $enabled_extensions,
    ];

    // Allow other modules to alter the form
    $this->moduleHandler->alter('datatables_admin_form', $form, $form_state);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $extensions = $form_state->getValue('enabled_extensions');
    foreach ($extensions as $key => $value){
      if ($value){
        $extensions[$key] = 1;
      }
    }
    $this->config('datatables.settings')
      ->set('library_path', $form_state->getValue('library_path'))
      ->set('cdn', $form_state->getValue('cdn'))
      ->set('cdn_url', $form_state->getValue('cdn_url'))
      ->set('page_length', $form_state->getValue('page_length'))
      ->set('state_save', $form_state->getValue('state_save'))
      ->set('responsive', $form_state->getValue('responsive'))
      ->set('auto_width', $form_state->getValue('auto_width'))
      ->set('extensions', $extensions)
      ->save();
    parent::submitForm($form, $form_state);
  }

}
