/**
 * Onboarding JavaScript for WP-AutoInsight
 *
 * @package WP-AutoInsight
 */
jQuery(document).ready(function ($) {
  let currentStep = 1;
  let selectedGoal = null;
  let connectedProvider = null;

  // Initialize onboarding
  initOnboarding();

  function initOnboarding() {
    updateProgressBar();
    bindEvents();
  }

  function bindEvents() {

    // Help toggle functionality
    $('.abcc-help-toggle').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const provider = $button.data('provider');
        const $content = $(`.abcc-help-content[data-provider="${provider}"]`);
        const $arrow = $button.find('.abcc-help-arrow');
        
        // Toggle visibility
        if ($content.is(':visible')) {
            $content.slideUp(200);
            $arrow.text('▼');
            $button.removeClass('active');
        } else {
            // Close other open help sections
            $('.abcc-help-content').slideUp(200);
            $('.abcc-help-toggle .abcc-help-arrow').text('▼');
            $('.abcc-help-toggle').removeClass('active');
            
            // Open this one
            $content.slideDown(200);
            $arrow.text('▲');
            $button.addClass('active');
        }
    });
    
    // Goal selection
    $(".abcc-goal-card").on("click", selectGoal);

    // Step navigation
    $("#abcc-next-step-1").on("click", () => nextStep(1));
    $("#abcc-next-step-2").on("click", () => nextStep(2));
    $("#abcc-prev-step-2").on("click", () => prevStep(2));
    $("#abcc-prev-step-3").on("click", () => prevStep(3));

    // API testing
    $(".abcc-test-api").on("click", testApiConnection);

    // First post generation
    $("#abcc-generate-first-post").on("click", generateFirstPost);

    // Skip onboarding
    $("#abcc-skip-onboarding").on("click", skipOnboarding);

    // Provider selection (visual feedback)
    $(".abcc-api-provider").on("click", function () {
      $(".abcc-api-provider").removeClass("selected");
      $(this).addClass("selected");
    });

    // Auto-test when API key is pasted/typed
    $('input[id$="-api-key"]').on("paste input", function () {
      const $input = $(this);
      const provider = $input.attr("id").replace("-api-key", "");

      // Clear previous status
      $(`.${provider}-status`).removeClass("success error").empty();

      // Auto-test after short delay
      clearTimeout($input.data("autotest-timeout"));
      $input.data(
        "autotest-timeout",
        setTimeout(() => {
          if ($input.val().length > 10) {
            // Reasonable minimum length
            $(`.abcc-test-api[data-provider="${provider}"]`).trigger("click");
          }
        }, 1000)
      );
    });
  }

  function selectGoal() {
    const $card = $(this);
    const goal = $card.data("goal");

    // Visual feedback
    $(".abcc-goal-card").removeClass("selected");
    $card.addClass("selected");

    selectedGoal = goal;
    $("#abcc-next-step-1").prop("disabled", false);

    // Save goal via AJAX
    $.ajax({
      url: abccOnboarding.ajaxurl,
      type: "POST",
      data: {
        action: "abcc_onboarding_goal",
        goal: goal,
        nonce: abccOnboarding.nonce,
      },
      success: function (response) {
        if (response.success) {
          console.log("Goal configured:", goal);
          // Add success animation
          $card.addClass("goal-saved");
          setTimeout(() => $card.removeClass("goal-saved"), 1000);
        }
      },
      error: function () {
        console.error("Failed to save goal");
      },
    });
  }

function testApiConnection() {
  const $button = $(this);
  const provider = $button.data("provider");
  const isWpConfig = $button.data("wp-config") === true;
  const $input = $(`#${provider}-api-key`);
  const $status = $(`.${provider}-status`);
  
  // Get API key - either from input or indicate it's from wp-config
  let apiKey = "";
  if (isWpConfig) {
    apiKey = "wp-config"; // Flag to indicate wp-config usage
  } else {
    apiKey = $input.val() ? $input.val().trim() : "";
    if (!apiKey) {
      showError($status, "Please enter an API key");
      return;
    }
  }

  // Update UI
  $button.prop("disabled", true).text(abccOnboarding.i18n.testing);
  $status.removeClass("success error").html(
    '<span class="abcc-spinner"></span> Testing...'
  );

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
        $("#abcc-next-step-2").prop("disabled", false);

        // Add visual feedback to provider card
        $(`.abcc-api-provider[data-provider="${provider}"]`).addClass(
          "connected"
        );
      } else {
        showError(
          $status,
          response.data.message || abccOnboarding.i18n.error
        );
      }
    },
    error: function () {
      showError($status, abccOnboarding.i18n.error);
    },
    complete: function () {
      $button.prop("disabled", false);
      if (isWpConfig) {
        $button.text("Test Connection");
      } else {
        $button.text("Test");
      }
    },
  });
}

  function generateFirstPost() {
    const $button = $("#abcc-generate-first-post");
    const $status = $("#abcc-generation-status");
    const $text = $("#abcc-generation-text");

    // Show loading state
    $button.prop("disabled", true);
    $status.show();
    $text.text(abccOnboarding.i18n.generating);

    // Simulate progress updates
    const progressMessages = [
      "Connecting to AI service...",
      "Generating content...",
      "Creating SEO metadata...",
      "Generating featured image...",
      "Finalizing post...",
    ];

    let messageIndex = 0;
    const progressInterval = setInterval(() => {
      if (messageIndex < progressMessages.length) {
        $text.text(progressMessages[messageIndex]);
        messageIndex++;
      }
    }, 2000);

    $.ajax({
      url: abccOnboarding.ajaxurl,
      type: "POST",
      data: {
        action: "abcc_onboarding_first_post",
        nonce: abccOnboarding.nonce,
      },
      timeout: 120000, // 2 minute timeout
      success: function (response) {
        clearInterval(progressInterval);

        if (response.success) {
          $text.text("Post created successfully! 🎉");
          setTimeout(() => {
            showSuccessStep(response.data.edit_url);
          }, 1500);
        } else {
          $text.text(
            "Error: " + (response.data.message || "Failed to create post")
          );
          $button.prop("disabled", false);
          setTimeout(() => $status.hide(), 3000);
        }
      },
      error: function (xhr, status, error) {
        clearInterval(progressInterval);
        let errorMsg = "Network error occurred";
        if (status === "timeout") {
          errorMsg = "Request timed out. Please try again.";
        }
        $text.text("Error: " + errorMsg);
        $button.prop("disabled", false);
        setTimeout(() => $status.hide(), 3000);
      },
    });
  }

  function showSuccessStep(editUrl) {
    // Hide all steps and show success
    $(".abcc-onboarding-step").removeClass("active").hide();
    $(".abcc-step-success").show();

    // Update progress to 100%
    $(".abcc-progress-fill").css("width", "100%");
    $(".abcc-step").removeClass("active").addClass("completed");

    // Set up the edit post link
    $("#abcc-view-first-post").attr("href", editUrl);

    // Add celebration animation
    setTimeout(() => {
      $(".abcc-success-content").addClass("celebrate");
    }, 500);
  }

  function nextStep(step) {
    if (step === 1 && !selectedGoal) {
      alert("Please select a goal first");
      return;
    }
    if (step === 2 && !connectedProvider) {
      alert("Please connect an AI provider first");
      return;
    }

    currentStep = step + 1;
    updateStepDisplay();
    updateProgressBar();
  }

  function prevStep(step) {
    currentStep = step - 1;
    updateStepDisplay();
    updateProgressBar();
  }

  function updateStepDisplay() {
    $(".abcc-onboarding-step").removeClass("active");
    $(`.abcc-step-${currentStep}`).addClass("active");
  }

  function updateProgressBar() {
    const progress = ((currentStep - 1) / 2) * 100;
    $(".abcc-progress-fill").css("width", progress + "%");

    // Update step indicators
    $(".abcc-step").each(function () {
      const stepNum = parseInt($(this).data("step"));
      if (stepNum < currentStep) {
        $(this).removeClass("active").addClass("completed");
      } else if (stepNum === currentStep) {
        $(this).addClass("active").removeClass("completed");
      } else {
        $(this).removeClass("active completed");
      }
    });
  }

  function showSuccess($element, message) {
    $element
      .removeClass("error")
      .addClass("success")
      .html('<span class="dashicons dashicons-yes-alt"></span> ' + message);
  }

  function showError($element, message) {
    $element
      .removeClass("success")
      .addClass("error")
      .html('<span class="dashicons dashicons-warning"></span> ' + message);
  }

  function skipOnboarding() {
    if (
      !confirm(
        "Are you sure you want to skip the setup? You can always configure WP-AutoInsight later in the settings."
      )
    ) {
      return;
    }

    $.ajax({
      url: abccOnboarding.ajaxurl,
      type: "POST",
      data: {
        action: "abcc_onboarding_skip",
        nonce: abccOnboarding.nonce,
      },
      success: function (response) {
        if (response.success) {
          window.location.href = "?page=automated-blog-content-creator-post";
        }
      },
    });
  }

  // Keyboard navigation
  $(document).on("keydown", function (e) {
    if (e.key === "Enter" && currentStep <= 3) {
      const $nextButton = $(`#abcc-next-step-${currentStep}`);
      if ($nextButton.length && !$nextButton.prop("disabled")) {
        $nextButton.click();
      }
    }
  });

  // Auto-resize API key inputs based on content
  $('input[id$="-api-key"]').on("input", function () {
    const value = $(this).val();
    if (value.length > 20) {
      $(this).addClass("long-key");
    } else {
      $(this).removeClass("long-key");
    }
  });

  // Add helpful tooltips
  $(".abcc-goal-card").hover(
    function () {
      $(this).find(".abcc-goal-features").slideDown(200);
    },
    function () {
      $(this).find(".abcc-goal-features").slideUp(200);
    }
  );

  // Smooth scrolling for step transitions
  function smoothScrollToTop() {
    $(".abcc-onboarding-container").animate({ scrollTop: 0 }, 300);
  }

  // Add smooth scroll to step transitions
  const originalNextStep = nextStep;
  const originalPrevStep = prevStep;

  nextStep = function (step) {
    originalNextStep(step);
    smoothScrollToTop();
  };

  prevStep = function (step) {
    originalPrevStep(step);
    smoothScrollToTop();
  };

  // Handle page visibility changes (pause timers when tab not active)
  let isPageVisible = true;
  document.addEventListener("visibilitychange", function () {
    isPageVisible = !document.hidden;
  });

  // Copy API key button (if they want to save it)
  $(".abcc-api-input").each(function () {
    const $container = $(this);
    const $input = $container.find("input");

    // Add copy button after successful test
    $container.on("success", function () {
      if (!$container.find(".abcc-copy-key").length) {
        const $copyBtn = $(
          '<button type="button" class="button button-small abcc-copy-key" title="Copy API key">📋</button>'
        );
        $container.append($copyBtn);

        $copyBtn.on("click", function () {
          $input.select();
          document.execCommand("copy");
          $copyBtn.text("✓").prop("disabled", true);
          setTimeout(() => {
            $copyBtn.text("📋").prop("disabled", false);
          }, 2000);
        });
      }
    });
  });

  // Add success trigger for copy button
  const originalShowSuccess = showSuccess;
  showSuccess = function ($element, message) {
    originalShowSuccess($element, message);
    $element.closest(".abcc-api-input").trigger("success");
  };

  // Prevent accidental page reload during onboarding
  window.addEventListener("beforeunload", function (e) {
    if (currentStep > 1 && currentStep <= 3) {
      e.preventDefault();
      e.returnValue =
        "Are you sure you want to leave? Your onboarding progress will be lost.";
      return e.returnValue;
    }
  });

  // Remove the beforeunload listener when onboarding is complete
  function removeUnloadListener() {
    window.removeEventListener("beforeunload", arguments.callee);
  }

  // Call this when showing success step
  const originalShowSuccessStep = showSuccessStep;
  showSuccessStep = function (editUrl) {
    originalShowSuccessStep(editUrl);
    removeUnloadListener();
  };
});