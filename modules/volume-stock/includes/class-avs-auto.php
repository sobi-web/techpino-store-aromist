<?php
/**
 * Every perfume gets volume stock by itself, «unlimited» until the shop sets its pool (1.2.0).
 *
 * When a variable product (or one of its variations) is saved, and all its published sizes have a
 * readable volume («۱۰ میل», «50ml» …), volume stock is switched on in «unlimited» mode: the ml of
 * each size is stored, nothing else changes (no stock is counted, nothing is limited). The moment the
 * shop sets the pool («موجودی حجمی ← تنظیم موجودی», or stock quantity on the product screen), the
 * product is limited by it like before.
 *
 * Products are left alone when they already count stock in units (parent or a published size manages
 * its own stock — the shop must enter the real ml pool), when a size has no readable volume, or when
 * the shop turned volume stock off on them before (`_avs_no_auto`).
 *
 * Only post meta is written (no product save), so other plugins' save hooks never run from here. The
 * products saved in a request are handled once, at its end. Existing perfumes are switched on once,
 * on the first wp-admin page load after the update.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

/**
 * Automatic «unlimited» volume stock.
 */
final class AVS_Auto {

	const DONE_OPTION = 'avs_auto_unlimited_done';

	/**
	 * Parents saved in this request.
	 *
	 * @var int[]
	 */
	private static $queue = array();

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_after_product_object_save', array( $this, 'product_saved' ), 50 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'variation_saved' ), 50 );
		add_action( 'shutdown', array( __CLASS__, 'flush' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_migrate' ), 30 );
	}

	/**
	 * Is the automatic switch on? (Filter `avs_auto_unlimited`.)
	 *
	 * @return bool
	 */
	public static function active() {
		/**
		 * Switch volume stock on («unlimited») for every new perfume by itself.
		 *
		 * @since 1.2.0
		 * @param bool $on On.
		 */
		return (bool) apply_filters( 'avs_auto_unlimited', true );
	}

	/**
	 * A product was saved: remember its parent.
	 *
	 * @param WC_Product $product Product.
	 */
	public function product_saved( $product ) {
		if ( $product instanceof WC_Product_Variation ) {
			self::queue( $product->get_parent_id() );
		} elseif ( $product instanceof WC_Product_Variable ) {
			self::queue( $product->get_id() );
		}
	}

	/**
	 * A variation was saved from the product screen.
	 *
	 * @param int $variation_id Variation id.
	 */
	public function variation_saved( $variation_id ) {
		self::queue( (int) wp_get_post_parent_id( $variation_id ) );
	}

	/**
	 * Remember a parent.
	 *
	 * @param int $parent_id Parent id.
	 */
	public static function queue( $parent_id ) {
		$parent_id = absint( $parent_id );
		if ( $parent_id ) {
			self::$queue[ $parent_id ] = $parent_id;
		}
	}

	/**
	 * Handle the remembered parents (end of the request).
	 */
	public static function flush() {
		$ids         = self::$queue;
		self::$queue = array();
		foreach ( $ids as $id ) {
			try {
				self::apply( $id );
			} catch ( \Throwable $e ) {
				error_log( sprintf( 'Techpino Volume Stock: automatic volume stock for #%d failed: %s (%s:%d)', $id, $e->getMessage(), $e->getFile(), $e->getLine() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	/**
	 * Switch one perfume on (unlimited), or fill the volume of its new sizes when it is already on.
	 *
	 * @param int $parent_id Parent id.
	 * @return bool Switched on now.
	 */
	public static function apply( $parent_id ) {
		$parent_id = absint( $parent_id );
		if ( ! self::active() || 'product' !== get_post_type( $parent_id ) || 'trash' === get_post_status( $parent_id ) ) {
			return false;
		}
		AVS_Product::reset_cache( $parent_id );
		if ( AVS_Product::is_enabled( $parent_id ) ) {
			self::fill_missing_ml( $parent_id );
			return false;
		}
		$ml_map = self::eligible( $parent_id );
		if ( ! $ml_map ) {
			return false;
		}
		return true === AVS_Manager::enable_unlimited( $parent_id, $ml_map );
	}

	/**
	 * Volumes of a product that can be switched on automatically (empty = leave it alone).
	 *
	 * @param int $parent_id Parent id.
	 * @return array variation_id => ml (published sizes only)
	 */
	public static function eligible( $parent_id ) {
		if ( ! AVS_Product::store_manages_stock() || 'yes' === get_post_meta( $parent_id, AVS_Product::META_NO_AUTO, true ) ) {
			return array();
		}
		$parent = wc_get_product( $parent_id );
		if ( ! $parent instanceof WC_Product_Variable || 'yes' === get_post_meta( $parent_id, '_manage_stock', true ) ) {
			return array();
		}
		$map = array();
		foreach ( $parent->get_children() as $child_id ) {
			if ( 'publish' !== get_post_status( $child_id ) ) {
				continue;
			}
			if ( 'yes' === get_post_meta( $child_id, '_manage_stock', true ) ) {
				return array(); // Counts stock in units: the shop has to enter the real pool.
			}
			$ml = AVS_Product::variation_ml( $child_id );
			if ( $ml <= 0 ) {
				$variation = wc_get_product( $child_id );
				$ml        = $variation instanceof WC_Product_Variation ? AVS_Product::detect_ml( $variation ) : 0;
			}
			if ( $ml <= 0 ) {
				return array(); // A size without a readable volume.
			}
			$map[ (int) $child_id ] = $ml;
		}
		return $map;
	}

	/**
	 * New sizes of a product with volume stock get their volume from their name.
	 *
	 * @param int $parent_id Parent id.
	 */
	private static function fill_missing_ml( $parent_id ) {
		$changed = false;
		foreach ( AVS_Product::children_ids( $parent_id ) as $child_id ) {
			if ( AVS_Product::variation_ml( $child_id ) > 0 ) {
				continue;
			}
			$variation = wc_get_product( $child_id );
			$ml        = $variation instanceof WC_Product_Variation ? AVS_Product::detect_ml( $variation ) : 0;
			if ( $ml > 0 ) {
				update_post_meta( $child_id, AVS_Product::META_ML, $ml );
				$changed = true;
			}
		}
		if ( $changed && ! AVS_Product::is_unlimited( $parent_id ) ) {
			AVS_Stock::sync_children_status( $parent_id );
		}
	}

	/**
	 * Once after the update: switch on every existing perfume that qualifies. Runs on a wp-admin page
	 * load of a shop manager (never for visitors). Only post meta is written, so it is quick.
	 */
	public static function maybe_migrate() {
		if ( AVS_VERSION === get_option( self::DONE_OPTION ) || ! self::active() || wp_doing_ajax() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_tax_query
					array(
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => 'variable',
					),
				),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query
					array(
						'key'     => AVS_Product::META_ENABLED,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		$count = 0;
		foreach ( $ids as $id ) {
			try {
				$count += self::apply( $id ) ? 1 : 0;
			} catch ( \Throwable $e ) {
				error_log( sprintf( 'Techpino Volume Stock: automatic volume stock for #%d failed: %s', $id, $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
		update_option( self::DONE_OPTION, AVS_VERSION, false );
		if ( $count ) {
			update_option( 'avs_auto_unlimited_count', $count, false );
		}
	}
}
