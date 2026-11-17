<?php
// اسنیپت #29: تعداد محصول در صفحه تکی
defined( 'ABSPATH' ) || exit;


/**
 * Aromist – فیلد تعداد سفارشی (دکمه‌های + و −) برای صفحه محصول
 *
 * روی فیلد تعداد خود ووکامرس اعمال می‌شود؛ پس ویجت «افزودن به سبد خرید» المنتور
 * بدون هیچ تنظیم اضافه‌ای همین ظاهر را می‌گیرد. شورت‌کد ندارد.
 *
 * در Code Snippets: نوع PHP، اجرا در همه جا.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- ۱) اضافه کردن + و − دور فیلد تعداد ووکامرس ---------- */
add_action( 'woocommerce_before_quantity_input_field', function () {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return;
	}
	$GLOBALS['aromist_qty_used'] = true;
	echo '<button type="button" class="ac-qty-btn ac-plus" aria-label="افزایش تعداد"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>';
} );

add_action( 'woocommerce_after_quantity_input_field', function () {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return;
	}
	echo '<button type="button" class="ac-qty-btn ac-minus" aria-label="کاهش تعداد"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg></button>';
} );

/* ---------- ۲) استایل ---------- */
function aromist_qty_css() {
	return <<<'CSS'
body .quantity.hidden{display:none !important}
body .quantity{
	direction:rtl !important;display:flex !important;flex-direction:row !important;justify-content:center !important;align-items:center !important;
	width:89px !important;height:48px !important;border-radius:8px !important;background:#fff !important;
	padding:8px !important;margin:0 0 18px !important;border:0 !important;box-shadow:none !important;box-sizing:border-box !important;float:none !important;
}
body .quantity .ac-qty-btn{
	display:flex !important;align-items:center !important;justify-content:center !important;flex:0 0 24px !important;
	width:24px !important;height:24px !important;min-width:0 !important;min-height:0 !important;
	border-radius:8px !important;color:#221E1F !important;background:#fff !important;
	padding:0 !important;margin:0 !important;border:0 !important;box-shadow:none !important;cursor:pointer;line-height:1 !important;
}
body .quantity .ac-qty-btn:hover,
body .quantity .ac-qty-btn:focus{color:#009879 !important;background:#fff !important}
body .quantity .ac-qty-btn svg{width:14px !important;height:14px !important;display:block !important}
body .quantity input.qty{
	-webkit-appearance:none !important;-moz-appearance:textfield !important;appearance:none !important;
	flex:0 0 36px !important;width:36px !important;min-width:0 !important;max-width:36px !important;height:auto !important;
	font-size:20px !important;line-height:37px !important;font-family:inherit !important;color:#221E1F !important;
	text-align:center !important;border:none !important;border-radius:0 !important;
	padding:4px 0 0 !important;margin:0 !important;background:transparent !important;box-shadow:none !important;outline:0 !important;
}
body .quantity input.qty::-webkit-outer-spin-button,
body .quantity input.qty::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
CSS;
}

// چاپ CSS در صفحات محصول (در هد) — و در صورت استفاده در صفحات دیگر، در فوتر
add_action( 'wp_head', function () {
	if ( function_exists( 'is_product' ) && is_product() ) {
		$GLOBALS['aromist_qty_css_printed'] = true;
		echo '<style id="aromist-qty-css">' . aromist_qty_css() . '</style>'; // phpcs:ignore
	}
}, 99 );

/* ---------- ۳) جاوااسکریپت + / − ---------- */
add_action( 'wp_footer', function () {
	if ( empty( $GLOBALS['aromist_qty_used'] ) && ! ( function_exists( 'is_product' ) && is_product() ) ) {
		return;
	}
	if ( empty( $GLOBALS['aromist_qty_css_printed'] ) ) {
		echo '<style id="aromist-qty-css">' . aromist_qty_css() . '</style>'; // phpcs:ignore
	}
	?>
	<script id="aromist-qty-js">
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.quantity .ac-qty-btn');
		if (!btn) { return; }
		e.preventDefault();

		var input = btn.parentNode.querySelector('input.qty');
		if (!input) { return; }

		var val  = parseFloat(input.value) || 0,
			step = parseFloat(input.getAttribute('step')) || 1,
			min  = parseFloat(input.getAttribute('min')),
			max  = parseFloat(input.getAttribute('max'));

		if (isNaN(min)) { min = 1; }

		if (btn.classList.contains('ac-plus')) {
			val += step;
			if (!isNaN(max) && max > 0 && val > max) { val = max; }
		} else {
			val -= step;
			if (val < min) { val = min; }
		}

		input.value = val;
		input.dispatchEvent(new Event('change', { bubbles: true }));
	});
	</script>
	<?php
}, 99 );
