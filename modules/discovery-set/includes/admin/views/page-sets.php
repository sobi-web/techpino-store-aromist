<?php
/**
 * Ready-made sets list.
 *
 * @package AromistDiscoverySet
 *
 * @var int[] $ids Set ids.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ads-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'ست‌های آماده', 'aromist-discovery-set' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product&ads_new=1' ) ); ?>" class="page-title-action"><?php esc_html_e( 'افزودن ست', 'aromist-discovery-set' ); ?></a>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ads-title-form">
		<input type="hidden" name="action" value="ads_sync_all">
		<?php wp_nonce_field( 'ads_sync_all' ); ?>
		<button type="submit" class="button"><?php esc_html_e( 'محاسبه‌ی دوباره‌ی قیمت و موجودی همه', 'aromist-discovery-set' ); ?></button>
	</form>
	<hr class="wp-header-end">

	<?php if ( ! $ids ) : ?>
		<div class="ads-card ads-empty">
			<p><?php esc_html_e( 'هنوز ستی نساخته‌اید. «افزودن ست» را بزنید، نوع محصول «دیسکاوری ست» از قبل انتخاب شده است.', 'aromist-discovery-set' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped ads-sets-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ست', 'aromist-discovery-set' ); ?></th>
					<th><?php esc_html_e( 'عطرها', 'aromist-discovery-set' ); ?></th>
					<th><?php esc_html_e( 'حجم‌ها: قیمت / قابل فروش', 'aromist-discovery-set' ); ?></th>
					<th><?php esc_html_e( 'وضعیت', 'aromist-discovery-set' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $ids as $ads_id ) : ?>
					<?php
					$ads_set = wc_get_product( $ads_id );
					if ( ! $ads_set instanceof WC_Product_Discovery_Set ) {
						continue;
					}
					$ads_error      = ADS_Set::preset_error( $ads_set );
					$ads_components = $ads_set->get_components();
					?>
					<tr>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $ads_id ) ); ?>" class="row-title"><?php echo esc_html( $ads_set->get_name() ); ?></a>
							<?php if ( 'publish' !== $ads_set->get_status() ) : ?>
								<span class="ads-muted">— <?php echo esc_html( get_post_status_object( $ads_set->get_status() ) ? get_post_status_object( $ads_set->get_status() )->label : $ads_set->get_status() ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( ads_join( array_map( array( 'ADS_Set', 'perfume_name' ), $ads_components ) ) ); ?></td>
						<td>
							<?php if ( '' !== $ads_error ) : ?>
								<span class="ads-bad"><?php echo esc_html( $ads_error ); ?></span>
							<?php else : ?>
								<ul class="ads-volume-summary">
									<?php foreach ( $ads_set->get_volumes() as $ads_ml ) : ?>
										<?php
										$ads_b   = ADS_Pricing::breakdown( $ads_components, $ads_ml, $ads_set );
										$ads_max = ADS_Availability::max_sets( ADS_Set::draws( $ads_components, $ads_ml ), array(), false );
										?>
										<li>
											<strong><?php echo esc_html( ads_format_ml( $ads_ml ) ); ?></strong>
											<?php echo $ads_b ? wp_kses_post( wc_price( $ads_b['price'] ) ) : '—'; ?>
											/
											<span class="<?php echo null === $ads_max || $ads_max > 0 ? 'ads-ok' : 'ads-bad'; ?>">
												<?php echo esc_html( null === $ads_max ? __( 'نامحدود', 'aromist-discovery-set' ) : sprintf( /* translators: %s: sets */ __( '%s ست', 'aromist-discovery-set' ), number_format_i18n( $ads_max ) ) ); ?>
											</span>
											<?php if ( $ads_ml === $ads_set->get_default_volume() ) : ?>
												<span class="ads-muted">(<?php esc_html_e( 'پیش‌فرض', 'aromist-discovery-set' ); ?>)</span>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $ads_set->is_in_stock() ) : ?>
								<span class="ads-ok"><?php esc_html_e( 'موجود', 'aromist-discovery-set' ); ?></span>
							<?php else : ?>
								<span class="ads-bad"><?php esc_html_e( 'ناموجود', 'aromist-discovery-set' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
