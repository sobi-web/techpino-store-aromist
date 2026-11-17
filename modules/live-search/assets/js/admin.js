/* جستجوی زنده تکپینو — پیشخوان */
( function ( $ ) {
	'use strict';

	var cfg = window.tplsAdmin || {};
	var t = cfg.i18n || {};
	var $form = $( '.tpls-admin__form' );
	if ( ! $form.length ) {
		return;
	}

	function esc( text ) {
		return $( '<div>' ).text( text == null ? '' : String( text ) ).html();
	}

	/* ---------- Tabs ---------- */

	function openTab( name ) {
		$( '.tpls-admin__tab' ).each( function () {
			var on = $( this ).data( 'tab' ) === name;
			$( this ).toggleClass( 'is-active', on ).attr( 'aria-selected', on ? 'true' : 'false' );
		} );
		$( '.tpls-admin__panel' ).each( function () {
			$( this ).toggleClass( 'is-active', $( this ).data( 'panel' ) === name );
		} );
		$( '#tpls-tab-field' ).val( name );
		try {
			var url = new URL( location.href );
			url.searchParams.set( 'tab', name );
			url.searchParams.delete( 'saved' );
			history.replaceState( null, '', url );
		} catch ( e ) {}
	}
	$( '.tpls-admin__tab' ).on( 'click', function () {
		openTab( $( this ).data( 'tab' ) );
	} );

	/* ---------- Colours ---------- */

	$( '.tpls-color' ).wpColorPicker();
	$( '#tpls-look-reset' ).on( 'click', function () {
		$( '.tpls-color' ).each( function () {
			$( this ).val( '' ).closest( '.wp-picker-container' ).find( '.wp-color-result' ).css( 'background-color', 'transparent' );
		} );
		$( '.tpls-admin__px input' ).val( '' );
	} );

	/* ---------- Banners ---------- */

	var $banners = $( '.tpls-banner-list' );
	var nextBanner = parseInt( $banners.attr( 'data-next' ), 10 ) || 0;

	$banners.sortable( { items: '.tpls-banner-row', handle: '.tpls-admin__handle', axis: 'y', tolerance: 'pointer' } );
	$( '#tpls-banner-add' ).on( 'click', function () {
		var html = $( '#tpls-banner-tpl' ).html().replace( /__i__/g, String( nextBanner++ ) );
		$banners.append( html );
	} );
	$banners.on( 'click', '.tpls-admin__remove', function () {
		$( this ).closest( '.tpls-banner-row' ).remove();
	} );

	var frame = null;
	function setImage( $media, id, url ) {
		var $box = $media.find( '.tpls-media__box' );
		$media.find( 'input[type="hidden"]' ).val( id || '' );
		$box.toggleClass( 'has-image', !! url );
		$box.find( 'img' ).attr( 'src', url || '' ).prop( 'hidden', ! url );
		$box.find( '.tpls-media__empty' ).prop( 'hidden', !! url );
		$media.find( '.tpls-media__remove' ).prop( 'hidden', ! url );
	}
	$banners.on( 'click', '.tpls-media__box', function () {
		var $media = $( this ).closest( '.tpls-media' );
		if ( ! window.wp || ! wp.media ) {
			return;
		}
		frame = wp.media( { title: t.pick || 'انتخاب تصویر', multiple: false, library: { type: 'image' } } );
		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
			setImage( $media, att.id, url );
		} );
		frame.open();
	} );
	$banners.on( 'click', '.tpls-media__remove', function () {
		setImage( $( this ).closest( '.tpls-media' ), '', '' );
	} );

	// The size guide follows the number of banners per row.
	function guide() {
		var g = cfg.guide || {};
		var cols = parseInt( $( 'input[name="tpls[banner_cols]"]:checked' ).val(), 10 ) || 1;
		var d = ( g.desktop || {} )[ cols ] || [ 900, 200 ];
		var m = g.mobile || [ 350, 140 ];
		$( '[data-guide="desktop"]' ).text( d[ 0 ] * 2 + ' × ' + d[ 1 ] * 2 );
		$( '[data-guide="desktop-note"]' ).text( 1 === cols ? 'یک بنر تمام‌عرض' : cols + ' بنر کنار هم؛ هر بنر این اندازه' );
		$( '[data-guide="mobile"]' ).text( m[ 0 ] * 2 + ' × ' + m[ 1 ] * 2 );
	}
	$( 'input[name="tpls[banner_cols]"]' ).on( 'change', guide );
	guide();

	/* ---------- Popular searches ---------- */

	var $pop = $( '.tpls-pop-list' );
	var nextPop = parseInt( $pop.attr( 'data-next' ), 10 ) || 0;
	$pop.sortable( { items: '.tpls-pop-row', handle: '.tpls-admin__handle', axis: 'y', tolerance: 'pointer' } );
	$( '#tpls-pop-add' ).on( 'click', function () {
		$pop.append( $( '#tpls-pop-tpl' ).html().replace( /__i__/g, String( nextPop++ ) ) );
		$pop.find( '.tpls-pop-row:last input:first' ).trigger( 'focus' );
	} );
	$pop.on( 'click', '.tpls-admin__remove', function () {
		$( this ).closest( '.tpls-pop-row' ).remove();
	} );
	$pop.on( 'keydown', 'input', function ( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			$( '#tpls-pop-add' ).trigger( 'click' );
		}
	} );

	/* ---------- Featured products ---------- */

	var $list = $( '#tpls-featured-list' );
	var $q = $( '#tpls-product-q' );
	var $results = $( '#tpls-product-results' );
	var timer = 0;

	function modeSwitch() {
		var mode = $( 'input[name="tpls[featured_mode]"]:checked' ).val();
		$( '[data-when-mode]' ).each( function () {
			$( this ).prop( 'hidden', $( this ).data( 'when-mode' ) !== mode );
		} );
	}
	$( 'input[name="tpls[featured_mode]"]' ).on( 'change', modeSwitch );
	modeSwitch();

	function row( p ) {
		return '<li class="tpls-featured-row" data-id="' + esc( p.id ) + '">' +
			'<span class="tpls-admin__handle" aria-hidden="true"></span>' +
			'<img src="' + esc( p.img ) + '" alt="">' +
			'<span class="tpls-featured-row__text"><b>' + esc( p.title ) + '</b><small>' + esc( p.price || '' ) + ( p.stock ? '' : ' — ناموجود' ) + '</small></span>' +
			'<input type="hidden" name="tpls[featured_ids][]" value="' + esc( p.id ) + '">' +
			'<button type="button" class="tpls-admin__remove button-link-delete" aria-label="' + esc( t.remove || 'حذف' ) + '">' + esc( t.remove || 'حذف' ) + '</button></li>';
	}
	( cfg.featured || [] ).forEach( function ( p ) {
		$list.append( row( p ) );
	} );
	$list.sortable( { items: '.tpls-featured-row', handle: '.tpls-admin__handle', axis: 'y', tolerance: 'pointer' } );
	$list.on( 'click', '.tpls-admin__remove', function () {
		$( this ).closest( '.tpls-featured-row' ).remove();
	} );

	function search() {
		var term = $.trim( $q.val() );
		if ( term.length < 2 ) {
			$results.prop( 'hidden', true ).empty();
			return;
		}
		$.getJSON( cfg.ajax, { action: 'tpls_products', nonce: cfg.nonce, q: term } ).done( function ( res ) {
			var items = res && res.success ? res.data : [];
			$results.empty();
			if ( ! items.length ) {
				$results.append( '<li class="tpls-search__none">' + esc( t.none || '' ) + '</li>' );
			}
			items.forEach( function ( p ) {
				$( '<li class="tpls-search__item" tabindex="0">' )
					.data( 'product', p )
					.html( '<img src="' + esc( p.img ) + '" alt=""><span><b>' + esc( p.title ) + '</b><small>' + esc( p.price || '' ) + '</small></span>' )
					.appendTo( $results );
			} );
			$results.prop( 'hidden', false );
		} );
	}
	$q.on( 'input', function () {
		clearTimeout( timer );
		timer = setTimeout( search, 250 );
	} );
	$q.on( 'keydown', function ( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			$results.find( '.tpls-search__item' ).first().trigger( 'click' );
		}
	} );
	function add() {
		var p = $( this ).data( 'product' );
		if ( ! p ) {
			return;
		}
		if ( $list.find( '[data-id="' + p.id + '"]' ).length ) {
			window.alert( t.already || '' );
			return;
		}
		if ( $list.children().length >= 48 ) {
			window.alert( t.max || '' );
			return;
		}
		$list.append( row( p ) );
		$q.val( '' );
		$results.prop( 'hidden', true ).empty();
	}
	$results.on( 'click', '.tpls-search__item', add );
	$results.on( 'keydown', '.tpls-search__item', function ( e ) {
		if ( 'Enter' === e.key ) {
			add.call( this );
		}
	} );
	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '.tpls-search' ).length ) {
			$results.prop( 'hidden', true );
		}
	} );
}( jQuery ) );
