<?php
// اسنیپت #33: تصویر دیفالت برند
defined( 'ABSPATH' ) || exit;


/**
 * Aromist – تصویر پیش‌فرض برای آیتم‌های بدون تصویر
 *
 * - برندها   (تاکسونومی ووکامرس product_brand)  → تصویر برند
 * - نت‌ها     (نوع نوشته notes)                  → تصویر شاخص
 * - گروه‌های بویایی (نوع نوشته olfactory-families) → تصویر شاخص
 *
 * هر جا تصویر خوانده شود (المنتور، لوپ‌ها، ویجت‌ها، قالب) و آیتم تصویر نداشته باشد، تصویر پیش‌فرض برمی‌گردد.
 * در پیشخوان و ویرایشگر تغییری ایجاد نمی‌کند؛ پس آیتم‌ها واقعاً «بدون تصویر» می‌مانند
 * و هر وقت برایشان تصویر گذاشتید، همان نمایش داده می‌شود.
 *
 * در Code Snippets: نوع PHP، اجرا در همه جا.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * ⚙️ تنظیمات
 * شناسه تصویر پیش‌فرض در رسانه:
 * 1910 = https://aromistshop.com/wp-content/uploads/2026/09/Armaf.webp
 */
const AROMIST_DEFAULT_IMAGE_ID = 3599;

function aromist_default_image_taxonomies() {
	return array( 'product_brand' );
}

function aromist_default_image_post_types() {
	return array( 'notes', 'olfactory-families' );
}

/* ---------- آیا الان باید تصویر پیش‌فرض اعمال شود؟ (نه در پیشخوان/ویرایشگر) ---------- */
function aromist_default_image_enabled() {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}
	// درخواست‌های REST (ویرایشگر بلوک) → مقدار واقعی، تا تصویر پیش‌فرض ذخیره نشود
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	return (bool) wp_attachment_is_image( AROMIST_DEFAULT_IMAGE_ID );
}

/* ---------- برندها: تصویر برند (term meta: thumbnail_id) ---------- */
add_filter( 'get_term_metadata', function ( $value, $term_id, $meta_key, $single ) {
	static $running = false;

	if ( 'thumbnail_id' !== $meta_key || $running || ! aromist_default_image_enabled() ) {
		return $value;
	}

	$term = get_term( $term_id );
	if ( ! $term || is_wp_error( $term ) || ! in_array( $term->taxonomy, aromist_default_image_taxonomies(), true ) ) {
		return $value;
	}

	$running = true;
	$real    = get_term_meta( $term_id, 'thumbnail_id', true );
	$running = false;

	if ( ! empty( $real ) && wp_attachment_is_image( (int) $real ) ) {
		return $value; // برند تصویر دارد
	}

	return array( AROMIST_DEFAULT_IMAGE_ID );
}, 10, 4 );

/* ---------- نت‌ها و گروه‌های بویایی: تصویر شاخص (post meta: _thumbnail_id) ---------- */
add_filter( 'get_post_metadata', function ( $value, $post_id, $meta_key, $single ) {
	static $running = false;

	if ( '_thumbnail_id' !== $meta_key || $running ) {
		return $value;
	}

	if ( ! in_array( get_post_type( $post_id ), aromist_default_image_post_types(), true ) || ! aromist_default_image_enabled() ) {
		return $value;
	}

	$running = true;
	$real    = get_post_meta( $post_id, '_thumbnail_id', true );
	$running = false;

	if ( ! empty( $real ) && wp_attachment_is_image( (int) $real ) ) {
		return $value; // تصویر شاخص دارد
	}

	return array( AROMIST_DEFAULT_IMAGE_ID );
}, 10, 4 );
