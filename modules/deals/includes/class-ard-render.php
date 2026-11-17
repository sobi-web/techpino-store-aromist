<?php
/**
 * HTML of the deals section (Elementor widget and [aromist_deals] shortcode share it).
 *
 * @package AromistDeals
 */

defined( 'ABSPATH' ) || exit;

final class ARD_Render {

	public static function defaults(): array {
		return array(
			'title'         => 'فرصت‌های تکرار نشدنی',
			'subtitle'      => 'از میان عطرهای محبوب، انتخاب‌هایی ویژه با تخفیف‌هایی که به زیبایی لحظه‌هایت می‌افزایند.',
			'title_tag'     => 'h2',
			'more_text'     => '',
			'more_url'      => '',
			'source'        => 'all',
			'cats'          => array(),
			'include'       => array(),
			'exclude'       => array(),
			'orderby'       => 'discount',
			'limit'         => 12,
			'min'           => 4,
			'skin'          => 'tpl',      // tpl = the site's own card template (Elementor loop item) | site = a copy of it | ref = the reference design.
			'card_template' => 0,          // Loop-item template for «tpl» (0 = «box-product-1»).
			'layout'        => 'carousel', // carousel | grid.
			'show_sizes'    => 'yes',
			'show_english'  => 'yes',
			'show_flag'     => 'no',       // «۱۵٪ تخفیف» chip on the image.
			'show_timer'    => 'yes',
			'show_progress' => 'yes',
			'head_timer'    => 'no',       // Countdown of the soonest-ending deal in the header.
			'button'        => 'add',      // add = AJAX add the chosen size | link = open the product.
			'arrows'        => 'yes',
			'arrows_pos'    => 'sides',    // sides = middle of the carousel, right and left | head = next to the title.
			'cart_icon'     => '',         // HTML of a custom icon (Elementor); '' = the shop card's own bag icon.
			'prev_icon'     => '',
			'next_icon'     => '',
			'small_sizes'   => 'pill',     // Narrow cards (phones): pill = only the discounted size | circles | none.
			'dots'          => 'yes',
			'autoplay'      => 'no',
			'interval'      => 5,
			'hide_parent'   => 'no',
			'editor'        => false,      // Elementor editor: explain instead of rendering nothing.
			'responsive'    => array(),    // Elementor: per_view / columns per breakpoint, printed inline.
		);
	}

	/**
	 * Whole section, or '' when there are fewer deals than the minimum.
	 *
	 * @param array $a See defaults().
	 */
	public static function section( array $a ): string {
		$a      = wp_parse_args( $a, self::defaults() );
		$min    = max( 1, (int) $a['min'] );
		$result = ARD_Deals::query(
			array(
				'source'  => $a['source'],
				'cats'    => $a['cats'],
				'include' => $a['include'],
				'exclude' => $a['exclude'],
				'orderby' => $a['orderby'],
				'limit'   => max( $min, (int) $a['limit'] ),
			)
		);

		// The page shows (or hides) this section depending on the deals: purge it when they change.
		do_action( 'litespeed_tag_add', 'ard_deals' );

		if ( count( $result['items'] ) < $min ) {
			if ( $a['editor'] ) {
				return '<div class="ard-editor-note">' . esc_html(
					sprintf(
						/* translators: 1: deals found, 2: minimum */
						'بخش «تخفیف‌های ویژه»: الان %1$s محصول تخفیف‌دار با این تنظیمات پیدا شد و حداقل %2$s لازم است؛ این بخش فعلاً در سایت نمایش داده نمی‌شود.',
						self::num( (string) $result['total'] ),
						self::num( (string) $min )
					)
				) . '</div>';
			}
			return '';
		}

		wp_enqueue_style( 'ard-deals' );
		wp_enqueue_script( 'ard-deals' );

		$skin   = in_array( $a['skin'], array( 'tpl', 'site', 'ref' ), true ) ? $a['skin'] : 'tpl';
		$tpl    = 'tpl' === $skin ? self::template_id( (int) $a['card_template'] ) : 0;
		if ( 'tpl' === $skin && ! $tpl ) {
			$skin = 'site'; // No Elementor / no loop template: the built-in copy of the card.
		}
		if ( $tpl ) {
			$a['card_template'] = $tpl;
			if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
				\Elementor\Core\Files\CSS\Post::create( $tpl )->enqueue();
			}
		}
		$layout = 'grid' === $a['layout'] ? 'grid' : 'carousel';
		$timers = false;
		foreach ( $result['items'] as $item ) {
			$timers = $timers || (bool) array_filter( wp_list_pluck( $item['sizes'], 'to' ) );
		}
		$attrs = array(
			'class'         => 'ard ard--' . $skin . ' ard--' . $layout . ( $timers ? '' : ' ard--no-timers' )
				. ' ard--small-' . ( in_array( $a['small_sizes'], array( 'pill', 'circles', 'none' ), true ) ? $a['small_sizes'] : 'pill' )
				. ( 'carousel' === $layout && 'yes' === $a['arrows'] && 'head' !== $a['arrows_pos'] ? ' ard--side-arrows' : '' ),
			'data-ard'      => '',
			'data-min'      => $min,
			'data-autoplay' => 'carousel' === $layout && 'yes' === $a['autoplay'] ? max( 2, (int) $a['interval'] ) * 1000 : 0,
			'data-hide'     => 'yes' === $a['hide_parent'] ? 'parent' : 'self',
		);
		if ( '' !== trim( (string) $a['title'] ) ) {
			$attrs['aria-label'] = wp_strip_all_tags( (string) $a['title'] );
		}

		$style = self::responsive_style( (array) $a['responsive'] );
		if ( $style ) {
			$attrs['id'] = 'ard-' . substr( md5( wp_json_encode( $a ) ), 0, 8 );
			$style       = str_replace( '#ID', '#' . $attrs['id'], $style );
		}

		$html = $style . '<section';
		foreach ( $attrs as $k => $v ) {
			$html .= ' ' . $k . ( '' === $v ? '' : '="' . esc_attr( (string) $v ) . '"' );
		}
		$html .= '>' . self::head( $a, $result['items'], $layout );

		$html .= '<div class="ard-stage"><div class="ard-track" role="list">';
		foreach ( $result['items'] as $item ) {
			$html .= '<div class="ard-slide" role="listitem">' . ( $tpl ? self::template_card( $item, $a ) : self::card( $item, $a, $skin ) ) . '</div>';
		}
		$html .= '</div>';
		if ( 'carousel' === $layout && 'yes' === $a['arrows'] && 'head' !== $a['arrows_pos'] ) {
			$html .= self::arrows( $a, 'ard-arrow' );
		}
		$html .= '</div>';

		if ( 'carousel' === $layout && 'yes' === $a['dots'] ) {
			$html .= '<div class="ard-dots" aria-hidden="true"></div>';
		}
		return $html . '</section>';
	}

	/** <style> with cards per view / columns for desktop, tablet and phone ('' when nothing is set). */
	private static function responsive_style( array $r ): string {
		if ( empty( $r['pv'] ) && empty( $r['cols'] ) ) {
			return '';
		}
		$num  = function ( $v ) {
			return is_numeric( $v ) && (float) $v > 0 ? rtrim( rtrim( number_format( (float) $v, 2, '.', '' ), '0' ), '.' ) : '';
		};
		$rule = function ( $i ) use ( $r, $num ) {
			$out = '';
			$pv  = $num( $r['pv'][ $i ] ?? '' );
			$col = $num( $r['cols'][ $i ] ?? '' );
			if ( '' !== $pv ) {
				$out .= '--ard-pv:' . $pv . ';';
			}
			if ( '' !== $col ) {
				$out .= '--ard-cols:' . (int) $col . ';';
			}
			return $out ? '#ID{' . $out . '}' : '';
		};
		// Each value only on its own screen size: an empty tablet/phone value keeps the stylesheet default.
		$tb  = (int) ( $r['tablet'] ?? 1024 );
		$mb  = (int) ( $r['mobile'] ?? 767 );
		$css = '';
		$d   = $rule( 0 );
		$t   = $rule( 1 );
		$m   = $rule( 2 );
		if ( $d ) {
			$css .= '@media (min-width:' . ( $tb + 1 ) . 'px){' . $d . '}';
		}
		if ( $t ) {
			$css .= '@media (min-width:' . ( $mb + 1 ) . 'px) and (max-width:' . $tb . 'px){' . $t . '}';
		}
		if ( $m ) {
			$css .= '@media (max-width:' . $mb . 'px){' . $m . '}';
		}
		return $css ? '<style>' . $css . '</style>' : '';
	}

	private static function head( array $a, array $items, string $layout ): string {
		$tag   = in_array( $a['title_tag'], array( 'h1', 'h2', 'h3', 'h4', 'div', 'p' ), true ) ? $a['title_tag'] : 'h2';
		$title = trim( (string) $a['title'] );
		$sub   = trim( (string) $a['subtitle'] );
		$side  = '';

		if ( 'yes' === $a['head_timer'] ) {
			$ends = array_filter( wp_list_pluck( $items, 'to' ) );
			if ( $ends ) {
				$side .= '<div class="ard-head__timer" data-ard-countdown data-to="' . esc_attr( (string) min( $ends ) ) . '">'
					. '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="13" r="8" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M12 9v4.2l2.6 1.6M9.5 2.8h5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>'
					. '<span>' . esc_html__( 'تا پایان پیشنهاد', 'aromist-deals' ) . '</span>'
					. '<b class="ard-clock" dir="ltr">' . esc_html( self::clock( min( $ends ) ) ) . '</b></div>';
			}
		}
		if ( '' !== trim( (string) $a['more_text'] ) && '' !== trim( (string) $a['more_url'] ) ) {
			$side .= '<a class="ard-head__more" href="' . esc_url( (string) $a['more_url'] ) . '">' . esc_html( (string) $a['more_text'] )
				. '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 6.5 9 12l5.5 5.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a>';
		}
		if ( 'carousel' === $layout && 'yes' === $a['arrows'] && 'head' === $a['arrows_pos'] ) {
			$side .= '<div class="ard-nav">' . self::arrows( $a, 'ard-nav__btn' ) . '</div>';
		}

		if ( '' === $title && '' === $sub && '' === $side ) {
			return '';
		}
		$html = '<div class="ard-head"><div class="ard-head__text">';
		if ( '' !== $title ) {
			$html .= '<' . $tag . ' class="ard-head__title">' . esc_html( $title ) . '</' . $tag . '>';
		}
		if ( '' !== $sub ) {
			$html .= '<p class="ard-head__sub">' . esc_html( $sub ) . '</p>';
		}
		$html .= '</div>';
		if ( '' !== $side ) {
			$html .= '<div class="ard-head__side">' . $side . '</div>';
		}
		return $html . '</div>';
	}

	/** Previous (right, in RTL) and next (left) buttons. */
	private static function arrows( array $a, string $class ): string {
		$chev = function ( $d ) {
			return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' . $d . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		};
		$prev = '' !== $a['prev_icon'] ? $a['prev_icon'] : $chev( 'M9.5 6.5 15 12l-5.5 5.5' );
		$next = '' !== $a['next_icon'] ? $a['next_icon'] : $chev( 'M14.5 6.5 9 12l5.5 5.5' );
		return '<button type="button" class="' . $class . ' ' . $class . '--prev" data-ard-prev aria-label="' . esc_attr__( 'قبلی', 'aromist-deals' ) . '">' . $prev . '</button>'
			. '<button type="button" class="' . $class . ' ' . $class . '--next" data-ard-next aria-label="' . esc_attr__( 'بعدی', 'aromist-deals' ) . '">' . $next . '</button>';
	}

	/** The bag icon of the shop's own product card (circle + bag); the circle draws itself on hover. */
	public static function site_cart_icon(): string {
		return '<svg class="ard-bag" viewBox="0 0 200 200" aria-hidden="true"><circle class="ard-bag__ring" cx="100" cy="100" r="85" fill="none" stroke="currentColor" stroke-width="3.5"/>'
			. '<g transform="matrix(0.959081,0,0,1.074453,99.33,92.82)" fill="none" stroke="currentColor" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round">'
			. '<path d="M -28 -10 L -32 25 C -32 30 -28 35 -23 35 L 23 35 C 28 35 32 30 32 25 L 28 -10 Z"/><path d="M -15 -10 C -15 -22 -10 -28 0 -28 C 10 -28 15 -22 15 -10"/></g></svg>';
	}

	/** A published loop-item template id: the given one, else the site's «box-product-1». */
	public static function template_id( int $id ): int {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->documents ) ) {
			return 0;
		}
		$ok = function ( $tid ) {
			return $tid && 'publish' === get_post_status( $tid ) && 'loop-item' === get_post_meta( $tid, '_elementor_template_type', true );
		};
		if ( $ok( $id ) ) {
			return $id;
		}
		$found = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => 'publish',
				'title'          => 'box-product-1',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		return $found && $ok( (int) $found[0] ) ? (int) $found[0] : 0;
	}

	/**
	 * The site's own card (Elementor loop item) for one deal, like every other product box, plus
	 * the countdown (moved inside the card by the script) and the «٪ تخفیف» chip.
	 */
	private static function template_card( array $item, array $a ): string {
		$doc = \Elementor\Plugin::$instance->documents->get_doc_for_frontend( (int) $a['card_template'] );
		if ( ! $doc ) {
			return self::card( $item, $a, 'site' );
		}
		global $post;
		$keep_post    = $post;
		$keep_product = $GLOBALS['product'] ?? null;
		$post         = get_post( $item['id'] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );
		$GLOBALS['product'] = wc_get_product( $item['id'] );

		\Elementor\Plugin::$instance->documents->switch_to_document( $doc );
		ob_start();
		$doc->print_content();
		$inner = (string) ob_get_clean();
		\Elementor\Plugin::$instance->documents->restore_document();

		$post = $keep_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( $keep_post ) {
			setup_postdata( $keep_post );
		} else {
			wp_reset_postdata();
		}
		$GLOBALS['product'] = $keep_product;

		$deal = $item['sizes'][ $item['deal'] ];
		$data = array();
		foreach ( $item['sizes'] as $s ) {
			$data[] = array(
				'v'    => $s['vid'],
				'pct'  => $s['pct'],
				'in'   => $s['in'] ? 1 : 0,
				'from' => $s['from'],
				'to'   => $s['to'],
			);
		}
		$extra = '';
		if ( 'yes' === $a['show_flag'] ) {
			$extra .= '<span class="ard-card__flag" data-ard-flag>' . esc_html( self::num( (string) $item['max_pct'] ) . '٪ تخفیف' ) . '</span>';
		}
		if ( 'yes' === $a['show_timer'] || 'yes' === $a['show_progress'] ) {
			$extra .= self::timer( $deal, $a );
		}
		return '<div class="ard-tpl" data-ard-card data-ard-tpl data-pid="' . esc_attr( (string) $item['id'] ) . '" data-active="' . esc_attr( (string) $item['deal'] ) . '" data-sizes="' . esc_attr( wp_json_encode( $data ) ) . '">'
			. $inner . $extra . '</div>';
	}

	/** One product card. */
	public static function card( array $item, array $a, string $skin ): string {
		$sizes = $item['sizes'];
		$deal  = $sizes[ $item['deal'] ];
		$data  = array();
		foreach ( $sizes as $s ) {
			$data[] = array(
				'v'    => $s['vid'],
				'p'    => $s['sale'] ? ARD_Price::markup( $s['pct'], $s['regular'], $s['price'] ) : ARD_Price::plain( $s['price'] ),
				'pct'  => $s['pct'],
				'in'   => $s['in'] ? 1 : 0,
				'from' => $s['from'],
				'to'   => $s['to'],
				'u'    => $s['url'],
				'l'    => self::size_text( $s, $skin ),
				'pl'   => self::size_text( $s, 'ref' ),
			);
		}
		$name = $item['name'];

		$html  = '<article class="ard-card" data-ard-card data-pid="' . esc_attr( (string) $item['id'] ) . '" data-active="' . esc_attr( (string) $item['deal'] ) . '" data-sizes="' . esc_attr( wp_json_encode( $data ) ) . '">';
		$html .= '<a class="ard-card__media" href="' . esc_url( $deal['url'] ) . '" tabindex="-1" aria-hidden="true" data-ard-link>' . self::image( $item, $skin );
		if ( 'yes' === $a['show_flag'] ) {
			$html .= '<span class="ard-card__flag">' . esc_html( self::num( (string) $item['max_pct'] ) . '٪ تخفیف' ) . '</span>';
		}
		$html .= '</a>';

		$html .= '<div class="ard-card__body">';
		$html .= '<h3 class="ard-card__title"><a href="' . esc_url( $deal['url'] ) . '" data-ard-link>' . esc_html( $name ) . '</a></h3>';
		if ( 'yes' === $a['show_english'] && '' !== $item['english'] ) {
			$html .= '<div class="ard-card__en" dir="ltr">' . esc_html( $item['english'] ) . '</div>';
		}
		if ( 'yes' === $a['show_sizes'] && '' !== $deal['label'] ) {
			if ( 'site' === $skin ) {
				$html .= self::size_buttons( $sizes, $item['deal'] );
			}
			// The reference design shows one size; the site box shows it too on narrow cards (phones).
			$html .= '<div class="ard-card__pills"><span class="ard-pill" data-ard-pill>' . esc_html( self::size_text( $deal, 'ref' ) ) . '</span></div>';
		}
		$html .= '</div>';

		$html .= '<div class="ard-card__foot"><div class="ard-card__price" data-ard-price>' . $data[ $item['deal'] ]['p'] . '</div>' . self::cart_button( $item, $deal, $a ) . '</div>';

		if ( 'yes' === $a['show_timer'] || 'yes' === $a['show_progress'] ) {
			$html .= self::timer( $deal, $a );
		}
		return $html . '</article>';
	}

	private static function image( array $item, string $skin ): string {
		if ( 'site' === $skin && function_exists( 'ahi_render' ) ) {
			// The shop's own card image: the notes image appears on hover.
			return ahi_render(
				$item['id'],
				array(
					'size'    => 'woocommerce_thumbnail',
					'link'    => false,
					'trigger' => 'image',
				)
			);
		}
		$img = $item['image_id']
			? wp_get_attachment_image( $item['image_id'], 'woocommerce_thumbnail', false, array( 'class' => 'ard-card__img', 'alt' => $item['name'], 'loading' => 'lazy' ) )
			: wc_placeholder_img( 'woocommerce_thumbnail', array( 'class' => 'ard-card__img' ) );
		return '<span class="ard-card__frame">' . $img . '</span>';
	}

	/** Persian digits, unless the shop chose Latin digits. */
	private static function num( string $s ): string {
		return 'latin' === ARD_Settings::get( 'digits' ) ? $s : ARD_Settings::fa_digits( $s );
	}

	/** «۵۰» + «میل» for the circles, «۵۰ میلی‌لیتر» for the pill. */
	private static function size_text( array $s, string $skin ): string {
		if ( null === $s['num'] ) {
			return (string) $s['label'];
		}
		$num = self::num( rtrim( rtrim( number_format( $s['num'], 2, '.', '' ), '0' ), '.' ) );
		return 'ref' === $skin ? $num . ' میلی‌لیتر' : $num;
	}

	private static function size_buttons( array $sizes, int $active ): string {
		// Up to 5 circles fit the card; the discounted size is always one of them.
		$keys = array_slice( array_keys( $sizes ), 0, 5 );
		if ( ! in_array( $active, $keys, true ) ) {
			$keys[ count( $keys ) - 1 ] = $active;
		}
		$shown = array_intersect_key( $sizes, array_flip( $keys ) );
		$html  = '<div class="ard-sizes" role="radiogroup" aria-label="' . esc_attr__( 'حجم', 'aromist-deals' ) . '">';
		foreach ( $shown as $i => $s ) {
			$cls   = 'ard-size' . ( $s['sale'] && $s['in'] ? ' is-sale' : '' ) . ( $s['in'] ? '' : ' is-out' ) . ( $i === $active ? ' is-active' : '' );
			$title = $s['label'] . ( $s['in'] ? ( $s['sale'] ? ' — ' . self::num( (string) $s['pct'] ) . '٪ تخفیف' : '' ) : ' (ناموجود)' );
			$html .= '<button type="button" class="' . esc_attr( $cls ) . '" role="radio" aria-checked="' . ( $i === $active ? 'true' : 'false' ) . '" data-i="' . esc_attr( (string) $i ) . '" title="' . esc_attr( $title ) . '">';
			if ( null !== $s['num'] ) {
				$html .= '<b>' . esc_html( self::size_text( $s, 'site' ) ) . '</b><small>میل</small>';
			} else {
				$html .= '<b>' . esc_html( $s['label'] ) . '</b>';
			}
			$html .= '</button>';
		}
		$rest = count( $sizes ) - count( $shown );
		if ( $rest > 0 ) {
			$html .= '<span class="ard-size ard-size--more">+' . esc_html( self::num( (string) $rest ) ) . '</span>';
		}
		return $html . '</div>';
	}

	private static function cart_button( array $item, array $deal, array $a ): string {
		$custom = '' !== (string) $a['cart_icon'];
		$svg    = $custom ? '<span class="ard-card__icon">' . $a['cart_icon'] . '</span>' : self::site_cart_icon();
		/* translators: %s: product name */
		$label = sprintf( __( 'افزودن «%s» به سبد خرید', 'aromist-deals' ), $item['name'] );
		if ( 'link' === $a['button'] || ! in_array( $item['type'], array( 'variable', 'simple' ), true ) ) {
			return '<a class="ard-card__cart' . ( $custom ? ' is-custom' : '' ) . '" href="' . esc_url( $deal['url'] ) . '" data-ard-link aria-label="' . esc_attr( $item['name'] ) . '">' . $svg . '</a>';
		}
		return '<button type="button" class="ard-card__cart' . ( $custom ? ' is-custom' : '' ) . '" data-ard-add data-product_id="' . esc_attr( (string) $item['id'] ) . '" data-product_name="' . esc_attr( $item['name'] ) . '" aria-label="' . esc_attr( $label ) . '"' . ( $deal['in'] ? '' : ' disabled' ) . '>' . $svg . '</button>';
	}

	/** Progress bar + countdown of one size's sale (hidden when that size has no end date). */
	public static function timer( array $s, array $a ): string {
		$on   = $s['sale'] && $s['to'] > time();
		$html = '<div class="ard-timer" data-ard-timer data-from="' . esc_attr( (string) $s['from'] ) . '" data-to="' . esc_attr( (string) $s['to'] ) . '"' . ( $on ? '' : ' hidden' ) . '>';
		if ( 'yes' === $a['show_progress'] ) {
			$html .= '<span class="ard-timer__bar" aria-hidden="true"><i style="width:' . esc_attr( (string) self::progress( $s['from'], $s['to'] ) ) . '%"></i></span>';
		}
		if ( 'yes' === $a['show_timer'] ) {
			$html .= '<span class="ard-timer__clock ard-clock" dir="ltr" title="' . esc_attr__( 'زمان باقی‌مانده تا پایان تخفیف', 'aromist-deals' ) . '">' . esc_html( $on ? self::clock( $s['to'] ) : '' ) . '</span>';
		}
		return $html . '</div>';
	}

	/** Width of the progress bar (%). The browser keeps it current; this is the first paint. */
	public static function progress( int $from, int $to ): float {
		if ( $to <= $from ) {
			return 0;
		}
		$left = max( 0, min( 1, ( $to - time() ) / ( $to - $from ) ) );
		return round( 100 * ( 'elapsed' === ARD_Settings::get( 'progress' ) ? 1 - $left : $left ), 1 );
	}

	/** «۰۶ : ۰۸ : ۵۰ : ۱۱» (days : hours : minutes : seconds); days are left out under a day. */
	public static function clock( int $to ): string {
		$left = max( 0, $to - time() );
		$d    = intdiv( $left, DAY_IN_SECONDS );
		$h    = intdiv( $left % DAY_IN_SECONDS, HOUR_IN_SECONDS );
		$m    = intdiv( $left % HOUR_IN_SECONDS, MINUTE_IN_SECONDS );
		$sec  = $left % MINUTE_IN_SECONDS;
		$out  = ( $d ? sprintf( '%02d : ', $d ) : '' ) . sprintf( '%02d : %02d : %02d', $h, $m, $sec );
		return self::num( $out );
	}
}
