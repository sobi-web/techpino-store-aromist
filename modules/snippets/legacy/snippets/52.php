<?php
// اسنیپت #52: Techpino Checkout Steps
defined( 'ABSPATH' ) || exit;


/**
 * Techpino Checkout Steps for Aromist
 * Author: Techpino (techpino)
 * Site: https://aromistshop.com
 *
 * Add as a PHP snippet, or include from functions.php:
 *   require_once get_stylesheet_directory() . '/inc/aromist-techpino-checkout-steps.php';
 *
 * Shortcode:
 *   [techpino_checkout_steps] (legacy alias: [beban_checkout_steps])
 *
 * Steps: 1 فروشگاه → 2 سبد خرید → 3 اطلاعات → 4 پرداخت → 5 تکمیل
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
add_action( 'init', 'aromist_tp_cs_boot' );
add_action( 'wp_footer', 'aromist_tp_cs_print_vpn_notice', 20 );
/**
 * Register shortcode.
 */
function aromist_tp_cs_boot() {
	add_shortcode( 'techpino_checkout_steps', 'aromist_tp_cs_shortcode' );
	// Keep the previous shortcode working for existing pages.
	add_shortcode( 'beban_checkout_steps', 'aromist_tp_cs_shortcode' );
}
/**
 * Shortcode callback.
 *
 * @param array|string $atts Attributes.
 * @return string
 */
function aromist_tp_cs_shortcode( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'step' => 'auto',
		),
		is_array( $atts ) ? $atts : array(),
		'beban_checkout_steps'
	);
	return aromist_tp_cs_get_html( $atts['step'] );
}
/**
 * Step definitions.
 *
 * @return array<int, array{key:string,label:string,icon:string}>
 */
function aromist_tp_cs_get_steps() {
	return array(
		1 => array(
			'key'   => 'shop',
			'label' => 'فروشگاه',
			'icon'  => 'shop',
		),
		2 => array(
			'key'   => 'cart',
			'label' => 'سبد خرید',
			'icon'  => 'cart',
		),
		3 => array(
			'key'   => 'info',
			'label' => 'اطلاعات',
			'icon'  => 'info',
		),
		4 => array(
			'key'   => 'payment',
			'label' => 'پرداخت',
			'icon'  => 'payment',
		),
		5 => array(
			'key'   => 'complete',
			'label' => 'تکمیل',
			'icon'  => 'complete',
		),
	);
}
/**
 * Thank-you / order-received / Elementor purchase-summary context.
 *
 * @return bool
 */
function aromist_tp_cs_is_thankyou_context() {
	global $wp;
	if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		return true;
	}
	if ( isset( $wp->query_vars['order-received'] ) ) {
		return true;
	}
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
		return true;
	}
	$summary_id = (int) get_option( 'elementor_woocommerce_purchase_summary_page_id', 0 );
	if ( $summary_id && function_exists( 'is_page' ) && is_page( $summary_id ) ) {
		return true;
	}
	return false;
}
/**
 * Detect current step from WooCommerce context.
 *
 * @return int 1–5
 */
function aromist_tp_cs_detect_current_step() {
	if ( aromist_tp_cs_is_thankyou_context() ) {
		return 5;
	}
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
		return 4;
	}
	if ( function_exists( 'is_checkout' ) && is_checkout() && ! aromist_tp_cs_is_thankyou_context() ) {
		return 3;
	}
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		return 2;
	}
	if (
		( function_exists( 'is_shop' ) && is_shop() )
		|| ( function_exists( 'is_product' ) && is_product() )
		|| ( function_exists( 'is_product_category' ) && is_product_category() )
		|| ( function_exists( 'is_product_tag' ) && is_product_tag() )
	) {
		return 1;
	}
	return 2;
}
/**
 * Resolve step from shortcode attribute.
 *
 * @param mixed $step Step attr.
 * @return int 1–5
 */
function aromist_tp_cs_resolve_step( $step = 'auto' ) {
	if ( aromist_tp_cs_is_thankyou_context() ) {
		return 5;
	}
	if ( 'auto' === $step || '' === $step || null === $step ) {
		return aromist_tp_cs_detect_current_step();
	}
	$step = absint( $step );
	if ( $step < 1 ) {
		$step = 1;
	}
	if ( $step > 5 ) {
		$step = 5;
	}
	return $step;
}
/**
 * SVG icon markup.
 *
 * @param string $icon Icon key.
 * @return string
 */
function aromist_tp_cs_get_icon_svg( $icon ) {
	$icons = array(
		'shop'     => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M3.01 11.22V15.71C3.01 20.2 4.81 22 9.3 22H14.69C19.18 22 20.98 20.2 20.98 15.71V11.22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M12.01 12C13.85 12 15.26 10.51 15.09 8.68L14.47 2H9.56L8.94 8.68C8.77 10.51 10.18 12 12.01 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M18.33 12C20.36 12 21.91 10.36 21.72 8.35L21.45 5.6C21.1 3.2 20.3 2 17.66 2H14.44L15.11 9.01C15.27 10.66 16.68 12 18.33 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.64 12C7.29 12 8.7 10.66 8.85 9.01L9.02 7.1L9.52 2H6.3C3.66 2 2.86 3.2 2.51 5.6L2.24 8.35C2.05 10.36 3.61 12 5.64 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 17C10.33 17 9.5 17.83 9.5 19.5V22H14.5V19.5C14.5 17.83 13.67 17 12 17Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		'cart'     => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M8.5 14.25C8.5 16.17 10.08 17.75 12 17.75C13.92 17.75 15.5 16.17 15.5 14.25" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/><path d="M8.81 2L5.19 5.63" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/><path d="M15.19 2L18.81 5.63" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 7.85C2 6 2.99 5.85 4.22 5.85H19.78C21.01 5.85 22 6 22 7.85C22 10 21.01 9.85 19.78 9.85H4.22C2.99 9.85 2 10 2 7.85Z" stroke="currentColor" stroke-width="1.5"/><path d="M3.5 10L4.91 18.64C5.23 20.58 6 22 8.86 22H14.89C18 22 18.46 20.64 18.82 18.76L20.5 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
		'info'     => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 13.43C13.7231 13.43 15.12 12.0331 15.12 10.31C15.12 8.58687 13.7231 7.19 12 7.19C10.2769 7.19 8.88 8.58687 8.88 10.31C8.88 12.0331 10.2769 13.43 12 13.43Z" stroke="currentColor" stroke-width="1.5"/><path d="M3.62 8.49C5.59 -0.169998 18.42 -0.159998 20.38 8.5C21.53 13.58 18.37 17.88 15.6 20.54C13.59 22.48 10.41 22.48 8.39 20.54C5.63 17.88 2.47 13.57 3.62 8.49Z" stroke="currentColor" stroke-width="1.5"/></svg>',
		'payment'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M2 8.50494H22" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 16.5049H8" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.5 16.5049H14.5" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/><path d="M6.44 3.50494H17.55C21.11 3.50494 22 4.38494 22 7.89494V16.1049C22 19.6149 21.11 20.4949 17.56 20.4949H6.44C2.89 20.5049 2 19.6249 2 16.1149V7.89494C2 4.38494 2.89 3.50494 6.44 3.50494Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		'complete' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M9 22H15C20 22 22 20 22 15V9C22 4 20 2 15 2H9C4 2 2 4 2 9V15C2 20 4 22 9 22Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M7.75 12L10.58 14.83L16.25 9.17004" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
	);
	return isset( $icons[ $icon ] ) ? $icons[ $icon ] : $icons['cart'];
}
/**
 * State class for a step relative to current.
 *
 * @param int $index   Step 1–5.
 * @param int $current Current step.
 * @return string done|current|next|upcoming
 */
function aromist_tp_cs_get_step_state( $index, $current ) {
	if ( $current >= 5 ) {
		return 'done';
	}
	if ( $index < $current ) {
		return 'done';
	}
	if ( $index === $current ) {
		return 'current';
	}
	if ( $index === $current + 1 ) {
		return 'next';
	}
	return 'upcoming';
}
/**
 * Full CSS (inline with shortcode output).
 *
 * @return string
 */
function aromist_tp_cs_get_css() {
	return <<<'CSS'
.beban-cs {
	--beban-cs-accent: #009879;
	--beban-cs-accent-soft: color-mix(in srgb, #009879 14%, #fff);
	--beban-cs-accent-muted: color-mix(in srgb, #009879 55%, #fff);
	--beban-cs-accent-border: color-mix(in srgb, #009879 28%, #fff);
	--beban-cs-primary: var(--beban-primary, #009879);
	--beban-cs-primary-hover: var(--beban-primary-hover, #007a62);
	--beban-cs-success: var(--beban-success, #009879);
	--beban-cs-text: var(--beban-text, #111827);
	--beban-cs-desc: var(--beban-desc, #6b7280);
	--beban-cs-bg: var(--beban-bg, #f5f5f5);
	--beban-cs-border: var(--beban-border, #eaeef4);
	--beban-cs-gold: #D6B569;
	box-sizing: border-box;
	width: 100%;
	max-width: 820px;
	margin: 0 auto;
	padding: 8px 4px 4px;
	font-family: 'Shabnam', sans-serif;
}
.beban-cs *,
.beban-cs *::before,
.beban-cs *::after {
	box-sizing: border-box;
}
.beban-cs__list {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
	margin: 0;
	padding: 0;
	list-style: none;
}
.beban-cs__item {
	display: flex;
	flex: 0 0 auto;
	flex-direction: column;
	align-items: center;
	min-width: 72px;
	text-align: center;
}
.beban-cs__node {
	position: relative;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 52px;
	height: 52px;
}
.beban-cs__icon-link { display: inline-flex; color: inherit; text-decoration: none; border-radius: 14px; }
.beban-cs__icon-link:focus-visible { outline: 3px solid #D6B569; outline-offset: 3px; }
.beban-cs__icon {
	position: relative;
	z-index: 2;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 48px;
	height: 48px;
	border-radius: 14px;
	background: var(--beban-cs-bg);
	color: #b0b7c3;
	border: 1px solid transparent;
	transition: background 0.35s ease, color 0.35s ease, box-shadow 0.35s ease, transform 0.35s ease, border-color 0.35s ease;
}
.beban-cs__icon svg {
	display: block;
}
.beban-cs__label-link { color: inherit; text-decoration: none; }
.beban-cs__label-link:hover .beban-cs__label { text-decoration: underline; text-underline-offset: 3px; }
.beban-cs__label {
	margin-top: 10px;
	font-size: 0.8125rem;
	font-weight: 500;
	line-height: 1.4;
	color: var(--beban-cs-desc);
	transition: color 0.35s ease;
}
.beban-cs__connector {
	flex: 1 1 auto;
	align-self: flex-start;
	min-width: 60px;
	height: 4px;
	margin-top: 23px;
	overflow: hidden;
	border-radius: 999px;
	background: var(--beban-cs-border);
	list-style: none;
}
.beban-cs__connector-fill {
	display: block;
	width: 100%;
	height: 100%;
	border-radius: inherit;
	background: var(--beban-cs-border);
}
.beban-cs__connector--done .beban-cs__connector-fill {
	background: var(--beban-cs-success);
}
.beban-cs__connector--active .beban-cs__connector-fill {
	background: linear-gradient(
		90deg,
		rgba(0, 152, 121, 0.18) 0%,
		rgba(0, 152, 121, 0.18) 35%,
		rgba(0, 152, 121, 0.95) 50%,
		rgba(0, 152, 121, 0.18) 65%,
		rgba(0, 152, 121, 0.18) 100%
	);
	background-size: 220% 100%;
	animation: beban-cs-line-flow 1.8s ease-in-out infinite alternate;
}
.beban-cs__item--done .beban-cs__icon {
	background: color-mix(in srgb, var(--beban-cs-success) 16%, #fff);
	color: var(--beban-cs-success);
	border-color: color-mix(in srgb, var(--beban-cs-success) 35%, transparent);
}
.beban-cs__item--done .beban-cs__label {
	color: var(--beban-cs-text);
}
.beban-cs__item--current .beban-cs__icon {
	background: var(--beban-cs-accent);
	color: #fff;
	border-color: var(--beban-cs-accent);
	box-shadow: 0 8px 20px color-mix(in srgb, var(--beban-cs-accent) 35%, transparent);
	transform: scale(1.06);
}
.beban-cs__item--current .beban-cs__label {
	color: var(--beban-cs-accent);
	font-weight: 700;
}
.beban-cs__pulse {
	position: absolute;
	inset: 0;
	z-index: 1;
	border-radius: 16px;
	background: color-mix(in srgb, var(--beban-cs-accent) 35%, transparent);
	animation: beban-cs-pulse 1.8s ease-out infinite;
	pointer-events: none;
}
.beban-cs__item--next .beban-cs__icon {
	background: color-mix(in srgb, var(--beban-cs-gold) 22%, #fff);
	color: var(--beban-cs-accent-muted);
	border-color: var(--beban-cs-accent-border);
	box-shadow: 0 0 0 3px color-mix(in srgb, var(--beban-cs-accent) 10%, transparent);
}
.beban-cs__item--next .beban-cs__label {
	color: var(--beban-cs-accent-muted);
	font-weight: 600;
}
@keyframes beban-cs-line-flow {
	0% {
		background-position: 100% 0;
	}
	100% {
		background-position: 0% 0;
	}
}
@keyframes beban-cs-pulse {
	0% {
		transform: scale(0.92);
		opacity: 0.55;
	}
	70% {
		transform: scale(1.35);
		opacity: 0;
	}
	100% {
		transform: scale(1.35);
		opacity: 0;
	}
}
@media (max-width: 640px) {
	.beban-cs__list {
		gap: 6px;
	}
	.beban-cs__item[data-step="1"],
	.beban-cs__item[data-step="1"] + .beban-cs__connector {
		display: none;
	}
	.beban-cs__item {
		min-width: 0;
		flex: 0 1 auto;
	}
	.beban-cs__node {
		width: 38px;
		height: 38px;
	}
	.beban-cs__icon {
		width: 34px;
		height: 34px;
		border-radius: 10px;
	}
	.beban-cs__icon svg {
		width: 16px;
		height: 16px;
	}
	.beban-cs__label {
		font-size: 0.625rem;
		margin-top: 6px;
	}
	.beban-cs__connector {
		min-width: 12px;
		height: 3px;
		margin-top: 17px;
	}
	.beban-cs__pulse {
		border-radius: 12px;
	}
	.beban-cs__item--current .beban-cs__icon {
		transform: scale(1.04);
		box-shadow: 0 4px 12px color-mix(in srgb, var(--beban-cs-accent) 30%, transparent);
	}
	.beban-cs__item--next .beban-cs__icon {
		box-shadow: 0 0 0 2px color-mix(in srgb, var(--beban-cs-accent) 10%, transparent);
	}
}
@media (prefers-reduced-motion: reduce) {
	.beban-cs__connector--active .beban-cs__connector-fill,
	.beban-cs__pulse {
		animation: none;
	}
}
CSS;
}
/**
 * Show the checkout VPN reminder independently of the steps shortcode.
 *
 * @return void
 */
function aromist_tp_cs_print_vpn_notice() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || aromist_tp_cs_is_thankyou_context() ) {
		return;
	}
	?>
	<div class="aromist-vpn-modal" data-aromist-vpn-modal hidden role="dialog" aria-modal="true" aria-labelledby="aromist-vpn-title" dir="rtl">
		<div class="aromist-vpn-modal__backdrop" data-vpn-close></div>
		<section class="aromist-vpn-modal__card">
			<button class="aromist-vpn-modal__close" type="button" data-vpn-close aria-label="بستن">×</button>
			<div class="aromist-vpn-modal__head">
				<span class="aromist-vpn-modal__mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 3.5 19 6v5.1c0 4.3-2.8 7.5-7 9.4-4.2-1.9-7-5.1-7-9.4V6l7-2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M12 8v4.2m0 3h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
				<span class="aromist-vpn-modal__brand">آرومیست</span>
			</div>
			<p class="aromist-vpn-modal__eyebrow">نکته مهم پیش از پرداخت</p>
			<h2 id="aromist-vpn-title">لطفاً وضعیت VPN را بررسی کنید</h2>
			<p class="aromist-vpn-modal__message">اگر از VPN یا فیلترشکن استفاده می‌کنید، پیش از رفتن به درگاه بانکی آن را خاموش کنید. VPN ممکن است ارتباط با درگاه را مختل کند یا باعث ناموفق ماندن پرداخت شود. سپس ادامه دهید.</p>
			<button class="aromist-vpn-modal__ok" type="button" data-vpn-close>متوجه شدم، ادامه می‌دهم</button>
		</section>
	</div>
	<style>
	.aromist-vpn-modal[hidden]{display:none!important}
	.aromist-vpn-modal,.aromist-vpn-modal *{box-sizing:border-box!important}
	.aromist-vpn-modal{position:fixed!important;inset:0!important;z-index:2147483000!important;display:grid!important;place-items:center!important;width:100vw!important;height:100vh!important;height:100dvh!important;margin:0!important;padding:16px!important;direction:rtl!important;text-align:right!important;font-family:inherit!important;line-height:1.5!important;color:#27332f!important}
	.aromist-vpn-modal__backdrop{position:absolute!important;inset:0!important;width:100%!important;height:100%!important;margin:0!important;padding:0!important;background:rgba(20,30,28,.42)!important;backdrop-filter:blur(2px)}
	.aromist-vpn-modal__card{position:relative!important;display:block!important;width:min(100%,400px)!important;max-width:400px!important;min-width:0!important;margin:0!important;padding:28px!important;border:1px solid rgba(0,152,121,.12)!important;border-radius:18px!important;background:#fff!important;box-shadow:0 20px 60px rgba(17,35,31,.18)!important;text-align:right!important;direction:rtl!important;color:#27332f!important;animation:aromist-vpn-in .2s ease-out both}
	.aromist-vpn-modal__close{position:absolute!important;top:14px!important;left:14px!important;right:auto!important;display:grid!important;place-items:center!important;width:30px!important;height:30px!important;min-width:30px!important;min-height:30px!important;margin:0!important;padding:0!important;border:0!important;border-radius:50%!important;background:#f4f6f5!important;box-shadow:none!important;color:#68736e!important;font-family:Arial,sans-serif!important;font-size:21px!important;font-weight:400!important;line-height:1!important;text-align:center!important;cursor:pointer!important}
	.aromist-vpn-modal__head{display:flex!important;flex-direction:row!important;align-items:center!important;justify-content:flex-start!important;gap:10px!important;width:100%!important;margin:0 0 22px!important;padding:0!important}
	.aromist-vpn-modal__mark{display:grid!important;flex:0 0 40px!important;place-items:center!important;width:40px!important;height:40px!important;margin:0!important;padding:0!important;border:0!important;border-radius:12px!important;background:#e9f5f1!important;color:#009879!important}
	.aromist-vpn-modal__mark svg{display:block!important;width:22px!important;height:22px!important;margin:0!important}
	.aromist-vpn-modal__brand{display:block!important;margin:0!important;padding:0!important;color:#46534e!important;font-size:14px!important;font-weight:700!important;line-height:1.4!important}
	.aromist-vpn-modal__eyebrow{display:block!important;margin:0 0 5px!important;padding:0!important;color:#009879!important;font-size:12px!important;font-weight:600!important;line-height:1.5!important}
	.aromist-vpn-modal__card h2{display:block!important;margin:0 0 9px!important;padding:0!important;border:0!important;background:none!important;color:#25312d!important;font-size:20px!important;font-weight:700!important;line-height:1.6!important;text-align:right!important}
	.aromist-vpn-modal__message{display:block!important;margin:0!important;padding:0!important;color:#68736e!important;font-size:14px!important;font-weight:400!important;line-height:1.9!important;text-align:right!important}
	.aromist-vpn-modal__ok{display:block!important;width:100%!important;min-width:0!important;min-height:0!important;margin:20px 0 0!important;padding:12px 16px!important;border:0!important;border-radius:10px!important;background:#009879!important;box-shadow:none!important;color:#fff!important;font-family:inherit!important;font-size:14px!important;font-weight:600!important;line-height:1.5!important;letter-spacing:normal!important;text-align:center!important;text-transform:none!important;cursor:pointer!important}
	.aromist-vpn-modal__close:hover{background:#edf2ef!important;color:#009879!important}.aromist-vpn-modal__ok:hover{background:#007f66!important}
	@keyframes aromist-vpn-in{from{opacity:0;transform:translateY(8px) scale(.99)}to{opacity:1;transform:translateY(0) scale(1)}}
	@media(max-width:480px){.aromist-vpn-modal__card{width:min(100%,360px)!important;padding:24px!important;border-radius:16px!important}}
	@media(prefers-reduced-motion:reduce){.aromist-vpn-modal__card{animation:none!important}}
	</style>
	<script>(function(){var modal=document.querySelector('[data-aromist-vpn-modal]');if(!modal)return;modal.hidden=false;modal.addEventListener('click',function(e){if(e.target.closest('[data-vpn-close]'))modal.hidden=true});document.addEventListener('keydown',function(e){if(e.key==='Escape')modal.hidden=true})})();</script>
	<?php
}

/**
 * Render HTML + inline CSS.
 *
 * @param mixed $step Step.
 * @return string
 */
function aromist_tp_cs_get_html( $step = 'auto' ) {
	static $css_printed = false;
	$current = aromist_tp_cs_resolve_step( $step );
	$steps   = aromist_tp_cs_get_steps();
	$keys    = array_keys( $steps );
	$count   = count( $keys );
	ob_start();
	if ( ! $css_printed ) {
		$css_printed = true;
		echo '<style id="aromist-techpino-checkout-steps-css">' . aromist_tp_cs_get_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	?>
	<nav class="beban-cs" data-beban-cs data-current="<?php echo esc_attr( (string) $current ); ?>" aria-label="مراحل خرید">
		<ol class="beban-cs__list">
			<?php foreach ( $keys as $i => $index ) : ?>
				<?php
				$step_data = $steps[ $index ];
				$state     = aromist_tp_cs_get_step_state( (int) $index, $current );
				?>
				<?php
				$step_url = '';
				if ( 1 === (int) $index && function_exists( 'wc_get_page_permalink' ) ) {
					$step_url = wc_get_page_permalink( 'shop' );
				} elseif ( 2 === (int) $index && function_exists( 'wc_get_cart_url' ) ) {
					$step_url = wc_get_cart_url();
				} elseif ( 3 === (int) $index && function_exists( 'wc_get_checkout_url' ) ) {
					$step_url = wc_get_checkout_url();
				}
				?>
				<li class="beban-cs__item beban-cs__item--<?php echo esc_attr( $state ); ?>" data-step="<?php echo esc_attr( (string) $index ); ?>">
					<div class="beban-cs__node">
						<?php if ( $step_url ) : ?><a class="beban-cs__icon-link" href="<?php echo esc_url( $step_url ); ?>" aria-label="رفتن به <?php echo esc_attr( $step_data['label'] ); ?>"><?php endif; ?>
						<span class="beban-cs__icon" aria-hidden="true">
							<?php echo aromist_tp_cs_get_icon_svg( $step_data['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</span>
						<?php if ( $step_url ) : ?></a><?php endif; ?>
						<?php if ( 'current' === $state ) : ?>
							<span class="beban-cs__pulse" aria-hidden="true"></span>
						<?php endif; ?>
					</div>
					<?php if ( $step_url ) : ?><a class="beban-cs__label-link" href="<?php echo esc_url( $step_url ); ?>"><?php endif; ?>
					<span class="beban-cs__label"><?php echo esc_html( $step_data['label'] ); ?></span>
					<?php if ( $step_url ) : ?></a><?php endif; ?>
				</li>
				<?php if ( $i < $count - 1 ) : ?>
					<?php
					$line_class = 'beban-cs__connector';
					if ( $current >= 5 || (int) $index < $current ) {
						$line_class .= ' beban-cs__connector--done';
					} elseif ( (int) $index === $current ) {
						$line_class .= ' beban-cs__connector--active';
					}
					?>
					<li class="<?php echo esc_attr( $line_class ); ?>" aria-hidden="true">
						<span class="beban-cs__connector-fill"></span>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ol>
	</nav>

	<?php
	return (string) ob_get_clean();
}