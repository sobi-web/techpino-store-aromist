<?php
/**
 * Admin menu: sets list, settings, boxes.
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin pages.
 */
final class ADS_Admin {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 60 );
		add_action( 'admin_post_ads_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_ads_box', array( $this, 'box_action' ) );
		add_action( 'admin_post_ads_sync_all', array( $this, 'sync_all' ) );
		add_action( 'admin_post_ads_builder_perfumes', array( $this, 'save_builder_perfumes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_filter( 'display_post_states', array( $this, 'post_states' ), 10, 2 );
	}

	/**
	 * Menu.
	 */
	public function menu() {
		$cap = 'manage_woocommerce';
		add_menu_page( __( 'دیسکاوری ست', 'aromist-discovery-set' ), __( 'دیسکاوری ست', 'aromist-discovery-set' ), $cap, 'ads-sets', array( $this, 'page_sets' ), 'dashicons-screenoptions', 56 );
		add_submenu_page( 'ads-sets', __( 'ست‌های آماده', 'aromist-discovery-set' ), __( 'ست‌های آماده', 'aromist-discovery-set' ), $cap, 'ads-sets', array( $this, 'page_sets' ) );
		add_submenu_page( 'ads-sets', __( 'افزودن ست', 'aromist-discovery-set' ), __( 'افزودن ست', 'aromist-discovery-set' ), 'edit_products', 'post-new.php?post_type=product&ads_new=1' );
		add_submenu_page( 'ads-sets', __( 'عطرهای ست اختصاصی', 'aromist-discovery-set' ), __( 'عطرهای ست اختصاصی', 'aromist-discovery-set' ), $cap, 'ads-builder-perfumes', array( $this, 'page_builder_perfumes' ) );
		add_submenu_page( 'ads-sets', __( 'تنظیمات دیسکاوری ست', 'aromist-discovery-set' ), __( 'تنظیمات', 'aromist-discovery-set' ), $cap, 'ads-settings', array( $this, 'page_settings' ) );
	}

	/**
	 * Is one of the plugin pages open?
	 *
	 * @return bool
	 */
	private function is_plugin_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'ads-sets', 'ads-settings', 'ads-builder-perfumes' ), true );
	}

	/**
	 * CSS / JS of the plugin pages.
	 */
	public function assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
			wp_enqueue_style( 'ads-admin', ADS_URL . 'assets/css/admin.css', array(), ads_asset_ver( 'assets/css/admin.css' ) );
		}
		if ( ! $this->is_plugin_page() ) {
			return;
		}
		wp_enqueue_style( 'ads-admin', ADS_URL . 'assets/css/admin.css', array(), ads_asset_ver( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'ads-admin-settings', ADS_URL . 'assets/js/admin-settings.js', array(), ads_asset_ver( 'assets/js/admin-settings.js' ), true );
	}

	/**
	 * Ready-made sets.
	 */
	public function page_sets() {
		$ids = ADS_Set::preset_ids();
		include ADS_PATH . 'includes/admin/views/page-sets.php';
	}

	/**
	 * Settings + boxes.
	 */
	public function page_settings() {
		$settings = ADS_Settings::all();
		$volumes  = ADS_Settings::volumes();
		$boxes    = ADS_Boxes::all();
		include ADS_PATH . 'includes/admin/views/page-settings.php';
	}

	/**
	 * Perfumes offered in the builder.
	 */
	public function page_builder_perfumes() {
		$rows = self::builder_perfume_rows();
		include ADS_PATH . 'includes/admin/views/page-builder-perfumes.php';
	}

	/**
	 * Every published variable perfume with what the builder page needs to know.
	 *
	 * @return array[] [ id, name, image, edit, enabled (volume stock), pool, price, error, on ]
	 */
	public static function builder_perfume_rows() {
		$ids    = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_tax_query
					array(
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => 'variable',
					),
				),
			)
		);
		$volume = ADS_Settings::default_volume();
		$rows   = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$enabled = AVS_Product::is_enabled( $id );
			$price   = ADS_Pricing::perfume_price( $id, $volume );
			$rows[]  = array(
				'id'      => (int) $id,
				'name'    => $product->get_name(),
				'image'   => $product->get_image( array( 40, 40 ) ),
				'edit'    => get_edit_post_link( $id ),
				'enabled' => $enabled,
				'pool'    => $enabled && ! ADS_Availability::is_unlimited_perfume( $id ) ? AVS_Product::pool( $id ) : null,
				'price'   => $price ? (float) $price['price'] : 0.0,
				'error'   => ADS_Set::perfume_error( $id ),
				'on'      => ADS_Set::in_builder( $id ),
				'avs_url' => class_exists( 'AVS_Admin' ) ? AVS_Admin::url( $enabled ? 'avs' : 'avs-add', array( 'product_id' => $id ) ) : '',
			);
		}
		// Perfumes that can be chosen first (the list is already sorted by name).
		usort(
			$rows,
			static function ( $a, $b ) {
				return (int) ( '' === $b['error'] ) - (int) ( '' === $a['error'] );
			}
		);
		return $rows;
	}

	/**
	 * Save the builder perfumes.
	 */
	public function save_builder_perfumes() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'aromist-discovery-set' ) );
		}
		check_admin_referer( 'ads_builder_perfumes' );
		$on = isset( $_POST['ads_in_builder'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ads_in_builder'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		foreach ( self::builder_perfume_rows() as $row ) {
			ADS_Set::set_in_builder( $row['id'], in_array( $row['id'], $on, true ) );
		}
		$this->back( 'ads-builder-perfumes', 'perfumes_saved' );
	}

	/**
	 * Save the settings form.
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'aromist-discovery-set' ) );
		}
		check_admin_referer( 'ads_save_settings' );
		$input = isset( $_POST['ads'] ) && is_array( $_POST['ads'] ) ? wp_unslash( $_POST['ads'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in ADS_Settings::save().
		ADS_Settings::save( $input );
		ADS_Sync::flush();
		$this->back( 'ads-settings', 'saved' );
	}

	/**
	 * Create a box / change its stock.
	 */
	public function box_action() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'aromist-discovery-set' ) );
		}
		check_admin_referer( 'ads_box' );

		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		if ( 'create' === $do ) {
			$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			if ( '' === $name ) {
				$this->back( 'ads-settings', 'box_name' );
			}
			$stock = isset( $_POST['stock'] ) ? absint( ADS_Settings::to_number( wp_unslash( $_POST['stock'] ) ) ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			try {
				ADS_Boxes::create( $name, $stock );
			} catch ( \Throwable $e ) {
				$this->back( 'ads-settings', 'box_error' );
			}
			$this->back( 'ads-settings', 'box_created' );
		}

		$id        = isset( $_POST['box'] ) ? absint( $_POST['box'] ) : 0;
		$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : 'set';
		$amount    = isset( $_POST['amount'] ) ? absint( ADS_Settings::to_number( wp_unslash( $_POST['amount'] ) ) ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$result    = ADS_Boxes::adjust( $id, $operation, $amount );
		ADS_Sync::flush();
		$this->back( 'ads-settings', is_wp_error( $result ) ? 'box_error' : 'box_saved' );
	}

	/**
	 * Recalculate every set now.
	 */
	public function sync_all() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'aromist-discovery-set' ) );
		}
		check_admin_referer( 'ads_sync_all' );
		ADS_Sync::sync_all();
		$this->back( 'ads-sets', 'synced' );
	}

	/**
	 * Redirect back to a page with a message.
	 *
	 * @param string $page Page slug.
	 * @param string $msg  Message key.
	 */
	private function back( $page, $msg ) {
		wp_safe_redirect( add_query_arg( array( 'page' => $page, 'ads_msg' => $msg ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Messages and warnings.
	 */
	public function notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg      = isset( $_GET['ads_msg'] ) ? sanitize_key( wp_unslash( $_GET['ads_msg'] ) ) : '';
		$messages = array(
			'saved'       => array( 'success', __( 'تنظیمات ذخیره شد و قیمت ست‌ها دوباره محاسبه شد.', 'aromist-discovery-set' ) ),
			'box_created' => array( 'success', __( 'جعبه ساخته شد. در جدول حجم‌ها آن را به حجم موردنظر وصل کنید.', 'aromist-discovery-set' ) ),
			'box_saved'   => array( 'success', __( 'موجودی جعبه ثبت شد.', 'aromist-discovery-set' ) ),
			'box_error'   => array( 'error', __( 'تغییر موجودی جعبه انجام نشد.', 'aromist-discovery-set' ) ),
			'box_name'    => array( 'error', __( 'نام جعبه را وارد کنید.', 'aromist-discovery-set' ) ),
			'synced'      => array( 'success', __( 'قیمت و موجودی همه‌ی ست‌ها دوباره محاسبه شد.', 'aromist-discovery-set' ) ),
			'perfumes_saved' => array( 'success', __( 'عطرهای ست اختصاصی ذخیره شد.', 'aromist-discovery-set' ) ),
		);
		if ( $this->is_plugin_page() && isset( $messages[ $msg ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $msg ][0] ), esc_html( $messages[ $msg ][1] ) );
		}

		$setup_error = ADS_Install::setup_error();
		if ( '' !== $setup_error && current_user_can( 'manage_woocommerce' ) ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'دیسکاوری ست:', 'aromist-discovery-set' ) . '</strong> '
				. esc_html__( 'راه‌اندازی اولیه (ساخت جعبه و محصول «ست سفارشی») به خطای یک افزونه‌ی دیگر خورد و انجام نشد. سایت سالم است؛ ده دقیقه‌ی دیگر دوباره امتحان می‌شود. خطا:', 'aromist-discovery-set' )
				. ' <code>' . esc_html( $setup_error ) . '</code></p></div>';
		}

		if ( ! $this->is_plugin_page() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( 'yes' !== get_option( 'woocommerce_manage_stock' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'مدیریت موجودی ووکامرس خاموش است؛ ست‌ها از مخزن عطرها و جعبه‌ها کم نمی‌کنند.', 'aromist-discovery-set' ) . '</p></div>';
		}
		$empty = array();
		foreach ( ADS_Settings::volumes() as $row ) {
			$box = ADS_Boxes::is_box( $row['box'] ) ? (int) $row['box'] : 0;
			if ( $box && ADS_Boxes::stock( $box ) <= 0 ) {
				$empty[ $box ] = ADS_Boxes::name( $box );
			}
		}
		if ( $empty ) {
			/* translators: %s: box names */
			echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( __( 'موجودی این جعبه‌ها صفر است و ست‌های حجم‌های مربوط ناموجود نمایش داده می‌شوند: %s', 'aromist-discovery-set' ), ads_join( $empty ) ) ) . '</p></div>';
		}
	}

	/**
	 * «سیستمی» / «جعبه» labels in the products list.
	 *
	 * @param array   $states States.
	 * @param WP_Post $post   Post.
	 * @return array
	 */
	public function post_states( $states, $post ) {
		if ( 'product' !== $post->post_type ) {
			return $states;
		}
		if ( (int) $post->ID === absint( ADS_Settings::get( 'custom_product_id' ) ) ) {
			$states['ads_custom'] = __( 'دیسکاوری ست: ست سفارشی (سیستمی)', 'aromist-discovery-set' );
		} elseif ( ADS_Boxes::is_box( $post->ID ) ) {
			$states['ads_box'] = __( 'جعبه‌ی دیسکاوری ست', 'aromist-discovery-set' );
		}
		return $states;
	}
}
