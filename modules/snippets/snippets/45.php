<?php
// اسنیپت #45: نمایش متغیر ها برای باکس محصول
defined( 'ABSPATH' ) || exit;

/**
 * Aromist – نمایش حجم‌های عطر به صورت دایره‌ای در باکس محصولات
 * با کلیک روی هر حجم، قیمت همان باکس به قیمت آن حجم تغییر می‌کند.
 * به صورت پیش‌فرض ارزان‌ترین حجم موجود انتخاب شده و قیمتش نمایش داده می‌شود.
 *
 * موبایل و تبلت (تا ۱۰۲۴ پیکسل): همان دایره‌های دسکتاپ (عدد و «میل» زیرش) ولی فقط ۳ حجم اول؛ حجم‌های
 * بعدی کنارشان به‌شکل دایره‌های کوچک کرمیِ چسبیده به هم می‌آیند (اگر جا نبود، زیر دایره‌ها).
 * اگر حجم پیش‌فرض بعد از سومی باشد، جای حجم سوم دیده می‌شود. دسکتاپ بدون تغییر است.
 *
 * شورت‌کد:  [aromist_volumes]
 * پارامترها (همه اختیاری):
 *   attribute = نامک ویژگی، مثل pa_volume یا volume  (خالی = اولین ویژگی متغیر محصول)
 *   limit     = تعداد دایره‌های قابل نمایش در دسکتاپ (پیش‌فرض ۳)
 *   unit      = واحد زیر عدد (پیش‌فرض «میل»)
 *   hide_out  = yes تا حجم‌های ناموجود نمایش داده نشوند (پیش‌فرض no = کم‌رنگ نمایش داده می‌شوند)
 *   id        = شناسه محصول (برای استفاده بیرون از لوپ محصولات)
 *   price     = سلکتور CSS المان قیمت داخل باکس (پیش‌فرض .price)
 *
 * مثال: [aromist_volumes attribute="pa_volume" limit="3"]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aromist_vol_fa_digits( $str ) {
	return strtr( (string) $str, array(
		'0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
		'5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
	) );
}

function aromist_vol_en_digits( $str ) {
	return strtr( (string) $str, array(
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
		'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	) );
}

// قیمت یک حجم: HTML قیمت + کمترین قیمت عددی (برای انتخاب پیش‌فرض)
// اگر چند متغیر با قیمت متفاوت داشته باشد، بازه قیمت نمایش داده می‌شود
function aromist_vol_price( $variations ) {
	$pool = array_filter( $variations, function ( $v ) {
		return $v->is_in_stock();
	} );
	if ( empty( $pool ) ) {
		$pool = $variations;
	}

	$min = $max = $cheapest = null;
	foreach ( $pool as $v ) {
		if ( '' === $v->get_price() ) {
			continue;
		}
		$p = (float) wc_get_price_to_display( $v );
		if ( null === $min || $p < $min ) {
			$min      = $p;
			$cheapest = $v;
		}
		if ( null === $max || $p > $max ) {
			$max = $p;
		}
	}

	if ( ! $cheapest ) {
		return array( 'html' => '', 'min' => null );
	}

	return array(
		'html' => $min === $max ? $cheapest->get_price_html() : wc_format_price_range( $min, $max ),
		'min'  => $min,
	);
}

// استایل و اسکریپت فقط یک بار در هر صفحه چاپ می‌شود
function aromist_vol_assets() {
	static $printed = false;
	if ( $printed ) {
		return '';
	}
	$printed = true;

	return '<style>
.arv{display:inline-flex;align-items:center;gap:6px;direction:rtl;flex-wrap:nowrap;vertical-align:middle;font-family:inherit}
.arv-dot{width:38px;height:38px;border-radius:50%;box-sizing:border-box;flex:0 0 auto;display:inline-flex;flex-direction:column;align-items:center;justify-content:center;line-height:1;background:#fff;color:#009879;border:1.5px solid rgba(0,152,121,.35);transition:border-color .2s,background-color .2s,color .2s;-webkit-tap-highlight-color:transparent;user-select:none}
.arv-dot b{font-size:12px;font-weight:700}
.arv-dot small{font-size:8px;margin-top:2px;opacity:.75}
.arv-dot:hover{border-color:#009879;background:rgba(0,152,121,.06)}
.arv-dot[data-price]{cursor:pointer}
.arv-dot[data-price]:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(0,152,121,.25)}
.arv-dot.is-out{color:#a3a3a3;border-color:#e2e2e2;border-style:dashed;background:#fafafa}
.arv-dot.is-active,.arv-dot.is-active:hover{background:#009879;border-color:#009879;color:#fff}
.arv-dot.is-out.is-active,.arv-dot.is-out.is-active:hover{background:#a3a3a3;border-color:#a3a3a3;color:#fff}
.arv-more{direction:ltr;unicode-bidi:isolate;font-size:12px;font-weight:700;color:#9a7a2e;background:rgba(215,181,106,.14);border-color:rgba(215,181,106,.65)}
.arv-more:hover{border-color:#D7B56A;background:rgba(215,181,106,.22)}
.arv-m-more{display:none}
.arv-price-swap{animation:arvPrice .3s ease}
@keyframes arvPrice{from{opacity:.3}to{opacity:1}}
@media (max-width:1024px){
.arv{max-width:100%}
.arv-dot{flex:0 1 38px;min-width:0}
.arv-dot.arv-x,.arv-dot.arv-more{display:none}
.arv-m-more{display:inline-flex;align-items:center;vertical-align:middle;direction:rtl;margin:2px 0 2px 0;margin-inline-start:2px}
.arv-m-more span{width:26px;height:26px;border-radius:50%;box-sizing:border-box;flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;background:#f7eed8;border:1.5px solid rgba(215,181,106,.8);color:#8a6d22;font-size:9px;font-weight:700;line-height:1;unicode-bidi:isolate}
.arv-m-more span+span{margin-inline-start:-6px}
html body .arv.arv.arv{gap:6px!important}
html body .arv.arv.arv::after{content:none!important;display:none!important}
html body .arv.arv.arv .arv-dot.arv-dot{width:38px!important;height:38px!important;border-width:1.5px!important}
html body .arv.arv.arv .arv-dot.arv-dot b{font-size:12px!important;line-height:1!important}
html body .arv.arv.arv .arv-dot.arv-dot small{display:block!important;font-size:8px!important;line-height:1!important;margin-top:2px!important}
}
@media (prefers-reduced-motion:reduce){.arv-price-swap{animation:none}}
</style>
<script>
(function () {
	if (window.aromistVolInit) return;
	window.aromistVolInit = true;

	// باکس محصول در لوپ المنتور / جت‌انجین / ووکامرس
	var CARD = ".e-loop-item,.jet-listing-grid__item,li.product,.type-product";
	var DOT = ".arv-dot[data-price]";

	// آیا مرورگر خواندن این المان را تمام کرده؟ (تا وسط بارگذاری صفحه، المان نیمه‌کاره را عوض نکنیم)
	function parsed(el) {
		if (document.readyState !== "loading") return true;
		for (var n = el; n && n !== document.body; n = n.parentNode) {
			if (n.nextSibling) return true;
		}
		return false;
	}

	function findPrices(box) {
		var sel = box.getAttribute("data-price-el") || ".price";
		var scope = box.closest(CARD);

		// اگر باکس شناخته نشد، از خود شورت‌کد بالا می‌رویم تا به اولین قیمت برسیم
		// (و قبل از رسیدن به باکس محصول دیگری متوقف می‌شویم)
		if (!scope) {
			for (var el = box.parentElement; el && el !== document.body; el = el.parentElement) {
				if (el.querySelectorAll(".arv").length > 1) break;
				if (el.querySelector(sel)) { scope = el; break; }
			}
		}
		if (!scope) return [];

		return Array.prototype.filter.call(scope.querySelectorAll(sel), function (p) {
			return !box.contains(p) && !(p.parentElement && p.parentElement.closest(sel));
		});
	}

	function showPrice(dot, prices, animate) {
		prices.forEach(function (p) {
			p.innerHTML = dot.getAttribute("data-price");
			if (!animate) return;
			p.classList.remove("arv-price-swap");
			void p.offsetWidth;
			p.classList.add("arv-price-swap");
		});
	}

	function select(dot) {
		var box = dot.closest(".arv");
		if (!box || dot.classList.contains("is-active")) return;

		box.removeAttribute("data-arv-init");
		box.querySelectorAll(".arv-dot.is-active").forEach(function (d) {
			d.classList.remove("is-active");
			d.setAttribute("aria-checked", "false");
		});
		dot.classList.add("is-active");
		dot.setAttribute("aria-checked", "true");

		showPrice(dot, findPrices(box), true);
	}

	// قیمت حجم پیش‌فرض (ارزان‌ترین) را روی باکس‌هایی که تازه به صفحه آمده‌اند اعمال می‌کند
	function init() {
		document.querySelectorAll(".arv[data-arv-init]").forEach(function (box) {
			if (!parsed(box)) return;
			var dot = box.querySelector(".arv-dot.is-active[data-price]");
			var prices = dot ? findPrices(box) : [];

			if (!prices.length || !prices.every(parsed)) {
				// صفحه کامل لود شده و قیمتی پیدا نشد: دیگر دنبالش نگرد
				if (document.readyState !== "loading") box.removeAttribute("data-arv-init");
				return;
			}

			box.removeAttribute("data-arv-init");
			showPrice(dot, prices, false);
		});
	}

	// باکس‌ها و قیمت‌ها همان لحظه‌ای که به صفحه اضافه می‌شوند (قبل از نمایش) مقداردهی می‌شوند؛
	// شامل صفحه‌بندی ایجکسی لوپ، فیلترها و ادیتور المنتور
	new MutationObserver(function (list) {
		for (var i = 0; i < list.length; i++) {
			if (list[i].addedNodes.length) { init(); return; }
		}
	}).observe(document.documentElement, { childList: true, subtree: true });
	document.addEventListener("DOMContentLoaded", init);

	// capture تا کلیک به لینک باکس محصول نرسد و صفحه عوض نشود
	document.addEventListener("click", function (e) {
		var dot = e.target.closest && e.target.closest(DOT);
		if (!dot) return;
		e.preventDefault();
		e.stopPropagation();
		select(dot);
	}, true);

	document.addEventListener("keydown", function (e) {
		if (e.key !== "Enter" && e.key !== " ") return;
		var dot = e.target.closest && e.target.closest(DOT);
		if (!dot) return;
		e.preventDefault();
		e.stopPropagation();
		select(dot);
	}, true);
})();
</script>';
}

add_shortcode( 'aromist_volumes', 'aromist_volumes_shortcode' );

function aromist_volumes_shortcode( $atts ) {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return '';
	}

	$atts = shortcode_atts( array(
		'id'        => 0,
		'attribute' => '',
		'limit'     => 3,
		'unit'      => 'میل',
		'hide_out'  => 'no',
		'price'     => '',
	), $atts, 'aromist_volumes' );

	// پیدا کردن محصول فعلی (داخل لوپ) یا با id
	if ( $atts['id'] ) {
		$product = wc_get_product( absint( $atts['id'] ) );
	} else {
		$product = isset( $GLOBALS['product'] ) && is_object( $GLOBALS['product'] )
			? $GLOBALS['product']
			: wc_get_product( get_the_ID() );
	}

	if ( ! $product || ! $product->is_type( 'variable' ) ) {
		return '';
	}

	$var_attrs = $product->get_variation_attributes();
	if ( empty( $var_attrs ) ) {
		return '';
	}

	// انتخاب ویژگی: pa_volume یا volume یا اولین ویژگی متغیر
	$attr = trim( $atts['attribute'] );
	if ( $attr && ! isset( $var_attrs[ $attr ] ) && isset( $var_attrs[ 'pa_' . $attr ] ) ) {
		$attr = 'pa_' . $attr;
	}
	if ( ! $attr || ! isset( $var_attrs[ $attr ] ) ) {
		$attr = array_key_first( $var_attrs );
	}

	$is_tax  = taxonomy_exists( $attr );
	$var_key = sanitize_title( $attr );

	// وضعیت موجودی و متغیرهای هر حجم
	$stock = array();
	$vars  = array();
	foreach ( $product->get_children() as $child_id ) {
		$variation = wc_get_product( $child_id );
		if ( ! $variation || 'publish' !== $variation->get_status() ) {
			continue;
		}
		$values = $variation->get_attributes();
		$value  = isset( $values[ $var_key ] ) ? $values[ $var_key ] : '';
		if ( '' === $value ) {
			continue;
		}
		$key            = $is_tax ? $value : sanitize_title( $value );
		$stock[ $key ]  = ! empty( $stock[ $key ] ) || $variation->is_in_stock();
		$vars[ $key ][] = $variation;
	}

	$items = array();
	foreach ( $var_attrs[ $attr ] as $value ) {
		if ( $is_tax ) {
			$term  = get_term_by( 'slug', $value, $attr );
			$label = $term ? $term->name : $value;
			$key   = $value;
		} else {
			$label = $value;
			$key   = sanitize_title( $value );
		}

		$in_stock = isset( $stock[ $key ] ) ? $stock[ $key ] : true;
		if ( 'yes' === $atts['hide_out'] && ! $in_stock ) {
			continue;
		}

		preg_match( '/\d+(?:\.\d+)?/', aromist_vol_en_digits( $label ), $m );
		$price = isset( $vars[ $key ] ) ? aromist_vol_price( $vars[ $key ] ) : array( 'html' => '', 'min' => null );

		$items[] = array(
			'label' => $label,
			'num'   => isset( $m[0] ) ? $m[0] : null,
			'in'    => $in_stock,
			'price' => $price['html'],
			'min'   => $price['min'],
		);
	}

	if ( empty( $items ) ) {
		return '';
	}

	// مرتب‌سازی از کم‌حجم به پرحجم
	usort( $items, function ( $a, $b ) {
		if ( null === $a['num'] ) {
			return 1;
		}
		if ( null === $b['num'] ) {
			return -1;
		}
		return (float) $a['num'] <=> (float) $b['num'];
	} );

	$limit = max( 1, (int) $atts['limit'] );
	$shown = array_slice( $items, 0, $limit );
	$rest  = count( $items ) - count( $shown );

	// حجم پیش‌فرض: ارزان‌ترین حجم موجود (اگر هیچ‌کدام موجود نبود، ارزان‌ترین از بین همه)
	$default = null;
	foreach ( $shown as $i => $item ) {
		if ( '' === $item['price'] ) {
			continue;
		}
		if ( null === $default
			|| ( $item['in'] && ! $shown[ $default ]['in'] )
			|| ( $item['in'] === $shown[ $default ]['in'] && $item['min'] < $shown[ $default ]['min'] ) ) {
			$default = $i;
		}
	}

	// موبایل و تبلت: فقط ۳ حجم اول دیده می‌شوند (بقیه با CSS پنهان می‌شوند و جایشان «N حجم دیگر» می‌آید).
	// اگر حجم پیش‌فرض بعد از سومی باشد، به‌جای حجم سوم دیده می‌شود تا قیمت نمایش‌داده‌شده یک دایره‌ی انتخاب‌شده داشته باشد.
	$mob_vis = array_slice( array_keys( $shown ), 0, 3 );
	if ( null !== $default && ! in_array( $default, $mob_vis, true ) ) {
		$mob_vis[ count( $mob_vis ) - 1 ] = $default;
	}
	$mob_more = count( $items ) - count( $mob_vis );

	$out  = aromist_vol_assets();
	$out .= '<div class="arv" role="radiogroup" aria-label="حجم‌های موجود"';
	if ( $atts['price'] ) {
		$out .= ' data-price-el="' . esc_attr( $atts['price'] ) . '"';
	}
	if ( null !== $default ) {
		$out .= ' data-arv-init';
	}
	$out .= '>';

	foreach ( $shown as $i => $item ) {
		$active = $i === $default;
		$title  = $item['label'] . ( $item['in'] ? '' : ' (ناموجود)' );
		$out   .= '<span class="arv-dot' . ( $item['in'] ? '' : ' is-out' ) . ( $active ? ' is-active' : '' ) . ( in_array( $i, $mob_vis, true ) ? '' : ' arv-x' ) . '" title="' . esc_attr( $title ) . '"';
		if ( '' !== $item['price'] ) {
			$out .= ' role="radio" tabindex="0" aria-checked="' . ( $active ? 'true' : 'false' ) . '" data-price="' . esc_attr( $item['price'] ) . '"';
		}
		$out .= '>';
		if ( null !== $item['num'] ) {
			$out .= '<b>' . esc_html( aromist_vol_fa_digits( $item['num'] ) ) . '</b>';
			$out .= '<small>' . esc_html( $atts['unit'] ) . '</small>';
		} else {
			$out .= '<b>' . esc_html( $item['label'] ) . '</b>';
		}
		$out .= '</span>';
	}

	if ( $rest > 0 ) {
		$out .= '<span class="arv-dot arv-more" title="' . esc_attr( aromist_vol_fa_digits( $rest ) . ' حجم دیگر' ) . '">+' . esc_html( aromist_vol_fa_digits( $rest ) ) . '</span>';
	}

	$out .= '</div>';

	// فقط موبایل و تبلت: حجم‌های پنهان به‌شکل چند دایره‌ی کرمیِ چسبیده به هم، کنار دایره‌ها (در دسکتاپ با CSS پنهان است).
	// فاصله‌ی بین دو تگ عمدی است: اگر جا نبود، دایره‌های کرمی با همین فاصله به خط بعد می‌روند.
	if ( $mob_more > 0 ) {
		$hid = array_values( array_diff( array_keys( $items ), $mob_vis ) );
		$out .= ' <span class="arv-m-more" role="img" aria-label="' . esc_attr( aromist_vol_fa_digits( $mob_more ) . ' حجم دیگر' ) . '">';
		foreach ( array_slice( $hid, 0, $mob_more > 3 ? 2 : 3 ) as $k ) {
			$out .= '<span>' . esc_html( null !== $items[ $k ]['num'] ? aromist_vol_fa_digits( $items[ $k ]['num'] ) : $items[ $k ]['label'] ) . '</span>';
		}
		if ( $mob_more > 3 ) {
			$out .= '<span>+' . esc_html( aromist_vol_fa_digits( $mob_more - 2 ) ) . '</span>';
		}
		$out .= '</span>';
	}

	return $out;
}
