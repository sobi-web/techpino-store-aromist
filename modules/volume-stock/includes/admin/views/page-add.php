<?php
/**
 * Enable volume stock on a product (wizard).
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only selection.
$avs_pid     = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
$avs_product = $avs_pid ? wc_get_product( $avs_pid ) : null;

$avs_active = 'avs-add';
$avs_title  = __( 'افزودن محصول به موجودی حجمی', 'aromist-volume-stock' );
require __DIR__ . '/partials/header.php';
?>

<section class="avs-panel">
	<h2><?php esc_html_e( '۱. انتخاب محصول', 'aromist-volume-stock' ); ?></h2>
	<form method="get" class="avs-inline-form">
		<input type="hidden" name="page" value="avs-add">
		<select class="wc-product-search avs-product-select" name="product_id" data-placeholder="<?php esc_attr_e( 'نام عطر را جستجو کنید…', 'aromist-volume-stock' ); ?>" data-action="woocommerce_json_search_products" data-allow_clear="true">
			<?php if ( $avs_product ) : ?>
				<option value="<?php echo esc_attr( $avs_pid ); ?>" selected><?php echo esc_html( wp_strip_all_tags( $avs_product->get_formatted_name() ) ); ?></option>
			<?php endif; ?>
		</select>
		<button class="button"><?php esc_html_e( 'ادامه', 'aromist-volume-stock' ); ?></button>
	</form>
	<p class="avs-help"><?php esc_html_e( 'فقط محصولات «متغیر» که حجم‌های مختلف دارند قابل انتخاب‌اند.', 'aromist-volume-stock' ); ?></p>
</section>

<?php
if ( ! $avs_product ) :
	// Suggestions: variable products not yet enabled whose variations look like volumes.
	$avs_candidates = wc_get_products(
		array(
			'type'    => 'variable',
			'status'  => array( 'publish' ),
			'limit'   => 40,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
		)
	);
	$avs_suggest    = array();
	foreach ( $avs_candidates as $avs_c ) {
		if ( AVS_Product::is_enabled( $avs_c->get_id() ) ) {
			continue;
		}
		$avs_children = $avs_c->get_children();
		$avs_first    = $avs_children ? wc_get_product( $avs_children[0] ) : null;
		if ( $avs_first instanceof WC_Product_Variation && AVS_Product::detect_ml( $avs_first ) > 0 ) {
			$avs_suggest[] = $avs_c;
		}
		if ( count( $avs_suggest ) >= 15 ) {
			break;
		}
	}
	if ( $avs_suggest ) :
		?>
		<section class="avs-panel">
			<h2><?php esc_html_e( 'پیشنهاد: عطرهای اخیر با حجم‌های مختلف', 'aromist-volume-stock' ); ?></h2>
			<div class="avs-suggest">
				<?php foreach ( $avs_suggest as $avs_c ) : ?>
					<a class="avs-suggest__item" href="<?php echo esc_url( AVS_Admin::url( 'avs-add', array( 'product_id' => $avs_c->get_id() ) ) ); ?>">
						<?php echo wp_kses_post( $avs_c->get_image( array( 36, 36 ) ) ); ?>
						<span><?php echo esc_html( $avs_c->get_name() ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	endif;
	return;
endif;

if ( ! $avs_product instanceof WC_Product_Variable ) {
	echo '<div class="notice notice-error inline"><p>' . esc_html__( 'این محصول متغیر نیست. موجودی حجمی فقط برای محصولات متغیر (با حجم‌های مختلف) کاربرد دارد.', 'aromist-volume-stock' ) . '</p></div>';
	return;
}

if ( AVS_Product::is_enabled( $avs_pid ) ) {
	echo '<div class="notice notice-info inline"><p>' . esc_html__( 'موجودی حجمی روی این محصول فعال است.', 'aromist-volume-stock' ) . ' <a href="' . esc_url( AVS_Admin::url( 'avs', array( 'product_id' => $avs_pid ) ) ) . '">' . esc_html__( 'مدیریت محصول', 'aromist-volume-stock' ) . '</a></p></div>';
	return;
}

$avs_map       = AVS_Manager::resolve_ml_map( $avs_product );
$avs_suggested = AVS_Manager::suggested_pool( $avs_product, $avs_map );
$avs_unit      = AVS_Settings::get( 'unit_label' );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="avs-panel" data-avs-enable-form>
	<input type="hidden" name="action" value="avs_enable">
	<input type="hidden" name="product_id" value="<?php echo esc_attr( $avs_pid ); ?>">
	<?php wp_nonce_field( 'avs_enable' ); ?>

	<h2>
		<?php esc_html_e( '۲. حجم هر متغیر', 'aromist-volume-stock' ); ?>
		<span class="avs-muted">— <?php echo esc_html( $avs_product->get_name() ); ?></span>
	</h2>
	<p class="avs-help"><?php esc_html_e( 'حجم از نام متغیرها خوانده شده؛ اگر اشتباه است اصلاح کنید.', 'aromist-volume-stock' ); ?></p>

	<table class="avs-table avs-table--compact">
		<thead>
			<tr>
				<th><?php esc_html_e( 'متغیر', 'aromist-volume-stock' ); ?></th>
				<th><?php esc_html_e( 'وضعیت', 'aromist-volume-stock' ); ?></th>
				<th><?php esc_html_e( 'موجودی فعلی (عدد)', 'aromist-volume-stock' ); ?></th>
				<th><?php esc_html_e( 'حجم هر عدد', 'aromist-volume-stock' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $avs_product->get_children() as $avs_child_id ) :
				$avs_v = wc_get_product( $avs_child_id );
				if ( ! $avs_v instanceof WC_Product_Variation ) {
					continue;
				}
				$avs_own = true === $avs_v->get_manage_stock( 'edit' );
				?>
				<tr>
					<td><?php echo esc_html( wp_strip_all_tags( wc_get_formatted_variation( $avs_v, true, false ) ) ); ?></td>
					<td><?php echo 'publish' === $avs_v->get_status() ? esc_html__( 'فعال', 'aromist-volume-stock' ) : '<span class="avs-badge">' . esc_html__( 'غیرفعال', 'aromist-volume-stock' ) . '</span>'; ?></td>
					<td><?php echo $avs_own ? esc_html( number_format_i18n( (int) $avs_v->get_stock_quantity( 'edit' ) ) ) : '<span class="avs-muted">—</span>'; ?></td>
					<td>
						<span class="avs-input-unit">
							<input type="number" min="0" step="1" name="ml[<?php echo esc_attr( $avs_child_id ); ?>]" value="<?php echo esc_attr( $avs_map[ $avs_child_id ] ?: '' ); ?>" class="avs-input" data-avs-ml data-stock="<?php echo esc_attr( $avs_own ? (int) $avs_v->get_stock_quantity( 'edit' ) : 0 ); ?>" <?php echo 'publish' === $avs_v->get_status() ? 'required' : ''; ?>>
							<em><?php echo esc_html( $avs_unit ); ?></em>
						</span>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( '۳. موجودی مخزن', 'aromist-volume-stock' ); ?></h2>
	<div class="avs-ml-grid">
		<p class="avs-field">
			<label for="avs-pool"><?php esc_html_e( 'موجودی فعلی انبار این عطر', 'aromist-volume-stock' ); ?></label>
			<span class="avs-input-unit">
				<input type="number" min="0" step="1" id="avs-pool" name="pool_ml" value="<?php echo esc_attr( $avs_suggested ); ?>" class="avs-input avs-input--big" required>
				<em><?php echo esc_html( $avs_unit ); ?></em>
			</span>
			<span class="avs-help">
				<?php
				/* translators: %s: suggested ml */
				printf( esc_html__( 'پیشنهاد بر اساس موجودی فعلی متغیرها: %s — اما بهتر است موجودی واقعی شیشه‌های انبار را بنویسید.', 'aromist-volume-stock' ), '<b data-avs-suggested>' . esc_html( AVS_Settings::format_ml( $avs_suggested ) ) . '</b>' );
				?>
			</span>
		</p>
		<p class="avs-field">
			<label for="avs-low"><?php esc_html_e( 'هشدار کمبود وقتی موجودی کمتر از', 'aromist-volume-stock' ); ?></label>
			<span class="avs-input-unit">
				<input type="number" min="0" step="1" id="avs-low" name="low_ml" value="<?php echo esc_attr( AVS_Settings::get( 'default_low_ml' ) ); ?>" class="avs-input">
				<em><?php echo esc_html( $avs_unit ); ?></em>
			</span>
		</p>
		<p class="avs-field">
			<label for="avs-waste"><?php esc_html_e( 'هدررفت هر عدد (اختیاری)', 'aromist-volume-stock' ); ?></label>
			<span class="avs-input-unit">
				<input type="number" min="0" step="1" id="avs-waste" name="waste_ml" value="0" class="avs-input">
				<em><?php echo esc_html( $avs_unit ); ?></em>
			</span>
		</p>
	</div>

	<p class="avs-field">
		<label for="avs-note"><?php esc_html_e( 'توضیحات (اختیاری)', 'aromist-volume-stock' ); ?></label>
		<input type="text" id="avs-note" name="note" class="avs-input" maxlength="250" placeholder="<?php esc_attr_e( 'مثلاً: شمارش انبار ۱۵ مهر', 'aromist-volume-stock' ); ?>">
	</p>

	<div class="avs-callout">
		<strong><?php esc_html_e( 'بعد از فعال‌سازی چه اتفاقی می‌افتد؟', 'aromist-volume-stock' ); ?></strong>
		<ul>
			<li><?php esc_html_e( 'همه حجم‌ها از همین مخزن برداشت می‌کنند؛ فروش ۲ عدد ۵۰ میل، ۱۰۰ میل از مخزن کم می‌کند.', 'aromist-volume-stock' ); ?></li>
			<li><?php esc_html_e( 'موجودی جداگانه متغیرها خاموش می‌شود و «موجودی» محصول همان موجودی مخزن (میل) است.', 'aromist-volume-stock' ); ?></li>
			<li><?php esc_html_e( 'هر حجمی که در مخزن جا نشود خودکار «ناموجود» می‌شود.', 'aromist-volume-stock' ); ?></li>
			<li><?php esc_html_e( 'سفارش‌های قبلی این محصول تبدیل می‌شوند تا لغو و مرجوعی آن‌ها به مخزن برگردد.', 'aromist-volume-stock' ); ?></li>
		</ul>
	</div>

	<p><button class="button button-primary button-hero avs-btn"><?php esc_html_e( 'فعال‌سازی موجودی حجمی', 'aromist-volume-stock' ); ?></button></p>
</form>
