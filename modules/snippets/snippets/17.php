<?php
// اسنیپت #17: شرت کد تعداد محصول صفحه آرشیو
defined( 'ABSPATH' ) || exit;

add_shortcode( 'archive_count', function ( $atts ) {

    $atts = shortcode_atts( array(
        'before' => 'نمایش ',
        'after'  => ' محصول',
        'range'  => 'no',
    ), $atts, 'archive_count' );

    global $wp_query;

    $total = (int) $wp_query->found_posts;
    if ( $total < 1 && ! empty( $wp_query->post_count ) ) {
        $total = (int) $wp_query->post_count;
    }
    if ( $total < 1 ) {
        return '';
    }

    if ( 'yes' === $atts['range'] ) {
        $per_page = (int) $wp_query->get( 'posts_per_page' );
        if ( $per_page < 1 ) {
            $per_page = $total;
        }
        $paged = max( 1, (int) $wp_query->get( 'paged' ) );
        $first = ( ( $paged - 1 ) * $per_page ) + 1;
        $last  = min( $total, $paged * $per_page );

        return sprintf(
            '<span class="archive-count">%s%s–%s از %s%s</span>',
            esc_html( $atts['before'] ),
            number_format_i18n( $first ),
            number_format_i18n( $last ),
            number_format_i18n( $total ),
            esc_html( $atts['after'] )
        );
    }

    return sprintf(
        '<span class="archive-count">%s%s%s</span>',
        esc_html( $atts['before'] ),
        number_format_i18n( $total ),
        esc_html( $atts['after'] )
    );
} );