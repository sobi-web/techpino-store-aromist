<?php
/**
 * The product index the browser searches.
 *
 * Every published, searchable product goes into one JSON file under uploads, which the web
 * server hands out directly and the browser keeps (its URL carries a content hash). Any
 * change to a product, its terms or its stock status marks the index stale and it is
 * written again once, at the end of that request. When the file cannot be written, the
 * same JSON is kept in an option and printed into the page instead.
 *
 * @package TechpinoLiveSearch
 */

defined( 'ABSPATH' ) || exit;

final class TPLS_Index {

	private const OPTION      = 'tpls_index';
	private const DATA_OPTION = 'tpls_index_data';
	private const DIR         = 'techpino-live-search';
	private const FILE        = 'index.json';

	/** @var bool A rebuild is already queued for the end of this request. */
	private static $queued = false;

	/** @var array|null Info of the index built during this request. */
	private static $built = null;

	public static function init(): void {
		$product_hooks = array(
			'woocommerce_new_product',
			'woocommerce_update_product',
			'woocommerce_delete_product',
			'woocommerce_trash_product',
			'woocommerce_new_product_variation',
			'woocommerce_update_product_variation',
			'woocommerce_delete_product_variation',
			'woocommerce_product_set_stock_status',
			'woocommerce_variation_set_stock_status',
			'woocommerce_product_set_visibility',
		);
		foreach ( $product_hooks as $hook ) {
			add_action( $hook, array( __CLASS__, 'queue' ) );
		}
		add_action( 'transition_post_status', array( __CLASS__, 'on_status' ), 10, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'on_post' ), 10, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'on_post' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_post' ) );
		add_action( 'created_term', array( __CLASS__, 'on_term' ), 10, 3 );
		add_action( 'edited_term', array( __CLASS__, 'on_term' ), 10, 3 );
		add_action( 'delete_term', array( __CLASS__, 'on_term' ), 10, 3 );
		add_action( 'update_option_woocommerce_hide_out_of_stock_items', array( __CLASS__, 'queue' ) );
		add_action( 'admin_post_tpls_rebuild', array( __CLASS__, 'rebuild_request' ) );
	}

	/** Drop the stored index; the next page that shows the widget builds a fresh one. */
	public static function flush(): void {
		delete_option( self::OPTION );
	}

	public static function queue(): void {
		if ( ! self::$queued ) {
			self::$queued = true;
			add_action( 'shutdown', array( __CLASS__, 'build' ), 1 );
		}
	}

	public static function on_status( $new, $old, $post ): void {
		if ( $new !== $old && $post instanceof WP_Post && in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
			self::queue();
		}
	}

	public static function on_post( $post_id, $post = null ): void {
		$type = $post instanceof WP_Post ? $post->post_type : get_post_type( $post_id );
		if ( in_array( $type, array( 'product', 'product_variation' ), true ) ) {
			self::queue();
		}
	}

	public static function on_term( $term_id, $tt_id, $taxonomy ): void {
		if ( in_array( $taxonomy, self::taxonomies(), true ) || 0 === strpos( (string) $taxonomy, 'pa_' ) ) {
			self::queue();
		}
	}

	/** «ساخت دوباره‌ی فهرست» on the Plugins screen. */
	public static function rebuild_request(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'techpino-live-search' ), 403 );
		}
		check_admin_referer( 'tpls_rebuild' );
		self::build();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified above.
		$back = isset( $_GET['back'] ) && 'tpls' === $_GET['back'] ? TPLS_Admin::url( 'advanced' ) : admin_url( 'plugins.php' );
		wp_safe_redirect( add_query_arg( 'tpls_rebuilt', '1', $back ) );
		exit;
	}

	/** @return string[] Taxonomies whose term names are searched and shown. */
	private static function taxonomies(): array {
		return array_filter( array( 'product_cat', 'product_brand', 'product_tag' ), 'taxonomy_exists' );
	}

	/**
	 * Where the browser gets the index from.
	 *
	 * @return array{url: string, data: string} One of the two is empty.
	 */
	public static function source(): array {
		$info = self::$built ? self::$built : get_option( self::OPTION );
		if ( ! is_array( $info ) || empty( $info['v'] ) || ( ! empty( $info['file'] ) && ! file_exists( self::path() ) ) ) {
			$info = self::build();
		}
		if ( ! empty( $info['file'] ) ) {
			return array(
				'url'  => add_query_arg( 'v', $info['v'], set_url_scheme( self::base_url() . '/' . self::FILE ) ),
				'data' => '',
			);
		}
		return array(
			'url'  => '',
			'data' => (string) get_option( self::DATA_OPTION, '' ),
		);
	}

	private static function dir(): string {
		$uploads = wp_upload_dir( null, false );
		return trailingslashit( $uploads['basedir'] ) . self::DIR;
	}

	private static function path(): string {
		return self::dir() . '/' . self::FILE;
	}

	private static function base_url(): string {
		$uploads = wp_upload_dir( null, false );
		return trailingslashit( $uploads['baseurl'] ) . self::DIR;
	}

	/**
	 * Write the index now.
	 *
	 * @return array{v: string, file: bool, count: int, time: int}
	 */
	public static function build(): array {
		$json = wp_json_encode( self::collect(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$info = array(
			'v'     => substr( md5( (string) $json ), 0, 12 ),
			'file'  => false,
			'count' => 0,
			'time'  => time(),
		);
		$data = json_decode( (string) $json, true );
		$info['count'] = isset( $data['p'] ) ? count( $data['p'] ) : 0;

		if ( wp_mkdir_p( self::dir() ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a plain public file, written in one go.
			$info['file'] = false !== file_put_contents( self::path(), $json, LOCK_EX );
		}
		if ( $info['file'] ) {
			delete_option( self::DATA_OPTION );
		} else {
			update_option( self::DATA_OPTION, $json, false );
		}
		update_option( self::OPTION, $info, true );
		self::$built = $info;
		return $info;
	}

	/**
	 * The index itself. Keys are kept to one letter: it is downloaded by every visitor.
	 *
	 * p: products — i id, t title, e English name, u link, m image (300px), n image (150px),
	 *    p lowest price, x highest price, r regular price when on sale, s in stock, q sales,
	 *    b brand indexes, c category indexes, g tag names, k SKUs, a attribute values,
	 *    v sizes («10,20,50»), o sizes out of stock («20»).
	 * b / c: brands and categories — [name, link, product count].
	 */
	private static function collect(): array {
		$terms = array();
		$out   = array(
			'p' => array(),
			'b' => array(),
			'c' => array(),
		);
		foreach ( array(
			'b' => 'product_brand',
			'c' => 'product_cat',
		) as $key => $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$list = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
					'exclude'    => 'product_cat' === $taxonomy ? array( (int) get_option( 'default_product_cat', 0 ) ) : array(),
				)
			);
			if ( is_wp_error( $list ) ) {
				continue;
			}
			foreach ( $list as $term ) {
				$link = get_term_link( $term );
				$terms[ $key ][ $term->term_id ] = count( $out[ $key ] );
				$out[ $key ][] = array( self::plain( $term->name ), is_wp_error( $link ) ? '' : self::url( $link ), (int) $term->count );
			}
		}

		$args = array(
			'status'     => 'publish',
			'limit'      => -1,
			'visibility' => 'search',
			'orderby'    => 'menu_order',
			'order'      => 'ASC',
		);
		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$args['stock_status'] = 'instock';
		}
		$products = wc_get_products( apply_filters( 'tpls_index_query_args', $args ) );

		foreach ( $products as $product ) {
			$row = self::row( $product, $terms );
			if ( $row ) {
				$out['p'][] = $row;
			}
		}
		return apply_filters( 'tpls_index', $out );
	}

	private static function row( WC_Product $product, array $terms ): ?array {
		$id = $product->get_id();

		$price = self::prices( $product );
		$image = $product->get_image_id();
		$large = $image ? wp_get_attachment_image_url( $image, 'woocommerce_thumbnail' ) : '';
		// Only a real 150px crop is worth a srcset entry; without one WordPress hands back the original.
		$small = $image ? wp_get_attachment_image_src( $image, 'thumbnail' ) : false;
		$small = $small && ! empty( $small[3] ) ? $small[0] : '';
		if ( ! $large ) {
			$large = wc_placeholder_img_src( 'woocommerce_thumbnail' );
			$small = '';
		}

		$row = array(
			'i' => $id,
			't' => self::plain( $product->get_name() ),
			'u' => self::url( get_permalink( $id ) ),
			'm' => self::url( $large ),
		);
		$english = self::plain( (string) get_post_meta( $id, 'english_title', true ) );
		if ( '' !== $english ) {
			$row['e'] = $english;
		}
		$sizes = self::sizes( $product );
		if ( $sizes['all'] ) {
			$row['v'] = implode( ',', $sizes['all'] );
			if ( $sizes['out'] ) {
				$row['o'] = implode( ',', $sizes['out'] );
			}
		}
		if ( $small && $small !== $large ) {
			$row['n'] = self::url( $small );
		}
		if ( null !== $price['min'] ) {
			$row['p'] = $price['min'];
			if ( $price['max'] > $price['min'] ) {
				$row['x'] = $price['max'];
			}
			if ( $price['regular'] > $price['min'] ) {
				$row['r'] = $price['regular'];
			}
		}
		$row['s'] = $product->is_in_stock() ? 1 : 0;
		$row['q'] = (int) $product->get_total_sales();

		foreach ( array(
			'b' => 'product_brand',
			'c' => 'product_cat',
		) as $key => $taxonomy ) {
			if ( empty( $terms[ $key ] ) ) {
				continue;
			}
			$ids  = wc_get_product_term_ids( $id, $taxonomy );
			$list = array();
			foreach ( $ids as $term_id ) {
				if ( isset( $terms[ $key ][ $term_id ] ) ) {
					$list[] = $terms[ $key ][ $term_id ];
				}
			}
			if ( $list ) {
				$row[ $key ] = $list;
			}
		}

		if ( taxonomy_exists( 'product_tag' ) ) {
			$tags = wp_get_post_terms( $id, 'product_tag', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $tags ) && $tags ) {
				$row['g'] = self::plain( implode( ' ', $tags ) );
			}
		}

		$skus = array_filter( array( $product->get_sku() ) );
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$sku = get_post_meta( $child_id, '_sku', true );
				if ( $sku ) {
					$skus[] = $sku;
				}
			}
		}
		if ( $skus ) {
			$row['k'] = implode( ' ', array_unique( $skus ) );
		}

		$values = array();
		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof WC_Product_Attribute || ! $attribute->get_visible() ) {
				continue;
			}
			if ( $attribute->is_taxonomy() ) {
				$names = wp_get_post_terms( $id, $attribute->get_name(), array( 'fields' => 'names' ) );
				if ( ! is_wp_error( $names ) ) {
					$values = array_merge( $values, $names );
				}
			} else {
				$values = array_merge( $values, $attribute->get_options() );
			}
		}
		if ( $values ) {
			$row['a'] = self::plain( implode( ' ', array_unique( $values ) ) );
		}

		return apply_filters( 'tpls_index_row', $row, $product );
	}

	/**
	 * The sizes of a perfume (attribute pa_volume, one per variation) as numbers of millilitres,
	 * smallest first, and the ones out of stock — for the size circles of the «کارت سایت» card.
	 *
	 * @return array{all: string[], out: string[]}
	 */
	private static function sizes( WC_Product $product ): array {
		$out = array(
			'all' => array(),
			'out' => array(),
		);
		$taxonomy = (string) apply_filters( 'tpls_size_attribute', 'pa_volume' );
		if ( ! $product->is_type( 'variable' ) || ! taxonomy_exists( $taxonomy ) ) {
			return $out;
		}
		$stock = array();
		/** @var WC_Product_Variable $product */
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation || 'publish' !== get_post_status( $child_id ) ) {
				continue;
			}
			$slug = (string) $variation->get_attribute( $taxonomy );
			$term = $slug ? get_term_by( 'name', $slug, $taxonomy ) : false;
			$name = $term ? $term->name : $slug;
			$name = strtr( $name, array_combine( array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ), range( 0, 9 ) ) );
			if ( ! preg_match( '/\d+(?:\.\d+)?/', $name, $m ) ) {
				continue;
			}
			$size           = $m[0];
			$stock[ $size ] = ! empty( $stock[ $size ] ) || $variation->is_in_stock();
		}
		uksort(
			$stock,
			static function ( $a, $b ) {
				return (float) $a <=> (float) $b;
			}
		);
		foreach ( $stock as $size => $in ) {
			$out['all'][] = (string) $size;
			if ( ! $in ) {
				$out['out'][] = (string) $size;
			}
		}
		return $out;
	}

	/**
	 * Prices as the shop displays them (tax setting included).
	 *
	 * @return array{min: float|null, max: float|null, regular: float|null}
	 */
	private static function prices( WC_Product $product ): array {
		$min     = null;
		$max     = null;
		$regular = null;

		if ( $product->is_type( 'variable' ) ) {
			/** @var WC_Product_Variable $product */
			$min     = $product->get_variation_price( 'min', true );
			$max     = $product->get_variation_price( 'max', true );
			$regular = $product->get_variation_regular_price( 'min', true );
		} elseif ( $product->is_type( 'grouped' ) ) {
			$list = array();
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child && '' !== $child->get_price() ) {
					$list[] = (float) wc_get_price_to_display( $child );
				}
			}
			if ( $list ) {
				$min = min( $list );
				$max = max( $list );
			}
		} elseif ( '' !== $product->get_price() ) {
			$min     = wc_get_price_to_display( $product );
			$max     = $min;
			$regular = '' !== $product->get_regular_price() ? wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ) : null;
		}

		if ( '' === $min || null === $min ) {
			return array(
				'min'     => null,
				'max'     => null,
				'regular' => null,
			);
		}
		return array(
			'min'     => (float) $min,
			'max'     => (float) $max,
			'regular' => $product->is_on_sale() && '' !== $regular && null !== $regular ? (float) $regular : null,
		);
	}

	/**
	 * Persian slugs arrive percent-encoded, three times their UTF-8 size; browsers take them
	 * either way. Spaces are the one thing that must stay encoded.
	 */
	private static function url( string $url ): string {
		return str_replace( ' ', '%20', rawurldecode( $url ) );
	}

	private static function plain( string $text ): string {
		return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) );
	}
}
