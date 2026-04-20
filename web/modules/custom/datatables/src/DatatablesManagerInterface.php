<?php

namespace Drupal\datatables;

/**
 * Interface for the DataTables manager service.
 */
interface DatatablesManagerInterface {

  /**
   * Returns default DataTables options.
   *
   * @return array
   *   An array of default options.
   */
  public function getDefaultOptions();

  /**
   * Formats a render array table as a DataTable.
   *
   * @param array $table
   *   A render array for a table.
   * @param array $options
   *   An array of DataTables options.
   *
   * @return array
   *   A render array for a DataTable.
   */
  public function formatTable(array $table, array $options = []);

  /**
   * Returns an array of available DataTables extensions.
   *
   * @return array
   *   An array of available extensions, keyed by extension name.
   */
  public function getAvailableExtensions();

}
