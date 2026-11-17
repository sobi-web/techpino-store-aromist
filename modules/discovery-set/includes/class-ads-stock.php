<?php
/**
 * Stock of sets: reduce, restore, refund restock, admin edits and checkout holds.
 *
 * A set product never manages stock, so WooCommerce's own stock code skips set items. This class
 * runs at the end of the same WooCommerce flows and takes / gives back the amounts recorded in
 * the item's `_ads_set` snapshot (ml from each perfume pool, units from the box):
 *
 *   wc_reduce_stock_levels()  → woocommerce_reduce_order_stock   → reduce()
 *   wc_increase_stock_levels()→ woocommerce_restore_order_stock  → restore()
 *   wc_restock_refunded_items → woocommerce_can_restock_refunded_items (last) → refund_restock()
 *   wc_maybe_adjust_line_item_product_stock → woocommerce_prevent_adjust_line_item_product_stock → adjust_item()
 *   checkout hold             → woocommerce_checkout_order_created (after WooCommerce, prio 11) → reserve()
 *
 * Perfume pool movements are written to the Volume Stock ledger with its own reasons, so its
 * reports count set sales as consumption.
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Checkout\Helpers\ReserveStockException;

/**
 * Stock hooks.
 */
final class ADS_Stock {

	const META_SET      = '_ads_set';
	const META_REDUCED  = '_ads_reduced';
	const META_REFUNDED = '_ads_refunded';

	/**
	 * Order note lines collected during one run. order id => string[].
	 *
	 * @var array
	 */
	private $notes = array();

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_reduce_order_stock', array( $this, 'reduce' ), 20 );
		add_action( 'woocommerce_restore_order_stock', array( $this, 'restore' ), 20 );
		add_filter( 'woocommerce_can_restock_refunded_items', array( $this, 'refund_restock' ), PHP_INT_MAX, 3 );
		add_filter( 'woocommerce_prevent_adjust_line_item_product_stock', array( $this, 'adjust_item' ), 10000, 3 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'reserve' ), 11 );
		add_action( 'woocommerce_ajax_add_order_item_meta', array( $this, 'stamp_admin_item' ), 10, 3 );
	}

	/*
	|--------------------------------------------------------------------------
	| Item helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Snapshot of a set item, or null.
	 *
	 * @param mixed $item Order item.
	 * @return array|null
	 */
	public static function snapshot( $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return null;
		}
		$set = $item->get_meta( self::META_SET, true );
		return is_array( $set ) && ! empty( $set['draws'] ) ? $set : null;
	}

	/**
	 * Amount per product for one set of this item.
	 *
	 * @param array $set Snapshot.
	 * @return array product id => amount
	 */
	private static function per_set( array $set ) {
		$out = array();
		foreach ( (array) $set['draws'] as $draw ) {
			$id         = absint( $draw['p'] );
			$out[ $id ] = ( isset( $out[ $id ] ) ? $out[ $id ] : 0 ) + absint( $draw['a'] );
		}
		return $out;
	}

	/**
	 * What has been taken for this item so far.
	 *
	 * @param WC_Order_Item_Product $item Item.
	 * @return array product id => amount
	 */
	private static function reduced( $item ) {
		$reduced = $item->get_meta( self::META_REDUCED, true );
		return is_array( $reduced ) ? array_map( 'intval', $reduced ) : array();
	}

	/**
	 * Change one product's stock (+ ledger for perfume pools, note line).
	 *
	 * @param int                   $product_id Product.
	 * @param int                   $change     Signed amount (negative = take).
	 * @param WC_Order              $order      Order.
	 * @param WC_Order_Item_Product $item       Item.
	 * @param string                $reason     Volume Stock ledger reason.
	 * @return int Amount actually changed (signed); 0 when nothing could be changed.
	 */
	private function move( $product_id, $change, $order, $item, $reason ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->managing_stock() || 0 === $change ) {
			return 0;
		}

		AVS_Stock::mute(); // Not an "outside edit" for the Volume Stock ledger; the row is written below.
		$new = wc_update_product_stock( $product, abs( $change ), $change < 0 ? 'decrease' : 'increase' );
		AVS_Stock::unmute();
		if ( is_wp_error( $new ) || null === $new || false === $new ) {
			return 0;
		}
		$new = (int) $new;
		$old = $new - $change;

		$is_pool = AVS_Product::is_enabled( $product_id );
		if ( $is_pool ) {
			AVS_Ledger::add(
				array(
					'product_id'    => $product_id,
					'variation_id'  => 0,
					'order_id'      => $order->get_id(),
					'order_item_id' => $item->get_id(),
					'quantity'      => 0,
					'change_ml'     => $change,
					'balance_ml'    => $new,
					'reason'        => $reason,
					'note'          => sprintf(
						/* translators: %s: set name */
						__( 'دیسکاوری ست: %s', 'aromist-discovery-set' ),
						$item->get_name()
					),
					'user_id'       => 'order_edit' === $reason ? get_current_user_id() : 0,
				)
			);
		}

		$this->notes[ $order->get_id() ][] = sprintf(
			'%1$s: %2$s (%3$s ← %4$s)',
			$is_pool ? ADS_Set::perfume_name( $product_id ) : $product->get_name(),
			( $change < 0 ? '−' : '+' ) . ( $is_pool ? ads_format_ml( abs( $change ) ) : number_format_i18n( abs( $change ) ) ),
			number_format_i18n( $old ),
			number_format_i18n( $new )
		);

		if ( $change < 0 ) {
			self::notify_levels( $product, $old, $new );
		}
		return $change;
	}

	/**
	 * WooCommerce "low stock" / "out of stock" notifications for a product we changed.
	 *
	 * @param WC_Product $product Product.
	 * @param int        $old     Before.
	 * @param int        $new     After.
	 */
	private static function notify_levels( $product, $old, $new ) {
		$fresh = wc_get_product( $product->get_id() );
		if ( ! $fresh ) {
			return;
		}
		$no_stock = absint( get_option( 'woocommerce_notify_no_stock_amount', 0 ) );
		$low      = absint( wc_get_low_stock_amount( $fresh ) );
		if ( $new <= $no_stock && $old > $no_stock ) {
			do_action( 'woocommerce_no_stock', $fresh ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		} elseif ( $new <= $low && $old > $low ) {
			do_action( 'woocommerce_low_stock', $fresh ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		}
	}

	/**
	 * Write the collected lines as one order note.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $title Title.
	 */
	private function flush_note( $order, $title ) {
		$id = $order->get_id();
		if ( empty( $this->notes[ $id ] ) ) {
			return;
		}
		$lines = $this->notes[ $id ];
		unset( $this->notes[ $id ] );
		if ( 'yes' === ADS_Settings::get( 'order_notes' ) ) {
			$order->add_order_note( esc_html( $title ) . '<br>' . implode( '<br>', array_map( 'esc_html', $lines ) ) );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Reduce / restore
	|--------------------------------------------------------------------------
	*/

	/**
	 * Take stock for every set item not yet reduced (end of wc_reduce_stock_levels()).
	 *
	 * @param WC_Order $order Order.
	 */
	public function reduce( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			$set = self::snapshot( $item );
			if ( ! $set || $item->meta_exists( self::META_REDUCED ) ) {
				continue; // Only once per item, like WooCommerce's `_reduced_stock`.
			}
			$qty     = max( 0, (int) $item->get_quantity() - (int) $item->get_meta( self::META_REFUNDED, true ) );
			$reduced = array();
			foreach ( self::per_set( $set ) as $id => $amount ) {
				$moved = $this->move( $id, -1 * $amount * $qty, $order, $item, 'order_reduce' );
				if ( $moved ) {
					$reduced[ $id ] = -1 * $moved;
				}
			}
			$item->update_meta_data( self::META_REDUCED, $reduced );
			$item->save();
		}
		$this->flush_note( $order, __( 'دیسکاوری ست — کسر موجودی:', 'aromist-discovery-set' ) );
	}

	/**
	 * Give back everything taken (end of wc_increase_stock_levels(): cancelled, failed, pending).
	 *
	 * @param WC_Order $order Order.
	 */
	public function restore( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			if ( ! self::snapshot( $item ) ) {
				continue;
			}
			if ( ! $item->meta_exists( self::META_REDUCED ) ) {
				continue;
			}
			foreach ( self::reduced( $item ) as $id => $amount ) {
				if ( $amount > 0 ) {
					$this->move( $id, $amount, $order, $item, 'order_restore' );
				}
			}
			$item->delete_meta_data( self::META_REDUCED );
			$item->save();
		}
		$this->flush_note( $order, __( 'دیسکاوری ست — بازگشت موجودی:', 'aromist-discovery-set' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Refund restock
	|--------------------------------------------------------------------------
	*/

	/**
	 * Refund with "restock refunded items": give back the share of the refunded sets.
	 * Runs last on the filter, so `$can` is final.
	 *
	 * @param bool     $can                 Restock allowed.
	 * @param WC_Order $order               Order.
	 * @param array    $refunded_line_items item id => [ qty, ... ].
	 * @return bool
	 */
	public function refund_restock( $can, $order, $refunded_line_items ) {
		if ( ! $can || ! $order instanceof WC_Order ) {
			return $can;
		}
		foreach ( (array) $refunded_line_items as $item_id => $data ) {
			$qty  = isset( $data['qty'] ) ? (int) wc_stock_amount( $data['qty'] ) : 0;
			$item = $qty > 0 ? $order->get_item( $item_id ) : null;
			$set  = self::snapshot( $item );
			if ( ! $set || ! $item->meta_exists( self::META_REDUCED ) ) {
				continue;
			}
			$reduced = self::reduced( $item );
			foreach ( self::per_set( $set ) as $id => $amount ) {
				$back = min( $amount * $qty, isset( $reduced[ $id ] ) ? $reduced[ $id ] : 0 );
				if ( $back > 0 && $this->move( $id, $back, $order, $item, 'refund_restock' ) ) {
					$reduced[ $id ] -= $back;
				}
			}
			$item->update_meta_data( self::META_REDUCED, $reduced );
			$item->update_meta_data( self::META_REFUNDED, (int) $item->get_meta( self::META_REFUNDED, true ) + $qty );
			$item->save();
		}
		$this->flush_note( $order, __( 'دیسکاوری ست — بازگشت مرجوعی به انبار:', 'aromist-discovery-set' ) );
		return $can;
	}

	/*
	|--------------------------------------------------------------------------
	| Admin edits of existing orders
	|--------------------------------------------------------------------------
	*/

	/**
	 * Quantity change / removal of a set item in an order whose stock was reduced
	 * (WooCommerce calls this only for processing / completed / on-hold orders, and with 0
	 * before deleting an item).
	 *
	 * @param bool                  $prevent       Prevent flag.
	 * @param WC_Order_Item_Product $item          Item.
	 * @param int                   $item_quantity New quantity (-1 = from the item).
	 * @return bool
	 */
	public function adjust_item( $prevent, $item, $item_quantity ) {
		$set = self::snapshot( $item );
		if ( $prevent || ! $set ) {
			return $prevent;
		}
		$order = $item->get_order();
		if ( ! $order ) {
			return true;
		}

		$quantity = (int) wc_stock_amount( $item_quantity >= 0 ? $item_quantity : $item->get_quantity() );
		$refunded = (int) $item->get_meta( self::META_REFUNDED, true );
		$reduced  = self::reduced( $item );
		$changed  = false;

		foreach ( self::per_set( $set ) as $id => $amount ) {
			$target = 0 === $quantity ? 0 : max( 0, $quantity - $refunded ) * $amount;
			$have   = isset( $reduced[ $id ] ) ? $reduced[ $id ] : 0;
			$diff   = $target - $have;
			if ( 0 === $diff ) {
				continue;
			}
			$moved = $this->move( $id, -1 * $diff, $order, $item, 'order_edit' );
			if ( $moved ) {
				$reduced[ $id ] = $have - $moved;
				$changed        = true;
			}
		}

		if ( $changed || ! $item->meta_exists( self::META_REDUCED ) ) {
			$item->update_meta_data( self::META_REDUCED, $reduced );
			$item->save();
		}
		if ( $changed && $quantity > 0 ) {
			$data_store = WC_Data_Store::load( 'order' );
			if ( ! $data_store->get_stock_reduced( $order->get_id() ) ) {
				$data_store->set_stock_reduced( $order->get_id(), true );
			}
		}
		$this->flush_note( $order, __( 'دیسکاوری ست — ویرایش سفارش:', 'aromist-discovery-set' ) );

		return true;
	}

	/**
	 * A ready-made set added from the order screen: stamp it with its default volume.
	 *
	 * @param int                   $item_id Item id.
	 * @param WC_Order_Item_Product $item    Item.
	 * @param WC_Order              $order   Order.
	 */
	public function stamp_admin_item( $item_id, $item, $order ) {
		if ( ! $item instanceof WC_Order_Item_Product || self::snapshot( $item ) ) {
			return;
		}
		$product = $item->get_product();
		if ( ! $product instanceof WC_Product_Discovery_Set || $product->is_custom() || '' !== ADS_Set::preset_error( $product ) ) {
			return;
		}
		ADS_Cart::stamp_item( $item, $product->get_components(), $product->get_default_volume(), $product );
		$item->save();
	}

	/*
	|--------------------------------------------------------------------------
	| Checkout hold
	|--------------------------------------------------------------------------
	*/

	/**
	 * Hold the sets' share of pools and boxes during checkout, in WooCommerce's reserved-stock
	 * table. Runs after WooCommerce reserved the normal items (priority 10) and adds the sets'
	 * amounts to the same (order, product) rows with the same locking query.
	 *
	 * @param WC_Order $order Order.
	 * @throws ReserveStockException When a perfume or box does not have enough.
	 */
	public function reserve( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		if ( ! apply_filters( 'woocommerce_hold_stock_for_checkout', wc_string_to_bool( get_option( 'woocommerce_manage_stock', 'yes' ) ) ) ) { // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
			return;
		}
		if ( get_option( 'woocommerce_schema_version', 0 ) < 430 ) {
			return;
		}
		$minutes = (int) apply_filters( 'woocommerce_order_hold_stock_minutes', (int) get_option( 'woocommerce_hold_stock_minutes', 60 ), $order ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		if ( $minutes <= 0 ) {
			return;
		}

		$rows = array();
		foreach ( $order->get_items() as $item ) {
			$set = self::snapshot( $item );
			if ( ! $set ) {
				continue;
			}
			foreach ( self::per_set( $set ) as $id => $amount ) {
				if ( ADS_Availability::is_limited( $id ) ) {
					$rows[ $id ] = ( isset( $rows[ $id ] ) ? $rows[ $id ] : 0 ) + $amount * (int) $item->get_quantity();
				}
			}
		}
		if ( ! $rows ) {
			return;
		}

		ksort( $rows ); // Same lock order as WooCommerce.
		foreach ( $rows as $id => $amount ) {
			$this->reserve_product( $id, $amount, $order, $minutes );
		}
	}

	/**
	 * Add an amount to the order's hold of one product, if stock minus other holds allows it.
	 *
	 * @param int      $product_id Product.
	 * @param int      $amount     Amount of the sets.
	 * @param WC_Order $order      Order.
	 * @param int      $minutes    Hold minutes.
	 * @throws ReserveStockException Not enough.
	 */
	private function reserve_product( $product_id, $amount, $order, $minutes ) {
		global $wpdb;

		$order_id = $order->get_id();
		if ( $this->wc_reserved_now( $order, $product_id ) ) {
			// WooCommerce just rewrote this row for the order's normal items; add the sets on top.
			// (own + amount always differs from own, so a successful update always reports changed rows.)
			$own = (int) $wpdb->get_var( $wpdb->prepare( "SELECT stock_quantity FROM {$wpdb->wc_reserved_stock} WHERE order_id = %d AND product_id = %d", $order_id, $product_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			// Only sets hold this product: drop a row left by an earlier checkout attempt, so the insert
			// below reports 1 row on success and 0 only when there is not enough stock.
			$wpdb->delete( $wpdb->wc_reserved_stock, array( 'order_id' => $order_id, 'product_id' => $product_id ), array( '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$own = 0;
		}
		$total = $own + (int) $amount;

		$query_for_stock    = WC_Data_Store::load( 'product' )->get_query_for_stock( $product_id );
		$query_for_reserved = $this->query_for_reserved_stock( $product_id, $order_id );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"
			INSERT INTO {$wpdb->wc_reserved_stock} ( `order_id`, `product_id`, `stock_quantity`, `timestamp`, `expires` )
			SELECT %d, %d, %d, NOW(), ( NOW() + INTERVAL %d MINUTE ) FROM DUAL
			WHERE ( $query_for_stock FOR UPDATE ) - ( $query_for_reserved LOCK IN SHARE MODE ) >= %d
			ON DUPLICATE KEY UPDATE `expires` = VALUES( `expires` ), `stock_quantity` = VALUES( `stock_quantity` )
			",
			$order_id,
			$product_id,
			$total,
			$minutes,
			$total
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$result = false;
		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
			if ( false !== $result ) {
				break;
			}
		}

		if ( ! $result ) {
			$message = ADS_Boxes::is_box( $product_id )
				/* translators: %s: box */
				? sprintf( __( 'جعبه‌ی «%s» برای ست‌های سفارش شما کافی نیست.', 'aromist-discovery-set' ), ADS_Boxes::name( $product_id ) )
				/* translators: %s: perfume */
				: sprintf( __( 'موجودی «%s» برای ست‌های سفارش شما کافی نیست. لطفاً سبد خرید را بررسی کنید.', 'aromist-discovery-set' ), ADS_Set::perfume_name( $product_id ) );
			throw new ReserveStockException( 'ads_not_enough_stock', esc_html( $message ), 403 );
		}
	}

	/**
	 * Did WooCommerce reserve this product for a normal item of the order (in this request)?
	 *
	 * @param WC_Order $order      Order.
	 * @param int      $product_id Product.
	 * @return bool
	 */
	private function wc_reserved_now( $order, $product_id ) {
		foreach ( $order->get_items() as $item ) {
			if ( self::snapshot( $item ) ) {
				continue;
			}
			$product = $item instanceof WC_Order_Item_Product ? $item->get_product() : null;
			if ( $product && $product->managing_stock() && ! $product->backorders_allowed() && (int) $product->get_stock_managed_by_id() === (int) $product_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Same query as WooCommerce's ReserveStock::get_query_for_reserved_stock() (private there).
	 *
	 * @param int $product_id       Product.
	 * @param int $exclude_order_id Order to leave out.
	 * @return string
	 */
	private function query_for_reserved_stock( $product_id, $exclude_order_id ) {
		global $wpdb;

		if ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$join         = "{$wpdb->prefix}wc_orders orders ON stock_table.`order_id` = orders.id";
			$where_status = "orders.status IN ( 'wc-checkout-draft', 'wc-pending' )";
		} else {
			$join         = "{$wpdb->posts} posts ON stock_table.`order_id` = posts.ID";
			$where_status = "posts.post_status IN ( 'wc-checkout-draft', 'wc-pending' )";
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"
			SELECT COALESCE( SUM( stock_table.`stock_quantity` ), 0 ) FROM $wpdb->wc_reserved_stock stock_table
			LEFT JOIN $join
			WHERE $where_status
			AND stock_table.`expires` > NOW()
			AND stock_table.`product_id` = %d
			AND stock_table.`order_id` != %d
			",
			$product_id,
			$exclude_order_id
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return apply_filters( 'woocommerce_query_for_reserved_stock', $query, $product_id, $exclude_order_id ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
	}
}
