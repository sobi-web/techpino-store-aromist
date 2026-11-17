/**
 * Aromist Deals — every page.
 *  - one clock that keeps all countdowns and progress bars current (window.ARD)
 *  - product cards (the site's size circles): open on the discounted size
 *  - product page: «٪» tags on discounted sizes, countdown under the price
 */
( function () {
	'use strict';

	var C = window.ardConfig || {};
	var FA = '۰۱۲۳۴۵۶۷۸۹';

	function fa( s ) {
		if ( C.latin ) {
			return String( s );
		}
		return String( s ).replace( /\d/g, function ( d ) {
			return FA.charAt( +d );
		} );
	}
	function pad( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}
	/** «۰۶ : ۰۸ : ۵۰ : ۱۱» — days are left out under a day. */
	function clock( left ) {
		var d = Math.floor( left / 86400 ),
			h = Math.floor( ( left % 86400 ) / 3600 ),
			m = Math.floor( ( left % 3600 ) / 60 ),
			s = left % 60;
		return fa( ( d ? pad( d ) + ' : ' : '' ) + pad( h ) + ' : ' + pad( m ) + ' : ' + pad( s ) );
	}
	function progress( from, to, now ) {
		if ( ! to || to <= from ) {
			return 0;
		}
		var left = Math.max( 0, Math.min( 1, ( to - now ) / ( to - from ) ) );
		return 100 * ( 'elapsed' === C.progress ? 1 - left : left );
	}

	/* ---------- One clock for every timer on the page ---------- */
	var timer = null;
	var inTick = false;
	function tick() {
		if ( inTick ) {
			return; // an `ard:ended` listener changed a timer: the next second repaints it
		}
		inTick = true;
		try {
			paint();
		} finally {
			inTick = false;
		}
	}
	function paint() {
		var now = Date.now() / 1000;
		var els = document.querySelectorAll( '[data-ard-timer]:not([hidden]), [data-ard-countdown]' );
		for ( var i = 0; i < els.length; i++ ) {
			var el = els[ i ],
				to = +el.getAttribute( 'data-to' ) || 0,
				from = +el.getAttribute( 'data-from' ) || 0;
			if ( ! to ) {
				continue;
			}
			var left = Math.max( 0, Math.floor( to - now ) );
			var c = el.querySelector( '.ard-clock' );
			if ( c ) {
				// Change the text node in place: no childList mutation for page observers every second.
				if ( c.firstChild && 3 === c.firstChild.nodeType && ! c.firstChild.nextSibling ) {
					c.firstChild.nodeValue = clock( left );
				} else {
					c.textContent = clock( left );
				}
			}
			var bar = el.querySelector( '.ard-timer__bar i' );
			if ( bar ) {
				bar.style.width = progress( from, to, now ).toFixed( 2 ) + '%';
			}
			if ( to <= now && ! el.ardEnded ) {
				el.ardEnded = true;
				el.dispatchEvent( new CustomEvent( 'ard:ended', { bubbles: true } ) );
			}
		}
	}
	function start() {
		if ( ! timer ) {
			tick();
			timer = setInterval( tick, 1000 );
		}
	}
	window.ARD = { fa: fa, clock: clock, progress: progress, tick: tick, start: start, config: C };

	/* ---------- Product cards: open on the discounted size ---------- */
	function pctOf( dot ) {
		var m = /data-ard-pct="(\d+)"/.exec( dot.getAttribute( 'data-price' ) || '' );
		return m ? +m[ 1 ] : 0;
	}
	function cards() {
		if ( ! C.cardSelect ) {
			return;
		}
		var boxes = document.querySelectorAll( '.arv:not([data-ard-done])' );
		for ( var i = 0; i < boxes.length; i++ ) {
			var box = boxes[ i ];
			// The size-circle snippet has not filled this card's price yet; next round.
			if ( box.hasAttribute( 'data-arv-init' ) ) {
				continue;
			}
			box.setAttribute( 'data-ard-done', '' );
			var dots = [].slice.call( box.querySelectorAll( '.arv-dot[data-price]:not(.is-out)' ) ).filter( pctOf );
			if ( ! dots.length ) {
				continue;
			}
			var active = box.querySelector( '.arv-dot.is-active' );
			if ( active && pctOf( active ) ) {
				continue;
			}
			dots.sort( function ( a, b ) {
				return pctOf( b ) - pctOf( a );
			} );
			dots[ 0 ].click(); // The snippet's own handler switches the price.
		}
	}

	/* ---------- Product page ---------- */
	var ICON =
		'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="13" r="8" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M12 9v4.2l2.6 1.6M9.5 2.8h5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';

	function variationsOf( form ) {
		try {
			var v = JSON.parse( form.getAttribute( 'data-product_variations' ) || '[]' );
			return Array.isArray( v ) ? v : [];
		} catch ( e ) {
			return [];
		}
	}
	function norm( v ) {
		try {
			return decodeURIComponent( String( v ) ).toLowerCase();
		} catch ( e ) {
			return String( v ).toLowerCase();
		}
	}

	/** «۱۵٪» on every size that is discounted (best % among its variations in stock). */
	function markSizes( form, vars ) {
		var best = {};
		vars.forEach( function ( v ) {
			if ( ! v.ard || ! v.is_in_stock ) {
				return;
			}
			Object.keys( v.attributes || {} ).forEach( function ( k ) {
				var val = v.attributes[ k ];
				if ( val ) {
					var key = k + '|' + norm( val );
					best[ key ] = Math.max( best[ key ] || 0, v.ard.pct );
				}
			} );
		} );
		if ( ! Object.keys( best ).length ) {
			return;
		}
		var items = form.querySelectorAll( '[data-attribute_name] li[data-value], [data-attribute_name] [data-value]' );
		for ( var i = 0; i < items.length; i++ ) {
			var li = items[ i ],
				wrap = li.closest( '[data-attribute_name]' ),
				pct = best[ wrap.getAttribute( 'data-attribute_name' ) + '|' + norm( li.getAttribute( 'data-value' ) ) ];
			if ( ! pct || li.querySelector( '.ard-sw-tag' ) ) {
				continue;
			}
			var tag = document.createElement( 'span' );
			tag.className = 'ard-sw-tag';
			tag.setAttribute( 'aria-hidden', 'true' );
			tag.textContent = fa( pct ) + '٪';
			li.classList.add( 'ard-sw-host' );
			li.appendChild( tag );
		}
	}

	function timerBox() {
		var box = document.createElement( 'div' );
		box.className = 'ard-pdp-timer';
		box.setAttribute( 'data-ard-timer', '' );
		box.hidden = true;
		box.innerHTML =
			'<div class="ard-pdp-timer__row"><span class="ard-pdp-timer__label">' + ICON + '<span></span></span>' +
			'<b class="ard-clock" dir="ltr"></b></div><span class="ard-timer__bar" aria-hidden="true"><i></i></span>';
		box.querySelector( '.ard-pdp-timer__label span' ).textContent = ( C.i18n && C.i18n.ends ) || 'تا پایان تخفیف';
		return box;
	}
	function showTimer( box, deal ) {
		var now = Date.now() / 1000;
		if ( deal && deal.to && deal.to > now ) {
			box.setAttribute( 'data-from', deal.from || 0 );
			box.setAttribute( 'data-to', deal.to );
			box.ardEnded = false;
			box.hidden = false;
			start();
			tick();
		} else {
			box.hidden = true;
		}
	}

	function variableForm( form ) {
		var vars = variationsOf( form );
		if ( C.pdpMark ) {
			markSizes( form, vars );
			// Swatch plugins may draw their buttons after us.
			setTimeout( function () {
				markSizes( form, vars );
			}, 600 );
		}
		if ( ! C.pdpTimer ) {
			return;
		}
		var box = timerBox();
		var place = function () {
			var price = form.querySelector( '.single_variation' );
			if ( price && box.parentNode !== price.parentNode ) {
				price.parentNode.insertBefore( box, price.nextSibling );
			}
		};
		place();
		var byId = {};
		vars.forEach( function ( v ) {
			byId[ v.variation_id ] = v;
		} );
		var current = function () {
			var input = form.querySelector( 'input.variation_id' );
			var v = input && byId[ input.value ];
			place();
			showTimer( box, v && v.ard );
		};
		if ( window.jQuery ) {
			window.jQuery( form )
				.on( 'found_variation.ard', function ( e, v ) {
					place();
					showTimer( box, v && v.ard );
				} )
				.on( 'reset_data.ard hide_variation.ard', function () {
					showTimer( box, null );
				} );
		}
		setTimeout( current, 0 );
	}

	function simpleForm( form ) {
		var data = form.querySelector( '.ard-simple-deal' );
		if ( ! data || ! C.pdpTimer ) {
			return;
		}
		var box = timerBox();
		var price = form.querySelector( '.ac-simple-price' );
		if ( price ) {
			price.parentNode.insertBefore( box, price.nextSibling );
		} else {
			form.insertBefore( box, form.firstChild );
		}
		showTimer( box, { from: +data.getAttribute( 'data-from' ), to: +data.getAttribute( 'data-to' ) } );
	}

	function productPage() {
		var forms = document.querySelectorAll( 'form.variations_form, form.cart' );
		for ( var i = 0; i < forms.length; i++ ) {
			var form = forms[ i ];
			if ( form.ardDone ) {
				continue;
			}
			form.ardDone = true;
			if ( form.classList.contains( 'variations_form' ) ) {
				variableForm( form );
			} else {
				simpleForm( form );
			}
		}
	}

	/* ---------- Boot ---------- */
	function run() {
		cards();
		productPage();
		if ( document.querySelector( '[data-ard-timer]:not([hidden]), [data-ard-countdown]' ) ) {
			start();
		}
	}
	// Bind the product form before WooCommerce's own ready handler picks the first size.
	productPage();
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
	// Cards added later (AJAX filters, pagination, carousels cloning slides).
	var queued = false;
	new MutationObserver( function ( list ) {
		for ( var i = 0; i < list.length; i++ ) {
			if ( list[ i ].addedNodes.length ) {
				if ( ! queued ) {
					queued = true;
					setTimeout( function () {
						queued = false;
						run();
					}, 60 );
				}
				return;
			}
		}
	} ).observe( document.documentElement, { childList: true, subtree: true } );
} )();
