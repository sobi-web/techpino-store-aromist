<?php
// اسنیپت #6: تعداد محصولات موجود در سبد خرید
defined( 'ABSPATH' ) || exit;

add_shortcode('cart_count', 'sobi_get_cart_count');

function sobi_get_cart_count() {
    // بررسی فعال بودن ووکامرس و سبد خرید
    if ( function_exists('WC') && WC()->cart ) {
        $count = WC()->cart->get_cart_contents_count();
        return '<span class="cart-count-badge">' . esc_html($count) . '</span>';
    }
    
    // در صورت عدم دسترسی یا خالی بودن سبد
    return '<span class="cart-count-badge">0</span>';
}