<?php
/**
 * Module:       جستجوی زنده تکپینو
 * Plugin URI:        https://aromistshop.com
 * Description:       ویجت المنتور «جستجوی زنده (تکپینو)»: جستجوی فوری محصولات بدون ایجکس، با حالت بازشو، داخل صفحه و پاپ‌آپ وسط صفحه. بنر، جستجوهای پرطرفدار، محصولات پرفروش و ظاهر فرم از منوی «جستجوی زنده» در پیشخوان تنظیم می‌شوند.
 * Version:           2.0.1
 * Author:            سبحان پارسامهر
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       techpino-live-search
 *
 * @package TechpinoLiveSearch
 */

defined( 'ABSPATH' ) || exit;

define( 'TPLS_VERSION', '2.0.1' );
define( 'TPLS_FILE', __FILE__ );
define( 'TPLS_PATH', plugin_dir_path( __FILE__ ) );
define( 'TPLS_URL', plugin_dir_url( __FILE__ ) );

require_once TPLS_PATH . 'includes/class-tpls-settings.php';
require_once TPLS_PATH . 'includes/class-tpls-index.php';
require_once TPLS_PATH . 'includes/class-tpls-plugin.php';
require_once TPLS_PATH . 'includes/class-tpls-admin.php';

register_activation_hook( TECHPINO_STORE_FILE, array( 'TPLS_Index', 'flush' ) );

add_action(
	'plugins_loaded',
	function () {
		if ( class_exists( 'WooCommerce' ) ) {
			// A new version may store more per product (1.1: English name and sizes): rebuild the index once.
			if ( TPLS_VERSION !== get_option( 'tpls_version' ) ) {
				TPLS_Index::flush();
				update_option( 'tpls_version', TPLS_VERSION, true );
			}
			TPLS_Index::init();
			TPLS_Plugin::instance();
			if ( is_admin() ) {
				TPLS_Admin::init();
			}
		}
	}
);
