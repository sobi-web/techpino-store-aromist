<?php
/**
 * Order screen: what each set item takes from the warehouse.
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin order hooks.
 */
final class ADS_Admin_Order {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'item_box' ), 10, 3 );
	}

	/**
	 * Packing / stock box under a set item.
	 *
	 * @param int           $item_id Item id.
	 * @param WC_Order_Item $item    Item.
	 * @param WC_Product    $product Product.
	 */
	public function item_box( $item_id, $item, $product ) {
		$set = ADS_Stock::snapshot( $item );
		if ( ! $set ) {
			return;
		}
		$qty      = (int) $item->get_quantity();
		$reduced  = $item->get_meta( ADS_Stock::META_REDUCED, true );
		$reduced  = is_array( $reduced ) ? $reduced : null;
		$refunded = (int) $item->get_meta( ADS_Stock::META_REFUNDED, true );
		$box      = absint( isset( $set['box'] ) ? $set['box'] : 0 );
		?>
		<div class="ads-order-box">
			<strong>
				<?php
				/* translators: 1: number of sets, 2: volume, 3: vials per set */
				echo esc_html( sprintf( __( 'بسته‌بندی: %1$s ست × %3$s ویال %2$s', 'aromist-discovery-set' ), number_format_i18n( $qty ), ads_format_ml( $set['v'] ), number_format_i18n( count( (array) $set['perf'] ) ) ) );
				?>
				<?php echo 'custom' === ( isset( $set['source'] ) ? $set['source'] : '' ) ? ' — ' . esc_html__( 'ست ساخت مشتری', 'aromist-discovery-set' ) : ''; ?>
			</strong>
			<ul>
				<?php foreach ( (array) $set['perf'] as $ads_line ) : ?>
					<?php $ads_id = absint( $ads_line['id'] ); ?>
					<li>
						<?php echo esc_html( $ads_line['name'] ); ?>:
						<?php
						/* translators: 1: ml per set, 2: total ml */
						echo esc_html( sprintf( __( '%1$s برای هر ست (با هدررفت)، جمع %2$s', 'aromist-discovery-set' ), ads_format_ml( $ads_line['ml'] ), ads_format_ml( (int) $ads_line['ml'] * $qty ) ) );
						?>
						<?php if ( null !== $reduced ) : ?>
							— <span class="ads-muted">
								<?php
								if ( empty( $reduced[ $ads_id ] ) && ! ADS_Availability::is_limited( $ads_id ) ) {
									esc_html_e( 'موجودی نامحدود (از مخزن کم نشد)', 'aromist-discovery-set' );
								} else {
									/* translators: %s: ml taken */
									echo esc_html( sprintf( __( 'کسرشده از مخزن: %s', 'aromist-discovery-set' ), ads_format_ml( isset( $reduced[ $ads_id ] ) ? (int) $reduced[ $ads_id ] : 0 ) ) );
								}
								?>
							</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
				<?php if ( $box ) : ?>
					<li>
						<?php
						/* translators: 1: box, 2: count */
						echo esc_html( sprintf( __( '%1$s: %2$s عدد', 'aromist-discovery-set' ), ADS_Boxes::name( $box ), number_format_i18n( $qty ) ) );
						?>
						<?php if ( null !== $reduced ) : ?>
							— <span class="ads-muted">
								<?php
								/* translators: %s: count */
								echo esc_html( sprintf( __( 'کسرشده: %s', 'aromist-discovery-set' ), number_format_i18n( isset( $reduced[ $box ] ) ? (int) $reduced[ $box ] : 0 ) ) );
								?>
							</span>
						<?php endif; ?>
					</li>
				<?php endif; ?>
			</ul>
			<?php if ( null === $reduced ) : ?>
				<span class="ads-muted"><?php esc_html_e( 'هنوز از انبار کم نشده (سفارش پرداخت نشده یا لغو شده).', 'aromist-discovery-set' ); ?></span>
			<?php endif; ?>
			<?php if ( $refunded > 0 ) : ?>
				<span class="ads-muted">
					<?php
					/* translators: %s: sets */
					echo esc_html( sprintf( __( '%s ست مرجوع و به انبار برگشت داده شده.', 'aromist-discovery-set' ), number_format_i18n( $refunded ) ) );
					?>
				</span>
			<?php endif; ?>
		</div>
		<?php
	}
}
