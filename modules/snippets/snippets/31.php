<?php
// اسنیپت #31: اعلان های ووکامرس اختصاصی تکپینو
defined( 'ABSPATH' ) || exit;


/**
 * Aromist – اعلان‌های ووکامرس به‌صورت Toast (پاپ‌آپ کوچک)
 *
 * همه پیام‌های ووکامرس (موفقیت، خطا، اطلاع‌رسانی، افزودن به سبد) را به Toast با رنگ‌های آرومیست تبدیل می‌کند.
 * بدون کتابخانه خارجی (بدون CDN) — سبک و سریع. شورت‌کد ندارد.
 *
 * در Code Snippets: نوع PHP، اجرا در همه جا. اسنیپت Toast قبلی (Toastify) را غیرفعال کنید.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- ۱) نام محصول روی دکمه‌های «افزودن به سبد» لیست محصولات (برای متن اعلان) ---------- */
add_filter( 'woocommerce_loop_add_to_cart_args', function ( $args, $product ) {
	if ( $product instanceof WC_Product ) {
		$args['attributes'] = ( isset( $args['attributes'] ) && is_array( $args['attributes'] ) ) ? $args['attributes'] : array();
		$args['attributes']['data-product_name'] = wp_strip_all_tags( $product->get_name() );
	}
	return $args;
}, 10, 2 );

/* ---------- ۲) استایل (در هد) ---------- */
add_action( 'wp_head', function () {
	if ( is_admin() || ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	?>
	<script>document.documentElement.classList.add('ac-toast-js');</script>
	<style id="aromist-toast-css">
	:root{
		--act-bg:rgba(6,62,51,.96);        /* سبز تیره آرومیست */
		--act-text:#FFF8EC;                /* کرمی روشن */
		--act-success:#B3E0D7;             /* سبز کم‌رنگ */
		--act-info:#E6C498;                /* کرمی/طلایی */
		--act-error:#FF9A92;
		--act-btn-text:#063E33;
	}

	/* تا زمانی که پیام به Toast تبدیل شود، نسخه داخل صفحه دیده نشود (فقط در محل‌های اعلان) */
	html.ac-toast-js .woocommerce-notices-wrapper > .woocommerce-message,
	html.ac-toast-js .woocommerce-notices-wrapper > .woocommerce-error,
	html.ac-toast-js .woocommerce-notices-wrapper > .woocommerce-info,
	html.ac-toast-js .woocommerce-NoticeGroup .woocommerce-message,
	html.ac-toast-js .woocommerce-NoticeGroup .woocommerce-error,
	html.ac-toast-js .woocommerce-NoticeGroup .woocommerce-info{display:none !important}

	/* ===== محل نمایش: بالا سمت راست ===== */
	.ac-toasts{
		position:fixed;z-index:999999;top:18px;right:18px;left:auto;bottom:auto;
		display:flex;flex-direction:column;align-items:flex-end;gap:10px;
		width:min(390px,calc(100vw - 28px));pointer-events:none;direction:rtl;
	}
	body.admin-bar .ac-toasts{top:50px}

	/* ===== خود Toast (همان طراحی نمونه، با رنگ‌های آرومیست) ===== */
	.ac-toast{
		--accent:var(--act-info);
		position:relative;overflow:hidden;box-sizing:border-box;pointer-events:auto;
		width:100%;max-width:none;min-height:68px;padding:13px 14px;
		display:flex;align-items:center;gap:11px;direction:rtl;text-align:right;
		font-family:inherit;font-size:14px;line-height:1.8;color:var(--act-text);
		background:var(--act-bg) !important;border:1px solid rgba(255,255,255,.1);border-radius:16px;
		box-shadow:0 18px 55px rgba(0,0,0,.22);
		-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);
		opacity:0;transform:translateY(-16px);
		transition:opacity .4s cubic-bezier(.215,.61,.355,1),transform .4s cubic-bezier(.215,.61,.355,1);
	}
	.ac-toast.is-visible{opacity:1;transform:none}
	.ac-toast.is-leaving{opacity:0;transform:translateY(-10px)}

	.ac-toast--success{--accent:var(--act-success)}
	.ac-toast--error{--accent:var(--act-error)}
	.ac-toast--info{--accent:var(--act-info)}

	/* نوار رنگی کنار */
	.ac-toast::before{content:"";position:absolute;top:0;bottom:0;left:0;right:auto;width:4px;background:var(--accent)}

	/* نوار زمان پایین */
	.ac-toast__progress{
		position:absolute;left:0;right:0;bottom:0;height:2px;background:var(--accent);
		transform-origin:right;animation:acToastProgress var(--duration,5200ms) linear forwards;
	}
	.ac-toast:hover .ac-toast__progress{animation-play-state:paused}
	@keyframes acToastProgress{from{transform:scaleX(1)}to{transform:scaleX(0)}}

	.ac-toast__icon{
		width:36px;height:36px;flex:0 0 36px;border-radius:11px;display:grid;place-items:center;
		background:rgba(255,255,255,.08);color:var(--accent);
	}
	.ac-toast__icon svg{width:19px;height:19px;display:block}

	.ac-toast__body{flex:1 1 auto;min-width:0;display:flex;flex-direction:column;align-items:flex-start;gap:8px}
	.ac-toast__text{min-width:0;font-weight:600;word-break:break-word;overflow-wrap:anywhere}

	/* دکمه داخل اعلان (مثل «مشاهده سبد خرید») */
	.ac-toast__action{
		display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 8px 0 14px;border-radius:100px;
		background:var(--accent);color:var(--act-btn-text) !important;font-size:12.5px;font-weight:700;line-height:1;
		text-decoration:none !important;white-space:nowrap;
		box-shadow:0 6px 16px rgba(0,0,0,.18),inset 0 1px 0 rgba(255,255,255,.35);
		transition:transform .2s ease,box-shadow .2s ease,filter .2s ease;
	}
	.ac-toast__action:hover{transform:translateY(-1px);filter:brightness(1.05);box-shadow:0 10px 22px rgba(0,0,0,.24),inset 0 1px 0 rgba(255,255,255,.35)}
	.ac-toast__action:active{transform:none}
	.ac-toast__action-arrow{
		width:20px;height:20px;border-radius:50%;display:grid;place-items:center;
		background:rgba(6,62,51,.14);transition:transform .2s ease;
	}
	.ac-toast__action-arrow svg{width:12px;height:12px;display:block}
	.ac-toast__action:hover .ac-toast__action-arrow{transform:translateX(-2px)}

	.ac-toast__close{
		order:3;flex:0 0 auto;margin:0;padding:4px;border:0;background:transparent;box-shadow:none;
		color:#fff;opacity:.55;cursor:pointer;line-height:1;display:flex;align-items:center;justify-content:center;
		transition:.2s ease;align-self:center;
	}
	.ac-toast__close:hover{opacity:1;transform:rotate(90deg);background:transparent;color:#fff}
	.ac-toast__close svg{width:18px;height:18px;display:block}

	/* ===== موبایل: نوار تمام‌عرض بالای صفحه ===== */
	@media (max-width:600px){
		.ac-toasts,
		body.admin-bar .ac-toasts{top:0;right:0;left:0;width:100%;align-items:stretch;gap:6px}
		.ac-toast{
			width:100%;min-height:60px;border-radius:0 0 16px 16px;border-width:0 0 1px;
			padding:calc(11px + env(safe-area-inset-top,0px)) 14px 12px;font-size:13px;line-height:1.7;
			transform:translateY(-100%);
		}
		.ac-toast + .ac-toast{border-radius:16px;margin:0 8px;width:auto;padding-top:11px}
		.ac-toast.is-leaving{transform:translateY(-100%)}
		.ac-toast__icon{width:34px;height:34px;flex-basis:34px;border-radius:10px}
	}

	@media (prefers-reduced-motion:reduce){
		.ac-toast,.ac-toast.is-leaving{transition:opacity .2s;transform:none}
		.ac-toast__progress{animation:none}
	}
	</style>
	<?php
}, 99 );

/* ---------- ۳) اسکریپت (در فوتر) ---------- */
add_action( 'wp_footer', function () {
	if ( is_admin() || ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	$config = array(
		'added'        => 'به سبد خرید اضافه شد.',
		'addedProduct' => '«%s» به سبد خرید اضافه شد.',
		'viewCart'     => 'مشاهده سبد خرید',
		'cartUrl'      => wc_get_cart_url(),
		'close'        => 'بستن',
	);
	?>
	<script id="aromist-toast-js">
	(function () {
		'use strict';

		var cfg = <?php echo wp_json_encode( $config ); ?>;

		var NOTICE = '.woocommerce-message, .woocommerce-error, .woocommerce-info, .wc-block-components-notice-banner';
		// محل‌هایی که ووکامرس پیام‌های لحظه‌ای را در آن‌ها چاپ می‌کند
		var CONTAINERS = '.woocommerce-notices-wrapper, .woocommerce-NoticeGroup, .ac-coupon-notice, .wc-block-components-notices, .wc-block-store-notices';
		// پیام‌هایی که بخشی از محتوای صفحه‌اند و نباید Toast شوند
		var EXCLUDE = '.woocommerce-form-login-toggle, .woocommerce-form-coupon-toggle, .woocommerce-no-products-found, .wc-empty-cart-message, .aromist-cart-empty, [data-no-toast]';

		var ICONS = {
			success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.2 4.2L19 7"/></svg>',
			error:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 7v6"/><circle cx="12" cy="16.8" r=".9" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="9"/></svg>',
			info:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6"/><circle cx="12" cy="7.6" r=".9" fill="currentColor" stroke="none"/></svg>',
			arrow:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>',
			close:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>'
		};

		var recent = {};
		var lastButton = null;
		var stack = null;

		function tidy(v) { return String(v || '').replace(/\s+/g, ' ').trim(); }

		function getStack() {
			if (!stack || !document.body.contains(stack)) {
				stack = document.createElement('div');
				stack.className = 'ac-toasts';
				document.body.appendChild(stack);
			}
			return stack;
		}

		function getType(el) {
			if (el.matches('.woocommerce-error, .is-error')) { return 'error'; }
			if (el.matches('.woocommerce-message, .is-success')) { return 'success'; }
			return 'info';
		}

		function getText(el) {
			var clone = el.cloneNode(true);
			clone.querySelectorAll('a.button, a.wc-forward, button, svg, .button, [role="button"], .screen-reader-text').forEach(function (n) { n.remove(); });
			return tidy(clone.textContent);
		}

		// لینک اقدام داخل پیام (مثل «مشاهده سبد خرید») — فقط لینک‌های همین سایت
		function getAction(el) {
			var a = el.querySelector('a.wc-forward, a.button');
			if (!a || !a.getAttribute('href')) { return null; }
			try {
				var url = new URL(a.getAttribute('href'), window.location.href);
				if (url.origin !== window.location.origin) { return null; }
				return { href: url.href, text: tidy(a.textContent) || cfg.viewCart };
			} catch (e) { return null; }
		}

		function toast(message, type, action) {
			message = tidy(message);
			type = type || 'info';
			if (!message) { return false; }

			var key = type + '|' + message, now = Date.now();
			if (recent[key] && now - recent[key] < 1800) { return false; }
			recent[key] = now;

			var duration = type === 'error' ? 7000 : 5200;

			var el = document.createElement('div');
			el.className = 'ac-toast ac-toast--' + type;
			el.setAttribute('role', type === 'error' ? 'alert' : 'status');
			el.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
			el.style.setProperty('--duration', duration + 'ms');

			var icon = document.createElement('span');
			icon.className = 'ac-toast__icon';
			icon.innerHTML = ICONS[type] || ICONS.info;

			var body = document.createElement('div');
			body.className = 'ac-toast__body';
			var text = document.createElement('span');
			text.className = 'ac-toast__text';
			text.textContent = message;
			body.appendChild(text);

			if (action && action.href) {
				var link = document.createElement('a');
				link.className = 'ac-toast__action';
				link.href = action.href;
				var label = document.createElement('span');
				label.textContent = action.text;
				var arrow = document.createElement('span');
				arrow.className = 'ac-toast__action-arrow';
				arrow.innerHTML = ICONS.arrow;
				link.appendChild(label);
				link.appendChild(arrow);
				body.appendChild(link);
			}

			var close = document.createElement('button');
			close.type = 'button';
			close.className = 'ac-toast__close';
			close.setAttribute('aria-label', cfg.close);
			close.innerHTML = ICONS.close;

			var progress = document.createElement('span');
			progress.className = 'ac-toast__progress';

			el.appendChild(icon);
			el.appendChild(body);
			el.appendChild(close);
			el.appendChild(progress);
			getStack().appendChild(el);

			// نمایش
			requestAnimationFrame(function () { requestAnimationFrame(function () { el.classList.add('is-visible'); }); });

			// زمان‌سنج با توقف روی هاور
			var remaining = duration, started = Date.now(), timer = null, gone = false;
			function hide() {
				if (gone) { return; }
				gone = true;
				clearTimeout(timer);
				el.classList.add('is-leaving');
				el.classList.remove('is-visible');
				setTimeout(function () { el.remove(); }, 300);
			}
			function start() { started = Date.now(); timer = setTimeout(hide, remaining); }
			function pause() { clearTimeout(timer); remaining -= Date.now() - started; }

			el.addEventListener('mouseenter', pause);
			el.addEventListener('mouseleave', start);
			close.addEventListener('click', hide);
			start();

			return true;
		}

		function shouldToast(el) {
			if (!(el instanceof Element) || el.dataset.acToastDone === '1') { return false; }
			if (el.closest(EXCLUDE)) { return false; }
			// پیام‌های «اطلاع‌رسانی» خارج از محل اعلان‌ها معمولاً بخشی از صفحه‌اند (مثل «محصولی یافت نشد»)
			if (getType(el) === 'info' && !el.closest(CONTAINERS)) { return false; }
			return true;
		}

		function processNotice(el) {
			if (!shouldToast(el)) { return; }
			el.dataset.acToastDone = '1';

			var type = getType(el);
			var items = el.matches('ul.woocommerce-error, ul.woocommerce-message, ul.woocommerce-info')
				? Array.prototype.slice.call(el.querySelectorAll(':scope > li'))
				: [];

			if (items.length) {
				items.forEach(function (li) { toast(getText(li), type, getAction(li)); });
			} else {
				toast(getText(el), type, getAction(el));
			}

			// پیام داخل صفحه حذف شود (یا نمایش داده شد، یا تکراری بود)
			el.remove();
		}

		function scan(root) {
			root = root || document;
			if (root instanceof Element && root.matches(NOTICE)) { processNotice(root); }
			if (root.querySelectorAll) { root.querySelectorAll(NOTICE).forEach(processNotice); }
		}

		// ----- افزودن به سبد (ایجکس) -----
		function productName(btn) {
			if (!(btn instanceof Element)) { return ''; }
			if (btn.dataset.product_name) { return tidy(btn.dataset.product_name); }
			var box = btn.closest('.product, .wc-block-grid__product, .wc-block-product, .wp-block-post, [data-wc-context]');
			var title = box ? box.querySelector('.product_title, .woocommerce-loop-product__title, .wc-block-grid__product-title, .wc-block-components-product-name, .wp-block-post-title, .elementor-heading-title') : null;
			if (!title && document.body.classList.contains('single-product')) { title = document.querySelector('.product_title'); }
			return tidy(title ? title.textContent : '');
		}

		function added(btn) {
			var name = productName(btn || lastButton);
			var msg = name ? cfg.addedProduct.replace('%s', name) : cfg.added;
			toast(msg, 'success', { href: cfg.cartUrl, text: cfg.viewCart });
		}

		document.addEventListener('click', function (e) {
			var btn = e.target.closest('.add_to_cart_button, .single_add_to_cart_button, .wc-block-components-product-button__button, [data-product_id]');
			if (btn) { lastButton = btn; }
		}, true);

		if (window.jQuery) {
			window.jQuery(document.body).on('added_to_cart', function (e, fragments, hash, $button) {
				added(($button && $button[0]) || lastButton);
			});
		}
		document.addEventListener('wc-blocks_added_to_cart', function () { added(lastButton); });

		// ----- پیام‌هایی که بعداً (با ایجکس) اضافه می‌شوند -----
		var observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (m) {
				m.addedNodes.forEach(function (node) {
					if (node.nodeType === 1 && !(node.closest && node.closest('.ac-toasts'))) {
						setTimeout(function () { scan(node); }, 0);
					}
				});
			});
		});

		function init() {
			scan(document);
			observer.observe(document.body, { childList: true, subtree: true });
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', init, { once: true });
		} else {
			init();
		}
	})();
	</script>
	<?php
}, 99 );
