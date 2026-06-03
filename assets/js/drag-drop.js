/**
 * Clandevs Smart Catalog Filters — Drag & Drop Filter Ordering JS
 */
(function($) {
	'use strict';

	$(function() {
		$('#cscf-filter-sortable').sortable({
			placeholder: 'ui-state-highlight',
			update: function() {
				var order = [];
				$('#cscf-filter-sortable li').each(function() {
					order.push($(this).data('key'));
				});
				$.post(cscfOrder.ajaxUrl, {
					action: 'cscf_save_order',
					order: order,
					nonce: cscfOrder.nonce
				});
			}
		});
	});
})(jQuery);
