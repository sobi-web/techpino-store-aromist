/* سوالات متداول محصول تکپینو — product screen and settings page. */
( function ( $ ) {
	'use strict';

	var t = window.tpfqAdmin || {};

	function fa( n ) {
		return Number( n ).toLocaleString( 'fa-IR' );
	}

	function fmt( text ) {
		var args = arguments;
		var i = 0;
		return String( text || '' ).replace( /%(\d)?\$?s/g, function ( m, pos ) {
			return pos ? args[ +pos ] : args[ ++i ];
		} );
	}

	/* ---------- sortable question rows ---------- */

	function repeater( box, onChange ) {
		var list = box.querySelector( '.tpfq-rep__list' );
		var tpl = box.querySelector( '.tpfq-rep__tpl' );

		function renumber() {
			var rows = list.querySelectorAll( '.tpfq-rep__row' );
			rows.forEach( function ( row, i ) {
				row.querySelector( '.tpfq-rep__num' ).textContent = fa( i + 1 );
			} );
			box.classList.toggle( 'is-empty', ! rows.length );
			onChange();
		}

		box.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-tpfq-add]' ) ) {
				var row = tpl.content.firstElementChild.cloneNode( true );
				list.appendChild( row );
				renumber();
				row.querySelector( 'input' ).focus();
				return;
			}
			var remove = e.target.closest( '[data-tpfq-remove]' );
			if ( remove ) {
				var current = remove.closest( '.tpfq-rep__row' );
				var filled = current.querySelector( 'input' ).value.trim() || current.querySelector( 'textarea' ).value.trim();
				if ( ! filled || window.confirm( t.confirm || '?' ) ) {
					current.remove();
					renumber();
				}
			}
		} );

		list.addEventListener( 'keydown', function ( e ) {
			// Enter in a question jumps to its answer instead of submitting the form.
			if ( 'Enter' === e.key && e.target.matches( 'input' ) ) {
				e.preventDefault();
				e.target.closest( '.tpfq-rep__row' ).querySelector( 'textarea' ).focus();
			}
		} );
		list.addEventListener( 'input', onChange );

		$( list ).sortable( {
			handle: '.tpfq-rep__handle',
			axis: 'y',
			placeholder: 'tpfq-rep__ph',
			forcePlaceholderSize: true,
			update: renumber,
		} );

		renumber();

		return function filled() {
			return Array.prototype.filter.call( list.querySelectorAll( '.tpfq-rep__row input' ), function ( input ) {
				return input.value.trim();
			} ).length;
		};
	}

	/* ---------- product screen: which questions this product shows ---------- */

	var product = document.querySelector( '.tpfq-mb' );
	if ( product ) {
		var select = product.querySelector( '#tpfq-mode' );
		var status = product.querySelector( '.tpfq-mb__status' );
		var defaults = +product.getAttribute( 'data-defaults' ) || 0;
		var scope = '1' === product.getAttribute( 'data-scope' );
		var own = function () {
			return 0;
		};

		var paint = function () {
			var mode = select.value;
			var n = own();
			var source = '';
			if ( 'custom' === mode ) {
				source = n ? 'custom' : '';
			} else if ( 'default' === mode ) {
				source = defaults ? 'default' : '';
			} else if ( 'both' === mode ) {
				source = n && defaults ? 'both' : n ? 'custom' : defaults ? 'default' : '';
			} else if ( 'none' !== mode ) {
				source = n ? 'custom' : defaults && scope ? 'default' : '';
			}
			product.setAttribute( 'data-mode', mode );
			status.setAttribute( 'data-source', source || 'none' );
			if ( 'custom' === source ) {
				status.textContent = fmt( t.custom, fa( n ) );
			} else if ( 'default' === source ) {
				status.textContent = fmt( t.default, fa( defaults ) );
			} else if ( 'both' === source ) {
				status.textContent = fmt( t.both, fa( n ), fa( defaults ) );
			} else {
				status.textContent = t.none || '';
			}
		};

		var rep = product.querySelector( '.tpfq-rep' );
		if ( rep ) {
			own = repeater( rep, function () {
				paint();
			} );
		}
		select.addEventListener( 'change', paint );
		paint();
	}

	/* ---------- settings page ---------- */

	var settings = document.querySelector( '.tpfq-settings' );
	if ( settings ) {
		settings.querySelectorAll( '.tpfq-rep' ).forEach( function ( box ) {
			repeater( box, function () {} );
		} );
		var cats = settings.querySelector( '[data-tpfq-cats]' );
		var radios = settings.querySelectorAll( 'input[name$="[scope]"]' );
		var scopeChange = function () {
			var picked = settings.querySelector( 'input[name$="[scope]"]:checked' );
			if ( cats ) {
				cats.classList.toggle( 'is-off', ! picked || 'cats' !== picked.value );
			}
		};
		radios.forEach( function ( r ) {
			r.addEventListener( 'change', scopeChange );
		} );
		scopeChange();
	}
} )( jQuery );
