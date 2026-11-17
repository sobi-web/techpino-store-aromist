<?php
/**
 * Admin: the settings page (default questions, which products get them, the «still have a
 * question?» box), the product screen's meta box, a column in the products list and a
 * field in its bulk edit.
 *
 * Question rows are posted as two parallel arrays in screen order, so dragging a row is
 * all it takes to reorder; rows without a question are dropped on save.
 *
 * @package TechpinoProductFaq
 */

defined( 'ABSPATH' ) || exit;

final class TPFQ_Admin {

	private const PAGE = 'tpfq-settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_filter( 'option_page_capability_tpfq', array( $this, 'capability' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TPFQ_PATH . 'techpino-product-faq.php' ), array( $this, 'action_links' ) );
		add_action( 'add_meta_boxes_product', array( $this, 'add_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'save_post_product', array( $this, 'save' ), 10, 2 );
		add_filter( 'manage_edit-product_columns', array( $this, 'columns' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'woocommerce_product_bulk_edit_end', array( $this, 'bulk_field' ) );
		add_action( 'woocommerce_product_bulk_edit_save', array( $this, 'bulk_save' ) );
	}

	public function capability(): string {
		return 'manage_woocommerce';
	}

	public function settings_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( $this->settings_url() ) . '">' . esc_html__( 'تنظیمات', 'techpino-product-faq' ) . '</a>' );
		return $links;
	}

	/*
	|--------------------------------------------------------------------------
	| Assets and shared markup
	|--------------------------------------------------------------------------
	*/

	public function assets( string $hook ): void {
		if ( 'edit.php' === $hook && 'product' === ( get_current_screen()->post_type ?? '' ) ) {
			wp_register_style( 'tpfq-list', false, array(), TPFQ_VERSION );
			wp_enqueue_style( 'tpfq-list' );
			wp_add_inline_style( 'tpfq-list', '.column-tpfq{width:9em}.tpfq-col{display:inline-block;padding:2px 8px;border-radius:999px;background:#f0f0f1;color:#50575e;font-size:12px;white-space:nowrap}.tpfq-col--custom,.tpfq-col--both{background:#e6f5f2;color:#00745d}.tpfq-col--default{background:#fcf3e3;color:#8a6116}.tpfq-col--off{background:none;color:#a7aaad}' );
			return;
		}
		if ( ! str_ends_with( $hook, '_page_' . self::PAGE ) ) {
			$screen = get_current_screen();
			if ( ! $screen || 'product' !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
				return;
			}
		}
		wp_enqueue_style( 'tpfq-admin', TPFQ_URL . 'assets/css/admin.css', array(), TPFQ_VERSION );
		wp_enqueue_script( 'tpfq-admin', TPFQ_URL . 'assets/js/admin.js', array( 'jquery-ui-sortable' ), TPFQ_VERSION, true );
		wp_localize_script(
			'tpfq-admin',
			'tpfqAdmin',
			array(
				'confirm' => __( 'این سوال حذف بشه؟', 'techpino-product-faq' ),
				/* translators: %s: number of questions */
				'custom'  => __( 'الان %s سوال اختصاصی همین محصول نمایش داده می‌شود.', 'techpino-product-faq' ),
				/* translators: %s: number of questions */
				'default' => __( 'الان %s سوال پیش‌فرض نمایش داده می‌شود.', 'techpino-product-faq' ),
				/* translators: 1: own questions, 2: default questions */
				'both'    => __( 'الان %1$s سوال اختصاصی و بعد از آن %2$s سوال پیش‌فرض نمایش داده می‌شود.', 'techpino-product-faq' ),
				'none'    => __( 'الان بخش سوالات متداول برای این محصول نمایش داده نمی‌شود.', 'techpino-product-faq' ),
			)
		);
	}

	/**
	 * A sortable list of question rows.
	 *
	 * @param array  $items  q/a pairs.
	 * @param string $name_q Field name of the questions (e.g. tpfq_q[]).
	 * @param string $name_a Field name of the answers.
	 * @param string $empty  Shown when there are no rows.
	 */
	private function repeater( array $items, string $name_q, string $name_a, string $empty ): void {
		?>
		<div class="tpfq-rep<?php echo $items ? '' : ' is-empty'; ?>">
			<div class="tpfq-rep__list">
				<?php
				foreach ( $items as $item ) {
					$this->row( $name_q, $name_a, $item['q'], $item['a'] );
				}
				?>
			</div>
			<div class="tpfq-rep__empty">
				<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
				<?php echo esc_html( $empty ); ?>
			</div>
			<button type="button" class="button tpfq-rep__add" data-tpfq-add><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'افزودن سوال', 'techpino-product-faq' ); ?></button>
			<template class="tpfq-rep__tpl"><?php $this->row( $name_q, $name_a ); ?></template>
		</div>
		<?php
	}

	private function row( string $name_q, string $name_a, string $q = '', string $a = '' ): void {
		?>
		<div class="tpfq-rep__row">
			<span class="tpfq-rep__handle" title="<?php esc_attr_e( 'برای جابه‌جایی بکشید', 'techpino-product-faq' ); ?>" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg></span>
			<span class="tpfq-rep__num"></span>
			<div class="tpfq-rep__fields">
				<input type="text" name="<?php echo esc_attr( $name_q ); ?>" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'سوال — مثلاً: ماندگاری این عطر چقدره؟', 'techpino-product-faq' ); ?>" aria-label="<?php esc_attr_e( 'سوال', 'techpino-product-faq' ); ?>">
				<textarea name="<?php echo esc_attr( $name_a ); ?>" rows="3" placeholder="<?php esc_attr_e( 'جواب', 'techpino-product-faq' ); ?>" aria-label="<?php esc_attr_e( 'جواب', 'techpino-product-faq' ); ?>"><?php echo esc_textarea( $a ); ?></textarea>
			</div>
			<button type="button" class="tpfq-rep__remove" data-tpfq-remove aria-label="<?php esc_attr_e( 'حذف این سوال', 'techpino-product-faq' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
		</div>
		<?php
	}

	/**
	 * q/a pairs from two posted arrays.
	 *
	 * @param mixed $questions
	 * @param mixed $answers
	 */
	private static function posted_items( $questions, $answers ): array {
		$questions = array_values( (array) $questions );
		$answers   = array_values( (array) $answers );
		$items     = array();
		foreach ( $questions as $i => $q ) {
			$q = sanitize_text_field( (string) $q );
			if ( '' === $q ) {
				continue;
			}
			$items[] = array(
				'q' => $q,
				'a' => trim( wp_kses_post( (string) ( $answers[ $i ] ?? '' ) ) ),
			);
		}
		return $items;
	}

	/*
	|--------------------------------------------------------------------------
	| Settings page — تنظیمات تکپینو ← سوالات متداول
	|--------------------------------------------------------------------------
	*/

	public function menu(): void {
		add_submenu_page(
			'techpino-settings',
			__( 'سوالات متداول محصولات', 'techpino-product-faq' ),
			__( 'سوالات متداول', 'techpino-product-faq' ),
			'manage_woocommerce',
			self::PAGE,
			array( $this, 'page' )
		);
	}

	public function register_setting(): void {
		register_setting(
			'tpfq',
			TPFQ_Plugin::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => TPFQ_Plugin::setting_defaults(),
			)
		);
	}

	/**
	 * @param mixed $input Posted values, or (when the option is first added) values already sanitized.
	 */
	public function sanitize( $input ): array {
		$out = TPFQ_Plugin::setting_defaults();
		if ( ! is_array( $input ) ) {
			return $out;
		}
		// WordPress runs this callback a second time when the option is first added.
		if ( ! isset( $input['q'] ) && isset( $input['items'] ) ) {
			return array_merge( $out, array_intersect_key( $input, $out ) );
		}

		$out['items'] = self::posted_items( $input['q'] ?? array(), $input['a'] ?? array() );
		$scope        = (string) ( $input['scope'] ?? 'all' );
		$out['scope'] = in_array( $scope, array( 'all', 'cats', 'manual' ), true ) ? $scope : 'all';
		$out['cats']  = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $input['cats'] ?? array() ) ) ) ) );
		foreach ( array( 'cta_title', 'cta_text', 'cta_button' ) as $key ) {
			$out[ $key ] = sanitize_text_field( (string) ( $input[ $key ] ?? '' ) );
		}
		$url            = trim( (string) ( $input['cta_url'] ?? '' ) );
		$out['cta_url'] = '' === $url ? '' : esc_url_raw( $url, TPFQ_Plugin::url_protocols() );
		TPFQ_Plugin::flush();
		return $out;
	}

	/** Products per mode and with their own questions, for the settings page. */
	private function stats(): array {
		global $wpdb;
		$stats = array(
			'own'   => 0,
			'modes' => array(),
		);
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$stats['own'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = 'product' AND p.post_status NOT IN ('trash','auto-draft')", TPFQ_Plugin::META ) );
		$rows         = $wpdb->get_results( $wpdb->prepare( "SELECT pm.meta_value AS mode, COUNT(*) AS n FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = 'product' AND p.post_status NOT IN ('trash','auto-draft') GROUP BY pm.meta_value", TPFQ_Plugin::MODE_META ) );
		// phpcs:enable
		foreach ( (array) $rows as $row ) {
			$stats['modes'][ (string) $row->mode ] = (int) $row->n;
		}
		return $stats;
	}

	/**
	 * Category checkboxes, as a tree.
	 *
	 * @param array<int,WP_Term[]> $tree Children by parent id.
	 */
	private function cat_boxes( array $tree, int $parent, int $depth, array $picked ): void {
		foreach ( $tree[ $parent ] ?? array() as $term ) {
			printf(
				'<label style="padding-inline-start:%1$dpx"><input type="checkbox" name="%2$s[cats][]" value="%3$d" %4$s> %5$s <span class="description">(%6$s)</span></label>',
				(int) ( $depth * 20 ),
				esc_attr( TPFQ_Plugin::OPTION ),
				(int) $term->term_id,
				checked( in_array( (int) $term->term_id, $picked, true ), true, false ),
				esc_html( $term->name ),
				esc_html( TPFQ_Plugin::fa_digits( (string) $term->count ) )
			);
			$this->cat_boxes( $tree, (int) $term->term_id, $depth + 1, $picked );
		}
	}

	public function page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s     = TPFQ_Plugin::settings();
		$name  = TPFQ_Plugin::OPTION;
		$stats = $this->stats();
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$tree  = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$tree[ (int) $term->parent ][] = $term;
		}
		$picked = array_map( 'intval', (array) $s['cats'] );
		$scopes = array(
			'all'    => __( 'همه‌ی محصولات', 'techpino-product-faq' ),
			'cats'   => __( 'فقط محصولات این دسته‌ها (و زیرمجموعه‌هایشان)', 'techpino-product-faq' ),
			'manual' => __( 'هیچ محصولی؛ فقط محصولاتی که در صفحه‌ی خودشان «سوالات پیش‌فرض» را انتخاب کنم', 'techpino-product-faq' ),
		);
		$modes  = TPFQ_Plugin::modes();
		?>
		<div class="wrap tpfq-settings">
			<h1><?php esc_html_e( 'سوالات متداول محصولات', 'techpino-product-faq' ); ?></h1>
			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'tpfq' ); ?>

				<section class="tpfq-card">
					<h2><?php esc_html_e( 'سوالات پیش‌فرض', 'techpino-product-faq' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'محصولاتی که سوال اختصاصی ندارند (و در محدوده‌ی پایین هستند) این سوال‌ها را نشان می‌دهند.', 'techpino-product-faq' ); ?>
						<?php
						/* translators: %s: the {product} placeholder */
						printf( esc_html__( '%s در سوال یا جواب با نام همان محصول عوض می‌شود؛ مثلاً «ماندگاری {product} چقدره؟».', 'techpino-product-faq' ), '<code>{product}</code>' );
						?>
					</p>
					<?php $this->repeater( TPFQ_Plugin::default_items(), $name . '[q][]', $name . '[a][]', __( 'هنوز سوال پیش‌فرضی ننوشته‌اید.', 'techpino-product-faq' ) ); ?>
				</section>

				<section class="tpfq-card">
					<h2><?php esc_html_e( 'سوالات پیش‌فرض برای کدام محصولات؟', 'techpino-product-faq' ); ?></h2>
					<fieldset class="tpfq-scope">
						<legend class="screen-reader-text"><?php esc_html_e( 'محدوده‌ی سوالات پیش‌فرض', 'techpino-product-faq' ); ?></legend>
						<?php foreach ( $scopes as $value => $label ) : ?>
							<label class="tpfq-scope__opt"><input type="radio" name="<?php echo esc_attr( $name ); ?>[scope]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $s['scope'], $value ); ?>> <?php echo esc_html( $label ); ?></label>
							<?php if ( 'cats' === $value ) : ?>
								<div class="tpfq-cats" data-tpfq-cats>
									<?php
									if ( $tree ) {
										$this->cat_boxes( $tree, 0, 0, $picked );
									} else {
										echo '<p class="description">' . esc_html__( 'هنوز دسته‌ای ساخته نشده است.', 'techpino-product-faq' ) . '</p>';
									}
									?>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</fieldset>
					<div class="tpfq-note">
						<p>
							<?php esc_html_e( 'در صفحه‌ی ویرایش هر محصول، باکس «سوالات متداول محصول» می‌تواند این را برای همان محصول عوض کند:', 'techpino-product-faq' ); ?>
							<?php echo esc_html( implode( ' · ', array_slice( $modes, 1 ) ) ); ?>.
							<?php esc_html_e( 'برای چند محصول با هم: فهرست محصولات ← انتخاب ← کارهای دسته‌جمعی ← ویرایش.', 'techpino-product-faq' ); ?>
						</p>
						<?php
						$facts = array();
						if ( $stats['own'] ) {
							/* translators: %s: number of products */
							$facts[] = sprintf( __( '%s محصول سوال اختصاصی دارند', 'techpino-product-faq' ), TPFQ_Plugin::fa_digits( (string) $stats['own'] ) );
						}
						foreach ( $stats['modes'] as $mode => $count ) {
							if ( '' !== $mode && isset( $modes[ $mode ] ) && $count ) {
								/* translators: 1: number of products, 2: mode */
								$facts[] = sprintf( __( '%1$s محصول روی «%2$s»', 'techpino-product-faq' ), TPFQ_Plugin::fa_digits( (string) $count ), $modes[ $mode ] );
							}
						}
						if ( $facts ) {
							echo '<p class="tpfq-facts">' . esc_html( implode( ' · ', $facts ) ) . '</p>';
						}
						?>
					</div>
				</section>

				<section class="tpfq-card">
					<h2><?php esc_html_e( 'باکس «جواب سوالت رو پیدا نکردی؟»', 'techpino-product-faq' ); ?></h2>
					<p class="description"><?php esc_html_e( 'زیر سوال‌ها. ویجت المنتور و شورت‌کد اگر متن یا لینک خودشان را نداشته باشند از اینجا می‌گیرند؛ نسخه‌ی موبایل هم. بدون لینک، دکمه نمایش داده نمی‌شود.', 'techpino-product-faq' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="tpfq-cta-title"><?php esc_html_e( 'عنوان', 'techpino-product-faq' ); ?></label></th>
							<td><input type="text" class="regular-text" id="tpfq-cta-title" name="<?php echo esc_attr( $name ); ?>[cta_title]" value="<?php echo esc_attr( (string) $s['cta_title'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="tpfq-cta-text"><?php esc_html_e( 'متن', 'techpino-product-faq' ); ?></label></th>
							<td><input type="text" class="large-text" id="tpfq-cta-text" name="<?php echo esc_attr( $name ); ?>[cta_text]" value="<?php echo esc_attr( (string) $s['cta_text'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="tpfq-cta-button"><?php esc_html_e( 'متن دکمه', 'techpino-product-faq' ); ?></label></th>
							<td><input type="text" class="regular-text" id="tpfq-cta-button" name="<?php echo esc_attr( $name ); ?>[cta_button]" value="<?php echo esc_attr( (string) $s['cta_button'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="tpfq-cta-url"><?php esc_html_e( 'لینک دکمه', 'techpino-product-faq' ); ?></label></th>
							<td>
								<input type="text" class="regular-text code" id="tpfq-cta-url" dir="ltr" name="<?php echo esc_attr( $name ); ?>[cta_url]" value="<?php echo esc_attr( (string) $s['cta_url'] ); ?>" placeholder="/contact/">
								<p class="description"><?php esc_html_e( 'مثل /contact/ یا tel:0912… یا لینک واتساپ و تلگرام.', 'techpino-product-faq' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<?php submit_button( __( 'ذخیره‌ی تغییرات', 'techpino-product-faq' ) ); ?>
			</form>
		</div>
		<?php
	}

	/*
	|--------------------------------------------------------------------------
	| Product screen
	|--------------------------------------------------------------------------
	*/

	public function add_box(): void {
		add_meta_box( 'tpfq', __( 'سوالات متداول محصول', 'techpino-product-faq' ), array( $this, 'box' ), 'product', 'normal', 'default' );
	}

	public function box( WP_Post $post ): void {
		$id       = (int) $post->ID;
		$mode     = TPFQ_Plugin::mode( $id );
		$defaults = TPFQ_Plugin::default_items();
		wp_nonce_field( 'tpfq_save', 'tpfq_nonce' );
		?>
		<div class="tpfq-mb" data-mode="<?php echo esc_attr( $mode ); ?>" data-defaults="<?php echo esc_attr( (string) count( $defaults ) ); ?>" data-scope="<?php echo TPFQ_Plugin::in_scope( $id ) ? '1' : '0'; ?>">
			<div class="tpfq-mb__mode">
				<label for="tpfq-mode"><?php esc_html_e( 'سوالات این محصول', 'techpino-product-faq' ); ?></label>
				<select id="tpfq-mode" name="tpfq_mode">
					<?php foreach ( TPFQ_Plugin::modes() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $mode, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<a href="<?php echo esc_url( $this->settings_url() ); ?>" target="_blank"><?php esc_html_e( 'سوالات پیش‌فرض و تنظیمات', 'techpino-product-faq' ); ?></a>
			</div>
			<p class="tpfq-mb__status" aria-live="polite"></p>
			<p class="description tpfq-mb__help" data-for="auto"><?php esc_html_e( 'خودکار: اگر پایین سوالی بنویسید همان‌ها نمایش داده می‌شوند؛ وگرنه سوالات پیش‌فرض (اگر این محصول در محدوده‌ی تنظیمات باشد؛ طبق دسته‌های ذخیره‌شده).', 'techpino-product-faq' ); ?></p>

			<div class="tpfq-mb__own">
				<h4><?php esc_html_e( 'سوالات اختصاصی این محصول', 'techpino-product-faq' ); ?></h4>
				<p class="description"><?php esc_html_e( 'ردیف‌ها را برای تغییر ترتیب بکشید؛ در جواب‌ها می‌توانید از HTML ساده (لینک، پررنگ، لیست) استفاده کنید.', 'techpino-product-faq' ); ?></p>
				<?php $this->repeater( TPFQ_Plugin::items( $id ), 'tpfq_q[]', 'tpfq_a[]', __( 'این محصول سوال اختصاصی ندارد.', 'techpino-product-faq' ) ); ?>
			</div>

			<?php if ( $defaults ) : ?>
				<details class="tpfq-mb__defaults">
					<summary>
						<?php
						/* translators: %s: number of questions */
						echo esc_html( sprintf( __( 'سوالات پیش‌فرض (%s)', 'techpino-product-faq' ), TPFQ_Plugin::fa_digits( (string) count( $defaults ) ) ) );
						?>
					</summary>
					<ol>
						<?php foreach ( $defaults as $item ) : ?>
							<li><?php echo esc_html( str_replace( '{product}', $post->post_title, $item['q'] ) ); ?></li>
						<?php endforeach; ?>
					</ol>
				</details>
			<?php endif; ?>
		</div>
		<?php
	}

	public function save( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['tpfq_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tpfq_nonce'] ) ), 'tpfq_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$this->save_mode( $post_id, sanitize_key( wp_unslash( $_POST['tpfq_mode'] ?? '' ) ) );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per row.
		$items = self::posted_items( wp_unslash( $_POST['tpfq_q'] ?? array() ), wp_unslash( $_POST['tpfq_a'] ?? array() ) );
		if ( $items ) {
			update_post_meta( $post_id, TPFQ_Plugin::META, $items );
		} else {
			delete_post_meta( $post_id, TPFQ_Plugin::META );
		}
	}

	private function save_mode( int $post_id, string $mode ): void {
		if ( '' !== $mode && array_key_exists( $mode, TPFQ_Plugin::modes() ) ) {
			update_post_meta( $post_id, TPFQ_Plugin::MODE_META, $mode );
		} else {
			delete_post_meta( $post_id, TPFQ_Plugin::MODE_META );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Products list: column and bulk edit
	|--------------------------------------------------------------------------
	*/

	public function columns( $columns ) {
		$out = array();
		foreach ( (array) $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$out['tpfq'] = __( 'سوالات متداول', 'techpino-product-faq' );
			}
			$out[ $key ] = $label;
		}
		if ( ! isset( $out['tpfq'] ) ) {
			$out['tpfq'] = __( 'سوالات متداول', 'techpino-product-faq' );
		}
		return $out;
	}

	public function column( string $column, int $post_id ): void {
		if ( 'tpfq' !== $column ) {
			return;
		}
		$labels = array(
			'custom'  => __( 'اختصاصی', 'techpino-product-faq' ),
			'default' => __( 'پیش‌فرض', 'techpino-product-faq' ),
			'both'    => __( 'اختصاصی + پیش‌فرض', 'techpino-product-faq' ),
		);
		$source = TPFQ_Plugin::source( $post_id );
		if ( '' === $source ) {
			echo '<span class="tpfq-col tpfq-col--off" title="' . esc_attr__( 'نمایش داده نمی‌شود', 'techpino-product-faq' ) . '">—</span>';
			return;
		}
		$count = count( TPFQ_Plugin::questions( $post_id ) );
		printf(
			'<span class="tpfq-col tpfq-col--%1$s">%2$s <b>%3$s</b></span>',
			esc_attr( $source ),
			esc_html( $labels[ $source ] ),
			esc_html( TPFQ_Plugin::fa_digits( (string) $count ) )
		);
	}

	public function bulk_field(): void {
		?>
		<div class="inline-edit-group">
			<label class="alignleft">
				<span class="title"><?php esc_html_e( 'سوالات متداول', 'techpino-product-faq' ); ?></span>
				<span class="input-text-wrap">
					<select class="tpfq_mode" name="_tpfq_mode">
						<option value="-1"><?php esc_html_e( '— بدون تغییر —', 'techpino-product-faq' ); ?></option>
						<?php foreach ( TPFQ_Plugin::modes() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( '' === $value ? 'auto' : $value ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</span>
			</label>
		</div>
		<?php
	}

	/**
	 * WooCommerce has checked the bulk-edit nonce and the user's rights by now.
	 *
	 * @param WC_Product $product
	 */
	public function bulk_save( $product ): void {
		$mode = isset( $_REQUEST['_tpfq_mode'] ) ? sanitize_key( wp_unslash( $_REQUEST['_tpfq_mode'] ) ) : '-1'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '-1' === $mode || '' === $mode || ! $product instanceof WC_Product ) {
			return;
		}
		$this->save_mode( $product->get_id(), 'auto' === $mode ? '' : $mode );
	}
}
