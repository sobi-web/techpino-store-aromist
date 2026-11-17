/**
 * Settings page: add / remove volume rows; keep each row's "default" radio value in step with its ml.
 */
( function () {
	'use strict';

	var body = document.querySelector( '.ads-volume-rows' );
	var template = document.getElementById( 'ads-volume-template' );
	var addButton = document.querySelector( '.ads-add-row' );
	if ( ! body || ! template || ! addButton ) {
		return;
	}
	var next = body.querySelectorAll( 'tr' ).length;

	addButton.addEventListener( 'click', function () {
		var html = template.innerHTML.replace( /__i__/g, String( next++ ) );
		body.insertAdjacentHTML( 'beforeend', html );
		var input = body.lastElementChild && body.lastElementChild.querySelector( 'input[type="number"]' );
		if ( input ) {
			input.focus();
		}
	} );

	body.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.ads-remove-row' );
		if ( button ) {
			button.closest( 'tr' ).remove();
		}
	} );

	body.addEventListener( 'input', function ( event ) {
		if ( 'number' !== event.target.type ) {
			return;
		}
		var radio = event.target.closest( 'tr' ).querySelector( 'input[type="radio"]' );
		if ( radio ) {
			radio.value = event.target.value;
		}
	} );
} )();

/**
 * «عطرهای ست اختصاصی»: search the list, check / uncheck all, live counter.
 */
( function () {
	'use strict';

	var page = document.querySelector( '.ads-perfumes-page' );
	if ( ! page ) {
		return;
	}
	var rows = Array.prototype.slice.call( page.querySelectorAll( '.ads-perfumes-table tbody tr' ) );
	var boxes = Array.prototype.slice.call( page.querySelectorAll( 'input[name="ads_in_builder[]"]' ) );
	var counter = page.querySelector( '.ads-perfumes-count' );
	var template = counter ? counter.textContent : '';

	function fa( n ) {
		return String( n ).replace( /\d/g, function ( d ) {
			return '۰۱۲۳۴۵۶۷۸۹'[ d ];
		} );
	}

	function count() {
		if ( ! counter ) {
			return;
		}
		var on = boxes.filter( function ( b ) {
			return b.checked;
		} ).length;
		// "N عطر از M انتخاب شده": replace the first number only.
		counter.textContent = template.replace( /^[۰-۹0-9٬,]+/, fa( on ) );
	}

	page.addEventListener( 'change', count );

	page.querySelectorAll( '[data-ads-check]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var on = 'all' === button.getAttribute( 'data-ads-check' );
			boxes.forEach( function ( b ) {
				var row = b.closest( 'tr' );
				if ( row.hidden ) {
					return;
				}
				if ( on && '1' !== b.getAttribute( 'data-ok' ) ) {
					return;
				}
				b.checked = on;
			} );
			count();
		} );
	} );

	var filter = page.querySelector( '[data-ads-filter]' );
	if ( filter ) {
		filter.addEventListener( 'input', function () {
			var q = filter.value.trim().toLowerCase();
			rows.forEach( function ( row ) {
				row.hidden = q && -1 === ( row.getAttribute( 'data-name' ) || '' ).toLowerCase().indexOf( q );
			} );
		} );
	}
} )();
