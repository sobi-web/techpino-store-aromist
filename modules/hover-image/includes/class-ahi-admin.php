<?php
/**
 * فیلد «تصویر هاور» در صفحه‌ی ویرایش محصول + ستون وضعیت در لیست محصولات.
 *
 * @package AromistHoverImage
 */

defined( 'ABSPATH' ) || exit;

class AHI_Admin {

	const NONCE = 'ahi_hover_image_nonce';

	public static function init() {
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_product', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'add_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
	}

	public static function add_meta_box() {
		add_meta_box( 'ahi-hover-image', 'تصویر هاور (نت‌ها و گروه بویایی)', array( __CLASS__, 'render_meta_box' ), 'product', 'side', 'low' );
	}

	public static function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'ahi-admin', AHI_URL . 'assets/ahi-admin.js', array( 'jquery' ), AHI_VERSION, true );
		}
		wp_enqueue_style( 'ahi-admin', AHI_URL . 'assets/ahi-admin.css', array(), AHI_VERSION );
	}

	/**
	 * @param WP_Post $post Product post.
	 */
	public static function render_meta_box( $post ) {
		$id  = (int) get_post_meta( $post->ID, AHI_META_KEY, true );
		$img = $id ? wp_get_attachment_image( $id, 'medium', false, array( 'class' => 'ahi-admin__img' ) ) : '';
		wp_nonce_field( 'ahi_save_' . $post->ID, self::NONCE );
		?>
		<div class="ahi-admin<?php echo $img ? ' has-image' : ''; ?>">
			<input type="hidden" name="ahi_hover_image" class="ahi-admin__input" value="<?php echo esc_attr( $id ? $id : '' ); ?>">
			<a href="#" class="ahi-admin__preview ahi-admin__select" aria-label="تغییر تصویر هاور"><?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			<p class="ahi-admin__actions">
				<a href="#" class="ahi-admin__select ahi-admin__add">انتخاب تصویر هاور</a>
				<a href="#" class="ahi-admin__remove">حذف تصویر هاور</a>
			</p>
			<p class="description">این تصویر وقتی موس روی باکس محصول می‌رود جای عکس اصلی دیده می‌شود. نسبت ابعادش را مثل عکس اصلی محصول بگیرید (مثلاً هر دو مربع).</p>
		</div>
		<?php
	}

	/**
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), 'ahi_save_' . $post_id ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$id = isset( $_POST['ahi_hover_image'] ) ? absint( $_POST['ahi_hover_image'] ) : 0;

		if ( $id && wp_attachment_is_image( $id ) ) {
			update_post_meta( $post_id, AHI_META_KEY, $id );
		} else {
			delete_post_meta( $post_id, AHI_META_KEY );
		}
	}

	public static function add_column( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'name' === $key ) {
				$out['ahi_hover'] = '<span class="ahi-col-head" title="تصویر هاور">هاور</span>';
			}
		}
		if ( ! isset( $out['ahi_hover'] ) ) {
			$out['ahi_hover'] = 'هاور';
		}
		return $out;
	}

	public static function render_column( $column, $post_id ) {
		if ( 'ahi_hover' !== $column ) {
			return;
		}
		$id = (int) get_post_meta( $post_id, AHI_META_KEY, true );
		if ( $id && wp_attachment_is_image( $id ) ) {
			echo wp_get_attachment_image( $id, array( 40, 40 ), false, array( 'class' => 'ahi-col-thumb', 'title' => 'تصویر هاور دارد' ) );
		} else {
			echo '<span class="ahi-col-empty" title="تصویر هاور ندارد">—</span>';
		}
	}
}
