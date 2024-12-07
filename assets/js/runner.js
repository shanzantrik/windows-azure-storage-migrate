/**
 * Plugin Template frontend js.
 *
 *  @package WordPress Plugin Template/Runner
 */

jQuery(document).ready(function($) {
  var page = 0;
  var total = 1;
  var nonce = "";

  var callRunner = function(page, nonce) {
    jQuery.ajax({
      type: "post",
      dataType: "json",
      url: myAjax.ajaxurl,
      data: {
        action: "windows_azure_storage_migrate_media",
        page: page,
        nonce: nonce
      },
      success: function(response) {
        if (response.type !== "none") {
          var html = '<div class="notice notice-' + response.type + '">' +
                    "<p><strong>" + response.data + "</strong></p>" +
                    "</div>";
          $("#responce").prepend(html);
        }
        if (page < total) {
          page++;
          setTimeout(function() {
            callRunner(page, nonce);
          }, 1000); // Add a 1-second delay between requests
        } else {
          $(".azure-migrate-button").prop("disabled", false);
          location.reload(); // Reload when complete
        }
      },
      error: function() {
        // On error, enable the button so user can resume
        $(".azure-migrate-button").prop("disabled", false);
      }
    });
  };

  $(".azure-migrate-button").click(function(e) {
    e.preventDefault();
    $(this).prop("disabled", true);
    total = parseInt($(this).attr("data-total"));
    nonce = $(this).attr("data-nonce");
    page = parseInt($(this).attr("data-position")) || 0;

    callRunner(page, nonce);
  });

  $(".azure-migrate-reset").click(function(e) {
    e.preventDefault();
    if (confirm("Are you sure you want to reset migration progress?")) {
      location.reload();
    }
  });
});
