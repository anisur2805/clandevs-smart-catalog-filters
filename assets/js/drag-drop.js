/**
 * Clandevs Smart Catalog Filters — Drag & Drop Filter Ordering JS
 */
(function ($) {
	'use strict';

	function currentOrder($list) {
		return $list
			.find('.cscfp-sortable-item')
			.map(function () {
				return $(this).data('key');
			})
			.get();
	}

	function save($list) {
		var $status = $('.cscfp-order-status');

		$.post(cscfOrder.ajaxUrl, {
			action: 'cscf_save_order',
			order: currentOrder($list),
			nonce: cscfOrder.nonce
		})
			.done(function (response) {
				var ok = !!(response && response.success);

				$status.text(ok ? cscfOrder.i18n.saved : cscfOrder.i18n.error).toggleClass('is-error', !ok);
			})
			.fail(function () {
				$status.text(cscfOrder.i18n.error).addClass('is-error');
			});
	}

	$(function () {
		var $list = $('#cscf-filter-sortable');

		if (!$list.length || typeof $list.sortable !== 'function') {
			return;
		}

		$list.sortable({
			handle: '.cscfp-drag-handle',
			axis: 'y',
			placeholder: 'cscfp-sortable-placeholder',
			forcePlaceholderSize: true,
			update: function () {
				save($list);
			}
		});
	});
})(jQuery);
