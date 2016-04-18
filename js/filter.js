/**
 * @file
 * A JavaScript file for the theme.
 *
 * In order for this JavaScript to be loaded on pages, see the instructions in
 * the README.txt next to this file.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.gcImportSelectedCounter = {
    attach: function (context) {
      if ($('#edit-import table:not(.sticky-header)', context).length) {
        $('.gc-import--filter-wrapper', context).once('gc-import-selected-counter', function () {
          $('<div class="form-item form-item--gc-import">\n' +
            '  <em class="select-counter">' + Drupal.t('No items selected.') + '</em>\n' +
            '</div>')
            .appendTo('.gc-import--counter');
        });

        $('#edit-import table:not(.sticky-header)', context).delegate('input[type="checkbox"]', 'change', function () {
          var checkedCount = $('#edit-import table:not(.sticky-header) input[type="checkbox"]:checked').length;
          if (checkedCount === 0) {
            $('.select-counter').html(Drupal.t('No items selected.'));
          }
          else {
            $('.select-counter').html(Drupal.formatPlural(
              checkedCount,
              '1 item selected.',
              '@count items selected.'
            ));
          }
        });
      }
    }
  };

  Drupal.behaviors.gcImportFilter = {
    attach: function (context, settings) {
      $('#edit-import table:not(.sticky-header)', context).once('gc-import-filter', function () {
        $('.form-item--gc-import--filter').remove();

        $('.gc-import-filter-processed').tablesorter();

        $('.gc-import--filter-wrapper')
          .append(
            '<div class="form-item form-item--gc-import form-item--gc-import--filter project-status">\n' +
            '  <label for="ga-form-select-status">' + Drupal.t('Status') + '</label>\n' +
            '  <select id="ga-form-select-status" class="form-select form-select--gc-import">\n' +
            '    <option value="all">' + Drupal.t('All') + '</option>\n' +
            '  </select>\n' +
            '</div>\n' +
            '<div class="form-item form-item--gc-import form-item--gc-import--filter">\n' +
            '  <label for="ga-form-select-template">' + Drupal.t('GatherContent Template Name') + '</label>\n' +
            '  <select id="ga-form-select-template" class="form-select form-select--gc-import">\n' +
            '    <option value="all">' + Drupal.t('All') + '</option>\n' +
            '  </select>\n' +
            '</div>\n' +
            '<div class="form-item form-item--gc-import form-item--gc-import--filter">\n' +
            '  <label for="ga-form-select-search">' + Drupal.t('Search') + '</label>\n' +
            '  <input placeholder="' + Drupal.t('Filter by Item Name') + '" type="text" id="ga-form-select-search" class="form-text form-text--gc-import">\n' +
            '</div>');

        // I collects the status values of the select box.
        $('#edit-import table tbody .status-item').each(function () {
          var optionText = $(this).text();
          var optionvalue = optionText.toLowerCase().replace(/[^a-z0-9]/g, '-');
          $(this).closest('tr').attr('data-status', optionvalue);
          if ($('#ga-form-select-status option[value="' + optionvalue + '"]').length === 0) {
            $('#ga-form-select-status').append('<option value="' + optionvalue + '">' + optionText + '</option>');
          }
        });

        // I collects the template values of the select box.
        $('#edit-import .template-name-item').each(function () {
          var optionText = $(this).text();
          var optionvalue = optionText.toLowerCase().replace(/[^a-z0-9]/g, '-');
          $(this).closest('tr').attr('data-template', optionvalue);
          if ($('#ga-form-select-template option[value="' + optionvalue + '"]').length === 0) {
            $('#ga-form-select-template').append('<option value="' + optionvalue + '">' + optionText + '</option>');
          }
        });
      });

      // If the field condition is changing then we run the filtering.
      $('#ga-form-select-status, #ga-form-select-search, #ga-form-select-template').bind('change keyup', function () {
        // I collects data from all fields.
        var statusValue = $('#ga-form-select-status').val();
        var templateValue = $('#ga-form-select-template').val();
        var searchValue = $('#ga-form-select-search').val();

        $('.gc-import-filter-processed .selected').each(function () {
          // It removes te bg color from the table rows.
          $(this).removeClass('selected');
        });
        $('.gc-import-filter-processed input[type="checkbox"]').each(function () {
          // It removes the checked attributes from the table rows.
          $(this).attr('checked', false).trigger('change');
        });

        // Run through every rows.
        $('#edit-import table tbody tr').each(function () {
          // The default value is the show 'all' items. There is no hidden value
          // by default.
          var hidden = false;

          // Check status value.
          if (($(this).data('status') !== statusValue) && statusValue !== 'all') {
            hidden = true;
          }
          // Check template value.
          if (($(this).data('template') !== templateValue) && templateValue !== 'all') {
            hidden = true;
          }

          // If the list item does not contain the text phrase fade it out.
          if ($(this).text().search(new RegExp(searchValue, 'i')) < 0) {
            hidden = true;
          }

          // Hide/Show rows after evaluate the conditions.
          if (hidden) {
            $(this).hide();
          }
          else {
            $(this).show();
          }
        });
      });
    }
  };
})(jQuery, Drupal);
