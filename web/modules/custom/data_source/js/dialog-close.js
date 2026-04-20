/**
 * @file
 * Handles dialog close functionality for cancel buttons.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.dataSourceDialogClose = {
    attach: function (context, settings) {
      // Handle all cancel action links
      $('.data-cancel-action', context).on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var $this = $(this);
        var cancelType = $this.attr('data-cancel-type');

        if (cancelType === 'redirect') {
          // Redirect to URL
          var url = $this.attr('data-cancel-url');
          if (url) {
            window.location.href = url;
          }
        } else {
          // Close dialog (default)
          closeDialog($this);
        }

        return false;
      });
    }
  };

  /**
   * Helper function to close dialogs.
   */
  function closeDialog($element) {
    // Method 1: Try to find and close Drupal UI Dialog
    var $dialog = $element.closest('.ui-dialog-content');
    if ($dialog.length && $.fn.dialog) {
      $dialog.dialog('close');
      return;
    }

    // Method 2: Try to find and close Bootstrap Modal
    var $modal = $element.closest('.modal');
    if ($modal.length) {
      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modal = bootstrap.Modal.getInstance($modal[0]);
        if (modal) {
          modal.hide();
        } else {
          new bootstrap.Modal($modal[0]).hide();
        }
      } else if ($.fn.modal) {
        $modal.modal('hide');
      }
      return;
    }

    // Method 3: Find any visible Drupal dialog and close it
    var $visibleDialog = $('.ui-dialog-content:visible');
    if ($visibleDialog.length && $.fn.dialog) {
      $visibleDialog.dialog('close');
      return;
    }

    // Method 4: Find any visible Bootstrap modal and close it
    var $visibleModal = $('.modal:visible');
    if ($visibleModal.length) {
      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        $visibleModal.each(function() {
          var modal = bootstrap.Modal.getInstance(this);
          if (modal) {
            modal.hide();
          }
        });
      } else if ($.fn.modal) {
        $visibleModal.modal('hide');
      }
    }
  }

})(jQuery, Drupal);
