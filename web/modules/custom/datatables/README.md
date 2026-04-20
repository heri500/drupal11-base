# DataTables for Drupal

This module provides integration of the jQuery DataTables plugin into Drupal.
DataTables is a powerful jQuery plugin for adding advanced interaction controls to HTML tables.

## Features
- Integrates with Drupal's existing table theming system
- Supports multiple DataTables on a single page
- Provides a theme function and a render element
- Configurable default settings
- Support for DataTables extensions (Buttons, Responsive, etc.)
- Drupal 9, 10, and 11 compatibility

## Requirements
- Drupal 9.x, 10.x, or 11.x
- jQuery 3.6+ or jQuery 4.x (for Drupal 11)
- DataTables library (2.0.1+ recommended)

## Installation

### Using Composer (recommended)

```bash
composer require drupal/datatables
```

### Manual Installation
1. Download the DataTables module and place it in your modules directory.
2. Download the DataTables library from https://datatables.net/download/ and extract it to `libraries/datatables`.
  - Ensure the main JavaScript file is at `libraries/datatables/datatables.min.js`
  - Ensure the main CSS file is at `libraries/datatables/datatables.min.css`
3. Enable the module through the UI or using Drush:
   ```bash
   drush en datatables
   ```

## Usage

### As a render element
```php
$element = [
  '#type' => 'datatable',
  '#header' => $header,
  '#rows' => $rows,
  '#datatable_options' => [
    'pageLength' => 25,
    'searching' => TRUE,
  ],
];
```

### As a theme function
```php
$output = [
  '#theme' => 'datatable',
  '#header' => $header,
  '#rows' => $rows,
  '#datatable_options' => [
    'ordering' => TRUE,
    'paging' => TRUE,
  ],
];
```

### Using the service
```php
$table = [
  '#type' => 'table',
  '#header' => $header,
  '#rows' => $rows,
];

$datatable = \Drupal::service('datatables.manager')->formatTable($table, [
  'pageLength' => 50,
  'searching' => TRUE,
]);

return $datatable;
```

## Configuration

Visit `admin/config/user-interface/datatables` to configure default settings for all DataTables.

## DataTables 2.0 and jQuery 4.x Compatibility

This version of the module has been updated to work with DataTables 2.0+ and jQuery 4.x, which is used in Drupal 11. The main changes include:

1. Updated JavaScript to use modern ES6 syntax
2. Updated library references to use DataTables 2.0+
3. Added proper responsive design support
4. Added support for the modern Buttons extension (replacing legacy TableTools)
5. Improved service architecture for better extensibility

## Extensions

DataTables extensions are supported through the libraries system. To use an extension:

1. Download the extension from https://datatables.net/extensions/
2. Place it in the `libraries/datatables/extensions/` directory
3. Configure your DataTable to use the extension:

```php
$table = [
  '#theme' => 'datatable',
  '#header' => $header,
  '#rows' => $rows,
  '#datatable_options' => [
    'dom' => 'Bfrtip',
    'buttons' => ['copy', 'csv', 'excel', 'pdf', 'print'],
  ],
  '#tabletools' => TRUE,
];
```

## Credits

- Maintained for Drupal 9/10/11 by the DataTables module maintainers
- Originally developed for Drupal 7 by Damien McKenna and others
- DataTables plugin by SpryMedia Ltd. (https://datatables.net)
