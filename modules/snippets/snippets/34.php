<?php
// اسنیپت #34: live search
defined( 'ABSPATH' ) || exit;


/**
 * Aromist – جستجوی زنده برای Loop Grid المنتور (برندها، نت‌ها، گروه‌های بویایی و ...)
 *
 * شورت‌کد:
 *   [aromist_live_seaطrch target="aromist-brands-grid" placeholder="نام برند موردنظر خود را وارد کنید......"]
 *
 * target = کلاس CSS که روی ویجت Loop Grid گذاشته‌اید (Advanced → CSS Classes)
 *
 * جستجو در لحظه و بدون بارگذاری مجدد انجام می‌شود (فارسی و انگلیسی/نامک).
 * در Code Snippets: نوع PHP، اجرا در همه جا.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'aromist_live_search', function ( $atts ) {
	$atts = shortcode_atts( array(
		'target'      => 'aromist-brands-grid',
		'placeholder' => 'نام برند موردنظر خود را وارد کنید......',
		'empty'       => 'موردی با این نام پیدا نشد.',
	), $atts, 'aromist_live_search' );

	$target = sanitize_html_class( $atts['target'] );
	$id     = 'ac-ls-' . wp_unique_id();

	static $assets_printed = false;
	ob_start();

	if ( ! $assets_printed ) {
		$assets_printed = true;
		?>
		<style id="aromist-live-search-css">
		.ac-ls{position:relative;width:100%;direction:rtl;font-family:inherit;box-sizing:border-box}
		.ac-ls *{box-sizing:border-box}
		.ac-ls__field{position:relative;display:flex;align-items:center}
		.ac-ls input.ac-ls__input{
			width:100%;height:48px;margin:0;padding:4px 12px 4px 46px !important;
			border:1px solid #DADADA80 !important;border-radius:16px !important;background:#fff !important;
			color:#221E1F;font-family:inherit;font-size:clamp(14px,2vw,16px);line-height:normal;text-align:right;direction:rtl;
			box-shadow:none !important;outline:0;transition:border-color .3s;-webkit-appearance:none;appearance:none;
		}
		.ac-ls.has-value input.ac-ls__input{padding-right:44px !important}
		.ac-ls__icon{position:absolute;left:12px;right:auto;top:50%;transform:translateY(-50%);width:24px;height:24px;pointer-events:none;display:flex;z-index:1}
		.ac-ls__icon svg{width:24px !important;height:24px !important;display:block}
		.ac-ls input.ac-ls__input::placeholder{color:#707070;opacity:1}
		.ac-ls input.ac-ls__input:focus{border-color:#009879 !important}
		.ac-ls input.ac-ls__input::-webkit-search-cancel-button{-webkit-appearance:none;display:none}
		.ac-ls__clear{
			position:absolute;right:10px;left:auto;top:50%;transform:translateY(-50%);width:28px;height:28px;border-radius:9px;border:0;
			background:#B3E0D7;color:#063E33;display:none;align-items:center;justify-content:center;cursor:pointer;padding:0;transition:.2s;
		}
		.ac-ls__clear:hover{background:#009879;color:#fff}
		.ac-ls__clear svg{width:14px;height:14px;display:block}
		.ac-ls.has-value .ac-ls__clear{display:flex}
		.ac-ls__status{margin:10px 4px 0;font-size:13px;color:#707070;min-height:0}
		.ac-ls__status:empty{display:none}
		.ac-ls__empty{
			display:none;margin:20px 0;padding:28px 16px;border-radius:16px;background:#FFF8EC;border:1px dashed #E6C498;
			text-align:center;color:#221E1F;font-size:15px;
		}
		.ac-ls-hidden{display:none !important}
		</style>
		<script id="aromist-live-search-js">
		(function () {
			'use strict';

			// یکسان‌سازی متن فارسی/انگلیسی برای مقایسه
			function normalize(s) {
				s = String(s || '');
				try { s = decodeURIComponent(s); } catch (e) {}
				return s
					.replace(/[يى]/g, 'ی').replace(/ك/g, 'ک').replace(/[ةۀ]/g, 'ه')
					.replace(/[أإآٱ]/g, 'ا').replace(/ؤ/g, 'و')
					.replace(/[ً-ٰٟ]/g, '')        // اعراب
					.replace(/[‌‍‎‏]/g, '')   // نیم‌فاصله و کاراکترهای جهت
					.replace(/[-_\/.,،]+/g, ' ')
					.toLowerCase().replace(/\s+/g, ' ').trim();
			}

			function itemText(item) {
				if (item.dataset.acLsText) { return item.dataset.acLsText; }
				var parts = [item.textContent];
				// نامک لینک (مثلاً /brand/azzaro/) برای جستجوی انگلیسی
				item.querySelectorAll('a[href]').forEach(function (a) {
					try { parts.push(new URL(a.getAttribute('href'), location.href).pathname); } catch (e) {}
				});
				item.querySelectorAll('img[alt]').forEach(function (img) { parts.push(img.alt); });
				return (item.dataset.acLsText = normalize(parts.join(' ')));
			}

			function setup(box) {
				if (box.dataset.acLsReady) { return; }
				box.dataset.acLsReady = '1';

				var input  = box.querySelector('.ac-ls__input');
				var clear  = box.querySelector('.ac-ls__clear');
				var status = box.querySelector('.ac-ls__status');
				var target = box.getAttribute('data-target');
				var empty  = box.querySelector('.ac-ls__empty');
				var timer;

				function grids() {
					var found = target ? Array.prototype.slice.call(document.querySelectorAll('.' + target)) : [];
					if (found.length) { return found; }

					// اگر کلاس روی Loop Grid گذاشته نشده بود: اولین Loop Grid بعد از همین فیلد جستجو
					var all = document.querySelectorAll('.elementor-widget-loop-grid');
					for (var i = 0; i < all.length; i++) {
						if (box.compareDocumentPosition(all[i]) & Node.DOCUMENT_POSITION_FOLLOWING) { return [all[i]]; }
					}
					return [];
				}

				function items() {
					var list = [];
					grids().forEach(function (g) {
						g.querySelectorAll('.e-loop-item, .elementor-loop-container > *').forEach(function (el) {
							if (list.indexOf(el) === -1 && !el.classList.contains('e-load-more-anchor')) { list.push(el); }
						});
					});
					return list;
				}

				function run() {
					var q = normalize(input.value);
					var terms = q ? q.split(' ') : [];
					var shown = 0, all = items();

					all.forEach(function (el) {
						var text = itemText(el);
						var ok = terms.every(function (t) { return text.indexOf(t) !== -1; });
						el.classList.toggle('ac-ls-hidden', !ok);
						if (ok) { shown++; }
					});

					box.classList.toggle('has-value', !!input.value);
					empty.style.display = (q && shown === 0) ? 'block' : 'none';
					status.textContent = q ? (shown + ' مورد پیدا شد') : '';

					// صفحه‌بندی المنتور هنگام جستجو پنهان شود
					grids().forEach(function (g) {
						g.querySelectorAll('.elementor-pagination, .e-load-more-anchor, .e-loop__load-more').forEach(function (p) {
							p.classList.toggle('ac-ls-hidden', !!q);
						});
					});
				}

				input.addEventListener('input', function () {
					clearTimeout(timer);
					timer = setTimeout(run, 120);
				});
				input.addEventListener('keydown', function (e) {
					if (e.key === 'Escape') { input.value = ''; run(); }
					if (e.key === 'Enter') { e.preventDefault(); run(); }
				});
				clear.addEventListener('click', function () { input.value = ''; run(); input.focus(); });

				// اگر آیتم‌های جدید اضافه شدند (بارگذاری بیشتر)، دوباره فیلتر شود
				var mo = new MutationObserver(function () { if (input.value) { run(); } });
				grids().forEach(function (g) { mo.observe(g, { childList: true, subtree: true }); });
			}

			function init() { document.querySelectorAll('.ac-ls').forEach(setup); }

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', init);
			} else {
				init();
			}
			// ویرایشگر المنتور
			window.addEventListener('elementor/frontend/init', function () { setTimeout(init, 300); });
		})();
		</script>
		<?php
	}
	?>
	<div class="ac-ls" id="<?php echo esc_attr( $id ); ?>" data-target="<?php echo esc_attr( $target ); ?>" role="search">
		<div class="ac-ls__field">
			<span class="ac-ls__icon"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 80 80" aria-hidden="true" focusable="false"><path d="M0 0h80v80H0z" fill="none"/><g fill="none"><path fill="#d7b56a" fill-rule="evenodd" d="M63.453 67.749L50.725 55.017a2.998 2.998 0 1 1 4.24-4.24l12.73 12.732a2.998 2.998 0 0 1-4.242 4.24" clip-rule="evenodd"/><path fill="#d7b56a" d="m50.725 55.017l1.414-1.414zm12.728 12.732l1.415-1.414zm-8.487-16.972l-1.415 1.414zM67.694 63.51l1.415-1.414zm0 4.24l1.414 1.414zM49.31 56.431l12.73 12.732l2.829-2.828L52.14 53.603zm0-7.068a5 5 0 0 0 0 7.068l2.83-2.828a1 1 0 0 1 0-1.412zm7.07 0a5 5 0 0 0-7.07 0l2.83 2.828a1 1 0 0 1 1.411 0zm12.73 12.732L56.38 49.363l-2.829 2.828L66.28 64.923zm0 7.068a5 5 0 0 0 0-7.068l-2.83 2.828a1 1 0 0 1 0 1.412zm-7.07 0a5 5 0 0 0 7.07 0l-2.83-2.828a1 1 0 0 1-1.411 0z"/><path fill="#009879" d="M15.597 29.069a19.053 19.053 0 1 1 36.807 9.863a19.053 19.053 0 0 1-36.808-9.863"/></g></svg></span>
			<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-input"><?php echo esc_html( $atts['placeholder'] ); ?></label>
			<input type="search" id="<?php echo esc_attr( $id ); ?>-input" class="ac-ls__input" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" autocomplete="off" enterkeyhint="search">
			<button type="button" class="ac-ls__clear" aria-label="پاک کردن جستجو"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
		</div>
		<div class="ac-ls__status" aria-live="polite"></div>
		<div class="ac-ls__empty"><?php echo esc_html( $atts['empty'] ); ?></div>
	</div>
	<?php
	return ob_get_clean();
} );
