/**
 * @file
 * Activates tablesorter plugin for GatherContent tables.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.gcTableSorter = {
    attach: function (context) {
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
