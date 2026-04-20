<?php

namespace Drupal\data_source;

/**
 * Defines status constants for requests.
 */
final class StandardVariable {
  public const JENIS_KELAMIN = [
    0 => 'LAKI-LAKI',
    1 => 'PEREMPUAN',
  ];

  public const AGAMA = [
    0 => 'ISLAM',
    1 => 'Kristen Protestan',
    2 => 'Kristen Katolik',
    3 => 'Hindu',
    4 => 'Buddha',
    5 => 'Konghucu',
  ];

  public const PAYMENT_METHOD = [
    //0 => 'CASH/TUNAI',
    1 => 'TRANSFER BANK',
    //2 => 'QRIS',
    //3 => 'KARTU DEBIT',
    //4 => 'KARTU KREDIT',
  ];

  public const PAYMENT_STATUS = [
    0 => 'UNPAID',
    1 => 'PAID',
  ];

  public const PAYMENT_APPROVAL_STATUS = [
    0 => 'pending',
    1 => 'success',
  ];

  public const DELIVERY_STATUS = [
    0 => 'BELUM DIKIRIM',
    1 => 'DIKIRIM',
  ];
}
