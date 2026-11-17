<?php
/**
 * Live search: assets, the browser config and the Elementor widget.
 *
 * Nothing is requested while the visitor types. The script fetches the product index
 * (see TPLS_Index) once the page is idle or the field gets focus, and searches it in place.
 *
 * @package TechpinoLiveSearch
 */

defined( 'ABSPATH' ) || exit;

final class TPLS_Plugin {

	/** @var TPLS_Plugin|null */
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_footer', array( $this, 'print_config' ), 1 );
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TPLS_FILE ), array( $this, 'action_links' ) );
		add_action( 'admin_notices', array( $this, 'rebuilt_notice' ) );
	}

	public function register_assets(): void {
		wp_register_style( 'tpls', TPLS_URL . 'assets/css/live-search.css', array(), TPLS_VERSION );
		$look = TPLS_Settings::css();
		if ( '' !== $look ) {
			wp_add_inline_style( 'tpls', $look );
		}
		wp_register_script(
			'tpls',
			TPLS_URL . 'assets/js/live-search.js',
			array(),
			TPLS_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	public function register_widget( $widgets_manager ): void {
		require_once TPLS_PATH . 'includes/class-tpls-widget.php';
		$widgets_manager->register( new TPLS_Widget() );
	}

	/** Only pages that printed the widget pay for the config (and, as a fallback, the inline index). */
	public function print_config(): void {
		if ( ! wp_script_is( 'tpls', 'enqueued' ) ) {
			return;
		}
		$source   = TPLS_Index::source();
		$settings = TPLS_Settings::get();
		$config   = array(
			'index'    => $source['url'],
			'syn'      => (string) $settings['synonyms'],
			'popup'    => 1025, // Desktop starts here; live-search.css uses the same breakpoint.
			'featured' => array(
				'show'  => ! empty( $settings['featured_show'] ),
				'mode'  => $settings['featured_mode'],
				'ids'   => array_map( 'intval', (array) $settings['featured_ids'] ),
				'count' => (int) $settings['featured_count'],
				'stock' => ! empty( $settings['featured_stock'] ),
				'title' => (string) $settings['featured_title'],
			),
			'currency' => array(
				'symbol' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'pos'    => get_option( 'woocommerce_currency_pos', 'right_space' ),
				'dec'    => wc_get_price_decimals(),
				'th'     => wc_get_price_thousand_separator(),
				'ds'     => wc_get_price_decimal_separator(),
			),
		);
		$script = 'window.tplsConfig=' . wp_json_encode( $config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';';
		if ( '' !== $source['data'] ) {
			$script .= 'window.tplsIndex=' . str_replace( '</', '<\/', $source['data'] ) . ';';
		}
		wp_add_inline_script( 'tpls', $script, 'before' );
	}

	public function action_links( array $links ): array {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=tpls_rebuild' ), 'tpls_rebuild' );
			array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'ساخت دوباره‌ی فهرست جستجو', 'techpino-live-search' ) . '</a>' );
			array_unshift( $links, '<a href="' . esc_url( TPLS_Admin::url() ) . '">' . esc_html__( 'تنظیمات', 'techpino-live-search' ) . '</a>' );
		}
		return $links;
	}

	public function rebuilt_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only shows a message.
		if ( empty( $_GET['tpls_rebuilt'] ) || ! $screen || 'plugins' !== $screen->id || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$info  = get_option( 'tpls_index' );
		$count = is_array( $info ) && isset( $info['count'] ) ? (int) $info['count'] : 0;
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: number of products. */
					__( 'فهرست جستجوی زنده دوباره ساخته شد: %s محصول.', 'techpino-live-search' ),
					number_format_i18n( $count )
				)
			)
		);
	}

	/** Small stroke icons, drawn like the mini cart's. */
	public static function icon( string $name ): string {
		$paths = array(
			'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.4-4.4"/>',
			'close'  => '<path d="m6 6 12 12M18 6 6 18"/>',
			'clock'  => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l2.5 2.5"/>',
			'trend'  => '<path d="m4 16 5-5 4 4 7-7"/><path d="M15 8h5v5"/>',
			'empty'  => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.4-4.4M8.5 13.5l5-5M13.5 13.5l-5-5"/>',
			'arrow'  => '<path d="M15 6l-6 6 6 6"/>',
		);
		return '<svg class="tpls-svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}
}
