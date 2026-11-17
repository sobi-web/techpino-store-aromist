/* سبد خرید کشویی تکپینو */
( function () {
	'use strict';

	var cfg = window.tpmcConfig || {};
	if ( ! cfg.url ) {
		return;
	}

	var doc = document;
	var root = doc.documentElement;
	var STORE = 'tpmc:1';
	var EMPTY = { hash: '', count: 0, lines: 0, items: '', rows: '', total: '' };

	var state = null;      // Last cart state known; null until the first answer.
	var fresh = false;     // That state came from the server during this page view.
	var queue = Promise.resolve();
	var pending = 0;       // Requests not answered yet.
	var timers = {};       // Quantity changes waiting to be sent, by cart item key.
	var drawers = {};
	var current = null;    // The open drawer.
	var lastFocus = null;
	var msgTimer = 0;
	var quietUntil = 0;    // Our own change is being announced; ignore the echo.
	var externalTimer = 0;

	function cookie( name ) {
		var match = doc.cookie.match( '(?:^|; )' + name + '=([^;]*)' );
		return match ? decodeURIComponent( match[ 1 ] ) : '';
	}

	function each( selector, fn, scope ) {
		Array.prototype.forEach.call( ( scope || doc ).querySelectorAll( selector ), fn );
	}

	function isEditor() {
		return doc.body.classList.contains( 'elementor-editor-active' );
	}

	function idle() {
		return ! pending && ! Object.keys( timers ).length;
	}

	/* ---------- Painting ---------- */

	function paintCounts() {
		if ( ! state ) {
			return;
		}
		each( '[data-tpmc-count]', function ( el ) {
			if ( el.closest( '[data-tpmc-static]' ) ) {
				return;
			}
			var n = String( 'lines' === el.getAttribute( 'data-tpmc-count' ) ? state.lines : state.count );
			if ( el.textContent.trim() !== n ) {
				el.textContent = n;
				el.classList.remove( 'is-bump' );
				void el.offsetWidth;
				el.classList.add( 'is-bump' );
			}
			el.setAttribute( 'data-n', n );
		} );
	}

	function paintDrawer( drawer ) {
		if ( ! drawer || drawer.hasAttribute( 'data-tpmc-static' ) ) {
			return;
		}
		drawer.classList.toggle( 'is-loading', ! state );
		drawer.classList.toggle( 'is-empty', ! state || ! state.lines );
		drawer.classList.toggle( 'is-busy', pending > 0 );
		if ( state ) {
			drawer.querySelector( '.tpmc-items' ).innerHTML = state.items;
			drawer.querySelector( '.tpmc-rows' ).innerHTML = state.rows;
			drawer.querySelector( '.tpmc-total__val' ).innerHTML = state.total;
		}
	}

	function paint() {
		paintCounts();
		paintDrawer( current );
	}

	function notify( text ) {
		var box = current && current.querySelector( '.tpmc-msg' );
		if ( ! box ) {
			return;
		}
		clearTimeout( msgTimer );
		box.textContent = text || '';
		box.hidden = ! text;
		if ( text ) {
			msgTimer = setTimeout( function () {
				box.hidden = true;
			}, 6000 );
		}
	}

	/* ---------- Server ---------- */

	function remember( next ) {
		state = next;
		fresh = true;
		try {
			if ( next.lines ) {
				sessionStorage.setItem( STORE, JSON.stringify( next ) );
			} else {
				sessionStorage.removeItem( STORE );
			}
		} catch ( e ) {}
	}

	/**
	 * Requests run one after another, so the server sees changes in the order they were
	 * made; the drawer is repainted once the last answer is in and nothing is waiting.
	 */
	function request( data ) {
		pending++;
		if ( current ) {
			current.classList.add( 'is-busy' );
		}
		queue = queue.then( function () {
			return fetch( cfg.url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'X-TPMC': '1' },
				body: new URLSearchParams( data )
			} ).then( function ( response ) {
				return response.json();
			} );
		} ).then( function ( answer ) {
			if ( ! answer || 'number' !== typeof answer.count ) {
				throw new Error( ( answer && answer.error ) || '' );
			}
			var error = answer.error || '';
			delete answer.error;
			remember( answer );
			return error;
		} ).then( function ( error ) {
			pending--;
			if ( idle() ) {
				paint();
			}
			notify( error );
		}, function ( failure ) {
			pending--;
			if ( idle() ) {
				paint(); // Back to the last state the server confirmed.
			}
			notify( ( failure && failure.message ) || cfg.error );
		} );
		return queue;
	}

	function refresh() {
		if ( ! pending ) {
			request( { op: 'get' } );
		}
	}

	/** Other cart displays on the page (theme mini cart, cart and checkout pages) follow our change. */
	function announce() {
		var $ = window.jQuery;
		if ( ! $ || ! idle() ) {
			return;
		}
		quietUntil = Date.now() + 4000;
		var body = $( doc.body );
		body.trigger( 'wc_fragment_refresh' );
		if ( doc.querySelector( '.woocommerce-cart-form' ) ) {
			body.trigger( 'wc_update_cart' );
		}
		if ( doc.querySelector( 'form.woocommerce-checkout' ) ) {
			body.trigger( 'update_checkout' );
		}
	}

	/* ---------- Quantity and removal ---------- */

	function syncButtons( item ) {
		var qty = +item.dataset.qty;
		var max = +item.dataset.max;
		each( '[data-tpmc-step]', function ( button ) {
			button.disabled = +button.dataset.tpmcStep > 0 ? max >= 0 && qty >= max : qty <= +item.dataset.min;
		}, item );
	}

	function step( button ) {
		var item = button.closest( '.tpmc-item' );
		if ( ! item || item.closest( '[data-tpmc-static]' ) ) {
			return;
		}
		var key = item.dataset.key;
		var max = +item.dataset.max;
		var qty = +item.dataset.qty + ( +item.dataset.step || 1 ) * +button.dataset.tpmcStep;
		qty = Math.round( qty * 1e4 ) / 1e4;
		if ( qty < +item.dataset.min || ( max >= 0 && qty > max ) ) {
			return;
		}

		// Shown at once; sent when the clicking stops.
		item.dataset.qty = qty;
		item.querySelector( '.tpmc-qty__val' ).textContent = qty;
		item.classList.add( 'is-busy' );
		syncButtons( item );
		notify( '' );

		clearTimeout( timers[ key ] );
		timers[ key ] = setTimeout( function () {
			delete timers[ key ];
			request( { op: 'set', key: key, qty: qty } ).then( announce );
		}, 350 );
	}

	function remove( button ) {
		var item = button.closest( '.tpmc-item' );
		if ( ! item || item.closest( '[data-tpmc-static]' ) ) {
			return;
		}
		var key = item.dataset.key;
		clearTimeout( timers[ key ] );
		delete timers[ key ];
		item.classList.add( 'is-removing' );
		notify( '' );
		request( { op: 'remove', key: key } ).then( announce );
	}

	/* ---------- Open and close ---------- */

	function drawerFor( id ) {
		var key = id || '*';
		if ( ! drawers[ key ] || ! drawers[ key ].isConnected ) {
			drawers[ key ] = ( id && doc.getElementById( id ) ) || doc.querySelector( '.tpmc-drawer' );
		}
		return drawers[ key ];
	}

	function expanded( value ) {
		each( '[data-tpmc-open]', function ( trigger ) {
			trigger.setAttribute( 'aria-expanded', value ? 'true' : 'false' );
		} );
	}

	function open( drawer, opener ) {
		if ( ! drawer || current === drawer ) {
			return;
		}
		if ( current ) {
			close( true );
		}
		// Out of whatever container Elementor put it in: nothing above it can clip or cover a fixed panel.
		if ( ! isEditor() && drawer.parentNode !== doc.body ) {
			doc.body.appendChild( drawer );
			void drawer.offsetWidth;
		}
		lastFocus = opener || doc.activeElement;
		current = drawer;
		paintDrawer( drawer );
		root.style.setProperty( '--tpmc-scrollbar', Math.max( 0, window.innerWidth - root.clientWidth ) + 'px' );
		root.classList.add( 'tpmc-lock' );
		drawer.classList.add( 'is-open' );
		drawer.classList.remove( 'tpmc--preview' );
		expanded( true );
		drawer.querySelector( '.tpmc-panel' ).focus( { preventScroll: true } );
		if ( ! fresh ) {
			refresh();
		}
	}

	function close( keepFocus ) {
		if ( ! current ) {
			return;
		}
		current.classList.remove( 'is-open' );
		notify( '' );
		current = null;
		root.classList.remove( 'tpmc-lock' );
		expanded( false );
		if ( ! keepFocus && lastFocus && lastFocus.focus ) {
			lastFocus.focus( { preventScroll: true } );
		}
	}

	/** After "add to cart": the drawer of the trigger the visitor can actually see. */
	function autoOpen() {
		if ( current ) {
			return;
		}
		var target = null;
		each( '[data-tpmc-open]', function ( trigger ) {
			var drawer = drawerFor( trigger.getAttribute( 'data-tpmc-open' ) );
			if ( ! target && drawer && 'yes' === drawer.getAttribute( 'data-auto-open' ) && trigger.offsetParent ) {
				target = drawer;
			}
		} );
		open( target );
	}

	/* ---------- Events ---------- */

	doc.addEventListener( 'click', function ( event ) {
		var target = event.target.closest ? event.target.closest( '[data-tpmc-open],[data-tpmc-close],[data-tpmc-step],[data-tpmc-remove]' ) : null;
		if ( ! target ) {
			return;
		}
		if ( target.hasAttribute( 'data-tpmc-open' ) ) {
			event.preventDefault();
			var drawer = drawerFor( target.getAttribute( 'data-tpmc-open' ) );
			if ( drawer && drawer === current ) {
				close();
			} else {
				open( drawer, target );
			}
		} else if ( target.hasAttribute( 'data-tpmc-close' ) ) {
			// In the editor a drawer may be held open by the preview option alone.
			var own = target.closest( '.tpmc-drawer' );
			if ( own && own !== current ) {
				own.classList.remove( 'is-open', 'tpmc--preview' );
			} else {
				close();
			}
		} else if ( target.hasAttribute( 'data-tpmc-step' ) ) {
			step( target );
		} else {
			remove( target );
		}
	} );

	doc.addEventListener( 'keydown', function ( event ) {
		if ( ! current ) {
			return;
		}
		if ( 'Escape' === event.key ) {
			close();
			return;
		}
		if ( 'Tab' !== event.key ) {
			return;
		}
		// Focus stays inside the open panel.
		var panel = current.querySelector( '.tpmc-panel' );
		var stops = Array.prototype.filter.call( panel.querySelectorAll( 'a[href],button:not([disabled])' ), function ( el ) {
			return el.offsetParent && '-1' !== el.getAttribute( 'tabindex' );
		} );
		if ( ! stops.length ) {
			event.preventDefault();
			return;
		}
		var first = stops[ 0 ];
		var last = stops[ stops.length - 1 ];
		var active = doc.activeElement;
		if ( event.shiftKey && ( active === first || active === panel ) ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && ( active === last || ! panel.contains( active ) ) ) {
			event.preventDefault();
			first.focus();
		}
	} );

	function external() {
		if ( Date.now() < quietUntil ) {
			return;
		}
		clearTimeout( externalTimer );
		externalTimer = setTimeout( function () {
			fresh = false;
			refresh();
		}, 150 );
	}

	function added( event, fragments ) {
		var next = null;
		try {
			next = fragments && fragments[ 'tpmc-data' ] ? JSON.parse( fragments[ 'tpmc-data' ] ) : null;
		} catch ( e ) {}
		if ( next && 'number' === typeof next.count ) {
			remember( next );
			if ( idle() ) {
				paint();
			}
		} else {
			fresh = false;
			refresh();
		}
		autoOpen();
	}

	function listen() {
		// WooCommerce announces cart changes through jQuery events.
		if ( window.jQuery ) {
			window.jQuery( doc.body )
				.on( 'added_to_cart', added )
				.on( 'removed_from_cart updated_wc_div updated_cart_totals wc_cart_emptied', external );
		}
		doc.body.addEventListener( 'wc-blocks_added_to_cart', function () {
			added();
		} );
		doc.body.addEventListener( 'wc-blocks_removed_from_cart', external );
	}

	/**
	 * First state without asking the server when possible: no cart cookie means an empty
	 * cart, and a cart hash equal to the one stored with the last answer means that answer
	 * still holds. Opening the drawer checks again once in either case.
	 */
	function check() {
		var stored = null;
		try {
			stored = JSON.parse( sessionStorage.getItem( STORE ) );
		} catch ( e ) {}

		fresh = false;
		if ( ! cookie( 'woocommerce_items_in_cart' ) ) {
			state = EMPTY;
		} else if ( stored && stored.hash && stored.hash === cookie( 'woocommerce_cart_hash' ) ) {
			state = stored;
		} else {
			refresh();
			return;
		}
		paint();
	}

	function boot() {
		listen();
		check();
		// The page itself is the answer to a (non-AJAX) "add to cart".
		if ( doc.querySelector( '.tpmc-drawer[data-tpmc-now]' ) ) {
			autoOpen();
		}
	}

	// A page restored from the back/forward cache may show a cart that has changed since.
	window.addEventListener( 'pageshow', function ( event ) {
		if ( event.persisted ) {
			check();
		}
	} );

	if ( 'loading' === doc.readyState ) {
		doc.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
