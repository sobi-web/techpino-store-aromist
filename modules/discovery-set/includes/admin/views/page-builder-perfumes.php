<?php
/**
 * «عطرهای ست اختصاصی»: which perfumes customers can choose in the builder.
 *
 * @package AromistDiscoverySet
 *
 * @var array[] $rows ADS_Admin::builder_perfume_rows().
 */

defined( 'ABSPATH' ) || exit;

$ads_on      = count( array_filter( wp_list_pluck( $rows, 'on' ) ) );
$ads_builder = ADS_Pages::builder_url();
?>
<div class="wrap ads-wrap ads-perfumes-page">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'عطرهای ست اختصاصی', 'aromist-discovery-set' ); ?></h1>
	<?php if ( $ads_builder ) : ?>
		<a class="page-title-action" href="<?php echo esc_url( $ads_builder ); ?>" target="_blank"><?php esc_html_e( 'مشاهده‌ی صفحه‌ی ساخت ست', 'aromist-discovery-set' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<p class="description">
		<?php esc_html_e( 'مشتری در صفحه‌ی «ساخت دیسکاوری ست اختصاصی» فقط عطرهای تیک‌خورده را می‌بیند. عطری را می‌توان انتخاب کرد که موجودی حجمی‌اش فعال باشد و حجم قیمت‌دار داشته باشد. اگر موجودی مخزن عطر تعیین شده باشد هر ست از آن کم می‌کند؛ اگر «نامحدود» باشد بدون محدودیت فروخته می‌شود.', 'aromist-discovery-set' ); ?>
	</p>

	<?php if ( ! $ads_on ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'هیچ عطری انتخاب نشده؛ صفحه‌ی «ساخت دیسکاوری ست اختصاصی» الان خالی است. عطرها را تیک بزنید (یا «انتخاب همه‌ی عطرهای قابل انتخاب») و ذخیره کنید.', 'aromist-discovery-set' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ads-card">
		<input type="hidden" name="action" value="ads_builder_perfumes">
		<?php wp_nonce_field( 'ads_builder_perfumes' ); ?>

		<div class="ads-perfumes-tools">
			<input type="search" class="regular-text" data-ads-filter placeholder="<?php esc_attr_e( 'جستجوی عطر…', 'aromist-discovery-set' ); ?>" aria-label="<?php esc_attr_e( 'جستجوی عطر', 'aromist-discovery-set' ); ?>">
			<button type="button" class="button" data-ads-check="all"><?php esc_html_e( 'انتخاب همه‌ی عطرهای قابل انتخاب', 'aromist-discovery-set' ); ?></button>
			<button type="button" class="button" data-ads-check="none"><?php esc_html_e( 'برداشتن همه', 'aromist-discovery-set' ); ?></button>
			<span class="ads-perfumes-count">
				<?php
				/* translators: 1: chosen, 2: total */
				echo esc_html( sprintf( __( '%1$s عطر از %2$s انتخاب شده', 'aromist-discovery-set' ), number_format_i18n( $ads_on ), number_format_i18n( count( $rows ) ) ) );
				?>
			</span>
		</div>

		<table class="widefat striped ads-perfumes-table">
			<thead>
				<tr>
					<td class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'در سازنده', 'aromist-discovery-set' ); ?></span></td>
					<th><?php esc_html_e( 'عطر', 'aromist-discovery-set' ); ?></th>
					<th><?php esc_html_e( 'موجودی حجمی', 'aromist-discovery-set' ); ?></th>
					<th>
						<?php
						/* translators: %s: volume */
						echo esc_html( sprintf( __( 'قیمت %s', 'aromist-discovery-set' ), ads_format_ml( ADS_Settings::default_volume() ) ) );
						?>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $ads_row ) : ?>
					<?php $ads_ok = '' === $ads_row['error']; ?>
					<tr data-name="<?php echo esc_attr( $ads_row['name'] ); ?>" class="<?php echo $ads_ok ? '' : 'is-disabled'; ?>">
						<th scope="row" class="check-column">
							<input type="checkbox" name="ads_in_builder[]" value="<?php echo esc_attr( $ads_row['id'] ); ?>" id="ads-p-<?php echo esc_attr( $ads_row['id'] ); ?>" <?php checked( $ads_row['on'] ); ?> <?php disabled( ! $ads_ok && ! $ads_row['on'] ); ?> data-ok="<?php echo $ads_ok ? '1' : '0'; ?>">
						</th>
						<td>
							<label for="ads-p-<?php echo esc_attr( $ads_row['id'] ); ?>" class="ads-perfume-name">
								<?php echo $ads_row['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<strong><?php echo esc_html( $ads_row['name'] ); ?></strong>
							</label>
							<div class="row-actions"><a href="<?php echo esc_url( $ads_row['edit'] ); ?>"><?php esc_html_e( 'ویرایش عطر', 'aromist-discovery-set' ); ?></a></div>
							<?php if ( ! $ads_ok ) : ?>
								<p class="ads-bad"><?php echo esc_html( $ads_row['error'] ); ?></p>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $ads_row['enabled'] && null === $ads_row['pool'] ) : ?>
								<span class="ads-good">●</span> <?php esc_html_e( 'نامحدود', 'aromist-discovery-set' ); ?>
							<?php elseif ( $ads_row['enabled'] ) : ?>
								<span class="ads-good">●</span> <?php echo esc_html( ads_format_ml( $ads_row['pool'] ) ); ?>
							<?php else : ?>
								<span class="ads-muted"><?php esc_html_e( 'فعال نیست', 'aromist-discovery-set' ); ?></span>
							<?php endif; ?>
							<?php if ( $ads_row['avs_url'] ) : ?>
								<div class="row-actions"><a href="<?php echo esc_url( $ads_row['avs_url'] ); ?>"><?php echo $ads_row['enabled'] ? esc_html__( 'مدیریت مخزن', 'aromist-discovery-set' ) : esc_html__( 'فعال‌سازی موجودی حجمی', 'aromist-discovery-set' ); ?></a></div>
							<?php endif; ?>
						</td>
						<td><?php echo $ads_row['price'] > 0 ? wp_kses_post( wc_price( $ads_row['price'] ) ) : '—'; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php submit_button( __( 'ذخیره', 'aromist-discovery-set' ) ); ?>
	</form>
</div>
