/**
 * Clandevs Smart Catalog Filters Pro — Shopper presets and date quick ranges.
 */
(function ($) {
	'use strict';

	var STORAGE_KEY = 'cscf_user_presets';
	var MAX_PRESETS = 10;

	/* Filter query helpers. */

	function currentFilterQuery() {
		var params = new URLSearchParams(window.location.search);
		var keep = new URLSearchParams();

		params.forEach(function (value, key) {
			var isFilter =
				key.indexOf('cscf_') === 0 ||
				key === 'min_price' ||
				key === 'max_price' ||
				key === 'rating_filter';

			if (isFilter && value !== '') {
				keep.append(key, value);
			}
		});

		return keep.toString();
	}

	function presetUrl(query) {
		var base = window.location.origin + window.location.pathname;

		return query ? base + '?' + query : base;
	}

	/* Guest presets live in localStorage. */

	function readGuestPresets() {
		try {
			var parsed = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '[]');

			return Array.isArray(parsed) ? parsed : [];
		} catch (e) {
			return [];
		}
	}

	function writeGuestPresets(presets) {
		try {
			window.localStorage.setItem(STORAGE_KEY, JSON.stringify(presets));
		} catch (e) {
			// Storage unavailable (private mode); presets are session-only.
		}
	}

	function isLoggedIn() {
		var localized = typeof cscfpShop !== 'undefined' && !!cscfpShop.loggedIn;

		return $('.cscf-presets').data('logged-in') === 1 || localized;
	}

	function renderGuestPresets() {
		if (isLoggedIn()) {
			return;
		}

		var $group = $('.cscf-preset-mine');

		$group.empty();

		readGuestPresets().forEach(function (preset, index) {
			$group.append(
				$('<option />')
					.attr('value', presetUrl(preset.filters))
					.attr('data-index', index)
					.text(preset.name)
			);
		});
	}

	function countMyPresets() {
		return isLoggedIn() ? $('.cscf-preset-mine option').length : readGuestPresets().length;
	}

	function syncControls() {
		var hasFilters = currentFilterQuery() !== '';
		var atLimit = countMyPresets() >= MAX_PRESETS;

		// `.prop()` toggles the boolean `hidden` property, so the attribute the
		// markup renders is respected. `.attr( 'hidden', null )` would remove the
		// attribute entirely and leave the element visible.
		$('.cscf-preset-save').prop('hidden', !(hasFilters && !atLimit));
		$('.cscf-preset-limit').prop('hidden', !atLimit);

		var selected = $('.cscf-preset-select option:selected');
		var deletable = selected.length > 0 && selected.closest('.cscf-preset-mine').length > 0;

		$('.cscf-preset-remove').prop('hidden', !deletable);
	}

	/* Events. */

	$(document).on('change', '.cscf-preset-select', function () {
		var url = $(this).val();

		syncControls();

		if (url) {
			window.location.href = url;
		}
	});

	$(document).on('click', '.cscf-preset-save', function () {
		var filters = currentFilterQuery();

		if (!filters) {
			window.alert(cscfpShop.i18n.noFilters);
			return;
		}

		var name = window.prompt(cscfpShop.i18n.promptName, '');

		if (!name) {
			return;
		}

		if (!isLoggedIn()) {
			var presets = readGuestPresets();

			if (presets.length >= MAX_PRESETS) {
				presets.shift();
			}

			presets.push({ name: name, filters: filters });
			writeGuestPresets(presets);
			renderGuestPresets();
			syncControls();
			return;
		}

		$.post(cscfpShop.ajaxUrl, {
			action: 'cscf_save_user_preset',
			nonce: cscfpShop.nonce,
			name: name,
			filters: filters
		})
			.done(function (response) {
				if (!response || !response.success) {
					window.alert(
						(response && response.data && response.data.message) || cscfpShop.i18n.error
					);
					return;
				}

				$('.cscf-preset-mine').append(
					$('<option />')
						.attr('value', response.data.url)
						.attr('data-id', response.data.id)
						.text(response.data.name)
				);

				syncControls();
			})
			.fail(function () {
				window.alert(cscfpShop.i18n.error);
			});
	});

	$(document).on('click', '.cscf-preset-remove', function () {
		var $selected = $('.cscf-preset-select option:selected');

		if (!$selected.length || !$selected.closest('.cscf-preset-mine').length) {
			return;
		}

		if (!window.confirm(cscfpShop.i18n.confirmDelete)) {
			return;
		}

		if (!isLoggedIn()) {
			var index = parseInt($selected.attr('data-index'), 10);
			var presets = readGuestPresets();

			if (!isNaN(index)) {
				presets.splice(index, 1);
				writeGuestPresets(presets);
				renderGuestPresets();
				syncControls();
			}

			return;
		}

		$.post(cscfpShop.ajaxUrl, {
			action: 'cscf_delete_user_preset',
			nonce: cscfpShop.nonce,
			id: $selected.attr('data-id')
		}).done(function (response) {
			if (response && response.success) {
				$selected.remove();
				syncControls();
			}
		});
	});

	// Date range: hide the custom inputs unless "Custom range" is chosen.
	$(document).on('change', '.cscf-date-preset', function () {
		var value = $(this).val();

		$(this)
			.closest('.cscf-date-range-block')
			.find('.cscf-date-custom')
			.prop('hidden', !(value === '' || value === 'custom'));
	});

	$(function () {
		renderGuestPresets();
		syncControls();
	});
})(jQuery);
