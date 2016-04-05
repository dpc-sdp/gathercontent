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
(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.gcImportFilter = {
    attach: function (context, settings) {

      $("#edit-import table:not(.sticky-header)").once('gc-import-filter', function(){
        $('.gc-import-filters').remove();

        $('.form-select-import .form-item-project').append(" <div class='gc-import-filters project-status'><label for='ga-form-select-status'>Status</label>  <select id='ga-form-select-status' class='form-select'><option value='all'>All</option></select></div>");
        $('.form-select-import .form-item-project').append(" <div class='gc-import-filters'><label for='ga-form-select-template'>GatherContent Template Name</label>  <select id='ga-form-select-template' class='form-select'><option value='all'>All</option></select></div>");

        $('.form-select-import .form-item-project').append(" <div class='gc-import-filters'><label for='ga-form-select-search'>Search</label>  <input placeholder='Filter by Item Name' type='text' id='ga-form-select-search' class='form-text' value=''></div>");

        $("#ga-form-select-search").keyup(function(){

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

        $("#edit-import table tbody .status-item").each(function(){
          var optionText =  $(this).text();
          var optionvalue = optionText.toLowerCase().replace(/[^a-z0-9]/g, '-');
          $(this).closest('tr').attr('data-status', optionvalue);
          if ($('#ga-form-select-status option[value="' + optionvalue + '"]').length === 0 ) {
            $('#ga-form-select-status').append("<option value='" + optionvalue  + "'>" + optionText + "</option>");
          }
        });

        $("#edit-import .template-name-item").each(function(){
          var optionText =  $(this).text();
          var optionvalue = optionText.toLowerCase().replace(/[^a-z0-9]/g, '-');
          $(this).closest('tr').attr('data-template', optionvalue);
          if ($('#ga-form-select-template option[value="' + optionvalue + '"]').length === 0 ) {
            $('#ga-form-select-template').append("<option value='" + optionvalue  + "'>" + optionText + "</option>");
          }
        });
      });

      $('#ga-form-select-status').bind('change', function() {

        $(".gc-import-filter-processed .selected").each(function(){
          $(this).removeClass('selected');
        });
        $(".gc-import-filter-processed input[type='checkbox']").each(function(){
          $(this).attr( "checked", false )
        });
        var selectedValue = $(this).val();
        $("[data-status]").hide();
        $("[data-status='" + selectedValue + "']").show();
        if(selectedValue == 'all') {
          $(".gc-import-filter-processed tr").each(function(){
            $(this).show();
          });
        }
      });

      $('#ga-form-select-template').bind('change', function() {

        $(".gc-import-filter-processed .selected").each(function(){
          $(this).removeClass('selected');
        });
        $(".gc-import-filter-processed input[type='checkbox']").each(function(){
          $(this).attr( "checked", false )
        });
        var selectedValue = $(this).val();
        $("[data-template]").hide();
        $("[data-template='" + selectedValue + "']").show();
        if(selectedValue == 'all') {
          $(".gc-import-filter-processed tr").each(function(){
            $(this).show();
          });
        }
      });

    }
  };
})(jQuery, Drupal);
