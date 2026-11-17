/**
 * Perfume blocks (set page tabs, builder «جزئیات» window): gallery thumbnails switch the big image,
 * «نمایش بیشتر» shows the rest of the notes. Delegated, so blocks added later work too.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( e ) {
		var more = e.target.closest( '[data-ads-more]' );
		if ( more ) {
			var extra = more.parentNode.querySelector( '.ads-note__extra' );
			if ( ! extra ) {
				return;
			}
			var open = extra.hidden;
			extra.hidden = ! open;
			more.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			if ( ! more.hasAttribute( 'data-more' ) ) {
				more.setAttribute( 'data-more', more.textContent );
			}
			more.textContent = open ? more.getAttribute( 'data-less' ) : more.getAttribute( 'data-more' );
			return;
		}

		var thumb = e.target.closest( '[data-ads-pthumb]' );
		if ( thumb ) {
			var gallery = thumb.closest( '[data-ads-pgallery]' );
			var main = gallery && gallery.querySelector( '[data-ads-pmain]' );
			if ( ! main ) {
				return;
			}
			var srcset = thumb.getAttribute( 'data-srcset' );
			if ( srcset ) {
				main.setAttribute( 'srcset', srcset );
			} else {
				main.removeAttribute( 'srcset' );
			}
			main.src = thumb.getAttribute( 'data-src' );
			gallery.querySelectorAll( '[data-ads-pthumb]' ).forEach( function ( t ) {
				t.classList.toggle( 'is-active', t === thumb );
				t.setAttribute( 'aria-pressed', t === thumb ? 'true' : 'false' );
			} );
		}
	} );
} )();
