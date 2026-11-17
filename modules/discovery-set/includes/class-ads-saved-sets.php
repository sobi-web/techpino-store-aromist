<?php
/**
 * «ست‌های من»: sets a signed-in customer saved in the builder.
 *
 * Stored in user meta `_ads_saved_sets` (only perfume ids and a name; prices and stock are always
 * read live). Shown in a WooCommerce account endpoint (/my-account/my-sets/), which the Techpino
 * user panel renders as one of its own AJAX sections (`tpup_known_sections`).
 *
 * AJAX (wc-ajax, nonce `ads_saved_sets`):
 *   ads_save_set    — id (optional, to update), name, components[]
 *   ads_delete_set  — id
 * Buying a saved set uses the normal ads_add_to_cart endpoint with the «ست سفارشی» product.
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Saved sets.
 */
final class ADS_Saved_Sets {

	const META     = '_ads_saved_sets';
	const ENDPOINT = 'my-sets';
	const NONCE    = 'ads_saved_sets';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'wc_ajax_ads_save_set', array( $this, 'ajax_save' ) );
		add_action( 'wc_ajax_ads_delete_set', array( $this, 'ajax_delete' ) );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_item' ), 60 );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render' ) );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( $this, 'title' ) );
		add_filter( 'tpup_known_sections', array( $this, 'panel_section' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 30 );
		add_filter( 'woocommerce_login_redirect', array( $this, 'login_redirect' ), 20 );
	}

	/**
	 * After signing in from the builder's «وارد شو و ذخیره کن» link, go back to the builder.
	 * Only the builder page is accepted as a target.
	 *
	 * @param string $redirect Default target.
	 * @return string
	 */
	public function login_redirect( $redirect ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to      = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
		$builder = ADS_Pages::builder_url();
		if ( '' === $to || '' === $builder ) {
			return $redirect;
		}
		$path = static function ( $url ) {
			return untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		};
		return $path( $to ) === $path( $builder ) ? $builder : $redirect;
	}

	/*
	|--------------------------------------------------------------------------
	| Storage
	|--------------------------------------------------------------------------
	*/

	/**
	 * Most sets one customer can keep.
	 *
	 * @return int
	 */
	public static function max() {
		return max( 1, (int) ADS_Settings::get( 'max_saved_sets' ) );
	}

	/**
	 * Saved sets of a user, newest first.
	 *
	 * @param int $user_id User.
	 * @return array id => [ id, name, c (perfume ids), created, updated ]
	 */
	public static function all( $user_id ) {
		$raw = $user_id ? get_user_meta( (int) $user_id, self::META, true ) : array();
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $set ) {
			if ( ! is_array( $set ) || empty( $set['id'] ) || empty( $set['c'] ) ) {
				continue;
			}
			$id         = self::clean_id( $set['id'] );
			$out[ $id ] = array(
				'id'      => $id,
				'name'    => isset( $set['name'] ) ? (string) $set['name'] : '',
				'c'       => ADS_Set::clean_ids( $set['c'] ),
				'created' => isset( $set['created'] ) ? (int) $set['created'] : 0,
				'updated' => isset( $set['updated'] ) ? (int) $set['updated'] : 0,
			);
		}
		uasort(
			$out,
			static function ( $a, $b ) {
				return max( $b['updated'], $b['created'] ) <=> max( $a['updated'], $a['created'] );
			}
		);
		return $out;
	}

	/**
	 * One saved set (null when missing).
	 *
	 * @param int    $user_id User.
	 * @param string $id      Set id.
	 * @return array|null
	 */
	public static function get( $user_id, $id ) {
		$all = self::all( $user_id );
		$id  = self::clean_id( $id );
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Clean a set id.
	 *
	 * @param mixed $id Id.
	 * @return string
	 */
	private static function clean_id( $id ) {
		return substr( preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $id ) ), 0, 20 );
	}

	/**
	 * Store the list. The set touched last goes first, so sets changed in the same second keep
	 * «newest first» (all() sorts by time, and PHP's sort keeps the order of equal items).
	 *
	 * @param int    $user_id User.
	 * @param array  $sets    Sets.
	 * @param string $touched Id of the set saved now.
	 */
	private static function store( $user_id, array $sets, $touched = '' ) {
		if ( '' !== $touched && isset( $sets[ $touched ] ) ) {
			$sets = array( $touched => $sets[ $touched ] ) + $sets;
		}
		if ( $sets ) {
			update_user_meta( (int) $user_id, self::META, array_values( $sets ) );
		} else {
			delete_user_meta( (int) $user_id, self::META );
		}
	}

	/**
	 * Save a new set or update one.
	 *
	 * @param int    $user_id    User.
	 * @param int[]  $components Perfume ids.
	 * @param string $name       Name ('' = «ست شماره N»).
	 * @param string $id         Set to update ('' = new).
	 * @return array|WP_Error The saved set.
	 */
	public static function save( $user_id, array $components, $name = '', $id = '' ) {
		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return new WP_Error( 'ads_login', __( 'برای ذخیره‌ی ست وارد حساب کاربری شوید.', 'aromist-discovery-set' ) );
		}
		$custom = ADS_Set::custom_product_id_if_exists();
		$set    = $custom ? wc_get_product( $custom ) : null;
		if ( ! $set instanceof WC_Product_Discovery_Set ) {
			return new WP_Error( 'ads_off', __( 'ساخت ست اختصاصی فعلاً در دسترس نیست.', 'aromist-discovery-set' ) );
		}

		$components = ADS_Set::clean_ids( $components );
		$error      = ADS_Set::selection_error( $components, ADS_Settings::default_volume(), $set );
		if ( '' !== $error ) {
			return new WP_Error( 'ads_invalid', $error );
		}

		$all  = self::all( $user_id );
		$id   = self::clean_id( $id );
		$name = trim( wp_strip_all_tags( (string) $name ) );
		$name = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 60 ) : substr( $name, 0, 60 );
		$now  = time();

		if ( '' !== $id && isset( $all[ $id ] ) ) {
			$all[ $id ]['c']       = $components;
			$all[ $id ]['name']    = '' !== $name ? $name : $all[ $id ]['name'];
			$all[ $id ]['updated'] = $now;
			self::store( $user_id, $all, $id );
			return $all[ $id ];
		}

		// The same perfumes saved before: keep one copy (rename it when a name was given).
		$key = self::signature( $components );
		foreach ( $all as $existing ) {
			if ( self::signature( $existing['c'] ) === $key ) {
				if ( '' !== $name ) {
					$all[ $existing['id'] ]['name'] = $name;
				}
				$all[ $existing['id'] ]['updated'] = $now;
				self::store( $user_id, $all, $existing['id'] );
				return $all[ $existing['id'] ];
			}
		}

		if ( count( $all ) >= self::max() ) {
			/* translators: %s: number of sets */
			return new WP_Error( 'ads_full', sprintf( __( 'حداکثر %s ست می‌توانید ذخیره کنید؛ اول یکی از ست‌های قبلی را حذف کنید.', 'aromist-discovery-set' ), number_format_i18n( self::max() ) ) );
		}

		$new_id = self::clean_id( 's' . wp_generate_password( 10, false, false ) );
		if ( '' === $name ) {
			/* translators: %s: number */
			$name = sprintf( __( 'ست شماره %s', 'aromist-discovery-set' ), number_format_i18n( count( $all ) + 1 ) );
		}
		$all[ $new_id ] = array(
			'id'      => $new_id,
			'name'    => $name,
			'c'       => $components,
			'created' => $now,
			'updated' => $now,
		);
		self::store( $user_id, $all, $new_id );
		return $all[ $new_id ];
	}

	/**
	 * Delete a set.
	 *
	 * @param int    $user_id User.
	 * @param string $id      Set id.
	 * @return bool Deleted.
	 */
	public static function delete( $user_id, $id ) {
		$all = self::all( $user_id );
		$id  = self::clean_id( $id );
		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}
		unset( $all[ $id ] );
		self::store( $user_id, $all );
		return true;
	}

	/**
	 * Order-independent key of a perfume list.
	 *
	 * @param int[] $components Ids.
	 * @return string
	 */
	private static function signature( array $components ) {
		$ids = array_map( 'intval', $components );
		sort( $ids );
		return implode( '-', $ids );
	}

	/*
	|--------------------------------------------------------------------------
	| Display data
	|--------------------------------------------------------------------------
	*/

	/**
	 * URL of «ست‌های من».
	 *
	 * @return string
	 */
	public static function url() {
		return wc_get_account_endpoint_url( self::ENDPOINT );
	}

	/**
	 * Card data of a saved set: live price, perfumes, problems.
	 *
	 * @param array $set Saved set.
	 * @return array
	 */
	public static function card( array $set ) {
		$custom   = ADS_Set::custom_product_id_if_exists();
		$product  = $custom ? wc_get_product( $custom ) : null;
		$volume   = ADS_Settings::default_volume();
		$problems = array();
		$perfumes = array();

		foreach ( $set['c'] as $id ) {
			$info       = ADS_Perfume_Info::get( $id );
			$perfumes[] = $info;
			$error      = ADS_Set::perfume_error( $id );
			if ( '' !== $error ) {
				/* translators: %s: perfume */
				$problems[] = sprintf( __( '«%s» فعلاً فروخته نمی‌شود.', 'aromist-discovery-set' ), $info['name'] );
			} elseif ( ! ADS_Set::in_builder( $id ) ) {
				/* translators: %s: perfume */
				$problems[] = sprintf( __( '«%s» دیگر در ست اختصاصی ارائه نمی‌شود.', 'aromist-discovery-set' ), $info['name'] );
			}
		}

		$price = null;
		if ( ! $problems && $product instanceof WC_Product_Discovery_Set ) {
			$price = ADS_Pages::price_data( $set['c'], $product );
			$short = ADS_Availability::shortages( ADS_Set::draws( $set['c'], $volume ), 1, WC()->cart ? ADS_Cart::needs() : array() );
			foreach ( $short as $product_id => $row ) {
				$problems[] = ADS_Boxes::is_box( $product_id )
					? __( 'جعبه‌ی ست فعلاً تمام شده است.', 'aromist-discovery-set' )
					/* translators: %s: perfume */
					: sprintf( __( 'موجودی «%s» برای این ست کافی نیست.', 'aromist-discovery-set' ), ADS_Set::perfume_name( $product_id ) );
			}
		}

		return array(
			'id'       => $set['id'],
			'name'     => $set['name'],
			'c'        => $set['c'],
			'date'     => max( $set['created'], $set['updated'] ),
			'perfumes' => $perfumes,
			'price'    => $price,
			'problems' => $problems,
			'can_buy'  => ! $problems && null !== $price && $product instanceof WC_Product_Discovery_Set,
			'edit_url' => add_query_arg( 'saved', $set['id'], ADS_Pages::builder_url() ),
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Account endpoint
	|--------------------------------------------------------------------------
	*/

	/**
	 * Register the endpoint (WooCommerce adds the rewrite endpoint and query var).
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function query_vars( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	/**
	 * «ست‌های من» in the account menu, after «سفارش‌ها» (only when the builder is available or the
	 * customer already has saved sets).
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public function menu_item( $items ) {
		if ( ! is_array( $items ) || ( '' === ADS_Pages::builder_url() && ! self::all( get_current_user_id() ) ) ) {
			return $items;
		}
		// After «کیف پول» when the panel adds it, otherwise after «رهگیری» / «سفارش‌ها».
		$out   = array();
		$added = false;
		$after = isset( $items['wallet'] ) ? 'wallet' : ( isset( $items['tracking'] ) ? 'tracking' : 'orders' );
		foreach ( $items as $key => $label ) {
			$out[ $key ] = $label;
			if ( $key === $after ) {
				$out[ self::ENDPOINT ] = __( 'ست‌های من', 'aromist-discovery-set' );
				$added                 = true;
			}
		}
		if ( ! $added ) {
			$out[ self::ENDPOINT ] = __( 'ست‌های من', 'aromist-discovery-set' );
		}
		return $out;
	}

	/**
	 * Page title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'ست‌های من', 'aromist-discovery-set' );
	}

	/**
	 * A section of the Techpino user panel (AJAX navigation and its icon).
	 *
	 * @param array $sections Sections.
	 * @return array
	 */
	public function panel_section( $sections ) {
		$entry = array(
			'label' => __( 'ست‌های من', 'aromist-discovery-set' ),
			'icon'  => 'box',
			'short' => __( 'ست‌ها', 'aromist-discovery-set' ),
		);
		$out   = array();
		$after = isset( $sections['wallet'] ) ? 'wallet' : ( isset( $sections['tracking'] ) ? 'tracking' : 'orders' );
		foreach ( (array) $sections as $key => $section ) {
			$out[ $key ] = $section;
			if ( $key === $after ) {
				$out[ self::ENDPOINT ] = $entry;
			}
		}
		if ( ! isset( $out[ self::ENDPOINT ] ) ) {
			$out[ self::ENDPOINT ] = $entry;
		}
		return $out;
	}

	/**
	 * The endpoint content.
	 */
	public function render() {
		$user_id = get_current_user_id();
		$sets    = array_map( array( __CLASS__, 'card' ), array_values( self::all( $user_id ) ) );
		echo ADS_Pages::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template output.
			'my-sets',
			array(
				'sets'        => $sets,
				'builder_url' => ADS_Pages::builder_url(),
				'max'         => self::max(),
				'nonce'       => wp_create_nonce( self::NONCE ),
				'product'     => ADS_Set::custom_product_id_if_exists(),
				'in_panel'    => function_exists( 'tpup_icon' ),
			)
		);
	}

	/**
	 * CSS / JS on account pages (the panel loads sections with AJAX, so they are always there).
	 */
	public function assets() {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || ! is_user_logged_in() ) {
			return;
		}
		wp_enqueue_style( 'ads-my-sets', ADS_URL . 'assets/css/my-sets.css', array(), ads_asset_ver( 'assets/css/my-sets.css' ) );
		wp_enqueue_script( 'ads-my-sets', ADS_URL . 'assets/js/my-sets.js', array( 'ads-add' ), ads_asset_ver( 'assets/js/my-sets.js' ), true );
		wp_localize_script(
			'ads-my-sets',
			'adsMySets',
			array(
				'ajax'    => WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'confirm' => __( 'این ست از «ست‌های من» حذف شود؟', 'aromist-discovery-set' ),
				'yes'     => __( 'حذف', 'aromist-discovery-set' ),
				'no'      => __( 'انصراف', 'aromist-discovery-set' ),
				'added'   => __( 'ست به سبد خرید اضافه شد.', 'aromist-discovery-set' ),
				'deleted' => __( 'ست حذف شد.', 'aromist-discovery-set' ),
				'error'   => __( 'انجام نشد. دوباره تلاش کنید.', 'aromist-discovery-set' ),
			)
		);
	}

	/*
	|--------------------------------------------------------------------------
	| AJAX
	|--------------------------------------------------------------------------
	*/

	/**
	 * Common checks of the AJAX requests.
	 *
	 * @return int User id (sends a JSON error and exits otherwise).
	 */
	private static function ajax_user() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json(
				array(
					'success' => false,
					'login'   => wc_get_page_permalink( 'myaccount' ),
					'message' => __( 'برای ذخیره‌ی ست وارد حساب کاربری شوید.', 'aromist-discovery-set' ),
				)
			);
		}
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json(
				array(
					'success' => false,
					'message' => __( 'صفحه منقضی شده است؛ صفحه را دوباره باز کنید.', 'aromist-discovery-set' ),
				)
			);
		}
		return $user_id;
	}

	/**
	 * Save / update.
	 */
	public function ajax_save() {
		$user_id    = self::ajax_user();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in ajax_user().
		$components = isset( $_POST['components'] ) ? ADS_Set::clean_ids( wp_unslash( $_POST['components'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$id         = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$set = self::save( $user_id, $components, $name, $id );
		if ( is_wp_error( $set ) ) {
			wp_send_json(
				array(
					'success' => false,
					'message' => $set->get_error_message(),
				)
			);
		}
		wp_send_json(
			array(
				'success' => true,
				'set'     => array(
					'id'   => $set['id'],
					'name' => $set['name'],
				),
				'url'     => self::url(),
				/* translators: %s: set name */
				'message' => sprintf( __( '«%s» در ست‌های من ذخیره شد.', 'aromist-discovery-set' ), $set['name'] ),
			)
		);
	}

	/**
	 * Delete.
	 */
	public function ajax_delete() {
		$user_id = self::ajax_user();
		$id      = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in ajax_user().
		$ok      = self::delete( $user_id, $id );
		wp_send_json(
			array(
				'success' => $ok,
				'count'   => count( self::all( $user_id ) ),
				'message' => $ok ? __( 'ست حذف شد.', 'aromist-discovery-set' ) : __( 'این ست پیدا نشد.', 'aromist-discovery-set' ),
			)
		);
	}
}
