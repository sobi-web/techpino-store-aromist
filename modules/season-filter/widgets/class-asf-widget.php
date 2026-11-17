<?php
/**
 * ویجت المنتور «فیلتر AJAX کاروسل»: چند دکمه بالای Loop Carousel که اسلایدها را بدون ری‌لود عوض می‌کند.
 *
 * @package AromistSeasonFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;

class ASF_Widget extends Widget_Base {

	public function get_name() {
		return ASF_Core::WIDGET;
	}

	public function get_title() {
		return 'فیلتر AJAX کاروسل (تکپینو)';
	}

	public function get_icon() {
		return 'eicon-filter';
	}

	public function get_categories() {
		return array( 'techpino' );
	}

	public function get_keywords() {
		return array( 'filter', 'season', 'carousel', 'ajax', 'فیلتر', 'فصل', 'گرم', 'خنک', 'کاروسل' );
	}

	public function get_style_depends() {
		return array( 'aromist-season-filter' );
	}

	public function get_script_depends() {
		return array( 'aromist-season-filter' );
	}

	/**
	 * گزینه‌های ترم برای کنترل انتخاب: دسته‌بندی‌ها و ویژگی‌های ووکامرس.
	 *
	 * @return array گروه‌ها برای کنترل SELECT المنتور.
	 */
	private static function term_groups() {
		$groups     = array(
			array(
				'label'   => '—',
				'options' => array( '' => '— انتخاب کنید —' ),
			),
		);
		$taxonomies = array_merge( array( 'product_cat' ), function_exists( 'wc_get_attribute_taxonomy_names' ) ? wc_get_attribute_taxonomy_names() : array() );

		foreach ( apply_filters( 'asf_term_taxonomies', $taxonomies ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 300,
				)
			);
			if ( is_wp_error( $terms ) || ! $terms ) {
				continue;
			}
			$options = array();
			foreach ( $terms as $term ) {
				$options[ $taxonomy . ':' . $term->term_id ] = $term->name;
			}
			$groups[] = array(
				'label'   => 0 === strpos( $taxonomy, 'pa_' ) && function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $taxonomy ) : get_taxonomy( $taxonomy )->labels->singular_name,
				'options' => $options,
			);
		}
		return $groups;
	}

	/**
	 * دکمه‌های پیش‌فرض: خنک، گرم، چهار فصل (ترم‌ها با نام پیدا می‌شوند).
	 *
	 * @return array
	 */
	private static function default_pills() {
		$map = array(
			array( 'خنک', 'pa_scent-type' ),
			array( 'گرم', 'pa_scent-type' ),
			array( 'چهار فصل', 'pa_for-the-season' ),
		);
		$out = array();
		foreach ( $map as $row ) {
			$term  = taxonomy_exists( $row[1] ) ? get_term_by( 'name', $row[0], $row[1] ) : false;
			$out[] = array(
				'label' => $row[0],
				'term'  => $term ? $row[1] . ':' . $term->term_id : '',
			);
		}
		return $out;
	}

	protected function register_controls() {

		/* ---------- دکمه‌ها ---------- */
		$this->start_controls_section( 'section_pills', array( 'label' => 'دکمه‌های فیلتر' ) );

		$this->add_control(
			'target_id',
			array(
				'label'       => 'شناسهٔ کاروسل هدف (CSS ID)',
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'مثلاً: men-carousel',
				'description' => 'روی کاروسل، تب Advanced ← «CSS ID» همین شناسه را بنویسید (بدون #)؛ بعد این ویجت را هر جای صفحه بگذارید. خالی = اولین کاروسلِ داخل همین کانتینر.',
				'dynamic'     => array( 'active' => false ),
			)
		);

		$repeater = new Repeater();
		$repeater->add_control(
			'label',
			array(
				'label'   => 'عنوان دکمه',
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);
		$repeater->add_control(
			'term',
			array(
				'label'  => 'ترم فیلتر',
				'type'   => Controls_Manager::SELECT,
				'groups' => self::term_groups(),
			)
		);

		$this->add_control(
			'pills',
			array(
				'label'       => 'دکمه‌ها',
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => self::default_pills(),
				'title_field' => '{{{ label }}}',
			)
		);

		$this->add_control(
			'default',
			array(
				'label'       => 'دکمهٔ فعال پیش‌فرض (شمارهٔ ردیف)',
				'type'        => Controls_Manager::NUMBER,
				'min'         => 1,
				'step'        => 1,
				'default'     => 1,
				'description' => 'محصولات اولیهٔ کاروسل بر اساس همین دکمه رندر می‌شوند؛ ترم فصلِ تنظیم‌شده روی خود کاروسل نادیده گرفته می‌شود.',
			)
		);

		$this->add_control(
			'aria_label',
			array(
				'label'   => 'برچسب دسترسی‌پذیری گروه',
				'type'    => Controls_Manager::TEXT,
				'default' => 'فیلتر محصولات بر اساس فصل',
			)
		);

		$this->add_control(
			'empty_text',
			array(
				'label'   => 'پیام وقتی محصولی نبود',
				'type'    => Controls_Manager::TEXT,
				'default' => 'محصولی با این ویژگی پیدا نشد.',
			)
		);

		$this->add_control(
			'error_text',
			array(
				'label'   => 'پیام خطا',
				'type'    => Controls_Manager::TEXT,
				'default' => 'دریافت محصولات انجام نشد؛ دوباره تلاش کنید.',
			)
		);

		$this->add_control(
			'hint',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => 'اگر «شناسهٔ کاروسل» را خالی بگذارید، ویجت باید <strong>داخل همان کانتینرِ</strong> Loop Carousel باشد. با شناسه می‌توانید دکمه‌ها را هر جای صفحه (مثلاً کنار عنوان) بگذارید. تغییر شناسه بعد از ذخیره اعمال می‌شود.',
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->end_controls_section();

		/* ---------- استایل ---------- */
		$this->start_controls_section(
			'section_style',
			array(
				'label' => 'استایل دکمه‌ها',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'                => 'چینش',
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'start'  => array( 'title' => 'ابتدا', 'icon' => 'eicon-align-start-h' ),
					'center' => array( 'title' => 'وسط', 'icon' => 'eicon-align-center-h' ),
					'end'    => array( 'title' => 'انتها', 'icon' => 'eicon-align-end-h' ),
				),
				'selectors_dictionary' => array(
					'start'  => 'flex-start',
					'center' => 'center',
					'end'    => 'flex-end',
				),
				'selectors'            => array( '{{WRAPPER}} .asf' => 'justify-content: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'      => 'فاصلهٔ بین دکمه‌ها',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .asf' => '--asf-gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} .asf__pill',
			)
		);

		$this->add_responsive_control(
			'padding',
			array(
				'label'      => 'فاصلهٔ داخلی',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .asf__pill' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'radius',
			array(
				'label'      => 'گردی گوشه‌ها',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
				'selectors'  => array( '{{WRAPPER}} .asf' => '--asf-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->start_controls_tabs( 'tabs_colors' );

		$this->start_controls_tab( 'tab_normal', array( 'label' => 'عادی' ) );
		$this->add_control(
			'color',
			array(
				'label'     => 'رنگ متن',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .asf' => '--asf-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'bg',
			array(
				'label'     => 'رنگ پس‌زمینه',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .asf' => '--asf-bg: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'border',
				'selector' => '{{WRAPPER}} .asf__pill',
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'shadow',
				'selector' => '{{WRAPPER}} .asf__pill',
			)
		);
		$this->end_controls_tab();

		// هاور فقط روی دکمه‌های غیرفعال اعمال می‌شود.
		$this->start_controls_tab( 'tab_hover', array( 'label' => 'هاور' ) );
		$this->add_control(
			'hover_color',
			array(
				'label'     => 'رنگ متن',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .asf__pill:not(.is-active):hover' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'hover_bg',
			array(
				'label'     => 'رنگ پس‌زمینه',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .asf__pill:not(.is-active):hover' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'hover_border_color',
			array(
				'label'     => 'رنگ حاشیه',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .asf__pill:not(.is-active):hover' => 'border-color: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'hover_shadow',
				'selector' => '{{WRAPPER}} .asf__pill:not(.is-active):hover',
			)
		);
		$this->add_control(
			'hover_lift',
			array(
				'label'      => 'بالا آمدن هنگام هاور',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 8 ) ),
				'selectors'  => array( '{{WRAPPER}} .asf__pill:not(.is-active):hover' => 'transform: translateY(-{{SIZE}}{{UNIT}});' ),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'tab_active', array( 'label' => 'فعال' ) );
		$this->add_control(
			'active_color',
			array(
				'label'     => 'رنگ متن',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .asf' => '--asf-active-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'active_bg',
			array(
				'label'     => 'رنگ پس‌زمینه',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .asf' => '--asf-active-bg: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'active_border_color',
			array(
				'label'     => 'رنگ حاشیه',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .asf__pill.is-active' => 'border-color: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'active_shadow',
				'selector' => '{{WRAPPER}} .asf__pill.is-active',
			)
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'transition_ms',
			array(
				'label'      => 'سرعت انیمیشن تغییر حالت (میلی‌ثانیه)',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 800, 'step' => 25 ) ),
				'selectors'  => array( '{{WRAPPER}} .asf' => '--asf-dur: {{SIZE}}ms;' ),
			)
		);

		$this->add_control(
			'fill_mobile',
			array(
				'label'        => 'پر کردن عرض ردیف در موبایل',
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => 'بله',
				'label_off'    => 'خیر',
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
				'prefix_class' => 'asf-fill-mobile-',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$pills    = ASF_Core::pills( $settings );
		$editing  = \Elementor\Plugin::$instance->editor->is_edit_mode();

		if ( ! $pills ) {
			if ( $editing ) {
				echo '<div class="asf-note">برای هر دکمه عنوان و ترم فیلتر را انتخاب کنید.</div>';
			}
			return;
		}

		$post_id = ASF_Core::current_doc_id();
		$target  = ASF_Core::target_for_filter( $post_id, $this->get_id() );
		if ( '' === $target && $editing ) {
			$ref = ASF_Core::clean_ref( isset( $settings['target_id'] ) ? $settings['target_id'] : '' );
			if ( '' !== $ref ) {
				echo '<div class="asf-note">کاروسلی با شناسهٔ «' . esc_html( $ref ) . '» پیدا نشد. در کاروسل (Advanced ← CSS ID) همین شناسه را بگذارید و صفحه را ذخیره کنید.</div>';
			} else {
				echo '<div class="asf-note">Loop Carousel کنار این ویجت پیدا نشد؛ ویجت را داخل همان کانتینرِ کاروسل بگذارید یا «شناسهٔ کاروسل» را پر کنید (بعد از ذخیره اعمال می‌شود).</div>';
			}
		}

		$active = ASF_Core::default_index( $settings );
		?>
		<div class="asf"
			data-asf
			data-post="<?php echo esc_attr( $post_id ); ?>"
			data-filter="<?php echo esc_attr( $this->get_id() ); ?>"
			data-target="<?php echo esc_attr( $target ); ?>"
			data-empty="<?php echo esc_attr( $settings['empty_text'] ); ?>"
			data-error="<?php echo esc_attr( $settings['error_text'] ); ?>"
			role="group"
			aria-label="<?php echo esc_attr( $settings['aria_label'] ); ?>">
			<?php foreach ( $pills as $index => $pill ) : ?>
				<button type="button"
					class="asf__pill<?php echo $index === $active ? ' is-active' : ''; ?>"
					data-pill="<?php echo esc_attr( $index ); ?>"
					aria-pressed="<?php echo $index === $active ? 'true' : 'false'; ?>">
					<?php echo esc_html( $pill['label'] ); ?>
				</button>
			<?php endforeach; ?>
			<span class="asf__status" role="status" aria-live="polite"></span>
		</div>
		<?php
	}
}
