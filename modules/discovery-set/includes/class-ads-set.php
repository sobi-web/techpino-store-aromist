<?php
/**
 * Set rules: which perfumes may be in a set, validation, and what one set takes from stock.
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Static set helpers.
 */
final class ADS_Set {

	/**
	 * Perfume meta: «yes» when the admin offers the perfume in «ساخت دیسکاوری ست اختصاصی».
	 */
	const META_IN_BUILDER = '_ads_in_builder';

	/**
	 * Request cache for perfume checks. id => '' | error.
	 *
	 * @var array
	 */
	private static $perfume_errors = array();

	/**
	 * Clear request caches.
	 */
	public static function reset_cache() {
		self::$perfume_errors = array();
	}

	/*
	|--------------------------------------------------------------------------
	| Perfumes
	|--------------------------------------------------------------------------
	*/

	/**
	 * Why a product cannot be in a set ('' = it can).
	 *
	 * A perfume must be a published variable product with volume stock (Techpino Volume Stock)
	 * and at least one priced volume variation.
	 *
	 * @param int $perfume_id Parent product id.
	 * @return string
	 */
	public static function perfume_error( $perfume_id ) {
		$perfume_id = absint( $perfume_id );
		if ( isset( self::$perfume_errors[ $perfume_id ] ) ) {
			return self::$perfume_errors[ $perfume_id ];
		}

		$product = $perfume_id ? wc_get_product( $perfume_id ) : null;
		$error   = '';

		if ( ! $product instanceof WC_Product_Variable ) {
			$error = __( 'عطر پیدا نشد یا محصول متغیر نیست.', 'aromist-discovery-set' );
		} elseif ( 'publish' !== $product->get_status() ) {
			/* translators: %s: perfume */
			$error = sprintf( __( '«%s» منتشر نشده است.', 'aromist-discovery-set' ), $product->get_name() );
		} elseif ( ! AVS_Product::is_enabled( $perfume_id ) ) {
			/* translators: %s: perfume */
			$error = sprintf( __( 'موجودی حجمی «%s» فعال نیست.', 'aromist-discovery-set' ), $product->get_name() );
		} elseif ( ! ADS_Pricing::price_base( $perfume_id ) ) {
			/* translators: %s: perfume */
			$error = sprintf( __( '«%s» هیچ حجم قیمت‌داری ندارد.', 'aromist-discovery-set' ), $product->get_name() );
		}

		self::$perfume_errors[ $perfume_id ] = $error;
		return $error;
	}

	/**
	 * Perfume name.
	 *
	 * @param int $perfume_id Id.
	 * @return string
	 */
	public static function perfume_name( $perfume_id ) {
		$product = wc_get_product( $perfume_id );
		return $product ? $product->get_name() : '#' . absint( $perfume_id );
	}

	/**
	 * Ml one set takes from a perfume's pool: volume + the perfume's decanting loss.
	 *
	 * @param int $perfume_id Id.
	 * @param int $volume     Volume.
	 * @return int
	 */
	public static function unit_ml( $perfume_id, $volume ) {
		return (int) $volume + AVS_Product::waste_ml( $perfume_id );
	}

	/**
	 * Search perfumes that can be put in a set (admin picker).
	 *
	 * @param string $term  Search text.
	 * @param int    $limit Max results.
	 * @return array id => name
	 */
	public static function search_perfumes( $term, $limit = 30 ) {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_key'       => AVS_Product::META_ENABLED, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
			'meta_value'     => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
		);
		$term = trim( (string) $term );
		if ( '' !== $term ) {
			if ( ctype_digit( $term ) ) {
				$args['post__in'] = array( absint( $term ) );
			} else {
				$args['s'] = $term;
			}
		}
		$out = array();
		foreach ( get_posts( $args ) as $id ) {
			if ( '' === self::perfume_error( $id ) ) {
				$out[ (int) $id ] = self::perfume_name( $id );
			}
		}
		return $out;
	}

	/*
	|--------------------------------------------------------------------------
	| Perfumes of the builder (chosen by the admin)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Is the perfume offered in the builder (customer-built sets)?
	 *
	 * @param int $perfume_id Perfume id.
	 * @return bool
	 */
	public static function in_builder( $perfume_id ) {
		return 'yes' === get_post_meta( absint( $perfume_id ), self::META_IN_BUILDER, true );
	}

	/**
	 * Offer / stop offering a perfume in the builder.
	 *
	 * @param int  $perfume_id Perfume id.
	 * @param bool $on         Offer it.
	 */
	public static function set_in_builder( $perfume_id, $on ) {
		$perfume_id = absint( $perfume_id );
		if ( ! $perfume_id || 'product' !== get_post_type( $perfume_id ) ) {
			return;
		}
		if ( $on ) {
			update_post_meta( $perfume_id, self::META_IN_BUILDER, 'yes' );
		} else {
			delete_post_meta( $perfume_id, self::META_IN_BUILDER );
		}
	}

	/**
	 * Perfumes customers can choose in the builder: chosen by the admin and sellable in a set
	 * (published, volume stock on, priced).
	 *
	 * @return int[] Ids, by name.
	 */
	public static function builder_ids() {
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query
					array(
						'key'   => self::META_IN_BUILDER,
						'value' => 'yes',
					),
					array(
						'key'   => AVS_Product::META_ENABLED,
						'value' => 'yes',
					),
				),
			)
		);
		return array_values(
			array_filter(
				array_map( 'intval', $ids ),
				static function ( $id ) {
					return '' === self::perfume_error( $id );
				}
			)
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Selections (4 perfumes + a volume)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Clean a list of perfume ids (keeps order, removes empties).
	 *
	 * @param mixed $ids Ids.
	 * @return int[]
	 */
	public static function clean_ids( $ids ) {
		if ( ! is_array( $ids ) ) {
			$ids = '' === (string) $ids ? array() : explode( ',', (string) $ids );
		}
		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}

	/**
	 * Why a selection cannot be sold ('' = valid). Stock is checked separately.
	 *
	 * @param int[]                         $components Perfume ids.
	 * @param int                           $volume     Volume.
	 * @param WC_Product_Discovery_Set|null $product    Set product (limits the volumes).
	 * @return string
	 */
	public static function selection_error( array $components, $volume, $product = null ) {
		$size = ads_set_size();
		if ( count( $components ) !== $size ) {
			/* translators: %s: number of perfumes */
			return sprintf( __( 'هر ست باید دقیقاً %s عطر داشته باشد.', 'aromist-discovery-set' ), number_format_i18n( $size ) );
		}
		if ( count( array_unique( $components ) ) !== count( $components ) ) {
			return __( 'هر عطر فقط یک بار می‌تواند در ست باشد.', 'aromist-discovery-set' );
		}
		$volumes = $product instanceof WC_Product_Discovery_Set ? $product->get_volumes() : ADS_Settings::volume_list();
		if ( ! in_array( (int) $volume, $volumes, true ) ) {
			return __( 'این حجم برای ست قابل انتخاب نیست.', 'aromist-discovery-set' );
		}
		foreach ( $components as $id ) {
			$error = self::perfume_error( $id );
			if ( '' !== $error ) {
				return $error;
			}
		}
		if ( $product instanceof WC_Product_Discovery_Set && $product->is_custom() ) {
			foreach ( $components as $id ) {
				if ( ! self::in_builder( $id ) ) {
					/* translators: %s: perfume */
					return sprintf( __( '«%s» برای ست اختصاصی قابل انتخاب نیست.', 'aromist-discovery-set' ), self::perfume_name( $id ) );
				}
			}
		}
		$breakdown = ADS_Pricing::breakdown( $components, $volume, $product instanceof WC_Product_Discovery_Set ? $product : null );
		if ( null === $breakdown || $breakdown['price'] <= 0 ) {
			return __( 'قیمت این ست صفر یا نامعتبر است؛ قیمت عطرها یا قیمت ثابت ست را بررسی کنید.', 'aromist-discovery-set' );
		}
		return '';
	}

	/**
	 * What one set takes from stock: [ [ 'p' => product id, 'a' => amount ], ... ].
	 * Perfumes: ml from the parent's pool. Box: 1 unit (when boxes are counted for the volume).
	 *
	 * @param int[] $components Perfume ids.
	 * @param int   $volume     Volume.
	 * @return array
	 */
	public static function draws( array $components, $volume ) {
		$draws = array();
		foreach ( $components as $id ) {
			$draws[] = array(
				'p' => (int) $id,
				'a' => self::unit_ml( $id, $volume ),
			);
		}
		$box = ADS_Boxes::for_volume( $volume );
		if ( $box ) {
			$draws[] = array(
				'p' => $box,
				'a' => 1,
			);
		}
		return $draws;
	}

	/*
	|--------------------------------------------------------------------------
	| Ready-made sets
	|--------------------------------------------------------------------------
	*/

	/**
	 * Why a ready-made set cannot be sold ('' = OK).
	 *
	 * @param WC_Product_Discovery_Set $product Set.
	 * @return string
	 */
	public static function preset_error( $product ) {
		if ( ! $product instanceof WC_Product_Discovery_Set ) {
			return __( 'محصول دیسکاوری ست نیست.', 'aromist-discovery-set' );
		}
		if ( $product->is_custom() ) {
			return '';
		}
		$volumes = $product->get_volumes();
		if ( ! $volumes ) {
			return __( 'هیچ حجمی برای این ست انتخاب نشده است.', 'aromist-discovery-set' );
		}
		return self::selection_error( $product->get_components(), $volumes[0], $product );
	}

	/**
	 * Ready-made sets (all statuses except trash).
	 *
	 * @param int $perfume_id Only sets containing this perfume (0 = all).
	 * @return int[]
	 */
	public static function preset_ids( $perfume_id = 0 ) {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_tax_query
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => 'discovery_set',
				),
			),
		);
		if ( $perfume_id ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query
				array(
					'key'   => WC_Product_Discovery_Set::META_COMPONENT,
					'value' => absint( $perfume_id ),
				),
			);
		}
		$custom = absint( ADS_Settings::get( 'custom_product_id' ) );
		return array_values( array_diff( array_map( 'intval', get_posts( $args ) ), array( $custom ) ) );
	}

	/**
	 * Store the components of a ready-made set (ordered list + one indexed row per perfume).
	 *
	 * @param WC_Product_Discovery_Set $product    Set (not saved here).
	 * @param int[]                    $components Ids.
	 */
	public static function set_components( $product, array $components ) {
		$components = self::clean_ids( $components );
		$product->update_meta_data( WC_Product_Discovery_Set::META_COMPONENTS, $components );
		$product->delete_meta_data( WC_Product_Discovery_Set::META_COMPONENT );
		foreach ( array_unique( $components ) as $id ) {
			$product->add_meta_data( WC_Product_Discovery_Set::META_COMPONENT, $id );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Customer-built sets
	|--------------------------------------------------------------------------
	*/

	/**
	 * The hidden «ست سفارشی» product that carries customer-built sets (created when missing).
	 *
	 * It is private: it never appears in shop listings, page-builder queries or sitemaps, and
	 * it is still purchasable as a set (see WC_Product_Discovery_Set::is_purchasable()).
	 *
	 * @return int
	 */
	public static function custom_product_id() {
		$id = absint( ADS_Settings::get( 'custom_product_id' ) );
		if ( $id && 'product' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) {
			$product = wc_get_product( $id );
			if ( $product instanceof WC_Product_Discovery_Set && $product->is_custom() ) {
				if ( 'private' !== $product->get_status() ) {
					$product->set_status( 'private' );
					$product->save();
				}
				return $id;
			}
		}

		$product = ADS_Install::new_product( 'WC_Product_Discovery_Set', __( 'دیسکاوری ست اختصاصی', 'aromist-discovery-set' ), 'private' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_stock_status( 'instock' );
		$product->update_meta_data( WC_Product_Discovery_Set::META_IS_CUSTOM, 'yes' );
		$id = (int) $product->save();

		if ( $id ) {
			ADS_Settings::update( array( 'custom_product_id' => $id ) );
		}
		return $id;
	}

	/**
	 * The «ست سفارشی» product id, without creating it (0 when missing). Safe on visitor requests.
	 *
	 * @return int
	 */
	public static function custom_product_id_if_exists() {
		$id = absint( ADS_Settings::get( 'custom_product_id' ) );
		return $id && 'product' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ? $id : 0;
	}

	/**
	 * Is this the customer-built set product?
	 *
	 * @param mixed $product Product.
	 * @return bool
	 */
	public static function is_custom_product( $product ) {
		return $product instanceof WC_Product_Discovery_Set && $product->is_custom();
	}
}
