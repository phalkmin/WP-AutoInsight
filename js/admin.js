jQuery(document).ready(function ($) {
  // ── Auto-save ──────────────────────────────────────────────────────────────
  var abccAutosaveTimer = null;
  var $abccIndicator = $('#abcc-autosave-indicator');

  var abccHideTimer = null;

  function abccToastShow(stateClass, text) {
    clearTimeout(abccHideTimer);
    $abccIndicator
      .removeClass('abcc-saving abcc-saved abcc-error')
      .text(text)
      .addClass(stateClass);
  }

  function abccToastHide(delay) {
    abccHideTimer = setTimeout(function () {
      $abccIndicator.removeClass('abcc-saving abcc-saved abcc-error');
    }, delay);
  }

  function abccShowSaving() {
    abccToastShow('abcc-saving', abccAdmin.i18n.saving || 'Saving\u2026');
  }

  function abccShowSaved() {
    abccToastShow('abcc-saved', abccAdmin.i18n.saved || 'Saved \u2713');
    abccToastHide(2500);
  }

  function abccShowError() {
    abccToastShow('abcc-error', abccAdmin.i18n.saveFailed || 'Save failed \u2717');
    abccToastHide(4000);
  }

  function abccAutosaveSetting(key, value) {
    abccShowSaving();
    $.post(ajaxurl, {
      action: 'abcc_autosave_setting',
      nonce: abccAdmin.nonce,
      key: key,
      value: value
    }).done(function (response) {
      if (response.success) {
        abccShowSaved();
      } else {
        abccShowError();
      }
    }).fail(function () {
      abccShowError();
    });
  }

  // Attach to any field with data-autosave-key attribute.
  $(document).on('change', '[data-autosave-key]', function () {
    var $field = $(this);
    var key    = $field.data('autosave-key');
    var value  = $field.is(':checkbox') ? ($field.is(':checked') ? '1' : '0') : $field.val();

    clearTimeout(abccAutosaveTimer);
    abccAutosaveTimer = setTimeout(function () {
      abccAutosaveSetting(key, value);
    }, 800);
  });
  // ── End Auto-save ──────────────────────────────────────────────────────────

  // ── Unsaved-changes warning (scheduling fields only) ──────────────────────
  // Fields with data-dirty-watch are submit-only (scheduling frequency, email
  // notifications). JS watches them and fires beforeunload if user navigates away.
  var abccDirty = false;

  $(document).on('change', '[data-dirty-watch]', function () {
    abccDirty = true;
  });

  $(document).on('submit', 'form', function () {
    abccDirty = false;
  });

  $(window).on('beforeunload', function () {
    if (abccDirty) {
      return '';
    }
  });
  // ── End Unsaved-changes warning ────────────────────────────────────────────

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

  // Initialize Select2 where available for existing admin fields.
  if ($.fn.select2) {
    $(".wpai-category-select, .wpai-post-type-select, .abcc-category-select").select2();
  }

  // Handle custom tone input visibility
  $("#openai_tone").on("change", function () {
    var customContainer = $("#abcc-custom-tone-wrapper");
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

    var savingText = (abccAdmin.i18n && abccAdmin.i18n.saving) ? abccAdmin.i18n.saving : "Saving\u2026";
    if ($submitButton.is("input")) {
      $submitButton.val(savingText);
    } else {
      $submitButton.text(savingText);
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

    const $keyInput = $("#" + provider + "_api_key");
    const apiKey = $keyInput.length ? $keyInput.val().trim() : "";

    $.post(
      ajaxurl,
      {
        action: "abcc_validate_api_key",
        provider: provider,
        api_key: apiKey,
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

  // Validate button — explicit click handler.
  $(document).on("click", ".abcc-validate-key", function () {
    const provider = $(this).data("provider");
    if (provider) {
      abccValidateProvider(provider);
    }
  });

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
    const $container = $("#abcc-templates-container");
    const slug = "custom_" + Math.random().toString(36).substr(2, 9);

    const newTemplate = `
      <div class="abcc-template-item" data-slug="${slug}">
        <div class="abcc-group-header">
          <input type="text" name="abcc_template_name[]" value="" class="abcc-template-name-input" placeholder="New Template Name">
          <input type="hidden" name="abcc_template_slug[]" value="${slug}">
          <span class="abcc-remove-item abcc-remove-template">&times; Remove</span>
        </div>
        <textarea name="abcc_template_prompt[]" rows="3" class="large-text"></textarea>
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
      if (!slug || slug === "default") {
        return;
      }
      const $nameInput = $(this).find('input[name="abcc_template_name[]"]');
      const name = $nameInput.length
        ? ($nameInput.val() || "").trim()
        : ($(this).find(".abcc-group-header strong").text() || "").trim();
      options.push({ value: slug, label: name || "Unnamed Template" });
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

  // Dashboard "Generate Post Now" quick action
  $("#abcc-dash-generate").on("click", function () {
    var $btn = $(this);
    var $status = $("#abcc-dash-generate-status");

    $btn.prop("disabled", true);
    abcc.showStatus($status, "Queueing generation job\u2026");

    $.ajax({
      url: ajaxurl,
      method: "POST",
      data: {
        action: "abcc_create_post",
        nonce: abccAdmin.buttonNonce,
      },
      success: function (response) {
        if (response.success) {
          abcc.showStatus($status, response.data.message);
          pollJob(response.data.job_id, {
            onUpdate: function (job) {
              abcc.showStatus($status, "Status: " + job.statusLabel);
            },
            onSuccess: function (job) {
              abcc.showStatus(
                $status,
                "Post created! <a href='" + abccAdmin.adminUrl + "post=" + job.post_id + "&action=edit'>Edit post</a>",
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
        abcc.setError($status, "Error: " + xhr.statusText);
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
        nonce: abccAdmin.nonce,
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

  // ── Bulk Generate ─────────────────────────────────────────────────────────
  var $bulkKeywords = $('#abcc-bulk-keywords-input');
  var $bulkStart    = $('#abcc-bulk-start');

  if ($bulkStart.length) {
    function updateBulkCount() {
      var keywords = $bulkKeywords.val().split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
      var count = keywords.length;
      $bulkStart.prop('disabled', count === 0);
      $bulkStart.text(count === 0
        ? (abccAdmin.i18n.generateNPosts || 'Generate 0 Posts').replace('%d', 0)
        : (abccAdmin.i18n.generateNPosts || 'Generate %d Posts').replace('%d', count)
      );
    }

    $bulkKeywords.on('input', updateBulkCount);

    $('#abcc-bulk-file-upload').on('change', function () {
      var file = this.files[0];
      if (!file) { return; }
      var reader = new FileReader();
      reader.onload = function (e) {
        $bulkKeywords.val(e.target.result.trim());
        updateBulkCount();
      };
      reader.readAsText(file);
    });

    $bulkStart.on('click', function () {
      var keywords = $bulkKeywords.val().split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
      if (!keywords.length) { return; }

      var $progress = $('#abcc-bulk-progress');
      var $body     = $('#abcc-bulk-log-body');
      $progress.show();
      $body.empty();
      $bulkStart.prop('disabled', true);
      $bulkKeywords.prop('disabled', true);
      $('#abcc-bulk-file-upload').prop('disabled', true);

      var template = $('#abcc-bulk-template').val();
      var model    = $('#abcc-bulk-model').val();
      var draft    = $('#abcc-bulk-draft').is(':checked') ? '1' : '0';

      keywords.forEach(function (kw) {
        $body.append(
          '<tr data-kw="' + abccEscapeHtml(kw) + '">' +
          '<td>' + abccEscapeHtml(kw) + '</td>' +
          '<td class="abcc-bulk-status">' + abccEscapeHtml(abccAdmin.i18n.queued || 'Queued') + '</td>' +
          '<td class="abcc-bulk-result"></td>' +
          '</tr>'
        );
      });

      function processNext(index) {
        if (index >= keywords.length) {
          $bulkStart.prop('disabled', false);
          $bulkKeywords.prop('disabled', false);
          $('#abcc-bulk-file-upload').prop('disabled', false).val('');
          return;
        }
        var kw  = keywords[index];
        var $tr = $body.find('tr[data-kw="' + abccEscapeHtml(kw) + '"]');
        $tr.find('.abcc-bulk-status').text(abccAdmin.i18n.generating || 'Generating\u2026');

        $.post(ajaxurl, {
          action:   'abcc_bulk_generate_single',
          nonce:    abccAdmin.nonce,
          keyword:  kw,
          template: template,
          model:    model,
          draft:    draft
        }).done(function (response) {
          if (response.success) {
            $tr.find('.abcc-bulk-status').text(abccAdmin.i18n.done || 'Done');
            if (response.data && response.data.edit_url) {
              $tr.find('.abcc-bulk-result').html(
                '<a href="' + abccEscapeHtml(response.data.edit_url) + '">' +
                (abccAdmin.i18n.viewPost || 'View post') + '</a>'
              );
            }
          } else {
            var msg = response.data && response.data.message ? response.data.message : 'Error';
            $tr.find('.abcc-bulk-status').text(abccAdmin.i18n.failed || 'Failed');
            $tr.find('.abcc-bulk-result').text(msg);
          }
        }).fail(function () {
          $tr.find('.abcc-bulk-status').text(abccAdmin.i18n.failed || 'Failed');
        }).always(function () {
          processNext(index + 1);
        });
      }

      processNext(0);
    });
  }
  // ── End Bulk Generate ─────────────────────────────────────────────────────

  // ── Image provider options toggle ─────────────────────────────────────────
  $('input[name="preferred_image_service"]').on('change', function () {
    var selected = $(this).val();
    $('.abcc-image-provider-options').hide();
    if ('auto' === selected || 'openai' === selected) {
      $('#abcc-provider-options-openai').show();
    } else if ('gemini' === selected) {
      $('#abcc-provider-options-gemini').show();
    } else if ('stability' === selected) {
      $('#abcc-provider-options-stability').show();
    }
  });
  // ── End image provider toggle ──────────────────────────────────────────────

  // ── Dashboard: Recent Activity filter ────────────────────────────────────
  $(document).on('click', '.abcc-filter-btn', function () {
    var $btn   = $(this);
    var filter = $btn.data('filter');
    var $list  = $('#abcc-dash-activity-list');
    var $items = $list.find('.abcc-activity-item');
    var $empty = $list.siblings('.abcc-activity-empty');

    $btn.closest('.abcc-activity-filters').find('.abcc-filter-btn').removeClass('abcc-filter-btn--active');
    $btn.addClass('abcc-filter-btn--active');

    var visible = 0;
    $items.each(function () {
      var show = ('all' === filter) || ($(this).data('status') === filter);
      $(this).toggle(show);
      if (show) { visible++; }
    });
    $empty.toggle(visible === 0);
  });
  // ── End Dashboard filter ──────────────────────────────────────────────────
});
