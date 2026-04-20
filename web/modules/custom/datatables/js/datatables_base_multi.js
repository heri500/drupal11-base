/**
 * @file
 * JavaScript for request admin modal and autocomplete functionality.
 * Multi-table version to support multiple DataTables on the same page.
 * Save as: js/datatables_base_multi.js
 */
(function ($, Drupal, once) {
  'use strict';

  // Store multiple dataTable instances globally within this closure
  let dataTableInstances = {};
  let selectedIdsByTable = {};
  let tableIdSelected = '';
  /**
   * Attach behaviors to request admin forms and modals.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.formModal = {
    attach: function (context, settings) {
      const selectedProductsData = $("#selected-products-data", context);
      let selectedProducts = [];
      let currentSelectedProduct = [];

      try {
        selectedProducts = JSON.parse(selectedProductsData.val() || "[]");
      } catch (e) {
        selectedProducts = [];
      }

      // Process each table configuration from drupalSettings
      if (settings.datatables && settings.datatables.tables) {
        Object.keys(settings.datatables.tables).forEach(function(tableId) {
          const tableConfig = settings.datatables.tables[tableId];
          initializeDataTable(tableId, tableConfig, context);
        });
      }

      function initializeDataTable(tableId, tableConfig, context) {
        // Initialize selectedIds for this table if not exists
        if (!selectedIdsByTable[tableId]) {
          selectedIdsByTable[tableId] = [];
        }

        // Function to get DataTable instance for specific table
        function getDataTableInstance(tableId) {
          if ($.fn.DataTable.isDataTable('#' + tableId)) {
            dataTableInstances[tableId] = $('#' + tableId).DataTable();
            return true;
          }
          return false;
        }

        // Try to get the DataTable instance - use once() to only try once per table
        once('datatable-instance-' + tableId, 'body', context).forEach(function() {
          // First attempt immediately
          if (getDataTableInstance(tableId)) {
            setupDataTableEvents(tableId, tableConfig);
          } else {
            // If not found, try again after a delay
            setTimeout(function() {
              if (getDataTableInstance(tableId)) {
                setupDataTableEvents(tableId, tableConfig);
              }
            }, 300);
          }
        });
      }

      function setupDataTableEvents(tableId, tableConfig) {
        const dataTableInstance = dataTableInstances[tableId];
        if (!dataTableInstance) return;

        // Function to add dt-control class to first column cells
        function addDtControlClass(tableId, hasDetail) {
          if (!hasDetail) return;

          $('#' + tableId + ' tbody tr td:first-child').addClass('dt-control');
          $('#' + tableId + ' tbody tr').each(function() {
            var $tds = $(this).find('td');

            // Get text from configured column index
            var dataId = $(this).find('div.icon-edit a').data('id');

            // Get data-status from <div> inside configured column
            var dataStatus = $tds.eq(tableConfig.colIdIdx + 6).find('div').data('status');

            // Set attributes on <tr>
            $(this).attr('data-id', dataId);
            if (dataStatus !== undefined) {
              $(this).attr('data-status', dataStatus);
            }
          });
        }

        // Call the function immediately
        if (tableConfig.hasdetail) {
          addDtControlClass(tableId, tableConfig.hasdetail);
        }

        // Format function for child rows - customize this based on your data structure
        function formatChildRow(rowData, rowId) {
          // Return a placeholder while loading data
          return '<div class="child-row-details p-3">' +
            '<div class="text-center child-row-loading">' +
            '<div class="spinner-border text-primary" role="status">' +
            '<span class="visually-hidden">Loading...</span>' +
            '</div>' +
            '<p class="mt-2">Loading details...</p>' +
            '</div>' +
            '</div>';
        }

        // Load child row data via AJAX
        function loadChildRowData(row, tr, rowId, tableId) {
          // Get base URL from drupalSettings
          const baseUrl = drupalSettings.path.baseUrl || '/';
          const detailUrl = baseUrl + tableConfig.detailUrl + '/' + rowId;
          row.child('<div id="child-row-' + rowId + '">\
              <div class="child-row-details p-3 text-center">\
                <div class="spinner-border text-primary" role="status">\
                  <span class="visually-hidden">Loading...</span>\
                </div>\
                <p class="mt-2">Loading details...</p>\
              </div>\
            </div>').show();

          tr.addClass('shown');

          // Use Drupal Ajax so attachments (chartjs, etc.) are automatically loaded
          Drupal.ajax({
            url: detailUrl,
            wrapper: 'child-row-' + rowId,  // Matches the div ID above
            method: 'replaceWith'
          }).execute();
        }

        // Add click handler for child rows
        $('#' + tableId + ' tbody').off('click', 'td.dt-control').on('click', 'td.dt-control', function() {
          const tr = $(this).closest('tr');
          const row = dataTableInstance.row(tr);
          const dataId = tr.data('id');
          if (row.child.isShown()) {
            // This row is already open - close it
            row.child.hide();
            tr.removeClass('shown');
          } else {
            // Open this row
            // Get the request ID from the configured column
            const rowData = row.data();
            let requestId;
            requestId = dataId;
            // Validate requestId
            if (!requestId || requestId === 'N/A' || requestId === '') {
              // If ID is missing, show error message
              row.child('<div class="child-row-details p-3"><div class="alert alert-danger">Error: Could not identify request ID.</div></div>').show();
              tr.addClass('shown');
              return;
            }

            // Load child row data via AJAX
            loadChildRowData(row, tr, requestId, tableId);
          }
        });

        // Add row selection functionality if needed
        if (tableConfig.enable_row_selection) {
          enableRowSelection(tableId, tableConfig);
        }

        // Add search functionality enhancement
        enhanceTableSearch(tableId);

        // Add refresh button functionality
        addRefreshButton(tableId);
        if (tableConfig.enable_row_selection) {
          addSelectAllButton(tableId);
        }
      }

      // Function to update export buttons state based on row selection
      function updateActionButtonsState(tableId, tableConfig) {
        tableIdSelected = tableId;
        drupalSettings.datatables.selectedTable = tableIdSelected;
        const selectedRows = $('#' + tableId + ' tbody tr.selected');
        const hasSelectedRows = selectedRows.length > 0;
        // Collect ID values from configured column
        selectedIdsByTable[tableId] = [];
        selectedRows.each(function () {
          const id = $(this).data('id');
          if (id) {
            selectedIdsByTable[tableId].push(id);
          }
        });

        if (typeof tableConfig.selectedId != 'undefined'){
          tableConfig.selectedId = selectedIdsByTable[tableId];
        }

        // Enable/disable export buttons for this specific table
        $('.action-btn')
          .prop('disabled', !hasSelectedRows)
          .toggleClass('disabled', !hasSelectedRows); // add/remove 'disable' class
      }

      function enableRowSelection(tableId, tableConfig) {
        // Add click handler for rows to toggle selection for specific table
        $(document).on('click', '#' + tableId + ' tbody tr td', function (e) {
          // Get the column index (0-based)
          var columnIndex = $(this).index();

          // Only enable selection if clicking on column 4 and above (index 3+)
          if (columnIndex >= 3 && $(e.target).closest('.btn').length === 0) {
            var $row = $(this).closest('tr');
            var row_id = $row.data('id');

            if (row_id) {
              $row.toggleClass('selected');
              // Update actions buttons state whenever row selection changes
              updateActionButtonsState(tableId, tableConfig);
            }
          }

          // Prevent event bubbling to avoid any other click handlers
          e.stopPropagation();
        });
      }

      function enhanceTableSearch(tableId) {
        const dataTableInstance = dataTableInstances[tableId];
        if (!dataTableInstance) return;

        // Add debounce function for search input to prevent too many searches
        const tableWrapper = $('#' + tableId).closest('.dataTables_wrapper');
        const searchInput = tableWrapper.find('div.dataTables_filter input');
        let searchTimeout;

        searchInput.off('keyup.datatables-' + tableId).on('keyup.datatables-' + tableId, function() {
          clearTimeout(searchTimeout);
          const self = this;

          searchTimeout = setTimeout(function() {
            dataTableInstance.search($(self).val()).draw();
          }, 400);
        });
      }

      function addRefreshButton(tableId) {
        const dataTableInstance = dataTableInstances[tableId];
        if (!dataTableInstance) return;

        // Add a refresh button next to the search box for specific table
        const tableWrapper = $('#'+ tableId +'_wrapper');
        const filterDiv = tableWrapper.find('div.dt-search');
        if (filterDiv.length && !filterDiv.find('#refresh-datatable-' + tableId).length) {
          const refreshButton = $('<button id="refresh-datatable-' + tableId + '" class="btn btn-sm btn-outline-secondary ms-2"><i class="fa-solid fa-sync"></i></button>');
          filterDiv.append(refreshButton);

          refreshButton.on('click', function() {
            dataTableInstance.ajax.reload();
          });
        }
      }

      function addSelectAllButton(tableId) {
        // Add a "Select All" button near the table controls
        const dataTableInstance = dataTableInstances[tableId];
        if (!dataTableInstance) return;
        const tableWrapper = $('#'+ tableId +'_wrapper');
        const filterDiv = tableWrapper.find('div.dt-search');
        if (filterDiv.length && !filterDiv.find('#select-all-rows').length) {
          const selectAllButton = $('<button id="select-all-rows" class="btn btn-sm btn-primary ms-2">Select All</button>');
          const deselectAllButton = $('<button id="deselect-all-rows" class="btn btn-sm btn-secondary ms-2">Deselect All</button>');

          filterDiv.append(selectAllButton);
          filterDiv.append(deselectAllButton);

          // Select all visible rows
          selectAllButton.on('click', function() {
            $('#' + tableId + ' tbody tr').each(function() {
              const row_id = $(this).data('id');
              if (row_id && !$(this).hasClass('selected')) {
                $(this).addClass('selected');
              }
            });
            updateActionButtonsState();
          });

          // Deselect all rows
          deselectAllButton.on('click', function() {
            $('#' + tableId + ' tbody tr.selected').removeClass('selected');
            updateActionButtonsState();
          });
        }
      }

      function attachEventHandlers(tableId, tableConfig) {
        // Handle edit icon clicks for specific table
        $('#' + tableId).off('click.editIcon').on('click.editIcon', '.edit-icon', function(e) {
          e.preventDefault();

          let selectedId = $(this).data('id');
          if (!selectedId) {
            alert('Error: Missing ID.');
            return;
          }

          // Create a direct AJAX request to open the modal with the correct ID
          let baseUrl = drupalSettings.path.baseUrl || '/';
          let modalUrl = baseUrl + tableConfig.addPath +'/' + selectedId;

          // Use Drupal's Ajax framework directly
          Drupal.ajax({
            url: modalUrl,
            dialogType: 'modal',
            dialog: {
              width: 800,
              title: tableConfig.editFormTitle,
            }
          }).execute();
        });
      }

      // Add event handler for check selected button for each table
      Object.keys(dataTableInstances).forEach(function(tableId) {
        const tableConfig = settings.datatables.tables[tableId];

        once('check-selected-' + tableId, '#check-select-row-' + tableId, context).forEach(function(element) {
          $(element).on('click', function() {
            const dataTableInstance = dataTableInstances[tableId];

            if (!dataTableInstance) {
              alert('DataTable instance not available for ' + tableId);
              return;
            }

            // Get all rows with 'selected' class for this specific table
            const selectedRows = $('#' + tableId + ' tbody tr.selected');

            if (selectedRows.length > 0) {
              // Get the IDs of selected rows
              const selectedIds = [];
              selectedRows.each(function() {
                const id = $(this).find('td:eq('+ tableConfig.colIdIdx +')').text();
                if (id) {
                  selectedIds.push(id);
                }
              });

              if (selectedIds.length > 0) {
                alert('Selected ' + selectedIds.length + ' row(s) from ' + tableId + ': ' + selectedIds.join(', '));
                // You can process the selected IDs here or send them to the server
              } else {
                alert('Selected rows do not contain valid IDs');
              }
            } else {
              alert('No rows selected in ' + tableId + '. Click on rows to select them.');
            }
          });
        });
      });

      // Handle delete icon clicks (global for all tables)
      $(document).off('click.deleteIcon');
      $(document).on('click.deleteIcon', '.delete-icon', function (e) {
        e.preventDefault();

        let idSelected = $(this).data('id');
        const tableId = $(this).closest('table').attr('id');
        const tableConfig = settings.datatables.tables[tableId];

        if (!idSelected) {
          alert('Error: Missing ID.');
          return;
        }

        // Construct the URL using drupalSettings
        let baseUrl = drupalSettings.path.baseUrl || '/';
        let deleteUrl = baseUrl + tableConfig.deletePath +'/' + idSelected;
        const deleteConfirmation = confirm('Are you sure to delete this record...??!');
        if (deleteConfirmation) {
          window.location.href = deleteUrl;
        }
      });

      // Handle edit icon clicks (global for all tables)
      $(document).off('click.editIcon').on('click.editIcon', '.edit-icon', function (e) {
        e.preventDefault();

        let selectedId = $(this).data('id');
        const tableId = $(this).closest('table').attr('id');
        const tableConfig = settings.datatables.tables[tableId];

        if (!selectedId) {
          alert('Error: Missing ID.');
          return;
        }

        // Create a direct AJAX request to open the modal with the correct ID
        let baseUrl = drupalSettings.path.baseUrl || '/';
        let modalUrl = baseUrl + tableConfig.addPath +'/' + selectedId;

        // Use Drupal's Ajax framework directly
        Drupal.ajax({
          url: modalUrl,
          dialogType: 'modal',
          dialog: {
            width: 800,
            title: tableConfig.editFormTitle,
          }
        }).execute();
      });

      // Initialize event handlers for all tables
      Object.keys(dataTableInstances).forEach(function(tableId) {
        const tableConfig = settings.datatables.tables[tableId];
        attachEventHandlers(tableId, tableConfig);
      });

      // Handle cancel button click to close dialog (global)
      once('cancel-btn', '#cancel-button', context).forEach(function(element) {
        $(element).on('click', function(e) {
          e.preventDefault();

          // Close the modal dialog
          if (Drupal.dialog) {
            // Find the closest dialog container and close it
            const $dialog = $(this).closest('.ui-dialog-content');
            if ($dialog.length) {
              $dialog.dialog('close');
            } else {
              // Fallback to closing all dialogs
              $('.ui-dialog-content').dialog('close');
            }
          }
        });
      });
    }
  };

  /**
   * Custom AJAX command to reload specific DataTable.
   */
  Drupal.AjaxCommands.prototype.reloadDataTable = function (ajax, response, status) {
    // Get the table ID from the response, or use default from settings
    const tableId = response.tableId;

    if (tableId && dataTableInstances[tableId]) {
      const table = dataTableInstances[tableId];

      // Reload the table data
      table.ajax.reload(function() {
        // Optional: Show a brief success message after reload
        console.log('DataTable ' + tableId + ' reloaded successfully');

        // Clear any row selections after reload for this specific table
        selectedIdsByTable[tableId] = [];
        const selectedRows = $('#' + tableId + ' tbody tr.selected');
        const hasSelectedRows = selectedRows.length > 0;
        // Enable/disable export buttons for this specific table
        $('.action-btn')
          .prop('disabled', !hasSelectedRows)
          .toggleClass('disabled', !hasSelectedRows); // add/remove 'disable' class
      }, false); // false means keep current page
    } else {
      console.warn('DataTable not found or not initialized: ' + tableId);
    }
  };
})(jQuery, Drupal, once);
