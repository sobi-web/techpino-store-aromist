<?php
/**
 * Admin panel: menus, assets, form handlers.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin controller.
 */
final class AVS_Admin {

	const CAP = 'manage_woocommerce';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );

		foreach ( array( 'enable', 'disable', 'adjust', 'update_product', 'save_settings', 'export_history', 'export_report' ) as $action ) {
			add_action( 'admin_post_avs_' . $action, array( $this, 'handle_' . $action ) );
		}

		add_filter( 'plugin_action_links_' . plugin_basename( AVS_FILE ), array( $this, 'plugin_links' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Menu & assets
	|--------------------------------------------------------------------------
	*/

	/**
	 * Menus.
	 */
	public function menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M10 1.5s-6 6.6-6 11a6 6 0 0 0 12 0c0-4.4-6-11-6-11zm0 15.5a4.5 4.5 0 0 1-4.5-4.5h1.5a3 3 0 0 0 3 3z"/></svg>' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		add_menu_page( __( 'موجودی حجمی', 'aromist-volume-stock' ), __( 'موجودی حجمی', 'aromist-volume-stock' ), self::CAP, 'avs', array( $this, 'page_dashboard' ), $icon, 56 );
		add_submenu_page( 'avs', __( 'محصولات حجمی', 'aromist-volume-stock' ), __( 'محصولات حجمی', 'aromist-volume-stock' ), self::CAP, 'avs', array( $this, 'page_dashboard' ) );
		add_submenu_page( 'avs', __( 'افزودن محصول', 'aromist-volume-stock' ), __( 'افزودن محصول', 'aromist-volume-stock' ), self::CAP, 'avs-add', array( $this, 'page_add' ) );
		add_submenu_page( 'avs', __( 'تاریخچه موجودی', 'aromist-volume-stock' ), __( 'تاریخچه', 'aromist-volume-stock' ), self::CAP, 'avs-history', array( $this, 'page_history' ) );
		add_submenu_page( 'avs', __( 'گزارش مصرف', 'aromist-volume-stock' ), __( 'گزارش‌ها', 'aromist-volume-stock' ), self::CAP, 'avs-reports', array( $this, 'page_reports' ) );
		add_submenu_page( 'avs', __( 'تنظیمات موجودی حجمی', 'aromist-volume-stock' ), __( 'تنظیمات', 'aromist-volume-stock' ), self::CAP, 'avs-settings', array( $this, 'page_settings' ) );

	}

	/**
	 * Is the current screen one of the plugin pages?
	 *
	 * @return bool
	 */
	private function is_plugin_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return in_array( $page, array( 'avs', 'avs-add', 'avs-history', 'avs-reports', 'avs-settings' ), true );
	}

	/**
	 * CSS / JS.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function assets( $hook ) {
		if ( ! $this->is_plugin_page() ) {
			return;
		}
		wp_enqueue_style( 'avs-admin', AVS_URL . 'assets/css/admin.css', array(), AVS_VERSION . '.' . filemtime( AVS_PATH . 'assets/css/admin.css' ) );
		wp_enqueue_script( 'avs-admin', AVS_URL . 'assets/js/admin.js', array(), AVS_VERSION . '.' . filemtime( AVS_PATH . 'assets/js/admin.js' ), true );

		// WooCommerce enhanced product search on the "add" page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && 'avs-add' === $_GET['page'] ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}
	}

	/**
	 * Settings link in the plugins list.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function plugin_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url( 'avs' ) ) . '">' . esc_html__( 'پنل موجودی حجمی', 'aromist-volume-stock' ) . '</a>' );
		return $links;
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Admin URL of a plugin page.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Query args.
	 * @return string
	 */
	public static function url( $page, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Store a one-time notice for the current user.
	 *
	 * @param string $message Message.
	 * @param string $type    success | error | warning.
	 */
	public static function flash( $message, $type = 'success' ) {
		set_transient( 'avs_flash_' . get_current_user_id(), array( 'message' => $message, 'type' => $type ), 120 );
	}

	/**
	 * Print and clear the one-time notice.
	 */
	public static function print_flash() {
		$key   = 'avs_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		if ( ! $flash ) {
			return;
		}
		delete_transient( $key );
		printf(
			'<div class="notice notice-%1$s is-dismissible avs-notice"><p>%2$s</p></div>',
			esc_attr( in_array( $flash['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $flash['type'] : 'info' ),
			wp_kses_post( $flash['message'] )
		);
	}

	/**
	 * Verify capability + nonce of a form handler.
	 *
	 * @param string $action Nonce action.
	 */
	private function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'شما اجازه انجام این کار را ندارید.', 'aromist-volume-stock' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * Posted integer.
	 *
	 * @param string $key Key.
	 * @return int
	 */
	private function post_int( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		return isset( $_POST[ $key ] ) ? (int) wc_stock_amount( wp_unslash( $_POST[ $key ] ) ) : 0;
	}

	/**
	 * Posted text.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private function post_text( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	/**
	 * Posted ml map ( variation_id => ml ).
	 *
	 * @return array
	 */
	private function post_ml_map() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$raw = isset( $_POST['ml'] ) && is_array( $_POST['ml'] ) ? wp_unslash( $_POST['ml'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$map = array();
		foreach ( $raw as $id => $value ) {
			$map[ absint( $id ) ] = sanitize_text_field( $value );
		}
		return $map;
	}

	/**
	 * Redirect back (safe).
	 *
	 * @param string $fallback Fallback URL.
	 */
	private function back( $fallback ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$to = isset( $_POST['_avs_back'] ) && is_string( $_POST['_avs_back'] ) ? esc_url_raw( wp_unslash( $_POST['_avs_back'] ) ) : '';
		wp_safe_redirect( $to ? $to : $fallback );
		exit;
	}

	/*
	|--------------------------------------------------------------------------
	| Form handlers (admin-post.php)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Enable volume stock on a product.
	 */
	public function handle_enable() {
		$this->guard( 'avs_enable' );
		$product_id = $this->post_int( 'product_id' );

		$result = AVS_Manager::enable(
			$product_id,
			array(
				'ml_map'   => $this->post_ml_map(),
				'pool_ml'  => $this->post_int( 'pool_ml' ),
				'low_ml'   => $this->post_int( 'low_ml' ),
				'waste_ml' => $this->post_int( 'waste_ml' ),
				'note'     => $this->post_text( 'note' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			self::flash( $result->get_error_message(), 'error' );
			wp_safe_redirect( self::url( 'avs-add', array( 'product_id' => $product_id ) ) );
			exit;
		}

		self::flash( __( 'موجودی حجمی فعال شد.', 'aromist-volume-stock' ) );
		wp_safe_redirect( self::url( 'avs', array( 'product_id' => $product_id ) ) );
		exit;
	}

	/**
	 * Disable volume stock.
	 */
	public function handle_disable() {
		$this->guard( 'avs_disable' );
		$product_id = $this->post_int( 'product_id' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		if ( empty( $_POST['confirm'] ) ) {
			self::flash( __( 'برای غیرفعال‌سازی، گزینه تأیید را علامت بزنید.', 'aromist-volume-stock' ), 'error' );
			$this->back( self::url( 'avs', array( 'product_id' => $product_id ) ) );
		}

		$result = AVS_Manager::disable( $product_id );
		if ( is_wp_error( $result ) ) {
			self::flash( $result->get_error_message(), 'error' );
			$this->back( self::url( 'avs', array( 'product_id' => $product_id ) ) );
		}

		self::flash( __( 'موجودی حجمی غیرفعال شد و موجودی هر حجم جداگانه تنظیم شد.', 'aromist-volume-stock' ), 'warning' );
		wp_safe_redirect( self::url( 'avs' ) );
		exit;
	}

	/**
	 * Manual pool change.
	 */
	public function handle_adjust() {
		$this->guard( 'avs_adjust' );
		$product_id = $this->post_int( 'product_id' );
		$operation  = sanitize_key( $this->post_text( 'operation' ) );

		$result = AVS_Manager::adjust( $product_id, $operation, $this->post_int( 'amount' ), sanitize_key( $this->post_text( 'reason' ) ), $this->post_text( 'note' ) );

		if ( is_wp_error( $result ) ) {
			self::flash( $result->get_error_message(), 'error' );
		} else {
			$product = wc_get_product( $product_id );
			self::flash(
				sprintf(
					/* translators: 1: product, 2: before, 3: after */
					__( 'موجودی «%1$s» از %2$s به %3$s تغییر کرد.', 'aromist-volume-stock' ),
					$product ? esc_html( $product->get_name() ) : '',
					esc_html( AVS_Settings::format_ml( $result['before'] ) ),
					esc_html( AVS_Settings::format_ml( $result['after'] ) )
				)
			);
		}
		$this->back( self::url( 'avs', array( 'product_id' => $product_id ) ) );
	}

	/**
	 * Product settings (ml per size, waste, low threshold).
	 */
	public function handle_update_product() {
		$this->guard( 'avs_update_product' );
		$product_id = $this->post_int( 'product_id' );

		$result = AVS_Manager::update_settings( $product_id, $this->post_ml_map(), $this->post_int( 'waste_ml' ), $this->post_int( 'low_ml' ) );

		if ( is_wp_error( $result ) ) {
			self::flash( $result->get_error_message(), 'error' );
		} else {
			self::flash( __( 'تنظیمات محصول ذخیره شد.', 'aromist-volume-stock' ) );
		}
		$this->back( self::url( 'avs', array( 'product_id' => $product_id ) ) );
	}

	/**
	 * Plugin settings.
	 */
	public function handle_save_settings() {
		$this->guard( 'avs_save_settings' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in guard(), sanitized in save().
		$input = isset( $_POST['avs'] ) && is_array( $_POST['avs'] ) ? wp_unslash( $_POST['avs'] ) : array();
		AVS_Settings::save( $input );
		self::flash( __( 'تنظیمات ذخیره شد.', 'aromist-volume-stock' ) );
		wp_safe_redirect( self::url( 'avs-settings' ) );
		exit;
	}

	/**
	 * Ledger filters from a request array.
	 *
	 * @param array $src Source ($_GET / $_POST).
	 * @return array
	 */
	public static function history_filters( array $src ) {
		$filters = array();
		if ( ! empty( $src['product_id'] ) ) {
			$filters['product_id'] = absint( $src['product_id'] );
		}
		if ( ! empty( $src['reason'] ) ) {
			$reason = sanitize_key( wp_unslash( $src['reason'] ) );
			if ( 'orders' === $reason ) {
				$filters['reason'] = AVS_Ledger::order_reasons();
			} elseif ( array_key_exists( $reason, AVS_Ledger::reasons() ) ) {
				$filters['reason'] = $reason;
			}
		}
		if ( ! empty( $src['order_id'] ) ) {
			$filters['order_id'] = absint( $src['order_id'] );
		}
		$tz = wp_timezone();
		foreach ( array( 'from' => 'date_from', 'to' => 'date_to' ) as $in => $out ) {
			$value = isset( $src[ $in ] ) ? sanitize_text_field( wp_unslash( $src[ $in ] ) ) : '';
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				$date = new DateTimeImmutable( $value, $tz );
				if ( 'date_to' === $out ) {
					$date = $date->modify( '+1 day' );
				}
				$filters[ $out ] = $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
			}
		}
		return $filters;
	}

	/**
	 * CSV of the ledger.
	 */
	public function handle_export_history() {
		$this->guard( 'avs_export_history' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$filters             = self::history_filters( $_POST );
		$filters['per_page'] = 5000;

		$rows = array();
		$page = 1;
		do {
			$filters['page'] = $page;
			$batch           = AVS_Ledger::query( $filters );
			foreach ( $batch as $row ) {
				$product   = wc_get_product( $row['product_id'] );
				$variation = $row['variation_id'] ? wc_get_product( $row['variation_id'] ) : null;
				$user      = $row['user_id'] ? get_userdata( $row['user_id'] ) : null;
				$rows[]    = array(
					wp_date( 'Y-m-d H:i', strtotime( $row['created_at'] . ' UTC' ) ),
					$product ? $product->get_name() : '#' . $row['product_id'],
					$variation ? AVS_Product::variation_ml( $variation->get_id() ) : '',
					$row['order_id'] ? (int) $row['order_id'] : '',
					AVS_Ledger::reason_label( $row['reason'] ),
					(int) $row['quantity'],
					(int) $row['change_ml'],
					null === $row['balance_ml'] ? '' : (int) $row['balance_ml'],
					$user ? $user->display_name : ( $row['user_id'] ? '#' . $row['user_id'] : __( 'سیستم', 'aromist-volume-stock' ) ),
					$row['note'],
				);
			}
			++$page;
		} while ( count( $batch ) === 5000 && $page < 50 );

		AVS_Reports::send_csv(
			'volume-stock-history-' . wp_date( 'Y-m-d' ) . '.csv',
			array( 'تاریخ', 'محصول', 'حجم (میل)', 'سفارش', 'دلیل', 'تعداد', 'تغییر (میل)', 'موجودی بعد (میل)', 'کاربر', 'توضیحات' ),
			$rows
		);
	}

	/**
	 * CSV of the consumption report.
	 */
	public function handle_export_report() {
		$this->guard( 'avs_export_report' );
		$range = AVS_Reports::range_from_request();
		$rows  = array();
		foreach ( AVS_Reports::rows( $range ) as $row ) {
			$rows[] = array(
				$row['name'],
				$row['sku'],
				$row['pool'],
				$row['units'],
				$row['sold_ml'],
				$row['in_ml'],
				$row['waste_ml'],
				$row['fixes_ml'],
				round( $row['avg_daily'], 1 ),
				null === $row['days_left'] ? '' : $row['days_left'],
			);
		}
		AVS_Reports::send_csv(
			'volume-stock-report-' . $range['from'] . '-' . $range['to'] . '.csv',
			array( 'محصول', 'SKU', 'موجودی فعلی (میل)', 'تعداد فروش', 'مصرف فروش (میل)', 'ورودی انبار (میل)', 'هدررفت (میل)', 'اصلاحات (میل)', 'میانگین مصرف روزانه (میل)', 'تخمین روز تا اتمام' ),
			$rows
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Pages
	|--------------------------------------------------------------------------
	*/

	/**
	 * Render a view.
	 *
	 * @param string $view File name without extension.
	 * @param array  $vars Variables.
	 */
	private function view( $view, array $vars = array() ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'aromist-volume-stock' ) );
		}
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		echo '<div class="wrap avs-wrap">';
		include AVS_PATH . 'includes/admin/views/' . $view . '.php';
		echo '</div>';
	}

	/**
	 * Products list or single product.
	 */
	public function page_dashboard() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		if ( $product_id ) {
			$this->view( 'page-product', array( 'product_id' => $product_id ) );
			return;
		}
		$this->view( 'page-dashboard' );
	}

	/**
	 * Enable wizard.
	 */
	public function page_add() {
		$this->view( 'page-add' );
	}

	/**
	 * History.
	 */
	public function page_history() {
		$this->view( 'page-history' );
	}

	/**
	 * Reports.
	 */
	public function page_reports() {
		$this->view( 'page-reports' );
	}

	/**
	 * Settings.
	 */
	public function page_settings() {
		$this->view( 'page-settings' );
	}
}
