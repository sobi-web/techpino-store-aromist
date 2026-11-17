<?php
/**
 * نقطهٔ AJAX: اسلایدهای کاروسل را برای یک دکمهٔ فیلتر رندر می‌کند.
 *
 * کلاینت فقط (سند، شناسهٔ ویجت فیلتر، شماره دکمه) می‌فرستد؛ کاروسل هدف و ترم از داده‌ی ذخیره‌شدهٔ
 * المنتور خوانده می‌شود، پس چیز دلخواهی رندر نمی‌شود. خروجی با ترنزینت کوتاه کش می‌شود.
 *
 * @package AromistSeasonFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ASF_Ajax {

	const ACTION = 'asf_slides';

	const OPTION_GEN = 'asf_generation';

	public static function init() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );

		// هر تغییری که کارت محصول را عوض می‌کند، کش را بی‌اعتبار می‌کند.
		foreach ( array( 'save_post_product', 'woocommerce_update_product', 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock', 'deleted_post', 'edited_term', 'elementor/document/after_save' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'bump' ) );
		}
	}

	public static function bump() {
		update_option( self::OPTION_GEN, (string) microtime( true ), false );
	}

	private static function fail( $status, $message ) {
		status_header( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( array( 'success' => false, 'data' => $message ) );
		wp_die();
	}

	/**
	 * استایل درون‌خطیِ تکراری (هر کارت یک نسخه از همان CSS را دارد) را بعد از اولین نمونه حذف می‌کند.
	 *
	 * @param string $html خروجی ویجت.
	 * @return string
	 */
	private static function dedupe_styles( $html ) {
		$seen = array();
		return preg_replace_callback(
			'#<style\b[^>]*>.*?</style>#s',
			static function ( $m ) use ( &$seen ) {
				$key = md5( $m[0] );
				if ( isset( $seen[ $key ] ) ) {
					return '';
				}
				$seen[ $key ] = true;
				return $m[0];
			},
			$html
		);
	}

	public static function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- خواندنیِ عمومی؛ فقط داده‌ی منتشرشده رندر می‌شود.
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$filter  = isset( $_GET['filter'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', wp_unslash( $_GET['filter'] ) ) : '';
		$pill    = isset( $_GET['pill'] ) ? absint( $_GET['pill'] ) : 0;
		// phpcs:enable

		if ( ! $post_id || '' === $filter ) {
			self::fail( 400, 'bad request' );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status || post_password_required( $post ) ) {
			self::fail( 404, 'not found' );
		}
		if ( ! is_post_type_viewable( $post->post_type ) && 'elementor_library' !== $post->post_type ) {
			self::fail( 404, 'not found' );
		}

		$document = \Elementor\Plugin::$instance->documents->get( $post_id );
		if ( ! $document || ! $document->is_built_with_elementor() ) {
			self::fail( 404, 'not found' );
		}

		$tree  = (array) $document->get_elements_data();
		$found = ASF_Core::find_with_parent( $tree, $filter );
		if ( ! $found || ! isset( $found[0]['widgetType'] ) || ASF_Core::WIDGET !== $found[0]['widgetType'] ) {
			self::fail( 404, 'filter not found' );
		}
		$target = ASF_Core::resolve_target( $tree, $found[0], $found[1] );
		if ( ! $target ) {
			self::fail( 404, 'carousel not found' );
		}

		$filter_widget = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $found[0] );
		$selection     = $filter_widget ? ASF_Core::selection( $filter_widget->get_settings_for_display(), $pill ) : null;
		if ( ! $selection ) {
			self::fail( 400, 'bad pill' );
		}

		$key  = 'asf_' . md5( implode( '|', array( ASF_VERSION, get_option( self::OPTION_GEN, '0' ), $post->post_modified_gmt, $post_id, $filter, $pill ) ) );
		$data = get_transient( $key );

		if ( ! is_array( $data ) ) {
			$widget = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $target );
			if ( ! $widget ) {
				self::fail( 500, 'render failed' );
			}
			ASF_Core::set_ajax_context( array( 'target' => $target['id'], 'selection' => $selection ) );
			try {
				ob_start();
				$widget->print_element();
				$html = ob_get_clean();
			} finally {
				ASF_Core::set_ajax_context( null );
			}
			$data = array(
				'html'  => self::dedupe_styles( $html ),
				'count' => substr_count( $html, 'data-elementor-type="loop-item"' ),
			);
			set_transient( $key, $data, (int) apply_filters( 'asf_cache_ttl', 10 * MINUTE_IN_SECONDS ) );
		}

		$max_age = (int) apply_filters( 'asf_browser_max_age', 2 * MINUTE_IN_SECONDS );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=' . $max_age );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $max_age ) . ' GMT' );
		header_remove( 'Pragma' );
		echo wp_json_encode( array( 'success' => true, 'data' => $data ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		wp_die();
	}
}
