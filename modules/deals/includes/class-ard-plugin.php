<?php
/**
 * Wiring: assets, Elementor widget, shortcode, AJAX add to cart, product page data, cache purges.
 *
 * @package AromistDeals
 */

defined( 'ABSPATH' ) || exit;

final class ARD_Plugin {

	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		new ARD_Price();

		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_action( 'elementor/widgets/register', array( $this, 'widgets' ) );
		add_shortcode( 'aromist_deals', array( $this, 'shortcode' ) );
		add_action( 'wc_ajax_ard_add', array( $this, 'ajax_add' ) );

		// Product page.
		add_filter( 'woocommerce_available_variation', array( $this, 'variation_data' ), 101, 3 );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'simple_data' ) );
		add_filter( 'woocommerce_product_get_default_attributes', array( $this, 'default_attributes' ), 30, 2 );

		// Deals changed → forget the candidate list, purge cached pages that show the section.
		add_action( 'woocommerce_delete_product_transients', array( $this, 'product_changed' ) );
		add_action( 'wc_after_products_starting_sales', array( $this, 'flush' ) );
		add_action( 'wc_after_products_ending_sales', array( $this, 'flush' ) );
		add_action( 'update_option_' . ARD_Settings::OPTION, array( $this, 'flush' ) );
	}

	public function assets(): void {
		wp_register_style( 'ard-front', ARD_URL . 'assets/css/ard-front.css', array(), ARD_VERSION );
		wp_register_style( 'ard-deals', ARD_URL . 'assets/css/ard-deals.css', array( 'ard-front' ), ARD_VERSION );
		wp_register_script( 'ard-front', ARD_URL . 'assets/js/ard-front.js', array(), ARD_VERSION, array( 'in_footer' => true ) );
		wp_register_script( 'ard-deals', ARD_URL . 'assets/js/ard-deals.js', array( 'ard-front' ), ARD_VERSION, array( 'in_footer' => true ) );

		$s = ARD_Settings::all();
		wp_add_inline_style( 'ard-front', ':root{--ard-badge-bg:' . sanitize_hex_color( $s['badge_bg'] ) . ';--ard-badge-fg:' . sanitize_hex_color( $s['badge_fg'] ) . '}' );
		wp_add_inline_script(
			'ard-front',
			'window.ardConfig=' . wp_json_encode(
				array(
					'cardMark'   => 'yes' === $s['card_mark'],
					'cardSelect' => 'yes' === $s['card_select'],
					'pdpTimer'   => 'yes' === $s['pdp_timer'],
					'pdpMark'    => 'yes' === $s['pdp_mark'],
					'progress'   => 'elapsed' === $s['progress'] ? 'elapsed' : 'remaining',
					'latin'      => 'latin' === $s['digits'],
					'ajax'       => WC_AJAX::get_endpoint( 'ard_add' ),
					'cartUrl'    => wc_get_cart_url(),
					'i18n'       => array(
						'ends'    => __( 'تا پایان تخفیف', 'aromist-deals' ),
						'special' => __( 'پیشنهاد ویژه', 'aromist-deals' ),
						'off'     => __( 'تخفیف', 'aromist-deals' ),
						'added'   => __( 'به سبد خرید اضافه شد', 'aromist-deals' ),
						'error'   => __( 'افزودن به سبد انجام نشد. دوباره امتحان کنید.', 'aromist-deals' ),
						'out'     => __( 'این حجم ناموجود است', 'aromist-deals' ),
					),
				)
			) . ';',
			'before'
		);

		// Small and needed on every page with prices or product cards.
		if ( $s['badge'] === 'yes' || $s['card_select'] === 'yes' || $s['pdp_timer'] === 'yes' || $s['pdp_mark'] === 'yes' ) {
			wp_enqueue_style( 'ard-front' );
			wp_enqueue_script( 'ard-front' );
		}
	}

	/**
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function body_class( $classes ) {
		if ( ARD_Settings::on( 'card_mark' ) && ARD_Settings::on( 'badge' ) ) {
			$classes[] = 'ard-mark-sizes';
		}
		$digits = ARD_Settings::get( 'digits' );
		if ( in_array( $digits, array( 'all', 'latin' ), true ) ) {
			$classes[] = 'ard-digits-' . $digits;
		}
		return $classes;
	}

	/**
	 * @param \Elementor\Widgets_Manager $manager Widgets manager.
	 */
	public function widgets( $manager ): void {
		require_once ARD_PATH . 'includes/elementor/class-ard-widget-deals.php';
		$manager->register( new ARD_Widget_Deals() );
	}

	/**
	 * [aromist_deals skin="site|ref" layout="carousel|grid" min="4" limit="12" cats="12,15" source="all|cats|archive|manual" ids="1,2" orderby="discount|ending|newest|random|manual" title="" subtitle=""]
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function shortcode( $atts ): string {
		$d    = ARD_Render::defaults();
		$atts = shortcode_atts(
			array_merge(
				array_diff_key( $d, array_flip( array( 'cats', 'include', 'exclude', 'editor' ) ) ),
				array(
					'cats'    => '',
					'ids'     => '',
					'exclude' => '',
				)
			),
			$atts,
			'aromist_deals'
		);
		$list            = function ( $v ) {
			return array_filter( array_map( 'absint', explode( ',', (string) $v ) ) );
		};
		$atts['cats']    = $list( $atts['cats'] );
		$atts['include'] = $list( $atts['ids'] );
		$atts['exclude'] = $list( $atts['exclude'] );
		if ( $atts['cats'] && 'all' === $atts['source'] ) {
			$atts['source'] = 'cats';
		}
		if ( $atts['include'] && 'all' === $atts['source'] ) {
			$atts['source'] = 'manual';
		}
		return ARD_Render::section( $atts );
	}

	/**
	 * Adds one size to the cart. Mirrors WooCommerce's form handler (the validation filter gets the
	 * variation id, which the volume-stock plugin needs), then answers with WooCommerce's fragments.
	 */
	public function ajax_add(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- a public cart action, like WooCommerce's own add_to_cart endpoint.
		$product_id   = absint( wp_unslash( $_POST['product_id'] ?? 0 ) );
		$variation_id = absint( wp_unslash( $_POST['variation_id'] ?? 0 ) );
		$quantity     = max( 1, (int) wc_stock_amount( wp_unslash( $_POST['quantity'] ?? 1 ) ) );
		// phpcs:enable

		$product = wc_get_product( $product_id );
		if ( ! $product || 'publish' !== get_post_status( $product_id ) ) {
			$this->fail( __( 'این محصول پیدا نشد.', 'aromist-deals' ) );
		}

		if ( $product->is_type( 'variable' ) ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->is_type( 'variation' ) || $variation->get_parent_id() !== $product_id ) {
				$this->fail( __( 'حجم را انتخاب کنید.', 'aromist-deals' ) );
			}
			$attributes = wc_get_product_variation_attributes( $variation_id );
			if ( in_array( '', $attributes, true ) ) {
				// An «any size» variation needs a choice on the product page.
				$this->fail( __( 'حجم را در صفحه‌ی محصول انتخاب کنید.', 'aromist-deals' ) );
			}
			$passed = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $attributes );
			$added  = $passed && false !== WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $attributes );
		} elseif ( $product->is_type( 'simple' ) ) {
			$passed = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
			$added  = $passed && false !== WC()->cart->add_to_cart( $product_id, $quantity );
		} else {
			$this->fail( __( 'این محصول را از صفحه‌ی خودش به سبد اضافه کنید.', 'aromist-deals' ) );
		}

		if ( $added ) {
			do_action( 'woocommerce_ajax_added_to_cart', $product_id );
			WC_AJAX::get_refreshed_fragments(); // Sends the JSON answer and exits.
		}
		$this->fail( '' );
	}

	/** Error answer with WooCommerce's own notice text (e.g. «not enough stock»). */
	private function fail( string $message ): void {
		$notices = array();
		foreach ( wc_get_notices( 'error' ) as $notice ) {
			$notices[] = trim( wp_strip_all_tags( is_array( $notice ) ? (string) $notice['notice'] : (string) $notice ) );
		}
		wc_clear_notices();
		if ( '' === $message ) {
			$message = $notices ? implode( ' ', $notices ) : __( 'افزودن به سبد انجام نشد. دوباره امتحان کنید.', 'aromist-deals' );
		}
		wp_send_json(
			array(
				'error'   => true,
				'message' => $message,
			)
		);
	}

	/**
	 * Sale % and window of each size, for the product page timer and the size tags.
	 *
	 * @param array                $data      Variation data.
	 * @param WC_Product           $product   Parent.
	 * @param WC_Product_Variation $variation Variation.
	 * @return array
	 */
	public function variation_data( $data, $product, $variation ) {
		if ( ! $variation instanceof WC_Product || ! $variation->is_on_sale() ) {
			return $data;
		}
		$pct = ARD_Deals::percent( (float) ( $data['display_regular_price'] ?? 0 ), (float) ( $data['display_price'] ?? 0 ) );
		if ( $pct ) {
			list( $from, $to ) = ARD_Deals::window( $variation );
			$data['ard']       = array(
				'pct'  => $pct,
				'from' => $from,
				'to'   => $to,
			);
		}
		return $data;
	}

	/** Simple products: the same data on a hidden element inside the form. */
	public function simple_data(): void {
		global $product;
		if ( ! $product instanceof WC_Product || ! $product->is_type( 'simple' ) || ! $product->is_on_sale() ) {
			return;
		}
		$row = ARD_Deals::size_row( $product, '' );
		if ( $row['sale'] ) {
			printf( '<span class="ard-simple-deal" hidden data-pct="%d" data-from="%d" data-to="%d"></span>', (int) $row['pct'], (int) $row['from'], (int) $row['to'] );
		}
	}

	/**
	 * The product page opens on the discounted size (best %), unless the shop set a default size
	 * on the product or the link already picks one (?attribute_pa_volume=…).
	 *
	 * @param array      $defaults Default attributes.
	 * @param WC_Product $product  Product.
	 * @return array
	 */
	public function default_attributes( $defaults, $product ) {
		static $cache = array(), $running = false;
		if ( $running || ! ARD_Settings::on( 'pdp_select' ) || ( is_admin() && ! wp_doing_ajax() ) || ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
			return $defaults;
		}
		if ( ! function_exists( 'is_product' ) || ! is_product() || get_post_meta( $product->get_id(), '_default_attributes', true ) ) {
			return $defaults;
		}
		foreach ( array_keys( $_GET ) as $key ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 0 === strpos( (string) $key, 'attribute_' ) ) {
				return $defaults;
			}
		}
		$id = $product->get_id();
		if ( ! array_key_exists( $id, $cache ) ) {
			$running      = true;
			$item         = ARD_Deals::item( $product );
			$running      = false;
			$cache[ $id ] = null;
			if ( $item ) {
				$out = array();
				foreach ( $item['sizes'][ $item['deal'] ]['attrs'] as $key => $value ) {
					if ( '' !== $value ) {
						$out[ substr( $key, 10 ) ] = $value;
					}
				}
				$cache[ $id ] = $out ? $out : null;
			}
		}
		return null === $cache[ $id ] ? $defaults : $cache[ $id ];
	}

	/**
	 * A product was saved, its stock changed, etc. Purge only when it is (or was) a deal.
	 *
	 * @param int $post_id Product or variation id (0 = all products).
	 */
	public function product_changed( $post_id = 0 ): void {
		$post_id = (int) $post_id;
		$before  = get_transient( ARD_Deals::CANDIDATES );
		ARD_Deals::forget();
		if ( ! $post_id ) {
			$this->purge();
			return;
		}
		$parent = (int) wp_get_post_parent_id( $post_id );
		$ids    = array( $post_id, $parent );
		if ( ( is_array( $before ) && array_intersect( $ids, $before ) ) || array_intersect( $ids, ARD_Deals::candidate_ids() ) ) {
			$this->purge();
		}
	}

	public function flush(): void {
		ARD_Deals::forget();
		$this->purge();
	}

	private function purge(): void {
		do_action( 'litespeed_purge', 'ard_deals' );
	}
}
