/**
 * Clandevs Smart Catalog Filters Pro — Comparison JS
 */
(function($) {
	'use strict';

	var compareIds = JSON.parse(localStorage.getItem('cscf_compare') || '[]');

	function updateBar() {
		if (compareIds.length > 0) {
			$('.cscf-compare-bar').show();
			$('.cscf-compare-count').text(compareIds.length + ' ' + (compareIds.length === 1 ? 'product' : 'products') + ' selected');
		} else {
			$('.cscf-compare-bar').hide();
		}
	}

	$(document).on('click', '.cscf-compare-toggle', function(e) {
		e.preventDefault();
		e.stopPropagation();
		var id = $(this).data('product-id');
		var idx = compareIds.indexOf(id);
		if (idx > -1) {
			compareIds.splice(idx, 1);
		} else {
			if (compareIds.length >= cscfpCompare.max) {
				alert(cscfpCompare.i18n.max);
				return;
			}
			compareIds.push(id);
		}
		localStorage.setItem('cscf_compare', JSON.stringify(compareIds));
		updateBar();
	});

	$(document).on('click', '.cscf-compare-go', function() {
		if (compareIds.length < 2) return;
		$.post(cscfpCompare.ajaxUrl, {
			action: 'cscf_compare',
			_nonce: cscfpCompare.nonce,
			ids: compareIds
		}, function(res) {
			if (res.success) {
				// Simple comparison modal.
				var html = '<div class="cscf-compare-modal"><table><tr>';
				res.data.forEach(function(p) {
					html += '<td><img src="' + (p.image || '') + '" style="max-width:150px;" /><br><strong>' + p.name + '</strong><br>' + p.price + '</td>';
				});
				html += '</tr></table></div>';
				$('body').append(html);
			}
		});
	});

	updateBar();
})(jQuery);
