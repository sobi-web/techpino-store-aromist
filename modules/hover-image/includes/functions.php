<?php
/**
 * Helpers + shared renderer used by the Elementor widget and the shortcode.
 *
 * @package AromistHoverImage
 */

defined( 'ABSPATH' ) || exit;

/**
 * شناسه‌ی تصویر هاور یک محصول.
 *
 * فیلتر `aromist_hover_image_id` اجازه می‌دهد منبع دیگری (مثلاً یک فیلد متای JetEngine) جایگزین شود.
 *
 * @param int  $product_id    Product ID.
 * @param bool $with_fallback اگر تصویر هاور نبود، اولین تصویر گالری برگردانده شود.
 * @return int Attachment ID or 0.
 */
function ahi_get_hover_image_id( $product_id, $with_fallback = false ) {
	$id = (int) get_post_meta( $product_id, AHI_META_KEY, true );

	if ( $id && ! wp_attachment_is_image( $id ) ) {
		$id = 0;
	}

	if ( ! $id && $with_fallback ) {
		$product = wc_get_product( $product_id );
		$gallery = $product ? $product->get_gallery_image_ids() : array();
		$id      = $gallery ? (int) reset( $gallery ) : 0;
	}

	return (int) apply_filters( 'aromist_hover_image_id', $id, $product_id, $with_fallback );
}

/**
 * محصول فعلی آیتم Listing جت‌انجین (Query از نوع Posts یا WC Product).
 *
 * @return int Product ID or 0.
 */
function ahi_jet_engine_product_id() {
	if ( ! function_exists( 'jet_engine' ) ) {
		return 0;
	}
	$je = jet_engine();
	if ( ! $je || empty( $je->listings ) || empty( $je->listings->data ) || ! is_callable( array( $je->listings->data, 'get_current_object' ) ) ) {
		return 0;
	}

	$obj = $je->listings->data->get_current_object();

	if ( $obj instanceof WC_Product ) {
		return $obj->get_id();
	}
	if ( $obj instanceof WP_Post && 'product' === $obj->post_type ) {
		return (int) $obj->ID;
	}
	return 0;
}

/**
 * نوع سند المنتوری که الان در حال رندر است (loop-item، jet-listing-items، ...).
 *
 * @return string
 */
function ahi_current_elementor_document_type() {
	if ( ! class_exists( '\Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance->documents ) ) {
		return '';
	}
	$doc = \Elementor\Plugin::$instance->documents->get_current();
	return ( $doc && is_callable( array( $doc, 'get_name' ) ) ) ? (string) $doc->get_name() : '';
}

/**
 * محصولی که ویجت / شورت‌کد باید نشان دهد؛ داخل Loop Grid المنتور، Listing جت‌انجین یا لوپ ووکامرس.
 *
 * @return int Product ID or 0.
 */
function ahi_current_product_id() {
	$doc_type = ahi_current_elementor_document_type();
	$jet_id   = ahi_jet_engine_product_id();
	$post     = get_post();
	$post_id  = ( $post && 'product' === $post->post_type ) ? (int) $post->ID : 0;

	// قالب آیتم Listing جت‌انجین.
	if ( 'jet-listing-items' === $doc_type && $jet_id ) {
		$id = $jet_id;
	} elseif ( 'loop-item' === $doc_type && $post_id ) {
		// قالب آیتم Loop Grid المنتور.
		$id = $post_id;
	} elseif ( $jet_id && $jet_id !== (int) get_queried_object_id() ) {
		// جت‌انجین شیء را صریحاً روی آیتم لیستینگ تنظیم کرده.
		$id = $jet_id;
	} elseif ( $post_id ) {
		$id = $post_id;
	} elseif ( isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof WC_Product ) {
		$id = $GLOBALS['product']->get_id();
	} else {
		$id = $jet_id;
	}

	return (int) apply_filters( 'aromist_hover_image_current_product_id', $id );
}

/**
 * Default render arguments.
 *
 * @return array
 */
function ahi_default_args() {
	return array(
		'size'     => 'woocommerce_thumbnail',
		'link'     => false,    // باکس‌ها معمولاً خودشان لینک دارند؛ لینک تو در تو HTML را خراب می‌کند.
		'trigger'  => 'card',   // card | image
		'effect'   => 'fade',   // fade | zoom | slide
		'mobile'   => 'button', // button | none
		'fallback' => 'none',   // none | gallery
		'ratio'    => '',       // e.g. "1/1"  (shortcode only; the widget sets it through Elementor selectors)
		'width'    => '',       // e.g. "180px" (shortcode only)
		'preview'  => false,    // show the hover image right away (Elementor editor)
	);
}

/**
 * HTML باکس تصویر محصول + تصویر هاور.
 *
 * @param int   $product_id Product ID.
 * @param array $args       See ahi_default_args().
 * @return string
 */
function ahi_render( $product_id, array $args = array() ) {
	$product = $product_id ? wc_get_product( $product_id ) : null;
	if ( ! $product ) {
		return '';
	}

	$args    = wp_parse_args( $args, ahi_default_args() );
	$size    = $args['size'] ? $args['size'] : 'woocommerce_thumbnail';
	$trigger = in_array( $args['trigger'], array( 'card', 'image' ), true ) ? $args['trigger'] : 'card';
	$effect  = in_array( $args['effect'], array( 'fade', 'zoom', 'slide' ), true ) ? $args['effect'] : 'fade';
	$mobile  = in_array( $args['mobile'], array( 'button', 'none' ), true ) ? $args['mobile'] : 'button';
	$title   = $product->get_name();

	// تصویر هاور روی محصول مادر ذخیره می‌شود (برای وقتی لیستینگ، متغیرها را نشان می‌دهد).
	$owner_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();

	wp_enqueue_style( 'aromist-hover-image' );
	wp_enqueue_script( 'aromist-hover-image' );

	// تصویر اصلی.
	$thumb_id = $product->get_image_id();
	$main     = $thumb_id
		? wp_get_attachment_image( $thumb_id, $size, false, array( 'class' => 'ahi__main', 'alt' => $title ) )
		: wc_placeholder_img( $size, array( 'class' => 'ahi__main' ) );

	// تصویر هاور: آدرس در data-* می‌ماند و فقط موقع هاور / لمس بارگذاری می‌شود.
	$hover_id = ahi_get_hover_image_id( $owner_id, 'gallery' === $args['fallback'] );
	$hover    = '';
	if ( $hover_id ) {
		$src = wp_get_attachment_image_src( $hover_id, $size );
		if ( $src ) {
			$srcset = (string) wp_get_attachment_image_srcset( $hover_id, $size );
			$sizes  = (string) wp_get_attachment_image_sizes( $hover_id, $size );
			$alt    = trim( (string) get_post_meta( $hover_id, '_wp_attachment_image_alt', true ) );
			$alt    = $alt ? $alt : sprintf( 'رایحه، نت‌ها و گروه بویایی %s', $title );
			$prefix = $args['preview'] ? '' : 'data-';

			$hover = sprintf(
				'<img class="ahi__hover%5$s" %6$ssrc="%1$s" %6$ssrcset="%2$s" %6$ssizes="%3$s" alt="%4$s" decoding="async">',
				esc_url( $src[0] ),
				esc_attr( $srcset ),
				esc_attr( $sizes ),
				esc_attr( $alt ),
				$args['preview'] ? ' is-loaded' : '',
				$prefix
			);
		}
	}

	$classes = array( 'ahi', 'ahi--fx-' . $effect, 'ahi--mobile-' . $mobile );
	if ( $hover ) {
		$classes[] = 'ahi--has-hover';
	}
	if ( $args['preview'] && $hover ) {
		$classes[] = 'is-active';
	}

	$vars = array();
	if ( $args['ratio'] && preg_match( '#^\d+(\.\d+)?\s*/\s*\d+(\.\d+)?$#', $args['ratio'] ) ) {
		$vars[] = '--ahi-ratio:' . $args['ratio'];
	}
	if ( $args['width'] && preg_match( '#^\d+(\.\d+)?(px|%|vw|rem|em)$#', $args['width'] ) ) {
		$vars[] = '--ahi-width:' . $args['width'];
	}
	$style = $vars ? ' style="' . esc_attr( implode( ';', $vars ) ) . '"' : '';

	// بدون لینک، قاب یک <span> است تا داخل کانتینرِ لینک‌دار المنتور / جت‌انجین مجاز باشد.
	if ( $args['link'] ) {
		$frame = sprintf( '<a class="ahi__frame" href="%s" aria-label="%s">%s%s</a>', esc_url( $product->get_permalink() ), esc_attr( $title ), $main, $hover );
	} else {
		$frame = '<span class="ahi__frame">' . $main . $hover . '</span>';
	}

	$button = '';
	if ( $hover && 'button' === $mobile ) {
		$button = '<button type="button" class="ahi__info" aria-pressed="false" aria-label="' . esc_attr( 'نمایش نت‌ها و گروه بویایی ' . $title ) . '">'
			. '<svg class="ahi__icon-info" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M12 10.5v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="7.5" r="1.1" fill="currentColor"/></svg>'
			. '<svg class="ahi__icon-close" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7l10 10M17 7L7 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>'
			. '</button>';
	}

	return sprintf(
		'<div class="%1$s" data-ahi-trigger="%2$s"%3$s>%4$s%5$s</div>',
		esc_attr( implode( ' ', $classes ) ),
		esc_attr( $trigger ),
		$style,
		$frame,
		$button
	);
}

/**
 * [aromist_hover_image id="" size="woocommerce_thumbnail" link="no" trigger="card" effect="fade" mobile="button" fallback="none" ratio="1/1" width="180px"]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function ahi_shortcode( $atts ) {
	$defaults = ahi_default_args();
	$atts     = shortcode_atts(
		array(
			'id'       => 0,
			'size'     => $defaults['size'],
			'link'     => 'no',
			'trigger'  => $defaults['trigger'],
			'effect'   => $defaults['effect'],
			'mobile'   => $defaults['mobile'],
			'fallback' => $defaults['fallback'],
			'ratio'    => '',
			'width'    => '',
		),
		$atts,
		'aromist_hover_image'
	);

	$product_id = $atts['id'] ? absint( $atts['id'] ) : ahi_current_product_id();

	return ahi_render(
		$product_id,
		array(
			'size'     => sanitize_key( $atts['size'] ),
			'link'     => 'yes' === $atts['link'],
			'trigger'  => $atts['trigger'],
			'effect'   => $atts['effect'],
			'mobile'   => $atts['mobile'],
			'fallback' => $atts['fallback'],
			'ratio'    => $atts['ratio'],
			'width'    => $atts['width'],
		)
	);
}
