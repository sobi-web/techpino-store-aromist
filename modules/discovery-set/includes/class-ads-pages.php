<?php
/**
 * The discovery-set section of the shop:
 *   - [aromist_discovery_sets]  ready-made sets: put it in the Elementor archive template of the
 *                               product category «دیسکاوری ست» (it lists the sets of the category
 *                               being viewed — مردانه / زنانه / all) or in any page
 *   - [aromist_set_builder]     «ساخت دیسکاوری ست» (customer-built sets)
 *   The top of those pages is designed in Elementor; the shortcodes only output their content.
 *   - the plugin's own single page for ready-made sets (instead of the theme's product template)
 *
 * Templates can be overridden in the theme: yourtheme/woocommerce/discovery-sets/{archive,builder,single}.php
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pages.
 */
final class ADS_Pages {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_shortcode( 'aromist_discovery_sets', array( $this, 'archive_shortcode' ) );
		add_shortcode( 'aromist_set_builder', array( $this, 'builder_shortcode' ) );
		add_filter( 'template_include', array( $this, 'single_template' ), 999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 20 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_filter( 'ads_custom_product_redirect', array( $this, 'custom_product_redirect' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Pages and URLs
	|--------------------------------------------------------------------------
	*/

	/**
	 * Create the two pages when missing (as drafts: publish them when the section is ready).
	 */
	public static function ensure_pages() {
		$pages = array(
			'archive_page_id' => array(
				'title'   => __( 'دیسکاوری ست‌ها', 'aromist-discovery-set' ),
				'slug'    => 'discovery-sets',
				'content' => '[aromist_discovery_sets]',
			),
			'builder_page_id' => array(
				'title'   => __( 'ساخت دیسکاوری ست اختصاصی', 'aromist-discovery-set' ),
				'slug'    => 'build-discovery-set',
				'content' => '[aromist_set_builder]',
			),
		);
		$update = array();
		foreach ( $pages as $key => $page ) {
			$id = absint( ADS_Settings::get( $key ) );
			if ( $id && 'page' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'draft',
					'post_title'   => $page['title'],
					'post_name'    => $page['slug'],
					'post_content' => $page['content'],
				),
				true
			);
			if ( is_wp_error( $id ) || ! $id ) {
				continue;
			}
			if ( defined( 'ELEMENTOR_VERSION' ) ) {
				// «المنتور تمام‌عرض»: header and footer of the site, no page title, full width content.
				update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
			}
			$update[ $key ] = (int) $id;
		}
		if ( $update ) {
			ADS_Settings::update( $update );
			ADS_Permalinks::schedule_flush();
		}
	}

	/**
	 * URL of a page setting ('' when the page is missing or not published for visitors).
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private static function page_url( $key ) {
		$id = absint( ADS_Settings::get( $key ) );
		if ( ! $id || 'page' !== get_post_type( $id ) ) {
			return '';
		}
		if ( 'publish' !== get_post_status( $id ) && ! current_user_can( 'edit_post', $id ) ) {
			return '';
		}
		return (string) get_permalink( $id );
	}

	/**
	 * Where «دیسکاوری ست‌ها» links go (breadcrumb, «همه‌ی ست‌ها»): the archive of the product
	 * category «دیسکاوری ست» — designed in Elementor with [aromist_discovery_sets] in it — or, with
	 * the setting «لینک دیسکاوری ست‌ها» = page, the plugin's page. Filter `ads_archive_url`.
	 *
	 * @return string
	 */
	public static function archive_url() {
		$url = 'page' !== ADS_Settings::get( 'archive_link' ) ? self::category_url() : '';
		if ( '' === $url ) {
			$url = self::page_url( 'archive_page_id' );
		}

		/**
		 * URL of «دیسکاوری ست‌ها».
		 *
		 * @param string $url URL.
		 */
		return (string) apply_filters( 'ads_archive_url', $url );
	}

	/**
	 * Archive URL of a product category (default: «دیسکاوری ست»).
	 *
	 * @param WP_Term|null $term Category.
	 * @return string
	 */
	public static function category_url( $term = null ) {
		if ( ! $term instanceof WP_Term ) {
			$root = ADS_Install::category_id( false );
			$term = $root ? get_term( $root, 'product_cat' ) : null;
		}
		$link = $term instanceof WP_Term ? get_term_link( $term ) : '';
		return is_string( $link ) ? $link : '';
	}

	/**
	 * Is this the archive of «دیسکاوری ست» or one of its sub-categories (مردانه، زنانه…)?
	 *
	 * @return bool
	 */
	public static function is_sets_category() {
		if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
			return false;
		}
		$term = get_queried_object();
		$root = ADS_Install::category_id( false );
		return $term instanceof WP_Term && $root && ( (int) $term->term_id === $root || term_is_ancestor_of( $root, $term, 'product_cat' ) );
	}

	/**
	 * Category whose sets [aromist_discovery_sets] lists: its `category` attribute (slug, name or
	 * id), else the product category being viewed (the shortcode in an Elementor archive template
	 * follows WooCommerce's query), else none (= all sets).
	 *
	 * @param string $given `category` attribute.
	 * @return WP_Term|null|false False = the given category does not exist.
	 */
	public static function sets_term( $given = '' ) {
		$given = trim( (string) $given );
		if ( '' !== $given ) {
			$term = ctype_digit( $given ) ? get_term( (int) $given, 'product_cat' ) : get_term_by( 'slug', sanitize_title( $given ), 'product_cat' );
			if ( ! $term instanceof WP_Term ) {
				$term = get_term_by( 'name', $given, 'product_cat' );
			}
			return $term instanceof WP_Term ? $term : false;
		}
		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$term = get_queried_object();
			return $term instanceof WP_Term ? $term : null;
		}
		return null;
	}

	/**
	 * Sub-category of «دیسکاوری ست» a set is in (e.g. «دیسکاوری ست مردانه»), if any.
	 *
	 * @param WC_Product $product Set.
	 * @return WP_Term|null
	 */
	public static function set_subcategory( $product ) {
		$root = ADS_Install::category_id( false );
		if ( ! $root ) {
			return null;
		}
		foreach ( $product->get_category_ids() as $term_id ) {
			$term = get_term( $term_id, 'product_cat' );
			if ( $term instanceof WP_Term && (int) $term->term_id !== $root && term_is_ancestor_of( $root, $term, 'product_cat' ) ) {
				return $term;
			}
		}
		return null;
	}

	/**
	 * Builder page URL, optionally prefilled with a set's perfumes.
	 *
	 * @param int[] $components Perfume ids.
	 * @return string
	 */
	public static function builder_url( array $components = array() ) {
		$url = self::page_url( 'builder_page_id' );
		if ( '' !== $url && $components ) {
			$url = add_query_arg( 'set', implode( '-', array_map( 'absint', $components ) ), $url );
		}
		return $url;
	}

	/**
	 * Visitors of the «ست سفارشی» product go to the builder.
	 *
	 * @param string $url Default (shop).
	 * @return string
	 */
	public function custom_product_redirect( $url ) {
		$builder = self::builder_url();
		return '' !== $builder ? $builder : $url;
	}

	/**
	 * Is this request the single page of a ready-made set (with the plugin's template)?
	 *
	 * @return bool
	 */
	public static function is_single_set() {
		if ( 'yes' !== ADS_Settings::get( 'own_template' ) || ! is_singular( 'product' ) ) {
			return false;
		}
		$product = wc_get_product( get_queried_object_id() );
		return $product instanceof WC_Product_Discovery_Set && ! $product->is_custom();
	}

	/**
	 * Is the current page one of the section's pages (or contains its shortcodes)?
	 *
	 * @return string '' | 'archive' | 'builder'
	 */
	private static function current_section_page() {
		if ( ! is_singular() ) {
			return '';
		}
		$id = get_queried_object_id();
		if ( $id && $id === absint( ADS_Settings::get( 'archive_page_id' ) ) ) {
			return 'archive';
		}
		if ( $id && $id === absint( ADS_Settings::get( 'builder_page_id' ) ) ) {
			return 'builder';
		}
		$post = get_post( $id );
		if ( ! $post ) {
			return '';
		}
		// Pages built with Elementor keep their shortcode widgets in `_elementor_data`.
		$text = $post->post_content . ' ' . (string) get_post_meta( $post->ID, '_elementor_data', true );
		if ( false !== strpos( $text, '[aromist_set_builder' ) ) {
			return 'builder';
		}
		if ( false !== strpos( $text, '[aromist_discovery_sets' ) ) {
			return 'archive';
		}
		return '';
	}

	/*
	|--------------------------------------------------------------------------
	| Assets / body class / template
	|--------------------------------------------------------------------------
	*/

	/**
	 * Register / enqueue CSS and JS.
	 */
	public function assets() {
		wp_register_style( 'ads-sets', ADS_URL . 'assets/css/sets.css', array(), ads_asset_ver( 'assets/css/sets.css' ) );
		wp_register_script( 'ads-add', ADS_URL . 'assets/js/add-to-cart.js', array( 'jquery' ), ads_asset_ver( 'assets/js/add-to-cart.js' ), true );
		wp_register_script( 'ads-perfume', ADS_URL . 'assets/js/perfume.js', array(), ads_asset_ver( 'assets/js/perfume.js' ), true );
		wp_register_script( 'ads-single', ADS_URL . 'assets/js/single.js', array( 'ads-add', 'ads-perfume' ), ads_asset_ver( 'assets/js/single.js' ), true );
		wp_register_script( 'ads-builder', ADS_URL . 'assets/js/builder.js', array( 'ads-add', 'ads-perfume' ), ads_asset_ver( 'assets/js/builder.js' ), true );
		wp_localize_script(
			'ads-add',
			'adsCart',
			array(
				'ajax'     => WC_AJAX::get_endpoint( 'ads_add_to_cart' ),
				'cart'     => wc_get_cart_url(),
				'checkout' => wc_get_checkout_url(),
				'error'    => __( 'افزودن به سبد انجام نشد. دوباره تلاش کنید.', 'aromist-discovery-set' ),
			)
		);

		$section = self::current_section_page();
		if ( self::is_single_set() || $section || self::is_sets_category() ) {
			wp_enqueue_style( 'ads-sets' );
		}
		if ( self::is_single_set() ) {
			wp_enqueue_script( 'ads-single' );
		}
		if ( 'builder' === $section ) {
			wp_enqueue_script( 'ads-builder' );
		}
	}

	/**
	 * Body classes of the section.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public function body_class( $classes ) {
		if ( self::is_single_set() ) {
			$classes[] = 'ads-single-set';
		}
		$section = self::current_section_page();
		if ( $section ) {
			$classes[] = 'ads-section-' . $section;
		}
		return $classes;
	}

	/**
	 * The plugin's single page for ready-made sets.
	 *
	 * @param string $template Template.
	 * @return string
	 */
	public function single_template( $template ) {
		if ( ! self::is_single_set() ) {
			return $template;
		}
		$own = wc_locate_template( 'discovery-sets/single.php', '', ADS_PATH . 'templates/' );
		return $own && file_exists( $own ) ? $own : $template;
	}

	/*
	|--------------------------------------------------------------------------
	| Shortcodes
	|--------------------------------------------------------------------------
	*/

	/**
	 * Load a section template into a string.
	 *
	 * @param string $name Template name.
	 * @param array  $args Variables.
	 * @return string
	 */
	public static function render( $name, array $args ) {
		ob_start();
		wc_get_template( 'discovery-sets/' . $name . '.php', $args, '', ADS_PATH . 'templates/' );
		return (string) ob_get_clean();
	}

	/**
	 * [aromist_discovery_sets category="" columns="" limit="" builder_card="yes" hero="no"] —
	 * ready-made sets as cards (horizontal cards on phones).
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function archive_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'category'     => '',    // Slug / name / id; empty = the category being viewed, or all sets.
				'columns'      => '',    // 1–4 on wide screens; empty = 3 (4 when there is room).
				'limit'        => '',    // Most sets shown; empty = all.
				'builder_card' => 'yes', // «ست خودت را بساز» card after the sets.
				'hero'         => 'no',  // The plugin's own intro on top (the page's top is designed in Elementor).
			),
			$atts,
			'aromist_discovery_sets'
		);
		wp_enqueue_style( 'ads-sets' );

		$term  = self::sets_term( $atts['category'] );
		$limit = '' === trim( (string) $atts['limit'] ) ? -1 : max( 1, absint( $atts['limit'] ) );
		return self::render(
			'archive',
			array(
				'sets'         => false === $term ? array() : array_map( array( __CLASS__, 'set_card' ), self::preset_products( array(), $limit, $term ) ),
				'builder_url'  => self::builder_url(),
				'term'         => $term ? $term : null,
				'columns'      => min( 4, absint( $atts['columns'] ) ),
				'builder_card' => self::is_yes( $atts['builder_card'] ),
				'hero'         => self::is_yes( $atts['hero'] ),
			)
		);
	}

	/**
	 * A yes / no shortcode attribute.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_yes( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( 'yes', 'true', '1', 'on', 'بله' ), true );
	}

	/**
	 * [aromist_set_builder hero="no"] — build your own set.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function builder_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'hero' => 'no', // The plugin's own «ست خودت را بساز» intro on top.
			),
			$atts,
			'aromist_set_builder'
		);
		wp_enqueue_style( 'ads-sets' );
		wp_enqueue_script( 'ads-builder' );
		return self::render( 'builder', array_merge( self::builder_data(), array( 'hero' => self::is_yes( $atts['hero'] ) ) ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Data
	|--------------------------------------------------------------------------
	*/

	/**
	 * Published ready-made sets.
	 *
	 * @param int[]        $exclude Ids to leave out.
	 * @param int          $limit   Max (-1 = all).
	 * @param WP_Term|null $term    Only sets in this product category (or its sub-categories).
	 * @return WC_Product_Discovery_Set[]
	 */
	public static function preset_products( array $exclude = array(), $limit = -1, $term = null ) {
		$args = array(
			'type'    => 'discovery_set',
			'status'  => 'publish',
			'limit'   => $limit,
			'exclude' => array_merge( $exclude, array( absint( ADS_Settings::get( 'custom_product_id' ) ) ) ),
			'orderby' => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
		);
		if ( $term instanceof WP_Term ) {
			$args['category'] = array( $term->slug ); // Sub-categories included.
		}
		$products = wc_get_products( $args );
		return array_values(
			array_filter(
				$products,
				static function ( $product ) {
					return $product instanceof WC_Product_Discovery_Set && ! $product->is_custom() && '' === ADS_Set::preset_error( $product );
				}
			)
		);
	}

	/**
	 * Price of a set for display.
	 *
	 * @param int[]                         $components Perfume ids.
	 * @param WC_Product_Discovery_Set|null $product    Set.
	 * @return array|null [ price, regular, html, percent ]
	 */
	public static function price_data( array $components, $product = null ) {
		$volume = $product instanceof WC_Product_Discovery_Set ? $product->get_default_volume() : ADS_Settings::default_volume();
		$b      = ADS_Pricing::breakdown( $components, $volume, $product );
		if ( null === $b ) {
			return null;
		}
		$display = static function ( $amount ) use ( $product ) {
			return $product ? wc_get_price_to_display( $product, array( 'price' => $amount ) ) : $amount;
		};
		$price   = $display( $b['price'] );
		$regular = $display( $b['regular'] );
		return array(
			'price'   => $price,
			'regular' => $regular,
			'html'    => $price < $regular ? wc_format_sale_price( $regular, $price ) : wc_price( $price ),
			'percent' => $regular > 0 && $price < $regular ? (int) round( ( 1 - $price / $regular ) * 100 ) : 0,
		);
	}

	/**
	 * Card data of a ready-made set.
	 *
	 * @param WC_Product_Discovery_Set $product Set.
	 * @return array
	 */
	public static function set_card( $product ) {
		$components = $product->get_components();
		$volume     = $product->get_default_volume();
		$max        = ADS_Availability::max_sets( ADS_Set::draws( $components, $volume ) );
		return array(
			'product'   => $product,
			'url'       => $product->get_permalink(),
			'name'      => $product->get_name(),
			'image'     => $product->get_image( 'woocommerce_single', array( 'loading' => 'lazy' ) ),
			'perfumes'  => array_map( array( __CLASS__, 'perfume_card' ), $components ),
			'price'     => self::price_data( $components, $product ),
			'available' => null === $max || $max > 0,
			'label'     => sprintf( '%s × %s', number_format_i18n( count( $components ) ), ads_format_ml( $volume ) ),
		);
	}

	/**
	 * Card data of a perfume (inside a set, or in the builder): ADS_Perfume_Info + 10 ml price.
	 *
	 * @param int $id Perfume id.
	 * @return array
	 */
	public static function perfume_card( $id ) {
		$info  = ADS_Perfume_Info::get( $id );
		$price = ADS_Pricing::perfume_price( $id, ADS_Settings::default_volume() );

		$info['price']   = $price ? (float) $price['price'] : 0.0;
		$info['img_src'] = $info['thumb'];
		$info['img']     = $info['image_id']
			? wp_get_attachment_image( $info['image_id'], 'woocommerce_thumbnail', false, array( 'loading' => 'lazy', 'alt' => $info['name'] ) )
			: wc_placeholder_img( 'woocommerce_thumbnail' );
		return $info;
	}

	/**
	 * Everything the single page of a ready-made set needs.
	 *
	 * @param WC_Product_Discovery_Set $product Set.
	 * @return array
	 */
	public static function single_data( $product ) {
		$components = $product->get_components();
		$volume     = $product->get_default_volume();
		ADS_Perfume_Info::prime( $components );

		$max     = ADS_Availability::max_sets( ADS_Set::draws( $components, $volume ), WC()->cart ? ADS_Cart::needs() : array() );
		$builder = ADS_Pages::builder_url();

		// Main image first, then the gallery (chosen in wp-admin, or the perfumes' images).
		$images = array();
		if ( $product->get_image_id() ) {
			$images[] = (int) $product->get_image_id();
		}
		foreach ( $product->get_gallery_image_ids() as $image_id ) {
			if ( $image_id && ! in_array( (int) $image_id, $images, true ) ) {
				$images[] = (int) $image_id;
			}
		}

		// «این ست را شخصی کن»: the builder with the set's perfumes that are offered there.
		$personal = '';
		if ( '' !== $builder ) {
			$offered  = array_values( array_filter( $components, array( 'ADS_Set', 'in_builder' ) ) );
			$personal = self::builder_url( $offered );
		}

		// «ست‌های دیگر»: sets of the same sub-category (مردانه / زنانه) first.
		$sub    = self::set_subcategory( $product );
		$others = $sub ? self::preset_products( array( $product->get_id() ), 4, $sub ) : array();
		if ( count( $others ) < 4 ) {
			$seen = array( $product->get_id() );
			foreach ( $others as $set ) {
				$seen[] = $set->get_id();
			}
			$others = array_merge( $others, self::preset_products( $seen, 4 - count( $others ) ) );
		}

		// Breadcrumb: «دیسکاوری ست‌ها», then the set's sub-category.
		$crumbs  = array();
		$archive = self::archive_url();
		if ( '' !== $archive ) {
			$crumbs[] = array( __( 'دیسکاوری ست‌ها', 'aromist-discovery-set' ), $archive );
		}
		if ( $sub ) {
			$crumbs[] = array( $sub->name, self::category_url( $sub ) );
		}

		return array(
			'components' => $components,
			'volume'     => $volume,
			'perfumes'   => array_map( array( __CLASS__, 'perfume_card' ), $components ),
			'price'      => self::price_data( $components, $product ),
			'max'        => $max,
			'can_buy'    => $product->is_purchasable() && $product->is_in_stock() && ( null === $max || $max > 0 ),
			'images'     => $images,
			'archive'    => $archive,
			'crumbs'     => $crumbs,
			'personal'   => $personal,
			'others'     => array_map( array( __CLASS__, 'set_card' ), $others ),
			'rating'     => (float) $product->get_average_rating(),
			'reviews'    => (int) $product->get_review_count(),
		);
	}

	/**
	 * Reviews of a set: the shop's review section («استودیو نظرات نقطه») when it is active,
	 * otherwise WooCommerce's own reviews template.
	 *
	 * @param WC_Product $product Set.
	 * @return string
	 */
	public static function reviews_html( $product ) {
		if ( ! comments_open( $product->get_id() ) && ! $product->get_review_count() ) {
			return '';
		}
		$html = '';
		if ( shortcode_exists( 'ngt_reviews' ) ) {
			$html = do_shortcode( sprintf( '[ngt_reviews post_id="%d" context="product"]', $product->get_id() ) );
		}
		if ( '' === trim( wp_strip_all_tags( $html, true ) ) && false === strpos( $html, '<form' ) ) {
			ob_start();
			comments_template();
			$html = (string) ob_get_clean();
			$html = '' !== trim( $html ) ? '<div class="ads-wc-reviews woocommerce">' . $html . '</div>' : '';
		}

		/**
		 * Reviews HTML of a set page.
		 *
		 * @param string     $html    HTML.
		 * @param WC_Product $product Set.
		 */
		return (string) apply_filters( 'ads_reviews_html', $html, $product );
	}

	/**
	 * Why the builder shows no perfume (for shop managers only).
	 *
	 * @return array [ 'chosen' => int, 'problems' => [ name => reason ], 'admin_url' => string ]
	 */
	public static function builder_diagnosis() {
		$chosen = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => ADS_Set::META_IN_BUILDER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
				'meta_value'     => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
			)
		);
		$problems = array();
		$volume   = ADS_Settings::default_volume();
		foreach ( $chosen as $id ) {
			$error = ADS_Set::perfume_error( $id );
			if ( '' === $error ) {
				$price = ADS_Pricing::perfume_price( $id, $volume );
				if ( ! $price || $price['price'] <= 0 ) {
					/* translators: %s: volume */
					$error = sprintf( __( 'قیمت %s ندارد.', 'aromist-discovery-set' ), ads_format_ml( $volume ) );
				}
			}
			if ( '' !== $error ) {
				$problems[ ADS_Set::perfume_name( $id ) ] = $error;
			}
		}
		return array(
			'chosen'    => count( $chosen ),
			'problems'  => $problems,
			'admin_url' => admin_url( 'admin.php?page=ads-builder-perfumes' ),
		);
	}

	/**
	 * Where the builder's filters come from, like on the product page: gender = the top-level
	 * categories «عطر مردانه / زنانه / یونیسکس», season = the sub-categories of «فصل ها» (or the
	 * season attribute when there are none), mood = the attribute «حال و هوا و شخصیت ها».
	 * Filter `ads_builder_filter_sources`.
	 *
	 * @return array [ gender => int[] category ids, season => int[] category ids, season_attribute
	 *               => taxonomy, mood_attribute => taxonomy ]
	 */
	public static function filter_sources() {
		$gender = array();
		$season = array();
		$top    = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => 0,
				'hide_empty' => false,
			)
		);
		foreach ( is_array( $top ) ? $top : array() as $term ) {
			$text = $term->name . ' ' . urldecode( $term->slug );
			if ( preg_match( '/مردانه|زنانه|یونیسکس|unisex|\bmens?\b|\bwomen|female|\bmale\b/iu', $text ) ) {
				$gender[] = (int) $term->term_id;
			} elseif ( preg_match( '/فصل|season/iu', $text ) ) {
				$children = get_terms(
					array(
						'taxonomy'   => 'product_cat',
						'parent'     => $term->term_id,
						'hide_empty' => false,
						'fields'     => 'ids',
					)
				);
				$season   = array_merge( $season, is_array( $children ) ? array_map( 'intval', $children ) : array() );
			}
		}

		/**
		 * Sources of the builder filters.
		 *
		 * @param array $sources Sources.
		 */
		return (array) apply_filters(
			'ads_builder_filter_sources',
			array(
				'gender'           => $gender,
				'season'           => $season,
				'season_attribute' => 'pa_for-the-season',
				'mood_attribute'   => 'pa_atmosphere-and-characters',
			)
		);
	}

	/**
	 * Name of a filter option without the shop's «عطر» / «فصل» prefix: «عطر فصل بهار» → «بهار».
	 *
	 * @param string $name Term name.
	 * @return string
	 */
	private static function short_name( $name ) {
		$name  = trim( html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' ) );
		$short = preg_replace( '/^فصل\s+/u', '', (string) preg_replace( '/^(عطر|ادکلن)\s+/u', '', $name ) );
		return '' !== trim( (string) $short ) ? trim( (string) $short ) : $name;
	}

	/**
	 * Options of a filter in a natural order (genders and seasons in their usual order, the rest
	 * alphabetically).
	 *
	 * @param string $key     Filter key.
	 * @param array  $options id => name.
	 * @return array id => name
	 */
	private static function sort_options( $key, array $options ) {
		$known    = array(
			'gender' => array( 'مردانه', 'زنانه', 'یونیسکس' ),
			'season' => array( 'بهار', 'تابستان', 'پاییز', 'زمستان' ),
		);
		$order    = isset( $known[ $key ] ) ? $known[ $key ] : array();
		$collator = class_exists( 'Collator' ) ? new Collator( 'fa_IR' ) : null;
		$rank     = static function ( $name ) use ( $order ) {
			foreach ( $order as $i => $word ) {
				if ( false !== mb_strpos( $name, $word ) ) {
					return $i;
				}
			}
			return count( $order );
		};
		uasort(
			$options,
			static function ( $a, $b ) use ( $rank, $collator ) {
				$diff = $rank( $a ) - $rank( $b );
				if ( 0 !== $diff ) {
					return $diff;
				}
				return $collator ? (int) $collator->compare( $a, $b ) : strcmp( $a, $b );
			}
		);
		return $options;
	}

	/**
	 * Everything the builder needs.
	 *
	 * @return array
	 */
	public static function builder_data() {
		$volume  = ADS_Settings::default_volume();
		$already = WC()->cart ? ADS_Cart::needs() : array();
		$ids     = ADS_Set::builder_ids();
		$sources = self::filter_sources();
		$mood    = isset( $sources['mood_attribute'] ) && taxonomy_exists( $sources['mood_attribute'] ) ? $sources['mood_attribute'] : '';
		$season  = empty( $sources['season'] ) && isset( $sources['season_attribute'] ) && taxonomy_exists( $sources['season_attribute'] ) ? $sources['season_attribute'] : '';
		ADS_Perfume_Info::prime( $ids );

		$perfumes = array();
		$options  = array(
			'gender' => array(),
			'season' => array(),
			'family' => array(),
			'mood'   => array(),
		);
		$counts   = $options;
		foreach ( $ids as $id ) {
			$card = self::perfume_card( $id );
			if ( $card['price'] <= 0 ) {
				continue;
			}
			$have = ADS_Availability::available( $id, ADS_Availability::session_order_id() );
			$need = ADS_Set::unit_ml( $id, $volume ) + ( isset( $already[ $id ] ) ? (int) $already[ $id ] : 0 );

			// Filter values of the perfume: key => [ id => name ].
			$values = array_fill_keys( array_keys( $options ), array() );
			foreach ( wp_get_post_terms( $id, 'product_cat' ) as $term ) {
				foreach ( array( 'gender', 'season' ) as $key ) {
					if ( ! empty( $sources[ $key ] ) && in_array( (int) $term->term_id, (array) $sources[ $key ], true ) ) {
						$values[ $key ][ (int) $term->term_id ] = self::short_name( $term->name );
					}
				}
			}
			$attributes = array_filter(
				array(
					'season' => $season,
					'mood'   => $mood,
				)
			);
			foreach ( $attributes as $key => $taxonomy ) {
				foreach ( wc_get_product_terms( $id, $taxonomy, array( 'fields' => 'all' ) ) as $term ) {
					$values[ $key ][ (int) $term->term_id ] = self::short_name( $term->name );
				}
			}
			foreach ( $card['families'] as $family ) {
				$values['family'][ (int) $family['id'] ] = $family['name'];
			}

			$card['f'] = array();
			foreach ( $values as $key => $list ) {
				$card['f'][ $key ] = array_keys( $list );
				foreach ( $list as $value_id => $name ) {
					$options[ $key ][ $value_id ] = $name;
					$counts[ $key ][ $value_id ]  = ( isset( $counts[ $key ][ $value_id ] ) ? $counts[ $key ][ $value_id ] : 0 ) + 1;
				}
			}
			$card['available'] = null === $have || $have >= $need;
			$perfumes[]        = $card;
		}

		// Available perfumes first (the list is already sorted by name).
		usort(
			$perfumes,
			static function ( $a, $b ) {
				return (int) $b['available'] - (int) $a['available'];
			}
		);

		// Select boxes: gender, season, olfactory family, mood (each one when it has a choice).
		$labels  = array(
			'gender' => __( 'جنسیت', 'aromist-discovery-set' ),
			'season' => __( 'فصل', 'aromist-discovery-set' ),
			'family' => __( 'گروه بویایی', 'aromist-discovery-set' ),
			'mood'   => __( 'حال و هوا و شخصیت', 'aromist-discovery-set' ),
		);
		$filters = array();
		foreach ( $labels as $key => $label ) {
			if ( count( $options[ $key ] ) < 2 ) {
				continue;
			}
			$list = array();
			foreach ( self::sort_options( $key, $options[ $key ] ) as $value_id => $name ) {
				$list[] = array(
					'id'    => (string) $value_id,
					'name'  => $name,
					'count' => $counts[ $key ][ $value_id ],
				);
			}
			$filters[] = array(
				'key'     => $key,
				'label'   => $label,
				'options' => $list,
			);
		}

		/**
		 * Filters (select boxes) of the builder.
		 *
		 * @param array[] $filters  [ key, label, options [ id, name, count ] ].
		 * @param array[] $perfumes Perfume cards; card['f'][ key ] holds its option ids.
		 */
		$filters = (array) apply_filters( 'ads_builder_filters', $filters, $perfumes );

		$valid   = wp_list_pluck( $perfumes, 'id' );
		$initial = array();
		$editing = null;

		// Edit a saved set: ?saved=<id> (signed-in customers).
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only prefill.
		if ( isset( $_GET['saved'] ) && is_user_logged_in() ) {
			$saved = ADS_Saved_Sets::get( get_current_user_id(), sanitize_key( wp_unslash( $_GET['saved'] ) ) );
			if ( $saved ) {
				$editing = array(
					'id'   => $saved['id'],
					'name' => $saved['name'],
				);
				$initial = array_slice( array_values( array_intersect( $saved['c'], $valid ) ), 0, ads_set_size() );
			}
		}
		// Prefill: ?set=12-34-56-78 (from a ready-made set or a shared link).
		if ( ! $editing && isset( $_GET['set'] ) ) {
			$initial = array_slice( array_values( array_intersect( ADS_Set::clean_ids( str_replace( '-', ',', sanitize_text_field( wp_unslash( $_GET['set'] ) ) ) ), $valid ) ), 0, ads_set_size() );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$row       = ADS_Settings::volume( $volume );
		$logged_in = is_user_logged_in();
		$here      = self::builder_url();
		return array(
			'perfumes' => $perfumes,
			'filters'  => $filters,
			'editing'  => $editing,
			'config'   => array(
				'size'       => ads_set_size(),
				'volume'     => $volume,
				'product'    => ADS_Set::custom_product_id_if_exists(),
				'discount'   => ADS_Settings::custom_discount(),
				'fee'        => $row ? (float) $row['fee'] : 0.0,
				'round'      => (int) ADS_Settings::get( 'round_to' ),
				'initial'    => array_map( 'intval', $initial ),
				'fresh'      => ! empty( $initial ) || null !== $editing,
				'editing'    => $editing,
				'loggedIn'   => $logged_in,
				'nonce'      => $logged_in ? wp_create_nonce( ADS_Saved_Sets::NONCE ) : '',
				'saveAjax'   => WC_AJAX::get_endpoint( 'ads_save_set' ),
				'login'      => add_query_arg( 'redirect_to', rawurlencode( $here ), wc_get_page_permalink( 'myaccount' ) ),
				'mySets'     => $logged_in ? ADS_Saved_Sets::url() : '',
				'checkout'   => wc_get_checkout_url(),
				'currency'   => array(
					'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
					'pos'      => get_option( 'woocommerce_currency_pos' ),
					'thousand' => wc_get_price_thousand_separator(),
					'decimal'  => wc_get_price_decimal_separator(),
					'decimals' => wc_get_price_decimals(),
				),
				'archive'    => self::archive_url(),
				'notesShown' => ADS_Perfume_Info::notes_limit(),
				'i18n'       => array(
					/* translators: %s: number of more notes */
					'moreNotes' => __( 'نمایش بیشتر (%s)', 'aromist-discovery-set' ),
					'less'      => __( 'نمایش کمتر', 'aromist-discovery-set' ),
					/* translators: %s: number */
					'image'     => __( 'تصویر %s', 'aromist-discovery-set' ),
					/* translators: 1: chosen, 2: total */
					'count'     => __( '%1$s از %2$s', 'aromist-discovery-set' ),
					/* translators: %s: number of perfumes still to choose */
					'more'      => __( '%s عطر دیگر انتخاب کن', 'aromist-discovery-set' ),
					'ready'     => __( 'ست کامل است؛ به سبد اضافه کن یا ذخیره‌اش کن.', 'aromist-discovery-set' ),
					'empty'     => __( 'یک عطر انتخاب کن', 'aromist-discovery-set' ),
					/* translators: %s: perfume */
					'remove'    => __( 'حذف %s از ست', 'aromist-discovery-set' ),
					'full'      => __( 'ست کامل است؛ برای عوض کردن، اول یک عطر را از ست حذف کن.', 'aromist-discovery-set' ),
					'saving'    => __( 'در حال ذخیره…', 'aromist-discovery-set' ),
					'saveNew'   => __( 'ذخیره در ست‌های من', 'aromist-discovery-set' ),
					'saveEdit'  => __( 'ذخیره‌ی تغییرات', 'aromist-discovery-set' ),
					'mySets'    => __( 'مشاهده در ست‌های من', 'aromist-discovery-set' ),
					'error'     => __( 'انجام نشد. دوباره تلاش کنید.', 'aromist-discovery-set' ),
					'add'       => __( 'افزودن به ست', 'aromist-discovery-set' ),
					'inSet'     => __( 'در ست تو ✓', 'aromist-discovery-set' ),
					'removeIt'  => __( 'حذف از ست', 'aromist-discovery-set' ),
					'out'       => __( 'ناموجود', 'aromist-discovery-set' ),
					'family'    => __( 'گروه بویایی', 'aromist-discovery-set' ),
					'notes'     => ADS_Perfume_Info::note_labels(),
					'view'      => __( 'مشاهده‌ی صفحه‌ی عطر', 'aromist-discovery-set' ),
					'close'     => __( 'بستن', 'aromist-discovery-set' ),
					'price'     => sprintf( /* translators: %s: volume */ __( 'قیمت %s', 'aromist-discovery-set' ), ads_format_ml( $volume ) ),
				),
			),
		);
	}
}
