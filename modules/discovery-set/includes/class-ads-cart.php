<?php
/**
 * Cart: sets as cart items, live prices, stock checks and the order line item.
 *
 * A set cart item carries `ads = [ 'v' => volume, 'c' => [ perfume ids ] ]`. Ready-made sets take
 * their perfumes from the product; customer-built sets (the hidden «ست سفارشی» product) take them
 * from the request / API. Different volumes or perfumes are different cart lines.
 *
 * Contents are shown through `woocommerce_get_item_data` and visible order item meta, which the
 * shop's custom cart / checkout snippets, the Techpino mini-cart and the Techpino user panel
 * already print.
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cart hooks.
 */
final class ADS_Cart {

	const KEY = 'ads';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 4 );
		add_filter( 'woocommerce_add_cart_item', array( $this, 'price_new_item' ), 20 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'from_session' ), 10, 2 );
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'remove_invalid_items' ), 20 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_prices' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'item_data' ), 10, 2 );
		add_filter( 'woocommerce_update_cart_validation', array( $this, 'validate_update' ), 20, 4 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'check_cart_items' ), 20 );
		add_filter( 'avs_cart_ml_by_parent', array( $this, 'avs_ml_by_parent' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'order_line_item' ), 10, 4 );
	}

	/*
	|--------------------------------------------------------------------------
	| Public API
	|--------------------------------------------------------------------------
	*/

	/**
	 * Add a set to the cart.
	 *
	 * @param int        $product_id Set product (ready-made, or the «ست سفارشی» product).
	 * @param int        $volume     Volume.
	 * @param int[]|null $components Perfumes (customer-built sets only; ready-made sets use their own).
	 * @param int        $quantity   Quantity.
	 * @param string     $name       Name the customer gave a customer-built set ('' = «دیسکاوری ست اختصاصی»).
	 * @return string|false Cart item key, false on error (a notice explains why).
	 */
	public static function add_set( $product_id, $volume, $components = null, $quantity = 1, $name = '' ) {
		if ( ! WC()->cart ) {
			return false;
		}
		return WC()->cart->add_to_cart(
			$product_id,
			max( 1, (int) $quantity ),
			0,
			array(),
			array(
				self::KEY => array(
					'v' => absint( $volume ),
					'c' => null === $components ? null : ADS_Set::clean_ids( $components ),
					'n' => self::clean_name( $name ),
				),
			)
		);
	}

	/**
	 * A set name as the customer may give it: plain text, at most 60 characters.
	 *
	 * @param mixed $name Name.
	 * @return string
	 */
	public static function clean_name( $name ) {
		$name = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( (string) $name ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 60 ) : substr( $name, 0, 60 );
	}

	/**
	 * Name of a customer-built set in the cart ('' = none given).
	 *
	 * @param array $item Cart item.
	 * @return string
	 */
	public static function item_name( $item ) {
		return isset( $item[ self::KEY ]['n'] ) && is_string( $item[ self::KEY ]['n'] ) ? $item[ self::KEY ]['n'] : '';
	}

	/**
	 * Is this cart item a set?
	 *
	 * @param array $item Cart item.
	 * @return bool
	 */
	public static function is_set_item( $item ) {
		return isset( $item[ self::KEY ]['v'], $item[ self::KEY ]['c'], $item['data'] ) && $item['data'] instanceof WC_Product_Discovery_Set;
	}

	/**
	 * Amount the whole cart needs from every limited product: pools (ml, from volume variations
	 * and sets) and boxes (units).
	 *
	 * @param string $exclude_key Cart item to leave out.
	 * @return array product id => amount
	 */
	public static function needs( $exclude_key = '' ) {
		$out = array();
		if ( ! WC()->cart ) {
			return $out;
		}
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( $key === $exclude_key || empty( $item['data'] ) ) {
				continue;
			}
			$qty = (int) $item['quantity'];
			if ( self::is_set_item( $item ) ) {
				foreach ( ADS_Set::draws( $item[ self::KEY ]['c'], $item[ self::KEY ]['v'] ) as $draw ) {
					$out[ $draw['p'] ] = ( isset( $out[ $draw['p'] ] ) ? $out[ $draw['p'] ] : 0 ) + $qty * (int) $draw['a'];
				}
			} elseif ( AVS_Product::is_volume_variation( $item['data'] ) ) {
				$parent         = $item['data']->get_parent_id();
				$out[ $parent ] = ( isset( $out[ $parent ] ) ? $out[ $parent ] : 0 ) + $qty * AVS_Product::unit_ml( $item['data'] );
			}
		}
		return $out;
	}

	/**
	 * Selection stored on a cart item.
	 *
	 * @param array $item Cart item.
	 * @return array [ int[] components, int volume ]
	 */
	public static function selection( $item ) {
		return array( ADS_Set::clean_ids( $item[ self::KEY ]['c'] ), absint( $item[ self::KEY ]['v'] ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Adding
	|--------------------------------------------------------------------------
	*/

	/**
	 * Attach (and validate) the set selection when a set is added. Throwing here makes
	 * WC_Cart::add_to_cart() show the message and refuse the item, for every way of adding
	 * (product form, AJAX, API).
	 *
	 * @param array $data         Cart item data.
	 * @param int   $product_id   Product id.
	 * @param int   $variation_id Variation id.
	 * @param int   $quantity     Quantity.
	 * @return array
	 * @throws Exception When the set cannot be added.
	 */
	public function add_cart_item_data( $data, $product_id, $variation_id = 0, $quantity = 1 ) {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product_Discovery_Set ) {
			return $data;
		}

		$given = isset( $data[ self::KEY ] ) && is_array( $data[ self::KEY ] ) ? $data[ self::KEY ] : array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- add-to-cart forms have no nonce in WooCommerce.
		$volume = isset( $given['v'] ) ? absint( $given['v'] ) : ( isset( $_REQUEST['ads_volume'] ) ? absint( ADS_Settings::to_number( wp_unslash( $_REQUEST['ads_volume'] ) ) ) : 0 );
		if ( ! $volume ) {
			$volume = $product->get_default_volume();
		}

		$name = '';
		if ( $product->is_custom() ) {
			$raw        = isset( $given['c'] ) ? $given['c'] : ( isset( $_REQUEST['ads_components'] ) ? wp_unslash( $_REQUEST['ads_components'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned to ints below.
			$components = ADS_Set::clean_ids( $raw );
			$name       = self::clean_name( isset( $given['n'] ) ? $given['n'] : ( isset( $_REQUEST['ads_name'] ) ? wp_unslash( $_REQUEST['ads_name'] ) : '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned by clean_name().
		} else {
			$components = $product->get_components();
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$error = ADS_Set::selection_error( $components, $volume, $product );
		if ( '' !== $error ) {
			throw new Exception( esc_html( $error ) );
		}

		$short = ADS_Availability::shortages( ADS_Set::draws( $components, $volume ), max( 1, (int) $quantity ), self::needs() );
		if ( $short ) {
			$id = array_key_first( $short );
			throw new Exception( esc_html( ADS_Availability::shortage_message( $id, $short[ $id ] ) ) );
		}

		$data[ self::KEY ] = array(
			'v' => $volume,
			'c' => $components,
		);
		if ( '' !== $name ) {
			$data[ self::KEY ]['n'] = $name; // Only named sets get the key, so unnamed ones keep merging in the cart.
		}
		return $data;
	}

	/**
	 * Keep the selection when the cart is restored from the session.
	 *
	 * @param array $item   Cart item.
	 * @param array $values Session values.
	 * @return array
	 */
	public function from_session( $item, $values ) {
		if ( isset( $values[ self::KEY ]['v'], $values[ self::KEY ]['c'] ) ) {
			$item[ self::KEY ] = array(
				'v' => absint( $values[ self::KEY ]['v'] ),
				'c' => ADS_Set::clean_ids( $values[ self::KEY ]['c'] ),
			);
			$name = self::clean_name( isset( $values[ self::KEY ]['n'] ) ? $values[ self::KEY ]['n'] : '' );
			if ( '' !== $name ) {
				$item[ self::KEY ]['n'] = $name;
			}
			self::apply_price( $item );
		}
		return $item;
	}

	/**
	 * Price a set as soon as it is added (before any totals run).
	 *
	 * @param array $item Cart item.
	 * @return array
	 */
	public function price_new_item( $item ) {
		self::apply_price( $item );
		return $item;
	}

	/**
	 * Put the live price — and the customer's name of a customer-built set — on the cart item's
	 * product object.
	 *
	 * WooCommerce rebuilds that object from the database on every request and only recalculates
	 * totals now and then, while the cart snippet and the mini-cart read the line price and name
	 * straight from it. So both are set when an item is added, when it is restored from the
	 * session and before every totals run. The order line takes its name from it at checkout.
	 *
	 * @param array $item Cart item.
	 */
	public static function apply_price( $item ) {
		if ( ! self::is_set_item( $item ) ) {
			return;
		}
		$name = self::item_name( $item );
		if ( '' !== $name && $item['data']->is_custom() ) {
			$item['data']->set_name( $name );
		}
		list( $components, $volume ) = self::selection( $item );
		$breakdown                   = ADS_Pricing::breakdown( $components, $volume, $item['data'] );
		if ( null === $breakdown ) {
			return;
		}
		$item['data']->set_regular_price( $breakdown['regular'] );
		$item['data']->set_sale_price( $breakdown['price'] < $breakdown['regular'] ? $breakdown['price'] : '' );
		$item['data']->set_price( $breakdown['price'] );
	}

	/**
	 * Remove set items that can no longer be sold (a perfume was removed / unpublished, the
	 * volume is no longer offered, the set was unpublished).
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public function remove_invalid_items( $cart ) {
		$remove = array();
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product_Discovery_Set ) {
				continue;
			}
			if ( ! isset( $item[ self::KEY ]['v'], $item[ self::KEY ]['c'] ) ) {
				$remove[ $key ] = __( 'اطلاعات ست کامل نیست.', 'aromist-discovery-set' );
				continue;
			}
			list( $components, $volume ) = self::selection( $item );
			$error                       = $item['data']->is_purchasable() ? ADS_Set::selection_error( $components, $volume, $item['data'] ) : __( 'این ست دیگر فروخته نمی‌شود.', 'aromist-discovery-set' );
			if ( '' !== $error ) {
				$remove[ $key ] = $error;
			}
		}
		foreach ( $remove as $key => $reason ) {
			$name = $cart->cart_contents[ $key ]['data']->get_name();
			$cart->remove_cart_item( $key );
			/* translators: 1: set name, 2: reason */
			wc_add_notice( sprintf( __( '«%1$s» از سبد خرید حذف شد: %2$s', 'aromist-discovery-set' ), $name, $reason ), 'notice' );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Prices and display
	|--------------------------------------------------------------------------
	*/

	/**
	 * Live price of every set in the cart.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public function set_prices( $cart ) {
		foreach ( $cart->get_cart() as $item ) {
			self::apply_price( $item );
		}
	}

	/**
	 * «حجم هر عطر» and «عطرها» under the item name (cart, checkout, mini-cart).
	 *
	 * @param array $data Item data.
	 * @param array $item Cart item.
	 * @return array
	 */
	public function item_data( $data, $item ) {
		if ( ! self::is_set_item( $item ) ) {
			return $data;
		}
		list( $components, $volume ) = self::selection( $item );

		$data[] = array(
			'key'   => __( 'حجم هر عطر', 'aromist-discovery-set' ),
			'value' => ads_format_ml( $volume ),
		);
		$data[] = array(
			'key'   => __( 'عطرها', 'aromist-discovery-set' ),
			'value' => ads_join( array_map( array( 'ADS_Set', 'perfume_name' ), $components ) ),
		);
		return $data;
	}

	/*
	|--------------------------------------------------------------------------
	| Stock checks
	|--------------------------------------------------------------------------
	*/

	/**
	 * Quantity change on the cart page / mini-cart.
	 *
	 * @param bool   $passed   Passed so far.
	 * @param string $key      Cart item key.
	 * @param array  $values   Cart item.
	 * @param int    $quantity New quantity.
	 * @return bool
	 */
	public function validate_update( $passed, $key, $values, $quantity ) {
		if ( ! $passed || ! self::is_set_item( $values ) ) {
			return $passed;
		}
		list( $components, $volume ) = self::selection( $values );
		$short                       = ADS_Availability::shortages( ADS_Set::draws( $components, $volume ), (int) $quantity, self::needs( $key ) );
		if ( $short ) {
			$id = array_key_first( $short );
			wc_add_notice( ADS_Availability::shortage_message( $id, $short[ $id ] ), 'error' );
			return false;
		}
		return $passed;
	}

	/**
	 * Whole-cart check (cart page + checkout). Pools are checked by Techpino Volume Stock through
	 * `avs_cart_ml_by_parent` (one message per perfume, sets and normal sizes together); boxes here.
	 */
	public function check_cart_items() {
		if ( ! WC()->cart ) {
			return;
		}
		$exclude = ADS_Availability::session_order_id();
		foreach ( self::needs() as $id => $amount ) {
			if ( ! ADS_Boxes::is_box( $id ) ) {
				continue;
			}
			$have = ADS_Availability::available( $id, $exclude );
			if ( null === $have || $amount <= $have ) {
				continue;
			}
			$message = ADS_Availability::shortage_message(
				$id,
				array(
					'need' => $amount,
					'have' => max( 0, $have ),
				)
			) . ' ' . __( 'لطفاً تعداد ست‌ها را کم کنید.', 'aromist-discovery-set' );
			if ( ! wc_has_notice( $message, 'error' ) ) {
				wc_add_notice( $message, 'error' );
			}
		}
	}

	/**
	 * Tell Techpino Volume Stock how many ml the sets in the cart take from each pool.
	 *
	 * @param array  $out         parent id => ml.
	 * @param string $exclude_key Cart item left out.
	 * @return array
	 */
	public function avs_ml_by_parent( $out, $exclude_key = '' ) {
		if ( ! WC()->cart ) {
			return $out;
		}
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( $key === $exclude_key || ! self::is_set_item( $item ) ) {
				continue;
			}
			list( $components, $volume ) = self::selection( $item );
			foreach ( $components as $id ) {
				$out[ $id ] = ( isset( $out[ $id ] ) ? $out[ $id ] : 0 ) + (int) $item['quantity'] * ADS_Set::unit_ml( $id, $volume );
			}
		}
		return $out;
	}

	/*
	|--------------------------------------------------------------------------
	| Order line item
	|--------------------------------------------------------------------------
	*/

	/**
	 * Store what was bought: visible «حجم هر عطر» / «عطرها» and the hidden snapshot that drives
	 * stock (`_ads_set`). The item never gets `_avs_ml` (that would make Volume Stock treat it as
	 * a size of a perfume).
	 *
	 * @param WC_Order_Item_Product $item   Item.
	 * @param string                $key    Cart key.
	 * @param array                 $values Cart item.
	 * @param WC_Order              $order  Order.
	 */
	public function order_line_item( $item, $key, $values, $order ) {
		if ( ! self::is_set_item( $values ) ) {
			return;
		}
		list( $components, $volume ) = self::selection( $values );
		$name                        = self::item_name( $values );
		if ( '' !== $name && $values['data']->is_custom() ) {
			$item->set_name( $name );
		}
		self::stamp_item( $item, $components, $volume, $values['data'], $name );
	}

	/**
	 * Write the set meta on an order item (checkout, or a set added from the order screen).
	 *
	 * @param WC_Order_Item_Product    $item       Item.
	 * @param int[]                    $components Perfume ids.
	 * @param int                      $volume     Volume.
	 * @param WC_Product_Discovery_Set $product    Set.
	 * @param string                   $name       Customer's name of a customer-built set ('' = none).
	 */
	public static function stamp_item( $item, array $components, $volume, $product, $name = '' ) {
		$names = array();
		$lines = array();
		foreach ( $components as $id ) {
			$names[] = ADS_Set::perfume_name( $id );
			$lines[] = array(
				'id'   => (int) $id,
				'name' => ADS_Set::perfume_name( $id ),
				'ml'   => ADS_Set::unit_ml( $id, $volume ),
			);
		}

		$item->update_meta_data( __( 'حجم هر عطر', 'aromist-discovery-set' ), ads_format_ml( $volume ) );
		$item->update_meta_data( __( 'عطرها', 'aromist-discovery-set' ), ads_join( $names ) );
		$item->update_meta_data(
			ADS_Stock::META_SET,
			array(
				'v'      => (int) $volume,
				'source' => $product instanceof WC_Product_Discovery_Set && $product->is_custom() ? 'custom' : 'preset',
				'name'   => (string) $name,
				'perf'   => $lines,
				'box'    => ADS_Boxes::for_volume( $volume ),
				'draws'  => ADS_Set::draws( $components, $volume ),
			)
		);
	}
}
