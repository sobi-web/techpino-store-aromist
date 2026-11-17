<?php
/**
 * Reports built from the ledger: consumption, restocks, waste, forecasts.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

/**
 * Report calculations.
 */
final class AVS_Reports {

	/**
	 * Date range from the request (site timezone), as local dates and UTC bounds.
	 *
	 * @param string $default_preset Preset when nothing is posted.
	 * @return array { preset, from, to, from_utc, to_utc, days }
	 */
	public static function range_from_request( $default_preset = '30' ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only report filters.
		$preset = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : $default_preset;
		$from   = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$to     = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		// phpcs:enable

		$tz    = wp_timezone();
		$today = new DateTimeImmutable( 'today', $tz );

		if ( 'custom' === $preset && self::valid_date( $from ) && self::valid_date( $to ) ) {
			$start = new DateTimeImmutable( $from, $tz );
			$end   = new DateTimeImmutable( $to, $tz );
			if ( $end < $start ) {
				list( $start, $end ) = array( $end, $start );
			}
		} else {
			$days = in_array( $preset, array( '7', '30', '90', '365' ), true ) ? (int) $preset : 30;
			if ( 'month' === $preset ) {
				$start = $today->modify( 'first day of this month' );
			} else {
				$preset = (string) $days;
				$start  = $today->modify( '-' . ( $days - 1 ) . ' days' );
			}
			$end = $today;
		}

		$end_exclusive = $end->modify( '+1 day' );
		$utc           = new DateTimeZone( 'UTC' );

		return array(
			'preset'   => $preset,
			'from'     => $start->format( 'Y-m-d' ),
			'to'       => $end->format( 'Y-m-d' ),
			'from_utc' => $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			'to_utc'   => $end_exclusive->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			'days'     => max( 1, (int) $start->diff( $end_exclusive )->days ),
		);
	}

	/**
	 * Is YYYY-MM-DD?
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	private static function valid_date( $date ) {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && false !== strtotime( $date );
	}

	/**
	 * Ids of products with volume stock enabled.
	 *
	 * @return int[]
	 */
	public static function enabled_product_ids() {
		return array_map(
			'absint',
			get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_key'       => AVS_Product::META_ENABLED, // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'     => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery
					'no_found_rows'  => true,
				)
			)
		);
	}

	/**
	 * Summary numbers of one product in a period, from ledger totals.
	 *
	 * @param array $totals reason => sum_ml for the product.
	 * @param int   $pool   Current pool.
	 * @param int   $days   Days in the period.
	 * @return array
	 */
	public static function summarize( array $totals, $pool, $days ) {
		$get = static function ( $key ) use ( $totals ) {
			return isset( $totals[ $key ] ) ? (int) $totals[ $key ] : 0;
		};

		// Orders: reduce is negative, restore / refund positive → net consumption.
		$sold_ml = -1 * ( $get( 'order_reduce' ) + $get( 'order_restore' ) + $get( 'refund_restock' ) + $get( 'order_edit' ) );
		$in_ml   = $get( 'restock' ) + max( 0, $get( 'initial' ) );
		$waste   = -1 * $get( 'waste' );
		$fixes   = $get( 'count_fix' ) + $get( 'manual' ) + $get( 'product_edit' ) + $get( 'legacy_order' );
		$avg     = $days > 0 ? $sold_ml / $days : 0;

		return array(
			'sold_ml'   => $sold_ml,
			'in_ml'     => $in_ml,
			'waste_ml'  => $waste,
			'fixes_ml'  => $fixes,
			'avg_daily' => $avg,
			'days_left' => $avg > 0 ? (int) floor( max( 0, $pool ) / $avg ) : null,
		);
	}

	/**
	 * Report rows for all enabled products (plus products that had movements in the period).
	 *
	 * @param array $range Range from range_from_request().
	 * @return array
	 */
	public static function rows( array $range ) {
		$totals = AVS_Ledger::totals_by_product( $range['from_utc'], $range['to_utc'] );
		$ids    = array_unique( array_merge( self::enabled_product_ids(), array_keys( $totals ) ) );
		$units  = self::units_by_product( $range );

		$rows = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			$pool    = AVS_Product::pool( $id );
			$sum     = self::summarize( isset( $totals[ $id ] ) ? $totals[ $id ] : array(), $pool, $range['days'] );

			$rows[] = array_merge(
				array(
					'product_id' => $id,
					'name'       => $product ? $product->get_name() : sprintf( '#%d', $id ),
					'sku'        => $product ? $product->get_sku() : '',
					'enabled'    => AVS_Product::is_enabled( $id ),
					'pool'       => $pool,
					'units'      => isset( $units[ $id ] ) ? $units[ $id ] : 0,
				),
				$sum
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return $b['sold_ml'] <=> $a['sold_ml'];
			}
		);

		return $rows;
	}

	/**
	 * Net units sold per product in the period (from order ledger rows).
	 *
	 * @param array $range Range.
	 * @return array product_id => units
	 */
	private static function units_by_product( array $range ) {
		global $wpdb;

		$table   = AVS_Install::table();
		$reasons = AVS_Ledger::order_reasons();
		$params  = array_merge( array( $range['from_utc'], $range['to_utc'] ), $reasons );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, SUM(quantity) AS units FROM {$table}
				WHERE created_at >= %s AND created_at < %s AND reason IN (" . implode( ',', array_fill( 0, count( $reasons ), '%s' ) ) . ')
				GROUP BY product_id',
				$params
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['product_id'] ] = (int) $row['units'];
		}
		return $out;
	}

	/**
	 * Per-variation breakdown of one product.
	 *
	 * @param int   $product_id Parent id.
	 * @param array $range      Range.
	 * @return array
	 */
	public static function variation_rows( $product_id, array $range ) {
		$totals = AVS_Ledger::order_totals_by_variation( $range['from_utc'], $range['to_utc'], $product_id );
		$ids    = array_unique( array_merge( AVS_Product::children_ids( $product_id ), array_keys( $totals ) ) );

		$rows = array();
		foreach ( $ids as $variation_id ) {
			if ( ! $variation_id ) {
				continue;
			}
			$variation = wc_get_product( $variation_id );
			$rows[]    = array(
				'variation_id' => $variation_id,
				'name'         => $variation instanceof WC_Product_Variation ? wp_strip_all_tags( wc_get_formatted_variation( $variation, true, false ) ) : sprintf( '#%d', $variation_id ),
				'ml'           => AVS_Product::variation_ml( $variation_id ),
				'units'        => isset( $totals[ $variation_id ] ) ? $totals[ $variation_id ]['units'] : 0,
				'sold_ml'      => isset( $totals[ $variation_id ] ) ? -1 * $totals[ $variation_id ]['ml'] : 0,
			);
		}
		return $rows;
	}

	/**
	 * Send rows as a CSV download (UTF-8 with BOM for Excel) and exit.
	 *
	 * @param string $filename File name.
	 * @param array  $header   Header row.
	 * @param array  $rows     Rows (arrays of scalars).
	 */
	public static function send_csv( $filename, array $header, array $rows ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $out, array_map( array( __CLASS__, 'csv_cell' ), $header ) );
		foreach ( $rows as $row ) {
			fputcsv( $out, array_map( array( __CLASS__, 'csv_cell' ), $row ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Neutralise spreadsheet formulas (CSV injection) in text cells.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function csv_cell( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}
		$value = wp_strip_all_tags( (string) $value );
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) && ! is_numeric( $value ) ) {
			$value = "'" . $value;
		}
		return $value;
	}
}
