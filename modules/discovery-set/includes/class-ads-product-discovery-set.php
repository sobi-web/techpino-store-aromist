<?php
/**
 * The «دیسکاوری ست» product type.
 *
 * A set never manages stock itself: each sold set takes millilitres from the pools of its four
 * perfumes and one box from the box stock (see ADS_Stock). Its stored price and stock status are
 * kept in sync by ADS_Sync for the default volume, so shop listings, price filters and page
 * builders see normal WooCommerce data.
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Discovery set product.
 */
class WC_Product_Discovery_Set extends WC_Product_Simple {

	const META_COMPONENTS    = '_ads_components';
	const META_COMPONENT     = '_ads_component';
	const META_VOLUMES       = '_ads_volumes';
	const META_DEFAULT       = '_ads_default_volume';
	const META_PRICE_MODE    = '_ads_price_mode';
	const META_FIXED_PRICES  = '_ads_fixed_prices';
	const META_IS_CUSTOM     = '_ads_is_custom';

	/**
	 * Product type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'discovery_set';
	}

	/**
	 * The hidden product that carries sets built by customers.
	 *
	 * @return bool
	 */
	public function is_custom() {
		return 'yes' === $this->get_meta( self::META_IS_CUSTOM, true );
	}

	/**
	 * Perfume (parent product) ids of a ready-made set, in display order.
	 *
	 * @return int[]
	 */
	public function get_components() {
		$ids = $this->get_meta( self::META_COMPONENTS, true );
		return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
	}

	/**
	 * Volumes this set is offered in (only volumes that are still allowed in the settings).
	 *
	 * @return int[]
	 */
	public function get_volumes() {
		$allowed = ADS_Settings::volume_list();
		$own     = $this->get_meta( self::META_VOLUMES, true );
		if ( $this->is_custom() || ! is_array( $own ) || ! $own ) {
			return $allowed;
		}
		$volumes = array_values( array_intersect( $allowed, array_map( 'absint', $own ) ) );
		// A set saved for volumes that are no longer offered is sold in the shop's volumes.
		return $volumes ? $volumes : $allowed;
	}

	/**
	 * Default volume (one of get_volumes(), 0 when none).
	 *
	 * @return int
	 */
	public function get_default_volume() {
		$volumes = $this->get_volumes();
		if ( ! $volumes ) {
			return 0;
		}
		$own = absint( $this->get_meta( self::META_DEFAULT, true ) );
		if ( in_array( $own, $volumes, true ) ) {
			return $own;
		}
		$global = ADS_Settings::default_volume();
		return in_array( $global, $volumes, true ) ? $global : $volumes[0];
	}

	/**
	 * Price mode: 'auto' (sum of the perfume prices) or 'fixed'.
	 *
	 * @return string
	 */
	public function get_price_mode() {
		return 'fixed' === $this->get_meta( self::META_PRICE_MODE, true ) && ! $this->is_custom() ? 'fixed' : 'auto';
	}

	/**
	 * Fixed price of a volume ('' when not set).
	 *
	 * @param int $ml Volume.
	 * @return string
	 */
	public function get_fixed_price( $ml ) {
		$prices = $this->get_meta( self::META_FIXED_PRICES, true );
		return is_array( $prices ) && isset( $prices[ $ml ] ) && '' !== $prices[ $ml ] ? wc_format_decimal( $prices[ $ml ] ) : '';
	}

	/**
	 * A set is bought for a chosen volume, never managed as stock of its own.
	 *
	 * @param string $context Context.
	 * @return bool
	 */
	public function get_manage_stock( $context = 'view' ) {
		return false;
	}

	/**
	 * Sets are physical.
	 *
	 * @param string $context Context.
	 * @return bool
	 */
	public function get_virtual( $context = 'view' ) {
		return false;
	}

	/**
	 * Sets are not downloadable.
	 *
	 * @param string $context Context.
	 * @return bool
	 */
	public function get_downloadable( $context = 'view' ) {
		return false;
	}

	/**
	 * Purchasable when published and (for ready-made sets) correctly configured.
	 * The price is calculated per cart item, so an empty stored price does not block it.
	 *
	 * @return bool
	 */
	public function is_purchasable() {
		if ( $this->is_custom() ) {
			// The private «ست سفارشی» product: every customer may buy sets through it.
			$ok = $this->exists() && in_array( $this->get_status(), array( 'publish', 'private' ), true );
		} else {
			$ok = $this->exists()
				&& ( 'publish' === $this->get_status() || current_user_can( 'edit_post', $this->get_id() ) )
				&& '' === ADS_Set::preset_error( $this );
		}

		return apply_filters( 'woocommerce_is_purchasable', $ok, $this );
	}

	/**
	 * Shop / loop button: go to the product page to choose the volume.
	 *
	 * @return string
	 */
	public function add_to_cart_url() {
		return apply_filters( 'woocommerce_product_add_to_cart_url', $this->get_permalink(), $this );
	}

	/**
	 * Loop button text.
	 *
	 * @return string
	 */
	public function add_to_cart_text() {
		$text = $this->is_purchasable() && $this->is_in_stock() ? __( 'انتخاب حجم ست', 'aromist-discovery-set' ) : __( 'مشاهده ست', 'aromist-discovery-set' );
		return apply_filters( 'woocommerce_product_add_to_cart_text', $text, $this );
	}

	/**
	 * Single page button text.
	 *
	 * @return string
	 */
	public function single_add_to_cart_text() {
		return apply_filters( 'woocommerce_product_single_add_to_cart_text', __( 'افزودن ست به سبد', 'aromist-discovery-set' ), $this );
	}

	/**
	 * No loop AJAX add (a volume must be chosen).
	 *
	 * @param string $feature Feature.
	 * @return bool
	 */
	public function supports( $feature ) {
		if ( 'ajax_add_to_cart' === $feature ) {
			return false;
		}
		return parent::supports( $feature );
	}
}
