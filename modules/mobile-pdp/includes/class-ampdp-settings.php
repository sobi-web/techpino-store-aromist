<?php
/**
 * Settings: header, search, product page and the trust row.
 * Saved as one array; each admin tab posts only its own fields (see sanitize()).
 *
 * @package AromistMobilePDP
 */

defined( 'ABSPATH' ) || exit;

final class AMPDP_Settings {

	const OPTION = 'ampdp_settings';

	/** Trust-row boxes the admin can fill. */
	const TRUST_MAX = 4;

	/** Banners in the search screen. */
	const BANNER_MAX = 4;

	/** @var array|null */
	private static $cache = null;

	public static function defaults(): array {
		return array(
			// Header.
			'header_tpl'         => 0, // An Elementor template drawn instead of the built-in header; 0 = built-in.
			'header_style'       => 'search', // Pages other than the product page: search (only a search field) | icons (☰, logo, buttons).
			'header_hide'        => 1, // Slide away while scrolling down.
			'logo_id'            => 0, // 0 = the site's own logo.
			'logo_size'          => 34,
			'wordmark'           => 'AROMIST',
			'menu'               => 'site', // «site» = the bottom bar's menu sheet; a number = a WordPress menu.
			'show_search'        => 1,
			'show_account'       => 1,
			'show_cart'          => 1,
			// Search.
			'search_placeholder' => 'دنبال چی میگردی . . .',
			'search_live'        => 1,
			'search_popular'     => "عطر مردانه\nعطر زنانه\nعطر زمستانی\nجورجیو آرمانی",
			'search_cats'        => array(),
			'search_banners'     => array(), // [ [ image_id, link ], … ]
			'banner_layout'      => 'stack', // stack | grid | scroll
			'banner_pos'         => 'top', // top | middle (after the popular searches) | bottom
			'search_tpl'         => 0, // An Elementor template shown where the banners go.
			// Product page.
			'pdp_tags'           => 1, // Category chips above the title.
			'pdp_tabs'           => 1, // Section menu at the top while scrolling.
			'about_lines'        => 8, // «درباره این عطر»: lines shown before «نمایش بیشتر».
			'pdp_faq'            => 1, // «سوالات متداول» (techpino-product-faq) after the full article.
			// Trust row.
			'trust_on'           => 1,
			'trust'              => array(
				array( 'icon' => 'shield', 'title' => 'اصالت کالا', 'link' => '' ),
				array( 'icon' => 'truck', 'title' => 'ارسال سریع', 'link' => '' ),
				array( 'icon' => 'swap', 'title' => 'گارانتی تعویض', 'link' => '' ),
				array( 'icon' => 'phone', 'title' => 'مشاوره خرید', 'link' => 'tel:09224203330' ),
			),
		);
	}

	public static function all(): array {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		}
		return self::$cache;
	}

	/** @return mixed */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/** Trust boxes that have a title. */
	public static function trust_items(): array {
		return array_values(
			array_filter(
				(array) self::get( 'trust' ),
				static function ( $item ) {
					return is_array( $item ) && '' !== trim( (string) ( $item['title'] ?? '' ) );
				}
			)
		);
	}

	/** Search banners that have an image. */
	public static function banners(): array {
		return array_values(
			array_filter(
				(array) self::get( 'search_banners' ),
				static function ( $item ) {
					return is_array( $item ) && ! empty( $item['image'] );
				}
			)
		);
	}

	/**
	 * Elementor templates that can be drawn as the header or inside the search screen.
	 *
	 * @return array<int, string> id => title (type)
	 */
	public static function elementor_templates(): array {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return array();
		}
		$types = array(
			'section'   => __( 'سکشن', 'aromist-mobile-pdp' ),
			'container' => __( 'کانتینر', 'aromist-mobile-pdp' ),
			'page'      => __( 'برگه', 'aromist-mobile-pdp' ),
			'header'    => __( 'هدر', 'aromist-mobile-pdp' ),
		);
		$posts = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_elementor_template_type',
						'value'   => array_keys( $types ),
						'compare' => 'IN',
					),
				),
			)
		);
		$out   = array();
		foreach ( $posts as $post ) {
			// A theme-builder template with display conditions (e.g. the site's own header) is already in use.
			if ( get_post_meta( $post->ID, '_elementor_conditions', true ) ) {
				continue;
			}
			$type            = (string) get_post_meta( $post->ID, '_elementor_template_type', true );
			$out[ $post->ID ] = sprintf( '%s (%s)', '' !== $post->post_title ? $post->post_title : '#' . $post->ID, $types[ $type ] ?? $type );
		}
		return $out;
	}

	/** Popular searches, one per line. */
	public static function popular(): array {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\R/u', (string) self::get( 'search_popular' ) ) ) ) );
	}

	/**
	 * Built-in icons (24×24 line icons) for the trust row.
	 *
	 * @return array<string, array{0:string,1:string}> key => [label, svg paths]
	 */
	public static function icons(): array {
		return array(
			'shield'  => array( __( 'سپر (اصالت)', 'aromist-mobile-pdp' ), '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>' ),
			'truck'   => array( __( 'کامیون (ارسال)', 'aromist-mobile-pdp' ), '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.62L18.3 9.38a1 1 0 0 0-.78-.38H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>' ),
			'swap'    => array( __( 'چرخش (تعویض)', 'aromist-mobile-pdp' ), '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M8 16H3v5"/>' ),
			'phone'   => array( __( 'تلفن', 'aromist-mobile-pdp' ), '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>' ),
			'headset' => array( __( 'هدست (پشتیبانی)', 'aromist-mobile-pdp' ), '<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/>' ),
			'gift'    => array( __( 'هدیه', 'aromist-mobile-pdp' ), '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C10 3 12 8 12 8s2-5 4.5-5a2.5 2.5 0 0 1 0 5"/>' ),
			'card'    => array( __( 'کارت (پرداخت)', 'aromist-mobile-pdp' ), '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>' ),
			'box'     => array( __( 'جعبه (بسته‌بندی)', 'aromist-mobile-pdp' ), '<path d="M21 8 12 3 3 8v8l9 5 9-5Z"/><path d="m3 8 9 5 9-5M12 13v8"/>' ),
			'drop'    => array( __( 'قطره (عطر)', 'aromist-mobile-pdp' ), '<path d="M12 2.7 6.3 9a7 7 0 1 0 11.4 0Z"/>' ),
			'star'    => array( __( 'ستاره', 'aromist-mobile-pdp' ), '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>' ),
			'clock'   => array( __( 'ساعت', 'aromist-mobile-pdp' ), '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>' ),
			'chat'    => array( __( 'گفتگو', 'aromist-mobile-pdp' ), '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1.1-4.6A8 8 0 1 1 21 12Z"/>' ),
		);
	}

	public static function icon_svg( string $key, string $class = 'ampdp-ic' ): string {
		$icons = self::icons();
		$paths = isset( $icons[ $key ] ) ? $icons[ $key ][1] : $icons['shield'][1];
		return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">' . $paths . '</svg>';
	}

	/**
	 * Each admin tab posts `_tab` and only its own fields; the other tabs keep what was saved.
	 *
	 * @param mixed $input Posted values.
	 */
	public static function sanitize( $input ): array {
		$saved = get_option( self::OPTION, array() );
		$out   = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		if ( ! is_array( $input ) ) {
			return $out;
		}
		$tab = sanitize_key( (string) ( $input['_tab'] ?? '' ) );

		if ( '' === $tab ) {
			// Already sanitized: when the option is first added WordPress runs this callback a second time.
			self::$cache = null;
			return array_merge( $out, array_intersect_key( $input, self::defaults() ) );
		}

		if ( 'header' === $tab ) {
			$out['header_tpl']   = absint( $input['header_tpl'] ?? 0 );
			$out['header_style'] = 'icons' === ( $input['header_style'] ?? '' ) ? 'icons' : 'search';
			$out['header_hide']  = empty( $input['header_hide'] ) ? 0 : 1;
			$out['logo_id']      = absint( $input['logo_id'] ?? 0 );
			$out['logo_size']    = min( 56, max( 20, absint( $input['logo_size'] ?? 34 ) ) );
			$out['wordmark']     = sanitize_text_field( (string) ( $input['wordmark'] ?? '' ) );
			$menu                = (string) ( $input['menu'] ?? 'site' );
			$out['menu']         = 'site' === $menu ? 'site' : (string) absint( $menu );
			$out['show_search']  = empty( $input['show_search'] ) ? 0 : 1;
			$out['show_account'] = empty( $input['show_account'] ) ? 0 : 1;
			$out['show_cart']    = empty( $input['show_cart'] ) ? 0 : 1;
		} elseif ( 'search' === $tab ) {
			$out['search_placeholder'] = sanitize_text_field( (string) ( $input['search_placeholder'] ?? '' ) );
			$out['search_live']        = empty( $input['search_live'] ) ? 0 : 1;
			$out['search_popular']     = sanitize_textarea_field( (string) ( $input['search_popular'] ?? '' ) );
			$out['search_cats']        = array_values( array_filter( array_map( 'absint', (array) ( $input['search_cats'] ?? array() ) ) ) );
			$banners                   = array();
			foreach ( array_slice( (array) ( $input['search_banners'] ?? array() ), 0, self::BANNER_MAX ) as $item ) {
				$item  = (array) $item;
				$image = absint( $item['image'] ?? 0 );
				$link  = trim( (string) ( $item['link'] ?? '' ) );
				if ( $image ) {
					$banners[] = array(
						'image' => $image,
						'link'  => '' === $link ? '' : esc_url_raw( $link ),
					);
				}
			}
			$out['search_banners'] = $banners;
			$layout                = (string) ( $input['banner_layout'] ?? 'stack' );
			$out['banner_layout']  = in_array( $layout, array( 'stack', 'grid', 'scroll' ), true ) ? $layout : 'stack';
			$pos                   = (string) ( $input['banner_pos'] ?? 'top' );
			$out['banner_pos']     = in_array( $pos, array( 'top', 'middle', 'bottom' ), true ) ? $pos : 'top';
			$out['search_tpl']     = absint( $input['search_tpl'] ?? 0 );
		} elseif ( 'pdp' === $tab ) {
			$out['pdp_tags']    = empty( $input['pdp_tags'] ) ? 0 : 1;
			$out['pdp_tabs']    = empty( $input['pdp_tabs'] ) ? 0 : 1;
			$out['pdp_faq']     = empty( $input['pdp_faq'] ) ? 0 : 1;
			$out['about_lines'] = min( 30, max( 3, absint( $input['about_lines'] ?? 8 ) ) );
		} elseif ( 'trust' === $tab ) {
			$out['trust_on'] = empty( $input['trust_on'] ) ? 0 : 1;
			$items           = array();
			$icons           = self::icons();
			foreach ( array_slice( (array) ( $input['trust'] ?? array() ), 0, self::TRUST_MAX ) as $item ) {
				$item    = (array) $item;
				$icon    = sanitize_key( (string) ( $item['icon'] ?? '' ) );
				$link    = trim( (string) ( $item['link'] ?? '' ) );
				$items[] = array(
					'icon'  => isset( $icons[ $icon ] ) ? $icon : 'shield',
					'title' => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
					'link'  => '' === $link ? '' : esc_url_raw( $link, array( 'http', 'https', 'tel', 'mailto' ) ),
				);
			}
			$out['trust'] = $items;
		}
		self::$cache = null;
		return $out;
	}
}
