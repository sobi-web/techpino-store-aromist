<?php
/**
 * Cart / checkout validation and storefront display for volume products.
 *
 * WooCommerce's own cart checks compare *units* with the stock owner's quantity; for a
 * volume pool that quantity is ml, so those checks are too permissive. Here every add-to-cart,
 * cart update and checkout is validated in ml across all sizes of the same product, minus the
 * ml held by other customers' pending checkouts.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cart hooks.
 */
final class AVS_Cart {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		// Classic cart / checkout.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 20, 5 );
		add_filter( 'woocommerce_update_cart_validation', array( $this, 'validate_update_cart' ), 20, 4 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'check_cart_items' ), 20 );
		add_filter( 'woocommerce_cart_item_required_stock_is_not_enough', array( $this, 'skip_wc_unit_check' ), 20, 2 );

		// Storefront.
		add_filter( 'woocommerce_quantity_input_max', array( $this, 'max_quantity' ), 20, 2 );
		add_filter( 'woocommerce_get_availability_text', array( $this, 'availability_text' ), 20, 2 );

		// Store API (block cart / checkout, mini-cart block).
		add_action( 'woocommerce_store_api_validate_add_to_cart', array( $this, 'store_api_validate_add' ), 10, 2 );
		add_action( 'woocommerce_store_api_validate_cart_item', array( $this, 'store_api_validate_item' ), 10, 2 );
		add_filter( 'woocommerce_store_api_product_quantity_maximum', array( $this, 'store_api_max' ), 20, 3 );
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Ml needed by the cart, per parent product.
	 *
	 * @param string $exclude_key Cart item key to leave out.
	 * @return array parent_id => ml
	 */
	private function cart_ml_by_parent( $exclude_key = '' ) {
		$out = array();
		if ( ! WC()->cart ) {
			return $out;
		}
		foreach ( WC()->cart->get_cart() as $key => $values ) {
			if ( $key === $exclude_key || empty( $values['data'] ) || ! AVS_Product::is_volume_variation( $values['data'] ) ) {
				continue;
			}
			$parent_id         = $values['data']->get_parent_id();
			$out[ $parent_id ] = ( isset( $out[ $parent_id ] ) ? $out[ $parent_id ] : 0 ) + (int) $values['quantity'] * AVS_Product::unit_ml( $values['data'] );
		}

		/**
		 * Ml needed by the cart per parent product. Other plugins add the ml their own cart items
		 * take from a pool (e.g. discovery sets), so every check sees the whole cart.
		 *
		 * @since 1.1.0
		 * @param array  $out         parent_id => ml.
		 * @param string $exclude_key Cart item key left out.
		 */
		return (array) apply_filters( 'avs_cart_ml_by_parent', $out, $exclude_key );
	}

	/**
	 * The current customer's own pending order (its reservation must not count against them).
	 *
	 * @return int
	 */
	private function session_order_id() {
		if ( ! WC()->session ) {
			return 0;
		}
		$id = absint( WC()->session->get( 'order_awaiting_payment', 0 ) );
		return $id ? $id : absint( WC()->session->get( 'store_api_draft_order', 0 ) );
	}

	/**
	 * Ml available to this customer: pool minus ml held by other pending checkouts.
	 *
	 * @param int $parent_id Parent id.
	 * @return int
	 */
	private function available_ml( $parent_id ) {
		return AVS_Product::pool( $parent_id ) - AVS_Product::held_ml( $parent_id, $this->session_order_id() );
	}

	/**
	 * Are backorders allowed on the pool? (Then no limit applies.)
	 *
	 * @param WC_Product_Variation $variation Variation.
	 * @return bool
	 */
	private function backorders_allowed( $variation ) {
		return $variation->backorders_allowed();
	}

	/**
	 * Error text when a quantity does not fit.
	 *
	 * @param WC_Product_Variation $variation Variation.
	 * @param int                  $room_ml   Ml still free for this line.
	 * @return string
	 */
	private function not_enough_message( $variation, $room_ml ) {
		$can = AVS_Product::units_for_ml( $variation, max( 0, $room_ml ) );
		$ml  = AVS_Product::variation_ml( $variation->get_id() );

		if ( $can <= 0 ) {
			return sprintf(
				/* translators: 1: product name, 2: size */
				__( 'موجودی «%1$s» برای حجم %2$s کافی نیست.', 'aromist-volume-stock' ),
				$variation->get_title(),
				AVS_Settings::format_ml( $ml )
			);
		}

		return sprintf(
			/* translators: 1: product name, 2: size, 3: max units */
			__( 'از «%1$s» در حجم %2$s حداکثر %3$s عدد (با احتساب سبد خرید شما) قابل سفارش است.', 'aromist-volume-stock' ),
			$variation->get_title(),
			AVS_Settings::format_ml( $ml ),
			number_format_i18n( $can )
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Classic cart
	|--------------------------------------------------------------------------
	*/

	/**
	 * Add to cart (form + AJAX).
	 *
	 * @param bool  $passed       Passed so far.
	 * @param int   $product_id   Product id.
	 * @param int   $quantity     Quantity.
	 * @param int   $variation_id Variation id.
	 * @param array $variations   Attributes.
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
		if ( ! $passed || ! $variation_id ) {
			return $passed;
		}
		$variation = wc_get_product( $variation_id );
		if ( ! AVS_Product::is_volume_variation( $variation ) || $this->backorders_allowed( $variation ) ) {
			return $passed;
		}

		$parent_id = $variation->get_parent_id();
		$in_cart   = $this->cart_ml_by_parent();
		$in_cart   = isset( $in_cart[ $parent_id ] ) ? $in_cart[ $parent_id ] : 0;
		$room      = $this->available_ml( $parent_id ) - $in_cart;

		if ( (int) wc_stock_amount( $quantity ) * AVS_Product::unit_ml( $variation ) > $room ) {
			wc_add_notice( $this->not_enough_message( $variation, $room ), 'error' );
			return false;
		}
		return $passed;
	}

	/**
	 * Quantity change on the cart page.
	 *
	 * @param bool   $passed        Passed so far.
	 * @param string $cart_item_key Key.
	 * @param array  $values        Cart item.
	 * @param int    $quantity      New quantity.
	 * @return bool
	 */
	public function validate_update_cart( $passed, $cart_item_key, $values, $quantity ) {
		if ( ! $passed || empty( $values['data'] ) ) {
			return $passed;
		}
		$variation = $values['data'];
		if ( ! AVS_Product::is_volume_variation( $variation ) || $this->backorders_allowed( $variation ) ) {
			return $passed;
		}

		$parent_id = $variation->get_parent_id();
		$others    = $this->cart_ml_by_parent( $cart_item_key );
		$others    = isset( $others[ $parent_id ] ) ? $others[ $parent_id ] : 0;
		$room      = $this->available_ml( $parent_id ) - $others;

		if ( (int) wc_stock_amount( $quantity ) * AVS_Product::unit_ml( $variation ) > $room ) {
			wc_add_notice( $this->not_enough_message( $variation, $room ), 'error' );
			return false;
		}
		return $passed;
	}

	/**
	 * Whole-cart check (cart page + checkout). Adds an error notice per product that does not fit.
	 */
	public function check_cart_items() {
		if ( ! WC()->cart ) {
			return;
		}

		$needed = $this->cart_ml_by_parent();
		foreach ( $needed as $parent_id => $ml ) {
			$parent = wc_get_product( $parent_id );
			// No pool counted («unlimited» volume stock) or backorders allowed: no limit.
			if ( ! $parent || ! $parent->managing_stock() || 'no' !== $parent->get_backorders() ) {
				continue;
			}
			$available = $this->available_ml( $parent_id );
			if ( $ml <= $available ) {
				continue;
			}

			$message = sprintf(
				/* translators: 1: product, 2: ml needed, 3: ml available */
				__( 'موجودی «%1$s» کافی نیست: سبد شما %2$s لازم دارد ولی فقط %3$s موجود است. لطفاً تعداد را کم کنید.', 'aromist-volume-stock' ),
				$parent->get_name(),
				AVS_Settings::format_ml( $ml ),
				AVS_Settings::format_ml( max( 0, $available ) )
			);
			if ( ! wc_has_notice( $message, 'error' ) ) {
				wc_add_notice( $message, 'error' );
			}
		}
	}

	/**
	 * WooCommerce's unit-based "enough stock" check does not apply to volume items (checked above in ml).
	 *
	 * @param bool       $not_enough Not enough.
	 * @param WC_Product $product    Product.
	 * @return bool
	 */
	public function skip_wc_unit_check( $not_enough, $product ) {
		return AVS_Product::is_volume_variation( $product ) ? false : $not_enough;
	}

	/*
	|--------------------------------------------------------------------------
	| Storefront
	|--------------------------------------------------------------------------
	*/

	/**
	 * Max quantity of a volume variation = whole units that fit into the pool.
	 *
	 * @param int|float  $max     Max.
	 * @param WC_Product $product Product.
	 * @return int|float
	 */
	public function max_quantity( $max, $product ) {
		if ( ! AVS_Product::is_volume_variation( $product ) || $product->backorders_allowed() || $product->is_sold_individually() ) {
			return $max;
		}
		return AVS_Product::units_for_ml( $product, AVS_Product::pool( $product->get_parent_id() ) );
	}

	/**
	 * "N in stock" text in units of this size instead of the pool's ml.
	 *
	 * @param string     $text    Text.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function availability_text( $text, $product ) {
		if ( ! AVS_Product::is_volume_variation( $product ) || ! $product->is_in_stock() || $product->is_on_backorder() ) {
			return $text;
		}

		if ( 'yes' !== AVS_Settings::get( 'show_units_in_stock' ) || 'no_amount' === get_option( 'woocommerce_stock_format' ) ) {
			return __( 'In stock', 'woocommerce' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
		}

		$units = AVS_Product::units_for_ml( $product, AVS_Product::pool( $product->get_parent_id() ) );
		/* translators: %s: stock amount */
		return sprintf( __( '%s in stock', 'woocommerce' ), number_format_i18n( $units ) ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
	}

	/*
	|--------------------------------------------------------------------------
	| Store API
	|--------------------------------------------------------------------------
	*/

	/**
	 * Throw a Store API error.
	 *
	 * @param string $message Message.
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException Always.
	 */
	private function store_api_error( $message ) {
		throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'avs_not_enough_volume', wp_strip_all_tags( $message ), 400 );
	}

	/**
	 * Store API add to cart.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $request Request (quantity, id, ...).
	 */
	public function store_api_validate_add( $product, $request ) {
		if ( ! AVS_Product::is_volume_variation( $product ) || $product->backorders_allowed() ) {
			return;
		}
		$parent_id = $product->get_parent_id();
		$in_cart   = $this->cart_ml_by_parent();
		$room      = $this->available_ml( $parent_id ) - ( isset( $in_cart[ $parent_id ] ) ? $in_cart[ $parent_id ] : 0 );
		$quantity  = isset( $request['quantity'] ) ? (int) wc_stock_amount( $request['quantity'] ) : 1;

		if ( $quantity * AVS_Product::unit_ml( $product ) > $room ) {
			$this->store_api_error( $this->not_enough_message( $product, $room ) );
		}
	}

	/**
	 * Store API cart validation (runs for every item).
	 *
	 * @param WC_Product $product   Product.
	 * @param array      $cart_item Cart item.
	 */
	public function store_api_validate_item( $product, $cart_item ) {
		if ( ! AVS_Product::is_volume_variation( $product ) || $product->backorders_allowed() ) {
			return;
		}
		$parent_id = $product->get_parent_id();
		$needed    = $this->cart_ml_by_parent();
		$needed    = isset( $needed[ $parent_id ] ) ? $needed[ $parent_id ] : 0;
		$available = $this->available_ml( $parent_id );

		if ( $needed > $available ) {
			$this->store_api_error(
				sprintf(
					/* translators: 1: product, 2: ml needed, 3: ml available */
					__( 'موجودی «%1$s» کافی نیست: سبد شما %2$s لازم دارد ولی فقط %3$s موجود است.', 'aromist-volume-stock' ),
					$product->get_title(),
					AVS_Settings::format_ml( $needed ),
					AVS_Settings::format_ml( max( 0, $available ) )
				)
			);
		}
	}

	/**
	 * Store API max quantity for a line.
	 *
	 * @param int|float  $value     Max.
	 * @param WC_Product $product   Product.
	 * @param array|null $cart_item Cart item.
	 * @return int|float
	 */
	public function store_api_max( $value, $product, $cart_item ) {
		if ( ! AVS_Product::is_volume_variation( $product ) || $product->backorders_allowed() ) {
			return $value;
		}
		$parent_id = $product->get_parent_id();
		$exclude   = is_array( $cart_item ) && isset( $cart_item['key'] ) ? $cart_item['key'] : '';
		$others    = $this->cart_ml_by_parent( $exclude );
		$room      = $this->available_ml( $parent_id ) - ( isset( $others[ $parent_id ] ) ? $others[ $parent_id ] : 0 );

		return min( (float) $value, AVS_Product::units_for_ml( $product, $room ) );
	}
}
