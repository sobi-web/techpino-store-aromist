/* global adsAddToCart, adsPost, adsMySets */
/**
 * «ست‌های من» (account / Techpino panel): buy a saved set, delete one.
 *
 * The panel swaps sections in with AJAX, so everything is delegated from the document. When the
 * panel is there its toast and confirm dialog are used (window.TPUP), otherwise plain messages.
 */
( function () {
	'use strict';

	var L = window.adsMySets || {};

	function endpoint( name ) {
		return String( L.ajax || '' ).replace( '%%endpoint%%', name );
	}

	function toast( message, type ) {
		if ( window.TPUP && window.TPUP.toast ) {
			window.TPUP.toast( message, type );
		}
	}

	function confirmDelete() {
		if ( window.TPUP && window.TPUP.confirm ) {
			var answer = window.TPUP.confirm( L.confirm, L.yes );
			if ( answer && 'function' === typeof answer.then ) {
				return answer;
			}
		}
		return Promise.resolve( window.confirm( L.confirm ) );
	}

	function say( card, message, isError ) {
		var msg = card.querySelector( '.ads-mset__msg' );
		if ( ! msg ) {
			return;
		}
		msg.textContent = message;
		msg.classList.toggle( 'is-error', !! isError );
		msg.hidden = ! message;
	}

	function buy( card, button ) {
		var root = card.closest( '[data-ads-mysets]' );
		var images = Array.prototype.map.call( card.querySelectorAll( '.ads-mset__perfumes img' ), function ( img ) {
			return img.currentSrc || img.src;
		} );
		button.disabled = true;
		button.classList.add( 'is-loading' );
		say( card, '' );
		adsAddToCart(
			{
				product_id: root.getAttribute( 'data-product' ),
				quantity: 1,
				components: ( card.getAttribute( 'data-components' ) || '' ).split( ',' ),
				name: card.getAttribute( 'data-name' ) || ''
			},
			button,
			{ images: images, from: button }
		)
			.then( function () {
				say( card, L.added );
				toast( L.added, 'success' );
			} )
			.catch( function ( err ) {
				say( card, err.message || L.error, true );
				toast( err.message || L.error, 'error' );
			} )
			.then( function () {
				button.disabled = false;
				button.classList.remove( 'is-loading' );
			} );
	}

	function remove( card, button ) {
		var root = card.closest( '[data-ads-mysets]' );
		confirmDelete().then( function ( ok ) {
			if ( ! ok ) {
				return;
			}
			button.disabled = true;
			adsPost( endpoint( 'ads_delete_set' ), { nonce: root.getAttribute( 'data-nonce' ), id: card.getAttribute( 'data-ads-mset' ) } )
				.then( function ( res ) {
					if ( ! res || ! res.success ) {
						throw new Error( ( res && res.message ) || L.error );
					}
					card.classList.add( 'is-removing' );
					window.setTimeout( function () {
						card.remove();
						if ( ! root.querySelector( '[data-ads-mset]' ) ) {
							var empty = root.querySelector( '[data-ads-mysets-empty]' );
							if ( empty ) {
								empty.hidden = false;
							}
							var head = root.querySelector( '.ads-mysets__head .ads-mbtn' );
							if ( head ) {
								head.hidden = true;
							}
						}
					}, 250 );
					toast( L.deleted, 'success' );
				} )
				.catch( function ( err ) {
					button.disabled = false;
					say( card, err.message || L.error, true );
				} );
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var button = e.target.closest ? e.target.closest( '[data-ads-mset-buy], [data-ads-mset-delete]' ) : null;
		if ( ! button ) {
			return;
		}
		var card = button.closest( '[data-ads-mset]' );
		if ( ! card ) {
			return;
		}
		e.preventDefault();
		if ( button.hasAttribute( 'data-ads-mset-buy' ) ) {
			buy( card, button );
		} else {
			remove( card, button );
		}
	} );
} )();
