<?php
/**
 * Module:       Techpino Hover Image – تصویر هاور محصول
 * Plugin URI:        https://aromistshop.com
 * Description:       نمایش یک تصویر اختصاصی (رایحه، نت‌ها و گروه بویایی) وقتی موس روی باکس محصول می‌رود. شامل فیلد «تصویر هاور» در ویرایش محصول، ویجت المنتور برای Loop Grid / JetEngine Listing و شورت‌کد.
 * Version:           1.1.0
 * Author:            Techpino
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       aromist-hover-image
 *
 * @package TechpinoHoverImage
 */

defined( 'ABSPATH' ) || exit;

define( 'AHI_VERSION', '1.1.0' );
define( 'AHI_FILE', __FILE__ );
define( 'AHI_PATH', plugin_dir_path( __FILE__ ) );
define( 'AHI_URL', plugin_dir_url( __FILE__ ) );
define( 'AHI_META_KEY', '_aromist_hover_image' );

require_once AHI_PATH . 'includes/functions.php';
require_once AHI_PATH . 'includes/class-ahi-admin.php';

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
			return;
		}
		AHI_Admin::init();
		add_shortcode( 'aromist_hover_image', 'ahi_shortcode' );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_register_style( 'aromist-hover-image', AHI_URL . 'assets/ahi.css', array(), AHI_VERSION );
		wp_register_script( 'aromist-hover-image', AHI_URL . 'assets/ahi.js', array(), AHI_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}
);

add_action(
	'elementor/widgets/register',
	static function ( $widgets_manager ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		require_once AHI_PATH . 'widgets/class-ahi-widget.php';
		$widgets_manager->register( new \AHI_Widget() );
	}
);
