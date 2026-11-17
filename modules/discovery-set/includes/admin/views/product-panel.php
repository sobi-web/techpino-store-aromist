<?php
/**
 * «محتوای ست» product data panel.
 *
 * @package AromistDiscoverySet
 *
 * @var WC_Product_Discovery_Set|null $set        Set being edited (null for a new product).
 * @var int[]                         $components Perfume ids.
 * @var int[]                         $own        Volumes checked.
 * @var int                           $default    Default volume.
 * @var string                        $mode       auto|fixed.
 * @var bool                          $is_custom  The «ست سفارشی» system product.
 */

defined( 'ABSPATH' ) || exit;

$ads_volumes = ADS_Settings::volumes();
$ads_size    = ads_set_size();
?>
<div id="ads_set_data" class="panel woocommerce_options_panel hidden ads-panel">
	<?php if ( $is_custom ) : ?>
		<div class="options_group">
			<p class="ads-panel__note"><?php esc_html_e( 'این محصول سیستمی است: هر ستی که مشتری خودش می‌سازد با این محصول به سبد اضافه می‌شود. عطرها و قیمت هر ست از انتخاب مشتری ساخته می‌شود.', 'aromist-discovery-set' ); ?></p>
		</div>
	<?php else : ?>

		<div class="options_group">
			<h4 class="ads-panel__title">
				<?php
				/* translators: %s: number of perfumes */
				echo esc_html( sprintf( __( '%s عطر این ست', 'aromist-discovery-set' ), number_format_i18n( $ads_size ) ) );
				?>
			</h4>
			<p class="ads-panel__note"><?php esc_html_e( 'فقط عطرهای متغیر منتشرشده‌ای که «موجودی حجمی» دارند قابل انتخاب‌اند. ترتیب همین‌جا ترتیب نمایش در صفحه‌ی محصول و گالری است.', 'aromist-discovery-set' ); ?></p>
			<div class="ads-slots">
				<?php for ( $ads_i = 0; $ads_i < $ads_size; $ads_i++ ) : ?>
					<?php $ads_id = isset( $components[ $ads_i ] ) ? (int) $components[ $ads_i ] : 0; ?>
					<div class="ads-slot">
						<span class="ads-slot__num"><?php echo esc_html( number_format_i18n( $ads_i + 1 ) ); ?></span>
						<select class="ads-perfume-select" name="ads_components[]" data-placeholder="<?php esc_attr_e( 'انتخاب عطر…', 'aromist-discovery-set' ); ?>" style="width:100%">
							<option value=""></option>
							<?php if ( $ads_id ) : ?>
								<option value="<?php echo esc_attr( $ads_id ); ?>" selected><?php echo esc_html( ADS_Set::perfume_name( $ads_id ) ); ?></option>
							<?php endif; ?>
						</select>
					</div>
				<?php endfor; ?>
			</div>
		</div>

		<div class="options_group">
			<h4 class="ads-panel__title"><?php esc_html_e( 'حجم', 'aromist-discovery-set' ); ?></h4>
			<?php if ( 1 === count( $ads_volumes ) ) : ?>
				<?php $ads_ml = (int) key( $ads_volumes ); ?>
				<input type="hidden" name="ads_volumes[]" value="<?php echo esc_attr( $ads_ml ); ?>">
				<input type="hidden" name="ads_default_volume" value="<?php echo esc_attr( $ads_ml ); ?>">
				<p class="ads-panel__fixed">
					<?php
					/* translators: 1: number of perfumes, 2: volume */
					echo esc_html( sprintf( __( 'هر ست: %1$s عطر، از هر کدام %2$s (ثابت برای همه‌ی ست‌ها)', 'aromist-discovery-set' ), number_format_i18n( $ads_size ), ads_format_ml( $ads_ml ) ) );
					?>
				</p>
				<p class="form-field ads-fixed-col">
					<label for="ads-fixed-<?php echo esc_attr( $ads_ml ); ?>"><?php esc_html_e( 'قیمت ثابت ست', 'aromist-discovery-set' ); ?></label>
					<input type="text" inputmode="decimal" id="ads-fixed-<?php echo esc_attr( $ads_ml ); ?>" class="short wc_input_price ads-fixed-input" name="ads_fixed_prices[<?php echo esc_attr( $ads_ml ); ?>]" value="<?php echo esc_attr( $set ? wc_format_localized_price( $set->get_fixed_price( $ads_ml ) ) : '' ); ?>">
				</p>
			<?php else : ?>
				<table class="ads-volumes-table widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ارائه شود', 'aromist-discovery-set' ); ?></th>
							<th><?php esc_html_e( 'پیش‌فرض', 'aromist-discovery-set' ); ?></th>
							<th class="ads-fixed-col"><?php esc_html_e( 'قیمت ثابت', 'aromist-discovery-set' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $ads_volumes as $ads_ml => $ads_row ) : ?>
							<tr>
								<td><label><input type="checkbox" class="ads-volume-check" name="ads_volumes[]" value="<?php echo esc_attr( $ads_ml ); ?>" <?php checked( in_array( (int) $ads_ml, $own, true ) ); ?>> <?php echo esc_html( ads_format_ml( $ads_ml ) ); ?></label></td>
								<td><input type="radio" class="ads-default-radio" name="ads_default_volume" value="<?php echo esc_attr( $ads_ml ); ?>" <?php checked( (int) $ads_ml, (int) $default ); ?>></td>
								<td class="ads-fixed-col"><input type="text" inputmode="decimal" class="short wc_input_price ads-fixed-input" name="ads_fixed_prices[<?php echo esc_attr( $ads_ml ); ?>]" value="<?php echo esc_attr( $set ? wc_format_localized_price( $set->get_fixed_price( $ads_ml ) ) : '' ); ?>" placeholder="<?php esc_attr_e( 'خودکار', 'aromist-discovery-set' ); ?>"></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="options_group">
			<h4 class="ads-panel__title"><?php esc_html_e( 'قیمت', 'aromist-discovery-set' ); ?></h4>
			<p class="ads-mode">
				<label><input type="radio" name="ads_price_mode" value="auto" <?php checked( 'auto', $mode ); ?>> <?php esc_html_e( 'خودکار: جمع قیمت عطرها با «تخفیف ست‌های آماده»', 'aromist-discovery-set' ); ?></label>
				<label><input type="radio" name="ads_price_mode" value="fixed" <?php checked( 'fixed', $mode ); ?>> <?php esc_html_e( 'قیمت ثابت برای هر حجم', 'aromist-discovery-set' ); ?></label>
			</p>
			<p class="ads-panel__note">
				<?php
				printf(
					/* translators: 1: discount percent, 2: settings link */
					esc_html__( 'تخفیف فعلی ست‌های آماده: %1$s٪ — قابل تغییر در %2$s. قیمت هر عطر: اگر متغیری با همان حجم دارد قیمت همان، وگرنه قیمت هر میلِ کوچک‌ترین حجم × حجم ست.', 'aromist-discovery-set' ),
					esc_html( ads_format_percent( ADS_Settings::discount() ) ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=ads-settings' ) ) . '">' . esc_html__( 'تنظیمات', 'aromist-discovery-set' ) . '</a>'
				);
				?>
			</p>
		</div>

		<div class="options_group">
			<h4 class="ads-panel__title">
				<?php esc_html_e( 'پیش‌نمایش قیمت و موجودی', 'aromist-discovery-set' ); ?>
				<button type="button" class="button button-small ads-preview-refresh"><?php esc_html_e( 'به‌روزرسانی', 'aromist-discovery-set' ); ?></button>
			</h4>
			<div class="ads-preview" aria-live="polite">
				<?php
				if ( $set && $components ) {
					ADS_Admin_Product::render_preview( $set );
				} else {
					echo '<p class="ads-panel__note">' . esc_html__( 'بعد از انتخاب عطرها، قیمت و موجودی هر حجم اینجا نمایش داده می‌شود.', 'aromist-discovery-set' ) . '</p>';
				}
				?>
			</div>
			<p class="ads-panel__note"><?php esc_html_e( '«تصویر محصول» را کاور اختصاصی ست قرار دهید. اگر «گالری محصول» را خالی بگذارید، گالری خودکار از تصویر شاخص ۴ عطر ساخته می‌شود؛ اگر تصویری به گالری اضافه کنید، همان نمایش داده می‌شود. ست خودکار در دسته‌ی «دیسکاوری ست» قرار می‌گیرد.', 'aromist-discovery-set' ); ?></p>
		</div>

	<?php endif; ?>
</div>
