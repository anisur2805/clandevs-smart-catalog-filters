/**
 * Clandevs Smart Catalog Filters — persist the review notice dismissal.
 *
 * WordPress's `.is-dismissible` only hides the notice for the current page
 * view, so the click is intercepted and recorded against the user instead.
 */
(function ($) {
	'use strict';

	$(document).on('click', '.cscf-review-notice .notice-dismiss', function () {
		var url = $(this).closest('.cscf-review-notice').data('dismiss-url');

		if (!url) {
			return;
		}

		$.get(url);
	});
})(jQuery);
