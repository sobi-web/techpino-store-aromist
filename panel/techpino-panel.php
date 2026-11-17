<?php
/**
 * «تنظیمات تکپینو»: پنل یکپارچه‌ی همه‌ی ماژول‌های تکپینو.
 *
 * برای هر ماژول: روشن/خاموش (ماژول خاموش اصلاً بارگذاری نمی‌شود)، نمایش یا پنهان بودن هر ویجت المنتور و
 * شورت‌کد روی سایت، و رنگ‌ها (همان متغیرهای CSS خود ماژول؛ خالی = رنگ قبلی).
 * ذخیره با AJAX است و همه‌چیز در یک گزینه‌ی autoload می‌ماند؛ در سایت فقط وقتی رنگی عوض شده باشد
 * یک تگ <style> کوچک چاپ می‌شود.
 *
 * هر سه افزونه‌ی تکپینو همین فایل را دارند؛ اولین افزونه‌ای که بارگذاری شود کلاس را تعریف می‌کند.
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Techpino_Panel', false ) ) {
	return;
}

final class Techpino_Panel {

	const OPTION = 'techpino_panel';
	const SLUG   = 'techpino-settings';
	const NONCE  = 'techpino_panel_save';
	const VER    = '1.0.0';

	/** @var string Plugin file whose /panel/ folder serves the CSS/JS. */
	private static $file = '';

	/** @var array|null */
	private static $opt = null;

	public static function boot( $plugin_file ) {
		if ( self::$file ) {
			return;
		}
		self::$file = $plugin_file;
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 1 );
		add_action( 'admin_menu', array( __CLASS__, 'sort' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_techpino_panel_save', array( __CLASS__, 'save' ) );
		add_action( 'wp_head', array( __CLASS__, 'print_css' ), 99 );
		add_filter( 'elementor/frontend/widget/should_render', array( __CLASS__, 'should_render' ), 10, 2 );
		add_filter( 'pre_do_shortcode_tag', array( __CLASS__, 'shortcode' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'mute_off_shortcodes' ), 99 );
	}

	/**
	 * Every module the panel knows.
	 *
	 * in:     constant defined when the plugin that ships the module is active.
	 * const:  constant defined when the module itself is loaded ('' = loaded whenever it is on).
	 * scope:  where the module defines its CSS variables (a bit more specific, so the panel wins).
	 * colors: role => [ label, default, css vars... ]; '--x:-20' = 20% darker, '--x:+90' = 90% lighter.
	 * parts:  what shows on the site; 'w:' Elementor widget, 's:' shortcode. Extra names are old aliases.
	 */
	public static function modules() {
		$primary = 'رنگ اصلی';
		$second  = 'رنگ دوم (طلایی)';
		$text    = 'رنگ متن';
		$bg      = 'رنگ پس‌زمینه';
		return array(
			'volume-stock'   => array(
				'title' => 'موجودی حجمی',
				'group' => 'موجودی حجمی',
				'icon'  => 'dashicons-chart-bar',
				'desc'  => 'موجودی عطرها بر حسب میلی‌لیتر، تاریخچه و گزارش مصرف.',
				'in'    => 'TECHPINO_STORE_FILE',
				'const' => 'AVS_VERSION',
				'warn'  => 'دیسکاوری ست بدون موجودی حجمی کار نمی‌کند و همراه آن از کار می‌افتد.',
				'pages' => array(
					'avs'          => 'محصولات حجمی',
					'avs-add'      => 'افزودن محصول',
					'avs-history'  => 'تاریخچه',
					'avs-reports'  => 'گزارش‌ها',
					'avs-settings' => 'تنظیمات',
				),
			),
			'discovery-set'  => array(
				'title'  => 'دیسکاوری ست',
				'group'  => 'دیسکاوری ست',
				'icon'   => 'dashicons-screenoptions',
				'desc'   => 'ست‌های آماده و ساخت ست اختصاصی از عطرهای موجودی حجمی.',
				'in'     => 'TECHPINO_STORE_FILE',
				'const'  => 'ADS_VERSION',
				'warn'   => 'ست‌های آماده و ساخت ست اختصاصی از سایت برداشته می‌شوند.',
				'pages'  => array(
					'ads-sets'                                 => 'ست‌های آماده',
					'post-new.php?post_type=product&ads_new=1' => 'افزودن ست',
					'ads-builder-perfumes'                     => 'عطرهای ست اختصاصی',
					'ads-settings'                             => 'تنظیمات',
				),
				'parts'  => array(
					's:aromist_discovery_sets' => 'آرشیو ست‌ها',
					's:aromist_set_builder'    => 'ساخت ست اختصاصی',
				),
				'scope'  => 'body .ads-wrap,body .ads-form,body .ads-mysets',
				'colors' => array(
					'primary'   => array( $primary, '#009879', '--ads-primary', '--ads-primary-dark:-20', '--ads-primary-100:+78', '--ads-primary-soft:+91' ),
					'secondary' => array( $second, '#d7b56a', '--ads-gold', '--ads-gold-dark:-22' ),
					'text'      => array( $text, '#1d1d1b', '--ads-ink', '--ads-black' ),
					'bg'        => array( $bg, '#f3f7f9', '--ads-bg' ),
				),
			),
			'deals'          => array(
				'title'  => 'تخفیف‌های ویژه',
				'group'  => 'فروشگاه',
				'icon'   => 'dashicons-tag',
				'desc'   => 'اسلایدر محصولات تخفیف‌دار با شمارش معکوس.',
				'in'     => 'TECHPINO_STORE_FILE',
				'const'  => 'ARD_VERSION',
				'pages'  => array( 'ard-deals' => 'تنظیمات تخفیف‌ها' ),
				'parts'  => array(
					'w:ard_deals'     => 'ویجت تخفیف‌های ویژه',
					's:aromist_deals' => 'شورت‌کد تخفیف‌های ویژه',
				),
				'scope'  => 'body .ard',
				'colors' => array(
					'primary'   => array( $primary, '#009879', '--ard-primary' ),
					'secondary' => array( $second, '#d7b56a', '--ard-gold' ),
					'text'      => array( $text, '#1d1d1b', '--ard-title' ),
					'bg'        => array( 'پس‌زمینه‌ی کارت', '#ffffff', '--ard-card-bg' ),
				),
			),
			'mobile-pdp'     => array(
				'title'  => 'صفحه‌ی محصول موبایل',
				'group'  => 'فروشگاه',
				'icon'   => 'dashicons-smartphone',
				'desc'   => 'صفحه‌ی محصول ساده و سریع برای موبایل و تبلت.',
				'in'     => 'TECHPINO_STORE_FILE',
				'const'  => 'AMPDP_VERSION',
				'warn'   => 'صفحه‌ی محصول در موبایل به حالت معمولی قالب برمی‌گردد.',
				'pages'  => array( 'ampdp' => 'تنظیمات صفحه‌ی محصول موبایل' ),
				'parts'  => array( 's:ampdp_button' => 'دکمه‌های هدر موبایل' ),
				'scope'  => 'html body.ampdp',
				'colors' => array(
					'primary'   => array( $primary, '#009879', '--ampdp-primary', '--ampdp-primary-600:-13', '--ampdp-primary-50:+91' ),
					'secondary' => array( $second, '#b8933f', '--ampdp-gold', '--ampdp-gold-50:+89' ),
					'text'      => array( $text, '#1d1d1b', '--ampdp-title' ),
					'bg'        => array( $bg, '#f3f7f9', '--ampdp-band' ),
				),
			),
			'product-faq'    => array(
				'title'  => 'سوالات متداول محصول',
				'group'  => 'فروشگاه',
				'icon'   => 'dashicons-editor-help',
				'desc'   => 'پرسش و پاسخ‌های هر محصول به صورت آکاردئونی.',
				'in'     => 'TECHPINO_STORE_FILE',
				'const'  => 'TPFQ_VERSION',
				'pages'  => array( 'tpfq-settings' => 'تنظیمات سوالات متداول' ),
				'parts'  => array(
					'w:tp-product-faq' => 'ویجت سوالات متداول',
					's:tp_product_faq' => 'شورت‌کد سوالات متداول',
				),
				'scope'  => 'body .tpfq',
				'colors' => array(
					'primary'   => array( $primary, '#009879', '--tpfq-accent' ),
					'secondary' => array( $second, '#d7b56a', '--tpfq-gold' ),
					'text'      => array( $text, '#1d1d1b', '--tpfq-ink', '--tpfq-q' ),
					'bg'        => array( 'پس‌زمینه‌ی کادر', '#ffffff', '--tpfq-card-bg' ),
				),
			),
			'live-search'    => array(
				'title'  => 'جستجوی زنده',
				'group'  => 'فروشگاه',
				'icon'   => 'dashicons-search',
				'desc'   => 'جستجوی فوری محصولات بدون بارگذاری صفحه.',
				'in'     => 'TECHPINO_STORE_FILE',
				'const'  => 'TPLS_VERSION',
				'pages'  => array( 'tpls' => 'تنظیمات و ظاهر جستجو' ),
				'parts'  => array( 'w:tp-live-search' => 'ویجت جستجوی زنده' ),
				'scope'  => 'body .tpls',
				'colors' => array(
					'primary'   => array( $primary, '#009879', '--tpls-c-accent' ),
					'secondary' => array( $second, '#d7b56a', '--tpls-c-gold-2' ),
					'text'      => array( $text, '#1d1d1b', '--tpls-c-ink' ),
					'bg'        => array( $bg, '#f3f7f9', '--tpls-c-soft' ),
				),
			),
			'mini-cart'      => array(
				'title'  => 'سبد خرید کشویی',
				'group'  => 'فروشگاه',
				'icon'   => 'dashicons-cart',
				'desc'   => 'آیکون سبد در هدر و سبد خرید کشویی کنار صفحه.',
				'in'     => 'TECHPINO_STORE_FILE',
				'const'  => 'TPMC_VERSION',
				'parts'  => array(
					'w:tp-mini-cart' => 'ویجت سبد خرید کشویی',
					's:tp_mini_cart' => array( 'شورت‌کد سبد خرید', 's:ng_cart_mini_icon' ),
				),
				'scope'  => 'body .tpmc-drawer,body .tpmc',
				'colors' => array(
					'primary'   => array( $primary, '#009879', '--tpmc-accent', '--tpmc-t-color', '--tpmc-t-bg-h' ),
					'secondary' => array( $second, '#e6c498', '--tpmc-gold' ),
					'text'      => array( $text, '#1d1d1b', '--tpmc-ink' ),
					'bg'        => array( $bg, '#ffffff', '--tpmc-bg' ),
				),
			),
			'hover-image'    => array(
				'title'  => 'تصویر هاور محصول',
				'group'  => 'فروشگاه',
				'icon'   => 'dashicons-format-image',
				'desc'   => 'تصویر دوم محصول هنگام بردن نشانگر روی کارت.',
				'in'     => 'TECHPINO_STORE_FILE',
				'const'  => 'AHI_VERSION',
				'parts'  => array(
					'w:aromist_hover_image' => 'ویجت تصویر محصول + هاور',
					's:aromist_hover_image' => 'شورت‌کد تصویر هاور',
				),
				'scope'  => 'body .ahi',
				'colors' => array(
					'text' => array( 'رنگ دکمه', '#1a1a1a', '--ahi-btn-color' ),
					'bg'   => array( 'پس‌زمینه‌ی دکمه', '#ffffff', '--ahi-btn-bg' ),
				),
			),
			'season-filter'  => array(
				'title'  => 'فیلتر فصلی کاروسل',
				'group'  => 'فروشگاه',
				'icon'   => 'dashicons-filter',
				'desc'   => 'دکمه‌های فیلتر AJAX بالای کاروسل محصولات.',
				'in'     => 'TECHPINO_STORE_FILE',
				'const'  => 'ASF_VERSION',
				'parts'  => array( 'w:aromist_season_filter' => 'ویجت فیلتر AJAX کاروسل' ),
				'scope'  => 'body .asf',
				'colors' => array(
					'primary' => array( 'رنگ گزینه‌ی فعال', '#d7b56a', '--asf-active-bg' ),
					'text'    => array( $text, '#1d1d1b', '--asf-color' ),
					'bg'      => array( $bg, '#ffffff', '--asf-bg' ),
				),
			),
			'loader'         => array(
				'title'  => 'لودینگ',
				'group'  => 'ظاهر سایت',
				'icon'   => 'dashicons-update',
				'desc'   => 'صفحه‌ی بارگذاری هنگام باز شدن سایت.',
				'in'     => 'TECHPINO_WIDGETS_FILE',
				'const'  => 'ARL_VERSION',
				'pages'  => array( 'arl-loader' => 'تنظیمات لودینگ' ),
				'scope'  => 'body .arl',
				'colors' => array(
					'primary'   => array( 'رنگ سبز', '#00785f', '--emerald' ),
					'secondary' => array( $second, '#d7b56a', '--gold' ),
					'text'      => array( $text, '#efe5cf', '--cream' ),
					'bg'        => array( $bg, '#0e3a31', '--forest', '--deep:-35', '--night:-74' ),
				),
			),
			'hero-slider'    => array(
				'title'  => 'اسلایدر بنر',
				'group'  => 'ظاهر سایت',
				'icon'   => 'dashicons-images-alt2',
				'desc'   => 'اسلایدر تصاویر بنر صفحه‌ی اصلی.',
				'in'     => 'TECHPINO_WIDGETS_FILE',
				'const'  => 'TPHS_VERSION',
				'pages'  => array( 'tphs' => 'مدیریت اسلایدها' ),
				'parts'  => array( 'w:tp-hero-slider' => 'ویجت اسلایدر بنر' ),
				'scope'  => 'body .tphs',
				'colors' => array(
					'primary' => array( $primary, '#009879', '--tphs-accent' ),
					'text'    => array( 'رنگ فلش‌ها', '#1d1d1b', '--tphs-ink' ),
				),
			),
			'contact-widget' => array(
				'title'  => 'ویجت تماس',
				'group'  => 'ظاهر سایت',
				'icon'   => 'dashicons-format-chat',
				'desc'   => 'دکمه‌ی شناور تماس و پیام‌رسان‌ها.',
				'in'     => 'TECHPINO_WIDGETS_FILE',
				'const'  => 'TPCW_VERSION',
				'pages'  => array( 'tpcw' => 'تنظیمات ویجت تماس' ),
				'scope'  => 'body',
				'colors' => array(
					'primary' => array( $primary, '#009879', '--tpcw-primary' ),
					'text'    => array( $text, '#1f2933', '--tpcw-ink' ),
				),
			),
			'single-post'    => array(
				'title'  => 'مقاله‌ی تکی',
				'group'  => 'ظاهر سایت',
				'icon'   => 'dashicons-media-document',
				'desc'   => 'چیدمان صفحه‌ی هر مقاله‌ی وبلاگ و بخش‌های آن.',
				'in'     => 'TECHPINO_WIDGETS_FILE',
				'const'  => 'TPSP_VERSION',
				'parts'  => array(
					's:tp_breadcrumb'      => 'مسیر صفحه',
					's:tp_post_toc'        => 'فهرست مطالب',
					's:tp_post_cat'        => 'دسته‌ی مقاله',
					's:tp_post_share'      => 'دکمه‌های اشتراک‌گذاری',
					's:tp_post_quicklinks' => 'لینک‌های سریع',
					's:tp_post_products'   => 'محصولات معرفی‌شده در مقاله',
					's:tp_products'        => 'لیست محصولات',
				),
				'scope'  => 'body',
				'colors' => array(
					'primary'   => array( $primary, '#009879', '--tpsp-primary', '--tpsp-primary-dark:-16' ),
					'secondary' => array( $second, '#d7b46a', '--tpsp-secondary' ),
					'text'      => array( $text, '#221e1f', '--tpsp-ink', '--tpsp-heading' ),
					'bg'        => array( $bg, '#f1f5f9', '--tpsp-soft' ),
				),
			),
			'reviews'        => array(
				'title'  => 'استودیو نظرات',
				'group'  => 'نظرات',
				'icon'   => 'dashicons-star-filled',
				'desc'   => 'نظرات، امتیاز و عکس‌های خریداران در صفحه‌ی محصول.',
				'in'     => 'TECHPINO_WIDGETS_FILE',
				'const'  => 'TPRV_VERSION',
				'pages'  => array( 'tprv-studio' => 'استودیو نظرات' ),
				'parts'  => array(
					'w:tprv-review-studio'    => array( 'استودیو کامل نظرات', 'w:ngtr-review-studio' ),
					'w:tprv-review-summary'   => array( 'میانگین امتیاز', 'w:ngtr-review-summary' ),
					'w:tprv-review-breakdown' => array( 'نمودار امتیاز نظرات', 'w:ngtr-review-breakdown' ),
					'w:tprv-review-gallery'   => array( 'تصاویر نظرات', 'w:ngtr-review-gallery' ),
					'w:tprv-review-filters'   => array( 'فیلتر نظرات', 'w:ngtr-review-filters' ),
					'w:tprv-review-list'      => array( 'لیست نظرات', 'w:ngtr-review-list' ),
					'w:tprv-review-form'      => array( 'فرم ثبت نظر', 'w:ngtr-review-form' ),
					's:tp_reviews'            => array( 'شورت‌کد نظرات', 's:ngt_reviews' ),
				),
				'scope'  => 'body .tprv-wrap',
				'colors' => array(
					'primary' => array( $primary, '#009879', '--tprv-accent', '--tprv-accent-strong:-26', '--tprv-accent-soft:+90' ),
					'text'    => array( $text, '#1d1d1b', '--tprv-text' ),
					'bg'      => array( $bg, '#ffffff', '--tprv-bg' ),
				),
			),
			'reviews-slider' => array(
				'title'  => 'اسلایدر نظرات محصولات',
				'group'  => 'نظرات',
				'icon'   => 'dashicons-format-quote',
				'desc'   => 'نمایش نظرات خریداران به صورت اسلایدر.',
				'in'     => 'TECHPINO_WIDGETS_FILE',
				'const'  => 'AROMIST_REVIEWS_VER',
				'parts'  => array( 'w:aromist_reviews' => 'ویجت نظرات محصولات' ),
				'scope'  => 'body .aromist-reviews',
				'colors' => array(
					'primary'   => array( $primary, '#009879', '--ar-primary' ),
					'secondary' => array( 'رنگ ستاره‌ها', '#d7b56a', '--ar-secondary', '--ar-star' ),
					'text'      => array( $text, '#555555', '--ar-text' ),
					'bg'        => array( 'پس‌زمینه‌ی کارت', '#ffffff', '--ar-card-bg' ),
				),
			),
			'user-panel'     => array(
				'title'  => 'پنل کاربری',
				'group'  => 'حساب کاربری',
				'icon'   => 'dashicons-admin-users',
				'desc'   => 'حساب کاربری مشتری: سفارش‌ها، کیف پول، آدرس‌ها و پیگیری.',
				'in'     => 'TPUP_FILE',
				'const'  => '',
				'warn'   => 'حساب کاربری به صفحه‌ی پیش‌فرض ووکامرس برمی‌گردد و کیف پول در دسترس نیست.',
				'pages'  => array( 'tpup-settings' => 'تنظیمات پنل کاربری' ),
				'scope'  => 'body,body .tpup-picker',
				'colors' => array(
					'primary'   => array( $primary, '#009879', '--tp-primary', '--tp-primary-700:-20', '--tp-primary-800:-36', '--tp-primary-100:+78', '--tp-primary-50:+91', '--tpc-primary', '--tpc-soft:+90' ),
					'secondary' => array( $second, '#d7b56a', '--tp-gold', '--tp-gold-700:-22', '--tp-gold-50:+88' ),
					'text'      => array( $text, '#221e1f', '--tp-ink', '--tpc-ink' ),
					'bg'        => array( $bg, '#f2f5f8', '--tp-bg' ),
				),
			),
			'snippets'       => array(
				'title'       => 'اسنیپت‌های سایت',
				'group'       => 'اسنیپت‌ها',
				'icon'        => 'dashicons-editor-code',
				'desc'        => 'کدهای اختصاصی سایت که قبلاً در افزونه‌ی Code Snippets بودند.',
				'in'          => 'TECHPINO_STORE_FILE',
				'const'       => 'TPSN_VERSION',
				'warn'        => 'همه‌ی اسنیپت‌ها (سبد خرید، تسویه حساب، گالری محصول، فوتر موبایل و…) از سایت برداشته می‌شوند.',
				'parts_title' => 'اسنیپت‌ها',
				'parts_hint'  => 'هر اسنیپت جدا روشن یا خاموش می‌شود. اسنیپت خاموش اصلاً اجرا نمی‌شود و شورت‌کدهایش چیزی نشان نمی‌دهند.',
				'parts'       => self::snippet_parts(),
				'tags'        => self::snippet_parts( true ),
			),
		);
	}

	/** Snippet switches ('x:id' => name), or with $tags every shortcode the snippets register. */
	private static function snippet_parts( $tags = false ) {
		static $list = null;
		if ( null === $list ) {
			$file = defined( 'TECHPINO_STORE_FILE' ) ? dirname( TECHPINO_STORE_FILE ) . '/modules/snippets/list.php' : '';
			$list = $file && is_file( $file ) ? (array) include $file : array();
		}
		$out = array();
		foreach ( $list as $id => $s ) {
			if ( $tags ) {
				$out = array_merge( $out, $s['tags'] );
			} else {
				$out[ 'x:' . $id ] = $s['name'];
			}
		}
		return $out;
	}

	/** Saved state, with defaults. */
	public static function opt() {
		if ( null === self::$opt ) {
			$o         = get_option( self::OPTION, array() );
			self::$opt = array_merge(
				array(
					'off'    => array(),
					'hidden' => array(),
					'colors' => array(),
					'css'    => '',
				),
				is_array( $o ) ? $o : array()
			);
		}
		return self::$opt;
	}

	/** True when the module was switched off in the panel (its code is then not loaded at all). */
	public static function is_off( $id ) {
		return in_array( $id, (array) self::opt()['off'], true );
	}

	/** Modules whose plugin is active. */
	private static function present() {
		return array_filter(
			self::modules(),
			static function ( $m ) {
				return defined( $m['in'] );
			}
		);
	}

	/** 'w:name' => [ label, every name it covers ]. */
	private static function parts( array $m ) {
		$out = array();
		foreach ( isset( $m['parts'] ) ? $m['parts'] : array() as $key => $p ) {
			$p           = (array) $p;
			$out[ $key ] = array( array_shift( $p ), array_merge( array( $key ), $p ) );
		}
		return $out;
	}

	// ------------------------------------------------------------------ site

	public static function print_css() {
		$css = (string) self::opt()['css'];
		if ( '' !== $css ) {
			echo '<style id="techpino-panel-css">' . wp_strip_all_tags( $css ) . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- built only from sanitize_hex_color() values.
		}
	}

	public static function should_render( $should, $widget ) {
		if ( $should && isset( self::opt()['hidden'][ 'w:' . $widget->get_name() ] ) && ! self::editing() ) {
			return false;
		}
		return $should;
	}

	public static function shortcode( $out, $tag ) {
		if ( false === $out && isset( self::opt()['hidden'][ 's:' . $tag ] ) && ! self::editing() ) {
			return '';
		}
		return $out;
	}

	/** A switched-off module's shortcodes print nothing instead of their raw [tag] text. */
	public static function mute_off_shortcodes() {
		if ( empty( self::opt()['off'] ) ) {
			return;
		}
		$modules = self::modules();
		foreach ( (array) self::opt()['off'] as $id ) {
			if ( ! isset( $modules[ $id ] ) ) {
				continue;
			}
			$tags = isset( $modules[ $id ]['tags'] ) ? $modules[ $id ]['tags'] : array();
			foreach ( self::parts( $modules[ $id ] ) as $part ) {
				foreach ( $part[1] as $name ) {
					if ( 's:' === substr( $name, 0, 2 ) ) {
						$tags[] = substr( $name, 2 );
					}
				}
			}
			foreach ( $tags as $tag ) {
				if ( ! shortcode_exists( $tag ) ) {
					add_shortcode( $tag, '__return_empty_string' );
				}
			}
		}
	}

	/** Hidden parts stay visible inside the Elementor editor, so they can still be edited. */
	private static function editing() {
		if ( ! class_exists( '\Elementor\Plugin', false ) || empty( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$el = \Elementor\Plugin::$instance;
		return ( isset( $el->editor ) && $el->editor->is_edit_mode() ) || ( isset( $el->preview ) && $el->preview->is_preview_mode() );
	}

	/** Mixes a #rrggbb colour with black (negative %) or white (positive %). */
	public static function shade( $hex, $pct ) {
		if ( ! $pct ) {
			return $hex;
		}
		$to  = $pct < 0 ? 0 : 255;
		$k   = min( 100, abs( $pct ) ) / 100;
		$out = '#';
		foreach ( str_split( substr( $hex, 1 ), 2 ) as $c ) {
			$v    = hexdec( $c );
			$out .= sprintf( '%02x', (int) round( $v + ( $to - $v ) * $k ) );
		}
		return $out;
	}

	public static function compile( array $colors ) {
		$css = '';
		foreach ( self::modules() as $id => $m ) {
			if ( empty( $colors[ $id ] ) || empty( $m['colors'] ) ) {
				continue;
			}
			$decl = '';
			foreach ( $m['colors'] as $role => $c ) {
				if ( empty( $colors[ $id ][ $role ] ) ) {
					continue;
				}
				foreach ( array_slice( $c, 2 ) as $var ) {
					$v     = explode( ':', $var );
					$decl .= $v[0] . ':' . self::shade( $colors[ $id ][ $role ], isset( $v[1] ) ? (int) $v[1] : 0 ) . ';';
				}
			}
			if ( '' !== $decl ) {
				$css .= $m['scope'] . '{' . $decl . '}';
			}
		}
		return $css;
	}

	/** '#ABC', 'abc', '#aabbcc' → '#aabbcc'; anything else → ''. */
	public static function hex( $v ) {
		$v = ltrim( trim( (string) $v ), '#' );
		if ( preg_match( '/^[0-9a-f]{3}$/i', $v ) ) {
			$v = $v[0] . $v[0] . $v[1] . $v[1] . $v[2] . $v[2];
		}
		return preg_match( '/^[0-9a-f]{6}$/i', $v ) ? '#' . strtolower( $v ) : '';
	}

	// ------------------------------------------------------------------ admin

	public static function menu() {
		global $admin_page_hooks;
		add_menu_page( 'تنظیمات تکپینو', 'تنظیمات تکپینو', 'manage_options', self::SLUG, array( __CLASS__, 'render' ), 'dashicons-admin-generic', 56.7 );
		$admin_page_hooks[ self::SLUG ] = self::SLUG; // hook ثابت برای همه‌ی زیرصفحه‌ها: techpino-settings_page_{slug}
		add_submenu_page( self::SLUG, 'تنظیمات تکپینو', 'پنل تنظیمات', 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
	}

	// Fixed, readable order; unknown pages keep their registration order at the end.
	public static function sort() {
		global $submenu;
		if ( empty( $submenu[ self::SLUG ] ) ) {
			return;
		}
		$order = array_flip( array( self::SLUG, 'ard-deals', 'ampdp', 'tpfq-settings', 'tpls', 'tphs', 'tpcw', 'arl-loader', 'tprv-studio', 'tpup-settings' ) );
		$keyed = array();
		foreach ( array_values( $submenu[ self::SLUG ] ) as $i => $item ) {
			$keyed[] = array( isset( $order[ $item[2] ] ) ? $order[ $item[2] ] : 999, $i, $item );
		}
		usort(
			$keyed,
			static function ( $a, $b ) {
				return array( $a[0], $a[1] ) <=> array( $b[0], $b[1] );
			}
		);
		$submenu[ self::SLUG ] = array_column( $keyed, 2 ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	}

	public static function action_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">تنظیمات تکپینو</a>' );
		return $links;
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}
		$url = plugin_dir_url( self::$file ) . 'panel/';
		wp_enqueue_style( 'techpino-panel', $url . 'panel.css', array( 'dashicons' ), self::VER );
		wp_enqueue_script( 'techpino-panel', $url . 'panel.js', array(), self::VER, true );
	}

	public static function save() {
		check_ajax_referer( self::NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'برای این کار دسترسی مدیر کل لازم است.' ), 403 );
		}
		$in     = isset( $_POST['m'] ) && is_array( $_POST['m'] ) ? wp_unslash( $_POST['m'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each value is validated below.
		$old    = self::opt();
		$off    = array_fill_keys( (array) $old['off'], 1 );
		$hidden = (array) $old['hidden'];
		$colors = (array) $old['colors'];

		foreach ( self::modules() as $id => $m ) {
			if ( empty( $in[ $id ]['present'] ) || ! is_array( $in[ $id ] ) ) {
				continue; // not on the screen (its plugin is inactive): keep what was saved
			}
			$row = $in[ $id ];
			if ( empty( $row['on'] ) ) {
				$off[ $id ] = 1;
			} else {
				unset( $off[ $id ] );
			}
			foreach ( self::parts( $m ) as $key => $part ) {
				foreach ( $part[1] as $name ) {
					if ( empty( $row['p'][ $key ] ) ) {
						$hidden[ $name ] = 1;
					} else {
						unset( $hidden[ $name ] );
					}
				}
			}
			unset( $colors[ $id ] );
			foreach ( isset( $m['colors'] ) ? $m['colors'] : array() as $role => $c ) {
				$hex = isset( $row['c'][ $role ] ) && is_string( $row['c'][ $role ] ) ? self::hex( $row['c'][ $role ] ) : '';
				if ( '' !== $hex ) {
					$colors[ $id ][ $role ] = $hex;
				}
			}
		}

		ksort( $off );
		ksort( $hidden );
		$new = array(
			'off'    => array_keys( $off ),
			'hidden' => $hidden,
			'colors' => $colors,
			'css'    => self::compile( $colors ),
		);
		update_option( self::OPTION, $new, true );
		self::$opt = $new;

		// Elementor may cache rendered widgets; hiding/showing one must not wait for that cache.
		if ( $new['hidden'] !== $old['hidden'] && class_exists( '\Elementor\Plugin', false ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		wp_send_json_success(
			array(
				'message' => 'تغییرات ذخیره شد.',
				'reload'  => $new['off'] !== array_values( (array) $old['off'] ),
			)
		);
	}

	private static function page_url( $slug ) {
		return admin_url( false !== strpos( $slug, '.php' ) ? $slug : 'admin.php?page=' . $slug );
	}

	public static function render() {
		$opt    = self::opt();
		$groups = array();
		foreach ( self::present() as $id => $m ) {
			$groups[ $m['group'] ][ $id ] = $m;
		}
		$count = array(
			'on'     => 0,
			'off'    => 0,
			'hidden' => 0,
			'colors' => 0,
		);
		foreach ( $groups as $list ) {
			foreach ( $list as $id => $m ) {
				++$count[ self::is_off( $id ) ? 'off' : 'on' ];
				foreach ( self::parts( $m ) as $key => $part ) {
					$count['hidden'] += isset( $opt['hidden'][ $key ] ) ? 1 : 0;
				}
				$count['colors'] += isset( $opt['colors'][ $id ] ) ? count( $opt['colors'][ $id ] ) : 0;
			}
		}
		?>
		<div class="wrap tpx">
			<h1 class="screen-reader-text">تنظیمات تکپینو</h1>
			<hr class="wp-header-end">
			<form id="tpx-form" class="tpx-app" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>" novalidate>
				<header class="tpx-top">
					<div class="tpx-brand">
						<span class="tpx-logo" aria-hidden="true">ت</span>
						<div><strong>تنظیمات تکپینو</strong><small>همه‌ی ماژول‌ها، ویجت‌ها و رنگ‌ها در یک جا</small></div>
					</div>
					<label class="tpx-search">
						<span class="dashicons dashicons-search" aria-hidden="true"></span>
						<span class="screen-reader-text">جستجو در ماژول‌ها</span>
						<input type="search" placeholder="جستجوی ماژول، ویجت یا شورت‌کد…" autocomplete="off">
					</label>
					<div class="tpx-actions">
						<span class="tpx-state" role="status" aria-live="polite">همه‌چیز ذخیره شده</span>
						<button type="submit" class="tpx-save" disabled><span class="dashicons dashicons-saved" aria-hidden="true"></span>ذخیره</button>
					</div>
				</header>

				<div class="tpx-body">
					<nav class="tpx-nav" aria-label="ماژول‌های تکپینو">
						<a class="tpx-nav__item is-active" href="#overview" data-nav="overview"><span class="dashicons dashicons-dashboard" aria-hidden="true"></span><span>نمای کلی</span></a>
						<?php foreach ( $groups as $group => $list ) : ?>
							<div class="tpx-nav__group" data-group>
								<p class="tpx-nav__title"><?php echo esc_html( $group ); ?></p>
								<?php foreach ( $list as $id => $m ) : ?>
									<a class="tpx-nav__item" href="#<?php echo esc_attr( $id ); ?>" data-nav="<?php echo esc_attr( $id ); ?>" data-search="<?php echo esc_attr( self::search_text( $m ) ); ?>">
										<span class="dashicons <?php echo esc_attr( $m['icon'] ); ?>" aria-hidden="true"></span>
										<span><?php echo esc_html( $m['title'] ); ?></span>
										<i class="tpx-dot<?php echo self::is_off( $id ) ? ' is-off' : ''; ?>" data-status="<?php echo esc_attr( $id ); ?>" aria-hidden="true"></i>
									</a>
								<?php endforeach; ?>
							</div>
						<?php endforeach; ?>
					</nav>

					<main class="tpx-main">
						<section class="tpx-view" data-view="overview" aria-labelledby="tpx-h-overview">
							<div class="tpx-hero">
								<div>
									<h2 id="tpx-h-overview" tabindex="-1">همه‌ی ماژول‌های تکپینو</h2>
									<p>روشن یا خاموش کردن ماژول‌ها، نمایش ویجت‌ها و رنگ‌ها را از همین‌جا عوض کنید؛ بدون بارگذاری دوباره‌ی صفحه.</p>
								</div>
								<dl class="tpx-stats">
									<div><dt>فعال</dt><dd data-count="on"><?php echo (int) $count['on']; ?></dd></div>
									<div><dt>خاموش</dt><dd data-count="off"><?php echo (int) $count['off']; ?></dd></div>
									<div><dt>بخش پنهان</dt><dd><?php echo (int) $count['hidden']; ?></dd></div>
									<div><dt>رنگ سفارشی</dt><dd><?php echo (int) $count['colors']; ?></dd></div>
								</dl>
							</div>
							<?php foreach ( $groups as $group => $list ) : ?>
								<div class="tpx-ov" data-group>
									<h3 class="tpx-ov__title"><?php echo esc_html( $group ); ?></h3>
									<div class="tpx-cards">
										<?php foreach ( $list as $id => $m ) : ?>
											<a class="tpx-mcard" href="#<?php echo esc_attr( $id ); ?>" data-search="<?php echo esc_attr( self::search_text( $m ) ); ?>">
												<span class="tpx-ico dashicons <?php echo esc_attr( $m['icon'] ); ?>" aria-hidden="true"></span>
												<span class="tpx-mcard__text"><strong><?php echo esc_html( $m['title'] ); ?></strong><small><?php echo esc_html( $m['desc'] ); ?></small></span>
												<span class="tpx-chip<?php echo self::is_off( $id ) ? ' is-off' : ''; ?>" data-status="<?php echo esc_attr( $id ); ?>" data-on="فعال" data-off="خاموش"><?php echo self::is_off( $id ) ? 'خاموش' : 'فعال'; ?></span>
												<?php if ( ! empty( $m['colors'] ) ) : ?>
													<span class="tpx-swatches" aria-hidden="true">
														<?php foreach ( $m['colors'] as $role => $c ) : ?>
															<i data-sw="<?php echo esc_attr( $id . ':' . $role ); ?>" style="background:<?php echo esc_attr( isset( $opt['colors'][ $id ][ $role ] ) ? $opt['colors'][ $id ][ $role ] : $c[1] ); ?>"></i>
														<?php endforeach; ?>
													</span>
												<?php endif; ?>
											</a>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</section>

						<?php
						foreach ( $groups as $list ) {
							foreach ( $list as $id => $m ) {
								self::render_module( $id, $m, $opt );
							}
						}
						?>
					</main>
				</div>
			</form>
			<div class="tpx-toast" id="tpx-toast" role="status" aria-live="polite"></div>
		</div>
		<?php
	}

	/** How a part is shown next to its label: [shortcode], #snippet-id or the widget name. */
	private static function part_code( $key ) {
		$name = substr( $key, 2 );
		$type = substr( $key, 0, 2 );
		return 's:' === $type ? '[' . $name . ']' : ( 'x:' === $type ? '#' . $name : $name );
	}

	private static function search_text( array $m ) {
		$s = $m['title'] . ' ' . $m['desc'] . ' ' . $m['group'];
		foreach ( self::parts( $m ) as $key => $part ) {
			$s .= ' ' . $part[0] . ' ' . substr( $key, 2 );
		}
		return $s;
	}

	private static function render_module( $id, array $m, array $opt ) {
		$on     = ! self::is_off( $id );
		$loaded = $m['const'] ? defined( $m['const'] ) : $on;
		$name   = 'm[' . $id . ']';
		$parts  = self::parts( $m );
		?>
		<section class="tpx-view" data-view="<?php echo esc_attr( $id ); ?>" aria-labelledby="tpx-h-<?php echo esc_attr( $id ); ?>" hidden>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[present]" value="1">
			<header class="tpx-head">
				<span class="tpx-ico tpx-ico--lg dashicons <?php echo esc_attr( $m['icon'] ); ?>" aria-hidden="true"></span>
				<div class="tpx-head__text">
					<h2 id="tpx-h-<?php echo esc_attr( $id ); ?>" tabindex="-1"><?php echo esc_html( $m['title'] ); ?></h2>
					<p><?php echo esc_html( $m['desc'] ); ?></p>
				</div>
				<label class="tpx-switch tpx-switch--lg">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[on]" value="1" data-module="<?php echo esc_attr( $id ); ?>"<?php checked( $on ); ?><?php echo empty( $m['warn'] ) ? '' : ' data-warn="' . esc_attr( $m['warn'] ) . '"'; ?>>
					<span class="tpx-switch__ui" aria-hidden="true"></span>
					<span class="tpx-switch__label">ماژول فعال است</span>
				</label>
			</header>

			<?php if ( $on && ! $loaded ) : ?>
				<p class="tpx-alert">این ماژول روشن است ولی بارگذاری نشده: در <code>wp-config.php</code> خاموش شده است. آن خط را بردارید.</p>
			<?php endif; ?>
			<?php $notice = (string) apply_filters( 'techpino_panel_notice', '', $id ); ?>
			<?php if ( '' !== $notice ) : ?>
				<p class="tpx-info"><?php echo esc_html( $notice ); ?></p>
			<?php endif; ?>
			<?php if ( preg_grep( '/^w:/', array_keys( $parts ) ) ) : ?>
				<p class="tpx-note">ماژول خاموش اصلاً بارگذاری نمی‌شود و ویجت‌هایش از فهرست المنتور بیرون می‌روند. اگر صفحه‌ای را که این ویجت‌ها را دارد در آن حالت در المنتور ذخیره کنید، ممکن است ویجت از آن صفحه حذف شود. برای پنهان کردن موقت، از «نمایش در سایت» استفاده کنید.</p>
			<?php endif; ?>

			<div class="tpx-grid">
				<?php if ( $parts ) : ?>
					<div class="tpx-card">
						<h3><?php echo esc_html( isset( $m['parts_title'] ) ? $m['parts_title'] : 'نمایش در سایت' ); ?></h3>
						<p class="tpx-card__hint"><?php echo esc_html( isset( $m['parts_hint'] ) ? $m['parts_hint'] : 'پنهان کردن روی سایت اثر دارد؛ داخل ویرایشگر المنتور همچنان دیده می‌شود تا بتوانید ویرایشش کنید.' ); ?></p>
						<ul class="tpx-parts">
							<?php foreach ( $parts as $key => $part ) : ?>
								<li>
									<label class="tpx-switch">
										<input type="checkbox" name="<?php echo esc_attr( $name . '[p][' . $key . ']' ); ?>" value="1"<?php checked( ! isset( $opt['hidden'][ $key ] ) ); ?>>
										<span class="tpx-switch__ui" aria-hidden="true"></span>
										<span class="tpx-switch__label"><?php echo esc_html( $part[0] ); ?> <code><?php echo esc_html( self::part_code( $key ) ); ?></code></span>
									</label>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $m['colors'] ) ) : ?>
					<div class="tpx-card">
						<h3>رنگ‌ها</h3>
						<p class="tpx-card__hint">خالی یعنی همان رنگ قبلی ماژول. تنظیم رنگ در خود ویجت المنتور همچنان بر این رنگ‌ها اولویت دارد.</p>
						<div class="tpx-colors">
							<?php
							foreach ( $m['colors'] as $role => $c ) :
								$val = isset( $opt['colors'][ $id ][ $role ] ) ? $opt['colors'][ $id ][ $role ] : '';
								$fid = 'tpx-c-' . $id . '-' . $role;
								?>
								<div class="tpx-color" data-default="<?php echo esc_attr( $c[1] ); ?>" data-sw="<?php echo esc_attr( $id . ':' . $role ); ?>">
									<label class="tpx-color__label" for="<?php echo esc_attr( $fid ); ?>"><?php echo esc_html( $c[0] ); ?></label>
									<div class="tpx-color__row">
										<input type="color" class="tpx-color__pick" value="<?php echo esc_attr( $val ? $val : $c[1] ); ?>" aria-label="<?php echo esc_attr( 'انتخاب ' . $c[0] ); ?>">
										<input type="text" class="tpx-color__hex" id="<?php echo esc_attr( $fid ); ?>" name="<?php echo esc_attr( $name . '[c][' . $role . ']' ); ?>" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php echo esc_attr( $c[1] ); ?>" maxlength="7" spellcheck="false" autocomplete="off" dir="ltr">
										<button type="button" class="tpx-color__reset" aria-label="<?php echo esc_attr( 'برگرداندن ' . $c[0] . ' به پیش‌فرض' ); ?>"<?php echo $val ? '' : ' hidden'; ?>><span class="dashicons dashicons-image-rotate" aria-hidden="true"></span></button>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $m['pages'] ) ) : ?>
					<div class="tpx-card">
						<h3>تنظیمات بیشتر</h3>
						<?php if ( $loaded ) : ?>
							<div class="tpx-links">
								<?php foreach ( $m['pages'] as $slug => $label ) : ?>
									<a class="tpx-link" href="<?php echo esc_url( self::page_url( $slug ) ); ?>"><?php echo esc_html( $label ); ?><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></a>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<p class="tpx-card__hint">صفحه‌های این ماژول وقتی ماژول روشن باشد در دسترس‌اند.</p>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}
