=== Woo Filter Studio ===
Contributors: anisur2805
Tags: woocommerce, product filter, ajax filter, shop filters, ecommerce
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Filter WooCommerce products by category, attributes, price, rating, stock, and more with AJAX and shortcode support (Elementor-friendly).

== Description ==

Woo Filter Studio helps shoppers quickly find products using category, brand, color, attribute, price, rating, and availability filters with AJAX-powered updates.

Core capabilities:

* AJAX filtering with shareable URL support.
* Category, brand, color, and custom attribute filtering.
* Price range slider with synced min/max inputs.
* Rating and stock/sale availability filters.
* Active filter chips with clear-all actions.
* Mobile-friendly filter drawer layout.
* Admin filter visibility controls.
* Admin styling controls with multiple preset skins.
* Filter usage analytics dashboard.

== Installation ==

1. Upload the `woo-filter-studio` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Ensure WooCommerce is installed and activated.
4. Configure filter visibility under `Woo Filter Studio > Settings`.
5. Configure styles under `Woo Filter Studio > Styling`.
6. Optional: Enable data deletion on uninstall under the Data Management section.

== Frequently Asked Questions ==

= Does this work without WooCommerce? =

No. Woo Filter Studio requires WooCommerce and only runs when WooCommerce is active.

= Can I use the filters on a custom page? =

Yes. Use the shortcode `[woo_filters]` in a standard WordPress page or post content area, with optional attributes such as `per_page`, `columns`, `category`, `show_filters`, and `show_pagination`. The plugin will load its WooCommerce frontend assets for shortcode pages automatically.

= Is the filtering AJAX-based? =

Yes. Supported interactions update the filter and product sections without full page refresh.

= Does uninstall remove data? =

By default, data is retained. Enable "Delete plugin data on uninstall" in Woo Filter Studio Settings to remove options and analytics on uninstall.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Added AJAX product filtering with category, brand, color, attribute, price, rating, and availability filters.
* Added shortcode support for embedding filters on custom pages.
* Added active filter chips, clear-all actions, and per-page controls.
* Added admin pages for filter visibility, styling controls, and analytics.
* Improved shortcode and archive filter-query consistency.
* Improved on-sale filtering behavior by using WooCommerce sale product IDs.
* Improved filter URL stability for share/bookmark usage.
* Improved multi-layout frontend behavior and filter option search scoping.
* Improved analytics coverage for dynamic attributes and capped value cardinality.

== Screenshots ==

1. Filter-rich shop archive layout with AJAX updates and active filter chips.
2. Styling controls for colors, spacing, typography, and preset skins.
3. Analytics dashboard for tracking shopper filter usage.

== Credits ==

Inline admin icons are based on Feather Icons (MIT License).

== Upgrade Notice ==

= 1.0.0 =
Initial public release of Woo Filter Studio.
