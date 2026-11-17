<?php
/**
 * Preview table of a set: price and stock per volume.
 *
 * @package AromistDiscoverySet
 *
 * @var WC_Product_Discovery_Set $set        Set.
 * @var int[]                    $components Perfume ids.
 * @var int[]                    $volumes    Volumes.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ads-preview__scroll">
	<table class="widefat striped ads-preview__table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'حجم', 'aromist-discovery-set' ); ?></th>
				<?php foreach ( $components as $ads_id ) : ?>
					<th><?php echo esc_html( ADS_Set::perfume_name( $ads_id ) ); ?></th>
				<?php endforeach; ?>
				<th><?php esc_html_e( 'جمع', 'aromist-discovery-set' ); ?></th>
				<th><?php esc_html_e( 'بسته‌بندی', 'aromist-discovery-set' ); ?></th>
				<th><?php esc_html_e( 'قیمت ست', 'aromist-discovery-set' ); ?></th>
				<th><?php esc_html_e( 'قابل فروش', 'aromist-discovery-set' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $volumes as $ads_ml ) : ?>
				<?php
				$ads_b     = ADS_Pricing::breakdown( $components, $ads_ml, $set );
				$ads_draws = ADS_Set::draws( $components, $ads_ml );
				$ads_max   = ADS_Availability::max_sets( $ads_draws, array(), false );
				$ads_short = ADS_Availability::shortages( $ads_draws, 1, array(), false );
				?>
				<tr>
					<th scope="row"><?php echo esc_html( ads_format_ml( $ads_ml ) ); ?></th>
					<?php foreach ( $components as $ads_id ) : ?>
						<td>
							<?php
							$ads_line = $ads_b && isset( $ads_b['lines'][ $ads_id ] ) ? $ads_b['lines'][ $ads_id ] : null;
							if ( $ads_line ) {
								echo wp_kses_post( wc_price( $ads_line['price'] ) );
								if ( ! $ads_line['exact'] ) {
									/* translators: %s: volume the price per ml comes from */
									echo '<br><small class="ads-muted">' . esc_html( sprintf( __( 'از قیمت %s', 'aromist-discovery-set' ), ads_format_ml( $ads_line['from_ml'] ) ) ) . '</small>';
								}
							} else {
								echo '—';
							}
							$ads_pool = ADS_Availability::stock( $ads_id );
							/* translators: 1: needed per set, 2: pool */
							echo '<br><small class="ads-muted">' . esc_html( sprintf( __( 'هر ست %1$s، مخزن %2$s', 'aromist-discovery-set' ), ads_format_ml( ADS_Set::unit_ml( $ads_id, $ads_ml ) ), ads_format_ml( $ads_pool ) ) ) . '</small>';
							?>
						</td>
					<?php endforeach; ?>
					<td><?php echo $ads_b ? wp_kses_post( wc_price( $ads_b['sum'] ) ) : '—'; ?></td>
					<td><?php echo $ads_b ? wp_kses_post( wc_price( $ads_b['fee'] ) ) : '—'; ?></td>
					<td>
						<?php
						if ( $ads_b ) {
							echo $ads_b['price'] < $ads_b['regular'] ? wp_kses_post( wc_format_sale_price( $ads_b['regular'], $ads_b['price'] ) ) : wp_kses_post( wc_price( $ads_b['price'] ) );
							if ( $ads_b['fixed'] ) {
								echo '<br><small class="ads-muted">' . esc_html__( 'قیمت ثابت', 'aromist-discovery-set' ) . '</small>';
							} elseif ( $ads_b['discount'] > 0 ) {
								/* translators: %s: percent */
								echo '<br><small class="ads-muted">' . esc_html( sprintf( __( 'با %s٪ تخفیف', 'aromist-discovery-set' ), ads_format_percent( $ads_b['discount'] ) ) ) . '</small>';
							}
						} else {
							echo '—';
						}
						?>
					</td>
					<td>
						<?php
						if ( $ads_short ) {
							echo '<span class="ads-bad">' . esc_html__( 'ناموجود', 'aromist-discovery-set' ) . '</span>';
							foreach ( $ads_short as $ads_pid => $ads_s ) {
								echo '<br><small class="ads-muted">' . esc_html( ADS_Availability::shortage_message( $ads_pid, $ads_s ) ) . '</small>';
							}
						} else {
							$ads_box = ADS_Boxes::for_volume( $ads_ml );
							echo '<span class="ads-ok">' . esc_html( null === $ads_max ? __( 'نامحدود', 'aromist-discovery-set' ) : sprintf( /* translators: %s: number of sets */ __( '%s ست', 'aromist-discovery-set' ), number_format_i18n( $ads_max ) ) ) . '</span>';
							if ( $ads_box ) {
								/* translators: 1: box, 2: stock */
								echo '<br><small class="ads-muted">' . esc_html( sprintf( __( '%1$s: %2$s عدد', 'aromist-discovery-set' ), ADS_Boxes::name( $ads_box ), number_format_i18n( ADS_Boxes::stock( $ads_box ) ) ) ) . '</small>';
							}
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
