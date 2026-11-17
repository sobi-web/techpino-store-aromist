<?php
/**
 * Order item helpers.
 *
 * Each volume order item stores the ml of one unit at the time of the order:
 *   _avs_ml    → nominal ml of one unit (e.g. 50)
 *   _avs_waste → extra ml consumed per unit (decanting loss)
 * so later changes to the product never change the history of old orders.
 *
 * WooCommerce's own `_reduced_stock` meta of a volume item is stored in ml (see AVS_Stock).
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

/**
 * Static helpers for WC_Order_Item_Product.
 */
final class AVS_Order_Item {

	const META_ML    = '_avs_ml';
	const META_WASTE = '_avs_waste';

	/**
	 * Set on items of orders placed before volume stock was enabled (their `_reduced_stock` was
	 * converted to ml). Movements of these items are real pool changes but not "sales" of the pool.
	 */
	const META_CONVERTED = '_avs_converted';

	/**
	 * Is this order item currently a volume item (its product draws from a volume pool)?
	 *
	 * @param mixed $item Order item.
	 * @return bool
	 */
	public static function is_volume( $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return false;
		}
		return AVS_Product::is_volume_variation( $item->get_product() );
	}

	/**
	 * Ml of one unit for this order item. Uses the ml stored on the item; when missing
	 * (orders created before volume stock was enabled, or added from the admin) the current
	 * variation values are used and, if $assign is true, stored on the item (not saved).
	 *
	 * @param WC_Order_Item_Product $item   Item.
	 * @param bool                  $assign Store missing values on the item object.
	 * @return int 0 when the item is not a volume item.
	 */
	public static function unit_ml( $item, $assign = true ) {
		if ( ! self::is_volume( $item ) ) {
			return 0;
		}

		$ml    = absint( $item->get_meta( self::META_ML, true ) );
		$waste = absint( $item->get_meta( self::META_WASTE, true ) );

		if ( $ml <= 0 ) {
			$variation = $item->get_product();
			$ml        = AVS_Product::variation_ml( $variation->get_id() );
			$waste     = AVS_Product::waste_ml( $variation->get_parent_id() );
			if ( $assign && $ml > 0 ) {
				$item->update_meta_data( self::META_ML, $ml );
				$item->update_meta_data( self::META_WASTE, $waste );
			}
		}

		return $ml > 0 ? $ml + $waste : 0;
	}

	/**
	 * Store the ml values on a new item (checkout).
	 *
	 * @param WC_Order_Item_Product $item Item.
	 */
	public static function stamp( $item ) {
		$product = $item->get_product();
		if ( ! AVS_Product::is_volume_variation( $product ) ) {
			return;
		}
		$item->update_meta_data( self::META_ML, AVS_Product::variation_ml( $product->get_id() ) );
		$item->update_meta_data( self::META_WASTE, AVS_Product::waste_ml( $product->get_parent_id() ) );
	}

	/**
	 * Ledger reason for a movement of this item.
	 *
	 * @param WC_Order_Item_Product $item   Item.
	 * @param string                $reason Normal reason.
	 * @return string
	 */
	public static function ledger_reason( $item, $reason ) {
		return $item->get_meta( self::META_CONVERTED, true ) ? 'legacy_order' : $reason;
	}

	/**
	 * Parent product id of an item.
	 *
	 * @param WC_Order_Item_Product $item Item.
	 * @return int
	 */
	public static function parent_id( $item ) {
		return absint( $item->get_product_id() );
	}
}
