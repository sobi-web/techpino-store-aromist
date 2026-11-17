/* Aromist Hover Image – front-end
 * همه‌ی رویدادها روی document گوش داده می‌شوند تا آیتم‌هایی که بعداً با Ajax اضافه می‌شوند
 * (Load more المنتور، فیلترهای JetSmartFilters و ...) هم بدون مقداردهی دوباره کار کنند.
 */
(function () {
	'use strict';

	// باکس محصول در Loop Grid المنتور، Listing Grid جت‌انجین، JetWooBuilder و لوپ پیش‌فرض ووکامرس.
	var CARD = '.e-loop-item, [data-elementor-type="loop-item"], .jet-listing-grid__item, .jet-woo-products__item, .jet-woo-builder-product, li.product, .ahi-card';

	var hovered = null;

	function load(root) {
		var img = root && root.querySelector('.ahi__hover');
		if (!img || img.classList.contains('is-loaded') || img.getAttribute('data-ahi-loading')) {
			return;
		}
		img.setAttribute('data-ahi-loading', '1');

		var done = function () {
			img.classList.add('is-loaded');
			img.removeAttribute('data-ahi-loading');
		};
		img.addEventListener('load', function () {
			if (img.decode) {
				img.decode().then(done, done);
			} else {
				done();
			}
		}, { once: true });
		img.addEventListener('error', function () {
			img.removeAttribute('data-ahi-loading');
		}, { once: true });

		// عرض واقعی نمایش، تا مرورگر از srcset کیفیت مناسب (با توجه به DPR) را بردارد و متن تار نشود.
		var w = Math.ceil(root.getBoundingClientRect().width);
		if (w > 0) {
			img.sizes = w + 'px';
		} else if (img.getAttribute('data-sizes')) {
			img.sizes = img.getAttribute('data-sizes');
		}
		if (img.getAttribute('data-srcset')) {
			img.srcset = img.getAttribute('data-srcset');
		}
		img.src = img.getAttribute('data-src');
	}

	/**
	 * از روی عنصری که موس/فوکوس رویش است، باکس تصویر مربوطه را پیدا می‌کند.
	 * preload: تصویری که باید از همین حالا بارگذاری شود.
	 * show:    تصویری که باید همین حالا نمایش داده شود (یا null).
	 */
	function resolve(target) {
		if (!target || !target.closest) {
			return null;
		}
		var root = target.closest('.ahi');
		if (root) {
			return { preload: root, show: root };
		}
		var card = target.closest(CARD);
		root = card && card.querySelector('.ahi');
		if (!root) {
			return null;
		}
		return { preload: root, show: root.getAttribute('data-ahi-trigger') === 'card' ? root : null };
	}

	function setHovered(next) {
		if (next === hovered) {
			return;
		}
		if (hovered) {
			hovered.classList.remove('is-hover');
		}
		hovered = next;
		if (next) {
			next.classList.add('is-hover');
		}
	}

	function onEnter(target) {
		var r = resolve(target);
		if (r) {
			load(r.preload);
		}
		setHovered(r ? r.show : null);
	}

	// موس / قلم (لمس نادیده گرفته می‌شود؛ برای لمس دکمه‌ی اطلاعات هست).
	document.addEventListener('pointerover', function (e) {
		if (e.pointerType === 'touch') {
			return;
		}
		onEnter(e.target);
	}, { passive: true });

	document.addEventListener('pointerout', function (e) {
		if (e.pointerType !== 'touch' && !e.relatedTarget) {
			setHovered(null); // موس از پنجره خارج شد
		}
	}, { passive: true });

	// صفحه‌کلید: وقتی لینک باکس محصول فوکوس می‌گیرد.
	document.addEventListener('focusin', function (e) {
		var visible = true;
		try {
			visible = e.target.matches(':focus-visible');
		} catch (err) {}
		if (visible) {
			onEnter(e.target);
		}
	});
	document.addEventListener('focusout', function (e) {
		if (!e.relatedTarget) {
			setHovered(null);
		}
	});

	// دکمه‌ی اطلاعات (دستگاه‌های لمسی). در فاز capture تا کلیک به لینک/کارت قابل‌کلیک بالادستی
	// (مثل overlay جت‌انجین یا لینک کل آیتم لوپ) نرسد.
	document.addEventListener('click', function (e) {
		var btn = e.target.closest && e.target.closest('.ahi__info');
		var actives = document.querySelectorAll('.ahi.is-active');
		var root = btn ? btn.closest('.ahi') : null;

		for (var i = 0; i < actives.length; i++) {
			if (actives[i] !== root && !actives[i].contains(e.target)) {
				actives[i].classList.remove('is-active');
				var b = actives[i].querySelector('.ahi__info');
				if (b) {
					b.setAttribute('aria-pressed', 'false');
				}
			}
		}

		if (!root) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		load(root);
		var on = root.classList.toggle('is-active');
		btn.setAttribute('aria-pressed', on ? 'true' : 'false');
	}, true);
})();
