=== Woo Filters ===
Contributors: anisur2805
Tags: woocommerce, product filter, ajax filter, shop filters, ecommerce
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: trunk
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AJAX-based WooCommerce product filters with category, price, rating, availability, brand, color, and attribute filtering.

== Description ==

Woo Filters helps customers narrow products quickly on WooCommerce archive pages without full page reloads.

Core capabilities:

* AJAX filtering with nonce-validated requests.
* Category, brand, color, and custom attribute filtering.
* Price range slider with synced min/max inputs.
* Rating and stock/sale availability filters.
* Active filter chips with clear-all actions.
* Mobile-friendly filter drawer layout.
* Admin filter visibility controls.
* Admin styling controls with multiple preset skins.
* Filter usage analytics dashboard.

== Installation ==

1. Upload the `woo-filters` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Ensure WooCommerce is installed and activated.
4. Configure filter visibility under `WooCommerce > Woo Filters Settings`.
5. Configure styles under `WooCommerce > Woo Filters Styling`.

== Frequently Asked Questions ==

= Does this work without WooCommerce? =

No. Woo Filters requires WooCommerce and only runs when WooCommerce is active.

= Can I use the filters on a custom page? =

Yes. Use the shortcode `[woo_filters]` with optional attributes such as `per_page` and `columns`.

= Is the filtering AJAX-based? =

Yes. Supported interactions update the filter and product sections without full page refresh.

== Changelog ==

= 0.11.0 =
* Added analytics dashboard for filter usage.
* Added stronger nonce validation for filter-bearing requests.
* Added shortcode and dynamic attribute filtering improvements.
* Added automated test bootstrap and query sync tests.

== Upgrade Notice ==

= 0.11.0 =
This release introduces analytics and security hardening for filter requests.
