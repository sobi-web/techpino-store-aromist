<?php
/**
 * Module: اسنیپت‌های سایت
 * Description: کدهای اختصاصی سایت که قبلاً در افزونه‌ی Code Snippets بودند (از خروجی همان افزونه ساخته شده).
 *
 * هر اسنیپت یک فایل در پوشه‌ی snippets/ است و همان وقتی اجرا می‌شود که Code Snippets اجرا می‌کرد
 * (plugins_loaded، اولویت ۱، با همان ترتیب). فایل را OPcache کش می‌کند؛ دیگر eval نمی‌شود.
 * تا وقتی افزونه‌ی Code Snippets فعال است این ماژول هیچ اسنیپتی را اجرا نمی‌کند تا کدی دو بار اجرا نشود.
 * هر اسنیپت از پنل «تنظیمات تکپینو» جداگانه خاموش/روشن می‌شود.
 */

defined( 'ABSPATH' ) || exit;

define( 'TPSN_VERSION', '1.0.0' );

// A function of its own, so a snippet's top-level variables stay local (as they did inside Code Snippets).
function tpsn_include_snippet( $tpsn_file ) {
	include $tpsn_file;
}

final class TPSN_Loader {

	/** @var bool Code Snippets is active and runs the snippets itself. */
	public static $standby = false;

	/** @var array id => error message */
	public static $failed = array();

	/** @var int[] Skipped: their code was already loaded by something else. */
	public static $clash = array();

	/** id => [ name, sig, admin, tags ], in Code Snippets' run order. */
	public static function snippets() {
		static $list = null;
		if ( null === $list ) {
			$list = include __DIR__ . '/list.php';
		}
		return $list;
	}

	public static function code_snippets_active() {
		return defined( 'CODE_SNIPPETS_FILE' ) || defined( 'CODE_SNIPPETS_VERSION' ) || function_exists( 'Code_Snippets\code_snippets' ) || function_exists( 'code_snippets' );
	}

	public static function run() {
		if ( self::code_snippets_active() ) {
			self::$standby = true;
			return;
		}
		$hidden = (array) Techpino_Panel::opt()['hidden'];
		foreach ( self::snippets() as $id => $s ) {
			if ( isset( $hidden[ 'x:' . $id ] ) || ( $s['admin'] && ! is_admin() ) ) {
				continue;
			}
			// Declaring the same function twice is an uncatchable fatal error, so check before loading.
			if ( $s['sig'] && self::declared( $s['sig'] ) ) {
				self::$clash[] = $id;
				continue;
			}
			ob_start(); // like Code Snippets: stray top-level output is dropped
			try {
				tpsn_include_snippet( __DIR__ . '/snippets/' . $id . '.php' );
			} catch ( \Throwable $e ) {
				self::$failed[ $id ] = $e->getMessage();
				error_log( 'Techpino snippet #' . $id . ' failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
			ob_end_clean();
		}
	}

	private static function declared( $sig ) {
		list( $type, $name ) = explode( ':', $sig, 2 );
		if ( 'f' === $type ) {
			return function_exists( $name );
		}
		return 'c' === $type ? class_exists( $name, false ) : defined( $name );
	}

	/** A switched-off snippet's shortcodes print nothing instead of their raw [tag] text. */
	public static function mute() {
		if ( self::$standby ) {
			return;
		}
		$hidden = (array) Techpino_Panel::opt()['hidden'];
		foreach ( self::snippets() as $id => $s ) {
			if ( ! isset( $hidden[ 'x:' . $id ] ) ) {
				continue;
			}
			foreach ( $s['tags'] as $tag ) {
				if ( ! shortcode_exists( $tag ) ) {
					add_shortcode( $tag, '__return_empty_string' );
				}
			}
		}
	}

	/** Message for this module's page in the panel (and the plugins screen). */
	public static function notice( $msg, $module ) {
		if ( 'snippets' !== $module ) {
			return $msg;
		}
		if ( self::$standby ) {
			return 'افزونه‌ی Code Snippets هنوز فعال است، پس خود آن این اسنیپت‌ها را اجرا می‌کند و این ماژول کاری نمی‌کند (تا هیچ کدی دو بار اجرا نشود). برای جابه‌جایی، Code Snippets را از صفحه‌ی افزونه‌ها غیرفعال کنید (پاکش نکنید). اگر مشکلی دیدید، دوباره فعالش کنید تا همه‌چیز مثل قبل شود.';
		}
		$out = array();
		if ( self::$clash ) {
			$out[] = 'این اسنیپت‌ها اجرا نشدند چون کدشان جای دیگری از قبل بارگذاری شده بود: #' . implode( '، #', self::$clash ) . '.';
		}
		foreach ( self::$failed as $id => $error ) {
			$out[] = 'اسنیپت #' . $id . ' خطا داد: ' . $error;
		}
		return implode( ' ', $out );
	}

	public static function plugins_screen_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugins' !== $screen->id || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$msg = self::notice( '', 'snippets' );
		if ( '' !== $msg ) {
			echo '<div class="notice notice-info"><p><strong>تکپینو – اسنیپت‌های سایت:</strong> ' . esc_html( $msg ) . '</p></div>';
		}
	}
}

add_action( 'plugins_loaded', array( 'TPSN_Loader', 'run' ), 1 );
add_action( 'init', array( 'TPSN_Loader', 'mute' ), 99 );
add_filter( 'techpino_panel_notice', array( 'TPSN_Loader', 'notice' ), 10, 2 );
add_action( 'admin_notices', array( 'TPSN_Loader', 'plugins_screen_notice' ) );
