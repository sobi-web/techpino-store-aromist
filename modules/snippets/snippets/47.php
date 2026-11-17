<?php
// اسنیپت #47: بخش نت ها در صفحه محصول نمایش تعداد و بهینه سازی نمایش
defined( 'ABSPATH' ) || exit;


// Aromist – محدود کردن نت‌های محصول به ۵ مورد + دکمه «نمایش بیشتر»
//
// مشکل: وقتی یک محصول بیشتر از ۵ نت (اولیه/میانی/پایانی) دارد، ویجت
// «تکرارکننده پویا»ی JetEngine از کادر بیرون می‌زند و چیدمان باکس به هم می‌ریزد.
//
// راه‌حل:
//  - فقط تکرارکننده‌هایی که لینک /note/ دارند هدف قرار می‌گیرند (گروه بویایی دست نمی‌خورد).
//  - هر ردیف جداگانه حداکثر ۵ نت نشان می‌دهد و بقیه پشت دکمه «نمایش X نت دیگر» می‌روند.
//  - ردیف‌ها wrap می‌شوند، نت‌های دوکلمه‌ای («لیمو ترش») نمی‌شکنند و عنوان ردیف دو خط نمی‌شود.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_footer', function () {
	$is_preview = class_exists( '\Elementor\Plugin' )
		&& isset( \Elementor\Plugin::$instance->preview )
		&& \Elementor\Plugin::$instance->preview->is_preview_mode();

	if ( ! ( function_exists( 'is_product' ) && is_product() ) && ! $is_preview ) {
		return;
	}
	?>
	<style id="aromist-notes-show-more">
		/* ردیفِ (آیکون + عنوان + نت‌ها) */
		.aro-notes-row > .elementor-widget-heading { flex-shrink: 0; }
		.aro-notes-row > .elementor-widget-heading .elementor-heading-title { white-space: nowrap; }
		.aro-notes-row > .aro-notes-widget {
			flex: 1 1 0% !important;
			min-width: 0 !important;
		}

		/* لیست نت‌ها */
		.jet-listing-dynamic-repeater__items.aro-notes {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			row-gap: 6px;
			column-gap: 0;
		}
		/* نت + جداکننده‌اش کنار هم در یک ردیف (جت‌انجین فرزندان آیتم را display:flex می‌کند) */
		.jet-listing-dynamic-repeater__items.aro-notes > .jet-listing-dynamic-repeater__item {
			display: flex;
			flex-direction: row;
			align-items: center;
			list-style: none;
			white-space: nowrap;
		}
		.jet-listing-dynamic-repeater__items.aro-notes:not(.is-open) > .aro-last-visible > .jet-listing-dynamic-repeater__delimiter {
			display: none;
		}
		.aro-notes > .jet-listing-dynamic-repeater__item a {
			transition: color .2s ease;
		}
		.aro-notes > .jet-listing-dynamic-repeater__item a:hover {
			color: var(--e-global-color-accent, #009879);
		}

		/* آیتم‌های اضافه */
		.jet-listing-dynamic-repeater__items.aro-notes:not(.is-open) > .aro-extra { display: none; }
		.aro-notes.is-open > .aro-extra {
			animation: aroNoteIn .35s ease both;
			animation-delay: calc(var(--aro-i, 0) * 35ms);
		}
		@keyframes aroNoteIn {
			from { opacity: 0; transform: translateY(4px); }
			to   { opacity: 1; transform: none; }
		}

		/* دکمه نمایش بیشتر / بستن */
		.jet-listing-dynamic-repeater__items.aro-notes > button.aro-notes__more {
			display: inline-flex;
			align-items: center;
			gap: 4px;
			margin: 0;
			margin-inline-start: 10px;
			padding: 2px 12px;
			border: 1px solid rgba(0, 152, 121, .25);
			border-radius: 999px;
			background: rgba(0, 152, 121, .08);
			color: var(--e-global-color-accent, #009879);
			font-family: inherit;
			font-size: 12px;
			font-weight: 500;
			line-height: 22px;
			white-space: nowrap;
			cursor: pointer;
			box-shadow: none;
			text-decoration: none;
			transition: background-color .2s ease, color .2s ease, border-color .2s ease;
		}
		.jet-listing-dynamic-repeater__items.aro-notes > button.aro-notes__more:hover,
		.jet-listing-dynamic-repeater__items.aro-notes > button.aro-notes__more:focus-visible {
			background: var(--e-global-color-accent, #009879);
			border-color: var(--e-global-color-accent, #009879);
			color: #fff;
			outline: none;
		}
		.jet-listing-dynamic-repeater__items.aro-notes > button.aro-notes__more:focus-visible {
			box-shadow: 0 0 0 3px rgba(0, 152, 121, .25);
		}
		.aro-notes__more svg {
			width: 12px;
			height: 12px;
			transition: transform .25s ease;
		}
		.aro-notes.is-open > .aro-notes__more svg { transform: rotate(180deg); }

		@media (prefers-reduced-motion: reduce) {
			.aro-notes.is-open > .aro-extra { animation: none; }
			.aro-notes__more svg { transition: none; }
		}
	</style>
	<script id="aromist-notes-show-more-js">
	(function () {
		var LIMIT = 5;
		var ITEM = 'jet-listing-dynamic-repeater__item';

		function fa(n) {
			return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; });
		}

		function setup(list) {
			if (list.classList.contains('aro-notes') || !list.querySelector('a[href*="/note/"]')) {
				return;
			}
			list.classList.add('aro-notes');

			var widget = list.closest('.elementor-widget');
			if (widget) {
				widget.classList.add('aro-notes-widget');
				if (widget.parentElement) widget.parentElement.classList.add('aro-notes-row');
			}

			// هر جداکننده را داخل آیتم قبلی‌اش ببر
			Array.prototype.slice.call(list.children).forEach(function (el) {
				var prev = el.previousElementSibling;
				if (el.classList.contains('jet-listing-dynamic-repeater__delimiter') && prev && prev.classList.contains(ITEM)) {
					prev.appendChild(el);
				}
			});

			var items = Array.prototype.slice.call(list.children).filter(function (el) {
				return el.classList.contains(ITEM);
			});
			var total = items.length;
			if (total <= LIMIT) return;

			// آیتم‌های بعد از پنجمی مخفی می‌شوند
			items[LIMIT - 1].classList.add('aro-last-visible');
			items.slice(LIMIT).forEach(function (el, i) {
				el.classList.add('aro-extra');
				el.style.setProperty('--aro-i', i);
			});

			var hidden = total - LIMIT;
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'aro-notes__more';
			btn.setAttribute('aria-expanded', 'false');
			btn.innerHTML = '<span class="aro-notes__label"></span>' +
				'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
			var label = btn.firstChild;

			function render(open) {
label.textContent = open ? 'بستن' : 'نمایش بیشتر';
				btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			}

			btn.addEventListener('click', function () {
				render(list.classList.toggle('is-open'));
			});

			render(false);
			list.appendChild(btn);
		}

		function init(root) {
			(root || document).querySelectorAll('.jet-listing-dynamic-repeater__items').forEach(setup);
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function () { init(); });
		} else {
			init();
		}

		// پیش‌نمایش ادیتور المنتور
		if (window.jQuery) {
			jQuery(window).on('elementor/frontend/init', function () {
				elementorFrontend.hooks.addAction('frontend/element_ready/jet-listing-dynamic-repeater.default', function ($scope) {
					init($scope[0]);
				});
			});
		}
	})();
	</script>
	<?php
}, 20 );
