<?php
/**
 * Plugin Name:       تکپینو – فروشگاه و محصول
 * Description:       ماژول‌های فروشگاهی تکپینو در یک افزونه: موجودی حجمی، دیسکاوری ست، تخفیف‌های ویژه، تصویر هاور، صفحه‌ی محصول موبایل، فیلتر فصلی، سوالات متداول، سبد خرید کشویی، جستجوی زنده و اسنیپت‌های سایت (به جای Code Snippets).
 * Version:           1.1.0
 * Author:            تکپینو
 * Requires Plugins:  woocommerce
 * Text Domain:       techpino-store
 *
 * این افزونه چند افزونه‌ی جداگانه را به صورت «ماژول» کنار هم نگه می‌دارد (پوشه‌ی modules).
 * نام گزینه‌ها، شورت‌کدها و ویجت‌ها همان قبلی مانده تا داده‌ها و صفحه‌ها خراب نشوند.
 *
 * اگر یک ماژول مشکل ساخت، در wp-config.php این خط را اضافه کنید (نام پوشه‌ی ماژول‌ها، با ویرگول):
 *     define( 'TECHPINO_STORE_OFF', 'نام-ماژول,نام-دیگر' );
 * سپس افزونه را یک بار غیرفعال و دوباره فعال کنید.
 */

defined( 'ABSPATH' ) || exit;

define( 'TECHPINO_STORE_FILE', __FILE__ );

// پنل «تنظیمات تکپینو» (روشن/خاموش ماژول‌ها، نمایش ویجت‌ها، رنگ‌ها). هر سه افزونه‌ی تکپینو آن را دارند.
require_once __DIR__ . '/panel/techpino-panel.php';
Techpino_Panel::boot( __FILE__ );

// پوشه => [ فایل اصلی، ثابتی که افزونه‌ی مستقل قدیمی تعریف می‌کرد، عنوان ]
$tp_modules = array(
	'deals' => array( 'aromist-deals.php', 'ARD_VERSION', 'تخفیف‌های ویژه' ),
	'discovery-set' => array( 'aromist-discovery-set.php', 'ADS_VERSION', 'دیسکاوری ست' ),
	'hover-image' => array( 'aromist-hover-image.php', 'AHI_VERSION', 'تصویر هاور محصول' ),
	'mobile-pdp' => array( 'aromist-mobile-pdp.php', 'AMPDP_VERSION', 'صفحه‌ی محصول موبایل' ),
	'season-filter' => array( 'aromist-season-filter.php', 'ASF_VERSION', 'فیلتر فصلی کاروسل' ),
	'volume-stock' => array( 'aromist-volume-stock.php', 'AVS_VERSION', 'موجودی حجمی' ),
	'live-search' => array( 'techpino-live-search.php', 'TPLS_VERSION', 'جستجوی زنده' ),
	'mini-cart' => array( 'techpino-mini-cart.php', 'TPMC_VERSION', 'سبد خرید کشویی' ),
	'product-faq' => array( 'techpino-product-faq.php', 'TPFQ_VERSION', 'سوالات متداول محصول' ),
	'snippets' => array( 'techpino-snippets.php', 'TPSN_VERSION', 'اسنیپت‌های سایت' ),
);

$tp_off   = defined( 'TECHPINO_STORE_OFF' ) ? array_map( 'trim', explode( ',', (string) TECHPINO_STORE_OFF ) ) : array();
$tp_clash = array();

foreach ( $tp_modules as $tp_dir => $tp_m ) {
	// خاموش در wp-config یا در پنل «تنظیمات تکپینو»
	if ( in_array( $tp_dir, $tp_off, true ) || Techpino_Panel::is_off( $tp_dir ) ) {
		continue;
	}
	// نسخه‌ی مستقل قدیمی هنوز فعال است: بارگذاری دوباره‌ی همان کد خطای مرگبار می‌دهد.
	if ( defined( $tp_m[1] ) ) {
		$tp_clash[] = $tp_m[2];
		continue;
	}
	require_once plugin_dir_path( __FILE__ ) . 'modules/' . $tp_dir . '/' . $tp_m[0];
}

if ( $tp_clash ) {
	add_action(
		'admin_notices',
		static function () use ( $tp_clash ) {
			echo '<div class="notice notice-warning"><p><strong>تکپینو – فروشگاه و محصول:</strong> '
				. esc_html( 'این ماژول‌ها هنوز به صورت افزونه‌ی جدا فعال‌اند و از اینجا بارگذاری نشدند: ' . implode( '، ', $tp_clash ) . '. افزونه‌ی جدا را غیرفعال کنید و این افزونه را یک بار غیرفعال و دوباره فعال کنید.' )
				. '</p></div>';
		}
	);
}
unset( $tp_modules, $tp_off, $tp_clash, $tp_dir, $tp_m );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'Techpino_Panel', 'action_link' ) );

// همه‌ی ویجت‌های المنتور تکپینو در یک دسته‌ی مشترک.
add_action(
	'elementor/elements/categories_registered',
	static function ( $elements_manager ) {
		$elements_manager->add_category(
			'techpino',
			array(
				'title' => 'تکپینو',
				'icon'  => 'eicon-star',
			)
		);
	}
);
