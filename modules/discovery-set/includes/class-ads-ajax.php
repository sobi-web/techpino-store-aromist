<?php
/**
 * AJAX add to cart for sets (single set page and builder): ?wc-ajax=ads_add_to_cart
 *
 * Success returns WooCommerce's refreshed cart fragments, so the page can fire `added_to_cart`
 * and the Techpino mini-cart (and WooCommerce's own fragments) update and open as for any product.
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * AJAX.
 */
final class ADS_Ajax {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'wc_ajax_ads_add_to_cart', array( $this, 'add_to_cart' ) );
	}

	/**
	 * Add a set: product_id (+ components[] and an optional name for the «ست سفارشی» product), quantity.
	 */
	public function add_to_cart() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- like WooCommerce's own AJAX add to cart.
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$quantity   = isset( $_POST['quantity'] ) ? max( 1, (int) wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) ) : 1;
		$components = isset( $_POST['components'] ) ? ADS_Set::clean_ids( wp_unslash( $_POST['components'] ) ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$name       = isset( $_POST['name'] ) ? ADS_Cart::clean_name( wp_unslash( $_POST['name'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned by clean_name().
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$product = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product instanceof WC_Product_Discovery_Set ) {
			wp_send_json(
				array(
					'success' => false,
					'message' => __( 'این محصول دیسکاوری ست نیست.', 'aromist-discovery-set' ),
				)
			);
		}

		wc_clear_notices();
		$key = ADS_Cart::add_set( $product_id, ADS_Settings::default_volume(), $product->is_custom() ? $components : null, $quantity, $product->is_custom() ? $name : '' );

		if ( ! $key ) {
			$messages = array_map(
				static function ( $notice ) {
					return wp_strip_all_tags( is_array( $notice ) ? $notice['notice'] : (string) $notice );
				},
				wc_get_notices( 'error' )
			);
			wc_clear_notices();
			wp_send_json(
				array(
					'success' => false,
					'message' => $messages ? implode( ' ', $messages ) : __( 'افزودن به سبد انجام نشد.', 'aromist-discovery-set' ),
				)
			);
		}

		wc_clear_notices();
		do_action( 'woocommerce_ajax_added_to_cart', $product_id ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment

		WC()->cart->calculate_totals();
		ob_start();
		woocommerce_mini_cart();
		$mini_cart = ob_get_clean();

		wp_send_json(
			array(
				'success'   => true,
				'fragments' => apply_filters( // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
					'woocommerce_add_to_cart_fragments',
					array(
						'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
					)
				),
				'cart_hash' => WC()->cart->get_cart_hash(),
				'count'     => WC()->cart->get_cart_contents_count(),
			)
		);
	}
}
