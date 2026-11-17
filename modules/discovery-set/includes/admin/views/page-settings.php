<?php
/**
 * Settings page.
 *
 * @package AromistDiscoverySet
 *
 * @var array $settings All settings.
 * @var array $volumes  ml => row.
 * @var array $boxes    id => name.
 */

defined( 'ABSPATH' ) || exit;

$ads_default = ADS_Settings::default_volume();
$ads_custom  = absint( $settings['custom_product_id'] );
$ads_hold    = (int) get_option( 'woocommerce_hold_stock_minutes', 60 );

/**
 * One row of the volumes table.
 *
 * @param string $index  Row index.
 * @param array  $row    Row.
 * @param array  $boxes  Boxes.
 * @param int    $default Default volume.
 */
$ads_volume_row = static function ( $index, $row, $boxes, $default ) {
	?>
	<tr class="ads-volume-row">
		<td><input type="number" min="1" step="1" class="small-text" name="ads[volumes][<?php echo esc_attr( $index ); ?>][ml]" value="<?php echo esc_attr( $row['ml'] ); ?>" required> <?php esc_html_e( 'میل', 'aromist-discovery-set' ); ?></td>
		<td><input type="text" inputmode="numeric" class="regular-text ads-num" name="ads[volumes][<?php echo esc_attr( $index ); ?>][fee]" value="<?php echo esc_attr( '' === $row['ml'] ? '' : wc_format_localized_price( $row['fee'] ) ); ?>" placeholder="0"> <?php echo esc_html( get_woocommerce_currency_symbol() ); ?></td>
		<td>
			<select name="ads[volumes][<?php echo esc_attr( $index ); ?>][box]">
				<option value="0"><?php esc_html_e( 'بدون شمارش جعبه', 'aromist-discovery-set' ); ?></option>
				<?php foreach ( $boxes as $ads_box_id => $ads_box_name ) : ?>
					<option value="<?php echo esc_attr( $ads_box_id ); ?>" <?php selected( (int) $row['box'], (int) $ads_box_id ); ?>><?php echo esc_html( $ads_box_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td><input type="radio" name="ads[default_volume]" value="<?php echo esc_attr( $row['ml'] ); ?>" <?php checked( (int) $row['ml'], (int) $default ); ?> aria-label="<?php esc_attr_e( 'حجم پیش‌فرض', 'aromist-discovery-set' ); ?>"></td>
		<td><button type="button" class="button-link ads-remove-row"><?php esc_html_e( 'حذف', 'aromist-discovery-set' ); ?></button></td>
	</tr>
	<?php
};
?>
<div class="wrap ads-wrap">
	<h1><?php esc_html_e( 'تنظیمات دیسکاوری ست', 'aromist-discovery-set' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ads-card">
		<input type="hidden" name="action" value="ads_save_settings">
		<?php wp_nonce_field( 'ads_save_settings' ); ?>

		<h2><?php esc_html_e( 'حجم و بسته‌بندی', 'aromist-discovery-set' ); ?></h2>
		<?php if ( 1 === count( $volumes ) ) : ?>
			<?php $ads_row = reset( $volumes ); ?>
			<p class="description">
				<?php
				/* translators: 1: number of perfumes, 2: volume */
				echo esc_html( sprintf( __( 'همه‌ی دیسکاوری ست‌ها (آماده و اختصاصی) %1$s عطر، از هر کدام %2$s هستند.', 'aromist-discovery-set' ), number_format_i18n( ads_set_size() ), ads_format_ml( $ads_row['ml'] ) ) );
				?>
			</p>
			<input type="hidden" name="ads[volumes][0][ml]" value="<?php echo esc_attr( $ads_row['ml'] ); ?>">
			<input type="hidden" name="ads[default_volume]" value="<?php echo esc_attr( $ads_row['ml'] ); ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ads-fee"><?php esc_html_e( 'هزینه‌ی بسته‌بندی هر ست', 'aromist-discovery-set' ); ?></label></th>
					<td><input type="text" inputmode="numeric" id="ads-fee" class="regular-text ads-num" name="ads[volumes][0][fee]" value="<?php echo esc_attr( wc_format_localized_price( $ads_row['fee'] ) ); ?>" placeholder="0"> <?php echo esc_html( get_woocommerce_currency_symbol() ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="ads-box"><?php esc_html_e( 'جعبه', 'aromist-discovery-set' ); ?></label></th>
					<td>
						<select id="ads-box" name="ads[volumes][0][box]">
							<option value="0"><?php esc_html_e( 'بدون شمارش جعبه', 'aromist-discovery-set' ); ?></option>
							<?php foreach ( $boxes as $ads_box_id => $ads_box_name ) : ?>
								<option value="<?php echo esc_attr( $ads_box_id ); ?>" <?php selected( (int) $ads_row['box'], (int) $ads_box_id ); ?>><?php echo esc_html( $ads_box_name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'هر ست فروخته‌شده یک عدد از این جعبه کم می‌کند.', 'aromist-discovery-set' ); ?></p>
					</td>
				</tr>
			</table>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'حجم‌هایی که ست‌ها می‌توانند در آن فروخته شوند.', 'aromist-discovery-set' ); ?></p>
			<table class="widefat striped ads-volumes-settings">
				<thead>
					<tr>
						<th><?php esc_html_e( 'حجم هر عطر', 'aromist-discovery-set' ); ?></th>
						<th><?php esc_html_e( 'هزینه‌ی بسته‌بندی', 'aromist-discovery-set' ); ?></th>
						<th><?php esc_html_e( 'جعبه', 'aromist-discovery-set' ); ?></th>
						<th><?php esc_html_e( 'پیش‌فرض', 'aromist-discovery-set' ); ?></th>
						<th><span class="screen-reader-text"><?php esc_html_e( 'حذف', 'aromist-discovery-set' ); ?></span></th>
					</tr>
				</thead>
				<tbody class="ads-volume-rows">
					<?php
					$ads_i = 0;
					foreach ( $volumes as $ads_row ) {
						$ads_volume_row( $ads_i++, $ads_row, $boxes, $ads_default );
					}
					?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'صفحه‌ها و نمایش', 'aromist-discovery-set' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			foreach (
				array(
					'archive_page_id' => array( __( 'صفحه‌ی دیسکاوری ست‌های آماده', 'aromist-discovery-set' ), '[aromist_discovery_sets]', __( 'آدرس هر ست زیر آدرس همین صفحه است (پیش‌نویس هم باشد کافی است؛ آن را حذف نکنید).', 'aromist-discovery-set' ) ),
					'builder_page_id' => array( __( 'صفحه‌ی ساخت دیسکاوری ست اختصاصی', 'aromist-discovery-set' ), '[aromist_set_builder]', __( 'عطرهایی که مشتری می‌تواند انتخاب کند در «دیسکاوری ست ← عطرهای ست اختصاصی» تعیین می‌شوند.', 'aromist-discovery-set' ) ),
				) as $ads_key => $ads_page
			) :
				$ads_page_id = absint( $settings[ $ads_key ] );
				?>
				<tr>
					<th scope="row"><label for="ads-<?php echo esc_attr( $ads_key ); ?>"><?php echo esc_html( $ads_page[0] ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'ads[' . $ads_key . ']',
								'id'                => 'ads-' . $ads_key,
								'selected'          => $ads_page_id,
								'show_option_none'  => __( '— انتخاب نشده —', 'aromist-discovery-set' ),
								'option_none_value' => '0',
								'post_status'       => array( 'publish', 'draft', 'private' ),
							)
						);
						?>
						<?php if ( $ads_page_id && get_post( $ads_page_id ) ) : ?>
							<a href="<?php echo esc_url( get_permalink( $ads_page_id ) ); ?>" target="_blank"><?php esc_html_e( 'مشاهده', 'aromist-discovery-set' ); ?></a>
							· <a href="<?php echo esc_url( get_edit_post_link( $ads_page_id ) ); ?>"><?php esc_html_e( 'ویرایش', 'aromist-discovery-set' ); ?></a>
							<?php if ( 'publish' !== get_post_status( $ads_page_id ) ) : ?>
								· <strong class="ads-bad"><?php esc_html_e( 'منتشر نشده (فقط مدیر می‌بیند)', 'aromist-discovery-set' ); ?></strong>
							<?php endif; ?>
						<?php endif; ?>
						<p class="description">
							<?php
							/* translators: %s: shortcode */
							echo esc_html( sprintf( __( 'شورت‌کد این صفحه: %s', 'aromist-discovery-set' ), $ads_page[1] ) );
							echo $ads_page[2] ? ' ' . esc_html( $ads_page[2] ) : '';
							?>
						</p>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'لینک «دیسکاوری ست‌ها»', 'aromist-discovery-set' ); ?></th>
				<td>
					<fieldset>
						<label><input type="radio" name="ads[archive_link]" value="category" <?php checked( 'page' !== $settings['archive_link'] ); ?>> <?php esc_html_e( 'آرشیو دسته‌ی «دیسکاوری ست» (قالب آرشیو المنتور با شورت‌کد [aromist_discovery_sets])', 'aromist-discovery-set' ); ?></label><br>
						<label><input type="radio" name="ads[archive_link]" value="page" <?php checked( 'page', $settings['archive_link'] ); ?>> <?php esc_html_e( 'صفحه‌ی دیسکاوری ست‌های آماده (بالا)', 'aromist-discovery-set' ); ?></label>
					</fieldset>
					<p class="description"><?php esc_html_e( 'مسیر صفحه‌ی هر ست و لینک «همه‌ی ست‌ها» به اینجا می‌رود.', 'aromist-discovery-set' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'صفحه‌ی اختصاصی هر ست', 'aromist-discovery-set' ); ?></th>
				<td><label><input type="checkbox" name="ads[own_template]" value="1" <?php checked( 'yes', $settings['own_template'] ); ?>> <?php esc_html_e( 'ست‌های آماده با طراحی اختصاصی افزونه نمایش داده شوند (نه قالب محصول المنتور)', 'aromist-discovery-set' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'نمایش در فروشگاه', 'aromist-discovery-set' ); ?></th>
				<td><label><input type="checkbox" name="ads[show_in_shop]" value="1" <?php checked( 'yes', $settings['show_in_shop'] ); ?>> <?php esc_html_e( 'ست‌ها در فروشگاه و دسته‌های اصلی هم نمایش داده شوند (پیش‌فرض: فقط در صفحه‌ی دیسکاوری ست‌ها و جستجو)', 'aromist-discovery-set' ); ?></label></td>
			</tr>
		</table>


		<h2><?php esc_html_e( 'شورت‌کدها', 'aromist-discovery-set' ); ?></h2>
		<p class="description"><?php esc_html_e( 'بالای صفحه‌ها را در المنتور طراحی کنید و این شورت‌کدها را در ویجت «کد کوتاه» بگذارید؛ عرض و فاصله‌ی کناری را بخش المنتور تعیین می‌کند.', 'aromist-discovery-set' ); ?></p>
		<table class="widefat striped ads-shortcodes">
			<tbody>
				<tr>
					<td><code>[aromist_discovery_sets]</code></td>
					<td><?php esc_html_e( 'ست‌های آماده. در قالب آرشیو دسته‌ی «دیسکاوری ست» (و زیردسته‌ها) خودش ست‌های همان دسته را نشان می‌دهد: در «دیسکاوری ست» همه، در «دیسکاوری ست مردانه» فقط مردانه‌ها.', 'aromist-discovery-set' ); ?></td>
				</tr>
				<tr>
					<td><code>[aromist_discovery_sets category="discovery-set-for-man"]</code></td>
					<td><?php esc_html_e( 'فقط ست‌های یک دسته (نامک، نام یا شناسه‌ی دسته) — برای برگه‌های معمولی.', 'aromist-discovery-set' ); ?></td>
				</tr>
				<tr>
					<td><code>columns="3"</code> · <code>limit="6"</code> · <code>builder_card="no"</code> · <code>hero="yes"</code></td>
					<td><?php esc_html_e( 'تعداد ستون در صفحه‌ی پهن (۱ تا ۴)، حداکثر تعداد ست، حذف کارت «ست خودت را بساز»، نمایش معرفی خود افزونه در بالا.', 'aromist-discovery-set' ); ?></td>
				</tr>
				<tr>
					<td><code>[aromist_set_builder]</code></td>
					<td><?php esc_html_e( 'ساخت دیسکاوری ست اختصاصی (با hero="yes" معرفی خود افزونه هم بالایش می‌آید). صفحه‌ای که این شورت‌کد را دارد در «صفحه‌ی ساخت دیسکاوری ست اختصاصی» بالا انتخاب کنید.', 'aromist-discovery-set' ); ?></td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'قیمت', 'aromist-discovery-set' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ads-discount"><?php esc_html_e( 'تخفیف ست‌های آماده (٪)', 'aromist-discovery-set' ); ?></label></th>
				<td>
					<input type="text" inputmode="decimal" id="ads-discount" class="small-text ads-num" name="ads[discount]" value="<?php echo esc_attr( wc_format_localized_decimal( ADS_Settings::discount() ) ); ?>"> ٪
					<p class="description"><?php esc_html_e( 'از جمع قیمت ۴ عطرِ ست‌هایی که در پیشخوان می‌سازید کم می‌شود. ست‌هایی که «قیمت ثابت» دارند شامل آن نمی‌شوند.', 'aromist-discovery-set' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ads-custom-discount"><?php esc_html_e( 'تخفیف ست‌های اختصاصی (٪)', 'aromist-discovery-set' ); ?></label></th>
				<td>
					<input type="text" inputmode="decimal" id="ads-custom-discount" class="small-text ads-num" name="ads[custom_discount]" value="<?php echo esc_attr( wc_format_localized_decimal( ADS_Settings::custom_discount() ) ); ?>"> ٪
					<p class="description"><?php esc_html_e( 'از جمع قیمت ۴ عطرِ ست‌هایی که مشتری خودش می‌سازد (و ست‌های آماده‌ای که «شخصی» می‌کند) کم می‌شود.', 'aromist-discovery-set' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ads-round"><?php esc_html_e( 'گرد کردن قیمت به مضرب', 'aromist-discovery-set' ); ?></label></th>
				<td>
					<input type="text" inputmode="numeric" id="ads-round" class="regular-text ads-num" name="ads[round_to]" value="<?php echo esc_attr( (int) $settings['round_to'] ); ?>"> <?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
					<p class="description"><?php esc_html_e( 'مثلاً ۱۰۰۰: قیمت‌ها به نزدیک‌ترین هزار گرد می‌شوند. صفر یعنی بدون گرد کردن.', 'aromist-discovery-set' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ads-max-saved"><?php esc_html_e( 'حداکثر «ست‌های من» هر مشتری', 'aromist-discovery-set' ); ?></label></th>
				<td>
					<input type="number" min="1" max="100" step="1" id="ads-max-saved" class="small-text" name="ads[max_saved_sets]" value="<?php echo esc_attr( ADS_Saved_Sets::max() ); ?>">
					<p class="description"><?php esc_html_e( 'تعداد ست‌هایی که مشتری می‌تواند در پنل کاربری ذخیره کند.', 'aromist-discovery-set' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'یادداشت سفارش', 'aromist-discovery-set' ); ?></th>
				<td><label><input type="checkbox" name="ads[order_notes]" value="1" <?php checked( 'yes', $settings['order_notes'] ); ?>> <?php esc_html_e( 'برداشت و بازگشت موجودی ست‌ها در یادداشت سفارش نوشته شود', 'aromist-discovery-set' ); ?></label></td>
			</tr>
		</table>

		<?php submit_button( __( 'ذخیره‌ی تنظیمات', 'aromist-discovery-set' ) ); ?>
	</form>

	<div class="ads-card">
		<h2><?php esc_html_e( 'جعبه‌ها', 'aromist-discovery-set' ); ?></h2>
		<p class="description"><?php esc_html_e( 'هر ست فروخته‌شده یک عدد از جعبه‌ی حجم خودش کم می‌کند. جعبه‌ها محصول خصوصی ووکامرس هستند و در فروشگاه نمایش داده نمی‌شوند.', 'aromist-discovery-set' ); ?></p>
		<?php if ( $boxes ) : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'جعبه', 'aromist-discovery-set' ); ?></th>
						<th><?php esc_html_e( 'موجودی', 'aromist-discovery-set' ); ?></th>
						<th><?php esc_html_e( 'تغییر موجودی', 'aromist-discovery-set' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $boxes as $ads_box_id => $ads_box_name ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( $ads_box_id ) ); ?>"><?php echo esc_html( $ads_box_name ); ?></a></td>
							<td><strong><?php echo esc_html( number_format_i18n( ADS_Boxes::stock( $ads_box_id ) ) ); ?></strong> <?php esc_html_e( 'عدد', 'aromist-discovery-set' ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ads-inline-form">
									<input type="hidden" name="action" value="ads_box">
									<input type="hidden" name="do" value="adjust">
									<input type="hidden" name="box" value="<?php echo esc_attr( $ads_box_id ); ?>">
									<?php wp_nonce_field( 'ads_box' ); ?>
									<select name="operation" aria-label="<?php esc_attr_e( 'نوع تغییر', 'aromist-discovery-set' ); ?>">
										<option value="increase"><?php esc_html_e( 'افزایش (ورود جعبه)', 'aromist-discovery-set' ); ?></option>
										<option value="decrease"><?php esc_html_e( 'کاهش (خرابی)', 'aromist-discovery-set' ); ?></option>
										<option value="set"><?php esc_html_e( 'تنظیم مقدار دقیق', 'aromist-discovery-set' ); ?></option>
									</select>
									<input type="text" inputmode="numeric" class="small-text ads-num" name="amount" required aria-label="<?php esc_attr_e( 'تعداد', 'aromist-discovery-set' ); ?>">
									<button type="submit" class="button"><?php esc_html_e( 'ثبت', 'aromist-discovery-set' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h3><?php esc_html_e( 'جعبه‌ی جدید', 'aromist-discovery-set' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ads-inline-form">
			<input type="hidden" name="action" value="ads_box">
			<input type="hidden" name="do" value="create">
			<?php wp_nonce_field( 'ads_box' ); ?>
			<input type="text" name="name" class="regular-text" placeholder="<?php esc_attr_e( 'مثلاً جعبه‌ی ۱۰ میل', 'aromist-discovery-set' ); ?>" required aria-label="<?php esc_attr_e( 'نام جعبه', 'aromist-discovery-set' ); ?>">
			<input type="text" inputmode="numeric" name="stock" class="small-text ads-num" placeholder="0" aria-label="<?php esc_attr_e( 'موجودی اولیه', 'aromist-discovery-set' ); ?>">
			<button type="submit" class="button"><?php esc_html_e( 'ساخت جعبه', 'aromist-discovery-set' ); ?></button>
		</form>
	</div>

	<div class="ads-card">
		<h2><?php esc_html_e( 'ست سفارشی (ساخت مشتری)', 'aromist-discovery-set' ); ?></h2>
		<p>
			<?php if ( $ads_custom && get_post( $ads_custom ) ) : ?>
				<?php
				printf(
					/* translators: %s: edit link */
					esc_html__( 'ست‌هایی که مشتری خودش می‌سازد با محصول سیستمی %s به سبد اضافه می‌شوند. تصویر شاخص آن، تصویر ست سفارشی در سبد خرید است.', 'aromist-discovery-set' ),
					'<a href="' . esc_url( get_edit_post_link( $ads_custom ) ) . '">' . esc_html( get_the_title( $ads_custom ) ) . '</a>'
				);
				?>
			<?php else : ?>
				<?php esc_html_e( 'محصول سیستمی «ست سفارشی» هنوز ساخته نشده است؛ در بارگذاری بعدی خودکار ساخته می‌شود.', 'aromist-discovery-set' ); ?>
			<?php endif; ?>
		</p>
	</div>

	<div class="ads-card">
		<h2><?php esc_html_e( 'وضعیت سیستم', 'aromist-discovery-set' ); ?></h2>
		<ul class="ads-status">
			<li class="<?php echo 'yes' === get_option( 'woocommerce_manage_stock' ) ? 'is-ok' : 'is-bad'; ?>"><?php esc_html_e( 'مدیریت موجودی ووکامرس روشن است', 'aromist-discovery-set' ); ?></li>
			<li class="<?php echo $ads_hold > 0 ? 'is-ok' : 'is-bad'; ?>">
				<?php
				/* translators: %s: minutes */
				echo esc_html( sprintf( __( 'نگه‌داشتن موجودی هنگام پرداخت: %s دقیقه', 'aromist-discovery-set' ), number_format_i18n( $ads_hold ) ) );
				?>
			</li>
			<li class="is-ok">
				<?php
				/* translators: %s: version */
				echo esc_html( sprintf( __( 'موجودی حجمی تکپینو نسخه‌ی %s', 'aromist-discovery-set' ), AVS_VERSION ) );
				?>
			</li>
			<li class="<?php echo ADS_Install::category_id( false ) ? 'is-ok' : 'is-bad'; ?>"><?php esc_html_e( 'دسته‌ی «دیسکاوری ست» (برای شرط نمایش قالب المنتور؛ با ذخیره‌ی اولین ست ساخته می‌شود)', 'aromist-discovery-set' ); ?></li>
		</ul>
	</div>
</div>
