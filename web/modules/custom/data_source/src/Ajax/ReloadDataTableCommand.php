<?php

declare(strict_types=1);

namespace Drupal\data_source\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command for reloading DataTable.
 */
class ReloadDataTableCommand implements CommandInterface {

  /**
   * The table ID to reload.
   *
   * @var string
   */
  protected $tableId;

  /**
   * Constructs a ReloadDataTableCommand object.
   *
   * @param string $table_id
   *   The DataTable ID to reload.
   */
  public function __construct($table_id = null) {
    $this->tableId = $table_id;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'reloadDataTable',
      'tableId' => $this->tableId,
    ];
  }

}
