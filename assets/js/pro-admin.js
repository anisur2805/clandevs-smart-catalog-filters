/**
 * Clandevs Smart Catalog Filters Pro — Admin JS
 */
(function ($) {
	'use strict';

	function i18n(key, fallback) {
		return (window.cscfpSwatches && window.cscfpSwatches[key]) || fallback;
	}

	function initSwatchUploader() {
		if (typeof window.wp === 'undefined' || !window.wp.media) {
			return;
		}

		$(document).on('click', '.cscf-upload-swatch', function (event) {
			event.preventDefault();

			var $button = $(this);
			var $item = $button.closest('.cscf-swatch-item');
			var $input = $item.find('.cscf-swatch-input');
			var $preview = $item.find('.cscf-swatch-preview');

			var frame = window.wp.media({
				title: i18n('mediaTitle', 'Select swatch image'),
				button: { text: i18n('mediaButton', 'Use image') },
				library: { type: 'image' },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();

				if (!attachment || !attachment.id) {
					return;
				}

				var url =
					attachment.sizes && attachment.sizes.thumbnail
						? attachment.sizes.thumbnail.url
						: attachment.url;

				$input.val(attachment.id);
				$preview.attr('src', url).removeClass('is-empty');
				$button.text(i18n('changeImage', 'Change Image'));
				$item.find('.cscf-remove-swatch').removeAttr('hidden');
			});

			frame.open();
		});

		$(document).on('click', '.cscf-remove-swatch', function (event) {
			event.preventDefault();

			var $item = $(this).closest('.cscf-swatch-item');

			$item.find('.cscf-swatch-input').val('0');
			$item.find('.cscf-swatch-preview').attr('src', '').addClass('is-empty');
			$item.find('.cscf-upload-swatch').text(i18n('setImage', 'Set Image'));
			$(this).attr('hidden', 'hidden');
		});
	}

	$(initSwatchUploader);
})(jQuery);
