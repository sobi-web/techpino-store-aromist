<?php
/**
 * Plugin bootstrap.
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main class.
 */
final class ADS_Plugin {

	/**
	 * Instance.
	 *
	 * @var ADS_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Cart hooks.
	 *
	 * @var ADS_Cart
	 */
	public $cart;

	/**
	 * Stock hooks.
	 *
	 * @var ADS_Stock
	 */
	public $stock;

	/**
	 * Sync hooks.
	 *
	 * @var ADS_Sync
	 */
	public $sync;

	/**
	 * Storefront hooks.
	 *
	 * @var ADS_Frontend
	 */
	public $frontend;

	/**
	 * Set URLs.
	 *
	 * @var ADS_Permalinks
	 */
	public $permalinks;

	/**
	 * Archive / builder / single pages.
	 *
	 * @var ADS_Pages
	 */
	public $pages;

	/**
	 * AJAX add to cart.
	 *
	 * @var ADS_Ajax
	 */
	public $ajax;

	/**
	 * «ست‌های من».
	 *
	 * @var ADS_Saved_Sets
	 */
	public $saved;

	/**
	 * Singleton.
	 *
	 * @return ADS_Plugin
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
		add_filter( 'woocommerce_product_class', array( $this, 'product_class' ), 10, 2 );
		add_filter( 'woocommerce_is_purchasable', array( 'ADS_Boxes', 'not_purchasable' ), 20, 2 );
		add_action( 'init', array( $this, 'init' ), 20 );

		$this->cart       = new ADS_Cart();
		$this->stock      = new ADS_Stock();
		$this->sync       = new ADS_Sync();
		$this->frontend   = new ADS_Frontend();
		$this->permalinks = new ADS_Permalinks();
		$this->pages      = new ADS_Pages();
		$this->ajax       = new ADS_Ajax();
		$this->saved      = new ADS_Saved_Sets();

		if ( is_admin() ) {
			require_once ADS_PATH . 'includes/admin/class-ads-admin.php';
			require_once ADS_PATH . 'includes/admin/class-ads-admin-product.php';
			require_once ADS_PATH . 'includes/admin/class-ads-admin-order.php';
			new ADS_Admin();
			new ADS_Admin_Product();
			new ADS_Admin_Order();
		}
	}

	/**
	 * Init: translations and one-time setup.
	 */
	public function init() {
		load_plugin_textdomain( 'aromist-discovery-set', false, dirname( plugin_basename( ADS_FILE ) ) . '/languages' );
		ADS_Install::maybe_setup();
	}

	/**
	 * Class of the new product type.
	 *
	 * @param string $classname    Class.
	 * @param string $product_type Type.
	 * @return string
	 */
	public function product_class( $classname, $product_type ) {
		return 'discovery_set' === $product_type ? 'WC_Product_Discovery_Set' : $classname;
	}
}
