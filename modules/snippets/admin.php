<?php
/**
 * بخش «تنظیمات تکپینو ← اسنیپت‌ها»: فهرست، افزودن و ویرایش اسنیپت (نام، نوع، محل اجرا، کد).
 * فهرست، جستجو و زباله‌دان همان صفحه‌های خود وردپرس برای یک نوع پست است.
 */

defined( 'ABSPATH' ) || exit;

final class TPSN_Admin {

	const NONCE = 'tpsn_save';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		if ( ! is_admin() ) {
			return;
		}
		$type = TPSN_Loader::POST_TYPE;
		add_action( 'add_meta_boxes_' . $type, array( __CLASS__, 'boxes' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'force_publish' ), 10, 2 );
		add_action( 'save_post_' . $type, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'changed' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'changed' ) );
		add_action( 'deleted_post', array( __CLASS__, 'changed' ) );
		add_filter( 'wp_untrash_post_status', array( __CLASS__, 'untrash_status' ), 10, 3 );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		add_filter( "manage_{$type}_posts_columns", array( __CLASS__, 'columns' ) );
		add_action( "manage_{$type}_posts_custom_column", array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( "bulk_actions-edit-{$type}", array( __CLASS__, 'bulk_actions' ) );
		add_action( 'admin_post_tpsn_toggle', array( __CLASS__, 'toggle' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'post_updated_messages', array( __CLASS__, 'messages' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'list_order' ) );
	}

	/** The list follows run order unless a column header was clicked. */
	public static function list_order( $query ) {
		if ( $query->is_main_query() && TPSN_Loader::POST_TYPE === $query->get( 'post_type' ) && ! $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', '_tpsn_order' );
			$query->set( 'orderby', array( 'meta_value_num' => 'ASC', 'ID' => 'ASC' ) );
		}
	}

	public static function register() {
		$cap = 'manage_options';
		register_post_type(
			TPSN_Loader::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => 'اسنیپت‌ها',
					'singular_name'      => 'اسنیپت',
					'menu_name'          => 'اسنیپت‌ها',
					'all_items'          => 'اسنیپت‌ها',
					'add_new'            => 'افزودن اسنیپت',
					'add_new_item'       => 'افزودن اسنیپت',
					'edit_item'          => 'ویرایش اسنیپت',
					'new_item'           => 'اسنیپت تازه',
					'search_items'       => 'جستجوی اسنیپت',
					'not_found'          => 'اسنیپتی پیدا نشد.',
					'not_found_in_trash' => 'زباله‌دان خالی است.',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => class_exists( 'Techpino_Panel' ) ? Techpino_Panel::SLUG : true,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'rewrite'         => false,
				'query_var'       => false,
				'map_meta_cap'    => false,
				'capabilities'    => array_fill_keys(
					array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts', 'edit_published_posts', 'create_posts' ),
					$cap
				),
			)
		);
	}

	private static function is_ours( $post_id ) {
		return TPSN_Loader::POST_TYPE === get_post_type( $post_id );
	}

	// ------------------------------------------------------------------ edit screen

	public static function boxes() {
		remove_meta_box( 'submitdiv', TPSN_Loader::POST_TYPE, 'side' );
		add_meta_box( 'tpsn-save', 'ذخیره', array( __CLASS__, 'box_save' ), TPSN_Loader::POST_TYPE, 'side', 'high' );
		add_meta_box( 'tpsn-code', 'کد و محل اجرا', array( __CLASS__, 'box_code' ), TPSN_Loader::POST_TYPE, 'normal', 'high' );
	}

	public static function box_save( $post ) {
		$s   = TPSN_Loader::read( $post->ID );
		$new = 'auto-draft' === $post->post_status;
		wp_nonce_field( self::NONCE, 'tpsn_nonce' );
		?>
		<p><label><input type="checkbox" name="tpsn_active" value="1" <?php checked( $new || $s['active'] ); ?>> <strong>فعال</strong></label></p>
		<p><label>ترتیب اجرا <input type="number" name="tpsn_order" value="<?php echo esc_attr( $s['order'] ); ?>" class="small-text" step="1"></label><br>
			<span class="description">عدد کمتر زودتر اجرا می‌شود.</span></p>
		<div style="display:flex;justify-content:space-between;align-items:center">
			<?php submit_button( 'ذخیره', 'primary', 'publish', false ); ?>
			<?php if ( ! $new ) : ?>
				<a class="submitdelete" style="color:#b32d2e" href="<?php echo esc_url( get_delete_post_link( $post->ID ) ); ?>">انتقال به زباله‌دان</a>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function box_code( $post ) {
		$s        = TPSN_Loader::read( $post->ID );
		$can_code = current_user_can( 'unfiltered_html' );
		?>
		<table class="form-table tpsn-form" role="presentation">
			<tr>
				<th scope="row"><label for="tpsn-type">نوع کد</label></th>
				<td><select name="tpsn_type" id="tpsn-type">
					<?php foreach ( TPSN_Loader::TYPES as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['type'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></td>
			</tr>
			<tr>
				<th scope="row">کجا اجرا شود</th>
				<td><fieldset>
					<?php foreach ( TPSN_Loader::SCOPES as $key => $label ) : ?>
						<label style="margin-inline-end:16px"><input type="radio" name="tpsn_scope" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['scope'], $key ); ?>> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
				</fieldset></td>
			</tr>
			<tr class="tpsn-front-only">
				<th scope="row">فقط در این صفحه‌ها</th>
				<td><fieldset>
					<?php foreach ( TPSN_Loader::PAGES as $key => $label ) : ?>
						<label style="display:inline-block;margin:0 0 6px 16px"><input type="checkbox" name="tpsn_pages[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $s['pages'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
				</fieldset>
				<p><label>و برگه‌ها یا محصولات با این شناسه‌ها: <input type="text" name="tpsn_ids" value="<?php echo esc_attr( implode( ', ', $s['ids'] ) ); ?>" class="regular-text" dir="ltr" placeholder="54, 120"></label></p>
				<p class="description">هیچ‌کدام انتخاب نشود = همه‌ی صفحه‌های سایت. شناسه را از آدرس صفحه‌ی ویرایش (post=54) بردارید.<br>
					<span class="tpsn-php-only">کد PHP با شرط صفحه بعد از تشخیص صفحه (هوک <code>wp</code>) اجرا می‌شود؛ پس <code>add_action( 'init', … )</code> در آن دیر است و در درخواست‌های AJAX هم اجرا نمی‌شود.</span></p></td>
			</tr>
			<tr class="tpsn-output-only">
				<th scope="row">محل درج در صفحه</th>
				<td><label style="margin-inline-end:16px"><input type="radio" name="tpsn_pos" value="head" <?php checked( $s['pos'], 'head' ); ?>> بالای صفحه (داخل <code>&lt;head&gt;</code>)</label>
					<label><input type="radio" name="tpsn_pos" value="footer" <?php checked( $s['pos'], 'footer' ); ?>> پایین صفحه (قبل از <code>&lt;/body&gt;</code>)</label></td>
			</tr>
		</table>
		<p class="description tpsn-php-only">کد PHP را بدون <code>&lt;?php</code> بنویسید. قبل از ذخیره خطای نحوی بررسی می‌شود؛ اگر موقع اجرا خطا بدهد، خودکار خاموش می‌شود و پیامش بالای همین صفحه می‌آید.</p>
		<p class="description tpsn-output-only">فقط خود کد را بنویسید، بدون تگ <code>&lt;style&gt;</code> یا <code>&lt;script&gt;</code>.</p>
		<?php if ( ! $can_code ) : ?>
			<p class="notice notice-warning inline" style="padding:8px">حساب شما اجازه‌ی ذخیره‌ی کد را ندارد (unfiltered_html)؛ فقط تنظیمات ذخیره می‌شود.</p>
		<?php endif; ?>
		<textarea name="tpsn_code" id="tpsn-code" rows="22" class="large-text code" dir="ltr" spellcheck="false" <?php disabled( ! $can_code ); ?>><?php echo esc_textarea( get_post_meta( $post->ID, '_tpsn_code', true ) ); ?></textarea>
		<?php
	}

	/** Snippets are always 'publish'; on/off is the «فعال» box, so drafts can't hide one by accident. */
	public static function force_publish( $data, $postarr ) {
		if ( TPSN_Loader::POST_TYPE === $data['post_type'] && isset( $_POST['tpsn_nonce'] ) && 'trash' !== $data['post_status'] ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked in save()
			$data['post_status'] = 'publish';
		}
		return $data;
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['tpsn_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tpsn_nonce'] ), self::NONCE ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$in    = wp_unslash( $_POST );
		$type  = isset( $in['tpsn_type'] ) && array_key_exists( $in['tpsn_type'], TPSN_Loader::TYPES ) ? $in['tpsn_type'] : 'php';
		$scope = isset( $in['tpsn_scope'] ) && array_key_exists( $in['tpsn_scope'], TPSN_Loader::SCOPES ) ? $in['tpsn_scope'] : 'all';
		$pages = isset( $in['tpsn_pages'] ) ? array_values( array_intersect( (array) $in['tpsn_pages'], array_keys( TPSN_Loader::PAGES ) ) ) : array();
		$ids   = isset( $in['tpsn_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', preg_split( '/[^0-9]+/', (string) $in['tpsn_ids'] ) ) ) ) ) : array();
		$meta  = array(
			'type'   => $type,
			'scope'  => $scope,
			'pages'  => $pages,
			'ids'    => $ids,
			'pos'    => isset( $in['tpsn_pos'] ) && 'head' === $in['tpsn_pos'] ? 'head' : 'footer',
			'order'  => isset( $in['tpsn_order'] ) ? (int) $in['tpsn_order'] : 10,
			'active' => empty( $in['tpsn_active'] ) ? '0' : '1',
		);
		// Running code is more than editing settings: same bar WordPress sets for raw HTML/JS.
		if ( current_user_can( 'unfiltered_html' ) && isset( $in['tpsn_code'] ) ) {
			$code         = (string) $in['tpsn_code'];
			$meta['code'] = 'php' === $type ? TPSN_Loader::php_body( $code ) : $code;
		}
		$code  = isset( $meta['code'] ) ? $meta['code'] : (string) get_post_meta( $post_id, '_tpsn_code', true );
		$error = 'php' === $type ? TPSN_Loader::lint( $code ) : '';
		if ( '' !== $error ) {
			$meta['active'] = '0';
			$error          = 'خطای نحوی: ' . $error;
		}
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, '_tpsn_' . $key, wp_slash( $value ) );
		}
		TPSN_Loader::set_error( $post_id, $error );
		TPSN_Loader::rebuild();
	}

	public static function changed( $post_id ) {
		if ( self::is_ours( $post_id ) ) {
			TPSN_Loader::rebuild();
		}
	}

	/** WordPress restores trashed posts as drafts; a snippet should come back as it was. */
	public static function untrash_status( $status, $post_id, $previous ) {
		return self::is_ours( $post_id ) && $previous ? $previous : $status;
	}

	public static function title_placeholder( $text, $post ) {
		return TPSN_Loader::POST_TYPE === $post->post_type ? 'نام اسنیپت (مثلاً: کد پیکسل متا)' : $text;
	}

	public static function messages( $messages ) {
		$messages[ TPSN_Loader::POST_TYPE ] = array_fill( 0, 11, 'اسنیپت ذخیره شد.' );
		$messages[ TPSN_Loader::POST_TYPE ][0] = '';
		return $messages;
	}

	// ------------------------------------------------------------------ list screen

	public static function columns() {
		return array(
			'cb'          => '<input type="checkbox">',
			'title'       => 'نام',
			'tpsn_active' => 'وضعیت',
			'tpsn_type'   => 'نوع',
			'tpsn_where'  => 'کجا',
			'tpsn_order'  => 'ترتیب',
		);
	}

	public static function column( $column, $post_id ) {
		$s = TPSN_Loader::read( $post_id );
		if ( 'tpsn_active' === $column ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=tpsn_toggle&post=' . $post_id ), 'tpsn_toggle_' . $post_id );
			printf(
				'<a href="%s" class="button button-small" style="%s">%s</a>',
				esc_url( $url ),
				$s['active'] ? 'color:#00684a;border-color:#00684a' : '',
				$s['active'] ? 'فعال' : 'خاموش'
			);
		} elseif ( 'tpsn_type' === $column ) {
			echo esc_html( TPSN_Loader::TYPES[ $s['type'] ] );
		} elseif ( 'tpsn_where' === $column ) {
			$where = array( TPSN_Loader::SCOPES[ $s['scope'] ] );
			foreach ( $s['pages'] as $page ) {
				$where[] = TPSN_Loader::PAGES[ $page ];
			}
			if ( $s['ids'] ) {
				$where[] = 'شناسه‌ی ' . implode( '، ', $s['ids'] );
			}
			if ( 'php' !== $s['type'] ) {
				$where[] = 'head' === $s['pos'] ? 'بالای صفحه' : 'پایین صفحه';
			}
			echo esc_html( implode( ' · ', $where ) );
		} elseif ( 'tpsn_order' === $column ) {
			echo (int) $s['order'];
		}
	}

	/** Quick Edit would bypass the code check, so the full edit screen is the only way in. */
	public static function row_actions( $actions, $post ) {
		if ( TPSN_Loader::POST_TYPE === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}

	public static function bulk_actions( $actions ) {
		unset( $actions['edit'] );
		return $actions;
	}

	public static function toggle() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $post_id || ! self::is_ours( $post_id ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.' );
		}
		check_admin_referer( 'tpsn_toggle_' . $post_id );
		$s = TPSN_Loader::read( $post_id );
		if ( $s['active'] ) {
			update_post_meta( $post_id, '_tpsn_active', '0' );
			TPSN_Loader::set_error( $post_id, '' );
		} else {
			$error = 'php' === $s['type'] ? TPSN_Loader::lint( $s['code'] ) : '';
			if ( '' === $error ) {
				update_post_meta( $post_id, '_tpsn_active', '1' );
			}
			TPSN_Loader::set_error( $post_id, '' === $error ? '' : 'خطای نحوی: ' . $error );
		}
		TPSN_Loader::rebuild();
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . TPSN_Loader::POST_TYPE ) );
		exit;
	}

	// ------------------------------------------------------------------ assets & notices

	private static function our_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && TPSN_Loader::POST_TYPE === $screen->post_type;
	}

	public static function assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! self::our_screen() ) {
			return;
		}
		$settings = wp_enqueue_code_editor(
			array(
				'type'       => 'application/x-httpd-php-open',
				'codemirror' => array(
					'direction'      => 'ltr',
					'indentUnit'     => 4,
					'indentWithTabs' => true,
				),
			)
		);
		wp_add_inline_style( 'wp-codemirror', '#tpsn-code-box .CodeMirror{height:520px;border:1px solid #dcdcde;direction:ltr;text-align:left}' );
		wp_add_inline_script(
			'jquery-core',
			'jQuery(function($){'
			. 'var s=' . wp_json_encode( $settings ) . ',cm=null,modes={php:"application/x-httpd-php-open",css:"text/css",js:"text/javascript"};'
			. '$("#tpsn-code").closest(".postbox").attr("id","tpsn-code-box");'
			. 'if(s&&window.wp&&wp.codeEditor&&!$("#tpsn-code").prop("disabled")){cm=wp.codeEditor.initialize("tpsn-code",s).codemirror;}'
			. 'function sync(){var t=$("#tpsn-type").val(),sc=$("input[name=tpsn_scope]:checked").val();'
			. 'if(cm){cm.setOption("mode",modes[t]);}'
			. '$(".tpsn-front-only").toggle(sc==="front");$(".tpsn-php-only").toggle(t==="php");$(".tpsn-output-only").toggle(t!=="php");}'
			. '$("#tpsn-type,input[name=tpsn_scope]").on("change",sync);sync();});'
		);
	}

	public static function notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! current_user_can( 'manage_options' ) || ( 'plugins' !== $screen->id && ! self::our_screen() ) ) {
			return;
		}
		$lines = TPSN_Loader::messages();
		if ( $lines ) {
			echo '<div class="notice notice-error"><p><strong>تکپینو – اسنیپت‌ها:</strong></p><ul style="list-style:disc;padding-inline-start:20px">';
			foreach ( $lines as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo '</ul></div>';
		}
	}
}

TPSN_Admin::init();
