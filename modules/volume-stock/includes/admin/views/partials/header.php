<?php
/**
 * Page header with navigation.
 *
 * @var string $avs_active Active tab slug.
 * @var string $avs_title  Page title.
 * @var string $avs_action Optional HTML (already escaped) for the header actions.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

$avs_tabs = array(
	'avs'          => __( 'محصولات حجمی', 'aromist-volume-stock' ),
	'avs-add'      => __( 'افزودن محصول', 'aromist-volume-stock' ),
	'avs-history'  => __( 'تاریخچه', 'aromist-volume-stock' ),
	'avs-reports'  => __( 'گزارش‌ها', 'aromist-volume-stock' ),
	'avs-settings' => __( 'تنظیمات', 'aromist-volume-stock' ),
);
?>
<div class="avs-header">
	<div class="avs-header__title">
		<span class="avs-logo" aria-hidden="true">
			<svg viewBox="0 0 24 24" width="22" height="22"><path fill="currentColor" d="M12 2s-7 7.7-7 12.8A7 7 0 0 0 19 14.8C19 9.7 12 2 12 2zm0 18a5.2 5.2 0 0 1-5.2-5.2h1.8a3.4 3.4 0 0 0 3.4 3.4z"/></svg>
		</span>
		<h1><?php echo esc_html( $avs_title ); ?></h1>
	</div>
	<?php if ( ! empty( $avs_action ) ) : ?>
		<div class="avs-header__actions"><?php echo $avs_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by caller. ?></div>
	<?php endif; ?>
</div>

<nav class="avs-tabs" aria-label="<?php esc_attr_e( 'بخش‌های موجودی حجمی', 'aromist-volume-stock' ); ?>">
	<?php foreach ( $avs_tabs as $avs_slug => $avs_label ) : ?>
		<a class="avs-tab<?php echo $avs_slug === $avs_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( AVS_Admin::url( $avs_slug ) ); ?>"<?php echo $avs_slug === $avs_active ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $avs_label ); ?></a>
	<?php endforeach; ?>
</nav>

<hr class="wp-header-end">
<?php
AVS_Admin::print_flash();

if ( ! AVS_Product::store_manages_stock() ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'مدیریت موجودی در ووکامرس خاموش است (ووکامرس ← پیکربندی ← محصولات ← موجودی انبار). تا روشن نشود، موجودی حجمی کم و زیاد نمی‌شود.', 'aromist-volume-stock' ) . '</p></div>';
}
