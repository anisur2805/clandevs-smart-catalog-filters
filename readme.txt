=== Clandevs Smart Catalog Filters ===
Contributors: anisur8294, anisur2805
Tags: woocommerce, product filter, ajax filter, shop filters, ecommerce
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Filter WooCommerce products by category, price, availability, brand, color, rating, and custom attributes with AJAX-powered filtering, analytics, and full styling controls.

== Description ==

Clandevs Smart Catalog Filters helps shoppers quickly find products using powerful AJAX-powered filters. All features are fully available — no premium upgrades or license keys required.

= Features =

* **Category filter** — let shoppers browse by product category.
* **Brand filter** — multi-select brand filtering with auto-detected taxonomies.
* **Color filter** — visual color swatches from WooCommerce attributes or custom taxonomy.
* **Customer rating filter** — filter by star ratings with visual stars.
* **Custom attribute filters** — automatically detect and display all WooCommerce custom attributes.
* **Price range slider** — dual-handle slider with synced min/max inputs.
* **Availability filters** — "In stock only" and "On sale only" checkboxes.
* **OR/AND multi-select logic** — match any or all selected options.
* **AJAX filtering** — instant updates without full page reload.
* **Shareable filter URLs** — filtered results can be bookmarked and shared.
* **Active filter chips** — visual indicators with individual remove and clear-all actions.
* **Mobile-friendly drawer** — responsive sidebar that collapses into a toggleable drawer.
* **Shortcode support** — use `[clandevs_catalog_filters]` to embed filters on any page.
* **Per-page switcher** — let shoppers control how many products are displayed.
* **No-results state** — styled empty state card when no products match.
* **3 skins** — Classic, Graphite, and Sunrise with 15 color controls.
* **Typography & layout** — font family, font size, sidebar width, gap, and border radius controls.
* **Analytics dashboard** — track how customers use filters with top-filter reports.
* **Admin filter visibility** — toggle each filter on or off from Settings.

= Shortcodes =

* `[clandevs_catalog_filters]` — display filters on any page.
* `[cscf_legacy_filter]` — legacy shortcode (still supported).

= Privacy =

This plugin does not connect to any external services. Freemius SDK is included for optional opt-in telemetry and update delivery. No data is sent without explicit user consent. Filter analytics are stored locally in your WordPress database.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/clandevs-smart-catalog-filters/` or install via the WordPress plugin installer.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Ensure WooCommerce is installed and active.
4. Configure filter visibility under **Catalog Filters > Settings**.
5. Customize appearance under **Catalog Filters > Styling**.
6. View filter analytics under **Catalog Filters > Analytics**.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. WooCommerce must be installed and active for this plugin to work.

= How do I add filters to a page? =

Use the `[clandevs_catalog_filters]` shortcode on any page or post. On shop archive pages, filters appear automatically in the sidebar.

= Can I customize the filter appearance? =

Yes. Go to **Catalog Filters > Styling** to change colors, skins, typography, layout dimensions, and border radii.

= Does this plugin collect any data? =

Filter analytics are stored locally in your WordPress database. Freemius SDK is included for optional opt-in telemetry only — no data is sent without your explicit consent.

= Will this work with my theme? =

The plugin uses its own CSS for filter UI and is designed to work with any WooCommerce-compatible theme. You can adjust the sidebar width and layout gap in the Styling settings.

== Screenshots ==

1. Shop archive with sidebar filters
2. Admin styling controls
3. Analytics dashboard

== Changelog ==

= 2.0.0 =
* Renamed plugin to "Clandevs Smart Catalog Filters"
* All features fully available — no premium gating
* Removed custom CSS feature for wp.org compliance
* Updated shortcode to `[clandevs_catalog_filters]`
* Legacy shortcode `[cscf_legacy_filter]` still supported

= 1.0.2 =
* Freemius SDK updated to 2.13.1

= 1.0.1 =
* Freemius deploy action fix

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.0.0 =
Major update: plugin renamed and all features unlocked. Legacy shortcode still works.

== License ==

This plugin is licensed under the GPLv2 or later. See https://www.gnu.org/licenses/gpl-2.0.html for details.
