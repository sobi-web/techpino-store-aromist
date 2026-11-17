<?php
/**
 * «ست‌های من» in the customer account (Techpino user panel section).
 *
 * Override: yourtheme/woocommerce/discovery-sets/my-sets.php
 *
 * @package AromistDiscoverySet
 *
 * @var array[] $sets        ADS_Saved_Sets::card() of each saved set.
 * @var string  $builder_url Builder page URL.
 * @var int     $max         Most sets a customer can keep.
 * @var string  $nonce       Nonce of the save / delete requests.
 * @var int     $product     The «ست سفارشی» product id.
 * @var bool    $in_panel    Rendered inside the Techpino panel.
 */

defined( 'ABSPATH' ) || exit;

$ads_icon = static function ( $name ) {
	$paths = array(
		'cart'   => 'M5.2 8.5h13.6l-1 10.4a2.3 2.3 0 0 1-2.3 2.1H8.5a2.3 2.3 0 0 1-2.3-2.1zM8.8 8.5V7a3.2 3.2 0 0 1 6.4 0v1.5',
		'pencil' => 'M15.5 4.5l4 4L9 19H5v-4zM13.5 6.5l4 4',
		'trash'  => 'M4.5 7h15M9.5 7V5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v2M6.5 7l.8 12a1.5 1.5 0 0 0 1.5 1.4h6.4a1.5 1.5 0 0 0 1.5-1.4l.8-12',
		'plus'   => 'M12 5v14M5 12h14',
		'box'    => 'M3.8 7.5 12 3.5l8.2 4v9L12 20.5l-8.2-4zM3.8 7.5 12 11.5l8.2-4M12 11.5v9',
		'alert'  => 'M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17zM12 7.8v5M12 16.2v.1',
	);
	return '<svg class="ads-ico" aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="' . esc_attr( $paths[ $name ] ) . '"/></svg>';
};
?>
<div class="ads-mysets<?php echo $in_panel ? ' tpup-page' : ''; ?>" data-ads-mysets data-nonce="<?php echo esc_attr( $nonce ); ?>" data-product="<?php echo esc_attr( $product ); ?>">
	<div class="ads-mysets__head<?php echo $in_panel ? ' tpup-page-head' : ''; ?>">
		<div>
			<h1 class="ads-mysets__title<?php echo $in_panel ? ' tpup-page-head__title' : ''; ?>"><?php esc_html_e( 'ست‌های من', 'aromist-discovery-set' ); ?></h1>
			<p class="ads-mysets__sub<?php echo $in_panel ? ' tpup-page-head__sub' : ''; ?>" data-ads-mysets-count>
				<?php
				echo esc_html(
					$sets
						/* translators: 1: number of sets, 2: maximum */
						? sprintf( __( '%1$s ست ذخیره‌شده (حداکثر %2$s)', 'aromist-discovery-set' ), number_format_i18n( count( $sets ) ), number_format_i18n( $max ) )
						: __( 'دیسکاوری ست‌هایی که خودت ساخته و ذخیره کرده‌ای', 'aromist-discovery-set' )
				);
				?>
			</p>
		</div>
		<?php if ( $builder_url && $sets ) : ?>
			<a class="ads-mbtn ads-mbtn--primary" href="<?php echo esc_url( $builder_url ); ?>"><?php echo $ads_icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'ساخت ست جدید', 'aromist-discovery-set' ); ?></span></a>
		<?php endif; ?>
	</div>

	<div class="ads-mysets__empty" data-ads-mysets-empty <?php echo $sets ? 'hidden' : ''; ?>>
		<span class="ads-mysets__empty-icon"><?php echo $ads_icon( 'box' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<p class="ads-mysets__empty-title"><?php esc_html_e( 'هنوز ستی ذخیره نکرده‌ای', 'aromist-discovery-set' ); ?></p>
		<p class="ads-mysets__empty-text"><?php echo esc_html( sprintf( /* translators: %s: number of perfumes */ __( '%s عطر دلخواهت را انتخاب کن و ست را برای خرید بعدی ذخیره کن.', 'aromist-discovery-set' ), number_format_i18n( ads_set_size() ) ) ); ?></p>
		<?php if ( $builder_url ) : ?>
			<a class="ads-mbtn ads-mbtn--primary" href="<?php echo esc_url( $builder_url ); ?>"><?php esc_html_e( 'ساخت دیسکاوری ست', 'aromist-discovery-set' ); ?></a>
		<?php endif; ?>
	</div>

	<?php if ( $sets ) : ?>
		<div class="ads-mysets__grid">
			<?php foreach ( $sets as $ads_set ) : ?>
				<article class="ads-mset<?php echo $ads_set['can_buy'] ? '' : ' has-problem'; ?>" data-ads-mset="<?php echo esc_attr( $ads_set['id'] ); ?>" data-name="<?php echo esc_attr( $ads_set['name'] ); ?>" data-components="<?php echo esc_attr( implode( ',', $ads_set['c'] ) ); ?>">
					<header class="ads-mset__head">
						<div>
							<h2 class="ads-mset__name"><?php echo esc_html( $ads_set['name'] ); ?></h2>
							<?php if ( $ads_set['date'] ) : ?>
								<span class="ads-mset__date"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'ذخیره: %s', 'aromist-discovery-set' ), date_i18n( get_option( 'date_format' ), $ads_set['date'] ) ) ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( $ads_set['price'] ) : ?>
							<div class="ads-mset__price price"><?php echo wp_kses_post( $ads_set['price']['html'] ); ?></div>
						<?php endif; ?>
					</header>

					<ul class="ads-mset__perfumes">
						<?php foreach ( $ads_set['perfumes'] as $ads_p ) : ?>
							<li>
								<img src="<?php echo esc_url( $ads_p['thumb'] ); ?>" alt="" width="44" height="44" loading="lazy">
								<span>
									<?php if ( $ads_p['url'] ) : ?>
										<a href="<?php echo esc_url( $ads_p['url'] ); ?>"><?php echo esc_html( $ads_p['name'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $ads_p['name'] ); ?>
									<?php endif; ?>
									<?php if ( $ads_p['family'] ) : ?>
										<small><?php echo esc_html( $ads_p['family'] ); ?></small>
									<?php endif; ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>

					<?php if ( $ads_set['problems'] ) : ?>
						<div class="ads-mset__problems">
							<?php echo $ads_icon( 'alert' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<div>
								<?php foreach ( $ads_set['problems'] as $ads_problem ) : ?>
									<p><?php echo esc_html( $ads_problem ); ?></p>
								<?php endforeach; ?>
								<p><?php esc_html_e( 'ست را ویرایش کن و عطر دیگری جایگزین کن.', 'aromist-discovery-set' ); ?></p>
							</div>
						</div>
					<?php endif; ?>

					<footer class="ads-mset__actions">
						<button type="button" class="ads-mbtn ads-mbtn--primary" data-ads-mset-buy <?php disabled( ! $ads_set['can_buy'] ); ?>><?php echo $ads_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'خرید', 'aromist-discovery-set' ); ?></span></button>
						<?php if ( $builder_url ) : ?>
							<a class="ads-mbtn ads-mbtn--soft" href="<?php echo esc_url( $ads_set['edit_url'] ); ?>"><?php echo $ads_icon( 'pencil' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'ویرایش', 'aromist-discovery-set' ); ?></span></a>
						<?php endif; ?>
						<button type="button" class="ads-mbtn ads-mbtn--danger" data-ads-mset-delete aria-label="<?php echo esc_attr( sprintf( /* translators: %s: set name */ __( 'حذف %s', 'aromist-discovery-set' ), $ads_set['name'] ) ); ?>"><?php echo $ads_icon( 'trash' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'حذف', 'aromist-discovery-set' ); ?></span></button>
					</footer>
					<p class="ads-mset__msg" role="status" aria-live="polite" hidden></p>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
