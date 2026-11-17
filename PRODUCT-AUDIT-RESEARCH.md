# Product Audit — Evidence File (Clandevs — WooCommerce Product Filter)

Collected: 2026-10-24 by background research agent. All claims sourced inline.
Method: primary sources fetched live from wordpress.org, api.wordpress.org, and plugins.svn.wordpress.org on 2026-10-24. WebFetch summaries of live pages; nothing taken from memory. Three WebSearch attempts for secondary listicles all returned empty result sets (recorded in Section 6).

---

## 1. WordPress.org listing snapshot

Listing: https://wordpress.org/plugins/clandevs-smart-catalog-filters/

| Field | Value observed 2026-10-24 | Source URL |
|---|---|---|
| Display title on listing | "Clandevs Smart Catalog Filters" (not the styled "Clandevs — WooCommerce Product Filter") | https://wordpress.org/plugins/clandevs-smart-catalog-filters/ |
| Slug | clandevs-smart-catalog-filters | https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=clandevs-smart-catalog-filters |
| Active installs (band on page) | "Fewer than 10" active installations | https://wordpress.org/plugins/clandevs-smart-catalog-filters/ |
| active_installs (API, exact) | `0` | https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=clandevs-smart-catalog-filters |
| Downloads total | Not exposed by the 1.2 info API payload and not shown on the listing or /advanced/ page; the /advanced/ statistics charts (Active versions, Downloads Per Day, Downloads history) rendered empty | https://wordpress.org/plugins/clandevs-smart-catalog-filters/advanced/ |
| Last updated | "2 months ago" on page; "Last modified: August 23, 2026"; API `last_updated` = "2026-08-23 7:54am GMT" | https://wordpress.org/plugins/clandevs-smart-catalog-filters/ and info API above |
| Version | 2.0.4 | https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=clandevs-smart-catalog-filters |
| Requires WP | 6.0 | info API (same as above) |
| Tested up to | 7.1.3 on listing page; readme trunk says 7.1 | https://wordpress.org/plugins/clandevs-smart-catalog-filters/ and https://plugins.svn.wordpress.org/clandevs-smart-catalog-filters/trunk/readme.txt |
| Requires PHP | 7.4 | info API (same as above) |
| Requires plugins | `["woocommerce"]` | info API (same as above) |
| Short description | "Clandevs Smart Catalog Filters helps shoppers quickly find products using powerful AJAX-powered filters." (opening line of description) | https://plugins.svn.wordpress.org/clandevs-smart-catalog-filters/trunk/readme.txt |
| Monetization badge | None. No Freemius badge, no "Commercial plugin" section. Listing states the plugin "is open source software" and "This plugin is licensed under the GPLv2 or later." Description says "All features are fully available — no premium upgrades or license keys required." A privacy note says "Freemius SDK is included for optional opt-in telemetry and update delivery" with no data sent "without explicit user consent." API `business_model` = `false`. NOTE: changelog 2.0.3 / 2.0.4 still mention "Pro" add-on hooks ("Stopped advertising Pro Import/Export screen to unlicensed users", "Added filter-form extension hooks for the Pro add-on") — an internal inconsistency with the "no premium gating" messaging. | https://wordpress.org/plugins/clandevs-smart-catalog-filters/ and https://plugins.svn.wordpress.org/clandevs-smart-catalog-filters/trunk/readme.txt |
| Tags (readme Tags line) | "woocommerce, product filter, ajax filter, shop filters, ecommerce" | https://plugins.svn.wordpress.org/clandevs-smart-catalog-filters/trunk/readme.txt |
| Tags (API) | ajax-filter, ecommerce, product-filter, shop-filters, woocommerce | info API (same as above) |
| Screenshots | 3 shown ("Shop archive with sidebar filters", "Admin styling controls", "Analytics dashboard") — NOT 5 as assumed in the audit brief | https://wordpress.org/plugins/clandevs-smart-catalog-filters/ and https://plugins.svn.wordpress.org/clandevs-smart-catalog-filters/trunk/readme.txt |
| Average rating + count | No rating. Listing: "There are no reviews for this plugin." / "No reviews have been submitted yet." API: `num_ratings` = `0`, all per-star counters = 0 | https://wordpress.org/plugins/clandevs-smart-catalog-filters/ and info API (same as above) |
| Support threads | 0 threads, 0 resolved (API); forum shows "No topics found. Select another view or start a new post." | https://wordpress.org/support/plugin/clandevs-smart-catalog-filters/ and info API |
| Contributors | anisur8294 (display name "Anisur Rahman") — sole contributor | info API (same as above) |
| Homepage | https://github.com/anisur2805/clandevs-smart-catalog-filters/ | info API (same as above) |
| Added to directory | 2026-05-23 (API `added` field) | info API (same as above) |
| Download link | https://downloads.wordpress.org/plugin/clandevs-smart-catalog-filters.2.0.4.zip | info API (same as above) |
| donate_link | empty string | info API (same as above) |
| stable_tag | Not present in the 1.2 info API payload; trunk readme has "Stable tag: 2.0.4" | https://plugins.svn.wordpress.org/clandevs-smart-catalog-filters/trunk/readme.txt |
| SVN tags | 2.0.0, 2.0.3, 2.0.4 (three tags only) | https://plugins.svn.wordpress.org/clandevs-smart-catalog-filters/tags/ |

Not visible anywhere fetched: total downloads, downloads per day, banner/asset-level promo treatment feedback, search impression data, uninstall rate.

Listing-page name mismatch: the audit brief calls it "Clandevs — WooCommerce Product Filter (recently renamed; slug never changed)". The live listing title, the info-API `name`, and the SVN trunk read me all currently read **"Clandevs Smart Catalog Filters"** — no "WooCommerce Product Filter" wording appears in the title, and the name contains no em-dash styling. Source: https://wordpress.org/plugins/clandevs-smart-catalog-filters/ and https://plugins.svn.wordpress.org/clandevs-smart-catalog-filters/trunk/readme.txt

---

## 2. Trunk README on .org

Source: https://plugins.svn.wordpress.org/clandevs-smart-catalog-filters/trunk/readme.txt

- Title line (verbatim): `=== Clandevs Smart Catalog Filters ===`
- Stable tag: `Stable tag: 2.0.4`
- Requires at least: 6.0 · Tested up to: 7.1 · Requires PHP: 7.4 (note: page shows tested 7.1.3; minor discrepancy between readme "7.1" and the page's "7.1.3")
- Tags line (verbatim): `Tags: woocommerce, product filter, ajax filter, shop filters, ecommerce`
- Screenshots: **3** entries (Shop archive with sidebar filters · Admin styling controls · Analytics dashboard). The audit brief assumed 5 declared; only 3 exist in the trunk readme.
- FAQ: **5** questions, verbatim:
  1. "Does this plugin require WooCommerce?"
  2. "How do I add filters to a page?"
  3. "Can I customize the filter appearance?"
  4. "Does this plugin collect any data?"
  5. "Will this work with my theme?"
- First lines of == Description == (verbatim opening):
  - "Clandevs Smart Catalog Filters helps shoppers quickly find products using powerful AJAX-powered filters."
  - "All features are fully available — no premium upgrades or license keys required."
  - Features then listed: Category filter · Brand filter (multi-select, auto-detected taxonomies) · Color filter (visual swatches from Woo attributes or custom taxonomy) · Customer rating filter (star ratings) · Custom attribute filters (auto-detect all Woo custom attributes) · Price range slider (dual-handle, synced min/max inputs) · Availability filters ("In stock only", "On sale only" checkboxes).
- Changelog 2.0.4 (current): "Stopped advertising Pro Import/Export screen to unlicensed users." · "Added hook after the filter settings form." · "Added filter-form extension hooks for the Pro add-on." The 2.0.3 entry repeats the same three lines verbatim.
- Mismatch vs display name "Clandevs — WooCommerce Product Filter": **confirmed mismatch.** The trunk readme title, the live listing title, and the API `name` all say "Clandevs Smart Catalog Filters"; none contain "WooCommerce Product Filter". If the plugin was recently renamed in display marketing only (site/branding), the .org metadata has not been updated to match.

---

## 3. Directory search visibility

Method: fetched each server-rendered search page on 2026-10-24. Page 1 shows 20 results per page. Position = rank on page 1.

### 3.1 Query: `woocommerce product filter`
Source: https://wordpress.org/plugins/search/woocommerce+product+filter/ — 5,687 results claimed.
Page 1 order (top 10):
1. WooCommerce
2. WooCommerce PayPal Payments
3. Filter Everything — WordPress & WooCommerce Filters
4. WooCommerce Stripe Payment Gateway
5. HUSKY – Products Filter for WooCommerce Professional
6. Product Feed PRO for WooCommerce by AdTribes – Product Feeds for WooCommerce
7. Product Filter for WooCommerce by WBW
8. Product Feed Manager for WooCommerce – CTX Feed – Support 220+ Shopping, AI & Social Channels
9. WooPayments: Integrated WooCommerce Payments
10. Themify – WooCommerce Product Filter

(11–20 for context: Category AJAX Filter – Posts, Custom Post Types & Product Filter; Google for WooCommerce; Advanced Product Fields (Product Addons) for WooCommerce; MailPoet – Newsletters, Email Marketing, and Automation; Customer Reviews for WooCommerce; Product Bundle Builder for WooCommerce; YITH WooCommerce Ajax Product Filter; Filter Orders by Product for WooCommerce; WCAPF – Ajax Product Filter for WooCommerce; Premmerce Product Filter for WooCommerce)

**Position of clandevs-smart-catalog-filters: ABSENT** (not in the 20 shown on page 1).

### 3.2 Query: `product filter`
Source: https://wordpress.org/plugins/search/product+filter/ — 7,194 results claimed.
Page 1 order (top 10):
1. Filter Everything — WordPress & WooCommerce Filters
2. Product Filter for WooCommerce by WBW
3. HUSKY – Products Filter for WooCommerce Professional
4. Product Feed PRO for WooCommerce by AdTribes – Product Feeds for WooCommerce
5. Category AJAX Filter – Posts, Custom Post Types & Product Filter
6. Themify – WooCommerce Product Filter
7. YMC Filter
8. Advanced AJAX Product Filters
9. YITH WooCommerce Ajax Product Filter
10. WCAPF – Ajax Product Filter for WooCommerce

**Position of clandevs-smart-catalog-filters: ABSENT** (not in the 20 shown on page 1).

### 3.3 Query: `woocommerce filter`
Source: https://wordpress.org/plugins/search/woocommerce+filter/ — 7,455 results claimed.
Page 1 order (top 10):
1. WooCommerce
2. Filter Everything — WordPress & WooCommerce Filters
3. WooCommerce PayPal Payments
4. WooCommerce Stripe Payment Gateway
5. HUSKY – Products Filter for WooCommerce Professional
6. WooPayments: Integrated WooCommerce Payments
7. Product Filter for WooCommerce by WBW
8. Google for WooCommerce
9. Category AJAX Filter – Posts, Custom Post Types & Product Filter
10. Customer Reviews for WooCommerce

**Position of clandevs-smart-catalog-filters: ABSENT** (not in the 20 shown on page 1).

### 3.4 Query: `ajax product filter`
Source: https://wordpress.org/plugins/search/ajax+product+filter/ — 2,304 results claimed.
Page 1 order (top 10):
1. Filter Everything — WordPress & WooCommerce Filters
2. Advanced AJAX Product Filters
3. Category AJAX Filter – Posts, Custom Post Types & Product Filter
4. YITH WooCommerce Ajax Product Filter
5. Product Filter for WooCommerce by WBW
6. Product Feed PRO for WooCommerce by AdTribes – Product Feeds for WooCommerce
7. HUSKY – Products Filter for WooCommerce Professional
8. WCAPF – Ajax Product Filter for WooCommerce
9. Dynamic AJAX Product Filters for WooCommerce
10. YMC Filter

**Position of clandevs-smart-catalog-filters: ABSENT** (not in the 18 visible on page 1).

### 3.5 Query: `shop filter`
Source: https://wordpress.org/plugins/search/shop+filter/ — 2,568 results claimed.
Page 1 order (top 10):
1. Filter Everything — WordPress & WooCommerce Filters (50,000+)
2. Product Filter for WooCommerce by WBW (50,000+)
3. HUSKY – Products Filter for WooCommerce Professional (80,000+; "formerly named WOOF" per the search page)
4. WooCommerce (7+ million)
5. Category AJAX Filter – Posts, Custom Post Types & Product Filter (6,000+)
6. Search & Filter (40,000+)
7. Etsy Shop (3,000+)
8. One Stop Shop for WooCommerce (10,000+)
9. YITH WooCommerce Ajax Product Filter (70,000+)
10. Themify – WooCommerce Product Filter (20,000+)

**Position of clandevs-smart-catalog-filters: ABSENT** (not in the 20 shown on page 1).

### 3.6 Query: `layered nav`
Source: https://wordpress.org/plugins/search/layered+nav/ — 32 results claimed.
Page 1 order (top 10):
1. Block Widgets Monster
2. Ultimate Addons for Elementor – Widgets, Templates, WooCommerce & Header Footer Builder
3. HappyAddons for Elementor – 160 Elementor Widgets, GSAP Animations & Templates
4. Variation Swatches for WooCommerce Stores
5. EffortLess Layered Menu Visibility
6. UiCore Blocks – Free WordPress Gutenberg Blocks
7. WEBO MCP
8. Turbo Addons Elementor
9. Zero Blocks Given
10. Wow Elements Addons for Elementor

(YITH WooCommerce Ajax Product Filter appears at position 18 of this small result set.)

**Position of clandevs-smart-catalog-filters: ABSENT** (not in the 20 shown on page 1; cannot confirm page 2 without another fetch).

### 3.7 Query: `woocommerce attribute filter`
Source: https://wordpress.org/plugins/search/woocommerce+attribute+filter/ — 2,406 results claimed.
Page 1 order (top 10):
1. WooCommerce PayPal Payments
2. Filter Everything — WordPress & WooCommerce Filters
3. HUSKY – Products Filter for WooCommerce Professional
4. WooPayments: Integrated WooCommerce Payments
5. Product Filter for WooCommerce by WBW
6. Google for WooCommerce
7. Category AJAX Filter – Posts, Custom Post Types & Product Filter
8. Product Feed PRO for WooCommerce by AdTribes – Product Feeds for WooCommerce
9. Customer Reviews for WooCommerce
10. Product Feed Manager for WooCommerce – CTX Feed – Support 220+ Shopping, AI & Social Channels

**Position of clandevs-smart-catalog-filters: ABSENT** (not in the 20 shown on page 1).

**Summary:** In all 7 commercially-relevant queries, the plugin is not on page 1. It does not rank for its own primary keyword cluster ("product filter", "woocommerce product filter", "woocommerce filter", "ajax product filter", "shop filter", "woocommerce attribute filter", "layered nav") despite its readme Tags including "product filter", "ajax filter", "shop filters", "woocommerce". The strong-page-one incumbents across queries are: Filter Everything, Product Filter by WBW, HUSKY (WOOF), YITH, Category AJAX Filter, Advanced AJAX Product Filters (BeRocket), Themify, WCAPF, Dynamic AJAX Product Filters.

---

## 4. Reviews & support forum

- Reviews: **None.** Listing page: "There are no reviews for this plugin." and "No reviews have been submitted yet." (source: https://wordpress.org/plugins/clandevs-smart-catalog-filters/). Info API: `num_ratings` = 0; 5★ 0 · 4★ 0 · 3★ 0 · 2★ 0 · 1★ 0 (source: https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=clandevs-smart-catalog-filters). No review text exists to quote verbatim.
- Support threads: **0 total, 0 resolved.** API fields `support_threads` = 0, `support_threads_resolved` = 0 (source: info API above). Forum page shows the empty state: "No topics found. Select another view or start a new post." (source: https://wordpress.org/support/plugin/clandevs-smart-catalog-filters/).
- Author reply cadence: not measurable — there are no threads and no replies.
- Consequence for the audit: no social proof exists on the listing (0 reviews, 0 support threads resolved in public). Every competitor in Section 5 visible on page 1 has a ratings count between 98 and 387.

## 5. Competitor comparison

Slugs verified live via the info API on 2026-10-24. "Model" = free vs freemium as evidenced on the .org pages.

### 5.1 Comparison table

| Name (exact, on .org) | Slug | Exact active_installs (API) | Band on page | Ratings (avg / count) | Last updated | Tested up to | Model | What replaces promos on its page |
|---|---|---|---|---|---|---|---|---|
| Filter Everything — WordPress & WooCommerce Filters | filter-everything | 50,000 | 50,000+ | 4.6 / 153 (133·5·3·0·12) | 2026-09-22 (v1.9.7) | 7.1.3 | Freemium in effect: description names "Filter Everything PRO" with demo + UTM links; no Commercial badge | Pro referenced in description body + "4 blocks" (Filter, Sorting, Button, Chips), video walkthrough |
| HUSKY – Products Filter for WooCommerce Professional | woocommerce-products-filter | 80,000 | 80,000+ | 4.6 / 337 (287·16·7·6·21) | 2026-10-24 (v1.4.6; "4 hours ago") | 7.1.3 | Freemium: explicit premium-version links to products-filter.com in description + FAQ | Donate link to products-filter.com; FAQ answer points to premium; shortcode/widget heavy readme |
| Product Filter for WooCommerce by WBW | woo-product-filter | 50,000 | 50,000+ | 4.8 / 325 (300·6·6·3·10) | 2026-10-20 (v3.4.7) | 7.1.3 | Freemium: changelog references "PRO modules", "PRO styles"; FAQ offers free PRO for translators | Donate link routes to woobewoo.com product page; 1 Gutenberg block ("WBW Product Filter") |
| YITH WooCommerce Ajax Product Filter | yith-woocommerce-ajax-navigation | 70,000 | 70,000+ | 3.5 / 98 (57·4·2·4·31) | 2026-10-01 (v5.24.0) | 7.1.3 | Commercial (API `business_model`: "commercial"); listing has a "## Commercial plugin" section: "This plugin is free but offers additional paid commercial upgrades or support." | Animated GIF promo banners; "GET THE PREMIUM VERSION HERE with a 100% Money Back guarantee >"; "NEED MORE? CHECK THE PREMIUM VERSION!" teaser |
| Advanced AJAX Product Filters (BeRocket) | woocommerce-ajax-filters | 50,000 | 50,000+ | 4.7 / 387 (340·18·3·6·20) | 2026-09-07 (v3.3) | 7.1.3 | Freemium: "Pro and Business versions" sections in description, UTM-tagged links to berocket.com; no Commercial badge | Promo GIF banner; separate Pro/Business feature sections with external links; FAQ links admin demo/trial |

Sources per row: listing page + info API for each slug:
- https://wordpress.org/plugins/filter-everything/ · https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=filter-everything
- https://wordpress.org/plugins/woocommerce-products-filter/ · https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=woocommerce-products-filter
- https://wordpress.org/plugins/woo-product-filter/ · https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=woo-product-filter
- https://wordpress.org/plugins/yith-woocommerce-ajax-navigation/ · https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=yith-woocommerce-ajax-navigation
- https://wordpress.org/plugins/woocommerce-ajax-filters/ · https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=woocommerce-ajax-filters

### 5.2 Per-competitor "what they do better" (strictly from .org description/readme)

**Filter Everything (filter-everything)** — https://wordpress.org/plugins/filter-everything/
- Filters any content type (products, posts, listings, events, CPTs), not just WooCommerce; "25+ built-in filtering options".
- Layout variety: checkboxes, radio, dropdowns, labels, color swatches, rating stars, numeric ranges, date ranges; horizontal or vertical.
- Filtering UX modes: step-by-step, auto-submission, select-and-apply button.
- Page-builder breadth: Elementor, Divi, Bricks, Oxygen, Breakdance, Beaver Builder, Gutenberg; ACF custom fields; WPML/Polylang.
- Ships 4 Gutenberg blocks (Filter, Sorting, Filter Button, Chips) + a WPTuts video walkthrough embedded in the description.
- PRO pitch exists (unlimited Filter Sets, SEO tools, import/export) but the free tier already covers the audited plugin's entire described feature set and more.

**HUSKY – Products Filter for WooCommerce Professional (woocommerce-products-filter)** — https://wordpress.org/plugins/woocommerce-products-filter/
- Larger filter surface: categories, attributes, tags, taxonomies, meta fields, price — incl. SKU search, text search, ACF field filtering.
- SEO-friendly filter URLs (e.g. /swoof/color-red/product_cat-sport/) that "can be included in your site map file" — a differentiation the audited plugin does not claim.
- "Filter form Front Builder" via [woof_front_builder] to assemble forms on the front end.
- Dynamic per-option result recount; Turbo Mode; statistics; infinite scrolling; extensions API ("ext" folder examples).
- Distribution advantages: 80,000 installs, 337 ratings, updated the same day as this audit (2026-10-24), 4.6★.

**Product Filter for WooCommerce by WBW (woo-product-filter)** — https://wordpress.org/plugins/woo-product-filter/
- Highest rating quality of the set: 4.8★ across 325 reviews.
- Elementor integration ("all features accessible in an Elementor tab") plus 1 Gutenberg block.
- Multiple display formats incl. icons for color/size/rating; statistics; live preview; automatic price-range steps; show counts/recount.
- Fresh maintenance cadence: last updated 2026-10-20; declares "WC tested up to: 11.2" (audited plugin's readme does not state a WC-tested value).

**YITH WooCommerce Ajax Product Filter (yith-woocommerce-ajax-navigation)** — https://wordpress.org/plugins/yith-woocommerce-ajax-navigation/
- Unlimited filter presets placeable per page; widget + shortcode + Gutenberg blocks (incl. custom pages) + Elementor widget.
- Adaptive/adoptive filtering: "terms not available will be hidden"; AND/OR multiple selection; Reset button.
- Performance engineering: "lazy load filters and paginate terms in AJAX".
- Free-tier SEO options for indexing filter URLs.
- Weak spot for context: lowest rating (3.5★, 31 one-star of 98) — but 70,000 installs and a full premium funnel (Commercial plugin section, money-back offer, animated promo GIF banners) keep it visible.

**Advanced AJAX Product Filters (woocommerce-ajax-filters)** — https://wordpress.org/plugins/woocommerce-ajax-filters/
- One-click setup wizard: "analyzes your catalog, recommends useful filters, and creates responsive desktop and mobile filter groups automatically" — onboarding the audited plugin lacks.
- Drag-and-drop filter builder; checkbox/dropdown/radio/slider/color/image swatches; categories, attributes, tags, price, ratings, custom meta.
- AJAX filter+sort+search without reload; SEO-friendly URLs; browser back-button support.
- Widget + shortcode placement; custom CSS; compatibility claims for Elementor, Divi, Beaver Builder, ACF Pro, WPML, Polylang, Relevanssi, Barn2 Product Table.
- Largest review base of the set: 387 ratings, 4.7★.

### 5.3 Note on paid-plugin promo treatment on .org
All five competitors monetize via description-body references and/or a "Commercial plugin" section (YITH), never via Pro links inside the readme's opening description — consistent with .org guidelines. The audited plugin is the only one of the six with no monetization pathway at all on its page (its changelog still mentions Pro add-on hooks, an inconsistency). Sources: each competitor URL above; audited plugin readme: https://plugins.svn.wordpress.org/clandevs-smart-catalog-filters/trunk/readme.txt

## 6. Secondary sources

Three WebSearch attempts were made on 2026-10-24 (queries: "best WooCommerce product filter plugin 2025"; "\"best WooCommerce product filter\" plugins compared"; "best product filter plugins for WooCommerce stores"). **All three returned empty result sets — no URLs, no titles.** No listicles could be collected; none are cited, and none were substituted from memory. This section therefore has zero items as of collection time. Low weight was always assigned to this section; primary-source findings above stand on their own.

## 7. Could not verify / needs owner data

| Unknown | Why it matters | How the owner can obtain it |
|---|---|---|
| Total downloads / downloads-per-day trend | Distinguishes "no installs" from "installs but no active use"; the /advanced/ charts rendered empty and the info API omits the field | Advanced stats page renders charts for logged-in views in a browser JS session; or export from wordpress.org admin (Plugin Dashboard → Stats), or track daily via the plugin's own update-check server logs |
| Uninstall / deactivate reasons | Core listing-quality signal; publicly unknowable | Freemius opt-in telemetry (already bundled per the listing's privacy note) can report deactivation reasons with consent; or add a voluntary two-question uninstall survey hook |
| Search impression data for the 7 queries in §3 | .org does not expose impressions; we can only observe page-1 absences | wordpress.org plugin admin "Advanced" → search-term tools are not public; owner can query the directory API for adjacent terms over time, or use web-server analytics on the plugin's homepage traffic |
| Conversion funnel (view → download → activate) | Determines whether the problem is discovery (§3) or activation | Funnels are not public; estimate via downloads deltas vs active-install deltas over time, and instrument the plugin's own first-run analytics (with consent) |
| Why active_installs reads 0 while "Fewer than 10" shows | "Fewer than 10" is a band, 0 is the exact value; sub-10 plugins are floored to 0 in the public API | Nothing to obtain — record that any band under 10 is indistinguishable publicly; watch for the band edge crossing by monitoring weekly |
| Whether the ".org listing was renamed to 'Clandevs — WooCommerce Product Filter'" | The audit brief names it that way, but the live title, trunk readme, and API all say "Clandevs Smart Catalog Filters" — either the rename was never pushed to .org or it was reverted | Owner: check the .org plugin admin → "Plugin name" field and the pending-rename queue; diff local trunk readme.txt against what was last committed to plugins.svn |
| Star-rating velocity once reviews arrive | Currently zero; no velocity computable | Owner monitors weekly after asking a small group of actual users for honest reviews; never incentivized (charset per .org rules) |
| Adoption of the "no premium gating" free feature set (which features actually get used) | Informs whether removing monetization was the right call and what to build next | Add a consented, lightweight usage event on filter-render and first settings-save; or survey users via the plugin's GitHub repo (listed as homepage on .org) |
| WC block-editor / Elementor coverage in the wild | .org pages don't list every integration tested | Maintain a public compatibility list and test-matrix page; ask in support forum on theme-specific threads |
| Cause of any future oscillation in the install band once growth starts | Band edges ("Fewer than 10" → "10+") mask real churn; publicly unknowable at this scale | Take weekly snapshots of the /advanced/ page and of `active_installs` via the info API over 8–12 weeks; correlate with releases and any marketing pushes |

---

## Source-failure log (transparency)

- WebSearch x3 (secondary listicles): returned empty results — recorded, not substituted (Section 6).
- All WebFetch primary fetches (listing, advanced, info API, trunk readme, tags dir, support forum, 7 search pages, competitor listings + APIs) returned successfully on 2026-10-24; no curl fallback was needed.
