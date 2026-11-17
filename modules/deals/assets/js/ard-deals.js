/**
 * Aromist Deals — the «فرصت‌های تکرار نشدنی» section: carousel, size switching, add to cart,
 * and removing deals that end while the page is open.
 */
( function () {
	'use strict';

	var ARD = window.ARD || {};
	var C = ARD.config || window.ardConfig || {};
	var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function throttle( fn ) {
		var on = false;
		return function () {
			if ( on ) {
				return;
			}
			on = true;
			( window.requestAnimationFrame || setTimeout )( function () {
				on = false;
				fn();
			} );
		};
	}

	/* ---------- Carousel (native scroll + snap; works in RTL) ---------- */
	function carousel( root ) {
		var track = root.querySelector( '.ard-track' );
		if ( ! track || ! root.classList.contains( 'ard--carousel' ) ) {
			return function () {};
		}
		var prev = root.querySelector( '[data-ard-prev]' ),
			next = root.querySelector( '[data-ard-next]' ),
			dots = root.querySelector( '.ard-dots' ),
			rtl = 'rtl' === getComputedStyle( track ).direction;

		function step() {
			var s = track.children;
			return s.length > 1 ? Math.abs( s[ 1 ].offsetLeft - s[ 0 ].offsetLeft ) : track.clientWidth;
		}
		function max() {
			return Math.max( 0, track.scrollWidth - track.clientWidth );
		}
		function count() {
			var st = step();
			return st ? Math.max( 1, Math.round( max() / st ) + 1 ) : 1;
		}
		function index() {
			var st = step();
			if ( ! st ) {
				return 0;
			}
			// At the very end the last position may be shorter than one step.
			if ( max() - Math.abs( track.scrollLeft ) < 4 ) {
				return count() - 1;
			}
			return Math.round( Math.abs( track.scrollLeft ) / st );
		}
		function go( i ) {
			var n = count();
			i = Math.max( 0, Math.min( n - 1, i ) );
			var x = Math.min( max(), i * step() );
			track.scrollTo( { left: rtl ? -x : x, behavior: reduced ? 'auto' : 'smooth' } );
		}
		function build( n ) {
			dots.innerHTML = '';
			for ( var k = 0; k < n; k++ ) {
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'ard-dot';
				b.tabIndex = -1;
				b.addEventListener( 'click', go.bind( null, k ) );
				dots.appendChild( b );
			}
		}
		function update() {
			var n = count(),
				i = index();
			if ( prev ) {
				prev.disabled = i <= 0;
			}
			if ( next ) {
				next.disabled = i >= n - 1;
			}
			if ( dots ) {
				if ( dots.children.length !== n ) {
					build( n );
				}
				for ( var k = 0; k < dots.children.length; k++ ) {
					dots.children[ k ].classList.toggle( 'is-active', k === i );
				}
			}
			root.classList.toggle( 'ard--static', n <= 1 );
		}

		if ( prev ) {
			prev.addEventListener( 'click', function () {
				go( index() - 1 );
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				go( index() + 1 );
			} );
		}
		track.addEventListener( 'scroll', throttle( update ), { passive: true } );

		// Drag with the mouse (touch screens scroll natively).
		var drag = null,
			justDragged = false;
		track.addEventListener( 'pointerdown', function ( e ) {
			if ( 'mouse' !== e.pointerType || 0 !== e.button ) {
				return;
			}
			drag = { x: e.clientX, left: track.scrollLeft, id: e.pointerId, moved: false };
		} );
		window.addEventListener( 'pointermove', function ( e ) {
			if ( ! drag ) {
				return;
			}
			var dx = e.clientX - drag.x;
			if ( ! drag.moved && Math.abs( dx ) > 6 ) {
				drag.moved = true;
				track.classList.add( 'is-dragging' );
				try {
					track.setPointerCapture( drag.id );
				} catch ( err ) {}
			}
			if ( drag.moved ) {
				e.preventDefault();
				track.scrollLeft = drag.left - dx;
			}
		} );
		var endDrag = function () {
			if ( ! drag ) {
				return;
			}
			var moved = drag.moved;
			drag = null;
			if ( ! moved ) {
				return;
			}
			justDragged = true;
			setTimeout( function () {
				justDragged = false;
			}, 50 );
			// Snap back on: settle on the nearest card.
			var x = Math.abs( track.scrollLeft );
			track.classList.remove( 'is-dragging' );
			track.scrollLeft = rtl ? -x : x;
			go( index() );
		};
		window.addEventListener( 'pointerup', endDrag );
		window.addEventListener( 'pointercancel', endDrag );
		track.addEventListener(
			'click',
			function ( e ) {
				if ( justDragged ) {
					e.preventDefault();
					e.stopPropagation();
				}
			},
			true
		);
		track.addEventListener( 'dragstart', function ( e ) {
			e.preventDefault();
		} );
		window.addEventListener( 'resize', throttle( update ) );
		update();

		// Autoplay: pauses while the pointer, a finger or the keyboard is on it, and in hidden tabs.
		var every = +root.getAttribute( 'data-autoplay' ) || 0;
		var phone = window.matchMedia && window.matchMedia( '(max-width: 767px)' );
		if ( every && ! reduced ) {
			var paused = false;
			var pause = function () {
				paused = true;
			};
			var resume = function () {
				paused = false;
			};
			root.addEventListener( 'pointerenter', pause );
			root.addEventListener( 'pointerleave', resume );
			root.addEventListener( 'focusin', pause );
			root.addEventListener( 'focusout', resume );
			root.addEventListener( 'touchstart', pause, { passive: true } );
			setInterval( function () {
				// Phones just scroll: no automatic sliding.
				if ( paused || document.hidden || count() <= 1 || ( phone && phone.matches ) ) {
					return;
				}
				var i = index();
				go( i >= count() - 1 ? 0 : i + 1 );
			}, every );
		}
		return update;
	}

	/* ---------- Cards ---------- */
	function sizesOf( card ) {
		if ( ! card.ardSizes ) {
			try {
				card.ardSizes = JSON.parse( card.getAttribute( 'data-sizes' ) || '[]' );
			} catch ( e ) {
				card.ardSizes = [];
			}
		}
		return card.ardSizes;
	}

	function select( card, i ) {
		var s = sizesOf( card )[ i ];
		if ( ! s ) {
			return;
		}
		card.setAttribute( 'data-active', i );
		var buttons = card.querySelectorAll( '.ard-size[data-i]' );
		for ( var k = 0; k < buttons.length; k++ ) {
			var on = +buttons[ k ].getAttribute( 'data-i' ) === i;
			buttons[ k ].classList.toggle( 'is-active', on );
			buttons[ k ].setAttribute( 'aria-checked', on ? 'true' : 'false' );
		}
		var price = card.querySelector( '[data-ard-price]' );
		if ( price ) {
			price.innerHTML = s.p;
			price.classList.remove( 'is-swap' );
			void price.offsetWidth;
			price.classList.add( 'is-swap' );
		}
		var links = card.querySelectorAll( '[data-ard-link]' );
		for ( k = 0; k < links.length; k++ ) {
			links[ k ].href = s.u;
		}
		var pill = card.querySelector( '[data-ard-pill]' );
		if ( pill ) {
			pill.textContent = s.pl || s.l;
		}
		var add = card.querySelector( '[data-ard-add]' );
		if ( add ) {
			add.disabled = ! s.in;
		}
		var t = card.querySelector( '[data-ard-timer]' );
		if ( t ) {
			t.setAttribute( 'data-from', s.from || 0 );
			t.setAttribute( 'data-to', s.to || 0 );
			t.ardEnded = false;
			if ( s.pct && s.to && s.to > Date.now() / 1000 ) {
				t.removeAttribute( 'hidden' );
				if ( ARD.tick ) {
					ARD.tick();
				}
			} else {
				t.setAttribute( 'hidden', '' );
			}
		}
	}

	function message( card, text, error ) {
		var old = card.querySelector( '.ard-card__msg' );
		if ( old ) {
			old.remove();
		}
		var m = document.createElement( 'div' );
		m.className = 'ard-card__msg' + ( error ? ' is-error' : '' );
		m.setAttribute( 'role', error ? 'alert' : 'status' );
		m.textContent = text;
		card.appendChild( m );
		setTimeout( function () {
			m.remove();
		}, error ? 4000 : 2200 );
	}

	function addToCart( card, btn ) {
		var s = sizesOf( card )[ +card.getAttribute( 'data-active' ) ];
		if ( ! s || ! s.in || btn.classList.contains( 'is-loading' ) || ! C.ajax ) {
			return;
		}
		var pid = btn.getAttribute( 'data-product_id' );
		var body = new URLSearchParams();
		body.set( 'product_id', pid );
		if ( String( s.v ) !== String( pid ) ) {
			body.set( 'variation_id', s.v );
		}
		body.set( 'quantity', '1' );
		btn.classList.add( 'is-loading' );
		var $ = window.jQuery;
		if ( $ ) {
			$( document.body ).trigger( 'adding_to_cart', [ $( btn ), {} ] );
		}
		fetch( C.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				btn.classList.remove( 'is-loading' );
				if ( ! res || res.error || ! res.fragments ) {
					message( card, ( res && res.message ) || C.i18n.error, true );
					return;
				}
				btn.classList.add( 'is-done' );
				setTimeout( function () {
					btn.classList.remove( 'is-done' );
				}, 1800 );
				if ( $ ) {
					$.each( res.fragments, function ( key, value ) {
						$( key ).replaceWith( value );
					} );
					// Mini-cart drawer and the site's toast listen to this.
					$( document.body ).trigger( 'added_to_cart', [ res.fragments, res.cart_hash, $( btn ) ] );
				} else {
					message( card, C.i18n.added, false );
				}
			} )
			.catch( function () {
				btn.classList.remove( 'is-loading' );
				window.location.href = s.u;
			} );
	}

	/** True while one of the card's sizes is still on sale (no end date, or not ended). */
	function alive( card ) {
		var now = Date.now() / 1000;
		return sizesOf( card ).some( function ( s ) {
			return s.pct && s.in && ( ! s.to || s.to > now );
		} );
	}

	function recount( root ) {
		var min = +root.getAttribute( 'data-min' ) || 1;
		if ( root.querySelectorAll( '.ard-slide' ).length >= min ) {
			return;
		}
		var widget = root.closest( '.elementor-widget' );
		if ( widget ) {
			widget.classList.add( 'ard-is-empty' );
			if ( 'parent' === root.getAttribute( 'data-hide' ) ) {
				widget.classList.add( 'ard-hide-parent' );
			}
		} else {
			root.hidden = true;
		}
	}

	function drop( root, card, update ) {
		var slide = card.closest( '.ard-slide' );
		if ( ! slide ) {
			return;
		}
		slide.classList.add( 'is-leaving' );
		setTimeout( function () {
			slide.remove();
			recount( root );
			update();
		}, reduced ? 0 : 400 );
	}

	/* ---------- The site's own card template ---------- */
	function templateCards( root ) {
		var cards = root.querySelectorAll( '[data-ard-tpl]' );
		for ( var i = 0; i < cards.length; i++ ) {
			templateCard( cards[ i ] );
		}
	}
	function templateCard( wrap ) {
		var box = wrap.querySelector( '.box-product-1' ) || wrap.querySelector( '.e-loop-item > .e-con, .e-loop-item > .elementor-section' );
		var timer = wrap.querySelector( ':scope > .ard-timer' );
		var flag = wrap.querySelector( ':scope > [data-ard-flag]' );
		if ( box ) {
			if ( timer ) {
				box.appendChild( timer );
			}
			if ( flag ) {
				box.appendChild( flag );
			}
		}
		if ( ! timer ) {
			return;
		}
		// The card's size circles (snippet «aromist_volumes»): the countdown belongs to discounted sizes only.
		var sizes = wrap.querySelector( '.arv' );
		if ( ! sizes || ! window.MutationObserver ) {
			return;
		}
		var sync = function () {
			var active = sizes.querySelector( '.arv-dot.is-active' );
			var onSale = ! active || /ard-badge/.test( active.getAttribute( 'data-price' ) || '' );
			var to = +timer.getAttribute( 'data-to' ) || 0;
			if ( onSale && to > Date.now() / 1000 ) {
				timer.removeAttribute( 'hidden' );
			} else {
				timer.setAttribute( 'hidden', '' );
			}
		};
		new MutationObserver( sync ).observe( sizes, { subtree: true, attributes: true, attributeFilter: [ 'class' ] } );
		sync();
	}

	/* ---------- Section ---------- */
	function init( root ) {
		if ( ! root || root.ardInit ) {
			return;
		}
		root.ardInit = true;
		templateCards( root );
		var update = carousel( root );

		// A cached page may be older than a deal's end: drop those right away.
		var cards = root.querySelectorAll( '[data-ard-card]' );
		for ( var i = 0; i < cards.length; i++ ) {
			if ( ! alive( cards[ i ] ) ) {
				cards[ i ].closest( '.ard-slide' ).remove();
			}
		}
		recount( root );
		update();

		root.addEventListener( 'click', function ( e ) {
			var card = e.target.closest( '[data-ard-card]' );
			if ( ! card ) {
				return;
			}
			var size = e.target.closest( '.ard-size[data-i]' );
			if ( size ) {
				e.preventDefault();
				select( card, +size.getAttribute( 'data-i' ) );
				return;
			}
			var add = e.target.closest( '[data-ard-add]' );
			if ( add ) {
				e.preventDefault();
				addToCart( card, add );
			}
		} );

		root.addEventListener( 'ard:ended', function ( e ) {
			var card = e.target.closest( '[data-ard-card]' );
			if ( ! card ) {
				return;
			}
			var active = +card.getAttribute( 'data-active' );
			var sizes = sizesOf( card );
			if ( sizes[ active ] ) {
				// This size's sale is over; its price in the data is no longer true.
				sizes[ active ].pct = 0;
			}
			if ( alive( card ) ) {
				// Another size is still discounted: show that one.
				var now = Date.now() / 1000;
				for ( var k = 0; k < sizes.length; k++ ) {
					if ( k !== active && sizes[ k ].pct && sizes[ k ].in && ( ! sizes[ k ].to || sizes[ k ].to > now ) ) {
						select( card, k );
						break;
					}
				}
				return;
			}
			drop( root, card, update );
		} );

		if ( ARD.start ) {
			ARD.start();
		}
	}

	function boot() {
		var roots = document.querySelectorAll( '[data-ard]' );
		for ( var i = 0; i < roots.length; i++ ) {
			init( roots[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

	// Elementor editor: widgets are re-rendered on every change.
	function elementorHook() {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/ard_deals.default', function ( $el ) {
				init( $el[ 0 ].querySelector( '[data-ard]' ) );
			} );
			return true;
		}
		return false;
	}
	if ( ! elementorHook() && window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', elementorHook );
	}
} )();
