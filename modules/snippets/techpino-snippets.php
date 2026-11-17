<?php
/**
 * Module: اسنیپت‌های سایت
 * Description: اسنیپت‌های PHP، CSS و JS که از «تنظیمات تکپینو ← اسنیپت‌ها» ساخته، نام‌گذاری و محل اجرایشان تعیین می‌شود.
 *
 * هر اسنیپت یک پست از نوع tpsn_snippet است (کد در متای _tpsn_code). اسنیپت‌های فعال در گزینه‌ی tpsn_compiled
 * کنار هم نگه داشته می‌شوند تا هر درخواست فقط یک گزینه‌ی autoload بخواند، نه کوئری.
 *
 * حالت امن PHP: قبل از ذخیره خطای نحوی بررسی می‌شود؛ اگر موقع اجرا خطا یا خطای مرگبار بدهد، خودکار خاموش می‌شود.
 * اضطراری: با define( 'TPSN_SAFE_MODE', true ); در wp-config.php هیچ اسنیپت PHP اجرا نمی‌شود.
 *
 * اسنیپت‌های قدیمی (پوشه‌ی legacy، خروجی Code Snippets) یک بار به همین بخش منتقل می‌شوند.
 * تا وقتی افزونه‌ی Code Snippets فعال است، همان منتقل‌شده‌ها اجرا نمی‌شوند تا کدی دو بار اجرا نشود.
 */

defined( 'ABSPATH' ) || exit;

define( 'TPSN_VERSION', '2.0.0' );

// A function of its own, so a snippet's top-level variables stay local (as they did inside Code Snippets).
function tpsn_eval_snippet( $tpsn_code ) {
	eval( $tpsn_code ); // phpcs:ignore Squiz.PHP.Eval -- running admin-written snippets is this module's job.
}

final class TPSN_Loader {

	const POST_TYPE = 'tpsn_snippet';
	const COMPILED  = 'tpsn_compiled';
	const ERRORS    = 'tpsn_errors';
	const MIGRATED  = 'tpsn_migrated';

	const TYPES  = array(
		'php' => 'PHP',
		'css' => 'CSS',
		'js'  => 'JavaScript',
	);
	const SCOPES = array(
		'all'   => 'کل سایت (سایت و پیشخوان)',
		'front' => 'فقط سایت',
		'admin' => 'فقط پیشخوان (مدیریت)',
	);
	const PAGES  = array(
		'home'    => 'صفحه‌ی اصلی',
		'product' => 'صفحه‌ی محصولات',
		'shop'    => 'فروشگاه و دسته‌ها',
		'cart'    => 'سبد خرید و تسویه حساب',
		'post'    => 'مقاله‌ها',
	);

	/** @var bool Code Snippets is active and runs the migrated snippets itself. */
	public static $standby = false;

	/** @var int[] Skipped: their code was already loaded by something else. */
	public static $clash = array();

	/** @var int|null The PHP snippet running right now, so a fatal error can be pinned on it. */
	private static $current = null;

	/** Active snippets in run order, and the shortcodes of switched-off ones. */
	public static function compiled() {
		$c = get_option( self::COMPILED );
		return is_array( $c ) && isset( $c['run'], $c['mute'] ) ? $c : array( 'run' => array(), 'mute' => array() );
	}

	public static function code_snippets_active() {
		return defined( 'CODE_SNIPPETS_FILE' ) || defined( 'CODE_SNIPPETS_VERSION' ) || function_exists( 'Code_Snippets\code_snippets' ) || function_exists( 'code_snippets' );
	}

	public static function boot() {
		if ( ! get_option( self::MIGRATED ) ) {
			require_once __DIR__ . '/migrate.php';
			TPSN_Migrate::run();
		}
		self::$standby = self::code_snippets_active();
		$safe          = defined( 'TPSN_SAFE_MODE' ) && TPSN_SAFE_MODE;
		$late          = array();

		foreach ( self::compiled()['run'] as $s ) {
			if ( ! self::scope_ok( $s['scope'] ) ) {
				continue;
			}
			if ( 'php' !== $s['type'] ) {
				self::queue_output( $s );
				continue;
			}
			if ( $safe || ( self::$standby && $s['legacy'] ) ) {
				continue;
			}
			if ( $s['pages'] || $s['ids'] ) {
				$late[] = $s; // which page it is is only known after the main query
				continue;
			}
			self::run_php( $s );
		}

		if ( $late ) {
			add_action(
				'wp',
				static function () use ( $late ) {
					foreach ( $late as $s ) {
						if ( self::page_ok( $s ) ) {
							self::run_php( $s );
						}
					}
				},
				1
			);
		}
	}

	/** 'front' also covers admin-ajax, which the site's own pages call (cart fragments, filters…). */
	public static function scope_ok( $scope ) {
		if ( 'admin' === $scope ) {
			return is_admin();
		}
		return 'front' === $scope ? ( ! is_admin() || wp_doing_ajax() ) : true;
	}

	/** No page picked = every page. Only called on the front end, after the main query. */
	public static function page_ok( array $s ) {
		if ( ! $s['pages'] && ! $s['ids'] ) {
			return true;
		}
		$wc     = function_exists( 'is_woocommerce' );
		$checks = array(
			'home'    => is_front_page(),
			'product' => $wc && is_product(),
			'shop'    => $wc && ( is_shop() || is_product_taxonomy() ),
			'cart'    => $wc && ( is_cart() || is_checkout() ),
			'post'    => is_singular( 'post' ),
		);
		foreach ( $s['pages'] as $page ) {
			if ( ! empty( $checks[ $page ] ) ) {
				return true;
			}
		}
		return $s['ids'] && is_singular() && in_array( (int) get_queried_object_id(), $s['ids'], true );
	}

	private static function run_php( array $s ) {
		// Declaring the same function twice is an uncatchable fatal error, so check before loading.
		if ( $s['sig'] && self::declared( $s['sig'] ) ) {
			self::$clash[] = $s['id'];
			return;
		}
		self::$current = $s['id'];
		ob_start(); // like Code Snippets: stray top-level output is dropped
		try {
			tpsn_eval_snippet( $s['code'] );
		} catch ( \Throwable $e ) {
			self::disable( $s['id'], $e->getMessage() . ' (خط ' . $e->getLine() . ')' );
		}
		ob_end_clean();
		self::$current = null;
	}

	private static function declared( $sig ) {
		list( $type, $name ) = array_pad( explode( ':', $sig, 2 ), 2, '' );
		if ( 'f' === $type ) {
			return function_exists( $name );
		}
		return 'c' === $type ? class_exists( $name, false ) : defined( $name );
	}

	private static function queue_output( array $s ) {
		$hook = ( is_admin() ? 'admin_' : 'wp_' ) . ( 'head' === $s['pos'] ? 'head' : 'footer' );
		add_action(
			$hook,
			static function () use ( $s ) {
				if ( ! is_admin() && ! self::page_ok( $s ) ) {
					return;
				}
				$tag = 'css' === $s['type'] ? 'style' : 'script';
				// Admin-written code (needs unfiltered_html to save), printed as is.
				echo "<{$tag} id=\"tpsn-{$s['id']}\">\n" . $s['code'] . "\n</{$tag}>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			},
			99
		);
	}

	/**
	 * Fatal error while a snippet was being loaded: switch it off so the next request works.
	 * Runs from WordPress' own fatal handler (it ends the request, so later shutdown functions never run)
	 * and from a shutdown function for sites that turned that handler off.
	 */
	public static function on_fatal( $passthru = null, $error = null ) {
		$error = is_array( $error ) ? $error : error_get_last();
		if ( null !== self::$current && $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			$id            = self::$current;
			self::$current = null;
			self::disable( $id, $error['message'] . ' (خط ' . $error['line'] . ')' );
		}
		return $passthru;
	}

	public static function disable( $id, $message ) {
		update_post_meta( $id, '_tpsn_active', '0' );
		self::set_error( $id, $message );
		self::rebuild();
		error_log( 'Techpino snippet #' . $id . ' switched off: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}

	/** '' clears it. */
	public static function set_error( $id, $message ) {
		$errors = (array) get_option( self::ERRORS, array() );
		if ( '' === $message ) {
			unset( $errors[ $id ] );
		} else {
			$errors[ $id ] = $message;
		}
		update_option( self::ERRORS, $errors, false );
	}

	/** Syntax check without running the code. '' = fine. */
	public static function lint( $code ) {
		try {
			token_get_all( "<?php\n" . $code, TOKEN_PARSE );
			return '';
		} catch ( \ParseError $e ) {
			return $e->getMessage() . ' (خط ' . max( 1, $e->getLine() - 1 ) . ')';
		}
	}

	/** Code as eval() wants it: without a leading <?php tag. */
	public static function php_body( $code ) {
		return (string) preg_replace( '/^\s*<\?php\b\s*/', '', (string) $code, 1 );
	}

	/** One snippet's settings, normalised. */
	public static function read( $id ) {
		$meta = static function ( $key, $default = '' ) use ( $id ) {
			$v = get_post_meta( $id, '_tpsn_' . $key, true );
			return '' === $v || false === $v ? $default : $v;
		};
		$type  = $meta( 'type', 'php' );
		$scope = $meta( 'scope', 'all' );
		$type  = is_string( $type ) && array_key_exists( $type, self::TYPES ) ? $type : 'php';
		$scope = is_string( $scope ) && array_key_exists( $scope, self::SCOPES ) ? $scope : 'all';
		$front = 'front' === $scope;
		return array(
			'id'     => (int) $id,
			'type'   => $type,
			'scope'  => $scope,
			'pages'  => $front ? array_values( array_intersect( (array) $meta( 'pages', array() ), array_keys( self::PAGES ) ) ) : array(),
			'ids'    => $front ? array_values( array_filter( array_map( 'intval', (array) $meta( 'ids', array() ) ) ) ) : array(),
			'pos'    => 'head' === $meta( 'pos' ) ? 'head' : 'footer',
			'order'  => (int) $meta( 'order', 10 ),
			'active' => '1' === (string) $meta( 'active', '0' ),
			'code'   => 'php' === $type ? self::php_body( $meta( 'code' ) ) : (string) $meta( 'code' ),
			'sig'    => (string) $meta( 'sig' ),
			'tags'   => array_values( array_filter( (array) $meta( 'tags', array() ) ) ),
			'legacy' => (int) $meta( 'legacy', 0 ),
		);
	}

	/** Rewrite the compiled list. Call after anything that changes a snippet. */
	public static function rebuild() {
		$run  = array();
		$mute = array();
		$ids  = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		foreach ( $ids as $id ) {
			$s = self::read( $id );
			if ( $s['active'] && '' !== trim( $s['code'] ) ) {
				$run[] = $s;
			} else {
				$mute = array_merge( $mute, $s['tags'] );
			}
		}
		usort(
			$run,
			static function ( $a, $b ) {
				return array( $a['order'], $a['id'] ) <=> array( $b['order'], $b['id'] );
			}
		);
		update_option(
			self::COMPILED,
			array(
				'run'  => $run,
				'mute' => array_values( array_unique( $mute ) ),
			),
			true
		);
	}

	/** A switched-off snippet's shortcodes print nothing instead of their raw [tag] text. */
	public static function mute() {
		foreach ( self::compiled()['mute'] as $tag ) {
			if ( ! shortcode_exists( $tag ) ) {
				add_shortcode( $tag, '__return_empty_string' );
			}
		}
	}

	/** Problems worth telling an admin about, one line each. */
	public static function messages() {
		$out = array();
		if ( defined( 'TPSN_SAFE_MODE' ) && TPSN_SAFE_MODE ) {
			$out[] = 'حالت اضطراری روشن است (TPSN_SAFE_MODE در wp-config.php): هیچ اسنیپت PHP اجرا نمی‌شود.';
		}
		if ( self::$standby ) {
			$out[] = 'افزونه‌ی Code Snippets هنوز فعال است، پس اسنیپت‌های منتقل‌شده از آن را خودش اجرا می‌کند و اینجا اجرا نمی‌شوند (تا کدی دو بار اجرا نشود). Code Snippets را از صفحه‌ی افزونه‌ها غیرفعال کنید (پاکش نکنید).';
		}
		if ( self::$clash ) {
			$out[] = 'این اسنیپت‌ها اجرا نشدند چون کدشان جای دیگری از قبل بارگذاری شده بود: #' . implode( '، #', self::$clash ) . '.';
		}
		foreach ( (array) get_option( self::ERRORS, array() ) as $id => $error ) {
			$out[] = 'اسنیپت «' . get_the_title( $id ) . '» (#' . $id . ') خاموش شد؛ ' . $error . ' — بعد از اصلاح کد، دوباره ذخیره کنید.';
		}
		return $out;
	}

	/** Message for this module's page in the panel. */
	public static function notice( $msg, $module ) {
		return 'snippets' === $module ? implode( ' ', self::messages() ) : $msg;
	}
}

add_action( 'plugins_loaded', array( 'TPSN_Loader', 'boot' ), 1 );
add_action( 'init', array( 'TPSN_Loader', 'mute' ), 99 );
add_filter( 'wp_php_error_args', array( 'TPSN_Loader', 'on_fatal' ), 1, 2 );
register_shutdown_function( array( 'TPSN_Loader', 'on_fatal' ) );
add_filter( 'techpino_panel_notice', array( 'TPSN_Loader', 'notice' ), 10, 2 );

require_once __DIR__ . '/admin.php';
