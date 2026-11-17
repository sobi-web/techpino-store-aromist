<?php
/**
 * Module:       موبایل تکپینو
 * Plugin URI:        https://aromistshop.com
 * Description:       صفحه‌ی محصول تکپینو در موبایل و تبلت (تا ۱۰۲۴ پیکسل): هدر ساده، ترتیب بخش‌های آکاردئونی، نوار خرید چسبان، برگه‌ی انتخاب حجم و جستجوی زنده. در دسکتاپ فقط کارت اقساط اسنپ‌پی عوض می‌شود. هیچ صفحه‌ی دیگری (هدر، فوتر و استایل‌ها) دست نمی‌خورد؛ فوتر فقط در صفحه‌ی محصول موبایل پنهان است. تنظیمات: ووکامرس ← صفحه محصول موبایل.
 * Version:           0.8.2
 * Author:            سبحان پارسامهر
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       aromist-mobile-pdp
 *
 * @package TechpinoMobilePDP
 */

defined( 'ABSPATH' ) || exit;

define( 'AMPDP_VERSION', '0.8.2' );
define( 'AMPDP_FILE', __FILE__ );
define( 'AMPDP_PATH', plugin_dir_path( __FILE__ ) );
define( 'AMPDP_URL', plugin_dir_url( __FILE__ ) );

require_once AMPDP_PATH . 'includes/class-ampdp-settings.php';
require_once AMPDP_PATH . 'includes/class-ampdp-plugin.php';
require_once AMPDP_PATH . 'includes/class-ampdp-admin.php';

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		AMPDP_Plugin::instance();
		if ( is_admin() ) {
			new AMPDP_Admin();
		}
	}
);
