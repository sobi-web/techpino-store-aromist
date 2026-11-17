<?php
/**
 * Business operations: enable / disable volume stock on a product, manual adjustments,
 * product settings. Used by the admin panel and the product edit screen.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

/**
 * Volume stock operations.
 */
final class AVS_Manager {

	/**
	 * Manual adjustment reasons shown in the panel.
	 *
	 * @return array
	 */
	public static function manual_reasons() {
		$all = AVS_Ledger::reasons();
		return array(
			'restock'   => $all['restock'],
			'waste'     => $all['waste'],
			'count_fix' => $all['count_fix'],
			'manual'    => $all['manual'],
		);
	}

	/**
	 * Suggested initial pool from the current per-variation stock (Σ stock × ml).
	 *
	 * @param WC_Product_Variable $parent Parent.
	 * @param array               $ml_map variation_id => ml.
	 * @return int
	 */
	public static function suggested_pool( $parent, array $ml_map ) {
		$total = 0;
		foreach ( $parent->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation || true !== $variation->get_manage_stock( 'edit' ) ) {
				continue;
			}
			$ml = isset( $ml_map[ $child_id ] ) ? absint( $ml_map[ $child_id ] ) : 0;
			$total += max( 0, (int) $variation->get_stock_quantity( 'edit' ) ) * $ml;
		}
		return $total;
	}

	/**
	 * Ml per variation: posted value, else saved value, else detected from the attributes.
	 *
	 * @param WC_Product_Variable $parent Parent.
	 * @param array               $posted variation_id => ml.
	 * @return array variation_id => ml (0 when unknown)
	 */
	public static function resolve_ml_map( $parent, array $posted = array() ) {
		$map = array();
		foreach ( $parent->get_children() as $child_id ) {
			$child_id = absint( $child_id );
			if ( isset( $posted[ $child_id ] ) && '' !== $posted[ $child_id ] ) {
				$map[ $child_id ] = AVS_Product::parse_ml( $posted[ $child_id ] );
				continue;
			}
			$saved = AVS_Product::variation_ml( $child_id );
			if ( $saved > 0 ) {
				$map[ $child_id ] = $saved;
				continue;
			}
			$variation        = wc_get_product( $child_id );
			$map[ $child_id ] = $variation instanceof WC_Product_Variation ? AVS_Product::detect_ml( $variation ) : 0;
		}
		return $map;
	}

	/**
	 * Enable volume stock on a variable product.
	 *
	 * @param int   $parent_id Parent id.
	 * @param array $args {
	 *     @type array    $ml_map   variation_id => ml (published variations must have ml > 0).
	 *     @type int|null $pool_ml  Initial pool. Null = keep the current parent stock / suggestion.
	 *     @type int      $low_ml   Low stock threshold (ml).
	 *     @type int      $waste_ml Extra ml per unit.
	 * }
	 * @return true|WP_Error
	 */
	public static function enable( $parent_id, array $args ) {
		$parent = wc_get_product( $parent_id );
		if ( ! $parent instanceof WC_Product_Variable ) {
			return new WP_Error( 'avs_not_variable', __( 'موجودی حجمی فقط برای محصولات متغیر قابل فعال‌سازی است.', 'aromist-volume-stock' ) );
		}
		if ( ! AVS_Product::store_manages_stock() ) {
			return new WP_Error( 'avs_no_stock_management', __( 'مدیریت موجودی در تنظیمات ووکامرس (محصولات ← موجودی انبار) خاموش است.', 'aromist-volume-stock' ) );
		}

		$ml_map  = self::resolve_ml_map( $parent, isset( $args['ml_map'] ) ? (array) $args['ml_map'] : array() );
		$missing = array();
		foreach ( $ml_map as $child_id => $ml ) {
			if ( $ml <= 0 && 'publish' === get_post_status( $child_id ) ) {
				$variation = wc_get_product( $child_id );
				$missing[] = $variation ? wp_strip_all_tags( wc_get_formatted_variation( $variation, true, false ) ) : '#' . $child_id;
			}
		}
		if ( $missing ) {
			return new WP_Error(
				'avs_missing_ml',
				sprintf(
					/* translators: %s: variations */
					__( 'حجم این متغیرها مشخص نیست: %s', 'aromist-volume-stock' ),
					implode( '، ', $missing )
				)
			);
		}
		if ( ! array_filter( $ml_map ) ) {
			return new WP_Error( 'avs_no_variations', __( 'این محصول هیچ متغیری با حجم مشخص ندارد.', 'aromist-volume-stock' ) );
		}

		$was_enabled = AVS_Product::is_enabled( $parent_id );
		$pool_before = true === $parent->get_manage_stock( 'edit' ) ? (int) $parent->get_stock_quantity( 'edit' ) : 0;
		$suggested   = self::suggested_pool( $parent, $ml_map );
		$pool        = isset( $args['pool_ml'] ) && null !== $args['pool_ml'] && '' !== $args['pool_ml']
			? max( 0, (int) $args['pool_ml'] )
			: ( $was_enabled ? $pool_before : $suggested );
		$low         = isset( $args['low_ml'] ) && '' !== $args['low_ml'] ? absint( $args['low_ml'] ) : absint( AVS_Settings::get( 'default_low_ml' ) );
		$waste       = isset( $args['waste_ml'] ) ? absint( $args['waste_ml'] ) : AVS_Product::waste_ml( $parent_id );

		AVS_Stock::mute();

		// 1) Variations: store ml, stop managing their own stock (inherit the parent pool).
		foreach ( $ml_map as $child_id => $ml ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation instanceof WC_Product_Variation ) {
				continue;
			}
			$variation->update_meta_data( AVS_Product::META_ML, (int) $ml );
			if ( true === $variation->get_manage_stock( 'edit' ) ) {
				$variation->set_manage_stock( false );
			}
			$variation->save();
		}

		// 2) Existing orders: WooCommerce's "_reduced_stock" of these items is in units → convert to ml.
		if ( ! $was_enabled ) {
			self::convert_order_items( $parent_id, 'to_ml', $ml_map, $waste );
		}

		// 3) Parent: the stock becomes the pool.
		$parent = wc_get_product( $parent_id );
		$parent->update_meta_data( AVS_Product::META_ENABLED, 'yes' );
		$parent->delete_meta_data( AVS_Product::META_NO_AUTO );
		$parent->update_meta_data( AVS_Product::META_WASTE, $waste );
		$parent->set_manage_stock( true );
		$parent->set_backorders( 'no' );
		$parent->set_low_stock_amount( $low );
		$parent->set_stock_quantity( $pool );
		AVS_Product::reset_cache( $parent_id );
		$parent->save();

		AVS_Stock::unmute();
		AVS_Product::reset_cache( $parent_id );
		AVS_Stock::sync_children_status( $parent_id );

		if ( ! $was_enabled ) {
			AVS_Ledger::add(
				array(
					'product_id' => $parent_id,
					'change_ml'  => $pool,
					'balance_ml' => $pool,
					'reason'     => 'initial',
					'note'       => isset( $args['note'] ) ? $args['note'] : '',
				)
			);
		} elseif ( $pool !== $pool_before ) {
			AVS_Ledger::add(
				array(
					'product_id' => $parent_id,
					'change_ml'  => $pool - $pool_before,
					'balance_ml' => $pool,
					'reason'     => 'manual',
				)
			);
		}

		/**
		 * Fires after volume stock was enabled / re-saved on a product.
		 *
		 * @param int $parent_id Parent id.
		 */
		do_action( 'avs_enabled', $parent_id );

		return true;
	}

	/**
	 * Switch volume stock on in «unlimited» mode: store the ml of each size, count nothing.
	 *
	 * Only post meta is written (no product save, so no other plugin's save hooks run). The parent and
	 * its sizes must not count stock (see AVS_Auto::eligible()).
	 *
	 * @param int   $parent_id Parent id.
	 * @param array $ml_map    variation_id => ml (published sizes).
	 * @return true|WP_Error
	 */
	public static function enable_unlimited( $parent_id, array $ml_map ) {
		$parent_id = absint( $parent_id );
		if ( AVS_Product::is_enabled( $parent_id ) ) {
			return new WP_Error( 'avs_enabled', __( 'موجودی حجمی این محصول از قبل فعال است.', 'aromist-volume-stock' ) );
		}
		if ( ! array_filter( $ml_map ) ) {
			return new WP_Error( 'avs_no_variations', __( 'این محصول هیچ متغیری با حجم مشخص ندارد.', 'aromist-volume-stock' ) );
		}
		foreach ( $ml_map as $child_id => $ml ) {
			if ( (int) $ml > 0 ) {
				update_post_meta( absint( $child_id ), AVS_Product::META_ML, (int) $ml );
			}
		}
		if ( '' === get_post_meta( $parent_id, AVS_Product::META_WASTE, true ) ) {
			update_post_meta( $parent_id, AVS_Product::META_WASTE, 0 );
		}
		update_post_meta( $parent_id, AVS_Product::META_ENABLED, 'yes' );
		AVS_Product::reset_cache( $parent_id );
		wc_delete_product_transients( $parent_id );

		/** This action is documented in includes/class-avs-manager.php */
		do_action( 'avs_enabled', $parent_id );
		return true;
	}

	/**
	 * Set the first pool of an «unlimited» product: from now on its sizes are limited by it.
	 *
	 * @param int    $parent_id Parent id.
	 * @param int    $pool      Ml.
	 * @param string $reason    Ledger reason.
	 * @param string $note      Note.
	 * @return array|WP_Error [ before (null = unlimited), after, change ]
	 */
	public static function set_first_pool( $parent_id, $pool, $reason = 'initial', $note = '' ) {
		$parent = wc_get_product( $parent_id );
		if ( ! $parent instanceof WC_Product_Variable || ! AVS_Product::is_unlimited( $parent_id ) ) {
			return new WP_Error( 'avs_not_unlimited', __( 'این محصول موجودی نامحدود ندارد.', 'aromist-volume-stock' ) );
		}
		$pool = max( 0, (int) $pool );

		AVS_Stock::mute();
		// Sizes take their stock from the parent (none should count its own; guard anyway).
		foreach ( $parent->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( $variation instanceof WC_Product_Variation && true === $variation->get_manage_stock( 'edit' ) ) {
				$variation->set_manage_stock( false );
				$variation->save();
			}
		}
		$parent = wc_get_product( $parent_id );
		$parent->set_manage_stock( true );
		$parent->set_backorders( 'no' );
		if ( '' === (string) $parent->get_low_stock_amount( 'edit' ) ) {
			$parent->set_low_stock_amount( absint( AVS_Settings::get( 'default_low_ml' ) ) );
		}
		$parent->set_stock_quantity( $pool );
		AVS_Product::reset_cache( $parent_id );
		$parent->save();
		AVS_Stock::unmute();

		AVS_Product::reset_cache( $parent_id );
		AVS_Stock::sync_children_status( $parent_id );

		AVS_Ledger::add(
			array(
				'product_id' => $parent_id,
				'change_ml'  => $pool,
				'balance_ml' => $pool,
				'reason'     => $reason,
				'note'       => '' !== $note ? $note : __( 'تعیین موجودی (قبلاً نامحدود)', 'aromist-volume-stock' ),
			)
		);

		/** This action is documented in includes/class-avs-manager.php */
		do_action( 'avs_enabled', $parent_id );

		return array(
			'before' => null,
			'after'  => $pool,
			'change' => $pool,
		);
	}

	/**
	 * Disable volume stock. Each variation gets its own stock again = whole units that fit in the pool
	 * (note: every size then shows the full pool on its own).
	 *
	 * @param int $parent_id Parent id.
	 * @return true|WP_Error
	 */
	public static function disable( $parent_id ) {
		$parent = wc_get_product( $parent_id );
		if ( ! $parent instanceof WC_Product_Variable || ! AVS_Product::is_enabled( $parent_id ) ) {
			return new WP_Error( 'avs_not_enabled', __( 'موجودی حجمی روی این محصول فعال نیست.', 'aromist-volume-stock' ) );
		}

		// The shop turned it off: never switch it on again by itself.
		update_post_meta( $parent_id, AVS_Product::META_NO_AUTO, 'yes' );

		if ( AVS_Product::is_unlimited( $parent_id ) ) {
			// Nothing was counted: only the switch goes (sizes keep selling without stock management).
			delete_post_meta( $parent_id, AVS_Product::META_ENABLED );
			AVS_Product::reset_cache( $parent_id );
			wc_delete_product_transients( $parent_id );
			/** This action is documented in includes/class-avs-manager.php */
			do_action( 'avs_disabled', $parent_id );
			return true;
		}

		$pool   = AVS_Product::pool( $parent_id );
		$waste  = AVS_Product::waste_ml( $parent_id );
		$ml_map = array();

		AVS_Stock::mute();

		// Convert open orders back to units first (needs the ml values and the enabled flag).
		foreach ( $parent->get_children() as $child_id ) {
			$ml_map[ $child_id ] = AVS_Product::variation_ml( $child_id );
		}
		self::convert_order_items( $parent_id, 'to_units', $ml_map, $waste );

		$parent->delete_meta_data( AVS_Product::META_ENABLED );
		$parent->set_manage_stock( false );
		AVS_Product::reset_cache( $parent_id );
		$parent->save();

		$split = array();
		foreach ( $ml_map as $child_id => $ml ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation instanceof WC_Product_Variation ) {
				continue;
			}
			$units = $ml > 0 ? intdiv( max( 0, $pool ), $ml + $waste ) : 0;
			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( $units );
			$variation->save();
			$split[] = AVS_Settings::format_ml( $ml ) . ': ' . number_format_i18n( $units );
		}

		AVS_Stock::unmute();
		AVS_Product::reset_cache( $parent_id );

		AVS_Ledger::add(
			array(
				'product_id' => $parent_id,
				'change_ml'  => 0,
				'balance_ml' => $pool,
				'reason'     => 'disable',
				'note'       => __( 'موجودی تک‌تک متغیرها: ', 'aromist-volume-stock' ) . implode( '، ', $split ),
			)
		);

		/**
		 * Fires after volume stock was disabled on a product.
		 *
		 * @param int $parent_id Parent id.
		 */
		do_action( 'avs_disabled', $parent_id );

		return true;
	}

	/**
	 * Convert `_reduced_stock` of existing order items of a product between units and ml,
	 * so that later cancellations / refunds give back the right amount.
	 *
	 * @param int    $parent_id Parent id.
	 * @param string $direction 'to_ml' or 'to_units'.
	 * @param array  $ml_map    variation_id => ml.
	 * @param int    $waste     Waste ml per unit.
	 * @return int Items converted.
	 */
	private static function convert_order_items( $parent_id, $direction, array $ml_map, $waste ) {
		global $wpdb;

		$items_table = $wpdb->prefix . 'woocommerce_order_items';
		$meta_table  = $wpdb->prefix . 'woocommerce_order_itemmeta';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$item_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT oi.order_item_id
				FROM {$items_table} oi
				INNER JOIN {$meta_table} p ON p.order_item_id = oi.order_item_id AND p.meta_key = '_product_id' AND p.meta_value = %s
				INNER JOIN {$meta_table} r ON r.order_item_id = oi.order_item_id AND r.meta_key = '_reduced_stock'
				WHERE oi.order_item_type = 'line_item' AND r.meta_value <> '' AND r.meta_value <> '0'",
				(string) absint( $parent_id )
			)
		);
		// phpcs:enable

		$count = 0;
		foreach ( (array) $item_ids as $item_id ) {
			$item = WC_Order_Factory::get_order_item( absint( $item_id ) );
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$variation_id = $item->get_variation_id();
			$reduced      = (int) wc_stock_amount( $item->get_meta( '_reduced_stock', true ) );

			if ( 'to_ml' === $direction ) {
				if ( absint( $item->get_meta( AVS_Order_Item::META_ML, true ) ) > 0 ) {
					continue; // Already in ml.
				}
				$ml = isset( $ml_map[ $variation_id ] ) ? absint( $ml_map[ $variation_id ] ) : 0;
				if ( $ml <= 0 ) {
					continue;
				}
				$item->update_meta_data( AVS_Order_Item::META_ML, $ml );
				$item->update_meta_data( AVS_Order_Item::META_WASTE, $waste );
				$item->update_meta_data( '_reduced_stock', $reduced * ( $ml + $waste ) );
				$item->update_meta_data( AVS_Order_Item::META_CONVERTED, 1 );
			} else {
				$ml    = absint( $item->get_meta( AVS_Order_Item::META_ML, true ) );
				$w     = absint( $item->get_meta( AVS_Order_Item::META_WASTE, true ) );
				$unit  = $ml + $w;
				if ( $ml <= 0 || $unit <= 0 ) {
					continue;
				}
				$item->update_meta_data( '_reduced_stock', (int) round( $reduced / $unit ) );
				$item->delete_meta_data( AVS_Order_Item::META_ML );
				$item->delete_meta_data( AVS_Order_Item::META_WASTE );
				$item->delete_meta_data( AVS_Order_Item::META_CONVERTED );
				$item->update_meta_data( '_avs_ml_history', $ml );
			}
			$item->save();
			++$count;
		}

		return $count;
	}

	/**
	 * Manual pool change from the panel.
	 *
	 * @param int    $parent_id Parent id.
	 * @param string $operation add | remove | set.
	 * @param int    $amount    Ml (>= 0).
	 * @param string $reason    Reason key.
	 * @param string $note      Note.
	 * @return array|WP_Error [ before, after, change ]
	 */
	public static function adjust( $parent_id, $operation, $amount, $reason, $note = '' ) {
		$parent = wc_get_product( $parent_id );
		if ( ! $parent instanceof WC_Product_Variable || ! AVS_Product::is_enabled( $parent_id ) ) {
			return new WP_Error( 'avs_not_enabled', __( 'موجودی حجمی روی این محصول فعال نیست.', 'aromist-volume-stock' ) );
		}

		$amount = absint( $amount );
		if ( ! array_key_exists( $reason, self::manual_reasons() ) ) {
			$reason = 'manual';
		}

		// «Unlimited»: «افزایش» or «تنظیم مقدار دقیق» sets the first pool; nothing can be taken away yet.
		if ( AVS_Product::is_unlimited( $parent_id ) ) {
			if ( 'remove' === $operation ) {
				return new WP_Error( 'avs_unlimited', __( 'موجودی این محصول نامحدود است؛ اول مقدار موجودی مخزن را تعیین کنید.', 'aromist-volume-stock' ) );
			}
			if ( ! in_array( $operation, array( 'add', 'set' ), true ) ) {
				return new WP_Error( 'avs_operation', __( 'نوع عملیات نامعتبر است.', 'aromist-volume-stock' ) );
			}
			return self::set_first_pool( $parent_id, $amount, 'manual' === $reason ? 'initial' : $reason, $note );
		}
		if ( 'set' !== $operation && $amount <= 0 ) {
			return new WP_Error( 'avs_amount', __( 'مقدار باید بیشتر از صفر باشد.', 'aromist-volume-stock' ) );
		}

		$before = AVS_Product::pool( $parent_id );

		if ( 'remove' === $operation && $amount > $before ) {
			return new WP_Error(
				'avs_amount',
				sprintf(
					/* translators: %s: pool */
					__( 'مقدار کسر از موجودی فعلی (%s) بیشتر است.', 'aromist-volume-stock' ),
					AVS_Settings::format_ml( $before )
				)
			);
		}

		AVS_Stock::mute();
		switch ( $operation ) {
			case 'add':
				$after = wc_update_product_stock( $parent, $amount, 'increase' );
				break;
			case 'remove':
				$after = wc_update_product_stock( $parent, $amount, 'decrease' );
				break;
			case 'set':
				$after = wc_update_product_stock( $parent, $amount, 'set' );
				break;
			default:
				AVS_Stock::unmute();
				return new WP_Error( 'avs_operation', __( 'نوع عملیات نامعتبر است.', 'aromist-volume-stock' ) );
		}
		AVS_Stock::unmute();

		if ( is_wp_error( $after ) || false === $after || null === $after ) {
			return new WP_Error( 'avs_failed', __( 'به‌روزرسانی موجودی انجام نشد.', 'aromist-volume-stock' ) );
		}

		$after  = (int) $after;
		$change = 'set' === $operation ? $after - $before : ( 'add' === $operation ? $amount : -$amount );

		AVS_Ledger::add(
			array(
				'product_id' => $parent_id,
				'change_ml'  => $change,
				'balance_ml' => $after,
				'reason'     => $reason,
				'note'       => $note,
			)
		);

		return array(
			'before' => $before,
			'after'  => $after,
			'change' => $change,
		);
	}

	/**
	 * Update ml per variation, waste and low stock threshold of an enabled product.
	 *
	 * @param int   $parent_id Parent id.
	 * @param array $ml_map    variation_id => ml.
	 * @param int   $waste_ml  Waste per unit.
	 * @param int   $low_ml    Low threshold.
	 * @return true|WP_Error
	 */
	public static function update_settings( $parent_id, array $ml_map, $waste_ml, $low_ml ) {
		$parent = wc_get_product( $parent_id );
		if ( ! $parent instanceof WC_Product_Variable || ! AVS_Product::is_enabled( $parent_id ) ) {
			return new WP_Error( 'avs_not_enabled', __( 'موجودی حجمی روی این محصول فعال نیست.', 'aromist-volume-stock' ) );
		}

		$children = array_map( 'absint', $parent->get_children() );
		foreach ( $ml_map as $child_id => $raw ) {
			$child_id = absint( $child_id );
			if ( ! in_array( $child_id, $children, true ) ) {
				continue;
			}
			$ml = AVS_Product::parse_ml( $raw );
			if ( $ml <= 0 && 'publish' === get_post_status( $child_id ) ) {
				return new WP_Error( 'avs_missing_ml', __( 'حجم همه متغیرهای فعال باید بیشتر از صفر باشد.', 'aromist-volume-stock' ) );
			}
			update_post_meta( $child_id, AVS_Product::META_ML, $ml );
			wc_delete_product_transients( $child_id );
		}

		AVS_Stock::mute();
		$parent->update_meta_data( AVS_Product::META_WASTE, absint( $waste_ml ) );
		$parent->set_low_stock_amount( absint( $low_ml ) );
		$parent->save();
		AVS_Stock::unmute();

		AVS_Stock::sync_children_status( $parent_id );
		return true;
	}
}
