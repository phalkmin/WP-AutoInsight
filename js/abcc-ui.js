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
	 * @param {jQuery} $element The jQuery element to show the status in.
	 * @param {string} message  The message to display.
	 * @param {string} type     The type of status: 'loading', 'success', 'error', or 'info'.
	 */
	abcc.showStatus = function($element, message, type = 'loading') {
		let html = '';

		if ('loading' === type) {
			html += '<span class="abcc-spinner"></span> ';
		}

		html += '<span>' + message + '</span>';

		$element.html(html).removeClass('abcc-status-error abcc-status-success abcc-status-info');

		if ('error' === type) {
			$element.addClass('abcc-status-error');
		} else if ('success' === type) {
			$element.addClass('abcc-status-success');
		} else {
			$element.addClass('abcc-status-info');
		}

		$element.show();
	};

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

})(jQuery);
