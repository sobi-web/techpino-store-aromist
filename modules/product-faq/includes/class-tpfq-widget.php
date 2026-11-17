<?php
/**
 * Elementor widget: «سوالات متداول محصول (تکپینو)».
 *
 * Meant for the single product template: it prints nothing for a product without
 * questions, heading included, so one template serves every product. Style controls
 * mostly set CSS variables that assets/css/faq.css reads.
 *
 * @package TechpinoProductFaq
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

class TPFQ_Widget extends \Elementor\Widget_Base {

	private const ROOT = '{{WRAPPER}} .tpfq';

	public function get_name(): string {
		return 'tp-product-faq';
	}

	public function get_title(): string {
		return 'سوالات متداول محصول (تکپینو)';
	}

	public function get_icon(): string {
		return 'eicon-help-o';
	}

	public function get_categories(): array {
		return array( 'techpino' );
	}

	public function get_keywords(): array {
		return array( 'faq', 'accordion', 'question', 'product', 'سوال', 'سوالات متداول', 'پرسش', 'تکپینو' );
	}

	/*
	 * No get_style_depends(): Elementor would then load the stylesheet on every product,
	 * questions or not. TPFQ_Plugin loads it in <head> for products that have questions,
	 * in the editor preview, and while rendering anywhere else.
	 */

	/** The questions belong to the product being shown: never served from Elementor's element cache. */
	protected function is_dynamic_content(): bool {
		return true;
	}

	/*
	|--------------------------------------------------------------------------
	| Control helpers
	|--------------------------------------------------------------------------
	*/

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
	private function color( string $id, string $label, string $var ): void {
		$this->add_control(
			$id,
			array(
				'label'     => $label,
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( self::ROOT => $var . ': {{VALUE}};' ),
			)
		);
	}

	/** A length stored in a CSS variable. */
	private function size( string $id, string $label, string $var, int $max ): void {
		$this->add_responsive_control(
			$id,
			array(
				'label'      => $label,
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => $max,
					),
				),
				'selectors'  => array( self::ROOT => $var . ': {{SIZE}}{{UNIT}};' ),
			)
		);
	}

	private function typography( string $id, string $label, string $selector ): void {
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => $id,
				'label'    => $label,
				'selector' => self::ROOT . ' ' . $selector,
			)
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Controls
	|--------------------------------------------------------------------------
	*/

	protected function register_controls(): void {
		$this->start_controls_section( 'section_faq', array( 'label' => 'سوالات متداول' ) );
		$this->add_control(
			'note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => 'سوالات پیش‌فرض: محصولات ← سوالات متداول. سوالات اختصاصی هر محصول: باکس «سوالات متداول محصول» در صفحه‌ی ویرایش همان محصول. برای محصولی که سوالی ندارد این ویجت چیزی نمایش نمی‌دهد. اگر محصول پیش‌نمایش سوالی نداشته باشد، اینجا چند سوال نمونه می‌بینید.',
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);
		$this->add_control(
			'product_id',
			array(
				'label'       => 'شناسه‌ی محصول',
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'description' => 'خالی بگذارید تا سوالات محصولِ همین صفحه نمایش داده شود.',
			)
		);
		$this->add_control(
			'title',
			array(
				'label'       => 'عنوان',
				'type'        => Controls_Manager::TEXT,
				'default'     => 'سوالات متداول',
				'label_block' => true,
				'description' => 'برای نام محصول از {product} استفاده کنید.',
			)
		);
		$this->add_control(
			'title_tag',
			array(
				'label'   => 'تگ عنوان',
				'type'    => Controls_Manager::SELECT,
				'default' => 'h2',
				'options' => array(
					'h2'  => 'H2',
					'h3'  => 'H3',
					'h4'  => 'H4',
					'div' => 'div',
				),
			)
		);
		$this->add_control(
			'subtitle',
			array(
				'label' => 'توضیح زیر عنوان',
				'type'  => Controls_Manager::TEXTAREA,
				'rows'  => 2,
			)
		);
		$this->add_control(
			'open',
			array(
				'label'     => 'در شروع',
				'type'      => Controls_Manager::SELECT,
				'default'   => 'first',
				'options'   => array(
					'first' => 'جواب اول باز باشد',
					'none'  => 'همه بسته باشند',
				),
				'separator' => 'before',
			)
		);
		$this->toggle( 'single', 'فقط یک جواب باز بماند' );
		$this->toggle( 'numbers', 'شماره‌ی سوال‌ها' );
		$this->toggle( 'card', 'نمایش داخل کارت' );
		$this->toggle( 'schema', 'اسکیمای FAQ (داده‌ی ساختاریافته)' );
		$this->end_controls_section();

		$this->start_controls_section( 'section_cta', array( 'label' => 'باکس «سوال دیگری داری؟»' ) );
		$this->toggle( 'cta', 'نمایش باکس' );
		// Empty fields use «محصولات ← سوالات متداول», shown as their placeholders.
		$global = TPFQ_Plugin::settings();
		$this->add_control(
			'cta_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => 'فیلدهای خالی از تنظیمات کلی (محصولات ← سوالات متداول) پر می‌شوند. بدون لینک، دکمه نمایش داده نمی‌شود.',
				'content_classes' => 'elementor-descriptor',
				'condition'       => array( 'cta' => 'yes' ),
			)
		);
		foreach (
			array(
				'cta_title'  => 'عنوان',
				'cta_text'   => 'متن',
				'cta_button' => 'متن دکمه',
			) as $id => $label
		) {
			$this->add_control(
				$id,
				array(
					'label'       => $label,
					'type'        => Controls_Manager::TEXT,
					'placeholder' => (string) $global[ $id ],
					'label_block' => true,
					'condition'   => array( 'cta' => 'yes' ),
				)
			);
		}
		$this->add_control(
			'cta_link',
			array(
				'label'       => 'لینک دکمه',
				'type'        => Controls_Manager::URL,
				'placeholder' => '' !== $global['cta_url'] ? (string) $global['cta_url'] : 'tel:0912… یا https://…',
				'dynamic'     => array( 'active' => true ),
				'condition'   => array( 'cta' => 'yes' ),
			)
		);
		$this->end_controls_section();

		/* Style */

		$this->start_controls_section(
			'style_head',
			array(
				'label' => 'عنوان',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->color( 'title_color', 'رنگ عنوان', '--tpfq-title' );
		$this->typography( 'title_typo', 'تایپوگرافی عنوان', '.tpfq-title' );
		$this->color( 'sub_color', 'رنگ توضیح', '--tpfq-sub' );
		$this->size( 'head_gap', 'فاصله تا سوال‌ها', '--tpfq-head-gap', 60 );
		$this->end_controls_section();

		$this->start_controls_section(
			'style_card',
			array(
				'label'     => 'کارت',
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'card' => 'yes' ),
			)
		);
		$this->color( 'card_bg', 'پس‌زمینه', '--tpfq-card-bg' );
		$this->color( 'card_line', 'رنگ حاشیه', '--tpfq-card-line' );
		$this->size( 'card_radius', 'گردی گوشه‌ها', '--tpfq-card-radius', 60 );
		$this->size( 'card_pad', 'فاصله‌ی داخلی', '--tpfq-card-pad', 60 );
		$this->end_controls_section();

		$this->start_controls_section(
			'style_items',
			array(
				'label' => 'سوال‌ها',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->color( 'accent', 'رنگ اصلی', '--tpfq-accent' );
		$this->color( 'item_bg', 'پس‌زمینه', '--tpfq-item-bg' );
		$this->color( 'item_bg_open', 'پس‌زمینه‌ی باز', '--tpfq-item-bg-open' );
		$this->color( 'item_line', 'رنگ حاشیه', '--tpfq-item-line' );
		$this->size( 'item_radius', 'گردی گوشه‌ها', '--tpfq-item-radius', 40 );
		$this->size( 'gap', 'فاصله‌ی سوال‌ها', '--tpfq-gap', 40 );
		$this->color( 'q_color', 'رنگ سوال', '--tpfq-q' );
		$this->typography( 'q_typo', 'تایپوگرافی سوال', '.tpfq-q__text' );
		$this->color( 'a_color', 'رنگ جواب', '--tpfq-a' );
		$this->typography( 'a_typo', 'تایپوگرافی جواب', '.tpfq-a' );
		$this->end_controls_section();

		$this->start_controls_section(
			'style_cta',
			array(
				'label'     => 'باکس «سوال دیگری داری؟»',
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'cta' => 'yes' ),
			)
		);
		$this->color( 'cta_bg', 'پس‌زمینه', '--tpfq-cta-bg' );
		$this->color( 'cta_line', 'رنگ حاشیه', '--tpfq-cta-line' );
		$this->color( 'cta_icon_bg', 'پس‌زمینه‌ی آیکون', '--tpfq-cta-icon-bg' );
		$this->color( 'cta_icon', 'رنگ آیکون', '--tpfq-cta-icon' );
		$this->color( 'cta_title_color', 'رنگ عنوان', '--tpfq-cta-title' );
		$this->color( 'btn_bg', 'پس‌زمینه‌ی دکمه', '--tpfq-btn-bg' );
		$this->color( 'btn_color', 'رنگ متن دکمه', '--tpfq-btn' );
		$this->color( 'btn_bg_h', 'پس‌زمینه‌ی دکمه (هاور)', '--tpfq-btn-bg-h' );
		$this->end_controls_section();
	}

	/*
	|--------------------------------------------------------------------------
	| Render
	|--------------------------------------------------------------------------
	*/

	protected function render(): void {
		$s       = $this->get_settings_for_display();
		$plugin  = TPFQ_Plugin::instance();
		$product = ! empty( $s['product_id'] ) ? absint( $s['product_id'] ) : TPFQ_Plugin::current_product_id();
		$items   = null;
		if ( TPFQ_Plugin::in_elementor_editor() && ! TPFQ_Plugin::questions( $product ) ) {
			$items = TPFQ_Plugin::sample_items();
		}

		$url   = isset( $s['cta_link']['url'] ) ? trim( (string) $s['cta_link']['url'] ) : '';
		$attrs = '';
		if ( '' !== $url ) {
			$this->add_link_attributes( 'cta_link', $s['cta_link'] );
			$this->remove_render_attribute( 'cta_link', 'href' );
			$attrs = ' ' . $this->get_render_attribute_string( 'cta_link' );
		}

		echo $plugin->render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
			array(
				'product'    => $product,
				'items'      => $items,
				'title'      => $s['title'],
				'title_tag'  => $s['title_tag'],
				'subtitle'   => $s['subtitle'],
				'open'       => $s['open'],
				'single'     => 'yes' === $s['single'],
				'numbers'    => 'yes' === $s['numbers'],
				'card'       => 'yes' === $s['card'],
				'schema'     => 'yes' === $s['schema'],
				'cta'        => 'yes' === $s['cta'],
				'cta_title'  => $s['cta_title'],
				'cta_text'   => $s['cta_text'],
				'cta_button' => $s['cta_button'],
				'cta_url'    => $url,
				'cta_attrs'  => '' === trim( $attrs ) ? '' : $attrs,
			)
		);
	}
}
