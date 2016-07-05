/**
 * @file
 * Client-side filtering for GatherContent module.
 */

(function ($, Drupal, window) {
  'use strict';

  Drupal.behaviors.gcImportSelectedCounter = {
    attach: function (context) {
      var self = this;
      if ($('table.tablesorter-enabled:not(.sticky-header)', context).length) {
        $('.gc-table--counter', context).each(function () {
          $('<div class="form-item form-item--gc-import">\n' +
            '  <em class="select-counter"></em>\n' +
            '</div>')
            .appendTo('.gc-table--counter');
        });

        self.updateCount();

        $('table.tablesorter-enabled', context).on('change', 'input:checkbox', function () {
          self.updateCount();
        });
      }
    },

    updateCount: function () {
      var checkedCount = $('.tablesorter-enabled tbody input:checkbox:checked').length;
      var visibleCount = $('.tablesorter-enabled tbody input:checkbox:visible').length;
      var totalCount = $('.tablesorter-enabled tbody input:checkbox').length;

      $('.select-counter').html(Drupal.formatPlural(
        visibleCount,
        '@selectedcount of @count item selected (@totalcount total).',
        '@selectedcount of @count items selected (@totalcount total).',
        {
          '@selectedcount': checkedCount,
          '@totalcount': totalCount
        }
      ));
    }
  };

  Drupal.behaviors.gcImportFilter = {
    attach: function (context, settings) {
      var self = this;
      $('table.tablesorter-enabled:not(.sticky-header)', context).each(function () {
        $('.gc-filter').remove();

        $('.gc-table--filter-wrapper')
          .append(
            '<div class="form-item gc-filter project-status">\n' +
            '  <label for="ga-form-select-status">' + Drupal.t('Status') + '</label>\n' +
            '  <select id="ga-form-select-status" class="form-select form-select--gc-import">\n' +
            '    <option value="all">' + Drupal.t('All') + '</option>\n' +
            '  </select>\n' +
            '</div>\n' +
            '<div class="form-item gc-filter">\n' +
            '  <label for="ga-form-select-search">' + Drupal.t('Item name') + '</label>\n' +
            '  <input placeholder="' + Drupal.t('Filter by Item Name') + '" type="text" id="ga-form-select-search" class="form-text form-text--gc-import">\n' +
            '</div>\n' +
            '<div class="form-item gc-filter">\n' +
            '  <label for="ga-form-select-template">' + Drupal.t('GatherContent Template Name') + '</label>\n' +
            '  <select id="ga-form-select-template" class="form-select form-select--gc-import">\n' +
            '    <option value="all">' + Drupal.t('All') + '</option>\n' +
            '  </select>\n' +
            '</div>'
          );

        // Populate status select.
        $('.status-item', $(this)).each(function () {
          var optionText = $(this).text();
          var optionvalue = optionText.toLowerCase().replace(/[^a-z0-9]/g, '-');
          $(this).closest('tr').attr('data-status', optionvalue);
          if ($('#ga-form-select-status option[value="' + optionvalue + '"]').length === 0) {
            $('#ga-form-select-status').append('<option value="' + optionvalue + '">' + optionText + '</option>');
          }
        });

        // Populate template value select.
        $('.template-name-item').each(function () {
          var optionText = $(this).text();
          var optionvalue = optionText.toLowerCase().replace(/[^a-z0-9]/g, '-');
          $(this).closest('tr').attr('data-template', optionvalue);
          if ($('#ga-form-select-template option[value="' + optionvalue + '"]').length === 0) {
            $('#ga-form-select-template').append('<option value="' + optionvalue + '">' + optionText + '</option>');
          }
        });
      });

      // If the field condition is changing then we run the filtering.
      $('#ga-form-select-status, #ga-form-select-search, #ga-form-select-template').bind('change keyup', function (event) {
        // Getting filtering conditions.
        var statusValue = $('#ga-form-select-status').val();
        var templateValue = $('#ga-form-select-template').val();
        var searchValue = $('#ga-form-select-search').val().replace(/([.*+?^=!:${}()|\[\]\/\\])/g, '');

        $('.selected:hidden', $(this)).each(function () {
          // It removes te bg color from the table rows and uncheckes it's
          // checkbox.
          $(this).removeClass('selected')
            .find('input[type="checkbox"]').attr('checked', false).trigger('change');
        });

        // Loop through every rows.
        $('table.tablesorter-enabled tbody tr').each(function () {
          // The default value is the show 'all' items. There is no hidden value
          // by default.
          var hidden = false;

          // Checking filter values.
          if ((($(this).data('status') !== statusValue) && statusValue !== 'all') ||
            (($(this).data('template') !== templateValue) && templateValue !== 'all') ||
            ($(this).find('.gc-item--name').text().search(new RegExp(searchValue, 'i')) === -1)
          ) {
            hidden = true;
          }

          // Toggle row visibility.
          if (hidden) {
            $(this).hide();
            if ($(this).is('.selected')) {
              // Update DOM to match Drupal's tableselect standards as much as
              // possible.
              $(this).removeClass('selected')
              .find('input[type="checkbox"]')
                .attr('checked', false)
                .trigger('change');
            }
          }
          else {
            $(this).show();
          }
        });

        // Fixing odd/even classes.
        self.fixZebra('table.tablesorter-enabled');
        // Update select all checkbox value.
        self.fixSelectAll('table.tablesorter-enabled');
        // Trigger counter update.
        $('table.tablesorter-enabled input:checkbox').trigger('change');
        // Trigger 'resize' on window for sticky table (avoiding messed sticky
        // header cells). Sticky header and it's cells dimensions will be
        // recalculated.
        $(window).trigger('resize');
      });
    },
    fixZebra: function (context) {
      $(context).find('tbody tr:visible').removeClass('odd even')
        // Weird, I know, but that's how Drupal works by default.
        .filter(':even').addClass('odd').end()
        .filter(':odd').addClass('even');
    },
    fixSelectAll: function (context) {
      var itemsVisible = $(context).find('tbody tr:visible input[type="checkbox"]').length;
      var itemsVisibleChecked = $(context).find('tbody tr:visible input[type="checkbox"]:checked').length;
      // Trick: If no items are visible, we uncheck select-all anyway.
      var selectAllChecked = itemsVisible !== 0 ?
        itemsVisible === itemsVisibleChecked : false;

      $(context).find('th.select-all input:checkbox').attr('checked', selectAllChecked);
    }
  };
})(jQuery, Drupal, window);
