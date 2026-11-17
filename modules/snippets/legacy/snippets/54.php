<?php
// اسنیپت #54: سکشن دیسکاوری ست ها توی صفحه اصلی
defined( 'ABSPATH' ) || exit;


/**
 * Aromist — باکس دیسکاوری ست (اسنیپت Code Snippets)
 * Version: 1.0.0
 * Requires: PHP 7.4+ (المنتور اختیاری)
 *
 * باکس مشکی دیسکاوری ست با ۴ ویال؛ هر ویال یک لینک: مردانه، زنانه، اختصاصی، همه‌ی ست‌ها.
 * - المنتور: ویجت «باکس دیسکاوری ست (آرومیست)».
 * - هر جای دیگر: [aromist_discovery_box]
 *   ویژگی‌ها: label1..4 (خط دوم با |)، link1..4، name1..4، color1..4، width، intro="no"، hint="no".
 * لینک خالی = لینک خودکار (دسته‌های دیسکاوری ست مردانه/زنانه، صفحه‌ی ساخت ست اختصاصی، آرشیو همه‌ی ست‌ها).
 * ویال ۱ سمت راست است. همه‌ی اندازه‌ها با cqw (عرض خود باکس)؛ در هر ستونی همان شکل را دارد.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Aromist_Discovery_Box' ) && ! class_exists( 'ADB_Render' ) ) {

	final class Aromist_Discovery_Box {

		/** @var bool Style and script were already printed on this page. */
		private static $printed = false;

		public static function boot() {
			add_shortcode( 'aromist_discovery_box', array( __CLASS__, 'shortcode' ) );
		}

		/**
		 * Vials from right to left.
		 *
		 * @return array[]
		 */
		public static function default_vials() {
			return array(
				array( 'key' => 'men', 'label' => 'مردانه', 'name' => 'دیسکاوری ست مردانه', 'liquid' => '#c8873a' ),
				array( 'key' => 'women', 'label' => 'زنانه', 'name' => 'دیسکاوری ست زنانه', 'liquid' => '#e2a796' ),
				array( 'key' => 'custom', 'label' => 'اختصاصی', 'name' => 'دیسکاوری ست اختصاصی', 'liquid' => '#ddb45e' ),
				array( 'key' => 'all', 'label' => "همه\nست‌ها", 'name' => 'همه دیسکاوری ست ها', 'liquid' => '#efdcb2' ),
			);
		}

		/**
		 * Link of a vial left empty: the discovery-set categories and the set builder page.
		 *
		 * @param string $key men | women | custom | all.
		 * @return string
		 */
		public static function auto_url( $key ) {
			$url = '';
			switch ( $key ) {
				case 'men':
					$url = self::term_url( 'discovery-set-for-man' );
					break;
				case 'women':
					$url = self::term_url( 'discovery-set-for-woman' );
					break;
				case 'custom':
					$url = class_exists( 'ADS_Pages' ) ? ADS_Pages::builder_url() : '';
					if ( '' === $url ) {
						$page = get_page_by_path( 'build-discovery-set' );
						$url  = $page ? get_permalink( $page ) : '';
					}
					break;
				case 'all':
					$url = class_exists( 'ADS_Pages' ) ? ADS_Pages::archive_url() : '';
					if ( '' === $url ) {
						$url = self::term_url( 'discovery-set' );
					}
					break;
			}
			return (string) apply_filters( 'adb_auto_url', $url, $key );
		}

		private static function term_url( $slug ) {
			$link = taxonomy_exists( 'product_cat' ) ? get_term_link( $slug, 'product_cat' ) : '';
			return is_string( $link ) ? $link : '';
		}

		/**
		 * [aromist_discovery_box label1="مردانه" link1="/…" name1="…" color1="#c8873a" … width="480px" intro="no" hint="no"]
		 *
		 * @param array|string $atts Shortcode attributes.
		 * @return string
		 */
		public static function shortcode( $atts ) {
			$atts  = is_array( $atts ) ? $atts : array();
			$vials = array();
			foreach ( self::default_vials() as $i => $vial ) {
				$n       = $i + 1;
				$vials[] = array(
					'key'    => $vial['key'],
					'label'  => isset( $atts[ 'label' . $n ] ) ? str_replace( '|', "\n", $atts[ 'label' . $n ] ) : $vial['label'],
					'name'   => isset( $atts[ 'name' . $n ] ) ? $atts[ 'name' . $n ] : $vial['name'],
					'url'    => isset( $atts[ 'link' . $n ] ) ? $atts[ 'link' . $n ] : '',
					'liquid' => isset( $atts[ 'color' . $n ] ) ? $atts[ 'color' . $n ] : $vial['liquid'],
				);
			}
			$style = '';
			if ( ! empty( $atts['width'] ) && preg_match( '/^\d+(\.\d+)?(px|%|rem|em|vw)$/', $atts['width'] ) ) {
				$style = 'max-width:' . $atts['width'] . ';';
			}
			return self::html(
				array(
					'vials' => $vials,
					'intro' => ! isset( $atts['intro'] ) || 'no' !== $atts['intro'],
					'hint'  => ! isset( $atts['hint'] ) || 'no' !== $atts['hint'],
					'style' => $style,
				)
			);
		}

		/**
		 * @param array $args vials (4 × key, label, name, url, liquid, external, nofollow), intro, hint, style.
		 * @return string
		 */
		public static function html( array $args ) {
			$first = ! self::$printed;
			self::$printed = true;

			$uid   = 'adbx' . wp_unique_id();
			$class = 'adbox';
			if ( ! empty( $args['intro'] ) ) {
				$class .= ' adbox--intro';
			}
			if ( ! empty( $args['hint'] ) ) {
				$class .= ' adbox--hint';
			}

			$links = '';
			foreach ( array_slice( (array) $args['vials'], 0, 4 ) as $i => $vial ) {
				$links .= self::vial( $vial, $i );
			}

			$html  = $first ? '<style id="adbox-css">' . self::css() . '</style>' : '';
			$html .= '<div class="' . esc_attr( $class ) . '"' . ( empty( $args['style'] ) ? '' : ' style="' . esc_attr( $args['style'] ) . '"' ) . '>';
			$html .= '<div class="adbox__stage">';
			$html .= str_replace( '{U}', $uid, self::back_svg() );
			$html .= '<nav class="adbox__vials" aria-label="دیسکاوری ست‌ها">' . $links . '</nav>';
			$html .= str_replace( '{U}', $uid, self::front_svg() );
			$html .= '</div></div>';
			$html .= $first ? '<script id="adbox-js">' . self::js() . '</script>' : '<script>window.AromistDiscoveryBox&&window.AromistDiscoveryBox.scan();</script>';

			return $html;
		}

		private static function vial( array $vial, $i ) {
			$url = isset( $vial['url'] ) ? trim( (string) $vial['url'] ) : '';
			if ( '' === $url ) {
				$url = self::auto_url( isset( $vial['key'] ) ? $vial['key'] : '' );
			}
			if ( '' === $url ) {
				$url = '#';
			}

			$liquid = sanitize_hex_color( isset( $vial['liquid'] ) ? (string) $vial['liquid'] : '' );
			$liquid = $liquid ? $liquid : '#ddb45e';
			$style  = sprintf( '--i:%d;--liquid-hi:%s;--liquid:%s;--liquid-lo:%s;', $i, self::mix( $liquid, '#ffffff', .45 ), $liquid, self::mix( $liquid, '#000000', .38 ) );

			$rel    = array();
			$target = '';
			if ( ! empty( $vial['nofollow'] ) ) {
				$rel[] = 'nofollow';
			}
			if ( ! empty( $vial['external'] ) ) {
				$target = ' target="_blank"';
				$rel[]  = 'noopener';
			}

			$lines = preg_split( '/\r\n|\r|\n|\|/', (string) $vial['label'] );
			$label = implode( '<br>', array_map( 'esc_html', array_filter( array_map( 'trim', $lines ), 'strlen' ) ) );
			$name  = trim( (string) ( isset( $vial['name'] ) ? $vial['name'] : '' ) );

			return '<a class="adbox__vial" href="' . esc_url( $url ) . '" style="' . esc_attr( $style ) . '"'
				. ( '' !== $name ? ' aria-label="' . esc_attr( $name ) . '" title="' . esc_attr( $name ) . '"' : '' )
				. $target . ( $rel ? ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"' : '' ) . '>'
				. '<span class="adbox__body">'
				. '<span class="adbox__mist" aria-hidden="true"><i></i><i></i><i></i></span>'
				. '<span class="adbox__vessel">'
				. '<span class="adbox__cap"></span><span class="adbox__collar"></span>'
				. '<span class="adbox__glass"><span class="adbox__liquid"></span><span class="adbox__label"><span>' . $label . '</span></span></span>'
				. '</span></span></a>';
		}

		/**
		 * Mix a hex color towards another (#rrggbb, 0..1).
		 */
		private static function mix( $hex, $to, $amount ) {
			$a = self::rgb( $hex );
			$b = self::rgb( $to );
			$c = array();
			for ( $k = 0; $k < 3; $k++ ) {
				$c[] = (int) round( $a[ $k ] + ( $b[ $k ] - $a[ $k ] ) * $amount );
			}
			return vsprintf( '#%02x%02x%02x', $c );
		}

		private static function rgb( $hex ) {
			$hex = ltrim( $hex, '#' );
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
		}

		/**
		 * Back layer (viewBox 1000×880): lid with its ears and the real logo, green velvet inside, the insert.
		 * {U} = per-box id prefix so two boxes on one page do not share gradients.
		 */
		private static function back_svg() {
			return <<<'SVG'
<svg class="adbox__shell" viewBox="0 0 1000 880" aria-hidden="true" focusable="false"><defs>
<linearGradient id="{U}-foil" x1="0" y1="0" x2="1" y2=".35" spreadMethod="reflect"><stop offset="0" stop-color="#a8824f"/><stop offset=".24" stop-color="#e4c498"/><stop offset=".42" stop-color="#fff4e0"/><stop offset=".56" stop-color="#e8cba2"/><stop offset=".78" stop-color="#b8915c"/><stop offset="1" stop-color="#f2dcb6"/><animateTransform attributeName="gradientTransform" type="translate" values="-1 0;1 0" dur="7s" repeatCount="indefinite"/></linearGradient>
<linearGradient id="{U}-lid" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1c1f1e"/><stop offset="1" stop-color="#0b0d0c"/></linearGradient>
<linearGradient id="{U}-rim" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3b3e3d"/><stop offset=".55" stop-color="#232625"/><stop offset="1" stop-color="#141615"/></linearGradient>
<linearGradient id="{U}-ear-l" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#2c2e2d"/><stop offset=".45" stop-color="#151716"/><stop offset="1" stop-color="#090a0a"/></linearGradient>
<linearGradient id="{U}-ear-r" x1="1" y1="0" x2="0" y2="0"><stop offset="0" stop-color="#3d3a35"/><stop offset=".45" stop-color="#1a1b1a"/><stop offset="1" stop-color="#0a0b0b"/></linearGradient>
<linearGradient id="{U}-side-l" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#0f3329"/><stop offset="1" stop-color="#051812"/></linearGradient>
<linearGradient id="{U}-side-r" x1="1" y1="0" x2="0" y2="0"><stop offset="0" stop-color="#174536"/><stop offset="1" stop-color="#061a14"/></linearGradient>
<linearGradient id="{U}-back" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#020b08"/><stop offset=".3" stop-color="#082a21"/><stop offset="1" stop-color="#05201a"/></linearGradient>
<linearGradient id="{U}-insert" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0f4436"/><stop offset="1" stop-color="#072a21"/></linearGradient>
<radialGradient id="{U}-velvet" cx=".72" cy=".1" r=".9"><stop offset="0" stop-color="#3a8f75" stop-opacity=".22"/><stop offset="1" stop-color="#3a8f75" stop-opacity="0"/></radialGradient>
<linearGradient id="{U}-front" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#262928"/><stop offset=".5" stop-color="#151716"/><stop offset="1" stop-color="#0b0c0c"/></linearGradient>
<linearGradient id="{U}-sheen" x1="1" y1="0" x2=".2" y2="1"><stop offset="0" stop-color="#fff4e0" stop-opacity=".14"/><stop offset=".45" stop-color="#fff4e0" stop-opacity="0"/></linearGradient>
<linearGradient id="{U}-lip" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5d5f5e"/><stop offset=".4" stop-color="#2c2e2d"/><stop offset="1" stop-color="#121313"/></linearGradient>
<linearGradient id="{U}-fade" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#000" stop-opacity=".75"/><stop offset="1" stop-color="#000" stop-opacity="0"/></linearGradient>
<filter id="{U}-blur" x="-20%" y="-300%" width="140%" height="700%"><feGaussianBlur stdDeviation="14"/></filter>
<symbol id="{U}-mark" viewBox="0 0 471.2 310.7"><path fill-rule="evenodd" d="M274.2 0L396.1 0.1L403.7 1L412.4 3L421.1 6.5L433.6 14L440.3 20L449.5 32L453.6 39L457.1 47.5L458.7 53L461 63L462 75L460.9 91L458.6 101L455.1 111L449.9 121L444.1 129.3L433.1 140.3L425.1 145.9L413.1 151.5L408.1 153.1L403.1 154.1L394.1 154.9L392.5 156L468.6 303L471.2 309L471 310L469.1 310.6L429.1 310.6L426.1 309.8L422.2 304L405.6 273L403.1 267.4L388.7 241L386.2 235L381.5 227L345.1 157.4L344.1 156.5L342.1 156.1L322.1 156.3L319.4 155L317.8 153L312.4 143L296.3 111L296.3 110L297.1 109.3L299.1 108.9L396.1 109L407.1 106.3L411.1 104.2L417.7 99L423.4 90L425.1 84.3L425.7 78L425.1 70.2L423.5 65L419.6 58L413.1 51.4L407.1 47.8L403.1 46.3L397.1 45.2L298.1 45.1L296.1 45.3L294.8 47L263.1 113.6L213.5 215L191.5 262L192.1 263.1L194.1 263.4L285.1 263.4L286.7 263L287.1 262L286.7 261L273 233L257.8 200L257.4 198L258.1 195L277 157L279.1 154.2L280.1 154.4L285.4 164L332.7 260L335.9 265L336.6 267L336.2 269L332.9 274L314 309L313.1 310.2L310.1 310.6L125.1 310.6L123.6 310L123.3 309L123.8 307L133.6 286L138.1 277.6L149.9 251L166.1 218.3L172.4 204L197.8 152L211.7 122L232.1 80.4L237.1 71.4L241.3 62L246.2 53L249.1 46.1L269.5 6L271.6 2ZM150.5 1L153.1 0L190.1 0.2L192.1 0.8L212.3 36L212.5 37L210.7 41L202.1 56.7L190.4 80L189.1 81.3L188.1 81.5L185.5 78L181.9 71L171.7 49L171.1 48.3L170.1 48.5L169.1 49.7L158.3 73L99.5 192L97.1 198.1L83 226L80.1 233L68 257L42.5 310L40.1 310.7L1.1 310.5L0 309L0.6 307L34.1 236L39.1 226.6L41.8 220L142.6 15L148.1 4.3Z"/></symbol>
<symbol id="{U}-word" viewBox="0 0 471.6 64.7"><path fill-rule="evenodd" d="M168 0.1L172.1 0L178.1 1L184.9 4.1L189.1 7.7L193.1 14.2L195 19.1L196.3 28.1L196.2 37.1L195.3 44.1L193.3 51.1L191.6 54.1L189.1 57.1L183.9 61.1L180.1 63.1L174.1 64.7L163.1 64.4L158.1 62.8L153.1 59.7L149.1 55.4L145.8 50.1L144.1 44.6L143.2 39.1L143.1 24.1L144.3 18.1L147.1 11.8L149.8 8.1L155.1 3.9L162.1 0.7ZM380.5 0.1L391.1 0.7L396.1 2L400.1 4.4L402.4 7.1L402.2 9.1L400.4 13.1L399.1 14.3L398.1 14.3L395.1 11.9L390.1 9.8L387.1 9.1L382.1 8.9L379.1 9.1L376.5 10.1L373.9 12.1L372.7 14.1L372.5 17.1L373.1 19L376.1 21.9L380.1 23.9L393.1 27.9L397.6 30.1L402.3 34.1L404.3 37.1L405.4 40.1L406.2 45.1L405.6 50.1L404.1 54.1L402.1 57L397.1 61.5L393.8 63.1L388.1 64.7L378.1 64.7L373.1 63.5L368.1 61.6L363.7 59.1L360.8 57.1L360.3 56.1L363.5 49.1L365.1 47.8L366.1 48L370.1 51.6L373.1 53.4L377.1 55.2L380.1 55.6L386.1 55.4L390.1 54.1L393.1 51.6L395 48.1L395.1 44.1L393.1 41.3L391.1 39.6L388.1 37.8L373.4 33.1L370.1 31.4L366.9 29.1L364.1 26.1L362.3 22.1L362.1 13.1L364.4 8.1L366.1 6.1L371.1 2.3L375.1 0.8ZM22.8 1.1L31.1 1L33.1 2.4L49.4 47.1L52.2 56.1L54.3 61.1L54.1 63L53.1 63.6L45.1 63.6L43.1 63L41.3 60.1L40.1 54.1L38 48.1L37.1 46.8L35.1 46.2L17.1 46.2L15.2 48.1L10.1 62.9L8.1 63.6L3.1 63.5L1.1 63.2L0 62.1L20.6 3.1L21.2 2.1ZM77.2 1.1L105.1 1.8L112.1 4.8L116.7 9.1L118.6 13.1L119.4 16.1L119.4 22.1L118.9 25.1L117 29.1L114.1 33.1L111.5 35.1L108.3 36.1L107.3 37.1L107.4 38.1L109.2 40.1L115.9 53.1L120.5 60.1L120.9 62.1L120.1 63.3L111.1 63.7L109.1 62.8L106.5 59.1L101.9 49.1L97.3 41.1L96.1 39.7L94.1 38.8L89.1 38.7L88.1 38.9L87.4 40.1L87.6 62.1L87.2 63.1L86.1 63.6L78.1 63.6L76.1 63L75.5 61.1L75.7 4.1L76.1 2.1ZM430.7 1.1L466.1 1.3L470.1 1.6L471.1 2.2L471.6 8.1L471.1 9.5L470.1 9.9L457.1 9.8L455.7 10.1L454.9 11.1L454.9 58.1L455.5 61.1L455.4 62.1L454.1 63.5L445.1 63.5L443.6 62.1L443.5 11.1L442.1 9.9L430.1 9.8L428.9 8.1L428.7 3.1L429.1 2.1ZM223.6 2.1L225.1 1.4L234.1 1.4L236.3 2.1L247.2 28.1L250.7 38.1L252.1 39.5L253.4 38.1L255.5 31.1L267.1 2.8L268.1 1.9L270.1 1.4L278.1 1.5L280.4 2.1L281.1 4.1L281.2 61.1L280.3 63.1L279.1 63.6L272.1 63.6L270.6 63.1L269.8 62.1L270.1 28.1L269.9 26.1L269.3 25.1L266.9 28.1L256.8 54.1L254.1 56.5L250.1 57.1L248.1 56.1L246.2 53.1L242.5 44.1L236.2 26.1L234.1 22.9L233.1 24.1L233.1 25.1L233.5 31.1L233.5 62.1L232.7 63.1L231.1 63.6L225.1 63.6L223.8 63.1L223.2 61.1L223.2 4.1ZM314.4 2.1L316.1 1.3L329.1 1.4L330.9 2.1L331.4 3.1L331.3 6.1L328.7 9.1L328.2 11.1L328.2 55.1L329.1 57.1L331.1 58.7L331.7 60.1L331.5 62.1L330.1 63.5L316.1 63.7L314.5 63.1L313.7 62.1L313.7 60.1L316.6 56.1L317 54.1L317.1 11.1L314.1 5.1L313.9 3.1ZM167.1 9L163.1 10.1L160.1 12L156.4 17.1L154.4 24.1L154.4 40.1L156.9 48.1L161.1 53.4L166.1 55.6L174.1 55.4L178.1 53.4L180.1 51.3L182.3 48.1L183.9 44.1L185.2 36.1L185 28.1L184.2 21.1L182 16.1L178.1 11.4L175.1 9.8L172.1 9.1ZM88.1 10L87.3 12.1L87.3 28.1L88 30.1L90.1 30.6L100.1 30.4L103.1 29.4L105.1 27.8L107.7 24.1L108.2 21.1L107.4 15.1L105.3 12.1L100.1 9.8ZM26.1 15.1L18.9 36.1L19.1 36.9L21.1 37.6L33.1 37.7L34.2 37.1L34.4 36.1L31.3 27.1L29.8 24.1L27.8 16.1L27.1 15.1Z"/></symbol>
</defs>
<ellipse cx="500" cy="864" rx="460" ry="20" fill="#000" opacity=".5" filter="url(#{U}-blur)"/>
<path d="M132 334 C92 332 64 322 62 304 L64 110 C66 50 84 12 112 3 C123 0 131 6 132 20 Z" fill="url(#{U}-ear-l)"/>
<path d="M62 304 L64 110 C66 50 84 12 112 3" fill="none" stroke="#fff" stroke-opacity=".1" stroke-width="2"/>
<path d="M868 334 C908 332 936 322 938 304 L936 110 C934 50 916 12 888 3 C877 0 869 6 868 20 Z" fill="url(#{U}-ear-r)"/>
<path d="M938 304 L936 110 C934 50 916 12 888 3" fill="none" stroke="#ffe9c4" stroke-opacity=".22" stroke-width="2"/>
<path d="M130 334 V48 Q130 6 170 6 H830 Q870 6 870 48 V334 Z" fill="url(#{U}-lid)"/>
<path d="M130 54 V48 Q130 6 170 6 H830 Q870 6 870 48 V54 Z" fill="url(#{U}-rim)"/>
<rect x="130" y="54" width="740" height="2" fill="#fff" opacity=".07"/>
<rect x="130" y="56" width="740" height="26" fill="url(#{U}-fade)" opacity=".6"/>
<rect x="156" y="78" width="688" height="238" rx="6" fill="none" stroke="#e4c498" stroke-opacity=".06" stroke-width="2"/>
<use href="#{U}-mark" x="450" y="68" width="100" height="65.9" fill="url(#{U}-foil)"/>
<use href="#{U}-word" x="427" y="147" width="146" height="20" fill="url(#{U}-foil)"/>
<rect x="258" y="183" width="96" height="1.6" fill="url(#{U}-foil)"/>
<rect x="646" y="183" width="96" height="1.6" fill="url(#{U}-foil)"/>
<text class="adbox__latin" x="502" y="188" text-anchor="middle" font-size="11" letter-spacing="4" fill="url(#{U}-foil)">YOUR SCENT  |  YOUR STORY</text>
<rect x="130" y="330" width="740" height="4" fill="#2a2d2c"/>
<rect x="130" y="334" width="740" height="330" fill="url(#{U}-back)"/>
<rect x="130" y="334" width="740" height="330" fill="url(#{U}-velvet)"/>
<rect x="130" y="334" width="740" height="40" fill="url(#{U}-fade)"/>
<path d="M132 334 C104 404 84 524 72 656 L136 656 Z" fill="url(#{U}-side-l)"/>
<path d="M132 334 C104 404 84 524 72 656" fill="none" stroke="#3a3d3c" stroke-width="3"/>
<path d="M868 334 C896 404 916 524 928 656 L864 656 Z" fill="url(#{U}-side-r)"/>
<path d="M868 334 C896 404 916 524 928 656" fill="none" stroke="#5a5048" stroke-width="3"/>
<rect x="130" y="588" width="740" height="66" fill="url(#{U}-insert)"/>
<rect x="130" y="588" width="740" height="2" fill="#e4c498" opacity=".1"/>
<g fill="#021510" stroke="#e4c498" stroke-opacity=".12" stroke-width="2"><ellipse cx="230" cy="622" rx="82" ry="16"/><ellipse cx="410" cy="622" rx="82" ry="16"/><ellipse cx="590" cy="622" rx="82" ry="16"/><ellipse cx="770" cy="622" rx="82" ry="16"/></g>
</svg>
SVG;
		}

		/**
		 * Front wall with its folded top edge and the logo; covers the lower part of the vials.
		 */
		private static function front_svg() {
			return <<<'SVG'
<svg class="adbox__shell adbox__shell--front" viewBox="0 0 1000 880" aria-hidden="true" focusable="false"><g class="adbox__front">
<path d="M70 652 H930 L906 860 H94 Z" fill="url(#{U}-front)"/>
<path d="M70 652 H930 L906 860 H94 Z" fill="url(#{U}-sheen)"/>
<rect x="64" y="638" width="872" height="28" rx="14" fill="url(#{U}-lip)"/>
<rect x="86" y="641" width="828" height="2.5" rx="1.2" fill="#fff4e0" opacity=".22"/>
<use href="#{U}-mark" x="455" y="710" width="90" height="59.3" fill="url(#{U}-foil)"/>
<use href="#{U}-word" x="432" y="792" width="136" height="18.7" fill="url(#{U}-foil)"/>
</g></svg>
SVG;
		}

		private static function css() {
			return <<<'CSS'
.adbox {
--adbox-foil: #E6C498;
--adbox-label-bg: #006E58;
--adbox-label-color: #FFF4E0;
container-type: inline-size;
position: relative;
width: 100%;
max-width: 560px;
margin-inline: auto;
font-family: var(--adbox-font, inherit);
-webkit-user-select: none;
user-select: none;
}
.adbox *, .adbox *::before, .adbox *::after { box-sizing: border-box; }
.adbox__stage { position: relative; aspect-ratio: 1000 / 880; }
.adbox__shell { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; pointer-events: none; }
.adbox__shell--front { z-index: 2; }
.adbox__front { pointer-events: auto; }
.adbox__latin { font-family: var(--adbox-latin, inherit); font-weight: 500; }
.adbox__vials {
position: absolute;
z-index: 1;
top: 17.05%;
bottom: 6.82%;
left: 16%;
right: 16%;
display: flex;
justify-content: space-between;
direction: rtl;
margin: 0;
padding: 0;
}
.adbox .adbox__vial {
--liquid-hi: #eed6a3;
--liquid: #ddb45e;
--liquid-lo: #89703a;
position: relative;
flex: 0 0 20.59%;
display: block;
color: inherit;
text-decoration: none;
outline: none;
isolation: isolate;
-webkit-tap-highlight-color: transparent;
}
.adbox__vial::before {
content: "";
position: absolute;
z-index: -1;
left: -30%;
right: -30%;
top: 65%;
height: 12%;
border-radius: 50%;
background: radial-gradient(closest-side, rgba(230, 196, 152, .6), rgba(230, 196, 152, 0));
opacity: 0;
transition: opacity .6s ease;
}
.adbox__body {
position: absolute;
inset: 8.96% 0 0 0;
translate: 0 0;
transition: translate .75s cubic-bezier(.2, .85, .25, 1);
}
.adbox__vessel { position: absolute; inset: 0; filter: drop-shadow(-1.1cqw .9cqw 1cqw rgba(0, 0, 0, .75)); }
.adbox__cap {
position: absolute;
top: 0;
left: 12%;
right: 12%;
height: 11.48%;
overflow: hidden;
border-radius: 1.6cqw 1.6cqw .4cqw .4cqw;
background:
linear-gradient(180deg, rgba(255, 255, 255, .6) 0, rgba(255, 255, 255, 0) 10%),
linear-gradient(90deg, #45484e 0%, #9da1a8 10%, #f5f6f8 24%, #c3c6cb 36%, #7d8188 52%, #a9adb3 66%, #eef0f2 81%, #6a6e75 92%, #3c3f44 100%);
box-shadow: inset 0 -.3cqw .4cqw rgba(0, 0, 0, .35);
}
.adbox__cap::after {
content: "";
position: absolute;
left: 0;
right: 0;
bottom: 9%;
height: 18%;
background: repeating-linear-gradient(180deg, rgba(0, 0, 0, .3) 0 1px, rgba(255, 255, 255, .18) 1px 2px, transparent 2px 4px);
}
.adbox__cap::before {
content: "";
position: absolute;
top: -20%;
bottom: -20%;
left: -80%;
width: 45%;
transform: skewX(-18deg);
background: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, .85), rgba(255, 255, 255, 0));
opacity: 0;
}
.adbox__collar {
position: absolute;
top: 10.82%;
left: 4%;
right: 4%;
height: 2.62%;
border-radius: .45cqw;
background: linear-gradient(90deg, #505359, #d8dbdf 22%, #fff 30%, #9a9ea5 55%, #e4e6e9 78%, #484b51);
box-shadow: 0 .25cqw .4cqw rgba(0, 0, 0, .45);
}
.adbox__glass {
position: absolute;
top: 13.77%;
bottom: 0;
left: 0;
right: 0;
overflow: hidden;
border-radius: 1cqw 1cqw 7cqw 7cqw / 1cqw 1cqw 4.5cqw 4.5cqw;
background: linear-gradient(90deg, rgba(255, 255, 255, .28) 0%, rgba(255, 255, 255, .06) 18%, rgba(255, 255, 255, .02) 55%, rgba(255, 255, 255, .08) 80%, rgba(255, 238, 210, .38) 100%);
box-shadow: inset 0 0 0 .22cqw rgba(255, 255, 255, .24), inset .5cqw 0 .8cqw rgba(255, 255, 255, .08);
transition: box-shadow .4s ease;
}
.adbox__liquid {
position: absolute;
top: 3%;
left: .3cqw;
right: .3cqw;
bottom: .3cqw;
border-radius: 0 0 6.7cqw 6.7cqw / 0 0 4.3cqw 4.3cqw;
background:
linear-gradient(90deg, rgba(70, 35, 0, .55) 0%, rgba(70, 35, 0, 0) 22%, rgba(255, 250, 225, .42) 42%, rgba(255, 250, 225, 0) 58%, rgba(70, 35, 0, .16) 78%, rgba(70, 35, 0, .55) 100%),
linear-gradient(180deg, var(--liquid-hi) 0%, var(--liquid) 30%, var(--liquid-lo) 100%);
}
.adbox__liquid::before {
content: "";
position: absolute;
top: -.35cqw;
left: 0;
right: 0;
height: 1cqw;
border-radius: 50%;
background: linear-gradient(180deg, rgba(255, 250, 232, .95), rgba(255, 238, 200, .35));
}
.adbox__glass::after {
content: "";
position: absolute;
z-index: 2;
top: 3%;
bottom: 6%;
left: 11%;
width: 9%;
border-radius: 9cqw;
background: linear-gradient(180deg, rgba(255, 255, 255, .7), rgba(255, 255, 255, .25) 40%, rgba(255, 255, 255, .12));
pointer-events: none;
}
.adbox__glass::before {
content: "";
position: absolute;
z-index: 2;
top: 5%;
bottom: 10%;
right: 8%;
width: 3.5%;
border-radius: 9cqw;
background: rgba(255, 245, 225, .4);
pointer-events: none;
}
.adbox__label {
position: absolute;
z-index: 1;
top: 36%;
left: 0;
right: 0;
transform: translateY(-50%);
display: flex;
align-items: center;
justify-content: center;
min-height: calc(2.4em + 3.2cqw);
padding: 1.6cqw .6cqw;
background:
linear-gradient(90deg, rgba(0, 0, 0, .55) 0%, rgba(0, 0, 0, 0) 22%, rgba(255, 255, 255, .13) 42%, rgba(255, 255, 255, 0) 60%, rgba(0, 0, 0, .55) 100%),
var(--adbox-label-bg);
text-shadow: 0 1px 1px rgba(0, 0, 0, .35);
color: var(--adbox-label-color);
font-size: max(11px, 3.05cqw);
font-weight: 800;
line-height: 1.2;
text-align: center;
letter-spacing: 0;
}
.adbox__label::before,
.adbox__label::after {
content: "";
position: absolute;
left: 0;
right: 0;
height: max(1px, .22cqw);
background: linear-gradient(90deg, rgba(184, 145, 92, .45), #fff4e0 35%, var(--adbox-foil) 65%, rgba(184, 145, 92, .45));
}
.adbox__label::before { top: .7cqw; }
.adbox__label::after { bottom: .7cqw; }
.adbox__label > span { display: block; white-space: nowrap; }
.adbox__mist { position: absolute; left: 50%; top: 0; width: 0; height: 0; pointer-events: none; }
.adbox__mist i {
position: absolute;
left: -2.6cqw;
top: -2.6cqw;
width: 5.2cqw;
height: 5.2cqw;
border-radius: 50%;
background: radial-gradient(closest-side, rgba(255, 251, 240, .9), rgba(255, 244, 220, 0));
opacity: 0;
}
.adbox__vial:hover .adbox__body,
.adbox__vial:focus-visible .adbox__body,
.adbox__vial:active .adbox__body { translate: 0 -6.5%; }
.adbox__vial:hover::before,
.adbox__vial:focus-visible::before { opacity: 1; }
.adbox__vial:hover .adbox__cap::before,
.adbox__vial:focus-visible .adbox__cap::before { animation: adbox-glint .9s ease-out .15s; }
.adbox__vial:hover .adbox__mist i,
.adbox__vial:focus-visible .adbox__mist i { animation: adbox-mist 2.4s ease-out infinite; }
.adbox__vial:hover .adbox__mist i:nth-child(2),
.adbox__vial:focus-visible .adbox__mist i:nth-child(2) { --dx: -1.8cqw; animation-delay: .8s; }
.adbox__vial:hover .adbox__mist i:nth-child(3),
.adbox__vial:focus-visible .adbox__mist i:nth-child(3) { --dx: 2.1cqw; animation-delay: 1.6s; }
.adbox__vial:focus-visible .adbox__glass {
box-shadow: inset 0 0 0 .22cqw rgba(255, 255, 255, .24), 0 0 0 .45cqw var(--adbox-foil);
}
.adbox--wait .adbox__body { opacity: 0; }
.adbox--play .adbox__body { animation: adbox-rise 1.3s cubic-bezier(.2, .9, .2, 1) calc(.15s + var(--i, 0) * .16s) backwards; }
@keyframes adbox-rise {
from { transform: translateY(26%); opacity: 0; }
35% { opacity: 1; }
to { transform: none; opacity: 1; }
}
@keyframes adbox-glint {
from { left: -80%; opacity: 1; }
to { left: 140%; opacity: 1; }
}
@keyframes adbox-mist {
0% { opacity: 0; transform: translate(0, 0) scale(.3); }
18% { opacity: .9; }
100% { opacity: 0; transform: translate(var(--dx, .4cqw), -11cqw) scale(2.6); }
}
@keyframes adbox-hint {
0%, 9%, 100% { transform: none; }
4.5% { transform: translateY(-4.5%); }
}
@media (hover: none) {
.adbox--hint .adbox__body { animation: adbox-hint 9s ease-in-out calc(2.8s + var(--i, 0) * .4s) infinite; }
.adbox--hint.adbox--play .adbox__body {
animation:
adbox-hint 9s ease-in-out calc(2.8s + var(--i, 0) * .4s) infinite,
adbox-rise 1.3s cubic-bezier(.2, .9, .2, 1) calc(.15s + var(--i, 0) * .16s) backwards;
}
}
@media (prefers-reduced-motion: reduce) {
.adbox .adbox__body { animation: none !important; transition: none; opacity: 1 !important; }
.adbox__mist, .adbox__cap::before { display: none; }
}
CSS;
		}

		private static function js() {
			return <<<'JS'
(function () {
'use strict';
if (window.AromistDiscoveryBox) {
window.AromistDiscoveryBox.scan();
return;
}
var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
/* A word that still does not fit the glass gets just enough smaller to fit. */
function fit(box) {
Array.prototype.forEach.call(box.querySelectorAll('.adbox__label > span'), function (span) {
span.style.fontSize = '';
var label = span.parentNode;
var cs = getComputedStyle(label);
var room = label.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
var need = span.scrollWidth;
if (room > 0 && need > room) {
span.style.fontSize = (room / need * 0.97).toFixed(3) + 'em';
}
});
}
function init(box) {
if (!box || box.adboxReady) {
return;
}
box.adboxReady = true;
fit(box);
if (document.fonts && document.fonts.ready) {
document.fonts.ready.then(function () { fit(box); });
}
if ('ResizeObserver' in window) {
var width = 0;
new ResizeObserver(function (entries) {
var w = Math.round(entries[0].contentRect.width);
if (w !== width) {
width = w;
fit(box);
}
}).observe(box);
}
if (reduce) {
Array.prototype.forEach.call(box.querySelectorAll('svg'), function (svg) {
if (svg.pauseAnimations) { svg.pauseAnimations(); }
});
return;
}
/* The vials rise out of the box the first time it scrolls into view. */
if (!box.classList.contains('adbox--intro') || !('IntersectionObserver' in window)) {
return;
}
box.classList.add('adbox--wait');
var io = new IntersectionObserver(function (entries) {
entries.forEach(function (entry) {
if (entry.isIntersecting) {
box.classList.remove('adbox--wait');
box.classList.add('adbox--play');
io.disconnect();
}
});
}, { threshold: 0.35 });
io.observe(box);
}
function scan() {
Array.prototype.forEach.call(document.querySelectorAll('.adbox'), init);
}
window.AromistDiscoveryBox = { scan: scan };
scan();
document.addEventListener('DOMContentLoaded', scan);
/* The Elementor editor re-renders the widget after the page has loaded. */
function hook() {
window.elementorFrontend.hooks.addAction('frontend/element_ready/aromist_discovery_box.default', function ($scope) {
init($scope[0].querySelector('.adbox'));
});
}
if (window.elementorFrontend && window.elementorFrontend.hooks) {
hook();
} else if (window.jQuery) {
window.jQuery(window).on('elementor/frontend/init', hook);
}
})();
JS;
		}
	}

	Aromist_Discovery_Box::boot();

	// Elementor widget «باکس دیسکاوری ست (آرومیست)». Declared here (not inside the class) because PHP does not allow nested class declarations.
	add_action(
		'elementor/widgets/register',
		static function ( $widgets_manager ) {
			if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
				return;
			}
			if ( ! class_exists( 'Aromist_Discovery_Box_Widget' ) ) {

				class Aromist_Discovery_Box_Widget extends \Elementor\Widget_Base {

					public function get_name() {
						return 'aromist_discovery_box';
					}

					public function get_title() {
						return 'باکس دیسکاوری ست (تکپینو)';
					}

					public function get_icon() {
						return 'eicon-product-images';
					}

					public function get_categories() {
						return array( 'techpino' );
					}

					public function get_keywords() {
						return array( 'discovery', 'box', 'perfume', 'vial', 'دیسکاوری', 'باکس', 'ست', 'ویال', 'عطر' );
					}

					protected function register_controls() {
						$controls  = '\Elementor\Controls_Manager';
						$positions = array( 'ویال ۱ (سمت راست)', 'ویال ۲', 'ویال ۳', 'ویال ۴ (سمت چپ)' );

						$this->start_controls_section( 'section_vials', array( 'label' => 'ویال‌ها' ) );
						$this->add_control(
							'vials_note',
							array(
								'type'            => $controls::RAW_HTML,
								'raw'             => 'لینک خالی = لینک خودکار (دسته‌ی دیسکاوری ست مردانه / زنانه، صفحه‌ی ساخت ست اختصاصی، آرشیو همه‌ی ست‌ها). برای شکستن متن برچسب به دو خط، Enter بزنید.',
								'content_classes' => 'elementor-descriptor',
							)
						);
						foreach ( Aromist_Discovery_Box::default_vials() as $i => $vial ) {
							$n = $i + 1;
							$this->add_control( 'heading_' . $n, array( 'label' => $positions[ $i ], 'type' => $controls::HEADING, 'separator' => 'before' ) );
							$this->add_control( 'label_' . $n, array( 'label' => 'متن روی شیشه', 'type' => $controls::TEXTAREA, 'rows' => 2, 'default' => $vial['label'] ) );
							$this->add_control(
								'link_' . $n,
								array(
									'label'       => 'لینک',
									'type'        => $controls::URL,
									'placeholder' => 'خالی = لینک خودکار',
									'dynamic'     => array( 'active' => true ),
									'default'     => array( 'url' => '' ),
								)
							);
							$this->add_control( 'name_' . $n, array( 'label' => 'نام کامل (سئو و صفحه‌خوان)', 'type' => $controls::TEXT, 'default' => $vial['name'], 'label_block' => true ) );
							$this->add_control( 'liquid_' . $n, array( 'label' => 'رنگ عطر داخل شیشه', 'type' => $controls::COLOR, 'default' => $vial['liquid'] ) );
						}
						$this->end_controls_section();

						$this->start_controls_section( 'section_layout', array( 'label' => 'اندازه و حرکت' ) );
						$this->add_responsive_control(
							'max_width',
							array(
								'label'      => 'حداکثر عرض باکس',
								'type'       => $controls::SLIDER,
								'size_units' => array( 'px', '%' ),
								'range'      => array(
									'px' => array( 'min' => 240, 'max' => 1000 ),
									'%'  => array( 'min' => 30, 'max' => 100 ),
								),
								'default'    => array( 'unit' => 'px', 'size' => 560 ),
								'selectors'  => array( '{{WRAPPER}} .adbox' => 'max-width: {{SIZE}}{{UNIT}};' ),
							)
						);
						$this->add_responsive_control(
							'align',
							array(
								'label'                => 'جای باکس',
								'type'                 => $controls::CHOOSE,
								'options'              => array(
									'right'  => array( 'title' => 'راست', 'icon' => 'eicon-h-align-right' ),
									'center' => array( 'title' => 'وسط', 'icon' => 'eicon-h-align-center' ),
									'left'   => array( 'title' => 'چپ', 'icon' => 'eicon-h-align-left' ),
								),
								'default'              => 'center',
								'selectors_dictionary' => array(
									'right'  => 'margin-left: auto; margin-right: 0;',
									'center' => 'margin-left: auto; margin-right: auto;',
									'left'   => 'margin-left: 0; margin-right: auto;',
								),
								'selectors'            => array( '{{WRAPPER}} .adbox' => '{{VALUE}}' ),
							)
						);
						$this->add_control( 'intro', array( 'label' => 'بالا آمدن ویال‌ها هنگام دیده شدن', 'type' => $controls::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'separator' => 'before' ) );
						$this->add_control( 'hint', array( 'label' => 'تکان راهنما در موبایل', 'description' => 'گوشی hover ندارد؛ هر چند ثانیه ویال‌ها یکی‌یکی کمی تکان می‌خورند که معلوم شود لمس‌شدنی‌اند.', 'type' => $controls::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ) );
						$this->end_controls_section();

						$this->start_controls_section( 'section_colors', array( 'label' => 'رنگ برچسب‌ها', 'tab' => $controls::TAB_STYLE ) );
						$this->add_control( 'label_bg', array( 'label' => 'رنگ برچسب', 'type' => $controls::COLOR, 'default' => '#006E58', 'selectors' => array( '{{WRAPPER}} .adbox' => '--adbox-label-bg: {{VALUE}};' ) ) );
						$this->add_control( 'label_color', array( 'label' => 'رنگ متن', 'type' => $controls::COLOR, 'default' => '#FFF4E0', 'selectors' => array( '{{WRAPPER}} .adbox' => '--adbox-label-color: {{VALUE}};' ) ) );
						$this->end_controls_section();
					}

					protected function render() {
						$settings = $this->get_settings_for_display();
						$vials    = array();
						foreach ( Aromist_Discovery_Box::default_vials() as $i => $vial ) {
							$n       = $i + 1;
							$link    = isset( $settings[ 'link_' . $n ] ) && is_array( $settings[ 'link_' . $n ] ) ? $settings[ 'link_' . $n ] : array();
							$vials[] = array(
								'key'      => $vial['key'],
								'label'    => isset( $settings[ 'label_' . $n ] ) ? $settings[ 'label_' . $n ] : $vial['label'],
								'name'     => isset( $settings[ 'name_' . $n ] ) ? $settings[ 'name_' . $n ] : $vial['name'],
								'url'      => isset( $link['url'] ) ? $link['url'] : '',
								'external' => ! empty( $link['is_external'] ),
								'nofollow' => ! empty( $link['nofollow'] ),
								'liquid'   => ! empty( $settings[ 'liquid_' . $n ] ) ? $settings[ 'liquid_' . $n ] : $vial['liquid'],
							);
						}
						// In the editor the vials show at once (no waiting for the scroll).
						$editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
						echo Aromist_Discovery_Box::html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
							array(
								'vials' => $vials,
								'intro' => ! $editor && 'yes' === ( isset( $settings['intro'] ) ? $settings['intro'] : 'yes' ),
								'hint'  => 'yes' === ( isset( $settings['hint'] ) ? $settings['hint'] : 'yes' ),
							)
						);
					}
				}
			}
			$widgets_manager->register( new Aromist_Discovery_Box_Widget() );
		}
	);
}
