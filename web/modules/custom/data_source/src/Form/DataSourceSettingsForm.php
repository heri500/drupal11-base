<?php

namespace Drupal\data_source\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure data source settings for this site.
 */
class DataSourceSettingsForm extends FormBase {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a DataSourceSettingsForm object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'data_source_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get current setting from state
    $current_database = $this->state->get('data_source.target_database', 'default');

    // Get all available database connections
    $database_connections = $this->getAvailableConnections();

    $form['target_database'] = [
      '#type' => 'select',
      '#title' => $this->t('Target Database Connection'),
      '#description' => $this->t('Select the database connection to be used by the Data Source service.'),
      '#options' => $database_connections,
      '#default_value' => $current_database,
      '#required' => TRUE,
    ];

    $form['connection_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Available Connections'),
      '#description' => $this->t('List of database connections defined in your settings.php file.'),
      '#open' => FALSE,
    ];

    // Display connection details
    $connection_details = [];
    foreach ($database_connections as $key => $label) {
      try {
        $connection = Database::getConnection('default', $key);
        $connection_options = $connection->getConnectionOptions();
        $connection_details[] = [
          'name' => $label,
          'driver' => $connection_options['driver'] ?? 'Unknown',
          'host' => $connection_options['host'] ?? 'N/A',
          'database' => $connection_options['database'] ?? 'N/A',
        ];
      } catch (\Exception $e) {
        $connection_details[] = [
          'name' => $label,
          'driver' => 'Error',
          'host' => 'Connection failed',
          'database' => $e->getMessage(),
        ];
      }
    }

    $form['connection_info']['connections_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Connection'),
        $this->t('Driver'),
        $this->t('Host'),
        $this->t('Database'),
      ],
      '#rows' => array_map(function($detail) {
        return [
          $detail['name'],
          $detail['driver'],
          $detail['host'],
          $detail['database'],
        ];
      }, $connection_details),
      '#empty' => $this->t('No database connections found.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save to state instead of config
    $this->state->set('data_source.target_database', $form_state->getValue('target_database'));

    $this->messenger()->addMessage($this->t('Data Source settings have been saved.'));
  }

  /**
   * Get available database connections.
   *
   * @return array
   *   Array of connection keys and labels.
   */
  protected function getAvailableConnections() {
    $connections = [];

    // Get connection info from Database class
    $connection_info = Database::getAllConnectionInfo();

    foreach ($connection_info as $key => $info) {
      $connections[$key] = $this->formatConnectionLabel($key, $info);
    }

    return $connections;
  }

  /**
   * Format connection label for display.
   *
   * @param string $key
   *   Connection key.
   * @param array $info
   *   Connection info array.
   *
   * @return string
   *   Formatted label.
   */
  protected function formatConnectionLabel($key, $info) {
    $default_info = $info['default'] ?? [];
    $database = $default_info['database'] ?? 'Unknown';
    $driver = $default_info['driver'] ?? 'Unknown';

    return sprintf('%s (%s - %s)', ucfirst($key), $driver, $database);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $target_database = $form_state->getValue('target_database');

    // Validate that the selected connection exists and is accessible
    try {
      $connection = Database::getConnection('default', $target_database);
      // Test the connection
      $connection->query('SELECT 1')->execute();
    } catch (\Exception $e) {
      $form_state->setErrorByName('target_database',
        $this->t('Unable to connect to the selected database: @error',
          ['@error' => $e->getMessage()]));
    }

    parent::validateForm($form, $form_state);
  }
}
