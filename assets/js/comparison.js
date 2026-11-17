/**
 * Clandevs Smart Catalog Filters Pro — Product comparison.
 */
(function ($) {
	'use strict';

	var STORAGE_KEY = 'cscf_compare';
	var ids = [];

	function readStored() {
		try {
			var raw = window.localStorage.getItem(STORAGE_KEY);
			var parsed = raw ? JSON.parse(raw) : [];

			if (!Array.isArray(parsed)) {
				return [];
			}

			return parsed
				.map(function (value) {
					return parseInt(value, 10);
				})
				.filter(function (value) {
					return !isNaN(value) && value > 0;
				})
				.slice(0, cscfpCompare.max);
		} catch (e) {
			return [];
		}
	}

	function persist() {
		try {
			window.localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
		} catch (e) {
			// Storage unavailable (private mode); selection stays for this page only.
		}
	}

	function syncToggles() {
		$('.cscf-compare-toggle').each(function () {
			var $button = $(this);
			var id = parseInt($button.data('product-id'), 10);
			var selected = ids.indexOf(id) > -1;

			$button
				.toggleClass('is-selected', selected)
				.attr('aria-pressed', selected ? 'true' : 'false')
				.text(selected ? cscfpCompare.i18n.selected : cscfpCompare.i18n.compare);
		});
	}

	function syncBar() {
		var $bar = $('.cscf-compare-bar');

		if (!ids.length) {
			$bar.attr('hidden', 'hidden');
			return;
		}

		$bar.removeAttr('hidden');
		$('.cscf-compare-count').text(cscfpCompare.i18n.count.replace('%d', ids.length));
	}

	function sync() {
		persist();
		syncToggles();
		syncBar();
	}

	function closeModal() {
		$('.cscf-compare-modal')
			.attr('hidden', 'hidden')
			.find('.cscf-compare-modal__body')
			.empty();
	}

	function loadComparison() {
		if (ids.length < 2) {
			window.alert(cscfpCompare.i18n.minimum);
			return;
		}

		var $modal = $('.cscf-compare-modal');
		var $body = $modal.find('.cscf-compare-modal__body');

		$body.html('<p class="cscf-compare-loading">&hellip;</p>');
		$modal.removeAttr('hidden');

		$.post(cscfpCompare.ajaxUrl, {
			action: cscfpCompare.action,
			nonce: cscfpCompare.nonce,
			ids: ids
		})
			.done(function (response) {
				if (response && response.success && response.data && response.data.html) {
					$body.html(response.data.html);
					$modal.find('.cscf-compare-close').trigger('focus');
					return;
				}

				var message =
					response && response.data && response.data.message
						? response.data.message
						: cscfpCompare.i18n.error;

				$body.text(message);
			})
			.fail(function () {
				$body.text(cscfpCompare.i18n.error);
			});
	}

	$(document).on('click', '.cscf-compare-toggle', function (e) {
		e.preventDefault();
		e.stopPropagation();

		var id = parseInt($(this).data('product-id'), 10);

		if (isNaN(id) || id <= 0) {
			return;
		}

		var index = ids.indexOf(id);

		if (index > -1) {
			ids.splice(index, 1);
		} else {
			if (ids.length >= cscfpCompare.max) {
				window.alert(cscfpCompare.i18n.max);
				return;
			}

			ids.push(id);
		}

		sync();
	});

	$(document).on('click', '.cscf-compare-go', loadComparison);

	$(document).on('click', '.cscf-compare-clear', function () {
		ids = [];
		sync();
		closeModal();
	});

	$(document).on('click', '.cscf-compare-remove', function () {
		var id = parseInt($(this).data('product-id'), 10);
		var index = ids.indexOf(id);

		if (index > -1) {
			ids.splice(index, 1);
			sync();
		}

		if (ids.length < 2) {
			closeModal();
			return;
		}

		loadComparison();
	});

	$(document).on('click', '.cscf-compare-close', closeModal);

	$(document).on('click', '.cscf-compare-modal', function (e) {
		if (e.target === this) {
			closeModal();
		}
	});

	$(document).on('keydown', function (e) {
		if (e.key === 'Escape') {
			closeModal();
		}
	});

	$(function () {
		ids = readStored();
		sync();
	});
})(jQuery);
