(function ($) {
  'use strict';

  /**
   * Behaviours for the node edit form to help support the content editor.
   */
  Drupal.behaviors.stanford_subsites_node_form = {
    attach: function(context, settings) {

      // When a child subsite is selected hide the parent settings and open
      // the menu vt.
      $("#edit-field-s-subsite-ref").change(function(e) {

        // The value of the subsite field.
        var val = $(this).val();

        // Subsite Settings Field Group Functions.
        // ///////////////////////////////////////.

        // Hide the subsite parent settings if a subsite has been selected.
        if (val > 0) {
          $("#edit-group-subsite-settings").stop().fadeOut();
        }
        // Show the form if the user changed the option to none.
        else {
          $("#edit-group-subsite-settings").stop().fadeIn();
        }

        // Menu Helper Functions.
        // //////////////////////.

        if (val > 0) {
          // Expand the vertical tab.
          $(".menu-link-form").attr('open', true);
          // Open the summary element and provde the aria help.
          $(".menu-link-form summary")
            .attr('aria-expanded', true)
            .attr('aria-pressed', true);
          // Check the enable menu link button.
          $("#edit-menu-enabled").attr('checked', true);
          // Fade the menu options form in.
          $("#edit-menu--2").fadeIn();
          // Select the subsite parent from the list of drop downs.
          $("#edit-menu-menu-parent option[value='subsite-menu-" + val + ":']").attr('selected', 'selected');
          // Set the title of the field to what is already in the page title.
          $("#edit-menu-title").val($("#edit-title-0-value").val());
        }
      });
    }
  };

}(jQuery));
