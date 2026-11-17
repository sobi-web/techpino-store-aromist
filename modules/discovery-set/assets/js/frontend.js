/**
 * Discovery set product form: live price and max quantity for the chosen volume.
 */
( function () {
	'use strict';

	function init( form ) {
		var data;
		try {
			data = JSON.parse( form.getAttribute( 'data-ads-volumes' ) || '{}' );
		} catch ( e ) {
			return;
		}

		var price = form.querySelector( '.ads-total__price' );
		var qty = form.querySelector( 'input.qty' );

		function update() {
			var checked = form.querySelector( 'input[name="ads_volume"]:checked' );
			var row = checked ? data[ checked.value ] : null;
			if ( ! row ) {
				return;
			}
			if ( price ) {
				price.innerHTML = row.price_html;
			}
			if ( qty ) {
				if ( row.max > 0 ) {
					qty.max = row.max;
					if ( parseInt( qty.value, 10 ) > row.max ) {
						qty.value = row.max;
					}
				} else {
					qty.removeAttribute( 'max' );
				}
			}
			form.dispatchEvent( new CustomEvent( 'ads:volume', { bubbles: true, detail: row } ) );
		}

		form.addEventListener( 'change', function ( event ) {
			if ( event.target && 'ads_volume' === event.target.name ) {
				update();
			}
		} );
		update();
	}

	function boot() {
		document.querySelectorAll( 'form.ads-form' ).forEach( init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
