<?php
/**
 * ویجت المنتور «تصویر محصول + هاور» برای قالب آیتم Loop Grid المنتور یا Listing جت‌انجین.
 *
 * @package AromistHoverImage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class AHI_Widget extends Widget_Base {

	public function get_name() {
		return 'aromist_hover_image';
	}

	public function get_title() {
		return 'تصویر محصول + هاور (تکپینو)';
	}

	public function get_icon() {
		return 'eicon-image-rollover';
	}

	public function get_categories() {
		return array( 'techpino' );
	}

	public function get_keywords() {
		return array( 'hover', 'image', 'product', 'loop', 'هاور', 'تصویر', 'محصول', 'نت' );
	}

	public function get_style_depends() {
		return array( 'aromist-hover-image' );
	}

	public function get_script_depends() {
		return array( 'aromist-hover-image' );
	}

	private function get_size_options() {
		$out = array();
		foreach ( get_intermediate_image_sizes() as $size ) {
			$out[ $size ] = $size;
		}
		$out['full'] = 'full (اصلی)';
		return $out;
	}

	protected function register_controls() {

		/* ---------- تنظیمات ---------- */
		$this->start_controls_section( 'section_content', array( 'label' => 'تصویر هاور' ) );

		$this->add_control(
			'image_size',
			array(
				'label'       => 'سایز تصویر',
				'type'        => Controls_Manager::SELECT,
				'default'     => 'woocommerce_thumbnail',
				'options'     => $this->get_size_options(),
				'description' => 'سایزی بزرگ‌تر از عرض نمایش انتخاب کنید تا عکس تار نشود (تصویر هاور خودش بهترین کیفیت را برمی‌دارد).',
			)
		);

		$this->add_responsive_control(
			'width',
			array(
				'label'       => 'عرض تصویر',
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( 'px', '%', 'vw' ),
				'range'       => array(
					'px' => array( 'min' => 20, 'max' => 800 ),
					'%'  => array( 'min' => 5, 'max' => 100 ),
					'vw' => array( 'min' => 5, 'max' => 100 ),
				),
				'description' => 'خالی = تمام عرض. چینش (راست/وسط/چپ) را از تنظیمات کانتینر والد انجام دهید.',
				'selectors'   => array(
					'{{WRAPPER}}' => 'width: {{SIZE}}{{UNIT}}; max-width: 100%; --container-widget-width: {{SIZE}}{{UNIT}}; --container-widget-flex-grow: 0;',
				),
			)
		);

		$this->add_responsive_control(
			'ratio',
			array(
				'label'     => 'نسبت ابعاد',
				'type'      => Controls_Manager::SELECT,
				'default'   => '',
				'options'   => array(
					''    => 'مثل عکس اصلی',
					'1/1' => '۱:۱ (مربع)',
					'4/5' => '۴:۵',
					'3/4' => '۳:۴',
					'2/3' => '۲:۳',
				),
				'selectors' => array( '{{WRAPPER}} .ahi' => '--ahi-ratio: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'link',
			array(
				'label'        => 'لینک به محصول',
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
				'description'  => 'اگر ویجت داخل کانتینری است که خودش لینک دارد، خاموش بماند؛ لینک تو در تو ساختار آیتم را به هم می‌ریزد.',
			)
		);

		$this->add_control(
			'trigger',
			array(
				'label'       => 'نمایش وقتی موس روی ...',
				'type'        => Controls_Manager::SELECT,
				'default'     => 'card',
				'options'     => array(
					'card'  => 'کل باکس محصول',
					'image' => 'فقط خود تصویر',
				),
				'description' => '«کل باکس» یعنی آیتم Loop Grid / Listing جت‌انجین که این ویجت داخلش است.',
			)
		);

		$this->add_control(
			'effect',
			array(
				'label'   => 'افکت',
				'type'    => Controls_Manager::SELECT,
				'default' => 'fade',
				'options' => array(
					'fade'  => 'محو شدن (Fade)',
					'zoom'  => 'محو + زوم ملایم',
					'slide' => 'بالا آمدن از پایین',
				),
			)
		);

		$this->add_control(
			'mobile',
			array(
				'label'       => 'موبایل و تبلت',
				'type'        => Controls_Manager::SELECT,
				'default'     => 'button',
				'options'     => array(
					'button' => 'دکمه‌ی ⓘ برای دیدن تصویر',
					'none'   => 'نمایش داده نشود',
				),
				'description' => 'در دستگاه‌های لمسی هاور وجود ندارد.',
			)
		);

		$this->add_control(
			'fallback',
			array(
				'label'   => 'اگر محصول تصویر هاور نداشت',
				'type'    => Controls_Manager::SELECT,
				'default' => 'none',
				'options' => array(
					'none'    => 'فقط عکس اصلی',
					'gallery' => 'اولین تصویر گالری',
				),
			)
		);

		$this->add_control(
			'editor_preview',
			array(
				'label'       => 'نمایش تصویر هاور در ویرایشگر',
				'type'        => Controls_Manager::SWITCHER,
				'default'     => '',
				'return_value' => 'yes',
				'description' => 'فقط داخل المنتور؛ روی سایت اثری ندارد.',
				'separator'   => 'before',
			)
		);

		$this->end_controls_section();

		/* ---------- استایل ---------- */
		$this->start_controls_section(
			'section_style',
			array(
				'label' => 'استایل',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'radius',
			array(
				'label'      => 'گردی گوشه‌ها',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}} .ahi' => '--ahi-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'duration',
			array(
				'label'     => 'سرعت افکت (میلی‌ثانیه)',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 1200, 'step' => 50 ) ),
				'default'   => array( 'size' => 350 ),
				'selectors' => array( '{{WRAPPER}} .ahi' => '--ahi-dur: {{SIZE}}ms;' ),
			)
		);

		$this->add_control(
			'hover_fit',
			array(
				'label'       => 'جای‌گیری تصویر هاور',
				'type'        => Controls_Manager::SELECT,
				'default'     => 'contain',
				'options'     => array(
					'contain' => 'کامل دیده شود (Contain)',
					'cover'   => 'کادر را پر کند (Cover)',
				),
				'description' => '«کامل» مطمئن می‌شود متن نت‌ها بریده نشود.',
				'selectors'   => array( '{{WRAPPER}} .ahi' => '--ahi-hover-fit: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'hover_bg',
			array(
				'label'     => 'رنگ پس‌زمینه‌ی تصویر هاور',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .ahi' => '--ahi-hover-bg: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'btn_heading',
			array(
				'label'     => 'دکمه‌ی ⓘ (موبایل)',
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'btn_bg',
			array(
				'label'     => 'پس‌زمینه',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .ahi' => '--ahi-btn-bg: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'btn_color',
			array(
				'label'     => 'رنگ آیکون',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .ahi' => '--ahi-btn-color: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s          = $this->get_settings_for_display();
		$product_id = ahi_current_product_id();
		$is_editor  = class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode();

		if ( ! $product_id || ! wc_get_product( $product_id ) ) {
			if ( $is_editor ) {
				echo '<div style="padding:16px;border:1px dashed #c3c4c7;text-align:center;font-size:13px">این ویجت را داخل قالب آیتم Loop Grid المنتور یا Listing جت‌انجین محصولات بگذارید. برای پیش‌نمایش، در تنظیمات قالب یک محصول را به‌عنوان پیش‌نمایش (Preview) انتخاب کنید.</div>';
			}
			return;
		}

		echo ahi_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			$product_id,
			array(
				'size'     => $s['image_size'],
				'link'     => 'yes' === $s['link'],
				'trigger'  => $s['trigger'],
				'effect'   => $s['effect'],
				'mobile'   => $s['mobile'],
				'fallback' => $s['fallback'],
				'preview'  => $is_editor && 'yes' === $s['editor_preview'],
			)
		);
	}
}
