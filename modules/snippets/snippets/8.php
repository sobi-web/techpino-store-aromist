<?php
// اسنیپت #8: قیمت اقساط اسنپ پی
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SB_SNAPPPAY_INSTALLMENTS' ) ) {
	define( 'SB_SNAPPPAY_INSTALLMENTS', 4 );
}

/**
 * ۱) ویجت واریانت‌آگاه: تمام قیمت‌های اقساط در data-attribute ذخیره می‌شود.
 */
function sb_snapppay_variation_widget() {
	global $product;

	if ( ! ( $product instanceof WC_Product ) || ! $product->is_type( 'variable' ) ) {
		return;
	}

	$map = array();
	foreach ( $product->get_children() as $variation_id ) {
		$variation = wc_get_product( $variation_id );

		if ( ! $variation || ! $variation->is_purchasable() ) {
			continue;
		}

		$price = (float) wc_get_price_to_display( $variation );

		if ( $price <= 0 ) {
			continue;
		}

		$map[ $variation_id ] = wc_price( $price / SB_SNAPPPAY_INSTALLMENTS );
	}

	$base    = (float) wc_get_price_to_display( $product );
	$initial = $base > 0 ? wc_price( $base / SB_SNAPPPAY_INSTALLMENTS ) : '';

	if ( empty( $map ) && '' === $initial ) {
		return;
	}
	

	echo '<div class="sb-snapppay-widget snapppay-widget"'
		. ' data-snapppay-map="' . esc_attr( wp_json_encode( $map ) ) . '"'
		. ' data-snapppay-default="' . esc_attr( $initial ) . '"'
		. ( '' === $initial ? ' style="display:none;"' : '' ) . '>';
   		echo " <img src='https://aromistshop.com/wp-content/plugins/snapppay-woocommerce-gateway/assets/images/logo-bg.png'  class='snapppay-widget-image' style='    width: 40px;
    border-radius: 15px;' />";
	echo '<div class="snapppay-widget-content">';

	echo '<div class="snapppay-widget-title" style="font-weight:700; font-size:14px;">هر قسط با اسنپ‌پی: <span class="snapppay-widget-price">' . wp_kses_post( $initial ) . '</span></div>';
	echo '<div class="snapppay-widget-description"   style="font-weight:500; font-size:12px;" >۴ قسط ماهانه، بدون سود و بدون ضامن.</div>';
	echo '</div></div>';
}

/**
 * ۲) حذف ویجت ثابت افزونه و ثبت نسخهٔ جدید.
 *    نام تابع و اولویت را با افزونهٔ نصب‌شده تطبیق بده.
 */
add_action( 'wp_loaded', function () {
	$candidates = array(
		'snapppay_variation_product_page_widget_price',
		'snapppay_product_page_widget_price',
	);

	foreach ( $candidates as $callback ) {
		if ( function_exists( $callback ) ) {
			for ( $priority = 1; $priority <= 40; $priority++ ) {
				remove_action( 'woocommerce_single_variation', $callback, $priority );
			}
		}
	}

	add_action( 'woocommerce_single_variation', 'sb_snapppay_variation_widget', 31 );
} );

/**
 * ۳) به‌روزرسانی آنی قیمت هنگام تغییر واریانت.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	wp_register_script( 'sb-snapppay-variation', '', array( 'jquery' ), '1.0.1', true );
	wp_enqueue_script( 'sb-snapppay-variation' );

	$js = 'jQuery(function ($) {
		function widget($form) {
			var $w = $form.find(".sb-snapppay-widget").first();
			return $w.length ? $w : $(".sb-snapppay-widget").first();
		}
		function prices($w) {
			try { return JSON.parse($w.attr("data-snapppay-map") || "{}"); }
			catch (e) { return {}; }
		}
		$(document.body)
			.on("found_variation", "form.variations_form", function (e, variation) {
				var $w = widget($(this));
				if (!$w.length) { return; }
				var html = prices($w)[variation.variation_id];
				if (html && variation.is_in_stock && variation.is_purchasable) {
					$w.find(".snapppay-widget-price").html(html);
					$w.css("display", "");
				} else {
					$w.css("display", "none");
				}
			})
			.on("reset_data", "form.variations_form", function () {
				var $w = widget($(this));
				if (!$w.length) { return; }
				var def = $w.attr("data-snapppay-default");
				if (def) {
					$w.find(".snapppay-widget-price").html(def);
					$w.css("display", "");
				} else {
					$w.css("display", "none");
				}
			})
			.on("hide_variation", "form.variations_form", function () {
				widget($(this)).css("display", "none");
			});
	});';

	wp_add_inline_script( 'sb-snapppay-variation', $js );
} );
