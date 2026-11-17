/* global adsAddToCart */
/**
 * Single page of a ready-made set: swipe gallery, perfume tabs, quantity, AJAX add to cart with
 * the vials flying to the cart icon, sticky bar on small screens.
 */
( function () {
	'use strict';

	var page = document.querySelector( '.ads-single' );
	if ( ! page ) {
		return;
	}

	/* ---------- Gallery (scroll-snap track: swipe on touch, arrows / thumbs elsewhere) ---------- */

	var track = page.querySelector( '[data-ads-track]' );
	var slides = track ? Array.prototype.slice.call( track.children ) : [];
	var thumbs = Array.prototype.slice.call( page.querySelectorAll( '[data-ads-go]' ) );
	var dots = Array.prototype.slice.call( page.querySelectorAll( '.ads-gallery__dots i' ) );
	var current = 0;
	var target = null; // Slide an arrow / thumbnail is scrolling to.
	var targetTimer;

	function mark( index ) {
		current = index;
		thumbs.forEach( function ( t, i ) {
			t.classList.toggle( 'is-active', i === index );
		} );
		dots.forEach( function ( d, i ) {
			d.classList.toggle( 'is-active', i === index );
		} );
	}

	function go( index ) {
		if ( ! slides.length ) {
			return;
		}
		index = ( index + slides.length ) % slides.length;
		// Scroll the track only (never the page); relative, so it works the same in RTL.
		var delta = slides[ index ].getBoundingClientRect().left - track.getBoundingClientRect().left;
		target = index;
		clearTimeout( targetTimer );
		targetTimer = setTimeout( function () {
			target = null;
		}, 900 );
		track.scrollBy( { left: delta, behavior: 'smooth' } );
		mark( index );
	}

	if ( track && slides.length > 1 ) {
		// The other images load right after the page, so a slide is never empty when it comes in.
		var eager = function () {
			slides.forEach( function ( s ) {
				var img = s.querySelector( 'img[loading="lazy"]' );
				if ( img ) {
					img.loading = 'eager';
				}
			} );
		};
		if ( 'complete' === document.readyState ) {
			eager();
		} else {
			window.addEventListener( 'load', eager );
		}
		track.addEventListener( 'scrollend', function () {
			target = null;
		} );
		if ( 'IntersectionObserver' in window ) {
			var io = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					// While an arrow / thumbnail scrolls past other slides, keep its slide marked.
					if ( entry.isIntersecting && entry.intersectionRatio > 0.6 && null === target ) {
						mark( slides.indexOf( entry.target ) );
					}
				} );
			}, { root: track, threshold: [ 0.6 ] } );
			slides.forEach( function ( s ) {
				io.observe( s );
			} );
		}
		thumbs.forEach( function ( t ) {
			t.addEventListener( 'click', function () {
				go( parseInt( t.getAttribute( 'data-ads-go' ), 10 ) || 0 );
			} );
		} );
		page.querySelectorAll( '[data-ads-step]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				go( current + parseInt( b.getAttribute( 'data-ads-step' ), 10 ) );
			} );
		} );
		track.addEventListener( 'keydown', function ( e ) {
			// RTL: the left arrow moves forward.
			if ( 'ArrowLeft' === e.key || 'ArrowRight' === e.key ) {
				e.preventDefault();
				go( current + ( 'ArrowLeft' === e.key ? 1 : -1 ) );
			}
		} );
	}

	/* ---------- Perfume tabs ---------- */

	var section = page.querySelector( '.ads-perfumes' );
	var tabs = section ? Array.prototype.slice.call( section.querySelectorAll( '[role="tab"]' ) ) : [];
	var panels = section ? Array.prototype.slice.call( section.querySelectorAll( '[role="tabpanel"]' ) ) : [];

	function show( index, focus ) {
		if ( ! tabs[ index ] ) {
			return;
		}
		tabs.forEach( function ( tab, i ) {
			var on = i === index;
			tab.classList.toggle( 'is-active', on );
			tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			tab.setAttribute( 'tabindex', on ? '0' : '-1' );
			panels[ i ].classList.toggle( 'is-active', on );
			panels[ i ].hidden = ! on;
		} );
		if ( focus ) {
			tabs[ index ].focus();
		}
		// Keep the active tab visible in the scrollable tab bar (mobile).
		var bar = tabs[ index ].parentNode;
		var t = tabs[ index ];
		if ( bar.scrollWidth > bar.clientWidth ) {
			var tr = t.getBoundingClientRect();
			var br = bar.getBoundingClientRect();
			bar.scrollBy( { left: tr.left - br.left - ( br.width - tr.width ) / 2, behavior: 'smooth' } );
		}
	}

	if ( tabs.length ) {
		section.classList.add( 'is-tabs' );
		show( 0 );
		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				show( i );
			} );
			tab.addEventListener( 'keydown', function ( e ) {
				var next = null;
				if ( 'ArrowLeft' === e.key ) {
					next = ( i + 1 ) % tabs.length;
				} else if ( 'ArrowRight' === e.key ) {
					next = ( i - 1 + tabs.length ) % tabs.length;
				} else if ( 'Home' === e.key ) {
					next = 0;
				} else if ( 'End' === e.key ) {
					next = tabs.length - 1;
				}
				if ( null !== next ) {
					e.preventDefault();
					show( next, true );
				}
			} );
		} );

		// «داخل این ست» links open their perfume.
		page.querySelectorAll( '[data-ads-show]' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				show( parseInt( link.getAttribute( 'data-ads-show' ), 10 ) || 0 );
				section.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			} );
		} );

		var hash = window.location.hash.replace( '#', '' );
		panels.forEach( function ( panel, i ) {
			if ( hash && panel.id === hash ) {
				show( i );
			}
		} );
	}

	/* ---------- Quantity ---------- */

	var qty = page.querySelector( '.ads-qty__input' );
	page.querySelectorAll( '.ads-qty__btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var step = parseInt( btn.getAttribute( 'data-step' ), 10 );
			var max = parseInt( qty.getAttribute( 'max' ), 10 ) || Infinity;
			qty.value = Math.min( max, Math.max( 1, ( parseInt( qty.value, 10 ) || 1 ) + step ) );
		} );
	} );

	/* ---------- AJAX add (falls back to the normal form post when JS fails) ---------- */

	var form = page.querySelector( '[data-ads-buy]' );
	var msg = page.querySelector( '.ads-buybox__msg' );
	var images = Array.prototype.map.call( page.querySelectorAll( '.ads-inside__list img' ), function ( img ) {
		return img.currentSrc || img.src;
	} );
	var busy = false;

	function add( button ) {
		if ( busy || ! form ) {
			return;
		}
		busy = true;
		var submit = form.querySelector( '.ads-buybox__add' );
		var buttons = [ submit, page.querySelector( '[data-ads-sticky-add]' ) ].filter( Boolean );
		buttons.forEach( function ( b ) {
			b.disabled = true;
			b.classList.add( 'is-loading' );
		} );
		msg.hidden = true;
		adsAddToCart(
			{ product_id: page.getAttribute( 'data-product-id' ), quantity: qty ? qty.value : 1 },
			button || submit,
			{ images: images, from: button || submit }
		)
			.then( function () {
				msg.className = 'ads-buybox__msg is-ok';
				msg.textContent = msg.getAttribute( 'data-ok' ) || '✓';
				msg.hidden = false;
			} )
			.catch( function ( err ) {
				msg.className = 'ads-buybox__msg is-error';
				msg.textContent = err.message;
				msg.hidden = false;
				if ( button && button.hasAttribute( 'data-ads-sticky-add' ) ) {
					page.querySelector( '#ads-buy' ).scrollIntoView( { behavior: 'smooth', block: 'center' } );
				}
			} )
			.then( function () {
				busy = false;
				buttons.forEach( function ( b ) {
					b.disabled = false;
					b.classList.remove( 'is-loading' );
				} );
			} );
	}

	if ( form && window.adsAddToCart && window.fetch ) {
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			add( e.submitter || form.querySelector( '.ads-buybox__add' ) );
		} );
	}

	/* ---------- Sticky bar on small screens while the buy box is out of view ---------- */

	var bar = page.querySelector( '[data-ads-sticky]' );
	var box = page.querySelector( '.ads-buybox' );
	if ( bar && window.adsWatchBottomInset ) {
		window.adsWatchBottomInset();
	}
	if ( bar && box ) {
		var ticking = false;
		var update = function () {
			ticking = false;
			// Shown once the buy box has scrolled above the screen.
			var hide = box.getBoundingClientRect().bottom > 0;
			if ( bar.hidden && ! hide && window.adsDodge ) {
				bar.hidden = false;
				window.adsDodge( bar );
			}
			bar.hidden = hide;
			document.documentElement.classList.toggle( 'ads-has-stickybar', ! hide );
		};
		window.addEventListener( 'scroll', function () {
			if ( ! ticking ) {
				ticking = true;
				window.requestAnimationFrame( update );
			}
		}, { passive: true } );
		update();
		bar.querySelector( '[data-ads-sticky-add]' ).addEventListener( 'click', function () {
			add( this );
		} );
	}
} )();
