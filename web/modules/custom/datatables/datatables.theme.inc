<?php

/**
 * @file
 * Theme functions for the DataTables module.
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Template\Attribute;

/**
 * Prepares a header cell for a DataTables table.
 *
 * @param mixed $cell
 *   The header cell content.
 * @param array $options
 *   Additional options for the cell.
 *
 * @return array
 *   The prepared header cell.
 */
function _datatables_prepare_header_cell($cell, array $options = []) {
  $attributes = [];

  if (is_array($cell)) {
    if (isset($cell['data'])) {
      $data = $cell['data'];
      unset($cell['data']);
      $attributes = $cell;
    }
    else {
      $data = '';
    }
  }
  else {
    $data = $cell;
  }

  // Merge with additional options
  $attributes = array_merge($attributes, $options);

  return [
    'data' => $data,
    'attributes' => new Attribute($attributes),
  ];
}

/**
 * Prepares a body cell for a DataTables table.
 *
 * @param mixed $cell
 *   The body cell content.
 * @param array $options
 *   Additional options for the cell.
 *
 * @return array
 *   The prepared body cell.
 */
function _datatables_prepare_body_cell($cell, array $options = []) {
  $attributes = [];

  if (is_array($cell)) {
    if (isset($cell['data'])) {
      $data = $cell['data'];
      unset($cell['data']);
      $attributes = $cell;
    }
    else {
      $data = '';
    }
  }
  else {
    $data = $cell;
  }

  // Merge with additional options
  $attributes = array_merge($attributes, $options);

  return [
    'data' => $data,
    'attributes' => new Attribute($attributes),
  ];
}

/**
 * Process datatable options and add necessary classes and attributes.
 *
 * @param array $variables
 *   An associative array containing the datatable variables.
 */
function _datatables_process_options(&$variables) {
  // Generate a unique table ID if not provided
  if (empty($variables['attributes']['id'])) {
    $variables['attributes']['id'] = Html::getUniqueId('datatable');
  }

  // Add the datatable class
  if (!isset($variables['attributes']['class'])) {
    $variables['attributes']['class'] = [];
  }
  elseif (is_string($variables['attributes']['class'])) {
    $variables['attributes']['class'] = [$variables['attributes']['class']];
  }

  $variables['attributes']['class'][] = 'datatable';

  // Add responsive class if enabled
  if ($variables['responsive']) {
    $variables['attributes']['class'][] = 'responsive-enabled';
  }

  // Prepare default datatable options if not specified
  if (empty($variables['datatable_options'])) {
    $variables['datatable_options'] = [];
  }

  // Merge with default options
  $default_options = [
    'autoWidth' => TRUE,
    'ordering' => TRUE,
    'paging' => TRUE,
    'searching' => TRUE,
    'info' => TRUE,
    'responsive' => $variables['responsive'],
  ];

  $variables['datatable_options'] = array_merge($default_options, $variables['datatable_options']);

  // Process header columns for DataTables specific options
  if (!empty($variables['header'])) {
    $columns = [];
    foreach ($variables['header'] as $key => $cell) {
      $column_options = [];

      // Extract DataTables-specific properties
      if (is_array($cell)) {
        if (isset($cell['orderable'])) {
          $column_options['orderable'] = (bool) $cell['orderable'];
        }
        if (isset($cell['searchable'])) {
          $column_options['searchable'] = (bool) $cell['searchable'];
        }
        if (isset($cell['width'])) {
          $column_options['width'] = $cell['width'];
        }
        if (isset($cell['className'])) {
          $column_options['className'] = $cell['className'];
        }
        if (isset($cell['data'])) {
          // Set column data property for column definition
          $column_options['name'] = is_string($cell['data']) ? $cell['data'] : '';
        }
        if (isset($cell['attributes']['name'])) {
          // Set column data property for column definition
          $column_options['name'] = is_string($cell['attributes']['name']->__toString()) ? $cell['attributes']['name']->__toString() : '';
        }
      }

      $columns[] = $column_options;
    }
    // Add columns definition to DataTables options
    if (!empty($columns)) {
      $variables['datatable_options']['columns'] = $columns;
    }
  }

  // Add language options if site is not in English
  $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
  if ($language != 'en') {
    $variables['datatable_options']['language'] = [
      'url' => "//cdn.datatables.net/plug-ins/1.13.4/i18n/{$language}.json",
    ];
  }

  // Configure TableTools extension if enabled
  if ($variables['tabletools']) {
    $variables['datatable_options']['dom'] = 'Bfrtip';
    if (!isset($variables['datatable_options']['buttons'])) {
      $variables['datatable_options']['buttons'] = ['copy', 'csv', 'excel', 'pdf', 'print'];
    }
  }

  // Attach the datatable settings to drupalSettings
  $id = $variables['attributes']['id'];
  $variables['#attached']['drupalSettings']['datatables'][$id] = [
    'datatable_settings' => $variables['datatable_options'],
    'tabletools' => $variables['tabletools'],
  ];

  // Attach required libraries
  $variables['#attached']['library'][] = 'datatables/datatables';
  $variables['#attached']['library'][] = 'datatables/datatables.default';

  if ($variables['tabletools']) {
    $variables['#attached']['library'][] = 'datatables/datatables.tabletools';
  }

  // Add responsive library if enabled
  if ($variables['responsive']) {
    $variables['#attached']['library'][] = 'datatables/datatables.responsive';
  }
}
