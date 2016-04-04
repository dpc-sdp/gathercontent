/**
 * @file
 * A JavaScript file for the theme.
 *
 * In order for this JavaScript to be loaded on pages, see the instructions in
 * the README.txt next to this file.
 */

// JavaScript should be made compatible with libraries other than jQuery by
// wrapping it with an "anonymous closure". See:
// - https://drupal.org/node/1446420
// - http://www.adequatelygood.com/2010/3/JavaScript-Module-Pattern-In-Depth

// Create a vclick event which is working on mobile and desktop. It's fired on
// click and touch events as well.
(function ($, Drupal, window, document) {
  'use strict';

  $(document).ready(function () {
    $('.form-select-import .form-item-project').append(" <label for='form-select-status'>Status</label>  <select id='form-select-status' class='form-select'>");
    $('.form-select-import .form-item-project').append(" <label for='form-select-search'>Search</label>  <input placeholder='Filter by Item Name' type='text' id='form-select-search' class='form-text'  maxlength='255' size='60' value=''>");

    $("#form-select-search").keyup(function(){

      // Retrieve the input field text and reset the count to zero.
      var filter = $(this).val(), count = 0;

      // Loop through the table.
      $("#edit-import table tbody tr").each(function(){

        // If the list item does not contain the text phrase fade it out.
        if ($(this).text().search(new RegExp(filter, "i")) < 0) {
          $(this).fadeOut();

          // Show the list item if the phrase matches and increase the count by 1.
        } else {
          $(this).show();
          count++;
        }
      });
    });
  });
})(jQuery);
