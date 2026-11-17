<?php
/**
 * Module:       Techpino Discovery Set – دیسکاوری ست
 * Plugin URI:        https://aromistshop.com
 * Description:       نوع محصول «دیسکاوری ست» برای ووکامرس: پک ۴ عطری با حجم قابل انتخاب، قیمت خودکار از قیمت عطرها، گالری خودکار و برداشت از مخزن میلی‌لیتری «موجودی حجمی» و موجودی جعبه.
 * Version:           0.3.4
 * Author:            Techpino
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce, aromist-volume-stock
 * WC requires at least: 9.0
 * WC tested up to:   11.2
 * Text Domain:       aromist-discovery-set
 * Domain Path:       /languages
 *
 * @package TechpinoDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

define( 'ADS_VERSION', '0.3.4' );
define( 'ADS_FILE', __FILE__ );
define( 'ADS_PATH', plugin_dir_path( __FILE__ ) );
define( 'ADS_URL', plugin_dir_url( __FILE__ ) );
define( 'ADS_MIN_AVS_VERSION', '1.1.0' );

require_once ADS_PATH . 'includes/functions.php';
require_once ADS_PATH . 'includes/class-ads-install.php';

register_activation_hook( TECHPINO_STORE_FILE, array( 'ADS_Install', 'activate' ) );
register_deactivation_hook( TECHPINO_STORE_FILE, array( 'ADS_Install', 'deactivate' ) );

/*
 * - custom_order_tables (HPOS): only the order / order item CRUD API is used.
 * - cart_checkout_blocks: not supported (the shop uses the classic cart and checkout).
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', TECHPINO_STORE_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', TECHPINO_STORE_FILE, false );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		$missing = ads_missing_requirements();
		if ( $missing ) {
			add_action(
				'admin_notices',
				static function () use ( $missing ) {
					echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'دیسکاوری ست:', 'aromist-discovery-set' ) . '</strong> ' . esc_html( $missing ) . '</p></div>';
				}
			);
			return;
		}

		require_once ADS_PATH . 'includes/class-ads-settings.php';
		require_once ADS_PATH . 'includes/class-ads-product-discovery-set.php';
		require_once ADS_PATH . 'includes/class-ads-boxes.php';
		require_once ADS_PATH . 'includes/class-ads-set.php';
		require_once ADS_PATH . 'includes/class-ads-pricing.php';
		require_once ADS_PATH . 'includes/class-ads-availability.php';
		require_once ADS_PATH . 'includes/class-ads-sync.php';
		require_once ADS_PATH . 'includes/class-ads-cart.php';
		require_once ADS_PATH . 'includes/class-ads-stock.php';
		require_once ADS_PATH . 'includes/class-ads-frontend.php';
		require_once ADS_PATH . 'includes/class-ads-perfume-info.php';
		require_once ADS_PATH . 'includes/class-ads-saved-sets.php';
		require_once ADS_PATH . 'includes/class-ads-permalinks.php';
		require_once ADS_PATH . 'includes/class-ads-pages.php';
		require_once ADS_PATH . 'includes/class-ads-ajax.php';
		require_once ADS_PATH . 'includes/class-ads-plugin.php';

		ADS_Plugin::instance();
	},
	30
);
