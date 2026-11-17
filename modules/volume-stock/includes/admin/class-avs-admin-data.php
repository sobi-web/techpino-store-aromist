<?php
/**
 * Data preparation for the admin views.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin view models.
 */
final class AVS_Admin_Data {

	/**
	 * Status labels.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'ok'        => __( 'کافی', 'aromist-volume-stock' ),
			'low'       => __( 'رو به اتمام', 'aromist-volume-stock' ),
			'out'       => __( 'تمام‌شده', 'aromist-volume-stock' ),
			'unlimited' => __( 'نامحدود', 'aromist-volume-stock' ),
		);
	}

	/**
	 * Low threshold of a product (ml).
	 *
	 * @param WC_Product $product Parent.
	 * @return int
	 */
	public static function low_threshold( $product ) {
		$low = $product->get_low_stock_amount( 'edit' );
		return '' === $low || null === $low ? absint( AVS_Settings::get( 'default_low_ml' ) ) : absint( $low );
	}

	/**
	 * Sizes of a product.
	 *
	 * @param WC_Product_Variable $product Parent.
	 * @param int                 $pool    Pool.
	 * @return array
	 */
	public static function sizes( $product, $pool ) {
		$sizes = array();
		$waste = AVS_Product::waste_ml( $product->get_id() );
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation instanceof WC_Product_Variation ) {
				continue;
			}
			$ml      = AVS_Product::variation_ml( $child_id );
			$unit    = $ml > 0 ? $ml + $waste : 0;
			$sizes[] = array(
				'id'        => $child_id,
				'label'     => wp_strip_all_tags( wc_get_formatted_variation( $variation, true, false ) ),
				'ml'        => $ml,
				'unit'      => $unit,
				'units'     => $unit > 0 ? intdiv( max( 0, $pool ), $unit ) : 0,
				'published' => 'publish' === $variation->get_status(),
				'price'     => $variation->get_price(),
				'own_stock' => true === $variation->get_manage_stock( 'edit' ),
			);
		}
		usort(
			$sizes,
			static function ( $a, $b ) {
				return $a['ml'] <=> $b['ml'];
			}
		);
		return $sizes;
	}

	/**
	 * Row model for one enabled product.
	 *
	 * @param int   $product_id Parent id.
	 * @param array $consumed   product_id => ml consumed by orders in the last 30 days.
	 * @return array|null
	 */
	public static function product_row( $product_id, array $consumed = array() ) {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product_Variable ) {
			return null;
		}

		$pool   = AVS_Product::pool( $product_id );
		$min    = AVS_Product::min_unit_ml( $product_id );
		$low    = self::low_threshold( $product );
		$sold30 = isset( $consumed[ $product_id ] ) ? max( 0, (int) $consumed[ $product_id ] ) : 0;
		$avg    = $sold30 / 30;

		$unlimited = AVS_Product::is_unlimited( $product_id );
		if ( $unlimited ) {
			$status = 'unlimited';
			$pool   = 0;
		} elseif ( $min > 0 && $pool < $min ) {
			$status = 'out';
		} elseif ( $pool <= $low ) {
			$status = 'low';
		} else {
			$status = 'ok';
		}

		return array(
			'id'         => $product_id,
			'product'    => $product,
			'name'       => $product->get_name(),
			'sku'        => $product->get_sku(),
			'pool'       => $pool,
			'unlimited'  => $unlimited,
			'min'        => $min,
			'low'        => $low,
			'status'     => $status,
			'sold30'     => $sold30,
			'avg_daily'  => $avg,
			'days_left'  => ! $unlimited && $avg > 0 ? (int) floor( max( 0, $pool ) / $avg ) : null,
			'waste'      => AVS_Product::waste_ml( $product_id ),
			'edit_url'   => get_edit_post_link( $product_id, 'raw' ),
			'view_url'   => get_permalink( $product_id ),
			'detail_url' => AVS_Admin::url( 'avs', array( 'product_id' => $product_id ) ),
		);
	}

	/**
	 * Load all variations (posts + meta) of these parents in two queries instead of one per variation.
	 *
	 * @param int[] $parent_ids Parent ids.
	 */
	public static function prime( array $parent_ids ) {
		global $wpdb;

		$parent_ids = array_filter( array_map( 'absint', $parent_ids ) );
		if ( ! $parent_ids ) {
			return;
		}
		update_meta_cache( 'post', $parent_ids );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$children = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product_variation' AND post_parent IN (" . implode( ',', array_fill( 0, count( $parent_ids ), '%d' ) ) . ')',
				$parent_ids
			)
		);
		// phpcs:enable

		if ( $children ) {
			_prime_post_caches( array_map( 'absint', $children ), false, true );
		}
	}

	/**
	 * Ml consumed by orders per product in the last N days.
	 *
	 * @param int $days Days.
	 * @return array product_id => ml
	 */
	public static function consumption( $days = 30 ) {
		$to     = gmdate( 'Y-m-d H:i:s', time() + MINUTE_IN_SECONDS );
		$from   = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$totals = AVS_Ledger::totals_by_product( $from, $to );
		$out    = array();
		foreach ( $totals as $product_id => $reasons ) {
			$sum = 0;
			foreach ( AVS_Ledger::order_reasons() as $reason ) {
				$sum += isset( $reasons[ $reason ] ) ? (int) $reasons[ $reason ] : 0;
			}
			$out[ $product_id ] = -1 * $sum;
		}
		return $out;
	}

	/**
	 * Width (%) of the pool bar.
	 *
	 * @param array $row Row.
	 * @return int
	 */
	public static function bar_percent( array $row ) {
		$scale = max( $row['low'] * 4, $row['min'] * 10, 1 );
		return (int) max( 2, min( 100, round( $row['pool'] / $scale * 100 ) ) );
	}

	/**
	 * Human forecast.
	 *
	 * @param int|null $days Days left.
	 * @return string
	 */
	public static function forecast( $days ) {
		if ( null === $days ) {
			return '—';
		}
		if ( $days < 1 ) {
			return __( 'کمتر از یک روز', 'aromist-volume-stock' );
		}
		/* translators: %s: days */
		return sprintf( __( '~%s روز', 'aromist-volume-stock' ), number_format_i18n( $days ) );
	}
}
