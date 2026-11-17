<?php
/**
 * Elementor widget «تخفیف‌های ویژه (تکپینو)».
 *
 * Shows nothing on the site (and can hide its parent container) until there are at least
 * «حداقل محصول» deals; in the editor it explains why instead.
 *
 * @package AromistDeals
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class ARD_Widget_Deals extends Widget_Base {

	public function get_name() {
		return 'ard_deals';
	}

	public function get_title() {
		return 'تخفیف‌های ویژه (تکپینو)';
	}

	public function get_icon() {
		return 'eicon-countdown';
	}

	public function get_categories() {
		return array( 'techpino' );
	}

	public function get_keywords() {
		return array( 'sale', 'deal', 'discount', 'offer', 'countdown', 'تخفیف', 'فروش ویژه', 'پیشنهاد', 'تایمر', 'فرصت' );
	}

	public function get_style_depends() {
		return array( 'ard-front', 'ard-deals' );
	}

	public function get_script_depends() {
		return array( 'ard-front', 'ard-deals' );
	}

	/** Prices, stock and timers change: never serve this widget from Elementor's element cache. */
	protected function is_dynamic_content(): bool {
		return true;
	}

	private function category_options(): array {
		$out   = array();
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		if ( is_array( $terms ) ) {
			foreach ( $terms as $t ) {
				$out[ $t->term_id ] = $t->name;
			}
		}
		return $out;
	}

	private function loop_templates(): array {
		$out = array( '' => 'box-product-1 (پیش‌فرض)' );
		$ids = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_key'       => '_elementor_template_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'loop-item', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		foreach ( $ids as $id ) {
			$out[ $id ] = get_the_title( $id ) . ' (#' . $id . ')';
		}
		return $out;
	}

	private function product_options(): array {
		$out = array();
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		foreach ( $ids as $id ) {
			$out[ $id ] = get_the_title( $id );
		}
		return $out;
	}

	protected function register_controls() {
		$d = ARD_Render::defaults();

		/* ---------- محتوا ---------- */
		$this->start_controls_section( 'section_head', array( 'label' => 'عنوان بخش' ) );
		$this->add_control(
			'title',
			array(
				'label'       => 'عنوان',
				'type'        => Controls_Manager::TEXT,
				'default'     => $d['title'],
				'label_block' => true,
			)
		);
		$this->add_control(
			'subtitle',
			array(
				'label'   => 'توضیح زیر عنوان',
				'type'    => Controls_Manager::TEXTAREA,
				'default' => $d['subtitle'],
				'rows'    => 2,
			)
		);
		$this->add_control(
			'title_tag',
			array(
				'label'   => 'تگ عنوان',
				'type'    => Controls_Manager::SELECT,
				'default' => 'h2',
				'options' => array(
					'h1'  => 'H1',
					'h2'  => 'H2',
					'h3'  => 'H3',
					'h4'  => 'H4',
					'div' => 'div',
				),
			)
		);
		$this->add_control(
			'head_timer',
			array(
				'label'        => 'تایمر کنار عنوان',
				'description'  => 'شمارش معکوسِ زودترین تخفیفی که تمام می‌شود.',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);
		$this->add_control(
			'more_text',
			array(
				'label'     => 'متن دکمه «مشاهده همه»',
				'type'      => Controls_Manager::TEXT,
				'default'   => '',
				'separator' => 'before',
			)
		);
		$this->add_control(
			'more_url',
			array(
				'label'       => 'لینک «مشاهده همه»',
				'type'        => Controls_Manager::URL,
				'placeholder' => home_url( '/shop/' ),
				'options'     => false,
			)
		);
		$this->end_controls_section();

		/* ---------- محصولات ---------- */
		$this->start_controls_section( 'section_query', array( 'label' => 'محصولات' ) );
		$this->add_control(
			'source',
			array(
				'label'   => 'کدام محصولات',
				'type'    => Controls_Manager::SELECT,
				'default' => 'all',
				'options' => array(
					'all'     => 'همه‌ی محصولات تخفیف‌دار',
					'cats'    => 'فقط از دسته‌های انتخابی',
					'archive' => 'دسته‌ی همین صفحه (برای قالب آرشیو)',
					'manual'  => 'انتخاب دستی',
				),
			)
		);
		$this->add_control(
			'cats',
			array(
				'label'       => 'دسته‌ها',
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->category_options(),
				'condition'   => array( 'source' => 'cats' ),
			)
		);
		$this->add_control(
			'include',
			array(
				'label'       => 'محصولات (به همین ترتیب)',
				'description' => 'فقط محصولاتی که الان تخفیف دارند نمایش داده می‌شوند.',
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->product_options(),
				'condition'   => array( 'source' => 'manual' ),
			)
		);
		$this->add_control(
			'exclude',
			array(
				'label'       => 'به‌جز این محصولات',
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->product_options(),
				'condition'   => array( 'source!' => 'manual' ),
			)
		);
		$this->add_control(
			'orderby',
			array(
				'label'   => 'ترتیب',
				'type'    => Controls_Manager::SELECT,
				'default' => 'discount',
				'options' => array(
					'discount' => 'بیشترین تخفیف',
					'ending'   => 'زودتر تمام می‌شود',
					'newest'   => 'تازه‌ترین تخفیف',
					'random'   => 'تصادفی',
					'manual'   => 'ترتیب انتخاب دستی',
				),
			)
		);
		$this->add_control(
			'min',
			array(
				'label'       => 'حداقل محصول برای نمایش',
				'description' => 'تا وقتی این تعداد محصول تخفیف‌دار نباشد، بخش در سایت دیده نمی‌شود.',
				'type'        => Controls_Manager::NUMBER,
				'default'     => 4,
				'min'         => 1,
				'max'         => 24,
				'separator'   => 'before',
			)
		);
		$this->add_control(
			'limit',
			array(
				'label'       => 'تعداد کل محصولات',
				'description' => 'حداکثر چند محصول تخفیف‌دار در این بخش بیاید (تعداد کارتی که هم‌زمان دیده می‌شود در «چیدمان» است).',
				'type'        => Controls_Manager::NUMBER,
				'default'     => 12,
				'min'         => 1,
				'max'         => 48,
			)
		);
		$this->add_control(
			'hide_parent',
			array(
				'label'        => 'کانتینر والد هم پنهان شود',
				'description'  => 'وقتی بخش نمایش داده نمی‌شود، کانتینری که ویجت داخلش است (با پس‌زمینه و فاصله‌هایش) هم پنهان می‌شود.',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->end_controls_section();

		/* ---------- کارت ---------- */
		$this->start_controls_section( 'section_card', array( 'label' => 'کارت محصول' ) );
		$this->add_control(
			'skin',
			array(
				'label'   => 'طرح کارت',
				'type'    => Controls_Manager::SELECT,
				'default' => 'tpl',
				'options' => array(
					'tpl'  => 'باکس خود سایت (قالب کارت محصول؛ مثل بقیه‌ی باکس‌ها)',
					'site' => 'کپی باکس تکپینو (دایره‌های حجم، دکمه‌ی افزودن ایجکسی)',
					'ref'  => 'طرح نمونه (یک حجم)',
				),
			)
		);
		$this->add_control(
			'card_template',
			array(
				'label'       => 'قالب کارت',
				'description' => 'همان قالب «آیتم حلقه» که کاروسل‌های دیگر سایت استفاده می‌کنند. تایمر و برچسب تخفیف داخل همین کارت اضافه می‌شوند.',
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => $this->loop_templates(),
				'condition'   => array( 'skin' => 'tpl' ),
			)
		);
		$switches = array(
			'show_sizes'    => array( 'حجم‌ها', 'yes' ),
			'show_english'  => array( 'نام انگلیسی', 'yes' ),
			'show_flag'     => array( 'برچسب «٪ تخفیف» روی عکس', '' ),
			'show_progress' => array( 'نوار زمان', 'yes' ),
			'show_timer'    => array( 'شمارش معکوس', 'yes' ),
		);
		foreach ( $switches as $key => $s ) {
			$args = array(
				'label'        => $s[0],
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => $s[1],
			);
			if ( in_array( $key, array( 'show_sizes', 'show_english' ), true ) ) {
				$args['condition'] = array( 'skin!' => 'tpl' ); // The site's card template decides these.
			}
			$this->add_control( $key, $args );
		}
		$this->add_control(
			'timer_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => 'تایمر از «زمان‌بندی» تخفیف هر حجم در ویرایش محصول خوانده می‌شود. برای تخفیف‌های بدون تاریخ پایان، می‌توانید در ووکامرس ← تخفیف‌ها یک «پایان کمپین» بگذارید؛ وگرنه تایمر آن کارت نمایش داده نمی‌شود.',
				'content_classes' => 'elementor-descriptor',
			)
		);
		$this->add_control(
			'small_sizes',
			array(
				'label'       => 'حجم‌ها در کارت‌های کوچک (موبایل)',
				'type'        => Controls_Manager::SELECT,
				'default'     => 'pill',
				'options'     => array(
					'pill'    => 'فقط حجم تخفیف‌دار (برچسب کوچک)',
					'circles' => 'دایره‌های ریز (قابل اسکرول)',
					'none'    => 'نمایش داده نشود',
				),
				'description' => 'وقتی کارت باریک‌تر از ۲۴۰ پیکسل است (موبایل، شبکه‌ی دوستونه).',
				'condition'   => array( 'skin' => 'site' ),
			)
		);
		$this->add_control(
			'button',
			array(
				'label'     => 'دکمه‌ی سبد',
				'type'      => Controls_Manager::SELECT,
				'default'   => 'add',
				'options'   => array(
					'add'  => 'افزودن همان حجم به سبد',
					'link' => 'رفتن به صفحه‌ی محصول',
				),
				'separator' => 'before',
				'condition' => array( 'skin!' => 'tpl' ),
			)
		);
		$this->add_control(
			'cart_icon',
			array(
				'label'       => 'آیکن دکمه‌ی سبد',
				'description' => 'خالی بماند = همان آیکن سبد باکس محصولات سایت (دایره + کیف).',
				'type'        => Controls_Manager::ICONS,
				'skin'        => 'inline',
				'label_block' => false,
				'default'     => array( 'value' => '', 'library' => '' ),
				'condition'   => array( 'skin!' => 'tpl' ),
			)
		);
		$this->end_controls_section();

		/* ---------- چیدمان ---------- */
		$this->start_controls_section( 'section_layout', array( 'label' => 'چیدمان' ) );
		$this->add_control(
			'layout',
			array(
				'label'   => 'چیدمان',
				'type'    => Controls_Manager::SELECT,
				'default' => 'carousel',
				'options' => array(
					'carousel' => 'کاروسل (اسلایدر)',
					'grid'     => 'شبکه',
				),
			)
		);
		$this->add_responsive_control(
			'per_view',
			array(
				'label'           => 'تعداد کارت قابل دیدن',
				'type'            => Controls_Manager::NUMBER,
				'min'             => 1,
				'max'             => 8,
				'step'            => 0.1,
				'default'         => 4,
				'tablet_default'  => 3,
				'mobile_default'  => 2.2,
				'description'     => 'برای دسکتاپ، تبلت و موبایل جدا (آیکن نمایشگر کنار عنوان). عدد اعشاری مثل ۲٫۲ یعنی کارت بعدی کمی پیداست تا معلوم باشد بخش اسکرول می‌خورد.',
				'selectors'       => array( '{{WRAPPER}} .ard' => '--ard-pv: {{VALUE}};' ),
				'condition'       => array( 'layout' => 'carousel' ),
			)
		);
		$this->add_responsive_control(
			'columns',
			array(
				'label'          => 'تعداد ستون',
				'type'           => Controls_Manager::NUMBER,
				'min'            => 1,
				'max'            => 6,
				'default'        => 4,
				'tablet_default' => 3,
				'mobile_default' => 2,
				'selectors'      => array( '{{WRAPPER}} .ard' => '--ard-cols: {{VALUE}};' ),
				'condition'      => array( 'layout' => 'grid' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'label'      => 'فاصله‌ی کارت‌ها',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
				'default'    => array( 'size' => 24, 'unit' => 'px' ),
				'tablet_default' => array( 'size' => 16, 'unit' => 'px' ),
				'mobile_default' => array( 'size' => 12, 'unit' => 'px' ),
				'selectors'  => array( '{{WRAPPER}} .ard' => '--ard-gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'arrows',
			array(
				'label'        => 'فلش‌ها',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'layout' => 'carousel' ),
			)
		);
		$this->add_control(
			'arrows_pos',
			array(
				'label'     => 'جای فلش‌ها',
				'type'      => Controls_Manager::SELECT,
				'default'   => 'sides',
				'options'   => array(
					'sides' => 'وسطِ راست و چپ کاروسل',
					'head'  => 'کنار عنوان',
				),
				'condition' => array( 'layout' => 'carousel', 'arrows' => 'yes' ),
			)
		);
		$this->add_responsive_control(
			'arrows_display',
			array(
				'label'          => 'نمایش فلش‌ها',
				'type'           => Controls_Manager::SELECT,
				'default'        => 'flex',
				'tablet_default' => 'flex',
				'mobile_default' => 'none',
				'options'        => array(
					'flex' => 'نمایش',
					'none' => 'پنهان',
				),
				'description'    => 'در موبایل با کشیدن انگشت جابه‌جا می‌شود؛ پیش‌فرض فلش ندارد.',
				'selectors'      => array( '{{WRAPPER}} .ard-arrow, {{WRAPPER}} .ard-nav' => 'display: {{VALUE}};' ),
				'condition'      => array( 'layout' => 'carousel', 'arrows' => 'yes' ),
			)
		);
		foreach ( array( 'prev_icon' => 'آیکن فلش قبلی (راست)', 'next_icon' => 'آیکن فلش بعدی (چپ)' ) as $key => $label ) {
			$this->add_control(
				$key,
				array(
					'label'       => $label,
					'type'        => Controls_Manager::ICONS,
					'skin'        => 'inline',
					'label_block' => false,
					'default'     => array( 'value' => '', 'library' => '' ),
					'condition'   => array( 'layout' => 'carousel', 'arrows' => 'yes' ),
				)
			);
		}
		$this->add_control(
			'dots',
			array(
				'label'        => 'نقطه‌ها',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'layout' => 'carousel' ),
			)
		);
		$this->add_control(
			'autoplay',
			array(
				'label'        => 'حرکت خودکار',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'condition'    => array( 'layout' => 'carousel' ),
			)
		);
		$this->add_control(
			'interval',
			array(
				'label'     => 'هر چند ثانیه',
				'type'      => Controls_Manager::NUMBER,
				'default'   => 5,
				'min'       => 2,
				'max'       => 20,
				'condition' => array(
					'layout'   => 'carousel',
					'autoplay' => 'yes',
				),
			)
		);
		$this->end_controls_section();

		/* ---------- استایل ---------- */
		$this->start_controls_section(
			'section_style_colors',
			array(
				'label' => 'رنگ‌ها',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$colors = array(
			'c_primary' => array( 'رنگ اصلی (سبز)', '--ard-primary' ),
			'c_gold'    => array( 'رنگ تخفیف و نوار (طلایی)', '--ard-gold' ),
			'c_card'    => array( 'پس‌زمینه‌ی کارت', '--ard-card-bg' ),
			'c_border'  => array( 'حاشیه‌ی کارت', '--ard-card-border' ),
			'c_media'   => array( 'پس‌زمینه‌ی عکس', '--ard-media-bg' ),
			'c_pill'    => array( 'پس‌زمینه‌ی برچسب حجم (طرح نمونه)', '--ard-pill-bg' ),
		);
		foreach ( $colors as $key => $c ) {
			$this->add_control(
				$key,
				array(
					'label'     => $c[0],
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ard' => $c[1] . ': {{VALUE}};' ),
				)
			);
		}
		$this->add_responsive_control(
			'card_radius',
			array(
				'label'      => 'گردی گوشه‌ی کارت',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
				'selectors'  => array( '{{WRAPPER}} .ard' => '--ard-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_head',
			array(
				'label' => 'عنوان',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'title_color',
			array(
				'label'     => 'رنگ عنوان',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .ard-head__title' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typo',
				'selector' => '{{WRAPPER}} .ard-head__title',
			)
		);
		$this->add_control(
			'sub_color',
			array(
				'label'     => 'رنگ توضیح',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .ard-head__sub' => 'color: {{VALUE}};' ),
				'separator' => 'before',
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'sub_typo',
				'selector' => '{{WRAPPER}} .ard-head__sub',
			)
		);
		$this->add_responsive_control(
			'head_gap',
			array(
				'label'      => 'فاصله‌ی عنوان تا کارت‌ها',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}} .ard' => '--ard-head-gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->style_arrows();
		$this->style_cart();
		$this->style_prices();
		$this->style_media();
	}

	/** Color + hover color + background + hover background + border for a button-like element. */
	private function button_colors( string $prefix, array $vars ): void {
		$labels = array(
			'color'    => 'رنگ آیکن',
			'bg'       => 'پس‌زمینه',
			'border'   => 'رنگ حاشیه',
			'h_color'  => 'رنگ آیکن (هاور)',
			'h_bg'     => 'پس‌زمینه (هاور)',
			'h_border' => 'رنگ حاشیه (هاور)',
		);
		foreach ( $labels as $key => $label ) {
			$this->add_control(
				$prefix . '_' . $key,
				array(
					'label'     => $label,
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ard' => $vars[ $key ] . ': {{VALUE}};' ),
				)
			);
		}
	}

	private function px( string $id, string $label, string $var, int $max, array $default = array() ): void {
		$args = array(
			'label'      => $label,
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => $max ) ),
			'selectors'  => array( '{{WRAPPER}} .ard' => $var . ': {{SIZE}}{{UNIT}};' ),
		);
		$this->add_responsive_control( $id, $args + $default );
	}

	private function style_arrows(): void {
		$this->start_controls_section(
			'section_style_arrows',
			array(
				'label'     => 'فلش‌ها',
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'layout' => 'carousel', 'arrows' => 'yes' ),
			)
		);
		$this->px( 'arrow_size', 'اندازه‌ی دکمه', '--ard-arrow-size', 80 );
		$this->px( 'arrow_icon', 'اندازه‌ی آیکن', '--ard-arrow-icon', 48 );
		$this->px( 'arrow_radius', 'گردی گوشه', '--ard-arrow-radius', 40 );
		$this->px( 'arrow_offset', 'فاصله از لبه', '--ard-arrow-offset', 60 );
		$this->add_responsive_control(
			'arrow_top',
			array(
				'label'      => 'ارتفاع (از بالای کارت‌ها)',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '%', 'px' ),
				'range'      => array(
					'%'  => array( 'min' => 0, 'max' => 100 ),
					'px' => array( 'min' => 0, 'max' => 600 ),
				),
				'selectors'  => array( '{{WRAPPER}} .ard' => '--ard-arrow-top: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->button_colors(
			'arrow',
			array(
				'color'    => '--ard-arrow-color',
				'bg'       => '--ard-arrow-bg',
				'border'   => '--ard-arrow-border',
				'h_color'  => '--ard-arrow-h-color',
				'h_bg'     => '--ard-arrow-h-bg',
				'h_border' => '--ard-arrow-h-border',
			)
		);
		$this->add_control(
			'arrow_shadow',
			array(
				'label'        => 'سایه',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'selectors'    => array( '{{WRAPPER}} .ard' => '--ard-arrow-shadow: 0 6px 18px -6px rgba(29, 29, 27, .28);' ),
			)
		);
		$this->end_controls_section();
	}

	private function style_cart(): void {
		$this->start_controls_section(
			'section_style_cart',
			array(
				'label'     => 'دکمه‌ی سبد خرید',
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'skin!' => 'tpl' ),
			)
		);
		$this->px( 'cart_size', 'اندازه‌ی دکمه', '--ard-cart-size', 80 );
		$this->px( 'cart_icon_size', 'اندازه‌ی آیکن دلخواه', '--ard-cart-icon', 48 );
		$this->button_colors(
			'cart',
			array(
				'color'    => '--ard-cart-color',
				'bg'       => '--ard-cart-bg',
				'border'   => '--ard-cart-border',
				'h_color'  => '--ard-cart-h-color',
				'h_bg'     => '--ard-cart-h-bg',
				'h_border' => '--ard-cart-h-border',
			)
		);
		$this->end_controls_section();
	}

	private function style_prices(): void {
		$this->start_controls_section(
			'section_style_prices',
			array(
				'label' => 'قیمت، برچسب و تایمر',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$colors = array(
			'p_new'   => array( 'رنگ قیمت جدید', '--ard-price-color' ),
			'p_old'   => array( 'رنگ قیمت قبلی (خط‌خورده)', '--ard-del' ),
			'p_line'  => array( 'رنگ خط روی قیمت قبلی', '--ard-del-line' ),
			'b_bg'    => array( 'پس‌زمینه‌ی برچسب درصد', '--ard-badge-bg' ),
			'b_fg'    => array( 'متن برچسب درصد', '--ard-badge-fg' ),
			't_bar'   => array( 'رنگ نوار زمان', '--ard-gold' ),
			't_track' => array( 'زمینه‌ی نوار زمان', '--ard-bar-track' ),
			't_clock' => array( 'رنگ شمارش معکوس', '--ard-clock-color' ),
		);
		foreach ( $colors as $key => $c ) {
			$this->add_control(
				$key,
				array(
					'label'     => $c[0],
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ard' => $c[1] . ': {{VALUE}};' ),
				)
			);
		}
		$this->px( 'p_new_size', 'اندازه‌ی قیمت جدید', '--ard-price-fs', 32 );
		$this->px( 'p_old_size', 'اندازه‌ی قیمت قبلی', '--ard-del-fs', 24 );
		$this->end_controls_section();
	}

	private function style_media(): void {
		$this->start_controls_section(
			'section_style_media',
			array(
				'label' => 'تصویر',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->px( 'img_h', 'ارتفاع تصویر', '--ard-img-h', 500, array( 'description' => 'خالی = مربع. در موبایل پیش‌فرض ۱۲۰ پیکسل.' ) );
		$this->px( 'img_pad', 'فاصله‌ی تصویر از لبه', '--ard-img-pad', 40 );
		$this->end_controls_section();
	}

	/** Rendered Elementor icon, '' when none is chosen. */
	private function icon_html( $icon ): string {
		if ( ! is_array( $icon ) || empty( $icon['value'] ) ) {
			return '';
		}
		ob_start();
		Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
		return (string) ob_get_clean();
	}



	protected function render() {
		$s      = $this->get_settings_for_display();
		$editor = \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode();
		$args   = array(
			'title'         => (string) ( $s['title'] ?? '' ),
			'subtitle'      => (string) ( $s['subtitle'] ?? '' ),
			'title_tag'     => (string) ( $s['title_tag'] ?? 'h2' ),
			'more_text'     => (string) ( $s['more_text'] ?? '' ),
			'more_url'      => (string) ( $s['more_url']['url'] ?? '' ),
			'source'        => (string) ( $s['source'] ?? 'all' ),
			'cats'          => (array) ( $s['cats'] ?? array() ),
			'include'       => (array) ( $s['include'] ?? array() ),
			'exclude'       => (array) ( $s['exclude'] ?? array() ),
			'orderby'       => (string) ( $s['orderby'] ?? 'discount' ),
			'limit'         => (int) ( $s['limit'] ?? 12 ),
			'min'           => (int) ( $s['min'] ?? 4 ),
			'skin'          => (string) ( $s['skin'] ?? 'tpl' ),
			'card_template' => (int) ( $s['card_template'] ?? 0 ),
			'layout'        => (string) ( $s['layout'] ?? 'carousel' ),
			'button'        => (string) ( $s['button'] ?? 'add' ),
			'interval'      => (int) ( $s['interval'] ?? 5 ),
			'hide_parent'   => 'yes' === ( $s['hide_parent'] ?? '' ) ? 'yes' : 'no',
			'arrows_pos'    => 'head' === ( $s['arrows_pos'] ?? '' ) ? 'head' : 'sides',
			'small_sizes'   => (string) ( $s['small_sizes'] ?? 'pill' ),
			'cart_icon'     => $this->icon_html( $s['cart_icon'] ?? null ),
			'prev_icon'     => $this->icon_html( $s['prev_icon'] ?? null ),
			'next_icon'     => $this->icon_html( $s['next_icon'] ?? null ),
			'editor'        => $editor,
		);
		foreach ( array( 'show_sizes', 'show_english', 'show_flag', 'show_progress', 'show_timer', 'head_timer', 'arrows', 'dots', 'autoplay' ) as $key ) {
			$args[ $key ] = 'yes' === ( $s[ $key ] ?? '' ) ? 'yes' : 'no';
		}

		// Cards per view / columns also go inline, so they work before Elementor regenerates its CSS file.
		$tablet = 1024;
		$mobile = 767;
		$bps    = \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints();
		if ( isset( $bps['tablet'] ) ) {
			$tablet = (int) $bps['tablet']->get_value();
		}
		if ( isset( $bps['mobile'] ) ) {
			$mobile = (int) $bps['mobile']->get_value();
		}
		$args['responsive'] = array(
			'tablet' => $tablet,
			'mobile' => $mobile,
			// Empty tablet/phone values = the control defaults (an old Elementor CSS file may hold older ones).
			// The site's own card is wider on phones (like the home page carousels: ~210px at 390).
			'pv'     => array( $s['per_view'] ?? '', ( $s['per_view_tablet'] ?? '' ) ?: 3, ( $s['per_view_mobile'] ?? '' ) ?: ( 'tpl' === $args['skin'] ? 1.75 : 2.2 ) ),
			'cols'   => array( $s['columns'] ?? '', ( $s['columns_tablet'] ?? '' ) ?: 3, ( $s['columns_mobile'] ?? '' ) ?: 2 ),
		);

		$html = ARD_Render::section( $args );
		if ( '' === $html ) {
			// Elementor drops an empty widget, but its container (background, padding) stays:
			// leave a marker so the CSS can hide the container too.
			$this->add_render_attribute( '_wrapper', 'class', 'ard-is-empty' . ( 'yes' === $args['hide_parent'] ? ' ard-hide-parent' : '' ) );
			$html = '<div class="ard-empty" hidden></div>';
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped in ARD_Render.
	}
}
