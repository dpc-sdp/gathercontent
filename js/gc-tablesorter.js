/**
 * @file
 * Activates tablesorter plugin for GatherContent tables.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.gcTableSorter = {
    attach: function (context) {
      if (typeof $.tablesorter !== 'undefined') {
        $.tablesorter.addParser({
          // Setting a unique id.
          id: 'datadate',
          is: function (s, table, cell, $cell) {
            if ($cell.attr('data-date')) {
              return true;
            }
            return false;
          },
          format: function (s, table, cell, cellIndex) {
            var $cell = $(cell);
            if ($cell.attr('data-date')) {
              return $cell.attr('data-date') || s;
            }
            return s;
          },
          parsed: false,
          type: 'text'
        });
      }

      $('table.tablesorter-enabled', context).once('gc-tablesorter', function () {
        $(this).tablesorter({
          cssAsc: 'sort-down',
          cssDesc: 'sort-up',
          widgets: ['zebra']
        });
      });
    },
    detach: function (context) {
      $('table.tablesorter-enabled', context).trigger('destroy')
      .find('tbody tr:visible')
        // Weird, I know, but that's how Drupal works by default.
        .filter(':even').addClass('odd').end()
        .filter(':odd').addClass('even');
    }
  };
})(jQuery, Drupal);
