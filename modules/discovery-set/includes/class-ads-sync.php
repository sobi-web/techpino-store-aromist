<?php
/**
 * Keeps the stored price and stock status of ready-made sets in step with their perfumes.
 *
 * Stored values (default volume price, "in stock" when at least one volume can be sold) are what
 * shop listings, sorting, price filters and page builders read. Cart prices never use them —
 * they are calculated live per cart item.
 *
 * Changes are collected during the request and applied once at `shutdown` (a checkout touches
 * four pools; the set must be judged after all of them changed). Tests call flush() directly.
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sync.
 */
final class ADS_Sync {

	/**
	 * Queued set ids.
	 *
	 * @var int[]
	 */
	private static $queue = array();

	/**
	 * Inside flush() (ignore the sets' own saves).
	 *
	 * @var bool
	 */
	private static $running = false;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_after_product_object_save', array( $this, 'product_saved' ), 20 );
		add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'prime_price_meta' ), 5 );
		add_action( 'woocommerce_product_set_stock', array( $this, 'stock_changed' ), 20 );
		add_action( 'avs_enabled', array( $this, 'perfume_changed' ) );
		add_action( 'avs_disabled', array( $this, 'perfume_changed' ) );
		add_action( 'transition_post_status', array( $this, 'status_changed' ), 20, 3 );
		add_action( 'trashed_post', array( $this, 'perfume_changed' ) );
		add_action( 'deleted_post', array( $this, 'perfume_changed' ) );
		add_action( 'ads_settings_saved', array( __CLASS__, 'queue_all' ) );
		add_action( ADS_Install::CRON_HOOK, array( __CLASS__, 'sync_all' ) );
		add_action( 'shutdown', array( __CLASS__, 'flush' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Queue
	|--------------------------------------------------------------------------
	*/

	/**
	 * Queue one set.
	 *
	 * @param int $set_id Set id.
	 */
	public static function queue( $set_id ) {
		$set_id = absint( $set_id );
		if ( $set_id && $set_id !== absint( ADS_Settings::get( 'custom_product_id' ) ) ) {
			self::$queue[ $set_id ] = $set_id;
		}
	}

	/**
	 * Queue every set that contains a perfume.
	 *
	 * @param int $perfume_id Perfume id.
	 */
	public static function queue_perfume( $perfume_id ) {
		ADS_Pricing::reset_cache( $perfume_id );
		ADS_Set::reset_cache();
		foreach ( ADS_Set::preset_ids( $perfume_id ) as $id ) {
			self::queue( $id );
		}
	}

	/**
	 * Queue every set.
	 */
	public static function queue_all() {
		ADS_Pricing::reset_cache();
		ADS_Set::reset_cache();
		foreach ( ADS_Set::preset_ids() as $id ) {
			self::queue( $id );
		}
	}

	/**
	 * Apply all queued syncs.
	 */
	public static function flush() {
		if ( self::$running || ! self::$queue ) {
			return;
		}
		self::$running = true;
		while ( self::$queue ) {
			$id = array_shift( self::$queue );
			try {
				self::sync( $id );
			} catch ( \Throwable $e ) {
				// Runs at shutdown of normal requests (orders, product edits): log, never break the page.
				error_log( sprintf( 'Techpino Discovery Set: sync of set #%d failed: %s (%s:%d)', $id, $e->getMessage(), $e->getFile(), $e->getLine() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
		self::$running = false;
	}

	/**
	 * Sync every set now (daily cron, "sync all" button).
	 */
	public static function sync_all() {
		self::queue_all();
		self::flush();
	}

	/*
	|--------------------------------------------------------------------------
	| Sync
	|--------------------------------------------------------------------------
	*/

	/**
	 * Recalculate and store one set's price and stock status.
	 *
	 * @param int $set_id Set id.
	 * @return bool Saved.
	 */
	public static function sync( $set_id ) {
		$product = wc_get_product( $set_id );
		if ( ! $product instanceof WC_Product_Discovery_Set || $product->is_custom() ) {
			return false;
		}
		self::apply( $product );
		if ( ! $product->get_changes() ) {
			return false;
		}
		$was           = self::$running;
		self::$running = true;
		$product->save();
		self::$running = $was;
		return true;
	}

	/**
	 * Put the calculated values on a set object (not saved here).
	 *
	 * @param WC_Product_Discovery_Set $product Set.
	 */
	public static function apply( $product ) {
		if ( $product->is_custom() ) {
			$product->set_stock_status( 'instock' );
			return;
		}

		$category = ADS_Install::category_id();
		if ( $category && ! in_array( $category, $product->get_category_ids( 'edit' ), true ) ) {
			$product->set_category_ids( array_merge( $product->get_category_ids( 'edit' ), array( $category ) ) );
		}

		// Sets have their own section; in the main shop / category listings only when chosen in the settings.
		self::set_if_changed( $product, 'catalog_visibility', 'yes' === ADS_Settings::get( 'show_in_shop' ) ? 'visible' : 'search' );

		$components = $product->get_components();
		$valid      = '' === ADS_Set::preset_error( $product );
		$breakdown  = $valid ? ADS_Pricing::breakdown( $components, $product->get_default_volume(), $product ) : null;

		if ( $breakdown ) {
			$regular = wc_format_decimal( $breakdown['regular'] );
			$sale    = $breakdown['price'] < $breakdown['regular'] ? wc_format_decimal( $breakdown['price'] ) : '';
			self::set_if_changed( $product, 'regular_price', $regular );
			self::set_if_changed( $product, 'sale_price', $sale );
			if ( '' === $sale && ( $product->get_date_on_sale_from( 'edit' ) || $product->get_date_on_sale_to( 'edit' ) ) ) {
				$product->set_date_on_sale_from( '' );
				$product->set_date_on_sale_to( '' );
			}
		}

		$in_stock = false;
		if ( $valid ) {
			foreach ( ADS_Availability::volumes( $components, $product->get_volumes(), false ) as $ok ) {
				if ( $ok ) {
					$in_stock = true;
					break;
				}
			}
		}
		self::set_if_changed( $product, 'stock_status', $in_stock ? 'instock' : 'outofstock' );
	}

	/**
	 * Set a prop only when its value differs (keeps get_changes() empty otherwise).
	 *
	 * @param WC_Product $product Product.
	 * @param string     $prop    Prop.
	 * @param string     $value   Value.
	 */
	private static function set_if_changed( $product, $prop, $value ) {
		$getter = 'get_' . $prop;
		$setter = 'set_' . $prop;
		if ( (string) $product->$getter( 'edit' ) !== (string) $value ) {
			$product->$setter( $value );
		}
	}

	/**
	 * Write a set's price meta before WooCommerce saves it.
	 *
	 * WooCommerce updates the post (firing `save_post`) before it writes `_price`. Plugins that read
	 * the price there see the old value; for a set that was never priced that is '' — and «خبرم کنید /
	 * LetMeKnow» then throws (number_format('')) in its back-in-stock notice when the set turns
	 * «موجود», which stopped the save half-way and left the set «ناموجود». Writing the new price first
	 * keeps every such plugin on a number.
	 *
	 * @param WC_Product $product Product about to be saved.
	 */
	public static function prime_price_meta( $product ) {
		if ( ! $product instanceof WC_Product_Discovery_Set || $product->is_custom() || ! $product->get_id() ) {
			return;
		}
		$regular = (string) $product->get_regular_price( 'edit' );
		$sale    = (string) $product->get_sale_price( 'edit' );
		$price   = '' !== $sale ? $sale : $regular;
		if ( '' === $price ) {
			return;
		}
		if ( (string) $product->get_price( 'edit' ) !== $price ) {
			$product->set_price( $price );
		}
		$id = $product->get_id();
		foreach ( array( '_price' => $price, '_regular_price' => $regular, '_sale_price' => $sale ) as $key => $value ) {
			if ( (string) get_post_meta( $id, $key, true ) !== $value ) {
				update_post_meta( $id, $key, $value );
			}
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Triggers
	|--------------------------------------------------------------------------
	*/

	/**
	 * A product was saved: a set itself, a perfume, or one of its variations (price change).
	 *
	 * @param WC_Product $product Product.
	 */
	public function product_saved( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		if ( $product instanceof WC_Product_Discovery_Set ) {
			if ( ! self::$running ) {
				self::queue( $product->get_id() );
			}
			return;
		}
		if ( $product instanceof WC_Product_Variation ) {
			self::queue_perfume( $product->get_parent_id() );
			return;
		}
		if ( $product instanceof WC_Product_Variable ) {
			self::queue_perfume( $product->get_id() );
		}
	}

	/**
	 * Stock of a pool or a box changed.
	 *
	 * @param WC_Product $product Product.
	 */
	public function stock_changed( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		if ( ADS_Boxes::is_box( $product->get_id() ) ) {
			self::queue_all();
		} elseif ( $product instanceof WC_Product_Variable ) {
			self::queue_perfume( $product->get_id() );
		}
	}

	/**
	 * A perfume was enabled/disabled, trashed or deleted.
	 *
	 * @param int $post_id Id.
	 */
	public function perfume_changed( $post_id ) {
		$post_id = absint( $post_id );
		$type    = get_post_type( $post_id );
		if ( 'product_variation' === $type ) {
			$post_id = (int) wp_get_post_parent_id( $post_id );
		} elseif ( $type && 'product' !== $type ) {
			return;
		}
		if ( $post_id ) {
			self::queue_perfume( $post_id );
		}
	}

	/**
	 * A perfume (or variation) was published / unpublished.
	 *
	 * @param string  $new_status New.
	 * @param string  $old_status Old.
	 * @param WP_Post $post       Post.
	 */
	public function status_changed( $new_status, $old_status, $post ) {
		if ( $new_status === $old_status || ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
			return;
		}
		$this->perfume_changed( $post->ID );
	}
}
