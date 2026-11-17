<?php
/**
 * Stock ledger: an append-only history of every change to a volume pool.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ledger read/write.
 */
final class AVS_Ledger {

	/**
	 * Reasons and their labels.
	 *
	 * @return array
	 */
	public static function reasons() {
		return array(
			'initial'        => __( 'موجودی اولیه (فعال‌سازی)', 'aromist-volume-stock' ),
			'order_reduce'   => __( 'کسر برای سفارش', 'aromist-volume-stock' ),
			'order_restore'  => __( 'بازگشت از سفارش (لغو / ناموفق)', 'aromist-volume-stock' ),
			'refund_restock' => __( 'بازگشت از مرجوعی', 'aromist-volume-stock' ),
			'order_edit'     => __( 'ویرایش سفارش در پیشخوان', 'aromist-volume-stock' ),
			'restock'        => __( 'ورود موجودی جدید', 'aromist-volume-stock' ),
			'waste'          => __( 'هدررفت / شکستگی', 'aromist-volume-stock' ),
			'count_fix'      => __( 'اصلاح شمارش انبار', 'aromist-volume-stock' ),
			'manual'         => __( 'تغییر دستی', 'aromist-volume-stock' ),
			'product_edit'   => __( 'تغییر از ویرایش محصول / API', 'aromist-volume-stock' ),
			'disable'        => __( 'غیرفعال‌سازی موجودی حجمی', 'aromist-volume-stock' ),
			'legacy_order'   => __( 'سفارش ثبت‌شده قبل از فعال‌سازی (بازگشت / ویرایش)', 'aromist-volume-stock' ),
		);
	}

	/**
	 * Reasons that come from customer orders.
	 *
	 * @return string[]
	 */
	public static function order_reasons() {
		return array( 'order_reduce', 'order_restore', 'refund_restock', 'order_edit' );
	}

	/**
	 * Label of a reason.
	 *
	 * @param string $reason Reason key.
	 * @return string
	 */
	public static function reason_label( $reason ) {
		$reasons = self::reasons();
		return isset( $reasons[ $reason ] ) ? $reasons[ $reason ] : $reason;
	}

	/**
	 * Add a ledger row.
	 *
	 * @param array $args {
	 *     @type int    $product_id    Parent product id (required).
	 *     @type int    $variation_id  Variation id.
	 *     @type int    $order_id      Order id.
	 *     @type int    $order_item_id Order item id.
	 *     @type int    $quantity      Units of the variation (when relevant).
	 *     @type int    $change_ml     Signed change in ml (required).
	 *     @type int    $balance_ml    Pool after the change. Read from the DB when omitted.
	 *     @type string $reason        Reason key (required).
	 *     @type string $note          Free text.
	 *     @type int    $user_id       User id. Current user when omitted.
	 * }
	 * @return int|false Row id.
	 */
	public static function add( array $args ) {
		global $wpdb;

		$product_id = absint( isset( $args['product_id'] ) ? $args['product_id'] : 0 );
		if ( ! $product_id || ! isset( $args['change_ml'] ) ) {
			return false;
		}

		$balance = array_key_exists( 'balance_ml', $args ) && null !== $args['balance_ml']
			? (int) $args['balance_ml']
			: AVS_Product::pool( $product_id );

		$row = array(
			'product_id'    => $product_id,
			'variation_id'  => absint( isset( $args['variation_id'] ) ? $args['variation_id'] : 0 ),
			'order_id'      => absint( isset( $args['order_id'] ) ? $args['order_id'] : 0 ),
			'order_item_id' => absint( isset( $args['order_item_id'] ) ? $args['order_item_id'] : 0 ),
			'quantity'      => (int) ( isset( $args['quantity'] ) ? $args['quantity'] : 0 ),
			'change_ml'     => (int) $args['change_ml'],
			'balance_ml'    => $balance,
			'reason'        => sanitize_key( isset( $args['reason'] ) ? $args['reason'] : 'manual' ),
			'note'          => isset( $args['note'] ) ? sanitize_textarea_field( $args['note'] ) : '',
			'user_id'       => isset( $args['user_id'] ) ? absint( $args['user_id'] ) : get_current_user_id(),
			'created_at'    => current_time( 'mysql', true ),
		);

		$ok = $wpdb->insert(
			AVS_Install::table(),
			$row,
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( ! $ok ) {
			return false;
		}

		$id = (int) $wpdb->insert_id;

		/**
		 * Fires after a volume stock movement is recorded.
		 *
		 * @param int   $id  Ledger row id.
		 * @param array $row Row data.
		 */
		do_action( 'avs_ledger_added', $id, $row );

		return $id;
	}

	/**
	 * Build the WHERE clause for queries.
	 *
	 * @param array $args Filters.
	 * @return array [ sql, params ]
	 */
	private static function where( array $args ) {
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['product_id'] ) ) {
			$where[]  = 'product_id = %d';
			$params[] = absint( $args['product_id'] );
		}
		if ( ! empty( $args['order_id'] ) ) {
			$where[]  = 'order_id = %d';
			$params[] = absint( $args['order_id'] );
		}
		if ( ! empty( $args['reason'] ) ) {
			$reasons = array_filter( array_map( 'sanitize_key', (array) $args['reason'] ) );
			if ( $reasons ) {
				$where[] = 'reason IN (' . implode( ',', array_fill( 0, count( $reasons ), '%s' ) ) . ')';
				$params  = array_merge( $params, $reasons );
			}
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $args['date_from'];
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at < %s';
			$params[] = $args['date_to'];
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * Query rows (newest first).
	 *
	 * @param array $args Filters + per_page, page.
	 * @return array
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		list( $where, $params ) = self::where( $args );

		$per_page = isset( $args['per_page'] ) ? max( 1, min( 5000, absint( $args['per_page'] ) ) ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$params[] = $per_page;
		$params[] = ( $page - 1 ) * $per_page;

		$table = AVS_Install::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $params );

		return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Count rows.
	 *
	 * @param array $args Filters.
	 * @return int
	 */
	public static function count( array $args = array() ) {
		global $wpdb;

		list( $where, $params ) = self::where( $args );
		$table                  = AVS_Install::table();
		$sql                    = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Sum of change_ml grouped by product and reason in a period.
	 *
	 * @param string   $from        UTC datetime (inclusive).
	 * @param string   $to          UTC datetime (exclusive).
	 * @param int[]    $product_ids Optional product ids.
	 * @return array product_id => [ reason => sum_ml ]
	 */
	public static function totals_by_product( $from, $to, array $product_ids = array() ) {
		global $wpdb;

		$table  = AVS_Install::table();
		$where  = 'created_at >= %s AND created_at < %s';
		$params = array( $from, $to );

		$product_ids = array_filter( array_map( 'absint', $product_ids ) );
		if ( $product_ids ) {
			$where .= ' AND product_id IN (' . implode( ',', array_fill( 0, count( $product_ids ), '%d' ) ) . ')';
			$params = array_merge( $params, $product_ids );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql  = $wpdb->prepare( "SELECT product_id, reason, SUM(change_ml) AS ml FROM {$table} WHERE {$where} GROUP BY product_id, reason", $params );
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['product_id'] ][ $row['reason'] ] = (int) $row['ml'];
		}
		return $out;
	}

	/**
	 * Net units / ml per variation from order movements in a period.
	 *
	 * @param string $from       UTC datetime.
	 * @param string $to         UTC datetime.
	 * @param int    $product_id Parent id (0 = all).
	 * @return array variation_id => [ units, ml ]
	 */
	public static function order_totals_by_variation( $from, $to, $product_id = 0 ) {
		global $wpdb;

		$table   = AVS_Install::table();
		$reasons = self::order_reasons();
		$where   = 'created_at >= %s AND created_at < %s AND reason IN (' . implode( ',', array_fill( 0, count( $reasons ), '%s' ) ) . ')';
		$params  = array_merge( array( $from, $to ), $reasons );

		if ( $product_id ) {
			$where   .= ' AND product_id = %d';
			$params[] = absint( $product_id );
		}

		// Units: sold units are stored positive on reduce rows and negative on restore rows.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql  = $wpdb->prepare( "SELECT variation_id, SUM(quantity) AS units, SUM(change_ml) AS ml FROM {$table} WHERE {$where} GROUP BY variation_id", $params );
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['variation_id'] ] = array(
				'units' => (int) $row['units'],
				'ml'    => (int) $row['ml'],
			);
		}
		return $out;
	}

	/**
	 * Delete all rows of a product (used when a product is deleted permanently).
	 *
	 * @param int $product_id Product id.
	 */
	public static function delete_product( $product_id ) {
		global $wpdb;
		$wpdb->delete( AVS_Install::table(), array( 'product_id' => absint( $product_id ) ), array( '%d' ) );
	}
}
