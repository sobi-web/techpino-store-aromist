<?php
// اسنیپت #27: فیلد های تسویه حساب
defined( 'ABSPATH' ) || exit;


/**
 * Aromist – مدیریت فیلدهای تسویه حساب ووکامرس
 *
 * - کد پستی: اختیاری
 * - ایمیل: اختیاری
 * - نام شرکت: حذف
 * - کشور: از فرم حذف می‌شود (به‌صورت پنهان روی «ایران» ثابت می‌ماند تا
 *   محاسبه حمل‌ونقل، استان‌ها و اعتبارسنجی ووکامرس درست کار کند)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AROMIST_FIXED_COUNTRY = 'IR';

/* ---------- ۱) فیلدهای پیش‌فرض آدرس (تسویه حساب + حساب کاربری) ---------- */
add_filter( 'woocommerce_default_address_fields', function ( $fields ) {
	// حذف شرکت
	unset( $fields['company'] );

	// کد پستی اختیاری
	if ( isset( $fields['postcode'] ) ) {
		$fields['postcode']['required'] = false;
	}

	return $fields;
}, 9999 );

/* ---------- ۲) تنظیمات کشوری (ووکامرس برای هر کشور ممکن است دوباره اجباری کند) ---------- */
add_filter( 'woocommerce_get_country_locale', function ( $locale ) {
	foreach ( $locale as $country => $fields ) {
		$locale[ $country ]['postcode']['required'] = false;
		$locale[ $country ]['company']['hidden']    = true;
		$locale[ $country ]['company']['required']  = false;
	}
	return $locale;
}, 9999 );

add_filter( 'woocommerce_get_country_locale_default', function ( $locale ) {
	$locale['postcode']['required'] = false;
	return $locale;
}, 9999 );

/* ---------- ۳) فیلدهای فرم تسویه حساب ---------- */
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
	// ایمیل اختیاری (اگر وارد شود، فرمت آن همچنان بررسی می‌شود)
	if ( isset( $fields['billing']['billing_email'] ) ) {
		$fields['billing']['billing_email']['required'] = false;
	}

	foreach ( array( 'billing', 'shipping' ) as $group ) {
		// حذف شرکت
		unset( $fields[ $group ][ $group . '_company' ] );

		// کد پستی اختیاری
		if ( isset( $fields[ $group ][ $group . '_postcode' ] ) ) {
			$fields[ $group ][ $group . '_postcode' ]['required'] = false;
		}

		// کشور: پنهان و ثابت روی ایران
		if ( isset( $fields[ $group ][ $group . '_country' ] ) ) {
			$fields[ $group ][ $group . '_country' ]['type']     = 'hidden';
			$fields[ $group ][ $group . '_country' ]['label']    = '';
			$fields[ $group ][ $group . '_country' ]['required'] = false;
			$fields[ $group ][ $group . '_country' ]['default']  = AROMIST_FIXED_COUNTRY;
			$fields[ $group ][ $group . '_country' ]['class']    = array( 'aromist-hidden-field' );
		}
	}
	return $fields;
}, 9999 );

// مقدار کشور همیشه ایران باشد (حتی اگر مشتری قبلاً کشور دیگری ذخیره کرده باشد)
add_filter( 'default_checkout_billing_country', function () {
	return AROMIST_FIXED_COUNTRY;
}, 9999 );
add_filter( 'default_checkout_shipping_country', function () {
	return AROMIST_FIXED_COUNTRY;
}, 9999 );
add_filter( 'woocommerce_checkout_get_value', function ( $value, $input ) {
	if ( in_array( $input, array( 'billing_country', 'shipping_country' ), true ) ) {
		return AROMIST_FIXED_COUNTRY;
	}
	return $value;
}, 9999, 2 );

// هنگام ثبت سفارش هم کشور را ایران ثبت کن
add_filter( 'woocommerce_checkout_posted_data', function ( $data ) {
	$data['billing_country'] = AROMIST_FIXED_COUNTRY;
	if ( array_key_exists( 'shipping_country', $data ) || ! empty( $data['ship_to_different_address'] ) ) {
		$data['shipping_country'] = AROMIST_FIXED_COUNTRY;
	}
	return $data;
}, 9999 );

/* ---------- ۴) پنهان کردن ردیف کشور در هر قالبی ---------- */
add_action( 'wp_head', function () {
	if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_account_page() ) ) {
		echo '<style>.aromist-hidden-field,#billing_country_field,#shipping_country_field,#billing_company_field,#shipping_company_field{display:none !important}</style>';
	}
}, 99 );
