/* global jQuery, adsCart */
/**
 * Shared AJAX add-to-cart for sets. On success fires WooCommerce's `added_to_cart` so the
 * site's mini-cart (Techpino) and WooCommerce's cart fragments update and open as usual.
 */
( function ( $ ) {
	'use strict';

	/**
	 * Height of the site's own fixed bars at the bottom of the screen (e.g. a mobile navigation
	 * bar), so the set's bottom bar / sticky bar sits above them: --ads-bottom-inset on <html>.
	 */
	window.adsMeasureBottomInset = function () {
		var vh = window.innerHeight;
		var vw = window.innerWidth;
		var inset = 0;
		var floaters = [];
		if ( vw < 992 ) {
			var nodes = document.body.getElementsByTagName( '*' );
			for ( var i = 0; i < nodes.length; i++ ) {
				var el = nodes[ i ];
				if ( el.closest( '.ads-wrap' ) ) {
					continue;
				}
				var style = window.getComputedStyle( el );
				if ( 'fixed' !== style.position || 'none' === style.display || 'hidden' === style.visibility ) {
					continue;
				}
				var r = el.getBoundingClientRect();
				if ( r.height < 1 || r.width < 1 ) {
					continue;
				}
				if ( r.width <= 160 && r.height <= 160 && r.bottom > vh * 0.5 ) {
					var center = r.left + r.width / 2;
					if ( center > vw / 3 && center < vw * 2 / 3 && r.bottom > vh - 140 ) {
						inset = Math.max( inset, Math.ceil( vh - r.top ) ); // Raised middle button of a bottom navigation.
					} else {
						floaters.push( el ); // Small floating buttons on a side (chat, back to top…).
					}
					continue;
				}
				if ( r.height > vh * 0.4 || r.width < vw * 0.6 || Math.abs( r.bottom - vh ) > 2 ) {
					continue;
				}
				var top = r.top;
				// Parts sticking out above the bar (a raised cart button of a bottom navigation).
				var kids = el.getElementsByTagName( '*' );
				for ( var k = 0; k < kids.length; k++ ) {
					var kr = kids[ k ].getBoundingClientRect();
					if ( kr.width > 0 && kr.height > 0 && kr.top < top && kr.top > vh * 0.6 ) {
						top = kr.top;
					}
				}
				inset = Math.max( inset, Math.ceil( vh - top ) );
			}
		}
		document.documentElement.style.setProperty( '--ads-bottom-inset', inset + 'px' );
		window.adsFloaters = floaters;
		document.querySelectorAll( '[data-ads-dodge]' ).forEach( window.adsDodge );
	};

	/**
	 * Keep a fixed bar clear of small floating buttons that sit over it (e.g. a chat bubble).
	 *
	 * @param {Element} bar Bar.
	 */
	window.adsDodge = function ( bar ) {
		bar.style.left = '';
		bar.style.right = '';
		if ( window.innerWidth >= 992 || ! window.adsFloaters ) {
			return;
		}
		var b = bar.getBoundingClientRect();
		var vw = window.innerWidth;
		window.adsFloaters.forEach( function ( el ) {
			var f = el.getBoundingClientRect();
			if ( f.bottom < b.top || f.top > b.bottom || f.right < b.left || f.left > b.right ) {
				return;
			}
			if ( f.left + f.width / 2 > vw / 2 ) {
				bar.style.right = Math.ceil( vw - f.left + 8 ) + 'px';
			} else {
				bar.style.left = Math.ceil( f.right + 8 ) + 'px';
			}
		} );
		bar.classList.toggle( 'is-narrow', bar.getBoundingClientRect().width < 340 );
	};

	window.adsWatchBottomInset = function () {
		var timer;
		var run = function () {
			clearTimeout( timer );
			timer = setTimeout( window.adsMeasureBottomInset, 120 );
		};
		window.adsMeasureBottomInset();
		window.addEventListener( 'resize', run );
		window.addEventListener( 'load', run );
		setTimeout( run, 1500 ); // Late widgets (chat, cookie bars).
	};

	/**
	 * Small images fly from the page to the header's cart icon (the Techpino mini-cart trigger the
	 * visitor can see), one after another. Skipped when the visitor prefers reduced motion.
	 *
	 * @param {string[]} sources Image URLs.
	 * @param {Element}  from    Element the images start from.
	 * @return {Promise} Resolves when the last image arrived.
	 */
	window.adsFlyToCart = function ( sources, from ) {
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		if ( reduce || ! from || ! document.body.animate ) {
			return Promise.resolve();
		}
		var target = null;
		document.querySelectorAll( '[data-tpmc-open], .tpmc-trigger, .elementor-menu-cart__toggle_button' ).forEach( function ( el ) {
			var r = el.getBoundingClientRect();
			if ( ! target && el.offsetParent && r.width > 0 && r.bottom > 0 && r.top < window.innerHeight ) {
				target = el;
			}
		} );
		var start = from.getBoundingClientRect();
		var end = target ? target.getBoundingClientRect() : { left: 24, top: 16, width: 24, height: 24 };
		var runs = ( sources || [] ).slice( 0, 4 ).map( function ( src, i ) {
			var img = document.createElement( 'img' );
			img.src = src;
			img.alt = '';
			img.className = 'ads-fly';
			img.style.left = ( start.left + start.width / 2 - 28 + ( i - 1.5 ) * 22 ) + 'px';
			img.style.top = ( start.top + start.height / 2 - 28 ) + 'px';
			document.body.appendChild( img );
			var dx = end.left + end.width / 2 - ( parseFloat( img.style.left ) + 28 );
			var dy = end.top + end.height / 2 - ( parseFloat( img.style.top ) + 28 );
			var anim = img.animate(
				[
					{ transform: 'translate(0,0) scale(1)', opacity: 1 },
					{ transform: 'translate(' + dx * 0.5 + 'px,' + ( dy * 0.5 - 60 ) + 'px) scale(.7)', opacity: 1, offset: 0.55 },
					{ transform: 'translate(' + dx + 'px,' + dy + 'px) scale(.2)', opacity: 0.2 }
				],
				{ duration: 750, delay: i * 90, easing: 'cubic-bezier(.4,0,.2,1)', fill: 'forwards' }
			);
			return new Promise( function ( resolve ) {
				anim.onfinish = function () {
					img.remove();
					resolve();
				};
				anim.oncancel = anim.onfinish;
			} );
		} );
		return Promise.all( runs ).then( function () {
			if ( target ) {
				target.animate( [ { transform: 'scale(1)' }, { transform: 'scale(1.18)' }, { transform: 'scale(1)' } ], { duration: 320 } );
			}
		} );
	};

	/**
	 * POST form data (arrays as key[]) and read the JSON answer.
	 *
	 * @param {string} url  URL.
	 * @param {Object} data Data.
	 * @return {Promise} JSON.
	 */
	window.adsPost = function ( url, data ) {
		var body = new URLSearchParams();
		Object.keys( data ).forEach( function ( key ) {
			var value = data[ key ];
			if ( Array.isArray( value ) ) {
				value.forEach( function ( v ) {
					body.append( key + '[]', v );
				} );
			} else if ( null !== value && undefined !== value ) {
				body.append( key, value );
			}
		} );
		return fetch( url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json();
		} );
	};

	/**
	 * Add a set to the cart.
	 *
	 * @param {Object}  data   product_id, quantity, components[].
	 * @param {Element} button Clicked button.
	 * @param {Object}  fly    Optional { images: [], from: Element }: images fly to the cart icon
	 *                         before the mini-cart opens.
	 * @return {Promise} Response.
	 */
	window.adsAddToCart = function ( data, button, fly ) {
		return window.adsPost( adsCart.ajax, data )
			.then( function ( res ) {
				if ( ! res || ! res.success ) {
					throw new Error( ( res && res.message ) || adsCart.error );
				}
				var flight = fly && fly.images ? window.adsFlyToCart( fly.images, fly.from || button ) : Promise.resolve();
				return flight.then( function () {
					// Empty button set: WooCommerce would otherwise squeeze its own «View cart» link next to
					// our button; the site's mini-cart opens instead.
					$( document.body ).trigger( 'added_to_cart', [ res.fragments, res.cart_hash, $() ] );
					return res;
				} );
			}, function () {
				throw new Error( adsCart.error );
			} );
	};
} )( jQuery );
