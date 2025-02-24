jQuery(document).ready(function ($) {
  function updateTokenEstimate() {
    const charLimit = $("#openai_char_limit").val();
    const model = $("#prompt_select").val();

    $.post(
      ajaxurl,
      {
        action: "abcc_estimate_tokens",
        char_limit: charLimit,
        model: model,
        _ajax_nonce: $("#abcc_openai_nonce").val(),
      },
      function (response) {
        if (response.success) {
          $(".token-estimate").html(response.data.message);
        }
      }
    );
  }

  updateTokenEstimate();

  $("#openai_char_limit, #prompt_select").on("change", updateTokenEstimate);

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
    $btn.prop("disabled", true);

    $.ajax({
      url: ajaxurl,
      method: "POST",
      data: {
        action: "openai_generate_post",
        _ajax_nonce: $("#abcc_openai_nonce").val(),
      },
      success: function (response) {
        console.log("Full response:", response);
        alert(response.data.message + " Post ID: " + response.data.post_id);
      },
      error: function (xhr) {
        console.error("Error details:", response.data.details);
        alert("Error generating post: " + xhr.responseText);
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
