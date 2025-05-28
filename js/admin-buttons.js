jQuery(document).ready(function ($) {
  // Handle Create Post button click
  $(document).on("click", "#abcc-create-post", function (e) {
    e.preventDefault();

    const $button = $(this);
    const postType = $button.data("post-type") || "post";

    if (!confirm(abccAdminButtons.i18n.confirmCreate)) {
      return;
    }

    $button.prop("disabled", true).text("Creating...");

    $.ajax({
      url: abccAdminButtons.ajaxurl,
      type: "POST",
      data: {
        action: "abcc_create_post",
        nonce: abccAdminButtons.nonce,
        post_type: postType,
      },
      success: function (response) {
        if (response.success) {
          alert(response.data.message);
          if (response.data.edit_url) {
            window.location.href = response.data.edit_url;
          } else if (response.data.post_id) {
            window.location.href = abccAdminButtons.ajaxurl.replace(
              "admin-ajax.php",
              "post.php?action=edit&post=" + response.data.post_id
            );
          }
        } else {
          alert(response.data.message || abccAdminButtons.i18n.error);
        }
      },
      error: function () {
        alert(abccAdminButtons.i18n.error);
      },
      complete: function () {
        $button.prop("disabled", false).text("Create AI Post");
      },
    });
  });
});
