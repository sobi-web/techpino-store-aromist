<?php
/**
 * One perfume of a set with everything its product page tells: images (main + gallery), brand,
 * English name, olfactory family, short description, specs and top / heart / base notes. The family,
 * the notes and the spec values link to their own pages, like on the product page.
 *
 * Override: yourtheme/woocommerce/discovery-sets/perfume.php
 *
 * @package AromistDiscoverySet
 *
 * @var array $perfume ADS_Pages::perfume_card().
 * @var int   $index   Position in the set (0-based).
 * @var int   $volume  Volume of the set (ml).
 */

defined( 'ABSPATH' ) || exit;

$ads_levels = ADS_Perfume_Info::note_labels();
$ads_links  = isset( $perfume['note_links'] ) ? $perfume['note_links'] : array();
$ads_images = ADS_Perfume_Info::image_ids( $perfume );
$ads_shown  = ADS_Perfume_Info::notes_limit();
$ads_values = ADS_Perfume_Info::spec_limit();
?>
<div class="ads-pinfo">
	<div class="ads-pinfo__gallery" data-ads-pgallery>
		<div class="ads-pinfo__media">
			<span class="ads-pinfo__num" aria-hidden="true"><?php echo esc_html( number_format_i18n( $index + 1 ) ); ?></span>
			<?php
			$ads_attr = array(
				'loading'        => 'lazy',
				'alt'            => $perfume['name'],
				'data-ads-pmain' => '1',
			);
			echo $ads_images // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				? wp_get_attachment_image( $ads_images[0], 'woocommerce_single', false, $ads_attr )
				: wc_placeholder_img( 'woocommerce_single', $ads_attr );
			?>
			<span class="ads-pinfo__vial"><?php echo esc_html( ads_format_ml( $volume ) ); ?></span>
		</div>
		<?php if ( count( $ads_images ) > 1 ) : ?>
			<div class="ads-pinfo__thumbs" role="group" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: perfume */ __( 'تصاویر %s', 'aromist-discovery-set' ), $perfume['name'] ) ); ?>">
				<?php foreach ( $ads_images as $ads_i => $ads_image_id ) : ?>
					<button type="button" class="ads-pinfo__thumb<?php echo 0 === $ads_i ? ' is-active' : ''; ?>" data-ads-pthumb
						data-src="<?php echo esc_url( (string) wp_get_attachment_image_url( $ads_image_id, 'woocommerce_single' ) ); ?>"
						data-srcset="<?php echo esc_attr( (string) wp_get_attachment_image_srcset( $ads_image_id, 'woocommerce_single' ) ); ?>"
						aria-pressed="<?php echo 0 === $ads_i ? 'true' : 'false'; ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: number */ __( 'تصویر %s', 'aromist-discovery-set' ), number_format_i18n( $ads_i + 1 ) ) ); ?>">
						<?php echo wp_get_attachment_image( $ads_image_id, 'woocommerce_gallery_thumbnail', false, array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="ads-pinfo__body">
		<div class="ads-pinfo__head">
			<?php if ( $perfume['brand'] ) : ?>
				<span class="ads-pinfo__brand"><?php echo esc_html( $perfume['brand'] ); ?></span>
			<?php endif; ?>
			<h3 class="ads-pinfo__name"><?php echo esc_html( $perfume['name'] ); ?></h3>
			<?php if ( $perfume['english'] ) : ?>
				<span class="ads-pinfo__en" dir="ltr" lang="en"><?php echo esc_html( $perfume['english'] ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $perfume['families'] ) ) : ?>
			<p class="ads-family"><span><?php esc_html_e( 'گروه بویایی:', 'aromist-discovery-set' ); ?></span> <strong><?php echo ads_link_list( $perfume['families'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></strong></p>
		<?php endif; ?>

		<?php if ( $perfume['excerpt'] ) : ?>
			<p class="ads-pinfo__excerpt"><?php echo esc_html( $perfume['excerpt'] ); ?></p>
		<?php endif; ?>

		<?php if ( $perfume['specs'] ) : ?>
			<dl class="ads-specs">
				<?php foreach ( $perfume['specs'] as $ads_spec ) : ?>
					<div class="ads-spec">
						<dt><?php echo esc_html( $ads_spec['label'] ); ?></dt>
						<dd><?php echo ads_link_list( array_slice( $ads_spec['values'], 0, $ads_values ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
		<?php endif; ?>

		<?php if ( array_filter( $ads_links ) ) : ?>
			<div class="ads-notes">
				<?php foreach ( $ads_levels as $ads_level => $ads_label ) : ?>
					<?php
					if ( empty( $ads_links[ $ads_level ] ) ) {
						continue;
					}
					$ads_list = $ads_links[ $ads_level ];
					$ads_rest = count( $ads_list ) - $ads_shown;
					?>
					<div class="ads-note ads-note--<?php echo esc_attr( $ads_level ); ?>">
						<span class="ads-note__icon" aria-hidden="true"><svg viewBox="0 0 32 28" width="30" height="26"><path class="t" d="M16 1 22.4 11H9.6z"/><path class="h" d="M9 12h14l5.6 8.5H3.4z"/><path class="b" d="M2.8 21.5h26.4L31 27H1z"/></svg></span>
						<span class="ads-note__label"><?php echo esc_html( $ads_label ); ?>:</span>
						<span class="ads-note__list">
							<?php echo ads_link_list( array_slice( $ads_list, 0, $ads_shown ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?><?php if ( $ads_rest > 0 ) : ?><span class="ads-note__extra" hidden>، <?php echo ads_link_list( array_slice( $ads_list, $ads_shown ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></span>
								<button type="button" class="ads-note__more" data-ads-more aria-expanded="false" data-less="<?php esc_attr_e( 'نمایش کمتر', 'aromist-discovery-set' ); ?>"><?php echo esc_html( sprintf( /* translators: %s: number of more notes */ __( 'نمایش بیشتر (%s)', 'aromist-discovery-set' ), number_format_i18n( $ads_rest ) ) ); ?></button>
							<?php endif; ?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( $perfume['url'] ) : ?>
			<a class="ads-link ads-pinfo__more" href="<?php echo esc_url( $perfume['url'] ); ?>">
				<?php esc_html_e( 'مشاهده‌ی عطر و خرید شیشه‌ی کامل', 'aromist-discovery-set' ); ?>
				<svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6"/></svg>
			</a>
		<?php endif; ?>
	</div>
</div>
