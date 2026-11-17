<?php
// اسنیپت #30: باکس افزودن به سبد خرید صفحه محصول
defined( 'ABSPATH' ) || exit;


/**
 * Aromist – طراحی باکس «افزودن به سبد خرید» صفحه محصول
 *
 * روی ویجت «افزودن به سبد خرید» المنتور (و فرم پیش‌فرض ووکامرس) اعمال می‌شود. شورت‌کد ندارد.
 * این اسنیپت همراه اسنیپت «فیلد تعداد» (aromist-quantity-snippet) کار می‌کند؛ هر دو فعال باشند.
 *
 * در Code Snippets: نوع PHP، اجرا در همه جا.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * ۱) برچسب درصد تخفیف
 * ---------------------------------------------------------------------- */
function aromist_atc_sale_badge( $product ) {
	if ( ! $product || ! $product->is_on_sale() ) {
		return '';
	}
	$regular = (float) $product->get_regular_price();
	$current = (float) $product->get_price();
	if ( $regular <= 0 || $current >= $regular ) {
		return '';
	}
	$percent = (int) round( ( ( $regular - $current ) / $regular ) * 100 );
	return '<span class="on-sale"><span>' . esc_html( $percent ) . '<span class="percentage">%</span></span></span>';
}

/* -------------------------------------------------------------------------
 * ۲) محصول متغیر: قیمت همیشه داخل باکس نمایش داده شود (حتی اگر قیمت همه متغیرها یکی باشد)
 *    + برچسب درصد تخفیف کنار قیمت
 * ---------------------------------------------------------------------- */
add_filter( 'woocommerce_show_variation_price', '__return_true', 99 );

add_filter( 'woocommerce_available_variation', function ( $data, $product, $variation ) {
	$data['price_html'] = '<span class="price">' . $variation->get_price_html() . aromist_atc_sale_badge( $variation ) . '</span>';
	return $data;
}, 99, 3 );

/* -------------------------------------------------------------------------
 * ۲-ب) انتخاب پیش‌فرض: اولین متغیر (اولین متغیرِ موجود در انبار) همیشه انتخاب‌شده باشد
 *      اگر در ویرایش محصول «مقدار پیش‌فرض فرم» تنظیم شده باشد، همان حفظ می‌شود.
 * ---------------------------------------------------------------------- */
add_filter( 'woocommerce_product_get_default_attributes', function ( $defaults, $product ) {
	static $cache = array(), $running = false;

	if ( ( is_admin() && ! wp_doing_ajax() ) || $running || ! empty( $defaults ) || ! $product || ! $product->is_type( 'variable' ) ) {
		return $defaults;
	}

	$id = $product->get_id();
	if ( isset( $cache[ $id ] ) ) {
		return $cache[ $id ];
	}

	$running    = true;
	$attributes = $product->get_variation_attributes(); // نام ویژگی => گزینه‌ها
	$result     = $defaults;

	if ( $attributes ) {
		// گزینه‌ها به همان ترتیبی که در صفحه نمایش داده می‌شوند
		$ordered = array();
		foreach ( $attributes as $name => $options ) {
			if ( taxonomy_exists( $name ) ) {
				$terms            = wc_get_product_terms( $id, $name, array( 'fields' => 'slugs' ) );
				$ordered[ $name ] = array_values( array_intersect( $terms, $options ) );
			} else {
				$ordered[ $name ] = array_values( $options );
			}
		}

		$names      = array_keys( $ordered );
		$first_name = $names[0];
		$variations = $product->get_available_variations();

		// اولین گزینه‌ی ویژگی اول که یک متغیرِ موجود و قابل خرید دارد
		foreach ( $ordered[ $first_name ] as $option ) {
			foreach ( $variations as $variation ) {
				$attr_key = 'attribute_' . sanitize_title( $first_name );
				$value    = isset( $variation['attributes'][ $attr_key ] ) ? $variation['attributes'][ $attr_key ] : '';

				if ( ( '' !== $value && $value !== $option ) || empty( $variation['is_in_stock'] ) || empty( $variation['is_purchasable'] ) || empty( $variation['variation_is_active'] ) ) {
					continue;
				}

				$result = array();
				foreach ( $names as $name ) {
					$key = 'attribute_' . sanitize_title( $name );
					$val = isset( $variation['attributes'][ $key ] ) ? $variation['attributes'][ $key ] : '';
					if ( $name === $first_name ) {
						$val = $option;
					} elseif ( '' === $val ) {
						$val = isset( $ordered[ $name ][0] ) ? $ordered[ $name ][0] : '';
					}
					$result[ sanitize_title( $name ) ] = $val;
				}
				break 2;
			}
		}

		// اگر هیچ متغیر موجودی نبود: اولین گزینه‌ی هر ویژگی
		if ( empty( $result ) ) {
			foreach ( $ordered as $name => $options ) {
				if ( isset( $options[0] ) ) {
					$result[ sanitize_title( $name ) ] = $options[0];
				}
			}
		}
	}

	$running       = false;
	$cache[ $id ]  = $result;
	return $result;
}, 20, 2 );

/* -------------------------------------------------------------------------
 * ۳) محصول ساده: قیمت کنار فیلد تعداد داخل همین باکس
 * ---------------------------------------------------------------------- */
add_action( 'woocommerce_before_add_to_cart_quantity', function () {
	global $product;
	if ( ! $product || ! $product->is_type( 'simple' ) || '' === $product->get_price() ) {
		return;
	}
	echo '<div class="woocommerce-variation-price ac-simple-price"><span class="price">' . $product->get_price_html() . aromist_atc_sale_badge( $product ) . '</span></div>'; // phpcs:ignore
} );

/* -------------------------------------------------------------------------
 * ۴) استایل‌ها
 * ---------------------------------------------------------------------- */
function aromist_atc_css() {
	return <<<'CSS'
/* ===== باکس اصلی ===== */
body.single-product form.cart:not(.grouped_form){
	position:relative !important;display:block !important;background:none !important;
	padding:0 !important;border-radius:0 !important;margin:0 !important;border:0 !important;box-shadow:none !important;box-sizing:border-box;
	direction:rtl;text-align:right;color:#221E1F;
}
body.single-product form.cart:not(.grouped_form)::after{content:"";display:table;clear:both}
body.single-product form.cart .screen-reader-text{
	position:absolute !important;width:1px !important;height:1px !important;padding:0 !important;margin:-1px !important;
	overflow:hidden !important;clip:rect(0,0,0,0) !important;white-space:nowrap !important;border:0 !important;
}

/* ===== جدول متغیرها ===== */
body.single-product form.cart table.variations{
	display:block !important;width:100% !important;position:relative !important;margin:0 !important;padding:0 !important;
	border:0 !important;background:none !important;border-collapse:separate;
}
body.single-product form.cart table.variations tbody{display:block !important}
body.single-product form.cart table.variations tr{
	display:flex !important;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;
	margin:0 0 8px !important;padding:0 !important;border:0 !important;background:none !important;
}
body.single-product form.cart table.variations th,
body.single-product form.cart table.variations td{
	display:block !important;padding:0 !important;margin:0 !important;border:0 !important;background:none !important;
	width:auto !important;text-align:right !important;line-height:1.6 !important;vertical-align:middle;
}
body.single-product form.cart table.variations td.value{display:flex !important;flex-wrap:wrap;flex:0 0 100%;max-width:100%}
body.single-product form.cart table.variations td.value::before{display:none !important}
body.single-product form.cart table.variations label{
	display:block !important;font-size:16px !important;line-height:23px !important;font-weight:500 !important;color:#221E1F !important;margin:0 !important;
}
body.single-product form.cart table.variations .woo-selected-variation-item-name{display:none !important}

/* ===== دکمه‌های متغیر: ردیف‌های ۳تایی (تا هر تعداد) ===== */
body.single-product form.cart .variable-items-wrapper{
	display:grid !important;grid-template-columns:repeat(3,minmax(0,1fr)) !important;gap:4px !important;
	margin:12px 0 24px !important;padding:0 !important;list-style:none !important;width:100%;
}
body.single-product form.cart .variable-items-wrapper .variable-item:not(.radio-variable-item){
	background:#B3E0D7 !important;color:#221E1F !important;box-shadow:none !important;border:0 !important;
	height:32px !important;width:100% !important;min-width:0 !important;max-width:none !important;border-radius:32px !important;
	margin:0 !important;padding:0 6px !important;font-size:14px !important;line-height:1 !important;overflow:hidden;
	cursor:pointer;transition:.4s;display:flex !important;align-items:center;justify-content:center;
}
body.single-product form.cart .variable-items-wrapper .variable-item:not(.radio-variable-item):hover,
body.single-product form.cart .variable-items-wrapper .variable-item:not(.radio-variable-item).selected,
body.single-product form.cart .variable-items-wrapper .variable-item:not(.radio-variable-item).selected:hover{
	background:#009879 !important;color:#fff !important;box-shadow:none !important;
}
body.single-product form.cart .variable-items-wrapper .variable-item .variable-item-contents{
	border-radius:8px !important;overflow:hidden !important;margin:0 !important;padding:0 !important;background:none !important;
}
body.single-product form.cart .variable-items-wrapper .variable-item .variable-item-span{
	padding:0 !important;color:inherit !important;font-size:inherit !important;line-height:1 !important;white-space:nowrap;
}
body.single-product form.cart .variable-items-wrapper .variable-item.disabled,
body.single-product form.cart .variable-items-wrapper .variable-item.no-stock{opacity:.5}
body.single-product form.cart .variable-items-wrapper .variable-item.image-variable-item{
	width:48px !important;height:48px !important;padding:0 !important;background:none !important;border-radius:8px !important;
}
/* اگر افزونه سواچ فعال نباشد: لیست کشویی */
body.single-product form.cart table.variations select:not(.woo-variation-raw-select){
	width:100%;border:1px solid #DADADA;padding:10px 16px;font-family:inherit;border-radius:12px;color:#707070;
	font-size:14px;line-height:26px;background:#fff;cursor:pointer;margin:12px 0 24px;
}

/* ===== دکمه «پاک کردن» ===== */
body.single-product form.cart .reset_variations{
	position:absolute !important;left:0 !important;right:auto !important;top:-4px !important;margin:10px 0 !important;
	display:inline-block !important;background:#D7B46A !important;border:1px solid #D7B46A !important;border-radius:5px !important;
	padding:0 10px !important;font-size:12px !important;line-height:22px !important;color:#fff !important;
	text-decoration:none !important;transition:.4s;
}
body.single-product form.cart .reset_variations:hover{background:#fff !important;color:#D7B46A !important}

/* ===== بخش قیمت + تعداد + دکمه =====
 * ردیف اول: تعداد (راست) + قیمت (چپ) | ردیف دوم: دکمه | ردیف سوم: اسنپ‌پی
 */
body.single-product form.cart .single_variation_wrap,
body.single-product form.cart:not(.variations_form):not(.grouped_form){
	display:flex !important;flex-wrap:wrap !important;align-items:center !important;gap:16px 12px !important;width:100%;
}
body.single-product form.cart:not(.grouped_form)::after{display:none}
body.single-product form.cart .single_variation_wrap > *,
body.single-product form.cart:not(.variations_form):not(.grouped_form) > *,
body.single-product form.cart .variations_button > *{order:5;flex:0 0 100%;max-width:100%;min-width:0}
body.single-product form.cart .woocommerce-variation-add-to-cart,
body.single-product form.cart .variations_button{display:contents !important}

/* تعداد: سمت راست */
body.single-product form.cart .quantity{order:1 !important;flex:0 0 auto !important;margin:0 !important;float:none !important}

/* قیمت: سمت چپ، هم‌ردیف تعداد (هر چند رقمی باشد به‌هم نمی‌ریزد) */
body.single-product form.cart .single_variation,
body.single-product form.cart .ac-simple-price{order:2 !important;flex:1 1 0% !important;min-width:0 !important;margin:0 !important}
body.single-product form.cart .woocommerce-variation-description{font-size:13px;line-height:1.9;color:#707070}
body.single-product form.cart .woocommerce-variation-description:empty{display:none}

/* قیمت: سمت چپ، هم‌ردیف فیلد تعداد */
body.single-product form.cart .woocommerce-variation-price{
	min-height:46px !important;display:flex !important;align-items:center !important;justify-content:flex-end !important;
	float:none !important;margin:0 !important;min-width:0;
}
body.single-product form.cart .woocommerce-variation-price .price{
	display:flex !important;flex-direction:column !important;float:none !important;position:relative !important;
	padding:0 45px 0 0 !important;margin:0 !important;color:#221E1F !important;max-width:100%;
	font-size:clamp(15px,1.6vw,18px) !important;line-height:32px !important;font-weight:400 !important;white-space:nowrap !important;
	align-items:flex-start !important;
}
body.single-product form.cart .woocommerce-variation-price .price bdi{white-space:nowrap !important;display:inline-flex;align-items:center;gap:3px}
body.single-product form.cart .woocommerce-variation-price .price del{
	color:#707070 !important;line-height:14px !important;font-size:14px !important;opacity:1 !important;
}
body.single-product form.cart .woocommerce-variation-price .price del *{color:#707070 !important}
body.single-product form.cart .woocommerce-variation-price .price del .woocommerce-Price-currencySymbol{display:none !important}
body.single-product form.cart .woocommerce-variation-price .price ins{
	line-height:32px !important;text-decoration:none !important;font-size:clamp(15px,1.6vw,18px) !important;
	background:none !important;color:#221E1F !important;font-weight:400 !important;
}
body.single-product form.cart .woocommerce-variation-price .price ins .woocommerce-Price-currencySymbol,
body.single-product form.cart .woocommerce-variation-price .price > .amount .woocommerce-Price-currencySymbol{font-size:14px}
body.single-product form.cart .woocommerce-variation-price .price .on-sale{
	position:absolute !important;top:50% !important;transform:translateY(-50%) !important;right:0 !important;left:auto !important;
	display:flex !important;align-items:center;justify-content:center;width:36px !important;height:46px !important;
	border-radius:8px !important;background:#D7B46A !important;color:#221E1F !important;font-size:16px !important;
	margin:0 !important;padding:0 !important;min-width:0 !important;min-height:0 !important;line-height:1 !important;
}
body.single-product form.cart .woocommerce-variation-price .price .on-sale .percentage{font-size:12px}

/* موجودی */
body.single-product form.cart .stock.in-stock{display:none !important}
body.single-product form.cart .stock.out-of-stock,
body.single-product form.cart .stock.available-on-backorder{
	display:flex !important;align-items:center;justify-content:center;width:100%;min-height:48px;margin:8px 0 0 !important;
	border-radius:16px;background:rgba(255,0,4,.1) !important;color:#FF0408 !important;font-size:14px;font-weight:500;padding:0 12px !important;
}
body.single-product form.cart .stock.available-on-backorder{background:rgba(215,180,106,.18) !important;color:#9a7a30 !important}

/* دکمه افزودن به سبد */
body.single-product form.cart .single_add_to_cart_button{
	order:3 !important;flex:0 0 100% !important;float:none !important;display:flex !important;align-items:center !important;justify-content:center !important;
	width:100% !important;min-height:50px !important;margin:0 !important;padding:6px !important;
	border:0 !important;border-radius:16px !important;background:#009879 !important;color:#fff !important;
	font-family:inherit !important;font-size:16px !important;font-weight:500 !important;line-height:1.6 !important;
	box-shadow:none !important;text-shadow:none !important;transition:.4s;cursor:pointer;opacity:1;
}
body.single-product form.cart .single_add_to_cart_button:hover{background:#D7B46A !important;color:#fff !important}
body.single-product form.cart .single_add_to_cart_button.disabled,
body.single-product form.cart .single_add_to_cart_button:disabled{opacity:.5 !important;cursor:not-allowed}
body.single-product form.cart .single_add_to_cart_button.loading{opacity:.7 !important}
body.single-product form.cart .single_add_to_cart_button.loading::after{display:none}

/* ===== اسنپ‌پی ===== */
body.single-product .snapppay-widget{width:100% !important;max-width:100% !important;box-sizing:border-box}
body.single-product form.cart .snapppay-widget{
	order:4 !important;flex:0 0 100% !important;width:100% !important;max-width:100% !important;
	display:flex !important;align-items:center;gap:8px;box-sizing:border-box;
	background:#fff !important;margin:0 !important;padding:16px !important;
	border-radius:16px !important;border:.5px solid #ebecf2 !important;
}
body.single-product form.cart .snapppay-widget-image{width:40px !important;height:auto;border-radius:15px !important;margin:0 !important;flex:0 0 40px}
body.single-product form.cart .snapppay-widget-content{color:#1a1c23;flex:1 1 auto;min-width:0}
body.single-product form.cart .snapppay-widget-title{font-size:14px !important;font-weight:700 !important;line-height:180% !important;margin-bottom:2px}
body.single-product form.cart .snapppay-widget-title *{color:#1a1c23}
body.single-product form.cart .snapppay-widget-description{font-size:12px !important;font-weight:300 !important;line-height:180% !important;letter-spacing:-.36px}
CSS;
}

add_action( 'wp_head', function () {
	if ( function_exists( 'is_product' ) && is_product() ) {
		echo '<style id="aromist-atc-css">' . aromist_atc_css() . '</style>'; // phpcs:ignore
	}
}, 100 );
