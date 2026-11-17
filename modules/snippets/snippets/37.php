<?php
// اسنیپت #37: فوتر موبایل
defined( 'ABSPATH' ) || exit;

/**
 * Techpino Mobile Footer Bar — Elementor widget
 * Version: 2.1.1
 * Requires: Elementor, PHP 7.4+ (WooCommerce optional)
 *
 * Elementor widget: «نوار پایین موبایل (تکپینو)» — a fixed bottom bar for phones:
 * منو · صفحه اصلی · سبد خرید (دکمه‌ی برجسته‌ی وسط) · فروشگاه · حساب کاربری.
 * «منو» opens a bottom sheet of grouped links (by gender, by season, all products, …)
 * followed by the full site menu, managed in نمایش › فهرست‌ها (menu location «نوار پایین موبایل»).
 * Every button, the cart button and the sheet have their own settings; icons come from a
 * built-in minimal set or from any Elementor icon library.
 *
 * Put the widget once in the site footer template (or any page). It moves itself to the
 * end of <body>, so it stays fixed whatever container it was dropped in.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Techpino_Footer_Bar_Snippet' ) ) {
	final class Techpino_Footer_Bar_Snippet {

		/** Menu location of the full site menu shown in the sheet. */
		public const LOCATION = 'tpfb-mobile';

		/** The site's own pages, used until a menu is set for the location: title => page slug ('' = home, 'shop' = shop page). */
		private const DEFAULT_MENU = array(
			'صفحه اصلی'  => '',
			'تماس با ما' => 'contact',
			'درباره ما'  => 'about',
			'فروشگاه'    => 'shop',
			'برندها'     => 'brands',
			'مقالات'     => 'blog',
		);

		private static $instance = null;

		/** @var bool The shared style / script were already printed on this page. */
		private $assets_printed = false;

		public static function instance(): self {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
			add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_fragment' ) );
			add_action( 'admin_init', array( $this, 'seed_menu' ) );
			if ( did_action( 'after_setup_theme' ) ) {
				$this->register_location();
			} else {
				add_action( 'after_setup_theme', array( $this, 'register_location' ), 20 );
			}
		}

		public function register_location(): void {
			register_nav_menus( array( self::LOCATION => 'نوار پایین موبایل (منوی کامل)' ) );
		}

		/**
		 * Once, on the first admin visit: a «نوار پایین موبایل» menu with the site's own pages, set to the
		 * location, so it is ready to edit in نمایش › فهرست‌ها. Nothing happens if the location already has a menu.
		 */
		public function seed_menu(): void {
			if ( get_option( 'tpfb_menu_seeded' ) || wp_doing_ajax() || ! current_user_can( 'edit_theme_options' ) ) {
				return;
			}
			update_option( 'tpfb_menu_seeded', 1, false );
			$locations = get_nav_menu_locations();
			if ( ! empty( $locations[ self::LOCATION ] ) && wp_get_nav_menu_object( $locations[ self::LOCATION ] ) ) {
				return;
			}
			$name    = 'نوار پایین موبایل';
			$menu    = wp_get_nav_menu_object( $name );
			$menu_id = $menu ? (int) $menu->term_id : wp_create_nav_menu( $name );
			if ( is_wp_error( $menu_id ) || ! $menu_id ) {
				return;
			}
			if ( ! $menu ) {
				foreach ( self::DEFAULT_MENU as $title => $slug ) {
					// Linked to the page itself where there is one, so a changed slug does not break the menu.
					$page = '' !== $slug ? get_page_by_path( $slug ) : null;
					$item = $page && 'publish' === $page->post_status
						? array( 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $page->ID )
						: array( 'menu-item-type' => 'custom', 'menu-item-url' => $this->default_url( $slug ) );
					wp_update_nav_menu_item( $menu_id, 0, $item + array( 'menu-item-title' => $title, 'menu-item-status' => 'publish' ) );
				}
			}
			$locations[ self::LOCATION ] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}

		private function default_url( string $slug ): string {
			if ( '' === $slug ) {
				return home_url( '/' );
			}
			return 'shop' === $slug ? $this->urls()['shop'] : home_url( '/' . $slug . '/' );
		}

		/**
		 * The full site menu for the sheet, two levels deep (deeper items join their top-level ancestor).
		 *
		 * @param string $source «location», or «menu-{id}» for a particular menu.
		 * @return array<int,array{label:string,url:string,blank:bool,rel:string,icon:string,current:bool,children:array}>
		 */
		public function site_menu( string $source ): array {
			$menu = 0;
			if ( 0 === strpos( $source, 'menu-' ) ) {
				$menu = (int) substr( $source, 5 );
			} else {
				$locations = get_nav_menu_locations();
				$menu      = (int) ( $locations[ self::LOCATION ] ?? 0 );
			}
			$items = $menu ? wp_get_nav_menu_items( $menu, array( 'update_post_term_cache' => false ) ) : false;
			if ( ! $items ) {
				$out = array();
				foreach ( self::DEFAULT_MENU as $title => $slug ) {
					$out[] = $this->menu_node( $title, $this->default_url( $slug ), false, '', array() );
				}
				return $out;
			}
			$out = array();
			$top = array(); // item ID => index of its top-level ancestor in $out
			foreach ( $items as $item ) {
				if ( ! empty( $item->_invalid ) ) {
					continue;
				}
				$node   = $this->menu_node( wp_strip_all_tags( (string) $item->title ), (string) $item->url, '_blank' === $item->target, (string) $item->xfn, (array) $item->classes );
				$parent = (int) $item->menu_item_parent;
				if ( $parent && isset( $top[ $parent ] ) ) {
					$out[ $top[ $parent ] ]['children'][] = $node;
					$top[ $item->ID ]                     = $top[ $parent ];
				} else {
					$out[]            = $node;
					$top[ $item->ID ] = count( $out ) - 1;
				}
			}
			return $out;
		}

		private function menu_node( string $label, string $url, bool $blank, string $rel, array $classes ): array {
			return array(
				'label'    => $label,
				'url'      => $url,
				'blank'    => $blank,
				'rel'      => trim( $rel . ( $blank ? ' noopener' : '' ) ),
				'icon'     => $this->menu_icon( $label, $url, $classes ),
				'current'  => $this->is_current( $url ),
				'children' => array(),
			);
		}

		/**
		 * Icon of a menu item: «tpfb-icon-{name}» in the item's CSS classes, else a guess from its title and address.
		 */
		public function menu_icon( string $label, string $url, array $classes ): string {
			foreach ( $classes as $class ) {
				if ( 0 === strpos( (string) $class, 'tpfb-icon-' ) && isset( $this->icons()[ substr( $class, 10 ) ] ) ) {
					return substr( $class, 10 );
				}
			}
			$path = untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
			if ( $path === untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ) && false === strpos( $url, '?' ) ) {
				return 'home';
			}
			$text  = $label . ' ' . rawurldecode( $path );
			$rules = array(
				'phone'   => array( 'تماس', 'contact' ),
				'info'    => array( 'درباره', 'about' ),
				'crown'   => array( 'برند', 'brand' ),
				'help'    => array( 'سوال', 'سؤال', 'راهنما', 'faq', 'help' ),
				'article' => array( 'مقاله', 'مقالات', 'بلاگ', 'وبلاگ', 'مجله', 'اخبار', 'blog', 'news' ),
				'bag'     => array( 'سبد', 'cart' ),
				'user'    => array( 'حساب', 'account' ),
				'heart'   => array( 'علاقه', 'wishlist' ),
				'shop'    => array( 'فروشگاه', 'shop', 'store' ),
				'bottle'  => array( 'عطر', 'product' ),
				'home'    => array( 'صفحه اصلی', 'خانه' ),
			);
			foreach ( $rules as $icon => $words ) {
				foreach ( $words as $word ) {
					if ( false !== stripos( $text, $word ) ) {
						return (string) apply_filters( 'tpfb_menu_icon', $icon, $label, $url );
					}
				}
			}
			return (string) apply_filters( 'tpfb_menu_icon', 'arrow', $label, $url );
		}

		/**
		 * Default targets of the bar buttons.
		 *
		 * @return array{home:string,shop:string,cart:string,account:string}
		 */
		public function urls(): array {
			$wc = function_exists( 'wc_get_page_permalink' );
			return array(
				'home'    => home_url( '/' ),
				'shop'    => $wc ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ),
				'cart'    => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
				'account' => $wc ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ),
			);
		}

		/**
		 * The built-in icon set: 24×24 line icons; parts marked «d» get a soft tint of the same colour.
		 * Names starting with «_» are for the widget's own use and are not offered in the settings.
		 *
		 * @return array<string,array{0:string,1:string}> name => [label, SVG content]
		 */
		public function icons(): array {
			static $icons = null;
			if ( null === $icons ) {
				$icons = (array) apply_filters(
					'tpfb_icons',
					array(
						'menu'      => array( 'منو', '<path d="M20 6.5H4M20 12H10M20 17.5H7"/>' ),
						'grid'      => array( 'دسته‌بندی', '<rect class="d" x="4" y="4" width="6.5" height="6.5" rx="2"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="2"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="2"/><circle cx="16.75" cy="16.75" r="3.3"/>' ),
						'home'      => array( 'خانه', '<path class="d" d="M4.5 10.4 11.1 5a1.4 1.4 0 0 1 1.8 0l6.6 5.4V19a1.5 1.5 0 0 1-1.5 1.5h-3.2v-4.2a2.8 2.8 0 0 0-5.6 0v4.2H6A1.5 1.5 0 0 1 4.5 19z"/>' ),
						'bag'       => array( 'کیف خرید', '<path class="d" d="M5.2 8.5h13.6l-.9 10.6a1.6 1.6 0 0 1-1.6 1.4H7.7a1.6 1.6 0 0 1-1.6-1.4z"/><path d="M9 11V7a3 3 0 0 1 6 0v4"/>' ),
						'cart'      => array( 'چرخ خرید', '<path d="M2.5 4h2.2l2.1 10.6a1.5 1.5 0 0 0 1.5 1.2h8.5a1.5 1.5 0 0 0 1.4-1.1L20.5 8H5.6"/><circle class="d" cx="9.5" cy="19.6" r="1.4"/><circle class="d" cx="17" cy="19.6" r="1.4"/>' ),
						'shop'      => array( 'فروشگاه', '<path class="d" d="M5.2 4h13.6a1 1 0 0 1 .9.6L21 9a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0l1.3-4.4a1 1 0 0 1 .9-.6z"/><path d="M4.5 12.5V19A1.5 1.5 0 0 0 6 20.5h12a1.5 1.5 0 0 0 1.5-1.5v-6.5M10 20.5v-3a2 2 0 0 1 4 0v3"/>' ),
						'bottle'    => array( 'شیشه‌ی عطر', '<rect x="9.5" y="2.5" width="5" height="3.2" rx="1"/><path d="M10.6 5.7V8M13.4 5.7V8"/><rect class="d" x="5.5" y="8" width="13" height="13.5" rx="3.6"/><path d="M9 12.2v3.6"/>' ),
						'user'      => array( 'کاربر', '<circle class="d" cx="12" cy="8" r="3.8"/><path d="M5 20c.8-3.6 3.6-5.6 7-5.6s6.2 2 7 5.6"/>' ),
						'login'     => array( 'ورود', '<path class="d" stroke="none" d="M13.5 4H17a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3.5z"/><path d="M13.5 4H17a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3.5M4.5 12H14M10.5 8.5 14 12l-3.5 3.5"/>' ),
						'gender'    => array( 'جنسیت', '<circle class="d" cx="9" cy="8.5" r="3.2"/><path d="M3.5 19.5c.4-3 2.6-5 5.5-5s5.1 2 5.5 5"/><circle cx="16.6" cy="9.6" r="2.5"/><path d="M16.6 14.6c2.2 0 3.7 1.6 4 4"/>' ),
						'male'      => array( 'مردانه', '<circle class="d" cx="10" cy="14" r="5.5"/><path d="M14 10l5.5-5.5M14.8 4.5h4.7v4.7"/>' ),
						'female'    => array( 'زنانه', '<circle class="d" cx="12" cy="9" r="5.5"/><path d="M12 14.5V21M9 18h6"/>' ),
						'unisex'    => array( 'یونیسکس', '<circle class="d" cx="11" cy="11.5" r="4.8"/><path d="M14.4 8.1 19 3.5M15.2 3.5H19v3.8M11 16.3V21M8.6 18.7h4.8"/>' ),
						'season'    => array( 'فصل', '<path class="d" d="M6.5 17.5a5.5 5.5 0 0 1 11 0z"/><path d="M3 17.5h18M7.5 21h9M12 7.5V10M5.6 11.1l1.5 1.5M18.4 11.1l-1.5 1.5"/>' ),
						'spring'    => array( 'بهار', '<path class="d" d="M7.5 5 10 7.5 12 4.5l2 3L16.5 5v4.5a4.5 4.5 0 0 1-9 0z"/><path d="M12 14v7M12 19c-.6-2.6-2.6-4-5.5-4 .4 2.7 2.4 4 5.5 4zM12 17.6c.5-2 2.1-3 4.5-3-.3 2.1-1.9 3-4.5 3z"/>' ),
						'summer'    => array( 'تابستان', '<circle class="d" cx="12" cy="12" r="4"/><path d="M12 3v1.8M12 19.2V21M3 12h1.8M19.2 12H21M5.6 5.6l1.3 1.3M17.1 17.1l1.3 1.3M5.6 18.4l1.3-1.3M17.1 6.9l1.3-1.3"/>' ),
						'autumn'    => array( 'پاییز', '<path class="d" d="M19.5 4.5c-9 0-14 4.6-14 12.2 0 1 .1 1.9.3 2.8 8.2.3 13.7-5.3 13.7-15z"/><path d="M4 20.5 12.5 12"/>' ),
						'winter'    => array( 'زمستان', '<path d="M12 3v18M4.2 7.5l15.6 9M4.2 16.5l15.6-9M9.8 4.5 12 6.5l2.2-2M9.8 19.5 12 17.5l2.2 2M17.4 6.35l-.64 2.9 2.84.91M4.4 13.85l2.84.9-.64 2.91M17.4 17.65l-.64-2.9 2.84-.9M4.4 10.15l2.84-.9-.64-2.9"/><circle class="d" stroke="none" cx="12" cy="12" r="2.4"/>' ),
						'allseason' => array( 'چهارفصل', '<path d="M19.5 12a7.5 7.5 0 0 1-13 5.1M4.5 12a7.5 7.5 0 0 1 13-5.1M6.5 20.4v-3.3h3.3M17.5 3.6v3.3h-3.3"/><circle class="d" cx="12" cy="12" r="2.6"/>' ),
						'heart'     => array( 'علاقه‌مندی', '<path class="d" d="M12 20s-7.5-4.4-7.5-10.1A4.2 4.2 0 0 1 12 7.4a4.2 4.2 0 0 1 7.5 2.5C19.5 15.6 12 20 12 20z"/>' ),
						'search'    => array( 'جستجو', '<circle class="d" cx="11" cy="11" r="6.5"/><path d="m20 20-4.4-4.4"/>' ),
						'sparkle'   => array( 'ویژه', '<path class="d" d="M12 4.5c.7 4.3 2.7 6.3 7 7-4.3.7-6.3 2.7-7 7-.7-4.3-2.7-6.3-7-7 4.3-.7 6.3-2.7 7-7z"/><path d="M19 3v3M17.5 4.5h3"/>' ),
						'phone'     => array( 'تماس', '<path class="d" d="M6.6 3.8h2.6c.4 0 .7.3.8.6l.9 3.3c.1.4 0 .8-.3 1L9 9.9c1.1 2.2 2.9 4 5.1 5.1l1.2-1.6c.3-.3.7-.4 1-.3l3.3.9c.4.1.6.4.6.8v2.6c0 1.2-1 2.1-2.2 2A15.6 15.6 0 0 1 4.6 6c0-1.2.8-2.2 2-2.2z"/>' ),
						'info'      => array( 'درباره', '<circle class="d" cx="12" cy="12" r="8.5"/><path d="M12 11v5.5M12 7.6h.01"/>' ),
						'crown'     => array( 'برند', '<path class="d" d="M4 8l4 3.5L12 5l4 6.5L20 8l-1.6 9.5H5.6z"/><path d="M6 20.5h12"/>' ),
						'article'   => array( 'مقاله', '<path class="d" d="M6.5 3.5H14L18.5 8v11a1.5 1.5 0 0 1-1.5 1.5H6.5A1.5 1.5 0 0 1 5 19V5a1.5 1.5 0 0 1 1.5-1.5z"/><path d="M14 3.5V8h4.5M8.5 12.5h7M8.5 16h5"/>' ),
						'help'      => array( 'پرسش', '<circle class="d" cx="12" cy="12" r="8.5"/><path d="M9.6 9.5a2.5 2.5 0 0 1 4.8.9c0 1.7-2.4 2.1-2.4 3.6M12 17h.01"/>' ),
						'arrow'     => array( 'فلش', '<circle class="d" cx="12" cy="12" r="8.5"/><path d="M13.5 8.5 10 12l3.5 3.5"/>' ),
						'_close'    => array( '', '<path d="M6.5 6.5l11 11M17.5 6.5l-11 11"/>' ),
						'_down'     => array( '', '<path d="m6.5 9.5 5.5 5.5 5.5-5.5"/>' ),
						'_back'     => array( '', '<path d="m14.5 6-6 6 6 6"/>' ),
					)
				);
			}
			return $icons;
		}

		/**
		 * Built-in icons offered in the settings.
		 *
		 * @return array<string,string> name => label
		 */
		public function icon_options(): array {
			$out = array();
			foreach ( $this->icons() as $name => $icon ) {
				if ( '_' !== $name[0] ) {
					$out[ $name ] = $icon[0];
				}
			}
			return $out;
		}

		public function svg( string $name ): string {
			$icons = $this->icons();
			if ( ! isset( $icons[ $name ][1] ) ) {
				return '';
			}
			return '<svg class="tpfb-svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icons[ $name ][1] . '</svg>';
		}

		/**
		 * An icon chosen in the settings: a built-in one, an Elementor library icon («custom»), or none.
		 *
		 * @param string $preset   Built-in icon name, «custom» or «none».
		 * @param mixed  $custom   Value of the Elementor ICONS control.
		 * @param string $fallback Built-in icon used when the choice is empty.
		 */
		public function icon( $preset, $custom, string $fallback ): string {
			$preset = (string) $preset;
			if ( 'none' === $preset ) {
				return '';
			}
			if ( 'custom' === $preset ) {
				if ( is_array( $custom ) && ! empty( $custom['value'] ) && class_exists( '\Elementor\Icons_Manager' ) ) {
					ob_start();
					\Elementor\Icons_Manager::render_icon( $custom, array( 'aria-hidden' => 'true' ) );
					return (string) ob_get_clean();
				}
				$preset = $fallback;
			}
			$html = $this->svg( $preset );
			return '' !== $html ? $html : $this->svg( $fallback );
		}

		/**
		 * Top-level product categories (for the «دسته‌بندی‌های ووکامرس» group of the sheet).
		 *
		 * @return array<int,array{name:string,url:string,image:string}>
		 */
		public function categories(): array {
			if ( ! taxonomy_exists( 'product_cat' ) ) {
				return array();
			}
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'parent'     => 0,
					'hide_empty' => true,
					'number'     => 40,
					'orderby'    => 'menu_order',
				)
			);
			if ( is_wp_error( $terms ) ) {
				return array();
			}
			$default = (int) get_option( 'default_product_cat', 0 );
			$out     = array();
			foreach ( $terms as $term ) {
				// Not shop categories: «دسته‌بندی نشده» and the wallet plugin's top-up packages.
				if ( $term->term_id === $default || 'fsww-wallet-credit' === $term->slug ) {
					continue;
				}
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				$thumb = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
				$out[] = array(
					'name'  => $term->name,
					'url'   => $link,
					'image' => $thumb ? (string) wp_get_attachment_image_url( $thumb, 'thumbnail' ) : '',
				);
			}
			return (array) apply_filters( 'tpfb_categories', $out );
		}

		/**
		 * Whether a link points at the page being viewed.
		 */
		public function is_current( string $url ): bool {
			if ( '' === $url || '#' === $url[0] ) {
				return false;
			}
			$target  = untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
			$current = untrailingslashit( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH ) );
			$home    = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
			if ( $target === $home ) {
				return $current === $home;
			}
			return $target === $current || 0 === strpos( $current . '/', $target . '/' );
		}

		public function digits( string $text ): string {
			if ( ! apply_filters( 'tpfb_persian_digits', true ) ) {
				return $text;
			}
			return strtr( $text, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
		}

		/**
		 * Number of items on the cart button; WooCommerce swaps it in place after every cart change.
		 */
		public function cart_badge(): string {
			$count = function_exists( 'WC' ) && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
			$text  = $count > 99 ? $this->digits( '99' ) . '+' : $this->digits( (string) $count );
			return '<span class="tpfb__badge tpfb-cart-count' . ( $count ? '' : ' is-empty' ) . '" aria-hidden="true">' . esc_html( $text ) . '</span>';
		}

		public function cart_fragment( $fragments ) {
			if ( is_array( $fragments ) ) {
				$fragments['span.tpfb-cart-count'] = $this->cart_badge();
			}
			return $fragments;
		}

		/**
		 * Style and script shared by every instance, printed with the first one.
		 */
		public function print_assets(): void {
			if ( $this->assets_printed ) {
				return;
			}
			$this->assets_printed = true;
			?>
			<style id="tpfb-css">
			.tpfb,.tpfb *,.tpfb-sheet,.tpfb-sheet *{box-sizing:border-box}
			.tpfb,.tpfb-sheet{--tpfb-brand:#009879;--tpfb-duo:.16;--tpfb-stroke:1.8}
			.tpfb--mono,.tpfb-sheet--mono{--tpfb-duo:0}
			.tpfb-svg{display:block;width:1em;height:1em;fill:none;stroke:currentColor;stroke-width:var(--tpfb-stroke);stroke-linecap:round;stroke-linejoin:round;overflow:visible}
			.tpfb-svg .d{fill:currentColor;fill-opacity:var(--tpfb-duo)}
			/* Bar: two buttons on each side of the cart button; the cart sits in a notch cut out of the bar's background. */
			.tpfb{--tpfb-bg:#fff;--tpfb-color:#7b8794;--tpfb-active:var(--tpfb-brand);--tpfb-line:rgba(15,23,42,.08);--tpfb-h:64px;--tpfb-icon:23px;--tpfb-radius:0px;--tpfb-cart:58px;--tpfb-lift:26px;--tpfb-gap:7px;--tpfb-ring:0px;--tpfb-ring-color:var(--tpfb-bg);--tpfb-cart-bg1:var(--tpfb-brand);--tpfb-cart-bg2:color-mix(in srgb,var(--tpfb-brand),#000 28%);--tpfb-cart-color:#fff;--tpfb-badge-bg:#ff4d5e;--tpfb-badge-color:#fff;--tpfb-safe:env(safe-area-inset-bottom,0px);
				position:fixed;z-index:9990;left:0;right:0;bottom:0;display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);align-items:start;height:calc(var(--tpfb-h) + var(--tpfb-safe));margin:0;padding:0 4px var(--tpfb-safe);direction:rtl}
			.tpfb--no-cart{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}
			.tpfb--flat{--tpfb-ring:4px}
			.tpfb__bg{position:absolute;z-index:-1;inset:0;pointer-events:none;filter:drop-shadow(0 -1px 0 var(--tpfb-line)) drop-shadow(0 -8px 16px rgba(15,23,42,.07))}
			.tpfb__bg::before{content:"";position:absolute;inset:0;border-radius:var(--tpfb-radius) var(--tpfb-radius) 0 0;background:var(--tpfb-bg)}
			.tpfb--notch .tpfb__bg::before{-webkit-mask:radial-gradient(circle at 50% calc(var(--tpfb-cart) / 2 - var(--tpfb-lift)),transparent calc(var(--tpfb-cart) / 2 + var(--tpfb-gap)),#000 calc(var(--tpfb-cart) / 2 + var(--tpfb-gap) + 1px));mask:radial-gradient(circle at 50% calc(var(--tpfb-cart) / 2 - var(--tpfb-lift)),transparent calc(var(--tpfb-cart) / 2 + var(--tpfb-gap)),#000 calc(var(--tpfb-cart) / 2 + var(--tpfb-gap) + 1px))}
			.tpfb--flat .tpfb__bg{filter:none}
			.tpfb--flat .tpfb__bg::before{border-top:1px solid var(--tpfb-line);box-shadow:0 -8px 24px -18px rgba(15,23,42,.35);-webkit-backdrop-filter:saturate(1.4) blur(14px);backdrop-filter:saturate(1.4) blur(14px)}
			.tpfb__side{display:flex;min-width:0}
			.tpfb .tpfb__item{position:relative;display:flex;flex:1 1 0;flex-direction:column;align-items:center;justify-content:center;gap:4px;min-width:0;height:var(--tpfb-h);margin:0;padding:0 2px;border:0;border-radius:0;background:none;box-shadow:none;color:var(--tpfb-color);font:inherit;font-size:11px;font-weight:600;line-height:1.35;letter-spacing:0;text-align:center;text-decoration:none;text-transform:none;cursor:pointer;-webkit-tap-highlight-color:transparent}
			.tpfb .tpfb__item:hover,.tpfb .tpfb__item:focus{background:none;color:var(--tpfb-color);text-decoration:none}
			.tpfb .tpfb__item:focus-visible,.tpfb .tpfb__cart:focus-visible{outline:2px solid var(--tpfb-active);outline-offset:-6px;border-radius:14px}
			.tpfb__icon{display:grid;place-items:center;width:52px;max-width:100%;height:30px;border-radius:999px;font-size:var(--tpfb-icon);line-height:1;transition:background-color .2s,transform .15s}
			.tpfb__icon svg,.tpfb__icon img{width:var(--tpfb-icon);height:var(--tpfb-icon)}
			.tpfb__icon svg:not(.tpfb-svg){fill:currentColor}
			.tpfb .tpfb__item:active .tpfb__icon{transform:scale(.9)}
			.tpfb__label{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
			.tpfb .tpfb__item.is-active,.tpfb .tpfb__item.is-active:hover,.tpfb .tpfb__item[aria-expanded="true"]{color:var(--tpfb-active);font-weight:700}
			.tpfb .tpfb__item.is-active .tpfb__icon,.tpfb .tpfb__item[aria-expanded="true"] .tpfb__icon{background:color-mix(in srgb,var(--tpfb-active) 13%,transparent)}
			/* Cart button: the label lines up with the others; the orb floats above the bar. */
			.tpfb .tpfb__cart{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;width:calc(var(--tpfb-cart) + 18px);height:var(--tpfb-h);margin:0;padding:0;color:var(--tpfb-color);font-size:11px;font-weight:600;line-height:1.35;text-align:center;text-decoration:none;cursor:pointer;-webkit-tap-highlight-color:transparent}
			.tpfb .tpfb__cart:hover,.tpfb .tpfb__cart:focus{color:var(--tpfb-color);text-decoration:none}
			.tpfb__cart-space{display:block;flex:none;height:30px}
			.tpfb__orb{position:absolute;z-index:1;top:calc(var(--tpfb-lift) * -1);left:calc(50% - var(--tpfb-cart) / 2);display:grid;place-items:center;width:var(--tpfb-cart);height:var(--tpfb-cart);border-radius:50%;color:var(--tpfb-cart-color);font-size:calc(var(--tpfb-cart) * .42);line-height:1}
			.tpfb__orb::before{content:"";position:absolute;z-index:-1;inset:0;border-radius:50%;background-color:var(--tpfb-cart-bg1);background-image:linear-gradient(150deg,var(--tpfb-cart-bg1),var(--tpfb-cart-bg2));box-shadow:0 0 0 var(--tpfb-ring) var(--tpfb-ring-color),0 10px 22px -8px color-mix(in srgb,var(--tpfb-cart-bg1) 80%,transparent),inset 0 1px 1px rgba(255,255,255,.3);transition:transform .25s cubic-bezier(.2,.8,.2,1)}
			.tpfb--cart-squircle .tpfb__orb::before{border-radius:34%}
			.tpfb--cart-diamond .tpfb__orb::before{border-radius:30%;transform:rotate(45deg) scale(.84)}
			.tpfb__cart:active .tpfb__orb::before{transform:scale(.92)}
			.tpfb--cart-diamond .tpfb__cart:active .tpfb__orb::before{transform:rotate(45deg) scale(.78)}
			.tpfb__orb-icon svg:not(.tpfb-svg){width:1em;height:1em;fill:currentColor}
			.tpfb__badge{position:absolute;z-index:2;top:1px;left:1px;display:grid;place-items:center;min-width:20px;height:20px;padding:0 5px;border-radius:999px;background:var(--tpfb-badge-bg);box-shadow:0 0 0 2px var(--tpfb-bg);color:var(--tpfb-badge-color);font-size:11px;font-weight:700;line-height:1;direction:ltr}
			.tpfb__badge.is-empty{display:none}
			.tpfb__orb:has(.tpfb__slot [class*="count"],.tpfb__slot [class*="badge"])>.tpfb__badge{display:none}
			/* Shortcode slot (your own mini cart): its trigger fills the orb, flattened to the orb colours, and keeps its own click behaviour.
			   Known triggers by class (.tpmc-trigger, .ngsc-trigger); otherwise the first button or link one or two levels in. */
			.tpfb__slot{position:absolute;inset:0;display:grid;place-items:center;border-radius:inherit;--tpmc-t-icon:calc(var(--tpfb-cart) * .42);--tpmc-c-x:0px;--tpmc-c-y:0px}
			.tpfb .tpfb__slot>:not(button):not(a):has(button,a){display:grid!important;place-items:center;justify-content:stretch;width:100%;height:100%;margin:0;padding:0}
			.tpfb .tpfb__slot :is(.tpmc-trigger,.ngsc-trigger),.tpfb .tpfb__slot>:is(button,a),.tpfb .tpfb__slot>*>:is(button,a){position:relative;display:grid!important;place-items:center;width:100%!important;height:100%!important;min-width:0!important;min-height:0!important;margin:0!important;padding:0!important;border:0!important;border-radius:inherit!important;outline-offset:3px;background:none!important;box-shadow:none!important;color:inherit!important;font-size:inherit!important;transform:none!important;cursor:pointer}
			.tpfb .tpfb__slot :is(.tpmc-trigger__icon,.ngsc-trigger__icon){width:auto!important;height:auto!important;font-size:inherit!important;color:inherit!important}
			.tpfb .tpfb__slot :is(.tpmc-trigger__icon,.ngsc-trigger__icon) svg{width:1em!important;height:1em!important}
			.tpfb .tpfb__slot :is(.tpmc-trigger__text,.ngsc-trigger__text){display:none!important}
			.tpfb .tpfb__slot .tpmc-count{box-shadow:0 0 0 2px var(--tpfb-bg)}
			.tpfb .tpfb__slot .tpmc-count[data-n="0"]{display:none!important}
			/* In the Elementor editor the bar sits in its container, so it can be seen and selected at every width. */
			body.elementor-editor-active .tpfb{position:relative;z-index:1;display:grid!important;max-width:440px;margin:calc(var(--tpfb-lift) + 16px) auto 16px}
			body.elementor-editor-active .tpfb-sheet:not([hidden]){display:block!important}
			/* Sheet */
			.tpfb-sheet{--tpfb-sheet-bg:#f5f7f6;--tpfb-card:#fff;--tpfb-border:rgba(15,23,42,.07);--tpfb-title:#1f2933;--tpfb-text:#6b7680;--tpfb-ink:var(--tpfb-brand);--tpfb-tint:color-mix(in srgb,var(--tpfb-brand) 11%,#fff);--tpfb-tile:color-mix(in srgb,var(--tpfb-brand) 5%,#fff);
				position:fixed;z-index:10000;inset:0;direction:rtl;line-height:1.5}
			.tpfb-sheet[hidden]{display:none!important}
			.tpfb-sheet__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.45);opacity:0;transition:opacity .28s}
			.tpfb-sheet__panel{position:absolute;left:0;right:0;bottom:0;display:flex;flex-direction:column;max-width:560px;max-height:85vh;max-height:85dvh;margin:0 auto;border-radius:26px 26px 0 0;background:var(--tpfb-sheet-bg);box-shadow:0 -12px 40px -12px rgba(15,23,42,.35);transform:translateY(100%);transition:transform .34s cubic-bezier(.2,.8,.2,1)}
			.tpfb-sheet.is-open .tpfb-sheet__backdrop{opacity:1}
			.tpfb-sheet.is-open .tpfb-sheet__panel{transform:none}
			.tpfb-sheet__drag{flex:none;padding:8px 16px 6px;touch-action:none}
			.tpfb-sheet__grip{display:block;width:40px;height:4px;margin:0 auto 10px;border-radius:4px;background:rgba(15,23,42,.15)}
			.tpfb-sheet__head{display:flex;align-items:center;justify-content:space-between;gap:12px}
			.tpfb-sheet__title{color:var(--tpfb-title);font-size:16px;font-weight:800}
			.tpfb-sheet .tpfb-sheet__close,.tpfb-sheet .tpfb-sheet__close:hover,.tpfb-sheet .tpfb-sheet__close:focus{display:grid;place-items:center;width:36px;height:36px;margin:0;padding:0;border:1px solid var(--tpfb-border);border-radius:12px;background:var(--tpfb-card);box-shadow:none;color:var(--tpfb-title);font-size:18px;line-height:1;cursor:pointer}
			.tpfb-sheet__body{flex:1 1 auto;min-height:0;padding:8px 16px calc(18px + env(safe-area-inset-bottom,0px));overflow-y:auto;overscroll-behavior:contain;-webkit-overflow-scrolling:touch}
			.tpfb-sheet .tpfb-card{display:block;margin:0 0 10px;padding:0;border:1px solid var(--tpfb-border);border-radius:18px;background:var(--tpfb-card)}
			.tpfb-sheet .tpfb-row,.tpfb-sheet .tpfb-row:hover{display:flex;align-items:center;gap:12px;padding:12px;color:var(--tpfb-title);list-style:none;text-decoration:none;cursor:pointer;-webkit-tap-highlight-color:transparent}
			.tpfb-sheet .tpfb-row::-webkit-details-marker{display:none}
			.tpfb-sheet .tpfb-row::marker{content:""}
			.tpfb-sheet .is-static>.tpfb-row{cursor:default}
			.tpfb-row__icon{display:grid;place-items:center;flex:none;width:46px;height:46px;border-radius:15px;background:var(--tpfb-tint);color:var(--tpfb-ink);font-size:24px}
			.tpfb-row__text{display:flex;flex:1;flex-direction:column;min-width:0}
			.tpfb-row__title{font-size:14.5px;font-weight:700;line-height:1.6}
			.tpfb-row__sub{overflow:hidden;color:var(--tpfb-text);font-size:12px;font-weight:400;line-height:1.6;white-space:nowrap;text-overflow:ellipsis}
			.tpfb-row__chev{display:grid;place-items:center;flex:none;width:30px;height:30px;border-radius:10px;background:var(--tpfb-sheet-bg);color:var(--tpfb-text);font-size:18px;transition:transform .3s cubic-bezier(.2,.8,.2,1),background-color .2s,color .2s}
			.tpfb-grp[open]>.tpfb-row .tpfb-row__chev{transform:rotate(180deg);background:var(--tpfb-tint);color:var(--tpfb-ink)}
			.tpfb-grp__body{padding:0 12px 12px}
			.tpfb-grp[open]>.tpfb-grp__body{animation:tpfb-drop .3s cubic-bezier(.2,.8,.2,1)}
			@keyframes tpfb-drop{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}
			.tpfb-sheet .tpfb-subs{display:grid;grid-template-columns:repeat(auto-fit,minmax(54px,1fr));gap:6px;margin:0;padding:12px 0 0;border-top:1px dashed var(--tpfb-border);list-style:none}
			.tpfb-sheet .tpfb-subs li{margin:0;padding:0;list-style:none}
			.tpfb-sheet .tpfb-sub{display:flex;flex-direction:column;align-items:center;gap:7px;height:100%;padding:12px 4px 10px;border-radius:14px;background:var(--tpfb-tile);color:var(--tpfb-title);font-size:12px;font-weight:600;line-height:1.4;text-align:center;text-decoration:none;transition:background-color .2s,transform .15s;-webkit-tap-highlight-color:transparent}
			.tpfb-sheet .tpfb-sub:hover{background:var(--tpfb-tint);color:var(--tpfb-title)}
			.tpfb-sheet .tpfb-sub:active{transform:scale(.95)}
			.tpfb-sub__icon{display:grid;place-items:center;flex:none;width:42px;height:42px;overflow:hidden;border-radius:50%;background:var(--tpfb-card);box-shadow:0 4px 12px -5px color-mix(in srgb,var(--tpfb-brand) 55%,transparent);color:var(--tpfb-ink);font-size:22px}
			.tpfb-sheet .tpfb-sub__icon img{display:block;width:100%;height:100%;max-width:none;object-fit:cover}
			.tpfb-row__icon svg:not(.tpfb-svg),.tpfb-sub__icon svg:not(.tpfb-svg){width:1em;height:1em;fill:currentColor}
			.tpfb-sub__arrow{display:none;color:var(--tpfb-text);font-size:16px}
			.tpfb-sheet .tpfb-subs--list{grid-template-columns:minmax(0,1fr);gap:2px}
			.tpfb-sheet .tpfb-subs--list .tpfb-sub{flex-direction:row;gap:12px;padding:8px 6px;background:none;font-size:13.5px;text-align:start}
			.tpfb-sheet .tpfb-subs--list .tpfb-sub:hover{background:var(--tpfb-tile)}
			.tpfb-subs--list .tpfb-sub__icon{width:36px;height:36px;background:var(--tpfb-tint);box-shadow:none;font-size:19px}
			.tpfb-subs--list .tpfb-sub__label{flex:1}
			.tpfb-subs--list .tpfb-sub__arrow{display:block}
			/* Full site menu, after the groups: rows in one card, or a two-column grid of small cards. */
			.tpfb-site{margin:18px 0 0}
			.tpfb-site__title{display:flex;align-items:center;gap:10px;margin:0 4px 8px;color:var(--tpfb-text);font-size:12.5px;font-weight:700}
			.tpfb-site__title::after{content:"";flex:1;height:1px;background:var(--tpfb-border)}
			.tpfb-sheet .tpfb-nav{margin:0 0 10px;padding:2px 0;list-style:none}
			.tpfb-sheet .tpfb-nav li{margin:0;padding:0;list-style:none}
			.tpfb-nav--list>.tpfb-nav__item+.tpfb-nav__item{border-top:1px solid var(--tpfb-border)}
			.tpfb-sheet .tpfb-nav__link,.tpfb-sheet .tpfb-nav__link:hover{display:flex;align-items:center;gap:12px;padding:10px 12px;color:var(--tpfb-title);font-size:14px;font-weight:600;line-height:1.5;list-style:none;text-decoration:none;cursor:pointer;-webkit-tap-highlight-color:transparent}
			.tpfb-sheet .tpfb-nav__link::-webkit-details-marker{display:none}
			.tpfb-sheet .tpfb-nav__link::marker{content:""}
			.tpfb-nav__icon{display:grid;place-items:center;flex:none;width:34px;height:34px;border-radius:11px;background:var(--tpfb-tint);color:var(--tpfb-ink);font-size:19px;transition:background-color .2s,color .2s}
			.tpfb-nav__label{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
			.tpfb-nav__chev{flex:none;color:var(--tpfb-text);font-size:16px;transition:transform .3s cubic-bezier(.2,.8,.2,1)}
			.tpfb-nav__grp[open]>.tpfb-nav__link .tpfb-nav__chev{transform:rotate(180deg)}
			.tpfb-sheet .tpfb-nav__link.is-current{color:var(--tpfb-ink)}
			.tpfb-nav__link.is-current .tpfb-nav__icon{background:var(--tpfb-ink);color:#fff}
			.tpfb-sheet .tpfb-nav__sub{margin:0;padding:0 12px 8px;padding-inline-start:58px;list-style:none}
			.tpfb-nav--no-icons .tpfb-nav__sub{padding-inline-start:24px}
			.tpfb-sheet .tpfb-nav__sub a,.tpfb-sheet .tpfb-nav__sub a:hover{display:block;padding:7px 0;color:var(--tpfb-text);font-size:13px;font-weight:500;text-decoration:none}
			.tpfb-sheet .tpfb-nav__sub a.is-current{color:var(--tpfb-ink);font-weight:700}
			.tpfb-nav__grp[open]>.tpfb-nav__sub{animation:tpfb-drop .3s cubic-bezier(.2,.8,.2,1)}
			.tpfb-sheet .tpfb-nav--grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));grid-auto-flow:row dense;gap:8px;padding:0;border:0;background:none}
			.tpfb-nav--grid>.tpfb-nav__item{border:1px solid var(--tpfb-border);border-radius:14px;background:var(--tpfb-card)}
			.tpfb-nav--grid>.tpfb-nav__item.has-sub{grid-column:1/-1}
			.tpfb-sheet .tpfb-nav--grid .tpfb-nav__link{gap:10px;padding:9px 10px;font-size:13px}
			.tpfb-nav--grid>.tpfb-nav__item:not(.has-sub) .tpfb-nav__chev{display:none}
			html.tpfb-lock{overflow:hidden}
			@media (prefers-reduced-motion:reduce){.tpfb-sheet__panel,.tpfb-sheet__backdrop,.tpfb-row__chev,.tpfb__orb::before{transition:none}.tpfb-grp[open]>.tpfb-grp__body,.tpfb-nav__grp[open]>.tpfb-nav__sub{animation:none}}
			@media print{.tpfb,.tpfb-sheet{display:none!important}}
			</style>
			<script id="tpfb-js">
			(function(){
				'use strict';
				/* The Elementor editor prints this again with every re-render of the widget. */
				if(window.tpfbReady){return;}
				window.tpfbReady=true;
				function each(list,fn){Array.prototype.forEach.call(list,fn);}
				function ready(fn){if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);}else{fn();}}
				ready(function(){
					/* Out of whatever container Elementor put it in: nothing above it can clip or cover a fixed bar. */
					if(!document.body.classList.contains('elementor-editor-active')){
						each(document.querySelectorAll('.tpfb,.tpfb-sheet'),function(el){document.body.appendChild(el);});
					}
				});
				var lastFocus=null,timer=null,drag=null;
				function openers(sheet){return document.querySelectorAll('[data-tpfb-open="'+sheet.id+'"]');}
				function openSheet(sheet){
					clearTimeout(timer);lastFocus=document.activeElement;sheet.hidden=false;void sheet.offsetWidth;sheet.classList.add('is-open');document.documentElement.classList.add('tpfb-lock');
					each(openers(sheet),function(b){b.setAttribute('aria-expanded','true');});
					var c=sheet.querySelector('.tpfb-sheet__close');if(c){c.focus({preventScroll:true});}
				}
				function closeSheet(sheet,instant){
					if(!sheet||!sheet.classList.contains('is-open')){return;}
					sheet.classList.remove('is-open');document.documentElement.classList.remove('tpfb-lock');
					each(openers(sheet),function(b){b.setAttribute('aria-expanded','false');});
					if(instant){sheet.hidden=true;}else{timer=setTimeout(function(){if(!sheet.classList.contains('is-open')){sheet.hidden=true;}},340);}
					if(lastFocus&&lastFocus.focus){lastFocus.focus({preventScroll:true});}lastFocus=null;
				}
				document.addEventListener('click',function(e){
					var t=e.target;if(!t||!t.closest){return;}
					var opener=t.closest('[data-tpfb-open]');
					if(opener){var sheet=document.getElementById(opener.getAttribute('data-tpfb-open'));if(sheet){e.preventDefault();if(sheet.classList.contains('is-open')){closeSheet(sheet);}else{openSheet(sheet);}}return;}
					var closer=t.closest('[data-tpfb-close]');
					if(closer){closeSheet(closer.closest('.tpfb-sheet'));return;}
					/* Cart slot: a tap on the label, or on the orb beside the shortcode's own button, presses that button. */
					var cart=t.closest('.tpfb__cart--slot');
					if(cart&&!t.closest('.tpfb__slot a,.tpfb__slot button,.tpfb__slot [role="button"]')&&(!t.closest('.tpfb__slot')||t.classList.contains('tpfb__slot'))){
						var control=cart.querySelector('.tpfb__slot .tpmc-trigger,.tpfb__slot .ngsc-trigger,.tpfb__slot button,.tpfb__slot a,.tpfb__slot [role="button"]');if(control){control.click();}
					}
				});
				document.addEventListener('keydown',function(e){
					var sheet=document.querySelector('.tpfb-sheet.is-open');if(!sheet){return;}
					if(e.key==='Escape'){closeSheet(sheet);return;}
					if(e.key!=='Tab'){return;}
					/* Keep keyboard focus inside the open sheet. */
					var f=Array.prototype.filter.call(sheet.querySelectorAll('a[href],button,summary'),function(el){return el.offsetParent!==null;});
					if(!f.length){return;}
					var first=f[0],last=f[f.length-1];
					if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}
				});
				/* Groups: optionally one open at a time; a group or submenu opened near the bottom scrolls into view. */
				document.addEventListener('toggle',function(e){
					var d=e.target;if(!d.classList||!(d.classList.contains('tpfb-grp')||d.classList.contains('tpfb-nav__grp'))||!d.open){return;}
					var body=d.closest('.tpfb-sheet__body');if(!body){return;}
					if(d.classList.contains('tpfb-grp')&&body.hasAttribute('data-tpfb-single')){each(body.querySelectorAll('.tpfb-grp[open]'),function(o){if(o!==d){o.open=false;}});}
					requestAnimationFrame(function(){var r=d.getBoundingClientRect(),b=body.getBoundingClientRect(),over=r.bottom-b.bottom+12;if(over>0&&b.height){body.scrollBy({top:Math.min(over,r.top-b.top),behavior:'smooth'});}});
				},true);
				/* Drag the sheet's handle down to close it. */
				document.addEventListener('touchstart',function(e){
					var h=e.target.closest&&e.target.closest('.tpfb-sheet__drag');if(!h||e.touches.length>1){return;}
					drag={panel:h.closest('.tpfb-sheet__panel'),y:e.touches[0].clientY,d:0};drag.panel.style.transition='none';
				},{passive:true});
				document.addEventListener('touchmove',function(e){if(!drag){return;}drag.d=Math.max(0,e.touches[0].clientY-drag.y);drag.panel.style.transform=drag.d?'translateY('+drag.d+'px)':'';},{passive:true});
				function endDrag(){if(!drag){return;}var p=drag.panel,d=drag.d;drag=null;p.style.transition='';p.style.transform='';if(d>80){closeSheet(p.closest('.tpfb-sheet'));}}
				document.addEventListener('touchend',endDrag);
				document.addEventListener('touchcancel',endDrag);
				/* Back to this page from the browser cache: no sheet left open. */
				window.addEventListener('pageshow',function(e){if(e.persisted){each(document.querySelectorAll('.tpfb-sheet.is-open'),function(s){closeSheet(s,true);});}});
			})();
			</script>
			<?php
		}

		public function register_widget( $widgets_manager ): void {
			if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
				return;
			}
			$snippet = $this;

			$widget = new class( array(), null, $snippet ) extends \Elementor\Widget_Base {

				/** @var Techpino_Footer_Bar_Snippet|null */
				private static $snippet = null;

				public function __construct( $data = array(), $args = null, $snippet = null ) {
					if ( $snippet ) {
						self::$snippet = $snippet;
					}
					parent::__construct( $data, $args );
				}

				public function get_name(): string { return 'tp-mobile-footer-bar'; }
				public function get_title(): string { return 'نوار پایین موبایل (تکپینو)'; }
				public function get_icon(): string { return 'eicon-footer'; }
				public function get_categories(): array { return array( 'techpino' ); }
				public function get_keywords(): array { return array( 'footer', 'mobile', 'bar', 'cart', 'فوتر', 'موبایل', 'نوار', 'سبد' ); }

				/* ───────────── Controls ───────────── */

				protected function register_controls(): void {
					$urls = self::$snippet ? self::$snippet->urls() : array( 'home' => '', 'shop' => '', 'cart' => '', 'account' => '' );

					$this->slot_section( 'menu', 'دکمه‌ی منو', 'منو', 'menu', null );
					$this->slot_section( 'home', 'دکمه‌ی صفحه‌ی اصلی', 'صفحه اصلی', 'home', $urls['home'] );

					$this->start_controls_section( 'section_cart', array( 'label' => 'دکمه‌ی سبد خرید (وسط)' ) );
					$on = array( 'cart_show' => 'yes' );
					$this->add_control( 'cart_show', $this->switcher( 'نمایش', 'yes' ) );
					$this->add_control( 'cart_label', array( 'label' => 'عنوان', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'سبد خرید', 'condition' => $on ) );
					$this->add_control(
						'cart_mode',
						array(
							'label'     => 'کار دکمه',
							'type'      => \Elementor\Controls_Manager::SELECT,
							'default'   => 'shortcode',
							'options'   => array(
								'shortcode' => 'شورت‌کد مینی‌کارت خودتان',
								'link'      => 'لینک به صفحه‌ی سبد خرید',
							),
							'condition' => $on,
						)
					);
					$this->add_control(
						'cart_shortcode',
						array(
							'label'       => 'شورت‌کد',
							'type'        => \Elementor\Controls_Manager::TEXTAREA,
							'rows'        => 2,
							'default'     => '[ng_cart_mini_icon]',
							'placeholder' => '[ng_cart_mini_icon]',
							'description' => 'دکمه‌ی همین شورت‌کد داخل دکمه‌ی وسط می‌نشیند و پنجره‌ی خودش را باز می‌کند. اگر شورت‌کد روی سایت نباشد، دکمه به صفحه‌ی سبد خرید می‌رود.',
							'condition'   => $on + array( 'cart_mode' => 'shortcode' ),
						)
					);
					$this->icon_controls( $this, 'cart_icon', 'bag', $on + array( 'cart_mode' => 'link' ) );
					$this->add_control( 'cart_link', $this->url_control( $urls['cart'], $on + array( 'cart_mode' => 'link' ) ) );
					$this->add_control(
						'cart_badge',
						$this->switcher( 'نمایش تعداد کالا', 'yes', $on ) + array( 'description' => 'اگر شورت‌کد خودش عدد را نشان دهد، این عدد خودبه‌خود پنهان می‌شود.' )
					);
					$this->end_controls_section();

					$this->slot_section( 'shop', 'دکمه‌ی فروشگاه', 'فروشگاه', 'shop', $urls['shop'] );

					$this->start_controls_section( 'section_account', array( 'label' => 'دکمه‌ی حساب کاربری' ) );
					$on = array( 'account_show' => 'yes' );
					$this->add_control( 'account_show', $this->switcher( 'نمایش', 'yes' ) );
					$this->add_control( 'account_guest_heading', array( 'label' => 'کاربری که وارد نشده', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before', 'condition' => $on ) );
					$this->add_control( 'account_guest_label', array( 'label' => 'عنوان', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'ورود | ثبت‌نام', 'condition' => $on ) );
					$this->icon_controls( $this, 'account_guest_icon', 'login', $on );
					$this->add_control( 'account_guest_link', $this->url_control( $urls['account'], $on ) );
					$this->add_control(
						'account_guest_class',
						array(
							'label'       => 'کلاس CSS اضافه',
							'type'        => \Elementor\Controls_Manager::TEXT,
							'description' => 'اگر افزونه‌ی ورودتان (مثلاً ورود با پیامک) با یک کلاس پاپ‌آپ باز می‌کند، آن کلاس را اینجا بنویسید.',
							'condition'   => $on,
						)
					);
					$this->add_control( 'account_user_heading', array( 'label' => 'کاربری که وارد شده', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before', 'condition' => $on ) );
					$this->add_control( 'account_user_label', array( 'label' => 'عنوان', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'حساب کاربری', 'condition' => $on ) );
					$this->add_control( 'account_user_name', $this->switcher( 'نام کاربر به جای عنوان', '', $on ) );
					$this->icon_controls( $this, 'account_user_icon', 'user', $on );
					$this->add_control( 'account_user_link', $this->url_control( $urls['account'], $on ) );
					$this->end_controls_section();

					$this->sheet_section( $urls );
					$this->site_menu_section();

					$this->start_controls_section( 'section_general', array( 'label' => 'تنظیمات کلی' ) );
					$this->add_control( 'show_labels', $this->switcher( 'نمایش عنوان‌ها', 'yes' ) );
					$this->add_control(
						'bar_shape',
						array(
							'label'   => 'شکل نوار',
							'type'    => \Elementor\Controls_Manager::SELECT,
							'default' => 'notch',
							'options' => array(
								'notch' => 'با فرورفتگی دور دکمه‌ی وسط',
								'flat'  => 'صاف',
							),
						)
					);
					$this->add_control(
						'cart_shape',
						array(
							'label'   => 'شکل دکمه‌ی وسط',
							'type'    => \Elementor\Controls_Manager::SELECT,
							'default' => 'circle',
							'options' => array(
								'circle'   => 'دایره',
								'squircle' => 'مربع گوشه‌گرد',
								'diamond'  => 'لوزی',
							),
						)
					);
					$this->add_control( 'duotone', $this->switcher( 'آیکون‌های دورنگ', 'yes' ) + array( 'description' => 'بخشی از هر آیکون داخلی با رنگ کم‌رنگ پر می‌شود.' ) );
					$this->add_control(
						'max_width',
						array(
							'label'       => 'نمایش تا عرض (پیکسل)',
							'type'        => \Elementor\Controls_Manager::NUMBER,
							'default'     => 767,
							'min'         => 0,
							'description' => 'نوار فقط در صفحه‌هایی باریک‌تر از این عرض دیده می‌شود. صفر یعنی در همه‌ی اندازه‌ها.',
						)
					);
					$this->end_controls_section();

					$this->style_sections();
				}

				/**
				 * A plain bar button: show, label, icon and (for links) target and extra class.
				 */
				private function slot_section( string $key, string $title, string $label, string $icon, ?string $url ): void {
					$on = array( $key . '_show' => 'yes' );
					$this->start_controls_section( 'section_' . $key, array( 'label' => $title ) );
					$this->add_control( $key . '_show', $this->switcher( 'نمایش', 'yes' ) );
					$this->add_control( $key . '_label', array( 'label' => 'عنوان', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => $label, 'condition' => $on ) );
					$this->icon_controls( $this, $key . '_icon', $icon, $on );
					if ( null === $url ) {
						$this->add_control( $key . '_note', array( 'type' => \Elementor\Controls_Manager::RAW_HTML, 'raw' => 'این دکمه پنجره‌ی پایین را باز می‌کند؛ گروه‌ها و لینک‌های آن در بخش «پنجره‌ی منو» تنظیم می‌شوند.', 'content_classes' => 'elementor-descriptor', 'condition' => $on ) );
					} else {
						$this->add_control( $key . '_link', $this->url_control( $url, $on ) );
						$this->add_control( $key . '_class', array( 'label' => 'کلاس CSS اضافه', 'type' => \Elementor\Controls_Manager::TEXT, 'condition' => $on ) );
					}
					$this->end_controls_section();
				}

				private function sheet_section( array $urls ): void {
					$this->start_controls_section( 'section_sheet', array( 'label' => 'پنجره‌ی منو' ) );
					$this->add_control( 'sheet_title', array( 'label' => 'عنوان پنجره', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'منو' ) );
					$cat = function ( string $path ): array {
						return array( 'url' => home_url( '/product-category/' . $path . '/' ) );
					};

					$repeater = new \Elementor\Repeater();
					$repeater->add_control(
						'entry_type',
						array(
							'label'   => 'نوع',
							'type'    => \Elementor\Controls_Manager::SELECT,
							'default' => 'item',
							'options' => array(
								'group'   => 'گروه (زیرگروه‌ها ردیف‌های بعدی‌اند)',
								'item'    => 'زیرگروهِ گروه بالایی',
								'link'    => 'لینک مستقل',
								'wc_cats' => 'گروه خودکار: دسته‌بندی‌های ووکامرس',
							),
						)
					);
					$repeater->add_control( 'label', array( 'label' => 'عنوان', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'عنوان', 'label_block' => true ) );
					$this->icon_controls( $repeater, 'icon', 'bottle' );
					$repeater->add_control(
						'link',
						$this->url_control( '', array( 'entry_type' => array( 'item', 'link' ) ) ) + array( 'description' => 'خالی بماند: محصولات با همین عنوان جستجو می‌شوند.' )
					);
					$repeater->add_control( 'group_open', $this->switcher( 'در شروع باز باشد', '', array( 'entry_type' => array( 'group', 'wc_cats' ) ) ) );

					$this->add_control(
						'entries',
						array(
							'label'       => 'گروه‌ها و لینک‌ها',
							'type'        => \Elementor\Controls_Manager::REPEATER,
							'fields'      => $repeater->get_controls(),
							'title_field' => "{{{ entry_type === 'group' ? '▾ ' : ( entry_type === 'wc_cats' ? '⟳ ' : ( entry_type === 'link' ? '↗ ' : '\u{2003}• ' ) ) }}}{{ label }}",
							'default'     => array(
								array( 'entry_type' => 'group', 'label' => 'خرید عطر بر اساس جنسیت', 'icon_preset' => 'gender' ),
								array( 'entry_type' => 'item', 'label' => 'مردانه', 'icon_preset' => 'male', 'link' => $cat( 'mens-perfume' ) ),
								array( 'entry_type' => 'item', 'label' => 'زنانه', 'icon_preset' => 'female', 'link' => $cat( 'female-perfume' ) ),
								array( 'entry_type' => 'item', 'label' => 'یونیسکس', 'icon_preset' => 'unisex', 'link' => $cat( 'unisex' ) ),
								array( 'entry_type' => 'group', 'label' => 'خرید عطر بر اساس فصل', 'icon_preset' => 'season' ),
								array( 'entry_type' => 'item', 'label' => 'بهار', 'icon_preset' => 'spring', 'link' => $cat( 'seasons/perfume-for-spring' ) ),
								array( 'entry_type' => 'item', 'label' => 'تابستان', 'icon_preset' => 'summer', 'link' => $cat( 'seasons/perfume-for-summer' ) ),
								array( 'entry_type' => 'item', 'label' => 'پاییز', 'icon_preset' => 'autumn', 'link' => $cat( 'seasons/autumn-season-perfume' ) ),
								array( 'entry_type' => 'item', 'label' => 'زمستان', 'icon_preset' => 'winter', 'link' => $cat( 'seasons/winter-fragrance' ) ),
								array( 'entry_type' => 'item', 'label' => '۴ فصل', 'icon_preset' => 'allseason', 'link' => $cat( 'seasons/perfume-four-seasons' ) ),
								array( 'entry_type' => 'link', 'label' => 'همه محصولات', 'icon_preset' => 'bottle', 'link' => array( 'url' => $urls['shop'] ) ),
							),
						)
					);
					$this->add_control(
						'sheet_mode',
						array(
							'label'   => 'نمایش گروه‌ها',
							'type'    => \Elementor\Controls_Manager::SELECT,
							'default' => 'accordion',
							'options' => array(
								'accordion' => 'بسته؛ با لمس باز می‌شوند',
								'open'      => 'همه باز',
							),
						)
					);
					$this->add_control( 'sheet_single', $this->switcher( 'هر بار فقط یک گروه باز', 'yes', array( 'sheet_mode' => 'accordion' ) ) );
					$this->add_control( 'sheet_summary', $this->switcher( 'نام زیرگروه‌ها زیر عنوان گروه', 'yes' ) );
					$this->add_control(
						'sub_layout',
						array(
							'label'   => 'چیدمان زیرگروه‌ها',
							'type'    => \Elementor\Controls_Manager::SELECT,
							'default' => 'grid',
							'options' => array(
								'grid' => 'کاشی (آیکون بالای عنوان)',
								'list' => 'فهرست',
							),
						)
					);
					$this->end_controls_section();
				}

				/**
				 * The full site menu at the end of the sheet; its items are edited in نمایش › فهرست‌ها.
				 */
				private function site_menu_section(): void {
					$on      = array( 'site_menu_show' => 'yes' );
					$sources = array( 'location' => 'مکان «نوار پایین موبایل (منوی کامل)»' );
					foreach ( (array) wp_get_nav_menus() as $menu ) {
						$sources[ 'menu-' . $menu->term_id ] = 'فهرست: ' . $menu->name;
					}
					$this->start_controls_section( 'section_site_menu', array( 'label' => 'منوی کامل سایت (داخل پنجره)' ) );
					$this->add_control( 'site_menu_show', $this->switcher( 'نمایش', 'yes' ) );
					$this->add_control( 'site_menu_title', array( 'label' => 'عنوان بخش', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'منوی سایت', 'condition' => $on ) );
					$this->add_control(
						'site_menu_source',
						array(
							'label'       => 'فهرست',
							'type'        => \Elementor\Controls_Manager::SELECT,
							'default'     => 'location',
							'options'     => $sources,
							'label_block' => true,
							'condition'   => $on,
						)
					);
					$this->add_control(
						'site_menu_layout',
						array(
							'label'     => 'چیدمان',
							'type'      => \Elementor\Controls_Manager::SELECT,
							'default'   => 'list',
							'options'   => array(
								'list' => 'فهرست زیر هم',
								'grid' => 'دو ستونی',
							),
							'condition' => $on,
						)
					);
					$this->add_control( 'site_menu_icons', $this->switcher( 'آیکون کنار هر آیتم', 'yes', $on ) );
					$names = implode( '، ', array_keys( self::$snippet ? self::$snippet->icon_options() : array() ) );
					$this->add_control(
						'site_menu_note',
						array(
							'type'            => \Elementor\Controls_Manager::RAW_HTML,
							'raw'             => 'آیتم‌ها در <a href="' . esc_url( admin_url( 'nav-menus.php' ) ) . '" target="_blank">نمایش › فهرست‌ها</a> مدیریت می‌شوند (زیرمنو هم پشتیبانی می‌شود). تا وقتی برای این مکان فهرستی انتخاب نشده، صفحه اصلی، تماس با ما، درباره ما، فروشگاه، برندها و مقالات نمایش داده می‌شوند.<br><br>آیکون هر آیتم از روی عنوانش انتخاب می‌شود. برای عوض کردنش، در «کلاس‌های CSS» همان آیتم بنویسید <code>tpfb-icon-</code> و نام آیکون، مثلاً <code>tpfb-icon-phone</code>. نام‌ها: ' . esc_html( $names ),
							'content_classes' => 'elementor-descriptor',
							'condition'       => $on,
						)
					);
					$this->end_controls_section();
				}

				private function style_sections(): void {
					$both  = '.tpfb.tpfb--{{ID}}, .tpfb-sheet.tpfb-sheet--{{ID}}';
					$bar   = '.tpfb.tpfb--{{ID}}';
					$sheet = '.tpfb-sheet.tpfb-sheet--{{ID}}';

					$this->start_controls_section( 'section_style_brand', array( 'label' => 'رنگ اصلی و آیکون‌ها', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
					$this->colors( array( 'brand_color' => array( 'رنگ اصلی (پیش‌فرض #009879)', '--tpfb-brand' ) ), $both );
					$this->add_control( 'icon_stroke', $this->slider( 'ضخامت خط آیکون‌ها', 1, 2.6, 0.1, array( $both => '--tpfb-stroke: {{SIZE}};' ) ) );
					$this->end_controls_section();

					$this->start_controls_section( 'section_style', array( 'label' => 'نوار', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
					$this->colors(
						array(
							'bar_bg'     => array( 'پس‌زمینه', '--tpfb-bg' ),
							'bar_color'  => array( 'رنگ آیکون و متن', '--tpfb-color' ),
							'bar_active' => array( 'رنگ دکمه‌ی صفحه‌ی فعلی', '--tpfb-active' ),
							'bar_line'   => array( 'رنگ خط و سایه‌ی بالای نوار', '--tpfb-line' ),
						),
						$bar
					);
					$this->add_control(
						'bar_height',
						$this->slider(
							'ارتفاع نوار',
							48,
							90,
							1,
							array(
								$bar   => '--tpfb-h: {{SIZE}}{{UNIT}};',
								'body' => '--tpfb-room: {{SIZE}}{{UNIT}};',
							)
						)
					);
					$this->add_control( 'bar_radius', $this->slider( 'گردی گوشه‌های بالا', 0, 32, 1, array( $bar => '--tpfb-radius: {{SIZE}}{{UNIT}};' ) ) );
					$this->add_control( 'icon_size', $this->slider( 'اندازه‌ی آیکون', 14, 36, 1, array( $bar => '--tpfb-icon: {{SIZE}}{{UNIT}};' ) ) );
					$this->add_group_control(
						\Elementor\Group_Control_Typography::get_type(),
						array(
							'name'     => 'label_typography',
							'label'    => 'تایپوگرافی عنوان',
							'selector' => $bar . ' .tpfb__item, ' . $bar . ' .tpfb__cart',
						)
					);
					$this->end_controls_section();

					$this->start_controls_section( 'section_style_cart', array( 'label' => 'دکمه‌ی سبد خرید', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
					$this->add_control( 'cart_size', $this->slider( 'اندازه', 44, 80, 1, array( $bar => '--tpfb-cart: {{SIZE}}{{UNIT}};' ) ) );
					$this->add_control( 'cart_lift', $this->slider( 'بیرون‌زدگی از نوار', 0, 44, 1, array( $bar => '--tpfb-lift: {{SIZE}}{{UNIT}};' ) ) );
					$this->add_control( 'cart_gap', $this->slider( 'فاصله تا لبه‌ی فرورفتگی', 0, 16, 1, array( $bar => '--tpfb-gap: {{SIZE}}{{UNIT}};' ) ) + array( 'condition' => array( 'bar_shape' => 'notch' ) ) );
					$this->add_control( 'cart_ring', $this->slider( 'ضخامت حلقه‌ی دور دکمه', 0, 10, 1, array( $bar => '--tpfb-ring: {{SIZE}}{{UNIT}};' ) ) );
					$this->colors(
						array(
							'cart_ring_color' => array( 'رنگ حلقه', '--tpfb-ring-color' ),
							'cart_bg1'        => array( 'رنگ دکمه (شروع گرادیان)', '--tpfb-cart-bg1' ),
							'cart_bg2'        => array( 'رنگ دکمه (پایان گرادیان)', '--tpfb-cart-bg2' ),
							'cart_color'      => array( 'رنگ آیکون', '--tpfb-cart-color' ),
							'badge_bg'        => array( 'پس‌زمینه‌ی عدد', '--tpfb-badge-bg' ),
							'badge_color'     => array( 'رنگ عدد', '--tpfb-badge-color' ),
						),
						$bar
					);
					$this->end_controls_section();

					$this->start_controls_section( 'section_style_sheet', array( 'label' => 'پنجره‌ی منو', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
					$this->colors(
						array(
							'sheet_bg'          => array( 'پس‌زمینه‌ی پنجره', '--tpfb-sheet-bg' ),
							'sheet_card'        => array( 'پس‌زمینه‌ی کارت‌ها', '--tpfb-card' ),
							'sheet_border'      => array( 'رنگ حاشیه‌ها', '--tpfb-border' ),
							'sheet_title_color' => array( 'رنگ عنوان‌ها', '--tpfb-title' ),
							'sheet_text_color'  => array( 'رنگ متن کمرنگ', '--tpfb-text' ),
							'sheet_ink'         => array( 'رنگ آیکون‌ها', '--tpfb-ink' ),
							'sheet_tint'        => array( 'پس‌زمینه‌ی آیکون گروه‌ها', '--tpfb-tint' ),
							'sheet_tile'        => array( 'پس‌زمینه‌ی کاشی زیرگروه‌ها', '--tpfb-tile' ),
						),
						$sheet
					);
					$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'sheet_group_typography', 'label' => 'تایپوگرافی عنوان گروه‌ها', 'selector' => $sheet . ' .tpfb-row__title' ) );
					$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'sheet_sub_typography', 'label' => 'تایپوگرافی زیرگروه‌ها', 'selector' => $sheet . ' .tpfb-sub' ) );
					$this->end_controls_section();
				}

				/**
				 * Built-in icon choice plus an Elementor icon picker shown for «custom».
				 *
				 * @param \Elementor\Controls_Stack|\Elementor\Repeater $stack
				 */
				private function icon_controls( $stack, string $key, string $default, array $condition = array() ): void {
					$options = ( self::$snippet ? self::$snippet->icon_options() : array() ) + array(
						'custom' => '— آیکون دلخواه از کتابخانه',
						'none'   => '— بدون آیکون',
					);
					$stack->add_control( $key . '_preset', array( 'label' => 'آیکون', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => $default, 'options' => $options, 'condition' => $condition ) );
					$stack->add_control( $key, array( 'label' => 'آیکون دلخواه', 'type' => \Elementor\Controls_Manager::ICONS, 'skin' => 'inline', 'label_block' => false, 'condition' => $condition + array( $key . '_preset' => 'custom' ) ) );
				}

				private function switcher( string $label, string $default, array $condition = array() ): array {
					return array( 'label' => $label, 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => $default, 'condition' => $condition );
				}

				private function url_control( string $url, array $condition ): array {
					return array(
						'label'       => 'لینک',
						'type'        => \Elementor\Controls_Manager::URL,
						'placeholder' => 'https://',
						'default'     => array( 'url' => $url ),
						'dynamic'     => array( 'active' => true ),
						'condition'   => $condition,
					);
				}

				private function slider( string $label, float $min, float $max, float $step, array $selectors ): array {
					return array(
						'label'      => $label,
						'type'       => \Elementor\Controls_Manager::SLIDER,
						'size_units' => array( 'px' ),
						'range'      => array( 'px' => array( 'min' => $min, 'max' => $max, 'step' => $step ) ),
						'selectors'  => $selectors,
					);
				}

				/**
				 * @param array<string,array{0:string,1:string}> $map control => [label, CSS variable]
				 */
				private function colors( array $map, string $selector ): void {
					foreach ( $map as $key => $color ) {
						$this->add_control( $key, array( 'label' => $color[0], 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( $selector => $color[1] . ': {{VALUE}};' ) ) );
					}
				}

				/* ───────────── Output ───────────── */

				protected function render(): void {
					$snippet = self::$snippet;
					if ( ! $snippet ) {
						return;
					}
					$s        = $this->get_settings_for_display();
					$id       = $this->get_id();
					$sheet_id = 'tpfb-menu-' . $id;
					$urls     = $snippet->urls();
					$labels   = 'yes' === ( $s['show_labels'] ?? 'yes' );
					$max      = isset( $s['max_width'] ) && '' !== $s['max_width'] ? absint( $s['max_width'] ) : 767;
					$menu     = 'yes' === ( $s['menu_show'] ?? 'yes' );
					$tree     = $menu ? $this->menu_tree( $s['entries'] ?? array() ) : array();
					$site     = $menu && 'yes' === ( $s['site_menu_show'] ?? 'yes' ) ? $snippet->site_menu( (string) ( $s['site_menu_source'] ?? 'location' ) ) : array();
					$cart     = 'yes' === ( $s['cart_show'] ?? 'yes' );
					$mono     = 'yes' !== ( $s['duotone'] ?? 'yes' );

					$snippet->print_assets();

					// Shown only up to the chosen width; there the page keeps room for the bar under its last line.
					$room = 'body:not(.elementor-editor-active){padding-bottom:calc(var(--tpfb-room,64px) + 12px + env(safe-area-inset-bottom,0px))!important}';
					$css  = $max > 0
						? '@media (min-width:' . ( $max + 1 ) . 'px){.tpfb.tpfb--' . $id . ',.tpfb-sheet.tpfb-sheet--' . $id . '{display:none!important}}@media (max-width:' . $max . 'px){' . $room . '}'
						: $room;
					echo '<style>' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from integers and the element id.

					$classes = array(
						'tpfb',
						'tpfb--' . $id,
						$cart && 'flat' !== ( $s['bar_shape'] ?? 'notch' ) ? 'tpfb--notch' : 'tpfb--flat',
						'tpfb--cart-' . sanitize_html_class( (string) ( $s['cart_shape'] ?? 'circle' ) ),
						$cart ? '' : 'tpfb--no-cart',
						$mono ? 'tpfb--mono' : '',
					);

					$right = array();
					if ( $tree || $site ) {
						$right[] = $this->bar_item(
							'button',
							array( 'type' => 'button', 'data-tpfb-open' => $sheet_id, 'aria-haspopup' => 'dialog', 'aria-expanded' => 'false', 'aria-controls' => $sheet_id ),
							$snippet->icon( $s['menu_icon_preset'] ?? '', $s['menu_icon'] ?? array(), 'menu' ),
							(string) ( $s['menu_label'] ?? '' ),
							$labels,
							false
						);
					}
					if ( 'yes' === ( $s['home_show'] ?? 'yes' ) ) {
						$right[] = $this->link_item( $s, 'home', $urls['home'], 'home', $labels, false );
					}
					$left = array();
					if ( 'yes' === ( $s['shop_show'] ?? 'yes' ) ) {
						// Shop pages, category archives and single products all count as «in the shop».
						$in_shop = function_exists( 'is_woocommerce' ) && is_woocommerce();
						$left[]  = $this->link_item( $s, 'shop', $urls['shop'], 'shop', $labels, $in_shop );
					}
					if ( 'yes' === ( $s['account_show'] ?? 'yes' ) ) {
						$left[] = $this->account_item( $s, $urls['account'], $labels );
					}
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- the parts are escaped where they are built.
					?>
					<nav class="<?php echo esc_attr( implode( ' ', array_filter( $classes ) ) ); ?>" aria-label="دسترسی سریع">
						<span class="tpfb__bg" aria-hidden="true"></span>
						<div class="tpfb__side"><?php echo implode( '', $right ); ?></div>
						<?php echo $cart ? $this->cart_item( $s, $urls['cart'], $labels ) : ''; ?>
						<div class="tpfb__side"><?php echo implode( '', $left ); ?></div>
					</nav>
					<?php
					// phpcs:enable
					if ( $tree || $site ) {
						$this->render_sheet( $tree, $site, $s, $sheet_id, $id, $mono );
					}
				}

				/**
				 * Sheet entries as groups (with their sub-items) and standalone links.
				 * An «item» row belongs to the group above it; before any group, or after a standalone link, it stands alone.
				 */
				private function menu_tree( $entries ): array {
					$snippet = self::$snippet;
					$tree    = array();
					$group   = null;
					foreach ( (array) $entries as $entry ) {
						$type  = $entry['entry_type'] ?? 'item';
						$label = trim( (string) ( $entry['label'] ?? '' ) );
						$icon  = $snippet->icon( $entry['icon_preset'] ?? '', $entry['icon'] ?? array(), 'bottle' );
						if ( 'group' === $type || 'wc_cats' === $type ) {
							$children = array();
							if ( 'wc_cats' === $type ) {
								foreach ( $snippet->categories() as $category ) {
									$children[] = array(
										'label' => $category['name'],
										'attrs' => array( 'href' => $category['url'] ),
										'icon'  => $category['image'] ? '<img src="' . esc_url( $category['image'] ) . '" alt="" loading="lazy" width="42" height="42">' : $snippet->svg( 'bottle' ),
									);
								}
							}
							$tree[] = array( 'type' => 'group', 'label' => $label, 'icon' => $icon, 'open' => 'yes' === ( $entry['group_open'] ?? '' ), 'children' => $children );
							$group  = count( $tree ) - 1;
							continue;
						}
						if ( '' === $label ) {
							continue;
						}
						$search = add_query_arg( array( 's' => rawurlencode( $label ), 'post_type' => 'product' ), home_url( '/' ) );
						$node   = array( 'label' => $label, 'icon' => $icon, 'attrs' => $this->link_attrs( $entry['link'] ?? array(), $search ) );
						if ( 'item' === $type && null !== $group ) {
							$tree[ $group ]['children'][] = $node;
						} else {
							$tree[] = array( 'type' => 'link' ) + $node;
							$group  = 'link' === $type ? null : $group;
						}
					}
					// A group with nothing under it is left out.
					return array_values(
						array_filter(
							$tree,
							function ( $node ) {
								return 'link' === $node['type'] || $node['children'];
							}
						)
					);
				}

				/**
				 * href / target / rel of an Elementor URL control value.
				 *
				 * @param mixed $link
				 */
				private function link_attrs( $link, string $fallback ): array {
					$url = is_array( $link ) ? trim( (string) ( $link['url'] ?? '' ) ) : '';
					if ( '' === $url ) {
						$url = $fallback;
					}
					$external = is_array( $link ) && ! empty( $link['is_external'] );
					$rel      = array_filter( array( $external ? 'noopener' : '', is_array( $link ) && ! empty( $link['nofollow'] ) ? 'nofollow' : '' ) );
					return array(
						'href'   => '' !== $url ? $url : '#',
						'target' => $external ? '_blank' : '',
						'rel'    => implode( ' ', $rel ),
					);
				}

				private function attrs( array $attrs ): string {
					$html = '';
					foreach ( $attrs as $name => $value ) {
						if ( '' === (string) $value ) {
							continue;
						}
						$html .= ' ' . $name . '="' . ( 'href' === $name ? esc_url( (string) $value ) : esc_attr( (string) $value ) ) . '"';
					}
					return $html;
				}

				private function classes( $value ): string {
					return implode( ' ', array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY ) ) ) );
				}

				private function bar_item( string $tag, array $attrs, string $icon, string $label, bool $labels, bool $active, string $class = '' ): string {
					$attrs = array( 'class' => trim( 'tpfb__item' . ( $active ? ' is-active' : '' ) . ' ' . $class ) ) + $attrs;
					if ( ! $labels ) {
						$attrs['aria-label'] = $label;
					}
					if ( $active && 'a' === $tag ) {
						$attrs['aria-current'] = 'page';
					}
					$html = '<' . $tag . $this->attrs( $attrs ) . '><span class="tpfb__icon">' . $icon . '</span>';
					if ( $labels && '' !== $label ) {
						$html .= '<span class="tpfb__label">' . esc_html( $label ) . '</span>';
					}
					return $html . '</' . $tag . '>';
				}

				private function link_item( array $s, string $key, string $url, string $icon, bool $labels, bool $active ): string {
					$snippet = self::$snippet;
					$attrs   = $this->link_attrs( $s[ $key . '_link' ] ?? array(), $url );
					return $this->bar_item(
						'a',
						$attrs,
						$snippet->icon( $s[ $key . '_icon_preset' ] ?? '', $s[ $key . '_icon' ] ?? array(), $icon ),
						(string) ( $s[ $key . '_label' ] ?? '' ),
						$labels,
						$active || $snippet->is_current( $attrs['href'] ),
						$this->classes( $s[ $key . '_class' ] ?? '' )
					);
				}

				/**
				 * «ورود | ثبت‌نام» for visitors, «حساب کاربری» (or the user's name) once logged in.
				 */
				private function account_item( array $s, string $url, bool $labels ): string {
					$snippet = self::$snippet;
					$user    = is_user_logged_in();
					$key     = $user ? 'account_user' : 'account_guest';
					$label   = (string) ( $s[ $key . '_label' ] ?? '' );
					if ( $user && 'yes' === ( $s['account_user_name'] ?? '' ) ) {
						$current = wp_get_current_user();
						$name    = trim( (string) $current->first_name );
						$label   = '' !== $name ? $name : ( '' !== (string) $current->display_name ? (string) $current->display_name : $label );
					}
					$attrs = $this->link_attrs( $s[ $key . '_link' ] ?? array(), $url );
					return $this->bar_item(
						'a',
						$attrs,
						$snippet->icon( $s[ $key . '_icon_preset' ] ?? '', $s[ $key . '_icon' ] ?? array(), $user ? 'user' : 'login' ),
						$label,
						$labels,
						$snippet->is_current( $attrs['href'] ) || ( function_exists( 'is_account_page' ) && is_account_page() ),
						$user ? '' : $this->classes( $s['account_guest_class'] ?? '' )
					);
				}

				/**
				 * The raised middle button: your mini-cart shortcode, or a link to the cart page.
				 */
				private function cart_item( array $s, string $url, bool $labels ): string {
					$snippet = self::$snippet;
					$label   = (string) ( $s['cart_label'] ?? '' );
					$text    = $labels && '' !== $label ? '<span class="tpfb__label">' . esc_html( $label ) . '</span>' : '';
					$badge   = '';
					if ( 'yes' === ( $s['cart_badge'] ?? 'yes' ) && function_exists( 'WC' ) ) {
						$badge = $snippet->cart_badge();
						// Since WooCommerce 7.8 the script that refreshes cart counts is only loaded where something asks for it.
						if ( wp_script_is( 'wc-cart-fragments', 'registered' ) ) {
							wp_enqueue_script( 'wc-cart-fragments' );
						}
					}
					$code = trim( (string) ( $s['cart_shortcode'] ?? '' ) );
					if ( 'shortcode' === ( $s['cart_mode'] ?? 'shortcode' ) && preg_match( '/\[([\w-]+)/', $code, $tag ) && shortcode_exists( $tag[1] ) ) {
						return '<div class="tpfb__cart tpfb__cart--slot"><div class="tpfb__orb"><div class="tpfb__slot">' . do_shortcode( shortcode_unautop( $code ) ) . '</div>' . $badge . '</div><span class="tpfb__cart-space"></span>' . $text . '</div>';
					}
					$attrs = array( 'class' => 'tpfb__cart' ) + $this->link_attrs( $s['cart_link'] ?? array(), $url );
					if ( ! $labels ) {
						$attrs['aria-label'] = $label;
					}
					$icon = $snippet->icon( $s['cart_icon_preset'] ?? '', $s['cart_icon'] ?? array(), 'bag' );
					return '<a' . $this->attrs( $attrs ) . '><span class="tpfb__orb"><span class="tpfb__orb-icon">' . $icon . '</span>' . $badge . '</span><span class="tpfb__cart-space"></span>' . $text . '</a>';
				}

				/**
				 * «منوی سایت» under the groups; an item with a submenu opens in place (open already on its own pages).
				 */
				private function site_menu_html( array $site, array $s ): string {
					$snippet = self::$snippet;
					$icons   = 'yes' === ( $s['site_menu_icons'] ?? 'yes' );
					$grid    = 'grid' === ( $s['site_menu_layout'] ?? 'list' );
					$title   = trim( (string) ( $s['site_menu_title'] ?? '' ) );
					$back    = $snippet->svg( '_back' );
					$html    = '<nav class="tpfb-site" aria-label="' . esc_attr( '' !== $title ? $title : 'منوی سایت' ) . '">';
					if ( '' !== $title ) {
						$html .= '<div class="tpfb-site__title">' . esc_html( $title ) . '</div>';
					}
					$html .= '<ul class="tpfb-nav ' . ( $grid ? 'tpfb-nav--grid' : 'tpfb-card tpfb-nav--list' ) . ( $icons ? '' : ' tpfb-nav--no-icons' ) . '">';
					foreach ( $site as $item ) {
						$inner = ( $icons ? '<span class="tpfb-nav__icon">' . $snippet->icon( $item['icon'], null, 'arrow' ) . '</span>' : '' ) . '<span class="tpfb-nav__label">' . esc_html( $item['label'] ) . '</span>';
						if ( ! $item['children'] ) {
							$html .= '<li class="tpfb-nav__item"><a class="tpfb-nav__link' . ( $item['current'] ? ' is-current' : '' ) . '"' . $this->nav_attrs( $item ) . '>' . $inner . '<span class="tpfb-nav__chev" aria-hidden="true">' . $back . '</span></a></li>';
							continue;
						}
						$open = $item['current'] || in_array( true, wp_list_pluck( $item['children'], 'current' ), true );
						$subs = '';
						if ( '' !== $item['url'] && '#' !== $item['url'][0] ) {
							// The parent's own page stays reachable from its submenu.
							$subs .= '<li><a' . ( $item['current'] ? ' class="is-current"' : '' ) . $this->nav_attrs( $item ) . '>مشاهده‌ی همه</a></li>';
						}
						foreach ( $item['children'] as $child ) {
							$subs .= '<li><a' . ( $child['current'] ? ' class="is-current"' : '' ) . $this->nav_attrs( $child ) . '>' . esc_html( $child['label'] ) . '</a></li>';
						}
						$html .= '<li class="tpfb-nav__item has-sub"><details class="tpfb-nav__grp"' . ( $open ? ' open' : '' ) . '><summary class="tpfb-nav__link' . ( $open ? ' is-current' : '' ) . '">' . $inner . '<span class="tpfb-nav__chev" aria-hidden="true">' . $snippet->svg( '_down' ) . '</span></summary><ul class="tpfb-nav__sub">' . $subs . '</ul></details></li>';
					}
					return $html . '</ul></nav>';
				}

				private function nav_attrs( array $item ): string {
					return $this->attrs(
						array(
							'href'         => '' !== $item['url'] ? $item['url'] : '#',
							'target'       => $item['blank'] ? '_blank' : '',
							'rel'          => $item['rel'],
							'aria-current' => $item['current'] ? 'page' : '',
						)
					);
				}

				private function render_sheet( array $tree, array $site, array $s, string $sheet_id, string $id, bool $mono ): void {
					$snippet   = self::$snippet;
					$accordion = 'open' !== ( $s['sheet_mode'] ?? 'accordion' );
					$single    = $accordion && 'yes' === ( $s['sheet_single'] ?? 'yes' );
					$summary   = 'yes' === ( $s['sheet_summary'] ?? 'yes' );
					$list      = 'list' === ( $s['sub_layout'] ?? 'grid' );
					$back      = $snippet->svg( '_back' );
					$head_tag  = $accordion ? 'summary' : 'div';
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- icons are built-in SVG or Elementor's own markup; the rest is escaped below.
					?>
					<div class="tpfb-sheet tpfb-sheet--<?php echo esc_attr( $id ); ?><?php echo $mono ? ' tpfb-sheet--mono' : ''; ?>" id="<?php echo esc_attr( $sheet_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $sheet_id ); ?>-title" hidden>
						<div class="tpfb-sheet__backdrop" data-tpfb-close></div>
						<div class="tpfb-sheet__panel">
							<div class="tpfb-sheet__drag">
								<span class="tpfb-sheet__grip" aria-hidden="true"></span>
								<div class="tpfb-sheet__head">
									<span class="tpfb-sheet__title" id="<?php echo esc_attr( $sheet_id ); ?>-title"><?php echo esc_html( (string) ( $s['sheet_title'] ?? '' ) ); ?></span>
									<button type="button" class="tpfb-sheet__close" data-tpfb-close aria-label="بستن"><?php echo $snippet->svg( '_close' ); ?></button>
								</div>
							</div>
							<div class="tpfb-sheet__body"<?php echo $single ? ' data-tpfb-single' : ''; ?>>
								<?php foreach ( $tree as $node ) : ?>
									<?php if ( 'link' === $node['type'] ) : ?>
										<a class="tpfb-card tpfb-row"<?php echo $this->attrs( $node['attrs'] ); ?>>
											<span class="tpfb-row__icon"><?php echo $node['icon']; ?></span>
											<span class="tpfb-row__text"><span class="tpfb-row__title"><?php echo esc_html( $node['label'] ); ?></span></span>
											<span class="tpfb-row__chev" aria-hidden="true"><?php echo $back; ?></span>
										</a>
									<?php else : ?>
										<?php $names = implode( '، ', array_slice( wp_list_pluck( $node['children'], 'label' ), 0, 6 ) ); ?>
										<?php echo $accordion ? '<details class="tpfb-card tpfb-grp"' . ( $node['open'] ? ' open' : '' ) . '>' : '<div class="tpfb-card tpfb-grp is-static">'; ?>
											<<?php echo $head_tag; ?> class="tpfb-row">
												<span class="tpfb-row__icon"><?php echo $node['icon']; ?></span>
												<span class="tpfb-row__text">
													<span class="tpfb-row__title"><?php echo esc_html( $node['label'] ); ?></span>
													<?php if ( $summary ) : ?>
														<span class="tpfb-row__sub"><?php echo esc_html( $names ); ?></span>
													<?php endif; ?>
												</span>
												<?php if ( $accordion ) : ?>
													<span class="tpfb-row__chev" aria-hidden="true"><?php echo $snippet->svg( '_down' ); ?></span>
												<?php endif; ?>
											</<?php echo $head_tag; ?>>
											<div class="tpfb-grp__body">
												<ul class="tpfb-subs<?php echo $list ? ' tpfb-subs--list' : ''; ?>">
													<?php foreach ( $node['children'] as $child ) : ?>
														<li>
															<a class="tpfb-sub"<?php echo $this->attrs( $child['attrs'] ); ?>>
																<span class="tpfb-sub__icon"><?php echo $child['icon']; ?></span>
																<span class="tpfb-sub__label"><?php echo esc_html( $child['label'] ); ?></span>
																<span class="tpfb-sub__arrow" aria-hidden="true"><?php echo $back; ?></span>
															</a>
														</li>
													<?php endforeach; ?>
												</ul>
											</div>
										<?php echo $accordion ? '</details>' : '</div>'; ?>
									<?php endif; ?>
								<?php endforeach; ?>
								<?php echo $site ? $this->site_menu_html( $site, $s ) : ''; ?>
							</div>
						</div>
					</div>
					<?php
					// phpcs:enable
				}
			};

			$widgets_manager->register( $widget );
		}
	}

	Techpino_Footer_Bar_Snippet::instance();
}
