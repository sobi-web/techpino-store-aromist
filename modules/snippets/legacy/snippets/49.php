<?php
// اسنیپت #49: مدت زمان تقریبی مطالعه
defined( 'ABSPATH' ) || exit;


/**
 * Plugin Name: زمان مطالعه تکپینو
 * Plugin URI:  https://aromistshop.com
 * Description: شورت‌کد [tp_reading_time] برای نمایش زمان تقریبی مطالعه نوشته.
 * Version:     1.0.0
 * Author:      سبحان پارسامهر
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'tp_reading_time', function ( $atts ) {
	$atts = shortcode_atts( array( 'wpm' => 200, 'suffix' => 'دقیقه مطالعه' ), $atts, 'tp_reading_time' );

	$post = get_post();
	if ( ! $post ) {
		return '';
	}

	$text    = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	$words   = count( preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY ) );
	$minutes = max( 1, (int) ceil( $words / max( 1, (int) $atts['wpm'] ) ) );
	$minutes = strtr( (string) $minutes, array_combine( range( 0, 9 ), array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ) ) );

	$icon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" style="vertical-align:-2px;margin-left:5px"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';

	return '<span class="tp-reading-time">' . $icon . esc_html( $minutes . ' ' . $atts['suffix'] ) . '</span>';
} );
