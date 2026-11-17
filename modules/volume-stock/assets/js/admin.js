/* Aromist Volume Stock – admin panel */
( function () {
	'use strict';

	var dialog = document.getElementById( 'avs-adjust-dialog' );
	var fmt = function ( n ) {
		try {
			return new Intl.NumberFormat( document.documentElement.lang || 'fa-IR' ).format( n );
		} catch ( e ) {
			return String( n );
		}
	};

	// ----- Adjust dialog -------------------------------------------------
	if ( dialog && typeof dialog.showModal === 'function' ) {
		var form = dialog.querySelector( 'form' );
		var amount = form.querySelector( '[name="amount"]' );
		var preview = form.querySelector( '[data-avs-preview]' );
		var pool = 0;
		var unit = '';
		var unlimited = false;

		var updatePreview = function () {
			var op = ( form.querySelector( '[name="operation"]:checked' ) || {} ).value;
			var value = parseInt( amount.value, 10 );
			preview.classList.remove( 'is-error' );
			if ( isNaN( value ) || value < 0 ) {
				preview.textContent = '';
				return;
			}
			var after = unlimited ? value : ( op === 'add' ? pool + value : ( op === 'remove' ? pool - value : value ) );
			if ( after < 0 ) {
				preview.classList.add( 'is-error' );
				preview.textContent = 'بیشتر از موجودی فعلی است';
				return;
			}
			preview.textContent = 'موجودی بعد از ثبت: ' + fmt( after ) + ' ' + unit;
		};

		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-avs-adjust]' );
			if ( btn ) {
				e.preventDefault();
				pool = parseInt( btn.getAttribute( 'data-pool' ), 10 ) || 0;
				unit = btn.getAttribute( 'data-unit' ) || '';
				unlimited = '1' === btn.getAttribute( 'data-unlimited' );
				form.reset();
				form.querySelector( '[name="product_id"]' ).value = btn.getAttribute( 'data-product' );
				dialog.querySelector( '[data-avs-name]' ).textContent = btn.getAttribute( 'data-name' );
				dialog.querySelector( '[data-avs-pool]' ).textContent = unlimited ? 'نامحدود' : fmt( pool ) + ' ' + unit;
				// «Unlimited»: the amount becomes the first pool («تنظیم مقدار دقیق»); nothing to take away.
				var removeOp = dialog.querySelector( '[data-avs-op-remove]' );
				if ( removeOp ) {
					removeOp.hidden = unlimited;
				}
				var note = dialog.querySelector( '[data-avs-unlimited-note]' );
				if ( note ) {
					note.hidden = ! unlimited;
				}
				if ( unlimited ) {
					form.querySelector( '[name="operation"][value="set"]' ).checked = true;
				}
				preview.textContent = '';
				dialog.showModal();
				amount.focus();
				return;
			}
			if ( e.target.closest( '[data-avs-close]' ) ) {
				dialog.close();
			}
		} );

		dialog.addEventListener( 'click', function ( e ) {
			if ( e.target === dialog ) {
				dialog.close(); // Click on the backdrop.
			}
		} );

		form.addEventListener( 'input', updatePreview );
		form.addEventListener( 'change', updatePreview );
		form.addEventListener( 'submit', function ( e ) {
			if ( preview.classList.contains( 'is-error' ) ) {
				e.preventDefault();
				amount.focus();
			}
		} );
	}

	// ----- Confirm forms --------------------------------------------------
	document.querySelectorAll( 'form[data-avs-confirm]' ).forEach( function ( f ) {
		f.addEventListener( 'submit', function ( e ) {
			if ( ! window.confirm( f.getAttribute( 'data-avs-confirm' ) ) ) {
				e.preventDefault();
			}
		} );
	} );

	// ----- Enable wizard: live suggested pool ------------------------------
	var enableForm = document.querySelector( '[data-avs-enable-form]' );
	if ( enableForm ) {
		var suggested = enableForm.querySelector( '[data-avs-suggested]' );
		var poolInput = enableForm.querySelector( '[name="pool_ml"]' );
		var touched = false;
		poolInput.addEventListener( 'input', function () {
			touched = true;
		} );
		enableForm.addEventListener( 'input', function ( e ) {
			if ( ! e.target.hasAttribute( 'data-avs-ml' ) ) {
				return;
			}
			var total = 0;
			enableForm.querySelectorAll( '[data-avs-ml]' ).forEach( function ( input ) {
				total += ( parseInt( input.value, 10 ) || 0 ) * ( parseInt( input.getAttribute( 'data-stock' ), 10 ) || 0 );
			} );
			if ( suggested ) {
				suggested.textContent = fmt( total ) + ' ' + ( suggested.textContent.split( ' ' ).pop() || '' );
			}
			if ( ! touched ) {
				poolInput.value = total;
			}
		} );
	}
} )();
