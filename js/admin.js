jQuery(document).ready(function ($) {
  let abccJobRefreshTimer = null;
  let abccActiveRunId = "";

  function abccEscapeHtml(value) {
    return $("<div>").text(value).html();
  }

  function refreshJobLog(runId) {
    const effectiveRunId = typeof runId !== "undefined" ? runId : abccActiveRunId;

    if (!$("#abcc-job-log-body").length) {
      return;
    }

    $.post(ajaxurl, {
      action: "abcc_get_job_log",
      nonce: abccAdmin.nonce,
      run_id: effectiveRunId || "",
      status_filter: $("#abcc-job-filter").length ? $("#abcc-job-filter").val() : "",
    }).done(function (response) {
      if (response.success && response.data.html) {
        $("#abcc-job-log-body").html(response.data.html);
      }
    });
  }

  function scheduleJobLogRefresh() {
    if (abccJobRefreshTimer) {
      window.clearInterval(abccJobRefreshTimer);
      abccJobRefreshTimer = null;
    }

    if (!$("#abcc-job-auto-refresh").length || !$("#abcc-job-auto-refresh").is(":checked")) {
      return;
    }

    abccJobRefreshTimer = window.setInterval(function () {
      refreshJobLog();
    }, 10000);
  }

  function pollJob(jobId, callbacks) {
    const options = callbacks || {};

    function pollOnce() {
      $.post(ajaxurl, {
        action: "abcc_get_job_status",
        nonce: abccAdmin.nonce,
        job_id: jobId,
      }).done(function (response) {
        if (!response.success) {
          if (options.onError) {
            options.onError(response.data && response.data.message ? response.data.message : "An error occurred.");
          }
          return;
        }

        const job = response.data;

        if (options.onUpdate) {
          options.onUpdate(job);
        }

        refreshJobLog();

        if (job.status === "queued" || job.status === "running") {
          window.setTimeout(pollOnce, 3000);
          return;
        }

        if (job.status === "succeeded") {
          if (options.onSuccess) {
            options.onSuccess(job);
          }
          return;
        }

        if (options.onFailed) {
          options.onFailed(job.message || "Generation failed.");
        }
      }).fail(function () {
        if (options.onError) {
          options.onError("Network error occurred.");
        }
      });
    }

    pollOnce();
  }

  $("#abcc-job-refresh").on("click", function () {
    refreshJobLog();
  });

  $("#abcc-job-filter").on("change", function () {
    refreshJobLog();
  });

  $("#abcc-job-auto-refresh").on("change", function () {
    scheduleJobLogRefresh();
  });

  $(document).on("click", ".abcc-copy-error", function () {
    const $button = $(this);
    const errorText = $button.data("error") || "";

    if (!errorText) {
      return;
    }

    const afterCopy = function () {
      const originalText = $button.text();
      $button.text(abccAdmin.i18n.copied);
      window.setTimeout(function () {
        $button.text(originalText);
      }, 1500);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(errorText).then(afterCopy);
      return;
    }

    const $temp = $("<textarea>").val(errorText).appendTo("body").select();
    document.execCommand("copy");
    $temp.remove();
    afterCopy();
  });

  scheduleJobLogRefresh();

  // Initialize Select2 for category dropdown
  $(".wpai-category-select, .wpai-post-type-select").select2();

  // Handle custom tone input visibility
  $("#openai_tone").on("change", function () {
    var customContainer = $("#custom_tone_container");
    if ($(this).val() === "custom") {
      customContainer.show();
    } else {
      customContainer.hide();
    }
  });

  // Enhanced model card click handling
  $(".model-card").on("click", function (e) {
    e.preventDefault();

    const $clickedCard = $(this);
    const $radioButton = $clickedCard.find('input[type="radio"]');

    // Don't do anything if clicking on a disabled card
    if ($clickedCard.hasClass("disabled")) {
      return;
    }

    // Remove selected class from all cards with animation
    $(".model-card.selected").removeClass("selected");

    // Add selected class to clicked card
    $clickedCard.addClass("selected just-selected");

    // Remove the animation class after animation completes
    setTimeout(() => {
      $clickedCard.removeClass("just-selected");
    }, 300);

    // Check the radio button
    $radioButton.prop("checked", true);

    // Trigger change event for any other scripts that might be listening
    $radioButton.trigger("change");

    // Optional: Show a subtle notification
    showModelSelectedNotification($clickedCard.find("h4").text());
  });

  // Handle keyboard navigation for accessibility
  $(".model-card").on("keydown", function (e) {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      $(this).click();
    }
  });

  // Initialize selected state based on checked radio button
  $('.model-card input[type="radio"]:checked')
    .closest(".model-card")
    .addClass("selected");

  // Make model cards focusable for keyboard navigation
  $(".model-card").attr("tabindex", "0");

  // Optional: Add a subtle notification when model is selected
  function showModelSelectedNotification(modelName) {
    // Remove any existing notification
    $(".model-selection-notice").remove();

    // Create and show new notification (use DOM methods to avoid XSS via template literals).
    const $notice = $("<div>").addClass("model-selection-notice").css({
      position: "fixed",
      top: "20px",
      right: "20px",
      background: "#2271b1",
      color: "white",
      padding: "12px 16px",
      borderRadius: "6px",
      boxShadow: "0 4px 12px rgba(0,0,0,0.15)",
      zIndex: 10000,
      fontSize: "14px",
      opacity: 0,
      transform: "translateX(100%)",
      transition: "all 0.3s ease",
    });
    $("<strong>").text("Selected: ").appendTo($notice);
    $notice.append(document.createTextNode(modelName));

    $("body").append($notice);

    // Trigger animation
    setTimeout(() => {
      $notice.css({
        opacity: 1,
        transform: "translateX(0)",
      });
    }, 10);

    // Auto-hide after 3 seconds
    setTimeout(() => {
      $notice.css({
        opacity: 0,
        transform: "translateX(100%)",
      });

      setTimeout(() => {
        $notice.remove();
      }, 300);
    }, 3000);
  }

  // Add visual feedback for form submission
  $("form").on("submit", function () {
    const $submitButton = $(this).find(
      'input[type="submit"], button[type="submit"]',
    );
    const originalText = $submitButton.val() || $submitButton.text();

    $submitButton.prop("disabled", true);

    if ($submitButton.is("input")) {
      $submitButton.val("Saving...");
    } else {
      $submitButton.text("Saving...");
    }

    // Reset button after 5 seconds as a fallback
    setTimeout(() => {
      $submitButton.prop("disabled", false);
      if ($submitButton.is("input")) {
        $submitButton.val(originalText);
      } else {
        $submitButton.text(originalText);
      }
    }, 5000);
  });

  // Add smooth scrolling to model cards when they're selected
  $(".model-card").on("click", function () {
    const $card = $(this);
    const cardTop = $card.offset().top;
    const windowTop = $(window).scrollTop();
    const windowHeight = $(window).height();

    // Only scroll if the card is not fully visible
    if (cardTop < windowTop || cardTop > windowTop + windowHeight - 100) {
      $("html, body").animate(
        {
          scrollTop: cardTop - 100,
        },
        300,
      );
    }
  });

  // API Key Validation — validate a single provider and update its status span.
  function abccValidateProvider(provider) {
    const $status = $(
      '.api-validation-status[data-provider="' + provider + '"]',
    );
    if (!$status.length) {
      return;
    }

    $status
      .removeClass("verified failed")
      .addClass("loading")
      .html(
        '<span class="spinner is-active" style="float:none; margin:0 5px;"></span>',
      );

    $.post(
      ajaxurl,
      {
        action: "abcc_validate_api_key",
        provider: provider,
        nonce: $("#abcc_openai_nonce").val(),
      },
      function (response) {
        $status.removeClass("loading");
        if (response.success) {
          $status.addClass("verified").html("✓ " + response.data.message);
        } else {
          $status.addClass("failed").html("✗ " + response.data.message);
        }
      },
    ).fail(function () {
      $status
        .removeClass("loading")
        .addClass("failed")
        .html("✗ Connection error");
    });
  }

  // Validate all providers at once (called after save).
  window.abccValidateAPIKeys = function () {
    $(".api-validation-status[data-provider]").each(function () {
      abccValidateProvider($(this).data("provider"));
    });
  };

  // Validate a single provider when the user finishes typing in its key field.
  $('input[id$="_api_key"]').on("blur", function () {
    if ($(this).val().trim() === "") {
      return;
    }
    // Derive provider from input id: openai_api_key → openai, etc.
    const provider = $(this).attr("id").replace("_api_key", "");
    abccValidateProvider(provider);
  });

  // Keyword Groups Dynamic UI
  $("#abcc-add-group").on("click", function (e) {
    e.preventDefault();
    const $container = $("#abcc-keyword-groups-container");
    const index = $container.children().length;

    // Create new group HTML (simplified for JS)
    const newGroup = `
      <div class="abcc-group-item" data-index="${index}">
        <div class="abcc-group-header">
          <input type="text" name="abcc_group_name[${index}]" value="" class="abcc-group-name-input" placeholder="New Group Name">
          <span class="abcc-remove-item abcc-remove-group">&times; Remove</span>
        </div>
        <div class="abcc-group-body">
          <div class="abcc-group-keywords">
            <label class="abcc-field-label">Keywords (one per line)</label>
            <textarea name="abcc_group_keywords[${index}]" rows="4" class="large-text"></textarea>
          </div>
          <div class="abcc-group-category">
            <label class="abcc-field-label">Target Category</label>
            ${$(".abcc-category-select").first().clone().attr("name", `abcc_group_category[${index}]`).prop("outerHTML")}
          </div>
          <div class="abcc-group-template">
            <label class="abcc-field-label">Content Template</label>
            <select name="abcc_group_template[${index}]">
              ${$('select[name^="abcc_group_template"]').first().html() || '<option value="default">Default Template</option>'}
            </select>
          </div>
        </div>
      </div>
    `;

    $container.append(newGroup);
  });

  $(document).on("click", ".abcc-remove-group", function () {
    if (confirm("Are you sure you want to remove this keyword group?")) {
      $(this).closest(".abcc-group-item").remove();
    }
  });

  // Content Templates Dynamic UI
  $("#abcc-add-template").on("click", function (e) {
    e.preventDefault();
    const $container = $("#abcc-content-templates-container");
    const slug = "custom_" + Math.random().toString(36).substr(2, 9);

    const newTemplate = `
      <div class="abcc-template-item" data-slug="${slug}">
        <div class="abcc-template-header">
          <input type="text" name="abcc_template_name[]" value="" class="abcc-template-name-input" placeholder="New Template Name">
          <input type="hidden" name="abcc_template_slug[]" value="${slug}">
          <span class="abcc-remove-item abcc-remove-template">&times; Remove</span>
        </div>
        <div class="abcc-template-body" style="grid-template-columns: 1fr;">
          <div class="abcc-template-prompt">
            <label class="abcc-field-label">Prompt Pattern</label>
            <textarea name="abcc_template_prompt[]" rows="6" class="large-text"></textarea>
            <div class="abcc-placeholder-list">
              Available Placeholders:
              <span class="abcc-placeholder-tag">{keywords}</span>
              <span class="abcc-placeholder-tag">{title}</span>
              <span class="abcc-placeholder-tag">{tone}</span>
              <span class="abcc-placeholder-tag">{site_name}</span>
              <span class="abcc-placeholder-tag">{category}</span>
            </div>
          </div>
        </div>
      </div>
    `;

    $container.append(newTemplate);

    // Update all template selectors in keyword groups
    updateTemplateSelectors();
  });

  $(document).on("click", ".abcc-remove-template", function () {
    if (confirm("Are you sure you want to remove this content template?")) {
      $(this).closest(".abcc-template-item").remove();
      updateTemplateSelectors();
    }
  });

  // Function to update all template dropdowns when a template is added or removed
  function updateTemplateSelectors() {
    const options = [{ value: "default", label: "Default Template" }];

    $(".abcc-template-item").each(function () {
      const slug = $(this).data("slug");
      const name =
        $(this).find(".abcc-template-name-input").val().trim() ||
        "Unnamed Template";
      options.push({ value: slug, label: name });
    });

    $('select[name^="abcc_group_template"]').each(function () {
      const currentVal = $(this).val();
      $(this).empty();
      options.forEach(function (opt) {
        $(this).append($("<option>").val(opt.value).text(opt.label));
      }, this);
      // Restore previous selection if it still exists
      if ($(this).find('option[value="' + currentVal + '"]').length) {
        $(this).val(currentVal);
      }
    });
  }

  // Manual post creation from the settings page "Create post manually" button
  $("#generate-post").on("click", function () {
    var $btn = $(this);
    var $status = $("#abcc-manual-generation-status");

    $btn.prop("disabled", true);
    abcc.showStatus($status, "Queueing generation job\u2026");

    var groupIndex = $("#abcc-group-select").length
      ? $("#abcc-group-select").val()
      : null;

    $.ajax({
      url: ajaxurl,
      method: "POST",
      data: {
        action: "abcc_create_post",
        nonce: abccAdmin.buttonNonce,
        group_index: groupIndex,
        post_type: $("#abcc_selected_post_types").length
          ? ($("#abcc_selected_post_types").val() || [])[0] || "post"
          : "post",
      },
      success: function (response) {
        if (response.success) {
          abccActiveRunId = "";
          abcc.showStatus($status, response.data.message);
          refreshJobLog();
          pollJob(response.data.job_id, {
            onUpdate: function (job) {
              abcc.showStatus($status, "Status: " + job.statusLabel);
            },
            onSuccess: function (job) {
              abcc.showStatus(
                $status,
                "Post created successfully. Post ID: " + job.post_id,
                "success"
              );
              $btn.prop("disabled", false);
            },
            onFailed: function (message) {
              abcc.setError($status, message);
              $btn.prop("disabled", false);
            },
            onError: function (message) {
              abcc.setError($status, message);
              $btn.prop("disabled", false);
            },
          });
        } else {
          abcc.setError($status, response.data.message);
          $btn.prop("disabled", false);
        }
      },
      error: function (xhr) {
        abcc.setError($status, "Error generating post: " + xhr.statusText);
        $btn.prop("disabled", false);
      },
    });
  });

  // Bulk Generate Panel Toggle
  $(".abcc-panel-header").on("click", function () {
    const $panel = $(this).closest(".abcc-collapsible-panel");
    const $content = $panel.find(".abcc-panel-content");
    const $icon = $(this).find(".dashicons");

    $content.slideToggle(200);
    if ($icon.hasClass("dashicons-arrow-down-alt2")) {
      $icon.removeClass("dashicons-arrow-down-alt2").addClass("dashicons-arrow-up-alt2");
    } else {
      $icon.removeClass("dashicons-arrow-up-alt2").addClass("dashicons-arrow-down-alt2");
    }
  });

  // Bulk Keywords Input Handler
  $("#abcc-bulk-keywords-input").on("input", function () {
    const keywords = $(this)
      .val()
      .split("\n")
      .map((k) => k.trim())
      .filter((k) => k !== "");
    const count = keywords.length;
    const $btn = $("#abcc-start-bulk");

    $btn.text(abccAdmin.i18n.generateNPosts.replace("%d", count));
    $btn.prop("disabled", count === 0);
  });

  $("#abcc-bulk-keywords-file").on("change", function (event) {
    const file = event.target.files && event.target.files[0];

    if (!file) {
      return;
    }

    if (!/\.txt$/i.test(file.name)) {
      window.alert("Please upload a .txt file.");
      $(this).val("");
      return;
    }

    const reader = new FileReader();
    reader.onload = function (loadEvent) {
      const contents = String(loadEvent.target.result || "");
      $("#abcc-bulk-keywords-input").val(contents).trigger("input");
    };
    reader.readAsText(file);
  });

  // Start Bulk Generation
  $("#abcc-start-bulk").on("click", function () {
    const $btn = $(this);
    const keywords = $("#abcc-bulk-keywords-input")
      .val()
      .split("\n")
      .map((k) => k.trim())
      .filter((k) => k !== "");
    const template = $("#abcc-bulk-template").val();
    const model = $("#abcc-bulk-model").val();
    const runId = `bulk-${Date.now()}`;
    const $progress = $("#abcc-bulk-progress");
    const $log = $("#abcc-bulk-log");

    if (keywords.length === 0) return;

    if (
      !confirm(
        `You are about to generate ${keywords.length} posts sequentially. This may take several minutes. Continue?`
      )
    ) {
      return;
    }

    $btn.prop("disabled", true);
    $("#abcc-bulk-keywords-input").prop("disabled", true);
    $("#abcc-bulk-keywords-file").prop("disabled", true);
    $progress.show();
    $log.empty();
    abccActiveRunId = runId;
    refreshJobLog(runId);

    processBulkSequential(keywords, template, model, runId, 0);
  });

  function processBulkSequential(keywords, template, model, runId, index) {
    if (index >= keywords.length) {
      $("#abcc-bulk-log").prepend(
        '<div class="abcc-bulk-log-entry" style="color: #46b450; font-weight: bold;">\u2713 All jobs queued. The generation log will keep updating below.</div>'
      );
      $("#abcc-start-bulk").prop("disabled", false).text(abccAdmin.i18n.generateNPosts.replace("%d", keywords.length));
      $("#abcc-bulk-keywords-input").prop("disabled", false);
      $("#abcc-bulk-keywords-file").prop("disabled", false).val("");
      return;
    }

    const keyword = keywords[index];
    const $log = $("#abcc-bulk-log");
    const $entry = $(
      `<div class="abcc-bulk-log-entry" id="bulk-entry-${index}">` +
        `[${index + 1}/${keywords.length}] Queueing: <strong>${abccEscapeHtml(keyword)}</strong>... ` +
        `<span class="abcc-bulk-status--running">Submitting...</span>` +
        `</div>`
    );

    $log.prepend($entry);

    $.ajax({
      url: ajaxurl,
      method: "POST",
      data: {
        action: "abcc_bulk_generate_single",
        nonce: abccAdmin.nonce,
        keyword: keyword,
        template: template,
        model: model,
        run_id: runId,
      },
      success: function (response) {
        const $status = $entry.find("span");
        if (response.success) {
          $status.text(`Queued (Job ID: ${response.data.job_id})`);
          refreshJobLog(runId);
          pollJob(response.data.job_id, {
            onUpdate: function (job) {
              $status
                .removeClass("abcc-bulk-status--success abcc-bulk-status--error")
                .addClass("abcc-bulk-status--running")
                .text(job.statusLabel);
            },
            onSuccess: function (job) {
              $status
                .removeClass("abcc-bulk-status--running abcc-bulk-status--error")
                .addClass("abcc-bulk-status--success")
                .html(`\u2713 Success (Post ID: ${job.post_id})`);
              refreshJobLog(runId);
            },
            onFailed: function (message) {
              $status
                .removeClass("abcc-bulk-status--running")
                .addClass("abcc-bulk-status--error")
                .html(`\u2717 Error: ${abccEscapeHtml(message)}`);
              refreshJobLog(runId);
            },
            onError: function (message) {
              $status
                .removeClass("abcc-bulk-status--running")
                .addClass("abcc-bulk-status--error")
                .html(`\u2717 Error: ${abccEscapeHtml(message)}`);
              refreshJobLog(runId);
            },
          });
        } else {
          $status
            .removeClass("abcc-bulk-status--running")
            .addClass("abcc-bulk-status--error")
            .html(`\u2717 Error: ${abccEscapeHtml(response.data.message)}`);
        }
      },
      error: function (xhr) {
        $entry
          .find("span")
          .removeClass("abcc-bulk-status--running")
          .addClass("abcc-bulk-status--error")
          .html(`\u2717 Network Error: ${abccEscapeHtml(xhr.statusText)}`);
      },
      complete: function () {
        // Sequential delay to avoid rate limits
        setTimeout(() => {
          processBulkSequential(keywords, template, model, runId, index + 1);
        }, 1000);
      },
    });
  }

  $("#preferred_image_service").on("change", function () {
    const showGeminiFields = $(this).val() === "gemini";
    $("#gemini-image-settings, #gemini-image-size-settings").toggle(showGeminiFields);
  });

  // Regenerate Post Logic
  $(document).on("click", ".abcc-regenerate-post", function (e) {
    e.preventDefault();
    const $link = $(this);
    const postId = $link.data("post-id");
    const $statusCell = $link.closest("td");

    if (
      !confirm(
        "Are you sure you want to regenerate this post? It will create a NEW draft using the same parameters.",
      )
    ) {
      return;
    }

    $link.css("pointer-events", "none");
    abcc.showStatus($statusCell, "Regenerating\u2026");

    $.post(
      ajaxurl,
      {
        action: "abcc_regenerate_post",
        post_id: postId,
        nonce: $("#abcc_openai_nonce").val(),
      },
      function (response) {
        if (response.success) {
          abccActiveRunId = "";
          abcc.showStatus($statusCell, response.data.message);
          refreshJobLog();
          pollJob(response.data.job_id, {
            onUpdate: function (job) {
              abcc.showStatus($statusCell, "Status: " + job.statusLabel);
            },
            onSuccess: function (job) {
              abcc.showStatus($statusCell, "Done! Redirecting\u2026", "success");
              window.location.href = job.edit_url;
            },
            onFailed: function (message) {
              abcc.setError($statusCell, message);
              $link.css("pointer-events", "auto");
            },
            onError: function (message) {
              abcc.setError($statusCell, message);
              $link.css("pointer-events", "auto");
            },
          });
        } else {
          abcc.setError(
            $statusCell,
            response.data.message || "An error occurred.",
          );
          $link.css("pointer-events", "auto");
        }
      },
    ).fail(function () {
      abcc.setError($statusCell, "Network error occurred.");
      $link.css("pointer-events", "auto");
    });
  });
});
