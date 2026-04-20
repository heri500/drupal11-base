<?php

namespace Drupal\datatables\Element;

use Drupal\Core\Render\Element\Table;
use Drupal\Core\Template\Attribute;

/**
 * Provides a render element for a datatable.
 *
 * @RenderElement("datatable")
 */
class Datatable extends Table {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();

    // Add datatable specific properties
    $info['#datatable_options'] = [];
    $info['#tabletools'] = FALSE;
    $info['#responsive'] = TRUE;

    // Override the theme with the datatable theme
    $info['#theme'] = 'datatable';

    return $info;
  }

}
