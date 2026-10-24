=== Clandevs — WooCommerce Product Filter ===
Contributors: anisur8294
Tags: woocommerce, product filter, ajax filter, shop filters, ecommerce
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 2.0.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AJAX-powered WooCommerce product filter with category, attribute, price, rating, color, brand, and tag filters. Includes mobile drawer, Elementor widget, Gutenberg block, analytics, and shareable filter URLs.

== Description ==

Clandevs — WooCommerce Product Filter helps shoppers quickly find products using powerful AJAX-powered filters. All features are fully available — no premium upgrades or license keys required.

= Filter Types =

* Filter by category, attribute, price, rating, color, brand, and product tags.
* Auto-detect WooCommerce custom attributes and display them as filter lists.
* Price range slider with synced min/max inputs.
* Customer rating filter with visual star display.
* Availability filters for in-stock and on-sale products.
* Color swatches and brand filters with visual term support.

= User Experience =

* AJAX filtering — instant updates without full page reload.
* Shareable filter URLs — bookmark and share filtered results.
* Active filter chips with individual remove and clear-all actions.
* Mobile-friendly drawer — responsive sidebar that collapses into a toggleable drawer.
* Contextual product counts — show how many products match each filter option.
* Show more / Show less for long filter lists.
* Hierarchical category indentation.

= Display & Styling =

* 3 built-in skins — Classic, Graphite, and Sunrise.
* 15+ color controls for accents, sidebar, chips, buttons, inputs, and empty states.
* Typography controls — font family, font size, sidebar width, layout gap, and border radius.
* Per-page switcher — let shoppers control how many products are displayed.
* Styled no-results state with clear actions.

= Integrations =

* Shortcode support — use `[clandevs_catalog_filters]` to embed filters on any page.
* Elementor widget — drag-and-drop filter sidebar widget.
* WooCommerce Blocks — native Gutenberg block for the block editor.
* Works with any WooCommerce-compatible theme.

= Management =

* Admin filter visibility — toggle each filter on or off from Settings.
* Drag-and-drop ordering — reorder filter sections from admin.
* Analytics dashboard — track how customers use filters with top-filter reports.

= Shortcodes =

* `[clandevs_catalog_filters]` — display filters on any page.
* `[cscf_legacy_filter]` — legacy shortcode (still supported).

= Privacy =

This plugin connects to no external services. Filter analytics are stored locally in your WordPress database, and no data leaves your site.

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

Filter analytics are stored locally in your WordPress database only. The plugin does not send any data to external services, and it connects to no third-party APIs.

= Will this work with my theme? =

The plugin uses its own CSS for filter UI and is designed to work with any WooCommerce-compatible theme. You can adjust the sidebar width and layout gap in the Styling settings.

= Is AJAX filtering supported? =

Yes. Filtering updates the product list without reloading the page, and the current filter state is reflected in the URL.

= Can I hide individual filters? =

Yes. Use **Catalog Filters > Settings** to show or hide category, brand, price, rating, availability, color, and the apply button.

== Screenshots ==

1. Filter sidebar on desktop — category, price, rating, brand, color, and attribute filters with active chips.
2. Mobile drawer — compact filter drawer with clear-all and close actions on small screens.
3. Admin settings screen — filter visibility toggles for each filter type.

== Changelog ==

= 2.0.6 =
* Hardened filter analytics against malformed request data (nested arrays, non-finite prices, corrupt flush batches).
* Fixed PHP warnings when hostile array parameters were passed in filter URLs.
* Added continuous integration across PHP 7.4–8.4.
* Removed obsolete Freemius deploy workflow and renamed the Composer package slug.

= 2.0.5 =
* Added an uninstall.php that honors the "Delete plugin data on uninstall" setting.
* Deactivation no longer deletes plugin data — data is only removed on uninstall when that setting is enabled.
* Filter analytics now feed the usage counter that powers the review prompt.
* Removed outdated telemetry references from the readme.

= 2.0.4 =
* Stopped advertising Pro Import/Export screen to unlicensed users.
* Added hook after the filter settings form.
* Added filter-form extension hooks for the Pro add-on.

= 2.0.3 =
* Stopped advertising Pro Import/Export screen to unlicensed users.
* Added hook after the filter settings form.
* Added filter-form extension hooks for the Pro add-on.

= 2.0.2 =
* Restored Freemius SDK for opt-in telemetry and updates
* Fixed contributors list — removed invalid WordPress.org username
* Shortened short description to meet 150 character limit
* Version header now matches SVN tag

= 2.0.1 =
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

= 2.0.6 =
Hardened analytics input handling. No configuration changes needed.

= 2.0.5 =
Uninstall now honors the "Delete plugin data on uninstall" setting. Deactivation keeps your data.

= 2.0.4 =
Bug fixes and Pro hook additions.

= 2.0.3 =
Bug fixes and Pro hook additions.

= 2.0.2 =
Freemius SDK restored, contributors and description fixed.

= 2.0.1 =
Major update: plugin renamed and all features unlocked. Legacy shortcode still works.

== License ==

This plugin is licensed under the GPLv2 or later. See https://www.gnu.org/licenses/gpl-2.0.html for details.
