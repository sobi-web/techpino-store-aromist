/* global adsAddToCart, adsPost */
/**
 * «ساخت دیسکاوری ست»: choose N perfumes, live price, search / filters, perfume details window,
 * save to «ست‌های من», mobile bottom sheet, AJAX add to cart.
 * Prices follow ADS_Pricing exactly: regular = round(sum + fee), price = round(sum × (1 − discount) + fee).
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-ads-builder]' );
	if ( ! root || ! root.querySelector( '[data-ads-grid]' ) ) {
		return;
	}

	var cfg = JSON.parse( root.getAttribute( 'data-ads-builder' ) || '{}' );
	var t = cfg.i18n || {};
	var size = cfg.size || 4;
	var STORE = 'ads_builder_v2';
	var info = {};
	try {
		info = JSON.parse( ( root.querySelector( '[data-ads-info-json]' ) || {} ).textContent || '{}' );
	} catch ( e ) {}

	var grid = root.querySelector( '[data-ads-grid]' );
	var cards = Array.prototype.slice.call( grid.querySelectorAll( '.ads-pcard' ) );
	var byId = {};
	cards.forEach( function ( card ) {
		byId[ card.getAttribute( 'data-id' ) ] = card;
	} );

	var tray = root.querySelector( '[data-ads-tray]' );
	var slotsEl = root.querySelector( '[data-ads-slots]' );
	var hintEl = root.querySelector( '[data-ads-hint]' );
	var addButtons = Array.prototype.slice.call( root.querySelectorAll( '[data-ads-add]' ) );
	var saveBtn = root.querySelector( '[data-ads-save-set]' );
	var nameInput = root.querySelector( '[data-ads-name]' );
	var doneEl = root.querySelector( '[data-ads-done]' );
	var clearEl = root.querySelector( '[data-ads-clear]' );
	var noneEl = root.querySelector( '[data-ads-none]' );
	var shownEl = root.querySelector( '[data-ads-shown]' );
	var backdrop = root.querySelector( '.ads-sheet-backdrop' );
	var modal = root.querySelector( '[data-ads-modal]' );
	var selects = Array.prototype.slice.call( root.querySelectorAll( '[data-ads-filter]' ) );
	var resetEl = root.querySelector( '[data-ads-reset]' );

	var selected = [];
	var busy = false;
	var editing = cfg.editing || null;
	var filters = { q: '', f: {} };
	var lastFocus = null;

	/* ---------- helpers ---------- */

	function fa( value ) {
		return String( value ).replace( /\d/g, function ( d ) {
			return '۰۱۲۳۴۵۶۷۸۹'[ d ];
		} );
	}

	function fill( template, values ) {
		var i = 0;
		return String( template || '' ).replace( /%(\d\$)?s/g, function ( m, n ) {
			var idx = n ? parseInt( n, 10 ) - 1 : i++;
			return values[ idx ];
		} );
	}

	function money( amount ) {
		var c = cfg.currency || {};
		var decimals = c.decimals || 0;
		var parts = Number( amount ).toFixed( decimals ).split( '.' );
		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, c.thousand || ',' );
		var num = parts.join( c.decimal || '.' );
		var sym = '<span class="woocommerce-Price-currencySymbol">' + ( c.symbol || '' ) + '</span>';
		var out;
		switch ( c.pos ) {
			case 'left': out = sym + num; break;
			case 'left_space': out = sym + '&nbsp;' + num; break;
			case 'right': out = num + sym; break;
			default: out = num + '&nbsp;' + sym;
		}
		return '<span class="woocommerce-Price-amount amount"><bdi>' + out + '</bdi></span>';
	}

	function roundPrice( price ) {
		var step = parseInt( cfg.round, 10 ) || 0;
		if ( step > 1 ) {
			var rounded = Math.round( price / step ) * step;
			return rounded <= 0 && price > 0 ? Math.ceil( price / step ) * step : rounded;
		}
		var f = Math.pow( 10, ( cfg.currency && cfg.currency.decimals ) || 0 );
		return Math.round( price * f ) / f;
	}

	function normalize( text ) {
		return String( text || '' )
			.toLowerCase()
			.replace( /[يى]/g, 'ی' )
			.replace( /ك/g, 'ک' )
			.replace( /[ۀة]/g, 'ه' )
			.replace( /[أإآ]/g, 'ا' )
			.replace( /[۰-۹]/g, function ( d ) {
				return String( '۰۱۲۳۴۵۶۷۸۹'.indexOf( d ) );
			} )
			.replace( /[‌‏\s\-_]+/g, '' );
	}

	function el( tag, cls, text ) {
		var node = document.createElement( tag );
		if ( cls ) {
			node.className = cls;
		}
		if ( undefined !== text && null !== text ) {
			node.textContent = text;
		}
		return node;
	}

	// [ [ name, url ], … ] → names joined with «، », each one a link to its own page.
	function links( list ) {
		var frag = document.createDocumentFragment();
		( list || [] ).forEach( function ( item, i ) {
			if ( i ) {
				frag.appendChild( document.createTextNode( '، ' ) );
			}
			var node;
			if ( item[ 1 ] ) {
				node = el( 'a', '', item[ 0 ] );
				node.href = item[ 1 ];
				node.target = '_blank';
				node.rel = 'noopener';
			} else {
				node = el( 'span', '', item[ 0 ] );
			}
			frag.appendChild( node );
		} );
		return frag;
	}

	function store() {
		try {
			window.sessionStorage.setItem( STORE, JSON.stringify( selected ) );
		} catch ( e ) {}
	}

	function restore() {
		if ( cfg.fresh ) {
			return ( cfg.initial || [] ).map( String );
		}
		try {
			var saved = JSON.parse( window.sessionStorage.getItem( STORE ) || '[]' );
			return Array.isArray( saved ) ? saved.map( String ) : [];
		} catch ( e ) {
			return [];
		}
	}

	function cardData( id ) {
		var card = byId[ id ];
		return {
			id: id,
			name: card.getAttribute( 'data-name' ),
			price: parseFloat( card.getAttribute( 'data-price' ) ) || 0,
			img: card.getAttribute( 'data-img' )
		};
	}

	function isMobile() {
		return window.innerWidth < 992;
	}

	/* ---------- render ---------- */

	function renderSlots( justAdded ) {
		var items = slotsEl.querySelectorAll( '.ads-slot' );
		for ( var i = 0; i < items.length; i++ ) {
			var li = items[ i ];
			var id = selected[ i ];
			li.className = 'ads-slot' + ( id ? ' is-filled' : ' is-empty' ) + ( id && id === justAdded ? ' is-new' : '' );
			li.textContent = '';
			li.appendChild( el( 'span', 'ads-slot__num', fa( i + 1 ) ) );
			if ( id ) {
				var p = cardData( id );
				var img = el( 'img', 'ads-slot__img' );
				img.src = p.img;
				img.alt = '';
				img.width = 56;
				img.height = 56;
				li.appendChild( img );
				var label = el( 'span', 'ads-slot__label' );
				label.appendChild( el( 'strong', '', p.name ) );
				var price = el( 'span', 'ads-slot__price' );
				price.innerHTML = money( p.price );
				label.appendChild( price );
				li.appendChild( label );
				var btn = el( 'button', 'ads-slot__remove', '×' );
				btn.type = 'button';
				btn.setAttribute( 'data-ads-remove', id );
				btn.setAttribute( 'aria-label', fill( t.remove, [ p.name ] ) );
				li.appendChild( btn );
			} else {
				li.appendChild( el( 'span', 'ads-slot__label', t.empty || '' ) );
			}
		}
	}

	function render( justAdded ) {
		var full = selected.length >= size;

		cards.forEach( function ( card ) {
			var id = card.getAttribute( 'data-id' );
			var on = selected.indexOf( id ) !== -1;
			var pick = card.querySelector( '[data-ads-pick]' );
			card.classList.toggle( 'is-picked', on );
			if ( pick ) {
				pick.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			}
		} );
		grid.classList.toggle( 'is-full', full );

		renderSlots( justAdded );

		var count = fill( t.count, [ fa( selected.length ), fa( size ) ] );
		root.querySelectorAll( '[data-ads-count], [data-ads-bar-count]' ).forEach( function ( node ) {
			node.textContent = count;
		} );

		var sum = 0;
		selected.forEach( function ( id ) {
			sum += cardData( id ).price;
		} );
		var fee = parseFloat( cfg.fee ) || 0;
		var discount = parseFloat( cfg.discount ) || 0;
		var regular = roundPrice( sum + fee );
		var price = roundPrice( sum * ( 1 - discount / 100 ) + fee );
		regular = Math.max( regular, price );

		root.querySelector( '[data-ads-sum]' ).innerHTML = selected.length ? money( sum + fee ) : '—';
		root.querySelector( '[data-ads-total]' ).innerHTML = selected.length ? money( price ) : '—';
		root.querySelector( '[data-ads-save-row]' ).hidden = ! ( selected.length && regular > price );
		root.querySelector( '[data-ads-save]' ).innerHTML = regular > price ? '− ' + money( regular - price ) : '';
		root.querySelector( '[data-ads-bar-total]' ).innerHTML = selected.length ? money( price ) : '';

		var dots = root.querySelectorAll( '[data-ads-dots] i' );
		for ( var i = 0; i < dots.length; i++ ) {
			var id = selected[ i ];
			dots[ i ].className = id ? 'is-on' : '';
			dots[ i ].style.backgroundImage = id ? 'url("' + cardData( id ).img + '")' : '';
		}

		addButtons.forEach( function ( b ) {
			b.disabled = ! full || busy;
		} );
		if ( saveBtn ) {
			saveBtn.disabled = ! full || busy;
		}
		root.classList.toggle( 'is-full', full );
		hintEl.classList.remove( 'is-error', 'is-ok' );
		hintEl.textContent = full ? ( t.ready || '' ) : fill( t.more, [ fa( size - selected.length ) ] );
		clearEl.hidden = ! selected.length;

		store();
	}

	/* ---------- picking ---------- */

	function flyToSlot( id ) {
		var card = byId[ id ];
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		if ( ! card || reduce || ! document.body.animate ) {
			return;
		}
		var target = isMobile()
			? root.querySelectorAll( '[data-ads-dots] i' )[ selected.indexOf( id ) ]
			: slotsEl.children[ selected.indexOf( id ) ];
		var from = card.querySelector( '.ads-pcard__media img' );
		if ( ! target || ! from ) {
			return;
		}
		var a = from.getBoundingClientRect();
		var b = target.getBoundingClientRect();
		if ( b.bottom < 0 || b.top > window.innerHeight ) {
			return;
		}
		var img = el( 'img', 'ads-fly' );
		img.src = card.getAttribute( 'data-img' );
		img.alt = '';
		img.style.left = ( a.left + a.width / 2 - 28 ) + 'px';
		img.style.top = ( a.top + a.height / 2 - 28 ) + 'px';
		document.body.appendChild( img );
		var dx = b.left + b.width / 2 - ( a.left + a.width / 2 );
		var dy = b.top + b.height / 2 - ( a.top + a.height / 2 );
		var anim = img.animate(
			[
				{ transform: 'translate(0,0) scale(1.2)', opacity: 0.9 },
				{ transform: 'translate(' + dx + 'px,' + dy + 'px) scale(.45)', opacity: 0.4 }
			],
			{ duration: 520, easing: 'cubic-bezier(.4,0,.2,1)', fill: 'forwards' }
		);
		anim.onfinish = function () {
			img.remove();
		};
	}

	function toggle( id ) {
		var at = selected.indexOf( id );
		if ( at !== -1 ) {
			selected.splice( at, 1 );
			render();
			return;
		}
		var card = byId[ id ];
		if ( ! card || '1' !== card.getAttribute( 'data-available' ) ) {
			return;
		}
		if ( selected.length >= size ) {
			hintEl.textContent = t.full || '';
			hintEl.classList.add( 'is-error' );
			tray.classList.remove( 'is-shake' );
			void tray.offsetWidth; // Restart the animation.
			tray.classList.add( 'is-shake' );
			if ( isMobile() ) {
				openSheet();
			}
			return;
		}
		selected.push( id );
		doneEl.hidden = true;
		render( id );
		flyToSlot( id );
	}

	/* ---------- filters ---------- */

	function has( card, key, value ) {
		return ( ' ' + ( card.getAttribute( 'data-f-' + key ) || '' ) + ' ' ).indexOf( ' ' + value + ' ' ) !== -1;
	}

	// Search + every chosen select box, except `skip` (to count the options of that box).
	function matches( card, q, skip ) {
		if ( q && normalize( card.getAttribute( 'data-search' ) ).indexOf( q ) === -1 ) {
			return false;
		}
		return Object.keys( filters.f ).every( function ( key ) {
			return key === skip || ! filters.f[ key ] || has( card, key, filters.f[ key ] );
		} );
	}

	function applyFilters() {
		var q = normalize( filters.q );
		var visible = 0;
		cards.forEach( function ( card ) {
			var ok = matches( card, q, null );
			card.hidden = ! ok;
			if ( ok ) {
				visible++;
			}
		} );

		// Each option shows how many perfumes it would leave with the other choices.
		selects.forEach( function ( select ) {
			var key = select.getAttribute( 'data-ads-filter' );
			var pool = cards.filter( function ( card ) {
				return matches( card, q, key );
			} );
			Array.prototype.forEach.call( select.options, function ( option ) {
				if ( ! option.value ) {
					return;
				}
				var n = pool.filter( function ( card ) {
					return has( card, key, option.value );
				} ).length;
				option.textContent = option.getAttribute( 'data-name' ) + ' (' + fa( n ) + ')';
				option.disabled = ! n && select.value !== option.value;
			} );
			select.classList.toggle( 'is-set', !! select.value );
		} );
		if ( resetEl ) {
			resetEl.hidden = ! selects.some( function ( select ) {
				return select.value;
			} );
		}

		noneEl.hidden = visible > 0;
		if ( shownEl ) {
			shownEl.textContent = fa( visible ) + ' ' + ( shownEl.getAttribute( 'data-unit' ) || shownEl.textContent.replace( /^[۰-۹0-9٬,]+\s*/, '' ) );
		}
	}
	if ( shownEl ) {
		shownEl.setAttribute( 'data-unit', shownEl.textContent.trim().replace( /^[۰-۹0-9٬,]+\s*/, '' ) );
	}

	/* ---------- mobile sheet ---------- */

	function openSheet() {
		if ( ! isMobile() ) {
			return;
		}
		root.classList.add( 'is-sheet-open' );
		backdrop.hidden = false;
		document.documentElement.classList.add( 'ads-noscroll' );
		var close = tray.querySelector( '.ads-tray__close' );
		if ( close ) {
			close.focus( { preventScroll: true } );
		}
	}

	function closeSheet() {
		root.classList.remove( 'is-sheet-open' );
		backdrop.hidden = true;
		if ( modal.hidden ) {
			document.documentElement.classList.remove( 'ads-noscroll' );
		}
	}

	/* ---------- details window ---------- */

	var modalFor = null;

	function modalPickLabel() {
		var on = selected.indexOf( modalFor ) !== -1;
		var btn = modal.querySelector( '[data-ads-modal-pick]' );
		var card = byId[ modalFor ];
		var available = card && '1' === card.getAttribute( 'data-available' );
		btn.textContent = on ? ( t.removeIt || '' ) : ( available ? ( t.add || '' ) : ( t.out || '' ) );
		btn.disabled = ! on && ( ! available || selected.length >= size );
		btn.classList.toggle( 'ads-btn--primary', ! on );
		btn.classList.toggle( 'ads-btn--ghost', on );
	}

	function openModal( id ) {
		var data = info[ id ];
		if ( ! data ) {
			return;
		}
		modalFor = id;
		lastFocus = document.activeElement;
		var box = modal.querySelector( '[data-ads-modal-content]' );
		box.textContent = '';

		var wrap = el( 'div', 'ads-pinfo ads-pinfo--modal' );
		var images = data.images || [];
		var gallery = el( 'div', 'ads-pinfo__gallery' );
		gallery.setAttribute( 'data-ads-pgallery', '' );
		var media = el( 'div', 'ads-pinfo__media' );
		var img = el( 'img' );
		img.src = images.length ? images[ 0 ][ 0 ] : '';
		img.alt = data.name;
		img.setAttribute( 'data-ads-pmain', '1' );
		media.appendChild( img );
		gallery.appendChild( media );
		if ( images.length > 1 ) {
			var thumbs = el( 'div', 'ads-pinfo__thumbs' );
			images.forEach( function ( image, i ) {
				var b = el( 'button', 'ads-pinfo__thumb' + ( i ? '' : ' is-active' ) );
				b.type = 'button';
				b.setAttribute( 'data-ads-pthumb', '' );
				b.setAttribute( 'data-src', image[ 0 ] );
				b.setAttribute( 'aria-pressed', i ? 'false' : 'true' );
				b.setAttribute( 'aria-label', fill( t.image, [ fa( i + 1 ) ] ) );
				var ti = el( 'img' );
				ti.src = image[ 1 ] || image[ 0 ];
				ti.alt = '';
				ti.loading = 'lazy';
				b.appendChild( ti );
				thumbs.appendChild( b );
			} );
			gallery.appendChild( thumbs );
		}
		wrap.appendChild( gallery );

		var body = el( 'div', 'ads-pinfo__body' );
		var head = el( 'div', 'ads-pinfo__head' );
		if ( data.brand ) {
			head.appendChild( el( 'span', 'ads-pinfo__brand', data.brand ) );
		}
		var title = el( 'h3', 'ads-pinfo__name', data.name );
		title.id = 'ads-modal-title';
		head.appendChild( title );
		if ( data.english ) {
			var en = el( 'span', 'ads-pinfo__en', data.english );
			en.dir = 'ltr';
			head.appendChild( en );
		}
		body.appendChild( head );

		if ( data.family && data.family.length ) {
			var fam = el( 'p', 'ads-family' );
			fam.appendChild( el( 'span', '', ( t.family || '' ) + ':' ) );
			fam.appendChild( document.createTextNode( ' ' ) );
			var famNames = el( 'strong' );
			famNames.appendChild( links( data.family ) );
			fam.appendChild( famNames );
			body.appendChild( fam );
		}
		if ( data.excerpt ) {
			body.appendChild( el( 'p', 'ads-pinfo__excerpt', data.excerpt ) );
		}
		if ( data.specs && data.specs.length ) {
			var dl = el( 'dl', 'ads-specs' );
			data.specs.forEach( function ( spec ) {
				var row = el( 'div', 'ads-spec' );
				var dd = el( 'dd' );
				dd.appendChild( links( spec[ 1 ] ) );
				row.appendChild( el( 'dt', '', spec[ 0 ] ) );
				row.appendChild( dd );
				dl.appendChild( row );
			} );
			body.appendChild( dl );
		}
		var levels = t.notes || {};
		var notes = el( 'div', 'ads-notes' );
		[ 'top', 'heart', 'base' ].forEach( function ( level ) {
			var list = data.notes && data.notes[ level ];
			if ( ! list || ! list.length ) {
				return;
			}
			var row = el( 'div', 'ads-note ads-note--' + level );
			var icon = el( 'span', 'ads-note__icon' );
			icon.setAttribute( 'aria-hidden', 'true' );
			icon.innerHTML = '<svg viewBox="0 0 32 28" width="30" height="26"><path class="t" d="M16 1 22.4 11H9.6z"/><path class="h" d="M9 12h14l5.6 8.5H3.4z"/><path class="b" d="M2.8 21.5h26.4L31 27H1z"/></svg>';
			row.appendChild( icon );
			row.appendChild( el( 'span', 'ads-note__label', ( levels[ level ] || '' ) + ':' ) );
			var shown = parseInt( cfg.notesShown, 10 ) || 5;
			var names = el( 'span', 'ads-note__list' );
			names.appendChild( links( list.slice( 0, shown ) ) );
			if ( list.length > shown ) {
				var extra = el( 'span', 'ads-note__extra' );
				extra.hidden = true;
				extra.appendChild( document.createTextNode( '، ' ) );
				extra.appendChild( links( list.slice( shown ) ) );
				names.appendChild( extra );
				var more = el( 'button', 'ads-note__more', fill( t.moreNotes, [ fa( list.length - shown ) ] ) );
				more.type = 'button';
				more.setAttribute( 'data-ads-more', '' );
				more.setAttribute( 'aria-expanded', 'false' );
				more.setAttribute( 'data-less', t.less || '' );
				names.appendChild( more );
			}
			row.appendChild( names );
			notes.appendChild( row );
		} );
		if ( notes.children.length ) {
			body.appendChild( notes );
		}
		if ( data.url ) {
			var link = el( 'a', 'ads-link ads-pinfo__more', t.view || '' );
			link.href = data.url;
			link.target = '_blank';
			link.rel = 'noopener';
			body.appendChild( link );
		}
		wrap.appendChild( body );
		box.appendChild( wrap );

		modal.querySelector( '[data-ads-modal-price]' ).innerHTML = ( t.price || '' ) + ': ' + money( cardData( id ).price );
		modalPickLabel();

		modal.hidden = false;
		document.documentElement.classList.add( 'ads-noscroll' );
		window.requestAnimationFrame( function () {
			modal.classList.add( 'is-open' );
		} );
		modal.querySelector( '.ads-modal__close' ).focus( { preventScroll: true } );
	}

	function closeModal() {
		if ( modal.hidden ) {
			return;
		}
		modal.classList.remove( 'is-open' );
		modal.hidden = true;
		if ( ! root.classList.contains( 'is-sheet-open' ) ) {
			document.documentElement.classList.remove( 'ads-noscroll' );
		}
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus( { preventScroll: true } );
		}
		modalFor = null;
	}

	/* ---------- cart / save ---------- */

	function setBusy( on ) {
		busy = on;
		root.classList.toggle( 'is-busy', on );
		addButtons.forEach( function ( b ) {
			b.disabled = on || selected.length !== size;
		} );
		if ( saveBtn ) {
			saveBtn.disabled = on || selected.length !== size;
		}
	}

	function addToCart( button ) {
		if ( busy || selected.length !== size ) {
			return;
		}
		setBusy( true );
		button.classList.add( 'is-loading' );
		var images = selected.map( function ( id ) {
			return cardData( id ).img;
		} );
		adsAddToCart(
			{ product_id: cfg.product, quantity: 1, components: selected.slice(), name: nameInput ? nameInput.value : '' },
			button,
			{ images: images, from: isMobile() ? root.querySelector( '[data-ads-dots]' ) : root.querySelector( '[data-ads-box]' ) }
		)
			.then( function () {
				setBusy( false );
				button.classList.remove( 'is-loading' );
				render();
				doneEl.hidden = false;
				hintEl.textContent = '';
				closeSheet();
			} )
			.catch( function ( err ) {
				setBusy( false );
				button.classList.remove( 'is-loading' );
				render();
				hintEl.textContent = err.message;
				hintEl.classList.add( 'is-error' );
			} );
	}

	function saveSet() {
		if ( busy || selected.length !== size || ! saveBtn ) {
			return;
		}
		setBusy( true );
		saveBtn.classList.add( 'is-loading' );
		var label = root.querySelector( '[data-ads-save-label]' );
		var before = label ? label.textContent : '';
		if ( label ) {
			label.textContent = t.saving || '';
		}
		adsPost( cfg.saveAjax, {
			nonce: cfg.nonce,
			components: selected.slice(),
			name: nameInput ? nameInput.value : '',
			id: editing ? editing.id : ''
		} )
			.then( function ( res ) {
				if ( res && res.login ) {
					window.location.href = cfg.login;
					return;
				}
				if ( ! res || ! res.success ) {
					throw new Error( ( res && res.message ) || t.error );
				}
				editing = res.set;
				if ( nameInput ) {
					nameInput.value = res.set.name;
				}
				var title = root.querySelector( '.ads-tray__title' );
				if ( title ) {
					title.textContent = res.set.name;
				}
				if ( window.history && window.history.replaceState ) {
					var url = new URL( window.location.href );
					url.searchParams.delete( 'set' );
					url.searchParams.set( 'saved', res.set.id );
					window.history.replaceState( null, '', url.toString() );
				}
				hintEl.textContent = '';
				hintEl.classList.add( 'is-ok' );
				hintEl.appendChild( document.createTextNode( res.message + ' ' ) );
				if ( res.url ) {
					var a = el( 'a', 'ads-link', '' );
					a.href = res.url;
					a.textContent = ( t.mySets || '' ) || '←';
					hintEl.appendChild( a );
				}
				before = t.saveEdit || before;
			} )
			.catch( function ( err ) {
				hintEl.textContent = err.message || t.error;
				hintEl.classList.add( 'is-error' );
			} )
			.then( function () {
				setBusy( false );
				saveBtn.classList.remove( 'is-loading' );
				if ( label ) {
					label.textContent = before;
				}
			} );
	}

	/* ---------- events ---------- */

	root.addEventListener( 'click', function ( e ) {
		var node = e.target.closest( 'button, [data-ads-sheet-close], [data-ads-modal-close]' );
		if ( ! node || ! root.contains( node ) ) {
			return;
		}
		if ( node.hasAttribute( 'data-ads-pick' ) ) {
			toggle( node.closest( '.ads-pcard' ).getAttribute( 'data-id' ) );
		} else if ( node.hasAttribute( 'data-ads-info' ) ) {
			openModal( node.closest( '.ads-pcard' ).getAttribute( 'data-id' ) );
		} else if ( node.hasAttribute( 'data-ads-modal-pick' ) ) {
			if ( modalFor ) {
				toggle( modalFor );
				modalPickLabel();
				if ( selected.indexOf( modalFor ) !== -1 ) {
					closeModal();
				}
			}
		} else if ( node.hasAttribute( 'data-ads-modal-close' ) ) {
			closeModal();
		} else if ( node.hasAttribute( 'data-ads-remove' ) ) {
			toggle( node.getAttribute( 'data-ads-remove' ) );
		} else if ( node.hasAttribute( 'data-ads-reset' ) ) {
			selects.forEach( function ( select ) {
				select.value = '';
			} );
			filters.f = {};
			applyFilters();
		} else if ( node.hasAttribute( 'data-ads-add' ) ) {
			addToCart( node );
		} else if ( node.hasAttribute( 'data-ads-save-set' ) ) {
			saveSet();
		} else if ( node.hasAttribute( 'data-ads-clear' ) || node.hasAttribute( 'data-ads-new' ) ) {
			selected = [];
			doneEl.hidden = true;
			if ( node.hasAttribute( 'data-ads-new' ) && nameInput && ! editing ) {
				nameInput.value = '';
				showName();
			}
			render();
		} else if ( node.hasAttribute( 'data-ads-sheet-open' ) ) {
			openSheet();
		} else if ( node.hasAttribute( 'data-ads-sheet-close' ) ) {
			closeSheet();
		}
	} );

	// The tray title follows the name the customer types (it is also the name in the cart).
	var trayTitle = root.querySelector( '.ads-tray__title' );
	var titleBase = trayTitle ? trayTitle.textContent : '';
	function showName() {
		if ( trayTitle && nameInput ) {
			trayTitle.textContent = nameInput.value.trim() || titleBase;
		}
	}
	if ( nameInput ) {
		nameInput.addEventListener( 'input', showName );
		nameInput.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key ) {
				e.preventDefault();
				nameInput.blur();
			}
		} );
	}

	selects.forEach( function ( select ) {
		select.addEventListener( 'change', function () {
			filters.f[ select.getAttribute( 'data-ads-filter' ) ] = select.value;
			applyFilters();
		} );
	} );

	var timer;
	root.querySelector( '[data-ads-search]' ).addEventListener( 'input', function ( e ) {
		clearTimeout( timer );
		var value = e.target.value;
		timer = setTimeout( function () {
			filters.q = value;
			applyFilters();
		}, 120 );
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' !== e.key ) {
			return;
		}
		if ( ! modal.hidden ) {
			closeModal();
		} else if ( root.classList.contains( 'is-sheet-open' ) ) {
			closeSheet();
		}
	} );

	// Keep focus inside the details window.
	modal.addEventListener( 'keydown', function ( e ) {
		if ( 'Tab' !== e.key ) {
			return;
		}
		var focusable = Array.prototype.filter.call( modal.querySelectorAll( 'a[href], button:not([disabled])' ), function ( n ) {
			return n.offsetParent;
		} );
		if ( ! focusable.length ) {
			return;
		}
		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];
		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	} );

	window.addEventListener( 'resize', function () {
		if ( ! isMobile() && root.classList.contains( 'is-sheet-open' ) ) {
			closeSheet();
		}
	} );

	/* ---------- start ---------- */

	selected = restore().filter( function ( id, i, all ) {
		var card = byId[ id ];
		return card && '1' === card.getAttribute( 'data-available' ) && all.indexOf( id ) === i;
	} ).slice( 0, size );
	root.classList.add( 'is-ready' );
	render();
	selects.forEach( function ( select ) {
		if ( select.value ) {
			filters.f[ select.getAttribute( 'data-ads-filter' ) ] = select.value;
		}
	} );
	if ( Object.keys( filters.f ).length ) {
		applyFilters();
	}
	if ( window.adsWatchBottomInset ) {
		window.adsWatchBottomInset();
	}
} )();
