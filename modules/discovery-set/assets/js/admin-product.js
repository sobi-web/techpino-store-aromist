/* global jQuery, adsProduct */
/**
 * Product edit screen: perfume pickers, volume rules, live preview, type-specific fields.
 */
( function ( $ ) {
	'use strict';

	var $panel = $( '#ads_set_data' );
	var timer = null;

	function isSet() {
		return 'discovery_set' === $( 'select#product-type' ).val();
	}

	/* Fields that WooCommerce shows for every type but that are calculated for sets. */
	function toggleTypeFields() {
		var set = isSet();
		$( '.stock_status_field' ).toggle( ! set );
		$( '#woocommerce-product-images' ).toggle( ! set );
		if ( set ) {
			$( '#_tax_status' ).closest( '.options_group' ).show();
		}
	}

	function initSelects() {
		$panel.find( 'select.ads-perfume-select' ).each( function () {
			var $select = $( this );
			if ( $select.data( 'select2' ) ) {
				return;
			}
			$select.selectWoo( {
				allowClear: true,
				placeholder: $select.data( 'placeholder' ),
				minimumInputLength: 0,
				dir: 'rtl',
				language: {
					inputTooShort: function () {
						return adsProduct.searchHint;
					},
					noResults: function () {
						return adsProduct.noResults;
					}
				},
				ajax: {
					url: adsProduct.ajax,
					dataType: 'json',
					delay: 250,
					data: function ( params ) {
						return { action: 'ads_search_perfumes', nonce: adsProduct.nonce, term: params.term || '' };
					},
					processResults: function ( data ) {
						var chosen = selectedIds();
						var results = ( data && data.results ) || [];
						return {
							results: results.map( function ( r ) {
								r.disabled = -1 !== chosen.indexOf( String( r.id ) ) && String( r.id ) !== String( $select.val() );
								return r;
							} )
						};
					},
					cache: true
				}
			} );
		} );
	}

	function selectedIds() {
		return $panel.find( 'select.ads-perfume-select' ).map( function () {
			return $( this ).val() || null;
		} ).get();
	}

	/* The default volume must be one of the offered volumes; fixed prices only in fixed mode. */
	function syncVolumeRules() {
		var $checks = $panel.find( '.ads-volume-check' );
		$checks.each( function () {
			var $row = $( this ).closest( 'tr' );
			var on = this.checked;
			$row.find( '.ads-default-radio' ).prop( 'disabled', ! on );
			$row.find( '.ads-fixed-input' ).prop( 'disabled', ! on );
		} );
		var $default = $panel.find( '.ads-default-radio:checked' );
		if ( ! $default.length || $default.prop( 'disabled' ) ) {
			$panel.find( '.ads-default-radio:not(:disabled)' ).first().prop( 'checked', true );
		}
		var fixed = 'fixed' === $panel.find( 'input[name="ads_price_mode"]:checked' ).val();
		$panel.find( '.ads-fixed-col' ).toggle( fixed );
	}

	function preview() {
		var ids = selectedIds();
		var $box = $panel.find( '.ads-preview' );
		if ( ids.filter( Boolean ).length < adsProduct.size ) {
			return;
		}
		var fixed = {};
		$panel.find( '.ads-fixed-input:not(:disabled)' ).each( function () {
			var ml = ( this.name.match( /\[(\d+)\]/ ) || [] )[ 1 ];
			if ( ml && this.value ) {
				fixed[ ml ] = this.value;
			}
		} );
		$box.attr( 'aria-busy', 'true' ).css( 'opacity', 0.5 );
		$.post( adsProduct.ajax, {
			action: 'ads_preview',
			nonce: adsProduct.nonce,
			components: ids,
			volumes: $panel.find( 'input[name="ads_volumes[]"]' ).filter( function () {
				return 'hidden' === this.type || this.checked;
			} ).map( function () {
				return this.value;
			} ).get(),
			mode: $panel.find( 'input[name="ads_price_mode"]:checked' ).val(),
			fixed: fixed
		} ).done( function ( res ) {
			if ( res && res.success ) {
				$box.html( res.data.html );
			}
		} ).always( function () {
			$box.removeAttr( 'aria-busy' ).css( 'opacity', '' );
		} );
	}

	function schedulePreview() {
		clearTimeout( timer );
		timer = setTimeout( preview, 400 );
	}

	$( function () {
		if ( ! $panel.length ) {
			return;
		}
		initSelects();
		syncVolumeRules();
		toggleTypeFields();

		$( document.body ).on( 'woocommerce-product-type-change', toggleTypeFields );
		$( 'select#product-type' ).on( 'change', toggleTypeFields );

		$panel.on( 'change', 'select.ads-perfume-select, .ads-volume-check, .ads-default-radio, input[name="ads_price_mode"]', function () {
			syncVolumeRules();
			schedulePreview();
		} );
		$panel.on( 'input', '.ads-fixed-input', schedulePreview );
		$panel.on( 'click', '.ads-preview-refresh', preview );

		/* "افزودن ست" menu link opens a new product with the type already chosen. */
		if ( /[?&]ads_new=1/.test( window.location.search ) && ! isSet() ) {
			$( 'select#product-type' ).val( 'discovery_set' ).trigger( 'change' );
		}
	} );
} )( jQuery );
