<?php
/**
 * Storefront: product page form, gallery, price label.
 *
 * Phase 1 ships a plain, theme-friendly form (perfumes, volume choice with live price, quantity,
 * button). The animated box and the Elementor widgets come in phase 2 on top of the same data.
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frontend hooks.
 */
final class ADS_Frontend {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_discovery_set_add_to_cart', array( $this, 'add_to_cart_form' ) );
		add_filter( 'woocommerce_product_get_gallery_image_ids', array( $this, 'gallery_ids' ), 10, 2 );
		add_filter( 'woocommerce_get_price_html', array( $this, 'price_html' ), 20, 2 );
		add_action( 'template_redirect', array( $this, 'redirect_custom_product' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_filter( 'woocommerce_cart_item_class', array( $this, 'cart_item_class' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_thumbnail', array( $this, 'custom_set_thumbnail' ), 20, 2 );
		add_action( 'woocommerce_before_add_to_cart_quantity', array( $this, 'elementor_open_holder' ), 96 );
	}

	/**
	 * Elementor Pro's «Add To Cart» widget opens `<div class="e-atc-qty-button-holder">` before the
	 * quantity only for simple / variable products, but closes it after the button for every type.
	 * For a set, open it here (right after Elementor's own hook at priority 95) whenever Elementor
	 * is going to close it, so the markup stays balanced and its layout / style controls apply.
	 */
	public function elementor_open_holder() {
		global $product;
		if ( ! $product instanceof WC_Product_Discovery_Set ) {
			return;
		}
		$widget = self::elementor_add_to_cart_widget();
		if ( $widget && in_array( $widget->get_settings_for_display( 'layout' ), array( 'auto', 'stacked' ), true ) ) {
			echo '<div class="e-atc-qty-button-holder">';
		}
	}

	/**
	 * The Elementor Pro «Add To Cart» widget that is rendering right now (it hooks its closing
	 * `</div>` to woocommerce_after_add_to_cart_button at priority 5 while it renders).
	 *
	 * @return object|null
	 */
	private static function elementor_add_to_cart_widget() {
		global $wp_filter;
		if ( empty( $wp_filter['woocommerce_after_add_to_cart_button']->callbacks[5] ) ) {
			return null;
		}
		foreach ( $wp_filter['woocommerce_after_add_to_cart_button']->callbacks[5] as $callback ) {
			$fn = $callback['function'];
			if ( is_array( $fn ) && is_object( $fn[0] ) && 'after_add_to_cart_button' === $fn[1]
				&& method_exists( $fn[0], 'get_settings_for_display' )
				&& false !== strpos( get_class( $fn[0] ), 'Product_Add_To_Cart' ) ) {
				return $fn[0];
			}
		}
		return null;
	}

	/**
	 * Mark set lines in the cart / checkout (the shop's snippets hide item data of normal
	 * products; for sets it is the list of perfumes and must stay visible).
	 *
	 * @param string $class Class.
	 * @param array  $item  Cart item.
	 * @return string
	 */
	public function cart_item_class( $class, $item ) {
		return ADS_Cart::is_set_item( $item ) ? $class . ' ads-set-item' : $class;
	}

	/**
	 * A customer-built set without its own image shows its first perfume's image in the cart,
	 * mini-cart and checkout.
	 *
	 * @param string $html Thumbnail HTML.
	 * @param array  $item Cart item.
	 * @return string
	 */
	public function custom_set_thumbnail( $html, $item ) {
		if ( ! ADS_Cart::is_set_item( $item ) || ! $item['data']->is_custom() || $item['data']->get_image_id() ) {
			return $html;
		}
		list( $components ) = ADS_Cart::selection( $item );
		$first              = $components ? wc_get_product( $components[0] ) : null;
		return $first ? $first->get_image( 'woocommerce_thumbnail' ) : $html;
	}

	/**
	 * CSS for set lines in the cart / checkout snippets and WooCommerce's own templates.
	 *
	 * @return string
	 */
	private static function cart_css() {
		return '.aromist-cart .woocommerce-cart-form__cart-item.ads-set-item .variation,'
			. '.aromist-checkout #order_review .ads-set-item .product-meta-checkout .variation{display:block}'
			. '.ads-set-item dl.variation{margin:4px 0 0;font-size:12px;line-height:1.9;color:#707070}'
			. '.ads-set-item dl.variation dt{display:inline;float:none;margin:0;font-weight:600}'
			. '.ads-set-item dl.variation dd{display:inline;margin:0 0 0 4px}'
			. '.ads-set-item dl.variation dd p{display:inline;margin:0}'
			. '.ads-set-item dl.variation dd::after{content:"";display:block}';
	}

	/**
	 * Data of every volume of a set for the product page.
	 *
	 * @param WC_Product_Discovery_Set $product Set.
	 * @return array ml => [ ml, label, price, regular, price_html, available, max ]
	 */
	public static function volume_data( $product ) {
		$components = $product->get_components();
		$already    = WC()->cart ? ADS_Cart::needs() : array();
		$out        = array();

		foreach ( $product->get_volumes() as $ml ) {
			$breakdown = ADS_Pricing::breakdown( $components, $ml, $product );
			if ( null === $breakdown ) {
				continue;
			}
			$draws = ADS_Set::draws( $components, $ml );
			$max   = ADS_Availability::max_sets( $draws, $already );

			$price_html = $breakdown['price'] < $breakdown['regular']
				? wc_format_sale_price( wc_get_price_to_display( $product, array( 'price' => $breakdown['regular'] ) ), wc_get_price_to_display( $product, array( 'price' => $breakdown['price'] ) ) )
				: wc_price( wc_get_price_to_display( $product, array( 'price' => $breakdown['price'] ) ) );

			$out[ $ml ] = array(
				'ml'         => (int) $ml,
				'label'      => ads_format_ml( $ml ),
				'price'      => (float) $breakdown['price'],
				'regular'    => (float) $breakdown['regular'],
				'price_html' => $price_html,
				'available'  => null === $max || $max > 0,
				'max'        => null === $max ? -1 : (int) $max,
			);
		}
		return $out;
	}

	/**
	 * The add-to-cart form of a ready-made set (single product page).
	 */
	public function add_to_cart_form() {
		global $product;
		if ( ! $product instanceof WC_Product_Discovery_Set || $product->is_custom() ) {
			return;
		}

		$volumes = self::volume_data( $product );
		$default = $product->get_default_volume();
		if ( ! isset( $volumes[ $default ] ) || ! $volumes[ $default ]['available'] ) {
			foreach ( $volumes as $ml => $row ) {
				if ( $row['available'] ) {
					$default = $ml;
					break;
				}
			}
		}

		wp_enqueue_style( 'ads-frontend' );
		wp_enqueue_script( 'ads-frontend' );

		wc_get_template(
			'single-product/add-to-cart/discovery-set.php',
			array(
				'product'     => $product,
				'perfumes'    => array_map( 'wc_get_product', $product->get_components() ),
				'volumes'     => $volumes,
				'selected'    => $default,
				'purchasable' => $product->is_purchasable() && $product->is_in_stock(),
			),
			'',
			ADS_PATH . 'templates/'
		);
	}

	/**
	 * Gallery of a ready-made set: the gallery chosen in wp-admin, or — when none was chosen — the
	 * featured images of its perfumes (read live, so it follows changes of the perfumes' images).
	 *
	 * @param int[]      $ids     Stored gallery.
	 * @param WC_Product $product Product.
	 * @return int[]
	 */
	public function gallery_ids( $ids, $product ) {
		if ( ! $product instanceof WC_Product_Discovery_Set || $product->is_custom() ) {
			return $ids;
		}
		if ( is_array( $ids ) && array_filter( $ids ) ) {
			return $ids;
		}
		$out = array();
		foreach ( $product->get_components() as $id ) {
			$image = (int) get_post_thumbnail_id( $id );
			if ( $image && ! in_array( $image, $out, true ) ) {
				$out[] = $image;
			}
		}
		/**
		 * Gallery image ids of a discovery set.
		 *
		 * @param int[]                    $out     Image ids.
		 * @param WC_Product_Discovery_Set $product Set.
		 * @param int[]                    $ids     Stored gallery (unused by default).
		 */
		return apply_filters( 'ads_gallery_image_ids', $out, $product, $ids );
	}

	/**
	 * Add the volume to the stored price, e.g. "۴ × ۵ میل".
	 *
	 * @param string     $html    Price HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function price_html( $html, $product ) {
		if ( ! $product instanceof WC_Product_Discovery_Set || $product->is_custom() || '' === $html ) {
			return $html;
		}
		$volume = $product->get_default_volume();
		if ( ! $volume ) {
			return $html;
		}
		$label = sprintf( '%s × %s', number_format_i18n( ads_set_size() ), ads_format_ml( $volume ) );
		return $html . ' <small class="ads-price-volume">(' . esc_html( $label ) . ')</small>';
	}

	/**
	 * The hidden «ست سفارشی» product has no page of its own.
	 */
	public function redirect_custom_product() {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ADS_Set::is_custom_product( $product ) ) {
			/**
			 * Where visitors of the «ست سفارشی» product page are sent (the set builder page in phase 3).
			 *
			 * @param string $url URL.
			 */
			wp_safe_redirect( apply_filters( 'ads_custom_product_redirect', wc_get_page_permalink( 'shop' ) ) );
			exit;
		}
	}

	/**
	 * Register CSS/JS (enqueued by the form).
	 */
	public function register_assets() {
		wp_register_style( 'ads-frontend', ADS_URL . 'assets/css/frontend.css', array(), ads_asset_ver( 'assets/css/frontend.css' ) );
		wp_register_script( 'ads-frontend', ADS_URL . 'assets/js/frontend.js', array(), ads_asset_ver( 'assets/js/frontend.js' ), true );

		if ( is_cart() || is_checkout() ) {
			wp_register_style( 'ads-cart', false, array(), ADS_VERSION );
			wp_enqueue_style( 'ads-cart' );
			wp_add_inline_style( 'ads-cart', self::cart_css() );
		}
	}
}
