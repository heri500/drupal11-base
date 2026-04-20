<?php

namespace Drupal\data_source;

/**
 * Defines status constants for requests.
 */
final class StatusRequest {
  public const STATUS = [
    0 => 'Non-Aktif',
    1 => 'Aktif',
  ];
  public const STATUSCOLOR = [
    0 => 'danger',
    1 => 'primary',
  ];

  public const STATUSICON = [
    0 => '<i class="fa-solid fa-circle-xmark text-danger"></i>',
    1 => '<i class="fa-solid fa-circle-check text-primary"></i>',
  ];
}
