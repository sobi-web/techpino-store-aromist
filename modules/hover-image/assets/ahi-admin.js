/* Aromist Hover Image – انتخاب تصویر هاور در ویرایش محصول */
(function ($) {
	'use strict';

	var frame;

	$(document).on('click', '.ahi-admin__select', function (e) {
		e.preventDefault();
		var $box = $(this).closest('.ahi-admin');

		frame = wp.media({
			title: 'انتخاب تصویر هاور',
			button: { text: 'استفاده به‌عنوان تصویر هاور' },
			library: { type: 'image' },
			multiple: false
		});

		frame.on('open', function () {
			var id = parseInt($box.find('.ahi-admin__input').val(), 10);
			if (id) {
				var att = wp.media.attachment(id);
				att.fetch();
				frame.state().get('selection').add(att);
			}
		});

		frame.on('select', function () {
			var att = frame.state().get('selection').first().toJSON();
			var sizes = att.sizes || {};
			var url = (sizes.medium || sizes.full || att).url;
			$box.find('.ahi-admin__input').val(att.id);
			$box.find('.ahi-admin__preview').html($('<img class="ahi-admin__img" alt="">').attr('src', url));
			$box.addClass('has-image');
		});

		frame.open();
	});

	$(document).on('click', '.ahi-admin__remove', function (e) {
		e.preventDefault();
		var $box = $(this).closest('.ahi-admin');
		$box.find('.ahi-admin__input').val('');
		$box.find('.ahi-admin__preview').empty();
		$box.removeClass('has-image');
	});
})(jQuery);
