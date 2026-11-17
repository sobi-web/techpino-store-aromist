<?php
/**
 * Sale prices everywhere on the site get the product page's look:
 * a gold «۱۵٪» box, the old price struck through above the new one.
 *
 * Works on WooCommerce's price HTML, so the site's product cards pick it up on their own
 * (the size circles fill the card price from each size's price HTML).
 *
 * @package AromistDeals
 */

defined( 'ABSPATH' ) || exit;

final class ARD_Price {

	/** Inside the product page buy box drawn by the site's snippet, which adds its own badge. */
	private $skip = 0;

	public function __construct() {
		add_filter( 'woocommerce_get_price_html', array( $this, 'filter' ), 100, 2 );

		// Cart page, checkout and the mini-cart: line total with the badge, unit price with the old price.
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'cart_subtotal' ), 100, 3 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'cart_price' ), 100, 3 );

		// Code Snippets «باکس افزودن به سبد خرید صفحه محصول» builds the buy-box price itself
		// (variation price_html at 99, simple price at quantity 10) and appends the same badge.
		add_filter( 'woocommerce_available_variation', array( $this, 'enter' ), 98 );
		add_filter( 'woocommerce_available_variation', array( $this, 'leave' ), 100 );
		add_action( 'woocommerce_before_add_to_cart_quantity', array( $this, 'enter_action' ), 9 );
		add_action( 'woocommerce_before_add_to_cart_quantity', array( $this, 'leave_action' ), 11 );
	}

	private static function snippet_badge(): bool {
		return function_exists( 'aromist_atc_sale_badge' );
	}

	/**
	 * @param array $data Variation data.
	 * @return array
	 */
	public function enter( $data ) {
		if ( self::snippet_badge() ) {
			++$this->skip;
		}
		return $data;
	}

	/**
	 * @param array $data Variation data.
	 * @return array
	 */
	public function leave( $data ) {
		if ( self::snippet_badge() ) {
			$this->skip = max( 0, $this->skip - 1 );
		}
		return $data;
	}

	public function enter_action(): void {
		$this->enter( array() );
	}

	public function leave_action(): void {
		$this->leave( array() );
	}

	/**
	 * @param string     $html    Price HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function filter( $html, $product ) {
		if ( $this->skip > 0 || '' === (string) $html || ! $product instanceof WC_Product || ! ARD_Settings::on( 'badge' ) ) {
			return $html;
		}
		if ( ( is_admin() && ! wp_doing_ajax() ) || false !== strpos( (string) $html, 'ard-price' ) ) {
			return $html;
		}
		if ( in_array( $product->get_type(), ARD_Deals::excluded_types(), true ) ) {
			return $html;
		}
		if ( $product->is_type( 'variable' ) ) {
			return $this->variable( (string) $html, $product );
		}
		if ( ! $product->is_on_sale() ) {
			return $html;
		}
		$regular = (float) wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) );
		$price   = (float) wc_get_price_to_display( $product );
		$pct     = ARD_Deals::percent( $regular, $price );
		return $pct ? self::markup( $pct, $regular, $price, $product->get_price_suffix() ) : $html;
	}

	/**
	 * Regular and current unit price of a discounted cart line, or null.
	 *
	 * @param array $item Cart item.
	 */
	private function cart_sale( $item ): ?array {
		$p = isset( $item['data'] ) ? $item['data'] : null;
		if ( ! $p instanceof WC_Product || ! ARD_Settings::on( 'cart_badge' ) || in_array( $p->get_type(), ARD_Deals::excluded_types(), true ) ) {
			return null;
		}
		if ( $p->is_type( 'variation' ) ) {
			$parent = wc_get_product( $p->get_parent_id() );
			if ( $parent && in_array( $parent->get_type(), ARD_Deals::excluded_types(), true ) ) {
				return null;
			}
		}
		$regular = (float) $p->get_regular_price();
		$current = (float) $p->get_price();
		if ( ! $p->is_on_sale() || $regular <= $current ) {
			return null;
		}
		$pct = ARD_Deals::percent( $regular, $current );
		return $pct ? array( $p, $regular, $pct ) : null;
	}

	/**
	 * @param string $html Line total HTML.
	 * @param array  $item Cart item.
	 * @return string
	 */
	public function cart_subtotal( $html, $item, $key = '' ) {
		$sale = $this->cart_sale( $item );
		if ( ! $sale || false !== strpos( (string) $html, 'ard-price' ) ) {
			return $html;
		}
		list( $p, $regular, $pct ) = $sale;
		$qty = max( 1, (float) ( $item['quantity'] ?? 1 ) );
		return self::markup(
			$pct,
			(float) wc_get_price_to_display( $p, array( 'price' => $regular, 'qty' => $qty ) ),
			(float) wc_get_price_to_display( $p, array( 'qty' => $qty ) ),
			'',
			'ard-price--cart'
		);
	}

	/**
	 * Unit price: old and new price on one line, no badge (the line total has it).
	 *
	 * @param string $html Unit price HTML.
	 * @param array  $item Cart item.
	 * @return string
	 */
	public function cart_price( $html, $item, $key = '' ) {
		$sale = $this->cart_sale( $item );
		if ( ! $sale || false !== strpos( (string) $html, 'ard-price' ) ) {
			return $html;
		}
		list( $p, $regular ) = $sale;
		return '<span class="ard-price ard-price--inline"><span class="ard-amounts">'
			. '<del aria-hidden="true">' . wc_price( wc_get_price_to_display( $p, array( 'price' => $regular ) ) ) . '</del>'
			. '<ins>' . wc_price( wc_get_price_to_display( $p ) ) . '</ins></span></span>';
	}

	/**
	 * Variable product (the site shows «از X تومان»): badge with the best % of the sizes in stock,
	 * «تا» in front of it when the sizes differ, and the old price when the cheapest size is on sale.
	 */
	private function variable( string $html, WC_Product $product ): string {
		$prices = $product->get_variation_prices( true );
		if ( empty( $prices['price'] ) ) {
			return $html;
		}
		$pcts = array();
		foreach ( $prices['price'] as $vid => $price ) {
			$pct = ARD_Deals::percent( (float) ( $prices['regular_price'][ $vid ] ?? 0 ), (float) $price );
			if ( ! $pct ) {
				continue;
			}
			$v = wc_get_product( $vid );
			if ( $v && $v->is_in_stock() ) {
				$pcts[ $vid ] = $pct;
			}
		}
		if ( ! $pcts ) {
			return $html;
		}
		$max     = max( $pcts );
		$upto    = count( array_unique( $pcts ) ) > 1 || count( $pcts ) < count( $prices['price'] );
		$min_vid = array_key_first( $prices['price'] );
		$del     = isset( $pcts[ $min_vid ] ) ? '<del aria-hidden="true">' . wc_price( $prices['regular_price'][ $min_vid ] ) . '</del>' : '';

		return '<span class="ard-price ard-price--var" data-ard-pct="' . esc_attr( (string) $max ) . '">'
			. self::badge( $max, $upto )
			. '<span class="ard-amounts">' . $del . '<span class="ard-now">' . $html . '</span></span>'
			. '</span>';
	}

	/** The gold box: «۱۵٪», or «تا ۲۰٪» for «up to». */
	public static function badge( int $pct, bool $upto = false ): string {
		/* translators: %s: discount percent */
		$sr = sprintf( $upto ? 'تا %s درصد تخفیف' : '%s درصد تخفیف', $pct );
		return '<span class="ard-badge' . ( $upto ? ' ard-badge--upto' : '' ) . '">'
			. '<span class="ard-badge__in" aria-hidden="true">'
			. ( $upto ? '<span class="ard-badge__upto">تا</span>' : '' )
			. '<span class="ard-badge__n">' . esc_html( (string) $pct ) . '<span class="ard-badge__p">%</span></span>'
			. '</span><span class="screen-reader-text">' . esc_html( $sr ) . '</span></span>';
	}

	/** Badge + old price (no currency) over the new price. */
	public static function markup( int $pct, float $regular, float $price, string $suffix = '', string $class = '' ): string {
		return '<span class="ard-price' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '" data-ard-pct="' . esc_attr( (string) $pct ) . '">'
			. self::badge( $pct )
			. '<span class="ard-amounts">'
			. '<del aria-hidden="true">' . wc_price( $regular ) . '</del>'
			. '<ins>' . wc_price( $price ) . $suffix . '</ins>'
			. '<span class="screen-reader-text">'
			/* translators: 1: old price, 2: new price */
			. esc_html( sprintf( 'قیمت اصلی %1$s بود و قیمت فعلی %2$s است.', wp_strip_all_tags( wc_price( $regular ) ), wp_strip_all_tags( wc_price( $price ) ) ) )
			. '</span></span></span>';
	}

	/** A price with no discount, in the same wrapper (deal cards switch between sizes). */
	public static function plain( float $price ): string {
		return '<span class="ard-price ard-price--plain"><span class="ard-amounts"><ins>' . wc_price( $price ) . '</ins></span></span>';
	}
}
