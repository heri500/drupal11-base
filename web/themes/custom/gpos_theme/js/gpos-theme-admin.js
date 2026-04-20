/**
 * @file
 * GPOS Theme admin interface enhancements.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Live preview for header color changes in admin interface.
   */
  Drupal.behaviors.gposThemeColorPreview = {
    attach: function (context, settings) {
      // Create preview element if it doesn't exist
      if (!$('.header-color-preview').length) {
        var previewHtml = '<div class="header-color-preview" style="margin-top: 15px; padding: 20px; border-radius: 4px; color: white; font-weight: bold; text-align: center; position: relative;">' +
          '<div class="preview-header-content">' +
          '<div class="preview-brand" style="float: left; font-size: 18px; font-weight: bold;">Brand Logo</div>' +
          '<div class="preview-nav" style="float: right;">' +
          '<span class="preview-nav-link" style="margin: 0 10px; cursor: pointer;">Home</span>' +
          '<span class="preview-nav-link" style="margin: 0 10px; cursor: pointer;">About</span>' +
          '<span class="preview-nav-link active" style="margin: 0 10px; cursor: pointer; text-decoration: underline;">Services</span>' +
          '<span class="preview-nav-link" style="margin: 0 10px; cursor: pointer;">Contact</span>' +
          '</div>' +
          '<div style="clear: both;"></div>' +
          '</div>' +
          '</div>';
        $(previewHtml).insertAfter('[data-drupal-selector="edit-gpos-nav-active-weight"]');
      }

      var $preview = $('.header-color-preview');
      var $primaryColor = $('[data-drupal-selector="edit-gpos-header-primary-color"]');
      var $secondaryColor = $('[data-drupal-selector="edit-gpos-header-secondary-color"]');
      var $opacity = $('[data-drupal-selector="edit-gpos-header-opacity"]');
      var $enableCustom = $('[data-drupal-selector="edit-gpos-header-custom-colors-enable"]');

      // Navigation text elements
      var $navLinkColor = $('[data-drupal-selector="edit-gpos-nav-link-color"]');
      var $navLinkWeight = $('[data-drupal-selector="edit-gpos-nav-link-weight"]');
      var $navActiveColor = $('[data-drupal-selector="edit-gpos-nav-active-color"]');
      var $navActiveWeight = $('[data-drupal-selector="edit-gpos-nav-active-weight"]');

      // Function to update preview
      function updatePreview() {
        if (!$enableCustom.is(':checked')) {
          // Use default colors when custom colors are disabled
          $preview.css({
            'background-image': 'linear-gradient(rgb(0, 0, 0), rgba(2, 2, 2, 0.52))',
            'box-shadow': '0 0.2rem 0.45rem rgba(0, 0, 0, 0.15), inset 0 -1px 0 rgba(255, 255, 255, 0.15)'
          });

          // Default navigation styles
          $('.preview-nav-link').css({
            'color': '#ffffff',
            'font-weight': '400',
            'text-decoration': 'none'
          });
          $('.preview-nav-link.active').css({
            'color': '#ffd700',
            'font-weight': '600',
            'text-decoration': 'underline'
          });
          return;
        }

        var primaryColor = $primaryColor.val() || '#000000';
        var secondaryColor = $secondaryColor.val() || '#020202';
        var opacity = ($opacity.val() || 52) / 100;

        // Navigation text settings
        var navLinkColor = $navLinkColor.val() || '#ffffff';
        var navLinkWeight = $navLinkWeight.val() || '400';
        var navActiveColor = $navActiveColor.val() || '#ffd700';
        var navActiveWeight = $navActiveWeight.val() || '600';

        // Convert hex to rgb
        function hexToRgb(hex) {
          var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
          return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
          } : null;
        }

        var primaryRgb = hexToRgb(primaryColor);
        var secondaryRgb = hexToRgb(secondaryColor);

        if (primaryRgb && secondaryRgb) {
          var gradient = 'linear-gradient(rgb(' + primaryRgb.r + ', ' + primaryRgb.g + ', ' + primaryRgb.b + '), rgba(' + secondaryRgb.r + ', ' + secondaryRgb.g + ', ' + secondaryRgb.b + ', ' + opacity + '))';

          // Update header background
          $preview.css({
            'background-image': gradient,
            'box-shadow': '0 0.2rem 0.45rem rgba(0, 0, 0, 0.15), inset 0 -1px 0 rgba(255, 255, 255, 0.15)'
          });

          // Update navigation link styles
          $('.preview-nav-link').css({
            'color': navLinkColor,
            'font-weight': navLinkWeight,
            'text-decoration': 'none',
            'transition': 'color 0.3s ease'
          });

          $('.preview-nav-link.active').css({
            'color': navActiveColor,
            'font-weight': navActiveWeight,
            'text-decoration': 'underline'
          });

          // Add hover effect
          $('.preview-nav-link').off('mouseenter mouseleave').on('mouseenter', function() {
            if (!$(this).hasClass('active')) {
              $(this).css('color', navActiveColor);
            }
          }).on('mouseleave', function() {
            if (!$(this).hasClass('active')) {
              $(this).css('color', navLinkColor);
            }
          });
        }
      }

      // Bind events to update preview in real-time
      $primaryColor.on('input change', updatePreview);
      $secondaryColor.on('input change', updatePreview);
      $navLinkColor.on('input change', updatePreview);
      $navLinkWeight.on('change', updatePreview);
      $navActiveColor.on('input change', updatePreview);
      $navActiveWeight.on('change', updatePreview);
      $opacity.on('input change', function() {
        // Update the opacity display
        var opacityValue = $(this).val();
        $(this).siblings('.form-range-output').text(opacityValue + '%');
        updatePreview();
      });
      $enableCustom.on('change', updatePreview);

      // Initial preview update
      updatePreview();

      // Add opacity value display
      if (!$('.form-range-output').length) {
        $('<span class="form-range-output" style="margin-left: 10px; font-weight: bold;">' + ($opacity.val() || 52) + '%</span>')
          .insertAfter($opacity);
      }
    }
  };

  /**
   * Enhanced color picker tooltips.
   */
  Drupal.behaviors.gposThemeColorTooltips = {
    attach: function (context, settings) {
      $('[data-drupal-selector^="edit-gpos-header"][type="color"]', context).each(function() {
        $(this).attr('title', 'Click to open color picker');
      });
    }
  };

})(jQuery, Drupal);
