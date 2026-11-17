/**
 * Clandevs Smart Catalog Filters Pro — Admin presets.
 */
(function ($) {
	'use strict';

	function renderRow(preset) {
		var $row = $('<div class="cscf-preset-item" />').attr('data-id', preset.id);

		$row.append($('<strong />').text(preset.name));
		$row.append($('<code />').text(preset.filters));
		$row.append(
			$('<button type="button" class="button cscf-delete-preset" />').text(
				cscfpPresets.i18n.delete
			)
		);

		return $row;
	}

	$(document).on('submit', '.cscf-preset-create-form', function (e) {
		e.preventDefault();

		var $form = $(this);
		var name = $.trim($form.find('[name="name"]').val());
		var filters = $.trim($form.find('[name="filters"]').val());

		if (!name) {
			window.alert(cscfpPresets.i18n.nameRequired);
			return;
		}

		$.post(cscfpPresets.ajaxUrl, {
			action: 'cscf_save_preset',
			nonce: cscfpPresets.nonce,
			name: name,
			filters: filters
		})
			.done(function (response) {
				if (!response || !response.success) {
					window.alert(
						(response && response.data && response.data.message) ||
							cscfpPresets.i18n.error
					);
					return;
				}

				$('.cscf-presets-empty').remove();
				$('#cscf-presets-list').append(renderRow(response.data));
				$form[0].reset();
			})
			.fail(function () {
				window.alert(cscfpPresets.i18n.error);
			});
	});

	$(document).on('click', '.cscf-delete-preset', function () {
		var $item = $(this).closest('.cscf-preset-item');

		if (!window.confirm(cscfpPresets.i18n.confirmDelete)) {
			return;
		}

		$.post(cscfpPresets.ajaxUrl, {
			action: 'cscf_delete_preset',
			nonce: cscfpPresets.nonce,
			id: $item.data('id')
		})
			.done(function (response) {
				if (response && response.success) {
					$item.remove();
					return;
				}

				window.alert(
					(response && response.data && response.data.message) || cscfpPresets.i18n.error
				);
			})
			.fail(function () {
				window.alert(cscfpPresets.i18n.error);
			});
	});
})(jQuery);
