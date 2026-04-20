/**
 * @file
 * JavaScript for request admin modal and autocomplete functionality.
 */
(function ($, Drupal, once) {
  'use strict';

  // Store the dataTable instance globally within this closure
  let dataTableInstance = null;
  let selectedIds = [];
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

      // Get the table ID from drupalSettings
      const tableId = settings.datatables.tableId;
      console.log(tableId);
      // Function to get DataTable instance
      function getDataTableInstance() {
        if ($.fn.DataTable.isDataTable('#' + tableId)) {
          dataTableInstance = $('#' + tableId).DataTable();
          console.log(dataTableInstance);
          return true;
        }
        return false;
      }

      // Try to get the DataTable instance - use once() to only try once per page load
      once('datatable-instance', 'body', context).forEach(function() {
        // First attempt immediately
        console.log('GET DATATABLES INSTANCE');
        if (getDataTableInstance()) {
          setupDataTableEvents();
        } else {
          // If not found, try again after a delay
          setTimeout(function() {
            console.log('GET DATATABLES INSTANCE TIMEOUT');
            if (getDataTableInstance()) {
              setupDataTableEvents();
            }
          }, 300);
        }
      });

      function setupDataTableEvents() {
        if (!dataTableInstance) return;

        // Function to add dt-control class to first column cells
        function addDtControlClass() {
          if (drupalSettings.datatables.hasdetail[drupalSettings.datatables.tableId] === 1) {
            $('#' + tableId + ' tbody tr td:first-child').addClass('dt-control');
          }
          $('#' + tableId + ' tbody tr').each(function() {
            var $tds = $(this).find('td');

            // Get text from 3rd column
            //var dataId = $tds.eq(drupalSettings.datatables.colIdIdx).text().trim();
            var dataId = $(this).find('div.row-id').data('id');

            // Get data-status from <div> inside 10th column
            var dataStatus = $tds.eq(drupalSettings.datatables.colIdIdx + 6).find('div').data('status');

            // Set attributes on <tr>
            $(this).attr('data-id', dataId);
            if (dataStatus !== undefined) {
              $(this).attr('data-status', dataStatus);
            }
          });
        }

        // Call the function immediately
        if (drupalSettings.datatables.hasdetail[drupalSettings.datatables.tableId] === 1) {
          addDtControlClass();
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

        // Load child row data via Drupal AjaxResponse
        function loadChildRowData(row, tr, rowId) {
          // Insert a placeholder wrapper into the child row
          // This will be replaced by the AjaxResponse HtmlCommand from the controller
          row.child('<div id="child-row-' + rowId + '">\
              <div class="child-row-details p-3 text-center">\
                <div class="spinner-border text-primary" role="status">\
                  <span class="visually-hidden">Loading...</span>\
                </div>\
                <p class="mt-2">Loading details...</p>\
              </div>\
            </div>').show();

          tr.addClass('shown');

          // Build the detail URL
          const baseUrl = drupalSettings.path.baseUrl || '/';
          const detailUrl = baseUrl + drupalSettings.datatables.detailUrl + '/' + rowId;

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
            // Get the request ID from the 4th column (index 3)
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
            loadChildRowData(row, tr, requestId);
          }
        });

        // Add row selection functionality if needed
        enableRowSelection();

        // Add search functionality enhancement
        enhanceTableSearch();

        // Add refresh button functionality
        addRefreshButton();

        if (drupalSettings.datatables.enable_row_selection) {
          addSelectAllButton();
        }
      }

      // Function to update export buttons state based on row selection
      function updateActionButtonsState() {
        const selectedRows = $('#' + tableId + ' tbody tr.selected');
        const hasSelectedRows = selectedRows.length > 0;
        // Collect ID values from cell no 4 (index 3)
        selectedIds = [];
        selectedRows.each(function () {
          let id = $(this).data('id');
          if (!id) {
            id = $(this).find('td:eq(' + drupalSettings.datatables.colIdIdx + ')').text().trim();
          }
          if (id) {
            selectedIds.push(id);
          }
        });
        console.log(selectedIds);
        if (typeof drupalSettings.datatables.selectedId != 'undefined'){
          drupalSettings.datatables.selectedId = selectedIds;
        }
        // Enable/disable all export buttons based on selection state
        $('.action-btn')
          .prop('disabled', !hasSelectedRows)
          .toggleClass('disabled', !hasSelectedRows); // add/remove 'disable' class
      }

      function enableRowSelection() {
        // Add click handler for rows to toggle selection
        console.log('SELECTION TRIGGERED');
        $(document).on('click', '#' + tableId + ' tbody tr td', function (e) {
          // Get the column index (0-based)
          var columnIndex = $(this).index();
          // Only enable selection if clicking on column 4 and above (index 3+)
          console.log($(e.target).closest('.btn'));
          if (columnIndex >= 3 && $(e.target).closest('.btn').length === 0) {
            var $row = $(this).closest('tr');
            var row_id = $row.data('id');

            if (row_id) {
              $row.toggleClass('selected');
              // Update actions buttons state whenever row selection changes
              updateActionButtonsState();
            }
          }

          // Prevent event bubbling to avoid any other click handlers
          e.stopPropagation();
        });
      }

      function enhanceTableSearch() {
        // Add debounce function for search input to prevent too many searches
        const searchInput = $('div.dataTables_filter input');
        let searchTimeout;

        searchInput.off('keyup.datatables').on('keyup.datatables', function() {
          clearTimeout(searchTimeout);
          const self = this;

          searchTimeout = setTimeout(function() {
            dataTableInstance.search($(self).val()).draw();
          }, 400);
        });
      }

      function addRefreshButton() {
        // Add a refresh button next to the search box
        const filterDiv = $('div.dt-search');

        if (filterDiv.length && !filterDiv.find('#refresh-datatable').length) {
          const refreshButton = $('<button id="refresh-datatable" class="btn btn-sm btn-outline-secondary ms-2"><i class="fa-solid fa-sync"></i></button>');
          filterDiv.append(refreshButton);

          refreshButton.on('click', function() {
            dataTableInstance.ajax.reload();
          });
        }
      }

      function addSelectAllButton() {
        // Add a "Select All" button near the table controls
        const filterDiv = $('div.dt-search');

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

      function attachEventHandlers() {
        $('.edit-icon').off('click.editIcon').on('click.editIcon', function(e) {
          e.preventDefault();

          let selectedId = $(this).data('id');
          if (!selectedId) {
            alert('Error: Missing ID.');
            return;
          }

          // Create a direct AJAX request to open the modal with the correct ID
          let baseUrl = drupalSettings.path.baseUrl || '/';
          let modalUrl = baseUrl + drupalSettings.datatables.addPath +'/' + selectedId;

          // Use Drupal's Ajax framework directly
          Drupal.ajax({
            url: modalUrl,
            dialogType: 'modal',
            dialog: {
              width: 800,
              title: drupalSettings.datatables.editFormTitle,
            }
          }).execute();
        });
      }

      // Add event handler for check selected button
      once('check-selected', '#check-select-row', context).forEach(function(element) {
        $(element).on('click', function() {
          if (!dataTableInstance) {
            // Try to get instance one more time
            getDataTableInstance();
          }

          if (dataTableInstance) {
            // Get all rows with 'selected' class
            const selectedRows = $('#' + tableId + ' tbody tr.selected');

            if (selectedRows.length > 0) {
              // Get the IDs of selected rows
              const selectedIds = [];
              selectedRows.each(function() {
                let id = $(this).data('id');
                if (!id) {
                  id = $(this).find('td:eq(' + drupalSettings.datatables.colIdIdx + ')').text().trim();
                }
                if (id) {
                  selectedIds.push(id);
                }
              });

              if (selectedIds.length > 0) {
                alert('Selected ' + selectedIds.length + ' row(s): ' + selectedIds.join(', '));
                // You can process the selected IDs here or send them to the server
              } else {
                alert('Selected rows do not contain valid IDs');
              }
            } else {
              alert('No rows selected. Click on rows to select them.');
            }
          } else {
            alert('DataTable instance not available');
          }
        });
      });

      $(document).off('click.deleteIcon');
      $(document).on('click.deleteIcon', '.delete-icon', function (e) {
        e.preventDefault();

        let idSelected = $(this).data('id');

        if (!idSelected) {
          alert('Error: Missing ID.');
          return;
        }

        // Construct the URL using drupalSettings
        let baseUrl = drupalSettings.path.baseUrl || '/';
        let deleteUrl = baseUrl + drupalSettings.datatables.deletePath +'/' + idSelected;
        console.log(deleteUrl);
        const deleteConfirmation = confirm('Are you sure to delete this record...??!');
        if (deleteConfirmation) {
          window.location.href = deleteUrl;
        }
      });

      // Handle edit icon clicks
      $(document).off('click.editIcon').on('click.editIcon', '.edit-icon', function (e) {
        e.preventDefault();

        let selectedId = $(this).data('id');
        //console.log(selectedId);
        if (!selectedId) {
          alert('Error: Missing ID.');
          return;
        }

        // Create a direct AJAX request to open the modal with the correct ID
        let baseUrl = drupalSettings.path.baseUrl || '/';
        let modalUrl = baseUrl + drupalSettings.datatables.addPath +'/' + selectedId;

        // Use Drupal's Ajax framework directly
        Drupal.ajax({
          url: modalUrl,
          dialogType: 'modal',
          dialog: {
            width: 800,
            title: drupalSettings.datatables.editFormTitle,
          }
        }).execute();
      });

      // Initialize event handlers for the first time
      attachEventHandlers();

      // Handle cancel button click to close dialog
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
   * Custom AJAX command to reload DataTable.
   */
  Drupal.AjaxCommands.prototype.reloadDataTable = function (ajax, response, status) {
    // Get the table ID from the response, or use default from settings
    const tableId = response.tableId || drupalSettings.datatables.tableId;

    if (tableId && $.fn.DataTable.isDataTable('#' + tableId)) {
      const table = $('#' + tableId).DataTable();

      // Reload the table data
      table.ajax.reload(function() {
        // Optional: Show a brief success message after reload
        console.log('DataTable reloaded successfully');

        // Clear any row selections after reload
        selectedIds = [];
      }, false); // false means keep current page
    } else {
      console.warn('DataTable not found or not initialized: ' + tableId);
    }
  };
})(jQuery, Drupal, once);
