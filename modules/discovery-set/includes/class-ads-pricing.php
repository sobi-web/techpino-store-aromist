<?php
/**
 * Prices.
 *
 * Price of one perfume in a set volume:
 *   - a published, priced variation with exactly that volume → its active price;
 *   - otherwise → price per ml of the smallest priced variation × volume.
 * Price of a set (auto mode):
 *   regular = Σ perfume prices + packaging fee of the volume
 *   price   = Σ perfume prices × (1 − discount) + packaging fee
 * (one discount for ready-made sets, another for customer-built sets — see ADS_Settings::discount_for())
 * both rounded to the configured step. Fixed mode uses the set's own price for the volume.
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pricing helpers.
 */
final class ADS_Pricing {

	/**
	 * Request cache: perfume id => [ ml => price ] of its priced volume variations.
	 *
	 * @var array
	 */
	private static $variation_prices = array();

	/**
	 * Clear the request cache (after a price change).
	 *
	 * @param int $perfume_id Perfume id (0 = all).
	 */
	public static function reset_cache( $perfume_id = 0 ) {
		if ( $perfume_id ) {
			unset( self::$variation_prices[ (int) $perfume_id ] );
		} else {
			self::$variation_prices = array();
		}
	}

	/**
	 * Active prices of a perfume's published volume variations.
	 *
	 * @param int $perfume_id Perfume id.
	 * @return array ml => price (ascending ml)
	 */
	public static function variation_prices( $perfume_id ) {
		$perfume_id = (int) $perfume_id;
		if ( isset( self::$variation_prices[ $perfume_id ] ) ) {
			return self::$variation_prices[ $perfume_id ];
		}

		$out    = array();
		$parent = wc_get_product( $perfume_id );
		if ( $parent instanceof WC_Product_Variable ) {
			foreach ( $parent->get_children() as $child_id ) {
				if ( 'publish' !== get_post_status( $child_id ) ) {
					continue;
				}
				$ml = AVS_Product::variation_ml( $child_id );
				if ( $ml <= 0 ) {
					continue;
				}
				$variation = wc_get_product( $child_id );
				if ( ! $variation ) {
					continue;
				}
				$price = $variation->get_price();
				if ( '' === $price || (float) $price <= 0 ) {
					continue;
				}
				// Two variations with the same volume: keep the cheaper one.
				$out[ $ml ] = isset( $out[ $ml ] ) ? min( $out[ $ml ], (float) $price ) : (float) $price;
			}
		}
		ksort( $out );

		self::$variation_prices[ $perfume_id ] = $out;
		return $out;
	}

	/**
	 * The variation used for volumes that do not exist: the smallest priced one.
	 *
	 * @param int $perfume_id Perfume id.
	 * @return array|null [ ml, price ]
	 */
	public static function price_base( $perfume_id ) {
		$prices = self::variation_prices( $perfume_id );
		if ( ! $prices ) {
			return null;
		}
		$ml = (int) array_key_first( $prices );
		return array( $ml, $prices[ $ml ] );
	}

	/**
	 * Price of one perfume in a volume, with how it was found.
	 *
	 * @param int $perfume_id Perfume id.
	 * @param int $volume     Volume.
	 * @return array|null [ 'price' => float, 'exact' => bool, 'from_ml' => int ]
	 */
	public static function perfume_price( $perfume_id, $volume ) {
		$prices = self::variation_prices( $perfume_id );
		$volume = (int) $volume;
		if ( $volume <= 0 || ! $prices ) {
			return null;
		}
		if ( isset( $prices[ $volume ] ) ) {
			return array(
				'price'   => $prices[ $volume ],
				'exact'   => true,
				'from_ml' => $volume,
			);
		}
		list( $ml, $price ) = self::price_base( $perfume_id );
		return array(
			'price'   => $price / $ml * $volume,
			'exact'   => false,
			'from_ml' => $ml,
		);
	}

	/**
	 * Full price breakdown of a set.
	 *
	 * @param int[]                         $components Perfume ids.
	 * @param int                           $volume     Volume.
	 * @param WC_Product_Discovery_Set|null $product    Set (fixed prices).
	 * @return array|null null when a perfume has no price. Keys: lines (id => perfume_price),
	 *                    sum, fee, discount (percent), regular, price, fixed (bool).
	 */
	public static function breakdown( array $components, $volume, $product = null ) {
		$lines = array();
		$sum   = 0.0;
		foreach ( $components as $id ) {
			$line = self::perfume_price( $id, $volume );
			if ( null === $line ) {
				return null;
			}
			$lines[ (int) $id ] = $line;
			$sum               += $line['price'];
		}

		$row      = ADS_Settings::volume( $volume );
		$fee      = $row ? (float) $row['fee'] : 0.0;
		$discount = ADS_Settings::discount_for( $product );
		$regular  = ADS_Settings::round_price( $sum + $fee );
		$price    = ADS_Settings::round_price( $sum * ( 1 - $discount / 100 ) + $fee );
		$fixed    = false;

		if ( $product instanceof WC_Product_Discovery_Set && 'fixed' === $product->get_price_mode() ) {
			$own = $product->get_fixed_price( $volume );
			if ( '' !== $own ) {
				$price = (float) $own;
				$fixed = true;
			}
		}

		$breakdown = array(
			'lines'    => $lines,
			'sum'      => $sum,
			'fee'      => $fee,
			'discount' => $discount,
			'regular'  => max( $regular, $price ),
			'price'    => max( 0.0, $price ),
			'fixed'    => $fixed,
		);

		/**
		 * Price breakdown of a set.
		 *
		 * @param array                         $breakdown  Breakdown.
		 * @param int[]                         $components Perfume ids.
		 * @param int                           $volume     Volume.
		 * @param WC_Product_Discovery_Set|null $product    Set.
		 */
		return apply_filters( 'ads_price_breakdown', $breakdown, $components, $volume, $product );
	}

	/**
	 * Final price of a set ('' when it cannot be priced).
	 *
	 * @param int[]                         $components Perfume ids.
	 * @param int                           $volume     Volume.
	 * @param WC_Product_Discovery_Set|null $product    Set.
	 * @return float|string
	 */
	public static function price( array $components, $volume, $product = null ) {
		$breakdown = self::breakdown( $components, $volume, $product );
		return null === $breakdown ? '' : $breakdown['price'];
	}
}
