jQuery(document).ready(function ($) {
  // Event listener for changing tone selection
  $('input[type="radio"][name="openai_tone"]').change(function () {
    if ($("#custom").is(":checked")) {
      $("#custom_tone").show();
    } else {
      $("#custom_tone").hide();
    }
  });

  // Trigger the change event to set the initial visibility state
  $('input[type="radio"][name="openai_tone"]:checked').change();

  // Event listener for generate post button
  $("#generate-post").on("click", function () {
    var $btn = $(this);
    var $status = $("#abcc-manual-generation-status");

    $btn.prop("disabled", true);
    abcc.showStatus($status, "Generating post... this may take a moment.");

    $.ajax({
      url: ajaxurl,
      method: "POST",
      data: {
        action: "openai_generate_post",
        _ajax_nonce: $("#abcc_openai_nonce").val(),
      },
      success: function (response) {
        if (response.success) {
          abcc.showStatus($status, response.data.message + " Post ID: " + response.data.post_id, "success");
          alert(response.data.message + " Post ID: " + response.data.post_id);
        } else {
          abcc.setError($status, response.data.message);
          alert("Error generating post: " + response.data.message);
        }
      },
      error: function (xhr) {
        abcc.setError($status, "Error generating post: " + xhr.statusText);
        alert("Error generating post: " + xhr.statusText);
      },
      complete: function () {
        $btn.prop("disabled", false);
      },
    });
  });

  $("#refresh-models").on("click", function () {
    var $button = $(this);
    $button.prop("disabled", true);

    $.ajax({
      url: ajaxurl,
      method: "POST",
      data: {
        action: "abcc_refresh_models",
        _ajax_nonce: $("#abcc_openai_nonce").val(),
      },
      success: function (response) {
        if (response.success && response.data.models) {
          // Update model dropdown
          var $select = $("#prompt_select");
          $select.empty();

          response.data.models.forEach(function (model) {
            $select.append(
              $("<option></option>")
                .val(model.id)
                .text(model.name + " - " + model.description)
                .attr("data-cost-tier", model.cost_tier)
            );
          });

          updateCostIndicator();
        }
      },
      complete: function () {
        $button.prop("disabled", false);
      },
    });
  });
});
