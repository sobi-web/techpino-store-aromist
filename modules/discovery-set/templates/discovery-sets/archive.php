<?php
/**
 * Ready-made discovery sets ([aromist_discovery_sets]).
 *
 * The top of the page is designed in Elementor; this outputs the set cards (horizontal cards on
 * phones) and, if asked, the plugin's own intro (hero="yes").
 *
 * Override: yourtheme/woocommerce/discovery-sets/archive.php
 *
 * @package AromistDiscoverySet
 *
 * @var array[]      $sets         ADS_Pages::set_card() of each set.
 * @var string       $builder_url  Builder page URL ('' when not published).
 * @var WP_Term|null $term         Category listed (null = all sets).
 * @var int          $columns      Columns on wide screens (0 = automatic).
 * @var bool         $builder_card «ست خودت را بساز» card after the sets.
 * @var bool         $hero         Show the plugin's own intro.
 */

defined( 'ABSPATH' ) || exit;

$ads_size    = ads_set_size();
$ads_vol     = ads_format_ml( ADS_Settings::default_volume() );
$ads_columns = isset( $columns ) ? (int) $columns : 0;
$ads_promo   = ! isset( $builder_card ) || $builder_card;
?>
<div class="ads-wrap ads-archive">
	<?php if ( ! empty( $hero ) ) : ?>
		<header class="ads-hero">
			<div class="ads-hero__text">
				<span class="ads-pill"><?php echo esc_html( sprintf( /* translators: 1: number of perfumes, 2: volume */ __( '%1$s عطر × %2$s', 'aromist-discovery-set' ), number_format_i18n( $ads_size ), $ads_vol ) ); ?></span>
				<h1 class="ads-hero__title"><?php echo esc_html( ! empty( $term ) ? $term->name : __( 'دیسکاوری ست‌ها', 'aromist-discovery-set' ) ); ?></h1>
				<p class="ads-hero__lead"><?php echo esc_html( sprintf( /* translators: 1: number of vials, 2: volume */ __( 'چند عطر را کنار هم امتحان کن و بعد شیشه‌ی کامل محبوبت را انتخاب کن. هر ست %1$s ویال %2$s دارد که در یک جعبه برایت ارسال می‌شود.', 'aromist-discovery-set' ), number_format_i18n( $ads_size ), $ads_vol ) ); ?></p>
				<div class="ads-hero__actions">
					<?php if ( $sets ) : ?>
						<a class="ads-btn ads-btn--primary" href="#ads-sets"><?php esc_html_e( 'دیدن ست‌های آماده', 'aromist-discovery-set' ); ?></a>
					<?php endif; ?>
					<?php if ( $builder_url ) : ?>
						<a class="ads-btn ads-btn--ghost" href="<?php echo esc_url( $builder_url ); ?>"><?php esc_html_e( 'ست اختصاصی خودت را بساز', 'aromist-discovery-set' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<div class="ads-hero__art" aria-hidden="true">
				<span class="ads-hero__box">
					<i></i><i></i><i></i><i></i>
				</span>
			</div>
		</header>

		<ol class="ads-howto" aria-label="<?php esc_attr_e( 'چطور کار می‌کند', 'aromist-discovery-set' ); ?>">
			<li><strong><?php esc_html_e( 'ست را انتخاب کن', 'aromist-discovery-set' ); ?></strong><span><?php esc_html_e( 'از ست‌های آماده یا ست اختصاصی خودت', 'aromist-discovery-set' ); ?></span></li>
			<li><strong><?php esc_html_e( 'رایحه‌ها را امتحان کن', 'aromist-discovery-set' ); ?></strong><span><?php esc_html_e( 'هر عطر را چند روز روی پوستت بزن', 'aromist-discovery-set' ); ?></span></li>
			<li><strong><?php esc_html_e( 'انتخاب نهایی', 'aromist-discovery-set' ); ?></strong><span><?php esc_html_e( 'شیشه‌ی کامل عطر محبوبت را بخر', 'aromist-discovery-set' ); ?></span></li>
		</ol>
	<?php endif; ?>

	<?php if ( $sets ) : ?>
		<div class="ads-sets" id="ads-sets">
			<div class="ads-set-grid<?php echo $ads_columns ? ' has-cols' : ''; ?>"<?php echo $ads_columns ? ' style="--ads-cols:' . esc_attr( $ads_columns ) . '"' : ''; ?>>
				<?php
				foreach ( $sets as $ads_card ) {
					wc_get_template( 'discovery-sets/card.php', array( 'card' => $ads_card ), '', ADS_PATH . 'templates/' );
				}
				?>
				<?php if ( $builder_url && $ads_promo ) : ?>
					<a class="ads-builder-promo" href="<?php echo esc_url( $builder_url ); ?>">
						<span class="ads-builder-promo__slots" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
						<strong><?php esc_html_e( 'ست خودت را بساز', 'aromist-discovery-set' ); ?></strong>
						<span class="ads-builder-promo__text"><?php echo esc_html( sprintf( /* translators: %s: number of perfumes */ __( '%s عطر دلخواهت را انتخاب کن.', 'aromist-discovery-set' ), number_format_i18n( $ads_size ) ) ); ?></span>
						<span class="ads-btn ads-btn--primary ads-btn--sm"><?php esc_html_e( 'شروع ساخت ست', 'aromist-discovery-set' ); ?></span>
					</a>
				<?php endif; ?>
			</div>
		</div>
	<?php else : ?>
		<div class="ads-empty ads-card">
			<p><?php esc_html_e( 'به‌زودی ست‌های آماده اینجا قرار می‌گیرند.', 'aromist-discovery-set' ); ?></p>
			<?php if ( $builder_url ) : ?>
				<a class="ads-btn ads-btn--primary" href="<?php echo esc_url( $builder_url ); ?>"><?php esc_html_e( 'ست اختصاصی خودت را بساز', 'aromist-discovery-set' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
