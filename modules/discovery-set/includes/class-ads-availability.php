<?php
/**
 * Stock available to sets.
 *
 * Every product a set draws from (perfume parents with a volume pool, box products) keeps its
 * amount in WooCommerce's own `_stock`: ml for pools, units for boxes. "Available" = stock minus
 * what other customers' pending checkouts hold (WooCommerce reserved-stock table).
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Availability helpers.
 */
final class ADS_Availability {

	/**
	 * Does this product limit what can be sold (manages stock, no backorders)?
	 *
	 * @param int $product_id Id.
	 * @return bool
	 */
	public static function is_limited( $product_id ) {
		if ( 'yes' !== get_option( 'woocommerce_manage_stock' ) ) {
			return false;
		}
		return 'yes' === get_post_meta( $product_id, '_manage_stock', true ) && 'no' === ( get_post_meta( $product_id, '_backorders', true ) ?: 'no' );
	}

	/**
	 * Stock of a product (ml for pools, units for boxes).
	 *
	 * @param int $product_id Id.
	 * @return int
	 */
	public static function stock( $product_id ) {
		return (int) wc_stock_amount( get_post_meta( $product_id, '_stock', true ) );
	}

	/**
	 * Amount held by other customers' pending checkouts.
	 *
	 * @param int $product_id       Id.
	 * @param int $exclude_order_id The current customer's own pending order.
	 * @return int
	 */
	public static function held( $product_id, $exclude_order_id = 0 ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return 0;
		}
		return max( 0, (int) wc_get_held_stock_quantity( $product, (int) $exclude_order_id ) );
	}

	/**
	 * Amount that can still be sold.
	 *
	 * @param int  $product_id       Id.
	 * @param int  $exclude_order_id Own pending order.
	 * @param bool $with_held        Subtract other checkouts' holds.
	 * @return int|null null = unlimited.
	 */
	public static function available( $product_id, $exclude_order_id = 0, $with_held = true ) {
		if ( ! self::is_limited( $product_id ) ) {
			// No pool counted («unlimited» volume stock): no limit — unless the shop marked the perfume
			// «ناموجود» by hand (all of its sizes out of stock).
			return 'outofstock' === get_post_meta( $product_id, '_stock_status', true ) && ! ADS_Boxes::is_box( $product_id ) ? 0 : null;
		}
		$amount = self::stock( $product_id );
		if ( $with_held ) {
			$amount -= self::held( $product_id, $exclude_order_id );
		}
		return $amount;
	}

	/**
	 * The current customer's own pending order (its holds never count against them).
	 *
	 * @return int
	 */
	public static function session_order_id() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return 0;
		}
		$id = absint( WC()->session->get( 'order_awaiting_payment', 0 ) );
		return $id ? $id : absint( WC()->session->get( 'store_api_draft_order', 0 ) );
	}

	/**
	 * What does not fit when `$count` sets are added on top of `$already` (amounts already
	 * needed by the cart, product id => amount)?
	 *
	 * @param array $draws     ADS_Set::draws().
	 * @param int   $count     Number of sets.
	 * @param array $already   Product id => amount already needed elsewhere.
	 * @param bool  $with_held Subtract other checkouts' holds.
	 * @return array product id => [ 'need' => int, 'have' => int ]. Empty = fits.
	 */
	public static function shortages( array $draws, $count = 1, array $already = array(), $with_held = true ) {
		$exclude = $with_held ? self::session_order_id() : 0;
		$need    = array();
		foreach ( $draws as $draw ) {
			$need[ $draw['p'] ] = ( isset( $need[ $draw['p'] ] ) ? $need[ $draw['p'] ] : 0 ) + (int) $draw['a'] * (int) $count;
		}

		$short = array();
		foreach ( $need as $id => $amount ) {
			$have = self::available( $id, $exclude, $with_held );
			if ( null === $have ) {
				continue;
			}
			$total = $amount + ( isset( $already[ $id ] ) ? (int) $already[ $id ] : 0 );
			if ( $total > $have ) {
				$short[ $id ] = array(
					'need' => $total,
					'have' => max( 0, $have ),
				);
			}
		}
		return $short;
	}

	/**
	 * How many sets still fit (null = unlimited).
	 *
	 * @param array $draws     ADS_Set::draws().
	 * @param array $already   Product id => amount already needed elsewhere.
	 * @param bool  $with_held Subtract other checkouts' holds.
	 * @return int|null
	 */
	public static function max_sets( array $draws, array $already = array(), $with_held = true ) {
		$exclude = $with_held ? self::session_order_id() : 0;
		$per_set = array();
		foreach ( $draws as $draw ) {
			$per_set[ $draw['p'] ] = ( isset( $per_set[ $draw['p'] ] ) ? $per_set[ $draw['p'] ] : 0 ) + (int) $draw['a'];
		}

		$max = null;
		foreach ( $per_set as $id => $amount ) {
			$have = self::available( $id, $exclude, $with_held );
			if ( null === $have || $amount <= 0 ) {
				continue;
			}
			$room = $have - ( isset( $already[ $id ] ) ? (int) $already[ $id ] : 0 );
			$fits = max( 0, intdiv( max( 0, $room ), $amount ) );
			$max  = null === $max ? $fits : min( $max, $fits );
		}
		return $max;
	}

	/**
	 * Perfume with volume stock but no pool set yet (sold without a limit).
	 *
	 * @param int $product_id Id.
	 * @return bool
	 */
	public static function is_unlimited_perfume( $product_id ) {
		return AVS_Product::is_enabled( $product_id ) && 'yes' !== get_post_meta( $product_id, '_manage_stock', true );
	}

	/**
	 * Human text for a shortage.
	 *
	 * @param int   $product_id Id.
	 * @param array $shortage   [ need, have ].
	 * @return string
	 */
	public static function shortage_message( $product_id, array $shortage ) {
		if ( ADS_Boxes::is_box( $product_id ) ) {
			return sprintf(
				/* translators: 1: box name, 2: available */
				__( 'جعبه‌ی «%1$s» کافی نیست (موجود: %2$s عدد).', 'aromist-discovery-set' ),
				ADS_Boxes::name( $product_id ),
				number_format_i18n( $shortage['have'] )
			);
		}
		if ( self::is_unlimited_perfume( $product_id ) ) {
			/* translators: %s: perfume */
			return sprintf( __( '«%s» در حال حاضر ناموجود است.', 'aromist-discovery-set' ), ADS_Set::perfume_name( $product_id ) );
		}
		return sprintf(
			/* translators: 1: perfume, 2: needed, 3: available */
			__( 'موجودی «%1$s» کافی نیست: %2$s لازم است ولی فقط %3$s موجود است.', 'aromist-discovery-set' ),
			ADS_Set::perfume_name( $product_id ),
			ads_format_ml( $shortage['need'] ),
			ads_format_ml( $shortage['have'] )
		);
	}

	/**
	 * Which volumes of a set can be sold right now (one set, nothing else in the cart).
	 *
	 * @param int[] $components Perfume ids.
	 * @param int[] $volumes    Volumes.
	 * @param bool  $with_held  Subtract other checkouts' holds.
	 * @return array ml => bool
	 */
	public static function volumes( array $components, array $volumes, $with_held = true ) {
		$out = array();
		foreach ( $volumes as $ml ) {
			$out[ (int) $ml ] = ! self::shortages( ADS_Set::draws( $components, $ml ), 1, array(), $with_held );
		}
		return $out;
	}
}
