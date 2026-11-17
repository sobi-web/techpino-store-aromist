<?php
// اسنیپت #50: تشکر از پرداخت
defined( 'ABSPATH' ) || exit;

/**
 * Plugin Name: Aromist Thank-You Redirect
 * Description: پس از پرداخت موفق، خریدار را از صفحهٔ «سفارش دریافت شد» ووکامرس به صفحهٔ اختصاصی «تشکر از خرید» می‌برد.
 * Version: 0.1.0
 * Author: Techpino
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Text Domain: aromist-thankyou-redirect
 */

defined( 'ABSPATH' ) || exit;

/** Page ID of «تشکر از خرید» on aromistshop.com. Override with the `aromist_ty_page_id` filter. */
const AROMIST_TY_PAGE_ID = 3448;

/**
 * Runs after WooCommerce's own order-received work on template_redirect
 * (wc_clear_cart_after_payment is priority 20), so the cart is already emptied.
 */
add_action( 'template_redirect', 'aromist_ty_maybe_redirect', 30 );

function aromist_ty_maybe_redirect() {
	if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}

	$order_id = absint( get_query_var( 'order-received' ) );
	$key      = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
	if ( ! $order_id || '' === $key ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) ) {
		return;
	}

	// Only paid/accepted orders. Failed, cancelled and pending ones keep WooCommerce's page and its error message.
	$statuses = apply_filters( 'aromist_ty_statuses', array( 'processing', 'completed', 'on-hold' ), $order );
	if ( ! $order->has_status( $statuses ) ) {
		return;
	}

	$page_id = (int) apply_filters( 'aromist_ty_page_id', AROMIST_TY_PAGE_ID );
	if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
		return;
	}

	$url = apply_filters( 'aromist_ty_redirect_url', get_permalink( $page_id ), $order );
	if ( ! $url ) {
		return;
	}

	wp_safe_redirect( $url, 302, 'Aromist Thank-You' );
	exit;
}