<?php
/**
 * Module:       سوالات متداول محصول تکپینو
 * Plugin URI:        https://aromistshop.com
 * Description:       بخش «سوالات متداول» محصولات: سوالات پیش‌فرض برای همه یا دسته‌هایی از محصولات (محصولات ← سوالات متداول) و سوالات اختصاصی برای هر محصول. فقط در محصولاتی که سوال دارند نمایش داده می‌شود. ویجت المنتور «سوالات متداول محصول (تکپینو)» و شورت‌کد [tp_product_faq].
 * Version:           1.1.0
 * Author:            سبحان پارسامهر
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       techpino-product-faq
 *
 * @package TechpinoProductFaq
 */

defined( 'ABSPATH' ) || exit;

define( 'TPFQ_VERSION', '1.1.0' );
define( 'TPFQ_PATH', plugin_dir_path( __FILE__ ) );
define( 'TPFQ_URL', plugin_dir_url( __FILE__ ) );

require_once TPFQ_PATH . 'includes/class-tpfq-plugin.php';

add_action(
	'plugins_loaded',
	function () {
		if ( class_exists( 'WooCommerce' ) ) {
			TPFQ_Plugin::instance();
		}
	}
);
