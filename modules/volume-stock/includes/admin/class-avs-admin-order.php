<?php
/**
 * Order screen: show the volume taken by each item.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order screen.
 */
final class AVS_Admin_Order {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'item_info' ), 10, 3 );
	}

	/**
	 * "2 × 50 ml = 100 ml" under a volume item.
	 *
	 * @param int           $item_id Item id.
	 * @param WC_Order_Item $item    Item.
	 * @param WC_Product    $product Product.
	 */
	public function item_info( $item_id, $item, $product ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return;
		}
		$ml = absint( $item->get_meta( AVS_Order_Item::META_ML, true ) );
		if ( $ml <= 0 ) {
			return;
		}
		$waste    = absint( $item->get_meta( AVS_Order_Item::META_WASTE, true ) );
		$qty      = (int) $item->get_quantity();
		$refunded = abs( (int) $item->get_order()->get_qty_refunded_for_item( $item_id ) );
		$reduced  = (int) $item->get_meta( '_reduced_stock', true );

		$text = sprintf(
			/* translators: 1: qty, 2: ml per unit, 3: total */
			__( 'موجودی حجمی: %1$s × %2$s = %3$s', 'aromist-volume-stock' ),
			number_format_i18n( $qty ),
			AVS_Settings::format_ml( $ml + $waste ),
			AVS_Settings::format_ml( $qty * ( $ml + $waste ) )
		);
		if ( $waste ) {
			/* translators: %s: waste */
			$text .= ' ' . sprintf( __( '(شامل %s هدررفت در هر عدد)', 'aromist-volume-stock' ), AVS_Settings::format_ml( $waste ) );
		}

		$state = $reduced > 0
			/* translators: %s: ml */
			? sprintf( __( 'کسرشده از مخزن: %s', 'aromist-volume-stock' ), AVS_Settings::format_ml( $reduced ) )
			: __( 'هنوز از مخزن کسر نشده', 'aromist-volume-stock' );
		if ( $refunded ) {
			/* translators: %s: units */
			$state .= ' · ' . sprintf( __( '%s عدد مرجوع شده', 'aromist-volume-stock' ), number_format_i18n( $refunded ) );
		}

		echo '<div class="avs-order-item" style="margin-top:6px;padding:6px 10px;border-radius:8px;background:#f3faf8;color:#007a61;font-size:12px;line-height:1.9">'
			. esc_html( $text ) . '<br><span style="color:#6b7280">' . esc_html( $state ) . '</span></div>';
	}
}
