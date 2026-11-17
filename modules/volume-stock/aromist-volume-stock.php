<?php
/**
 * Module:       Techpino Volume Stock – موجودی حجمی
 * Plugin URI:        https://aromistshop.com
 * Description:       مدیریت موجودی حجمی (میلی‌لیتری) برای محصولات متغیر ووکامرس. همه حجم‌های یک عطر (۱۰، ۲۰، ۵۰، ۱۰۰ میل و ...) از یک مخزن مشترک برداشت می‌کنند.
 * Version:           1.2.0
 * Author:            Techpino
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 9.0
 * WC tested up to:   11.2
 * Text Domain:       aromist-volume-stock
 * Domain Path:       /languages
 *
 * @package TechpinoVolumeStock
 */

defined( 'ABSPATH' ) || exit;

define( 'AVS_VERSION', '1.2.0' );
define( 'AVS_DB_VERSION', '1' );
define( 'AVS_FILE', __FILE__ );
define( 'AVS_PATH', plugin_dir_path( __FILE__ ) );
define( 'AVS_URL', plugin_dir_url( __FILE__ ) );

require_once AVS_PATH . 'includes/class-avs-install.php';
require_once AVS_PATH . 'includes/class-avs-settings.php';
require_once AVS_PATH . 'includes/class-avs-product.php';
require_once AVS_PATH . 'includes/class-avs-order-item.php';
require_once AVS_PATH . 'includes/class-avs-ledger.php';
require_once AVS_PATH . 'includes/class-avs-manager.php';
require_once AVS_PATH . 'includes/class-avs-stock.php';
require_once AVS_PATH . 'includes/class-avs-cart.php';
require_once AVS_PATH . 'includes/class-avs-auto.php';
require_once AVS_PATH . 'includes/class-avs-reports.php';
require_once AVS_PATH . 'includes/class-avs-plugin.php';

register_activation_hook( TECHPINO_STORE_FILE, array( 'AVS_Install', 'activate' ) );

/*
 * Declare compatibility with WooCommerce features.
 * - custom_order_tables (HPOS): the plugin only uses the order CRUD API and the order-items tables,
 *   which are the same with and without HPOS.
 * - cart_checkout_blocks: Store API add-to-cart / cart validation hooks are implemented.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', TECHPINO_STORE_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'افزونه «موجودی حجمی تکپینو» به ووکامرس نیاز دارد. لطفاً ووکامرس را فعال کنید.', 'aromist-volume-stock' ) . '</p></div>';
				}
			);
			return;
		}

		AVS_Plugin::instance();
	},
	20
);
