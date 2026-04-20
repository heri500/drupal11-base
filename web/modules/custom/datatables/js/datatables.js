/**
 * @file
 * Provides integration of the jQuery DataTables plugin.
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Converts PHP-marked JS function strings back into real functions.
   */
  function datatablesPrepareOptions(options) {
    for (const key in options) {
      if (Object.prototype.hasOwnProperty.call(options, key)) {
        const val = options[key];
        if (val && typeof val === 'object' && val.__dt_js_function) {
          // Convert string back into a real function
          options[key] = eval('(' + val.__dt_js_function + ')');
        }
      }
    }
    return options;
  }

  /**
   * Attaches the datatable behavior to elements.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches datatable behavior.
   */
  Drupal.behaviors.datatable = {
    attach: function (context, settings) {
      // Check if settings exists.
      if (settings.datatables === undefined) {
        return;
      }

      once('datatable', 'table.datatable', context).forEach(function (element) {
        var $table = $(element);
        var id = $table.attr('id');
        console.log(id);
        if (settings.datatables[id] !== undefined) {
          var datatable_settings = settings.datatables[id].datatable_settings;
          console.log(datatable_settings);

          // Set the default page length.
          if (datatable_settings.pageLength === undefined) {
            datatable_settings.pageLength = 10;
          }

          // Check for a stateSave parameter.
          if (datatable_settings.stateSave === undefined) {
            // Default stateSave to true.
            datatable_settings.stateSave = true;
          }

          // Add tableTools if requested.
          if (settings.datatables[id].tabletools && datatable_settings.dom === undefined) {
            datatable_settings.dom = 'Bfrtip';
            datatable_settings.buttons = [
              'copy', 'csv', 'excel', 'pdf', 'print', 'colvis'
            ];
          }

          // Prepare options (convert PHP callbacks to functions).
          datatable_settings = datatablesPrepareOptions(datatable_settings);

          // Initialize the table.
          $table.DataTable(datatable_settings);
        }
      });
    }
  };
})(jQuery, Drupal, once);
