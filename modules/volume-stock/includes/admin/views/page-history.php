<?php
/**
 * Stock history (ledger).
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
$avs_filters = AVS_Admin::history_filters( $_GET );
$avs_paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$avs_in      = array(
	'product_id' => isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0,
	'reason'     => isset( $_GET['reason'] ) ? sanitize_key( wp_unslash( $_GET['reason'] ) ) : '',
	'from'       => isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
	'to'         => isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '',
	'order_id'   => isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0,
);
// phpcs:enable

$avs_per_page = 50;
$avs_total    = AVS_Ledger::count( $avs_filters );
$avs_pages    = max( 1, (int) ceil( $avs_total / $avs_per_page ) );
$avs_rows     = AVS_Ledger::query( array_merge( $avs_filters, array( 'per_page' => $avs_per_page, 'page' => $avs_paged ) ) );

$avs_products = array();
foreach ( AVS_Reports::enabled_product_ids() as $avs_id ) {
	$avs_p = wc_get_product( $avs_id );
	if ( $avs_p ) {
		$avs_products[ $avs_id ] = $avs_p->get_name();
	}
}
asort( $avs_products );

$avs_active = 'avs-history';
$avs_title  = __( 'تاریخچه موجودی', 'aromist-volume-stock' );
require __DIR__ . '/partials/header.php';
?>

<form method="get" class="avs-toolbar avs-toolbar--wrap">
	<input type="hidden" name="page" value="avs-history">
	<select name="product_id" class="avs-input" aria-label="<?php esc_attr_e( 'محصول', 'aromist-volume-stock' ); ?>">
		<option value=""><?php esc_html_e( 'همه محصولات', 'aromist-volume-stock' ); ?></option>
		<?php foreach ( $avs_products as $avs_id => $avs_name ) : ?>
			<option value="<?php echo esc_attr( $avs_id ); ?>" <?php selected( $avs_in['product_id'], $avs_id ); ?>><?php echo esc_html( $avs_name ); ?></option>
		<?php endforeach; ?>
	</select>
	<select name="reason" class="avs-input" aria-label="<?php esc_attr_e( 'نوع', 'aromist-volume-stock' ); ?>">
		<option value=""><?php esc_html_e( 'همه انواع', 'aromist-volume-stock' ); ?></option>
		<option value="orders" <?php selected( $avs_in['reason'], 'orders' ); ?>><?php esc_html_e( 'فقط سفارش‌ها', 'aromist-volume-stock' ); ?></option>
		<?php foreach ( AVS_Ledger::reasons() as $avs_key => $avs_label ) : ?>
			<option value="<?php echo esc_attr( $avs_key ); ?>" <?php selected( $avs_in['reason'], $avs_key ); ?>><?php echo esc_html( $avs_label ); ?></option>
		<?php endforeach; ?>
	</select>
	<label class="avs-date"><?php esc_html_e( 'از', 'aromist-volume-stock' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $avs_in['from'] ); ?>" class="avs-input"></label>
	<label class="avs-date"><?php esc_html_e( 'تا', 'aromist-volume-stock' ); ?> <input type="date" name="to" value="<?php echo esc_attr( $avs_in['to'] ); ?>" class="avs-input"></label>
	<input type="number" name="order_id" value="<?php echo $avs_in['order_id'] ? esc_attr( $avs_in['order_id'] ) : ''; ?>" class="avs-input avs-input--sm" placeholder="<?php esc_attr_e( 'شماره سفارش', 'aromist-volume-stock' ); ?>">
	<button class="button"><?php esc_html_e( 'فیلتر', 'aromist-volume-stock' ); ?></button>
	<?php if ( array_filter( $avs_in ) ) : ?>
		<a class="button-link" href="<?php echo esc_url( AVS_Admin::url( 'avs-history' ) ); ?>"><?php esc_html_e( 'پاک کردن فیلترها', 'aromist-volume-stock' ); ?></a>
	<?php endif; ?>
</form>

<div class="avs-panel__head">
	<p class="avs-muted">
		<?php
		/* translators: %s: count */
		printf( esc_html__( '%s ردیف', 'aromist-volume-stock' ), esc_html( number_format_i18n( $avs_total ) ) );
		?>
	</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="avs_export_history">
		<?php wp_nonce_field( 'avs_export_history' ); ?>
		<?php foreach ( $avs_in as $avs_key => $avs_value ) : ?>
			<?php if ( $avs_value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $avs_key ); ?>" value="<?php echo esc_attr( $avs_value ); ?>">
			<?php endif; ?>
		<?php endforeach; ?>
		<button class="button"><?php esc_html_e( 'خروجی اکسل (CSV)', 'aromist-volume-stock' ); ?></button>
	</form>
</div>

<?php
$avs_rows_history = $avs_rows;
$avs_show_product = true;
require __DIR__ . '/partials/history-table.php';

if ( $avs_pages > 1 ) :
	?>
	<div class="avs-pagination">
		<?php
		echo wp_kses_post(
			paginate_links(
				array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'current'   => min( $avs_paged, $avs_pages ),
					'total'     => $avs_pages,
					'prev_text' => '›',
					'next_text' => '‹',
				)
			)
		);
		?>
	</div>
<?php endif; ?>
