<?php
/**
 * Elementor widget: «جستجوی زنده (تکپینو)».
 *
 * The field, the suggestions, the empty state and the skeleton are printed here; the
 * result cards are built by assets/js/live-search.js from the product index. Most style
 * controls write CSS variables that assets/css/live-search.css reads, so a control left
 * empty falls back to the site palette.
 *
 * @package TechpinoLiveSearch
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;

class TPLS_Widget extends \Elementor\Widget_Base {

	private const ROOT  = '{{WRAPPER}} .tpls';
	private const FIELD = '{{WRAPPER}} .tpls-field';
	private const INPUT = '{{WRAPPER}} .tpls-input';
	private const PANEL = '{{WRAPPER}} .tpls-panel';
	private const CARD  = '{{WRAPPER}} .tpls-card, {{WRAPPER}} .tpls-hcard';

	public function get_name(): string {
		return 'tp-live-search';
	}

	public function get_title(): string {
		return 'جستجوی زنده (تکپینو)';
	}

	public function get_icon(): string {
		return 'eicon-site-search';
	}

	public function get_categories(): array {
		return array( 'techpino' );
	}

	public function get_keywords(): array {
		return array( 'search', 'live search', 'ajax search', 'product search', 'جستجو', 'سرچ', 'جستجوی زنده', 'تکپینو' );
	}

	public function get_style_depends(): array {
		return array( 'tpls' );
	}

	public function get_script_depends(): array {
		return array( 'tpls' );
	}

	/*
	|--------------------------------------------------------------------------
	| Control helpers
	|--------------------------------------------------------------------------
	*/

	private function text( string $id, string $label, string $default, array $args = array() ): void {
		$this->add_control(
			$id,
			array_merge(
				array(
					'label'       => $label,
					'type'        => Controls_Manager::TEXT,
					'default'     => $default,
					'label_block' => true,
				),
				$args
			)
		);
	}

	private function toggle( string $id, string $label, string $default = 'yes', array $args = array() ): void {
		$this->add_control(
			$id,
			array_merge(
				array(
					'label'        => $label,
					'type'         => Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => $default,
				),
				$args
			)
		);
	}

	private function select( string $id, string $label, array $options, string $default, array $args = array() ): void {
		$this->add_control(
			$id,
			array_merge(
				array(
					'label'   => $label,
					'type'    => Controls_Manager::SELECT,
					'options' => $options,
					'default' => $default,
				),
				$args
			)
		);
	}

	/** A colour stored in a CSS variable on the widget root. */
	private function color( string $id, string $label, string $var, array $args = array() ): void {
		$this->add_control(
			$id,
			array_merge(
				array(
					'label'     => $label,
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( self::ROOT => $var . ': {{VALUE}};' ),
				),
				$args
			)
		);
	}

	/** A length stored in a CSS variable on the widget root. */
	private function size( string $id, string $label, string $var, int $min, int $max, array $units = array( 'px' ), array $args = array() ): void {
		$range = array();
		foreach ( $units as $unit ) {
			$range[ $unit ] = 'px' === $unit ? array(
				'min' => $min,
				'max' => $max,
			) : array(
				'min' => 0,
				'max' => 100,
			);
		}
		$this->add_responsive_control(
			$id,
			array_merge(
				array(
					'label'      => $label,
					'type'       => Controls_Manager::SLIDER,
					'size_units' => $units,
					'range'      => $range,
					'selectors'  => array( self::ROOT => $var . ': {{SIZE}}{{UNIT}};' ),
				),
				$args
			)
		);
	}

	/** Four sides (padding, radius, border width) written straight to a property. */
	private function sides( string $id, string $label, string $selector, string $property, array $units = array( 'px' ) ): void {
		$this->add_responsive_control(
			$id,
			array(
				'label'      => $label,
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => $units,
				'selectors'  => array( $selector => $property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
	}

	private function typography( string $id, string $label, string $selector ): void {
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => $id,
				'label'    => $label,
				'selector' => $selector,
			)
		);
	}

	private function shadow( string $id, string $label, string $selector ): void {
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => $id,
				'label'    => $label,
				'selector' => $selector,
			)
		);
	}

	private function heading( string $id, string $label ): void {
		$this->add_control(
			$id,
			array(
				'label'     => $label,
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);
	}

	/** Start / centre / end (and optionally stretch), written to a CSS variable. */
	private function align( string $id, string $label, string $var, bool $stretch = false, array $args = array() ): void {
		$options = array(
			'start'  => array(
				'title' => 'ابتدا',
				'icon'  => 'eicon-h-align-right',
			),
			'center' => array(
				'title' => 'وسط',
				'icon'  => 'eicon-h-align-center',
			),
			'end'    => array(
				'title' => 'انتها',
				'icon'  => 'eicon-h-align-left',
			),
		);
		if ( $stretch ) {
			$options['stretch'] = array(
				'title' => 'تمام عرض',
				'icon'  => 'eicon-h-align-stretch',
			);
		}
		$this->add_responsive_control(
			$id,
			array_merge(
				array(
					'label'     => $label,
					'type'      => Controls_Manager::CHOOSE,
					'options'   => $options,
					'selectors' => array( self::ROOT => $var . ': {{VALUE}};' ),
				),
				$args
			)
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Controls
	|--------------------------------------------------------------------------
	*/

	protected function register_controls(): void {
		$this->content_field();
		$this->content_results();
		$this->content_card();
		$this->content_terms();
		$this->content_before();
		$this->content_empty();
		$this->content_search();
		$this->content_preview();

		$this->style_field();
		$this->style_submit();
		$this->style_panel();
		$this->style_popup();
		$this->style_headings();
		$this->style_chips();
		$this->style_cards();
		$this->style_image();
		$this->style_texts();
		$this->style_badges();
		$this->style_all();
		$this->style_empty();
	}

	private function content_field(): void {
		$this->start_controls_section( 'section_field', array( 'label' => 'فیلد جستجو' ) );
		$this->text( 'placeholder', 'متن راهنما', 'نام عطر، برند یا دسته‌بندی را بنویسید…' );
		$this->add_control(
			'icon',
			array(
				'label'       => 'آیکون',
				'type'        => Controls_Manager::ICONS,
				'description' => 'اگر خالی بماند، ذره‌بین خود ویجت نمایش داده می‌شود.',
			)
		);
		$this->add_control(
			'icon_position',
			array(
				'label'   => 'جای آیکون',
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'start',
				'toggle'  => false,
				'options' => array(
					'start' => array(
						'title' => 'ابتدای فیلد',
						'icon'  => 'eicon-h-align-right',
					),
					'end'   => array(
						'title' => 'انتهای فیلد',
						'icon'  => 'eicon-h-align-left',
					),
					'none'  => array(
						'title' => 'بدون آیکون',
						'icon'  => 'eicon-ban',
					),
				),
			)
		);
		$this->toggle( 'clear', 'دکمه‌ی پاک کردن متن' );
		$this->select(
			'submit',
			'دکمه‌ی جستجو',
			array(
				'none' => 'ندارد',
				'icon' => 'آیکون',
				'text' => 'متن',
				'both' => 'آیکون و متن',
			),
			'none',
			array( 'separator' => 'before' )
		);
		$this->text( 'submit_text', 'متن دکمه', 'جستجو', array( 'condition' => array( 'submit' => array( 'text', 'both' ) ) ) );
		$this->add_control(
			'submit_icon',
			array(
				'label'     => 'آیکون دکمه',
				'type'      => Controls_Manager::ICONS,
				'condition' => array( 'submit' => array( 'icon', 'both' ) ),
			)
		);
		$this->select(
			'enter',
			'با زدن Enter',
			array(
				'page'  => 'رفتن به صفحه‌ی همه‌ی نتایج',
				'first' => 'باز شدن اولین نتیجه',
			),
			'page',
			array( 'separator' => 'before' )
		);
		$this->toggle(
			'autofocus',
			'فوکوس خودکار در پاپ‌آپ / آف‌کنواس',
			'yes',
			array( 'description' => 'وقتی ویجت داخل آف‌کنواس یا پاپ‌آپ المنتور است، با باز شدن آن مکان‌نما در فیلد قرار می‌گیرد.' )
		);
		$this->text( 'label', 'برچسب دسترس‌پذیری', 'جستجوی محصولات' );
		$this->end_controls_section();
	}

	private function content_results(): void {
		$this->start_controls_section( 'section_results', array( 'label' => 'نمایش نتایج' ) );
		$this->select(
			'display',
			'محل نمایش',
			array(
				'dropdown' => 'بازشو زیر فیلد (روی محتوا)',
				'inline'   => 'داخل صفحه، زیر فیلد',
				'popup'    => 'پاپ‌آپ وسط صفحه (فقط دسکتاپ)',
			),
			'dropdown',
			array( 'description' => 'برای هدر «بازشو» یا «پاپ‌آپ» و برای آف‌کنواس موبایل «داخل صفحه» مناسب است. در حالت پاپ‌آپ، با کلیک روی فیلد یک پنجره وسط صفحه باز می‌شود؛ روی تبلت و موبایل (کمتر از ۱۰۲۵ پیکسل) خودکار «بازشو» می‌شود.' )
		);
		$this->toggle(
			'hide_siblings',
			'پنهان شدن بقیه‌ی محتوای همین ظرف',
			'yes',
			array(
				'condition'   => array( 'display' => 'inline' ),
				'description' => 'تا وقتی نتیجه‌ای نمایش داده می‌شود، عنصرهای کنار این ویجت (مثل بنرها و پرفروش‌ها) پنهان می‌شوند و با پاک شدن متن برمی‌گردند.',
			)
		);
		$this->select(
			'layout',
			'چیدمان کارت',
			array(
				'list' => 'افقی — تصویر کنار متن',
				'grid' => 'عمودی — تصویر بالای متن',
				'card' => 'کارت سایت — افقی، با حجم‌ها، قیمت و سبد',
			),
			'card',
			array(
				'separator'   => 'before',
				'description' => 'در «کارت سایت» تعداد ستون ثابت است: دسکتاپ و تبلت ۲ ستون، موبایل ۱ ستون.',
			)
		);
		$this->add_responsive_control(
			'columns',
			array(
				'label'          => 'تعداد ستون',
				'condition'      => array( 'layout!' => 'card' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => '2',
				'tablet_default' => '2',
				'mobile_default' => '1',
				'options'        => array(
					'1' => '۱',
					'2' => '۲',
					'3' => '۳',
					'4' => '۴',
					'5' => '۵',
					'6' => '۶',
				),
				'selectors'      => array( self::ROOT => '--tpls-cols: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'max_results',
			array(
				'label'   => 'حداکثر تعداد نتایج',
				'type'    => Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 60,
				'default' => 8,
			)
		);
		$this->add_control(
			'min_chars',
			array(
				'label'   => 'شروع جستجو از چند حرف',
				'type'    => Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 5,
				'default' => 2,
			)
		);
		$this->select(
			'oos',
			'محصولات ناموجود',
			array(
				'last' => 'نمایش در انتهای نتایج',
				'show' => 'نمایش به ترتیب مرتبط بودن',
				'hide' => 'نمایش داده نشوند',
			),
			'last'
		);
		$this->text( 'products_title', 'عنوان بخش محصولات', 'محصولات', array( 'separator' => 'before' ) );
		$this->toggle( 'show_count', 'نمایش تعداد نتایج کنار عنوان' );
		$this->select(
			'all',
			'دکمه‌ی همه‌ی نتایج',
			array(
				'more'   => 'وقتی نتایج بیشتری هست',
				'always' => 'همیشه',
				'never'  => 'هرگز',
			),
			'more',
			array( 'separator' => 'before' )
		);
		$this->text(
			'all_text',
			'متن دکمه',
			'مشاهده‌ی همه‌ی {n} نتیجه',
			array(
				'condition'   => array( 'all!' => 'never' ),
				'description' => '{n} با تعداد نتایج جایگزین می‌شود.',
			)
		);
		$this->toggle( 'all_icon', 'فلش کنار متن دکمه', 'yes', array( 'condition' => array( 'all!' => 'never' ) ) );
		$this->end_controls_section();
	}

	private function content_card(): void {
		$this->start_controls_section( 'section_card', array( 'label' => 'محتوای کارت محصول' ) );
		$this->toggle( 'show_image', 'تصویر' );
		$this->toggle( 'show_brand', 'برند' );
		$this->toggle( 'show_cat', 'دسته‌بندی', '' );
		$this->toggle( 'show_price', 'قیمت' );
		$this->toggle( 'highlight', 'پررنگ کردن عبارت جستجو در نام' );
		$this->select(
			'brand_position',
			'جای برند',
			array(
				'above' => 'بالای نام',
				'below' => 'زیر نام',
			),
			'above',
			array( 'condition' => array( 'show_brand' => 'yes' ) )
		);
		$this->text( 'price_from', 'پیشوند قیمت محصول متغیر', 'از', array( 'condition' => array( 'show_price' => 'yes' ) ) );
		$this->text(
			'currency',
			'واحد پول',
			'',
			array(
				'condition'   => array( 'show_price' => 'yes' ),
				'placeholder' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'description' => 'اگر خالی بماند، واحد پول ووکامرس نمایش داده می‌شود.',
			)
		);
		$this->select(
			'digits',
			'اعداد',
			array(
				'persian' => 'فارسی (مثل کارت محصولات سایت)',
				'latin'   => 'انگلیسی',
			),
			'persian'
		);
		$this->select(
			'sale_badge',
			'برچسب تخفیف',
			array(
				'percent' => 'درصد تخفیف',
				'text'    => 'متن',
				'none'    => 'ندارد',
			),
			'percent',
			array( 'separator' => 'before' )
		);
		$this->text( 'sale_text', 'متن برچسب تخفیف', 'تخفیف', array( 'condition' => array( 'sale_badge' => 'text' ) ) );
		$this->toggle( 'oos_badge', 'برچسب ناموجود' );
		$this->text( 'oos_text', 'متن برچسب ناموجود', 'ناموجود', array( 'condition' => array( 'oos_badge' => 'yes' ) ) );
		$this->end_controls_section();
	}

	private function content_terms(): void {
		$this->start_controls_section( 'section_terms', array( 'label' => 'برندها و دسته‌های مرتبط' ) );
		$this->toggle( 'show_terms', 'نمایش بالای محصولات' );
		$this->add_control(
			'terms_from',
			array(
				'label'     => 'از بین',
				'type'      => Controls_Manager::SELECT2,
				'multiple'  => true,
				'default'   => array( 'brand', 'cat' ),
				'options'   => array(
					'brand' => 'برندها',
					'cat'   => 'دسته‌بندی‌ها',
				),
				'condition' => array( 'show_terms' => 'yes' ),
			)
		);
		$this->add_control(
			'terms_max',
			array(
				'label'     => 'حداکثر تعداد',
				'type'      => Controls_Manager::NUMBER,
				'min'       => 1,
				'max'       => 20,
				'default'   => 4,
				'condition' => array( 'show_terms' => 'yes' ),
			)
		);
		$this->text( 'terms_title', 'عنوان', 'برندها و دسته‌ها', array( 'condition' => array( 'show_terms' => 'yes' ) ) );
		$this->toggle( 'terms_kind', 'نوشتن «برند» / «دسته» کنار نام', 'yes', array( 'condition' => array( 'show_terms' => 'yes' ) ) );
		$this->end_controls_section();
	}

	private function content_before(): void {
		$this->start_controls_section( 'section_before', array( 'label' => 'پیش از تایپ' ) );
		$this->add_control(
			'before_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => '<div style="line-height:1.9">بنر، جستجوهای پرطرفدار و محصولات پرفروش از منوی <b>جستجوی زنده</b> در پیشخوان وردپرس تنظیم می‌شوند.<br><a href="' . esc_url( TPLS_Admin::url() ) . '" target="_blank" rel="noopener">باز کردن تنظیمات ←</a></div>',
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);
		$this->toggle( 'show_banners', 'نمایش بنر' );
		$this->toggle( 'show_popular', 'نمایش جستجوهای پرطرفدار' );
		$this->toggle( 'show_featured', 'نمایش محصولات پرفروش' );
		$this->toggle( 'recent', 'جستجوهای اخیر بازدیدکننده' );
		$this->text( 'recent_title', 'عنوان جستجوهای اخیر', 'جستجوهای اخیر', array( 'condition' => array( 'recent' => 'yes' ) ) );
		$this->toggle( 'chip_icons', 'آیکون کنار برچسب‌ها' );
		$this->end_controls_section();
	}

	private function content_empty(): void {
		$this->start_controls_section( 'section_empty', array( 'label' => 'بدون نتیجه' ) );
		$this->add_control(
			'empty_icon',
			array(
				'label' => 'آیکون',
				'type'  => Controls_Manager::ICONS,
			)
		);
		$this->toggle( 'empty_icon_show', 'نمایش آیکون' );
		$this->text(
			'empty_title',
			'عنوان',
			'برای «{q}» محصولی پیدا نشد',
			array( 'description' => '{q} با عبارت جستجو جایگزین می‌شود.' )
		);
		$this->add_control(
			'empty_text',
			array(
				'label'   => 'توضیح',
				'type'    => Controls_Manager::TEXTAREA,
				'rows'    => 3,
				'default' => 'املای کلمه را بررسی کنید یا نام برند را امتحان کنید.',
			)
		);
		$this->toggle( 'empty_suggest', 'نمایش پیشنهادها زیر پیام' );
		$this->end_controls_section();
	}

	private function content_search(): void {
		$this->start_controls_section( 'section_search', array( 'label' => 'تنظیمات جستجو' ) );
		$this->add_control(
			'search_in',
			array(
				'label'       => 'جستجو علاوه بر نام محصول در',
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'default'     => array( 'brand', 'cat', 'tag', 'sku' ),
				'options'     => array(
					'brand' => 'برند',
					'cat'   => 'دسته‌بندی',
					'tag'   => 'برچسب',
					'sku'   => 'شناسه (SKU)',
					'attr'  => 'ویژگی‌ها (نوع رایحه، فصل، …)',
				),
			)
		);
		$this->add_control(
			'synonyms',
			array(
				'label'       => 'کلمه‌های هم‌معنی',
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 6,
				'placeholder' => "dior، دیور\nchanel، شنل، شانل\ntom ford، تام فورد",
				'description' => 'هر خط یک گروه است و کلمه‌ها با ویرگول جدا می‌شوند. جستجوی هر کلمه، بقیه‌ی گروه را هم پیدا می‌کند. «ی/ي»، «ک/ك»، نیم‌فاصله و اعداد فارسی خودکار یکسان می‌شوند. این فهرست به فهرست کلی (در «جستجوی زنده ← پیشرفته») اضافه می‌شود.',
			)
		);
		$this->end_controls_section();
	}

	private function content_preview(): void {
		$this->start_controls_section( 'section_preview', array( 'label' => 'پیش‌نمایش در ویرایشگر' ) );
		$this->select(
			'editor_preview',
			'نمایش',
			array(
				'off'     => 'بسته',
				'results' => 'باز — با نتیجه',
				'empty'   => 'باز — بدون نتیجه',
				'before'  => 'باز — پیش از تایپ',
			),
			'results',
			array( 'description' => 'فقط برای دیدن و تنظیم ظاهر در ویرایشگر است و روی سایت اثری ندارد.' )
		);
		$this->text( 'preview_query', 'عبارت نمونه', 'عطر', array( 'condition' => array( 'editor_preview' => array( 'results', 'empty' ) ) ) );
		$this->end_controls_section();
	}

	private function style_field(): void {
		$this->start_controls_section(
			'style_field',
			array(
				'label' => 'فیلد جستجو',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->size( 'field_height', 'ارتفاع', '--tpls-h', 32, 96 );
		$this->sides( 'field_padding', 'فاصله‌ی داخلی', self::FIELD, 'padding' );
		$this->sides( 'field_radius', 'گردی گوشه‌ها', self::FIELD, 'border-radius', array( 'px', '%' ) );
		$this->sides( 'field_border', 'ضخامت خط دور', self::FIELD, 'border-width' );
		$this->typography( 'input_typography', 'تایپوگرافی متن', self::INPUT );

		$this->start_controls_tabs( 'field_tabs' );
		$this->start_controls_tab( 'field_normal', array( 'label' => 'عادی' ) );
		$this->color( 'field_bg', 'پس‌زمینه', '--tpls-f-bg' );
		$this->color( 'field_color', 'رنگ متن', '--tpls-f-color' );
		$this->color( 'field_placeholder', 'رنگ متن راهنما', '--tpls-f-ph' );
		$this->color( 'field_line', 'رنگ خط دور', '--tpls-f-line' );
		$this->color( 'field_icon_color', 'رنگ آیکون', '--tpls-f-icon' );
		$this->shadow( 'field_shadow', 'سایه', self::FIELD );
		$this->end_controls_tab();
		$this->start_controls_tab( 'field_focus', array( 'label' => 'در حال تایپ' ) );
		$this->color( 'field_bg_f', 'پس‌زمینه', '--tpls-f-bg-f' );
		$this->color( 'field_color_f', 'رنگ متن', '--tpls-f-color-f' );
		$this->color( 'field_line_f', 'رنگ خط دور', '--tpls-f-line-f' );
		$this->color( 'field_icon_color_f', 'رنگ آیکون', '--tpls-f-icon-f' );
		$this->color( 'field_ring', 'رنگ هاله‌ی دور فیلد', '--tpls-f-ring' );
		$this->size( 'field_ring_size', 'ضخامت هاله', '--tpls-f-ring-size', 0, 12 );
		$this->shadow( 'field_shadow_f', 'سایه', '{{WRAPPER}} .tpls-field:focus-within' );
		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->heading( 'field_icon_heading', 'آیکون' );
		$this->size( 'field_icon_size', 'اندازه', '--tpls-f-icon-size', 10, 48 );
		$this->size( 'field_icon_gap', 'فاصله تا متن', '--tpls-f-gap', 0, 40 );

		$this->heading( 'clear_heading', 'دکمه‌ی پاک کردن' );
		$this->size( 'clear_size', 'اندازه', '--tpls-clear-size', 16, 48 );
		$this->color( 'clear_color', 'رنگ', '--tpls-clear-color' );
		$this->color( 'clear_bg', 'پس‌زمینه', '--tpls-clear-bg' );
		$this->end_controls_section();
	}

	private function style_submit(): void {
		$this->start_controls_section(
			'style_submit',
			array(
				'label'     => 'دکمه‌ی جستجو',
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'submit!' => 'none' ),
			)
		);
		$this->size( 'submit_height', 'ارتفاع', '--tpls-s-h', 24, 80 );
		$this->size( 'submit_min_width', 'حداقل عرض', '--tpls-s-w', 24, 200 );
		$this->size( 'submit_radius', 'گردی گوشه‌ها', '--tpls-s-radius', 0, 60, array( 'px', '%' ) );
		$this->size( 'submit_icon_size', 'اندازه‌ی آیکون', '--tpls-s-icon', 10, 40 );
		$this->typography( 'submit_typography', 'تایپوگرافی', '{{WRAPPER}} .tpls-submit' );
		$this->start_controls_tabs( 'submit_tabs' );
		$this->start_controls_tab( 'submit_normal', array( 'label' => 'عادی' ) );
		$this->color( 'submit_bg', 'پس‌زمینه', '--tpls-s-bg' );
		$this->color( 'submit_color', 'رنگ', '--tpls-s-color' );
		$this->end_controls_tab();
		$this->start_controls_tab( 'submit_hover', array( 'label' => 'هاور' ) );
		$this->color( 'submit_bg_h', 'پس‌زمینه', '--tpls-s-bg-h' );
		$this->color( 'submit_color_h', 'رنگ', '--tpls-s-color-h' );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->end_controls_section();
	}

	private function style_panel(): void {
		$this->start_controls_section(
			'style_panel',
			array(
				'label' => 'پنل نتایج',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->size(
			'panel_width',
			'عرض',
			'--tpls-p-w',
			200,
			1400,
			array( 'px', '%', 'vw' ),
			array( 'condition' => array( 'display' => 'dropdown' ) )
		);
		$this->align( 'panel_align', 'جای پنل وقتی از فیلد پهن‌تر یا باریک‌تر است', '--tpls-p-align', false, array( 'condition' => array( 'display' => 'dropdown' ) ) );
		$this->size( 'panel_offset', 'فاصله از فیلد', '--tpls-p-offset', 0, 60 );
		$this->size( 'panel_max_height', 'حداکثر ارتفاع', '--tpls-p-max-h', 160, 1200, array( 'px', 'vh' ) );
		$this->sides( 'panel_padding', 'فاصله‌ی داخلی', self::PANEL, 'padding' );
		$this->sides( 'panel_radius', 'گردی گوشه‌ها', self::PANEL, 'border-radius' );
		$this->color( 'panel_bg', 'پس‌زمینه', '--tpls-p-bg' );
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'panel_border',
				'selector' => self::PANEL,
			)
		);
		$this->shadow( 'panel_shadow', 'سایه', self::PANEL );
		$this->size( 'section_gap', 'فاصله‌ی بین بخش‌ها', '--tpls-section-gap', 0, 60 );
		$this->color( 'scrollbar', 'رنگ نوار اسکرول', '--tpls-scroll' );
		$this->color( 'skeleton', 'رنگ اسکلت بارگذاری', '--tpls-skeleton' );
		$this->add_control(
			'panel_z',
			array(
				'label'     => 'z-index',
				'type'      => Controls_Manager::NUMBER,
				'condition' => array( 'display' => 'dropdown' ),
				'selectors' => array( self::ROOT => '--tpls-z: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();
	}

	private function style_popup(): void {
		$this->start_controls_section(
			'style_popup',
			array(
				'label'     => 'پاپ‌آپ',
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'display' => 'popup' ),
			)
		);
		$this->size( 'popup_w', 'عرض', '--tpls-pop-w', 480, 1400 );
		$this->size( 'popup_h', 'ارتفاع', '--tpls-pop-h', 420, 1000 );
		$this->size( 'popup_radius', 'گردی گوشه‌ها', '--tpls-p-radius', 0, 48 );
		$this->color( 'popup_bg', 'پس‌زمینه', '--tpls-p-bg' );
		$this->color( 'popup_overlay', 'رنگ لایه‌ی پشت پاپ‌آپ', '--tpls-overlay' );
		$this->size( 'popup_blur', 'محو شدن صفحه‌ی پشت', '--tpls-overlay-blur', 0, 20 );
		$this->shadow( 'popup_shadow', 'سایه', '{{WRAPPER}} .tpls-dialog' );
		$this->end_controls_section();
	}

	private function style_headings(): void {
		$this->start_controls_section(
			'style_headings',
			array(
				'label' => 'عنوان بخش‌ها',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->color( 'heading_color', 'رنگ', '--tpls-heading' );
		$this->typography( 'heading_typography', 'تایپوگرافی', '{{WRAPPER}} .tpls-heading' );
		$this->size( 'heading_gap', 'فاصله تا محتوا', '--tpls-heading-gap', 0, 40 );
		$this->heading( 'count_heading', 'تعداد نتایج' );
		$this->color( 'count_bg', 'پس‌زمینه', '--tpls-count-bg' );
		$this->color( 'count_color', 'رنگ', '--tpls-count-color' );
		$this->heading( 'recent_clear_heading', 'دکمه‌ی پاک کردن جستجوهای اخیر' );
		$this->color( 'recent_clear_color', 'رنگ', '--tpls-recent-clear' );
		$this->end_controls_section();
	}

	private function style_chips(): void {
		$this->start_controls_section(
			'style_chips',
			array(
				'label' => 'برچسب‌ها (برند، دسته، پیشنهاد)',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->typography( 'chip_typography', 'تایپوگرافی', '{{WRAPPER}} .tpls-chip' );
		$this->sides( 'chip_padding', 'فاصله‌ی داخلی', '{{WRAPPER}} .tpls-chip', 'padding' );
		$this->size( 'chip_radius', 'گردی گوشه‌ها', '--tpls-chip-radius', 0, 40 );
		$this->size( 'chip_border', 'ضخامت خط دور', '--tpls-chip-bw', 0, 4 );
		$this->size( 'chip_gap', 'فاصله‌ی بین برچسب‌ها', '--tpls-chip-gap', 0, 30 );
		$this->size( 'chip_icon_size', 'اندازه‌ی آیکون', '--tpls-chip-icon', 8, 30 );
		$this->start_controls_tabs( 'chip_tabs' );
		$this->start_controls_tab( 'chip_normal', array( 'label' => 'عادی' ) );
		$this->color( 'chip_bg', 'پس‌زمینه', '--tpls-chip-bg' );
		$this->color( 'chip_color', 'رنگ متن', '--tpls-chip-color' );
		$this->color( 'chip_line', 'رنگ خط دور', '--tpls-chip-line' );
		$this->color( 'chip_kind', 'رنگ «برند / دسته»', '--tpls-chip-kind' );
		$this->end_controls_tab();
		$this->start_controls_tab( 'chip_hover', array( 'label' => 'هاور' ) );
		$this->color( 'chip_bg_h', 'پس‌زمینه', '--tpls-chip-bg-h' );
		$this->color( 'chip_color_h', 'رنگ متن', '--tpls-chip-color-h' );
		$this->color( 'chip_line_h', 'رنگ خط دور', '--tpls-chip-line-h' );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->end_controls_section();
	}

	private function style_cards(): void {
		$this->start_controls_section(
			'style_cards',
			array(
				'label' => 'کارت محصول',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->size( 'card_gap_x', 'فاصله‌ی ستون‌ها', '--tpls-gap-x', 0, 60 );
		$this->size( 'card_gap_y', 'فاصله‌ی ردیف‌ها', '--tpls-gap-y', 0, 60 );
		$this->size( 'card_inner_gap', 'فاصله‌ی تصویر و متن', '--tpls-card-gap', 0, 40 );
		$this->sides( 'card_padding', 'فاصله‌ی داخلی', self::CARD, 'padding' );
		$this->sides( 'card_radius', 'گردی گوشه‌ها', self::CARD, 'border-radius' );
		$this->add_responsive_control(
			'card_valign',
			array(
				'label'     => 'تراز عمودی متن',
				'type'      => Controls_Manager::CHOOSE,
				'condition' => array( 'layout' => 'list' ),
				'options'   => array(
					'flex-start' => array(
						'title' => 'بالا',
						'icon'  => 'eicon-v-align-top',
					),
					'center'     => array(
						'title' => 'وسط',
						'icon'  => 'eicon-v-align-middle',
					),
					'flex-end'   => array(
						'title' => 'پایین',
						'icon'  => 'eicon-v-align-bottom',
					),
				),
				'selectors' => array( self::ROOT => '--tpls-card-valign: {{VALUE}};' ),
			)
		);
		$this->align( 'card_text_align', 'تراز متن', '--tpls-card-align', false, array( 'condition' => array( 'layout' => 'grid' ) ) );
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => self::CARD,
			)
		);
		$this->start_controls_tabs( 'card_tabs' );
		$this->start_controls_tab( 'card_normal', array( 'label' => 'عادی' ) );
		$this->color( 'card_bg', 'پس‌زمینه', '--tpls-card-bg' );
		$this->shadow( 'card_shadow', 'سایه', self::CARD );
		$this->end_controls_tab();
		$this->start_controls_tab(
			'card_hover',
			array(
				'label' => 'هاور',
			)
		);
		$this->color( 'card_bg_h', 'پس‌زمینه', '--tpls-card-bg-h', array( 'description' => 'برای انتخاب با کلیدهای جهت‌نما هم استفاده می‌شود.' ) );
		$this->color( 'card_line_h', 'رنگ خط دور', '--tpls-card-line-h' );
		$this->color( 'card_title_h', 'رنگ نام محصول', '--tpls-title-h' );
		$this->add_control(
			'card_lift',
			array(
				'label'     => 'بالا آمدن کارت',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array(
						'min' => 0,
						'max' => 8,
					),
				),
				'selectors' => array( self::ROOT => '--tpls-card-lift: -{{SIZE}}px;' ),
			)
		);
		$this->shadow( 'card_shadow_h', 'سایه', '{{WRAPPER}} .tpls-card:hover, {{WRAPPER}} .tpls-item.is-active .tpls-card, {{WRAPPER}} .tpls-hcard:hover, {{WRAPPER}} .tpls-item.is-active .tpls-hcard' );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_control(
			'oos_opacity',
			array(
				'label'     => 'کم‌رنگی کارت ناموجود',
				'type'      => Controls_Manager::SLIDER,
				'separator' => 'before',
				'range'     => array(
					'px' => array(
						'min'  => 0.2,
						'max'  => 1,
						'step' => 0.05,
					),
				),
				'selectors' => array( self::ROOT => '--tpls-oos-opacity: {{SIZE}};' ),
			)
		);
		$this->end_controls_section();
	}

	private function style_image(): void {
		$this->start_controls_section(
			'style_image',
			array(
				'label'     => 'تصویر محصول',
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_image' => 'yes' ),
			)
		);
		$this->size( 'image_size', 'اندازه', '--tpls-img', 32, 200, array( 'px' ), array( 'condition' => array( 'layout' => 'list' ) ) );
		$this->select(
			'image_ratio',
			'نسبت ابعاد',
			array(
				''    => 'پیش‌فرض (مربع)',
				'1/1' => '۱:۱',
				'4/5' => '۴:۵',
				'3/4' => '۳:۴',
				'2/3' => '۲:۳',
				'4/3' => '۴:۳',
			),
			'',
			array( 'selectors' => array( self::ROOT => '--tpls-ratio: {{VALUE}};' ) )
		);
		$this->select(
			'image_fit',
			'جای‌گیری تصویر',
			array(
				''        => 'کامل دیده شود (contain)',
				'cover'   => 'کادر را پر کند (cover)',
			),
			'',
			array( 'selectors' => array( self::ROOT => '--tpls-fit: {{VALUE}};' ) )
		);
		$this->size( 'image_radius', 'گردی گوشه‌ها', '--tpls-img-radius', 0, 100, array( 'px', '%' ) );
		$this->size( 'image_padding', 'فاصله‌ی داخلی', '--tpls-img-pad', 0, 30 );
		$this->color( 'image_bg', 'پس‌زمینه', '--tpls-img-bg' );
		$this->toggle(
			'image_blend',
			'محو کردن پس‌زمینه‌ی سفید عکس',
			'yes',
			array(
				'description' => 'پس‌زمینه‌ی سفید عکس‌های عطر با رنگ پس‌زمینه‌ی کادر یکی می‌شود.',
				'selectors'   => array( self::ROOT => '--tpls-blend: multiply;' ),
			)
		);
		$this->toggle(
			'image_zoom',
			'بزرگ شدن تصویر در هاور',
			'yes',
			array( 'selectors' => array( self::ROOT => '--tpls-zoom: 1.06;' ) )
		);
		$this->end_controls_section();
	}

	private function style_texts(): void {
		$this->start_controls_section(
			'style_texts',
			array(
				'label' => 'متن‌های کارت',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->color( 'title_color', 'رنگ نام محصول', '--tpls-title' );
		$this->typography( 'title_typography', 'تایپوگرافی نام محصول', '{{WRAPPER}} .tpls-card__title, {{WRAPPER}} .tpls-hcard__title' );
		$this->add_control(
			'title_lines',
			array(
				'label'     => 'حداکثر خط‌های نام',
				'type'      => Controls_Manager::NUMBER,
				'min'       => 1,
				'max'       => 4,
				'selectors' => array( self::ROOT => '--tpls-title-lines: {{VALUE}};' ),
			)
		);

		$this->heading( 'mark_heading', 'عبارت جستجو در نام' );
		$this->color( 'mark_color', 'رنگ', '--tpls-mark' );
		$this->color( 'mark_bg', 'پس‌زمینه', '--tpls-mark-bg' );
		$this->select(
			'mark_weight',
			'ضخامت',
			array(
				''    => 'پیش‌فرض',
				'400' => 'معمولی',
				'600' => 'نیمه‌ضخیم',
				'700' => 'ضخیم',
				'800' => 'خیلی ضخیم',
			),
			'',
			array( 'selectors' => array( self::ROOT => '--tpls-mark-weight: {{VALUE}};' ) )
		);

		$this->heading( 'en_heading', 'نام انگلیسی (کارت سایت)' );
		$this->color( 'en_color', 'رنگ', '--tpls-hc-en' );
		$this->typography( 'en_typography', 'تایپوگرافی', '{{WRAPPER}} .tpls-hcard__en' );

		$this->heading( 'sizes_heading', 'حجم‌ها (کارت سایت)' );
		$this->color( 'size_on_bg', 'پس‌زمینه‌ی حجم انتخاب‌شده', '--tpls-size-on' );
		$this->color( 'size_on_color', 'رنگ متن حجم انتخاب‌شده', '--tpls-size-on-color' );
		$this->color( 'size_line', 'خط دور حجم‌ها', '--tpls-size-line' );
		$this->color( 'size_color', 'رنگ متن حجم‌ها', '--tpls-size-color' );

		$this->heading( 'brand_heading', 'برند' );
		$this->color( 'brand_color', 'رنگ', '--tpls-brand' );
		$this->typography( 'brand_typography', 'تایپوگرافی', '{{WRAPPER}} .tpls-card__brand' );

		$this->heading( 'cat_heading', 'دسته‌بندی' );
		$this->color( 'cat_color', 'رنگ', '--tpls-cat' );
		$this->typography( 'cat_typography', 'تایپوگرافی', '{{WRAPPER}} .tpls-card__cat' );

		$this->heading( 'price_heading', 'قیمت' );
		$this->color( 'price_color', 'رنگ مبلغ', '--tpls-price' );
		$this->typography( 'price_typography', 'تایپوگرافی مبلغ', '{{WRAPPER}} .tpls-price__amount' );
		$this->color( 'price_from_color', 'رنگ «از»', '--tpls-price-from' );
		$this->color( 'price_cur_color', 'رنگ واحد پول', '--tpls-price-cur' );
		$this->typography( 'price_small_typography', 'تایپوگرافی «از» و واحد پول', '{{WRAPPER}} .tpls-price__from, {{WRAPPER}} .tpls-price__cur' );
		$this->color( 'price_old_color', 'رنگ قیمت قبل از تخفیف', '--tpls-price-old' );
		$this->size( 'text_gap', 'فاصله‌ی خط‌های متن', '--tpls-text-gap', 0, 20 );
		$this->end_controls_section();
	}

	private function style_badges(): void {
		$this->start_controls_section(
			'style_badges',
			array(
				'label' => 'برچسب تخفیف و ناموجود',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->typography( 'badge_typography', 'تایپوگرافی', '{{WRAPPER}} .tpls-badge' );
		$this->size( 'badge_radius', 'گردی گوشه‌ها', '--tpls-badge-radius', 0, 30 );
		$this->size( 'badge_offset', 'فاصله از گوشه‌ی تصویر', '--tpls-badge-offset', -10, 30 );
		$this->heading( 'sale_heading', 'تخفیف' );
		$this->color( 'sale_bg', 'پس‌زمینه', '--tpls-sale-bg' );
		$this->color( 'sale_color', 'رنگ', '--tpls-sale-color' );
		$this->heading( 'oos_heading', 'ناموجود' );
		$this->color( 'oos_bg', 'پس‌زمینه', '--tpls-oos-bg' );
		$this->color( 'oos_color', 'رنگ', '--tpls-oos-color' );
		$this->end_controls_section();
	}

	private function style_all(): void {
		$this->start_controls_section(
			'style_all',
			array(
				'label'     => 'دکمه‌ی همه‌ی نتایج',
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'all!' => 'never' ),
			)
		);
		$this->align( 'all_align', 'تراز', '--tpls-all-align', true );
		$this->typography( 'all_typography', 'تایپوگرافی', '{{WRAPPER}} .tpls-all' );
		$this->sides( 'all_padding', 'فاصله‌ی داخلی', '{{WRAPPER}} .tpls-all', 'padding' );
		$this->size( 'all_radius', 'گردی گوشه‌ها', '--tpls-all-radius', 0, 40 );
		$this->size( 'all_border', 'ضخامت خط دور', '--tpls-all-bw', 0, 4 );
		$this->start_controls_tabs( 'all_tabs' );
		$this->start_controls_tab( 'all_normal', array( 'label' => 'عادی' ) );
		$this->color( 'all_bg', 'پس‌زمینه', '--tpls-all-bg' );
		$this->color( 'all_color', 'رنگ متن', '--tpls-all-color' );
		$this->color( 'all_line', 'رنگ خط دور', '--tpls-all-line' );
		$this->end_controls_tab();
		$this->start_controls_tab( 'all_hover', array( 'label' => 'هاور' ) );
		$this->color( 'all_bg_h', 'پس‌زمینه', '--tpls-all-bg-h' );
		$this->color( 'all_color_h', 'رنگ متن', '--tpls-all-color-h' );
		$this->color( 'all_line_h', 'رنگ خط دور', '--tpls-all-line-h' );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->end_controls_section();
	}

	private function style_empty(): void {
		$this->start_controls_section(
			'style_empty',
			array(
				'label' => 'بدون نتیجه',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->align( 'empty_align', 'تراز', '--tpls-empty-align' );
		$this->sides( 'empty_padding', 'فاصله‌ی داخلی', '{{WRAPPER}} .tpls-empty', 'padding' );
		$this->size( 'empty_icon_size', 'اندازه‌ی دایره‌ی آیکون', '--tpls-empty-size', 32, 160, array( 'px' ), array( 'condition' => array( 'empty_icon_show' => 'yes' ) ) );
		$this->color( 'empty_icon_color', 'رنگ آیکون', '--tpls-empty-color', array( 'condition' => array( 'empty_icon_show' => 'yes' ) ) );
		$this->color( 'empty_icon_bg', 'پس‌زمینه‌ی آیکون', '--tpls-empty-bg', array( 'condition' => array( 'empty_icon_show' => 'yes' ) ) );
		$this->color( 'empty_title_color', 'رنگ عنوان', '--tpls-empty-title' );
		$this->typography( 'empty_title_typography', 'تایپوگرافی عنوان', '{{WRAPPER}} .tpls-empty__title' );
		$this->color( 'empty_text_color', 'رنگ توضیح', '--tpls-empty-text' );
		$this->typography( 'empty_text_typography', 'تایپوگرافی توضیح', '{{WRAPPER}} .tpls-empty__text' );
		$this->end_controls_section();
	}

	/*
	|--------------------------------------------------------------------------
	| Output
	|--------------------------------------------------------------------------
	*/

	private function icon_html( $icon, string $fallback = '' ): string {
		if ( empty( $icon['value'] ) ) {
			return $fallback ? TPLS_Plugin::icon( $fallback ) : '';
		}
		ob_start();
		Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
		return (string) ob_get_clean();
	}

	/** Text with {q} turned into a slot the script fills with the visitor's words. */
	private function with_query( string $text ): string {
		return implode( '<span class="tpls-q"></span>', array_map( 'esc_html', explode( '{q}', $text ) ) );
	}

	/**
	 * Banners from the admin screen, or — for widgets set up before it existed — the ones saved in the widget.
	 *
	 * @return array<int, array{desktop: string, mobile: string, link: string, blank: bool, alt: string}>
	 */
	private function banner_list(): array {
		$list = TPLS_Settings::banners();
		if ( $list ) {
			return $list;
		}
		$raw = (array) $this->get_data( 'settings' );
		foreach ( (array) ( $raw['banners'] ?? array() ) as $item ) {
			$url = isset( $item['image']['url'] ) ? (string) $item['image']['url'] : '';
			if ( '' === $url ) {
				continue;
			}
			$list[] = array(
				'desktop' => $url,
				'mobile'  => $url,
				'link'    => isset( $item['link']['url'] ) ? (string) $item['link']['url'] : '',
				'blank'   => ! empty( $item['link']['is_external'] ),
				'alt'     => '',
			);
		}
		return $list;
	}

	/** @return array<int, array{text: string, link: string}> */
	private function popular_list(): array {
		$list = TPLS_Settings::popular();
		if ( $list ) {
			return $list;
		}
		$raw = (array) $this->get_data( 'settings' );
		foreach ( (array) ( $raw['suggestions'] ?? array() ) as $item ) {
			$text = isset( $item['text'] ) ? trim( (string) $item['text'] ) : '';
			if ( '' !== $text ) {
				$list[] = array(
					'text' => $text,
					'link' => '',
				);
			}
		}
		return $list;
	}

	/** Banner images as <picture>: the mobile image below 768px, the desktop one above. */
	private function banners(): string {
		$html = '';
		foreach ( $this->banner_list() as $b ) {
			$img = '<picture class="tpls-banner__pic">'
				. ( $b['mobile'] !== $b['desktop'] ? '<source media="(max-width: 767px)" srcset="' . esc_url( $b['mobile'] ) . '">' : '' )
				. '<img class="tpls-banner__img" src="' . esc_url( $b['desktop'] ) . '" alt="' . esc_attr( $b['alt'] ) . '" loading="lazy" decoding="async"></picture>';
			if ( '' !== $b['link'] ) {
				$html .= '<a class="tpls-banner" href="' . esc_url( $b['link'] ) . '"' . ( $b['blank'] ? ' target="_blank" rel="noopener"' : '' ) . '>' . $img . '</a>';
			} else {
				$html .= '<span class="tpls-banner">' . $img . '</span>';
			}
		}
		return $html;
	}

	private function chips(): string {
		$html = '';
		foreach ( $this->popular_list() as $item ) {
			$inner = '<span class="tpls-chip__icon">' . TPLS_Plugin::icon( 'trend' ) . '</span><span class="tpls-chip__text">' . esc_html( $item['text'] ) . '</span>';
			if ( '' !== $item['link'] ) {
				$html .= '<a class="tpls-chip tpls-chip--link" href="' . esc_url( $item['link'] ) . '">' . $inner . '</a>';
			} else {
				$html .= '<button type="button" class="tpls-chip" data-q="' . esc_attr( $item['text'] ) . '">' . $inner . '</button>';
			}
		}
		return $html;
	}

	/** The inside of the submit button (icon and/or text). */
	private function submit_inner( array $s ): string {
		$html = '';
		if ( in_array( $s['submit'], array( 'icon', 'both' ), true ) ) {
			$html .= '<span class="tpls-submit__icon">' . $this->icon_html( $s['submit_icon'], 'search' ) . '</span>';
		}
		if ( in_array( $s['submit'], array( 'text', 'both' ), true ) ) {
			$html .= '<span class="tpls-submit__text">' . esc_html( $s['submit_text'] ) . '</span>';
		}
		return $html;
	}

	private function form( array $s, bool $editing, string $panel_id ): string {
		ob_start();
		echo '<form class="tpls-form" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '">';
		echo '<div class="tpls-field">';
		if ( 'none' !== $s['icon_position'] ) {
			echo '<span class="tpls-field__icon">' . $this->icon_html( $s['icon'], 'search' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		printf(
			'<input class="tpls-input" type="search" name="s" value="%1$s" placeholder="%2$s" aria-label="%3$s" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" enterkeyhint="search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="%4$s">',
			esc_attr( $editing && in_array( $s['editor_preview'], array( 'results', 'empty' ), true ) ? $s['preview_query'] : get_search_query() ),
			esc_attr( $s['placeholder'] ),
			esc_attr( $s['label'] ),
			esc_attr( $panel_id )
		);
		echo '<input type="hidden" name="post_type" value="product">';
		if ( 'yes' === $s['clear'] ) {
			echo '<button type="button" class="tpls-clear" aria-label="' . esc_attr__( 'پاک کردن', 'techpino-live-search' ) . '" hidden>' . TPLS_Plugin::icon( 'close' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( 'none' !== $s['submit'] ) {
			echo '<button type="submit" class="tpls-submit tpls-submit--' . esc_attr( $s['submit'] ) . '" aria-label="' . esc_attr( $s['submit_text'] ? $s['submit_text'] : 'جستجو' ) . '">' . $this->submit_inner( $s ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div></form>';
		return (string) ob_get_clean();
	}

	/** The panel: what shows before typing, the results, the loading skeleton and the empty state. */
	private function panel( array $s, string $panel_id, string $layout, string $chips, string $banners, bool $featured ): string {
		$g = TPLS_Settings::get();
		ob_start();
		echo '<div class="tpls-pop" hidden><div class="tpls-panel" id="' . esc_attr( $panel_id ) . '">';

		echo '<div class="tpls-before">';
		if ( '' !== $banners && 'yes' === $s['show_banners'] ) {
			$count = substr_count( $banners, 'class="tpls-banner"' );
			echo '<div class="tpls-block tpls-banners tpls-banners--pos-' . esc_attr( $g['banner_pos'] ) . ' tpls-banners--cols-' . (int) min( $count, (int) $g['banner_cols'] ) . '">' . $banners . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( 'yes' === $s['recent'] ) {
			echo '<div class="tpls-block tpls-recent" hidden><div class="tpls-head"><span class="tpls-heading">' . esc_html( $s['recent_title'] ) . '</span>'
				. '<button type="button" class="tpls-recent__clear">' . esc_html__( 'پاک کردن', 'techpino-live-search' ) . '</button></div>'
				. '<div class="tpls-chips"></div></div>';
		}
		if ( $chips && 'yes' === $s['show_popular'] ) {
			echo '<div class="tpls-block tpls-suggest">' . ( $g['popular_title'] ? '<div class="tpls-head"><span class="tpls-heading">' . esc_html( $g['popular_title'] ) . '</span></div>' : '' )
				. '<div class="tpls-chips">' . $chips . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( $featured ) {
			echo '<div class="tpls-block tpls-featured">' . ( $g['featured_title'] ? '<div class="tpls-head"><span class="tpls-heading">' . esc_html( $g['featured_title'] ) . '</span></div>' : '' )
				. '<ul class="tpls-list tpls-list--featured" role="list">' . str_repeat( '<li class="tpls-item"><span class="tpls-skel"><span class="tpls-skel__img"></span><span class="tpls-skel__lines"><span></span><span></span></span></span></li>', 2 ) . '</ul></div>';
		}
		echo '</div>';

		echo '<div class="tpls-results"></div>';

		echo '<div class="tpls-loading" aria-hidden="true">' . str_repeat( '<span class="tpls-skel"><span class="tpls-skel__img"></span><span class="tpls-skel__lines"><span></span><span></span></span></span>', 'grid' === $layout ? 4 : 3 ) . '</div>';

		echo '<div class="tpls-empty" role="status">';
		if ( 'yes' === $s['empty_icon_show'] ) {
			echo '<span class="tpls-empty__icon">' . $this->icon_html( $s['empty_icon'], 'empty' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<p class="tpls-empty__title">' . $this->with_query( (string) $s['empty_title'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( '' !== trim( (string) $s['empty_text'] ) ) {
			echo '<p class="tpls-empty__text">' . nl2br( esc_html( $s['empty_text'] ) ) . '</p>';
		}
		if ( $chips && 'yes' === $s['empty_suggest'] && 'yes' === $s['show_popular'] ) {
			echo '<div class="tpls-chips">' . $chips . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';

		echo '</div></div>';
		return (string) ob_get_clean();
	}

	protected function render(): void {
		$s       = $this->get_settings_for_display();
		$g       = TPLS_Settings::get();
		$id      = $this->get_id();
		$editing = \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode();
		$display = in_array( $s['display'], array( 'inline', 'popup' ), true ) ? $s['display'] : 'dropdown';
		$inline  = 'inline' === $display;
		$popup   = 'popup' === $display;
		$layout  = in_array( $s['layout'], array( 'grid', 'list' ), true ) ? $s['layout'] : 'card';
		$chips   = $this->chips();
		$banners = $this->banners();
		$feature = 'yes' === $s['show_featured'] && ! empty( $g['featured_show'] );

		$options = array(
			'id'        => $id,
			'min'       => max( 1, (int) $s['min_chars'] ),
			'max'       => max( 1, (int) $s['max_results'] ),
			'inline'    => $inline,
			'popup'     => $popup,
			'hide'      => $inline && 'yes' === $s['hide_siblings'],
			'layout'    => $layout,
			'featured'  => $feature,
			'fields'    => array_values( (array) $s['search_in'] ),
			'syn'       => (string) $s['synonyms'],
			'oos'       => $s['oos'],
			'enter'     => $s['enter'],
			'focus'     => 'yes' === $s['autofocus'],
			'recent'    => 'yes' === $s['recent'],
			'icons'     => 'yes' === $s['chip_icons'],
			'image'     => 'yes' === $s['show_image'],
			'brand'     => 'yes' === $s['show_brand'] ? $s['brand_position'] : '',
			'cat'       => 'yes' === $s['show_cat'],
			'price'     => 'yes' === $s['show_price'],
			'mark'      => 'yes' === $s['highlight'],
			'from'      => (string) $s['price_from'],
			'cur'       => (string) $s['currency'],
			'fa'        => 'persian' === $s['digits'],
			'sale'      => $s['sale_badge'],
			'saleText'  => (string) $s['sale_text'],
			'oosText'   => 'yes' === $s['oos_badge'] ? (string) $s['oos_text'] : '',
			'terms'     => 'yes' === $s['show_terms'] ? array_values( (array) $s['terms_from'] ) : array(),
			'termsMax'  => max( 1, (int) $s['terms_max'] ),
			'termsText' => (string) $s['terms_title'],
			'kind'      => 'yes' === $s['terms_kind'],
			'title'     => (string) $s['products_title'],
			'count'     => 'yes' === $s['show_count'],
			'all'       => $s['all'],
			'allText'   => (string) $s['all_text'],
			'allIcon'   => 'yes' === $s['all_icon'],
			'preview'   => $editing ? $s['editor_preview'] : '',
			'previewQ'  => $editing ? (string) $s['preview_query'] : '',
		);

		$classes = array( 'tpls', 'tpls--' . $display, 'tpls--' . $layout );
		if ( 'none' !== $s['icon_position'] ) {
			$classes[] = 'tpls--icon-' . $s['icon_position'];
		}
		$panel_id = 'tpls-panel-' . $id;
		$form     = $this->form( $s, $editing, $panel_id );
		$panel    = $this->panel( $s, $panel_id, $layout, $chips, $banners, $feature );
		$live     = '<div class="tpls-live screen-reader-text" aria-live="polite"></div>';

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped where it is built.
		printf( '<div class="%s" data-tpls="%s">', esc_attr( implode( ' ', $classes ) ), esc_attr( wp_json_encode( $options, JSON_UNESCAPED_UNICODE ) ) );

		if ( $popup ) {
			// The trigger looks like the field; the real field lives in the dialog (desktop) or in place (smaller screens).
			echo '<button type="button" class="tpls-trigger" aria-haspopup="dialog" aria-label="' . esc_attr( $s['label'] ) . '"><span class="tpls-field">';
			if ( 'none' !== $s['icon_position'] ) {
				echo '<span class="tpls-field__icon">' . $this->icon_html( $s['icon'], 'search' ) . '</span>';
			}
			echo '<span class="tpls-trigger__text">' . esc_html( $s['placeholder'] ) . '</span>';
			if ( 'none' !== $s['submit'] ) {
				echo '<span class="tpls-submit tpls-submit--' . esc_attr( $s['submit'] ) . '">' . $this->submit_inner( $s ) . '</span>';
			}
			echo '</span></button>';
			echo '<dialog class="tpls-modal" aria-label="' . esc_attr( $s['label'] ) . '"><div class="tpls-dialog">';
			echo '<div class="tpls-dialog__head">' . $form . '<button type="button" class="tpls-modal__close" aria-label="' . esc_attr__( 'بستن', 'techpino-live-search' ) . '">' . TPLS_Plugin::icon( 'close' ) . '</button></div>';
			echo $panel . $live;
			echo '</div></dialog>';
		} else {
			echo $form . $panel . $live;
		}

		echo '</div>';
		// phpcs:enable
	}
}
