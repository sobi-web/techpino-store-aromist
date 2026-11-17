<?php
/**
 * The «جستجوی زنده» screen in the dashboard: banners, popular searches, featured products,
 * the look of the form, and the product index.
 *
 * @package TechpinoLiveSearch
 */

defined( 'ABSPATH' ) || exit;

final class TPLS_Admin {

	public const SLUG = 'tpls';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_tpls_save', array( __CLASS__, 'save' ) );
		add_action( 'wp_ajax_tpls_products', array( __CLASS__, 'ajax_products' ) );
	}

	public static function url( string $tab = '' ): string {
		return add_query_arg(
			array_filter(
				array(
					'page' => self::SLUG,
					'tab'  => $tab,
				)
			),
			admin_url( 'admin.php' )
		);
	}

	public static function menu(): void {
		add_submenu_page(
			'techpino-settings',
			'جستجوی زنده تکپینو',
			'جستجوی زنده',
			'manage_woocommerce',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	private static function tabs(): array {
		return array(
			'banners'  => 'بنر',
			'popular'  => 'جستجوهای پرطرفدار',
			'featured' => 'محصولات پرفروش',
			'look'     => 'ظاهر فرم',
			'advanced' => 'پیشرفته',
		);
	}

	public static function assets( string $hook ): void {
		if ( ! str_ends_with( $hook, '_page_' . self::SLUG ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'tpls-admin', TPLS_URL . 'assets/css/admin.css', array(), TPLS_VERSION );
		wp_enqueue_script( 'tpls-admin', TPLS_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ), TPLS_VERSION, true );

		$s     = TPLS_Settings::get();
		$items = array();
		foreach ( (array) $s['featured_ids'] as $id ) {
			$product = wc_get_product( (int) $id );
			if ( $product ) {
				$items[] = self::product_data( $product );
			}
		}
		wp_localize_script(
			'tpls-admin',
			'tplsAdmin',
			array(
				'ajax'     => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'tpls_admin' ),
				'featured' => $items,
				'guide'    => TPLS_Settings::banner_guide(),
				'i18n'     => array(
					'pick'    => 'انتخاب تصویر',
					'change'  => 'تغییر',
					'none'    => 'نتیجه‌ای پیدا نشد',
					'remove'  => 'حذف',
					'max'     => 'حداکثر ۴۸ محصول',
					'already' => 'این محصول قبلاً اضافه شده',
				),
			)
		);
	}

	/** @return array{id: int, title: string, img: string, price: string, stock: bool} */
	private static function product_data( WC_Product $product ): array {
		$image = $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : '';
		return array(
			'id'    => $product->get_id(),
			'title' => $product->get_name(),
			'img'   => (string) ( $image ? $image : wc_placeholder_img_src( 'thumbnail' ) ),
			'price' => html_entity_decode( wp_strip_all_tags( $product->get_price_html() ), ENT_QUOTES, 'UTF-8' ),
			'stock' => $product->is_in_stock(),
		);
	}

	public static function ajax_products(): void {
		check_ajax_referer( 'tpls_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( null, 403 );
		}
		$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		if ( '' === $term ) {
			wp_send_json_success( array() );
		}
		$ids  = WC_Data_Store::load( 'product' )->search_products( $term, '', false, false, 12 );
		$list = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product && 'publish' === $product->get_status() ) {
				$list[] = self::product_data( $product );
			}
		}
		wp_send_json_success( $list );
	}

	public static function save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'techpino-live-search' ), 403 );
		}
		check_admin_referer( 'tpls_save' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified above; TPLS_Settings::sanitize() cleans every field.
		update_option( TPLS_Settings::OPTION, TPLS_Settings::sanitize( $_POST['tpls'] ?? array() ), true );
		TPLS_Settings::reset_cache();
		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
		wp_safe_redirect( add_query_arg( 'saved', '1', self::url( $tab ) ) );
		exit;
	}

	/* ---------- Page ---------- */

	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s    = TPLS_Settings::get();
		$tabs = self::tabs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks the open tab.
		$tab  = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'banners';
		$info = get_option( 'tpls_index' );

		echo '<div class="wrap tpls-admin">';
		echo '<h1>' . esc_html__( 'جستجوی زنده تکپینو', 'techpino-live-search' ) . '</h1>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['tpls_rebuilt'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>فهرست جستجو دوباره ساخته شد.</p></div>';
		}
		echo '<p class="tpls-admin__lead">این بخش‌ها داخل ویجت «جستجوی زنده (تکپینو)» نمایش داده می‌شوند. ویجت را از المنتور در هدر، آف‌کنواس یا هر جای دیگر بگذارید؛ محتوای آن از همین‌جا می‌آید.</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="tpls-admin__form">';
		echo '<input type="hidden" name="action" value="tpls_save">';
		echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" id="tpls-tab-field">';
		wp_nonce_field( 'tpls_save' );

		echo '<nav class="tpls-admin__tabs" role="tablist">';
		foreach ( $tabs as $key => $label ) {
			printf(
				'<button type="button" role="tab" class="tpls-admin__tab%1$s" data-tab="%2$s" aria-selected="%3$s">%4$s</button>',
				$key === $tab ? ' is-active' : '',
				esc_attr( $key ),
				$key === $tab ? 'true' : 'false',
				esc_html( $label )
			);
		}
		echo '</nav>';

		foreach ( array_keys( $tabs ) as $key ) {
			printf( '<section class="tpls-admin__panel%s" data-panel="%s">', $key === $tab ? ' is-active' : '', esc_attr( $key ) );
			call_user_func( array( __CLASS__, 'tab_' . $key ), $s, $info );
			echo '</section>';
		}

		echo '<p class="submit"><button type="submit" class="button button-primary button-large">ذخیره‌ی تغییرات</button></p>';
		echo '</form></div>';
	}

	private static function field_name( string $name ): string {
		return 'tpls[' . $name . ']';
	}

	private static function switch_field( string $name, bool $on, string $label, string $hint = '' ): void {
		printf(
			'<label class="tpls-admin__switch"><input type="checkbox" name="%1$s" value="1"%2$s><span class="tpls-admin__switch-ui"></span><span>%3$s</span></label>%4$s',
			esc_attr( self::field_name( $name ) ),
			checked( $on, true, false ),
			esc_html( $label ),
			$hint ? '<p class="description">' . esc_html( $hint ) . '</p>' : ''
		);
	}

	/* ---------- Banners ---------- */

	private static function banner_row( string $i, array $b ): string {
		$thumb = static function ( int $id ): string {
			return $id ? (string) wp_get_attachment_image_url( $id, 'medium' ) : '';
		};
		$n    = 'tpls[banners][' . $i . ']';
		$d_id = (int) ( $b['desktop'] ?? 0 );
		$m_id = (int) ( $b['mobile'] ?? 0 );

		ob_start();
		?>
		<div class="tpls-banner-row">
			<span class="tpls-admin__handle" title="جابه‌جایی" aria-hidden="true"></span>
			<div class="tpls-banner-row__pics">
				<?php
				foreach ( array(
					'desktop' => array( 'تصویر دسکتاپ', $d_id ),
					'mobile'  => array( 'تصویر موبایل', $m_id ),
				) as $kind => $pic ) :
					$url = $thumb( $pic[1] );
					?>
					<div class="tpls-media" data-kind="<?php echo esc_attr( $kind ); ?>">
						<span class="tpls-media__label"><?php echo esc_html( $pic[0] ); ?></span>
						<button type="button" class="tpls-media__box<?php echo $url ? ' has-image' : ''; ?>">
							<img src="<?php echo esc_url( $url ); ?>" alt=""<?php echo $url ? '' : ' hidden'; ?>>
							<span class="tpls-media__empty"<?php echo $url ? ' hidden' : ''; ?>>انتخاب تصویر</span>
						</button>
						<input type="hidden" name="<?php echo esc_attr( $n . '[' . $kind . ']' ); ?>" value="<?php echo esc_attr( (string) $pic[1] ); ?>">
						<button type="button" class="tpls-media__remove button-link"<?php echo $url ? '' : ' hidden'; ?>>حذف تصویر</button>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="tpls-banner-row__meta">
				<label>لینک بنر
					<input type="text" class="large-text" dir="ltr" placeholder="https://" name="<?php echo esc_attr( $n . '[link]' ); ?>" value="<?php echo esc_attr( (string) ( $b['link'] ?? '' ) ); ?>">
				</label>
				<label>متن جایگزین تصویر (برای دسترس‌پذیری)
					<input type="text" class="large-text" name="<?php echo esc_attr( $n . '[alt]' ); ?>" value="<?php echo esc_attr( (string) ( $b['alt'] ?? '' ) ); ?>">
				</label>
				<label class="tpls-admin__check"><input type="checkbox" name="<?php echo esc_attr( $n . '[blank]' ); ?>" value="1"<?php checked( ! empty( $b['blank'] ) ); ?>> باز شدن در تب جدید</label>
			</div>
			<button type="button" class="tpls-admin__remove button-link-delete" aria-label="حذف بنر">حذف بنر</button>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function tab_banners( array $s ): void {
		?>
		<div class="tpls-admin__card">
			<h2>بنر داخل پنل جستجو</h2>
			<p class="description">بنرها پیش از تایپ نمایش داده می‌شوند و با شروع تایپ جای خود را به نتایج می‌دهند. برای هر بنر می‌شود یک تصویر دسکتاپ و یک تصویر موبایل گذاشت؛ اگر یکی را خالی بگذارید از دیگری استفاده می‌شود.</p>

			<div class="tpls-admin__row">
				<label for="tpls-banner-pos">جای بنر</label>
				<select id="tpls-banner-pos" name="<?php echo esc_attr( self::field_name( 'banner_pos' ) ); ?>">
					<option value="top"<?php selected( $s['banner_pos'], 'top' ); ?>>بالای همه‌چیز</option>
					<option value="middle"<?php selected( $s['banner_pos'], 'middle' ); ?>>بعد از جستجوهای پرطرفدار، قبل از محصولات</option>
					<option value="bottom"<?php selected( $s['banner_pos'], 'bottom' ); ?>>پایین پنل، بعد از محصولات</option>
				</select>
			</div>
			<div class="tpls-admin__row">
				<span class="tpls-admin__label">چند بنر در یک ردیف (دسکتاپ)</span>
				<div class="tpls-admin__radios">
					<?php for ( $c = 1; $c <= 3; $c++ ) : ?>
						<label><input type="radio" name="<?php echo esc_attr( self::field_name( 'banner_cols' ) ); ?>" value="<?php echo (int) $c; ?>"<?php checked( (int) $s['banner_cols'], $c ); ?>> <?php echo esc_html( number_format_i18n( $c ) ); ?></label>
					<?php endfor; ?>
				</div>
				<p class="description">در موبایل بنرها همیشه زیر هم و تمام‌عرض هستند.</p>
			</div>

			<div class="tpls-admin__guide" id="tpls-guide">
				<strong>سایز پیشنهادی بنر</strong>
				<ul>
					<li>دسکتاپ: <b data-guide="desktop"></b> پیکسل <span class="description">(<span data-guide="desktop-note"></span>)</span></li>
					<li>موبایل: <b data-guide="mobile"></b> پیکسل <span class="description">(تمام‌عرض، یکی زیر دیگری)</span></li>
				</ul>
				<p class="description">سایزها دو برابرِ اندازه‌ی نمایشی هستند تا روی نمایشگرهای تیز (Retina) شفاف بمانند. فرمت WebP یا JPG با حجم زیر ۱۵۰ کیلوبایت پیشنهاد می‌شود. تصویر در کادر هم‌نسبت نمایش داده می‌شود و اگر نسبتش کمی فرق کند از لبه‌ها بریده می‌شود، پس نوشته‌ها و لوگو را وسط تصویر نگه دارید.</p>
			</div>

			<div class="tpls-banner-list" data-next="<?php echo (int) count( (array) $s['banners'] ); ?>">
				<?php
				foreach ( array_values( (array) $s['banners'] ) as $i => $banner ) {
					echo self::banner_row( (string) $i, $banner ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in banner_row().
				}
				?>
			</div>
			<template id="tpls-banner-tpl"><?php echo self::banner_row( '__i__', array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
			<p><button type="button" class="button" id="tpls-banner-add">+ افزودن بنر</button></p>
		</div>
		<?php
	}

	/* ---------- Popular searches ---------- */

	private static function popular_row( string $i, string $text, string $link ): string {
		$n = 'tpls[popular][' . $i . ']';
		return '<div class="tpls-pop-row"><span class="tpls-admin__handle" aria-hidden="true"></span>'
			. '<label class="tpls-pop-row__field">متن دکمه<input type="text" name="' . esc_attr( $n . '[text]' ) . '" value="' . esc_attr( $text ) . '" placeholder="مثلاً: عطر زنانه"></label>'
			. '<label class="tpls-pop-row__field tpls-pop-row__field--link">لینک (اختیاری)<input type="text" dir="ltr" name="' . esc_attr( $n . '[link]' ) . '" value="' . esc_attr( $link ) . '" placeholder="https://aromistshop.com/product-category/..."></label>'
			. '<button type="button" class="tpls-admin__remove button-link-delete" aria-label="حذف">حذف</button></div>';
	}

	private static function tab_popular( array $s ): void {
		?>
		<div class="tpls-admin__card">
			<h2>جستجوهای پرطرفدار</h2>
			<p class="description">این عبارت‌ها پیش از تایپ به شکل دکمه نمایش داده می‌شوند. اگر برای یک دکمه لینک بگذارید، کاربر با کلیک به همان صفحه می‌رود (مثلاً صفحه‌ی یک دسته یا برند)؛ اگر لینک خالی بماند، همان متن جستجو می‌شود. با کشیدن، ترتیب را عوض کنید.</p>
			<div class="tpls-admin__row">
				<label for="tpls-popular-title">عنوان بخش</label>
				<input type="text" id="tpls-popular-title" class="regular-text" name="<?php echo esc_attr( self::field_name( 'popular_title' ) ); ?>" value="<?php echo esc_attr( (string) $s['popular_title'] ); ?>">
				<p class="description">اگر خالی بماند، عنوان نمایش داده نمی‌شود.</p>
			</div>
			<?php $popular = TPLS_Settings::popular(); ?>
			<div class="tpls-pop-list" data-next="<?php echo (int) count( $popular ); ?>">
				<?php
				foreach ( $popular as $i => $item ) {
					echo self::popular_row( (string) $i, $item['text'], $item['link'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in popular_row().
				}
				?>
			</div>
			<template id="tpls-pop-tpl"><?php echo self::popular_row( '__i__', '', '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
			<p><button type="button" class="button" id="tpls-pop-add">+ افزودن جستجو</button></p>
		</div>
		<?php
	}

	/* ---------- Featured products ---------- */

	private static function tab_featured( array $s ): void {
		?>
		<div class="tpls-admin__card">
			<h2>محصولات پیش از جستجو</h2>
			<p class="description">این محصولات پیش از تایپ، زیر جستجوهای پرطرفدار نمایش داده می‌شوند.</p>
			<?php self::switch_field( 'featured_show', ! empty( $s['featured_show'] ), 'نمایش این بخش' ); ?>

			<div class="tpls-admin__row">
				<label for="tpls-featured-title">عنوان بخش</label>
				<input type="text" id="tpls-featured-title" class="regular-text" name="<?php echo esc_attr( self::field_name( 'featured_title' ) ); ?>" value="<?php echo esc_attr( (string) $s['featured_title'] ); ?>">
			</div>

			<div class="tpls-admin__row">
				<span class="tpls-admin__label">انتخاب محصولات</span>
				<div class="tpls-admin__radios tpls-admin__radios--stack">
					<label><input type="radio" name="<?php echo esc_attr( self::field_name( 'featured_mode' ) ); ?>" value="auto"<?php checked( $s['featured_mode'], 'auto' ); ?>> خودکار: پرفروش‌ترین محصولات فروشگاه</label>
					<label><input type="radio" name="<?php echo esc_attr( self::field_name( 'featured_mode' ) ); ?>" value="manual"<?php checked( $s['featured_mode'], 'manual' ); ?>> دستی: محصولات را خودم انتخاب می‌کنم</label>
				</div>
			</div>

			<div class="tpls-admin__row" data-when-mode="auto">
				<label for="tpls-featured-count">تعداد محصولات</label>
				<input type="number" id="tpls-featured-count" min="1" max="24" class="small-text" name="<?php echo esc_attr( self::field_name( 'featured_count' ) ); ?>" value="<?php echo (int) $s['featured_count']; ?>">
				<p class="description">در دسکتاپ محصولات دو ستونه‌اند؛ عددهای زوج (۴، ۶، ۸) ردیف آخر را کامل می‌کنند.</p>
				<?php self::switch_field( 'featured_stock', ! empty( $s['featured_stock'] ), 'فقط محصولات موجود' ); ?>
			</div>

			<div class="tpls-admin__row" data-when-mode="manual">
				<span class="tpls-admin__label">محصولات انتخاب‌شده</span>
				<div class="tpls-search">
					<input type="search" id="tpls-product-q" class="regular-text" placeholder="نام یا شناسه‌ی محصول را بنویسید…" autocomplete="off">
					<ul class="tpls-search__results" id="tpls-product-results" hidden></ul>
				</div>
				<ul class="tpls-featured-list" id="tpls-featured-list"></ul>
				<p class="description">با کشیدن، ترتیب نمایش را عوض کنید. هر محصولی که بعداً حذف یا پنهان شود، خودکار نمایش داده نمی‌شود.</p>
			</div>
		</div>
		<?php
	}

	/* ---------- Look ---------- */

	private static function tab_look( array $s ): void {
		$groups = array();
		foreach ( TPLS_Settings::look_fields() as $key => $field ) {
			$groups[ $field[0] ][ $key ] = $field;
		}
		$look = (array) $s['look'];
		?>
		<div class="tpls-admin__card">
			<h2>ظاهر فرم و پنل</h2>
			<p class="description">هر مورد را خالی بگذارید تا از طراحی پیش‌فرض (رنگ‌های تکپینو) استفاده شود. تنظیماتی که در خود ویجت المنتور (تب «استایل») انجام دهید روی همان ویجت بر این مقدارها غلبه می‌کنند.</p>
			<p><button type="button" class="button" id="tpls-look-reset">پاک کردن همه‌ی مقدارها (بازگشت به پیش‌فرض)</button></p>
		</div>
		<?php foreach ( $groups as $group => $fields ) : ?>
			<div class="tpls-admin__card">
				<h3><?php echo esc_html( $group ); ?></h3>
				<div class="tpls-admin__grid">
					<?php foreach ( $fields as $key => $field ) : ?>
						<div class="tpls-admin__cell">
							<label for="tpls-look-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field[1] ); ?></label>
							<?php if ( 'color' === $field[2] ) : ?>
								<input type="text" id="tpls-look-<?php echo esc_attr( $key ); ?>" class="tpls-color" dir="ltr" data-default-color="<?php echo esc_attr( $field[6] ); ?>" name="<?php echo esc_attr( 'tpls[look][' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) ( $look[ $key ] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $field[6] ); ?>">
							<?php else : ?>
								<span class="tpls-admin__px"><input type="number" id="tpls-look-<?php echo esc_attr( $key ); ?>" min="<?php echo (int) $field[4]; ?>" max="<?php echo (int) $field[5]; ?>" name="<?php echo esc_attr( 'tpls[look][' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) ( $look[ $key ] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $field[6] ); ?>"> <?php echo 'pct' === $field[2] ? '%' : 'px'; ?></span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>
		<?php
	}

	/* ---------- Advanced ---------- */

	private static function tab_advanced( array $s, $info ): void {
		$count = is_array( $info ) && isset( $info['count'] ) ? (int) $info['count'] : 0;
		$time  = is_array( $info ) && ! empty( $info['time'] ) ? (int) $info['time'] : 0;
		$url   = wp_nonce_url( admin_url( 'admin-post.php?action=tpls_rebuild&back=tpls' ), 'tpls_rebuild' );
		?>
		<div class="tpls-admin__card">
			<h2>کلمه‌های هم‌معنی</h2>
			<p class="description">هر خط یک گروه است و کلمه‌ها با ویرگول جدا می‌شوند. جستجوی هر کلمه، بقیه‌ی گروه را هم پیدا می‌کند. «ی/ي»، «ک/ك»، نیم‌فاصله و اعداد فارسی خودکار یکسان می‌شوند. این فهرست برای همه‌ی ویجت‌ها اعمال می‌شود.</p>
			<textarea name="<?php echo esc_attr( self::field_name( 'synonyms' ) ); ?>" rows="8" class="large-text" placeholder="dior، دیور&#10;chanel، شنل، شانل&#10;tom ford، تام فورد"><?php echo esc_textarea( (string) $s['synonyms'] ); ?></textarea>
		</div>
		<div class="tpls-admin__card">
			<h2>فهرست محصولات جستجو</h2>
			<p class="description">محصولات جستجوپذیر در یک فایل کوچک ذخیره می‌شوند که یک بار در مرورگر بازدیدکننده بارگذاری می‌شود. با هر تغییر محصول، فهرست خودکار دوباره ساخته می‌شود.</p>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: number of products, 2: date. */
						'%1$s محصول در فهرست — آخرین ساخت: %2$s',
						number_format_i18n( $count ),
						$time ? wp_date( 'Y/m/d H:i', $time ) : '—'
					)
				);
				?>
			</p>
			<a class="button" href="<?php echo esc_url( $url ); ?>">ساخت دوباره‌ی فهرست</a>
		</div>
		<?php
	}
}
