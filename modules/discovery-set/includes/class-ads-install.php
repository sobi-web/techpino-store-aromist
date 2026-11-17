<?php
/**
 * Activation and one-time setup.
 *
 * Products of the new type cannot be created inside the activation hook (the plugin's classes
 * load on `plugins_loaded`, which already ran), so activation only flags the setup and
 * `maybe_setup()` creates the category, the default box and the hidden «ست سفارشی» product on
 * the next `init`.
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Install.
 */
final class ADS_Install {

	const SETUP_OPTION  = 'ads_setup_version';
	const FAILED_OPTION = 'ads_setup_failed';
	const CRON_HOOK    = 'ads_daily_sync';

	/**
	 * Activation.
	 */
	public static function activate() {
		delete_option( self::SETUP_OPTION );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Run the setup once per plugin version — only on a wp-admin page load, never for visitors.
	 *
	 * The setup creates products, which runs every plugin hooked to product saves. One of them
	 * failing (as «خبرم کنید / LetMeKnow» did on the live site) must never take the site down:
	 * errors are caught and shown as an admin notice, and a failed attempt is not repeated on
	 * every request.
	 */
	public static function maybe_setup() {
		if ( ADS_VERSION === get_option( self::SETUP_OPTION ) ) {
			return;
		}
		if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$failed = get_option( self::FAILED_OPTION );
		if ( is_array( $failed ) && isset( $failed['version'], $failed['time'] ) && ADS_VERSION === $failed['version'] && time() - (int) $failed['time'] < 10 * MINUTE_IN_SECONDS ) {
			return; // Retry at most every 10 minutes.
		}

		try {
			self::setup();
			delete_option( self::FAILED_OPTION );
		} catch ( \Throwable $e ) {
			update_option(
				self::FAILED_OPTION,
				array(
					'version' => ADS_VERSION,
					'time'    => time(),
					'error'   => $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')',
				),
				false
			);
		}
	}

	/**
	 * Settings / data changes between versions.
	 *
	 * 0.3.0: a separate discount for customer-built sets.
	 * 0.2.0: sets are sold in 10 ml only — keep the 10 ml row of the volume table (or the first
	 * row's packaging fee and box), drop the others, and point every ready-made set at 10 ml.
	 */
	public static function migrate() {
		$rows = (array) ADS_Settings::get( 'volumes' );
		$keep = null;
		foreach ( $rows as $row ) {
			if ( isset( $row['ml'] ) && in_array( absint( $row['ml'] ), ADS_Settings::allowed_volumes(), true ) ) {
				$keep = $row;
				break;
			}
		}
		if ( null === $keep ) {
			$first = $rows ? reset( $rows ) : array();
			$keep  = array(
				'ml'  => ADS_Settings::allowed_volumes()[0],
				'fee' => isset( $first['fee'] ) ? (float) $first['fee'] : 0,
				'box' => isset( $first['box'] ) ? absint( $first['box'] ) : 0,
			);
		}
		ADS_Settings::update(
			array(
				'volumes'        => array( $keep ),
				'default_volume' => absint( $keep['ml'] ),
			)
		);

		// The «ست سفارشی» product's cart line reads better as «دیسکاوری ست اختصاصی» (only the old default name).
		$custom = ADS_Set::custom_product_id_if_exists();
		if ( $custom && 'ست سفارشی' === get_post_field( 'post_title', $custom ) ) {
			wp_update_post(
				array(
					'ID'         => $custom,
					'post_title' => __( 'دیسکاوری ست اختصاصی', 'aromist-discovery-set' ),
				)
			);
		}

		// 0.3.0: customer-built sets have their own discount; it starts equal to the ready-made one.
		$custom_discount = ADS_Settings::get( 'custom_discount' );
		if ( null === $custom_discount || '' === $custom_discount ) {
			ADS_Settings::update( array( 'custom_discount' => ADS_Settings::discount() ) );
		}

		foreach ( ADS_Set::preset_ids() as $id ) {
			$set = wc_get_product( $id );
			if ( $set instanceof WC_Product_Discovery_Set ) {
				$set->update_meta_data( WC_Product_Discovery_Set::META_VOLUMES, array( absint( $keep['ml'] ) ) );
				$set->update_meta_data( WC_Product_Discovery_Set::META_DEFAULT, absint( $keep['ml'] ) );
				$set->save_meta_data();
			}
		}
	}

	/**
	 * Why the last setup attempt failed ('' when it did not).
	 *
	 * @return string
	 */
	public static function setup_error() {
		$failed = get_option( self::FAILED_OPTION );
		return is_array( $failed ) && ! empty( $failed['error'] ) ? (string) $failed['error'] : '';
	}

	/**
	 * Create a product post first and return the product object for it, so the product already
	 * has an ID when WooCommerce saves it. (Some plugins hooked to product saves — «خبرم کنید /
	 * LetMeKnow» on the live site — crash on a new product without an ID.)
	 *
	 * @param string $class     WC product class.
	 * @param string $name      Product name.
	 * @param string $status    Final status (set by the caller's save()).
	 * @return WC_Product
	 * @throws Exception When the post cannot be created.
	 */
	public static function new_product( $class, $name, $status = 'private' ) {
		$id = wp_insert_post(
			array(
				'post_type'   => 'product',
				'post_status' => 'auto-draft',
				'post_title'  => $name,
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			throw new Exception( is_wp_error( $id ) ? esc_html( $id->get_error_message() ) : 'wp_insert_post failed' );
		}
		$product = new $class( $id );
		$product->set_name( $name );
		$product->set_status( $status );
		return $product;
	}

	/**
	 * Create whatever is missing (safe to run again).
	 */
	public static function setup() {
		self::migrate();
		ADS_Boxes::ensure_default_box();
		ADS_Set::custom_product_id();
		ADS_Pages::ensure_pages();
		ADS_Permalinks::schedule_flush();
		ADS_Sync::queue_all();

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}

		update_option( self::SETUP_OPTION, ADS_VERSION, true );
	}

	/**
	 * The «دیسکاوری ست» product category. It is created with the first saved set (not on
	 * activation), so an empty category never shows up in the shop's category menus.
	 *
	 * @param bool $create Create it when missing.
	 * @return int Term id, 0 when missing / on failure.
	 */
	public static function category_id( $create = true ) {
		$id = absint( ADS_Settings::get( 'category_id' ) );
		if ( $id ) {
			$term = get_term( $id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				return $id;
			}
		}
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return 0;
		}
		if ( ! $create ) {
			$existing = get_term_by( 'slug', 'discovery-set', 'product_cat' );
			return $existing ? (int) $existing->term_id : 0;
		}

		$existing = get_term_by( 'slug', 'discovery-set', 'product_cat' );
		if ( $existing ) {
			$id = (int) $existing->term_id;
		} else {
			$created = wp_insert_term( __( 'دیسکاوری ست', 'aromist-discovery-set' ), 'product_cat', array( 'slug' => 'discovery-set' ) );
			$id      = is_wp_error( $created ) ? 0 : (int) $created['term_id'];
		}

		if ( $id ) {
			ADS_Settings::update( array( 'category_id' => $id ) );
		}
		return $id;
	}
}
