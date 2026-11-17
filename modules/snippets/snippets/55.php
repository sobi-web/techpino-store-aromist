<?php
// اسنیپت #55: منوی پایین موبایل پنل کاربری تکپینو
defined( 'ABSPATH' ) || exit;

/**
 * Techpino user panel — mobile bottom bar order
 * Version: 1.1.0
 *
 * From the right: داشبورد، سفارش‌ها، صفحه‌ی اصلی (highlighted)، رهگیری، بیشتر.
 * Everything else (کیف پول، آدرس‌ها، …) is in «بیشتر».
 *
 * Works only through the panel's own hooks (tpup_menu_items, tpup_bottom_nav);
 * the plugin itself is not changed. Switching this snippet off restores the default bar.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'tpbn_home_key' ) ) {

	/** Key of the «صفحه‌ی اصلی» entry added to the panel menu. */
	function tpbn_home_key(): string {
		return 'site-home';
	}

	// «صفحه‌ی اصلی» joins the panel menu so the bottom bar can use it.
	// It is a normal link (no AJAX): it leaves the panel for the shop.
	add_filter(
		'tpup_menu_items',
		function ( $items ) {
			if ( ! is_array( $items ) ) {
				return $items;
			}
			$items[ tpbn_home_key() ] = array(
				'key'   => tpbn_home_key(),
				'label' => 'صفحه‌ی اصلی',
				'short' => 'صفحه اصلی',
				'icon'  => 'home',
				'url'   => home_url( '/' ),
				'ajax'  => false,
			);
			return $items;
		},
		20
	);

	// The four bar slots, from the right. The fifth slot is always «بیشتر».
	add_filter(
		'tpup_bottom_nav',
		function () {
			return array( 'dashboard', 'orders', tpbn_home_key(), 'tracking' );
		},
		20
	);

	// Styles: the home slot stands out; the extra menu entry stays out of the sidebar and the
	// «بیشتر» grid, which already have their own «صفحه‌ی اصلی» buttons.
	add_action(
		'wp_head',
		function () {
			if ( ! class_exists( 'TPUP_Shell' ) || ! TPUP_Shell::is_panel() ) {
				return;
			}
			$key = tpbn_home_key();
			?>
			<style id="tpbn-css">
			.tpup-nav__list li:has(> [data-section="<?php echo esc_attr( $key ); ?>"]),
			.tpup-sheet__grid li:has(> [data-section="<?php echo esc_attr( $key ); ?>"]) { display: none !important; }

			@media (max-width: 767px) {
				.tpup .tpup-bottomnav { overflow: visible; }
				.tpup .tpup-bottomnav__item[data-section="<?php echo esc_attr( $key ); ?>"] {
					color: var(--tp-primary-700, #007a62);
					font-weight: 700;
					-webkit-tap-highlight-color: transparent;
				}
				/* A rounded diamond lifted out of the bar, with a white rim that cuts into its top line. */
				.tpup .tpup-bottomnav__item[data-section="<?php echo esc_attr( $key ); ?>"] .tpup-bottomnav__icon {
					position: relative;
					width: 48px;
					height: 48px;
					margin-top: -20px;
					border-radius: 16px;
					transform: rotate(45deg);
					background: linear-gradient(135deg, color-mix(in srgb, var(--tp-primary, #009879) 78%, #fff), var(--tp-primary, #009879) 55%, var(--tp-primary-700, #007a62));
					color: #fff;
					box-shadow: 0 0 0 4px #fff, 0 10px 20px -8px color-mix(in srgb, var(--tp-primary, #009879) 75%, transparent), inset 0 1px 0 rgba(255, 255, 255, .35);
					transition: transform .2s;
				}
				.tpup .tpup-bottomnav__item[data-section="<?php echo esc_attr( $key ); ?>"] .tpup-bottomnav__icon .tpup-icon {
					width: 23px;
					height: 23px;
					transform: rotate(-45deg);
					stroke-width: 2;
				}
				/* A soft ring spreads out three times after the page opens, then rests. */
				.tpup .tpup-bottomnav__item[data-section="<?php echo esc_attr( $key ); ?>"] .tpup-bottomnav__icon::after {
					content: '';
					position: absolute;
					inset: -4px;
					border-radius: 19px;
					border: 2px solid var(--tp-primary, #009879);
					opacity: 0;
					pointer-events: none;
					animation: tpbn-pulse 2s ease-out .6s 3;
				}
				.tpup .tpup-bottomnav__item[data-section="<?php echo esc_attr( $key ); ?>"]:active .tpup-bottomnav__icon { transform: rotate(45deg) scale(.93); }
				.tpup .tpup-bottomnav__item[data-section="<?php echo esc_attr( $key ); ?>"] .tpup-bottomnav__label {
					margin-top: 4px;
					color: var(--tp-primary-700, #007a62);
				}

				@keyframes tpbn-pulse {
					0% { opacity: .55; transform: scale(1); }
					100% { opacity: 0; transform: scale(1.35); }
				}
			}
			@media (prefers-reduced-motion: reduce) {
				.tpup .tpup-bottomnav__item[data-section="<?php echo esc_attr( $key ); ?>"] .tpup-bottomnav__icon::after { animation: none !important; }
			}
			</style>
			<?php
		},
		100
	);
}
