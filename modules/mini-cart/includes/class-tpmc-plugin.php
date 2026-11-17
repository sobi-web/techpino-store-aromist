<?php
/**
 * Mini cart: assets, markup, shortcode and the AJAX endpoint.
 *
 * The drawer shell (title, empty state, buttons) is printed once with the page and never
 * changes; the endpoint only returns the cart lines and totals, which the script paints
 * into it. Nothing is requested on a page view when the visitor's cart is empty or
 * unchanged since the last answer (see assets/js/mini-cart.js).
 *
 * @package TechpinoMiniCart
 */

defined( 'ABSPATH' ) || exit;

final class TPMC_Plugin {

	/** @var TPMC_Plugin|null */
	private static $instance = null;

	/** @var int Drawers printed on this page. */
	private $drawers = 0;

	/** @var bool A shortcode trigger was printed, so the page needs a drawer to open. */
	private $needs_drawer = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
		add_action( 'init', array( $this, 'register_shortcodes' ), 99 );
		add_action( 'wp_footer', array( $this, 'print_default_drawer' ), 5 );
		add_action( 'wc_ajax_tpmc', array( $this, 'ajax' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'add_fragment' ) );
	}

	public function register_assets(): void {
		wp_register_style( 'tpmc', TPMC_URL . 'assets/css/mini-cart.css', array(), TPMC_VERSION );
		wp_register_script(
			'tpmc',
			TPMC_URL . 'assets/js/mini-cart.js',
			array(),
			TPMC_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'tpmc',
			'tpmcConfig',
			array(
				'url'   => WC_AJAX::get_endpoint( 'tpmc' ),
				'error' => __( 'به‌روزرسانی سبد خرید انجام نشد. دوباره تلاش کنید.', 'techpino-mini-cart' ),
			)
		);
	}

	public function register_widget( $widgets_manager ): void {
		require_once TPMC_PATH . 'includes/class-tpmc-widget.php';
		$widgets_manager->register( new TPMC_Widget() );
	}

	/*
	|--------------------------------------------------------------------------
	| Shortcode
	|--------------------------------------------------------------------------
	*/

	public function register_shortcodes(): void {
		add_shortcode( 'tp_mini_cart', array( $this, 'shortcode' ) );
		// The old snippet's tag (used in the mobile footer bar) keeps working once that snippet is switched off.
		if ( ! shortcode_exists( 'ng_cart_mini_icon' ) ) {
			add_shortcode( 'ng_cart_mini_icon', array( $this, 'shortcode' ) );
		}
	}

	/**
	 * [tp_mini_cart type="icon|button" text="سبد خرید" count="quantity|lines|no" hide_zero="yes|no"]
	 * Opens the first drawer on the page (the Elementor widget's), or a default one.
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'type'      => 'icon',
				'text'      => __( 'سبد خرید', 'techpino-mini-cart' ),
				'count'     => 'quantity',
				'hide_zero' => 'no',
			),
			$atts,
			'tp_mini_cart'
		);

		wp_enqueue_style( 'tpmc' );
		wp_enqueue_script( 'tpmc' );
		$this->needs_drawer = true;

		return '<span class="tpmc tpmc--inline">' . $this->trigger(
			array(
				'type'      => $atts['type'],
				'text'      => $atts['text'],
				'count'     => in_array( $atts['count'], array( 'no', '' ), true ) ? '' : $atts['count'],
				'hide_zero' => 'yes' === $atts['hide_zero'],
			)
		) . '</span>';
	}

	public function print_default_drawer(): void {
		if ( $this->needs_drawer && ! $this->drawers ) {
			echo $this->drawer( array( 'id' => 'default' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in drawer().
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Markup
	|--------------------------------------------------------------------------
	*/

	public function icon( string $name ): string {
		$paths = array(
			'bag'   => '<path d="M5 8h14l-.8 11.2a2 2 0 0 1-2 1.8H7.8a2 2 0 0 1-2-1.8L5 8Z"/><path d="M9 11V6.5a3 3 0 0 1 6 0V11"/>',
			'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
			'trash' => '<path d="M4 7h16M9 7V4.5h6V7M6.5 7l.8 12a2 2 0 0 0 2 1.9h5.4a2 2 0 0 0 2-1.9l.8-12M10 11v6M14 11v6"/>',
			'plus'  => '<path d="M12 5v14M5 12h14"/>',
			'minus' => '<path d="M5 12h14"/>',
		);
		return '<svg class="tpmc-svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	private function cart(): ?WC_Cart {
		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) && did_action( 'before_woocommerce_init' ) ) {
			wc_load_cart();
		}
		return WC()->cart;
	}

	/**
	 * The button that opens the drawer.
	 *
	 * @param array $args type, text, icon (HTML), count (quantity|lines|''), hide_zero, label, target (drawer id).
	 */
	public function trigger( array $args ): string {
		$args = wp_parse_args(
			$args,
			array(
				'type'      => 'icon',
				'text'      => '',
				'icon'      => '',
				'count'     => 'quantity',
				'hide_zero' => false,
				'label'     => __( 'سبد خرید', 'techpino-mini-cart' ),
				'target'    => '',
			)
		);
		$type = 'button' === $args['type'] ? 'button' : 'icon';
		$mode = 'lines' === $args['count'] ? 'lines' : 'quantity';
		$cart = $this->cart();
		$n    = 0;
		if ( $cart ) {
			$n = 'lines' === $mode ? count( $cart->get_cart() ) : (int) $cart->get_cart_contents_count();
		}

		ob_start();
		?>
		<button type="button" class="tpmc-trigger tpmc-trigger--<?php echo esc_attr( $type ); ?>" data-tpmc-open="<?php echo esc_attr( $args['target'] ); ?>" aria-haspopup="dialog" aria-expanded="false" aria-label="<?php echo esc_attr( $args['label'] ); ?>">
			<span class="tpmc-trigger__icon" aria-hidden="true"><?php echo '' !== $args['icon'] ? $args['icon'] : $this->icon( 'bag' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup. ?></span>
			<?php if ( 'button' === $type && '' !== trim( (string) $args['text'] ) ) : ?>
				<span class="tpmc-trigger__text"><?php echo esc_html( $args['text'] ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $args['count'] ) : ?>
				<span class="tpmc-count<?php echo $args['hide_zero'] ? ' tpmc-count--hide-zero' : ''; ?>" data-tpmc-count="<?php echo esc_attr( $mode ); ?>" data-n="<?php echo esc_attr( (string) $n ); ?>"><?php echo esc_html( (string) $n ); ?></span>
			<?php endif; ?>
		</button>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * The side panel.
	 *
	 * @param array $args See the defaults below. `preview` (editor only) prints the cart lines
	 *                    with the page: array( 'lines' => array, 'open' => bool, 'empty' => bool ).
	 */
	public function drawer( array $args ): string {
		$args = wp_parse_args(
			$args,
			array(
				'id'            => 'default',
				'class'         => '',
				'side'          => 'left',
				'title'         => __( 'سبد خرید', 'techpino-mini-cart' ),
				'chip'          => true,
				'auto_open'     => true,
				'layout'        => 'row',
				'checkout_text' => __( 'تکمیل سفارش', 'techpino-mini-cart' ),
				'cart_text'     => __( 'مشاهده سبد', 'techpino-mini-cart' ),
				'total_label'   => __( 'جمع سبد خرید', 'techpino-mini-cart' ),
				'note'          => '',
				'empty_title'   => __( 'سبد خریدت خالیه', 'techpino-mini-cart' ),
				'empty_text'    => __( 'هنوز عطری انتخاب نکردی؛ از بین محصولات، رایحه‌ی خودت رو پیدا کن.', 'techpino-mini-cart' ),
				'empty_button'  => __( 'مشاهده محصولات', 'techpino-mini-cart' ),
				'empty_url'     => '',
				'empty_icon'    => '',
				'preview'       => null,
			)
		);

		++$this->drawers;

		$id      = sanitize_html_class( (string) $args['id'] );
		$cart    = $this->cart();
		$preview = is_array( $args['preview'] ) ? $args['preview'] : null;
		$content = array(
			'items' => '',
			'rows'  => '',
			'total' => '',
		);

		$classes = array( 'tpmc-drawer', 'tpmc--' . $id, 'tpmc--' . ( 'right' === $args['side'] ? 'right' : 'left' ) );
		if ( '' !== $args['class'] ) {
			$classes[] = sanitize_html_class( $args['class'] );
		}
		if ( $preview ) {
			$content = $this->content( $preview['lines'], $this->lines_subtotal( $preview['lines'] ), 0.0 );
			if ( ! empty( $preview['empty'] ) || ! $preview['lines'] ) {
				$classes[] = 'is-empty';
			}
			if ( ! empty( $preview['open'] ) ) {
				$classes[] = 'is-open';
				$classes[] = 'tpmc--preview';
			}
		} else {
			// Lines arrive with the script; until then an empty cart is already final.
			$classes[] = $cart && ! $cart->is_empty() ? 'is-loading' : 'is-empty';
		}

		$empty_url = '' !== $args['empty_url'] ? $args['empty_url'] : wc_get_page_permalink( 'shop' );
		$show_cart = '' !== trim( (string) $args['cart_text'] );
		// This very page load added a product (form submit, no AJAX): the script opens the drawer.
		$open_now = ! $preview && $args['auto_open'] && did_action( 'woocommerce_add_to_cart' ) && ! wp_doing_ajax();

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" id="tpmc-drawer-<?php echo esc_attr( $id ); ?>" data-auto-open="<?php echo $args['auto_open'] ? 'yes' : 'no'; ?>"<?php echo $preview ? ' data-tpmc-static' : ''; ?><?php echo $open_now ? ' data-tpmc-now' : ''; ?>>
			<div class="tpmc-overlay" data-tpmc-close></div>
			<aside class="tpmc-panel" role="dialog" aria-modal="true" aria-labelledby="tpmc-title-<?php echo esc_attr( $id ); ?>" tabindex="-1">
				<header class="tpmc-head">
					<div class="tpmc-title" id="tpmc-title-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $args['title'] ); ?></div>
					<?php if ( $args['chip'] ) : ?>
						<span class="tpmc-chip">
							<span data-tpmc-count="quantity"><?php echo esc_html( (string) ( $preview ? array_sum( wp_list_pluck( $preview['lines'], 'qty' ) ) : 0 ) ); ?></span>
							<?php esc_html_e( 'کالا', 'techpino-mini-cart' ); ?>
						</span>
					<?php endif; ?>
					<button type="button" class="tpmc-close" data-tpmc-close aria-label="<?php esc_attr_e( 'بستن سبد خرید', 'techpino-mini-cart' ); ?>"><?php echo $this->icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				</header>

				<div class="tpmc-msg" role="status" aria-live="polite" hidden></div>

				<div class="tpmc-body">
					<ul class="tpmc-items"><?php echo $content['items']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in item(). ?></ul>

					<div class="tpmc-skel" aria-hidden="true"><span></span><span></span></div>

					<div class="tpmc-empty">
						<div class="tpmc-empty__art" aria-hidden="true"><?php echo '' !== $args['empty_icon'] ? $args['empty_icon'] : $this->icon( 'bag' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup. ?></div>
						<?php if ( '' !== trim( (string) $args['empty_title'] ) ) : ?>
							<p class="tpmc-empty__title"><?php echo esc_html( $args['empty_title'] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== trim( (string) $args['empty_text'] ) ) : ?>
							<p class="tpmc-empty__text"><?php echo esc_html( $args['empty_text'] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== trim( (string) $args['empty_button'] ) ) : ?>
							<a class="tpmc-btn tpmc-btn--primary" href="<?php echo esc_url( $empty_url ); ?>"><?php echo esc_html( $args['empty_button'] ); ?></a>
						<?php endif; ?>
					</div>
				</div>

				<footer class="tpmc-foot">
					<div class="tpmc-rows"><?php echo $content['rows']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in content(). ?></div>
					<div class="tpmc-total">
						<span class="tpmc-total__label"><?php echo esc_html( $args['total_label'] ); ?></span>
						<strong class="tpmc-total__val"><?php echo wp_kses_post( $content['total'] ); ?></strong>
					</div>
					<?php if ( '' !== trim( (string) $args['note'] ) ) : ?>
						<p class="tpmc-note"><?php echo esc_html( $args['note'] ); ?></p>
					<?php endif; ?>
					<div class="tpmc-actions<?php echo $show_cart && 'stack' !== $args['layout'] ? ' tpmc-actions--row' : ''; ?>">
						<a class="tpmc-btn tpmc-btn--primary" href="<?php echo esc_url( wc_get_checkout_url() ); ?>"><?php echo esc_html( $args['checkout_text'] ); ?></a>
						<?php if ( $show_cart ) : ?>
							<a class="tpmc-btn tpmc-btn--ghost" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php echo esc_html( $args['cart_text'] ); ?></a>
						<?php endif; ?>
					</div>
				</footer>
			</aside>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/*
	|--------------------------------------------------------------------------
	| Cart lines
	|--------------------------------------------------------------------------
	*/

	/**
	 * @return array<int,array{key:string,product:WC_Product,qty:float|int,item:array}>
	 */
	private function cart_lines(): array {
		$lines = array();
		$cart  = $this->cart();
		if ( ! $cart ) {
			return $lines;
		}
		foreach ( $cart->get_cart() as $key => $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $product instanceof WC_Product || ! $product->exists() || $item['quantity'] <= 0 ) {
				continue;
			}
			if ( ! apply_filters( 'woocommerce_widget_cart_item_visible', true, $item, $key ) ) {
				continue;
			}
			$lines[] = array(
				'key'     => (string) $key,
				'product' => $product,
				'qty'     => $item['quantity'],
				'item'    => $item,
			);
		}
		return $lines;
	}

	/**
	 * Lines to show in the Elementor editor: the real cart, or two products as a sample.
	 */
	public function preview_lines(): array {
		$lines = $this->cart_lines();
		if ( $lines ) {
			return $lines;
		}
		$products = wc_get_products(
			array(
				'limit'   => 2,
				'status'  => 'publish',
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);
		foreach ( $products as $i => $product ) {
			$lines[] = array(
				'key'     => 'sample-' . $i,
				'product' => $product,
				'qty'     => $i + 1,
				'item'    => array(),
			);
		}
		return $lines;
	}

	private function lines_subtotal( array $lines ): float {
		$sum = 0.0;
		foreach ( $lines as $line ) {
			$sum += (float) wc_get_price_to_display( $line['product'], array( 'qty' => $line['qty'] ) );
		}
		return $sum;
	}

	/**
	 * Quantity limits of a line, as the cart page would apply them.
	 *
	 * @return array{min:float,max:float,step:float} max is -1 when unlimited.
	 */
	private function limits( WC_Product $product, $qty ): array {
		if ( $product->is_sold_individually() ) {
			return array(
				'min'  => 1.0,
				'max'  => 1.0,
				'step' => 1.0,
			);
		}
		$args = apply_filters(
			'woocommerce_quantity_input_args',
			array(
				'input_value' => $qty,
				'min_value'   => apply_filters( 'woocommerce_quantity_input_min', 1, $product ),
				'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
				'step'        => apply_filters( 'woocommerce_quantity_input_step', 1, $product ),
			),
			$product
		);
		$max  = isset( $args['max_value'] ) && '' !== $args['max_value'] ? (float) $args['max_value'] : -1.0;
		$step = isset( $args['step'] ) ? (float) $args['step'] : 1.0;

		return array(
			'min'  => max( 1.0, isset( $args['min_value'] ) ? (float) $args['min_value'] : 1.0 ),
			'max'  => $max < 0 ? -1.0 : $max,
			'step' => $step > 0 ? $step : 1.0,
		);
	}

	private function item( array $line ): string {
		$product = $line['product'];
		$item    = $line['item'];
		$key     = $line['key'];
		$qty     = $line['qty'];
		$real    = (bool) $item;
		$cart    = $this->cart();
		$limits  = $this->limits( $product, $qty );

		$name  = $product->get_name();
		$link  = $product->is_visible() ? $product->get_permalink( $item ) : '';
		$thumb = $product->get_image( 'woocommerce_gallery_thumbnail' );
		$meta  = '';
		if ( $real && $cart ) {
			$name  = apply_filters( 'woocommerce_cart_item_name', $name, $item, $key );
			$link  = apply_filters( 'woocommerce_cart_item_permalink', $link, $item, $key );
			$thumb = apply_filters( 'woocommerce_cart_item_thumbnail', $thumb, $item, $key );
			$meta  = implode( ' · ', array_filter( array_map( 'trim', explode( "\n", (string) wc_get_formatted_cart_item_data( $item, true ) ) ) ) );
			$unit  = apply_filters( 'woocommerce_cart_item_price', $cart->get_product_price( $product ), $item, $key );
			$total = apply_filters( 'woocommerce_cart_item_subtotal', $cart->get_product_subtotal( $product, $qty ), $item, $key );
		} else {
			$unit  = wc_price( wc_get_price_to_display( $product ) );
			$total = wc_price( wc_get_price_to_display( $product, array( 'qty' => $qty ) ) );
		}
		$name = wp_strip_all_tags( (string) $name );

		ob_start();
		?>
		<li class="tpmc-item" data-key="<?php echo esc_attr( $key ); ?>" data-qty="<?php echo esc_attr( (string) $qty ); ?>" data-min="<?php echo esc_attr( (string) $limits['min'] ); ?>" data-max="<?php echo esc_attr( (string) $limits['max'] ); ?>" data-step="<?php echo esc_attr( (string) $limits['step'] ); ?>">
			<?php if ( $link ) : ?>
				<a class="tpmc-item__thumb" href="<?php echo esc_url( $link ); ?>" tabindex="-1" aria-hidden="true"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product image markup. ?></a>
			<?php else : ?>
				<span class="tpmc-item__thumb"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product image markup. ?></span>
			<?php endif; ?>
			<div class="tpmc-item__body">
				<div class="tpmc-item__head">
					<?php if ( $link ) : ?>
						<a class="tpmc-item__name" href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $name ); ?></a>
					<?php else : ?>
						<span class="tpmc-item__name"><?php echo esc_html( $name ); ?></span>
					<?php endif; ?>
					<button type="button" class="tpmc-item__remove" data-tpmc-remove aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name */ __( 'حذف %s از سبد', 'techpino-mini-cart' ), $name ) ); ?>"><?php echo $this->icon( 'trash' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				</div>
				<?php if ( '' !== $meta ) : ?>
					<div class="tpmc-item__meta"><?php echo esc_html( $meta ); ?></div>
				<?php endif; ?>
				<div class="tpmc-item__foot">
					<?php if ( $limits['max'] < 0 || $limits['max'] > 1 ) : ?>
						<div class="tpmc-qty" role="group" aria-label="<?php esc_attr_e( 'تعداد', 'techpino-mini-cart' ); ?>">
							<button type="button" class="tpmc-qty__btn" data-tpmc-step="1" aria-label="<?php esc_attr_e( 'یکی بیشتر', 'techpino-mini-cart' ); ?>"<?php disabled( $limits['max'] >= 0 && $qty >= $limits['max'] ); ?>><?php echo $this->icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
							<span class="tpmc-qty__val"><?php echo esc_html( (string) $qty ); ?></span>
							<button type="button" class="tpmc-qty__btn" data-tpmc-step="-1" aria-label="<?php esc_attr_e( 'یکی کمتر', 'techpino-mini-cart' ); ?>"<?php disabled( $qty <= $limits['min'] ); ?>><?php echo $this->icon( 'minus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						</div>
					<?php endif; ?>
					<div class="tpmc-item__price">
						<?php if ( $qty > 1 ) : ?>
							<span class="tpmc-item__unit"><?php echo wp_kses_post( $unit ); ?> × <?php echo esc_html( (string) $qty ); ?></span>
						<?php endif; ?>
						<strong class="tpmc-item__total"><?php echo wp_kses_post( $total ); ?></strong>
					</div>
				</div>
			</div>
		</li>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * What the customer saves on a line thanks to a sale price.
	 */
	private function saving( array $line ): float {
		$product = $line['product'];
		$regular = (float) $product->get_regular_price();
		$price   = (float) $product->get_price();
		if ( $regular <= $price ) {
			return 0.0;
		}
		return (float) wc_get_price_to_display(
			$product,
			array(
				'price' => $regular,
				'qty'   => $line['qty'],
			)
		) - (float) wc_get_price_to_display(
			$product,
			array(
				'price' => $price,
				'qty'   => $line['qty'],
			)
		);
	}

	private function row( string $label, string $value, string $modifier ): string {
		return '<div class="tpmc-row tpmc-row--' . esc_attr( $modifier ) . '"><span>' . esc_html( $label ) . '</span><span>' . wp_kses_post( $value ) . '</span></div>';
	}

	/**
	 * @return array{items:string,rows:string,total:string}
	 */
	private function content( array $lines, float $subtotal, float $discount ): array {
		$items = '';
		$saved = 0.0;
		foreach ( $lines as $line ) {
			$items .= $this->item( $line );
			$saved += $this->saving( $line );
		}

		$rows = '';
		if ( $saved > 0 ) {
			$rows .= $this->row( __( 'سود شما از این خرید', 'techpino-mini-cart' ), wc_price( $saved ), 'save' );
		}
		if ( $discount > 0 ) {
			$rows .= $this->row( __( 'کد تخفیف', 'techpino-mini-cart' ), '−' . wc_price( $discount ), 'off' );
		}

		return array(
			'items' => $items,
			'rows'  => $rows,
			'total' => $lines ? wc_price( max( 0, $subtotal - $discount ) ) : '',
		);
	}

	/**
	 * The whole cart as the script needs it.
	 */
	public function state(): array {
		$cart  = $this->cart();
		$lines = $this->cart_lines();
		$state = array(
			'hash'  => '',
			'count' => 0,
			'lines' => count( $lines ),
		);
		if ( ! $cart || ! $lines ) {
			return $state + $this->content( array(), 0.0, 0.0 );
		}

		$discount = (float) $cart->get_discount_total();
		if ( $cart->display_prices_including_tax() ) {
			$discount += (float) $cart->get_discount_tax();
		}

		$state['hash']  = (string) $cart->get_cart_hash();
		$state['count'] = (int) $cart->get_cart_contents_count();

		return $state + $this->content( $lines, (float) $cart->get_displayed_subtotal(), $discount );
	}

	/*
	|--------------------------------------------------------------------------
	| AJAX
	|--------------------------------------------------------------------------
	*/

	/**
	 * WooCommerce's own "added to cart" answer carries the new state, so adding a product
	 * costs no second request.
	 */
	public function add_fragment( $fragments ) {
		if ( is_array( $fragments ) ) {
			$fragments['tpmc-data'] = wp_json_encode( $this->state() );
		}
		return $fragments;
	}

	/**
	 * /?wc-ajax=tpmc — op: get | set (key, qty) | remove (key). Always answers with the cart state.
	 *
	 * No nonce: a nonce printed into a cached page, or made before the visitor's session
	 * started, is stale by the time it is used. Changes need a custom request header
	 * instead, which another site cannot send without this site's CORS permission.
	 */
	public function ajax(): void {
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : 'get'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $this->cart() ) {
			wp_send_json( array( 'error' => __( 'سبد خرید در دسترس نیست.', 'techpino-mini-cart' ) ), 503 );
		}

		$error = '';
		if ( 'get' !== $op ) {
			if ( empty( $_SERVER['HTTP_X_TPMC'] ) ) {
				wp_send_json( array( 'error' => __( 'درخواست نامعتبر است.', 'techpino-mini-cart' ) ), 403 );
			}

			// Shipping rates (a remote call for some carriers) are worked out on the cart and checkout pages, not on every click here.
			add_filter( 'woocommerce_cart_ready_to_calc_shipping', '__return_false', 99 );

			// phpcs:disable WordPress.Security.NonceVerification.Missing
			$key = isset( $_POST['key'] ) ? wc_clean( wp_unslash( $_POST['key'] ) ) : '';
			$qty = isset( $_POST['qty'] ) ? wc_stock_amount( wc_clean( wp_unslash( $_POST['qty'] ) ) ) : 0;
			// phpcs:enable

			// Notices raised by validation hooks belong in the drawer, not on the next page.
			$kept = wc_get_notices();
			wc_clear_notices();

			$error  = 'remove' === $op ? $this->remove( $key ) : $this->set_quantity( $key, $qty );
			$raised = wc_get_notices( 'error' );
			if ( $raised ) {
				$error = wp_strip_all_tags( (string) $raised[0]['notice'] );
			}
			wc_set_notices( $kept );
		}

		// The cart cookies are how the script knows, on the next page, whether its copy is still good.
		$this->cart()->maybe_set_cart_cookies();

		$state = $this->state();
		if ( '' !== $error ) {
			$state['error'] = $error;
		}
		wp_send_json( $state );
	}

	private function remove( string $key ): string {
		$cart = $this->cart();
		if ( '' !== $key && $cart->get_cart_item( $key ) ) {
			$cart->remove_cart_item( $key );
			$cart->calculate_totals();
		}
		return '';
	}

	/**
	 * @return string Error to show, or ''.
	 */
	private function set_quantity( string $key, $qty ): string {
		$cart = $this->cart();
		$item = '' !== $key ? $cart->get_cart_item( $key ) : array();
		if ( ! $item || empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
			return __( 'این کالا دیگر در سبد خرید نیست.', 'techpino-mini-cart' );
		}

		$limits  = $this->limits( $item['data'], $item['quantity'] );
		$qty     = max( $limits['min'], (float) $qty );
		$message = '';
		if ( $limits['max'] >= 0 && $qty > $limits['max'] ) {
			if ( $limits['max'] < $limits['min'] ) {
				return __( 'موجودی این کالا کافی نیست.', 'techpino-mini-cart' );
			}
			$qty = $limits['max'];
			/* translators: %s: quantity */
			$message = sprintf( __( 'از این کالا حداکثر %s عدد قابل سفارش است.', 'techpino-mini-cart' ), wc_format_localized_decimal( $qty ) );
		}
		$qty = wc_stock_amount( $qty );
		if ( (float) $item['quantity'] === (float) $qty ) {
			return $message;
		}

		if ( ! apply_filters( 'woocommerce_update_cart_validation', true, $key, $item, $qty ) ) {
			return __( 'این تعداد قابل سفارش نیست.', 'techpino-mini-cart' );
		}
		$cart->set_quantity( $key, $qty, true );

		return $message;
	}
}
