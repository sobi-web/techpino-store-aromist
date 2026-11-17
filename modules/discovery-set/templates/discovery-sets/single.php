<?php
/**
 * Single page of a ready-made discovery set (full page: the site's header and footer around it).
 *
 * Override: yourtheme/woocommerce/discovery-sets/single.php
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

while ( have_posts() ) :
	the_post();

	global $product;
	$product = wc_get_product( get_the_ID() );
	if ( ! $product instanceof WC_Product_Discovery_Set ) {
		continue;
	}

	$ads      = ADS_Pages::single_data( $product );
	$ads_size = count( $ads['components'] );
	$ads_vol  = ads_format_ml( $ads['volume'] );
	$ads_save = $ads['price'] && $ads['price']['regular'] > $ads['price']['price'] ? $ads['price']['regular'] - $ads['price']['price'] : 0;

	if ( function_exists( 'WC' ) && WC()->structured_data ) {
		WC()->structured_data->generate_product_data( $product );
	}
	?>
	<main id="content" class="ads-wrap ads-single" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
		<?php woocommerce_output_all_notices(); ?>

		<nav class="ads-breadcrumb" aria-label="<?php esc_attr_e( 'مسیر صفحه', 'aromist-discovery-set' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'خانه', 'aromist-discovery-set' ); ?></a>
			<?php foreach ( $ads['crumbs'] as $ads_crumb ) : ?>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( $ads_crumb[1] ); ?>"><?php echo esc_html( $ads_crumb[0] ); ?></a>
			<?php endforeach; ?>
			<span aria-hidden="true">/</span>
			<span aria-current="page"><?php echo esc_html( $product->get_name() ); ?></span>
		</nav>

		<section class="ads-top ads-card">
			<div class="ads-gallery" data-ads-gallery>
				<div class="ads-gallery__stage">
					<?php if ( $ads['price'] && $ads['price']['percent'] > 0 ) : ?>
						<span class="ads-badge ads-badge--sale">
							<?php
							/* translators: %s: percent */
							echo esc_html( sprintf( __( '%s٪ تخفیف', 'aromist-discovery-set' ), number_format_i18n( $ads['price']['percent'] ) ) );
							?>
						</span>
					<?php endif; ?>
					<div class="ads-gallery__track" data-ads-track tabindex="0" aria-label="<?php esc_attr_e( 'تصاویر ست', 'aromist-discovery-set' ); ?>">
						<?php if ( $ads['images'] ) : ?>
							<?php foreach ( $ads['images'] as $ads_i => $ads_image_id ) : ?>
								<figure class="ads-gallery__slide">
									<?php
									echo wp_get_attachment_image(
										$ads_image_id,
										'woocommerce_single',
										false,
										0 === $ads_i
											? array( 'fetchpriority' => 'high', 'loading' => 'eager', 'alt' => $product->get_name() )
											: array( 'loading' => 'lazy' )
									);
									?>
								</figure>
							<?php endforeach; ?>
						<?php else : ?>
							<figure class="ads-gallery__slide"><?php echo wc_placeholder_img( 'woocommerce_single' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></figure>
						<?php endif; ?>
					</div>
					<?php if ( count( $ads['images'] ) > 1 ) : ?>
						<button type="button" class="ads-gallery__nav ads-gallery__nav--prev" data-ads-step="-1" aria-label="<?php esc_attr_e( 'تصویر قبلی', 'aromist-discovery-set' ); ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg></button>
						<button type="button" class="ads-gallery__nav ads-gallery__nav--next" data-ads-step="1" aria-label="<?php esc_attr_e( 'تصویر بعدی', 'aromist-discovery-set' ); ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6"/></svg></button>
						<div class="ads-gallery__dots" aria-hidden="true">
							<?php foreach ( $ads['images'] as $ads_i => $ads_image_id ) : ?>
								<i class="<?php echo 0 === $ads_i ? 'is-active' : ''; ?>"></i>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
				<?php if ( count( $ads['images'] ) > 1 ) : ?>
					<div class="ads-gallery__thumbs">
						<?php foreach ( $ads['images'] as $ads_i => $ads_image_id ) : ?>
							<button type="button" class="ads-gallery__thumb<?php echo 0 === $ads_i ? ' is-active' : ''; ?>" data-ads-go="<?php echo esc_attr( $ads_i ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: number */ __( 'تصویر %s', 'aromist-discovery-set' ), number_format_i18n( $ads_i + 1 ) ) ); ?>">
								<?php echo wp_get_attachment_image( $ads_image_id, 'woocommerce_gallery_thumbnail', false, array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="ads-summary">
				<div class="ads-eyebrow-row">
					<span class="ads-pill"><?php esc_html_e( 'دیسکاوری ست', 'aromist-discovery-set' ); ?></span>
					<span class="ads-eyebrow-row__meta"><?php echo esc_html( sprintf( /* translators: 1: number of perfumes, 2: volume */ __( '%1$s عطر × %2$s', 'aromist-discovery-set' ), number_format_i18n( $ads_size ), $ads_vol ) ); ?></span>
				</div>
				<h1 class="ads-summary__title"><?php echo esc_html( $product->get_name() ); ?></h1>

				<?php if ( $ads['reviews'] > 0 ) : ?>
					<a class="ads-rating" href="#ads-reviews">
						<span class="ads-stars" style="--ads-rating:<?php echo esc_attr( round( $ads['rating'] / 5 * 100 ) ); ?>%" aria-hidden="true"></span>
						<span>
							<?php
							/* translators: 1: rating, 2: number of reviews */
							echo esc_html( sprintf( __( '%1$s از ۵ (%2$s نظر)', 'aromist-discovery-set' ), number_format_i18n( $ads['rating'], 1 ), number_format_i18n( $ads['reviews'] ) ) );
							?>
						</span>
					</a>
				<?php endif; ?>

				<?php if ( $product->get_short_description() ) : ?>
					<div class="ads-summary__excerpt"><?php echo wp_kses_post( wpautop( $product->get_short_description() ) ); ?></div>
				<?php endif; ?>

				<div class="ads-inside">
					<h2 class="ads-inside__title"><?php echo esc_html( sprintf( /* translators: %s: volume */ __( 'عطرهای %s داخل این ست', 'aromist-discovery-set' ), $ads_vol ) ); ?></h2>
					<ol class="ads-inside__list">
						<?php foreach ( $ads['perfumes'] as $ads_i => $ads_p ) : ?>
							<li>
								<a href="#ads-perfume-<?php echo esc_attr( $ads_p['id'] ); ?>" data-ads-show="<?php echo esc_attr( $ads_i ); ?>">
									<img src="<?php echo esc_url( $ads_p['thumb'] ); ?>" alt="" width="56" height="56" loading="lazy">
									<span class="ads-inside__text">
										<strong><?php echo esc_html( $ads_p['name'] ); ?></strong>
										<small><?php echo esc_html( $ads_p['family'] ? $ads_p['family'] : $ads_p['brand'] ); ?></small>
									</span>
									<span class="ads-inside__ml"><?php echo esc_html( $ads_vol ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>

				<div class="ads-buybox" id="ads-buy">
					<div class="ads-buybox__row">
						<div class="ads-buybox__price price"><?php echo $ads['price'] ? wp_kses_post( $ads['price']['html'] ) : ''; ?></div>
						<?php if ( $ads['price'] && $ads['price']['percent'] > 0 ) : ?>
							<span class="ads-off"><?php echo esc_html( sprintf( /* translators: %s: percent */ __( '%s٪', 'aromist-discovery-set' ), number_format_i18n( $ads['price']['percent'] ) ) ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( $ads_save > 0 ) : ?>
						<p class="ads-buybox__save">
							<?php
							/* translators: %s: amount */
							echo wp_kses_post( sprintf( __( 'سود شما از خرید ست: %s', 'aromist-discovery-set' ), wc_price( $ads_save ) ) );
							?>
						</p>
					<?php endif; ?>

					<?php if ( $ads['can_buy'] ) : ?>
						<form class="ads-buybox__form" method="post" action="<?php echo esc_url( $product->get_permalink() ); ?>" data-ads-buy>
							<div class="ads-qty" data-ads-qty>
								<button type="button" class="ads-qty__btn" data-step="1" aria-label="<?php esc_attr_e( 'یکی بیشتر', 'aromist-discovery-set' ); ?>">+</button>
								<input type="number" name="quantity" class="ads-qty__input" value="1" min="1" <?php echo null !== $ads['max'] ? 'max="' . esc_attr( $ads['max'] ) . '"' : ''; ?> inputmode="numeric" aria-label="<?php esc_attr_e( 'تعداد', 'aromist-discovery-set' ); ?>">
								<button type="button" class="ads-qty__btn" data-step="-1" aria-label="<?php esc_attr_e( 'یکی کمتر', 'aromist-discovery-set' ); ?>">−</button>
							</div>
							<input type="hidden" name="ads_volume" value="<?php echo esc_attr( $ads['volume'] ); ?>">
							<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="ads-btn ads-btn--primary ads-buybox__add">
								<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M5.2 8.5h13.6l-1 10.4a2.3 2.3 0 0 1-2.3 2.1H8.5a2.3 2.3 0 0 1-2.3-2.1zM8.8 8.5V7a3.2 3.2 0 0 1 6.4 0v1.5"/></svg>
								<span><?php esc_html_e( 'افزودن ست به سبد', 'aromist-discovery-set' ); ?></span>
							</button>
						</form>
						<p class="ads-buybox__msg" role="status" aria-live="polite" data-ok="<?php esc_attr_e( 'ست به سبد خرید اضافه شد.', 'aromist-discovery-set' ); ?>" hidden></p>
					<?php else : ?>
						<p class="ads-buybox__out"><?php esc_html_e( 'این ست در حال حاضر موجود نیست.', 'aromist-discovery-set' ); ?></p>
					<?php endif; ?>

					<?php if ( $ads['personal'] ) : ?>
						<a class="ads-btn ads-btn--ghost ads-buybox__custom" href="<?php echo esc_url( $ads['personal'] ); ?>">
							<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M4 7h11M4 17h7M18 4l3 3-3 3M15 20l-3-3 3-3M21 7h-4M11 17h10"/></svg>
							<span><?php esc_html_e( 'این ست را شخصی کن', 'aromist-discovery-set' ); ?></span>
						</a>
						<p class="ads-buybox__hint"><?php esc_html_e( 'عطرهایش را عوض کن و ست اختصاصی خودت را بساز.', 'aromist-discovery-set' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<section class="ads-section ads-perfumes" id="ads-perfumes" aria-labelledby="ads-perfumes-title">
			<header class="ads-section__head">
				<h2 id="ads-perfumes-title" class="ads-section__title"><?php esc_html_e( 'عطرهای این ست', 'aromist-discovery-set' ); ?></h2>
				<p class="ads-section__sub"><?php esc_html_e( 'مشخصات، گروه بویایی و نت‌های هر عطر', 'aromist-discovery-set' ); ?></p>
			</header>
			<div class="ads-ptabs" role="tablist" aria-label="<?php esc_attr_e( 'عطرهای ست', 'aromist-discovery-set' ); ?>" data-ads-tabs>
				<?php foreach ( $ads['perfumes'] as $ads_i => $ads_p ) : ?>
					<button type="button" role="tab" class="ads-ptab<?php echo 0 === $ads_i ? ' is-active' : ''; ?>" id="ads-ptab-<?php echo esc_attr( $ads_p['id'] ); ?>" aria-controls="ads-perfume-<?php echo esc_attr( $ads_p['id'] ); ?>" aria-selected="<?php echo 0 === $ads_i ? 'true' : 'false'; ?>" tabindex="<?php echo 0 === $ads_i ? '0' : '-1'; ?>">
						<span class="ads-ptab__num"><?php echo esc_html( number_format_i18n( $ads_i + 1 ) ); ?></span>
						<img src="<?php echo esc_url( $ads_p['thumb'] ); ?>" alt="" width="40" height="40" loading="lazy">
						<span class="ads-ptab__name"><?php echo esc_html( $ads_p['name'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="ads-ppanels ads-card">
				<?php foreach ( $ads['perfumes'] as $ads_i => $ads_p ) : ?>
					<article class="ads-ppanel<?php echo 0 === $ads_i ? ' is-active' : ''; ?>" role="tabpanel" id="ads-perfume-<?php echo esc_attr( $ads_p['id'] ); ?>" aria-labelledby="ads-ptab-<?php echo esc_attr( $ads_p['id'] ); ?>" tabindex="0">
						<?php
						wc_get_template(
							'discovery-sets/perfume.php',
							array(
								'perfume' => $ads_p,
								'index'   => $ads_i,
								'volume'  => $ads['volume'],
							),
							'',
							ADS_PATH . 'templates/'
						);
						?>
					</article>
				<?php endforeach; ?>
			</div>
		</section>

		<?php if ( trim( (string) $product->get_description() ) ) : ?>
			<section class="ads-section ads-card ads-content" aria-labelledby="ads-desc-title">
				<h2 id="ads-desc-title" class="ads-section__title"><?php esc_html_e( 'درباره‌ی این ست', 'aromist-discovery-set' ); ?></h2>
				<div class="ads-content__body"><?php the_content(); ?></div>
			</section>
		<?php endif; ?>

		<?php $ads_reviews = ADS_Pages::reviews_html( $product ); ?>
		<?php if ( '' !== $ads_reviews ) : ?>
			<section class="ads-section ads-reviews" id="ads-reviews" aria-labelledby="ads-reviews-title">
				<h2 id="ads-reviews-title" class="ads-section__title"><?php esc_html_e( 'نظرات خریداران', 'aromist-discovery-set' ); ?></h2>
				<?php echo $ads_reviews; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output of the review plugin / WooCommerce template. ?>
			</section>
		<?php endif; ?>

		<?php if ( $ads['others'] ) : ?>
			<section class="ads-section" aria-labelledby="ads-others-title">
				<header class="ads-section__head">
					<h2 id="ads-others-title" class="ads-section__title"><?php esc_html_e( 'ست‌های دیگر', 'aromist-discovery-set' ); ?></h2>
					<?php if ( $ads['archive'] ) : ?>
						<a class="ads-link" href="<?php echo esc_url( $ads['archive'] ); ?>"><?php esc_html_e( 'همه‌ی ست‌ها', 'aromist-discovery-set' ); ?></a>
					<?php endif; ?>
				</header>
				<div class="ads-sets">
					<div class="ads-set-grid ads-set-grid--4">
						<?php
						foreach ( $ads['others'] as $ads_card ) {
							wc_get_template( 'discovery-sets/card.php', array( 'card' => $ads_card ), '', ADS_PATH . 'templates/' );
						}
						?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( ADS_Pages::builder_url() ) : ?>
			<a class="ads-section ads-promo" href="<?php echo esc_url( ADS_Pages::builder_url() ); ?>">
				<span class="ads-promo__box" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
				<span class="ads-promo__text">
					<strong><?php esc_html_e( 'ست اختصاصی خودت را بساز', 'aromist-discovery-set' ); ?></strong>
					<span><?php echo esc_html( sprintf( /* translators: %s: number of perfumes */ __( '%s عطر دلخواهت را انتخاب کن تا در یک جعبه برایت بفرستیم.', 'aromist-discovery-set' ), number_format_i18n( ads_set_size() ) ) ); ?></span>
				</span>
				<span class="ads-btn ads-btn--light"><?php esc_html_e( 'شروع ساخت ست', 'aromist-discovery-set' ); ?></span>
			</a>
		<?php endif; ?>

		<?php if ( $ads['can_buy'] ) : ?>
			<div class="ads-stickybar" data-ads-sticky data-ads-dodge hidden>
				<div class="ads-stickybar__info">
					<span class="ads-stickybar__name"><?php echo esc_html( $product->get_name() ); ?></span>
					<span class="ads-stickybar__price price"><?php echo $ads['price'] ? wp_kses_post( $ads['price']['html'] ) : ''; ?></span>
				</div>
				<button type="button" class="ads-btn ads-btn--primary" data-ads-sticky-add><?php esc_html_e( 'افزودن به سبد', 'aromist-discovery-set' ); ?></button>
			</div>
		<?php endif; ?>
	</main>
	<?php
endwhile;

get_footer( 'shop' );
