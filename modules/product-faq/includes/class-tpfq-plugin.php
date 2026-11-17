<?php
/**
 * Product FAQ: data, markup, shortcode, schema and assets.
 *
 * A product shows its own questions (`_tpfq_items` meta), the default questions from
 * «محصولات ← سوالات متداول» (option `tpfq_settings`), both, or none — see source().
 * The front end needs no script: the accordion is native <details>, and a shared `name`
 * makes it close the other answers when one opens. Nothing at all is printed for a
 * product without questions, so the Elementor widget can sit in the single product
 * template for every product.
 *
 * @package TechpinoProductFaq
 */

defined( 'ABSPATH' ) || exit;

final class TPFQ_Plugin {

	/** A product's own questions: array of q/a pairs. */
	public const META = '_tpfq_items';

	/** A product's choice: '' (automatic) | default | custom | both | none. See modes(). */
	public const MODE_META = '_tpfq_mode';

	/** Default questions, which products get them, and the «still have a question?» box. */
	public const OPTION = 'tpfq_settings';

	/** @var TPFQ_Plugin|null */
	private static $instance = null;

	/** @var array|null */
	private static $settings = null;

	/** @var int[]|null Categories whose products get the default questions, with their subcategories. */
	private static $scope_cats = null;

	/** @var array<int,array> questions() by product, for this request. */
	private static $resolved = array();

	/** @var int Sections printed on this page (for unique accordion names). */
	private $sections = 0;

	/** @var array<int,array> Questions to describe in the page's FAQPage schema, by product. */
	private $schema = array();

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_for_page' ), 20 );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
		add_action( 'wp_footer', array( $this, 'print_schema' ), 20 );
		// Pages LiteSpeed already cached must pick up new default questions.
		add_action( 'add_option_' . self::OPTION, array( $this, 'purge_page_cache' ) );
		add_action( 'update_option_' . self::OPTION, array( $this, 'purge_page_cache' ) );

		if ( is_admin() ) {
			require_once TPFQ_PATH . 'includes/class-tpfq-admin.php';
			new TPFQ_Admin();
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Data
	|--------------------------------------------------------------------------
	*/

	/**
	 * @return array<string,string> mode => label
	 */
	public static function modes(): array {
		return array(
			''        => __( 'خودکار (طبق تنظیمات کلی)', 'techpino-product-faq' ),
			'default' => __( 'سوالات پیش‌فرض', 'techpino-product-faq' ),
			'custom'  => __( 'فقط سوالات اختصاصی این محصول', 'techpino-product-faq' ),
			'both'    => __( 'سوالات اختصاصی + پیش‌فرض', 'techpino-product-faq' ),
			'none'    => __( 'نمایش داده نشود', 'techpino-product-faq' ),
		);
	}

	public static function setting_defaults(): array {
		return array(
			'items'      => array(),
			'scope'      => 'all', // all | cats | manual (only products set to «سوالات پیش‌فرض»).
			'cats'       => array(),
			'cta_title'  => __( 'جواب سوالت رو پیدا نکردی؟', 'techpino-product-faq' ),
			'cta_text'   => __( 'کارشناس‌های ما با کمال میل راهنماییت می‌کنن.', 'techpino-product-faq' ),
			'cta_button' => __( 'پرسیدن از کارشناس', 'techpino-product-faq' ),
			'cta_url'    => '',
		);
	}

	public static function settings(): array {
		if ( null === self::$settings ) {
			$saved          = get_option( self::OPTION, array() );
			self::$settings = array_merge( self::setting_defaults(), is_array( $saved ) ? $saved : array() );
		}
		return self::$settings;
	}

	public static function flush(): void {
		self::$settings   = null;
		self::$scope_cats = null;
		self::$resolved   = array();
	}

	/**
	 * Valid q/a pairs out of a stored list.
	 *
	 * @param mixed $raw
	 * @return array<int,array{q:string,a:string}>
	 */
	public static function clean( $raw ): array {
		$items = array();
		if ( ! is_array( $raw ) ) {
			return $items;
		}
		foreach ( $raw as $row ) {
			if ( is_array( $row ) && isset( $row['q'] ) && '' !== trim( (string) $row['q'] ) ) {
				$items[] = array(
					'q' => (string) $row['q'],
					'a' => isset( $row['a'] ) ? (string) $row['a'] : '',
				);
			}
		}
		return $items;
	}

	/** The product's own questions. */
	public static function items( int $product_id ): array {
		return $product_id ? self::clean( get_post_meta( $product_id, self::META, true ) ) : array();
	}

	public static function default_items(): array {
		return self::clean( self::settings()['items'] );
	}

	public static function mode( int $product_id ): string {
		$mode = $product_id ? (string) get_post_meta( $product_id, self::MODE_META, true ) : '';
		return array_key_exists( $mode, self::modes() ) ? $mode : '';
	}

	/** Whether «خودکار» gives this product the default questions (settings → which products). */
	public static function in_scope( int $product_id ): bool {
		$settings = self::settings();
		if ( 'all' === $settings['scope'] ) {
			return true;
		}
		if ( 'cats' !== $settings['scope'] ) {
			return false;
		}
		if ( null === self::$scope_cats ) {
			self::$scope_cats = array();
			foreach ( array_map( 'absint', (array) $settings['cats'] ) as $cat ) {
				$children         = $cat ? get_term_children( $cat, 'product_cat' ) : array();
				self::$scope_cats = array_merge( self::$scope_cats, array( $cat ), is_array( $children ) ? array_map( 'intval', $children ) : array() );
			}
			self::$scope_cats = array_values( array_unique( array_filter( self::$scope_cats ) ) );
		}
		return self::$scope_cats && has_term( self::$scope_cats, 'product_cat', $product_id );
	}

	/**
	 * Which questions the product shows: custom | default | both | '' (none).
	 * «خودکار»: its own questions when it has some, otherwise the defaults if it is in their scope.
	 */
	public static function source( int $product_id ): string {
		if ( ! $product_id ) {
			return '';
		}
		$own      = (bool) self::items( $product_id );
		$defaults = (bool) self::default_items();
		switch ( self::mode( $product_id ) ) {
			case 'none':
				return '';
			case 'custom':
				return $own ? 'custom' : '';
			case 'default':
				return $defaults ? 'default' : '';
			case 'both':
				if ( $own && $defaults ) {
					return 'both';
				}
				return $own ? 'custom' : ( $defaults ? 'default' : '' );
		}
		if ( $own ) {
			return 'custom';
		}
		return $defaults && self::in_scope( $product_id ) ? 'default' : '';
	}

	/**
	 * The questions the product's page shows, with {product} replaced by its name.
	 *
	 * @return array<int,array{q:string,a:string}>
	 */
	public static function questions( int $product_id ): array {
		if ( isset( self::$resolved[ $product_id ] ) ) {
			return self::$resolved[ $product_id ];
		}
		$source = self::source( $product_id );
		$list   = array();
		if ( 'custom' === $source || 'both' === $source ) {
			$list = self::items( $product_id );
		}
		if ( 'default' === $source || 'both' === $source ) {
			$list = array_merge( $list, self::default_items() );
		}
		if ( $list ) {
			$product = wc_get_product( $product_id );
			$name    = $product ? $product->get_name() : '';
			foreach ( $list as &$item ) {
				$item['q'] = str_replace( '{product}', $name, $item['q'] );
				$item['a'] = str_replace( '{product}', $name, $item['a'] );
			}
			unset( $item );
		}
		self::$resolved[ $product_id ] = $list;
		return $list;
	}

	/**
	 * The product the page is about: the global product, the queried one, or the post in the loop
	 * (the Elementor editor previews a single product template through the latter).
	 */
	public static function current_product_id(): int {
		global $product;
		if ( $product instanceof WC_Product ) {
			return $product->get_id();
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			return (int) get_queried_object_id();
		}
		$post = get_post();
		return $post && 'product' === $post->post_type ? (int) $post->ID : 0;
	}

	public static function in_elementor_editor(): bool {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return false;
		}
		$elementor = \Elementor\Plugin::$instance;
		return ( $elementor->editor && $elementor->editor->is_edit_mode() )
			|| ( $elementor->preview && $elementor->preview->is_preview_mode() );
	}

	/**
	 * Shown in the Elementor editor when the previewed product has no questions, so the section can be styled.
	 */
	public static function sample_items(): array {
		return array(
			array(
				'q' => 'ماندگاری این عطر روی پوست چقدره؟',
				'a' => 'روی پوست معمولاً ۶ تا ۸ ساعت و روی لباس تا یک روز کامل می‌مونه. پوست چرب و مرطوب، عطر رو بیشتر نگه می‌داره.',
			),
			array(
				'q' => 'این عطر برای چه فصلی مناسب‌تره؟',
				'a' => 'به خاطر نت‌های گرم و پودری، بهترین عملکردش در پاییز و زمستون و شب‌های خنک بهاره.',
			),
			array(
				'q' => 'از کجا مطمئن بشم عطر اورجیناله؟',
				'a' => 'همه‌ی محصولات آرومیست با کد اصالت و فاکتور ارسال می‌شن و تا ۷ روز ضمانت بازگشت دارن.',
			),
			array(
				'q' => 'می‌تونم اول نمونه (دکانت) این عطر رو امتحان کنم؟',
				'a' => 'بله؛ از بخش حجم، گزینه‌های ۱۰ و ۲۰ میل رو انتخاب کن.',
			),
		);
	}

	public function purge_page_cache(): void {
		self::flush();
		do_action( 'litespeed_purge_all' );
	}

	/*
	|--------------------------------------------------------------------------
	| Assets
	|--------------------------------------------------------------------------
	*/

	public function register_assets(): void {
		wp_register_style( 'tpfq', TPFQ_URL . 'assets/css/faq.css', array(), TPFQ_VERSION );
	}

	public function enqueue(): void {
		if ( ! wp_style_is( 'tpfq', 'registered' ) ) {
			$this->register_assets();
		}
		wp_enqueue_style( 'tpfq' );
	}

	/**
	 * Loads the stylesheet in <head> on pages that will show a section, so it never arrives late.
	 * Anywhere else the widget or shortcode still enqueues it while rendering.
	 */
	public function enqueue_for_page(): void {
		if ( function_exists( 'is_product' ) && is_product() && self::questions( (int) get_queried_object_id() ) ) {
			$this->enqueue();
			return;
		}
		$post = is_singular() ? get_post() : null;
		if ( $post && has_shortcode( (string) $post->post_content, 'tp_product_faq' ) ) {
			$this->enqueue();
		}
	}

	public function register_widget( $widgets_manager ): void {
		require_once TPFQ_PATH . 'includes/class-tpfq-widget.php';
		$widgets_manager->register( new TPFQ_Widget() );
	}

	/*
	|--------------------------------------------------------------------------
	| Shortcode
	|--------------------------------------------------------------------------
	*/

	public function register_shortcodes(): void {
		add_shortcode( 'tp_product_faq', array( $this, 'shortcode' ) );
	}

	/**
	 * [tp_product_faq id="" title="سوالات متداول" subtitle="" open="first|none" single="yes" numbers="yes"
	 *  card="yes" schema="yes" cta="auto|yes|no" cta_title="" cta_text="" cta_button="" cta_url=""]
	 *
	 * Empty cta_* fall back to the settings; cta="auto" shows the box when there is a link.
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'         => '',
				'title'      => __( 'سوالات متداول', 'techpino-product-faq' ),
				'subtitle'   => '',
				'open'       => 'first',
				'single'     => 'yes',
				'numbers'    => 'yes',
				'card'       => 'yes',
				'schema'     => 'yes',
				'cta'        => 'auto',
				'cta_title'  => '',
				'cta_text'   => '',
				'cta_button' => '',
				'cta_url'    => '',
			),
			$atts,
			'tp_product_faq'
		);

		$url = '' !== trim( $atts['cta_url'] ) ? $atts['cta_url'] : self::settings()['cta_url'];

		return $this->render(
			array(
				'product'    => absint( $atts['id'] ),
				'title'      => $atts['title'],
				'subtitle'   => $atts['subtitle'],
				'open'       => $atts['open'],
				'single'     => 'yes' === $atts['single'],
				'numbers'    => 'yes' === $atts['numbers'],
				'card'       => 'yes' === $atts['card'],
				'schema'     => 'yes' === $atts['schema'],
				'cta'        => 'yes' === $atts['cta'] || ( 'auto' === $atts['cta'] && '' !== trim( (string) $url ) ),
				'cta_title'  => $atts['cta_title'],
				'cta_text'   => $atts['cta_text'],
				'cta_button' => $atts['cta_button'],
				'cta_url'    => $atts['cta_url'],
			)
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Markup
	|--------------------------------------------------------------------------
	*/

	public static function fa_digits( string $text ): string {
		return strtr( $text, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}

	private function icon( string $name ): string {
		$paths = array(
			'chat' => '<path d="M20 11.5a7.5 7.5 0 0 1-10.9 6.7L4.5 19.5l1.3-4.3A7.5 7.5 0 1 1 20 11.5Z"/><path d="M10 9.6a2.1 2.1 0 1 1 2.9 1.9c-.6.3-.9.8-.9 1.4v.3"/><path d="M12 15.6h.01"/>',
			'help' => '<path d="M4 13.5v-1.8a8 8 0 0 1 16 0v1.8"/><rect x="3" y="13" width="4.5" height="6" rx="1.6"/><rect x="16.5" y="13" width="4.5" height="6" rx="1.6"/><path d="M19 19.2c0 1.2-1.6 2.3-5 2.3"/>',
		);
		return '<svg class="tpfq-svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * The section, or '' when the product has no questions.
	 *
	 * @param array $args See the defaults below. `items` replaces the product's questions (editor samples).
	 *                    Empty cta_title / cta_text / cta_button / cta_url come from the settings.
	 */
	public function render( array $args ): string {
		$args = wp_parse_args(
			$args,
			array(
				'product'    => 0,
				'items'      => null,
				'title'      => __( 'سوالات متداول', 'techpino-product-faq' ),
				'title_tag'  => 'h2',
				'subtitle'   => '',
				'open'       => 'first',
				'single'     => true,
				'numbers'    => true,
				'card'       => true,
				'schema'     => true,
				'cta'        => false,
				'cta_title'  => '',
				'cta_text'   => '',
				'cta_button' => '',
				'cta_url'    => '',
				'cta_attrs'  => '',
				'class'      => '',
			)
		);

		$product_id = $args['product'] ? (int) $args['product'] : self::current_product_id();
		$items      = is_array( $args['items'] ) ? $args['items'] : self::questions( $product_id );
		if ( ! $items ) {
			return '';
		}

		$this->enqueue();
		++$this->sections;

		if ( $args['schema'] && ! is_array( $args['items'] ) && $product_id && ! self::in_elementor_editor() ) {
			$this->schema[ $product_id ] = $items;
		}

		$settings = self::settings();
		foreach ( array( 'cta_title', 'cta_text', 'cta_button', 'cta_url' ) as $key ) {
			if ( '' === trim( (string) $args[ $key ] ) ) {
				$args[ $key ] = (string) $settings[ $key ];
			}
		}

		$product  = $product_id ? wc_get_product( $product_id ) : null;
		$title    = str_replace( '{product}', $product ? $product->get_name() : '', (string) $args['title'] );
		$tag      = in_array( $args['title_tag'], array( 'h2', 'h3', 'h4', 'h5', 'div', 'p' ), true ) ? $args['title_tag'] : 'h2';
		$group    = $args['single'] ? 'tpfq-' . $product_id . '-' . $this->sections : '';
		$show_cta = $args['cta'] && ( '' !== trim( (string) $args['cta_title'] ) || '' !== trim( (string) $args['cta_url'] ) );
		$classes  = array( 'tpfq', $args['card'] ? 'tpfq--card' : 'tpfq--plain' );
		if ( ! $args['numbers'] ) {
			$classes[] = 'tpfq--no-num';
		}
		if ( '' !== $args['class'] ) {
			$classes[] = sanitize_html_class( $args['class'] );
		}

		ob_start();
		?>
		<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo '' !== trim( $title ) ? ' aria-labelledby="tpfq-title-' . esc_attr( (string) $this->sections ) . '"' : ''; ?>>
			<?php if ( '' !== trim( $title ) || '' !== trim( (string) $args['subtitle'] ) ) : ?>
				<header class="tpfq-head">
					<?php if ( '' !== trim( $title ) ) : ?>
						<<?php echo esc_html( $tag ); ?> class="tpfq-title" id="tpfq-title-<?php echo esc_attr( (string) $this->sections ); ?>"><?php echo esc_html( $title ); ?></<?php echo esc_html( $tag ); ?>>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $args['subtitle'] ) ) : ?>
						<p class="tpfq-sub"><?php echo esc_html( $args['subtitle'] ); ?></p>
					<?php endif; ?>
				</header>
			<?php endif; ?>

			<div class="tpfq-box">
				<div class="tpfq-list">
					<?php foreach ( $items as $i => $item ) : ?>
						<details class="tpfq-item"<?php echo '' !== $group ? ' name="' . esc_attr( $group ) . '"' : ''; ?><?php echo 0 === $i && 'first' === $args['open'] ? ' open' : ''; ?>>
							<summary class="tpfq-q">
								<?php if ( $args['numbers'] ) : ?>
									<span class="tpfq-num" aria-hidden="true"><?php echo esc_html( self::fa_digits( (string) ( $i + 1 ) ) ); ?></span>
								<?php endif; ?>
								<span class="tpfq-q__text"><?php echo esc_html( $item['q'] ); ?></span>
								<span class="tpfq-toggle" aria-hidden="true"></span>
							</summary>
							<div class="tpfq-a"><?php echo wpautop( wp_kses_post( $item['a'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd. ?></div>
						</details>
					<?php endforeach; ?>
				</div>

				<?php if ( $show_cta ) : ?>
					<div class="tpfq-cta">
						<span class="tpfq-cta__icon"><?php echo $this->icon( 'chat' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div class="tpfq-cta__body">
							<?php if ( '' !== trim( (string) $args['cta_title'] ) ) : ?>
								<p class="tpfq-cta__title"><?php echo esc_html( $args['cta_title'] ); ?></p>
							<?php endif; ?>
							<?php if ( '' !== trim( (string) $args['cta_text'] ) ) : ?>
								<p class="tpfq-cta__text"><?php echo esc_html( $args['cta_text'] ); ?></p>
							<?php endif; ?>
						</div>
						<?php if ( '' !== trim( (string) $args['cta_url'] ) && '' !== trim( (string) $args['cta_button'] ) ) : ?>
							<a class="tpfq-cta__btn" href="<?php echo esc_url( $args['cta_url'], self::url_protocols() ); ?>"<?php echo $args['cta_attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by Elementor's add_link_attributes(). ?>><?php echo $this->icon( 'help' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $args['cta_button'] ); ?></span></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** Link schemes the «still have a question?» button may use. */
	public static function url_protocols(): array {
		return array( 'http', 'https', 'tel', 'mailto', 'sms', 'whatsapp', 'tg' );
	}

	/*
	|--------------------------------------------------------------------------
	| Schema
	|--------------------------------------------------------------------------
	*/

	/**
	 * One FAQPage block for every section printed on the page.
	 */
	public function print_schema(): void {
		if ( ! $this->schema || ! apply_filters( 'tpfq_print_schema', true ) ) {
			return;
		}
		$questions = array();
		foreach ( $this->schema as $items ) {
			foreach ( $items as $item ) {
				$answer = trim( wp_strip_all_tags( $item['a'] ) );
				if ( '' === $answer ) {
					continue;
				}
				$questions[] = array(
					'@type'          => 'Question',
					'name'           => $item['q'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $answer,
					),
				);
			}
		}
		if ( ! $questions ) {
			return;
		}
		$data = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $questions,
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . "</script>\n";
	}
}
