/* جستجوی زنده تکپینو */
( function () {
	'use strict';

	var cfg = window.tplsConfig || {};
	var doc = document;
	var RECENT = 'tpls:recent';
	var KIND = { b: 'برند', c: 'دسته' };
	var ICON = {
		clock: '<svg class="tpls-svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l2.5 2.5"/></svg>',
		arrow: '<svg class="tpls-svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6"/></svg>'
	};

	var index = null;      // Prepared index, once loaded.
	var loading = null;    // The promise while it loads.
	var instances = [];
	var uid = 0;

	function each( selector, fn, scope ) {
		Array.prototype.forEach.call( ( scope || doc ).querySelectorAll( selector ), fn );
	}

	function esc( text ) {
		return String( text ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	/* ---------- Persian-aware text folding ---------- */

	var FOLD = { 'ي': 'ی', 'ى': 'ی', 'ئ': 'ی', 'ك': 'ک', 'ة': 'ه', 'ۀ': 'ه', 'ە': 'ه', 'أ': 'ا', 'إ': 'ا', 'آ': 'ا', 'ٱ': 'ا', 'ؤ': 'و' };
	var FOLD_RE = /[يىئكةۀەأإآٱؤ۰-۹٠-٩]/g;
	var MARKS = /[̀-ًͯ-ٰٟـ‍‎‏]/g;
	var SPACES = /[\s‌ \-_.,،؛;:!?؟()[\]{}"'«»+/\\|*#]+/g;

	function foldChar( ch ) {
		var code = ch.charCodeAt( 0 );
		if ( code >= 0x6F0 && code <= 0x6F9 ) {
			return String( code - 0x6F0 );
		}
		if ( code >= 0x660 && code <= 0x669 ) {
			return String( code - 0x660 );
		}
		return FOLD[ ch ];
	}

	/** Lower case, no diacritics, one shape per letter, Latin digits, single spaces. */
	function norm( text ) {
		var s = String( null == text ? '' : text ).toLowerCase();
		if ( s.normalize ) {
			s = s.normalize( 'NFKD' );
		}
		return s.replace( MARKS, '' ).replace( FOLD_RE, foldChar ).replace( SPACES, ' ' ).trim();
	}

	function compact( text ) {
		return text.replace( / /g, '' );
	}

	/* Highlighting works on the original title, so each folded letter matches all its shapes. */
	var SHAPES = { 'ا': 'اآأإٱ', 'ی': 'یيىئ', 'ک': 'کك', 'ه': 'هةۀە', 'و': 'وؤ' };
	for ( var d = 0; d < 10; d++ ) {
		SHAPES[ d ] = String( d ) + String.fromCharCode( 0x6F0 + d ) + String.fromCharCode( 0x660 + d );
	}
	var GAP = '[\\u064B-\\u065F\\u0670\\u0640\\u200C\\u200D\\s-]*';

	function pattern( word ) {
		return Array.from( word ).map( function ( ch ) {
			if ( ' ' === ch ) {
				return '[\\s\\u200C\\u00A0\\-_.,،]*';
			}
			return SHAPES[ ch ] ? '[' + SHAPES[ ch ] + ']' : ch.replace( /[.*+?^${}()|[\]\\/-]/g, '\\$&' );
		} ).join( GAP );
	}

	function highlighter( words ) {
		var list = [];
		words.forEach( function ( alts ) {
			alts.forEach( function ( w ) {
				list.push( pattern( w ) );
			} );
		} );
		list.sort( function ( a, b ) {
			return b.length - a.length;
		} );
		try {
			return list.length ? new RegExp( list.join( '|' ), 'gi' ) : null;
		} catch ( e ) {
			return null;
		}
	}

	function mark( text, re ) {
		if ( ! re ) {
			return esc( text );
		}
		var out = '';
		var last = 0;
		text.replace( re, function ( match ) {
			var at = arguments[ arguments.length - 2 ];
			if ( match ) {
				out += esc( text.slice( last, at ) ) + '<mark class="tpls-mark">' + esc( match ) + '</mark>';
				last = at + match.length;
			}
			return match;
		} );
		return out + esc( text.slice( last ) );
	}

	/* ---------- Index ---------- */

	function field( text ) {
		var n = norm( text );
		return n ? [ n, compact( n ) ] : null;
	}

	function prepare( data ) {
		function terms( kind ) {
			return ( data[ kind ] || [] ).map( function ( t ) {
				var n = norm( t[ 0 ] );
				return { kind: kind, name: t[ 0 ], url: t[ 1 ], count: t[ 2 ] || 0, n: n, nc: compact( n ) };
			} );
		}
		function pick( list, ids ) {
			return ( ids || [] ).map( function ( i ) {
				return list[ i ];
			} ).filter( Boolean );
		}
		function names( list ) {
			return list.map( function ( t ) {
				return t.name;
			} ).join( ' ' );
		}

		var brands = terms( 'b' );
		var cats = terms( 'c' );
		var products = ( data.p || [] ).map( function ( p, n ) {
			var b = pick( brands, p.b );
			var c = pick( cats, p.c );
			var t = norm( p.t );
			return {
				raw: p,
				n: n,
				b: b,
				c: c,
				t: [ t, compact( t ) ],
				f: {
					en: field( p.e ),
					brand: field( names( b ) ),
					cat: field( names( c ) ),
					tag: field( p.g ),
					sku: field( p.k ),
					attr: field( p.a )
				}
			};
		} );
		return { p: products, b: brands, c: cats };
	}

	function load() {
		if ( index ) {
			return Promise.resolve( index );
		}
		if ( ! loading ) {
			var source = window.tplsIndex ? Promise.resolve( window.tplsIndex ) : ( cfg.index ? fetch( cfg.index ).then( function ( r ) {
				if ( ! r.ok ) {
					throw new Error( 'tpls: ' + r.status );
				}
				return r.json();
			} ) : Promise.reject( new Error( 'tpls: no index' ) ) );
			loading = source.then( function ( data ) {
				index = prepare( data );
				return index;
			} ).catch( function ( e ) {
				loading = null;
				throw e;
			} );
		}
		return loading;
	}

	/* ---------- Matching ---------- */

	// Points for a match at the start of the field, at the start of a word, anywhere, and ignoring spaces.
	var WEIGHT = {
		title: [ 100, 70, 40, 30 ],
		en: [ 90, 65, 38, 28 ],
		brand: [ 65, 60, 35, 25 ],
		cat: [ 30, 30, 15, 10 ],
		tag: [ 25, 25, 12, 8 ],
		sku: [ 80, 60, 40, 40 ],
		attr: [ 15, 15, 8, 0 ],
		term: [ 100, 80, 40, 30 ]
	};

	function hit( h, w, wc, points ) {
		if ( ! h ) {
			return 0;
		}
		var at = h[ 0 ].indexOf( w );
		if ( 0 === at ) {
			return points[ 0 ];
		}
		if ( at > 0 ) {
			return ( ' ' + h[ 0 ] ).indexOf( ' ' + w ) >= 0 ? points[ 1 ] : points[ 2 ];
		}
		return points[ 3 ] && wc.length > 1 && h[ 1 ].indexOf( wc ) >= 0 ? points[ 3 ] : 0;
	}

	function synonyms( text ) {
		return String( text || '' ).split( /\r?\n/ ).map( function ( line ) {
			return line.split( /[,،=|]/ ).map( norm ).filter( Boolean );
		} ).filter( function ( group ) {
			return group.length > 1;
		} );
	}

	function expand( word, groups ) {
		var alts = [ word ];
		groups.forEach( function ( group ) {
			var found = group.some( function ( m ) {
				return m === word || ( word.length > 1 && 0 === m.indexOf( word ) );
			} );
			if ( found ) {
				group.forEach( function ( m ) {
					if ( alts.indexOf( m ) < 0 ) {
						alts.push( m );
					}
				} );
			}
		} );
		return alts;
	}

	/** The query as a list of words, each with its alternatives (the word plus its synonyms). */
	function words( query, groups ) {
		if ( ! query ) {
			return [];
		}
		if ( query.indexOf( ' ' ) > 0 ) {
			var whole = expand( query, groups );
			if ( whole.length > 1 ) {
				return [ whole ];
			}
		}
		return query.split( ' ' ).map( function ( w ) {
			return expand( w, groups );
		} );
	}

	function score( item, list, fields, query ) {
		var total = 0;
		for ( var i = 0; i < list.length; i++ ) {
			var best = 0;
			for ( var j = 0; j < list[ i ].length; j++ ) {
				var w = list[ i ][ j ];
				var wc = compact( w );
				best = Math.max( best, hit( item.t, w, wc, WEIGHT.title ) );
				best = Math.max( best, hit( item.f.en, w, wc, WEIGHT.en ) );
				for ( var k = 0; k < fields.length; k++ ) {
					best = Math.max( best, hit( item.f[ fields[ k ] ], w, wc, WEIGHT[ fields[ k ] ] ) );
				}
			}
			if ( ! best ) {
				return 0;
			}
			total += best;
		}
		return list.length > 1 && item.t[ 0 ].indexOf( query ) >= 0 ? total + 50 : total;
	}

	function search( inst, raw ) {
		var o = inst.o;
		var query = norm( raw );
		var list = words( query, inst.syn );
		var found = [];

		index.p.forEach( function ( item ) {
			if ( 'hide' === o.oos && ! item.raw.s ) {
				return;
			}
			var s = score( item, list, o.fields, query );
			if ( s ) {
				found.push( { item: item, s: s } );
			}
		} );
		found.sort( function ( a, b ) {
			if ( 'last' === o.oos && a.item.raw.s !== b.item.raw.s ) {
				return b.item.raw.s - a.item.raw.s;
			}
			return b.s - a.s || ( b.item.raw.q || 0 ) - ( a.item.raw.q || 0 ) || a.item.raw.t.length - b.item.raw.t.length || a.item.n - b.item.n;
		} );

		// A brand or category shows when it matches every word, or when its whole name is in the query («عطر دیور»).
		var terms = [];
		var padded = ' ' + query + ' ';
		o.terms.forEach( function ( kind ) {
			( 'brand' === kind ? index.b : index.c ).forEach( function ( term ) {
				var total = 0;
				for ( var i = 0; i < list.length; i++ ) {
					var best = 0;
					for ( var j = 0; j < list[ i ].length; j++ ) {
						best = Math.max( best, hit( [ term.n, term.nc ], list[ i ][ j ], compact( list[ i ][ j ] ), WEIGHT.term ) );
					}
					if ( ! best ) {
						total = padded.indexOf( ' ' + term.n + ' ' ) >= 0 ? 90 : 0;
						break;
					}
					total += best;
				}
				if ( total ) {
					terms.push( { term: term, s: total } );
				}
			} );
		} );
		terms.sort( function ( a, b ) {
			return b.s - a.s || ( 'b' === a.term.kind ? -1 : 0 ) + ( 'b' === b.term.kind ? 1 : 0 ) || b.term.count - a.term.count;
		} );

		return {
			words: list,
			items: found.map( function ( f ) {
				return f.item;
			} ),
			terms: terms.slice( 0, o.termsMax ).map( function ( t ) {
				return t.term;
			} )
		};
	}

	/* ---------- Markup ---------- */

	function digits( value, persian ) {
		var s = String( value );
		return persian ? s.replace( /\d/g, function ( n ) {
			return String.fromCharCode( 0x6F0 + +n );
		} ) : s;
	}

	function money( amount, o ) {
		var c = cfg.currency || {};
		var parts = Number( amount ).toFixed( +c.dec || 0 ).split( '.' );
		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, null == c.th ? ',' : c.th );
		var html = '<span class="tpls-price__amount">' + esc( digits( parts.join( c.ds || '.' ), o.fa ) ) + '</span>';
		var symbol = o.cur || c.symbol || '';
		if ( ! symbol ) {
			return html;
		}
		var cur = '<span class="tpls-price__cur">' + esc( symbol ) + '</span>';
		if ( 'left' === c.pos ) {
			return cur + html;
		}
		if ( 'left_space' === c.pos ) {
			return cur + ' ' + html;
		}
		return 'right' === c.pos ? html + cur : html + ' ' + cur;
	}

	function price( p, o ) {
		if ( null == p.p ) {
			return '';
		}
		return ( p.x && o.from ? '<span class="tpls-price__from">' + esc( o.from ) + '</span>' : '' ) +
			( p.r ? '<del class="tpls-price__old">' + money( p.r, o ) + '</del>' : '' ) +
			money( p.p, o );
	}

	/** The ring-and-bag cart icon of the site's product cards. */
	var CART = '<svg class="tpls-hcard__cart" viewBox="0 0 200 200" width="38" height="38" aria-hidden="true" focusable="false"><circle cx="98.4" cy="98.6" r="85" fill="none" stroke="currentColor" stroke-width="5"/><g transform="matrix(.959 0 0 1.074 99.33 92.82)" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"><path d="M-28-10-32 25c0 5 4 10 9 10h46c5 0 9-5 9-10l-4-35Z"/><path d="M-15-10c0-12 5-18 15-18s15 6 15 18"/></g></svg>';

	function itemId( inst, i, opt ) {
		return inst.uid + '-' + ( opt && opt.prefix ? opt.prefix : '' ) + i;
	}

	function itemAttrs( opt ) {
		return opt && opt.plain ? '' : ' role="option" aria-selected="false"';
	}

	/**
	 * «کارت سایت»: the product card of the shop archive. Image on the start side; Persian name,
	 * English name, the first three sizes as circles (the cheapest one in stock filled), the
	 * rest as small overlapping gold circles, then the price and the cart icon.
	 */
	function siteCard( inst, item, i, re, opt ) {
		var o = inst.o;
		var p = item.raw;
		var sizes = p.v ? String( p.v ).split( ',' ) : [];
		var out = p.o ? String( p.o ).split( ',' ) : [];
		var first = -1;
		sizes.forEach( function ( v, k ) {
			if ( first < 0 && out.indexOf( v ) < 0 ) {
				first = k;
			}
		} );
		var dots = '';
		if ( sizes.length ) {
			dots = '<span class="tpls-hcard__sizes">' + sizes.slice( 0, 3 ).map( function ( v, k ) {
				var cls = out.indexOf( v ) >= 0 ? 'is-out' : ( k === first && p.s ? 'is-on' : '' );
				return '<i class="' + cls + '"><b>' + esc( digits( v, true ) ) + '</b><small>میل</small></i>';
			} ).join( '' ) + ( sizes.length > 3 ? '<span class="tpls-hcard__more">' + sizes.slice( 3 ).map( function ( v ) {
				return '<em class="' + ( out.indexOf( v ) >= 0 ? 'is-out' : '' ) + '">' + esc( digits( v, true ) ) + '</em>';
			} ).join( '' ) + '</span>' : '' ) + '</span>';
		}
		var pct = p.r && 'none' !== o.sale ? Math.round( ( 1 - p.p / p.r ) * 100 ) : 0;
		var badge = pct ? '<span class="tpls-badge tpls-badge--sale">' + esc( 'percent' === o.sale ? digits( pct, o.fa ) + '٪' : o.saleText ) + '</span>' : '';
		var media = o.image ? '<span class="tpls-hcard__media"><img src="' + esc( p.m ) + '"' +
			( p.n ? ' srcset="' + esc( p.n + ' 150w, ' + p.m + ' 300w' ) + '" sizes="' + inst.sizes + '"' : '' ) +
			' alt="" width="300" height="300" loading="lazy" decoding="async">' + badge + '</span>' : '';
		var amount = null == p.p ? '' : ( p.r ? '<del class="tpls-price__old">' + money( p.r, o ) + '</del>' : '' ) + money( p.p, o );
		return '<li class="tpls-item tpls-item--card' + ( p.s ? '' : ' is-oos' ) + '" id="' + itemId( inst, i, opt ) + '"' + itemAttrs( opt ) + '>' +
			'<a class="tpls-hcard" href="' + esc( p.u ) + '">' + media +
			'<span class="tpls-hcard__body">' +
			'<span class="tpls-hcard__title">' + ( o.mark && re ? mark( p.t, re ) : esc( p.t ) ) + '</span>' +
			( p.e ? '<span class="tpls-hcard__en">' + esc( p.e ) + '</span>' : '' ) +
			dots +
			'<span class="tpls-hcard__foot">' +
			( p.s ? ( o.price && amount ? '<span class="tpls-hcard__price">' + amount + '</span>' : '<span></span>' ) : '<span class="tpls-hcard__oos">' + esc( o.oosText || 'ناموجود' ) + '</span>' ) +
			CART + '</span>' +
			'</span></a></li>';
	}

	function card( inst, item, i, re, opt ) {
		var o = inst.o;
		var p = item.raw;
		if ( 'card' === o.layout ) {
			return siteCard( inst, item, i, re, opt );
		}
		var badges = '';
		if ( p.r && 'none' !== o.sale ) {
			var label = 'percent' === o.sale ? digits( Math.round( ( 1 - p.p / p.r ) * 100 ), o.fa ) + '٪' : o.saleText;
			if ( label ) {
				badges += '<span class="tpls-badge tpls-badge--sale">' + esc( label ) + '</span>';
			}
		}
		if ( ! p.s && o.oosText ) {
			badges += '<span class="tpls-badge tpls-badge--oos">' + esc( o.oosText ) + '</span>';
		}
		if ( badges ) {
			badges = '<span class="tpls-badges">' + badges + '</span>';
		}

		var media = '';
		if ( o.image ) {
			media = '<span class="tpls-card__media"><img src="' + esc( p.m ) + '"' +
				( p.n ? ' srcset="' + esc( p.n + ' 150w, ' + p.m + ' 300w' ) + '" sizes="' + inst.sizes + '"' : '' ) +
				' alt="" width="300" height="300" loading="lazy" decoding="async">' + badges + '</span>';
		}
		var brand = o.brand && item.b.length ? '<span class="tpls-card__brand">' + esc( item.b[ 0 ].name ) + '</span>' : '';
		var cat = o.cat && item.c.length ? '<span class="tpls-card__cat">' + esc( item.c.slice( 0, 2 ).map( function ( t ) {
			return t.name;
		} ).join( '، ' ) ) + '</span>' : '';
		var amount = o.price ? price( p, o ) : '';

		return '<li class="tpls-item' + ( p.s ? '' : ' is-oos' ) + '" id="' + itemId( inst, i, opt ) + '"' + itemAttrs( opt ) + '>' +
			'<a class="tpls-card" href="' + esc( p.u ) + '">' + media +
			'<span class="tpls-card__body">' + ( o.image ? '' : badges ) +
			( 'above' === o.brand ? brand : '' ) +
			'<span class="tpls-card__title">' + ( o.mark && re ? mark( p.t, re ) : esc( p.t ) ) + '</span>' +
			( 'below' === o.brand ? brand : '' ) + cat +
			( amount ? '<span class="tpls-card__price">' + amount + '</span>' : '' ) +
			'</span></a></li>';
	}

	/* ---------- Featured products (shown before the visitor types) ---------- */

	function featuredItems() {
		var f = cfg.featured || {};
		var list;
		if ( 'manual' === f.mode ) {
			var byId = {};
			index.p.forEach( function ( it ) {
				byId[ it.raw.i ] = it;
			} );
			list = ( f.ids || [] ).map( function ( id ) {
				return byId[ id ];
			} ).filter( Boolean );
		} else {
			list = index.p.filter( function ( it ) {
				return ! f.stock || it.raw.s;
			} ).sort( function ( a, b ) {
				return ( b.raw.q || 0 ) - ( a.raw.q || 0 ) || a.n - b.n;
			} ).slice( 0, Math.max( 1, +f.count || 6 ) );
		}
		return list;
	}

	function paintFeatured( inst ) {
		var box = inst.root.querySelector( '.tpls-featured' );
		if ( ! box || ! index ) {
			return;
		}
		var list = featuredItems();
		box.hidden = ! list.length;
		inst.featuredDone = true;
		if ( list.length ) {
			box.querySelector( '.tpls-list' ).innerHTML = list.map( function ( item, i ) {
				return card( inst, item, i, null, { prefix: 'f', plain: true } );
			} ).join( '' );
		}
	}

	function featured( inst ) {
		if ( ! inst.o.featured || inst.featuredDone ) {
			return;
		}
		if ( index ) {
			paintFeatured( inst );
			return;
		}
		load().then( function () {
			paintFeatured( inst );
		}, function () {
			var box = inst.root.querySelector( '.tpls-featured' );
			if ( box ) {
				box.hidden = true;
			}
		} );
	}

	function chip( text, icon ) {
		return '<button type="button" class="tpls-chip" data-q="' + esc( text ) + '">' +
			( icon ? '<span class="tpls-chip__icon">' + icon + '</span>' : '' ) +
			'<span class="tpls-chip__text">' + esc( text ) + '</span></button>';
	}

	function head( title, count ) {
		return title || count ? '<div class="tpls-head">' + ( title ? '<span class="tpls-heading">' + esc( title ) + '</span>' : '' ) + ( count || '' ) + '</div>' : '';
	}

	function searchUrl( inst, query ) {
		var url = new URL( inst.form.getAttribute( 'action' ) || '/', location.href );
		url.searchParams.set( 's', query );
		url.searchParams.set( 'post_type', 'product' );
		return url.toString();
	}

	function paintResults( inst, found, query ) {
		var o = inst.o;
		var html = '';
		if ( found.terms.length ) {
			html += '<div class="tpls-block tpls-terms">' + head( o.termsText ) + '<div class="tpls-chips">' + found.terms.map( function ( t ) {
				return '<a class="tpls-chip tpls-chip--' + t.kind + '" href="' + esc( t.url ) + '"><span class="tpls-chip__text">' + esc( t.name ) + '</span>' +
					( o.kind ? '<span class="tpls-chip__kind">' + KIND[ t.kind ] + '</span>' : '' ) + '</a>';
			} ).join( '' ) + '</div></div>';
		}
		if ( found.items.length ) {
			var shown = found.items.slice( 0, o.max );
			var re = o.mark ? highlighter( found.words ) : null;
			html += '<div class="tpls-block tpls-products">' +
				head( o.title, o.count ? '<span class="tpls-count">' + digits( found.items.length, o.fa ) + '</span>' : '' ) +
				'<ul class="tpls-list" id="' + inst.uid + '-list" role="listbox" aria-label="' + esc( o.title || query ) + '">' +
				shown.map( function ( item, i ) {
					return card( inst, item, i, re );
				} ).join( '' ) + '</ul></div>';
			if ( 'always' === o.all || ( 'more' === o.all && found.items.length > shown.length ) ) {
				html += '<div class="tpls-foot"><a class="tpls-all" href="' + esc( searchUrl( inst, query.trim() ) ) + '">' +
					'<span class="tpls-all__text">' + esc( o.allText.replace( '{n}', digits( found.items.length, o.fa ) ) ) + '</span>' +
					( o.allIcon ? '<span class="tpls-all__icon">' + ICON.arrow + '</span>' : '' ) + '</a></div>';
			}
		}
		inst.active = -1;
		inst.input.removeAttribute( 'aria-activedescendant' );
		inst.results.innerHTML = html;
	}

	/* ---------- Recent searches (this browser only) ---------- */

	function recent() {
		try {
			var list = JSON.parse( localStorage.getItem( RECENT ) || '[]' );
			return Array.isArray( list ) ? list.filter( function ( q ) {
				return 'string' === typeof q && q;
			} ) : [];
		} catch ( e ) {
			return [];
		}
	}

	function remember( query ) {
		query = String( query ).trim();
		if ( norm( query ).length < 2 ) {
			return;
		}
		var key = norm( query );
		var list = recent().filter( function ( q ) {
			return norm( q ) !== key;
		} );
		list.unshift( query );
		try {
			localStorage.setItem( RECENT, JSON.stringify( list.slice( 0, 6 ) ) );
		} catch ( e ) {}
	}

	function forget() {
		try {
			localStorage.removeItem( RECENT );
		} catch ( e ) {}
	}

	function paintRecent( inst, sample ) {
		var box = inst.root.querySelector( '.tpls-recent' );
		if ( ! box ) {
			return;
		}
		var list = sample || recent();
		box.hidden = ! list.length;
		box.querySelector( '.tpls-chips' ).innerHTML = list.map( function ( q ) {
			return chip( q, inst.o.icons ? ICON.clock : '' );
		} ).join( '' );
	}

	function hasBefore( inst ) {
		var box = inst.root.querySelector( '.tpls-recent' );
		return !! inst.o.featured || !! inst.root.querySelector( '.tpls-suggest, .tpls-banners' ) || !! ( box && ! box.hidden );
	}

	/* ---------- State ---------- */

	function siblings( inst, hide ) {
		var el = inst.root.closest( '.elementor-element' ) || inst.root;
		if ( ! el.parentElement || inst.hidden === hide ) {
			return;
		}
		inst.hidden = hide;
		Array.prototype.forEach.call( el.parentElement.children, function ( child ) {
			if ( child !== el ) {
				child.classList.toggle( 'tpls-sibling-hidden', hide );
			}
		} );
	}

	function setState( inst, state ) {
		var show = !! state && ( inst.o.inline || inst.open || inst.pinned );
		inst.state = state;
		if ( state ) {
			inst.panel.setAttribute( 'data-state', state );
		}
		inst.pop.hidden = ! show;
		inst.root.classList.toggle( 'is-open', show );
		inst.input.setAttribute( 'aria-expanded', show && 'results' === state ? 'true' : 'false' );
		var list = inst.results.querySelector( '.tpls-list' );
		if ( list && 'results' === state ) {
			inst.input.setAttribute( 'aria-controls', list.id );
		}
		if ( inst.o.hide && ! inst.editing ) {
			siblings( inst, show && 'before' !== state );
		}
	}

	function announce( inst, text ) {
		clearTimeout( inst.sayTimer );
		inst.sayTimer = setTimeout( function () {
			inst.live.textContent = text;
		}, 500 );
	}

	function update( inst ) {
		var query = inst.input.value;
		if ( inst.clear ) {
			inst.clear.hidden = ! query;
		}
		if ( norm( query ).length < inst.o.min ) {
			paintRecent( inst );
			featured( inst );
			setState( inst, hasBefore( inst ) ? 'before' : '' );
			return;
		}
		if ( ! index ) {
			setState( inst, 'loading' );
			load().then( function () {
				if ( inst.input.value === query ) {
					update( inst );
				}
			}, function () {
				setState( inst, '' );
			} );
			return;
		}
		var found = search( inst, query );
		if ( found.items.length || found.terms.length ) {
			paintResults( inst, found, query );
			setState( inst, 'results' );
			announce( inst, found.items.length ? digits( found.items.length, inst.o.fa ) + ' محصول پیدا شد' : 'محصولی پیدا نشد' );
		} else {
			each( '.tpls-empty .tpls-q', function ( el ) {
				el.textContent = query.trim();
			}, inst.root );
			setState( inst, 'empty' );
			announce( inst, 'محصولی پیدا نشد' );
		}
	}

	function open( inst ) {
		inst.open = true;
		load().catch( function () {} );
		update( inst );
	}

	/* ---------- Popup (desktop): a native <dialog> in the top layer ---------- */

	function openModal( inst ) {
		if ( ! inst.isModal() || inst.modal.open ) {
			return;
		}
		doc.documentElement.classList.add( 'tpls-lock' );
		inst.modal.showModal();
		inst.open = true;
		load().catch( function () {} );
		inst.input.focus( { preventScroll: true } );
		update( inst );
	}

	function close( inst ) {
		if ( inst.pinned || inst.o.inline || inst.isModal() ) {
			return;
		}
		inst.open = false;
		setState( inst, inst.state );
		activate( inst, -1 );
	}

	function activate( inst, i ) {
		var items = inst.results.querySelectorAll( '.tpls-item' );
		if ( items[ inst.active ] ) {
			items[ inst.active ].classList.remove( 'is-active' );
			items[ inst.active ].setAttribute( 'aria-selected', 'false' );
		}
		inst.active = i >= 0 && i < items.length ? i : -1;
		if ( inst.active < 0 ) {
			inst.input.removeAttribute( 'aria-activedescendant' );
			return;
		}
		var el = items[ inst.active ];
		el.classList.add( 'is-active' );
		el.setAttribute( 'aria-selected', 'true' );
		inst.input.setAttribute( 'aria-activedescendant', el.id );
		el.scrollIntoView( { block: 'nearest' } );
	}

	function go( inst, item ) {
		var link = item && item.querySelector( 'a' );
		if ( link ) {
			remember( inst.input.value );
			location.href = link.href;
		}
	}

	/* ---------- Popups and off-canvas: focus the field when they open ---------- */

	function shown( host ) {
		return 'true' !== host.getAttribute( 'aria-hidden' ) && ! host.hasAttribute( 'inert' ) && 'none' !== getComputedStyle( host ).display;
	}

	function focusSoon( inst ) {
		var tries = 0;
		load().catch( function () {} );
		( function attempt() {
			inst.input.focus( { preventScroll: true } );
			if ( doc.activeElement !== inst.input && tries++ < 8 ) {
				setTimeout( attempt, 60 );
			}
		} )();
	}

	function watchHost( inst ) {
		var host = inst.root.closest( '.e-off-canvas, [role="dialog"]' );
		if ( ! host || ! window.MutationObserver ) {
			return;
		}
		var was = shown( host );
		new MutationObserver( function () {
			var now = shown( host );
			if ( now && ! was && inst.o.focus && ! inst.editing ) {
				focusSoon( inst );
			}
			if ( ! now && was ) {
				close( inst );
			}
			was = now;
		} ).observe( host, { attributes: true, attributeFilter: [ 'aria-hidden', 'inert', 'class', 'style' ] } );
	}

	/* ---------- Setup ---------- */

	function init( root ) {
		if ( root.tpls ) {
			return;
		}
		var o;
		try {
			o = JSON.parse( root.getAttribute( 'data-tpls' ) || '{}' );
		} catch ( e ) {
			return;
		}
		var img = parseFloat( getComputedStyle( root ).getPropertyValue( '--tpls-img' ) ) || 72;
		var inst = {
			root: root,
			o: o,
			uid: 'tpls-' + ( o.id || 'w' ) + '-' + ( ++uid ),
			form: root.querySelector( '.tpls-form' ),
			input: root.querySelector( '.tpls-input' ),
			clear: root.querySelector( '.tpls-clear' ),
			pop: root.querySelector( '.tpls-pop' ),
			panel: root.querySelector( '.tpls-panel' ),
			results: root.querySelector( '.tpls-results' ),
			live: root.querySelector( '.tpls-live' ),
			syn: synonyms( String( cfg.syn || '' ) + '\n' + String( o.syn || '' ) ),
			sizes: 'grid' === o.layout ? '(max-width: 767px) 50vw, 240px' : 'card' === o.layout ? '115px' : Math.ceil( img ) + 'px',
			modal: root.querySelector( '.tpls-modal' ),
			trigger: root.querySelector( '.tpls-trigger' ),
			mq: window.matchMedia ? window.matchMedia( '(min-width: ' + ( +cfg.popup || 1025 ) + 'px)' ) : { matches: true },
			editing: '' !== ( o.preview || '' ),
			pinned: !! o.preview && 'off' !== o.preview,
			open: false,
			state: '',
			active: -1,
			hidden: false
		};
		o.fields = o.fields || [];
		o.terms = o.terms || [];
		if ( ! inst.form || ! inst.input || ! inst.panel ) {
			return;
		}
		inst.isModal = function () {
			return !! ( o.popup && inst.modal && ! inst.pinned && inst.mq.matches && 'function' === typeof inst.modal.showModal );
		};
		root.tpls = inst;
		instances.push( inst );
		var input = inst.input;
		if ( o.popup && inst.pinned ) {
			root.classList.add( 'is-pinned' );
		}

		input.addEventListener( 'focus', function () {
			open( inst );
		} );
		input.addEventListener( 'input', function () {
			inst.open = true;
			update( inst );
		} );
		input.addEventListener( 'keydown', function ( e ) {
			var items = inst.results.querySelectorAll( '.tpls-item' );
			var listed = ! inst.pop.hidden && 'results' === inst.state && items.length;
			switch ( e.key ) {
				case 'ArrowDown':
				case 'ArrowUp':
					if ( ! listed ) {
						if ( 'ArrowDown' === e.key ) {
							open( inst );
						}
						return;
					}
					e.preventDefault();
					if ( 'ArrowDown' === e.key ) {
						activate( inst, inst.active + 1 >= items.length ? 0 : inst.active + 1 );
					} else {
						activate( inst, inst.active <= 0 ? items.length - 1 : inst.active - 1 );
					}
					break;
				case 'Enter':
					if ( e.isComposing ) {
						return;
					}
					if ( inst.active >= 0 || ( 'first' === o.enter && listed ) ) {
						e.preventDefault();
						go( inst, items[ Math.max( 0, inst.active ) ] );
					} else if ( ! input.value.trim() ) {
						e.preventDefault();
					}
					break;
				case 'Escape':
					if ( inst.isModal() ) {
						// First Escape clears the text; with an empty field the dialog closes by itself.
						if ( input.value ) {
							e.preventDefault();
							e.stopPropagation();
							input.value = '';
							update( inst );
						}
						return;
					}
					if ( ! o.inline && ! inst.pop.hidden ) {
						e.preventDefault();
						close( inst );
					} else if ( input.value ) {
						e.preventDefault();
						e.stopPropagation();
						input.value = '';
						update( inst );
					}
					break;
			}
		} );

		inst.form.addEventListener( 'submit', function ( e ) {
			if ( ! input.value.trim() || inst.editing ) {
				e.preventDefault();
				return;
			}
			remember( input.value );
		} );

		// A click anywhere on the field puts the caret in the input.
		inst.form.querySelector( '.tpls-field' ).addEventListener( 'mousedown', function ( e ) {
			if ( e.target !== input && ! e.target.closest( 'button' ) ) {
				e.preventDefault();
				input.focus();
			}
		} );

		if ( inst.clear ) {
			inst.clear.addEventListener( 'click', function () {
				input.value = '';
				input.focus();
				update( inst );
			} );
		}

		// Taps inside the panel keep the keyboard up on phones; links still follow.
		inst.pop.addEventListener( 'mousedown', function ( e ) {
			if ( ! o.inline && ! inst.isModal() ) {
				e.preventDefault();
			}
		} );
		inst.pop.addEventListener( 'mousemove', function ( e ) {
			var item = e.target.closest( '.tpls-item' );
			if ( item && ! item.classList.contains( 'is-active' ) && inst.active >= 0 ) {
				activate( inst, -1 );
			}
		} );
		inst.pop.addEventListener( 'click', function ( e ) {
			var q = e.target.closest( '[data-q]' );
			if ( q ) {
				input.value = q.getAttribute( 'data-q' );
				inst.open = true;
				update( inst );
				if ( ! o.inline || doc.activeElement === input ) {
					input.focus( { preventScroll: true } );
				}
				return;
			}
			if ( e.target.closest( '.tpls-recent__clear' ) ) {
				forget();
				update( inst );
				return;
			}
			if ( e.target.closest( '.tpls-card, .tpls-all' ) ) {
				if ( inst.editing ) {
					e.preventDefault();
					return;
				}
				remember( input.value );
			}
		} );

		root.addEventListener( 'focusout', function ( e ) {
			if ( e.relatedTarget && ! root.contains( e.relatedTarget ) ) {
				close( inst );
			}
		} );

		if ( inst.modal && inst.trigger ) {
			inst.trigger.addEventListener( 'click', function () {
				openModal( inst );
			} );
			inst.modal.addEventListener( 'click', function ( e ) {
				if ( e.target === inst.modal || e.target.closest( '.tpls-modal__close' ) ) {
					inst.modal.close();
				}
			} );
			inst.modal.addEventListener( 'close', function () {
				doc.documentElement.classList.remove( 'tpls-lock' );
				inst.open = false;
				input.value = '';
				activate( inst, -1 );
				update( inst );
			} );
		}

		watchHost( inst );

		if ( inst.pinned ) {
			if ( 'before' === o.preview ) {
				input.value = '';
				paintRecent( inst, recent().length ? null : [ 'دیور ساواج', 'عطر زنانه', 'تام فورد' ] );
				setState( inst, hasBefore( inst ) ? 'before' : '' );
			} else if ( 'empty' === o.preview ) {
				each( '.tpls-empty .tpls-q', function ( el ) {
					el.textContent = input.value.trim() || o.previewQ;
				}, root );
				setState( inst, 'empty' );
			} else {
				update( inst );
			}
		} else if ( o.inline || input.value ) {
			if ( input.value ) {
				load().catch( function () {} );
			}
			update( inst );
		}
		if ( o.featured && inst.pinned && 'before' === o.preview ) {
			featured( inst );
		}
	}

	function boot( scope ) {
		each( '.tpls', init, scope );
		if ( instances.length && ! boot.idle ) {
			boot.idle = true;
			( window.requestIdleCallback || function ( fn ) {
				return setTimeout( fn, 1500 );
			} )( function () {
				load().catch( function () {} );
			}, { timeout: 4000 } );
		}
	}

	doc.addEventListener( 'pointerdown', function ( e ) {
		instances.forEach( function ( inst ) {
			if ( ! inst.root.contains( e.target ) ) {
				close( inst );
			}
		} );
	} );

	window.addEventListener( 'pageshow', function ( e ) {
		if ( e.persisted ) {
			instances.forEach( close );
		}
	} );

	if ( window.jQuery ) {
		window.jQuery( doc ).on( 'elementor/popup/show', function () {
			instances.forEach( function ( inst ) {
				if ( inst.o.focus && ! inst.editing && inst.root.closest( '.elementor-popup-modal' ) ) {
					focusSoon( inst );
				}
			} );
		} );
	}

	function hookElementor() {
		if ( ! window.elementorFrontend || ! window.elementorFrontend.hooks ) {
			return false;
		}
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/tp-live-search.default', function ( $el ) {
			boot( $el[ 0 ] );
		} );
		return true;
	}
	if ( ! hookElementor() && window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', hookElementor );
	}

	if ( 'loading' === doc.readyState ) {
		doc.addEventListener( 'DOMContentLoaded', function () {
			boot();
		} );
	} else {
		boot();
	}
}() );
