/**
 * File: js/abcc-ui.js
 *
 * Shared UI components for WP-AutoInsight.
 *
 * @package WP-AutoInsight
 */

window.abcc = window.abcc || {};

(function($) {
	'use strict';

	/**
	 * Shows a status message with an optional spinner.
	 *
	 * The message is treated as TEXT. Server-supplied strings (provider errors,
	 * job labels, filenames) flow through here, so it must never parse HTML.
	 * Callers that intentionally build markup use abcc.showHtml instead.
	 *
	 * @param {jQuery} $element The jQuery element to show the status in.
	 * @param {string} message  The message to display, as plain text.
	 * @param {string} type     The type of status: 'loading', 'success', 'error', or 'info'.
	 */
	abcc.showStatus = function($element, message, type = 'loading') {
		const $wrap = $('<span>').text(message);

		$element.empty();

		if ('loading' === type) {
			$element.append($('<span>').addClass('abcc-spinner'), ' ');
		}

		$element.append($wrap);

		applyStatusClass($element, type);
		$element.show();
	};

	/**
	 * Shows a status message that intentionally contains markup.
	 *
	 * Only for markup the plugin builds itself. Never pass a server-supplied
	 * string here — use abcc.showStatus, which escapes.
	 *
	 * @param {jQuery} $element The jQuery element to show the status in.
	 * @param {string} html     Trusted HTML built by the plugin.
	 * @param {string} type     The type of status: 'loading', 'success', 'error', or 'info'.
	 */
	abcc.showHtml = function($element, html, type = 'info') {
		$element.html(html);
		applyStatusClass($element, type);
		$element.show();
	};

	/**
	 * Applies the status class for a given type, clearing the others.
	 *
	 * @param {jQuery} $element The element to class.
	 * @param {string} type     Status type.
	 */
	function applyStatusClass($element, type) {
		$element.removeClass('abcc-status-error abcc-status-success abcc-status-info');

		if ('error' === type) {
			$element.addClass('abcc-status-error');
		} else if ('success' === type) {
			$element.addClass('abcc-status-success');
		} else {
			$element.addClass('abcc-status-info');
		}
	}

	/**
	 * Clears the status message from an element.
	 *
	 * @param {jQuery} $element The jQuery element to clear.
	 */
	abcc.clearStatus = function($element) {
		$element.html('').hide();
	};

	/**
	 * Sets an error message in an element.
	 *
	 * @param {jQuery} $element The jQuery element to show the error in.
	 * @param {string} message  The error message to display.
	 */
	abcc.setError = function($element, message) {
		abcc.showStatus($element, message, 'error');
	};

	/**
	 * Reads a localized string with a safe fallback.
	 *
	 * @param {string} key Key in the localized i18n object.
	 * @return {string} The localized string, or a readable fallback.
	 */
	abcc.i18n = function(key) {
		// abccAdmin is localized on the settings pages, abccMetaBox on the
		// post editor; the raw key is the last-resort fallback.
		const admin = (window.abccAdmin && window.abccAdmin.i18n) || {};
		const metaBox = (window.abccMetaBox && window.abccMetaBox.i18n) || {};
		return admin[key] || metaBox[key] || key;
	};

	/**
	 * Polls a generation job until it resolves, with a hard cap.
	 *
	 * @param {number|string} jobId   Job ID to poll.
	 * @param {Object}        options Callbacks and configuration:
	 *                                nonce, onUpdate, onSuccess, onFailed,
	 *                                onError, onStall, $status, intervalMs,
	 *                                maxTries, stallAfter.
	 */
	abcc.pollJob = function(jobId, options) {
		const opts = options || {};
		const intervalMs = opts.intervalMs || 3000;
		const maxTries = opts.maxTries || 40;          // ~2 minutes at 3s.
		const stallAfter = opts.stallAfter || 20;      // ~1 minute.
		let tries = 0;
		let stallShown = false;

		function pollOnce() {
			tries++;

			$.post(ajaxurl, {
				action: 'abcc_get_job_status',
				nonce: opts.nonce,
				job_id: jobId
			}).done(function(response) {
				if (!response.success) {
					if (opts.onError) {
						opts.onError((response.data && response.data.message) || abcc.i18n('unknownError'));
					}
					return;
				}

				const job = response.data;

				if (opts.onUpdate) {
					opts.onUpdate(job);
				}

				if ('succeeded' === job.status) {
					if (opts.onSuccess) {
						opts.onSuccess(job);
					}
					return;
				}

				if ('queued' !== job.status && 'running' !== job.status) {
					if (opts.onFailed) {
						opts.onFailed(job.message || abcc.i18n('generationFailed'));
					}
					return;
				}

				if (tries >= maxTries) {
					if (opts.onStall) {
						opts.onStall();
					} else if (opts.onError) {
						opts.onError(abcc.i18n('stillWorking'));
					}
					return;
				}

				if (!stallShown && tries >= stallAfter && opts.$status) {
					stallShown = true;
					abcc.showStatus(opts.$status, abcc.i18n('stillWorking'), 'info');
				}

				window.setTimeout(pollOnce, intervalMs);
			}).fail(function() {
				if (tries >= maxTries) {
					if (opts.onError) {
						opts.onError(abcc.i18n('networkError'));
					}
					return;
				}

				window.setTimeout(pollOnce, intervalMs);
			});
		}

		pollOnce();
	};

})(jQuery);
