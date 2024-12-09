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

          page++;
          setTimeout(function() {
            callRunner(page, nonce);
          }, 1000);
        } else {
          $(".azure-migrate-button").prop("disabled", false);
          alert("Migration completed!");
          location.reload();
        }
      },
      error: function(xhr, status, error) {
        console.error("Error:", error);
        $(".azure-migrate-button").prop("disabled", false);
        alert("An error occurred. Please try again.");
      }
    });
  };

  $(".azure-migrate-button").click(function(e) {
    e.preventDefault();
    if (confirm("This will migrate ALL files in your uploads directory to Azure. Continue?")) {
      $(this).prop("disabled", true);
      nonce = $(this).attr("data-nonce");
      page = parseInt($(this).attr("data-position")) || 0;
      callRunner(page, nonce);
    }
  });

  $(".azure-migrate-reset").click(function(e) {
    e.preventDefault();
    if (confirm("Are you sure you want to reset migration progress?")) {
      location.reload();
    }
  });
});
