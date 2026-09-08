/**
 * Topic Library tab interactions.
 *
 * @package WP-AutoInsight
 * @since 4.2.0
 */

/* global jQuery, ajaxurl, abccTopics */
( function ( $ ) {
	'use strict';

	function feedback( message, isError ) {
		$( '#abcc-topic-form-feedback' )
			.text( message )
			.toggleClass( 'abcc-error', !! isError );
	}

	function post( action, data ) {
		return $.post(
			ajaxurl,
			$.extend(
				{
					action: action,
					nonce: abccTopics.nonce,
				},
				data
			)
		);
	}

	function resetForm() {
		$( '#abcc-topic-id' ).val( '' );
		$( '#abcc-topic-title' ).val( '' );
		$( '#abcc-topic-prompt' ).val( '' );
		$( '#abcc-topic-frequency' ).val( 'daily' );
		$( '#abcc-topic-status-override' ).val( '' );
		$( '#abcc-topic-provider-override' ).val( '' );
		$( '#abcc-topic-form-title' ).text( abccTopics.i18n.addTopic );
		$( '#abcc-topic-cancel' ).hide();
		feedback( '', false );
	}

	$( function () {
		var $form = $( '#abcc-topic-form' );

		if ( ! $form.length ) {
			return;
		}

		$form.on( 'submit', function ( event ) {
			event.preventDefault();

			var topicId = $( '#abcc-topic-id' ).val();
			var data = {
				title: $( '#abcc-topic-title' ).val(),
				prompt: $( '#abcc-topic-prompt' ).val(),
				frequency: $( '#abcc-topic-frequency' ).val(),
				post_status_override: $( '#abcc-topic-status-override' ).val(),
				provider_override: $( '#abcc-topic-provider-override' ).val(),
			};
			var action = 'abcc_topic_create';

			if ( topicId ) {
				action = 'abcc_topic_update';
				data.topic_id = topicId;
			}

			feedback( abccTopics.i18n.saving, false );

			post( action, data )
				.done( function ( response ) {
					if ( response.success ) {
						window.location.reload();
					} else {
						feedback( ( response.data && response.data.message ) || abccTopics.i18n.error, true );
					}
				} )
				.fail( function () {
					feedback( abccTopics.i18n.networkError, true );
				} );
		} );

		$( '#abcc-topic-cancel' ).on( 'click', resetForm );

		$( document ).on( 'click', '.abcc-topic-edit', function ( event ) {
			event.preventDefault();

			var topic = $( this ).data( 'topic' );

			$( '#abcc-topic-id' ).val( topic.id );
			$( '#abcc-topic-title' ).val( topic.title );
			$( '#abcc-topic-prompt' ).val( topic.prompt );
			$( '#abcc-topic-frequency' ).val( topic.frequency );
			$( '#abcc-topic-status-override' ).val( topic.post_status_override );
			$( '#abcc-topic-provider-override' ).val( topic.provider_override );
			$( '#abcc-topic-form-title' ).text( abccTopics.i18n.editTopic );
			$( '#abcc-topic-cancel' ).show();
			$( 'html, body' ).animate( { scrollTop: $( '#abcc-topic-form-wrap' ).offset().top - 50 }, 200 );
		} );

		$( document ).on( 'click', '.abcc-topic-toggle', function ( event ) {
			event.preventDefault();

			post( 'abcc_topic_toggle', { topic_id: $( this ).data( 'topic-id' ) } )
				.done( function () {
					window.location.reload();
				} )
				.fail( function () {
					window.alert( abccTopics.i18n.error );
				} );
		} );

		$( document ).on( 'click', '.abcc-topic-run-now', function ( event ) {
			event.preventDefault();

			var $link = $( this );

			$link.text( abccTopics.i18n.queuing );

			post( 'abcc_topic_run_now', { topic_id: $link.data( 'topic-id' ) } )
				.done( function ( response ) {
					$link.text( response.success ? abccTopics.i18n.queued : abccTopics.i18n.error );
				} )
				.fail( function () {
					$link.text( abccTopics.i18n.error );
				} );
		} );

		$( document ).on( 'click', '.abcc-topic-delete', function ( event ) {
			event.preventDefault();

			if ( ! window.confirm( abccTopics.i18n.confirmDelete ) ) {
				return;
			}

			post( 'abcc_topic_delete', { topic_id: $( this ).data( 'topic-id' ) } )
				.done( function () {
					window.location.reload();
				} )
				.fail( function () {
					window.alert( abccTopics.i18n.error );
				} );
		} );
	} );
} )( jQuery );
