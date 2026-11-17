<?php
/**
 * Card of a ready-made set (archive page, «ست‌های دیگر»).
 *
 * Override: yourtheme/woocommerce/discovery-sets/card.php
 *
 * @package AromistDiscoverySet
 *
 * @var array $card ADS_Pages::set_card().
 */

defined( 'ABSPATH' ) || exit;
?>
<article class="ads-set-card<?php echo $card['available'] ? '' : ' is-unavailable'; ?>">
	<a class="ads-set-card__media" href="<?php echo esc_url( $card['url'] ); ?>" tabindex="-1" aria-hidden="true">
		<?php echo $card['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( $card['price'] && $card['price']['percent'] > 0 ) : ?>
			<span class="ads-badge ads-badge--sale">
				<?php
				/* translators: %s: percent */
				echo esc_html( sprintf( __( '%s٪ تخفیف', 'aromist-discovery-set' ), number_format_i18n( $card['price']['percent'] ) ) );
				?>
			</span>
		<?php endif; ?>
		<?php if ( ! $card['available'] ) : ?>
			<span class="ads-badge ads-badge--out"><?php esc_html_e( 'ناموجود', 'aromist-discovery-set' ); ?></span>
		<?php endif; ?>
	</a>
	<div class="ads-set-card__body">
		<span class="ads-set-card__eyebrow"><?php echo esc_html( $card['label'] ); ?></span>
		<h3 class="ads-set-card__title"><a href="<?php echo esc_url( $card['url'] ); ?>"><?php echo esc_html( $card['name'] ); ?></a></h3>
		<ul class="ads-set-card__thumbs" aria-label="<?php esc_attr_e( 'عطرهای ست', 'aromist-discovery-set' ); ?>">
			<?php foreach ( $card['perfumes'] as $ads_perfume ) : ?>
				<li title="<?php echo esc_attr( $ads_perfume['name'] ); ?>">
					<img src="<?php echo esc_url( $ads_perfume['img_src'] ); ?>" alt="<?php echo esc_attr( $ads_perfume['name'] ); ?>" loading="lazy" width="48" height="48">
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="ads-set-card__names"><?php echo esc_html( ads_join( wp_list_pluck( $card['perfumes'], 'name' ) ) ); ?></p>
		<div class="ads-set-card__foot">
			<span class="ads-set-card__price price"><?php echo $card['price'] ? wp_kses_post( $card['price']['html'] ) : ''; ?></span>
			<a class="ads-btn ads-btn--primary ads-btn--sm" href="<?php echo esc_url( $card['url'] ); ?>"><?php esc_html_e( 'مشاهده و خرید', 'aromist-discovery-set' ); ?></a>
		</div>
	</div>
</article>
