<?php
/**
 * Add-to-cart form of a ready-made discovery set.
 *
 * Override: yourtheme/woocommerce/single-product/add-to-cart/discovery-set.php
 *
 * @package AromistDiscoverySet
 *
 * @var WC_Product_Discovery_Set $product     Set.
 * @var WC_Product[]             $perfumes    The four perfumes.
 * @var array                    $volumes     ADS_Frontend::volume_data().
 * @var int                      $selected    Selected volume.
 * @var bool                     $purchasable Can be bought now.
 */

defined( 'ABSPATH' ) || exit;

$ads_any_available = false;
foreach ( $volumes as $ads_row ) {
	$ads_any_available = $ads_any_available || $ads_row['available'];
}
$ads_current = isset( $volumes[ $selected ] ) ? $volumes[ $selected ] : null;

do_action( 'woocommerce_before_add_to_cart_form' ); ?>

<form class="cart ads-form" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data" data-ads-volumes="<?php echo esc_attr( wp_json_encode( $volumes ) ); ?>">

	<div class="ads-perfumes" aria-label="<?php esc_attr_e( 'عطرهای این ست', 'aromist-discovery-set' ); ?>">
		<?php foreach ( $perfumes as $ads_perfume ) : ?>
			<?php
			if ( ! $ads_perfume ) {
				continue;
			}
			$ads_link = $ads_perfume->is_visible() ? $ads_perfume->get_permalink() : '';
			?>
			<div class="ads-perfume">
				<?php if ( $ads_link ) : ?>
					<a href="<?php echo esc_url( $ads_link ); ?>" class="ads-perfume__link">
				<?php endif; ?>
					<span class="ads-perfume__image"><?php echo $ads_perfume->get_image( 'woocommerce_gallery_thumbnail', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="ads-perfume__name"><?php echo esc_html( $ads_perfume->get_name() ); ?></span>
				<?php if ( $ads_link ) : ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( 1 === count( $volumes ) ) : ?>
		<input type="hidden" name="ads_volume" value="<?php echo esc_attr( (int) key( $volumes ) ); ?>">
		<p class="ads-volume-fixed">
			<?php
			/* translators: 1: number of perfumes, 2: volume */
			echo esc_html( sprintf( __( '%1$s عطر، از هر کدام %2$s', 'aromist-discovery-set' ), number_format_i18n( count( $perfumes ) ), $volumes[ key( $volumes ) ]['label'] ) );
			?>
		</p>
	<?php elseif ( $volumes ) : ?>
		<fieldset class="ads-volumes">
			<legend class="ads-volumes__title"><?php esc_html_e( 'حجم هر عطر', 'aromist-discovery-set' ); ?></legend>
			<div class="ads-volumes__list">
				<?php foreach ( $volumes as $ads_ml => $ads_row ) : ?>
					<label class="ads-volume<?php echo $ads_row['available'] ? '' : ' is-unavailable'; ?>">
						<input type="radio" name="ads_volume" value="<?php echo esc_attr( $ads_ml ); ?>" <?php checked( $ads_ml, $selected ); ?> <?php disabled( ! $ads_row['available'] ); ?>>
						<span class="ads-volume__ml"><?php echo esc_html( $ads_row['label'] ); ?></span>
						<span class="ads-volume__state"><?php echo $ads_row['available'] ? '' : esc_html__( 'ناموجود', 'aromist-discovery-set' ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
	<?php endif; ?>

	<div class="ads-total">
		<span class="ads-total__label"><?php esc_html_e( 'قیمت ست', 'aromist-discovery-set' ); ?></span>
		<span class="ads-total__price price"><?php echo $ads_current ? wp_kses_post( $ads_current['price_html'] ) : ''; ?></span>
	</div>

	<?php if ( $purchasable && $ads_any_available ) : ?>
		<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

		<div class="ads-actions">
			<?php
			do_action( 'woocommerce_before_add_to_cart_quantity' );

			woocommerce_quantity_input(
				array(
					'min_value'   => 1,
					'max_value'   => $ads_current && $ads_current['max'] > 0 ? $ads_current['max'] : '',
					'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : 1, // phpcs:ignore WordPress.Security.NonceVerification.Missing
				)
			);

			do_action( 'woocommerce_after_add_to_cart_quantity' );
			?>

			<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt ads-add<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>

			<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
		</div>
	<?php else : ?>
		<p class="ads-unavailable stock out-of-stock"><?php esc_html_e( 'این ست در حال حاضر موجود نیست.', 'aromist-discovery-set' ); ?></p>
	<?php endif; ?>
</form>

<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
