/**
 * Onboarding JavaScript for WP-AutoInsight
 *
 * Four-step provider-first flow (v4.3):
 *   Step 1 – Connect a provider (step-providers.php, old IDs retained)
 *   Step 2 – Post status         (step-post-status.php, generic data attrs)
 *   Step 3 – First Topic          (step-first-topic.php, generic data attrs)
 *   Step 4 – Try audio / Finish   (step-try-audio.php,  generic data attrs)
 *
 * @package WP-AutoInsight
 */
jQuery(document).ready(function ($) {
  var currentStep = 1;
  var connectedProvider = null;
  var createdTopicSignature = null;

  // Collect numbered steps (exclude success screen).
  var $steps = $('.abcc-onboarding-step').not('.abcc-step-success');
  var totalSteps = $steps.length; // 4 in normal flow

  // Initialize onboarding
  initOnboarding();

  function initOnboarding() {
    updateStepDisplay();
    updateProgressBar();
    bindEvents();
    markConfiguredProviders();
    // Check WP 7.0 Connectors on load (providers is step 1).
    abccCheckWP70Connectors();
    // Auto-test any wp-config keys visible on step 1.
    setTimeout(function () {
      var $wpConfigButtons = $('.abcc-test-api[data-wp-config="true"]');
      if ($wpConfigButtons.length) {
        $wpConfigButtons.first().trigger('click');
      }
    }, 300);
  }

  // -------------------------------------------------------------------------
  // Event binding
  // -------------------------------------------------------------------------

  function bindEvents() {

    // Help toggle functionality
    $('.abcc-help-toggle').on('click', function (e) {
      e.preventDefault();

      var $button = $(this);
      var provider = $button.data('provider');
      var $content = $('.abcc-help-content[data-provider="' + provider + '"]');
      var $arrow = $button.find('.abcc-help-arrow');

      if ($content.is(':visible')) {
        $content.slideUp(200);
        $arrow.text('▼');
        $button.removeClass('active');
      } else {
        // Close other open help sections
        $('.abcc-help-content').slideUp(200);
        $('.abcc-help-toggle .abcc-help-arrow').text('▼');
        $('.abcc-help-toggle').removeClass('active');

        $content.slideDown(200);
        $arrow.text('▲');
        $button.addClass('active');
      }
    });

    // Generic next/prev navigation (steps 2-4 use data-goto).
    $(document).on('click', '.abcc-next-step', handleNextStep);
    $(document).on('click', '.abcc-prev-step', handlePrevStep);

    // Step 1 (providers) still uses the old hard-coded IDs from step-providers.php.
    // There is no Back button on step 1 (#abcc-prev-step-2 exists in the partial
    // but is the first step so we leave it in place — it will be a no-op since
    // currentStep can't go below 1).
    $(document).on('click', '#abcc-prev-step-2', function () {
      // No-op: providers is step 1, cannot go back.
    });

    // API testing
    $(".abcc-test-api").on("click", testApiConnection);

    // Finish button (step 4)
    $(document).on('click', '#abcc-onboarding-finish', handleFinish);

    // Skip onboarding (delegated — catches all step skip buttons)
    $(document).on('click', '.abcc-skip-onboarding', handleSkip);

    // Provider selection (visual feedback)
    $(".abcc-api-provider").on("click", function () {
      $(".abcc-api-provider").removeClass("selected");
      $(this).addClass("selected");
    });

    // Auto-test when API key is pasted/typed
    $('input[id$="-api-key"]').on("paste input", function () {
      var $input = $(this);
      var provider = $input.attr("id").replace("-api-key", "");

      $('.' + provider + '-status').removeClass("success error").empty();

      clearTimeout($input.data("autotest-timeout"));
      $input.data(
        "autotest-timeout",
        setTimeout(function () {
          if ($input.val().length > 10) {
            $('.abcc-test-api[data-provider="' + provider + '"]').trigger("click");
          }
        }, 1000)
      );
    });
  }

  // Providers with a stored key (restart flow) unlock Continue without a re-test.
  function markConfiguredProviders() {
    var configured = abccOnboarding.configuredProviders || [];
    if (!configured.length) {
      return;
    }
    $.each(configured, function (i, provider) {
      showSuccess($("." + provider + "-status"), abccOnboarding.i18n.keySaved);
      $(".abcc-api-provider[data-provider=\"" + provider + "\"]").addClass("connected");
    });
    connectedProvider = configured[0];
    $("#abcc-next-step-2").prop("disabled", false);
  }

  // -------------------------------------------------------------------------
  // WP 7.0 Connectors
  // -------------------------------------------------------------------------

  function abccCheckWP70Connectors() {
    $.ajax({
      url: abccOnboarding.ajaxurl,
      type: "POST",
      data: {
        action: "abcc_check_wp_connectors",
        nonce: abccOnboarding.nonce,
      },
      success: function (response) {
        if (!response.success) {
          return;
        }

        var data = response.data;
        $("#abcc-open-connectors").attr("href", data.connectors_url);

        if (data.has_connectors && data.has_keys) {
          var providerNames = data.providers
            .map(function (provider) {
              return (abccOnboarding.providerNames && abccOnboarding.providerNames[provider]) || provider;
            })
            .join(", ");

          $("#abcc-wp70-connected-providers").text(
            abccOnboarding.i18n.connectedVia.replace("%s", providerNames)
          );

          $("#abcc-wp70-connector-banner").slideDown();
          $(".abcc-api-providers").show();
        } else if (data.has_connectors && !data.has_keys) {
          $("#abcc-wp70-no-keys-banner").slideDown();
        }
      },
    });
  }

  // Handle Connectors "Use existing connection" button
  $(document).on("click", "#abcc-wp70-use-connectors", function (event) {
    event.preventDefault();
    connectedProvider = "wp-connectors";
    // Enable step 1's Continue button (old hard-coded ID from step-providers.php).
    $("#abcc-next-step-2").prop("disabled", false);
    $(this).text(abccOnboarding.i18n.connectorsActive).prop("disabled", true);
    $(".abcc-api-providers").slideUp();
  });

  $(document).on("click", "#abcc-wp70-add-different", function () {
    $("#abcc-wp70-connector-banner").slideUp();
    $(".abcc-api-providers").slideDown();
  });

  // -------------------------------------------------------------------------
  // Navigation handlers
  // -------------------------------------------------------------------------

  function handleNextStep(e) {
    e.preventDefault();
    var $btn = $(this);
    var targetStep = parseInt($btn.data('goto'), 10);
    var fromStep = parseInt($btn.data('step'), 10) || currentStep;

    if (fromStep === 2) {
      // Step 2: save post_status before advancing.
      savePostStatus(targetStep);
    } else if (fromStep === 3) {
      // Step 3: optionally create first topic, then advance.
      maybeCreateFirstTopic(targetStep);
    } else {
      goToStep(targetStep);
    }
  }

  function handlePrevStep(e) {
    e.preventDefault();
    var targetStep = parseInt($(this).data('goto'), 10);
    goToStep(targetStep);
  }

  // -------------------------------------------------------------------------
  // Step 1 — provider Continue (old ID #abcc-next-step-2, no AJAX needed)
  // Binding is on the DOM element; enabling is done by testApiConnection success.
  // The old click binding on #abcc-next-step-2 was removed — this delegated
  // handler catches it generically because #abcc-next-step-2 does NOT carry
  // the .abcc-next-step class, so we wire it explicitly here.
  // -------------------------------------------------------------------------

  $(document).on('click', '#abcc-next-step-2', function (e) {
    e.preventDefault();
    if (!$(this).prop('disabled')) {
      goToStep(2);
    }
  });

  // -------------------------------------------------------------------------
  // Step 2 AJAX: post status
  // -------------------------------------------------------------------------

  function savePostStatus(targetStep) {
    var status = $('input[name="abcc_onboarding_status"]:checked').val() || 'draft';
    var language = $('#abcc_onboarding_language').val() || 'site';

    $.ajax({
      url: abccOnboarding.ajaxurl,
      type: 'POST',
      data: {
        action: 'abcc_onboarding_post_status',
        status: status,
        language: language,
        nonce: abccOnboarding.nonce,
      },
      success: function (response) {
        if (response.success) {
          goToStep(targetStep);
        } else {
          showStepError(response.data && response.data.message
            ? response.data.message
            : abccOnboarding.i18n.saveFailed);
        }
      },
      error: function () {
        showStepError(abccOnboarding.i18n.networkError);
      },
    });
  }

  // -------------------------------------------------------------------------
  // Step 3 AJAX: first topic (optional)
  // -------------------------------------------------------------------------

  function maybeCreateFirstTopic(targetStep) {
    var title  = $('#abcc-first-topic-title').val().trim();
    var prompt = $('#abcc-first-topic-prompt').val().trim();

    if (!title && !prompt) {
      // Nothing entered — skip without POSTing.
      goToStep(targetStep);
      return;
    }

    var frequency = $('#abcc-first-topic-frequency').val() || 'weekly';
    var signature = JSON.stringify([title, prompt, frequency]);
    if (signature === createdTopicSignature) {
      // Already created on an earlier pass (user came back with Back).
      goToStep(targetStep);
      return;
    }
    var $btn = $('.abcc-next-step[data-step="3"]');
    $btn.prop('disabled', true);

    $.ajax({
      url: abccOnboarding.ajaxurl,
      type: 'POST',
      data: {
        action: 'abcc_onboarding_first_topic',
        title: title,
        prompt: prompt,
        frequency: frequency,
        nonce: abccOnboarding.nonce,
      },
      success: function (response) {
        $btn.prop('disabled', false);
        if (response.success) {
          createdTopicSignature = signature;
          goToStep(targetStep);
        } else {
          showStepError(response.data && response.data.message
            ? response.data.message
            : abccOnboarding.i18n.topicFailed);
        }
      },
      error: function () {
        $btn.prop('disabled', false);
        showStepError(abccOnboarding.i18n.networkError);
      },
    });
  }

  // -------------------------------------------------------------------------
  // Step 4: Finish
  // -------------------------------------------------------------------------

  function handleFinish(e) {
    e.preventDefault();
    var $btn = $('#abcc-onboarding-finish');
    $btn.prop('disabled', true).text(abccOnboarding.i18n.finishing);

    $.ajax({
      url: abccOnboarding.ajaxurl,
      type: 'POST',
      data: {
        action: 'abcc_onboarding_complete',
        nonce: abccOnboarding.nonce,
      },
      success: function (response) {
        if (response.success) {
          showSuccessStep();
        } else {
          $btn.prop('disabled', false).text(abccOnboarding.i18n.finish);
          showStepError((response.data && response.data.message) || abccOnboarding.i18n.saveFailed);
        }
      },
      error: function () {
        $btn.prop('disabled', false).text(abccOnboarding.i18n.finish);
        showStepError(abccOnboarding.i18n.networkError);
      },
    });
  }

  // -------------------------------------------------------------------------
  // Skip onboarding
  // -------------------------------------------------------------------------

  function handleSkip(e) {
    e.preventDefault();
    if (!confirm(abccOnboarding.i18n.confirmSkip || "Skip the setup?")) {
      return;
    }

    var $btn = $(e.currentTarget);
    $btn.prop('disabled', true);
    $.ajax({
      url: abccOnboarding.ajaxurl,
      type: "POST",
      data: { action: "abcc_onboarding_skip", nonce: abccOnboarding.nonce },
      success: function (response) {
        if (response.success) {
          removeUnloadListener();
          window.location.href = "?page=automated-blog-content-creator-post";
          return;
        }
        $btn.prop('disabled', false);
        showStepError((response.data && response.data.message) || abccOnboarding.i18n.saveFailed);
      },
      error: function () {
        $btn.prop('disabled', false);
        showStepError(abccOnboarding.i18n.networkError);
      },
    });
  }

  // -------------------------------------------------------------------------
  // API connection test (step 1)
  // -------------------------------------------------------------------------

  function testApiConnection() {
    var $button   = $(this);
    if ($button.prop("disabled")) {
      return;
    }
    var provider  = $button.data("provider");
    var isWpConfig = $button.data("wp-config") === true;
    var $input    = $("#" + provider + "-api-key");
    var $status   = $("." + provider + "-status");

    // A manual click supersedes the pending debounced auto-test.
    clearTimeout($input.data("autotest-timeout"));

    var apiKey = "";
    if (isWpConfig) {
      apiKey = "wp-config";
    } else {
      apiKey = $input.val() ? $input.val().trim() : "";
      if (!apiKey) {
        showError($status, abccOnboarding.i18n.enterKey);
        return;
      }
    }

    $button.prop("disabled", true).text(abccOnboarding.i18n.testing);
    abcc.showStatus($status, abccOnboarding.i18n.testing);

    $.ajax({
      url: abccOnboarding.ajaxurl,
      type: "POST",
      data: {
        action: "abcc_onboarding_test_api",
        provider: provider,
        api_key: apiKey,
        wp_config: isWpConfig,
        nonce: abccOnboarding.nonce,
      },
      success: function (response) {
        if (response.success) {
          showSuccess($status, abccOnboarding.i18n.success);
          connectedProvider = provider;
          // Enable step 1's Continue button (old hard-coded ID from step-providers.php).
          $("#abcc-next-step-2").prop("disabled", false);

          $(".abcc-api-provider[data-provider=\"" + provider + "\"]").addClass("connected");
        } else {
          showError(
            $status,
            (response.data && response.data.message) || abccOnboarding.i18n.error
          );
        }
      },
      error: function () {
        showError($status, abccOnboarding.i18n.error);
      },
      complete: function () {
        $button.prop("disabled", false);
        $button.text(isWpConfig ? abccOnboarding.i18n.testConnection : abccOnboarding.i18n.test);
      },
    });
  }

  // -------------------------------------------------------------------------
  // Step display & progress
  // -------------------------------------------------------------------------

  function goToStep(n) {
    currentStep = n;
    updateStepDisplay();
    updateProgressBar();
    smoothScrollToTop();
  }

  function updateStepDisplay() {
    // Show only the nth numbered step (1-indexed); hide all others including success.
    $(".abcc-step-success").removeClass("active").hide();
    $steps.removeClass("active").hide().eq(currentStep - 1).addClass("active").show();
  }

  function updateProgressBar() {
    var progress = totalSteps > 1 ? ((currentStep - 1) / (totalSteps - 1)) * 100 : 0;
    $(".abcc-progress-fill").css("width", progress + "%");

    $(".abcc-step-indicators .abcc-step").each(function () {
      var stepNum = parseInt($(this).data("step"), 10);
      if (stepNum < currentStep) {
        $(this).removeClass("active").addClass("completed");
      } else if (stepNum === currentStep) {
        $(this).addClass("active").removeClass("completed");
      } else {
        $(this).removeClass("active completed");
      }
    });
  }

  function showSuccessStep() {
    $steps.removeClass("active").hide();
    $(".abcc-step-success").addClass("active").show();

    $(".abcc-progress-fill").css("width", "100%");
    $(".abcc-step-indicators .abcc-step").removeClass("active").addClass("completed");

    removeUnloadListener();

    setTimeout(function () {
      $(".abcc-success-content").addClass("celebrate");
    }, 500);

    // No auto-redirect — the success screen waits for an explicit click so
    // the "What's Next" list stays readable (screen-reader users especially).
    $(".abcc-success-actions .button-primary").trigger("focus");
  }

  // -------------------------------------------------------------------------
  // Utility
  // -------------------------------------------------------------------------

  function showSuccess($element, message) {
    var $icon = $("<span>").addClass("dashicons dashicons-yes-alt");
    var $wrap = $("<span>").append($icon, $("<span>").text(" " + message));

    $element.removeClass("error").addClass("success");
    abcc.showHtml($element, $wrap, "success");
    $element.closest(".abcc-api-input").trigger("success");
  }

  function showError($element, message) {
    var $icon = $("<span>").addClass("dashicons dashicons-warning");
    var $wrap = $("<span>").append($icon, $("<span>").text(" " + message));

    $element.removeClass("success").addClass("error");
    abcc.showHtml($element, $wrap, "error");
  }

  function showStepError(message) {
    // Find or create an inline error area near the current step's actions.
    var $step = $steps.eq(currentStep - 1);
    var $err = $step.find('.abcc-step-error');
    if (!$err.length) {
      $err = $('<p class="abcc-step-error" style="color:#d63638;margin-top:8px;"></p>');
      $step.find('.abcc-step-actions').before($err);
    }
    $err.text(message).show();
    setTimeout(function () { $err.fadeOut(); }, 5000);
  }

  function smoothScrollToTop() {
    $(".abcc-onboarding-container").animate({ scrollTop: 0 }, 300);
  }

  // -------------------------------------------------------------------------
  // Keyboard navigation
  // -------------------------------------------------------------------------

  $(document).on("keydown", function (e) {
    if (e.key !== "Enter") { return; }
    // Textareas need Enter for newlines; buttons, links and selects handle Enter natively.
    if ($(e.target).is("textarea, button, a, select")) { return; }
    if ($(".abcc-step-success").is(":visible")) { return; }
    if (currentStep === 1) {
      var $btn = $("#abcc-next-step-2");
      if ($btn.length && !$btn.prop("disabled")) {
        $btn.trigger("click");
      }
    } else {
      var $btn = $steps.eq(currentStep - 1).find(".abcc-next-step");
      if ($btn.length && !$btn.prop("disabled")) {
        $btn.trigger("click");
      }
    }
  });

  // -------------------------------------------------------------------------
  // Auto-resize API key inputs
  // -------------------------------------------------------------------------

  $('input[id$="-api-key"]').on("input", function () {
    var value = $(this).val();
    if (value.length > 20) {
      $(this).addClass("long-key");
    } else {
      $(this).removeClass("long-key");
    }
  });

  // -------------------------------------------------------------------------
  // Copy API key button (add after successful test)
  // -------------------------------------------------------------------------

  $(".abcc-api-input").each(function () {
    var $container = $(this);
    var $input = $container.find("input");

    $container.on("success", function () {
      if (!$container.find(".abcc-copy-key").length) {
        var $copyBtn = $('<button type="button" class="button button-small abcc-copy-key">📋</button>').attr(
          "title",
          abccOnboarding.i18n.copyKey
        );
        $container.append($copyBtn);

        $copyBtn.on("click", function () {
          $input.select();
          document.execCommand("copy");
          $copyBtn.text("✓").prop("disabled", true);
          setTimeout(function () {
            $copyBtn.text("📋").prop("disabled", false);
          }, 2000);
        });
      }
    });
  });

  // -------------------------------------------------------------------------
  // Prevent accidental page reload during onboarding
  // -------------------------------------------------------------------------

  var beforeUnloadHandler = function (e) {
    if (currentStep > 1 && currentStep <= totalSteps) {
      e.preventDefault();
      e.returnValue = abccOnboarding.i18n.leaveWarning;
      return e.returnValue;
    }
  };
  window.addEventListener("beforeunload", beforeUnloadHandler);

  function removeUnloadListener() {
    window.removeEventListener("beforeunload", beforeUnloadHandler);
  }
});
