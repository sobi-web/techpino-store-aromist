<?php
/**
 * Consumption report.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

$avs_range = AVS_Reports::range_from_request( '30' );
$avs_rows  = AVS_Reports::rows( $avs_range );

$avs_sum = array( 'units' => 0, 'sold_ml' => 0, 'in_ml' => 0, 'waste_ml' => 0 );
foreach ( $avs_rows as $avs_row ) {
	foreach ( $avs_sum as $avs_key => $avs_v ) {
		$avs_sum[ $avs_key ] += $avs_row[ $avs_key ];
	}
}

$avs_presets = array(
	'7'     => __( '۷ روز', 'aromist-volume-stock' ),
	'30'    => __( '۳۰ روز', 'aromist-volume-stock' ),
	'90'    => __( '۹۰ روز', 'aromist-volume-stock' ),
	'month' => __( 'ماه جاری (میلادی)', 'aromist-volume-stock' ),
	'365'   => __( 'یک سال', 'aromist-volume-stock' ),
);

$avs_active = 'avs-reports';
$avs_title  = __( 'گزارش مصرف موجودی حجمی', 'aromist-volume-stock' );
require __DIR__ . '/partials/header.php';
?>

<form method="get" class="avs-toolbar avs-toolbar--wrap" data-avs-range-form>
	<input type="hidden" name="page" value="avs-reports">
	<div class="avs-filters">
		<?php foreach ( $avs_presets as $avs_key => $avs_label ) : ?>
			<a class="avs-chip-filter<?php echo (string) $avs_key === $avs_range['preset'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( AVS_Admin::url( 'avs-reports', array( 'range' => $avs_key ) ) ); ?>"><?php echo esc_html( $avs_label ); ?></a>
		<?php endforeach; ?>
	</div>
	<div class="avs-toolbar__end">
		<input type="hidden" name="range" value="custom">
		<label class="avs-date"><?php esc_html_e( 'از', 'aromist-volume-stock' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $avs_range['from'] ); ?>" class="avs-input"></label>
		<label class="avs-date"><?php esc_html_e( 'تا', 'aromist-volume-stock' ); ?> <input type="date" name="to" value="<?php echo esc_attr( $avs_range['to'] ); ?>" class="avs-input"></label>
		<button class="button"><?php esc_html_e( 'نمایش', 'aromist-volume-stock' ); ?></button>
	</div>
</form>

<p class="avs-muted avs-range-label">
	<?php
	/* translators: 1: from date, 2: to date, 3: days */
	printf( esc_html__( 'بازه: %1$s تا %2$s (%3$s روز)', 'aromist-volume-stock' ), esc_html( wp_date( get_option( 'date_format' ), strtotime( $avs_range['from'] . ' 12:00' ) ) ), esc_html( wp_date( get_option( 'date_format' ), strtotime( $avs_range['to'] . ' 12:00' ) ) ), esc_html( number_format_i18n( $avs_range['days'] ) ) );
	?>
</p>

<div class="avs-cards">
	<div class="avs-card">
		<span class="avs-card__label"><?php esc_html_e( 'فروش (عدد)', 'aromist-volume-stock' ); ?></span>
		<strong class="avs-card__value"><?php echo esc_html( number_format_i18n( $avs_sum['units'] ) ); ?></strong>
	</div>
	<div class="avs-card">
		<span class="avs-card__label"><?php esc_html_e( 'مصرف فروش', 'aromist-volume-stock' ); ?></span>
		<strong class="avs-card__value"><?php echo esc_html( AVS_Settings::format_ml( $avs_sum['sold_ml'] ) ); ?></strong>
	</div>
	<div class="avs-card">
		<span class="avs-card__label"><?php esc_html_e( 'ورودی انبار', 'aromist-volume-stock' ); ?></span>
		<strong class="avs-card__value"><?php echo esc_html( AVS_Settings::format_ml( $avs_sum['in_ml'] ) ); ?></strong>
	</div>
	<div class="avs-card">
		<span class="avs-card__label"><?php esc_html_e( 'هدررفت / شکستگی', 'aromist-volume-stock' ); ?></span>
		<strong class="avs-card__value"><?php echo esc_html( AVS_Settings::format_ml( $avs_sum['waste_ml'] ) ); ?></strong>
	</div>
</div>

<div class="avs-panel__head">
	<p class="avs-help"><?php esc_html_e( 'مصرف فروش = برداشت سفارش‌ها منهای لغوها و مرجوعی‌ها. گزارش از روز نصب افزونه داده دارد.', 'aromist-volume-stock' ); ?></p>
	<form method="post" action="<?php echo esc_url( add_query_arg( array( 'range' => $avs_range['preset'], 'from' => $avs_range['from'], 'to' => $avs_range['to'] ), admin_url( 'admin-post.php' ) ) ); ?>">
		<input type="hidden" name="action" value="avs_export_report">
		<?php wp_nonce_field( 'avs_export_report' ); ?>
		<button class="button"><?php esc_html_e( 'خروجی اکسل (CSV)', 'aromist-volume-stock' ); ?></button>
	</form>
</div>

<?php if ( ! $avs_rows ) : ?>
	<div class="avs-empty"><p><?php esc_html_e( 'داده‌ای برای این بازه نیست.', 'aromist-volume-stock' ); ?></p></div>
<?php else : ?>
	<div class="avs-table-wrap">
		<table class="avs-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'محصول', 'aromist-volume-stock' ); ?></th>
					<th><?php esc_html_e( 'فروش (عدد)', 'aromist-volume-stock' ); ?></th>
					<th><?php esc_html_e( 'مصرف فروش', 'aromist-volume-stock' ); ?></th>
					<th><?php esc_html_e( 'ورودی', 'aromist-volume-stock' ); ?></th>
					<th><?php esc_html_e( 'هدررفت', 'aromist-volume-stock' ); ?></th>
					<th><?php esc_html_e( 'اصلاحات', 'aromist-volume-stock' ); ?></th>
					<th><?php esc_html_e( 'میانگین روزانه', 'aromist-volume-stock' ); ?></th>
					<th><?php esc_html_e( 'موجودی فعلی', 'aromist-volume-stock' ); ?></th>
					<th><?php esc_html_e( 'تخمین اتمام', 'aromist-volume-stock' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $avs_rows as $avs_row ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'محصول', 'aromist-volume-stock' ); ?>">
							<a href="<?php echo esc_url( AVS_Admin::url( 'avs', array( 'product_id' => $avs_row['product_id'], 'range' => $avs_range['preset'], 'from' => $avs_range['from'], 'to' => $avs_range['to'] ) ) ); ?>"><?php echo esc_html( $avs_row['name'] ); ?></a>
							<?php if ( ! $avs_row['enabled'] ) : ?>
								<span class="avs-badge"><?php esc_html_e( 'غیرفعال', 'aromist-volume-stock' ); ?></span>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'فروش (عدد)', 'aromist-volume-stock' ); ?>"><?php echo esc_html( number_format_i18n( $avs_row['units'] ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'مصرف فروش', 'aromist-volume-stock' ); ?>"><strong><?php echo esc_html( AVS_Settings::format_ml( $avs_row['sold_ml'] ) ); ?></strong></td>
						<td data-label="<?php esc_attr_e( 'ورودی', 'aromist-volume-stock' ); ?>"><?php echo esc_html( AVS_Settings::format_ml( $avs_row['in_ml'] ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'هدررفت', 'aromist-volume-stock' ); ?>"><?php echo esc_html( AVS_Settings::format_ml( $avs_row['waste_ml'] ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'اصلاحات', 'aromist-volume-stock' ); ?>" class="avs-change <?php echo $avs_row['fixes_ml'] < 0 ? 'is-neg' : ( $avs_row['fixes_ml'] > 0 ? 'is-pos' : '' ); ?>"><?php echo AVS_Settings::signed_ml_html( $avs_row['fixes_ml'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></td>
						<td data-label="<?php esc_attr_e( 'میانگین روزانه', 'aromist-volume-stock' ); ?>"><?php echo esc_html( AVS_Settings::format_ml( round( $avs_row['avg_daily'], 1 ) ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'موجودی فعلی', 'aromist-volume-stock' ); ?>"><?php echo esc_html( AVS_Settings::format_ml( $avs_row['pool'] ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'تخمین اتمام', 'aromist-volume-stock' ); ?>"><?php echo esc_html( AVS_Admin_Data::forecast( $avs_row['days_left'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
