<?php
/**
 * Plugin settings (option `ads_settings`).
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
final class ADS_Settings {

	const OPTION = 'ads_settings';

	/**
	 * Request cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// The volume sets are sold in (only 10 ml — see allowed_volumes()): ml, packaging fee, box product id (0 = boxes not counted).
			'volumes'           => array(
				array(
					'ml'  => 10,
					'fee' => 0,
					'box' => 0,
				),
			),
			'default_volume'    => 10,
			'discount'          => 0,     // Percent off the sum of the perfume prices of ready-made sets.
			'custom_discount'   => '',    // Percent off customer-built sets ('' = same as ready-made sets).
			'max_saved_sets'    => 20,    // Sets a customer can keep in «ست‌های من».
			'round_to'          => 1000,  // Final prices are rounded to the nearest multiple.
			'order_notes'       => 'yes',
			'category_id'       => 0,
			'custom_product_id' => 0,
			'archive_page_id'   => 0,     // «دیسکاوری ست‌ها» page: the base of the sets' URLs (/discovery-sets/name/).
			'archive_link'      => 'category', // «دیسکاوری ست‌ها» links: 'category' (archive of «دیسکاوری ست») or 'page'.
			'builder_page_id'   => 0,     // «ساخت دیسکاوری ست» page.
			'own_template'      => 'yes', // Ready-made sets use the plugin's own single page (not the theme's product template).
			'show_in_shop'      => 'no',  // Sets appear in the main shop / category listings too.
		);
	}

	/**
	 * Volumes a set can be sold in. Discovery sets are sold in 10 ml only.
	 *
	 * @return int[]
	 */
	public static function allowed_volumes() {
		/**
		 * Volumes (ml per perfume) a discovery set can be sold in.
		 *
		 * @param int[] $volumes Volumes.
		 */
		$volumes = array_values( array_filter( array_map( 'absint', (array) apply_filters( 'ads_allowed_volumes', array( 10 ) ) ) ) );
		sort( $volumes );
		return $volumes ? $volumes : array( 10 );
	}

	/**
	 * All settings.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Update some keys (already clean values).
	 *
	 * @param array $values Key => value.
	 */
	public static function update( array $values ) {
		$all = array_merge( self::all(), $values );
		update_option( self::OPTION, $all, true );
		self::$cache = null;
	}

	/**
	 * Clear the request cache (tests, after direct option writes).
	 */
	public static function reset_cache() {
		self::$cache = null;
	}

	/**
	 * Sanitize the settings form and save.
	 *
	 * @param array $input Raw input.
	 */
	public static function save( array $input ) {
		$rows = array();
		if ( isset( $input['volumes'] ) && is_array( $input['volumes'] ) ) {
			foreach ( $input['volumes'] as $row ) {
				$ml = isset( $row['ml'] ) ? absint( self::to_number( $row['ml'] ) ) : 0;
				if ( $ml <= 0 || isset( $rows[ $ml ] ) ) {
					continue;
				}
				$rows[ $ml ] = array(
					'ml'  => $ml,
					'fee' => isset( $row['fee'] ) ? max( 0, (float) self::to_number( $row['fee'] ) ) : 0,
					'box' => isset( $row['box'] ) ? absint( $row['box'] ) : 0,
				);
			}
		}
		ksort( $rows );
		$rows = array_values( $rows );

		$default = isset( $input['default_volume'] ) ? absint( $input['default_volume'] ) : 0;
		$mls     = wp_list_pluck( $rows, 'ml' );
		if ( ! in_array( $default, $mls, true ) ) {
			$default = $mls ? (int) $mls[0] : 0;
		}

		$discount = isset( $input['discount'] ) ? (float) self::to_number( $input['discount'] ) : 0;
		$custom   = isset( $input['custom_discount'] ) ? (float) self::to_number( $input['custom_discount'] ) : $discount;

		self::update(
			array(
				'volumes'         => $rows,
				'default_volume'  => $default,
				'discount'        => min( 100, max( 0, $discount ) ),
				'custom_discount' => min( 100, max( 0, $custom ) ),
				'max_saved_sets'  => isset( $input['max_saved_sets'] ) ? min( 100, max( 1, absint( self::to_number( $input['max_saved_sets'] ) ) ) ) : (int) self::get( 'max_saved_sets' ),
				'round_to'        => isset( $input['round_to'] ) ? absint( self::to_number( $input['round_to'] ) ) : 0,
				'order_notes'     => ! empty( $input['order_notes'] ) ? 'yes' : 'no',
				'own_template'    => ! empty( $input['own_template'] ) ? 'yes' : 'no',
				'show_in_shop'    => ! empty( $input['show_in_shop'] ) ? 'yes' : 'no',
				'archive_page_id' => isset( $input['archive_page_id'] ) ? absint( $input['archive_page_id'] ) : absint( self::get( 'archive_page_id' ) ),
				'archive_link'    => isset( $input['archive_link'] ) && 'page' === $input['archive_link'] ? 'page' : 'category',
				'builder_page_id' => isset( $input['builder_page_id'] ) ? absint( $input['builder_page_id'] ) : absint( self::get( 'builder_page_id' ) ),
			)
		);

		/**
		 * Fires after the settings were saved (prices of every set are re-synced).
		 */
		do_action( 'ads_settings_saved' );
	}

	/**
	 * Persian / Arabic digits and separators → plain number string.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function to_number( $value ) {
		$value = strtr(
			(string) $value,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
				'٫' => '.', '٬' => '', ',' => '', ' ' => '',
			)
		);
		return preg_replace( '/[^0-9.\-]/', '', $value );
	}

	/**
	 * Volume rows keyed by ml.
	 *
	 * @return array ml => [ ml, fee, box ]
	 */
	public static function volumes() {
		$saved = array();
		foreach ( (array) self::get( 'volumes' ) as $row ) {
			$ml = isset( $row['ml'] ) ? absint( $row['ml'] ) : 0;
			if ( $ml > 0 ) {
				$saved[ $ml ] = array(
					'ml'  => $ml,
					'fee' => isset( $row['fee'] ) ? (float) $row['fee'] : 0.0,
					'box' => isset( $row['box'] ) ? absint( $row['box'] ) : 0,
				);
			}
		}
		// Only allowed volumes; an allowed volume without its own row borrows the first row's fee and box.
		$first = $saved ? reset( $saved ) : array(
			'fee' => 0.0,
			'box' => 0,
		);
		$out   = array();
		foreach ( self::allowed_volumes() as $ml ) {
			$out[ $ml ] = isset( $saved[ $ml ] ) ? $saved[ $ml ] : array(
				'ml'  => $ml,
				'fee' => (float) $first['fee'],
				'box' => (int) $first['box'],
			);
		}
		return $out;
	}

	/**
	 * Allowed volumes (ml).
	 *
	 * @return int[]
	 */
	public static function volume_list() {
		return array_map( 'intval', array_keys( self::volumes() ) );
	}

	/**
	 * One volume row, or null when the volume is not offered.
	 *
	 * @param int $ml Volume.
	 * @return array|null
	 */
	public static function volume( $ml ) {
		$all = self::volumes();
		return isset( $all[ (int) $ml ] ) ? $all[ (int) $ml ] : null;
	}

	/**
	 * Default volume (always one of the allowed volumes, 0 when none).
	 *
	 * @return int
	 */
	public static function default_volume() {
		$list    = self::volume_list();
		$default = (int) self::get( 'default_volume' );
		if ( in_array( $default, $list, true ) ) {
			return $default;
		}
		return $list ? $list[0] : 0;
	}

	/**
	 * Discount of ready-made sets (percent).
	 *
	 * @return float
	 */
	public static function discount() {
		return min( 100.0, max( 0.0, (float) self::get( 'discount' ) ) );
	}

	/**
	 * Discount of customer-built sets (percent). Until it is saved once it follows the ready-made one.
	 *
	 * @return float
	 */
	public static function custom_discount() {
		$value = self::get( 'custom_discount' );
		if ( null === $value || '' === $value ) {
			return self::discount();
		}
		return min( 100.0, max( 0.0, (float) $value ) );
	}

	/**
	 * Discount that applies to a set product: customer-built (the «ست سفارشی» product) or ready-made.
	 *
	 * @param WC_Product|null $product Set product (null = ready-made).
	 * @return float
	 */
	public static function discount_for( $product = null ) {
		return $product instanceof WC_Product_Discovery_Set && $product->is_custom() ? self::custom_discount() : self::discount();
	}

	/**
	 * Round a price to the configured step.
	 *
	 * @param float $price Price.
	 * @return float
	 */
	public static function round_price( $price ) {
		$step = (int) self::get( 'round_to' );
		if ( $step > 1 ) {
			$rounded = (float) ( round( $price / $step ) * $step );
			// Never round a positive price down to zero (a free set could be ordered).
			return $rounded <= 0 && $price > 0 ? (float) ( ceil( $price / $step ) * $step ) : $rounded;
		}
		return (float) round( $price, wc_get_price_decimals() );
	}
}
