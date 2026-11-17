<?php
// اسنیپت #48: بخش ویژگی محصولات تکپینو
defined( 'ABSPATH' ) || exit;

/**
 * ویژگی‌های محصول تکپینو — جایگزین [beban_product_attributes]
 *
 * شورت‌کد: [tp_product_attributes]
 * پارامترهای اختیاری: id (شناسه محصول)، limit (تعداد ویژگی، پیش‌فرض ۶)، values (تعداد مقدار هر ویژگی، پیش‌فرض ۲)
 *
 * در Code Snippets: نوع PHP، حالت «Run snippet everywhere» (تا پیش‌نمایش المنتور هم کار کند).
 */

add_shortcode(
	'tp_product_attributes',
	function ( $atts ) {
		static $css_printed = false;

		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'id'     => 0,
				'limit'  => 6,
				'values' => 2,
			),
			$atts,
			'tp_product_attributes'
		);

		// فهرست و ترتیب ویژگی‌ها (همان انتخاب فعلی در تنظیمات bebanstore).
		$taxonomies = array(
			'pa_atmosphere-and-characters', // حال و هوا و شخصیت ها
			'pa_for-the-season',            // مناسب برای فصل
			'pa_scent-type',                // طبع و طعم
			'pa_sensory-taste-and-aroma',   // پخش بو
			'pa_sillage-and-longevity',     // ماندگاری
			'pa_usage-scenario',            // موقعیت استفاده
		);
		$separator = '، ';

		// پیدا کردن محصول: شناسه دستی ← محصول صفحه ← (در ویرایشگر المنتور) آخرین محصول برای پیش‌نمایش.
		$product = null;
		if ( absint( $atts['id'] ) ) {
			$product = wc_get_product( absint( $atts['id'] ) );
		}
		if ( ! $product && isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof WC_Product ) {
			$product = $GLOBALS['product'];
		}
		if ( ! $product && 'product' === get_post_type( get_the_ID() ) ) {
			$product = wc_get_product( get_the_ID() );
		}
		if ( ! $product && class_exists( '\Elementor\Plugin' ) ) {
			$elementor = \Elementor\Plugin::$instance;
			$editing   = ( isset( $elementor->editor ) && $elementor->editor->is_edit_mode() ) || ( isset( $elementor->preview ) && $elementor->preview->is_preview_mode() );
			if ( $editing ) {
				$latest  = wc_get_products( array( 'limit' => 1, 'status' => 'publish', 'return' => 'ids' ) );
				$product = $latest ? wc_get_product( $latest[0] ) : null;
			}
		}
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		// ویژگی‌های محصول بر اساس نام taxonomy (نامک‌های فارسی هم پشتیبانی می‌شوند).
		$by_name = array();
		foreach ( $product->get_attributes() as $attribute ) {
			if ( $attribute instanceof WC_Product_Attribute ) {
				$by_name[ rawurldecode( $attribute->get_name() ) ] = $attribute;
			}
		}

		$limit = max( 1, absint( $atts['limit'] ) );
		$per   = max( 1, absint( $atts['values'] ) );
		$items = array();

		foreach ( $taxonomies as $taxonomy ) {
			if ( count( $items ) >= $limit ) {
				break;
			}
			$attribute = isset( $by_name[ $taxonomy ] ) ? $by_name[ $taxonomy ] : null;
			if ( ! $attribute || ! $attribute->get_visible() ) {
				continue;
			}

			// همان ترتیبی که ووکامرس برای این ویژگی تعریف کرده؛ دو مقدار اول.
			$names = $attribute->is_taxonomy()
				? wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) )
				: $attribute->get_options();
			$names = array_slice( array_values( array_filter( array_map( 'trim', (array) $names ) ) ), 0, $per );
			if ( ! $names ) {
				continue;
			}

			$items[] = array(
				'label' => wc_attribute_label( $attribute->get_name(), $product ),
				'value' => implode( $separator, $names ),
			);
		}

		if ( ! $items ) {
			return '';
		}

		$html = '';
		if ( ! $css_printed ) {
			$css_printed = true;
			// دسکتاپ: دقیقاً مثل قبل (۳ ستون، فاصله ۱۶px).
			// ستون‌ها بر اساس عرض خود بخش (نه کل صفحه) کم می‌شوند: زیر ۴۸۰px دو ستونه، زیر ۲۴۰px تک‌ستونه.
			$html .= '<style id="tp-pattrs-css">'
				. '.tp-pattrs{width:100%;container-type:inline-size}'
				. '.tp-pattrs__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;width:100%;margin-top:16px;box-sizing:border-box}'
				. '.tp-pattrs__item{min-width:0;padding:13px;border-radius:8px;background-color:#f3f7f9;box-sizing:border-box}'
				. '.tp-pattrs__label{margin:0 0 5px;padding:0;color:#888888;font-size:12px;font-weight:300;line-height:1.4;opacity:.8}'
				. '.tp-pattrs__value{margin:0;padding:0;color:#333333;font-size:14px;font-weight:500;line-height:1.4;overflow-wrap:anywhere}'
				. '@container (max-width:480px){.tp-pattrs__grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.tp-pattrs__item{padding:12px}}'
				. '@container (max-width:240px){.tp-pattrs__grid{grid-template-columns:minmax(0,1fr)}}'
				. '@supports not (container-type:inline-size){@media (max-width:767px){.tp-pattrs__grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.tp-pattrs__item{padding:12px}}}'
				. '</style>';
		}

		$html .= '<div class="tp-pattrs"><div class="tp-pattrs__grid">';
		foreach ( $items as $item ) {
			$html .= '<div class="tp-pattrs__item">'
				. '<div class="tp-pattrs__label">' . esc_html( $item['label'] ) . '</div>'
				. '<div class="tp-pattrs__value">' . esc_html( $item['value'] ) . '</div>'
				. '</div>';
		}
		$html .= '</div></div>';

		return $html;
	}
);