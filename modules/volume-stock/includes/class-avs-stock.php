<?php
/**
 * WooCommerce stock integration.
 *
 * How it works
 * ------------
 * The pool (ml) is the parent product's WooCommerce stock and the variations inherit it.
 * WooCommerce reduces / reserves stock per "stock owner" (the parent) using
 * `woocommerce_order_item_quantity`. For volume items we convert units → ml there, so:
 *   - reserving stock during checkout locks the pool rows in ml (no overselling across sizes),
 *   - reducing stock subtracts `qty × ml` atomically,
 *   - `_reduced_stock` of the item is stored in ml, so cancelling / failing an order restores
 *     exactly what was taken.
 * The two WooCommerce flows that do NOT pass through that filter (refund restock and editing
 * items of an existing order in the admin) are corrected / replaced here.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stock hooks.
 */
final class AVS_Stock {

	/**
	 * Mute counter for "external change" logging while the plugin itself saves products.
	 *
	 * @var int
	 */
	private static $mute = 0;

	/**
	 * Order note lines collected during a reduce / restore run. order_id => string[].
	 *
	 * @var array
	 */
	private $note_lines = array();

	/**
	 * Pending refund corrections. order_id => variation_id => list of entries.
	 *
	 * @var array
	 */
	private $refund_queue = array();

	/**
	 * Pool before a product save that changed the stock outside of this plugin. product_id => ml.
	 *
	 * @var array
	 */
	private $pool_before_save = array();

	/**
	 * Parents for which an "empty pool" notification was already sent in this request.
	 *
	 * @var array
	 */
	private $empty_notified = array();

	/**
	 * Register hooks.
	 */
	public function __construct() {
		// Units → ml for WooCommerce reduce + reservation.
		add_filter( 'woocommerce_order_item_quantity', array( $this, 'order_item_quantity' ), 99, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'stamp_line_item' ), 10, 4 );

		// Ledger + notes for reduce / restore.
		add_action( 'woocommerce_reduce_order_item_stock', array( $this, 'after_reduce_item' ), 10, 3 );
		add_action( 'woocommerce_reduce_order_stock', array( $this, 'flush_order_note' ), 99 );
		add_action( 'woocommerce_restore_order_item_stock', array( $this, 'after_restore_item' ), 10, 4 );
		add_action( 'woocommerce_restore_order_stock', array( $this, 'flush_order_note' ), 99 );

		// Refund restock (WooCommerce restocks units here; we correct it to ml).
		add_filter( 'woocommerce_can_restock_refunded_items', array( $this, 'before_refund_restock' ), 9999, 3 );
		add_filter( 'woocommerce_refund_restock_note', array( $this, 'refund_restock_note' ), 10, 5 );
		add_action( 'woocommerce_restock_refunded_item', array( $this, 'after_refund_restock_item' ), 10, 5 );

		// Editing items of an existing order in the admin / REST API.
		add_filter( 'woocommerce_prevent_adjust_line_item_product_stock', array( $this, 'adjust_line_item' ), 9999, 3 );

		// Stock status of volume variations and parents.
		add_filter( 'woocommerce_product_variation_get_stock_status', array( $this, 'variation_stock_status' ), 10, 2 );
		add_action( 'woocommerce_before_product_object_save', array( $this, 'before_parent_save' ), 99, 1 );
		add_action( 'woocommerce_after_product_object_save', array( $this, 'after_parent_save' ), 99, 1 );
		add_action( 'woocommerce_product_set_stock', array( $this, 'after_parent_stock_set' ), 10, 1 );

		// Notifications / admin display.
		add_filter( 'woocommerce_email_content_low_stock', array( $this, 'low_stock_email' ), 10, 2 );
		add_filter( 'woocommerce_admin_stock_html', array( $this, 'admin_stock_html' ), 10, 2 );
	}

	/*
	|--------------------------------------------------------------------------
	| Mute (plugin's own saves)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Start a plugin-internal operation.
	 */
	public static function mute() {
		++self::$mute;
	}

	/**
	 * End a plugin-internal operation.
	 */
	public static function unmute() {
		self::$mute = max( 0, self::$mute - 1 );
	}

	/**
	 * Inside a plugin-internal operation?
	 *
	 * @return bool
	 */
	public static function is_muted() {
		return self::$mute > 0;
	}

	/*
	|--------------------------------------------------------------------------
	| Units → ml
	|--------------------------------------------------------------------------
	*/

	/**
	 * Convert the quantity WooCommerce reduces / reserves for a volume item to ml.
	 *
	 * The filter name is generic, so the conversion is limited to WooCommerce's stock code
	 * (wc_reduce_stock_levels and the checkout stock reservation). Anything else that applies
	 * this filter (e.g. a payment gateway building its item list) keeps receiving units.
	 *
	 * @param int|float             $qty   Quantity.
	 * @param WC_Order              $order Order.
	 * @param WC_Order_Item_Product $item  Item.
	 * @return int|float
	 */
	public function order_item_quantity( $qty, $order, $item ) {
		if ( ! $item instanceof WC_Order_Item_Product || ! AVS_Order_Item::is_volume( $item ) ) {
			return $qty;
		}

		/**
		 * Whether to convert this quantity to ml. Defaults to true only when called from
		 * WooCommerce stock reduction / reservation.
		 *
		 * @param bool                  $convert Convert.
		 * @param WC_Order_Item_Product $item    Item.
		 */
		if ( ! apply_filters( 'avs_convert_order_item_quantity', self::called_from_stock_code(), $item ) ) {
			return $qty;
		}

		$unit = AVS_Order_Item::unit_ml( $item, true );
		if ( $unit <= 0 ) {
			return $qty;
		}

		return wc_stock_amount( $qty ) * $unit;
	}

	/**
	 * Is the current filter call coming from WooCommerce's stock reduction or reservation?
	 *
	 * @return bool
	 */
	private static function called_from_stock_code() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
		foreach ( $trace as $frame ) {
			$function = isset( $frame['function'] ) ? $frame['function'] : '';
			$class    = isset( $frame['class'] ) ? $frame['class'] : '';

			if ( 'wc_reduce_stock_levels' === $function && '' === $class ) {
				return true;
			}
			if ( 'reserve_stock_for_order' === $function && 'ReserveStock' === substr( $class, -12 ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Store the ml of one unit on new checkout line items.
	 *
	 * @param WC_Order_Item_Product $item          Item.
	 * @param string                $cart_item_key Cart key.
	 * @param array                 $values        Cart values.
	 * @param WC_Order              $order         Order.
	 */
	public function stamp_line_item( $item, $cart_item_key, $values, $order ) {
		if ( $item instanceof WC_Order_Item_Product ) {
			AVS_Order_Item::stamp( $item );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Reduce / restore (ledger + notes)
	|--------------------------------------------------------------------------
	*/

	/**
	 * After WooCommerce reduced the stock of one item.
	 *
	 * @param WC_Order_Item_Product $item   Item.
	 * @param array                 $change from / to / product.
	 * @param WC_Order              $order  Order.
	 */
	public function after_reduce_item( $item, $change, $order ) {
		if ( ! AVS_Order_Item::is_volume( $item ) ) {
			return;
		}

		$unit      = AVS_Order_Item::unit_ml( $item, false );
		$from      = (int) $change['from'];
		$to        = (int) $change['to'];
		$ml        = $from - $to;
		$parent_id = AVS_Order_Item::parent_id( $item );

		AVS_Ledger::add(
			array(
				'product_id'    => $parent_id,
				'variation_id'  => $item->get_variation_id(),
				'order_id'      => $order->get_id(),
				'order_item_id' => $item->get_id(),
				'quantity'      => (int) $item->get_quantity(),
				'change_ml'     => -$ml,
				'balance_ml'    => $to,
				'reason'        => 'order_reduce',
				'user_id'       => 0,
			)
		);

		$this->add_note_line(
			$order->get_id(),
			sprintf(
				/* translators: 1: product, 2: quantity, 3: ml per unit, 4: total ml, 5: pool before, 6: pool after */
				__( '%1$s: %2$s × %3$s = %4$s (مخزن: %5$s ← %6$s)', 'aromist-volume-stock' ),
				$item->get_name(),
				number_format_i18n( $item->get_quantity() ),
				AVS_Settings::format_ml( $unit ),
				AVS_Settings::format_ml( $ml ),
				number_format_i18n( $from ),
				AVS_Settings::format_ml( $to )
			)
		);

		$this->maybe_notify_empty( $parent_id, $from, $to );
	}

	/**
	 * After WooCommerce restored the stock of one item (cancelled / failed / pending).
	 *
	 * @param WC_Order_Item_Product $item      Item.
	 * @param int                   $new_stock New pool.
	 * @param int                   $old_stock Old pool.
	 * @param WC_Order              $order     Order.
	 */
	public function after_restore_item( $item, $new_stock, $old_stock, $order ) {
		if ( ! AVS_Order_Item::is_volume( $item ) ) {
			return;
		}

		$unit = AVS_Order_Item::unit_ml( $item, false );
		$ml   = (int) $new_stock - (int) $old_stock;

		AVS_Ledger::add(
			array(
				'product_id'    => AVS_Order_Item::parent_id( $item ),
				'variation_id'  => $item->get_variation_id(),
				'order_id'      => $order->get_id(),
				'order_item_id' => $item->get_id(),
				'quantity'      => $unit > 0 ? -1 * (int) round( $ml / $unit ) : 0,
				'change_ml'     => $ml,
				'balance_ml'    => (int) $new_stock,
				'reason'        => AVS_Order_Item::ledger_reason( $item, 'order_restore' ),
				'user_id'       => 0,
			)
		);

		$this->add_note_line(
			$order->get_id(),
			sprintf(
				/* translators: 1: product, 2: ml, 3: pool after */
				__( '%1$s: %2$s به مخزن برگشت (موجودی: %3$s)', 'aromist-volume-stock' ),
				$item->get_name(),
				AVS_Settings::format_ml( $ml ),
				AVS_Settings::format_ml( $new_stock )
			)
		);
	}

	/**
	 * Collect a line for the order note.
	 *
	 * @param int    $order_id Order id.
	 * @param string $line     Line.
	 */
	private function add_note_line( $order_id, $line ) {
		$this->note_lines[ $order_id ][] = $line;
	}

	/**
	 * Write collected lines as one order note.
	 *
	 * @param WC_Order $order Order.
	 */
	public function flush_order_note( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$id = $order->get_id();
		if ( empty( $this->note_lines[ $id ] ) ) {
			return;
		}
		$lines = $this->note_lines[ $id ];
		unset( $this->note_lines[ $id ] );

		if ( 'yes' === AVS_Settings::get( 'order_notes' ) ) {
			$order->add_order_note( __( 'موجودی حجمی:', 'aromist-volume-stock' ) . '<br>' . implode( '<br>', array_map( 'esc_html', $lines ) ) );
		}
	}

	/**
	 * Send WooCommerce's "out of stock" notification when the pool falls below the smallest size.
	 * (WooCommerce itself only notifies when the pool reaches its "no stock" threshold, usually 0.)
	 *
	 * @param int $parent_id Parent id.
	 * @param int $from      Pool before.
	 * @param int $to        Pool after.
	 */
	private function maybe_notify_empty( $parent_id, $from, $to ) {
		if ( isset( $this->empty_notified[ $parent_id ] ) ) {
			return;
		}
		$min      = AVS_Product::min_unit_ml( $parent_id );
		$no_stock = absint( get_option( 'woocommerce_notify_no_stock_amount', 0 ) );

		if ( $min > 0 && $from >= $min && $to < $min && $to > $no_stock ) {
			$this->empty_notified[ $parent_id ] = true;
			$parent                             = wc_get_product( $parent_id );
			if ( $parent ) {
				/** This action is documented in woocommerce/includes/wc-stock-functions.php */
				do_action( 'woocommerce_no_stock', $parent );
			}
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Refund restock
	|--------------------------------------------------------------------------
	*/

	/**
	 * Before WooCommerce restocks refunded items: remember the volume items and their state.
	 *
	 * @param bool     $can                 Can restock.
	 * @param WC_Order $order               Order.
	 * @param array    $refunded_line_items Items => [ qty, ... ].
	 * @return bool
	 */
	public function before_refund_restock( $can, $order, $refunded_line_items ) {
		if ( ! $can || ! $order instanceof WC_Order ) {
			return $can;
		}

		foreach ( (array) $refunded_line_items as $item_id => $data ) {
			$qty = isset( $data['qty'] ) ? wc_stock_amount( $data['qty'] ) : 0;
			if ( $qty <= 0 ) {
				continue;
			}
			$item = $order->get_item( $item_id );
			if ( ! AVS_Order_Item::is_volume( $item ) ) {
				continue;
			}
			$reduced = (int) wc_stock_amount( $item->get_meta( '_reduced_stock', true ) );
			$unit    = AVS_Order_Item::unit_ml( $item, false );
			if ( $reduced <= 0 || $unit <= 0 ) {
				continue; // WooCommerce skips it as well.
			}

			$this->refund_queue[ $order->get_id() ][ $item->get_variation_id() ][] = array(
				'item_id' => (int) $item_id,
				'qty'     => $qty,
				'unit'    => $unit,
				'reduced' => $reduced,
				// Never give back more than was taken for this item.
				'restore' => min( $qty * $unit, $reduced ),
			);
		}

		return $can;
	}

	/**
	 * Correct the order note WooCommerce writes for a restocked refund (it thinks in units).
	 *
	 * @param string     $note      Note.
	 * @param int        $old_stock Old stock.
	 * @param int        $new_stock New stock (after WooCommerce added the units).
	 * @param WC_Order   $order     Order.
	 * @param WC_Product $product   Product.
	 * @return string
	 */
	public function refund_restock_note( $note, $old_stock, $new_stock, $order, $product ) {
		if ( ! $order instanceof WC_Order || ! $product instanceof WC_Product ) {
			return $note;
		}
		$queue = isset( $this->refund_queue[ $order->get_id() ][ $product->get_id() ] ) ? $this->refund_queue[ $order->get_id() ][ $product->get_id() ] : array();
		if ( empty( $queue ) ) {
			return $note;
		}
		$entry = $queue[0];
		$final = (int) $new_stock - $entry['qty'] + $entry['restore'];

		return sprintf(
			/* translators: 1: product, 2: ml, 3: old pool, 4: new pool */
			__( 'مرجوعی: %2$s از «%1$s» به مخزن برگشت (%3$s ← %4$s).', 'aromist-volume-stock' ),
			$product->get_name(),
			AVS_Settings::format_ml( $entry['restore'] ),
			number_format_i18n( (int) $old_stock ),
			AVS_Settings::format_ml( $final )
		);
	}

	/**
	 * After WooCommerce restocked one refunded item (by units): top it up to the real ml.
	 *
	 * @param int        $product_id Product (variation) id.
	 * @param int        $old_stock  Old stock.
	 * @param int        $new_stock  New stock.
	 * @param WC_Order   $order      Order.
	 * @param WC_Product $product    Product.
	 */
	public function after_refund_restock_item( $product_id, $old_stock, $new_stock, $order, $product ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$order_id = $order->get_id();
		if ( empty( $this->refund_queue[ $order_id ][ $product_id ] ) ) {
			return;
		}

		$entry = array_shift( $this->refund_queue[ $order_id ][ $product_id ] );
		$extra = $entry['restore'] - $entry['qty']; // WooCommerce already added `qty`.
		$final = (int) $new_stock;

		self::mute();
		if ( $extra > 0 ) {
			$final = wc_update_product_stock( $product, $extra, 'increase' );
		} elseif ( $extra < 0 ) {
			$final = wc_update_product_stock( $product, -$extra, 'decrease' );
		}
		self::unmute();

		$item = $order->get_item( $entry['item_id'] );
		if ( $item ) {
			// WooCommerce subtracted `qty` from _reduced_stock; the real remaining amount in ml is:
			$item->update_meta_data( '_reduced_stock', max( 0, $entry['reduced'] - $entry['restore'] ) );
			$item->save();
		}

		AVS_Ledger::add(
			array(
				'product_id'    => $product instanceof WC_Product_Variation ? $product->get_parent_id() : (int) $product_id,
				'variation_id'  => (int) $product_id,
				'order_id'      => $order_id,
				'order_item_id' => $entry['item_id'],
				'quantity'      => -1 * (int) $entry['qty'],
				'change_ml'     => (int) $entry['restore'],
				'balance_ml'    => is_numeric( $final ) ? (int) $final : null,
				'reason'        => $item ? AVS_Order_Item::ledger_reason( $item, 'refund_restock' ) : 'refund_restock',
			)
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Editing an existing order's items (admin / REST)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Replace WooCommerce's unit-based adjustment with an ml-based one for volume items.
	 *
	 * Mirrors wc_maybe_adjust_line_item_product_stock(): `_reduced_stock` is kept in ml,
	 * `_restock_refunded_items` stays in units (as written by WooCommerce).
	 *
	 * @param bool                  $prevent       Prevent flag.
	 * @param WC_Order_Item_Product $item          Item.
	 * @param int                   $item_quantity New quantity (-1 = read from item).
	 * @return bool
	 */
	public function adjust_line_item( $prevent, $item, $item_quantity ) {
		if ( $prevent || ! AVS_Order_Item::is_volume( $item ) ) {
			return $prevent;
		}

		$unit = AVS_Order_Item::unit_ml( $item, true );
		if ( $unit <= 0 ) {
			return $prevent;
		}

		$item_quantity  = (int) wc_stock_amount( $item_quantity >= 0 ? $item_quantity : $item->get_quantity() );
		$already        = (int) wc_stock_amount( $item->get_meta( '_reduced_stock', true ) );
		$refunded_units = (int) wc_stock_amount( $item->get_meta( '_restock_refunded_items', true ) );
		$target         = max( 0, $item_quantity - $refunded_units ) * $unit;
		$diff           = 0 === $item_quantity ? -$already : $target - $already;

		if ( 0 === $diff ) {
			$item->save_meta_data(); // Persists _avs_ml when it was just assigned.
			return true;
		}

		$product = $item->get_product();
		self::mute();
		$new_stock = $diff < 0
			? wc_update_product_stock( $product, -$diff, 'increase' )
			: wc_update_product_stock( $product, $diff, 'decrease' );
		self::unmute();

		if ( is_wp_error( $new_stock ) || false === $new_stock || null === $new_stock ) {
			return true;
		}

		$item->update_meta_data( '_reduced_stock', 0 === $item_quantity ? 0 : $target );
		$item->save();

		$order_id = $item->get_order_id();
		if ( $item_quantity > 0 && $order_id ) {
			$order_data_store = WC_Data_Store::load( 'order' );
			if ( ! $order_data_store->get_stock_reduced( $order_id ) ) {
				$order_data_store->set_stock_reduced( $order_id, true );
			}
		}

		AVS_Ledger::add(
			array(
				'product_id'    => AVS_Order_Item::parent_id( $item ),
				'variation_id'  => $item->get_variation_id(),
				'order_id'      => $order_id,
				'order_item_id' => $item->get_id(),
				'quantity'      => (int) round( $diff / $unit ),
				'change_ml'     => -$diff,
				'balance_ml'    => (int) $new_stock,
				'reason'        => AVS_Order_Item::ledger_reason( $item, 'order_edit' ),
			)
		);

		$order = $order_id ? wc_get_order( $order_id ) : false;
		if ( $order && 'yes' === AVS_Settings::get( 'order_notes' ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: product, 2: signed ml change, 3: new pool */
					__( 'موجودی حجمی (ویرایش سفارش): %1$s ← %2$s، موجودی مخزن: %3$s', 'aromist-volume-stock' ),
					esc_html( $item->get_name() ),
					esc_html( ( $diff > 0 ? '−' : '+' ) . AVS_Settings::format_ml( abs( $diff ) ) ),
					esc_html( AVS_Settings::format_ml( $new_stock ) )
				),
				false,
				true
			);
		}

		return true;
	}

	/*
	|--------------------------------------------------------------------------
	| Stock status
	|--------------------------------------------------------------------------
	*/

	/**
	 * Status of a volume variation = can one unit be taken from the pool?
	 *
	 * @param string               $status    Stored status.
	 * @param WC_Product_Variation $variation Variation.
	 * @return string
	 */
	public function variation_stock_status( $status, $variation ) {
		if ( ! AVS_Product::is_volume_variation( $variation ) ) {
			return $status;
		}
		return self::status_for_variation( $variation->get_id(), $variation->get_parent_id(), $variation->get_backorders() );
	}

	/**
	 * Status of a volume variation from ids.
	 *
	 * @param int    $variation_id Variation id.
	 * @param int    $parent_id    Parent id.
	 * @param string $backorders   Backorders setting (inherited from the parent).
	 * @return string
	 */
	public static function status_for_variation( $variation_id, $parent_id, $backorders ) {
		$ml = AVS_Product::variation_ml( $variation_id );
		if ( $ml <= 0 ) {
			return 'outofstock';
		}
		$unit = $ml + AVS_Product::waste_ml( $parent_id );

		if ( AVS_Product::pool( $parent_id ) >= $unit ) {
			return 'instock';
		}
		return in_array( $backorders, array( 'yes', 'notify' ), true ) ? 'onbackorder' : 'outofstock';
	}

	/**
	 * Before a volume parent is saved: status = can the smallest size still be sold?
	 * Also detects stock edits that did not come from orders or this plugin (product screen, REST, import).
	 *
	 * @param WC_Product $product Product.
	 */
	public function before_parent_save( $product ) {
		if ( ! $product instanceof WC_Product_Variable || 'yes' !== $product->get_meta( AVS_Product::META_ENABLED ) ) {
			return;
		}

		$changes = $product->get_changes();
		if ( ! self::is_muted() && $product->get_id() && array_key_exists( 'stock_quantity', $changes ) ) {
			$db_pool = AVS_Product::pool( $product->get_id() );
			if ( (int) wc_stock_amount( $changes['stock_quantity'] ) !== $db_pool ) {
				$this->pool_before_save[ $product->get_id() ] = $db_pool;
			}
		}

		if ( ! $product->get_manage_stock() ) {
			return;
		}

		$pool     = (int) wc_stock_amount( $product->get_stock_quantity( 'edit' ) );
		$min      = max( 1, AVS_Product::min_unit_ml( $product->get_id() ) );
		$no_stock = absint( get_option( 'woocommerce_notify_no_stock_amount', 0 ) );

		if ( $pool >= $min && $pool > $no_stock ) {
			$status = 'instock';
		} elseif ( 'no' !== $product->get_backorders( 'edit' ) ) {
			$status = 'onbackorder';
		} else {
			$status = 'outofstock';
		}
		$product->set_stock_status( $status );
	}

	/**
	 * After a volume parent was saved: log outside edits and sync the stored status of its variations.
	 *
	 * @param WC_Product $product Product.
	 */
	public function after_parent_save( $product ) {
		if ( ! $product instanceof WC_Product_Variable || 'yes' !== $product->get_meta( AVS_Product::META_ENABLED ) ) {
			return;
		}

		$id = $product->get_id();
		if ( isset( $this->pool_before_save[ $id ] ) ) {
			$before = $this->pool_before_save[ $id ];
			unset( $this->pool_before_save[ $id ] );
			$after = AVS_Product::pool( $id );
			if ( $after !== $before ) {
				AVS_Ledger::add(
					array(
						'product_id' => $id,
						'change_ml'  => $after - $before,
						'balance_ml' => $after,
						'reason'     => 'product_edit',
					)
				);
			}
		}

		self::sync_children_status( $id );
	}

	/**
	 * After wc_update_product_stock() changed a parent (also covers `$updating = true` calls).
	 *
	 * @param WC_Product $product Product.
	 */
	public function after_parent_stock_set( $product ) {
		if ( $product instanceof WC_Product && AVS_Product::is_enabled( $product->get_id() ) ) {
			self::sync_children_status( $product->get_id() );
		}
	}

	/**
	 * Store the real status on each volume variation (post meta + product lookup table),
	 * so database queries (shop filters, "hide out of stock", reports) see the same thing
	 * as the product page. WooCommerce copies the parent's status to inherited variations on
	 * every parent save; this runs right after that.
	 *
	 * @param int $parent_id Parent id.
	 */
	public static function sync_children_status( $parent_id ) {
		global $wpdb;

		if ( ! AVS_Product::is_enabled( $parent_id ) || AVS_Product::is_unlimited( $parent_id ) ) {
			return; // «Unlimited»: each size keeps the status the shop gave it.
		}

		$backorders   = get_post_meta( $parent_id, '_backorders', true );
		$lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';
		$changed      = false;

		foreach ( AVS_Product::children_ids( $parent_id ) as $child_id ) {
			if ( 'yes' === get_post_meta( $child_id, '_manage_stock', true ) || AVS_Product::variation_ml( $child_id ) <= 0 ) {
				continue;
			}
			$status = self::status_for_variation( $child_id, $parent_id, $backorders );
			if ( get_post_meta( $child_id, '_stock_status', true ) === $status ) {
				continue;
			}

			update_post_meta( $child_id, '_stock_status', $status );
			$wpdb->update( $lookup_table, array( 'stock_status' => $status ), array( 'product_id' => $child_id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			wc_delete_product_transients( $child_id );
			$changed = true;
		}

		if ( $changed ) {
			wc_delete_product_transients( $parent_id );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Notifications / admin display
	|--------------------------------------------------------------------------
	*/

	/**
	 * Low stock email text in ml.
	 *
	 * @param string     $message Message.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function low_stock_email( $message, $product ) {
		if ( ! $product instanceof WC_Product ) {
			return $message;
		}
		$parent_id = $product instanceof WC_Product_Variation ? $product->get_parent_id() : $product->get_id();
		if ( ! AVS_Product::is_enabled( $parent_id ) ) {
			return $message;
		}
		$parent = wc_get_product( $parent_id );

		return sprintf(
			/* translators: 1: product name, 2: pool */
			__( 'موجودی حجمی «%1$s» کم است: %2$s در مخزن باقی مانده.', 'aromist-volume-stock' ),
			$parent ? $parent->get_name() : $product->get_name(),
			AVS_Settings::format_ml( AVS_Product::pool( $parent_id ) )
		);
	}

	/**
	 * Stock column of the products list: show the pool in ml.
	 *
	 * @param string     $html    HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function admin_stock_html( $html, $product ) {
		if ( ! AVS_Product::is_volume_parent( $product ) ) {
			return $html;
		}
		if ( AVS_Product::is_unlimited( $product->get_id() ) ) {
			return $html . ' (' . esc_html__( 'حجمی، نامحدود', 'aromist-volume-stock' ) . ')';
		}
		$pool   = AVS_Product::pool( $product->get_id() );
		$status = $product->is_in_stock() ? '<mark class="instock">' . esc_html__( 'موجود', 'aromist-volume-stock' ) . '</mark>' : '<mark class="outofstock">' . esc_html__( 'ناموجود', 'aromist-volume-stock' ) . '</mark>';

		return $status . ' (' . esc_html( AVS_Settings::format_ml( $pool ) ) . ')';
	}
}
