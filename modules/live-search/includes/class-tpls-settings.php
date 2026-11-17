<?php
/**
 * The settings behind the «جستجوی زنده» admin screen: what is shown before the visitor types
 * (banners, popular searches, featured products) and the look of the form.
 *
 * One option holds everything. Appearance values are CSS variables printed on `.tpls`, so
 * the Elementor widget's own style controls (more specific selectors) still win per widget.
 *
 * @package TechpinoLiveSearch
 */

defined( 'ABSPATH' ) || exit;

final class TPLS_Settings {

	public const OPTION = 'tpls_settings';

	/** @var array|null */
	private static $cache = null;

	public static function defaults(): array {
		return array(
			'popular_title'  => 'جستجوهای پرطرفدار',
			'popular'        => array(),
			'banner_pos'     => 'middle',
			'banner_cols'    => 1,
			'banners'        => array(),
			'featured_show'  => 1,
			'featured_mode'  => 'auto',
			'featured_title' => 'پرفروش‌ترین محصولات',
			'featured_count' => 6,
			'featured_ids'   => array(),
			'featured_stock' => 1,
			'synonyms'       => '',
			'look'           => array(),
		);
	}

	public static function get(): array {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		}
		return self::$cache;
	}

	/**
	 * Popular searches: each a text and an optional link. A link sends the visitor there;
	 * without one the text is searched. (Lists saved by 2.0.0 held plain strings.)
	 *
	 * @return array<int, array{text: string, link: string}>
	 */
	public static function popular(): array {
		$list = array();
		foreach ( (array) self::get()['popular'] as $item ) {
			$item = is_string( $item ) ? array( 'text' => $item ) : $item;
			$text = is_array( $item ) ? trim( (string) ( $item['text'] ?? '' ) ) : '';
			if ( '' !== $text ) {
				$list[] = array(
					'text' => $text,
					'link' => (string) ( $item['link'] ?? '' ),
				);
			}
		}
		return $list;
	}

	public static function reset_cache(): void {
		self::$cache = null;
	}

	/**
	 * Appearance fields: key => [group, label, kind, CSS variable(s), min, max, default shown as a hint].
	 * kind: color | px | pct. An empty variable means the value is combined elsewhere (see css()).
	 */
	public static function look_fields(): array {
		return array(
			'field_h'        => array( 'فیلد جستجو', 'ارتفاع فیلد', 'px', '--tpls-h', 36, 96, '54' ),
			'field_radius'   => array( 'فیلد جستجو', 'گردی گوشه‌های فیلد', 'px', '--tpls-f-radius', 0, 60, '14' ),
			'field_bg'       => array( 'فیلد جستجو', 'پس‌زمینه', 'color', '--tpls-f-bg', 0, 0, '#F3F7F9' ),
			'field_bg_f'     => array( 'فیلد جستجو', 'پس‌زمینه هنگام تایپ', 'color', '--tpls-f-bg-f', 0, 0, '#FFFFFF' ),
			'field_line'     => array( 'فیلد جستجو', 'رنگ خط دور', 'color', '--tpls-f-line', 0, 0, '#E9EDF0' ),
			'field_line_f'   => array( 'فیلد جستجو', 'رنگ خط دور هنگام تایپ', 'color', '--tpls-f-line-f', 0, 0, '#009879' ),
			'field_color'    => array( 'فیلد جستجو', 'رنگ متن', 'color', '--tpls-f-color', 0, 0, '#1D1D1B' ),
			'field_ph'       => array( 'فیلد جستجو', 'رنگ متن راهنما', 'color', '--tpls-f-ph', 0, 0, '#7A7A7A' ),
			'field_icon'     => array( 'فیلد جستجو', 'رنگ آیکون', 'color', '--tpls-f-icon', 0, 0, '#7A7A7A' ),

			'pop_w'          => array( 'پاپ‌آپ و پنل نتایج', 'عرض پاپ‌آپ (دسکتاپ)', 'px', '--tpls-pop-w', 640, 1400, '940' ),
			'pop_h'          => array( 'پاپ‌آپ و پنل نتایج', 'ارتفاع پاپ‌آپ (دسکتاپ)', 'px', '--tpls-pop-h', 420, 1000, '720' ),
			'pop_radius'     => array( 'پاپ‌آپ و پنل نتایج', 'گردی گوشه‌ها', 'px', '--tpls-p-radius', 0, 48, '28' ),
			'pop_bg'         => array( 'پاپ‌آپ و پنل نتایج', 'پس‌زمینه‌ی پنل', 'color', '--tpls-p-bg', 0, 0, '#FFFFFF' ),
			'overlay'        => array( 'پاپ‌آپ و پنل نتایج', 'رنگ لایه‌ی پشت پاپ‌آپ', 'color', '', 0, 0, '#141E1C' ),
			'overlay_op'     => array( 'پاپ‌آپ و پنل نتایج', 'شدت لایه‌ی پشت پاپ‌آپ', 'pct', '', 0, 95, '55' ),
			'overlay_blur'   => array( 'پاپ‌آپ و پنل نتایج', 'محو شدن صفحه‌ی پشت پاپ‌آپ', 'px', '--tpls-overlay-blur', 0, 20, '4' ),
			'heading_color'  => array( 'پاپ‌آپ و پنل نتایج', 'رنگ عنوان بخش‌ها', 'color', '--tpls-heading', 0, 0, '#7A7A7A' ),

			'chip_bg'        => array( 'برچسب‌های جستجوی پرطرفدار', 'پس‌زمینه', 'color', '--tpls-chip-bg', 0, 0, '#FFFFFF' ),
			'chip_color'     => array( 'برچسب‌های جستجوی پرطرفدار', 'رنگ متن', 'color', '--tpls-chip-color', 0, 0, '#315449' ),
			'chip_line'      => array( 'برچسب‌های جستجوی پرطرفدار', 'رنگ خط دور', 'color', '--tpls-chip-line', 0, 0, '#E9EDF0' ),
			'chip_bg_h'      => array( 'برچسب‌های جستجوی پرطرفدار', 'پس‌زمینه در هاور', 'color', '--tpls-chip-bg-h', 0, 0, '#009879' ),
			'chip_color_h'   => array( 'برچسب‌های جستجوی پرطرفدار', 'رنگ متن در هاور', 'color', '--tpls-chip-color-h', 0, 0, '#FFFFFF' ),

			'banner_radius'  => array( 'بنر', 'گردی گوشه‌های بنر', 'px', '--tpls-banner-r', 0, 40, '16' ),

			'accent'         => array( 'کارت محصول', 'رنگ اصلی (حجم‌ها، آیکون سبد، هاور)', 'color', '--tpls-c-accent', 0, 0, '#009879' ),
			'card_bg'        => array( 'کارت محصول', 'پس‌زمینه', 'color', '--tpls-hc-bg', 0, 0, '#FFFFFF' ),
			'card_bg_h'      => array( 'کارت محصول', 'پس‌زمینه در هاور', 'color', '--tpls-hc-bg-h', 0, 0, '#F3F7F9' ),
			'card_line'      => array( 'کارت محصول', 'رنگ خط دور', 'color', '--tpls-hc-line', 0, 0, '#EEF2F9' ),
			'card_radius'    => array( 'کارت محصول', 'گردی گوشه‌ها', 'px', '--tpls-hc-radius', 0, 40, '20' ),
			'card_title'     => array( 'کارت محصول', 'رنگ نام فارسی', 'color', '--tpls-hc-title', 0, 0, '#1D1D1B' ),
			'card_en'        => array( 'کارت محصول', 'رنگ نام انگلیسی', 'color', '--tpls-hc-en', 0, 0, '#7A7A7A' ),
			'card_price'     => array( 'کارت محصول', 'رنگ قیمت', 'color', '--tpls-hc-price', 0, 0, '#1D1D1B' ),
		);
	}

	/** Image sizes worth uploading for each banner layout (CSS px; upload at 2× for sharp screens). */
	public static function banner_guide(): array {
		return array(
			'desktop' => array(
				1 => array( 900, 200 ),
				2 => array( 444, 200 ),
				3 => array( 292, 200 ),
			),
			'mobile'  => array( 350, 140 ),
		);
	}

	private static function color( $value ): string {
		$value = trim( (string) $value );
		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/i', $value ) ) {
			return $value;
		}
		return '';
	}

	/** @param mixed $in Posted data. */
	public static function sanitize( $in ): array {
		$in  = is_array( $in ) ? wp_unslash( $in ) : array();
		$d   = self::defaults();
		$out = $d;

		$out['popular_title'] = sanitize_text_field( $in['popular_title'] ?? $d['popular_title'] );
		$out['popular']       = array();
		foreach ( array_slice( (array) ( $in['popular'] ?? array() ), 0, 30 ) as $item ) {
			$item = is_string( $item ) ? array( 'text' => $item ) : $item;
			$text = is_array( $item ) ? sanitize_text_field( $item['text'] ?? '' ) : '';
			if ( '' === $text ) {
				continue;
			}
			$out['popular'][] = array(
				'text' => $text,
				'link' => esc_url_raw( trim( (string) ( $item['link'] ?? '' ) ) ),
			);
		}

		$out['banner_pos']  = in_array( $in['banner_pos'] ?? '', array( 'top', 'middle', 'bottom' ), true ) ? $in['banner_pos'] : $d['banner_pos'];
		$out['banner_cols'] = min( 3, max( 1, (int) ( $in['banner_cols'] ?? 1 ) ) );
		foreach ( array_slice( (array) ( $in['banners'] ?? array() ), 0, 8 ) as $banner ) {
			if ( ! is_array( $banner ) ) {
				continue;
			}
			$desktop = absint( $banner['desktop'] ?? 0 );
			$mobile  = absint( $banner['mobile'] ?? 0 );
			if ( ! $desktop && ! $mobile ) {
				continue;
			}
			$out['banners'][] = array(
				'desktop' => $desktop,
				'mobile'  => $mobile,
				'link'    => esc_url_raw( trim( (string) ( $banner['link'] ?? '' ) ) ),
				'blank'   => ! empty( $banner['blank'] ) ? 1 : 0,
				'alt'     => sanitize_text_field( $banner['alt'] ?? '' ),
			);
		}

		$out['featured_show']  = ! empty( $in['featured_show'] ) ? 1 : 0;
		$out['featured_mode']  = 'manual' === ( $in['featured_mode'] ?? '' ) ? 'manual' : 'auto';
		$out['featured_title'] = sanitize_text_field( $in['featured_title'] ?? $d['featured_title'] );
		$out['featured_count'] = min( 24, max( 1, (int) ( $in['featured_count'] ?? $d['featured_count'] ) ) );
		$out['featured_stock'] = ! empty( $in['featured_stock'] ) ? 1 : 0;
		$out['featured_ids']   = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) ( $in['featured_ids'] ?? array() ) ) ) ) ), 0, 48 );

		$out['synonyms'] = sanitize_textarea_field( $in['synonyms'] ?? '' );

		foreach ( self::look_fields() as $key => $field ) {
			$value = $in['look'][ $key ] ?? '';
			if ( 'color' === $field[2] ) {
				$value = self::color( $value );
			} else {
				$value = '' === trim( (string) $value ) ? '' : (string) min( $field[5], max( $field[4], (int) $value ) );
			}
			if ( '' !== $value ) {
				$out['look'][ $key ] = $value;
			}
		}

		return $out;
	}

	/** `.tpls { --var: value; }` for every appearance value that was set. */
	public static function css(): string {
		$look  = (array) self::get()['look'];
		$rules = '';
		foreach ( self::look_fields() as $key => $field ) {
			if ( '' === $field[3] || ! isset( $look[ $key ] ) || '' === $look[ $key ] ) {
				continue;
			}
			$rules .= $field[3] . ':' . $look[ $key ] . ( 'px' === $field[2] ? 'px' : '' ) . ';';
		}
		// The overlay is one colour plus one strength, printed as a single rgba().
		if ( isset( $look['overlay'] ) || isset( $look['overlay_op'] ) ) {
			$hex = ltrim( isset( $look['overlay'] ) && 0 === strpos( $look['overlay'], '#' ) ? $look['overlay'] : '#141E1C', '#' );
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			$hex    = substr( $hex . '000000', 0, 6 );
			$alpha  = isset( $look['overlay_op'] ) ? (int) $look['overlay_op'] / 100 : 0.55;
			$rules .= sprintf( '--tpls-overlay:rgba(%d,%d,%d,%s);', hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ), rtrim( rtrim( number_format( $alpha, 2, '.', '' ), '0' ), '.' ) ?: '0' );
		}
		return '' === $rules ? '' : '.tpls{' . $rules . '}';
	}

	/**
	 * Banners ready to print: each with a desktop and a mobile image URL (either may fall back to the other).
	 *
	 * @return array<int, array{desktop: string, mobile: string, link: string, blank: bool, alt: string}>
	 */
	public static function banners(): array {
		$list = array();
		foreach ( (array) self::get()['banners'] as $banner ) {
			$desktop = ! empty( $banner['desktop'] ) ? wp_get_attachment_image_url( (int) $banner['desktop'], 'full' ) : '';
			$mobile  = ! empty( $banner['mobile'] ) ? wp_get_attachment_image_url( (int) $banner['mobile'], 'full' ) : '';
			if ( ! $desktop && ! $mobile ) {
				continue;
			}
			$list[] = array(
				'desktop' => (string) ( $desktop ? $desktop : $mobile ),
				'mobile'  => (string) ( $mobile ? $mobile : $desktop ),
				'link'    => (string) ( $banner['link'] ?? '' ),
				'blank'   => ! empty( $banner['blank'] ),
				'alt'     => (string) ( $banner['alt'] ?? '' ),
			);
		}
		return $list;
	}
}
