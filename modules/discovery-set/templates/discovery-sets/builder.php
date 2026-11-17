<?php
/**
 * «ساخت دیسکاوری ست اختصاصی» ([aromist_set_builder]).
 *
 * Override: yourtheme/woocommerce/discovery-sets/builder.php
 *
 * @package AromistDiscoverySet
 *
 * @var array[]    $perfumes Perfume cards (+ f [ filter key => option ids ], available).
 * @var array[]    $filters  Select boxes: [ key, label, options [ id, name, count ] ].
 * @var array|null $editing  Saved set being edited: [ id, name ].
 * @var array      $config   Settings for builder.js.
 * @var bool       $hero     Show the plugin's own intro (hero="yes"); the page's top is designed in Elementor.
 */

defined( 'ABSPATH' ) || exit;

$ads_size   = (int) $config['size'];
$ads_volume = ads_format_ml( $config['volume'] );
$ads_info   = array();
foreach ( $perfumes as $ads_p ) {
	$ads_info[ $ads_p['id'] ] = ADS_Perfume_Info::for_js( $ads_p['id'] );
}
$ads_box_icon = '<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M3.8 7.5 12 3.5l8.2 4v9L12 20.5l-8.2-4zM3.8 7.5 12 11.5l8.2-4M12 11.5v9"/></svg>';
?>
<div class="ads-wrap ads-builder" data-ads-builder="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">

	<?php if ( ! empty( $hero ) ) : ?>
		<header class="ads-hero ads-hero--builder">
			<div class="ads-hero__text">
				<span class="ads-pill"><?php esc_html_e( 'دیسکاوری ست اختصاصی', 'aromist-discovery-set' ); ?></span>
				<h1 class="ads-hero__title"><?php esc_html_e( 'ست خودت را بساز', 'aromist-discovery-set' ); ?></h1>
				<p class="ads-hero__lead">
					<?php
					/* translators: 1: number of perfumes, 2: volume */
					echo esc_html( sprintf( __( '%1$s عطر دلخواهت را انتخاب کن؛ از هر کدام یک ویال %2$s در جعبه‌ی ست تو قرار می‌گیرد.', 'aromist-discovery-set' ), number_format_i18n( $ads_size ), $ads_volume ) );
					?>
				</p>
			</div>
			<ol class="ads-steps" aria-label="<?php esc_attr_e( 'مراحل', 'aromist-discovery-set' ); ?>">
				<li><?php echo esc_html( sprintf( /* translators: %s: number */ __( 'انتخاب %s عطر', 'aromist-discovery-set' ), number_format_i18n( $ads_size ) ) ); ?></li>
				<li><?php esc_html_e( 'افزودن به سبد یا ذخیره', 'aromist-discovery-set' ); ?></li>
				<li><?php esc_html_e( 'تحویل در یک جعبه', 'aromist-discovery-set' ); ?></li>
			</ol>
		</header>
	<?php endif; ?>

	<?php if ( $editing ) : ?>
		<div class="ads-editing" role="status">
			<?php echo $ads_box_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span>
				<?php
				/* translators: %s: set name */
				echo esc_html( sprintf( __( 'در حال ویرایش «%s» از ست‌های من', 'aromist-discovery-set' ), $editing['name'] ) );
				?>
			</span>
			<?php if ( $config['mySets'] ) : ?>
				<a class="ads-link" href="<?php echo esc_url( $config['mySets'] ); ?>"><?php esc_html_e( 'بازگشت به ست‌های من', 'aromist-discovery-set' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! $config['product'] ) : ?>
		<div class="ads-empty ads-card"><p><?php esc_html_e( 'ساخت ست اختصاصی هنوز راه‌اندازی نشده است.', 'aromist-discovery-set' ); ?></p></div>
	<?php elseif ( ! $perfumes ) : ?>
		<div class="ads-empty ads-card"><p><?php esc_html_e( 'فعلاً عطری برای ساخت ست موجود نیست.', 'aromist-discovery-set' ); ?></p></div>
		<?php if ( current_user_can( 'manage_woocommerce' ) ) : ?>
			<?php $ads_why = ADS_Pages::builder_diagnosis(); ?>
			<div class="ads-admin-hint" role="note">
				<strong><?php esc_html_e( 'فقط مدیر این را می‌بیند — چرا عطری نمایش داده نمی‌شود:', 'aromist-discovery-set' ); ?></strong>
				<?php if ( ! $ads_why['chosen'] ) : ?>
					<p><?php esc_html_e( 'هنوز هیچ عطری برای ست اختصاصی انتخاب نشده است. در «دیسکاوری ست ← عطرهای ست اختصاصی» عطرها را تیک بزنید و ذخیره کنید.', 'aromist-discovery-set' ); ?></p>
				<?php else : ?>
					<p>
						<?php
						/* translators: %s: number of perfumes */
						echo esc_html( sprintf( __( '%s عطر انتخاب شده ولی هیچ‌کدام قابل فروش در ست نیست:', 'aromist-discovery-set' ), number_format_i18n( $ads_why['chosen'] ) ) );
						?>
					</p>
					<ul>
						<?php foreach ( array_slice( $ads_why['problems'], 0, 10, true ) as $ads_name => $ads_reason ) : ?>
							<li><b><?php echo esc_html( $ads_name ); ?>:</b> <?php echo esc_html( $ads_reason ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<a class="ads-btn ads-btn--primary ads-btn--sm" href="<?php echo esc_url( $ads_why['admin_url'] ); ?>"><?php esc_html_e( 'رفتن به «عطرهای ست اختصاصی»', 'aromist-discovery-set' ); ?></a>
			</div>
		<?php endif; ?>
	<?php else : ?>

	<div class="ads-builder__body">
		<section class="ads-builder__catalog" aria-label="<?php esc_attr_e( 'انتخاب عطر', 'aromist-discovery-set' ); ?>">
			<div class="ads-tools">
				<label class="ads-search">
					<span class="screen-reader-text"><?php esc_html_e( 'جستجوی عطر', 'aromist-discovery-set' ); ?></span>
					<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M11 4.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13ZM20 20l-4.3-4.3"/></svg>
					<input type="search" class="ads-search__input" data-ads-search placeholder="<?php esc_attr_e( 'جستجوی نام عطر، برند یا نت…', 'aromist-discovery-set' ); ?>" autocomplete="off" enterkeyhint="search">
				</label>
				<?php if ( $filters ) : ?>
					<div class="ads-filters" data-ads-filters>
						<?php foreach ( $filters as $ads_filter ) : ?>
							<label class="ads-filter">
								<span class="ads-filter__label"><?php echo esc_html( $ads_filter['label'] ); ?></span>
								<select class="ads-select" data-ads-filter="<?php echo esc_attr( $ads_filter['key'] ); ?>">
									<option value=""><?php esc_html_e( 'همه', 'aromist-discovery-set' ); ?></option>
									<?php foreach ( $ads_filter['options'] as $ads_option ) : ?>
										<option value="<?php echo esc_attr( $ads_option['id'] ); ?>" data-name="<?php echo esc_attr( $ads_option['name'] ); ?>"><?php echo esc_html( sprintf( '%1$s (%2$s)', $ads_option['name'], number_format_i18n( $ads_option['count'] ) ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						<?php endforeach; ?>
					</div>
					<button type="button" class="ads-link ads-filters__reset" data-ads-reset hidden>
						<svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
						<?php esc_html_e( 'حذف فیلترها', 'aromist-discovery-set' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<p class="ads-builder__count" data-ads-shown aria-live="polite">
				<?php
				/* translators: %s: number of perfumes */
				echo esc_html( sprintf( __( '%s عطر', 'aromist-discovery-set' ), number_format_i18n( count( $perfumes ) ) ) );
				?>
			</p>

			<div class="ads-pgrid" data-ads-grid>
				<?php foreach ( $perfumes as $ads_p ) : ?>
					<?php
					$ads_search = implode( ' ', array_merge( array( $ads_p['name'], $ads_p['english'], $ads_p['brand'], $ads_p['family'] ), $ads_p['notes']['top'], $ads_p['notes']['heart'], $ads_p['notes']['base'] ) );
					?>
					<article class="ads-pcard<?php echo $ads_p['available'] ? '' : ' is-unavailable'; ?>"
						data-id="<?php echo esc_attr( $ads_p['id'] ); ?>"
						data-name="<?php echo esc_attr( $ads_p['name'] ); ?>"
						data-search="<?php echo esc_attr( $ads_search ); ?>"
						data-price="<?php echo esc_attr( $ads_p['price'] ); ?>"
						data-img="<?php echo esc_url( $ads_p['thumb'] ); ?>"
						<?php foreach ( $filters as $ads_filter ) : ?>
							data-f-<?php echo esc_attr( $ads_filter['key'] ); ?>="<?php echo esc_attr( implode( ' ', isset( $ads_p['f'][ $ads_filter['key'] ] ) ? $ads_p['f'][ $ads_filter['key'] ] : array() ) ); ?>"
						<?php endforeach; ?>
						data-available="<?php echo $ads_p['available'] ? '1' : '0'; ?>">
						<button type="button" class="ads-pcard__media" data-ads-info aria-label="<?php echo esc_attr( sprintf( /* translators: %s: perfume */ __( 'جزئیات %s', 'aromist-discovery-set' ), $ads_p['name'] ) ); ?>">
							<?php echo $ads_p['img']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="ads-pcard__check" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16"><path fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
						</button>
						<div class="ads-pcard__body">
							<?php if ( $ads_p['brand'] ) : ?>
								<span class="ads-pcard__brand"><?php echo esc_html( $ads_p['brand'] ); ?></span>
							<?php endif; ?>
							<h3 class="ads-pcard__name"><?php echo esc_html( $ads_p['name'] ); ?></h3>
							<?php if ( $ads_p['family'] ) : ?>
								<span class="ads-pcard__family"><?php echo esc_html( $ads_p['family'] ); ?></span>
							<?php endif; ?>
							<span class="ads-pcard__price"><small><?php echo esc_html( $ads_volume ); ?></small> <?php echo wp_kses_post( wc_price( $ads_p['price'] ) ); ?></span>
						</div>
						<div class="ads-pcard__actions">
							<?php if ( $ads_p['available'] ) : ?>
								<button type="button" class="ads-pcard__pick" data-ads-pick aria-pressed="false">
									<span class="ads-pcard__pick-add"><?php esc_html_e( 'افزودن به ست', 'aromist-discovery-set' ); ?></span>
									<span class="ads-pcard__pick-on"><?php esc_html_e( 'در ست تو ✓', 'aromist-discovery-set' ); ?></span>
								</button>
							<?php else : ?>
								<span class="ads-pcard__out"><?php esc_html_e( 'ناموجود', 'aromist-discovery-set' ); ?></span>
							<?php endif; ?>
							<button type="button" class="ads-pcard__more" data-ads-info><?php esc_html_e( 'جزئیات', 'aromist-discovery-set' ); ?><span class="screen-reader-text"> <?php echo esc_html( $ads_p['name'] ); ?></span></button>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
			<p class="ads-builder__none ads-card" data-ads-none hidden><?php esc_html_e( 'عطری با این مشخصات پیدا نشد.', 'aromist-discovery-set' ); ?></p>
		</section>

		<aside class="ads-tray" data-ads-tray aria-label="<?php esc_attr_e( 'ست تو', 'aromist-discovery-set' ); ?>">
			<div class="ads-tray__head">
				<span class="ads-tray__icon"><?php echo $ads_box_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<h2 class="ads-tray__title"><?php echo $editing ? esc_html( $editing['name'] ) : esc_html__( 'ست تو', 'aromist-discovery-set' ); ?></h2>
				<span class="ads-tray__count" data-ads-count><?php echo esc_html( sprintf( /* translators: 1: chosen, 2: total */ __( '%1$s از %2$s', 'aromist-discovery-set' ), number_format_i18n( 0 ), number_format_i18n( $ads_size ) ) ); ?></span>
				<button type="button" class="ads-tray__close" data-ads-sheet-close aria-label="<?php esc_attr_e( 'بستن', 'aromist-discovery-set' ); ?>">
					<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
				</button>
			</div>

			<div class="ads-box" data-ads-box>
				<ol class="ads-slots" data-ads-slots>
					<?php for ( $ads_i = 0; $ads_i < $ads_size; $ads_i++ ) : ?>
						<li class="ads-slot is-empty">
							<span class="ads-slot__num"><?php echo esc_html( number_format_i18n( $ads_i + 1 ) ); ?></span>
							<span class="ads-slot__label"><?php esc_html_e( 'یک عطر انتخاب کن', 'aromist-discovery-set' ); ?></span>
						</li>
					<?php endfor; ?>
				</ol>
			</div>

			<div class="ads-tray__price" data-ads-price aria-live="polite">
				<div class="ads-tray__row"><span><?php esc_html_e( 'جمع قیمت عطرها', 'aromist-discovery-set' ); ?></span><span data-ads-sum>—</span></div>
				<div class="ads-tray__row ads-tray__row--save" data-ads-save-row hidden>
					<span>
						<?php
						/* translators: %s: percent */
						echo esc_html( sprintf( __( 'تخفیف ست (%s٪)', 'aromist-discovery-set' ), ads_format_percent( $config['discount'] ) ) );
						?>
					</span>
					<span data-ads-save></span>
				</div>
				<div class="ads-tray__row ads-tray__row--total"><span><?php esc_html_e( 'قیمت ست', 'aromist-discovery-set' ); ?></span><strong data-ads-total>—</strong></div>
			</div>

			<label class="ads-tray__name">
				<span><?php esc_html_e( 'نام ست (اختیاری)', 'aromist-discovery-set' ); ?></span>
				<input type="text" maxlength="60" data-ads-name value="<?php echo $editing ? esc_attr( $editing['name'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'مثلاً ست تابستانی من', 'aromist-discovery-set' ); ?>" autocomplete="off" enterkeyhint="done">
				<small><?php esc_html_e( 'ست با همین نام در سبد خرید و سفارش ثبت می‌شود.', 'aromist-discovery-set' ); ?></small>
			</label>

			<button type="button" class="ads-btn ads-btn--primary ads-tray__add" data-ads-add disabled>
				<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M5.2 8.5h13.6l-1 10.4a2.3 2.3 0 0 1-2.3 2.1H8.5a2.3 2.3 0 0 1-2.3-2.1zM8.8 8.5V7a3.2 3.2 0 0 1 6.4 0v1.5"/></svg>
				<span><?php esc_html_e( 'افزودن ست به سبد', 'aromist-discovery-set' ); ?></span>
			</button>
			<p class="ads-tray__hint" data-ads-hint role="status"></p>

			<div class="ads-tray__save" data-ads-save-box>
				<?php if ( $config['loggedIn'] ) : ?>
					<button type="button" class="ads-btn ads-btn--ghost ads-tray__save-btn" data-ads-save-set disabled>
						<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M6.5 3.5h11a1 1 0 0 1 1 1v16l-6.5-4-6.5 4v-16a1 1 0 0 1 1-1z"/></svg>
						<span data-ads-save-label><?php echo $editing ? esc_html__( 'ذخیره‌ی تغییرات', 'aromist-discovery-set' ) : esc_html__( 'ذخیره در ست‌های من', 'aromist-discovery-set' ); ?></span>
					</button>
				<?php else : ?>
					<a class="ads-btn ads-btn--ghost ads-tray__save-btn" href="<?php echo esc_url( $config['login'] ); ?>">
						<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M6.5 3.5h11a1 1 0 0 1 1 1v16l-6.5-4-6.5 4v-16a1 1 0 0 1 1-1z"/></svg>
						<span><?php esc_html_e( 'برای ذخیره‌ی ست وارد شو', 'aromist-discovery-set' ); ?></span>
					</a>
				<?php endif; ?>
			</div>

			<button type="button" class="ads-link ads-tray__clear" data-ads-clear hidden><?php esc_html_e( 'پاک کردن انتخاب‌ها', 'aromist-discovery-set' ); ?></button>

			<div class="ads-tray__done" data-ads-done hidden>
				<p class="ads-tray__done-text"><?php esc_html_e( 'ست تو به سبد خرید اضافه شد.', 'aromist-discovery-set' ); ?></p>
				<div class="ads-tray__done-actions">
					<a class="ads-btn ads-btn--primary ads-btn--sm" href="<?php echo esc_url( $config['checkout'] ); ?>"><?php esc_html_e( 'تکمیل خرید', 'aromist-discovery-set' ); ?></a>
					<button type="button" class="ads-btn ads-btn--ghost ads-btn--sm" data-ads-new><?php esc_html_e( 'ساخت ست دیگر', 'aromist-discovery-set' ); ?></button>
				</div>
			</div>
		</aside>
	</div>

	<div class="ads-bar" data-ads-bar data-ads-dodge>
		<button type="button" class="ads-bar__open" data-ads-sheet-open aria-label="<?php esc_attr_e( 'نمایش ست تو', 'aromist-discovery-set' ); ?>">
			<span class="ads-bar__dots" data-ads-dots aria-hidden="true">
				<?php for ( $ads_i = 0; $ads_i < $ads_size; $ads_i++ ) : ?>
					<i></i>
				<?php endfor; ?>
			</span>
			<span class="ads-bar__text">
				<strong data-ads-bar-count><?php echo esc_html( sprintf( /* translators: 1: chosen, 2: total */ __( '%1$s از %2$s', 'aromist-discovery-set' ), number_format_i18n( 0 ), number_format_i18n( $ads_size ) ) ); ?></strong>
				<span data-ads-bar-total></span>
			</span>
			<svg class="ads-bar__chev" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 15l6-6 6 6"/></svg>
		</button>
		<button type="button" class="ads-btn ads-btn--primary ads-bar__add" data-ads-add disabled><?php esc_html_e( 'افزودن به سبد', 'aromist-discovery-set' ); ?></button>
	</div>
	<div class="ads-sheet-backdrop" data-ads-sheet-close hidden></div>

	<div class="ads-modal" data-ads-modal role="dialog" aria-modal="true" aria-labelledby="ads-modal-title" hidden>
		<div class="ads-modal__backdrop" data-ads-modal-close></div>
		<div class="ads-modal__panel" role="document">
			<button type="button" class="ads-modal__close" data-ads-modal-close aria-label="<?php esc_attr_e( 'بستن', 'aromist-discovery-set' ); ?>">
				<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
			</button>
			<div class="ads-modal__content" data-ads-modal-content></div>
			<div class="ads-modal__foot">
				<span class="ads-modal__price" data-ads-modal-price></span>
				<button type="button" class="ads-btn ads-btn--primary" data-ads-modal-pick></button>
			</div>
		</div>
	</div>

	<script type="application/json" data-ads-info-json><?php echo wp_json_encode( $ads_info, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>

	<?php endif; ?>
</div>
