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

	function abccBlockEditorReady() {
		return !! ( document.body.classList.contains( 'block-editor-page' ) && window.wp && wp.data && wp.blocks && wp.data.select( 'core/block-editor' ) && wp.data.dispatch( 'core/block-editor' ) );
	}

	function abccClassicEditor() {
		if ( window.tinyMCE && tinyMCE.get( 'content' ) && ! tinyMCE.get( 'content' ).isHidden() ) {
			return tinyMCE.get( 'content' );
		}
		return null;
	}

	// Reads what the user sees, including unsaved edits.
	function abccGetEditorContent() {
		if ( abccBlockEditorReady() && wp.data.select( 'core/editor' ) ) {
			return wp.data.select( 'core/editor' ).getEditedPostContent();
		}
		var mce = abccClassicEditor();
		if ( mce ) {
			return mce.getContent();
		}
		var $textarea = $( '#content' );
		return $textarea.length ? $textarea.val() : '';
	}

	// Replaces the editor content as an undoable, unsaved change.
	function abccSetEditorContent( html ) {
		if ( abccBlockEditorReady() ) {
			var editor = wp.data.dispatch( 'core/block-editor' );
			var ids    = wp.data.select( 'core/block-editor' ).getBlockOrder();
			var blocks = wp.blocks.parse( html );
			if ( ids.length ) {
				editor.replaceBlocks( ids, blocks );
			} else {
				editor.insertBlocks( blocks );
			}
			return true;
		}
		var mce = abccClassicEditor();
		if ( mce ) {
			if ( mce.undoManager ) {
				mce.undoManager.transact( function () {
					mce.setContent( html );
				} );
			} else {
				mce.setContent( html );
			}
			mce.setDirty( true );
			return true;
		}
		var $textarea = $( '#content' );
		if ( $textarea.length ) {
			$textarea.val( html );
			return true;
		}
		return false;
	}

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
				content: abccGetEditorContent(),
			},
			success: function ( response ) {
				$button.prop( 'disabled', false ).text( i18n.rewriteBtn );
				if ( ! response.success ) {
					abcc.setError( $status, ( response.data && response.data.message ) || i18n.unknownError );
					return;
				}
				if ( abccSetEditorContent( response.data.content ) ) {
					abcc.showStatus( $status, i18n.rewriteApplied, 'success' );
				} else {
					abcc.setError( $status, i18n.rewriteNoEditor );
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

				// Shared capped poller (abcc-ui.js): 1s interval, 60-try cap.
				abcc.pollJob( response.data.job_id, {
					nonce:      $button.data( 'nonce' ),
					intervalMs: 1000,
					maxTries:   60,
					stallAfter: 30,
					$status:    $status,
					onUpdate: function ( job ) {
						if ( job && job.edit_url ) {
							abcc.showStatus( $status, i18n.regenerateSuccess, 'success' );
							window.location.href = job.edit_url;
						}
					},
					onSuccess: function ( job ) {
						abcc.showStatus( $status, i18n.regenerateSuccess, 'success' );
						if ( job.edit_url ) {
							window.location.href = job.edit_url;
						}
					},
					onFailed: function ( message ) {
						abcc.setError( $status, message || i18n.unknownError );
						$button.prop( 'disabled', false ).text( i18n.regenerateBtn );
					},
					onError: function ( message ) {
						abcc.setError( $status, message || i18n.unknownError );
						$button.prop( 'disabled', false ).text( i18n.regenerateBtn );
					},
					onStall: function () {
						abcc.setError( $status, abcc.i18n( 'stillWorking' ) );
						$button.prop( 'disabled', false ).text( i18n.regenerateBtn );
					},
				} );
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
					$button.prop( 'disabled', false ).text( i18n.infographicBtn );
					var editUrl = ajaxurl.replace(
						'admin-ajax.php',
						'upload.php?item=' + parseInt( response.data.attachment_id, 10 )
					);
					var $viewLink = $( '<a>' )
						.attr( 'href', response.data.attachment_url )
						.attr( 'target', '_blank' )
						.text( i18n.view );
					var $editLink = $( '<a>' ).attr( 'href', editUrl ).text( i18n.edit );
					var $msg = $( '<span>' )
						.text( ( response.data.message || i18n.infographicSuccess ) + ' ' )
						.append( $viewLink, ' | ', $editLink );

					abcc.showHtml( $status, $msg, 'success' );
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

// ── Retry featured image (admin notice on failed generation) ───────────────
jQuery( document ).ready( function ( $ ) {
	'use strict';

	$( document ).on( 'click', '.abcc-retry-image', function () {
		var $button = $( this );
		var $notice = $button.closest( '.abcc-image-failure' );

		$button.prop( 'disabled', true );

		$.post( ajaxurl, {
			action:  'abcc_retry_featured_image',
			post_id: $button.data( 'post-id' ),
			nonce:   $button.data( 'nonce' ),
		} ).done( function ( response ) {
			if ( response.success ) {
				$notice
					.removeClass( 'notice-warning' )
					.addClass( 'notice-success' )
					.find( 'p' )
					.text( response.data.message );
			} else {
				$button.prop( 'disabled', false );
				$notice.find( 'p' ).prepend(
					$( '<strong>' ).text( ( response.data && response.data.message ? response.data.message : '' ) + ' ' )
				);
			}
		} ).fail( function () {
			$button.prop( 'disabled', false );
		} );
	} );
} );
