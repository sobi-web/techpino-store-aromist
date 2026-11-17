<?php
/**
 * Product helpers: which products use a volume pool, ml per variation, pool amounts.
 *
 * Data model
 * ----------
 * - Parent (variable product):
 *     _avs_enabled   = 'yes'  → the product uses a volume pool.
 *     _manage_stock  = 'yes'  → WooCommerce manages the stock on the parent…
 *     _stock         = pool   → …and that stock number IS the pool in millilitres.
 *     _avs_waste_ml  = int    → optional extra ml consumed per sold unit (decanting loss).
 *   «Unlimited» (1.2.0): _avs_enabled = 'yes' but _manage_stock ≠ 'yes' → the volumes are known
 *   (_avs_ml) but no pool is counted yet; every size sells without a limit until the shop sets
 *   the pool (AVS_Manager::adjust()), which turns the parent's stock management on.
 * - Variations:
 *     _manage_stock  = 'no'   → stock is inherited from the parent (WooCommerce "parent" mode).
 *     _avs_ml        = int    → nominal ml of one unit of this variation (10, 20, 50, 100 …).
 *
 * Because the pool is WooCommerce's own parent stock, every stock write goes through
 * wc_update_product_stock() (atomic SQL) and WooCommerce's stock reservation (row locks)
 * protects the whole pool across all variations during concurrent checkouts.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

/**
 * Static helpers used everywhere in the plugin.
 */
final class AVS_Product {

	const META_ENABLED = '_avs_enabled';
	const META_ML      = '_avs_ml';
	const META_WASTE   = '_avs_waste_ml';
	const META_NO_AUTO = '_avs_no_auto'; // «yes» after the shop turned volume stock off: never turn it on again by itself.

	/**
	 * Request cache: parent id => bool.
	 *
	 * @var array
	 */
	private static $enabled = array();

	/**
	 * Clear request caches (after enabling/disabling).
	 *
	 * @param int $parent_id Optional parent id.
	 */
	public static function reset_cache( $parent_id = 0 ) {
		if ( $parent_id ) {
			unset( self::$enabled[ (int) $parent_id ] );
		} else {
			self::$enabled = array();
		}
	}

	/**
	 * Store-wide stock management switch.
	 *
	 * @return bool
	 */
	public static function store_manages_stock() {
		return 'yes' === get_option( 'woocommerce_manage_stock' );
	}

	/**
	 * Is volume stock enabled on this parent product?
	 *
	 * @param int $parent_id Parent (variable product) id.
	 * @return bool
	 */
	public static function is_enabled( $parent_id ) {
		$parent_id = absint( $parent_id );
		if ( ! $parent_id ) {
			return false;
		}
		if ( ! isset( self::$enabled[ $parent_id ] ) ) {
			self::$enabled[ $parent_id ] = 'yes' === get_post_meta( $parent_id, self::META_ENABLED, true );
		}
		return self::$enabled[ $parent_id ];
	}

	/**
	 * Volume stock on, but no pool set yet: sizes sell without a limit.
	 *
	 * @param int $parent_id Parent id.
	 * @return bool
	 */
	public static function is_unlimited( $parent_id ) {
		return self::is_enabled( $parent_id ) && 'yes' !== get_post_meta( absint( $parent_id ), '_manage_stock', true );
	}

	/**
	 * Is this product a variation that draws from a volume pool right now?
	 *
	 * A variation that manages its own stock is deliberately NOT treated as volume stock,
	 * so a misconfiguration falls back to normal WooCommerce behaviour instead of wrong numbers.
	 *
	 * @param mixed $product Product.
	 * @return bool
	 */
	public static function is_volume_variation( $product ) {
		if ( ! $product instanceof WC_Product_Variation || ! self::store_manages_stock() ) {
			return false;
		}
		if ( ! self::is_enabled( $product->get_parent_id() ) ) {
			return false;
		}
		if ( 'parent' !== $product->get_manage_stock() ) {
			return false;
		}
		return self::variation_ml( $product->get_id() ) > 0;
	}

	/**
	 * Is this a variable product with volume stock (parent level)?
	 *
	 * @param mixed $product Product.
	 * @return bool
	 */
	public static function is_volume_parent( $product ) {
		return $product instanceof WC_Product && $product->is_type( 'variable' ) && self::is_enabled( $product->get_id() );
	}

	/**
	 * Nominal ml of one unit of a variation.
	 *
	 * @param int $variation_id Variation id.
	 * @return int
	 */
	public static function variation_ml( $variation_id ) {
		return absint( get_post_meta( $variation_id, self::META_ML, true ) );
	}

	/**
	 * Extra ml consumed per sold unit (loss while decanting).
	 *
	 * @param int $parent_id Parent id.
	 * @return int
	 */
	public static function waste_ml( $parent_id ) {
		return absint( get_post_meta( $parent_id, self::META_WASTE, true ) );
	}

	/**
	 * Effective ml taken from the pool for one unit of the variation.
	 *
	 * @param WC_Product_Variation $variation Variation.
	 * @return int
	 */
	public static function unit_ml( WC_Product_Variation $variation ) {
		$ml = self::variation_ml( $variation->get_id() );
		return $ml > 0 ? $ml + self::waste_ml( $variation->get_parent_id() ) : 0;
	}

	/**
	 * Current pool (ml) read from the parent's stock meta.
	 *
	 * @param int $parent_id Parent id.
	 * @return int
	 */
	public static function pool( $parent_id ) {
		return (int) wc_stock_amount( get_post_meta( $parent_id, '_stock', true ) );
	}

	/**
	 * Ml currently held by other customers' pending checkouts (WooCommerce stock reservation).
	 *
	 * @param int $parent_id        Parent id.
	 * @param int $exclude_order_id Order to ignore (the current customer's own pending order).
	 * @return int
	 */
	public static function held_ml( $parent_id, $exclude_order_id = 0 ) {
		$parent = wc_get_product( $parent_id );
		if ( ! $parent ) {
			return 0;
		}
		return max( 0, (int) wc_get_held_stock_quantity( $parent, (int) $exclude_order_id ) );
	}

	/**
	 * Whole units of a variation that fit into an amount of ml.
	 *
	 * @param WC_Product_Variation $variation    Variation.
	 * @param int                  $available_ml Available ml.
	 * @return int
	 */
	public static function units_for_ml( WC_Product_Variation $variation, $available_ml ) {
		$unit = self::unit_ml( $variation );
		if ( $unit <= 0 || $available_ml <= 0 ) {
			return 0;
		}
		return intdiv( (int) $available_ml, $unit );
	}

	/**
	 * Child variation ids (all statuses).
	 *
	 * @param int $parent_id Parent id.
	 * @return int[]
	 */
	public static function children_ids( $parent_id ) {
		$parent = wc_get_product( $parent_id );
		return $parent ? array_map( 'absint', $parent->get_children() ) : array();
	}

	/**
	 * Smallest unit (ml) among the published volume variations. 0 when none.
	 *
	 * @param int $parent_id Parent id.
	 * @return int
	 */
	public static function min_unit_ml( $parent_id ) {
		$min   = 0;
		$waste = self::waste_ml( $parent_id );
		foreach ( self::children_ids( $parent_id ) as $child_id ) {
			if ( 'publish' !== get_post_status( $child_id ) ) {
				continue;
			}
			$ml = self::variation_ml( $child_id );
			if ( $ml > 0 && ( 0 === $min || $ml + $waste < $min ) ) {
				$min = $ml + $waste;
			}
		}
		return $min;
	}

	/**
	 * Parse a millilitre amount out of a label such as "50 میل", "۱۰۰ میلی‌لیتر", "30ml" or "1 لیتر".
	 *
	 * @param string $text Label or slug.
	 * @return int 0 when nothing numeric was found.
	 */
	public static function parse_ml( $text ) {
		$text = rawurldecode( (string) $text );
		$text = strtr(
			$text,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
				'٫' => '.', '٬' => '',
			)
		);
		// Thousands separators ("1,000").
		$text = preg_replace( '/(?<=\d),(?=\d{3}(?!\d))/u', '', $text );

		if ( ! preg_match( '/(\d+(?:[.,]\d+)?)\s*(ml|mil|cc|میلی\s*‌?\s*لیتر|میل|لیتر|lit(?:er|re)?|l)?/iu', $text, $m ) ) {
			return 0;
		}

		$number = (float) str_replace( ',', '.', $m[1] );
		$unit   = isset( $m[2] ) ? strtolower( $m[2] ) : '';

		if ( in_array( $unit, array( 'l', 'lit', 'liter', 'litre', 'لیتر' ), true ) ) {
			$number *= 1000;
		}

		return max( 0, (int) round( $number ) );
	}

	/**
	 * Try to detect the ml of a variation from its attributes (e.g. pa_volume = "50 میل").
	 *
	 * @param WC_Product_Variation $variation Variation.
	 * @return int
	 */
	public static function detect_ml( WC_Product_Variation $variation ) {
		$candidates = array();

		foreach ( $variation->get_attributes() as $taxonomy => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$label = $value;
			if ( taxonomy_exists( $taxonomy ) ) {
				$term = get_term_by( 'slug', $value, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$label = $term->name;
				}
			}
			// Prefer attributes that look like volume.
			$is_volume = (bool) preg_match( '/volume|size|ml|حجم/iu', rawurldecode( $taxonomy ) );
			$ml        = self::parse_ml( $label );
			if ( $ml > 0 ) {
				$candidates[] = array( $is_volume ? 0 : 1, $ml );
			}
		}

		if ( empty( $candidates ) ) {
			return 0;
		}
		sort( $candidates );
		return (int) $candidates[0][1];
	}
}
