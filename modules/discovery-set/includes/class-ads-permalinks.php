<?php
/**
 * URLs of ready-made sets: /<archive page path>/<set slug>/ (e.g. /discovery-sets/winter/),
 * so sets live in their own section next to their archive page instead of under /product/.
 *
 * Sets stay WooCommerce products; only their permalink changes. A rewrite rule maps the new
 * URL to the product, old /product/… URLs redirect (301) to the new one, and the rules are
 * flushed once after setup or when the archive page changes.
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Permalinks.
 */
final class ADS_Permalinks {

	const FLUSH_OPTION = 'ads_flush_rewrite';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'add_rules' ), 30 );
		add_filter( 'post_type_link', array( $this, 'set_link' ), 20, 2 );
		add_action( 'template_redirect', array( $this, 'redirect_old_url' ), 5 );
		add_action( 'ads_settings_saved', array( __CLASS__, 'schedule_flush' ) );
		add_action( 'post_updated', array( $this, 'page_changed' ), 10, 3 );
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_flush' ) );
	}

	/**
	 * Are pretty set URLs on? (Pretty permalinks and an archive page that exists.)
	 *
	 * @return bool
	 */
	public static function enabled() {
		return '' !== (string) get_option( 'permalink_structure' ) && '' !== self::base();
	}

	/**
	 * Path of the archive page ('' when there is none).
	 *
	 * @return string
	 */
	public static function base() {
		$id = absint( ADS_Settings::get( 'archive_page_id' ) );
		if ( ! $id || 'page' !== get_post_type( $id ) || 'trash' === get_post_status( $id ) ) {
			return '';
		}
		return trim( (string) get_page_uri( $id ), '/' );
	}

	/**
	 * Rewrite rule: /<base>/<slug>/ → the product.
	 */
	public function add_rules() {
		$base = self::base();
		if ( '' === $base ) {
			return;
		}
		add_rewrite_rule( '^' . preg_quote( $base, '#' ) . '/([^/]+)/?$', 'index.php?post_type=product&product=$matches[1]', 'top' );
	}

	/**
	 * Is this post a published ready-made set?
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	private static function is_preset_post( $post ) {
		return $post instanceof WP_Post
			&& 'product' === $post->post_type
			&& has_term( 'discovery_set', 'product_type', $post )
			&& (int) $post->ID !== absint( ADS_Settings::get( 'custom_product_id' ) );
	}

	/**
	 * Permalink of a ready-made set.
	 *
	 * @param string  $link Link.
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public function set_link( $link, $post ) {
		if ( ! self::is_preset_post( $post ) || 'publish' !== $post->post_status || '' === $post->post_name || ! self::enabled() ) {
			return $link;
		}
		return home_url( user_trailingslashit( self::base() . '/' . $post->post_name ) );
	}

	/**
	 * Old /product/<slug>/ address of a set → the new one (301), keeping the query string.
	 */
	public function redirect_old_url() {
		if ( ! is_singular( 'product' ) || is_preview() || ! self::enabled() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! self::is_preset_post( $post ) || 'publish' !== $post->post_status ) {
			return;
		}
		$target = get_permalink( $post );
		$want   = untrailingslashit( (string) wp_parse_url( $target, PHP_URL_PATH ) );
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$have   = untrailingslashit( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
		if ( '' === $want || rawurldecode( $have ) === rawurldecode( $want ) ) {
			return;
		}
		$query = (string) wp_parse_url( $uri, PHP_URL_QUERY );
		wp_safe_redirect( $target . ( '' !== $query ? '?' . $query : '' ), 301 );
		exit;
	}

	/**
	 * The archive page's slug / parent changed: rebuild the rules.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $after   After.
	 * @param WP_Post $before  Before.
	 */
	public function page_changed( $post_id, $after, $before ) {
		if ( (int) $post_id === absint( ADS_Settings::get( 'archive_page_id' ) )
			&& ( $after->post_name !== $before->post_name || (int) $after->post_parent !== (int) $before->post_parent || $after->post_status !== $before->post_status ) ) {
			self::schedule_flush();
		}
	}

	/**
	 * Ask for a rewrite flush on the next request.
	 */
	public static function schedule_flush() {
		update_option( self::FLUSH_OPTION, 1, true );
	}

	/**
	 * Flush once when asked (after the rules of this request were registered).
	 */
	public static function maybe_flush() {
		if ( get_option( self::FLUSH_OPTION ) ) {
			delete_option( self::FLUSH_OPTION );
			flush_rewrite_rules( false );
		}
	}
}
