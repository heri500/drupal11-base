(function ($, Drupal) {
  Drupal.behaviors.tooltipInit = {
    attach: function (context, settings) {
      // Initialize tooltips only on newly added/updated DOM
      $(context).find('[data-bs-toggle="tooltip"]').each(function () {
        new bootstrap.Tooltip(this);
      });
    }
  };
})(jQuery, Drupal);
