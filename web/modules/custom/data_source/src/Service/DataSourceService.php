<?php

namespace Drupal\data_source\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use DateTimeInterface;
/**
 * Service for data source operations.
 */
class DataSourceService {
  protected $startDate;

  protected $endDate;
  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  public $database;

  /**
   * Table name prefix used in all database operations.
   *
   * @var string
   */
  protected $tablePrefix = '';

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidService;

  /**
   * Constructs a DataSourceService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   *   The UUID service.
   */
  public function __construct(
    Connection $database,
    AccountProxyInterface $current_user,
    FileSystemInterface $file_system,
    EntityTypeManagerInterface $entity_type_manager,
    StateInterface $state,
    LoggerChannelFactoryInterface $logger_factory,
    UuidInterface $uuid_service
  ) {
    $this->currentUser = $current_user;
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->loggerFactory = $logger_factory->get('data_source');
    $this->uuidService = $uuid_service;
    // Get the target database from state
    $target_database = $this->state->get('data_source.target_database', 'default');
    // Use the configured database connection
    $this->database = Database::getConnection('default', $target_database);
  }

  /**
   * Generate a new UUID.
   *
   * @return string
   *   A new UUID string.
   */
  public function generateUuid(): string {
    return $this->uuidService->generate();
  }

  /**
   * Get the current target database key.
   *
   * @return string
   *   The target database key.
   */
  public function getTargetDatabase() {
    return $this->state->get('data_source.target_database', 'default');
  }

  /**
   * Set the target database connection.
   *
   * @param string $target_database
   *   The target database key.
   */
  public function setTargetDatabase($target_database) {
    $this->database = Database::getConnection('default', $target_database);
  }

  /**
   * Checks if a table exists.
   *
   * @param string $table_name
   *   The name of the table to check.
   *
   * @return bool
   *   TRUE if the table exists, FALSE otherwise.
   */
  public function tableExists($table_name) {
    $connection = $this->database;
    $driver = $connection->driver();
    $full_table_name = $this->tablePrefix . $table_name;
    if ($driver === 'pgsql') {
      // PostgreSQL: use to_regclass to check if the table exists.
      $query = $connection->query("SELECT to_regclass('public.{$full_table_name}')");
      $result = $query->fetchField();
      return !empty($result);
    }
    elseif ($driver === 'mysql') {
      // MySQL: check the information_schema.tables
      $query = $connection->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table", [
        ':table' => $full_table_name,
      ]);
      $result = $query->fetchField();
      return $result > 0;
    }

    // Fallback for other database drivers
    return $connection->schema()->tableExists($full_table_name);
  }


  /**
   * Gets the full table name with prefix.
   *
   * @param string $table_name
   *   Base table name.
   *
   * @return string
   *   Full table name with prefix.
   */
  public function getFullTableName($table_name) {
    return $this->tablePrefix . $table_name;
  }

  /**
   * Fetches records from the specified table with DataTables server-side processing support.
   *
   * @param string $table_name
   *   Table name without prefix.
   * @param array $fields
   *   Fields to select.
   * @param array $params
   *   Query parameters including:
   *   - order_by: Field to order by.
   *   - order_direction: Sort direction (ASC/DESC).
   *   - search_value: Search string.
   *   - search_fields: Fields to search in.
   *   - range: Array with 'start' and 'length' keys for pagination.
   *
   * @return array
   *   Array containing:
   *   - records: The database records.
   *   - filtered_total: Total records after filtering.
   *   - total: Total records before filtering.
   */
  /**
   * Fetches records from the specified table with DataTables server-side processing support.
   *
   * @param string $table_name
   *   Table name without prefix.
   * @param array $fields
   *   Fields to select.
   * @param array $params
   *   Query parameters including:
   *   - order_by: Field to order by.
   *   - order_direction: Sort direction (ASC/DESC).
   *   - search_value: Search string.
   *   - search_fields: Fields to search in.
   *   - range: Array with 'start' and 'length' keys for pagination.
   *   - group_by: Array of fields to group by.
   *
   * @return array
   *   Array containing:
   *   - records: The database records.
   *   - filtered_total: Total records after filtering.
   *   - total: Total records before filtering.
   */
  public function fetchRecords($table_name, array $fields, array $params = []) {
    $full_table_name = $this->getFullTableName($table_name);
    // First, get the total count of records in the table
    $total_query = $this->database->select($full_table_name, 'ta', ['prefix' => FALSE]);
    $total_query->addExpression('COUNT(*)', 'count');

    if (!empty($params['conditions'])) {
      $db_and = $total_query->andConditionGroup();
      $db_or = null;
      $has_regular_conditions = false;
      foreach ($params['conditions'] as $field_cond) {
        // Check if this is an expression-based condition
        if (isset($field_cond['expression'])) {
          // Use where() method for raw SQL expressions
          $args = isset($field_cond['args']) ? $field_cond['args'] : [];
          $total_query->where($field_cond['expression'], $args);
          continue;
        }

        // We have regular conditions
        $has_regular_conditions = true;

        // Handle regular field conditions (your existing code)
        $operator_sign = '=';
        foreach ($field_cond as $key => $value) {
          if ($key == 'operator') {
            $operator_sign = $value;
          } else {
            if (is_array($value) && !empty($value)) {
              $db_and->condition($key, $value, 'IN');
            } else {
              if (in_array($operator_sign, ['LIKE', 'ILIKE'])) {
                if (empty($db_or)) {
                  $db_or = $total_query->orConditionGroup();
                }
                $db_or->condition($key, '%' . $this->database->escapeLike($value) . '%', $operator_sign);
              } else {
                $db_and->condition($key, $value, $operator_sign);
              }
            }
          }
        }
      }

      // Only apply condition groups if we had regular conditions
      if ($has_regular_conditions) {
        if (!empty($db_or)) {
          $db_and->condition($db_or);
        }
        $total_query->condition($db_and);
      }
    }

    $total = $total_query->execute()->fetchField();

    // Build the base query object
    $query = $this->database->select($full_table_name, 'ta', ['prefix' => FALSE])
      ->fields('ta', $fields);
    // Create a query clone for getting filtered count
    $filtered_count_query = clone $query;
    $filtered_count_query->countQuery();
    // Get filtered count
    $filtered_total = !empty($params['search_value'])
      ? $filtered_count_query->execute()->fetchField()
      : $total;

    // Handle ordering (DataTables may send multiple sort columns)
    if (!empty($params['order_by'])) {
      $order_direction = !empty($params['order_direction']) ? $params['order_direction'] : 'ASC';
      $query->orderBy($params['order_by'], $order_direction);
    }

    // Add pagination - PostgreSQL uses LIMIT and OFFSET
    if (!empty($params['range']) && isset($params['range']['start']) && isset($params['range']['length']) && $params['range']['length'] != -1) {
      $query->range($params['range']['start'], $params['range']['length']);
    }
    //add expression if table has expression need to execute
    $table_expression = $this->getTableExpression($table_name);
    if (!empty($table_expression) && is_array($table_expression)){
      foreach ($table_expression as $idx => $Expression){
        $query->addExpression($Expression['expression'], $Expression['alias']);
      }
    }
    if (!empty($params['expressions'])){
      foreach ($params['expressions'] as $idx => $Expression){
        $query->addExpression($Expression['expression'], $Expression['alias']);
      }
    }

    //add leftjoin if table has expression need to execute
    $table_leftjoin = $this->getTableLeftJoin($table_name);
    if (!empty($table_leftjoin)){
      foreach ($table_leftjoin as $LeftJoin) {
        $AliasTable = !empty($LeftJoin['alias']) ? $LeftJoin['alias'] : 'al';
        $query->leftJoin($LeftJoin['table_name'], $AliasTable, 'ta.' . $LeftJoin['target_field'] . ' = ' . $AliasTable . '.' . $LeftJoin['source_field']);
        if (is_array($LeftJoin['field_name']) && !empty($LeftJoin['field_name'])) {
          foreach ($LeftJoin['field_name'] as $field_name) {
            $query->addField($AliasTable, $field_name);
          }
        }
      }
    }
    if (!empty($params['conditions'])) {
      $db_and = $query->andConditionGroup();
      $db_or = null;
      $has_regular_conditions = false;

      foreach ($params['conditions'] as $field_cond) {
        // Check if this is an expression-based condition
        if (isset($field_cond['expression'])) {
          // Use where() method for raw SQL expressions
          $args = isset($field_cond['args']) ? $field_cond['args'] : [];
          $query->where($field_cond['expression'], $args);
          continue;
        }

        // We have regular conditions
        $has_regular_conditions = true;

        // Handle regular field conditions (your existing code)
        $operator_sign = '=';
        foreach ($field_cond as $key => $value) {
          if ($key == 'operator') {
            $operator_sign = $value;
          } else {
            if (is_array($value) && !empty($value)) {
              $db_and->condition($key, $value, 'IN');
            } else {
              if (in_array($operator_sign, ['LIKE', 'ILIKE'])) {
                if (empty($db_or)) {
                  $db_or = $query->orConditionGroup();
                }
                $db_or->condition($key, '%' . $this->database->escapeLike($value) . '%', $operator_sign);
              } else {
                $db_and->condition($key, $value, $operator_sign);
              }
            }
          }
        }
      }

      // Only apply condition groups if we had regular conditions
      if ($has_regular_conditions) {
        if (!empty($db_or)) {
          $db_and->condition($db_or);
        }
        $query->condition($db_and);
      }
    }

    // Add search conditions with PostgreSQL ILIKE for case-insensitive search
    if (!empty($params['search_value']) && !empty($params['search_fields'])) {
      $db_or = $query->orConditionGroup();
      foreach ($params['search_fields'] as $field) {
        // Use ILIKE for PostgreSQL case-insensitive search
        if (in_array($field, ['id','created_at'])){
          $field = 'ta.'.$field;
        }
        $db_or->condition($field, '%' . $this->database->escapeLike($params['search_value']) . '%', 'ILIKE');
      }
      $query->condition($db_or);

      // Apply same conditions to count query
      $filtered_count_query_or = $filtered_count_query->orConditionGroup();
      foreach ($params['search_fields'] as $field) {
        if (in_array($field, ['id','created_at'])){
          $field = 'ta.'.$field;
        }
        $filtered_count_query_or->condition($field, '%' . $this->database->escapeLike($params['search_value']) . '%', 'ILIKE');
      }
      $filtered_count_query->condition($filtered_count_query_or);
    }

    // Add GROUP BY support
    if (!empty($params['group_by']) && is_array($params['group_by'])) {
      foreach ($params['group_by'] as $group_field) {
        $query->groupBy($group_field);
      }
    }

    // Ordered Row
    if (isset($params['order_index'])){
      $FieldName = $params['selected_fields_name'][$params['order_index']];
      if (!empty($FieldName)) {
        $query->orderBy($FieldName, $params['order_direction']);
      }
    }

    // Execute and get records
    $records = $query->execute()->fetchAll();
    return [
      'records' => $records,
      'filtered_total' => (int) $filtered_total,
      'total' => (int) $total,
    ];
  }

  public function fetchRecordsById($table_name, array $fields, $id_value, $order_by = null) {
    $full_table_name = $this->getFullTableName($table_name);
    if (empty($fields) || !is_array($fields)){
      $fields = $this->getTableFields($table_name);
      $new_fields = [];
      if (!empty($fields) && count($fields)){
        foreach ($fields as $key => $field_data){
          $new_fields[] = $key;
        }
        $fields = $new_fields;
      }
    }
    $query = $this->database->select($full_table_name, 'ta', ['prefix' => FALSE])
      ->fields('ta', $fields);

    // Check table left join
    $left_join = $this->getTableLeftJoin($table_name);
    if (is_array($left_join) && !empty($left_join)) {
      foreach ($left_join as $left_join_data) {
        $AliasTable = !empty($left_join_data['alias']) ? $left_join_data['alias'] : 'al';
        $query->leftJoin($left_join_data['table_name'], $AliasTable, 'ta.' . $left_join_data['target_field'] . ' = ' . $AliasTable . '.' . $left_join_data['source_field']);
        if (is_array($left_join_data['field_name']) && !empty($left_join_data['field_name'])) {
          foreach ($left_join_data['field_name'] as $field_name) {
            $query->addField($AliasTable, $field_name);
          }
        }
      }
    }

    $field_id = $this->getTableFieldsId($table_name);
    if (!empty($field_id) && !empty($id_value)) {
      $db_and = $query->andConditionGroup();
      $db_and->condition('ta.'.$field_id[0], $id_value);
      $query->condition($db_and);
    }
    // Get table expression if any
    $has_expressions = $this->getTableExpression($table_name);
    if (!empty($has_expressions)){
      foreach ($has_expressions as $Expression){
        $query->addExpression($Expression['expression'], $Expression['alias']);
      }
    }
    if (!empty($order_by)){
      if (is_array($order_by)){
        foreach ($order_by as $order_data){
          $query->orderBy($order_data['field'], $order_data['direction']);
        }
      }
    }
    // Execute and get records
    return $query->execute()->fetchObject();
  }

  public function fetchRecordsByIds($table_name, array $fields, array $id_value, $order_by = null) {
    $full_table_name = $this->getFullTableName($table_name);
    if (empty($fields) || !is_array($fields)){
      $fields = $this->getTableFields($table_name);
      $new_fields = [];
      if (!empty($fields) && count($fields)){
        foreach ($fields as $key => $field_data){
          $new_fields[] = $key;
        }
        $fields = $new_fields;
      }
    }
    $query = $this->database->select($full_table_name, 'ta', ['prefix' => FALSE])
      ->fields('ta', $fields);

    // Check table left join
    $left_join = $this->getTableLeftJoin($table_name);
    if (is_array($left_join) && !empty($left_join)) {
      foreach ($left_join as $left_join_data) {
        $AliasTable = !empty($left_join_data['alias']) ? $left_join_data['alias'] : 'al';
        $query->leftJoin($left_join_data['table_name'], $AliasTable, 'ta.' . $left_join_data['target_field'] . ' = ' . $AliasTable . '.' . $left_join_data['source_field']);
        if (is_array($left_join_data['field_name']) && !empty($left_join_data['field_name'])) {
          foreach ($left_join_data['field_name'] as $field_name) {
            $query->addField($AliasTable, $field_name);
          }
        }
      }
    }

    $field_id = $this->getTableFieldsId($table_name);
    if (!empty($field_id) && !empty($id_value)) {
      $db_and = $query->andConditionGroup();
      $db_and->condition($field_id, $id_value, 'IN');
      $query->condition($db_and);
    }
    if (!empty($order_by)){
      if (is_array($order_by)){
        foreach ($order_by as $order_data){
          $query->orderBy($order_data['field'], $order_data['direction']);
        }
      }
    }
    // Execute and get records
    return $query->execute()->fetchAll();
  }

  /**
   * @param $table_name
   * @param array $fields
   * @param array $field_value
   * @param array $left_join
   * @param array $add_expression
   * @param array $group_by
   * @return array
   */
  public function fetchRecordsByField(
    $table_name,
    array $fields,
    array $field_value,
    array $add_expression,
    array $group_by,
    array|null $left_join = null,
    $order_by = null,
    $limit_view = null
  ): array
  {
    $full_table_name = $this->getFullTableName($table_name);
    $query = $this->database->select($full_table_name, 'ta', ['prefix' => FALSE]);
    if (!empty($fields)) {
      $query->fields('ta', $fields);
    }else{
      $fields = $this->getTableFields($table_name);
      $fields = $this->getTableFieldsNameOnly($fields);
      $query->fields('ta', $fields);
    }
    if (is_array($field_value) && !empty($field_value)) {
      $db_and = $query->andConditionGroup();
      $db_or = null;
      foreach ($field_value as $field_cond) {
        // Use ILIKE for PostgreSQL case-insensitive search
        $operator_sign = '=';
        foreach ($field_cond as $key => $value){
          if ($key == 'operator'){
            $operator_sign = $value;
          } else {
            if (is_array($value) && !empty($value)) {
              $db_and->condition($key, $value, 'IN');
            } else {
              if (in_array($operator_sign, ['LIKE', 'ILIKE'])){
                if (empty($db_or)){
                  $db_or = $query->orConditionGroup();
                }
                $db_or->condition($key, '%' . $this->database->escapeLike($value) . '%', $operator_sign);
              }else {
                $db_and->condition($key, $value, $operator_sign);
              }
            }
          }
        }
        if (!empty($db_or)) {
          $db_and->condition($db_or);
        }
      }
      $query->condition($db_and);
    }
    // check left join array and execute left join
    if (!is_null($left_join)) {
      $left_join = $this->getTableLeftJoin($table_name);
    }
    if (is_array($left_join) && !empty($left_join)) {
      foreach ($left_join as $left_join_data) {
        $AliasTable = !empty($left_join_data['alias']) ? $left_join_data['alias'] : 'al';
        $query->leftJoin($left_join_data['table_name'], $AliasTable, 'ta.' . $left_join_data['target_field'] . ' = ' . $AliasTable . '.' . $left_join_data['source_field']);
        if (is_array($left_join_data['field_name']) && !empty($left_join_data['field_name'])) {
          foreach ($left_join_data['field_name'] as $field_name) {
            $query->addField($AliasTable, $field_name);
          }
        }
      }
    }
    // check if there is any expression and execute it
    if (is_array($add_expression) && !empty($add_expression)) {
      foreach ($add_expression as $expression_data) {
        $query->addExpression($expression_data['expression'], $expression_data['alias']);
      }
    }else{
      $table_expression = $this->getTableExpression($table_name);
      if (!empty($table_expression) && is_array($table_expression)){
        foreach ($table_expression as $idx => $Expression){
          $query->addExpression($Expression['expression'], $Expression['alias']);
        }
      }
    }
    // check if there is any group by and execute it
    if (is_array($group_by) && !empty($group_by)) {
      foreach ($group_by as $groupby_data) {
        $query->groupBy($groupby_data['field']);
      }
    }
    if (!empty($order_by)){
      if (is_array($order_by)){
        foreach ($order_by as $order_data){
          $query->orderBy($order_data['field'], $order_data['direction']);
        }
      }
    }
    if (!empty($limit_view)){
      $query->range($limit_view['start'], $limit_view['length']);
    }
    // Execute and get records
    $records = $query->execute()->fetchAll();
    return $records;
  }
  public function getTotalRecord($table_name = '') {
    if (empty($table_name)) {
      return 0;
    }

    $query = $this->database->select($table_name, 't', ['prefix' => FALSE])
      ->countQuery();

    return $query->execute()->fetchField();
  }
  public function createOptions($table_name, $field_id, $field_value = []){
    $optionSelect = [];
    $field_data = $this->getTableFields($table_name);
    $new_fields = [];
    if (!empty($field_data) && count($field_data)){
      foreach ($field_data as $key => $field){
        $new_fields[] = $key;
      }
      $field_data = $new_fields;
    }
    if (empty($field_id)){
      $field_id = $this->getTableFieldsId($table_name);
    }
    if (!empty($table_name) && !empty($field_id) && !empty($field_value) && !empty($field_data)){
      if (is_array($field_value)){
        $new_field_value = [];
        $expression_data = [];
        foreach ($field_value as $fieldName){
          if (str_starts_with($fieldName, 'tgl')){
            $expression_data[] = ['expression' => 'DATE('.$fieldName.')', 'alias' => 'transform_date'];
            $new_field_value[] = 'transform_date';
          }else{
            $new_field_value[] = $fieldName;
          }
        }
        $field_value = $new_field_value;
      }
      $records = $this->fetchRecordsByField($table_name, $field_data,[],$expression_data,[]);
      foreach ($records as $optionData){
        if (is_array($field_value)){
          $valueData = [];
          foreach ($field_value as $fieldName){
            $valueData[] = $optionData->{$fieldName};
          }
          $valueData = implode('-',$valueData);
        }
        $optionSelect[$optionData->{$field_id}] = $valueData;
      }
    }
    return $optionSelect;
  }

  /**
   * @param $table_name
   * @param array $fieldsid_data
   * @return array|int|null
   */
  public function deleteTableById($table_name, array $fieldsid_data)
  {
    $query = null;
    if (!empty($table_name) && !empty($fieldsid_data) && is_array($fieldsid_data)) {
      $query = $this->database->delete($table_name)
        ->condition($fieldsid_data['field'], $fieldsid_data['value'])
        ->execute();
    }
    return $query;
  }

  /**
   * Checks if a record exists in a table.
   *
   * @param string $table_name
   *   The name of the table.
   * @param string $field
   *   The field name to check.
   * @param mixed $value
   *   The value to check for.
   *
   * @return bool
   *   TRUE if the record exists, FALSE otherwise.
   */
  public function recordExists($table_name, $field, $value) : bool {
    try {
      $query = $this->database->select($table_name, 't', ['prefix' => FALSE]);
      $query->fields('t', [$field]);
      $query->condition('t.' . $field, $value);
      $query->range(0, 1);

      $result = $query->execute()->fetchField();
      return !empty($result);
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Updates a record in a table.
   *
   * @param string $table_name
   *   The name of the table.
   * @param array $fields
   *   An array of field values to update, keyed by field name.
   * @param array $conditions
   *   An array of field conditions, keyed by field name.
   *
   * @return bool
   *   TRUE if the record was updated, FALSE otherwise.
   */
  public function updateTable($table_name, array $fields, array $conditions) {
    try {
      $query = $this->database->update($table_name)
        ->fields($fields)
        ->condition($conditions['field'], $conditions['value']);
      return $query->execute();
    }
    catch (\Exception $e) {
      \Drupal::logger('data_source')->error('updateTable: Database error updating table @table. Error: @message. Fields: @fields, Conditions: @cond', [
        '@table' => $table_name,
        '@message' => $e->getMessage(),
        '@fields' => print_r($fields, TRUE),
        '@cond' => print_r($conditions, TRUE),
      ]);
      return FALSE;
    }
  }

  /**
   * Inserts a record into a table.
   *
   * @param string $table_name
   *   The name of the table.
   * @param array $fields
   *   An array of field values to insert, keyed by field name.
   *
   * @return bool
   *   TRUE if the record was inserted, FALSE otherwise.
   */
  public function insertTableOld($table_name, array $fields) {
    try {
      $table_fields = $this->getTableFields($table_name);
      $table_fields = $this->getTableFieldsNameOnly($table_fields);
      // Add created timestamp if field exists
      if (in_array('created', $table_fields)) {
        $fields['created'] = date('Y-m-d H:i:s');
      }

      // Add changed timestamp if field exists
      if (in_array('changed', $table_fields)) {
        $fields['changed'] = date('Y-m-d H:i:s');
      }

      // Add user ID if field exists
      if (
        (in_array('uid', $table_fields) && !isset($fields['uid'])) ||
        (in_array('uid_created', $table_fields) && !isset($fields['uid_created']))
      ) {
        if (in_array('uid', $table_fields) && !isset($fields['uid'])) {
          $fields['uid'] = $this->currentUser->id();
        }else{
          $fields['uid_created'] = $this->currentUser->id();
        }
      }
      $query = $this->database->insert($table_name);
      $query->fields($fields);
      return $query->execute();
    }
    catch (\Exception $e) {
      // Log the error
      $this->loggerFactory->error(
        'Insert failed for @table: @message',
        ['@table' => $table_name, '@message' => $e->getMessage()]
      );
      // Re-throw so we can see the real error
      throw $e;
    }
  }

  /**
   * Inserts a record into a table.
   *
   * @param string $table_name
   *   The name of the table.
   * @param array $fields
   *   An array of field values to insert, keyed by field name.
   *
   * @return mixed
   *   The inserted ID (int or UUID string) if available, TRUE otherwise.
   */
  public function insertTable($table_name, array $fields) {
    try {
      $table_fields = $this->getTableFields($table_name);
      $table_fields = $this->getTableFieldsNameOnly($table_fields);

      // Add created timestamp if field exists
      if (in_array('created', $table_fields)) {
        $fields['created'] = date('Y-m-d H:i:s');
      }

      // Add changed timestamp if field exists
      if (in_array('changed', $table_fields)) {
        $fields['changed'] = date('Y-m-d H:i:s');
      }

      // Add user ID if field exists
      if (
        (in_array('uid', $table_fields) && !isset($fields['uid'])) ||
        (in_array('uid_created', $table_fields) && !isset($fields['uid_created']))
      ) {
        if (in_array('uid', $table_fields) && !isset($fields['uid'])) {
          $fields['uid'] = $this->currentUser->id();
        } else {
          $fields['uid_created'] = $this->currentUser->id();
        }
      }

      // Get primary keys
      $primary_keys = $this->getPrimaryKeysFromInformationSchema($table_name);

      // If table has NO primary key → simple insert, return TRUE
      if (empty($primary_keys)) {
        $this->database->insert($table_name)
          ->fields($fields)
          ->execute();
        return TRUE;
      }

      // We assume the 1st PK column is the main one (common case)
      $pk = $primary_keys[0];

      // Check if PK is UUID type
      $is_uuid = $this->isUuidColumn($table_name, $pk);

      // If PK is UUID and not provided → generate it
      if (!isset($fields[$pk]) && $is_uuid) {
        $fields[$pk] = $this->generateUuid();
      }

      // Build and execute the insert query
      $query = $this->database->insert($table_name)
        ->fields($fields);

      // Execute and capture the result
      $result = $query->execute();

      // Return the appropriate value based on PK type
      if ($is_uuid && isset($fields[$pk])) {
        // For UUID, return the generated/provided UUID
        return $fields[$pk];
      } elseif ($result) {
        // For auto-increment, execute() returns the last insert ID
        return $result;
      }

      return TRUE; // fallback
    }
    catch (\Exception $e) {
      $this->loggerFactory->error(
        'Insert failed for @table: @message',
        ['@table' => $table_name, '@message' => $e->getMessage()]
      );
      throw $e;
    }
  }

  private function isUuidColumn(string $table, string $column) {
    $query = $this->database->query("
    SELECT data_type
    FROM   information_schema.columns
    WHERE  table_name = :table
    AND    column_name = :column
  ", [
      ':table' => $table,
      ':column' => $column
    ]);

    return strtolower($query->fetchField()) === 'uuid';
  }

  /**
   * @param $table_name
   * @return array
   */
  public function getTableFields($table_name): array
  {
    try {
      $query = $this->database->select('information_schema.columns', 'c', ['prefix' => FALSE])
        ->fields('c', [
          'column_name',
          'data_type',
          'is_nullable',
          'column_default',
          'character_maximum_length'
        ])
        ->condition('table_name', $table_name)
        ->orderBy('ordinal_position');

      $result = $query->execute();
      $fields = [];

      foreach ($result as $row) {
        $fields[$row->column_name] = [
          'name' => $row->column_name,
          'type' => $row->data_type,
          'nullable' => $row->is_nullable === 'YES',
          'default' => $row->column_default,
          'length' => $row->character_maximum_length,
        ];
      }

      return $fields;

    } catch (\Exception $e) {
      \Drupal::logger('data_source')->error('Error getting table field details: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * @param $table_name
   * @return array
   */
  public function getTableFieldsNameOnly(array $field_data): array{
    $field_name = [];
    if (is_array($field_data) && !empty($field_data)){
      foreach ($field_data as $idx => $field_value){
        $field_name[] = $idx;
      }
    }
    return $field_name;
  }

  /**
   * @param $table_name
   * @return array
   */
  public function getTableFieldsTypeOnly(array $field_data): array{
    $field_type = [];
    if (is_array($field_data) && !empty($field_data)){
      foreach ($field_data as $idx => $field_value){
        $field_type[] = $field_value['type'];
      }
    }
    return $field_type;
  }


  /**
   * @param $table_name
   * @return array
   */
  public function getTableFieldsId($table_name): array {
    try {
      $database_type = $this->database->databaseType();

      // Primary approach: Use information_schema (works for PostgreSQL, MySQL, MariaDB)
      $primary_keys = $this->getPrimaryKeysFromInformationSchema($table_name);

      // If that fails, try database-specific fallbacks
      if (empty($primary_keys)) {
        switch ($database_type) {
          case 'pgsql':
            $primary_keys = $this->getPrimaryKeysPostgreSQL($table_name);
            break;
          case 'mysql':
            $primary_keys = $this->getPrimaryKeysMySQL($table_name);
            break;
          default:
            \Drupal::logger('data_source')->warning('Unsupported database type: @type', ['@type' => $database_type]);
        }
      }

      return $primary_keys;

    } catch (\Exception $e) {
      \Drupal::logger('data_source')->error('Error getting primary key: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Get primary keys using information_schema (universal approach)
   */
  private function getPrimaryKeysFromInformationSchema($table_name): array {
    try {
      $connection = $this->database;
      $connection_options = $connection->getConnectionOptions();
      $database_name = $connection_options['database'];

      // Get the prefix
      $prefix = $connection_options['prefix'] ?? '';
      if (is_array($prefix)) {
        $table_prefix = $prefix['default'] ?? '';
      } else {
        $table_prefix = $prefix;
      }

      // Build the full table name
      $full_table_name = $table_prefix . $table_name;

      // Try to get primary keys
      $primary_keys = [];

      // PostgreSQL specific query
      if ($connection->databaseType() === 'pgsql') {
        $query = $connection->query("
        SELECT a.attname AS column_name, a.attnum AS ordinal_position
        FROM pg_index i
        JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
        WHERE i.indrelid = :table_name::regclass
          AND i.indisprimary
        ORDER BY a.attnum
      ", [
          ':table_name' => $full_table_name,
        ]);

        foreach ($query as $row) {
          $primary_keys[] = $row->column_name;
        }
      }
      // MySQL/MariaDB specific query
      else {
        $query = $connection->query("
        SELECT kcu.column_name, kcu.ordinal_position
        FROM information_schema.key_column_usage kcu
        INNER JOIN information_schema.table_constraints tc
          ON kcu.constraint_name = tc.constraint_name
          AND kcu.table_name = tc.table_name
          AND kcu.table_schema = tc.table_schema
        WHERE kcu.table_name = :table_name
          AND tc.constraint_type = 'PRIMARY KEY'
          AND kcu.table_schema = :database_name
        ORDER BY kcu.ordinal_position
      ", [
          ':table_name' => $full_table_name,
          ':database_name' => $database_name,
        ]);

        foreach ($query as $row) {
          $primary_keys[] = $row->column_name;
        }
      }

      return $primary_keys;

    } catch (\Exception $e) {
      \Drupal::logger('data_source')->error('Error getting primary keys: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * PostgreSQL-specific fallback using pg_constraint
   */
  private function getPrimaryKeysPostgreSQL($table_name): array {
    try {
      $query = $this->database->query("
      SELECT a.attname as column_name, a.attnum as ordinal_position
      FROM pg_constraint c
      JOIN pg_class t ON c.conrelid = t.oid
      JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(c.conkey)
      WHERE t.relname = :table_name AND c.contype = 'p'
      ORDER BY a.attnum
    ", [':table_name' => $table_name]);

      $primary_keys = [];
      foreach ($query as $row) {
        $primary_keys[] = $row->column_name;
      }
      return $primary_keys;

    } catch (\Exception $e) {
      \Drupal::logger('data_source')->debug('PostgreSQL fallback failed: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * MySQL/MariaDB-specific fallback using SHOW KEYS
   */
  private function getPrimaryKeysMySQL($table_name): array {
    try {
      $query = $this->database->query("
      SHOW KEYS FROM {" . $table_name . "} WHERE Key_name = 'PRIMARY'
      ORDER BY Seq_in_index
    ");

      $primary_keys = [];
      foreach ($query as $row) {
        $primary_keys[] = $row->Column_name;
      }
      return $primary_keys;

    } catch (\Exception $e) {
      \Drupal::logger('data_source')->debug('MySQL fallback failed: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Returns the searchable fields for a given table.
   *
   * @param string $table_name
   *   The name of the table.
   *
   * @return array
   *   Array of searchable field names.
   */
  public function getSearchFields($table_name) {
    $field_data = [];
    $get_table_field = $this->getTableFields($table_name);
    if (!empty($get_table_field) && count($get_table_field)){
      foreach ($get_table_field as $key => $field){
        if (in_array($field['type'], ['varchar','date', 'text', 'jsonb', 'character varying', 'inet'])){
          $field_data[] = $key;
        }
      }
    }
    return $field_data;
  }

  public function allowEditOnprocess($table_name) {
    $allow_edit = 0;

    switch ($table_name) {
      case 'users':
      case 'product':
      case 'transactions':
        break;
      case 'commission_calculation_log':
        $allow_edit = 1;
        break;
      // Add cases for other tables here
    }
    return $allow_edit;
  }
  public function getTableExpression($table_name) : array {
    $expressions = [];
    switch ($table_name) {
      case 'user_pv_balance':
        $expressions[] = [
          'expression' => '(SELECT name FROM users WHERE id = (SELECT user_id FROM user_unit uu WHERE uu.id = ta.user_unit_id))',
          'alias' => 'member_name',
          'type' => 'varchar',
        ];
        $expressions[] = [
          "expression" => "(attribute->>'name')",
          "alias" => "unit_name",
          "type" => "varchar",
        ];
        break;
      // Add cases for other tables here
    }
    return $expressions;
  }
  public function getTableLeftJoin($table_name, $option = null) : array {
    $left_join = [];
    //alias, table_name, target_field, source_field, field_name as array
    switch ($table_name) {
      case 'user_bonus':
        // Get data user_bonus
        $left_join_data = [];
        $left_join_data['table_name'] = 'users';
        $field_data = $this->getTableFields($left_join_data['table_name']);
        $field_join = $this->getTableFieldsNameOnly($field_data);
        $field_type = $this->getTableFieldsTypeOnly($field_data);
        $left_join_data['alias'] = 'users';
        $left_join_data['target_field'] = 'user_id';
        $left_join_data['source_field'] = 'id';
        $left_join_data['field_name'] = $field_join;
        $left_join_data['field_type'] = $field_type;
        $left_join[] = $left_join_data;
        break;
      case 'transactions':
        // Get data transactions
        $left_join_data = [];
        $left_join_data['table_name'] = 'users';
        $left_join_data['alias'] = 'users';
        $left_join_data['target_field'] = 'user_id';
        $left_join_data['source_field'] = 'id';
        if (empty($option)) {
          $field_data = $this->getTableFields($left_join_data['table_name']);
          $field_join = $this->getTableFieldsNameOnly($field_data);
          $field_type = $this->getTableFieldsTypeOnly($field_data);
          $left_join_data['field_name'] = $field_join;
          $left_join_data['field_type'] = $field_type;
        }else if ($option == 'no-left-join-field'){
          $left_join_data['field_name'] = [];
          $left_join_data['field_type'] = [];
        }
        $left_join[] = $left_join_data;
        $left_join_data = [];
        $left_join_data['table_name'] = 'payments';
        $left_join_data['alias'] = 'p';
        $left_join_data['target_field'] = 'id';
        $left_join_data['source_field'] = 'transaction_id';
        if (empty($option)) {
          $left_join_data['field_name'] = ['payment_method','payment_amount','payment_url','id'];
          $left_join_data['field_type'] = ['text','numeric','text','uuid'];
        }else if ($option == 'no-left-join-field'){
          $left_join_data['field_name'] = [];
          $left_join_data['field_type'] = [];
        }
        $left_join[] = $left_join_data;
        break;
      case 'users':
        // Get data transactions
        $left_join_data = [];
        $left_join_data['table_name'] = 'user_info';
        $field_data = $this->getTableFields($left_join_data['table_name']);
        $field_join = $this->getTableFieldsNameOnly($field_data);
        $field_type = $this->getTableFieldsTypeOnly($field_data);
        $left_join_data['alias'] = 'user_info';
        $left_join_data['target_field'] = 'id';
        $left_join_data['source_field'] = 'user_id';
        $left_join_data['field_name'] = $field_join;
        $left_join_data['field_type'] = $field_type;
        $left_join[] = $left_join_data;
        break;
      case 'user_unit':
        // Get data user_bonus
        $left_join_data = [];
        $left_join_data['table_name'] = 'users';
        $field_data = $this->getTableFields($left_join_data['table_name']);
        $field_join = $this->getTableFieldsNameOnly($field_data);
        $field_type = $this->getTableFieldsTypeOnly($field_data);
        $left_join_data['alias'] = 'users';
        $left_join_data['target_field'] = 'user_id';
        $left_join_data['source_field'] = 'id';
        $left_join_data['field_name'] = $field_join;
        $left_join_data['field_type'] = $field_type;
        $left_join[] = $left_join_data;
        $left_join_data = [];
        $left_join_data['table_name'] = 'levels';
        $field_data = $this->getTableFields($left_join_data['table_name']);
        $field_join = $this->getTableFieldsNameOnly($field_data);
        $field_type = $this->getTableFieldsTypeOnly($field_data);
        $left_join_data['alias'] = 'levels';
        $left_join_data['target_field'] = 'level_id';
        $left_join_data['source_field'] = 'id';
        $left_join_data['field_name'] = $field_join;
        $left_join_data['field_type'] = $field_type;
        $left_join[] = $left_join_data;
        break;
      case 'user_referral':
        // Get data user referral
        $left_join_data = [];
        $left_join_data['table_name'] = 'user_info';
        $field_data = $this->getTableFields($left_join_data['table_name']);
        $field_join = $this->getTableFieldsNameOnly($field_data);
        $field_type = $this->getTableFieldsTypeOnly($field_data);
        $left_join_data['alias'] = 'user_info';
        $left_join_data['target_field'] = 'user_sub';
        $left_join_data['source_field'] = 'user_id';
        $left_join_data['field_name'] = $field_join;
        $left_join_data['field_type'] = $field_type;
        $left_join[] = $left_join_data;
        $left_join_data = [];
        $left_join_data['table_name'] = 'users';
        $field_data = $this->getTableFields($left_join_data['table_name']);
        $field_join = $this->getTableFieldsNameOnly($field_data);
        $field_type = $this->getTableFieldsTypeOnly($field_data);
        $left_join_data['alias'] = 'users';
        $left_join_data['target_field'] = 'user_sub';
        $left_join_data['source_field'] = 'id';
        $left_join_data['field_name'] = $field_join;
        $left_join_data['field_type'] = $field_type;
        $left_join[] = $left_join_data;
        break;
      case 'user_reapet_order':
        // Left join user_unit
        $left_join_data = [];
        $left_join_data['table_name'] = 'user_unit';
        $left_join_data['alias'] = 'uu';
        $left_join_data['target_field'] = 'user_unit_id';
        $left_join_data['source_field'] = 'id';
        $left_join_data['field_name'] = ['user_id'];
        $left_join_data['field_type'] = ['uuid'];
        $left_join[] = $left_join_data;

        // Left join transactions
        $left_join_data = [];
        $left_join_data['table_name'] = 'transactions';
        $left_join_data['alias'] = 't';
        $left_join_data['target_field'] = 'transaction_id';
        $left_join_data['source_field'] = 'id';
        $left_join_data['field_name'] = ['point_value_locking'];
        $left_join_data['field_type'] = ['numeric'];
        $left_join[] = $left_join_data;
        break;
      case 'user_pv_balance':
        // Get data user_unit
        $left_join_data = [];
        $left_join_data['table_name'] = 'user_unit';
        $field_data = $this->getTableFields($left_join_data['table_name']);
        $field_join = $this->getTableFieldsNameOnly($field_data);
        $field_type = $this->getTableFieldsTypeOnly($field_data);
        $left_join_data['alias'] = 'uu';
        $left_join_data['target_field'] = 'user_unit_id';
        $left_join_data['source_field'] = 'id';
        $left_join_data['field_name'] = $field_join;
        $left_join_data['field_type'] = $field_type;
        $left_join[] = $left_join_data;
        break;
    }
    return $left_join;
  }

  /**
   * Execute a raw SQL query and return the result.
   *
   * @param string $sql
   *   The SQL query string (use placeholders for safety).
   * @param array $args
   *   (optional) The arguments for placeholders.
   * @param string $fetch
   *   The fetch type: 'all', 'assoc', 'col', 'field', 'object'.
   *
   * @return array|object|string|int|float|bool|null
   *   Query result depending on fetch mode:
   *   - 'all': array of result objects.
   *   - 'assoc': array of associative arrays keyed by "id".
   *   - 'col': array of single column values.
   *   - 'field': single field value (string|int|float|bool|null).
   *   - 'object': single row object.
   */
  public function executeRawQuery(
    string $sql,
    array $args = [],
    string $fetch = 'all'
  ): array|object|string|int|float|bool|null {
    try {
      $result = $this->database->query($sql, $args);

      switch ($fetch) {
        case 'assoc':
          return $result->fetchAllAssoc('id'); // requires 'id' column
        case 'col':
          return $result->fetchCol();
        case 'field':
          return $result->fetchField();
        case 'object':
          return $result->fetchObject();
        case 'all':
        default:
          return $result->fetchAll();
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('Raw SQL failed: @msg', ['@msg' => $e->getMessage()]);
      return [];
    }
  }

  public function handleDateParameters(
    Request $request,
    ?DateTimeInterface &$param_start_date,
    ?DateTimeInterface &$param_end_date
  ): void {

    $start_date = $request->query->get('start_date');
    $end_date   = $request->query->get('end_date');

    try {
      if (!$start_date && !$end_date) {
        // Defaults
        $this->startDate = new DateTime('2023-01-01 00:00:00');
        $this->endDate   = new DateTime();
      }
      else {
        $this->startDate = $start_date ? new DateTime($start_date) : new DateTime('2023-01-01 00:00:00');
        $this->endDate   = $end_date   ? new DateTime($end_date)   : new DateTime();
      }

      // Ensure correct order
      if ($this->startDate > $this->endDate) {
        [$this->startDate, $this->endDate] = [$this->endDate, $this->startDate];
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('data_source')->error(
        'Invalid date format: @error',
        ['@error' => $e->getMessage()]
      );

      $this->startDate = new DateTime('2023-01-01 00:00:00');
      $this->endDate   = new DateTime();
    }

    $param_start_date = $this->startDate;
    $param_end_date   = $this->endDate;
  }

  public function addDateFilter(array $field_value, string $date_field = 'created_at'): array {
    if ($this->startDate && $this->endDate) {
      $field_value[] = ['operator' => '>=', 'ta.'.$date_field => $this->startDate->format('Y-m-d') . ' 00:00:00'];
      $field_value[] = ['operator' => '<=', 'ta.'.$date_field => $this->endDate->format('Y-m-d') . ' 23:59:59'];
    }
    return $field_value;
  }

  public function isAdminLogin(): bool {
    $roles = $this->currentUser->getRoles();
    return (in_array('administrator', $roles) || in_array('admin_web', $roles)) || $this->currentUser->id() == 1;
  }

}
