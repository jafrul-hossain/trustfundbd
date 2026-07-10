/* global jQuery, CHP, wp */
( function ( $ ) {
	'use strict';

	function withSpinner( $btn, fn ) {
		var original = $btn.text();
		$btn.prop( 'disabled', true ).text( CHP.i18n.scanning );
		fn( function () {
			$btn.prop( 'disabled', false ).text( original );
		} );
	}

	$( function () {

		/* Run Scan */
		$( document ).on( 'click', '#chp-run-scan', function () {
			var $btn = $( this );
			withSpinner( $btn, function ( done ) {
				$.post( CHP.ajaxUrl, {
					action: 'chp_run_scan',
					nonce: CHP.nonce
				} ).done( function ( response ) {
					if ( ! response.success ) {
						return;
					}
					$( '#chp-health-score' ).text( response.data.score + '%' );
					$( '#chp-checklist-count' ).text( response.data.passed + ' / ' + response.data.total );
					$( '#chp-scan-result' ).html( response.data.html );
				} ).always( done );
			} );
		} );

		/* Generate Client Mode */
		$( document ).on( 'click', '#chp-generate-client-mode', function () {
			var $btn = $( this );
			withSpinner( $btn, function ( done ) {
				$.post( CHP.ajaxUrl, {
					action: 'chp_generate_client_mode',
					nonce: CHP.nonce
				} ).done( function ( response ) {
					if ( response.success ) {
						window.location.href = response.data.url;
					}
				} ).always( done );
			} );
		} );

		/* Media picker (logos, favicons, PDFs) */
		$( document ).on( 'click', '.chp-media-select', function ( e ) {
			e.preventDefault();
			var $button = $( this );
			var $input  = $button.siblings( '.chp-media-url' );
			var frame = wp.media( { multiple: false } );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				$input.val( attachment.url );
			} );
			frame.open();
		} );

		/* Site Cleanup */
		$( document ).on( 'click', '#chp-run-cleanup', function () {
			var $btn = $( this );
			var tasks = $( '#chp-cleanup-form input[name="tasks[]"]:checked' ).map( function () {
				return this.value;
			} ).get();

			if ( ! tasks.length ) {
				return;
			}
			if ( ! window.confirm( CHP.i18n.confirm ) ) {
				return;
			}

			withSpinner( $btn, function ( done ) {
				$.post( CHP.ajaxUrl, {
					action: 'chp_cleanup_run',
					nonce: CHP.nonce,
					tasks: tasks
				} ).done( function ( response ) {
					if ( response.success ) {
						var lines = [];
						$.each( response.data.results, function ( task, removed ) {
							lines.push( task + ': ' + removed );
						} );
						$( '#chp-cleanup-result' ).html( '<p>' + CHP.i18n.done + ' — ' + lines.join( ', ' ) + '</p>' );
					}
				} ).always( done );
			} );
		} );

		/* Plugin Cleanup delete */
		$( document ).on( 'click', '.chp-plugin-delete', function () {
			var $btn = $( this );
			var plugin = $btn.data( 'plugin' );
			if ( ! window.confirm( CHP.i18n.confirm ) ) {
				return;
			}
			withSpinner( $btn, function ( done ) {
				$.post( CHP.ajaxUrl, {
					action: 'chp_plugin_delete',
					nonce: CHP.nonce,
					plugin: plugin
				} ).done( function ( response ) {
					if ( response.success ) {
						$btn.closest( 'tr' ).fadeOut( 200, function () {
							$( this ).remove();
						} );
					} else {
						$( '#chp-plugin-cleanup-result' ).html( '<p>' + response.data.message + '</p>' );
					}
				} ).always( done );
			} );
		} );

		/* Test email */
		$( document ).on( 'click', '#chp-send-test-email', function () {
			var $btn = $( this );
			withSpinner( $btn, function ( done ) {
				$.post( CHP.ajaxUrl, {
					action: 'chp_send_test_email',
					nonce: CHP.nonce
				} ).done( function ( response ) {
					var message = response.data && response.data.message ? response.data.message : '';
					$( '#chp-test-email-result' ).html( '<p>' + message + '</p>' );
				} ).always( done );
			} );
		} );

	} );
} )( jQuery );
