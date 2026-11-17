<?php
/**
 * Aromist on phones: the shared shell on every page (compact header, search, ☰ menu) and the
 * redesigned product page (trust row, buy bar + size sheet), with the AJAX endpoints
 * (add to cart, search suggestions).
 *
 * Everything the visitor sees changes only up to 1024px — phones and tablets (assets/css/mobile.css); the
 * markup printed here is hidden on wider screens. Pages are the same HTML for phones and
 * desktops (LiteSpeed page cache), so no decision is taken from the user agent.
 * Beyond the shell only product pages change; the footer is never touched.
 *
 * @package AromistMobilePDP
 */

defined( 'ABSPATH' ) || exit;

final class AMPDP_Plugin {

	/** Option: off | preview (shop managers only) | on (everyone). */
	const OPTION = 'ampdp_mode';

	/** @var AMPDP_Plugin|null */
	private static $instance = null;

	/** @var bool|null Memoised is_active() for this request. */
	private $active = null;

	/** @var bool|null Memoised is_product_page() for this request. */
	private $product_page = null;

	/** @var bool|null Memoised is_shell_page() for this request. */
	private $shell_page = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 30 );
		add_action( 'wp_body_open', array( $this, 'print_header' ) );
		add_action( 'wp_footer', array( $this, 'print_footer_ui' ), 5 );
		add_action( 'wc_ajax_ampdp_add', array( $this, 'ajax_add' ) );
		add_action( 'wc_ajax_ampdp_search', array( $this, 'ajax_search' ) );
		add_shortcode( 'ampdp_button', array( $this, 'shortcode_button' ) );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'editor_styles' ) );
		add_filter( 'hello_elementor_viewport_content', array( $this, 'viewport' ) );
		foreach ( array( self::OPTION, AMPDP_Settings::OPTION ) as $option ) {
			add_action( 'add_option_' . $option, array( $this, 'purge_page_cache' ) );
			add_action( 'update_option_' . $option, array( $this, 'purge_page_cache' ) );
		}
	}

	public static function modes(): array {
		return array( 'off', 'preview', 'on' );
	}

	public static function mode(): string {
		$mode = (string) get_option( self::OPTION, 'off' );
		return in_array( $mode, self::modes(), true ) ? $mode : 'off';
	}

	/**
	 * On for this visitor: «on», or «preview» for a signed-in shop manager (whose pages
	 * LiteSpeed does not cache, so guests never get a preview page from the cache).
	 */
	public function is_active(): bool {
		if ( null !== $this->active ) {
			return $this->active;
		}
		$mode = self::mode();
		$on   = 'on' === $mode || ( 'preview' === $mode && current_user_can( 'manage_woocommerce' ) );
		// Never inside the Elementor editor or its preview frame.
		if ( isset( $_GET['elementor-preview'] ) || ( isset( $_GET['action'] ) && 'elementor' === $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$on = false;
		}
		$this->active = (bool) apply_filters( 'ampdp_active', $on );
		return $this->active;
	}

	/**
	 * A product page this plugin redesigns: a plain variable or simple product shown with
	 * the product template the stylesheet is written for. Anything else — a discovery set,
	 * a product on another template — keeps its page as it is.
	 */
	private function is_product_page(): bool {
		if ( null !== $this->product_page ) {
			return $this->product_page;
		}
		$this->product_page = false;
		if ( $this->is_active() && function_exists( 'is_product' ) && is_product() ) {
			$product            = $this->product();
			$types              = (array) apply_filters( 'ampdp_product_types', array( 'variable', 'simple' ) );
			$this->product_page = $product && in_array( $product->get_type(), $types, true ) && $this->uses_template();
		}
		return $this->product_page;
	}

	/** The page is rendered with Elementor template #407 (filter `ampdp_template_id`; 0 skips the check). */
	private function uses_template(): bool {
		$template_id = (int) apply_filters( 'ampdp_template_id', 407 );
		if ( ! $template_id ) {
			return true;
		}
		if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			return false;
		}
		$documents = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->get_documents_for_location( 'single' );
		return isset( $documents[ $template_id ] );
	}

	/**
	 * A front-end page this plugin draws on: the redesigned product page, and nothing else. Every other
	 * page — its header, footer and styles — is the site's own.
	 */
	private function is_shell_page(): bool {
		if ( null !== $this->shell_page ) {
			return $this->shell_page;
		}
		$this->shell_page = $this->is_active() && ! is_admin() && ! wp_doing_ajax() && ! is_feed() && ! is_embed() && $this->is_product_page();
		$this->shell_page = (bool) apply_filters( 'ampdp_shell_page', $this->shell_page );
		return $this->shell_page;
	}

	private function product(): ?WC_Product {
		$product = wc_get_product( get_queried_object_id() );
		return $product instanceof WC_Product ? $product : null;
	}

	private function in_stock( WC_Product $product ): bool {
		return $product->is_purchasable() && $product->is_in_stock();
	}

	public function body_class( array $classes ): array {
		if ( ! $this->is_shell_page() ) {
			return $classes;
		}
		$classes[] = 'ampdp';
		if ( ! $this->is_product_page() ) {
			return $classes;
		}
		$product   = $this->product();
		$classes[] = 'ampdp-pdp';
		$classes[] = 'ampdp-type-' . sanitize_html_class( $product->get_type() );
		if ( ! AMPDP_Settings::get( 'pdp_tags' ) ) {
			$classes[] = 'ampdp-no-tags';
		}
		if ( ! $this->in_stock( $product ) ) {
			$classes[] = 'ampdp-oos';
		}
		return $classes;
	}

	public function assets(): void {
		if ( ! $this->is_shell_page() ) {
			return;
		}
		$product = $this->is_product_page() ? $this->product() : null;
		$ver     = AMPDP_VERSION . '.' . filemtime( AMPDP_PATH . 'assets/js/mobile.js' ) . filemtime( AMPDP_PATH . 'assets/css/mobile.css' );
		wp_enqueue_style( 'ampdp', AMPDP_URL . 'assets/css/mobile.css', array(), $ver );
		wp_enqueue_script( 'ampdp', AMPDP_URL . 'assets/js/mobile.js', array( 'jquery' ), $ver, true );

		wp_localize_script(
			'ampdp',
			'AMPDP',
			array(
				'ajaxUrl'   => WC_AJAX::get_endpoint( 'ampdp_add' ),
				'searchUrl' => AMPDP_Settings::get( 'search_live' ) ? WC_AJAX::get_endpoint( 'ampdp_search' ) : '',
				'cartUrl'   => wc_get_cart_url(),
				'product'   => $product ? array(
					'id'       => $product->get_id(),
					'name'     => $product->get_name(),
					'type'     => $product->get_type(),
					'inStock'  => $this->in_stock( $product ),
					'minPrice' => (float) ( $product->is_type( 'variable' ) ? $product->get_variation_price( 'min', true ) : wc_get_price_to_display( $product ) ),
				) : null,
				'pdp'       => array(
					'tabs'       => (bool) AMPDP_Settings::get( 'pdp_tabs' ),
					'aboutLines' => (int) AMPDP_Settings::get( 'about_lines' ),
				),
				'hideHeader' => false, // The header stays put on every page.
				'i18n'      => array(
					'from'        => __( 'قیمت از', 'aromist-mobile-pdp' ),
					'toman'       => __( 'تومان', 'aromist-mobile-pdp' ),
					'pick'        => __( 'حجم را انتخاب کنید', 'aromist-mobile-pdp' ),
					'chooseSize'  => __( 'انتخاب حجم', 'aromist-mobile-pdp' ),
					/* translators: %s: number of sizes */
					'sizes'       => __( '%s حجم موجود', 'aromist-mobile-pdp' ),
					/* translators: %s: size, e.g. «۱۰ میل» */
					'priceOf'     => __( 'قیمت %s', 'aromist-mobile-pdp' ),
					'off'         => __( 'تخفیف', 'aromist-mobile-pdp' ),
					'upTo'        => __( 'تا', 'aromist-mobile-pdp' ),
					/* translators: %s: price of one millilitre */
					'perMl'       => __( 'هر میل %s تومان', 'aromist-mobile-pdp' ),
					'inStock'     => __( 'موجود', 'aromist-mobile-pdp' ),
					'outOfStock'  => __( 'ناموجود', 'aromist-mobile-pdp' ),
					'best'        => __( 'به‌صرفه‌ترین', 'aromist-mobile-pdp' ),
					'first'       => __( 'قسط اول', 'aromist-mobile-pdp' ),
					'snappTitle'  => __( 'خرید قسطی با اسنپ‌پی', 'aromist-mobile-pdp' ),
					'snappText'   => __( '۴ قسط ماهانه، بدون سود و بدون ضامن', 'aromist-mobile-pdp' ),
					'more'        => __( 'نمایش بیشتر', 'aromist-mobile-pdp' ),
					'less'        => __( 'نمایش کمتر', 'aromist-mobile-pdp' ),
					'sections'    => __( 'بخش‌های صفحه', 'aromist-mobile-pdp' ),
					'aboutHead'   => __( 'درباره این عطر', 'aromist-mobile-pdp' ),
					'tabAbout'    => __( 'درباره عطر', 'aromist-mobile-pdp' ),
					'tabSpecs'    => __( 'مشخصات', 'aromist-mobile-pdp' ),
					'tabNotes'    => __( 'نت‌ها', 'aromist-mobile-pdp' ),
					'tabArticle'  => __( 'معرفی کامل', 'aromist-mobile-pdp' ),
					'tabFaq'      => __( 'سوالات', 'aromist-mobile-pdp' ),
					'tabReviews'  => __( 'نظرات', 'aromist-mobile-pdp' ),
					'tabSimilar'  => __( 'مشابه', 'aromist-mobile-pdp' ),
					'notesHead'   => __( 'گروه بویایی و نت‌ها', 'aromist-mobile-pdp' ),
					'scrollMore'  => __( 'نت‌های بیشتر', 'aromist-mobile-pdp' ),
					'noReviews'   => __( 'بدون نظر', 'aromist-mobile-pdp' ),
					'firstReview' => __( 'اولین نظر را بنویسید', 'aromist-mobile-pdp' ),
					/* translators: %s: number of reviews */
					'reviews'     => __( '%s نظر', 'aromist-mobile-pdp' ),
					'specs'       => __( 'مشخصات این عطر', 'aromist-mobile-pdp' ),
					'article'     => __( 'معرفی کامل عطر', 'aromist-mobile-pdp' ),
					'reviewsHead' => __( 'نظرات کاربران', 'aromist-mobile-pdp' ),
					/* translators: 1: current image, 2: number of images */
					'of'          => __( '%1$s از %2$s', 'aromist-mobile-pdp' ),
					'zoom'        => __( 'نمایش بزرگ', 'aromist-mobile-pdp' ),
					'close'       => __( 'بستن', 'aromist-mobile-pdp' ),
					'linkCopied'  => __( 'لینک محصول کپی شد', 'aromist-mobile-pdp' ),
					'error'       => __( 'افزودن به سبد انجام نشد. دوباره امتحان کنید.', 'aromist-mobile-pdp' ),
					'unavailable' => __( 'این عطر فعلاً موجود نیست', 'aromist-mobile-pdp' ),
					'searching'   => __( 'در حال جستجو…', 'aromist-mobile-pdp' ),
					'noResults'   => __( 'محصولی با این نام پیدا نشد.', 'aromist-mobile-pdp' ),
					/* translators: %s: what was typed */
					'allResults'  => __( 'همه نتایج برای «%s»', 'aromist-mobile-pdp' ),
					'fromPrice'   => __( 'از', 'aromist-mobile-pdp' ),
				),
			)
		);
	}

	/**
	 * Keep the layout viewport sized to the device while allowing visitors to zoom in.
	 * (the theme, Hello Elementor, prints this tag).
	 */
	public function viewport( $content ) {
		if ( is_admin() || ! $this->is_product_page() ) {
			return $content;
		}
		return 'width=device-width, initial-scale=1';
	}

	/** The Elementor editor's preview: the header buttons look as on the site while a header is designed. */
	public function editor_styles(): void {
		wp_enqueue_style( 'ampdp', AMPDP_URL . 'assets/css/mobile.css', array(), AMPDP_VERSION . '.' . filemtime( AMPDP_PATH . 'assets/css/mobile.css' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Markup
	|--------------------------------------------------------------------------
	*/

	public static function icon( string $name ): string {
		if ( 'bag' === $name ) {
			// The bag of the site's product cards (template 420), with a line as thick as the other icons.
			return '<svg class="ampdp-ic" viewBox="-36 -33 72 72" width="24" height="24" aria-hidden="true" focusable="false"><g transform="scale(.959 1.074)"><path vector-effect="non-scaling-stroke" d="M-28-10-32 25c0 5 4 10 9 10h46c5 0 9-5 9-10l-4-35Z"/><path vector-effect="non-scaling-stroke" d="M-15-10c0-12 5-18 15-18s15 6 15 18"/></g></svg>';
		}
		$paths = array(
			'menu'   => '<path d="M4 7h16M4 12h16M4 17h10"/>',
			'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
			'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
			'bag'    => '<path d="M5 8h14l-.8 11.2a2 2 0 0 1-2 1.8H7.8a2 2 0 0 1-2-1.8L5 8Z"/><path d="M9 11V6.5a3 3 0 0 1 6 0V11"/>',
			'close'  => '<path d="M18 6 6 18M6 6l12 12"/>',
			'back'   => '<path d="m9 18 6-6-6-6"/>',
			'share'  => '<circle cx="18" cy="5" r="2.6"/><circle cx="6" cy="12" r="2.6"/><circle cx="18" cy="19" r="2.6"/><path d="m8.3 10.8 7.4-4.3M8.3 13.2l7.4 4.3"/>',
			'plus'   => '<path d="M12 5v14M5 12h14"/>',
			'minus'  => '<path d="M5 12h14"/>',
			'bell'   => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
			'down'   => '<path d="m6 9 6 6 6-6"/>',
			'left'   => '<path d="m15 18-6-6 6-6"/>',
			'grid'   => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
		);
		return '<svg class="ampdp-ic" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * A line icon inside the thin ring of the site's product-card cart icon — the product
	 * page's header buttons (back, search) match its cart button this way.
	 */
	public static function ring_icon( string $name ): string {
		$paths = array(
			'back'   => '<path d="M10.6 8.4 14.2 12l-3.6 3.6"/>',
			'search' => '<circle cx="11.4" cy="11.4" r="3.7"/><path d="m14.2 14.2 2.4 2.4"/>',
		);
		// Ring and line as thin as the cart icon's (3.5 and 4.5 of 200).
		return '<svg class="ampdp-ring-ic" viewBox="0 0 24 24" width="40" height="40" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10.2" fill="none" stroke="currentColor" stroke-width=".42"/><g fill="none" stroke="currentColor" stroke-width=".62" stroke-linecap="round" stroke-linejoin="round">' . $paths[ $name ] . '</g></svg>';
	}

	/** The cart icon of the site's product cards (template 420): a ring around the bag. */
	public static function cart_icon(): string {
		return '<svg class="ampdp-cart-ic" viewBox="0 0 200 200" width="40" height="40" aria-hidden="true" focusable="false"><circle cx="98.4" cy="98.6" r="85" fill="none" stroke="currentColor" stroke-width="3.5"/><g transform="matrix(.959 0 0 1.074 99.33 92.82)" fill="none" stroke="currentColor" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"><path d="M-28-10-32 25c0 5 4 10 9 10h46c5 0 9-5 9-10l-4-35Z"/><path d="M-15-10c0-12 5-18 15-18s15 6 15 18"/></g></svg>';
	}

	/**
	 * One header button: ☰, search, account or cart. The built-in header uses these, and an
	 * Elementor header gets them with the shortcode [ampdp_button type="…"].
	 */
	private function header_button( string $type ): string {
		$s = AMPDP_Settings::all();
		switch ( $type ) {
			case 'back':
				// The previous page when it is on this site (mobile.js), else the product's category or the shop.
				return '<a class="ampdp-ib ampdp-ib--plain" href="' . esc_url( $this->back_url() ) . '" data-ampdp-back aria-label="' . esc_attr__( 'بازگشت', 'aromist-mobile-pdp' ) . '">' . self::icon( 'back' ) . '</a>';
			case 'search-plain':
				return '<button type="button" class="ampdp-ib ampdp-ib--plain" data-ampdp-search aria-haspopup="dialog" aria-label="' . esc_attr__( 'جستجو', 'aromist-mobile-pdp' ) . '">' . self::icon( 'search' ) . '</button>';
			case 'share':
				return '<button type="button" class="ampdp-ib ampdp-ib--plain" data-ampdp-share aria-label="' . esc_attr__( 'اشتراک‌گذاری', 'aromist-mobile-pdp' ) . '">' . self::icon( 'share' ) . '</button>';
			case 'cart-plain':
				$count = WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
				$badge = '<span class="ampdp-badge" data-tpmc-count="quantity" data-n="' . esc_attr( (string) $count ) . '">' . esc_html( (string) $count ) . '</span>';
				$label = esc_attr__( 'سبد خرید', 'aromist-mobile-pdp' );
				if ( class_exists( 'TPMC_Plugin' ) ) {
					return '<button type="button" class="ampdp-ib ampdp-ib--plain" data-tpmc-open="" aria-haspopup="dialog" aria-expanded="false" aria-label="' . $label . '">' . self::icon( 'bag' ) . $badge . '</button>';
				}
				return '<a class="ampdp-ib ampdp-ib--plain" href="' . esc_url( wc_get_cart_url() ) . '" aria-label="' . $label . '">' . self::icon( 'bag' ) . $badge . '</a>';
			case 'menu':
				$own = 'site' !== $s['menu'] && $this->menu_items();
				return '<button type="button" class="ampdp-ib" data-ampdp-menu="' . ( $own ? 'own' : 'site' ) . '" aria-haspopup="dialog" aria-label="' . esc_attr__( 'منو', 'aromist-mobile-pdp' ) . '">' . self::icon( 'menu' ) . '</button>';
			case 'search':
				return '<button type="button" class="ampdp-ib" data-ampdp-search aria-haspopup="dialog" aria-label="' . esc_attr__( 'جستجو', 'aromist-mobile-pdp' ) . '">' . self::icon( 'search' ) . '</button>';
			case 'account':
				$label = is_user_logged_in() ? __( 'حساب کاربری', 'aromist-mobile-pdp' ) : __( 'ورود | ثبت‌نام', 'aromist-mobile-pdp' );
				return '<a class="ampdp-ib" href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" aria-label="' . esc_attr( $label ) . '">' . self::icon( 'user' ) . '</a>';
			case 'cart':
				$count = WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
				$badge = '<span class="ampdp-badge" data-tpmc-count="quantity" data-n="' . esc_attr( (string) $count ) . '">' . esc_html( (string) $count ) . '</span>';
				$label = esc_attr__( 'سبد خرید', 'aromist-mobile-pdp' );
				if ( class_exists( 'TPMC_Plugin' ) ) {
					return '<button type="button" class="ampdp-ib ampdp-ib--cart" data-tpmc-open="" aria-haspopup="dialog" aria-expanded="false" aria-label="' . $label . '">' . self::cart_icon() . $badge . '</button>';
				}
				return '<a class="ampdp-ib ampdp-ib--cart" href="' . esc_url( wc_get_cart_url() ) . '" aria-label="' . $label . '">' . self::cart_icon() . $badge . '</a>';
		}
		return '';
	}

	/** Where «بازگشت» goes when there is no earlier page of this site: the product's main category, else the shop. */
	private function back_url(): string {
		$product = is_product() ? $this->product() : null;
		if ( $product ) {
			$cats = wc_get_product_term_ids( $product->get_id(), 'product_cat' );
			$main = $cats ? get_term( (int) $cats[0], 'product_cat' ) : null;
			if ( $main instanceof WP_Term ) {
				$link = get_term_link( $main );
				if ( ! is_wp_error( $link ) ) {
					return (string) $link;
				}
			}
		}
		return (string) wc_get_page_permalink( 'shop' );
	}

	/** [ampdp_button type="menu|search|account|cart"] — for a header designed in Elementor. */
	public function shortcode_button( $atts ): string {
		$atts = shortcode_atts( array( 'type' => 'search' ), (array) $atts, 'ampdp_button' );
		$type = sanitize_key( (string) $atts['type'] );
		// Shown in the Elementor editor too, so the header can be designed around it.
		$editor = isset( $_GET['elementor-preview'] ) || ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'elementor_ajax' === $_REQUEST['action'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ( ! $editor && ! $this->is_shell_page() ) || ! in_array( $type, array( 'menu', 'search', 'account', 'cart' ), true ) ) {
			return '';
		}
		return '<span class="ampdp-sc">' . $this->header_button( $type ) . '</span>';
	}

	/** A published Elementor template's content (with its CSS), or '' when there is none. */
	private function elementor_content( int $id ): string {
		if ( ! $id || 'publish' !== get_post_status( $id ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return '';
		}
		return (string) \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $id, true );
	}

	/**
	 * Compact sticky header (and its search) — every page, phones only. The header is either
	 * the built-in one or an Elementor template chosen in the settings; its search, ☰ and cart
	 * buttons work the same way in both.
	 */
	public function print_header(): void {
		if ( ! $this->is_shell_page() ) {
			return;
		}
		$s      = AMPDP_Settings::all();
		$live   = $this->live_search( $s );
		$custom = $this->elementor_content( (int) $s['header_tpl'] );
		if ( '' !== $custom ) {
			echo '<header class="ampdp-hdr ampdp-hdr--tpl" id="ampdp-hdr">' . $custom . '</header>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's rendered template.
			$this->print_search_layer( $s, $live );
			return;
		}
		// Product page: back on the right; cart, share and search on the left. No bar: it is part of the page.
		?>
		<header class="ampdp-hdr ampdp-hdr--pdp" id="ampdp-hdr">
			<?php echo $this->header_button( 'back' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
			<div class="ampdp-hdr__side">
				<?php
				echo $this->header_button( 'cart-plain' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				echo $this->header_button( 'share' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				echo $this->header_button( 'search-plain' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				?>
			</div>
		</header>
		<?php
		$this->print_search_layer( $s, $live );
	}

	/**
	 * Search behind a search button (product page, the ☰-logo header, an Elementor header): the
	 * site's live search as a bar that covers the header, its results in a panel under it. Without
	 * that plugin, this plugin's own search screen.
	 */
	private function print_search_layer( array $s, string $live ): void {
		if ( '' === $live ) {
			$this->print_search( $s );
			return;
		}
		?>
		<div class="ampdp-sbar ampdp-sbar--float" id="ampdp-search" hidden>
			<button type="button" class="ampdp-ib ampdp-sbar__close" data-ampdp-sbar-close aria-label="<?php esc_attr_e( 'بستن جستجو', 'aromist-mobile-pdp' ); ?>"><?php echo self::icon( 'back' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			<?php echo $live; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the widget's own escaped markup. ?>
		</div>
		<?php
	}

	/**
	 * The site's live search (plugin techpino-live-search, widget «جستجوی زنده (تکپینو)») drawn
	 * for the search screen: results inside the screen, as the site's product card laid sideways.
	 * '' when that plugin or Elementor is not there; the screen then uses its own search.
	 */
	private function live_search( array $s ): string {
		if ( ! class_exists( 'TPLS_Plugin' ) || ! did_action( 'elementor/loaded' ) ) {
			return '';
		}
		$banners = array();
		foreach ( AMPDP_Settings::banners() as $i => $banner ) {
			$banners[] = array(
				'_id'   => 'b' . $i,
				'image' => array(
					'id'  => (int) $banner['image'],
					'url' => (string) wp_get_attachment_image_url( (int) $banner['image'], 'large' ),
				),
				'link'  => array( 'url' => (string) $banner['link'] ),
			);
		}
		$suggestions = array();
		foreach ( AMPDP_Settings::popular() as $i => $q ) {
			$suggestions[] = array(
				'_id'  => 'q' . $i,
				'text' => $q,
			);
		}
		$widget = \Elementor\Plugin::instance()->elements_manager->create_element_instance(
			array(
				'id'         => 'ampdpls',
				'elType'     => 'widget',
				'widgetType' => 'tp-live-search',
				'settings'   => array(
					'placeholder'   => (string) $s['search_placeholder'],
					'display'       => 'dropdown',
					'layout'        => 'card',
					'banners'       => $banners,
					'submit'        => 'none',
					'digits'        => 'persian',
					'max_results'   => 8,
					'suggestions'   => $suggestions,
					'show_terms'    => 'yes',
					'all'           => 'more',
				),
			)
		);
		if ( ! $widget ) {
			return '';
		}
		wp_enqueue_style( 'tpls' );
		wp_enqueue_script( 'tpls' );
		ob_start();
		$widget->print_element();
		return (string) ob_get_clean();
	}

	private function print_search( array $s ): void {
		$popular = AMPDP_Settings::popular();
		$cats    = array_filter(
			array_map(
				static function ( $id ) {
					$term = get_term( (int) $id, 'product_cat' );
					return $term instanceof WP_Term ? $term : null;
				},
				(array) $s['search_cats']
			)
		);
		$search  = static function ( string $q ): string {
			return add_query_arg(
				array(
					's'         => rawurlencode( $q ),
					'post_type' => 'product',
				),
				home_url( '/' )
			);
		};
		?>
		<div class="ampdp-search" id="ampdp-search" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'جستجو', 'aromist-mobile-pdp' ); ?>" hidden>
			<form class="ampdp-search__bar" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<button type="button" class="ampdp-ib" data-ampdp-close aria-label="<?php esc_attr_e( 'بستن جستجو', 'aromist-mobile-pdp' ); ?>"><?php echo self::icon( 'back' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				<label class="ampdp-search__field">
					<?php echo self::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input type="search" name="s" id="ampdp-search-input" placeholder="<?php echo esc_attr( (string) $s['search_placeholder'] ); ?>" aria-label="<?php esc_attr_e( 'جستجو در محصولات', 'aromist-mobile-pdp' ); ?>" autocomplete="off" enterkeyhint="search">
					<button type="button" class="ampdp-search__clear" aria-label="<?php esc_attr_e( 'پاک کردن', 'aromist-mobile-pdp' ); ?>" hidden><?php echo self::icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				</label>
				<input type="hidden" name="post_type" value="product">
			</form>
			<div class="ampdp-search__body">
				<div class="ampdp-search__results" aria-live="polite" hidden></div>
				<div class="ampdp-search__idle">
					<?php
					if ( 'top' === $s['banner_pos'] ) {
						$this->print_search_banners( $s );
					}
					?>
					<section class="ampdp-search__sec" data-ampdp-recent hidden>
						<div class="ampdp-search__head"><h3><?php esc_html_e( 'جستجوهای اخیر', 'aromist-mobile-pdp' ); ?></h3><button type="button" class="ampdp-link" data-ampdp-recent-clear><?php esc_html_e( 'پاک کردن', 'aromist-mobile-pdp' ); ?></button></div>
						<div class="ampdp-chips"></div>
					</section>
					<?php if ( $popular ) : ?>
						<section class="ampdp-search__sec">
							<div class="ampdp-search__head"><h3><?php esc_html_e( 'جستجوهای پرطرفدار', 'aromist-mobile-pdp' ); ?></h3></div>
							<div class="ampdp-chips">
								<?php foreach ( $popular as $q ) : ?>
									<a class="ampdp-chip" href="<?php echo esc_url( $search( $q ) ); ?>" data-ampdp-q="<?php echo esc_attr( $q ); ?>"><?php echo esc_html( $q ); ?></a>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endif; ?>
					<?php
					if ( 'middle' === $s['banner_pos'] ) {
						$this->print_search_banners( $s );
					}
					?>
					<?php if ( $cats ) : ?>
						<section class="ampdp-search__sec">
							<div class="ampdp-search__head"><h3><?php esc_html_e( 'دسته‌بندی‌ها', 'aromist-mobile-pdp' ); ?></h3></div>
							<div class="ampdp-cats">
								<?php
								foreach ( $cats as $term ) :
									$img = (string) wp_get_attachment_image_url( (int) get_term_meta( $term->term_id, 'thumbnail_id', true ), 'thumbnail' );
									?>
									<a class="ampdp-cat" href="<?php echo esc_url( (string) get_term_link( $term ) ); ?>">
										<span class="ampdp-cat__img"><?php echo $img ? '<img src="' . esc_url( $img ) . '" alt="" loading="lazy">' : self::icon( 'grid' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<span><?php echo esc_html( $term->name ); ?></span>
									</a>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endif; ?>
					<?php
					if ( 'bottom' === $s['banner_pos'] ) {
						$this->print_search_banners( $s );
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/** Banners (and/or an Elementor template) in the search screen, from the settings. */
	private function print_search_banners( array $s ): void {
		$banners = AMPDP_Settings::banners();
		$custom  = $this->elementor_content( (int) $s['search_tpl'] );
		if ( ! $banners && '' === $custom ) {
			return;
		}
		echo '<section class="ampdp-search__sec ampdp-banners">';
		if ( $banners ) {
			echo '<div class="ampdp-banners__list ampdp-banners--' . esc_attr( (string) $s['banner_layout'] ) . '">';
			foreach ( $banners as $banner ) {
				$img = wp_get_attachment_image(
					(int) $banner['image'],
					'grid' === $s['banner_layout'] ? 'medium_large' : 'large',
					false,
					array(
						'class'   => 'ampdp-banner__img',
						'loading' => 'lazy',
						'sizes'   => 'grid' === $s['banner_layout'] ? '50vw' : '100vw',
					)
				);
				if ( ! $img ) {
					continue;
				}
				if ( '' !== $banner['link'] ) {
					echo '<a class="ampdp-banner" href="' . esc_url( $banner['link'] ) . '">' . $img . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image().
				} else {
					echo '<div class="ampdp-banner">' . $img . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image().
				}
			}
			echo '</div>';
		}
		if ( '' !== $custom ) {
			echo '<div class="ampdp-banners__tpl">' . $custom . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's rendered template.
		}
		echo '</section>';
	}

	/**
	 * Items of the WordPress menu chosen for ☰, as a two-level tree.
	 *
	 * @return array<int, array{item: WP_Post, children: WP_Post[]}>
	 */
	private function menu_items(): array {
		$menu_id = absint( AMPDP_Settings::get( 'menu' ) );
		$items   = $menu_id ? wp_get_nav_menu_items( $menu_id ) : array();
		if ( ! $items ) {
			return array();
		}
		$tree = array();
		$top  = array();
		foreach ( $items as $item ) {
			if ( ! (int) $item->menu_item_parent ) {
				$tree[ $item->ID ] = array(
					'item'     => $item,
					'children' => array(),
				);
			}
			$top[ $item->ID ] = (int) $item->menu_item_parent;
		}
		foreach ( $items as $item ) {
			$parent = (int) $item->menu_item_parent;
			// Deeper levels are listed under their top-level ancestor.
			while ( $parent && ! isset( $tree[ $parent ] ) && isset( $top[ $parent ] ) ) {
				$parent = $top[ $parent ];
			}
			if ( $parent && isset( $tree[ $parent ] ) ) {
				$tree[ $parent ]['children'][] = $item;
			}
		}
		return $tree;
	}

	/**
	 * Trust row from the settings, as a <template>: the script puts it right after the buy box
	 * (Elementor may serve the template's containers from its element cache, so no render hook).
	 */
	private function print_trust_row(): void {
		if ( ! AMPDP_Settings::get( 'trust_on' ) ) {
			return;
		}
		$items = AMPDP_Settings::trust_items();
		if ( ! $items ) {
			return;
		}
		echo '<template id="ampdp-trust"><div class="ampdp-trust" style="--ampdp-trust-n:' . (int) count( $items ) . '">';
		foreach ( $items as $item ) {
			$tag  = '' !== $item['link'] ? 'a' : 'div';
			$href = 'a' === $tag ? ' href="' . esc_url( $item['link'], array( 'http', 'https', 'tel', 'mailto' ) ) . '"' : '';
			printf(
				'<%1$s class="ampdp-trust__item"%2$s><i>%3$s</i><span>%4$s</span></%1$s>',
				$tag, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a or div.
				$href, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				AMPDP_Settings::icon_svg( $item['icon'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
				esc_html( $item['title'] )
			);
		}
		echo '</div></template>';
	}

	/**
	 * The product's «سوالات متداول» (plugin techpino-product-faq), as a <template>: the script puts
	 * it after the full article. Drawn here rather than taken from the template's FAQ widget, so
	 * phones get it whether or not the Elementor template has that widget (hidden on phones).
	 */
	private function print_faq( WC_Product $product ): void {
		if ( ! AMPDP_Settings::get( 'pdp_faq' ) || ! class_exists( 'TPFQ_Plugin' ) ) {
			return;
		}
		$id    = $product->get_id();
		$faq   = TPFQ_Plugin::instance();
		$count = method_exists( 'TPFQ_Plugin', 'questions' ) ? count( TPFQ_Plugin::questions( $id ) ) : count( TPFQ_Plugin::items( $id ) );
		$url   = method_exists( 'TPFQ_Plugin', 'settings' ) ? trim( (string) TPFQ_Plugin::settings()['cta_url'] ) : '';
		$html  = $count ? $faq->render(
			array(
				'product' => $id,
				'title'   => '',
				'open'    => 'none',
				'card'    => false,
				// The «still have a question?» box only with somewhere to go.
				'cta'     => '' !== $url,
				'class'   => 'tpfq--ampdp',
			)
		) : '';
		if ( '' === $html ) {
			return;
		}
		?>
		<template id="ampdp-faq">
			<section class="ampdp-faq" id="ampdp-faq-sec" aria-labelledby="ampdp-faq-title">
				<h2 class="ampdp-faq__title" id="ampdp-faq-title"><?php esc_html_e( 'سوالات متداول', 'aromist-mobile-pdp' ); ?> <span class="ampdp-acc__cnt"><?php echo esc_html( strtr( (string) $count, array_combine( range( 0, 9 ), array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ) ) ) ); ?></span></h2>
				<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in TPFQ_Plugin::render(). ?>
			</section>
		</template>
		<?php
	}

	/** Scrim and the ☰ menu sheet on every page; the buy bar and size sheet on product pages. */
	public function print_footer_ui(): void {
		if ( ! $this->is_shell_page() ) {
			return;
		}
		if ( $this->is_product_page() ) {
			$this->print_buy_ui();
		}
		echo '<div class="ampdp-scrim" data-ampdp-close hidden></div>';
		$this->print_menu_sheet();
	}

	/** Trust row, buy bar and size sheet — product pages, phones only. */
	private function print_buy_ui(): void {
		$product  = $this->product();
		$in_stock = $this->in_stock( $product );
		$variable = $product->is_type( 'variable' );
		$thumb    = (string) wp_get_attachment_image_url( (int) $product->get_image_id(), 'woocommerce_gallery_thumbnail' );
		$this->print_trust_row();
		$this->print_faq( $product );
		?>
		<div class="ampdp-bar<?php echo $in_stock ? '' : ' ampdp-bar--oos'; ?>" id="ampdp-bar" aria-hidden="true">
			<div class="ampdp-bar__price">
				<?php if ( $in_stock ) : ?>
					<small data-ampdp-bar-label><?php echo $variable ? esc_html__( 'قیمت از', 'aromist-mobile-pdp' ) : esc_html__( 'قیمت', 'aromist-mobile-pdp' ); ?></small>
					<strong data-ampdp-bar-price></strong><span class="ampdp-cur"><?php esc_html_e( 'تومان', 'aromist-mobile-pdp' ); ?></span>
				<?php else : ?>
					<small><?php esc_html_e( 'ناموجود', 'aromist-mobile-pdp' ); ?></small>
					<strong class="ampdp-bar__oos"><?php esc_html_e( 'این عطر فعلاً موجود نیست', 'aromist-mobile-pdp' ); ?></strong>
				<?php endif; ?>
			</div>
			<?php if ( $in_stock ) : ?>
				<button type="button" class="ampdp-btn" data-ampdp-buy data-product_name="<?php echo esc_attr( $product->get_name() ); ?>">
					<?php echo self::icon( 'bag' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php echo $variable ? esc_html__( 'انتخاب حجم و خرید', 'aromist-mobile-pdp' ) : esc_html__( 'افزودن به سبد', 'aromist-mobile-pdp' ); ?></span>
				</button>
			<?php else : ?>
				<button type="button" class="ampdp-btn ampdp-btn--soft" data-ampdp-notify>
					<?php echo self::icon( 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php esc_html_e( 'موجود شد خبرم کن', 'aromist-mobile-pdp' ); ?></span>
				</button>
			<?php endif; ?>
		</div>

		<?php if ( $variable && $in_stock ) : ?>
			<section class="ampdp-sheet" id="ampdp-sheet" role="dialog" aria-modal="true" aria-labelledby="ampdp-sheet-title" hidden>
				<div class="ampdp-sheet__handle" aria-hidden="true"><i></i></div>
				<div class="ampdp-sheet__head">
					<?php if ( $thumb ) : ?>
						<img src="<?php echo esc_url( $thumb ); ?>" alt="" width="56" height="56">
					<?php endif; ?>
					<div>
						<b id="ampdp-sheet-title"><?php echo esc_html( $product->get_name() ); ?></b>
						<small><?php esc_html_e( 'حجم را انتخاب کنید', 'aromist-mobile-pdp' ); ?></small>
					</div>
					<button type="button" class="ampdp-ib" data-ampdp-close aria-label="<?php esc_attr_e( 'بستن', 'aromist-mobile-pdp' ); ?>"><?php echo self::icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				</div>
				<div class="ampdp-sheet__body">
					<div class="ampdp-opts" role="radiogroup" aria-labelledby="ampdp-sheet-title"></div>
					<p class="ampdp-sheet__msg" role="alert" hidden></p>
				</div>
				<div class="ampdp-sheet__snapp" hidden>
					<img src="" alt="">
					<span><?php esc_html_e( 'قسط اول با اسنپ‌پی', 'aromist-mobile-pdp' ); ?></span>
					<b data-ampdp-sheet-first></b>
				</div>
				<div class="ampdp-sheet__foot">
					<div class="ampdp-qty" role="group" aria-label="<?php esc_attr_e( 'تعداد', 'aromist-mobile-pdp' ); ?>">
						<button type="button" data-ampdp-step="1" aria-label="<?php esc_attr_e( 'یکی بیشتر', 'aromist-mobile-pdp' ); ?>"><?php echo self::icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						<output>۱</output>
						<button type="button" data-ampdp-step="-1" aria-label="<?php esc_attr_e( 'یکی کمتر', 'aromist-mobile-pdp' ); ?>" disabled><?php echo self::icon( 'minus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					</div>
					<button type="button" class="ampdp-btn ampdp-btn--block" data-ampdp-add>
						<span><?php esc_html_e( 'افزودن به سبد خرید', 'aromist-mobile-pdp' ); ?></span>
						<small data-ampdp-sheet-total></small>
					</button>
				</div>
			</section>
		<?php endif; ?>
		<?php
	}

	/** The ☰ menu when a WordPress menu is chosen in the settings (else ☰ opens the site's own sheet). */
	private function print_menu_sheet(): void {
		$menu = 'site' !== AMPDP_Settings::get( 'menu' ) ? $this->menu_items() : array();
		if ( ! $menu ) {
			return;
		}
		?>
			<section class="ampdp-sheet ampdp-menu" id="ampdp-menu" role="dialog" aria-modal="true" aria-labelledby="ampdp-menu-title" hidden>
				<div class="ampdp-sheet__handle" aria-hidden="true"><i></i></div>
				<div class="ampdp-sheet__head">
					<div><b id="ampdp-menu-title"><?php esc_html_e( 'منو', 'aromist-mobile-pdp' ); ?></b></div>
					<button type="button" class="ampdp-ib" data-ampdp-close aria-label="<?php esc_attr_e( 'بستن', 'aromist-mobile-pdp' ); ?>"><?php echo self::icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				</div>
				<nav class="ampdp-sheet__body ampdp-menu__list">
					<?php foreach ( $menu as $node ) : ?>
						<?php if ( $node['children'] ) : ?>
							<details class="ampdp-menu__grp">
								<summary><?php echo esc_html( $node['item']->title ); ?><?php echo self::icon( 'down' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></summary>
								<a class="ampdp-menu__sub" href="<?php echo esc_url( $node['item']->url ); ?>"><?php esc_html_e( 'همه', 'aromist-mobile-pdp' ); ?> <?php echo esc_html( $node['item']->title ); ?></a>
								<?php foreach ( $node['children'] as $child ) : ?>
									<a class="ampdp-menu__sub" href="<?php echo esc_url( $child->url ); ?>"><?php echo esc_html( $child->title ); ?></a>
								<?php endforeach; ?>
							</details>
						<?php else : ?>
							<a class="ampdp-menu__item" href="<?php echo esc_url( $node['item']->url ); ?>"><?php echo esc_html( $node['item']->title ); ?></a>
						<?php endif; ?>
					<?php endforeach; ?>
				</nav>
			</section>
		<?php
	}

	/*
	|--------------------------------------------------------------------------
	| AJAX
	|--------------------------------------------------------------------------
	*/

	/**
	 * Same checks as WooCommerce's own form submit (WC_Form_Handler), so the volume-stock
	 * rules and any other `woocommerce_add_to_cart_validation` filter see the variation id;
	 * WooCommerce's `add_to_cart` endpoint passes only three arguments to that filter.
	 * Success answers with the refreshed cart fragments, like that endpoint.
	 */
	public function ajax_add(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- a public cart action, like WooCommerce's own add_to_cart endpoint.
		$product_id   = absint( wp_unslash( $_POST['product_id'] ?? 0 ) );
		$variation_id = absint( wp_unslash( $_POST['variation_id'] ?? 0 ) );
		$quantity     = max( 1, (int) wc_stock_amount( wp_unslash( $_POST['quantity'] ?? 1 ) ) );
		$posted       = isset( $_POST['attributes'] ) && is_array( $_POST['attributes'] ) ? wc_clean( wp_unslash( $_POST['attributes'] ) ) : array();
		// phpcs:enable

		$product = wc_get_product( $product_id );
		if ( ! $product || 'publish' !== get_post_status( $product_id ) ) {
			$this->fail( __( 'این محصول پیدا نشد.', 'aromist-mobile-pdp' ) );
		}

		if ( $product->is_type( 'variable' ) ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->is_type( 'variation' ) || $variation->get_parent_id() !== $product_id ) {
				$this->fail( __( 'حجم را انتخاب کنید.', 'aromist-mobile-pdp' ) );
			}
			// Fixed attributes come from the variation; an «any» attribute from what was posted.
			$attributes = wc_get_product_variation_attributes( $variation_id );
			foreach ( $attributes as $key => $value ) {
				if ( '' === $value && isset( $posted[ $key ] ) ) {
					$attributes[ $key ] = (string) $posted[ $key ];
				}
			}
			$passed = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $attributes );
			$added  = $passed && false !== WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $attributes );
		} elseif ( $product->is_type( 'simple' ) ) {
			$passed = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
			$added  = $passed && false !== WC()->cart->add_to_cart( $product_id, $quantity );
		} else {
			$this->fail( __( 'این محصول را از فرم خود صفحه به سبد اضافه کنید.', 'aromist-mobile-pdp' ) );
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
			$message = $notices ? implode( ' ', $notices ) : __( 'افزودن به سبد انجام نشد. دوباره امتحان کنید.', 'aromist-mobile-pdp' );
		}
		wp_send_json(
			array(
				'error'   => true,
				'message' => $message,
			)
		);
	}

	/**
	 * Search suggestions: up to 6 visible products whose name (or English name) matches.
	 * Public, read-only — the same products the site's search page lists.
	 */
	public function ajax_search(): void {
		$q = trim( wc_clean( wp_unslash( $_GET['q'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$q = str_replace( array( 'ي', 'ك' ), array( 'ی', 'ک' ), $q );
		if ( mb_strlen( $q ) < 2 ) {
			wp_send_json( array( 'items' => array() ) );
		}
		$hidden = wc_get_product_visibility_term_ids();
		$base   = array(
			'post_type'        => 'product',
			'post_status'      => 'publish',
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'term_taxonomy_id',
					'terms'    => array( (int) ( $hidden['exclude-from-search'] ?? 0 ) ),
					'operator' => 'NOT IN',
				),
			),
		);
		$ids    = get_posts( $base + array( 's' => $q, 'posts_per_page' => 6 ) );
		if ( count( $ids ) < 6 ) {
			$ids = array_merge(
				$ids,
				get_posts(
					$base + array(
						'posts_per_page' => 6 - count( $ids ),
						'post__not_in'   => $ids ? $ids : array( 0 ),
						'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'     => 'english_title',
								'value'   => $q,
								'compare' => 'LIKE',
							),
						),
					)
				)
			);
		}
		$items = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->is_visible() ) {
				continue;
			}
			$image   = (string) wp_get_attachment_image_url( (int) $product->get_image_id(), 'woocommerce_gallery_thumbnail' );
			$items[] = array(
				'name'    => $product->get_name(),
				'en'      => (string) get_post_meta( $id, 'english_title', true ),
				'url'     => (string) get_permalink( $id ),
				'img'     => $image ? $image : wc_placeholder_img_src( 'woocommerce_gallery_thumbnail' ),
				'price'   => (float) ( $product->is_type( 'variable' ) ? $product->get_variation_price( 'min', true ) : wc_get_price_to_display( $product ) ),
				'from'    => $product->is_type( 'variable' ),
				'inStock' => $product->is_in_stock(),
			);
		}
		wp_send_json( array( 'items' => $items ) );
	}

	/** A change must reach visitors whose pages LiteSpeed already cached. */
	public function purge_page_cache(): void {
		do_action( 'litespeed_purge_all' );
	}
}
