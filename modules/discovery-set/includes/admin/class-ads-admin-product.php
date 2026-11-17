<?php
/**
 * Product edit screen: the «دیسکاوری ست» type, its «محتوای ست» tab and saving.
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin product hooks.
 */
final class ADS_Admin_Product {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_filter( 'product_type_selector', array( $this, 'type_selector' ) );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'tabs' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save' ), 20 );
		add_action( 'woocommerce_product_options_inventory_product_data', array( $this, 'builder_checkbox' ), 20 );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_builder_checkbox' ), 25 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_action( 'wp_ajax_ads_search_perfumes', array( $this, 'ajax_search' ) );
		add_action( 'wp_ajax_ads_preview', array( $this, 'ajax_preview' ) );
	}

	/**
	 * Add the type to the «نوع محصول» dropdown.
	 *
	 * @param array $types Types.
	 * @return array
	 */
	public function type_selector( $types ) {
		$types['discovery_set'] = __( 'دیسکاوری ست', 'aromist-discovery-set' );
		return $types;
	}

	/**
	 * «محتوای ست» tab (first for this type).
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public function tabs( $tabs ) {
		$tabs['ads_set'] = array(
			'label'    => __( 'محتوای ست', 'aromist-discovery-set' ),
			'target'   => 'ads_set_data',
			'class'    => array( 'show_if_discovery_set' ),
			'priority' => 5,
		);
		return $tabs;
	}

	/**
	 * Is the current screen a product edit screen?
	 *
	 * @return bool
	 */
	private function is_product_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'product' === $screen->id;
	}

	/**
	 * The tab content.
	 */
	public function panel() {
		global $post;
		$product = $post ? wc_get_product( $post->ID ) : null;
		$set     = $product instanceof WC_Product_Discovery_Set ? $product : null;

		$components = $set ? $set->get_components() : array();
		$own        = $set ? $set->get_meta( WC_Product_Discovery_Set::META_VOLUMES, true ) : array();
		$own        = is_array( $own ) && $own ? array_map( 'absint', $own ) : ADS_Settings::volume_list();
		$default    = $set ? $set->get_default_volume() : ADS_Settings::default_volume();
		$mode       = $set ? $set->get_price_mode() : 'auto';
		$is_custom  = $set && $set->is_custom();

		include ADS_PATH . 'includes/admin/views/product-panel.php';
	}

	/**
	 * «در ساخت ست اختصاصی» checkbox of a perfume (Inventory tab, next to its volume stock box).
	 */
	public function builder_checkbox() {
		global $post;
		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}
		$enabled = AVS_Product::is_enabled( $post->ID );
		?>
		<div class="options_group show_if_variable ads-builder-option">
			<p class="form-field">
				<label for="ads_in_builder"><?php esc_html_e( 'دیسکاوری ست اختصاصی', 'aromist-discovery-set' ); ?></label>
				<input type="hidden" name="ads_in_builder_shown" value="1">
				<input type="checkbox" class="checkbox" id="ads_in_builder" name="ads_in_builder" value="yes" <?php checked( ADS_Set::in_builder( $post->ID ) ); ?>>
				<span class="description">
					<?php esc_html_e( 'مشتری بتواند این عطر را در صفحه‌ی «ساخت دیسکاوری ست اختصاصی» انتخاب کند.', 'aromist-discovery-set' ); ?>
					<?php if ( ! $enabled ) : ?>
						<br><strong><?php esc_html_e( 'تا موجودی حجمی این عطر فعال نشود، در سازنده نمایش داده نمی‌شود.', 'aromist-discovery-set' ); ?></strong>
					<?php endif; ?>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Save the checkbox.
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public function save_builder_checkbox( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verified the product save nonce.
		if ( ! $product instanceof WC_Product || $product instanceof WC_Product_Discovery_Set || empty( $_POST['ads_in_builder_shown'] ) ) {
			return;
		}
		if ( ! empty( $_POST['ads_in_builder'] ) ) {
			$product->update_meta_data( ADS_Set::META_IN_BUILDER, 'yes' );
		} else {
			$product->delete_meta_data( ADS_Set::META_IN_BUILDER );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Save the tab and recalculate the stored price / stock status.
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public function save( $product ) {
		if ( ! $product instanceof WC_Product_Discovery_Set ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verified the product save nonce.
		if ( ! $product->is_custom() ) {
			$components = isset( $_POST['ads_components'] ) ? ADS_Set::clean_ids( wp_unslash( $_POST['ads_components'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			ADS_Set::set_components( $product, $components );

			$allowed = ADS_Settings::volume_list();
			$volumes = isset( $_POST['ads_volumes'] ) ? array_values( array_intersect( $allowed, array_map( 'absint', (array) wp_unslash( $_POST['ads_volumes'] ) ) ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$product->update_meta_data( WC_Product_Discovery_Set::META_VOLUMES, $volumes );

			$default = isset( $_POST['ads_default_volume'] ) ? absint( $_POST['ads_default_volume'] ) : 0;
			if ( ! in_array( $default, $volumes, true ) ) {
				$default = $volumes ? $volumes[0] : 0;
			}
			$product->update_meta_data( WC_Product_Discovery_Set::META_DEFAULT, $default );

			$mode = isset( $_POST['ads_price_mode'] ) && 'fixed' === $_POST['ads_price_mode'] ? 'fixed' : 'auto';
			$product->update_meta_data( WC_Product_Discovery_Set::META_PRICE_MODE, $mode );

			$fixed = array();
			if ( isset( $_POST['ads_fixed_prices'] ) && is_array( $_POST['ads_fixed_prices'] ) ) {
				foreach ( wp_unslash( $_POST['ads_fixed_prices'] ) as $ml => $price ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					$price = wc_format_decimal( ADS_Settings::to_number( $price ) );
					if ( '' !== $price && in_array( absint( $ml ), $allowed, true ) ) {
						$fixed[ absint( $ml ) ] = $price;
					}
				}
			}
			$product->update_meta_data( WC_Product_Discovery_Set::META_FIXED_PRICES, $fixed );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$product->set_manage_stock( false );
		$product->set_virtual( false );
		$product->set_downloadable( false );

		ADS_Set::reset_cache();
		ADS_Sync::apply( $product );
	}

	/**
	 * Warnings on the edit screen of a set.
	 */
	public function notices() {
		if ( ! $this->is_product_screen() ) {
			return;
		}
		global $post;
		$product = $post ? wc_get_product( $post->ID ) : null;
		if ( ! $product instanceof WC_Product_Discovery_Set || 'auto-draft' === $product->get_status() ) {
			return;
		}
		if ( $product->is_custom() ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'این محصول سیستمی «دیسکاوری ست» است و ست‌هایی را که مشتری خودش می‌سازد حمل می‌کند. فقط نام و تصویر شاخص (تصویر ست سفارشی در سبد خرید) را تغییر دهید و آن را حذف نکنید.', 'aromist-discovery-set' ) . '</p></div>';
			return;
		}
		$error = ADS_Set::preset_error( $product );
		if ( '' !== $error ) {
			/* translators: %s: problem */
			echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( __( 'این ست قابل فروش نیست: %s', 'aromist-discovery-set' ), $error ) ) . '</p></div>';
		}
	}

	/**
	 * Scripts on product screens.
	 *
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( ! $this->is_product_screen() ) {
			return;
		}
		wp_enqueue_style( 'ads-admin', ADS_URL . 'assets/css/admin.css', array(), ads_asset_ver( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'ads-admin-product', ADS_URL . 'assets/js/admin-product.js', array( 'jquery', 'selectWoo', 'wc-admin-product-meta-boxes' ), ads_asset_ver( 'assets/js/admin-product.js' ), true );
		wp_localize_script(
			'ads-admin-product',
			'adsProduct',
			array(
				'ajax'       => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'ads_admin' ),
				'size'       => ads_set_size(),
				'searchHint' => __( 'نام عطر را بنویسید…', 'aromist-discovery-set' ),
				'noResults'  => __( 'عطری با موجودی حجمی پیدا نشد', 'aromist-discovery-set' ),
				'loading'    => __( 'در حال محاسبه…', 'aromist-discovery-set' ),
			)
		);
	}

	/**
	 * AJAX: perfumes for the picker (selectWoo format).
	 */
	public function ajax_search() {
		check_ajax_referer( 'ads_admin', 'nonce' );
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( null, 403 );
		}
		$term    = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$results = array();
		foreach ( ADS_Set::search_perfumes( $term ) as $id => $name ) {
			$results[] = array(
				'id'   => $id,
				'text' => $name,
			);
		}
		wp_send_json( array( 'results' => $results ) );
	}

	/**
	 * AJAX: price / stock preview of the current (unsaved) choices.
	 */
	public function ajax_preview() {
		check_ajax_referer( 'ads_admin', 'nonce' );
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( null, 403 );
		}
		$components = isset( $_POST['components'] ) ? ADS_Set::clean_ids( wp_unslash( $_POST['components'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$volumes    = isset( $_POST['volumes'] ) ? array_values( array_intersect( ADS_Settings::volume_list(), array_map( 'absint', (array) wp_unslash( $_POST['volumes'] ) ) ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$mode       = isset( $_POST['mode'] ) && 'fixed' === $_POST['mode'] ? 'fixed' : 'auto';
		$fixed      = array();
		if ( isset( $_POST['fixed'] ) && is_array( $_POST['fixed'] ) ) {
			foreach ( wp_unslash( $_POST['fixed'] ) as $ml => $price ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$price = wc_format_decimal( ADS_Settings::to_number( $price ) );
				if ( '' !== $price ) {
					$fixed[ absint( $ml ) ] = $price;
				}
			}
		}

		// An unsaved set object carries the choices into the same code the shop uses.
		$draft = new WC_Product_Discovery_Set();
		ADS_Set::set_components( $draft, $components );
		$draft->update_meta_data( WC_Product_Discovery_Set::META_VOLUMES, $volumes );
		$draft->update_meta_data( WC_Product_Discovery_Set::META_PRICE_MODE, $mode );
		$draft->update_meta_data( WC_Product_Discovery_Set::META_FIXED_PRICES, $fixed );

		ob_start();
		self::render_preview( $draft );
		wp_send_json_success( array( 'html' => ob_get_clean() ) );
	}

	/**
	 * Preview table: price and stock of each volume.
	 *
	 * @param WC_Product_Discovery_Set $set Set (saved or draft).
	 */
	public static function render_preview( $set ) {
		$components = $set->get_components();
		$error      = ADS_Set::preset_error( $set );
		$volumes    = $set->get_volumes();

		if ( '' !== $error ) {
			echo '<p class="ads-preview__error">' . esc_html( $error ) . '</p>';
			if ( count( $components ) !== ads_set_size() || ! $volumes ) {
				return;
			}
		}

		include ADS_PATH . 'includes/admin/views/product-preview.php';
	}
}
