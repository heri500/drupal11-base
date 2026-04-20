/**
 * @file
 * Login page specific JavaScript functionality.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.gposLoginPage = {
    attach: function (context, settings) {
      // Add focus effects to form elements
      once('login-form-effects', '.form-control', context).forEach(function (element) {
        var $input = $(element);
        var $formGroup = $input.closest('.form-group');

        $input.on('focus', function () {
          $formGroup.addClass('focused');
        });

        $input.on('blur', function () {
          $formGroup.removeClass('focused');
          if ($(this).val()) {
            $formGroup.addClass('filled');
          } else {
            $formGroup.removeClass('filled');
          }
        });

        // Check if field is pre-filled
        if ($input.val()) {
          $formGroup.addClass('filled');
        }
      });

      // Add loading state to login button
      once('login-button', '.login-btn', context).forEach(function (element) {
        var $btn = $(element);

        $btn.on('click', function () {
          var originalText = $btn.val() || $btn.text();

          $btn.prop('disabled', true);
          if ($btn.is('input')) {
            $btn.val(Drupal.t('Logging in...'));
          } else {
            $btn.html('<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + Drupal.t('Logging in...'));
          }

          // Re-enable button after 5 seconds as fallback
          setTimeout(function () {
            $btn.prop('disabled', false);
            if ($btn.is('input')) {
              $btn.val(originalText);
            } else {
              $btn.text(originalText);
            }
          }, 5000);
        });
      });

      // Handle form validation errors
      once('error-handling', '.form-item--error-message', context).forEach(function (element) {
        var $error = $(element);
        var $input = $error.siblings('.form-control');

        $input.addClass('error');

        $input.on('input', function () {
          if ($(this).val()) {
            $(this).removeClass('error');
            $error.fadeOut();
          }
        });
      });

      // Auto-hide success messages after 5 seconds
      once('auto-hide-messages', '.messages--status', context).forEach(function (element) {
        $(element).delay(5000).fadeOut('slow');
      });

      // Responsive image handling
      function handleResponsiveLogin() {
        var $loginImg = $('.login-img');
        var windowWidth = $(window).width();

        if (windowWidth < 992) {
          $loginImg.parent().hide();
        } else {
          $loginImg.parent().show();
        }
      }

      // Initial check
      handleResponsiveLogin();

      // Handle window resize
      $(window).on('resize', handleResponsiveLogin);

      // Add smooth animations
      once('login-animations', '.login-wrapper', context).forEach(function (element) {
        $(element).css('opacity', 0).animate({
          opacity: 1
        }, 800);
      });
    }
  };

})(jQuery, Drupal, once);
