<?php
/**
 * Product edit screen integration.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product screen.
 */
final class AVS_Admin_Product {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_product_options_inventory_product_data', array( $this, 'inventory_box' ) );
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'variation_field' ), 20, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation' ), 10, 2 );

		// Keep a volume product consistent whatever is posted from the edit screen.
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'force_parent' ) );
		add_action( 'woocommerce_admin_process_variation_object', array( $this, 'force_variation' ), 10, 2 );

		add_action( 'admin_head-post.php', array( $this, 'screen_css' ) );
		add_action( 'admin_footer-post.php', array( $this, 'screen_js' ) );

		// Warn before deactivating the plugin.
		add_filter( 'plugin_action_links_' . plugin_basename( TECHPINO_STORE_FILE ), array( $this, 'deactivate_warning' ), 20 );
	}

	/**
	 * Current product being edited.
	 *
	 * @return int
	 */
	private function current_product_id() {
		global $post;
		return $post && 'product' === $post->post_type ? (int) $post->ID : 0;
	}

	/**
	 * Box in the Inventory tab.
	 */
	public function inventory_box() {
		$product_id = $this->current_product_id();
		if ( ! $product_id ) {
			return;
		}
		$enabled   = AVS_Product::is_enabled( $product_id );
		$unlimited = $enabled && AVS_Product::is_unlimited( $product_id );
		?>
		<div class="options_group show_if_variable avs-inventory-box">
			<p class="form-field">
				<label><?php esc_html_e( 'موجودی حجمی', 'aromist-volume-stock' ); ?></label>
				<?php if ( $unlimited ) : ?>
					<span class="avs-inv-status">
						<strong style="color:#009879">● <?php esc_html_e( 'فعال — نامحدود', 'aromist-volume-stock' ); ?></strong>
						<br>
						<span class="description"><?php esc_html_e( 'موجودی این عطر هنوز تعیین نشده و همه‌ی حجم‌ها بدون محدودیت فروخته می‌شوند. وقتی موجودی مخزن (میل) را تعیین کنید، فروش به همان مقدار محدود می‌شود.', 'aromist-volume-stock' ); ?></span>
						<br>
						<a class="button" href="<?php echo esc_url( AVS_Admin::url( 'avs', array( 'product_id' => $product_id ) ) ); ?>"><?php esc_html_e( 'تعیین موجودی در پنل موجودی حجمی', 'aromist-volume-stock' ); ?></a>
					</span>
				<?php elseif ( $enabled ) : ?>
					<span class="avs-inv-status">
						<strong style="color:#009879">● <?php esc_html_e( 'فعال', 'aromist-volume-stock' ); ?></strong>
						— <?php esc_html_e( 'موجودی مخزن:', 'aromist-volume-stock' ); ?>
						<strong><?php echo esc_html( AVS_Settings::format_ml( AVS_Product::pool( $product_id ) ) ); ?></strong>
						<br>
						<span class="description"><?php esc_html_e( '«تعداد موجودی» بالا همان موجودی مخزن به میلی‌لیتر است. برای ثبت ورودی/هدررفت با دلیل و تاریخچه از پنل موجودی حجمی استفاده کنید.', 'aromist-volume-stock' ); ?></span>
						<br>
						<a class="button" href="<?php echo esc_url( AVS_Admin::url( 'avs', array( 'product_id' => $product_id ) ) ); ?>"><?php esc_html_e( 'مدیریت در پنل موجودی حجمی', 'aromist-volume-stock' ); ?></a>
					</span>
				<?php else : ?>
					<span class="description">
						<?php esc_html_e( 'اگر این عطر در حجم‌های مختلف فروخته می‌شود و موجودی آن به میلی‌لیتر است، موجودی حجمی را فعال کنید.', 'aromist-volume-stock' ); ?>
						<br>
						<a class="button" href="<?php echo esc_url( AVS_Admin::url( 'avs-add', array( 'product_id' => $product_id ) ) ); ?>"><?php esc_html_e( 'فعال‌سازی موجودی حجمی', 'aromist-volume-stock' ); ?></a>
					</span>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * "Ml per unit" field in each variation.
	 *
	 * @param int     $loop           Index.
	 * @param array   $variation_data Data.
	 * @param WP_Post $variation      Variation post.
	 */
	public function variation_field( $loop, $variation_data, $variation ) {
		$variation_id = (int) $variation->ID;
		$value        = AVS_Product::variation_ml( $variation_id );
		$object       = wc_get_product( $variation_id );
		$detected     = $object instanceof WC_Product_Variation ? AVS_Product::detect_ml( $object ) : 0;
		$enabled      = AVS_Product::is_enabled( wp_get_post_parent_id( $variation_id ) );
		?>
		<p class="form-row form-row-first avs-variation-ml">
			<label for="avs_ml_<?php echo esc_attr( $loop ); ?>">
				<?php esc_html_e( 'حجم هر عدد (میل)', 'aromist-volume-stock' ); ?>
				<?php if ( $enabled ) : ?>
					<span style="color:#009879">— <?php esc_html_e( 'برداشت از مخزن حجمی', 'aromist-volume-stock' ); ?></span>
				<?php endif; ?>
			</label>
			<input type="number" min="0" step="1" class="short" id="avs_ml_<?php echo esc_attr( $loop ); ?>" name="avs_ml[<?php echo esc_attr( $loop ); ?>]" value="<?php echo $value ? esc_attr( $value ) : ''; ?>" placeholder="<?php echo $detected ? esc_attr( $detected ) : ''; ?>">
		</p>
		<?php
	}

	/**
	 * Save the variation field (WooCommerce already verified the nonce of the variations request).
	 *
	 * @param int $variation_id Variation id.
	 * @param int $i            Index.
	 */
	public function save_variation( $variation_id, $i ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies "save-variations".
		if ( ! current_user_can( 'edit_product', $variation_id ) || ! isset( $_POST['avs_ml'][ $i ] ) ) {
			return;
		}
		$raw = sanitize_text_field( wp_unslash( $_POST['avs_ml'][ $i ] ) );
		// phpcs:enable

		$parent_id = wp_get_post_parent_id( $variation_id );
		$ml        = AVS_Product::parse_ml( $raw );

		if ( '' === $raw ) {
			// Never remove the ml of a variation that draws from an active pool.
			if ( ! AVS_Product::is_enabled( $parent_id ) ) {
				delete_post_meta( $variation_id, AVS_Product::META_ML );
			}
			return;
		}
		if ( $ml > 0 ) {
			update_post_meta( $variation_id, AVS_Product::META_ML, $ml );
			if ( AVS_Product::is_enabled( $parent_id ) ) {
				AVS_Stock::sync_children_status( $parent_id );
			}
		}
	}

	/**
	 * Volume parent with a pool: stock stays managed on the parent, no backorders.
	 * «Unlimited» parent: stays unlimited, unless the shop turns «مدیریت موجودی» on and enters an
	 * amount here — that amount becomes its pool (logged as an edit from the product screen).
	 *
	 * @param WC_Product $product Product.
	 */
	public function force_parent( $product ) {
		if ( ! AVS_Product::is_volume_parent( $product ) ) {
			return;
		}
		if ( AVS_Product::is_unlimited( $product->get_id() ) && ! $product->get_manage_stock( 'edit' ) ) {
			return;
		}
		$product->set_manage_stock( true );
		$product->set_backorders( 'no' );
	}

	/**
	 * Volume variation: never manages its own stock.
	 *
	 * @param WC_Product_Variation $variation Variation.
	 * @param int                  $i         Index.
	 */
	public function force_variation( $variation, $i ) {
		if ( $variation instanceof WC_Product_Variation && AVS_Product::is_enabled( $variation->get_parent_id() ) ) {
			$variation->set_manage_stock( false );
		}
	}

	/**
	 * Hide per-variation stock fields on volume products.
	 */
	public function screen_css() {
		$product_id = $this->current_product_id();
		if ( ! $product_id || ! AVS_Product::is_enabled( $product_id ) ) {
			return;
		}
		echo '<style>
			#variable_product_options .woocommerce_variation .variable_manage_stock,
			#variable_product_options .woocommerce_variation label.tips:has(.variable_manage_stock),
			#variable_product_options .woocommerce_variation .show_if_variation_manage_stock { display:none !important; }
			.avs-inventory-box { background:#f3faf8; }
		</style>';
	}

	/**
	 * Relabel the parent stock field for volume products.
	 */
	public function screen_js() {
		$product_id = $this->current_product_id();
		if ( ! $product_id || ! AVS_Product::is_enabled( $product_id ) ) {
			return;
		}
		?>
		<script>
		( function () {
			var label = document.querySelector( 'label[for="_stock"]' );
			if ( label ) {
				label.textContent = <?php echo wp_json_encode( __( 'موجودی مخزن (میل)', 'aromist-volume-stock' ) ); ?>;
			}
			var low = document.querySelector( 'label[for="_low_stock_amount"]' );
			if ( low ) {
				low.textContent = <?php echo wp_json_encode( __( 'هشدار کمبود (میل)', 'aromist-volume-stock' ) ); ?>;
			}
		} )();
		</script>
		<?php
	}

	/**
	 * Confirmation before deactivating: without the plugin, the pool (ml) would be read as units.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function deactivate_warning( $links ) {
		if ( isset( $links['deactivate'] ) && AVS_Reports::enabled_product_ids() ) {
			$message             = __( 'هشدار: تا وقتی محصولی موجودی حجمی فعال دارد، غیرفعال کردن این افزونه باعث می‌شود موجودی میلی‌لیتری آن محصولات «عدد» حساب شود (مثلاً ۱۰۰۰ میل = ۱۰۰۰ عدد). ابتدا از پنل، موجودی حجمی محصولات را غیرفعال کنید. ادامه می‌دهید؟', 'aromist-volume-stock' );
			$links['deactivate'] = str_replace( '<a ', '<a onclick="return confirm(' . esc_attr( wp_json_encode( $message ) ) . ');" ', $links['deactivate'] );
		}
		return $links;
	}
}
