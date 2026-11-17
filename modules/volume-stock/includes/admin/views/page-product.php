<?php
/**
 * One product: pool, sizes, adjust, settings, history.
 *
 * @var int $product_id Parent id.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

$avs_product = wc_get_product( $product_id );

if ( ! $avs_product instanceof WC_Product_Variable || ! AVS_Product::is_enabled( $product_id ) ) {
	$avs_active = 'avs';
	$avs_title  = __( 'موجودی حجمی', 'aromist-volume-stock' );
	require __DIR__ . '/partials/header.php';
	echo '<div class="avs-empty"><p>' . esc_html__( 'موجودی حجمی روی این محصول فعال نیست.', 'aromist-volume-stock' ) . '</p>';
	if ( $avs_product instanceof WC_Product_Variable ) {
		echo '<a class="button button-primary avs-btn" href="' . esc_url( AVS_Admin::url( 'avs-add', array( 'product_id' => $product_id ) ) ) . '">' . esc_html__( 'فعال‌سازی برای این محصول', 'aromist-volume-stock' ) . '</a>';
	}
	echo '</div>';
	return;
}

$avs_row      = AVS_Admin_Data::product_row( $product_id, AVS_Admin_Data::consumption( 30 ) );
$avs_held     = AVS_Product::held_ml( $product_id );
$avs_sizes    = AVS_Admin_Data::sizes( $avs_product, $avs_row['pool'] );
$avs_statuses = AVS_Admin_Data::statuses();
$avs_history  = AVS_Ledger::query( array( 'product_id' => $product_id, 'per_page' => 15 ) );
$avs_back     = AVS_Admin::url( 'avs', array( 'product_id' => $product_id ) );
$avs_range    = AVS_Reports::range_from_request( '30' );
$avs_by_size  = AVS_Reports::variation_rows( $product_id, $avs_range );

$avs_active = 'avs';
$avs_title  = $avs_product->get_name();
$avs_action = '<a class="button" href="' . esc_url( $avs_row['edit_url'] ) . '">' . esc_html__( 'ویرایش محصول', 'aromist-volume-stock' ) . '</a> '
	. '<a class="button" href="' . esc_url( $avs_row['view_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'مشاهده در سایت', 'aromist-volume-stock' ) . '</a> '
	. '<button type="button" class="button button-primary avs-btn" data-avs-adjust data-product="' . esc_attr( $product_id ) . '" data-name="' . esc_attr( $avs_product->get_name() ) . '" data-pool="' . esc_attr( $avs_row['pool'] ) . '" data-unlimited="' . ( $avs_row['unlimited'] ? '1' : '0' ) . '" data-unit="' . esc_attr( AVS_Settings::get( 'unit_label' ) ) . '">' . ( $avs_row['unlimited'] ? esc_html__( 'تعیین موجودی', 'aromist-volume-stock' ) : esc_html__( 'تنظیم موجودی', 'aromist-volume-stock' ) ) . '</button>';
require __DIR__ . '/partials/header.php';
?>

<p class="avs-breadcrumb"><a href="<?php echo esc_url( AVS_Admin::url( 'avs' ) ); ?>">‹ <?php esc_html_e( 'همه محصولات حجمی', 'aromist-volume-stock' ); ?></a></p>

<div class="avs-cards">
	<div class="avs-card avs-card--hero avs-card--<?php echo esc_attr( $avs_row['status'] ); ?>">
		<span class="avs-card__label"><?php esc_html_e( 'موجودی مخزن', 'aromist-volume-stock' ); ?></span>
		<strong class="avs-card__value"><?php echo $avs_row['unlimited'] ? esc_html__( 'نامحدود', 'aromist-volume-stock' ) : esc_html( AVS_Settings::format_ml( $avs_row['pool'] ) ); ?></strong>
		<span class="avs-badge avs-badge--<?php echo esc_attr( $avs_row['status'] ); ?>"><?php echo esc_html( $avs_statuses[ $avs_row['status'] ] ); ?></span>
		<?php if ( $avs_row['unlimited'] ) : ?>
			<span class="avs-muted"><?php esc_html_e( 'موجودی تعیین نشده؛ همه‌ی حجم‌ها بدون محدودیت فروخته می‌شوند. با «تعیین موجودی» محدود می‌شود.', 'aromist-volume-stock' ); ?></span>
		<?php endif; ?>
	</div>
	<div class="avs-card">
		<span class="avs-card__label"><?php esc_html_e( 'رزرو در پرداخت‌های باز', 'aromist-volume-stock' ); ?></span>
		<strong class="avs-card__value"><?php echo esc_html( AVS_Settings::format_ml( $avs_held ) ); ?></strong>
		<span class="avs-muted"><?php esc_html_e( 'سفارش‌هایی که مشتری در حال پرداخت آن‌هاست', 'aromist-volume-stock' ); ?></span>
	</div>
	<div class="avs-card">
		<span class="avs-card__label"><?php esc_html_e( 'مصرف ۳۰ روز اخیر', 'aromist-volume-stock' ); ?></span>
		<strong class="avs-card__value"><?php echo esc_html( AVS_Settings::format_ml( $avs_row['sold30'] ) ); ?></strong>
		<span class="avs-muted"><?php /* translators: %s: ml */ printf( esc_html__( 'میانگین روزانه %s', 'aromist-volume-stock' ), esc_html( AVS_Settings::format_ml( round( $avs_row['avg_daily'], 1 ) ) ) ); ?></span>
	</div>
	<div class="avs-card">
		<span class="avs-card__label"><?php esc_html_e( 'تخمین اتمام', 'aromist-volume-stock' ); ?></span>
		<strong class="avs-card__value"><?php echo esc_html( AVS_Admin_Data::forecast( $avs_row['days_left'] ) ); ?></strong>
		<span class="avs-muted"><?php /* translators: %s: threshold */ printf( esc_html__( 'هشدار زیر %s', 'aromist-volume-stock' ), esc_html( AVS_Settings::format_ml( $avs_row['low'] ) ) ); ?></span>
	</div>
</div>

<div class="avs-grid">
	<section class="avs-panel">
		<h2><?php esc_html_e( 'حجم‌ها', 'aromist-volume-stock' ); ?></h2>
		<table class="avs-table avs-table--compact">
			<thead>
				<tr>
					<th><?php esc_html_e( 'متغیر', 'aromist-volume-stock' ); ?></th>
					<th><?php esc_html_e( 'حجم هر عدد', 'aromist-volume-stock' ); ?></th>
					<th><?php esc_html_e( 'قابل فروش', 'aromist-volume-stock' ); ?></th>
					<th><?php esc_html_e( 'فروش ۳۰ روز', 'aromist-volume-stock' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$avs_sold_by_id = array();
				foreach ( $avs_by_size as $avs_s ) {
					$avs_sold_by_id[ $avs_s['variation_id'] ] = $avs_s;
				}
				foreach ( $avs_sizes as $avs_size ) :
					$avs_s = isset( $avs_sold_by_id[ $avs_size['id'] ] ) ? $avs_sold_by_id[ $avs_size['id'] ] : array( 'units' => 0, 'sold_ml' => 0 );
					?>
					<tr class="<?php echo $avs_size['published'] ? '' : 'avs-is-disabled'; ?>">
						<td>
							<?php echo esc_html( $avs_size['label'] ); ?>
							<?php if ( ! $avs_size['published'] ) : ?>
								<span class="avs-badge"><?php esc_html_e( 'غیرفعال', 'aromist-volume-stock' ); ?></span>
							<?php endif; ?>
							<?php if ( $avs_size['own_stock'] ) : ?>
								<span class="avs-badge avs-badge--out" title="<?php esc_attr_e( 'این متغیر موجودی جداگانه دارد و از مخزن برداشت نمی‌کند.', 'aromist-volume-stock' ); ?>"><?php esc_html_e( 'خارج از مخزن', 'aromist-volume-stock' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( AVS_Settings::format_ml( $avs_size['ml'] ) ); ?>
							<?php if ( $avs_row['waste'] ) : ?>
								<span class="avs-muted">+ <?php echo esc_html( AVS_Settings::format_ml( $avs_row['waste'] ) ); ?> <?php esc_html_e( 'هدررفت', 'aromist-volume-stock' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $avs_row['unlimited'] ) : ?>
								<strong><?php esc_html_e( 'نامحدود', 'aromist-volume-stock' ); ?></strong>
							<?php else : ?>
								<strong><?php echo esc_html( number_format_i18n( $avs_size['units'] ) ); ?></strong> <?php esc_html_e( 'عدد', 'aromist-volume-stock' ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( $avs_s['units'] ) ); ?> <?php esc_html_e( 'عدد', 'aromist-volume-stock' ); ?> <span class="avs-muted">(<?php echo esc_html( AVS_Settings::format_ml( $avs_s['sold_ml'] ) ); ?>)</span></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</section>

	<section class="avs-panel">
		<h2><?php esc_html_e( 'تنظیمات این محصول', 'aromist-volume-stock' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="avs_update_product">
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
			<input type="hidden" name="_avs_back" value="<?php echo esc_attr( $avs_back ); ?>">
			<?php wp_nonce_field( 'avs_update_product' ); ?>

			<div class="avs-ml-grid">
				<?php foreach ( $avs_sizes as $avs_size ) : ?>
					<p class="avs-field">
						<label for="avs-ml-<?php echo esc_attr( $avs_size['id'] ); ?>"><?php echo esc_html( $avs_size['label'] ); ?></label>
						<span class="avs-input-unit">
							<input type="number" min="0" step="1" id="avs-ml-<?php echo esc_attr( $avs_size['id'] ); ?>" name="ml[<?php echo esc_attr( $avs_size['id'] ); ?>]" value="<?php echo esc_attr( $avs_size['ml'] ); ?>" class="avs-input">
							<em><?php echo esc_html( AVS_Settings::get( 'unit_label' ) ); ?></em>
						</span>
					</p>
				<?php endforeach; ?>
			</div>

			<div class="avs-ml-grid">
				<p class="avs-field">
					<label for="avs-low"><?php esc_html_e( 'هشدار کمبود وقتی موجودی کمتر از', 'aromist-volume-stock' ); ?></label>
					<span class="avs-input-unit">
						<input type="number" min="0" step="1" id="avs-low" name="low_ml" value="<?php echo esc_attr( $avs_row['low'] ); ?>" class="avs-input">
						<em><?php echo esc_html( AVS_Settings::get( 'unit_label' ) ); ?></em>
					</span>
				</p>
				<p class="avs-field">
					<label for="avs-waste"><?php esc_html_e( 'هدررفت هر عدد (اختیاری)', 'aromist-volume-stock' ); ?></label>
					<span class="avs-input-unit">
						<input type="number" min="0" step="1" id="avs-waste" name="waste_ml" value="<?php echo esc_attr( $avs_row['waste'] ); ?>" class="avs-input">
						<em><?php echo esc_html( AVS_Settings::get( 'unit_label' ) ); ?></em>
					</span>
				</p>
			</div>
			<p class="avs-help"><?php esc_html_e( 'هدررفت: میلی که هنگام پر کردن هر شیشه اضافه مصرف می‌شود. مثلاً اگر ۱ باشد، فروش یک ۵۰ میل، ۵۱ میل از مخزن کم می‌کند. تغییر حجم‌ها روی سفارش‌های قبلی اثر ندارد.', 'aromist-volume-stock' ); ?></p>
			<p><button class="button button-primary avs-btn"><?php esc_html_e( 'ذخیره تنظیمات', 'aromist-volume-stock' ); ?></button></p>
		</form>
	</section>
</div>

<section class="avs-panel">
	<div class="avs-panel__head">
		<h2><?php esc_html_e( 'آخرین تغییرات موجودی', 'aromist-volume-stock' ); ?></h2>
		<a class="button" href="<?php echo esc_url( AVS_Admin::url( 'avs-history', array( 'product_id' => $product_id ) ) ); ?>"><?php esc_html_e( 'تاریخچه کامل', 'aromist-volume-stock' ); ?></a>
	</div>
	<?php
	$avs_rows_history = $avs_history;
	$avs_show_product = false;
	require __DIR__ . '/partials/history-table.php';
	?>
</section>

<details class="avs-panel avs-danger">
	<summary><?php esc_html_e( 'غیرفعال کردن موجودی حجمی این محصول', 'aromist-volume-stock' ); ?></summary>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-avs-confirm="<?php esc_attr_e( 'موجودی حجمی این محصول غیرفعال شود؟', 'aromist-volume-stock' ); ?>">
		<input type="hidden" name="action" value="avs_disable">
		<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
		<input type="hidden" name="_avs_back" value="<?php echo esc_attr( $avs_back ); ?>">
		<?php wp_nonce_field( 'avs_disable' ); ?>
		<p><?php esc_html_e( 'بعد از غیرفعال‌سازی، هر حجم موجودی جداگانه می‌گیرد = تعداد عددی که از مخزن فعلی در آن حجم جا می‌شود. توجه: در این حالت هر حجم کل مخزن را جداگانه نشان می‌دهد و مجموع آن‌ها بیشتر از موجودی واقعی است؛ پس موجودی هر حجم را بعداً دستی اصلاح کنید. سفارش‌های باز این محصول به حالت تعدادی تبدیل می‌شوند تا لغو و مرجوعی درست کار کند.', 'aromist-volume-stock' ); ?></p>
		<label class="avs-check"><input type="checkbox" name="confirm" value="1" required> <?php esc_html_e( 'متوجه شدم، غیرفعال شود.', 'aromist-volume-stock' ); ?></label>
		<p><button class="button avs-btn-danger"><?php esc_html_e( 'غیرفعال کردن', 'aromist-volume-stock' ); ?></button></p>
	</form>
</details>

<?php require __DIR__ . '/partials/adjust-dialog.php'; ?>
