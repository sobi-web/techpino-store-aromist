<?php
// اسنیپت #35: Techpino – ویرایشگر کلاسیک وردپرس برای «توضیحات» همه
defined( 'ABSPATH' ) || exit;


/**
 * Aromist – ویرایشگر کلاسیک وردپرس برای «توضیحات» همه دسته‌بندی‌ها و تاکسونومی‌ها
 *
 * فیلد ساده‌ی «توضیحات» در صفحه افزودن و ویرایش همه تاکسونومی‌ها
 * (دسته‌ها، برچسب‌ها، دسته‌بندی محصولات، برندها، دسته‌بندی نت‌ها، گروه‌های بویایی و ...)
 * با ویرایشگر کلاسیک (دیداری/متن + افزودن رسانه) جایگزین می‌شود.
 *
 * در Code Snippets: نوع PHP، اجرا در «همه جا» (برای نمایش درست HTML در سایت هم لازم است).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * ۱) اجازه ذخیره و نمایش HTML کامل (تیتر، پاراگراف، لیست، تصویر، جدول و ...)
 *    به‌طور پیش‌فرض وردپرس فقط چند تگ ساده را در توضیحات دسته نگه می‌دارد.
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	// ذخیره: همان سطح مجاز محتوای نوشته‌ها (امن)
	remove_filter( 'pre_term_description', 'wp_filter_kses' );
	add_filter( 'pre_term_description', 'wp_filter_post_kses' );

	// نمایش در سایت
	remove_filter( 'term_description', 'wp_kses_data' );
	add_filter( 'term_description', 'wp_kses_post' );
} );

/* -------------------------------------------------------------------------
 * ۲) افزودن ویرایشگر به همه تاکسونومی‌های دارای رابط کاربری
 * ---------------------------------------------------------------------- */
add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_categories' ) && ! current_user_can( 'manage_product_terms' ) ) {
		return;
	}
	foreach ( get_taxonomies( array( 'show_ui' => true ), 'names' ) as $taxonomy ) {
		if ( in_array( $taxonomy, array( 'nav_menu', 'link_category', 'wp_pattern_category' ), true ) ) {
			continue;
		}
		add_action( "{$taxonomy}_add_form_fields", 'aromist_term_editor_add_field', 1 );
		add_action( "{$taxonomy}_edit_form_fields", 'aromist_term_editor_edit_field', 1, 2 );
	}
}, 99 );

function aromist_term_editor_settings() {
	return array(
		'textarea_name' => 'description',
		'textarea_rows' => 12,
		'media_buttons' => true,
		'teeny'         => false,
		'quicktags'     => true,
		'tinymce'       => array(
			'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,blockquote,alignright,aligncenter,alignleft,link,unlink,wp_more,fullscreen,wp_adv',
			'toolbar2' => 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
		),
	);
}

/* ---------- صفحه «افزودن» (فرم سمت راست لیست) ---------- */
function aromist_term_editor_add_field( $taxonomy ) {
	?>
	<div class="form-field term-description-wrap ac-term-editor-wrap">
		<label for="aromist_term_description">توضیحات</label>
		<?php wp_editor( '', 'aromist_term_description', aromist_term_editor_settings() ); ?>
		<p id="description-description">توضیحات به‌طور پیش‌فرض برجسته نیستند؛ اما برخی پوسته‌ها ممکن است آن را نمایش دهند.</p>
	</div>
	<script>
	(function () {
		// ویرایشگر را جای فیلد ساده قرار بده (قبل از راه‌اندازی ویرایشگر)
		var ours = document.querySelector('.ac-term-editor-wrap');
		var form = document.getElementById('addtag');
		if (!ours || !form) { return; }
		var orig = form.querySelector('.term-description-wrap:not(.ac-term-editor-wrap)');
		if (orig) { orig.parentNode.replaceChild(ours, orig); }

		// قبل از ارسال ایجکس، محتوای ویرایشگر در فیلد قرار بگیرد
		form.addEventListener('click', function (e) {
			if (e.target.closest('#submit') && window.tinymce) { window.tinymce.triggerSave(); }
		}, true);
		form.addEventListener('submit', function () {
			if (window.tinymce) { window.tinymce.triggerSave(); }
		}, true);

		// بعد از افزودن موفق، ویرایشگر خالی شود
		if (window.jQuery) {
			window.jQuery(document).ajaxSuccess(function (event, xhr, settings) {
				if (!settings || typeof settings.data !== 'string' || settings.data.indexOf('action=add-tag') === -1) { return; }
				if (xhr && xhr.responseText && xhr.responseText.indexOf('wp_error') !== -1) { return; }
				var ed = window.tinymce && window.tinymce.get('aromist_term_description');
				if (ed) { ed.setContent(''); }
				var ta = document.getElementById('aromist_term_description');
				if (ta) { ta.value = ''; }
			});
		}
	})();
	</script>
	<?php
}

/* ---------- صفحه «ویرایش» ---------- */
function aromist_term_editor_edit_field( $term, $taxonomy ) {
	?>
	<tr class="form-field term-description-wrap ac-term-editor-wrap">
		<th scope="row"><label for="aromist_term_description">توضیحات</label></th>
		<td>
			<?php wp_editor( $term->description, 'aromist_term_description', aromist_term_editor_settings() ); ?>
			<p class="description" id="description-description">توضیحات به‌طور پیش‌فرض برجسته نیستند؛ اما برخی پوسته‌ها ممکن است آن را نمایش دهند.</p>
			<script>
			(function () {
				// فیلد ساده‌ی قبلی حذف و ویرایشگر جای آن قرار بگیرد (فقط یک فیلد description ارسال شود)
				var ours = document.querySelector('tr.ac-term-editor-wrap');
				var form = document.getElementById('edittag');
				if (!ours || !form) { return; }
				var orig = form.querySelector('tr.term-description-wrap:not(.ac-term-editor-wrap)');
				if (orig) { orig.parentNode.replaceChild(ours, orig); }
			})();
			</script>
		</td>
	</tr>
	<?php
}

/* ---------- کمی استایل برای جا افتادن ویرایشگر ---------- */
add_action( 'admin_head-edit-tags.php', 'aromist_term_editor_admin_css' );
add_action( 'admin_head-term.php', 'aromist_term_editor_admin_css' );
function aromist_term_editor_admin_css() {
	?>
	<style>
	.ac-term-editor-wrap .wp-editor-wrap{margin-top:6px}
	.ac-term-editor-wrap .wp-editor-area{min-height:220px}
	#addtag .ac-term-editor-wrap .wp-editor-container{background:#fff}
	#edittag .ac-term-editor-wrap td{max-width:none}
	</style>
	<?php
}
