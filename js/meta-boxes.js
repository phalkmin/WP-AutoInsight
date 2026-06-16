/**
 * File: js/meta-boxes.js
 *
 * Post edit screen handlers for the WP-AutoInsight Tools meta box.
 * Depends on: jquery, abcc-ui-script (window.abcc), abccMetaBox (localized).
 *
 * @package WP-AutoInsight
 */

jQuery( document ).ready( function ( $ ) {
	'use strict';

	var i18n = abccMetaBox.i18n;

	// ── Rewrite with AI ───────────────────────────────────────────────────────
	$( '#abcc-rewrite-post' ).on( 'click', function () {
		var $button = $( this );
		var $status = $( '#abcc-rewrite-status' );

		if ( ! confirm( i18n.confirmRewrite ) ) {
			return;
		}

		$button.prop( 'disabled', true ).text( i18n.rewriting );
		abcc.showStatus( $status, i18n.analyzing );

		$.ajax( {
			url:  ajaxurl,
			type: 'POST',
			data: {
				action:  'abcc_rewrite_post',
				post_id: $button.data( 'post-id' ),
				nonce:   $button.data( 'nonce' ),
			},
			success: function ( response ) {
				if ( response.success ) {
					abcc.showStatus( $status, i18n.rewriteSuccess, 'success' );
					setTimeout( function () { window.location.reload(); }, 1500 );
				} else {
					abcc.setError( $status, response.data.message || i18n.unknownError );
					$button.prop( 'disabled', false ).text( i18n.rewriteBtn );
				}
			},
			error: function () {
				abcc.setError( $status, i18n.networkError );
				$button.prop( 'disabled', false ).text( i18n.rewriteBtn );
			},
		} );
	} );

	// ── Regenerate as New Draft ───────────────────────────────────────────────
	$( '#abcc-regenerate-from-meta' ).on( 'click', function () {
		var $button = $( this );
		var $status = $( '#abcc-regenerate-meta-status' );

		if ( ! confirm( i18n.confirmRegenerate ) ) {
			return;
		}

		$button.prop( 'disabled', true ).text( i18n.regenerating );
		abcc.showStatus( $status, i18n.generatingDraft );

		$.ajax( {
			url:  ajaxurl,
			type: 'POST',
			data: {
				action:  'abcc_regenerate_post',
				post_id: $button.data( 'post-id' ),
				nonce:   $button.data( 'nonce' ),
			},
			success: function ( response ) {
				if ( ! response.success ) {
					abcc.setError( $status, response.data.message || i18n.unknownError );
					$button.prop( 'disabled', false ).text( i18n.regenerateBtn );
					return;
				}

				abcc.showStatus( $status, i18n.generatingDraft || i18n.regenerateSuccess, 'info' );

				var jobId    = response.data.job_id;
				var nonce    = $button.data( 'nonce' );
				var tries    = 0;
				var maxTries = 60; // ~60s at 1s interval.

				var poll = function () {
					tries++;
					$.post( ajaxurl, {
						action:  'abcc_get_job_status',
						job_id:  jobId,
						nonce:   nonce,
					} )
						.done( function ( res ) {
							if ( ! res.success ) {
								abcc.setError( $status, ( res.data && res.data.message ) || i18n.unknownError );
								$button.prop( 'disabled', false ).text( i18n.regenerateBtn );
								return;
							}
							if ( res.success && res.data && res.data.edit_url ) {
								abcc.showStatus( $status, i18n.regenerateSuccess, 'success' );
								window.location.href = res.data.edit_url;
								return;
							}
							if ( res.success && res.data && 'failed' === res.data.status ) {
								abcc.setError( $status, ( res.data.message || i18n.unknownError ) );
								$button.prop( 'disabled', false ).text( i18n.regenerateBtn );
								return;
							}
							if ( tries >= maxTries ) {
								abcc.setError( $status, i18n.unknownError );
								$button.prop( 'disabled', false ).text( i18n.regenerateBtn );
								return;
							}
							setTimeout( poll, 1000 );
						} )
						.fail( function () {
							if ( tries >= maxTries ) {
								abcc.setError( $status, i18n.networkError );
								$button.prop( 'disabled', false ).text( i18n.regenerateBtn );
								return;
							}
							setTimeout( poll, 1000 );
						} );
				};

				setTimeout( poll, 1000 );
			},
			error: function () {
				abcc.setError( $status, i18n.networkError );
				$button.prop( 'disabled', false ).text( i18n.regenerateBtn );
			},
		} );
	} );

	// ── Create Infographic ────────────────────────────────────────────────────
	$( '#abcc-create-infographic' ).on( 'click', function () {
		var $button = $( this );
		var $status = $( '#abcc-infographic-status' );

		if ( ! confirm( i18n.confirmInfographic ) ) {
			return;
		}

		$button.prop( 'disabled', true ).text( i18n.creating );
		abcc.showStatus( $status, i18n.generatingInfographic );

		$.ajax( {
			url:  ajaxurl,
			type: 'POST',
			data: {
				action:  'abcc_create_infographic',
				post_id: $button.data( 'post-id' ),
				nonce:   $button.data( 'nonce' ),
			},
			success: function ( response ) {
				if ( response.success ) {
					var attachmentUrl = $( '<a>' ).attr( 'href', response.data.attachment_url ).prop( 'href' );
					var editUrl = ajaxurl.replace(
						'admin-ajax.php',
						'upload.php?item=' + parseInt( response.data.attachment_id, 10 )
					);
					abcc.showStatus(
						$status,
						i18n.infographicSuccess +
							' <a href="' + attachmentUrl + '" target="_blank">' + i18n.view + '</a>' +
							' | <a href="' + editUrl + '">' + i18n.edit + '</a>',
						'success'
					);
				} else {
					abcc.setError( $status, response.data.message || i18n.unknownError );
					$button.prop( 'disabled', false ).text( i18n.infographicBtn );
				}
			},
			error: function () {
				abcc.setError( $status, i18n.networkError );
				$button.prop( 'disabled', false ).text( i18n.infographicBtn );
			},
		} );
	} );
} );
