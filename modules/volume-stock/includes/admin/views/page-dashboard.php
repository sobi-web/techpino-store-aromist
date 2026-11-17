<?php
/**
 * Products with volume stock.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
$avs_search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$avs_status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$avs_orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'status';
$avs_paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
// phpcs:enable

$avs_ids = AVS_Reports::enabled_product_ids();
AVS_Admin_Data::prime( $avs_ids );
$avs_consumed = AVS_Admin_Data::consumption( 30 );

$avs_all = array();
foreach ( $avs_ids as $avs_id ) {
	$avs_row = AVS_Admin_Data::product_row( $avs_id, $avs_consumed );
	if ( $avs_row ) {
		$avs_all[] = $avs_row;
	}
}

$avs_counts = array( 'all' => count( $avs_all ), 'ok' => 0, 'low' => 0, 'out' => 0, 'unlimited' => 0 );
$avs_total_pool = 0;
$avs_total_sold = 0;
foreach ( $avs_all as $avs_row ) {
	++$avs_counts[ $avs_row['status'] ];
	$avs_total_pool += max( 0, $avs_row['pool'] );
	$avs_total_sold += $avs_row['sold30'];
}

$avs_rows = array_filter(
	$avs_all,
	static function ( $row ) use ( $avs_search, $avs_status ) {
		if ( $avs_status && array_key_exists( $avs_status, AVS_Admin_Data::statuses() ) && $row['status'] !== $avs_status ) {
			return false;
		}
		if ( '' !== $avs_search ) {
			$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $row['name'] . ' ' . $row['sku'] ) : strtolower( $row['name'] . ' ' . $row['sku'] );
			$needle   = function_exists( 'mb_strtolower' ) ? mb_strtolower( $avs_search ) : strtolower( $avs_search );
			return false !== strpos( $haystack, $needle );
		}
		return true;
	}
);

$avs_rank = array( 'out' => 0, 'low' => 1, 'ok' => 2, 'unlimited' => 3 );
usort(
	$avs_rows,
	static function ( $a, $b ) use ( $avs_orderby, $avs_rank ) {
		switch ( $avs_orderby ) {
			case 'name':
				return strcmp( $a['name'], $b['name'] );
			case 'pool':
				return $a['pool'] <=> $b['pool'];
			case 'sold':
				return $b['sold30'] <=> $a['sold30'];
			case 'forecast':
				$da = null === $a['days_left'] ? PHP_INT_MAX : $a['days_left'];
				$db = null === $b['days_left'] ? PHP_INT_MAX : $b['days_left'];
				return $da <=> $db;
			default:
				return array( $avs_rank[ $a['status'] ], $a['name'] ) <=> array( $avs_rank[ $b['status'] ], $b['name'] );
		}
	}
);

$avs_per_page = 25;
$avs_total    = count( $avs_rows );
$avs_pages    = max( 1, (int) ceil( $avs_total / $avs_per_page ) );
$avs_paged    = min( $avs_paged, $avs_pages );
$avs_rows     = array_slice( $avs_rows, ( $avs_paged - 1 ) * $avs_per_page, $avs_per_page );
$avs_statuses = AVS_Admin_Data::statuses();

$avs_active = 'avs';
$avs_title  = __( 'موجودی حجمی', 'aromist-volume-stock' );
$avs_action = '<a class="button button-primary avs-btn" href="' . esc_url( AVS_Admin::url( 'avs-add' ) ) . '">+ ' . esc_html__( 'افزودن محصول', 'aromist-volume-stock' ) . '</a>';
require __DIR__ . '/partials/header.php';
?>

<div class="avs-cards">
	<div class="avs-card">
		<span class="avs-card__label"><?php esc_html_e( 'محصولات حجمی', 'aromist-volume-stock' ); ?></span>
		<strong class="avs-card__value"><?php echo esc_html( number_format_i18n( $avs_counts['all'] ) ); ?></strong>
	</div>
	<a class="avs-card avs-card--low" href="<?php echo esc_url( AVS_Admin::url( 'avs', array( 'status' => 'low' ) ) ); ?>">
		<span class="avs-card__label"><?php esc_html_e( 'رو به اتمام', 'aromist-volume-stock' ); ?></span>
		<strong class="avs-card__value"><?php echo esc_html( number_format_i18n( $avs_counts['low'] ) ); ?></strong>
	</a>
	<a class="avs-card avs-card--out" href="<?php echo esc_url( AVS_Admin::url( 'avs', array( 'status' => 'out' ) ) ); ?>">
		<span class="avs-card__label"><?php esc_html_e( 'تمام‌شده', 'aromist-volume-stock' ); ?></span>
		<strong class="avs-card__value"><?php echo esc_html( number_format_i18n( $avs_counts['out'] ) ); ?></strong>
	</a>
	<div class="avs-card">
		<span class="avs-card__label"><?php esc_html_e( 'مصرف ۳۰ روز اخیر', 'aromist-volume-stock' ); ?></span>
		<strong class="avs-card__value"><?php echo esc_html( AVS_Settings::format_ml( $avs_total_sold ) ); ?></strong>
	</div>
	<div class="avs-card">
		<span class="avs-card__label"><?php esc_html_e( 'کل موجودی مخزن‌ها', 'aromist-volume-stock' ); ?></span>
		<strong class="avs-card__value"><?php echo esc_html( AVS_Settings::format_ml( $avs_total_pool ) ); ?></strong>
	</div>
</div>

<form method="get" class="avs-toolbar">
	<input type="hidden" name="page" value="avs">
	<div class="avs-filters">
		<?php
		$avs_filter_links = array( '' => __( 'همه', 'aromist-volume-stock' ) ) + $avs_statuses;
		foreach ( $avs_filter_links as $avs_key => $avs_label ) :
			$avs_count = '' === $avs_key ? $avs_counts['all'] : $avs_counts[ $avs_key ];
			?>
			<a class="avs-chip-filter<?php echo $avs_key === $avs_status ? ' is-active' : ''; ?>" href="<?php echo esc_url( AVS_Admin::url( 'avs', array_filter( array( 'status' => $avs_key, 's' => $avs_search ) ) ) ); ?>">
				<?php echo esc_html( $avs_label ); ?> <span><?php echo esc_html( number_format_i18n( $avs_count ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<div class="avs-toolbar__end">
		<?php if ( $avs_status ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $avs_status ); ?>">
		<?php endif; ?>
		<label class="screen-reader-text" for="avs-orderby"><?php esc_html_e( 'مرتب‌سازی', 'aromist-volume-stock' ); ?></label>
		<select name="orderby" id="avs-orderby" class="avs-input" onchange="this.form.submit()">
			<option value="status" <?php selected( $avs_orderby, 'status' ); ?>><?php esc_html_e( 'اولویت (تمام‌شده‌ها اول)', 'aromist-volume-stock' ); ?></option>
			<option value="forecast" <?php selected( $avs_orderby, 'forecast' ); ?>><?php esc_html_e( 'زودترین اتمام', 'aromist-volume-stock' ); ?></option>
			<option value="sold" <?php selected( $avs_orderby, 'sold' ); ?>><?php esc_html_e( 'بیشترین مصرف', 'aromist-volume-stock' ); ?></option>
			<option value="pool" <?php selected( $avs_orderby, 'pool' ); ?>><?php esc_html_e( 'کمترین موجودی', 'aromist-volume-stock' ); ?></option>
			<option value="name" <?php selected( $avs_orderby, 'name' ); ?>><?php esc_html_e( 'نام', 'aromist-volume-stock' ); ?></option>
		</select>
		<label class="screen-reader-text" for="avs-search"><?php esc_html_e( 'جستجو', 'aromist-volume-stock' ); ?></label>
		<input type="search" id="avs-search" name="s" value="<?php echo esc_attr( $avs_search ); ?>" class="avs-input" placeholder="<?php esc_attr_e( 'جستجوی نام یا SKU…', 'aromist-volume-stock' ); ?>">
		<button class="button"><?php esc_html_e( 'جستجو', 'aromist-volume-stock' ); ?></button>
	</div>
</form>

<?php if ( ! $avs_counts['all'] ) : ?>
	<div class="avs-empty">
		<h2><?php esc_html_e( 'هنوز هیچ محصولی موجودی حجمی ندارد', 'aromist-volume-stock' ); ?></h2>
		<p><?php esc_html_e( 'یک عطر متغیر (مثلاً با حجم‌های ۱۰، ۲۰، ۵۰ و ۱۰۰ میل) انتخاب کنید تا همه حجم‌ها از یک مخزن مشترک برداشت کنند.', 'aromist-volume-stock' ); ?></p>
		<a class="button button-primary avs-btn" href="<?php echo esc_url( AVS_Admin::url( 'avs-add' ) ); ?>"><?php esc_html_e( 'افزودن اولین محصول', 'aromist-volume-stock' ); ?></a>
	</div>
<?php elseif ( ! $avs_rows ) : ?>
	<div class="avs-empty"><p><?php esc_html_e( 'محصولی با این فیلتر پیدا نشد.', 'aromist-volume-stock' ); ?></p></div>
<?php else : ?>
	<div class="avs-table-wrap">
		<table class="avs-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'محصول', 'aromist-volume-stock' ); ?></th>
					<th><?php esc_html_e( 'موجودی مخزن', 'aromist-volume-stock' ); ?></th>
					<th><?php esc_html_e( 'قابل فروش از هر حجم', 'aromist-volume-stock' ); ?></th>
					<th><?php esc_html_e( 'مصرف ۳۰ روز', 'aromist-volume-stock' ); ?></th>
					<th><?php esc_html_e( 'تخمین اتمام', 'aromist-volume-stock' ); ?></th>
					<th class="avs-col-actions"><span class="screen-reader-text"><?php esc_html_e( 'عملیات', 'aromist-volume-stock' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $avs_rows as $avs_row ) : ?>
					<tr class="avs-row avs-row--<?php echo esc_attr( $avs_row['status'] ); ?>">
						<td class="avs-col-product" data-label="<?php esc_attr_e( 'محصول', 'aromist-volume-stock' ); ?>">
							<div class="avs-product">
								<span class="avs-product__thumb"><?php echo wp_kses_post( $avs_row['product']->get_image( array( 44, 44 ) ) ); ?></span>
								<span>
									<a class="avs-product__name" href="<?php echo esc_url( $avs_row['detail_url'] ); ?>"><?php echo esc_html( $avs_row['name'] ); ?></a>
									<?php if ( $avs_row['sku'] ) : ?>
										<span class="avs-muted">SKU: <?php echo esc_html( $avs_row['sku'] ); ?></span>
									<?php endif; ?>
								</span>
							</div>
						</td>
						<td data-label="<?php esc_attr_e( 'موجودی مخزن', 'aromist-volume-stock' ); ?>">
							<?php if ( $avs_row['unlimited'] ) : ?>
							<div class="avs-pool">
								<strong><?php esc_html_e( 'نامحدود', 'aromist-volume-stock' ); ?></strong>
								<span class="avs-badge avs-badge--unlimited"><?php echo esc_html( $avs_statuses['unlimited'] ); ?></span>
							</div>
							<span class="avs-muted"><?php esc_html_e( 'موجودی تعیین نشده؛ همه‌ی حجم‌ها بدون محدودیت فروخته می‌شوند.', 'aromist-volume-stock' ); ?></span>
							<?php else : ?>
							<div class="avs-pool">
								<strong><?php echo esc_html( AVS_Settings::format_ml( $avs_row['pool'] ) ); ?></strong>
								<span class="avs-badge avs-badge--<?php echo esc_attr( $avs_row['status'] ); ?>"><?php echo esc_html( $avs_statuses[ $avs_row['status'] ] ); ?></span>
							</div>
							<div class="avs-bar" aria-hidden="true"><span style="width:<?php echo esc_attr( AVS_Admin_Data::bar_percent( $avs_row ) ); ?>%"></span></div>
							<span class="avs-muted"><?php /* translators: %s: threshold */ printf( esc_html__( 'هشدار زیر %s', 'aromist-volume-stock' ), esc_html( AVS_Settings::format_ml( $avs_row['low'] ) ) ); ?></span>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'قابل فروش از هر حجم', 'aromist-volume-stock' ); ?>">
							<div class="avs-sizes">
								<?php foreach ( AVS_Admin_Data::sizes( $avs_row['product'], $avs_row['pool'] ) as $avs_size ) : ?>
									<?php if ( ! $avs_size['published'] || $avs_size['ml'] <= 0 ) { continue; } ?>
									<span class="avs-size<?php echo ! $avs_row['unlimited'] && $avs_size['units'] <= 0 ? ' is-empty' : ''; ?>">
										<b><?php echo esc_html( AVS_Settings::format_ml( $avs_size['ml'] ) ); ?></b>
										<?php if ( $avs_row['unlimited'] ) : ?>
											<?php esc_html_e( 'نامحدود', 'aromist-volume-stock' ); ?>
										<?php else : ?>
											<?php echo esc_html( number_format_i18n( $avs_size['units'] ) ); ?> <?php esc_html_e( 'عدد', 'aromist-volume-stock' ); ?>
										<?php endif; ?>
									</span>
								<?php endforeach; ?>
							</div>
						</td>
						<td data-label="<?php esc_attr_e( 'مصرف ۳۰ روز', 'aromist-volume-stock' ); ?>">
							<strong><?php echo esc_html( AVS_Settings::format_ml( $avs_row['sold30'] ) ); ?></strong>
							<span class="avs-muted"><?php /* translators: %s: ml per day */ printf( esc_html__( 'روزانه %s', 'aromist-volume-stock' ), esc_html( AVS_Settings::format_ml( round( $avs_row['avg_daily'], 1 ) ) ) ); ?></span>
						</td>
						<td data-label="<?php esc_attr_e( 'تخمین اتمام', 'aromist-volume-stock' ); ?>">
							<?php echo esc_html( AVS_Admin_Data::forecast( $avs_row['days_left'] ) ); ?>
						</td>
						<td class="avs-col-actions">
							<button type="button" class="button avs-btn-soft" data-avs-adjust data-product="<?php echo esc_attr( $avs_row['id'] ); ?>" data-name="<?php echo esc_attr( $avs_row['name'] ); ?>" data-pool="<?php echo esc_attr( $avs_row['pool'] ); ?>" data-unlimited="<?php echo $avs_row['unlimited'] ? '1' : '0'; ?>" data-unit="<?php echo esc_attr( AVS_Settings::get( 'unit_label' ) ); ?>"><?php echo $avs_row['unlimited'] ? esc_html__( 'تعیین موجودی', 'aromist-volume-stock' ) : esc_html__( 'تنظیم موجودی', 'aromist-volume-stock' ); ?></button>
							<a class="button" href="<?php echo esc_url( $avs_row['detail_url'] ); ?>"><?php esc_html_e( 'جزئیات', 'aromist-volume-stock' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php if ( $avs_pages > 1 ) : ?>
		<div class="avs-pagination">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $avs_paged,
						'total'     => $avs_pages,
						'prev_text' => '›',
						'next_text' => '‹',
					)
				)
			);
			?>
		</div>
	<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/adjust-dialog.php'; ?>
