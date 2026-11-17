<?php
/**
 * Module:       سبد خرید کشویی تکپینو
 * Plugin URI:        https://aromistshop.com
 * Description:       ویجت المنتور «سبد خرید کشویی (تکپینو)»: آیکون سبد با شمارنده و پنل کناری ایجکسی برای دیدن، کم‌وزیاد کردن و حذف کالاها. شورت‌کد: [tp_mini_cart]
 * Version:           1.0.0
 * Author:            سبحان پارسامهر
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       techpino-mini-cart
 *
 * @package TechpinoMiniCart
 */

defined( 'ABSPATH' ) || exit;

define( 'TPMC_VERSION', '1.0.0' );
define( 'TPMC_PATH', plugin_dir_path( __FILE__ ) );
define( 'TPMC_URL', plugin_dir_url( __FILE__ ) );

require_once TPMC_PATH . 'includes/class-tpmc-plugin.php';

add_action(
	'plugins_loaded',
	function () {
		if ( class_exists( 'WooCommerce' ) ) {
			TPMC_Plugin::instance();
		}
	}
);
