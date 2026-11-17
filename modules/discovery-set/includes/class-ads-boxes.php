<?php
/**
 * Boxes (packaging) of the sets.
 *
 * A box is a private simple product that manages WooCommerce stock, so every change is atomic,
 * checkout reservations work through WooCommerce's own reserved-stock table and the usual
 * "low stock" emails are sent. It can never be bought on its own (private + not purchasable).
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Box helpers.
 */
final class ADS_Boxes {

	const META = '_ads_box';

	/**
	 * All box products (id => name).
	 *
	 * @return array
	 */
	public static function all() {
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'private', 'publish', 'draft' ),
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_key'       => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
				'meta_value'     => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$out[ (int) $id ] = self::name( $id );
		}
		return $out;
	}

	/**
	 * Box name (without WordPress's «خصوصی:» prefix of private posts).
	 *
	 * @param int $id Box id.
	 * @return string
	 */
	public static function name( $id ) {
		return (string) get_post_field( 'post_title', $id );
	}

	/**
	 * Is this product id a box?
	 *
	 * @param int $id Product id.
	 * @return bool
	 */
	public static function is_box( $id ) {
		return $id && 'yes' === get_post_meta( $id, self::META, true ) && 'product' === get_post_type( $id );
	}

	/**
	 * Create a box.
	 *
	 * @param string $name  Name.
	 * @param int    $stock Initial stock.
	 * @return int Product id, 0 on failure.
	 * @throws Exception When the product post cannot be created.
	 */
	public static function create( $name, $stock = 0 ) {
		$product = ADS_Install::new_product( 'WC_Product_Simple', $name, 'private' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( max( 0, (int) $stock ) );
		$product->set_backorders( 'no' );
		$product->set_sold_individually( false );
		$product->set_regular_price( '' );
		$product->update_meta_data( self::META, 'yes' );
		$id = $product->save();
		return (int) $id;
	}

	/**
	 * On first setup: create «جعبه دیسکاوری ست» and use it for every volume.
	 */
	public static function ensure_default_box() {
		if ( self::all() ) {
			return;
		}
		$id = self::create( __( 'جعبه دیسکاوری ست', 'aromist-discovery-set' ), 0 );
		if ( ! $id ) {
			return;
		}
		$rows = array();
		foreach ( ADS_Settings::volumes() as $row ) {
			$row['box'] = $id;
			$rows[]     = $row;
		}
		ADS_Settings::update( array( 'volumes' => $rows ) );
	}

	/**
	 * Box used by a volume (0 = boxes are not counted for it).
	 *
	 * @param int $ml Volume.
	 * @return int
	 */
	public static function for_volume( $ml ) {
		$row = ADS_Settings::volume( $ml );
		$id  = $row ? (int) $row['box'] : 0;
		return self::is_box( $id ) ? $id : 0;
	}

	/**
	 * Stock of a box.
	 *
	 * @param int $id Box id.
	 * @return int
	 */
	public static function stock( $id ) {
		return (int) wc_stock_amount( get_post_meta( $id, '_stock', true ) );
	}

	/**
	 * Change the stock of a box from the settings page.
	 *
	 * @param int    $id        Box id.
	 * @param string $operation set|increase|decrease.
	 * @param int    $amount    Amount.
	 * @return int|WP_Error New stock.
	 */
	public static function adjust( $id, $operation, $amount ) {
		$product = wc_get_product( $id );
		if ( ! $product || ! self::is_box( $id ) ) {
			return new WP_Error( 'ads_no_box', __( 'جعبه پیدا نشد.', 'aromist-discovery-set' ) );
		}
		$amount = max( 0, (int) $amount );
		if ( ! in_array( $operation, array( 'set', 'increase', 'decrease' ), true ) ) {
			$operation = 'set';
		}
		$new = wc_update_product_stock( $product, $amount, $operation );
		return is_wp_error( $new ) ? $new : (int) $new;
	}

	/**
	 * Boxes can never be bought on their own.
	 *
	 * @param bool       $purchasable Purchasable.
	 * @param WC_Product $product     Product.
	 * @return bool
	 */
	public static function not_purchasable( $purchasable, $product ) {
		if ( $product instanceof WC_Product && 'yes' === $product->get_meta( self::META, true ) ) {
			return false;
		}
		return $purchasable;
	}
}
