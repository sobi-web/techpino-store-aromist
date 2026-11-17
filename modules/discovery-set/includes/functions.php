<?php
/**
 * Small global helpers.
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Why the plugin cannot run, or '' when everything it needs is active.
 *
 * @return string
 */
function ads_missing_requirements() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return __( 'ووکامرس فعال نیست.', 'aromist-discovery-set' );
	}
	if ( ! defined( 'AVS_VERSION' ) || ! class_exists( 'AVS_Product' ) ) {
		return __( 'افزونه‌ی «موجودی حجمی تکپینو» (Techpino Volume Stock) فعال نیست. ست‌ها از مخزن میلی‌لیتری آن برداشت می‌کنند.', 'aromist-discovery-set' );
	}
	if ( version_compare( AVS_VERSION, ADS_MIN_AVS_VERSION, '<' ) ) {
		/* translators: 1: required version, 2: installed version */
		return sprintf( __( 'نسخه‌ی %1$s یا بالاتر «موجودی حجمی تکپینو» لازم است (نسخه‌ی نصب‌شده: %2$s).', 'aromist-discovery-set' ), ADS_MIN_AVS_VERSION, AVS_VERSION );
	}
	return '';
}

/**
 * Format millilitres, e.g. "۵ میل".
 *
 * @param int|float $ml Amount.
 * @return string
 */
function ads_format_ml( $ml ) {
	if ( class_exists( 'AVS_Settings' ) ) {
		return AVS_Settings::format_ml( $ml );
	}
	return number_format_i18n( (float) $ml ) . ' ' . __( 'میل', 'aromist-discovery-set' );
}

/**
 * Number of perfumes in a set.
 *
 * @return int
 */
function ads_set_size() {
	/**
	 * Number of perfumes in every discovery set.
	 *
	 * @param int $size Size.
	 */
	return max( 2, (int) apply_filters( 'ads_set_size', 4 ) );
}

/**
 * Is this product a discovery set?
 *
 * @param mixed $product Product or id.
 * @return bool
 */
function ads_is_set( $product ) {
	if ( is_numeric( $product ) ) {
		$product = wc_get_product( $product );
	}
	return $product instanceof WC_Product && $product->is_type( 'discovery_set' );
}

/**
 * Join names with the Persian comma.
 *
 * @param string[] $names Names.
 * @return string
 */
function ads_join( array $names ) {
	return implode( '، ', array_filter( array_map( 'strval', $names ), 'strlen' ) );
}

/**
 * Names joined with the Persian comma, each one a link to its own page when it has one.
 *
 * @param array[] $items [ name, url ] items (ADS_Perfume_Info links).
 * @return string HTML.
 */
function ads_link_list( array $items ) {
	$out = array();
	foreach ( $items as $item ) {
		$name = isset( $item['name'] ) ? (string) $item['name'] : '';
		if ( '' === $name ) {
			continue;
		}
		$out[] = ! empty( $item['url'] )
			? '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $name ) . '</a>'
			: '<span>' . esc_html( $name ) . '</span>';
	}
	return implode( '، ', $out );
}

/**
 * Percent for display: "۱۰" or "۱۲٫۵" (no useless decimals).
 *
 * @param float $percent Percent.
 * @return string
 */
function ads_format_percent( $percent ) {
	$percent  = round( (float) $percent, 2 );
	$decimals = (float) (int) $percent === $percent ? 0 : ( (float) round( $percent, 1 ) === $percent ? 1 : 2 );
	return number_format_i18n( $percent, $decimals );
}

/**
 * Version string of an asset: plugin version + file time, so browsers and page caches pick up
 * every change of the file.
 *
 * @param string $relative Path inside the plugin, e.g. 'assets/css/sets.css'.
 * @return string
 */
function ads_asset_ver( $relative ) {
	$file = ADS_PATH . ltrim( $relative, '/' );
	return ADS_VERSION . ( file_exists( $file ) ? '.' . filemtime( $file ) : '' );
}
