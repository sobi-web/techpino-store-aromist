<?php
/**
 * Settings page: WooCommerce → «صفحه محصول موبایل».
 * Tabs: status (off / preview / on), header, search, product page, trust row.
 *
 * @package AromistMobilePDP
 */

defined( 'ABSPATH' ) || exit;

class AMPDP_Admin {

	const PAGE = 'ampdp';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 60 );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AMPDP_FILE ), array( $this, 'action_links' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'techpino-settings',
			__( 'صفحه محصول موبایل', 'aromist-mobile-pdp' ),
			__( 'صفحه محصول موبایل', 'aromist-mobile-pdp' ),
			'manage_woocommerce',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'تنظیمات', 'aromist-mobile-pdp' ) . '</a>' );
		return $links;
	}

	public function register(): void {
		register_setting(
			'ampdp',
			AMPDP_Plugin::OPTION,
			array(
				'type'              => 'string',
				'default'           => 'off',
				'sanitize_callback' => static function ( $value ) {
					$value = sanitize_key( (string) $value );
					return in_array( $value, AMPDP_Plugin::modes(), true ) ? $value : AMPDP_Plugin::mode();
				},
			)
		);
		register_setting(
			'ampdp',
			AMPDP_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'AMPDP_Settings', 'sanitize' ),
			)
		);
	}

	public function assets( $hook ): void {
		if ( ! str_ends_with( $hook, '_page_' . self::PAGE ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'ampdp-admin', AMPDP_URL . 'assets/js/admin.js', array( 'jquery' ), AMPDP_VERSION . '.' . filemtime( AMPDP_PATH . 'assets/js/admin.js' ), true );
		$icons = array();
		foreach ( array_keys( AMPDP_Settings::icons() ) as $key ) {
			$icons[ $key ] = AMPDP_Settings::icon_svg( $key, 'ampdp-admin-ic' );
		}
		wp_localize_script(
			'ampdp-admin',
			'ampdpAdmin',
			array(
				'icons' => $icons,
				'pick'  => __( 'انتخاب تصویر', 'aromist-mobile-pdp' ),
				'use'   => __( 'استفاده از این تصویر', 'aromist-mobile-pdp' ),
			)
		);
	}

	private function tabs(): array {
		return array(
			'status' => __( 'وضعیت', 'aromist-mobile-pdp' ),
			'header' => __( 'هدر', 'aromist-mobile-pdp' ),
			'search' => __( 'جستجو', 'aromist-mobile-pdp' ),
			'pdp'    => __( 'صفحه محصول', 'aromist-mobile-pdp' ),
			'trust'  => __( 'ردیف اعتماد', 'aromist-mobile-pdp' ),
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$tabs = $this->tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'status'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'status';
		}
		$name = AMPDP_Settings::OPTION;
		$s    = AMPDP_Settings::all();
		?>
		<div class="wrap ampdp-admin">
			<style>
				.ampdp-admin .ampdp-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
				.ampdp-admin .ampdp-logo { width: 64px; height: 64px; border: 1px solid #dcdcde; border-radius: 10px; background: #fff center / contain no-repeat; }
				.ampdp-admin .ampdp-admin-ic { width: 24px; height: 24px; fill: none; stroke: #0A6B56; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
				.ampdp-admin .ampdp-ic-preview { display: inline-grid; place-items: center; width: 38px; height: 38px; border-radius: 50%; background: #FBF3E2; }
				.ampdp-admin .ampdp-cats { max-height: 260px; overflow: auto; padding: 8px 12px; border: 1px solid #dcdcde; border-radius: 8px; background: #fff; max-width: 420px; }
				.ampdp-admin .ampdp-cats label { display: block; line-height: 2; }
				.ampdp-admin .ampdp-trust td { padding: 8px 6px; vertical-align: middle; }
				.ampdp-admin .ampdp-banner-prev { width: 120px; height: 60px; border: 1px dashed #c3c4c7; border-radius: 8px; background: #fff center / cover no-repeat; }
				.ampdp-admin .ampdp-banner-prev.has-img { border-style: solid; }
				.ampdp-admin code.ampdp-code { display: inline-block; margin: 2px 0; direction: ltr; }
			</style>
			<h1><?php esc_html_e( 'صفحه محصول موبایل', 'aromist-mobile-pdp' ); ?></h1>
			<p><?php esc_html_e( 'در موبایل و تبلت (تا ۱۰۲۴ پیکسل): هدر جمع‌وجور و جستجو در همه‌ی صفحه‌ها، کاروسل‌ها به شکل اسکرول ساده، صفحه‌ی اصلی و آرشیوها مرتب‌شده، و چیدمان تازه‌ی صفحه‌ی محصول با نوار خرید چسبان و برگه‌ی انتخاب حجم. در دسکتاپ فقط کارت اقساط اسنپ‌پی صفحه‌ی محصول عوض می‌شود. فوتر هیچ‌جا تغییر نمی‌کند.', 'aromist-mobile-pdp' ); ?></p>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nav-tab <?php echo $key === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&tab=' . $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( 'ampdp' ); ?>
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>[_tab]" value="<?php echo esc_attr( $tab ); ?>">
				<table class="form-table" role="presentation">
					<?php
					if ( 'header' === $tab ) {
						$this->tab_header( $name, $s );
					} elseif ( 'search' === $tab ) {
						$this->tab_search( $name, $s );
					} elseif ( 'pdp' === $tab ) {
						$this->tab_pdp( $name, $s );
					} elseif ( 'trust' === $tab ) {
						$this->tab_trust( $name, $s );
					} else {
						$this->tab_status();
					}
					?>
				</table>
				<?php if ( 'status' !== $tab ) : ?>
					<input type="hidden" name="<?php echo esc_attr( AMPDP_Plugin::OPTION ); ?>" value="<?php echo esc_attr( AMPDP_Plugin::mode() ); ?>">
				<?php endif; ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private function tab_status(): void {
		$mode    = AMPDP_Plugin::mode();
		$choices = array(
			'off'     => array( __( 'خاموش', 'aromist-mobile-pdp' ), __( 'سایت همان‌طور که بود نمایش داده می‌شود.', 'aromist-mobile-pdp' ) ),
			'preview' => array( __( 'پیش‌نمایش (فقط مدیران)', 'aromist-mobile-pdp' ), __( 'فقط مدیران فروشگاه که وارد حساب شده‌اند نسخه‌ی جدید را می‌بینند. برای دیدن روی گوشی، با همان حساب مدیر در گوشی وارد سایت شوید.', 'aromist-mobile-pdp' ) ),
			'on'      => array( __( 'روشن برای همه', 'aromist-mobile-pdp' ), __( 'همه‌ی بازدیدکننده‌ها در موبایل نسخه‌ی جدید را می‌بینند. کش صفحه‌ها بعد از ذخیره پاک می‌شود.', 'aromist-mobile-pdp' ) ),
		);
		$sample  = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 1,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'ids',
				'type'    => array( 'variable', 'simple' ),
			)
		);
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'وضعیت', 'aromist-mobile-pdp' ); ?></th>
			<td>
				<fieldset>
					<?php foreach ( $choices as $value => $choice ) : ?>
						<label style="display:block;margin-bottom:12px">
							<input type="radio" name="<?php echo esc_attr( AMPDP_Plugin::OPTION ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $mode, $value ); ?>>
							<strong><?php echo esc_html( $choice[0] ); ?></strong>
							<span class="description" style="display:block;margin-inline-start:24px"><?php echo esc_html( $choice[1] ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<?php if ( $sample ) : ?>
					<p><a href="<?php echo esc_url( get_permalink( (int) $sample[0] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'دیدن یک صفحه‌ی محصول', 'aromist-mobile-pdp' ); ?></a></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function tab_header( string $name, array $s ): void {
		$logo_id  = (int) $s['logo_id'];
		$site_id  = (int) get_theme_mod( 'custom_logo' );
		$logo_url = (string) wp_get_attachment_image_url( $logo_id ? $logo_id : $site_id, 'thumbnail' );
		$menus    = wp_get_nav_menus();
		?>
		<tr>
			<th scope="row"><label for="ampdp-header-tpl"><?php esc_html_e( 'طراحی هدر', 'aromist-mobile-pdp' ); ?></label></th>
			<td>
				<?php $this->template_select( 'ampdp-header-tpl', $name . '[header_tpl]', (int) $s['header_tpl'], __( 'هدر آماده‌ی پلاگین (تنظیمات پایین)', 'aromist-mobile-pdp' ) ); ?>
				<p class="description"><?php esc_html_e( 'برای طراحی هدر با المنتور: در قالب‌ها ← قالب‌های ذخیره‌شده یک قالب «سکشن» یا «کانتینر» بسازید، آن را در حالت موبایل طراحی کنید و این‌جا انتخابش کنید. هدر فقط در موبایل و تبلت (تا ۱۰۲۴ پیکسل) نمایش داده می‌شود. لوگو، اندازه‌ی لوگو، متن کنار لوگو و دکمه‌های هدر آماده (پایین همین صفحه) دیگر استفاده نمی‌شوند؛ «منوی دکمه‌ی ☰» و «هنگام اسکرول» همچنان کار می‌کنند.', 'aromist-mobile-pdp' ); ?></p>
				<p class="description"><?php esc_html_e( 'دکمه‌ها داخل قالب المنتور (با ویجت «کد کوتاه»):', 'aromist-mobile-pdp' ); ?>
					<br><code class="ampdp-code">[ampdp_button type="menu"]</code> <?php esc_html_e( 'منو', 'aromist-mobile-pdp' ); ?>
					&nbsp; <code class="ampdp-code">[ampdp_button type="search"]</code> <?php esc_html_e( 'جستجو', 'aromist-mobile-pdp' ); ?>
					&nbsp; <code class="ampdp-code">[ampdp_button type="account"]</code> <?php esc_html_e( 'حساب', 'aromist-mobile-pdp' ); ?>
					&nbsp; <code class="ampdp-code">[ampdp_button type="cart"]</code> <?php esc_html_e( 'سبد با تعداد', 'aromist-mobile-pdp' ); ?>
				</p>
				<p class="description"><?php esc_html_e( 'یا به هر ویجتی (دکمه، آیکون، تصویر) در «پیشرفته ← کلاس‌های CSS» یکی از این کلاس‌ها را بدهید:', 'aromist-mobile-pdp' ); ?>
					<code class="ampdp-code">ampdp-open-menu</code> <code class="ampdp-code">ampdp-open-search</code> <code class="ampdp-code">ampdp-open-cart</code>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'هدر صفحه‌ها', 'aromist-mobile-pdp' ); ?></th>
			<td>
				<fieldset>
					<label style="display:block;line-height:2.2"><input type="radio" name="<?php echo esc_attr( $name ); ?>[header_style]" value="search" <?php checked( $s['header_style'], 'search' ); ?>> <?php esc_html_e( 'فقط کادر جستجو (نتایج در پنلی زیر هدر باز می‌شود)', 'aromist-mobile-pdp' ); ?></label>
					<label style="display:block;line-height:2.2"><input type="radio" name="<?php echo esc_attr( $name ); ?>[header_style]" value="icons" <?php checked( $s['header_style'], 'icons' ); ?>> <?php esc_html_e( 'منو، لوگو و دکمه‌های جستجو / حساب / سبد', 'aromist-mobile-pdp' ); ?></label>
				</fieldset>
				<p class="description"><?php esc_html_e( 'صفحه‌ی محصول همیشه هدر خودش را دارد: بازگشت، لوگو، جستجو و سبد. کادر جستجو به پلاگین «جستجوی زنده تکپینو» نیاز دارد.', 'aromist-mobile-pdp' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'هنگام اسکرول', 'aromist-mobile-pdp' ); ?></th>
			<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[header_hide]" value="1" <?php checked( ! empty( $s['header_hide'] ) ); ?>> <?php esc_html_e( 'با اسکرول به پایین هدر کنار برود و با اسکرول به بالا برگردد', 'aromist-mobile-pdp' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'لوگو', 'aromist-mobile-pdp' ); ?></th>
			<td>
				<div class="ampdp-row">
					<span class="ampdp-logo" data-ampdp-logo-preview data-site="<?php echo esc_url( (string) wp_get_attachment_image_url( $site_id, 'thumbnail' ) ); ?>" style="background-image:url('<?php echo esc_url( $logo_url ); ?>')"></span>
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>[logo_id]" value="<?php echo esc_attr( (string) $logo_id ); ?>" data-ampdp-logo-id>
					<button type="button" class="button" data-ampdp-logo-pick><?php esc_html_e( 'انتخاب تصویر', 'aromist-mobile-pdp' ); ?></button>
					<button type="button" class="button-link" data-ampdp-logo-reset><?php esc_html_e( 'برگشت به لوگوی سایت', 'aromist-mobile-pdp' ); ?></button>
				</div>
				<p class="description"><?php esc_html_e( 'تصویر مربعی.', 'aromist-mobile-pdp' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ampdp-logo-size"><?php esc_html_e( 'اندازه‌ی لوگو', 'aromist-mobile-pdp' ); ?></label></th>
			<td><input type="number" class="small-text" id="ampdp-logo-size" min="20" max="56" name="<?php echo esc_attr( $name ); ?>[logo_size]" value="<?php echo esc_attr( (string) $s['logo_size'] ); ?>"> <?php esc_html_e( 'پیکسل (۲۰ تا ۵۶؛ ارتفاع هدر ۵۶ پیکسل است)', 'aromist-mobile-pdp' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><label for="ampdp-wordmark"><?php esc_html_e( 'متن کنار لوگو', 'aromist-mobile-pdp' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" id="ampdp-wordmark" name="<?php echo esc_attr( $name ); ?>[wordmark]" value="<?php echo esc_attr( (string) $s['wordmark'] ); ?>">
				<p class="description"><?php esc_html_e( 'خالی بگذارید تا فقط لوگو نمایش داده شود.', 'aromist-mobile-pdp' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ampdp-menu"><?php esc_html_e( 'منوی دکمه‌ی ☰', 'aromist-mobile-pdp' ); ?></label></th>
			<td>
				<select id="ampdp-menu" name="<?php echo esc_attr( $name ); ?>[menu]">
					<option value="site" <?php selected( $s['menu'], 'site' ); ?>><?php esc_html_e( 'منوی نوار پایین سایت (دسته‌بندی محصولات)', 'aromist-mobile-pdp' ); ?></option>
					<?php foreach ( $menus as $menu ) : ?>
						<option value="<?php echo esc_attr( (string) $menu->term_id ); ?>" <?php selected( (string) $s['menu'], (string) $menu->term_id ); ?>><?php echo esc_html( sprintf( /* translators: %s: menu name */ __( 'فهرست وردپرس: %s', 'aromist-mobile-pdp' ), $menu->name ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'فهرست‌های وردپرس از نمایش ← فهرست‌ها ساخته می‌شوند؛ زیرمنوها به شکل بازشو نمایش داده می‌شوند.', 'aromist-mobile-pdp' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'دکمه‌های هدر', 'aromist-mobile-pdp' ); ?></th>
			<td>
				<fieldset>
					<?php
					foreach ( array(
						'show_search'  => __( 'جستجو', 'aromist-mobile-pdp' ),
						'show_account' => __( 'حساب کاربری', 'aromist-mobile-pdp' ),
						'show_cart'    => __( 'سبد خرید', 'aromist-mobile-pdp' ),
					) as $key => $label ) :
						?>
						<label style="display:block;line-height:2.2"><input type="checkbox" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="1" <?php checked( ! empty( $s[ $key ] ) ); ?>> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	private function tab_search( string $name, array $s ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$tree  = array();
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$tree[ (int) $term->parent ][] = $term;
			}
		}
		$picked = array_map( 'intval', (array) $s['search_cats'] );
		?>
		<tr>
			<th scope="row"><label for="ampdp-ph"><?php esc_html_e( 'متن داخل کادر', 'aromist-mobile-pdp' ); ?></label></th>
			<td><input type="text" class="regular-text" id="ampdp-ph" name="<?php echo esc_attr( $name ); ?>[search_placeholder]" value="<?php echo esc_attr( (string) $s['search_placeholder'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'پیشنهاد هنگام تایپ', 'aromist-mobile-pdp' ); ?></th>
			<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[search_live]" value="1" <?php checked( ! empty( $s['search_live'] ) ); ?>> <?php esc_html_e( 'با تایپ دو حرف، محصولات مرتبط با عکس و قیمت نشان داده شوند', 'aromist-mobile-pdp' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="ampdp-popular"><?php esc_html_e( 'جستجوهای پرطرفدار', 'aromist-mobile-pdp' ); ?></label></th>
			<td>
				<textarea id="ampdp-popular" class="large-text" rows="5" name="<?php echo esc_attr( $name ); ?>[search_popular]"><?php echo esc_textarea( (string) $s['search_popular'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'هر عبارت در یک خط. خالی بگذارید تا این بخش نمایش داده نشود.', 'aromist-mobile-pdp' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'دسته‌بندی‌های میانبر', 'aromist-mobile-pdp' ); ?></th>
			<td>
				<div class="ampdp-cats">
					<?php $this->cat_boxes( $tree, 0, 0, $picked, $name ); ?>
				</div>
				<p class="description"><?php esc_html_e( 'با تصویر دسته‌بندی (اگر تصویر داشته باشد) زیر جستجوهای پرطرفدار نمایش داده می‌شوند.', 'aromist-mobile-pdp' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'بنرها', 'aromist-mobile-pdp' ); ?></th>
			<td>
				<table class="ampdp-trust">
					<tbody>
						<?php
						$banners = array_values( (array) $s['search_banners'] );
						for ( $i = 0; $i < AMPDP_Settings::BANNER_MAX; $i++ ) :
							$banner = (array) ( $banners[ $i ] ?? array() );
							$image  = (int) ( $banner['image'] ?? 0 );
							$url    = $image ? (string) wp_get_attachment_image_url( $image, 'medium' ) : '';
							$base   = $name . '[search_banners][' . $i . ']';
							?>
							<tr data-ampdp-banner>
								<td><span class="ampdp-banner-prev<?php echo $url ? ' has-img' : ''; ?>" data-ampdp-img-preview style="<?php echo $url ? 'background-image:url(\'' . esc_url( $url ) . '\')' : ''; ?>"></span></td>
								<td>
									<input type="hidden" name="<?php echo esc_attr( $base . '[image]' ); ?>" value="<?php echo esc_attr( (string) $image ); ?>" data-ampdp-img-id>
									<button type="button" class="button" data-ampdp-img-pick><?php esc_html_e( 'انتخاب تصویر', 'aromist-mobile-pdp' ); ?></button>
									<button type="button" class="button-link" data-ampdp-img-clear><?php esc_html_e( 'حذف', 'aromist-mobile-pdp' ); ?></button>
								</td>
								<td><input type="text" dir="ltr" class="regular-text" name="<?php echo esc_attr( $base . '[link]' ); ?>" value="<?php echo esc_attr( (string) ( $banner['link'] ?? '' ) ); ?>" placeholder="https://… (<?php esc_attr_e( 'لینک اختیاری', 'aromist-mobile-pdp' ); ?>)"></td>
							</tr>
						<?php endfor; ?>
					</tbody>
				</table>
				<p>
					<label for="ampdp-banner-layout"><?php esc_html_e( 'چیدمان', 'aromist-mobile-pdp' ); ?></label>
					<select id="ampdp-banner-layout" name="<?php echo esc_attr( $name ); ?>[banner_layout]">
						<option value="stack" <?php selected( $s['banner_layout'], 'stack' ); ?>><?php esc_html_e( 'تمام‌عرض، زیر هم', 'aromist-mobile-pdp' ); ?></option>
						<option value="grid" <?php selected( $s['banner_layout'], 'grid' ); ?>><?php esc_html_e( 'دوتایی کنار هم', 'aromist-mobile-pdp' ); ?></option>
						<option value="scroll" <?php selected( $s['banner_layout'], 'scroll' ); ?>><?php esc_html_e( 'ردیف اسکرولی (به پهلو)', 'aromist-mobile-pdp' ); ?></option>
					</select>
					&nbsp;
					<label for="ampdp-banner-pos"><?php esc_html_e( 'جای بنرها', 'aromist-mobile-pdp' ); ?></label>
					<select id="ampdp-banner-pos" name="<?php echo esc_attr( $name ); ?>[banner_pos]">
						<option value="top" <?php selected( $s['banner_pos'], 'top' ); ?>><?php esc_html_e( 'بالای همه', 'aromist-mobile-pdp' ); ?></option>
						<option value="middle" <?php selected( $s['banner_pos'], 'middle' ); ?>><?php esc_html_e( 'بعد از جستجوهای پرطرفدار', 'aromist-mobile-pdp' ); ?></option>
						<option value="bottom" <?php selected( $s['banner_pos'], 'bottom' ); ?>><?php esc_html_e( 'پایین همه', 'aromist-mobile-pdp' ); ?></option>
					</select>
				</p>
				<p class="description"><?php esc_html_e( 'بنرها قبل از تایپ کردن نمایش داده می‌شوند. اندازه‌ی پیشنهادی: تمام‌عرض ۱۰۸۰×۴۵۰، دوتایی ۵۴۰×۵۴۰.', 'aromist-mobile-pdp' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ampdp-search-tpl"><?php esc_html_e( 'قالب المنتور در جستجو', 'aromist-mobile-pdp' ); ?></label></th>
			<td>
				<?php $this->template_select( 'ampdp-search-tpl', $name . '[search_tpl]', (int) $s['search_tpl'], __( 'هیچ', 'aromist-mobile-pdp' ) ); ?>
				<p class="description"><?php esc_html_e( 'برای بنر یا هر طرح دیگری که با المنتور ساخته‌اید؛ بعد از بنرهای بالا و در همان جا نمایش داده می‌شود.', 'aromist-mobile-pdp' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/** Dropdown of Elementor templates, with links to edit the chosen one or make a new one. */
	private function template_select( string $id, string $field, int $value, string $none ): void {
		$templates = AMPDP_Settings::elementor_templates();
		?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $field ); ?>">
			<option value="0"><?php echo esc_html( $none ); ?></option>
			<?php foreach ( $templates as $tpl_id => $title ) : ?>
				<option value="<?php echo esc_attr( (string) $tpl_id ); ?>" <?php selected( $value, $tpl_id ); ?>><?php echo esc_html( $title ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php if ( $value && isset( $templates[ $value ] ) ) : ?>
			<a class="button" href="<?php echo esc_url( admin_url( 'post.php?post=' . $value . '&action=elementor' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'ویرایش با المنتور', 'aromist-mobile-pdp' ); ?></a>
		<?php endif; ?>
		<a class="button-link" href="<?php echo esc_url( admin_url( 'edit.php?post_type=elementor_library&tabs_group=library' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'ساخت قالب تازه', 'aromist-mobile-pdp' ); ?></a>
		<?php if ( ! $templates ) : ?>
			<p class="description"><?php esc_html_e( 'هنوز قالبی از نوع سکشن، کانتینر، برگه یا هدر منتشر نشده است.', 'aromist-mobile-pdp' ); ?></p>
		<?php endif; ?>
		<?php
	}

	private function tab_pdp( string $name, array $s ): void {
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'دسته‌بندی‌ها', 'aromist-mobile-pdp' ); ?></th>
			<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[pdp_tags]" value="1" <?php checked( ! empty( $s['pdp_tags'] ) ); ?>> <?php esc_html_e( 'دسته‌بندی‌های عطر (مثل مردانه، زمستانی) زیر تصویر و بالای نام عطر نمایش داده شوند', 'aromist-mobile-pdp' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'منوی بخش‌ها', 'aromist-mobile-pdp' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[pdp_tabs]" value="1" <?php checked( ! empty( $s['pdp_tabs'] ) ); ?>> <?php esc_html_e( 'بعد از باکس خرید، نوار بخش‌ها بالای صفحه بچسبد', 'aromist-mobile-pdp' ); ?></label>
				<p class="description"><?php esc_html_e( 'درباره عطر، مشخصات، نت‌ها، معرفی کامل، سوالات، نظرات و مشابه؛ با لمس هر کدام صفحه به همان بخش می‌رود و بخشی که در آن هستید پررنگ می‌شود.', 'aromist-mobile-pdp' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'سوالات متداول', 'aromist-mobile-pdp' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[pdp_faq]" value="1" <?php checked( ! empty( $s['pdp_faq'] ) ); ?>> <?php esc_html_e( 'بعد از «معرفی کامل عطر»، سوالات متداول همان محصول نمایش داده شود', 'aromist-mobile-pdp' ); ?></label>
				<p class="description">
					<?php
					if ( class_exists( 'TPFQ_Plugin' ) ) {
						printf(
							/* translators: %s: link to the FAQ settings */
							esc_html__( 'سوال‌ها از پلاگین «سوالات متداول محصول تکپینو» می‌آیند (%s، یا باکس همان محصول). محصولی که سوال ندارد این بخش را ندارد.', 'aromist-mobile-pdp' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=tpfq-settings' ) ) . '">' . esc_html__( 'تنظیمات تکپینو ← سوالات متداول', 'aromist-mobile-pdp' ) . '</a>'
						);
					} else {
						esc_html_e( 'برای این بخش، پلاگین «سوالات متداول محصول تکپینو» باید فعال باشد.', 'aromist-mobile-pdp' );
					}
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ampdp-about-lines"><?php esc_html_e( '«درباره این عطر»', 'aromist-mobile-pdp' ); ?></label></th>
			<td><input type="number" class="small-text" id="ampdp-about-lines" min="3" max="30" name="<?php echo esc_attr( $name ); ?>[about_lines]" value="<?php echo esc_attr( (string) $s['about_lines'] ); ?>"> <?php esc_html_e( 'خط نمایش داده شود؛ اگر متن بلندتر بود، دکمه‌ی «نمایش بیشتر» می‌آید', 'aromist-mobile-pdp' ); ?></td>
		</tr>
		<?php
	}

	private function cat_boxes( array $tree, int $parent, int $depth, array $picked, string $name ): void {
		foreach ( $tree[ $parent ] ?? array() as $term ) {
			if ( 'uncategorized' === $term->slug ) {
				continue;
			}
			printf(
				'<label style="padding-inline-start:%1$dpx"><input type="checkbox" name="%2$s[search_cats][]" value="%3$d" %4$s> %5$s <span class="description">(%6$d)</span></label>',
				(int) ( $depth * 18 ),
				esc_attr( $name ),
				(int) $term->term_id,
				checked( in_array( (int) $term->term_id, $picked, true ), true, false ),
				esc_html( $term->name ),
				(int) $term->count
			);
			$this->cat_boxes( $tree, (int) $term->term_id, $depth + 1, $picked, $name );
		}
	}

	private function tab_trust( string $name, array $s ): void {
		$items = array_values( (array) $s['trust'] );
		$icons = AMPDP_Settings::icons();
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'نمایش', 'aromist-mobile-pdp' ); ?></th>
			<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[trust_on]" value="1" <?php checked( ! empty( $s['trust_on'] ) ); ?>> <?php esc_html_e( 'ردیف اعتماد زیر باکس خرید نمایش داده شود', 'aromist-mobile-pdp' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'کادرها', 'aromist-mobile-pdp' ); ?></th>
			<td>
				<table class="ampdp-trust">
					<thead><tr><th></th><th><?php esc_html_e( 'آیکون', 'aromist-mobile-pdp' ); ?></th><th><?php esc_html_e( 'عنوان', 'aromist-mobile-pdp' ); ?></th><th><?php esc_html_e( 'لینک (اختیاری)', 'aromist-mobile-pdp' ); ?></th></tr></thead>
					<tbody>
						<?php
						for ( $i = 0; $i < AMPDP_Settings::TRUST_MAX; $i++ ) :
							$item = (array) ( $items[ $i ] ?? array() );
							$icon = (string) ( $item['icon'] ?? 'shield' );
							$base = $name . '[trust][' . $i . ']';
							?>
							<tr>
								<td><span class="ampdp-ic-preview" data-ampdp-icon-preview><?php echo AMPDP_Settings::icon_svg( $icon, 'ampdp-admin-ic' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span></td>
								<td>
									<select name="<?php echo esc_attr( $base . '[icon]' ); ?>" data-ampdp-icon>
										<?php foreach ( $icons as $key => $def ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $icon, $key ); ?>><?php echo esc_html( $def[0] ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td><input type="text" name="<?php echo esc_attr( $base . '[title]' ); ?>" value="<?php echo esc_attr( (string) ( $item['title'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'مثلاً اصالت کالا', 'aromist-mobile-pdp' ); ?>"></td>
								<td><input type="text" dir="ltr" name="<?php echo esc_attr( $base . '[link]' ); ?>" value="<?php echo esc_attr( (string) ( $item['link'] ?? '' ) ); ?>" placeholder="tel:0912… / https://…"></td>
							</tr>
						<?php endfor; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'تا ۴ کادر؛ کادری که عنوان ندارد نمایش داده نمی‌شود. عنوان‌ها کوتاه باشند (حداکثر حدود ۱۲ حرف) تا در یک خط جا شوند.', 'aromist-mobile-pdp' ); ?></p>
			</td>
		</tr>
		<?php
	}
}
