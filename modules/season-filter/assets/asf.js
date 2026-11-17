/**
 * Aromist Season Filter: با کلیک روی دکمه، اسلایدهای Loop Carousel کنارش را با AJAX عوض می‌کند.
 * جواب هر دکمه در حافظه کش می‌شود و با اولین نشانهٔ قصد کاربر (hover / touch / focus) پیش‌دریافت می‌شود.
 */
( function () {
	'use strict';

	var cfg = window.ASF || {};
	var cache = Object.create( null );
	var seenStyles = null;
	var TIMEOUT = 15000;

	if ( ! cfg.ajax || document.body.classList.contains( 'elementor-editor-active' ) ) {
		return;
	}

	function seen() {
		if ( ! seenStyles ) {
			seenStyles = new Set();
			[].forEach.call( document.querySelectorAll( 'style' ), function ( s ) {
				seenStyles.add( s.textContent );
			} );
		}
		return seenStyles;
	}

	/** پاسخ سرور (HTML کامل ویجت) را به آرایهٔ اسلاید تبدیل می‌کند؛ استایل‌های تکراری را حذف می‌کند. */
	function parse( html ) {
		var doc = new DOMParser().parseFromString( html, 'text/html' );
		var known = seen();

		[].forEach.call( doc.querySelectorAll( 'style' ), function ( s ) {
			var text = s.textContent;
			if ( known.has( text ) ) {
				s.remove();
			} else if ( ! s.closest( '.swiper-slide' ) ) {
				known.add( text );
				document.head.appendChild( s.cloneNode( true ) );
			}
		} );

		return {
			slides: [].map.call( doc.querySelectorAll( '.swiper-wrapper > .swiper-slide' ), function ( n ) {
				return n.outerHTML;
			} )
		};
	}

	function load( g, pill ) {
		var key = g.post + ':' + g.filter + ':' + pill;
		if ( cache[ key ] ) {
			return cache[ key ];
		}
		var url = cfg.ajax + '?action=' + encodeURIComponent( cfg.action ) +
			'&post=' + encodeURIComponent( g.post ) +
			'&filter=' + encodeURIComponent( g.filter ) +
			'&pill=' + encodeURIComponent( pill );

		// درخواست کند نباید دکمه‌ها را برای همیشه در حالت «در حال دریافت» نگه دارد.
		var ctrl = window.AbortController ? new AbortController() : null;
		var timer = ctrl ? setTimeout( function () { ctrl.abort(); }, TIMEOUT ) : 0;

		cache[ key ] = fetch( url, { credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined } )
			.then( function ( r ) {
				clearTimeout( timer );
				if ( ! r.ok ) {
					throw new Error( 'http ' + r.status );
				}
				return r.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success || ! json.data ) {
					throw new Error( 'bad response' );
				}
				return parse( json.data.html );
			} )
			.catch( function ( err ) {
				clearTimeout( timer );
				delete cache[ key ];
				throw err;
			} );
		return cache[ key ];
	}

	/** اسلایدهای Swiper موجود را با اسلایدهای جدید عوض می‌کند. */
	function swap( widget, data ) {
		var el = widget.querySelector( '.swiper, .swiper-container' );
		var wrap = el && el.querySelector( '.swiper-wrapper' );
		if ( ! wrap ) {
			return;
		}
		var sw = el.swiper;
		var loop = false;

		if ( sw ) {
			if ( sw.autoplay && sw.autoplay.running ) {
				sw.autoplay.stop();
			}
			if ( undefined === sw.__asfLoop ) {
				sw.__asfLoop = !! sw.params.loop;
			}
			loop = sw.__asfLoop;
			if ( sw.params.loop ) {
				sw.loopDestroy();
			}
		}

		// بعضی شورت‌کدها (مثل حجم‌ها) CSS خود را فقط داخل اولین کارت چاپ می‌کنند؛ قبل از حذف اسلایدها به head می‌بریم.
		[].forEach.call( wrap.querySelectorAll( ':scope > .swiper-slide style' ), function ( s ) {
			document.head.appendChild( s );
		} );
		[].forEach.call( wrap.querySelectorAll( ':scope > .swiper-slide' ), function ( n ) {
			n.remove();
		} );
		wrap.insertAdjacentHTML( 'beforeend', data.slides.join( '' ) );

		if ( sw ) {
			var perView = 'number' === typeof sw.params.slidesPerView ? Math.ceil( sw.params.slidesPerView ) : 1;
			// حالت loop با اسلاید کمتر از تعداد نمایش خراب می‌شود.
			sw.params.loop = loop && data.slides.length > perView;
			sw.update();
			if ( sw.params.loop ) {
				sw.loopCreate();
				sw.update();
				sw.slideToLoop( 0, 0, false );
			} else {
				sw.slideTo( 0, 0, false );
			}
		}
	}

	function init( root ) {
		if ( root.__asf ) {
			return;
		}
		root.__asf = true;

		var g = {
			post: root.getAttribute( 'data-post' ),
			filter: root.getAttribute( 'data-filter' ),
			seq: 0
		};
		var pills = [].slice.call( root.querySelectorAll( '.asf__pill' ) );
		var status = root.querySelector( '.asf__status' );
		var warmed = false;

		function widgetEl() {
			var id = root.getAttribute( 'data-target' );
			if ( ! id ) {
				return null;
			}
			// ویجت فیلتر ممکن است با «شناسهٔ کاروسل» جای دیگری از صفحه باشد؛ اول سند خودش، بعد کل صفحه.
			var sel = '.elementor-element[data-id="' + id + '"]';
			var scope = root.closest( '[data-elementor-type]' ) || document;
			return scope.querySelector( sel ) || document.querySelector( sel );
		}

		function activate( btn ) {
			pills.forEach( function ( p ) {
				var on = p === btn;
				p.classList.toggle( 'is-active', on );
				p.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			} );
		}

		function say( text, state ) {
			status.textContent = text || '';
			if ( state ) {
				status.setAttribute( 'data-state', state );
			} else {
				status.removeAttribute( 'data-state' );
			}
		}

		function prefetch( btn ) {
			load( g, btn.getAttribute( 'data-pill' ) ).catch( function () {} );
		}

		function warmRest() {
			if ( warmed ) {
				return;
			}
			warmed = true;
			pills.forEach( prefetch );
		}

		[ 'pointerover', 'touchstart', 'focusin' ].forEach( function ( type ) {
			root.addEventListener( type, function ( e ) {
				var btn = e.target.closest ? e.target.closest( '.asf__pill' ) : null;
				if ( btn ) {
					prefetch( btn );
				}
			}, { passive: true } );
		} );

		root.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.asf__pill' );
			if ( ! btn || 'true' === btn.getAttribute( 'aria-pressed' ) ) {
				return;
			}
			var widget = widgetEl();
			if ( ! widget ) {
				return;
			}

			var previous = pills.filter( function ( p ) {
				return 'true' === p.getAttribute( 'aria-pressed' );
			} )[ 0 ];
			var token = ++g.seq;

			activate( btn );
			say( '' );
			widget.classList.add( 'asf-busy' );
			widget.setAttribute( 'aria-busy', 'true' );

			load( g, btn.getAttribute( 'data-pill' ) )
				.then( function ( data ) {
					// بقیهٔ دکمه‌ها را بعد از رسیدن جواب اول و در زمان بیکاری پیش‌دریافت می‌کنیم.
					( window.requestIdleCallback || window.setTimeout )( warmRest );
					if ( token !== g.seq ) {
						return;
					}
					swap( widget, data );
					if ( data.slides.length ) {
						say( data.slides.length.toLocaleString( 'fa-IR' ) + ' محصول نمایش داده شد' );
					} else {
						say( root.getAttribute( 'data-empty' ), 'empty' );
					}
				} )
				.catch( function ( err ) {
					if ( window.console && console.warn ) {
						console.warn( '[aromist-season-filter]', err );
					}
					if ( token !== g.seq ) {
						return;
					}
					activate( previous );
					say( root.getAttribute( 'data-error' ), 'error' );
				} )
				.then( function () {
					if ( token === g.seq ) {
						widget.classList.remove( 'asf-busy' );
						widget.removeAttribute( 'aria-busy' );
					}
				} );
		} );
	}

	function boot() {
		[].forEach.call( document.querySelectorAll( '[data-asf]' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

	window.addEventListener( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/aromist_season_filter.default', function ( $el ) {
				var node = $el[ 0 ] && $el[ 0 ].querySelector( '[data-asf]' );
				if ( node ) {
					init( node );
				}
			} );
		}
	} );
}() );
