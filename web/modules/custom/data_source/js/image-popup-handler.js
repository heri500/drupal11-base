(function ($, Drupal, once) {
  'use strict';

  window.OpenImageView = function (el) {
    const imageUrl = $(el).data('url');
    if (!imageUrl) return;

    const transactionId = $(el).data('transaction-id');

    if (transactionId) {
      const baseUrl = drupalSettings.path?.baseUrl || '/';
      $.ajax({
        url: baseUrl + 'member/transaction-detail/' + transactionId,
        type: 'GET',
        success: function (response) {
          $('#transaction-detail-wrapper').html(response);
        },
        error: function (xhr, status, error) {
          console.error('Error loading transaction:', error);
          $('#transaction-detail-wrapper').html(`
            <div class="alert alert-danger">
              <i class="fas fa-exclamation-circle me-2"></i>Failed to load transaction details
            </div>
          `);
        }
      });
    }

    $('#transactionsImagePreview').attr('src', imageUrl);

    // Radix loads Bootstrap 4 as a jQuery plugin ($.fn.modal).
    // Use the jQuery API — not new bootstrap.Modal() which is Bootstrap 5 only.
    $('#ImageModalView').modal('show');
  };

  $(document).ready(function () {
    const $img      = $('#transactionsImagePreview');
    const $modalBody = $img.closest('.modal-body');
    let scale       = 1;
    let isDragging  = false;
    let startX, startY, scrollLeft, scrollTop;

    $('#zoomInBtn').on('click', function () {
      scale += 0.1;
      $img.css('transform', 'scale(' + scale + ')');
    });

    $('#zoomOutBtn').on('click', function () {
      if (scale > 0.2) {
        scale -= 0.1;
        $img.css('transform', 'scale(' + scale + ')');
      }
    });

    $('#resetZoomBtn').on('click', function () {
      scale = 1;
      $img.css('transform', 'scale(1)');
      $modalBody.scrollTop(0).scrollLeft(0);
    });

    $modalBody.on('mousedown', function (e) {
      isDragging = true;
      $modalBody.css('cursor', 'grabbing');
      startX     = e.pageX - $modalBody.offset().left;
      startY     = e.pageY - $modalBody.offset().top;
      scrollLeft = $modalBody.scrollLeft();
      scrollTop  = $modalBody.scrollTop();
    });

    $modalBody.on('mouseleave mouseup', function () {
      isDragging = false;
      $modalBody.css('cursor', 'grab');
    });

    $modalBody.on('mousemove', function (e) {
      if (!isDragging) return;
      e.preventDefault();
      const x = e.pageX - $modalBody.offset().left;
      const y = e.pageY - $modalBody.offset().top;
      $modalBody.scrollLeft(scrollLeft - (x - startX));
      $modalBody.scrollTop(scrollTop  - (y - startY));
    });
  });

})(jQuery, Drupal, once);
