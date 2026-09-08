/**
 * Audio transcription functionality for WP-AutoInsight
 *
 * @package WP-AutoInsight
 */
jQuery(document).ready(function ($) {
  // Handle transcribe only
  $("#abcc-transcribe-only").on("click", function (e) {
    e.preventDefault();

    const $button = $(this);
    const $status = $("#abcc-transcription-status");
    const $result = $("#abcc-transcription-result");
    const attachmentId = $button.data("id");

    // Reset UI
    $result.hide();
    abcc.showStatus($status, abccAudio.i18n.transcribing, "loading");
    $button.prop("disabled", true);
    $(".abcc-audio-create-post").prop("disabled", true);

    $.ajax({
      url: abccAudio.ajaxurl,
      type: "POST",
      data: {
        action: "abcc_transcribe_audio",
        attachment_id: attachmentId,
        create_post: false,
        nonce: abccAudio.nonce,
      },
      timeout: 300000, // 5 minutes timeout for large files
      success: function (response) {
        if (response.success) {
          abcc.showStatus($status, response.data.message, "success");

          // Show transcript and create post button
          $("#abcc-transcript-text").val(response.data.transcript);
          $result.show();
          $("#abcc-create-post-from-transcript").show();

          // Auto-resize textarea
          const textarea = document.getElementById("abcc-transcript-text");
          textarea.style.height = "auto";
          textarea.style.height = textarea.scrollHeight + "px";
        } else {
          abcc.showStatus(
            $status,
            response.data.message || abccAudio.i18n.error,
            "error"
          );
        }
      },
      error: function (xhr, status, error) {
        let errorMsg = abccAudio.i18n.error;
        if (status === "timeout") {
          errorMsg = "Request timed out. Try with a smaller file.";
        }
        abcc.showStatus($status, errorMsg, "error");
      },
      complete: function () {
        $button.prop("disabled", false);
        $(".abcc-audio-create-post").prop("disabled", false);
      },
    });
  });

  // Handle create post from transcript
  $("#abcc-create-post-from-transcript").on("click", function (e) {
    e.preventDefault();

    const $button = $(this);
    const $status = $("#abcc-transcription-status");
    const attachmentId = $("#abcc-transcribe-only").data("id");
    const transcript = $("#abcc-transcript-text").val();

    if (!transcript.trim()) {
      alert(abccAudio.i18n.noTranscript || "No transcript available");
      return;
    }

    $button.prop("disabled", true);
    abcc.showStatus($status, abccAudio.i18n.creating, "loading");

    $.ajax({
      url: abccAudio.ajaxurl,
      type: "POST",
      data: {
        action: "abcc_create_post_from_transcript",
        attachment_id: attachmentId,
        transcript: transcript,
        nonce: abccAudio.nonce,
      },
      success: function (response) {
        if (response.success) {
          var $editLink = $("<a>")
            .attr("href", response.data.edit_url)
            .attr("class", "button button-small")
            .attr("target", "_blank")
            .text("Edit Post");
          var $msg = $("<span>")
            .text(response.data.message + " ")
            .append($editLink);

          abcc.showHtml($status, $msg, "success");
          $button.hide(); // Hide since post is created
        } else {
          abcc.showStatus(
            $status,
            response.data.message || abccAudio.i18n.error,
            "error"
          );
        }
      },
      error: function () {
        abcc.showStatus($status, abccAudio.i18n.error, "error");
      },
      complete: function () {
        $button.prop("disabled", false);
      },
    });
  });

  // Handle Create Post from Audio (new mode-aware action).
  $(document).on("click", ".abcc-audio-create-post", function () {
    var $btn = $(this);
    var attachmentId = $btn.data("attachment-id");
    var mode = $("input[name='abcc_audio_mode']:checked").val() || "transcript_plus_intro";
    var $status = $btn.siblings(".abcc-audio-status").first();
    if (!$status.length) {
      $status = $("<p class='abcc-audio-status'></p>").insertAfter($btn);
    }

    $btn.prop("disabled", true);
    abcc.showStatus($status, "Transcribing and generating… this can take a minute.", "loading");

    $.ajax({
      url: abccAudio.ajaxurl,
      type: "POST",
      timeout: 300000,
      data: {
        action: "abcc_audio_generate_post",
        attachment_id: attachmentId,
        mode: mode,
        nonce: abccAudio.nonce,
      },
      success: function (response) {
        if (response.success) {
          var $editLink = $("<a>")
            .attr("href", response.data.edit_url)
            .attr("class", "button button-small")
            .attr("target", "_blank")
            .text("Edit Post");
          var $msg = $("<span>")
            .text(response.data.message + " ")
            .append($editLink);

          abcc.showHtml($status, $msg, "success");
        } else {
          abcc.showStatus($status, response.data.message, "error");
        }
        $btn.prop("disabled", false);
      },
      error: function (xhr) {
        abcc.showStatus($status, "Error: " + xhr.statusText, "error");
        $btn.prop("disabled", false);
      },
    });
  });

  // Auto-resize transcript textarea when content changes
  $("#abcc-transcript-text").on("input", function () {
    this.style.height = "auto";
    this.style.height = this.scrollHeight + "px";
  });

  // Format file size display
  function formatFileSize(bytes) {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
  }

  // Show file info if available
  const fileSize = parseInt($(".abcc-audio-create-post").data("file-size"));
  if (fileSize) {
    const maxSize = 25 * 1024 * 1024; // 25MB
    const $fileInfo = $("<span>").text("File size: " + formatFileSize(fileSize));

    if (fileSize > maxSize) {
      const $warning = $("<span>")
        .css("color", "#dc3232")
        .text("(Too large - max 25MB)");
      $fileInfo.append(" ", $warning);
      $(".abcc-audio-create-post, #abcc-transcribe-only").prop("disabled", true);
    }

    abcc.showHtml($("#abcc-transcription-status"), $fileInfo, "info");
  }
});
