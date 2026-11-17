/* «تنظیمات تکپینو»: جابه‌جایی بین بخش‌ها بدون بارگذاری صفحه و ذخیره با AJAX. */
(function () {
	'use strict';

	var form = document.getElementById('tpx-form');
	if (!form) {
		return;
	}
	var views = form.querySelectorAll('.tpx-view');
	var navs = form.querySelectorAll('[data-nav]');
	var saveBtn = form.querySelector('.tpx-save');
	var stateEl = form.querySelector('.tpx-state');
	var search = form.querySelector('.tpx-search input');
	var toast = document.getElementById('tpx-toast');
	var dirty = false;
	var busy = false;
	var current = '';

	// ---- navigation (#module in the address bar, so a link or refresh opens the same section)
	function show(id, focus) {
		var found = false;
		views.forEach(function (v) {
			var on = v.getAttribute('data-view') === id;
			v.hidden = !on;
			found = found || on;
		});
		if (!found && id !== 'overview') {
			return show('overview', focus);
		}
		current = id;
		navs.forEach(function (a) {
			var on = a.getAttribute('data-nav') === id;
			a.classList.toggle('is-active', on);
			if (on) {
				a.setAttribute('aria-current', 'page');
			} else {
				a.removeAttribute('aria-current');
			}
		});
		if (focus) {
			var h = form.querySelector('.tpx-view:not([hidden]) h2');
			if (h) {
				h.focus({ preventScroll: true });
			}
			var top = form.getBoundingClientRect().top + window.pageYOffset - 40;
			if (window.pageYOffset > top) {
				window.scrollTo(0, top);
			}
		}
	}
	window.addEventListener('hashchange', function () {
		show(location.hash.slice(1) || 'overview', true);
	});
	show(location.hash.slice(1) || 'overview', false);

	// ---- search (Persian ي/ك typed on Arabic keyboards still match)
	function norm(s) {
		return (s || '').toLowerCase().replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/‌/g, ' ').trim();
	}
	search.addEventListener('input', function () {
		var q = norm(search.value);
		form.querySelectorAll('[data-search]').forEach(function (el) {
			el.hidden = q !== '' && norm(el.getAttribute('data-search')).indexOf(q) === -1;
		});
		form.querySelectorAll('[data-group]').forEach(function (g) {
			g.hidden = !g.querySelector('[data-search]:not([hidden])');
		});
		if (q !== '' && current !== 'overview') {
			history.replaceState(null, '', '#overview'); // no hashchange: typing focus stays in the search box
			show('overview', false);
		}
	});
	search.addEventListener('keydown', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			var first = form.querySelector('.tpx-cards [data-search]:not([hidden])');
			if (first) {
				location.hash = first.getAttribute('href').slice(1);
			}
		}
	});

	// ---- unsaved changes
	function setDirty(on) {
		dirty = on;
		form.classList.toggle('is-dirty', on);
		saveBtn.disabled = !on || busy;
		stateEl.textContent = on ? 'تغییرات ذخیره نشده' : 'همه‌چیز ذخیره شده';
	}
	function inSearch(el) {
		return el === search;
	}
	form.addEventListener('change', function (e) {
		if (inSearch(e.target)) {
			return;
		}
		var t = e.target;
		if (t.hasAttribute('data-module')) {
			if (!t.checked && t.getAttribute('data-warn') && !window.confirm(t.getAttribute('data-warn') + '\n\nاین ماژول خاموش شود؟')) {
				t.checked = true;
				return;
			}
			form.querySelectorAll('[data-status="' + t.getAttribute('data-module') + '"]').forEach(function (el) {
				el.classList.toggle('is-off', !t.checked);
				if (el.hasAttribute('data-on')) {
					el.textContent = el.getAttribute(t.checked ? 'data-on' : 'data-off');
				}
			});
			var on = form.querySelectorAll('[data-module]:checked').length;
			form.querySelector('[data-count="on"]').textContent = on;
			form.querySelector('[data-count="off"]').textContent = form.querySelectorAll('[data-module]').length - on;
		}
		setDirty(true);
	});

	// ---- colours: picker and text box stay in sync; empty text = module default
	function hex(v) {
		v = (v || '').trim().replace(/^#/, '');
		if (/^[0-9a-f]{3}$/i.test(v)) {
			v = v[0] + v[0] + v[1] + v[1] + v[2] + v[2];
		}
		return /^[0-9a-f]{6}$/i.test(v) ? '#' + v.toLowerCase() : '';
	}
	function paintSwatch(box, value) {
		var sw = form.querySelector('.tpx-swatches [data-sw="' + box.getAttribute('data-sw') + '"]');
		if (sw) {
			sw.style.background = value;
		}
	}
	form.addEventListener('input', function (e) {
		var box = e.target.closest('.tpx-color');
		if (!box) {
			if (!inSearch(e.target)) {
				setDirty(true);
			}
			return;
		}
		var pick = box.querySelector('.tpx-color__pick');
		var text = box.querySelector('.tpx-color__hex');
		if (e.target === pick) {
			text.value = pick.value;
		} else if (hex(text.value)) {
			pick.value = hex(text.value);
		}
		var ok = text.value === '' || hex(text.value) !== '';
		text.classList.toggle('is-bad', !ok);
		text.setAttribute('aria-invalid', ok ? 'false' : 'true');
		box.querySelector('.tpx-color__reset').hidden = text.value === '';
		paintSwatch(box, hex(text.value) || box.getAttribute('data-default'));
		setDirty(true);
	});
	form.addEventListener('click', function (e) {
		var btn = e.target.closest('.tpx-color__reset');
		if (!btn) {
			return;
		}
		var box = btn.closest('.tpx-color');
		var text = box.querySelector('.tpx-color__hex');
		text.value = '';
		text.classList.remove('is-bad');
		text.setAttribute('aria-invalid', 'false');
		box.querySelector('.tpx-color__pick').value = box.getAttribute('data-default');
		btn.hidden = true;
		paintSwatch(box, box.getAttribute('data-default'));
		text.focus();
		setDirty(true);
	});

	// ---- save
	var toastTimer;
	function notify(msg, isError, reload) {
		toast.textContent = msg;
		toast.className = 'tpx-toast is-show' + (isError ? ' is-err' : '');
		if (reload) {
			var b = document.createElement('button');
			b.type = 'button';
			b.textContent = 'تازه‌سازی صفحه';
			b.addEventListener('click', function () {
				location.reload();
			});
			toast.appendChild(b);
		}
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () {
			toast.classList.remove('is-show');
		}, reload ? 10000 : 3500);
	}

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		if (busy || !dirty) {
			return;
		}
		var bad = form.querySelector('.tpx-color__hex.is-bad');
		if (bad) {
			var view = bad.closest('.tpx-view').getAttribute('data-view');
			history.replaceState(null, '', '#' + view);
			show(view, false);
			bad.focus();
			notify('کد رنگ درست نیست؛ به شکل ‎#009879‎ بنویسید یا خالی بگذارید.', true);
			return;
		}
		busy = true;
		saveBtn.disabled = true;
		saveBtn.classList.add('is-busy');
		stateEl.textContent = 'در حال ذخیره…';

		var data = new FormData(form);
		data.append('action', 'techpino_panel_save');
		data.append('_ajax_nonce', form.getAttribute('data-nonce'));

		fetch(form.getAttribute('data-ajax'), { method: 'POST', body: data, credentials: 'same-origin' })
			.then(function (r) {
				return r.json().catch(function () {
					throw new Error('پاسخ سرور قابل خواندن نبود (کد ' + r.status + ').');
				});
			})
			.then(function (res) {
				if (!res || !res.success) {
					throw new Error((res && res.data && res.data.message) || 'ذخیره نشد. صفحه را تازه کنید و دوباره امتحان کنید.');
				}
				busy = false;
				setDirty(false);
				notify(res.data.message + (res.data.reload ? ' برای دیدن منوی ماژول‌هایی که روشن/خاموش شدند صفحه را تازه کنید.' : ''), false, res.data.reload);
			})
			.catch(function (err) {
				busy = false;
				setDirty(true);
				stateEl.textContent = 'ذخیره نشد';
				notify(err && err.message ? err.message : 'ذخیره نشد. اتصال اینترنت را بررسی کنید.', true);
			})
			.then(function () {
				saveBtn.classList.remove('is-busy');
			});
	});

	document.addEventListener('keydown', function (e) {
		if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S' || e.key === 'س')) {
			e.preventDefault();
			if (form.requestSubmit) {
				form.requestSubmit();
			} else {
				saveBtn.click();
			}
		}
	});
	window.addEventListener('beforeunload', function (e) {
		if (dirty) {
			e.preventDefault();
			e.returnValue = '';
		}
	});
})();
