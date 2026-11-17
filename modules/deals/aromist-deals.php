<?php
/**
 * Module:       تخفیف‌های ویژه تکپینو
 * Plugin URI:        https://aromistshop.com
 * Description:       ویجت المنتور «فرصت‌های تکرار نشدنی» برای محصولات تخفیف‌دار (کاروسل یا شبکه، با تایمر پایان تخفیف؛ تا وقتی حداقل تعداد محصول تخفیف‌دار نباشد دیده نمی‌شود)، برچسب درصد تخفیف کنار قیمت در همه‌ی سایت، و تایمر و نشان تخفیف روی حجم‌ها در صفحه‌ی محصول. تنظیمات: ووکامرس ← تخفیف‌ها.
 * Version:           0.4.0
 * Author:            سبحان پارسامهر
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       aromist-deals
 *
 * @package TechpinoDeals
 */

defined( 'ABSPATH' ) || exit;

define( 'ARD_VERSION', '0.4.0' );
define( 'ARD_FILE', __FILE__ );
define( 'ARD_PATH', plugin_dir_path( __FILE__ ) );
define( 'ARD_URL', plugin_dir_url( __FILE__ ) );

require_once ARD_PATH . 'includes/class-ard-settings.php';
require_once ARD_PATH . 'includes/class-ard-deals.php';
require_once ARD_PATH . 'includes/class-ard-price.php';
require_once ARD_PATH . 'includes/class-ard-render.php';
require_once ARD_PATH . 'includes/class-ard-plugin.php';
require_once ARD_PATH . 'includes/class-ard-admin.php';

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		ARD_Plugin::instance();
		if ( is_admin() ) {
			new ARD_Admin();
		}
	}
);
