<?php
/**
 * Module:       Techpino Season Filter – فیلتر AJAX کاروسل محصولات
 * Plugin URI:        https://aromistshop.com
 * Description:       ویجت المنتور «فیلتر AJAX کاروسل» (گرم / خنک / چهار فصل): سه دکمه بالای کاروسل محصولات که بدون ری‌لود، اسلایدهای همان کاروسل Loop Carousel را عوض می‌کند. جایگزین تب‌های سنگین.
 * Version:           0.3.0
 * Author:            Techpino
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce, elementor
 * Text Domain:       aromist-season-filter
 *
 * @package TechpinoSeasonFilter
 */

defined( 'ABSPATH' ) || exit;

define( 'ASF_VERSION', '0.3.0' );
define( 'ASF_FILE', __FILE__ );
define( 'ASF_PATH', plugin_dir_path( __FILE__ ) );
define( 'ASF_URL', plugin_dir_url( __FILE__ ) );

require_once ASF_PATH . 'includes/class-asf-core.php';
require_once ASF_PATH . 'includes/class-asf-ajax.php';

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
		if ( ! class_exists( '\Elementor\Plugin' ) || ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		ASF_Core::init();
		ASF_Ajax::init();
	},
	20
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_register_style( 'aromist-season-filter', ASF_URL . 'assets/asf.css', array(), ASF_VERSION );
		wp_register_script( 'aromist-season-filter', ASF_URL . 'assets/asf.js', array(), ASF_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_add_inline_script(
			'aromist-season-filter',
			'window.ASF=' . wp_json_encode(
				array(
					'ajax'   => admin_url( 'admin-ajax.php' ),
					'action' => ASF_Ajax::ACTION,
				)
			) . ';',
			'before'
		);
	}
);

add_action(
	'elementor/widgets/register',
	static function ( $widgets_manager ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		require_once ASF_PATH . 'widgets/class-asf-widget.php';
		$widgets_manager->register( new \ASF_Widget() );
	}
);
