<?php
/**
 * Stock adjustment dialog (shared by the list and the product page).
 * Opened by buttons with [data-avs-adjust] carrying data-product, data-name, data-pool.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

$avs_back = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
?>
<dialog class="avs-dialog" id="avs-adjust-dialog" aria-labelledby="avs-adjust-title">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="avs-dialog__form">
		<input type="hidden" name="action" value="avs_adjust">
		<input type="hidden" name="product_id" value="">
		<input type="hidden" name="_avs_back" value="<?php echo esc_attr( $avs_back ); ?>">
		<?php wp_nonce_field( 'avs_adjust' ); ?>

		<div class="avs-dialog__head">
			<h2 id="avs-adjust-title"><?php esc_html_e( 'تنظیم موجودی', 'aromist-volume-stock' ); ?></h2>
			<button type="button" class="avs-dialog__close" data-avs-close aria-label="<?php esc_attr_e( 'بستن', 'aromist-volume-stock' ); ?>">×</button>
		</div>

		<p class="avs-dialog__product"><strong data-avs-name></strong> — <?php esc_html_e( 'موجودی فعلی:', 'aromist-volume-stock' ); ?> <strong data-avs-pool></strong></p>
		<p class="avs-help" data-avs-unlimited-note hidden><?php esc_html_e( 'موجودی این عطر الان نامحدود است. مقداری که وارد کنید موجودی مخزن می‌شود و از این به بعد فروش همه‌ی حجم‌ها به آن محدود می‌شود.', 'aromist-volume-stock' ); ?></p>

		<fieldset class="avs-seg">
			<legend class="screen-reader-text"><?php esc_html_e( 'نوع تغییر', 'aromist-volume-stock' ); ?></legend>
			<label><input type="radio" name="operation" value="add" checked> <span><?php esc_html_e( 'افزایش', 'aromist-volume-stock' ); ?></span></label>
			<label data-avs-op-remove><input type="radio" name="operation" value="remove"> <span><?php esc_html_e( 'کاهش', 'aromist-volume-stock' ); ?></span></label>
			<label><input type="radio" name="operation" value="set"> <span><?php esc_html_e( 'تنظیم مقدار دقیق', 'aromist-volume-stock' ); ?></span></label>
		</fieldset>

		<p class="avs-field">
			<label for="avs-amount"><?php esc_html_e( 'مقدار (میل)', 'aromist-volume-stock' ); ?></label>
			<input type="number" id="avs-amount" name="amount" min="0" step="1" required inputmode="numeric" class="avs-input avs-input--big">
			<span class="avs-help" data-avs-preview></span>
		</p>

		<p class="avs-field">
			<label for="avs-reason"><?php esc_html_e( 'دلیل', 'aromist-volume-stock' ); ?></label>
			<select id="avs-reason" name="reason" class="avs-input">
				<?php foreach ( AVS_Manager::manual_reasons() as $avs_key => $avs_label ) : ?>
					<option value="<?php echo esc_attr( $avs_key ); ?>"><?php echo esc_html( $avs_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<p class="avs-field">
			<label for="avs-note"><?php esc_html_e( 'توضیحات (اختیاری)', 'aromist-volume-stock' ); ?></label>
			<input type="text" id="avs-note" name="note" class="avs-input" maxlength="250" placeholder="<?php esc_attr_e( 'مثلاً: ورود یک شیشه ۱۰۰۰ میلی', 'aromist-volume-stock' ); ?>">
		</p>

		<div class="avs-dialog__foot">
			<button type="button" class="button" data-avs-close><?php esc_html_e( 'انصراف', 'aromist-volume-stock' ); ?></button>
			<button type="submit" class="button button-primary avs-btn"><?php esc_html_e( 'ثبت تغییر', 'aromist-volume-stock' ); ?></button>
		</div>
	</form>
</dialog>
