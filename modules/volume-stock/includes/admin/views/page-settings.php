<?php
/**
 * Plugin settings.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

$avs_s = AVS_Settings::all();

$avs_active = 'avs-settings';
$avs_title  = __( 'تنظیمات موجودی حجمی', 'aromist-volume-stock' );
require __DIR__ . '/partials/header.php';
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="avs-panel avs-settings">
	<input type="hidden" name="action" value="avs_save_settings">
	<?php wp_nonce_field( 'avs_save_settings' ); ?>

	<p class="avs-field">
		<label for="avs-unit"><?php esc_html_e( 'واحد نمایش', 'aromist-volume-stock' ); ?></label>
		<input type="text" id="avs-unit" name="avs[unit_label]" value="<?php echo esc_attr( $avs_s['unit_label'] ); ?>" class="avs-input avs-input--sm" maxlength="20">
		<span class="avs-help"><?php esc_html_e( 'مثلاً «میل» یا «میلی‌لیتر». فقط نمایش است؛ همه محاسبات بر حسب میلی‌لیتر است.', 'aromist-volume-stock' ); ?></span>
	</p>

	<p class="avs-field">
		<label for="avs-low-default"><?php esc_html_e( 'هشدار کمبود پیش‌فرض برای محصولات جدید (میل)', 'aromist-volume-stock' ); ?></label>
		<input type="number" min="0" step="1" id="avs-low-default" name="avs[default_low_ml]" value="<?php echo esc_attr( $avs_s['default_low_ml'] ); ?>" class="avs-input avs-input--sm">
		<span class="avs-help"><?php esc_html_e( 'ایمیل «موجودی کم» ووکامرس وقتی ارسال می‌شود که موجودی مخزن به این مقدار برسد. گیرنده ایمیل در تنظیمات ووکامرس ← محصولات ← موجودی انبار تعیین می‌شود.', 'aromist-volume-stock' ); ?></span>
	</p>

	<label class="avs-check">
		<input type="checkbox" name="avs[show_units_in_stock]" value="1" <?php checked( 'yes', $avs_s['show_units_in_stock'] ); ?>>
		<?php esc_html_e( 'در صفحه محصول، تعداد قابل فروش هر حجم نمایش داده شود (مثلاً «۱۹ عدد در انبار»)، نه میلی‌لیتر مخزن.', 'aromist-volume-stock' ); ?>
	</label>

	<label class="avs-check">
		<input type="checkbox" name="avs[order_notes]" value="1" <?php checked( 'yes', $avs_s['order_notes'] ); ?>>
		<?php esc_html_e( 'برای هر کسر / بازگشت موجودی حجمی، یادداشت در سفارش ثبت شود.', 'aromist-volume-stock' ); ?>
	</label>

	<label class="avs-check avs-check--danger">
		<input type="checkbox" name="avs[delete_on_uninstall]" value="1" <?php checked( 'yes', $avs_s['delete_on_uninstall'] ); ?>>
		<?php esc_html_e( 'هنگام «حذف» افزونه، تاریخچه و تنظیمات هم پاک شوند (توصیه نمی‌شود).', 'aromist-volume-stock' ); ?>
	</label>

	<p><button class="button button-primary avs-btn"><?php esc_html_e( 'ذخیره تنظیمات', 'aromist-volume-stock' ); ?></button></p>
</form>

<section class="avs-panel">
	<h2><?php esc_html_e( 'وضعیت سیستم', 'aromist-volume-stock' ); ?></h2>
	<?php
	global $wpdb;
	$avs_checks = array(
		array( __( 'مدیریت موجودی ووکامرس', 'aromist-volume-stock' ), AVS_Product::store_manages_stock() ),
		array( __( 'نگه‌داشتن موجودی هنگام پرداخت (دقیقه)', 'aromist-volume-stock' ), (int) get_option( 'woocommerce_hold_stock_minutes', 60 ) > 0, (string) get_option( 'woocommerce_hold_stock_minutes', 60 ) ),
		array( __( 'جدول تاریخچه', 'aromist-volume-stock' ), AVS_Install::table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', AVS_Install::table() ) ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		array( __( 'نسخه ووکامرس', 'aromist-volume-stock' ), true, WC()->version ),
		array( __( 'ذخیره‌سازی سفارش‌ها (HPOS)', 'aromist-volume-stock' ), true, \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? __( 'فعال', 'aromist-volume-stock' ) : __( 'قدیمی (پست‌ها)', 'aromist-volume-stock' ) ),
	);
	?>
	<table class="avs-table avs-table--compact">
		<tbody>
			<?php foreach ( $avs_checks as $avs_c ) : ?>
				<tr>
					<td><?php echo esc_html( $avs_c[0] ); ?></td>
					<td>
						<span class="avs-badge avs-badge--<?php echo $avs_c[1] ? 'ok' : 'out'; ?>"><?php echo $avs_c[1] ? '✓' : '✕'; ?></span>
						<?php echo isset( $avs_c[2] ) ? esc_html( $avs_c[2] ) : ''; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p class="avs-help"><?php esc_html_e( '«نگه‌داشتن موجودی» باید بیشتر از صفر باشد تا وقتی مشتری در درگاه بانکی است، میل‌های سفارشش برای دیگران قابل خرید نباشد (ووکامرس ← پیکربندی ← محصولات ← موجودی انبار).', 'aromist-volume-stock' ); ?></p>
</section>
