<?php
/**
 * Elementor widget: «سبد خرید کشویی (تکپینو)».
 *
 * The drawer is moved to the end of <body> when it opens, so its style controls cannot
 * use {{WRAPPER}}; they target `.tpmc-drawer.tpmc--{{ID}}` instead, mostly through CSS
 * variables that assets/css/mini-cart.css reads.
 *
 * @package TechpinoMiniCart
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;

class TPMC_Widget extends \Elementor\Widget_Base {

	private const TRIGGER = '{{WRAPPER}} .tpmc';
	private const DRAWER  = '.tpmc-drawer.tpmc--{{ID}}';
	private const PANEL   = '.tpmc-drawer.tpmc--{{ID}} .tpmc-panel';

	public function get_name(): string {
		return 'tp-mini-cart';
	}

	public function get_title(): string {
		return 'سبد خرید کشویی (تکپینو)';
	}

	public function get_icon(): string {
		return 'eicon-cart';
	}

	public function get_categories(): array {
		return array( 'techpino' );
	}

	public function get_keywords(): array {
		return array( 'cart', 'mini cart', 'woocommerce', 'سبد', 'سبد خرید', 'تکپینو' );
	}

	public function get_style_depends(): array {
		return array( 'tpmc' );
	}

	public function get_script_depends(): array {
		return array( 'tpmc' );
	}

	/** The counter depends on the visitor's cart: never served from Elementor's element cache. */
	protected function is_dynamic_content(): bool {
		return true;
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

	/** A colour stored in a CSS variable. */
	private function color( string $id, string $label, string $selector, string $var ): void {
		$this->add_control(
			$id,
			array(
				'label'     => $label,
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $selector => $var . ': {{VALUE}};' ),
			)
		);
	}

	/** A length stored in a CSS variable. */
	private function size( string $id, string $label, string $selector, string $var, int $min, int $max, array $units = array( 'px' ) ): void {
		$this->add_responsive_control(
			$id,
			array(
				'label'      => $label,
				'type'       => Controls_Manager::SLIDER,
				'size_units' => $units,
				'range'      => array(
					'px' => array(
						'min' => $min,
						'max' => $max,
					),
				),
				'selectors'  => array( $selector => $var . ': {{SIZE}}{{UNIT}};' ),
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

	/*
	|--------------------------------------------------------------------------
	| Controls
	|--------------------------------------------------------------------------
	*/

	protected function register_controls(): void {
		$this->content_trigger();
		$this->content_panel();
		$this->content_empty();
		$this->style_trigger();
		$this->style_count();
		$this->style_panel();
		$this->style_head();
		$this->style_items();
		$this->style_foot();
		$this->style_empty();
	}

	private function content_trigger(): void {
		$this->start_controls_section( 'section_trigger', array( 'label' => 'دکمه‌ی سبد' ) );
		$this->add_control(
			'type',
			array(
				'label'   => 'نوع نمایش',
				'type'    => Controls_Manager::SELECT,
				'default' => 'icon',
				'options' => array(
					'icon'   => 'فقط آیکون',
					'button' => 'آیکون و متن',
				),
			)
		);
		$this->text( 'text', 'متن دکمه', 'سبد خرید', array( 'condition' => array( 'type' => 'button' ) ) );
		$this->add_control(
			'icon',
			array(
				'label'       => 'آیکون',
				'type'        => Controls_Manager::ICONS,
				'description' => 'اگر خالی بماند، آیکون ساک خرید خود ویجت نمایش داده می‌شود.',
			)
		);
		$this->add_control(
			'count',
			array(
				'label'   => 'شمارنده',
				'type'    => Controls_Manager::SELECT,
				'default' => 'quantity',
				'options' => array(
					'quantity' => 'مجموع تعداد کالاها',
					'lines'    => 'تعداد ردیف‌های سبد',
					'none'     => 'بدون شمارنده',
				),
			)
		);
		$this->toggle( 'hide_zero', 'پنهان شدن شمارنده در سبد خالی', '', array( 'condition' => array( 'count!' => 'none' ) ) );
		$this->text( 'label', 'برچسب دسترس‌پذیری', 'سبد خرید' );
		$this->end_controls_section();
	}

	private function content_panel(): void {
		$this->start_controls_section( 'section_panel', array( 'label' => 'پنل سبد خرید' ) );
		$this->add_control(
			'editor_preview',
			array(
				'label'       => 'پیش‌نمایش در ویرایشگر',
				'type'        => Controls_Manager::SELECT,
				'default'     => 'closed',
				'options'     => array(
					'closed' => 'بسته',
					'items'  => 'باز — با کالا',
					'empty'  => 'باز — سبد خالی',
				),
				'description' => 'فقط برای دیدن و تنظیم ظاهر پنل در ویرایشگر است و روی سایت اثری ندارد. اگر سبد خودتان خالی باشد، دو محصول نمونه نشان داده می‌شود.',
			)
		);
		$this->add_control(
			'side',
			array(
				'label'     => 'سمت باز شدن',
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'left',
				'toggle'    => false,
				'separator' => 'before',
				'options'   => array(
					'left'  => array(
						'title' => 'چپ',
						'icon'  => 'eicon-h-align-left',
					),
					'right' => array(
						'title' => 'راست',
						'icon'  => 'eicon-h-align-right',
					),
				),
			)
		);
		$this->toggle( 'auto_open', 'باز شدن بعد از افزودن به سبد' );
		$this->text( 'title', 'عنوان پنل', 'سبد خرید' );
		$this->toggle( 'chip', 'نمایش تعداد کنار عنوان' );
		$this->toggle( 'show_unit', 'نمایش قیمت واحد (وقتی تعداد بیش از یک است)' );
		$this->text( 'total_label', 'عنوان جمع', 'جمع سبد خرید', array( 'separator' => 'before' ) );
		$this->text( 'note', 'توضیح زیر جمع', '', array( 'placeholder' => 'مثلاً: هزینه‌ی ارسال در مرحله‌ی بعد محاسبه می‌شود.' ) );
		$this->text( 'checkout_text', 'متن دکمه‌ی تسویه', 'تکمیل سفارش' );
		$this->text( 'cart_text', 'متن دکمه‌ی سبد', 'مشاهده سبد', array( 'description' => 'اگر خالی بماند، این دکمه نمایش داده نمی‌شود.' ) );
		$this->add_control(
			'layout',
			array(
				'label'   => 'چیدمان دکمه‌ها',
				'type'    => Controls_Manager::SELECT,
				'default' => 'row',
				'options' => array(
					'row'   => 'کنار هم',
					'stack' => 'زیر هم',
				),
			)
		);
		$this->end_controls_section();
	}

	private function content_empty(): void {
		$this->start_controls_section( 'section_empty', array( 'label' => 'سبد خالی' ) );
		$this->add_control(
			'empty_icon',
			array(
				'label' => 'آیکون',
				'type'  => Controls_Manager::ICONS,
			)
		);
		$this->text( 'empty_title', 'عنوان', 'سبد خریدت خالیه' );
		$this->add_control(
			'empty_text',
			array(
				'label'   => 'توضیح',
				'type'    => Controls_Manager::TEXTAREA,
				'rows'    => 3,
				'default' => 'هنوز عطری انتخاب نکردی؛ از بین محصولات، رایحه‌ی خودت رو پیدا کن.',
			)
		);
		$this->text( 'empty_button', 'متن دکمه', 'مشاهده محصولات', array( 'description' => 'اگر خالی بماند، دکمه نمایش داده نمی‌شود.' ) );
		$this->add_control(
			'empty_link',
			array(
				'label'       => 'لینک دکمه',
				'type'        => Controls_Manager::URL,
				'options'     => array( 'url' ),
				'placeholder' => 'پیش‌فرض: صفحه‌ی فروشگاه',
				'dynamic'     => array( 'active' => true ),
			)
		);
		$this->end_controls_section();
	}

	private function style_trigger(): void {
		$this->start_controls_section(
			'style_trigger',
			array(
				'label' => 'دکمه‌ی سبد',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'label'     => 'تراز',
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => 'ابتدا',
						'icon'  => 'eicon-h-align-right',
					),
					'center'     => array(
						'title' => 'وسط',
						'icon'  => 'eicon-h-align-center',
					),
					'flex-end'   => array(
						'title' => 'انتها',
						'icon'  => 'eicon-h-align-left',
					),
				),
				'selectors' => array( self::TRIGGER => '--tpmc-align: {{VALUE}};' ),
			)
		);
		$this->size( 'trigger_size', 'اندازه‌ی دکمه', self::TRIGGER, '--tpmc-t-size', 28, 80 );
		$this->size( 'trigger_icon', 'اندازه‌ی آیکون', self::TRIGGER, '--tpmc-t-icon', 12, 48 );
		$this->size( 'trigger_radius', 'گردی گوشه‌ها', self::TRIGGER, '--tpmc-t-radius', 0, 60, array( 'px', '%' ) );
		$this->size( 'trigger_border', 'ضخامت خط دور', self::TRIGGER, '--tpmc-t-bw', 0, 6 );
		$this->size( 'trigger_gap', 'فاصله‌ی آیکون و متن', self::TRIGGER, '--tpmc-t-gap', 0, 30 );
		$this->typography( 'trigger_typography', 'تایپوگرافی متن', '{{WRAPPER}} .tpmc .tpmc-trigger' );

		$this->start_controls_tabs( 'trigger_tabs' );
		$this->start_controls_tab( 'trigger_normal', array( 'label' => 'عادی' ) );
		$this->color( 'trigger_color', 'رنگ آیکون و متن', self::TRIGGER, '--tpmc-t-color' );
		$this->color( 'trigger_bg', 'پس‌زمینه', self::TRIGGER, '--tpmc-t-bg' );
		$this->color( 'trigger_line', 'رنگ خط دور', self::TRIGGER, '--tpmc-t-line' );
		$this->end_controls_tab();
		$this->start_controls_tab( 'trigger_hover', array( 'label' => 'هاور' ) );
		$this->color( 'trigger_color_h', 'رنگ آیکون و متن', self::TRIGGER, '--tpmc-t-color-h' );
		$this->color( 'trigger_bg_h', 'پس‌زمینه', self::TRIGGER, '--tpmc-t-bg-h' );
		$this->color( 'trigger_line_h', 'رنگ خط دور', self::TRIGGER, '--tpmc-t-line-h' );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->end_controls_section();
	}

	private function style_count(): void {
		$this->start_controls_section(
			'style_count',
			array(
				'label'     => 'شمارنده',
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'count!' => 'none' ),
			)
		);
		$this->color( 'count_bg', 'پس‌زمینه', self::TRIGGER, '--tpmc-c-bg' );
		$this->color( 'count_color', 'رنگ عدد', self::TRIGGER, '--tpmc-c-color' );
		$this->size( 'count_size', 'اندازه', self::TRIGGER, '--tpmc-c-size', 14, 40 );
		$this->size( 'count_font', 'اندازه‌ی عدد', self::TRIGGER, '--tpmc-c-font', 8, 24 );
		$this->size( 'count_x', 'جابه‌جایی افقی', self::TRIGGER, '--tpmc-c-x', -30, 60 );
		$this->size( 'count_y', 'جابه‌جایی عمودی', self::TRIGGER, '--tpmc-c-y', -30, 60 );
		$this->end_controls_section();
	}

	private function style_panel(): void {
		$this->start_controls_section(
			'style_panel',
			array(
				'label' => 'پنل',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_responsive_control(
			'panel_width',
			array(
				'label'      => 'عرض',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vw' ),
				'range'      => array(
					'px' => array(
						'min' => 280,
						'max' => 640,
					),
					'vw' => array(
						'min' => 30,
						'max' => 100,
					),
				),
				'selectors'  => array( self::DRAWER => '--tpmc-w: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->size( 'panel_gap', 'فاصله از لبه‌های صفحه', self::DRAWER, '--tpmc-gap', 0, 40 );
		$this->size( 'panel_radius', 'گردی گوشه‌ها', self::DRAWER, '--tpmc-radius', 0, 40 );
		$this->typography( 'panel_typography', 'فونت پنل', self::DRAWER );
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'panel_shadow',
				'selector' => self::PANEL,
			)
		);

		$this->heading( 'panel_colors', 'رنگ‌ها' );
		$this->color( 'color_accent', 'رنگ اصلی (سبز)', self::DRAWER, '--tpmc-accent' );
		$this->color( 'color_gold', 'رنگ دوم (طلایی)', self::DRAWER, '--tpmc-gold' );
		$this->color( 'color_cream', 'رنگ ملایم (کرم)', self::DRAWER, '--tpmc-cream' );
		$this->color( 'color_deep', 'متن روی رنگ ملایم', self::DRAWER, '--tpmc-deep' );
		$this->color( 'color_bg', 'پس‌زمینه‌ی پنل', self::DRAWER, '--tpmc-bg' );
		$this->color( 'color_soft', 'پس‌زمینه‌ی خاکستری روشن', self::DRAWER, '--tpmc-soft' );
		$this->color( 'color_ink', 'متن اصلی', self::DRAWER, '--tpmc-ink' );
		$this->color( 'color_muted', 'متن کم‌رنگ', self::DRAWER, '--tpmc-muted' );
		$this->color( 'color_line', 'خط‌ها', self::DRAWER, '--tpmc-line' );

		$this->heading( 'overlay_heading', 'لایه‌ی پشت پنل' );
		$this->color( 'overlay_color', 'رنگ', self::DRAWER, '--tpmc-overlay' );
		$this->add_control(
			'overlay_blur',
			array(
				'label'       => 'محو شدن صفحه (blur)',
				'type'        => Controls_Manager::SLIDER,
				'range'       => array(
					'px' => array(
						'min' => 0,
						'max' => 20,
					),
				),
				'description' => 'روی گوشی‌های ضعیف، باز شدن پنل را کند می‌کند.',
				'selectors'   => array( self::DRAWER . ' .tpmc-overlay' => '-webkit-backdrop-filter: blur({{SIZE}}px); backdrop-filter: blur({{SIZE}}px);' ),
			)
		);
		$this->end_controls_section();
	}

	private function style_head(): void {
		$this->start_controls_section(
			'style_head',
			array(
				'label' => 'سربرگ پنل',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'title_color',
			array(
				'label'     => 'رنگ عنوان',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( self::PANEL . ' .tpmc-title' => 'color: {{VALUE}};' ),
			)
		);
		$this->typography( 'title_typography', 'تایپوگرافی عنوان', self::PANEL . ' .tpmc-title' );
		$this->add_control(
			'chip_bg',
			array(
				'label'     => 'پس‌زمینه‌ی تعداد',
				'type'      => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array( self::PANEL . ' .tpmc-chip' => 'background: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'chip_color',
			array(
				'label'     => 'رنگ تعداد',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( self::PANEL . ' .tpmc-chip' => 'color: {{VALUE}};' ),
			)
		);

		$this->heading( 'close_heading', 'دکمه‌ی بستن' );
		$this->size( 'close_size', 'اندازه', self::DRAWER, '--tpmc-close-size', 28, 60 );
		$this->color( 'close_color', 'رنگ آیکون', self::DRAWER, '--tpmc-close-color' );
		$this->color( 'close_bg', 'پس‌زمینه', self::DRAWER, '--tpmc-close-bg' );
		$this->color( 'close_color_h', 'رنگ آیکون در هاور', self::DRAWER, '--tpmc-close-color-h' );
		$this->color( 'close_bg_h', 'پس‌زمینه در هاور', self::DRAWER, '--tpmc-close-bg-h' );
		$this->end_controls_section();
	}

	private function style_items(): void {
		$this->start_controls_section(
			'style_items',
			array(
				'label' => 'کالاهای سبد',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->color( 'item_bg', 'پس‌زمینه‌ی کارت', self::DRAWER, '--tpmc-item-bg' );
		$this->color( 'item_line', 'خط دور کارت', self::DRAWER, '--tpmc-item-line' );
		$this->size( 'item_radius', 'گردی کارت', self::DRAWER, '--tpmc-item-radius', 0, 30 );
		$this->size( 'item_pad', 'فاصله‌ی داخلی کارت', self::DRAWER, '--tpmc-item-pad', 0, 24 );
		$this->size( 'item_gap', 'فاصله‌ی بین کارت‌ها', self::DRAWER, '--tpmc-item-gap', 0, 30 );
		$this->size( 'image_size', 'اندازه‌ی تصویر', self::DRAWER, '--tpmc-img', 44, 140 );
		$this->size( 'image_radius', 'گردی تصویر', self::DRAWER, '--tpmc-img-radius', 0, 30 );

		$this->heading( 'name_heading', 'متن‌ها' );
		$this->add_control(
			'name_color',
			array(
				'label'     => 'رنگ نام محصول',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( self::PANEL . ' .tpmc-item__name' => 'color: {{VALUE}};' ),
			)
		);
		$this->typography( 'name_typography', 'تایپوگرافی نام محصول', self::PANEL . ' .tpmc-item__name' );
		$this->color( 'price_color', 'رنگ قیمت', self::DRAWER, '--tpmc-price' );
		$this->typography( 'price_typography', 'تایپوگرافی قیمت', self::PANEL . ' .tpmc-item__total' );

		$this->heading( 'qty_heading', 'تعداد و حذف' );
		$this->color( 'qty_color', 'رنگ دکمه‌های + و −', self::DRAWER, '--tpmc-qty-color' );
		$this->color( 'qty_bg', 'پس‌زمینه‌ی کنترل تعداد', self::DRAWER, '--tpmc-qty-bg' );
		$this->color( 'qty_line', 'خط دور کنترل تعداد', self::DRAWER, '--tpmc-qty-line' );
		$this->color( 'remove_color', 'رنگ دکمه‌ی حذف', self::DRAWER, '--tpmc-remove' );
		$this->color( 'remove_color_h', 'رنگ دکمه‌ی حذف در هاور', self::DRAWER, '--tpmc-remove-h' );
		$this->end_controls_section();
	}

	private function style_foot(): void {
		$this->start_controls_section(
			'style_foot',
			array(
				'label' => 'جمع و دکمه‌ها',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->color( 'foot_bg', 'پس‌زمینه', self::DRAWER, '--tpmc-foot-bg' );
		$this->color( 'total_color', 'رنگ مبلغ جمع', self::DRAWER, '--tpmc-total' );
		$this->typography( 'total_typography', 'تایپوگرافی مبلغ جمع', self::PANEL . ' .tpmc-total__val' );

		$this->heading( 'buttons_heading', 'دکمه‌ها' );
		$this->size( 'button_height', 'ارتفاع', self::DRAWER, '--tpmc-btn-h', 36, 72 );
		$this->size( 'button_radius', 'گردی گوشه‌ها', self::DRAWER, '--tpmc-btn-radius', 0, 40 );
		$this->typography( 'button_typography', 'تایپوگرافی', self::PANEL . ' .tpmc-btn' );

		$this->heading( 'primary_heading', 'دکمه‌ی تسویه' );
		$this->color( 'primary_bg', 'پس‌زمینه', self::DRAWER, '--tpmc-p-bg' );
		$this->color( 'primary_color', 'رنگ متن', self::DRAWER, '--tpmc-p-color' );
		$this->color( 'primary_bg_h', 'پس‌زمینه در هاور', self::DRAWER, '--tpmc-p-bg-h' );
		$this->color( 'primary_color_h', 'رنگ متن در هاور', self::DRAWER, '--tpmc-p-color-h' );

		$this->heading( 'ghost_heading', 'دکمه‌ی مشاهده‌ی سبد' );
		$this->color( 'ghost_bg', 'پس‌زمینه', self::DRAWER, '--tpmc-g-bg' );
		$this->color( 'ghost_color', 'رنگ متن', self::DRAWER, '--tpmc-g-color' );
		$this->color( 'ghost_line', 'رنگ خط دور', self::DRAWER, '--tpmc-g-line' );
		$this->color( 'ghost_bg_h', 'پس‌زمینه در هاور', self::DRAWER, '--tpmc-g-bg-h' );
		$this->color( 'ghost_color_h', 'رنگ متن در هاور', self::DRAWER, '--tpmc-g-color-h' );
		$this->end_controls_section();
	}

	private function style_empty(): void {
		$this->start_controls_section(
			'style_empty',
			array(
				'label' => 'سبد خالی',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->size( 'empty_size', 'اندازه‌ی دایره‌ی آیکون', self::DRAWER, '--tpmc-empty-size', 60, 180 );
		$this->color( 'empty_color', 'رنگ آیکون', self::DRAWER, '--tpmc-empty-color' );
		$this->color( 'empty_bg', 'پس‌زمینه‌ی آیکون', self::DRAWER, '--tpmc-empty-bg' );
		$this->add_control(
			'empty_title_color',
			array(
				'label'     => 'رنگ عنوان',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( self::PANEL . ' .tpmc-empty__title' => 'color: {{VALUE}};' ),
			)
		);
		$this->typography( 'empty_title_typography', 'تایپوگرافی عنوان', self::PANEL . ' .tpmc-empty__title' );
		$this->add_control(
			'empty_text_color',
			array(
				'label'     => 'رنگ توضیح',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( self::PANEL . ' .tpmc-empty__text' => 'color: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();
	}

	/*
	|--------------------------------------------------------------------------
	| Output
	|--------------------------------------------------------------------------
	*/

	private function icon_html( $icon ): string {
		if ( empty( $icon['value'] ) ) {
			return '';
		}
		ob_start();
		Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
		return (string) ob_get_clean();
	}

	protected function render(): void {
		$s       = $this->get_settings_for_display();
		$plugin  = TPMC_Plugin::instance();
		$id      = $this->get_id();
		$editing = \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode();
		$preview = null;
		if ( $editing ) {
			$mode    = isset( $s['editor_preview'] ) ? $s['editor_preview'] : 'closed';
			$preview = array(
				'lines' => $plugin->preview_lines(),
				'open'  => 'closed' !== $mode,
				'empty' => 'empty' === $mode,
			);
		}

		echo '<div class="tpmc">';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- both methods escape their output.
		echo $plugin->trigger(
			array(
				'type'      => $s['type'],
				'text'      => $s['text'],
				'icon'      => $this->icon_html( $s['icon'] ),
				'count'     => 'none' === $s['count'] ? '' : $s['count'],
				'hide_zero' => 'yes' === $s['hide_zero'],
				'label'     => $s['label'],
				'target'    => 'tpmc-drawer-' . $id,
			)
		);
		echo '</div>';
		echo $plugin->drawer(
			array(
				'id'            => $id,
				'class'         => 'yes' === $s['show_unit'] ? '' : 'tpmc--no-unit',
				'side'          => $s['side'],
				'title'         => $s['title'],
				'chip'          => 'yes' === $s['chip'],
				'auto_open'     => 'yes' === $s['auto_open'],
				'layout'        => $s['layout'],
				'checkout_text' => $s['checkout_text'],
				'cart_text'     => $s['cart_text'],
				'total_label'   => $s['total_label'],
				'note'          => $s['note'],
				'empty_title'   => $s['empty_title'],
				'empty_text'    => $s['empty_text'],
				'empty_button'  => $s['empty_button'],
				'empty_url'     => isset( $s['empty_link']['url'] ) ? $s['empty_link']['url'] : '',
				'empty_icon'    => $this->icon_html( $s['empty_icon'] ),
				'preview'       => $preview,
			)
		);
		// phpcs:enable
	}
}
