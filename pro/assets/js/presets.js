/**
 * Clandevs Smart Catalog Filters Pro — Presets JS
 */
(function($) {
	'use strict';

	$(document).on('click', '.cscf-load-preset', function(e) {
		e.preventDefault();
		var id = $(this).closest('.cscf-preset-item').data('id');
		$.post(cscfpPresets.ajaxUrl, {
			action: 'cscf_load_preset',
			id: id,
			_nonce: cscfpPresets.nonce || ''
		}, function(res) {
			if (res.success && res.data.filters) {
				// Apply filters to the form.
				var params = new URLSearchParams(res.data.filters);
				window.location.href = window.location.pathname + '?' + params.toString();
			}
		});
	});

	$(document).on('click', '.cscf-delete-preset', function(e) {
		e.preventDefault();
		var $item = $(this).closest('.cscf-preset-item');
		var id = $item.data('id');
		$.post(cscfpPresets.ajaxUrl, {
			action: 'cscf_delete_preset',
			id: id,
			_nonce: cscfpPresets.nonce || ''
		}, function(res) {
			if (res.success) {
				$item.fadeOut(200, function() { $(this).remove(); });
			}
		});
	});
})(jQuery);
