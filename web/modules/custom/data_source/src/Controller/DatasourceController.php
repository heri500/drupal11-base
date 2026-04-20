<?php

namespace Drupal\data_source\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\data_source\Service\DataSourceService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\data_source\StatusRequest;
use Drupal\user\Entity\User;
use Drupal\data_source\Service\FileLinkGenerator;

/**
 * Provides route responses for the Data Source module.
 */
class DatasourceController extends ControllerBase {

  /**
   * The data source service.
   *
   * @var \Drupal\data_source\Service\DataSourceService
   */
  protected $dataSourceService;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  protected FileLinkGenerator $fileLinkGenerator;

  protected $moduleHandler;


  /**
   * Constructs a DatasourceController object.
   *
   * @param \Drupal\data_source\Service\DataSourceService $data_source_service
   *   The data source service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    DataSourceService $data_source_service,
    RequestStack $request_stack,
    FileLinkGenerator $file_link_generator,
    \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
  ) {
    $this->dataSourceService = $data_source_service;
    $this->requestStack = $request_stack;
    $this->fileLinkGenerator = $file_link_generator;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('data_source.service'),
      $container->get('request_stack'),
      $container->get('data_source.file_link_generator'),
      $container->get('module_handler')
    );
  }

  /**
   * Returns data from the specified table in DataTables format.
   *
   * @param string|null $table_name
   *   The name of the table to query.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the data in DataTables format.
   */
  public function getData($table_name = NULL) {
    if (empty($table_name)) {
      return new JsonResponse([
        'draw' => 0,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
      ]);
    }
    $statusIcon = StatusRequest::STATUSICON;
    $request = $this->requestStack->getCurrentRequest();
    $dt_params = $request->query->all();
    $status_can_edit = $this->dataSourceService->allowEditOnprocess($table_name);
    // Get table fields and validate table existence
    $field_id = $this->dataSourceService->getTableFieldsId($table_name);
    if (!empty($field_id)){
      $field_id = $field_id[0];
    }
    $field_data = $this->dataSourceService->getTableFields($table_name);
    if (empty($field_data)) {
      return new JsonResponse(['error' => 'No field definitions found for table ' . $table_name], 404);
    }

    // Check if table exists
    if (!$this->dataSourceService->tableExists($table_name)) {
      return new JsonResponse(['error' => 'Table ' . $table_name . ' does not exist.'], 404);
    }
    $dt_params['table_name'] = $table_name;
    // Get Field Index that sent to get only field with the same index
    $selected_field = !empty($dt_params['field_index']) ? json_decode($dt_params['field_index']) : [];
    // Map DataTables parameters to our service parameters
    $params = $this->mapDataTablesParams($dt_params, $field_data, $selected_field);
    //$field_data = $params['fields'];
    // if ($table_name == 'user_bonus') {
    //  dpm($table_name);
    //  dpm($params['fields_only']);
    // }
    //dpm($params['fields']);
    // Get data using the service
    $field_data_only = [];
    $counter = 0;
    foreach ($field_data as $key => $value_data){
      if (!empty($selected_field)) {
        if (in_array($counter, $selected_field)) {
          $field_data_only[] = $key;
        }
      }else{
        $field_data_only[] = $key;
      }
      $counter++;
    }
    $result = $this->dataSourceService->fetchRecords($table_name, $field_data_only, $params);
    // Check expressions
    $has_expressions = $this->dataSourceService->getTableExpression($table_name);
    if (!empty($has_expressions)){
      foreach ($has_expressions as $Expression){
        $field_data[$Expression['alias']] = [
          'name' => $Expression['alias'],
          'type' => $Expression['type']
        ];
      }
    }else{
      if (isset($params['expressions'])) {
        foreach ($params['expressions'] as $expression) {
          $field_data[$expression['alias']] = [
            'name' => $expression['alias'],
            'type' => $expression['type']
          ];
        }
      }
    }
    // Check Left Join
    $has_leftjoin = $this->dataSourceService->getTableLeftJoin($table_name);
    if (!empty($has_leftjoin)){
      foreach ($has_leftjoin as $LeftJoin){
        if (!empty($LeftJoin['field_name'])) {
          $ljCounter = 0;
          foreach ($LeftJoin['field_name'] as $fieldName) {
            if (isset($field_data[$fieldName])){
              $newFieldName = $LeftJoin['alias'].'_'.$fieldName;
            }else{
              $newFieldName = $fieldName;
            }
            $field_data[$newFieldName] = [
              'name' => $newFieldName,
              'type' => $LeftJoin['field_type'][$ljCounter],
            ];
            $ljCounter++;
          }
        }
      }
    }
    // Prepare response in DataTables format
    $output = [
      'draw' => isset($dt_params['draw']) ? (int) $dt_params['draw'] : 1,
      'recordsTotal' => $result['total'],
      'recordsFiltered' => $result['filtered_total'],
      'data' => [],
      'drupalSettings' => [
        'datatables' => [
          'hasdetail' => $dt_params['hasdetail'],
          'tableId' => $table_name,
        ],
      ]
    ];

    // Format data rows
    $editable = !empty($dt_params['editable']) && $dt_params['editable'] == 1;
    $view_detail = !empty($dt_params['hasdetail']) && $dt_params['hasdetail'] == 1;
    $deletable = !empty($dt_params['deletable']) && $dt_params['deletable'] == 1;
    if ($editable){
      $canEdit = 1;
    }else{
      $canEdit = 0;
    }
    if ($deletable){
      $canDelete = 1;
    }else{
      $canDelete = 0;
    }
    if ($view_detail){
      $view_detail = 1;
    }else{
      $view_detail = 0;
    }
    // filter field for selected field only
    $new_selected_field = [];
    $counter = 0;
    if (!empty($params['fields'])){
      $new_field_data = [];
      foreach ($params['fields'] as $key => $value_data){
        $new_field_data[] = $key;
      }
    }
    if (!empty($selected_field)) {
      foreach ($selected_field as $field_key) {
        if (isset($new_field_data[$field_key])) {
          if (isset($field_data[$new_field_data[$field_key]])) {
            $new_selected_field[$new_field_data[$field_key]] = $field_data[$new_field_data[$field_key]];
          }
        }
      }
    }
    else {
      $new_selected_field = $field_data;
    }
    $field_data = $new_selected_field;
    foreach ($result['records'] as $record) {
      foreach ($field_data as $key => $field) {
        if (str_starts_with($key, 'status')) {
          if ($record->{$key} == 'PAID' || $record->{$key} == 'completed'){
            $RecStatus = 1;
          }else if ($record->{$key} == 'UNPAID'){
            $RecStatus = 0;
          }else if ($record->{$key} == 'finished'){
            $RecStatus = 2;
          }else{
            $RecStatus = 0;
          }
          if ($RecStatus > $status_can_edit){
            $canEdit = 0;
            $canDelete = 0;
          }else{
            $canEdit = 1;
            $canDelete = 1;
          }
        }
      }
      $row = [];
      if ($view_detail){
        $row[] = '';
      }
      // Add edit button if requested
      if ($editable) {
        if ($canEdit) {
          $row[] = '<div class="icon-edit"><a title="click to edit record" data-id="' . $record->{$field_id} . '" class="edit-icon" href="#"><i class="fa-solid fa-pen-to-square"></i></a></div>';
        }else{
          $row[] = '<div class="disable-icon-edit"><a title="record lock" data-id="' . $record->{$field_id} . '" class="lock-icon icon-danger" href="#"><i class="fa-solid fa-lock"></i></a></div>';
        }
      }
      if ($deletable) {
        if ($canDelete) {
          $row[] = '<div class="icon-edit"><a title="click to delete record" data-id="' . $record->{$field_id} . '" class="delete-icon icon-danger" href="#"><i class="fa-solid fa-trash-can"></i></a></div>';
        }else{
          $row[] = '<div class="disable-icon-edit"><a title="record lock" data-id="' . $record->{$field_id} . '" class="lock-icon icon-danger" href="#"><i class="fa-solid fa-lock"></i></a></div>';
        }
      }
      // Add all fields to the row
      foreach ($field_data as $key => $field) {
        if (str_starts_with($key, 'status')) {
          if ($field['type'] == 'varchar' || $field['type'] == 'text'){
            $row[] = $record->{$key};
          }else {
            $row[] = $statusIcon[$record->{$key}];
          }
        } else if (str_starts_with($key, 'uid')) {
          if (!empty($record->{$key})) {
            $user = User::load($record->{$key});
            $username = $user->getAccountName();
            $row[] = $username;
          } else {
            $row[] = '-';
          }
        } else if (isset($field['type']) && (str_starts_with($key, 'date') || str_ends_with($key, 'date') ||
          $field['type'] == 'date' || $field['type'] == 'timestamp without time zone' || $field['type'] == 'timestamp with time zone'
        )) {
          if (!empty($record->{$key})) {
            $date = (new \DateTime($record->{$key}))->format('d-m-Y');
          }else{
            $date = '-';
          }
          $row[] = $date;
        } else if (str_starts_with($key, 'created') || str_starts_with($key, 'changed') || str_starts_with($key, 'updated')) {
          if (!empty($record->{$key})) {
            $date = (new \DateTime($record->{$key}))->format('d-m-Y');
          }else{
            $date = '-';
          }
          $row[] = $date;
        } else if (str_starts_with($key, 'file_id')) {
          if (!empty($record->{$key})) {
            $file_link = '<div class="d-grid status-cell">' . $this->fileLinkGenerator->renderLink($record->{$key}) . '</div>';
            $row[] = $file_link;
          }else{
            $row[] = '-';
          }
        } else if (str_starts_with($key, 'stock')) {
          if (empty($record->{$key})){
            $record->{$key} = 0;
          }
          $row[] = '<div id="'.$record->{$field_id}.'" class="stock-editable">'.$record->{$key}.'</div>';
        } else if (isset($field['type']) && $field['type'] == 'jsonb'){
          if (!empty($record->{$key})){
            $field_json = json_decode($record->{$key});
            $ViewData = '';
            if (!empty($field_json)){
              foreach ($field_json as $idx => $json_val){
                if ($json_val){
                  $json_val = '<i class="fa-solid fa-circle-check text-primary"></i>';
                }else{
                  $json_val = '<i class="fa-solid fa-circle-xmark text-danger"></i>';
                }
                $ViewData .= $idx.' : '.$json_val.'<br>';
              }
            }
            $row[] = $ViewData;
          }
        } else if (isset($field['type']) && $field['type'] == 'boolean') {
          if ($record->{$key}) {
            $row[] = '<i class="fa-solid fa-circle-check text-primary"></i>';
          } else {
            $row[] = '<i class="fa-solid fa-circle-xmark text-danger"></i>';
          }
        }else if (isset($field['type']) && $field['type'] == 'uuid'){
          //$short_id = substr($record->{$key}, 0, 4) . '...' . substr($record->{$key}, -4);
          $row[] = $record->{$key};
        } else if (str_contains($key, 'url')){
          if (empty($record->{$key})) {
            if (!empty($dt_params['url_empty_upload'])){
              $button_text = $dt_params['btn_text_upload'] ?? 'UPLOAD';
              $row[] = '<a class="btn btn-sm btn-outline-danger" onclick="UploadImage(\''.$record->{$field_id}.'\'); return false;" href="#" data-id= "'.$record->{$field_id}.'">'.$button_text.'</a>';
            }else {
              $row[] = '<i class="fa-solid fa-circle-xmark text-danger"></i>';
            }
          }else{
            $proxy_url = $record->{$key};
            // Check if google_drive_upload module is enabled
            if ($this->moduleHandler->moduleExists('google_drive_upload')) {
              $file_id = \Drupal\google_drive_upload\GoogleDriveUrlHelper::extractFileId($record->{$key});
              if ($file_id) {
                $proxy_url = \Drupal\google_drive_upload\GoogleDriveUrlHelper::convertToProxyUrl($record->{$key});
              }
            }
            if (isset($dt_params['imgtransactionid'])){
              $row[] = '<a class="btn btn-sm btn-outline-success" onclick="OpenImageView(this)" href="#" data-'.$dt_params['imgtransactionid'].'= "'.$record->{$field_id}.'" data-url="' . $proxy_url . '">VIEW</a>';
            }else {
              $row[] = '<a class="btn btn-sm btn-outline-success" onclick="OpenImageView(this)" href="#" data-url="' . $proxy_url . '">VIEW</a>';
            }
          }
        } else if ($key === 'code') {
          if (!empty($record->{$key})){
            $splitCode = explode('GHANI0000',$record->{$key});
            if (isset($splitCode[1])){
              $row[] = $splitCode[1];
            }else{
              $row[] = $record->{$key};
            }
          }else{
            $row[] = '-';
          }
        } else {
          $row[] = $record->{$key};
        }
      }
      $output['data'][] = $row;
    }
    return new JsonResponse($output);
  }

  /**
   * Maps DataTables request parameters to our service parameters.
   *
   * @param array $dt_params
   *   The DataTables parameters.
   * @param array $fields
   *   Available fields.
   *
   * @return array
   *   Mapped parameters for our service.
   */
  protected function mapDataTablesParams(array $dt_params, array $fields, array $selected_field): array
  {
    // create searchable fields from table column
    if (!empty($dt_params['field_index'])) {
      $field_idx = json_decode($dt_params['field_index']);
    }
    $searchable_field = [];
    if (!empty($dt_params)){
      $colIdx = 0;
      foreach ($dt_params as $param_key => $param_value){
        if ($param_key == 'editable' && $param_value){
          $colIdx++;
        }else if ($param_key == 'deletable' && $param_value){
          $colIdx++;
        }else if ($param_key == 'hasdetail' && $param_value){
          $colIdx++;
        }else if ($param_key == 'columns' && !empty($param_value)){
          $counter = 0;
          $counter_field_idx = 0;
          foreach ($param_value as $idx => $col_data){
            if (is_array($col_data) && !empty($col_data)){
              foreach ($col_data as $col_key => $col_value){
                if ($counter >= $colIdx){
                  if ($col_key == 'searchable' && $col_value == 'true'){
                    $searchable_field[] = $field_idx[$counter_field_idx];
                  }
                }
              }
              if ($counter >= $colIdx) {
                $counter_field_idx++;
              }
            }
            $counter++;
          }
        }
      }
    }
    //dpm($searchable_field);
    $params = [];
    if (!empty($dt_params['conditions'])){
      $Condition = json_decode(urldecode($dt_params['conditions']), TRUE);
      $params['conditions'] = $Condition;
    }
    // get table if has expressions
    $Expressions = [];
    if (!empty($dt_params['expressions'])){
      $Expressions = json_decode(urldecode($dt_params['expressions']), TRUE);
      $params['expressions'] = $Expressions;
    }
    $has_expressions = $this->dataSourceService->getTableExpression($dt_params['table_name']);
    $has_expressions = array_merge($has_expressions, $Expressions);
    if (!empty($has_expressions)){
      foreach ($has_expressions as $Expression){
        if (empty($Expression['type'])){
          $Expression['type'] = 'string';
        }
        $fields[$Expression['alias']] = [
          'name' => $Expression['alias'],
          'type' => $Expression['type']
        ];
      }
    }
    // get table left join
    if (isset($dt_params['left_join_field'])) {
      $has_leftjoin = $this->dataSourceService->getTableLeftJoin($dt_params['table_name'], $dt_params['left_join_field']);
    }else{
      $has_leftjoin = $this->dataSourceService->getTableLeftJoin($dt_params['table_name']);
    }
    if (!empty($has_leftjoin)) {
      foreach ($has_leftjoin as $LeftJoin) {
        if (!empty($LeftJoin['field_name'])) {
          $ljCounter = 0;
          foreach ($LeftJoin['field_name'] as $fieldName) {
            $fields[$fieldName] = [
              'name' => $fieldName,
              'type' => $LeftJoin['field_type'][$ljCounter],
              'source' => 'left join',
              'alias' => $LeftJoin['alias'],
            ];
            $ljCounter++;
          }
        }
      }
    }
    $params['fields'] = $fields;
    //dpm($params['fields']);
    $params['fields_only'] = $this->dataSourceService->getTableFieldsNameOnly($fields);

    //Collecting Selected Field
    // filter field for selected field only
    $new_selected_field = [];
    $counter = 0;
    if (!empty($params['fields'])){
      $new_field_data = [];
      foreach ($params['fields'] as $key => $value_data){
        $new_field_data[] = $key;
      }
    }
    if (!empty($selected_field)) {
      foreach ($selected_field as $field_key) {
        if (isset($new_field_data[$field_key])) {
          $new_selected_field[$new_field_data[$field_key]] = $params['fields'][$new_field_data[$field_key]];
        }
      }
    }
    else {
      $new_selected_field = $params['fields'];
    }
    $params['selected_fields'] = $new_selected_field;
    $params['selected_fields_name'] = $this->dataSourceService->getTableFieldsNameOnly($new_selected_field);
    // Search value
    $params['search_value'] = !empty($dt_params['search']['value']) ? $dt_params['search']['value'] :
      (!empty($dt_params['sSearch']) ? $dt_params['sSearch'] : NULL);
    // Search fields
    if (empty($searchable_field)) {
      $params['search_fields'] = $this->dataSourceService->getSearchFields($dt_params['table_name'] ?? NULL);
    }else{
      $search_field = [];
      foreach ($searchable_field as $idx_search){
        if (isset($params['fields_only'][$idx_search])) {
          if (isset($params['fields'][$params['fields_only'][$idx_search]])
            && isset($params['fields'][$params['fields_only'][$idx_search]]['source'])
          ){
            $AliasTable = $params['fields'][$params['fields_only'][$idx_search]]['alias'];
            $search_field[] = $AliasTable.'.'.$params['fields_only'][$idx_search];
          }else {
            $search_field[] = 'ta.'.$params['fields_only'][$idx_search];
          }
        }
      }
      $params['search_fields'] = $search_field;
    }
    // Sorting (supporting both new and legacy DataTables parameters)
    if (!empty($dt_params['order']) && is_array($dt_params['order'])) {
      // New DataTables format
      $sort_index = isset($dt_params['order'][0]['column']) ?
        (int) $dt_params['order'][0]['column'] : 0;

      // Adjust for editable column if present
      if (!empty($dt_params['hasdetail']) && $dt_params['hasdetail'] == 1) {
        if (!empty($dt_params['editable']) && $dt_params['editable'] == 1) {
          $sort_index = max(0, $sort_index - 3);
        }else{
          $sort_index = max(0, $sort_index - 1);
        }
        $params['view_detail'] = 1;
      }else{
        if (!empty($dt_params['editable']) && $dt_params['editable'] == 1) {
          $sort_index = max(0, $sort_index - 2);
        }else{
          $sort_index = max(0, $sort_index);
        }
      }
      $params['order_by'] = isset($params['selected_fields_name'][$sort_index]) ? $params['selected_fields_name'][$sort_index] : $params['selected_fields_name'][0];
      $params['order_index'] = $sort_index;
      $params['order_direction'] = isset($dt_params['order'][0]['dir']) ?
        strtoupper($dt_params['order'][0]['dir']) : 'ASC';
    }
    else {
      // Legacy DataTables format
      $sort_index = isset($dt_params['iSortCol_0']) ?
        (int) $dt_params['iSortCol_0'] : 0;

      // Adjust for editable column if present
      if (!empty($dt_params['editable']) && $dt_params['editable'] == 1) {
        $sort_index = max(0, $sort_index - 1);
      }

      $params['order_by'] = isset($params['selected_fields_name'][$sort_index]) ? $params['selected_fields_name'][$sort_index] : $params['selected_fields_name'][0];
      $params['order_direction'] = isset($dt_params['sSortDir_0']) ?
        strtoupper($dt_params['sSortDir_0']) : 'DESC';
    }
    // Pagination (supporting both new and legacy DataTables parameters)
    if (isset($dt_params['start']) && isset($dt_params['length'])) {
      // New DataTables format
      $params['range'] = [
        'start' => (int) $dt_params['start'],
        'length' => (int) $dt_params['length'],
      ];
    }
    elseif (isset($dt_params['iDisplayStart']) && isset($dt_params['iDisplayLength'])) {
      // Legacy DataTables format
      $params['range'] = [
        'start' => (int) $dt_params['iDisplayStart'],
        'length' => (int) $dt_params['iDisplayLength'],
      ];
    }

    // Add GROUP BY support
    if (!empty($dt_params['group_by'])) {
      $GroupBy = json_decode(urldecode($dt_params['group_by']), TRUE);
      $params['group_by'] = $GroupBy;
    }

    return $params;
  }

}
