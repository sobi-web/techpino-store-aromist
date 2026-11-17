/**
 * Settings page: logo and banner pickers (media library) and the trust-row icon previews.
 */
/* global ampdpAdmin, wp, jQuery */
( function ( $ ) {
	'use strict';

	/** One media frame, reused; `done` gets the chosen file. */
	var frame = null;
	var done = null;
	function pick( callback ) {
		done = callback;
		if ( ! frame ) {
			frame = wp.media( {
				title: ampdpAdmin.pick,
				button: { text: ampdpAdmin.use },
				library: { type: 'image' },
				multiple: false,
			} );
			frame.on( 'select', function () {
				done( frame.state().get( 'selection' ).first().toJSON() );
			} );
		}
		frame.open();
	}
	function sized( file, size ) {
		return file.sizes && file.sizes[ size ] ? file.sizes[ size ].url : file.url;
	}

	$( document ).on( 'click', '[data-ampdp-logo-pick]', function ( e ) {
		e.preventDefault();
		pick( function ( file ) {
			$( '[data-ampdp-logo-id]' ).val( file.id );
			$( '[data-ampdp-logo-preview]' ).css( 'background-image', 'url("' + sized( file, 'thumbnail' ) + '")' );
		} );
	} );

	$( document ).on( 'click', '[data-ampdp-logo-reset]', function ( e ) {
		e.preventDefault();
		var preview = $( '[data-ampdp-logo-preview]' );
		$( '[data-ampdp-logo-id]' ).val( 0 );
		preview.css( 'background-image', 'url("' + preview.data( 'site' ) + '")' );
	} );

	// Search banners: one image per row.
	$( document ).on( 'click', '[data-ampdp-img-pick]', function ( e ) {
		e.preventDefault();
		var row = $( this ).closest( '[data-ampdp-banner]' );
		pick( function ( file ) {
			row.find( '[data-ampdp-img-id]' ).val( file.id );
			row.find( '[data-ampdp-img-preview]' ).addClass( 'has-img' ).css( 'background-image', 'url("' + sized( file, 'medium' ) + '")' );
		} );
	} );

	$( document ).on( 'click', '[data-ampdp-img-clear]', function ( e ) {
		e.preventDefault();
		var row = $( this ).closest( '[data-ampdp-banner]' );
		row.find( '[data-ampdp-img-id]' ).val( 0 );
		row.find( '[data-ampdp-img-preview]' ).removeClass( 'has-img' ).css( 'background-image', '' );
	} );

	$( document ).on( 'change', '[data-ampdp-icon]', function () {
		$( this ).closest( 'tr' ).find( '[data-ampdp-icon-preview]' ).html( ampdpAdmin.icons[ this.value ] || '' );
	} );
} )( jQuery );
