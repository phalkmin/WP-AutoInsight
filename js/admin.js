jQuery(document).ready(function ($) {
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

    // Create and show new notification
    const $notice = $(`
      <div class="model-selection-notice" style="
        position: fixed;
        top: 20px;
        right: 20px;
        background: #2271b1;
        color: white;
        padding: 12px 16px;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        font-size: 14px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
      ">
        <strong>Selected:</strong> ${modelName}
      </div>
    `);

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
      'input[type="submit"], button[type="submit"]'
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
        300
      );
    }
  });
});
