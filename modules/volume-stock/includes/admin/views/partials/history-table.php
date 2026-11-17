<?php
/**
 * Ledger rows table.
 *
 * @var array $avs_rows_history Rows.
 * @var bool  $avs_show_product Show the product column.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $avs_rows_history ) ) {
	echo '<p class="avs-muted">' . esc_html__( 'هنوز تغییری ثبت نشده است.', 'aromist-volume-stock' ) . '</p>';
	return;
}

$avs_reason_class = array(
	'order_reduce'   => 'out',
	'order_restore'  => 'ok',
	'refund_restock' => 'ok',
	'order_edit'     => 'info',
	'restock'        => 'ok',
	'initial'        => 'info',
	'waste'          => 'low',
	'count_fix'      => 'info',
	'manual'         => 'info',
	'product_edit'   => 'low',
	'disable'        => 'out',
	'legacy_order'   => 'info',
);
?>
<div class="avs-table-wrap">
	<table class="avs-table avs-table--compact avs-history">
		<thead>
			<tr>
				<th><?php esc_html_e( 'زمان', 'aromist-volume-stock' ); ?></th>
				<?php if ( $avs_show_product ) : ?>
					<th><?php esc_html_e( 'محصول', 'aromist-volume-stock' ); ?></th>
				<?php endif; ?>
				<th><?php esc_html_e( 'نوع', 'aromist-volume-stock' ); ?></th>
				<th><?php esc_html_e( 'تغییر', 'aromist-volume-stock' ); ?></th>
				<th><?php esc_html_e( 'موجودی بعد', 'aromist-volume-stock' ); ?></th>
				<th><?php esc_html_e( 'جزئیات', 'aromist-volume-stock' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $avs_rows_history as $avs_h ) :
				$avs_change = (int) $avs_h['change_ml'];
				$avs_ts     = strtotime( $avs_h['created_at'] . ' UTC' );
				$avs_user   = $avs_h['user_id'] ? get_userdata( $avs_h['user_id'] ) : null;
				$avs_order  = $avs_h['order_id'] ? wc_get_order( $avs_h['order_id'] ) : null;
				?>
				<tr>
					<td class="avs-nowrap" title="<?php echo esc_attr( wp_date( 'Y-m-d H:i:s', $avs_ts ) ); ?>">
						<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' – H:i', $avs_ts ) ); ?>
					</td>
					<?php if ( $avs_show_product ) : ?>
						<td>
							<?php
							$avs_p = wc_get_product( $avs_h['product_id'] );
							if ( $avs_p ) {
								echo '<a href="' . esc_url( AVS_Admin::url( 'avs', array( 'product_id' => $avs_h['product_id'] ) ) ) . '">' . esc_html( $avs_p->get_name() ) . '</a>';
							} else {
								echo esc_html( '#' . $avs_h['product_id'] );
							}
							?>
						</td>
					<?php endif; ?>
					<td>
						<span class="avs-badge avs-badge--<?php echo esc_attr( isset( $avs_reason_class[ $avs_h['reason'] ] ) ? $avs_reason_class[ $avs_h['reason'] ] : 'info' ); ?>"><?php echo esc_html( AVS_Ledger::reason_label( $avs_h['reason'] ) ); ?></span>
					</td>
					<td class="avs-nowrap avs-change <?php echo $avs_change < 0 ? 'is-neg' : ( $avs_change > 0 ? 'is-pos' : '' ); ?>">
						<?php echo AVS_Settings::signed_ml_html( $avs_change ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
					</td>
					<td class="avs-nowrap"><?php echo null === $avs_h['balance_ml'] ? '—' : esc_html( AVS_Settings::format_ml( $avs_h['balance_ml'] ) ); ?></td>
					<td class="avs-details">
						<?php
						$avs_bits = array();
						if ( $avs_h['variation_id'] ) {
							$avs_ml = AVS_Product::variation_ml( $avs_h['variation_id'] );
							if ( $avs_ml ) {
								$avs_q      = abs( (int) $avs_h['quantity'] );
								$avs_bits[] = esc_html( ( $avs_q ? number_format_i18n( $avs_q ) . ' × ' : '' ) . AVS_Settings::format_ml( $avs_ml ) );
							}
						}
						if ( $avs_order ) {
							$avs_bits[] = '<a href="' . esc_url( $avs_order->get_edit_order_url() ) . '">' . esc_html( sprintf( /* translators: %s: order number */ __( 'سفارش #%s', 'aromist-volume-stock' ), $avs_order->get_order_number() ) ) . '</a>';
						} elseif ( $avs_h['order_id'] ) {
							$avs_bits[] = esc_html( '#' . $avs_h['order_id'] );
						}
						if ( $avs_user ) {
							$avs_bits[] = esc_html( $avs_user->display_name );
						}
						if ( '' !== (string) $avs_h['note'] ) {
							$avs_bits[] = '<span class="avs-note">' . esc_html( $avs_h['note'] ) . '</span>';
						}
						echo $avs_bits ? implode( ' · ', $avs_bits ) : '<span class="avs-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part escaped above.
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
