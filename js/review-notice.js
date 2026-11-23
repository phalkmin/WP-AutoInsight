/**
 * WordPress.org review request notice: one AJAX call per click, then the notice is removed.
 */
/* global jQuery, ajaxurl */
(function ($) {
  'use strict';

  $(document).on('click', '.abcc-review-notice .abcc-review-notice-action', function () {
    var $notice = $(this).closest('.abcc-review-notice');

    if ($notice.data('abccBusy')) {
      return;
    }
    $notice.data('abccBusy', true);
    $notice.find('button').prop('disabled', true);

    $.post(ajaxurl, {
      action: 'abcc_review_notice_action',
      nonce: $notice.data('nonce'),
      action_type: $(this).data('action-type')
    }).done(function (response) {
      if (!response || !response.success) {
        $notice.data('abccBusy', false);
        $notice.find('button').prop('disabled', false);
        return;
      }
      $notice.slideUp(150, function () {
        $notice.remove();
      });
    }).fail(function () {
      // The choice was not saved; leave the notice so the user can retry.
      $notice.data('abccBusy', false);
      $notice.find('button').prop('disabled', false);
    });
  });
})(jQuery);
