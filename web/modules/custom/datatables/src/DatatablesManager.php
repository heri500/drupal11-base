<?php

namespace Drupal\datatables;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Asset\LibraryDiscoveryInterface;

/**
 * Provides DataTables service.
 */
class DatatablesManager implements DatatablesManagerInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The library discovery service.
   *
   * @var \Drupal\Core\Asset\LibraryDiscoveryInterface
   */
  protected $libraryDiscovery;

  /**
   * Constructs a new DatatablesManager.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $library_discovery
   *   The library discovery service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, LibraryDiscoveryInterface $library_discovery) {
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->libraryDiscovery = $library_discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOptions() {
    $config = $this->configFactory->get('datatables.settings');
    $default_options = [
      'pageLength' => $config->get('page_length') ?: 10,
      'lengthMenu' => [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
      'stateSave' => (bool) $config->get('state_save') ?: TRUE,
      'responsive' => (bool) $config->get('responsive') ?: TRUE,
      'autoWidth' => (bool) $config->get('auto_width') ?: TRUE,
      'processing' => TRUE,
    ];

    // Allow other modules to alter the default options.
    $this->moduleHandler->alter('datatables_default_options', $default_options);

    return $default_options;
  }

  /**
   * {@inheritdoc}
   */
  public function formatTable(array $table, array $options = []) {
    // Merge in the default options.
    $options = array_merge($this->getDefaultOptions(), $options);

    // Format the table as a datatable.
    $build = [
      '#theme' => 'datatable',
      '#header' => $table['#header'] ?? [],
      '#rows' => $table['#rows'] ?? [],
      '#attributes' => $table['#attributes'] ?? [],
      '#caption' => $table['#caption'] ?? NULL,
      '#colgroups' => $table['#colgroups'] ?? [],
      '#sticky' => $table['#sticky'] ?? FALSE,
      '#responsive' => $table['#responsive'] ?? TRUE,
      '#datatable_options' => $options,
      '#tabletools' => !empty($options['buttons']),
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableExtensions() {
    $extensions = [
      'buttons' => $this->t('Buttons (Export, Column visibility)'),
      'responsive' => $this->t('Responsive tables'),
      'fixedheader' => $this->t('Fixed Header'),
      'fixedcolumns' => $this->t('Fixed Columns'),
      'scroller' => $this->t('Scroller'),
      'rowgroup' => $this->t('Row Grouping'),
      'rowreorder' => $this->t('Row Reordering'),
      'searchpanes' => $this->t('Search Panes'),
      'select' => $this->t('Selection'),
    ];

    // Allow other modules to alter the available extensions.
    $this->moduleHandler->alter('datatables_extensions', $extensions);

    return $extensions;
  }

  /**
   * Translates a string.
   *
   * @param string $string
   *   A string containing the English text to translate.
   * @param array $args
   *   An associative array of replacements.
   * @param array $options
   *   An associative array of additional options.
   *
   * @return string
   *   The translated string.
   */
  protected function t($string, array $args = [], array $options = []) {
    return $this->translation()->translate($string, $args, $options);
  }

  /**
   * Gets the translation service.
   *
   * @return \Drupal\Core\StringTranslation\TranslationInterface
   *   The translation service.
   */
  protected function translation() {
    return \Drupal::translation();
  }

}
