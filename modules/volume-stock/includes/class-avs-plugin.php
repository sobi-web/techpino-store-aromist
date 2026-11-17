<?php
/**
 * Plugin bootstrap.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 */
final class AVS_Plugin {

	/**
	 * Instance.
	 *
	 * @var AVS_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Stock hooks.
	 *
	 * @var AVS_Stock
	 */
	public $stock;

	/**
	 * Cart hooks.
	 *
	 * @var AVS_Cart
	 */
	public $cart;

	/**
	 * Automatic «unlimited» volume stock for new perfumes.
	 *
	 * @var AVS_Auto
	 */
	public $auto;

	/**
	 * Singleton.
	 *
	 * @return AVS_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		AVS_Install::maybe_upgrade();

		$this->stock = new AVS_Stock();
		$this->cart  = new AVS_Cart();
		$this->auto  = new AVS_Auto();

		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( is_admin() ) {
			require_once AVS_PATH . 'includes/admin/class-avs-admin.php';
			require_once AVS_PATH . 'includes/admin/class-avs-admin-data.php';
			require_once AVS_PATH . 'includes/admin/class-avs-admin-product.php';
			require_once AVS_PATH . 'includes/admin/class-avs-admin-order.php';
			new AVS_Admin();
			new AVS_Admin_Product();
			new AVS_Admin_Order();
		}
	}

	/**
	 * Translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'aromist-volume-stock', false, dirname( plugin_basename( AVS_FILE ) ) . '/languages' );
	}
}
