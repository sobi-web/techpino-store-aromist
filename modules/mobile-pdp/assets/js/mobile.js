/**
 * Aromist on phones and tablets (up to 1024px, Elementor's tablet breakpoint). Every page: the compact
 * header, its search and the ☰ menu. Redesigned product pages: the whole mobile page, and the installment card
 * (SnappPay) at every width.
 *
 * The page keeps its own parts: WooCommerce's variation form and swatches, the SnappPay
 * widget's amounts, the «خبرم کنید» button (LetMeKnow), the site's menu sheet
 * (techpino-footer-bar) and mini-cart (techpino-mini-cart). This file adds the gallery,
 * prices on the size buttons, the price row, the installment card, the sticky buy bar
 * with its size sheet, the search, the section menu, the specs table, the accordions and
 * the similar-products rails. Element ids of the template: see mobile.css.
 */
/* global AMPDP, jQuery */
( function ( $ ) {
	'use strict';

	var doc = document;
	var root = doc.documentElement;
	var body = doc.body;
	if ( ! body.classList.contains( 'ampdp' ) ) {
		return;
	}
	// Every page gets the shell (header, search, menu) on phones; product pages the rest,
	// and wider screens only the product page's installment card (SnappPay).
	var mobile = window.matchMedia( '(max-width: 1024px)' ).matches;
	var pdp = body.classList.contains( 'ampdp-pdp' );

	var cfg = window.AMPDP || {};
	var t = cfg.i18n || {};
	var product = cfg.product || {};
	var FA = '۰۱۲۳۴۵۶۷۸۹';
	var ICON = {
		down: '<path d="m6 9 6 6 6-6"/>',
		left: '<path d="m15 18-6-6 6-6"/>',
		close: '<path d="M18 6 6 18M6 6l12 12"/>',
		expand: '<path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>',
		clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
	};

	/* ---------- helpers ---------- */

	function svg( name ) {
		return '<svg class="ampdp-ic" viewBox="0 0 24 24" aria-hidden="true">' + ICON[ name ] + '</svg>';
	}
	function fa( n, digits ) {
		return Number( n ).toLocaleString( 'fa-IR', { maximumFractionDigits: digits || 0 } );
	}
	function faDigits( text ) {
		return String( text ).replace( /\d/g, function ( d ) {
			return FA.charAt( +d );
		} );
	}
	/** Latin number from any text: «1,650,000 تومان», «۱٬۶۵۰٬۰۰۰», «۴,۵» (a comma after one digit is a decimal). */
	function num( text ) {
		var s = String( text )
			.replace( /[۰-۹]/g, function ( d ) {
				return FA.indexOf( d );
			} )
			.replace( /[٠-٩]/g, function ( d ) {
				return d.charCodeAt( 0 ) - 1632;
			} )
			.replace( /[٬\s]/g, '' );
		if ( /^\d[,٫.]\d$/.test( s.replace( /[^\d,٫.]/g, '' ) ) ) {
			return parseFloat( s.replace( /[^\d,٫.]/g, '' ).replace( /[,٫]/, '.' ) );
		}
		s = s.replace( /[^\d]/g, '' );
		return s ? parseInt( s, 10 ) : NaN;
	}
	function fmt( text ) {
		var args = arguments;
		var i = 0;
		return String( text ).replace( /%(\d)?\$?s/g, function ( m, pos ) {
			return pos ? args[ +pos ] : args[ ++i ];
		} );
	}
	function stripTags( html ) {
		var d = doc.createElement( 'div' );
		d.innerHTML = html;
		return d.textContent || '';
	}
	function el( tag, cls, html ) {
		var e = doc.createElement( tag );
		if ( cls ) {
			e.className = cls;
		}
		if ( html ) {
			e.innerHTML = html;
		}
		return e;
	}
	function text( tag, cls, value ) {
		var e = el( tag, cls );
		e.textContent = value;
		return e;
	}
	function byId( id, scope ) {
		return ( scope || doc ).querySelector( '.elementor-element[data-id="' + id + '"]' );
	}
	function smoothTo( node, offset, block ) {
		if ( 'center' === block ) {
			node.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			return;
		}
		var top = node.getBoundingClientRect().top + window.pageYOffset - ( offset || 0 );
		window.scrollTo( { top: Math.max( 0, top ), behavior: 'smooth' } );
	}

	/* ---------- layers: search, sheets, image viewer ---------- */

	var scrim = doc.querySelector( '.ampdp-scrim' );
	var layers = ( function () {
		var current = null;
		var opener = null;
		var withScrim = false;

		function open( node, focus, useScrim ) {
			if ( current ) {
				close( true );
			}
			opener = doc.activeElement;
			current = node;
			node.hidden = false;
			void node.offsetWidth;
			node.classList.add( 'is-open' );
			withScrim = !! ( useScrim && scrim );
			if ( withScrim ) {
				scrim.hidden = false;
				void scrim.offsetWidth;
				scrim.classList.add( 'is-on' );
			}
			root.classList.add( 'ampdp-lock' );
			if ( focus ) {
				setTimeout( function () {
					focus.focus( { preventScroll: true } );
				}, 60 );
			}
		}

		function close( keepFocus ) {
			if ( ! current ) {
				return;
			}
			var node = current;
			current = null;
			node.classList.remove( 'is-open' );
			node.style.transform = '';
			setTimeout( function () {
				if ( ! node.classList.contains( 'is-open' ) ) {
					node.hidden = true;
				}
			}, 330 );
			if ( withScrim ) {
				withScrim = false;
				scrim.classList.remove( 'is-on' );
				setTimeout( function () {
					if ( ! scrim.classList.contains( 'is-on' ) ) {
						scrim.hidden = true;
					}
				}, 260 );
			}
			root.classList.remove( 'ampdp-lock' );
			if ( ! keepFocus && opener && opener.focus ) {
				opener.focus( { preventScroll: true } );
			}
		}

		doc.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && current ) {
				close();
			}
		} );
		doc.addEventListener( 'click', function ( e ) {
			if ( current && e.target.closest( '[data-ampdp-close]' ) ) {
				close();
			}
		} );

		// Any sheet: drag its handle down to close.
		doc.addEventListener( 'pointerdown', function ( e ) {
			var handle = e.target.closest( '.ampdp-sheet__handle' );
			var sheet = handle && handle.closest( '.ampdp-sheet' );
			if ( ! sheet || sheet !== current ) {
				return;
			}
			var y0 = e.clientY;
			var dy = 0;
			sheet.classList.add( 'is-dragging' );
			handle.setPointerCapture( e.pointerId );
			function move( ev ) {
				dy = Math.max( 0, ev.clientY - y0 );
				sheet.style.transform = 'translateY(' + dy + 'px)';
			}
			function end() {
				handle.removeEventListener( 'pointermove', move );
				handle.removeEventListener( 'pointerup', end );
				handle.removeEventListener( 'pointercancel', end );
				sheet.classList.remove( 'is-dragging' );
				sheet.style.transform = '';
				if ( dy > 90 ) {
					close();
				}
			}
			handle.addEventListener( 'pointermove', move );
			handle.addEventListener( 'pointerup', end );
			handle.addEventListener( 'pointercancel', end );
		} );

		return { open: open, close: close };
	} )();

	var toastTimer;
	function toast( message ) {
		var box = doc.querySelector( '.ampdp-toast' );
		if ( ! box ) {
			box = el( 'div', 'ampdp-toast' );
			box.setAttribute( 'role', 'status' );
			body.appendChild( box );
		}
		box.textContent = message;
		box.classList.add( 'is-on' );
		clearTimeout( toastTimer );
		toastTimer = setTimeout( function () {
			box.classList.remove( 'is-on' );
		}, 4000 );
	}

	/* ---------- «نمایش بیشتر» under long text ---------- */

	/** Shows `lines` lines of the box; longer text gets «نمایش بیشتر» / «نمایش کمتر». */
	function clampMore( box, lines ) {
		if ( ! box ) {
			return;
		}
		box.style.setProperty( '--ampdp-lines', String( lines || 8 ) );
		box.classList.add( 'ampdp-clamp' );
		if ( box.scrollHeight <= box.clientHeight + 4 ) {
			box.classList.remove( 'ampdp-clamp' );
			return;
		}
		var btn = el( 'button', 'ampdp-more', '<span>' + t.more + '</span>' + svg( 'down' ) );
		btn.type = 'button';
		btn.setAttribute( 'aria-expanded', 'false' );
		box.parentNode.insertBefore( btn, box.nextSibling );
		btn.addEventListener( 'click', function () {
			var open = 'true' !== btn.getAttribute( 'aria-expanded' );
			box.classList.toggle( 'ampdp-clamp', ! open );
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			btn.firstChild.textContent = open ? t.less : t.more;
			// Closing a long text: bring its start back into view.
			if ( ! open && box.getBoundingClientRect().top < 0 ) {
				smoothTo( box, 120 );
			}
		} );
	}

	/* ---------- accordions ---------- */

	function accordion( section, head, parts, title, count ) {
		if ( ! section || ! head ) {
			return null;
		}
		section.classList.add( 'ampdp-acc' );
		head.classList.add( 'ampdp-acc__head' );
		head.setAttribute( 'role', 'button' );
		head.setAttribute( 'tabindex', '0' );
		head.setAttribute( 'aria-expanded', 'false' );
		parts.forEach( function ( part ) {
			if ( part ) {
				part.classList.add( 'ampdp-acc__body' );
			}
		} );
		var label = head.querySelector( '.elementor-heading-title, .elementor-icon-box-title' );
		if ( label && title ) {
			label.textContent = title;
		}
		if ( label && null !== count && undefined !== count ) {
			label.appendChild( doc.createTextNode( ' ' ) );
			label.appendChild( text( 'span', 'ampdp-acc__cnt', fa( count ) ) );
		}
		function toggle( force ) {
			var open = 'boolean' === typeof force ? force : ! section.classList.contains( 'is-open' );
			section.classList.toggle( 'is-open', open );
			head.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
		head.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( 'a' ) ) {
				e.preventDefault();
			}
			toggle();
		} );
		head.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key || ' ' === e.key ) {
				e.preventDefault();
				toggle();
			}
		} );
		return { section: section, toggle: toggle };
	}

	/** An accordion for a section that has no heading element of its own: a head is made and put first. */
	function accordionWrap( section, title ) {
		if ( ! section ) {
			return null;
		}
		var inner = section.querySelector( ':scope > .e-con-inner' ) || section;
		var head = el( 'div', 'ampdp-acc__head', '<span class="elementor-heading-title"></span>' );
		head.firstChild.textContent = title;
		inner.insertBefore( head, inner.firstChild );
		inner.classList.add( 'ampdp-acc__inner' );
		section.classList.add( 'ampdp-acc--wrap' );
		return accordion( section, head, [], null );
	}

	/* ====================================================================== header */

	( function header() {
		if ( ! mobile ) {
			return;
		}
		var hdr = doc.getElementById( 'ampdp-hdr' );
		if ( ! hdr ) {
			return;
		}
		// A header designed in Elementor: widgets with these classes (or #links) act as its buttons.
		hdr.querySelectorAll( '.ampdp-open-search, a[href="#ampdp-search"]' ).forEach( function ( n ) {
			n.setAttribute( 'data-ampdp-search', '' );
		} );
		hdr.querySelectorAll( '.ampdp-open-menu, a[href="#ampdp-menu"]' ).forEach( function ( n ) {
			n.setAttribute( 'data-ampdp-menu', '' );
		} );
		hdr.querySelectorAll( '.ampdp-open-cart, a[href="#ampdp-cart"]' ).forEach( function ( n ) {
			if ( doc.querySelector( '.tpmc-drawer' ) ) {
				n.setAttribute( 'data-tpmc-open', '' );
			} else if ( 'A' === n.tagName ) {
				n.href = ( cfg.cartUrl || n.href );
			}
		} );
		// Such a widget may wrap a link to «#»: the page must not jump to the top.
		hdr.addEventListener( 'click', function ( e ) {
			var a = e.target.closest( 'a[href^="#"]' );
			if ( a && e.target.closest( '[data-ampdp-search], [data-ampdp-menu], [data-tpmc-open]' ) ) {
				e.preventDefault();
			}
		} );

		// «بازگشت» (product page): the previous page when it is on this site; else the link (category or shop).
		var back = hdr.querySelector( '[data-ampdp-back]' );
		if ( back ) {
			back.addEventListener( 'click', function ( e ) {
				var ref = doc.referrer;
				if ( ref && 0 === ref.indexOf( location.origin + '/' ) && ref !== location.href && window.history.length > 1 ) {
					e.preventDefault();
					window.history.back();
				}
			} );
		}

		// Share (product page): the phone's share sheet; else the link is copied.
		var share = hdr.querySelector( '[data-ampdp-share]' );
		if ( share ) {
			share.addEventListener( 'click', function () {
				var url = location.href.split( '#' )[ 0 ];
				function copy() {
					var done = function () {
						toast( t.linkCopied );
					};
					if ( navigator.clipboard && navigator.clipboard.writeText ) {
						navigator.clipboard.writeText( url ).then( done, done );
						return;
					}
					var ta = el( 'textarea' );
					ta.value = url;
					ta.style.cssText = 'position:fixed;opacity:0';
					body.appendChild( ta );
					ta.select();
					try {
						doc.execCommand( 'copy' );
					} catch ( e ) {}
					body.removeChild( ta );
					done();
				}
				if ( navigator.share ) {
					navigator.share( { title: doc.title, url: url } ).catch( function ( err ) {
						if ( err && 'AbortError' !== err.name ) {
							copy();
						}
					} );
				} else {
					copy();
				}
			} );
		}

		// ☰: the site's own menu sheet (techpino-footer-bar listens to [data-tpfb-open]) or the menu chosen in the settings.
		var own = doc.getElementById( 'ampdp-menu' );
		var siteSheet = doc.querySelector( '.tpfb-sheet[id]' );
		hdr.querySelectorAll( '[data-ampdp-menu]' ).forEach( function ( btn ) {
			if ( own ) {
				btn.addEventListener( 'click', function () {
					layers.open( own, own.querySelector( '[data-ampdp-close]' ), true );
				} );
			} else if ( siteSheet ) {
				btn.setAttribute( 'data-tpfb-open', siteSheet.id );
				btn.setAttribute( 'aria-controls', siteSheet.id );
			} else if ( 'BUTTON' === btn.tagName ) {
				btn.hidden = true;
			}
		} );

		// Hide while scrolling down, back on the way up (unless switched off in the settings).
		var last = window.pageYOffset;
		var busy = false;
		window.addEventListener(
			'scroll',
			function () {
				if ( busy ) {
					return;
				}
				busy = true;
				requestAnimationFrame( function () {
					var y = window.pageYOffset;
					hdr.classList.toggle( 'is-scrolled', y > 4 );
					if ( false !== cfg.hideHeader && ! hdr.classList.contains( 'ampdp-hdr--pdp' ) && ! body.classList.contains( 'ampdp-sbar-open' ) ) {
						if ( y > last + 6 && y > 160 ) {
							hdr.classList.add( 'is-hidden' );
						} else if ( y < last - 6 || y < 160 ) {
							hdr.classList.remove( 'is-hidden' );
						}
					}
					last = y;
					busy = false;
				} );
			},
			{ passive: true }
		);
	} )();

	/* ====================================================================== search */

	( function search() {
		if ( ! mobile ) {
			return;
		}
		var bars = doc.querySelectorAll( '.ampdp-sbar' );
		if ( bars.length ) {
			bars.forEach( searchBar );
			return;
		}
		var box = doc.getElementById( 'ampdp-search' );
		if ( ! box ) {
			return;
		}
		var form = box.querySelector( 'form' );
		var input = box.querySelector( 'input[type="search"]' );
		var clear = box.querySelector( '.ampdp-search__clear' );
		var results = box.querySelector( '.ampdp-search__results' );
		var idle = box.querySelector( '.ampdp-search__idle' );
		var recentBox = box.querySelector( '[data-ampdp-recent]' );
		var KEY = 'ampdp_recent';
		var timer = null;
		var xhr = null;

		function url( q ) {
			return form.getAttribute( 'action' ) + '?s=' + encodeURIComponent( q ) + '&post_type=product';
		}
		function recent() {
			try {
				var list = JSON.parse( window.localStorage.getItem( KEY ) || '[]' );
				return Array.isArray( list ) ? list : [];
			} catch ( e ) {
				return [];
			}
		}
		function remember( q ) {
			q = String( q || '' ).trim();
			if ( ! q ) {
				return;
			}
			var list = recent().filter( function ( x ) {
				return x !== q;
			} );
			list.unshift( q );
			try {
				window.localStorage.setItem( KEY, JSON.stringify( list.slice( 0, 6 ) ) );
			} catch ( e ) {}
		}
		function paintRecent() {
			var list = recent();
			recentBox.hidden = ! list.length;
			var chips = recentBox.querySelector( '.ampdp-chips' );
			chips.innerHTML = '';
			list.forEach( function ( q ) {
				var a = el( 'a', 'ampdp-chip', svg( 'clock' ) );
				a.href = url( q );
				a.setAttribute( 'data-ampdp-q', q );
				a.appendChild( doc.createTextNode( q ) );
				chips.appendChild( a );
			} );
		}
		function showIdle() {
			if ( xhr ) {
				xhr.abort();
			}
			results.hidden = true;
			idle.hidden = false;
		}
		function render( q, items ) {
			results.innerHTML = '';
			if ( ! items.length ) {
				results.appendChild( text( 'p', 'ampdp-search__state', t.noResults ) );
			}
			items.forEach( function ( item ) {
				var a = el( 'a', 'ampdp-res' );
				a.href = item.url;
				var img = doc.createElement( 'img' );
				img.src = item.img;
				img.alt = '';
				img.loading = 'lazy';
				a.appendChild( img );
				var box = el( 'div', 'ampdp-res__txt' );
				box.appendChild( text( 'b', '', item.name ) );
				if ( item.en ) {
					box.appendChild( text( 'small', '', item.en ) );
				}
				var price = el( 'div', 'ampdp-res__price' );
				if ( item.inStock && item.price ) {
					if ( item.from ) {
						price.appendChild( doc.createTextNode( t.fromPrice + ' ' ) );
					}
					price.appendChild( text( 'strong', '', fa( item.price ) ) );
					price.appendChild( doc.createTextNode( ' ' + t.toman ) );
				} else if ( ! item.inStock ) {
					price.appendChild( text( 'span', 'is-oos', t.outOfStock ) );
				}
				box.appendChild( price );
				a.appendChild( box );
				a.addEventListener( 'click', function () {
					remember( q );
				} );
				results.appendChild( a );
			} );
			var all = text( 'a', 'ampdp-res-all', fmt( t.allResults, q ) );
			all.href = url( q );
			all.addEventListener( 'click', function () {
				remember( q );
			} );
			results.appendChild( all );
		}

		doc.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-ampdp-search]' );
			if ( btn && ! box.contains( btn ) ) {
				e.preventDefault();
				paintRecent();
				layers.open( box, input );
			}
		} );
		input.addEventListener( 'input', function () {
			var q = input.value.trim();
			clear.hidden = ! input.value;
			clearTimeout( timer );
			if ( q.length < 2 || ! cfg.searchUrl ) {
				showIdle();
				return;
			}
			timer = setTimeout( function () {
				if ( xhr ) {
					xhr.abort();
				}
				idle.hidden = true;
				results.hidden = false;
				if ( ! results.firstChild ) {
					results.appendChild( text( 'p', 'ampdp-search__state', t.searching ) );
				}
				xhr = $.getJSON( cfg.searchUrl, { q: q } ).done( function ( res ) {
					if ( input.value.trim() === q ) {
						render( q, ( res && res.items ) || [] );
					}
				} );
			}, 250 );
		} );
		clear.addEventListener( 'click', function () {
			input.value = '';
			clear.hidden = true;
			showIdle();
			input.focus();
		} );
		form.addEventListener( 'submit', function ( e ) {
			if ( ! input.value.trim() ) {
				e.preventDefault();
				return;
			}
			remember( input.value );
		} );
		box.addEventListener( 'click', function ( e ) {
			var chip = e.target.closest( '[data-ampdp-q]' );
			if ( chip ) {
				remember( chip.getAttribute( 'data-ampdp-q' ) );
			}
			if ( e.target.closest( '[data-ampdp-recent-clear]' ) ) {
				try {
					window.localStorage.removeItem( KEY );
				} catch ( err ) {}
				paintRecent();
			}
		} );
	} )();

	/**
	 * The site's live search (techpino-live-search) in the header: a field whose panel (banners,
	 * recent and popular searches, results) opens under the header while the page behind dims
	 * and stops scrolling. On the product page — or with the ☰-logo header — the field is a bar
	 * that covers the header when the search button is tapped; «بازگشت» or a tap outside closes it.
	 */
	function searchBar( bar ) {
		var root = bar.querySelector( '.tpls' );
		var input = root && root.querySelector( '.tpls-input' );
		if ( ! input ) {
			return;
		}
		var float = bar.classList.contains( 'ampdp-sbar--float' );
		var hdr = doc.getElementById( 'ampdp-hdr' );
		var dim = doc.querySelector( '.ampdp-sdim' ) || body.appendChild( el( 'div', 'ampdp-sdim' ) );
		function place() {
			var edge = float ? bar : hdr || bar;
			body.style.setProperty( '--ampdp-sbar-top', Math.max( 0, Math.round( edge.getBoundingClientRect().bottom ) ) + 'px' );
		}
		function state() {
			var open = root.classList.contains( 'is-open' );
			if ( open ) {
				place();
			}
			body.classList.toggle( 'ampdp-sbar-open', open );
			root.ownerDocument.documentElement.classList.toggle( 'ampdp-lock', open );
		}
		new MutationObserver( state ).observe( root, { attributes: true, attributeFilter: [ 'class' ] } );
		window.addEventListener( 'resize', function () {
			if ( body.classList.contains( 'ampdp-sbar-open' ) ) {
				place();
			}
		} );
		if ( ! float ) {
			dim.addEventListener( 'click', function () {
				input.blur();
			} );
			return;
		}
		function close() {
			input.blur();
			// The live search closes its panel on a press outside it.
			if ( root.classList.contains( 'is-open' ) ) {
				body.dispatchEvent( new Event( 'pointerdown', { bubbles: true } ) );
			}
			bar.classList.remove( 'is-on' );
			body.classList.remove( 'ampdp-sbar-float' );
			setTimeout( function () {
				if ( ! bar.classList.contains( 'is-on' ) ) {
					bar.hidden = true;
				}
			}, 220 );
		}
		doc.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-ampdp-search]' );
			if ( btn && ! bar.contains( btn ) ) {
				e.preventDefault();
				// Over the header, wherever it is now (below the admin bar for signed-in managers).
				bar.style.top = ( hdr ? Math.max( 0, Math.round( hdr.getBoundingClientRect().top ) ) : 0 ) + 'px';
				bar.hidden = false;
				void bar.offsetWidth;
				bar.classList.add( 'is-on' );
				body.classList.add( 'ampdp-sbar-float' );
				input.focus();
				// The live search opens on the field's focus event, which a browser may skip for a
				// window that is not focused: then open it the same way.
				setTimeout( function () {
					if ( ! root.classList.contains( 'is-open' ) ) {
						input.dispatchEvent( new Event( 'focus' ) );
					}
				}, 60 );
			}
		} );
		bar.querySelector( '[data-ampdp-sbar-close]' ).addEventListener( 'click', close );
		dim.addEventListener( 'click', close );
		doc.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && bar.classList.contains( 'is-on' ) ) {
				close();
			}
		} );
	}

	/* ===================================================== Goftino above the bottom bar */

	/**
	 * Goftino's closed chat bubble (an 80px iframe, inline `bottom: 20px`) sits on the bottom
	 * bar. While it is that small bubble it is lifted above the bar; once the chat opens the
	 * iframe grows and Goftino's own placement is left alone.
	 */
	( function goftino() {
		var bar = doc.querySelector( '.tpfb' );
		if ( ! mobile || ! bar || ! bar.getBoundingClientRect().height ) {
			return;
		}
		function place( frame ) {
			var h = frame.getBoundingClientRect().height;
			if ( h > 0 && h <= 100 ) {
				var want = Math.round( window.innerHeight - bar.getBoundingClientRect().top + 8 ) + 'px';
				if ( frame.style.bottom !== want ) {
					frame.style.bottom = want;
				}
			}
		}
		function watch( frame ) {
			place( frame );
			new MutationObserver( function () {
				place( frame );
			} ).observe( frame, { attributes: true, attributeFilter: [ 'style' ] } );
		}
		var frame = doc.getElementById( 'goftino_w' );
		if ( frame ) {
			watch( frame );
			return;
		}
		// Goftino loads after the page: wait for its iframe.
		var waiter = new MutationObserver( function () {
			var f = doc.getElementById( 'goftino_w' );
			if ( f ) {
				waiter.disconnect();
				watch( f );
			}
		} );
		waiter.observe( body, { childList: true, subtree: true } );
		setTimeout( function () {
			waiter.disconnect();
		}, 20000 );
	} )();

	/* =========================================== carousels → plain sideways scroll */

	/**
	 * Phones: every product, article and image carousel of the page content becomes a plain row
	 * that scrolls sideways (no sliding, dots or arrows). Swiper is switched off, also when it
	 * starts late (a carousel inside a tab starts when the tab opens). The width of a slide
	 * depends on what it holds: see «rails» in mobile.css.
	 */
	( function rails() {
		if ( ! mobile || doc.body.classList.contains( 'home' ) ) {
			return;
		}
		var SEL = '.elementor-widget-loop-carousel .swiper, .elementor-widget-media-carousel .swiper, .elementor-widget-image-carousel .swiper';
		var SKIP = '.elementor-location-header, .elementor-location-footer, .tpfb, .tpmc-drawer, .elementor-popup-modal, .ampdp-search, .ard, .elementor-element[data-id="9d11409"]';
		function kindOf( sw ) {
			var widget = sw.closest( '.elementor-widget-media-carousel, .elementor-widget-image-carousel' );
			if ( widget ) {
				// One image at a time (a banner slider): full width. Read from the widget's settings,
				// since Swiper may not have started yet.
				var st = {};
				try {
					st = JSON.parse( widget.getAttribute( 'data-settings' ) || '{}' ) || {};
				} catch ( e ) {}
				var per = st.slides_per_view_mobile || st.slides_to_show_mobile || st.slides_per_view || st.slides_to_show;
				return '1' === String( per ) ? 'hero' : 'media';
			}
			var slide = sw.querySelector( '.swiper-slide' );
			return slide && /\btype-post\b/.test( slide.className ) ? 'post' : 'product';
		}
		function stop( sw ) {
			if ( sw.swiper && ! sw.swiper.destroyed ) {
				try {
					sw.swiper.destroy( true, true );
				} catch ( e ) {}
			}
		}
		function unlock( sw ) {
			sw.querySelectorAll( '.swiper-slide[inert], .swiper-slide[aria-hidden]' ).forEach( function ( slide ) {
				slide.removeAttribute( 'inert' );
				slide.removeAttribute( 'aria-hidden' );
			} );
		}
		function rail( sw ) {
			if ( sw.classList.contains( 'ampdp-rail' ) || sw.closest( SKIP ) ) {
				return;
			}
			var kind = kindOf( sw );
			var h = 0;
			if ( 'hero' === kind ) {
				return; // A banner slider (one picture at a time) keeps sliding, as designed.
			}
			if ( 'media' === kind ) {
				// Image slides have no height of their own: keep the carousel's, measured once the
				// page (and Elementor's stylesheet for the widget, which may come late) has loaded.
				if ( 'complete' !== doc.readyState ) {
					return;
				}
				h = Math.round( sw.getBoundingClientRect().height );
				if ( h < 60 ) {
					return;
				}
			}
			stop( sw ); // Clears the inline styles too, so the height is set after it.
			if ( h ) {
				sw.style.setProperty( '--ampdp-rail-h', h + 'px' );
			}
			sw.classList.add( 'ampdp-rail', 'ampdp-rail--' + kind );
			sw.querySelectorAll( '.swiper-slide-duplicate' ).forEach( function ( d ) {
				d.parentNode.removeChild( d );
			} );
			// Swiper's lazy images: load them as plain images.
			sw.querySelectorAll( '[data-background]' ).forEach( function ( n ) {
				n.style.backgroundImage = 'url("' + n.getAttribute( 'data-background' ) + '")';
			} );
			sw.querySelectorAll( 'img[data-src]' ).forEach( function ( img ) {
				img.src = img.getAttribute( 'data-src' );
			} );
			// Swiper marks the slides it does not show as `inert` (their links cannot be tapped) and
			// aria-hidden; with the slider gone every slide is on screen, so clear both, now and if they come back.
			unlock( sw );
			new MutationObserver( function () {
				stop( sw );
				unlock( sw );
			} ).observe( sw, { attributes: true, attributeFilter: [ 'class' ] } );
			new MutationObserver( function () {
				unlock( sw );
			} ).observe( sw, { attributes: true, subtree: true, attributeFilter: [ 'inert', 'aria-hidden' ] } );
			if ( 'media' === kind ) {
				ratio( sw );
			}
		}
		/** An image carousel shows its pictures whole: the first picture's proportions. */
		function ratio( sw ) {
			var first = sw.querySelector( '.elementor-carousel-image, .swiper-slide img' );
			var src = first && ( 'IMG' === first.tagName ? first.currentSrc || first.src : ( first.style.backgroundImage.match( /url\(["']?(.*?)["']?\)/ ) || [] )[ 1 ] );
			if ( ! src ) {
				return;
			}
			var img = new Image();
			img.onload = function () {
				if ( img.naturalWidth && img.naturalHeight ) {
					sw.style.setProperty( '--ampdp-ratio', img.naturalWidth + ' / ' + img.naturalHeight );
					sw.classList.add( 'has-ratio' );
				}
			};
			img.src = src;
		}
		function scan() {
			doc.querySelectorAll( SEL ).forEach( rail );
		}
		scan();
		window.addEventListener( 'load', scan );
		// Carousels Elementor builds a little later.
		var tries = 0;
		( function again() {
			scan();
			if ( ++tries < 20 ) {
				setTimeout( again, 400 );
			}
		} )();
	} )();

	/* ===================================================================== footer */

	/**
	 * Phones: the footer's two link lists («دسترسی سریع», «دسته بندی ها») open and close on their
	 * titles; the about text shows three lines and «نمایش بیشتر». Tablets keep them open.
	 */
	if ( window.matchMedia( '(max-width: 767px)' ).matches ) {
		[ [ '0a481ab', '6047e8a' ], [ '11b5ede', '44f5dfd' ] ].forEach( function ( ids ) {
			var box = byId( ids[ 0 ] );
			var head = byId( ids[ 1 ], box || doc );
			if ( ! box || ! head ) {
				return;
			}
			box.classList.add( 'ampdp-facc' );
			head.classList.add( 'ampdp-facc__head' );
			head.setAttribute( 'role', 'button' );
			head.setAttribute( 'tabindex', '0' );
			head.setAttribute( 'aria-expanded', 'false' );
			Array.prototype.forEach.call( box.children, function ( child ) {
				if ( child !== head ) {
					child.classList.add( 'ampdp-facc__body' );
				}
			} );
			function toggle() {
				var open = ! box.classList.contains( 'is-open' );
				box.classList.toggle( 'is-open', open );
				head.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			}
			head.addEventListener( 'click', toggle );
			head.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key || ' ' === e.key ) {
					e.preventDefault();
					toggle();
				}
			} );
		} );
		var about = byId( 'e33176c' );
		if ( about ) {
			clampMore( about.querySelector( '.elementor-widget-container' ) || about, 3 );
		}
	}

	// The rest is the product page.
	if ( ! pdp ) {
		return;
	}

	/* ====================================================== texts above the fold */

	( function texts() {
		if ( ! mobile ) {
			return;
		}
		// Breadcrumb: categories only; the product's name is the title right below.
		var crumb = doc.querySelector( '.elementor-element[data-id="c3b027e"] .woocommerce-breadcrumb' );
		if ( crumb && crumb.lastChild && 3 === crumb.lastChild.nodeType && crumb.querySelector( 'a' ) ) {
			crumb.removeChild( crumb.lastChild );
		}

		// Category links above the title as short chips; gender first, in gold.
		var tags = doc.querySelector( '.elementor-element[data-id="6720d9c"] .elementor-icon-box-description' );
		if ( tags ) {
			Array.prototype.forEach.call( tags.querySelectorAll( 'a' ), function ( a ) {
				var label = a.textContent.trim().replace( /^عطر\s+(فصل\s+)?/, '' );
				a.textContent = label;
				if ( /^(مردانه|زنانه|یونیسکس)$/.test( label ) ) {
					a.classList.add( 'ampdp-tag-gold' );
					tags.insertBefore( a, tags.firstChild );
				}
			} );
		}

		// Notes card: «گروه بویایی» without the colon.
		var family = doc.querySelector( '.elementor-element[data-id="28bd5b0"] .elementor-heading-title' );
		if ( family ) {
			family.textContent = family.textContent.replace( /[:：]\s*$/, '' ).trim();
		}

		clampMore( doc.querySelector( '.elementor-element[data-id="8a2a31f"] .woocommerce-product-details__short-description' ), cfg.pdp && cfg.pdp.aboutLines );
	} )();

	/* ============================================== notes: one sideways line each */

	/**
	 * Every level's notes sit on one line that scrolls sideways. While more notes are hidden
	 * at the line's end, a fading arrow shows there (a tap scrolls on); the first time the card
	 * comes into view, the long lines move a little and back, so the swipe is discovered.
	 */
	( function notes() {
		if ( ! mobile ) {
			return;
		}
		var card = byId( 'ffa2ed7' );
		if ( ! card ) {
			return;
		}
		var long = [];
		card.querySelectorAll( '.jet-listing-dynamic-repeater__items' ).forEach( function ( list ) {
			list.classList.add( 'ampdp-scroll-x' );
			var wrap = list.closest( '.elementor-widget-jet-listing-dynamic-repeater' );
			if ( ! wrap ) {
				return;
			}
			var dir = 'rtl' === getComputedStyle( list ).direction ? -1 : 1;
			var hint = el( 'button', 'ampdp-xhint', svg( 'left' ) );
			hint.type = 'button';
			hint.tabIndex = -1;
			hint.setAttribute( 'aria-label', t.scrollMore );
			wrap.classList.add( 'ampdp-xwrap' );
			wrap.appendChild( hint );
			function paint() {
				var max = list.scrollWidth - list.clientWidth;
				wrap.classList.toggle( 'ampdp-x-more', max > 4 && Math.abs( list.scrollLeft ) < max - 4 );
			}
			list.addEventListener( 'scroll', function () {
				requestAnimationFrame( paint );
			}, { passive: true } );
			hint.addEventListener( 'click', function () {
				list.scrollBy( { left: dir * list.clientWidth * 0.6, behavior: 'smooth' } );
			} );
			paint();
			if ( list.scrollWidth - list.clientWidth > 4 ) {
				long.push( { list: list, dir: dir } );
			}
		} );
		if ( ! long.length || ! ( 'IntersectionObserver' in window ) || window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
			return;
		}
		var io = new IntersectionObserver( function ( entries ) {
			if ( ! entries[ 0 ].isIntersecting ) {
				return;
			}
			io.disconnect();
			long.forEach( function ( x, i ) {
				setTimeout( function () {
					if ( Math.abs( x.list.scrollLeft ) > 2 ) {
						return;
					}
					x.list.scrollTo( { left: x.dir * 44, behavior: 'smooth' } );
					setTimeout( function () {
						x.list.scrollTo( { left: 0, behavior: 'smooth' } );
					}, 520 );
				}, 300 + i * 140 );
			} );
		}, { threshold: 0.7 } );
		io.observe( card );
	} )();

	/* ================================================================== trust row */

	( function trust() {
		if ( ! mobile ) {
			return;
		}
		var tpl = doc.getElementById( 'ampdp-trust' );
		var buyBox = byId( 'd2315d6' );
		if ( tpl && buyBox && tpl.content ) {
			buyBox.parentNode.insertBefore( tpl.content.cloneNode( true ), buyBox.nextSibling );
		}
	} )();

	/* ===================================================================== gallery */

	( function gallery() {
		if ( ! mobile ) {
			return;
		}
		var box = byId( '9d11409' );
		var main = byId( 'ba8371f' );
		if ( ! box || ! main ) {
			return;
		}
		var images = [];
		var seen = {};
		function add( img ) {
			var src = img && ( img.getAttribute( 'src' ) || img.getAttribute( 'data-src' ) );
			if ( ! src ) {
				return;
			}
			var key = src.replace( /-\d+x\d+(?=\.\w+$)/, '' );
			if ( seen[ key ] ) {
				return;
			}
			seen[ key ] = true;
			images.push( { src: src, srcset: img.getAttribute( 'srcset' ) || '', alt: img.getAttribute( 'alt' ) || '' } );
		}
		add( main.querySelector( 'img' ) );
		var carousel = byId( '9ec9aac' );
		if ( carousel ) {
			carousel.querySelectorAll( '.swiper-slide:not(.swiper-slide-duplicate) img' ).forEach( add );
		}
		if ( ! images.length ) {
			return;
		}

		function track( lazy ) {
			var tr = el( 'div', 'ampdp-gal__track' );
			images.forEach( function ( im, i ) {
				var img = doc.createElement( 'img' );
				img.src = im.src;
				if ( im.srcset ) {
					img.srcset = im.srcset;
					img.sizes = '100vw';
				}
				img.alt = im.alt;
				img.decoding = 'async';
				if ( lazy || i ) {
					img.loading = 'lazy';
				}
				tr.appendChild( img );
			} );
			return tr;
		}
		function index( tr ) {
			return Math.round( Math.abs( tr.scrollLeft ) / Math.max( 1, tr.clientWidth ) );
		}

		var gal = el( 'div', 'ampdp-gal' );
		var tr = track( false );
		gal.appendChild( tr );
		var n = images.length;
		var dots;
		var count;
		if ( n > 1 ) {
			dots = el( 'div', 'ampdp-gal__dots', new Array( n + 1 ).join( '<i></i>' ) );
			count = el( 'span', 'ampdp-gal__count' );
			gal.appendChild( dots );
			gal.appendChild( count );
		}
		if ( body.classList.contains( 'ampdp-oos' ) ) {
			gal.appendChild( text( 'span', 'ampdp-gal__oos', t.outOfStock ) );
		}
		var zoom = el( 'button', 'ampdp-ib ampdp-gal__zoom', svg( 'expand' ) );
		zoom.type = 'button';
		zoom.setAttribute( 'aria-label', t.zoom );
		gal.appendChild( zoom );
		box.insertBefore( gal, main );
		main.classList.add( 'ampdp-replaced' );

		function paint() {
			if ( n < 2 ) {
				return;
			}
			var i = index( tr );
			dots.querySelectorAll( 'i' ).forEach( function ( d, k ) {
				d.classList.toggle( 'is-on', k === i );
			} );
			count.textContent = fmt( t.of, fa( i + 1 ), fa( n ) );
		}
		tr.addEventListener( 'scroll', function () {
			requestAnimationFrame( paint );
		}, { passive: true } );
		paint();

		var viewer = null;
		function openViewer() {
			if ( ! viewer ) {
				viewer = el( 'div', 'ampdp-viewer' );
				viewer.setAttribute( 'role', 'dialog' );
				viewer.setAttribute( 'aria-modal', 'true' );
				viewer.setAttribute( 'aria-label', t.zoom );
				viewer.hidden = true;
				var top = el( 'div', 'ampdp-viewer__top', '<span></span>' );
				var close = el( 'button', 'ampdp-ib', svg( 'close' ) );
				close.type = 'button';
				close.setAttribute( 'data-ampdp-close', '' );
				close.setAttribute( 'aria-label', t.close );
				top.appendChild( close );
				var vtr = track( true );
				viewer.appendChild( top );
				viewer.appendChild( vtr );
				vtr.addEventListener( 'scroll', function () {
					top.firstChild.textContent = fmt( t.of, fa( index( vtr ) + 1 ), fa( n ) );
				}, { passive: true } );
				body.appendChild( viewer );
			}
			var i = index( tr );
			layers.open( viewer, viewer.querySelector( '[data-ampdp-close]' ) );
			var vt = viewer.querySelector( '.ampdp-gal__track' );
			vt.scrollLeft = 'rtl' === getComputedStyle( vt ).direction ? -i * vt.clientWidth : i * vt.clientWidth;
			viewer.querySelector( '.ampdp-viewer__top span' ).textContent = fmt( t.of, fa( i + 1 ), fa( n ) );
		}
		zoom.addEventListener( 'click', openViewer );
		tr.addEventListener( 'click', openViewer );
	} )();

	/* ============================================================ buy box state */

	var form = doc.querySelector( '.elementor-element[data-id="d2315d6"] form.cart' ) || doc.querySelector( 'form.cart' );
	var S = {
		variable: !! ( form && form.classList.contains( 'variations_form' ) ),
		options: [],
		sel: -1,
		chosen: false,
		snapp: null,
	};

	( function readSnapp() {
		var w = form && form.querySelector( '.snapppay-widget' );
		if ( ! w ) {
			return;
		}
		var map = {};
		try {
			map = JSON.parse( w.getAttribute( 'data-snapppay-map' ) || '{}' ) || {};
		} catch ( e ) {}
		var img = w.querySelector( 'img' );
		S.snapp = {
			map: map,
			fallback: num( stripTags( w.getAttribute( 'data-snapppay-default' ) || '' ) ),
			img: img ? img.getAttribute( 'src' ) : '',
		};
	} )();

	function installment( option ) {
		if ( ! S.snapp || ( option && ! option.ok ) ) {
			return NaN;
		}
		if ( option && S.snapp.map[ option.vid ] ) {
			return num( stripTags( S.snapp.map[ option.vid ] ) );
		}
		return option ? NaN : S.snapp.fallback;
	}

	( function readOptions() {
		if ( ! S.variable ) {
			return;
		}
		var raw = form.getAttribute( 'data-product_variations' );
		var vars = null;
		try {
			vars = raw && 'false' !== raw ? JSON.parse( raw ) : null;
		} catch ( e ) {}
		if ( ! vars ) {
			return;
		}
		vars.forEach( function ( v ) {
			var parts = [];
			var order = 0;
			var single = 1 === Object.keys( v.attributes ).length;
			Object.keys( v.attributes ).forEach( function ( key ) {
				var select = form.querySelector( 'select[name="' + key + '"]' );
				var value = v.attributes[ key ];
				if ( ! select ) {
					return;
				}
				var opts = Array.prototype.slice.call( select.options );
				var hit = opts.filter( function ( o ) {
					return o.value === value;
				} )[ 0 ];
				if ( hit ) {
					parts.push( hit.textContent.trim() );
					order = order * 100 + opts.indexOf( hit );
				}
			} );
			var label = parts.join( ' / ' );
			var ml = single && /میل|ml/i.test( label ) ? num( label.match( /[\d۰-۹]+/ ) || '' ) : NaN;
			S.options.push( {
				vid: v.variation_id,
				attrs: v.attributes,
				label: faDigits( label ),
				order: order,
				price: +v.display_price,
				regular: +v.display_regular_price || +v.display_price,
				pct: +v.display_regular_price > +v.display_price ? Math.round( ( 1 - v.display_price / v.display_regular_price ) * 100 ) : 0,
				ml: ml > 0 ? ml : 0,
				max: v.max_qty ? +v.max_qty : 99,
				ok: !! ( v.is_in_stock && v.is_purchasable && v.variation_is_active && v.variation_is_visible ),
			} );
		} );
		S.options.sort( function ( a, b ) {
			return a.order - b.order;
		} );

		// «به‌صرفه‌ترین»: the lowest price per millilitre, when there is a real choice.
		var perMl = S.options.filter( function ( o ) {
			return o.ok && o.ml;
		} );
		if ( perMl.length >= 3 ) {
			perMl.sort( function ( a, b ) {
				return a.price / a.ml - b.price / b.ml;
			} );
			perMl[ 0 ].best = true;
		}
	} )();

	/** «۲۰٪ تخفیف» chip; `upTo` = «تا ۲۰٪ تخفیف». */
	function saleChip( pct, upTo ) {
		return pct ? '<span class="ampdp-off">' + ( upTo ? t.upTo + ' ' : '' ) + fa( pct ) + '٪ ' + t.off + '</span>' : '';
	}
	function maxPct() {
		return S.options.reduce( function ( m, o ) {
			return o.ok && o.pct > m ? o.pct : m;
		}, 0 );
	}

	function optionByVid( vid ) {
		for ( var i = 0; i < S.options.length; i++ ) {
			if ( S.options[ i ].vid === +vid ) {
				return i;
			}
		}
		return -1;
	}

	/* ===================================================================== buy box */

	var priceRow = null;
	var snappCard = null;

	( function buyBox() {
		if ( ! form ) {
			return;
		}
		var wrap = S.variable ? form.querySelector( '.single_variation_wrap' ) : form;

		if ( S.variable && mobile ) {
			var available = S.options.filter( function ( o ) {
				return o.ok;
			} ).length;
			var rows = form.querySelectorAll( 'table.variations tr' );
			if ( 1 === rows.length ) {
				var th = rows[ 0 ].querySelector( 'th.label' );
				var label = th && th.querySelector( 'label' );
				if ( label && 'pa_volume' === label.getAttribute( 'for' ) ) {
					label.textContent = t.chooseSize;
				}
				if ( th && available ) {
					th.parentNode.insertBefore( text( 'td', 'ampdp-size-count', fmt( t.sizes, fa( available ) ) ), th.nextSibling );
				}
			}
			// Price under each size button (one attribute only: one variation per button).
			var ul = form.querySelector( '.variable-items-wrapper' );
			if ( ul && 1 === rows.length ) {
				var items = ul.querySelectorAll( '.variable-item' );
				if ( 4 === items.length ) {
					ul.classList.add( 'ampdp-n4' );
				}
				items.forEach( function ( li ) {
					var span = li.querySelector( '.variable-item-span' );
					if ( span ) {
						span.textContent = faDigits( span.textContent );
					}
					var value = li.getAttribute( 'data-value' );
					var o = S.options.filter( function ( x ) {
						return x.attrs[ ul.getAttribute( 'data-attribute_name' ) ] === value;
					} )[ 0 ];
					if ( o && ! li.querySelector( '.ampdp-chip-price' ) ) {
						var holder = li.querySelector( '.variable-item-contents' ) || li;
						holder.appendChild( text( 'small', 'ampdp-chip-price', o.ok ? fa( o.price ) : t.outOfStock ) );
						li.classList.toggle( 'ampdp-chip-oos', ! o.ok );
					}
				} );
				ul.addEventListener( 'click', function () {
					S.chosen = true;
				} );
			}

			priceRow = el( 'div', 'ampdp-price' );
			wrap.insertBefore( priceRow, wrap.firstChild );
		}

		if ( S.snapp ) {
			snappCard = el(
				'div',
				'ampdp-snapp',
				'<div class="ampdp-snapp__top"><img src="' + encodeURI( S.snapp.img ) + '" alt="" width="44" height="44"><div><b>' + t.snappTitle + '</b><span>' + t.snappText + '</span></div></div>' +
					'<div class="ampdp-snapp__row"><span>' + t.first + '</span><strong></strong><span class="ampdp-cur">' + t.toman + '</span></div>' +
					'<div class="ampdp-snapp__steps" aria-hidden="true"><i></i><i></i><i></i><i></i></div>'
			);
			wrap.appendChild( snappCard );
			form.classList.add( 'ampdp-has-snapp' );
		}

		paintBuy();
		if ( S.variable ) {
			$( form )
				.on( 'found_variation.ampdp', function ( e, v ) {
					S.sel = optionByVid( v.variation_id );
					paintBuy();
				} )
				.on( 'reset_data.ampdp hide_variation.ampdp', function () {
					S.sel = -1;
					paintBuy();
				} );
			// The form may have settled on its default size before this ran.
			var vidInput = form.querySelector( 'input.variation_id' );
			if ( vidInput && +vidInput.value ) {
				S.sel = optionByVid( vidInput.value );
				paintBuy();
			}
			$( form ).on( 'change input', 'input.qty', paintBuy );
		}
	} )();

	function paintBuy() {
		var o = S.options[ S.sel ];
		var min = +product.minPrice || 0;
		if ( form ) {
			form.classList.toggle( 'ampdp-var-oos', !! ( o && ! o.ok ) );
		}
		if ( priceRow ) {
			priceRow.classList.toggle( 'is-oos', !! ( o && ! o.ok ) );
			priceRow.innerHTML =
				'<div><div class="ampdp-price__lbl">' + ( o ? fmt( t.priceOf, o.label ) : t.from ) + '</div>' +
				( o ? '<div class="ampdp-price__stock' + ( o.ok ? '' : ' is-oos' ) + '">' + ( o.ok ? t.inStock : t.outOfStock ) + '</div>' : '' ) + '</div>' +
				'<div class="ampdp-price__val">' +
				( o && o.ok && o.pct ? '<div class="ampdp-price__was">' + saleChip( o.pct ) + '<del>' + fa( o.regular ) + '</del></div>' : '' ) +
				'<strong>' + fa( o ? o.price : min ) + '</strong><span class="ampdp-cur">' + t.toman + '</span>' +
				( o && o.ml && o.ok ? '<span class="ampdp-price__per">' + fmt( t.perMl, fa( Math.round( o.price / o.ml ) ) ) + '</span>' : '' ) + '</div>';
		}
		if ( snappCard ) {
			var qtyInput = form.querySelector( 'input.qty' );
			var qty = Math.max( 1, parseInt( qtyInput && qtyInput.value, 10 ) || 1 );
			var amount = product.inStock ? installment( o ) : NaN;
			snappCard.hidden = isNaN( amount );
			snappCard.querySelector( 'strong' ).textContent = fa( amount * qty );
		}
		paintBar();
	}

	// Wider screens stop here: they keep their own layout, with the installment card above.
	if ( ! mobile ) {
		return;
	}

	/* ==================================================== «موجود شد خبرم کنید» button */

	/**
	 * LetMeKnow turns the add button into its notify button and paints it red with inline
	 * !important styles. While it is the notify button, it gets the page's soft style instead;
	 * when an in-stock size is picked again, the button is handed back untouched.
	 */
	( function notifyButton() {
		if ( ! form ) {
			return;
		}
		var STYLE = {
			'background-color': '#fff',
			color: '#0A6B56',
			'border-color': '#009879',
			'border-width': '1.5px',
			'box-shadow': 'none',
		};
		function check() {
			var btn = form.querySelector( '.single_add_to_cart_button' );
			if ( ! btn ) {
				return;
			}
			var notify = /letmeknow/.test( btn.className );
			var mine = btn.getAttribute( 'data-ampdp-style' );
			if ( notify && mine !== btn.getAttribute( 'style' ) ) {
				Object.keys( STYLE ).forEach( function ( key ) {
					btn.style.setProperty( key, STYLE[ key ], 'important' );
				} );
				btn.setAttribute( 'data-ampdp-style', btn.getAttribute( 'style' ) || '' );
			} else if ( ! notify && null !== mine ) {
				Object.keys( STYLE ).forEach( function ( key ) {
					btn.style.removeProperty( key );
				} );
				btn.removeAttribute( 'data-ampdp-style' );
			}
		}
		new MutationObserver( check ).observe( form, { attributes: true, attributeFilter: [ 'class', 'style' ], subtree: true } );
		check();
	} )();

	/* ===================================================================== buy bar */

	var bar = doc.getElementById( 'ampdp-bar' );

	function paintBar() {
		if ( ! bar || ! product.inStock ) {
			return;
		}
		var o = S.chosen ? S.options[ S.sel ] : null;
		var label = bar.querySelector( '[data-ampdp-bar-label]' );
		var price = bar.querySelector( '[data-ampdp-bar-price]' );
		if ( o && o.ok ) {
			label.innerHTML = o.label + ' ' + saleChip( o.pct );
			price.textContent = fa( o.price );
		} else {
			if ( S.variable ) {
				label.innerHTML = t.from + ' ' + saleChip( maxPct(), true );
			}
			price.textContent = fa( +product.minPrice || 0 );
		}
	}

	( function buyBar() {
		if ( ! bar ) {
			return;
		}
		if ( doc.querySelector( '.tpcw' ) ) {
			body.classList.add( 'ampdp-has-tpcw' );
		}
		var buyBox = byId( 'd2315d6' );
		var target = ( form && form.querySelector( '.single_add_to_cart_button' ) ) || buyBox;
		if ( target && 'IntersectionObserver' in window ) {
			new IntersectionObserver( function ( entries ) {
				var on = ! entries[ 0 ].isIntersecting;
				body.classList.toggle( 'ampdp-bar-on', on );
				bar.setAttribute( 'aria-hidden', on ? 'false' : 'true' );
			} ).observe( target );
		} else {
			body.classList.add( 'ampdp-bar-on' );
			bar.setAttribute( 'aria-hidden', 'false' );
		}
		paintBar();

		var buy = bar.querySelector( '[data-ampdp-buy]' );
		if ( buy ) {
			buy.addEventListener( 'click', function () {
				if ( S.variable && S.options.length ) {
					sheet.open();
				} else if ( ! S.variable && product.id ) {
					add( this, { product_id: product.id, quantity: 1 }, function ( message ) {
						toast( message );
					} );
				} else {
					smoothTo( buyBox || form, 0, 'center' );
				}
			} );
		}
		// Out of stock: bring the «موجود شد خبرم کنید» button (LetMeKnow) into view.
		var notify = bar.querySelector( '[data-ampdp-notify]' );
		if ( notify ) {
			notify.addEventListener( 'click', function () {
				var btn = buyBox && buyBox.querySelector( '[class*="letmeknow"], .single_add_to_cart_button' );
				smoothTo( btn || buyBox || doc.body, 0, 'center' );
				if ( btn ) {
					setTimeout( function () {
						btn.focus( { preventScroll: true } );
					}, 500 );
				}
			} );
		}
	} )();

	/* ============================================================== add to cart */

	/**
	 * POST to the plugin's endpoint; on success refresh the cart fragments and announce
	 * `added_to_cart`, which opens the site's mini-cart (techpino-mini-cart auto-open).
	 */
	function add( btn, data, onError, onDone ) {
		btn.classList.add( 'is-loading' );
		$.post( cfg.ajaxUrl, data )
			.done( function ( res ) {
				if ( res && res.fragments ) {
					$.each( res.fragments, function ( key, value ) {
						$( key ).replaceWith( value );
					} );
					if ( onDone ) {
						onDone();
					}
					$( doc.body ).trigger( 'added_to_cart', [ res.fragments, res.cart_hash, $( btn ) ] );
				} else {
					onError( ( res && res.message ) || t.error );
				}
			} )
			.fail( function () {
				onError( t.error, true );
			} )
			.always( function () {
				btn.classList.remove( 'is-loading' );
			} );
	}

	/** Put the inline form on the same size (and quantity) as the sheet. */
	function syncForm( o, qty ) {
		Object.keys( o.attrs ).forEach( function ( key ) {
			$( form ).find( 'select[name="' + key + '"]' ).val( o.attrs[ key ] ).trigger( 'change' );
		} );
		if ( qty ) {
			$( form ).find( 'input.qty' ).val( qty ).trigger( 'change' );
		}
	}

	/* ================================================================ size sheet */

	var sheet = ( function () {
		var node = doc.getElementById( 'ampdp-sheet' );
		if ( ! node || ! S.options.length ) {
			return { open: function () {} };
		}
		var list = node.querySelector( '.ampdp-opts' );
		var msg = node.querySelector( '.ampdp-sheet__msg' );
		var qtyBox = node.querySelector( '.ampdp-qty' );
		var addBtn = node.querySelector( '[data-ampdp-add]' );
		var snappRow = node.querySelector( '.ampdp-sheet__snapp' );
		var sel = -1;
		var qty = 1;

		list.innerHTML = S.options
			.map( function ( o, i ) {
				return (
					'<button type="button" class="ampdp-opt" role="radio" aria-checked="false" data-i="' + i + '"' + ( o.ok ? '' : ' disabled' ) + '>' +
					'<span class="ampdp-opt__radio"></span>' +
					'<span class="ampdp-opt__main"><b>' + o.label + '</b>' + ( o.ok && o.pct ? saleChip( o.pct ) : o.best ? '<span class="ampdp-opt__badge">' + t.best + '</span>' : '' ) +
					'<small>' + ( ! o.ok ? t.outOfStock : o.ml ? fmt( t.perMl, fa( Math.round( o.price / o.ml ) ) ) : '' ) + '</small></span>' +
					'<span class="ampdp-opt__price">' + ( o.ok && o.pct ? '<del>' + fa( o.regular ) + '</del>' : '' ) + '<span>' + fa( o.price ) + '<i>' + t.toman + '</i></span></span>' +
					'</button>'
				);
			} )
			.join( '' );
		if ( S.snapp && S.snapp.img ) {
			snappRow.querySelector( 'img' ).src = S.snapp.img;
		}

		function render() {
			var o = S.options[ sel ];
			list.querySelectorAll( '.ampdp-opt' ).forEach( function ( b, i ) {
				b.setAttribute( 'aria-checked', i === sel ? 'true' : 'false' );
				b.tabIndex = i === sel || ( -1 === sel && 0 === i ) ? 0 : -1;
			} );
			var max = o ? o.max : 99;
			qty = Math.min( qty, max );
			qtyBox.querySelector( 'output' ).textContent = fa( qty );
			qtyBox.querySelector( '[data-ampdp-step="-1"]' ).disabled = qty <= 1;
			qtyBox.querySelector( '[data-ampdp-step="1"]' ).disabled = qty >= max;
			addBtn.disabled = ! o;
			addBtn.querySelector( 'small' ).textContent = o ? fa( o.price * qty ) + ' ' + t.toman : t.pick;
			var first = installment( o );
			snappRow.hidden = ! o || isNaN( first );
			if ( o && ! isNaN( first ) ) {
				snappRow.querySelector( 'b' ).textContent = fa( first * qty ) + ' ' + t.toman;
			}
		}

		list.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '.ampdp-opt' );
			if ( b && ! b.disabled ) {
				sel = +b.getAttribute( 'data-i' );
				msg.hidden = true;
				render();
			}
		} );
		list.addEventListener( 'keydown', function ( e ) {
			if ( -1 === [ 'ArrowDown', 'ArrowUp', 'ArrowLeft', 'ArrowRight' ].indexOf( e.key ) ) {
				return;
			}
			e.preventDefault();
			var step = 'ArrowDown' === e.key || 'ArrowLeft' === e.key ? 1 : -1;
			var i = sel;
			for ( var k = 0; k < S.options.length; k++ ) {
				i = ( i + step + S.options.length ) % S.options.length;
				if ( S.options[ i ].ok ) {
					break;
				}
			}
			sel = i;
			render();
			list.querySelectorAll( '.ampdp-opt' )[ sel ].focus();
		} );
		qtyBox.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '[data-ampdp-step]' );
			if ( b && ! b.disabled ) {
				qty = Math.max( 1, qty + +b.getAttribute( 'data-ampdp-step' ) );
				render();
			}
		} );

		addBtn.addEventListener( 'click', function () {
			var o = S.options[ sel ];
			if ( ! o ) {
				return;
			}
			msg.hidden = true;
			// The site's «added to cart» toast reads the name from the button.
			addBtn.setAttribute( 'data-product_name', ( product.name || '' ) + ' - ' + o.label );
			var data = { product_id: product.id, variation_id: o.vid, quantity: qty };
			Object.keys( o.attrs ).forEach( function ( key ) {
				data[ 'attributes[' + key + ']' ] = o.attrs[ key ];
			} );
			add(
				addBtn,
				data,
				function ( message, network ) {
					if ( network ) {
						// No answer from the endpoint: the page's own form submits instead.
						syncForm( o, qty );
						var submit = form.querySelector( '.single_add_to_cart_button' );
						if ( submit ) {
							submit.click();
							return;
						}
					}
					msg.textContent = message;
					msg.hidden = false;
				},
				function () {
					S.chosen = true;
					syncForm( o, qty );
					layers.close( true );
				}
			);
		} );

		return {
			open: function () {
				sel = S.sel >= 0 && S.options[ S.sel ].ok ? S.sel : -1;
				if ( -1 === sel ) {
					for ( var i = 0; i < S.options.length; i++ ) {
						if ( S.options[ i ].ok ) {
							sel = i;
							break;
						}
					}
				}
				var qtyInput = form.querySelector( 'input.qty' );
				qty = Math.max( 1, parseInt( qtyInput && qtyInput.value, 10 ) || 1 );
				msg.hidden = true;
				render();
				layers.open( node, list.querySelector( '.ampdp-opt[aria-checked="true"]' ) || list.querySelector( '.ampdp-opt:not(:disabled)' ), true );
			},
		};
	} )();

	/* ====================================================================== specs */

	( function specs() {
		var table = doc.querySelector( '.elementor-element[data-id="c9045ff"] table.shop_attributes' );
		if ( ! table ) {
			return;
		}
		var head = doc.querySelector( '.elementor-element[data-id="fc9bbac"] .elementor-heading-title' );
		if ( head ) {
			head.textContent = t.specs;
		}
		var rows = Array.prototype.filter.call( table.querySelectorAll( 'tr' ), function ( tr ) {
			return ! tr.classList.contains( 'woocommerce-product-attributes-item--attribute_pa_volume' );
		} );
		if ( ! rows.length ) {
			return;
		}
		// Persian digits and commas in the values.
		rows.forEach( function ( tr ) {
			var td = tr.querySelector( 'td' );
			var walker = td && doc.createTreeWalker( td, NodeFilter.SHOW_TEXT );
			while ( walker && walker.nextNode() ) {
				walker.currentNode.textContent = faDigits( walker.currentNode.textContent ).replace( /\s*,\s*/g, '، ' );
			}
		} );
		// Every row shows (a perfume has about seven).
		rows[ rows.length - 1 ].classList.add( 'ampdp-last' );
	} )();

	/* ================================================================= accordions */

	var reviews = null;
	var article = null;
	var about = null;
	var specsAcc = null;
	var notes = null;

	( function accordions() {
		// Every section but the last (related articles) is an accordion; the short «about» text starts open.
		about = accordionWrap( byId( 'dff47aa' ), t.aboutHead );
		if ( about ) {
			about.toggle( true );
		}
		specsAcc = accordion( byId( '8da0201' ), byId( 'fc9bbac' ), [ byId( 'c9045ff' ) ], t.specs );
		notes = accordionWrap( byId( '6a4f2b2' ), t.notesHead );
		article = accordion( byId( 'a57496c' ), byId( 'ae93826' ), [ byId( 'f7b9386' ) ], t.article );
		// Similar products (the last section) is a plain section with a full-width rail, not an accordion.
		var meta = doc.querySelector( '.ngtr-summary-meta' );
		var count = meta ? num( ( meta.textContent.match( /[\d۰-۹]+\s*امتیاز/ ) || [ '' ] )[ 0 ] ) : NaN;
		reviews = accordion( byId( 'eff1c02' ), byId( '6c34468' ), [ byId( '9ace345' ) ], t.reviewsHead, isNaN( count ) ? null : count );
		S.reviewCount = isNaN( count ) ? 0 : count;
	} )();

	/* ======================================================================== FAQ */

	/**
	 * «سوالات متداول» (techpino-product-faq), printed in a <template> by print_faq(): placed next to
	 * the full article, and mobile.css orders it right after it. Its answers open by themselves
	 * (native <details>); no script of its own.
	 */
	var faq = null;
	var faqAcc = null;

	( function faqSection() {
		var tpl = doc.getElementById( 'ampdp-faq' );
		var node = tpl && tpl.content ? tpl.content.querySelector( '.ampdp-faq' ) : null;
		var after = byId( '4f52531' );
		var grid = doc.querySelector( '.elementor-element[data-id="48e24e8"] > .e-con-inner' );
		if ( ! node || ! ( after || grid ) ) {
			return;
		}
		faq = doc.importNode( node, true );
		if ( after ) {
			after.parentNode.insertBefore( faq, after.nextSibling );
		} else {
			grid.appendChild( faq );
		}
		var title = faq.querySelector( '.ampdp-faq__title' );
		faqAcc = accordion(
			faq,
			title,
			Array.prototype.filter.call( faq.children, function ( c ) {
				return c !== title;
			} ),
			null
		);
	} )();

	/* ===================================================================== rating */

	( function rating() {
		var box = byId( '5eddada' );
		if ( ! box ) {
			return;
		}
		var scoreEl = doc.querySelector( '.ngtr-summary .ngtr-score' );
		var score = scoreEl ? num( ( scoreEl.firstChild || scoreEl ).textContent ) : 0;
		var count = S.reviewCount;
		var stars = '<span class="ampdp-rating__stars" aria-hidden="true">★★★★★<span style="width:' + Math.max( 0, Math.min( 100, ( score || 0 ) * 20 ) ) + '%">★★★★★</span></span>';
		var row = el(
			'div',
			'ampdp-rating',
			count > 0
				? stars + '<span>' + fa( score, 1 ) + '</span><span class="ampdp-sep"></span><a href="#reviews">' + fmt( t.reviews, fa( count ) ) + '</a>'
				: stars + '<span>' + t.noReviews + '</span><span class="ampdp-sep"></span><a href="#reviews">' + t.firstReview + '</a>'
		);
		box.appendChild( row );
		row.querySelector( 'a' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( reviews ) {
				reviews.toggle( true );
				smoothTo( reviews.section, 64 );
			}
		} );
	} )();

	/* ============================================================== section menu */

	/**
	 * Past the buy box, a bar of the page's sections sticks to the top (under the header
	 * when the header shows). A tap scrolls to that section (opening it if it is an
	 * accordion); the section being read is marked as the page scrolls.
	 */
	( function sectionMenu() {
		if ( ! cfg.pdp || ! cfg.pdp.tabs ) {
			return;
		}
		var items = [
			[ 'dff47aa', t.tabAbout, about ],
			[ '8da0201', t.tabSpecs, specsAcc ],
			[ '6a4f2b2', t.tabNotes, notes ],
			[ 'a57496c', t.tabArticle, article ],
			[ faq, t.tabFaq, faqAcc ],
			[ 'eff1c02', t.tabReviews, reviews ],
			[ '55c0860', t.tabSimilar ],
		]
			.map( function ( x ) {
				var node = 'string' === typeof x[ 0 ] ? byId( x[ 0 ] ) : x[ 0 ];
				return node && node.getBoundingClientRect().height > 0 ? { node: node, label: x[ 1 ], acc: x[ 2 ] } : null;
			} )
			.filter( Boolean );
		if ( items.length < 3 ) {
			return;
		}
		var hdr = doc.getElementById( 'ampdp-hdr' );
		var nav = el( 'nav', 'ampdp-tabs' );
		nav.setAttribute( 'aria-label', t.sections );
		var track = el( 'div', 'ampdp-tabs__track' );
		nav.appendChild( track );
		var links = items.map( function ( item, i ) {
			if ( ! item.node.id ) {
				item.node.id = 'ampdp-sec-' + item.node.getAttribute( 'data-id' );
			}
			var a = text( 'a', 'ampdp-tab', item.label );
			a.href = '#' + item.node.id;
			a.setAttribute( 'data-i', String( i ) );
			track.appendChild( a );
			return a;
		} );
		body.appendChild( nav );

		var cur = -1;
		var lockUntil = 0;
		function headerShown() {
			return hdr && ! hdr.classList.contains( 'is-hidden' ) && hdr.getBoundingClientRect().bottom > 0 ? hdr.getBoundingClientRect().bottom : 0;
		}
		function mark( i ) {
			if ( i === cur ) {
				return;
			}
			cur = i;
			links.forEach( function ( a, k ) {
				a.classList.toggle( 'is-on', k === i );
				if ( k === i ) {
					a.setAttribute( 'aria-current', 'location' );
				} else {
					a.removeAttribute( 'aria-current' );
				}
			} );
			var a = links[ i ];
			if ( a ) {
				var tr = track.getBoundingClientRect();
				var r = a.getBoundingClientRect();
				if ( r.left < tr.left + 24 || r.right > tr.right - 24 ) {
					track.scrollBy( { left: r.left + r.width / 2 - ( tr.left + tr.width / 2 ), behavior: 'smooth' } );
				}
			}
		}
		var busy = false;
		function update() {
			busy = false;
			var hdrH = headerShown();
			var first = items[ 0 ].node.getBoundingClientRect();
			var last = items[ items.length - 1 ].node.getBoundingClientRect();
			var on = first.top < hdrH + 120 && last.bottom > 80;
			nav.classList.toggle( 'is-on', on );
			body.classList.toggle( 'ampdp-tabs-on', on );
			nav.style.setProperty( '--ampdp-tabs-y', hdrH + 'px' );
			if ( ! on || Date.now() < lockUntil ) {
				if ( ! on ) {
					mark( -1 );
				}
				return;
			}
			// The section being read: the last one whose top passed about a third of the screen.
			var line = Math.max( hdrH + nav.offsetHeight + 24, window.innerHeight * 0.35 );
			var at = -1;
			items.forEach( function ( item, k ) {
				if ( item.node.getBoundingClientRect().top <= line ) {
					at = k;
				}
			} );
			mark( at );
		}
		window.addEventListener(
			'scroll',
			function () {
				if ( ! busy ) {
					busy = true;
					requestAnimationFrame( update );
				}
			},
			{ passive: true }
		);
		window.addEventListener( 'resize', update );
		update();

		track.addEventListener( 'click', function ( e ) {
			var a = e.target.closest( '.ampdp-tab' );
			if ( ! a ) {
				return;
			}
			e.preventDefault();
			var i = +a.getAttribute( 'data-i' );
			var item = items[ i ];
			if ( item.acc ) {
				item.acc.toggle( true );
			}
			mark( i );
			lockUntil = Date.now() + 900;
			var y = item.node.getBoundingClientRect().top + window.pageYOffset;
			// Going up brings the header back (above this bar); going down it slides away.
			var up = ( y < window.pageYOffset || false === cfg.hideHeader ) && hdr && ! hdr.classList.contains( 'ampdp-hdr--pdp' );
			var off = nav.offsetHeight + ( up && hdr ? hdr.offsetHeight : 0 );
			window.scrollTo( { top: Math.max( 0, y - off + 1 ), behavior: 'smooth' } );
		} );
	} )();
} )( jQuery );
