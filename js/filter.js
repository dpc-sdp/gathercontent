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

      // Create mapping page template counter.

      var counter = 0;
      $("#gc-mapping-form-templates input[type='checkbox']").change(function() {
        if($(this).prop('checked')) {
          counter++;
        }
        else {
          counter--;
        }
        $("#gc-mapping-form-templates .vertical-tab-button.last .selected-templates").html(counter);
      });

      // Import widgets.

      $('#gc-mapping-form-templates .vertical-tab-button.last').append("<a><span class='selected-templates'></span> Templates selected </a>");
      $("#gc-mapping-form-templates .vertical-tab-button.last .selected-templates").append(counter);


      $("#edit-import table:not(.sticky-header)").once('gc-import-filter', function(){
        $('.gc-import-filters').remove();

        $(".gc-import-filter-processed").tablesorter();

        $('.form-select-import .form-item-project').append(" <div class='gc-import-filters project-status'><label for='ga-form-select-status'>Status</label>  <select id='ga-form-select-status' class='form-select'><option value='all'>All</option></select></div>");
        $('.form-select-import .form-item-project').append(" <div class='gc-import-filters'><label for='ga-form-select-template'>GatherContent Template Name</label>  <select id='ga-form-select-template' class='form-select'><option value='all'>All</option></select></div>");

        $('.form-select-import .form-item-project').append(" <div class='gc-import-filters'><label for='ga-form-select-search'>Search</label>  <input placeholder='Filter by Item Name' type='text' id='ga-form-select-search' class='form-text' value=''></div>");


        // I collects the status values of the select box.
        $("#edit-import table tbody .status-item").each(function(){
          var optionText =  $(this).text();
          var optionvalue = optionText.toLowerCase().replace(/[^a-z0-9]/g, '-');
          $(this).closest('tr').attr('data-status', optionvalue);
          if ($('#ga-form-select-status option[value="' + optionvalue + '"]').length === 0 ) {
            $('#ga-form-select-status').append("<option value='" + optionvalue  + "'>" + optionText + "</option>");
          }
        });

        // I collects the template values of the select box.
        $("#edit-import .template-name-item").each(function(){
          var optionText =  $(this).text();
          var optionvalue = optionText.toLowerCase().replace(/[^a-z0-9]/g, '-');
          $(this).closest('tr').attr('data-template', optionvalue);
          if ($('#ga-form-select-template option[value="' + optionvalue + '"]').length === 0 ) {
            $('#ga-form-select-template').append("<option value='" + optionvalue  + "'>" + optionText + "</option>");
          }
        });
      });

      // If the field condition is changing then we run the filtering.
      $('#ga-form-select-status, #ga-form-select-search, #ga-form-select-template').bind('change keyup', function() {

        // I collects data from all fields.
        var statusValue = $("#ga-form-select-status").val();
        var templateValue = $("#ga-form-select-template").val();
        var searchValue = $("#ga-form-select-search").val();

        $(".gc-import-filter-processed .selected").each(function(){
          // It removes te bg color from the table rows.
          $(this).removeClass('selected');
        });
        $(".gc-import-filter-processed input[type='checkbox']").each(function(){
          //It removes the checked attributes from the table rows.
          $(this).attr( "checked", false )
        });

        // Run through every rows.
        $("#edit-import table tbody tr").each(function(){

          // The default value is the show 'all' items. There is no hidden value by default.
          var hidden = false;

          // Check status value.
          if (($(this).data("status") !== statusValue) && statusValue !== 'all') {
            hidden = true;
          }
          // Check template value.
          if (($(this).data("template") !== templateValue) && templateValue !== 'all') {
            hidden = true;
          }

          // If the list item does not contain the text phrase fade it out.
          if ($(this).text().search(new RegExp(searchValue, "i")) < 0) {
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
