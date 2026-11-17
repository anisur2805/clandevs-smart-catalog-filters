<?php
/**
 * Variation-level filtering (size, color per variation).
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Matches attribute selections against individual variations rather than the
 * parent product, so a shopper asking for "red" and "large" only sees products
 * that have a single variation which is both red and large.
 */
class VariationFilter {

	/**
	 * Option holding the enabled flag.
	 */
	private const OPTION_ENABLED = 'cscf_variation_filter_enabled';

	/**
	 * Admin page slug.
	 */
	private const PAGE_SLUG = 'cscf-variation-filter';

	/**
	 * Object cache group.
	 */
	private const CACHE_GROUP = 'cscfp_variation_filter';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'cscf_query_args', array( $this, 'modify_query' ), 20, 2 );
		add_action( 'cscf_admin_menu_registered', array( $this, 'add_submenu' ) );
		add_action( 'admin_post_cscf_save_variation_settings', array( $this, 'handle_save' ) );
	}

	/**
	 * Register the settings submenu.
	 *
	 * @param string $menu_slug Parent menu slug.
	 * @return void
	 */
	public function add_submenu( string $menu_slug ): void {
		add_submenu_page(
			$menu_slug,
			__( 'Variation Filtering', 'clandevs-smart-catalog-filters' ),
			__( 'Variation Filtering', 'clandevs-smart-catalog-filters' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Variation Filtering', 'clandevs-smart-catalog-filters' ) . '</h1>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success flag rendered after a nonce-checked redirect.
		if ( isset( $_GET['cscf-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'clandevs-smart-catalog-filters' ) . '</p></div>';
		}

		echo '<p>' . esc_html__( 'Match attribute filters against individual variations instead of the parent product. A product is shown only when one of its variations matches every selected attribute.', 'clandevs-smart-catalog-filters' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'cscf_save_variation_settings' );
		echo '<input type="hidden" name="action" value="cscf_save_variation_settings" />';
		echo '<table class="form-table"><tbody><tr>';
		echo '<th scope="row">' . esc_html__( 'Variation matching', 'clandevs-smart-catalog-filters' ) . '</th>';
		echo '<td><label><input type="checkbox" name="enabled" value="yes" ' . checked( $this->is_enabled(), true, false ) . ' /> ';
		echo esc_html__( 'Enable variation-level filtering', 'clandevs-smart-catalog-filters' ) . '</label></td>';
		echo '</tr></tbody></table>';
		submit_button( __( 'Save Changes', 'clandevs-smart-catalog-filters' ) );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Persist the settings form.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		check_admin_referer( 'cscf_save_variation_settings' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'clandevs-smart-catalog-filters' ), '', array( 'response' => 403 ) );
		}

		update_option( self::OPTION_ENABLED, isset( $_POST['enabled'] ) ? 'yes' : 'no' );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::PAGE_SLUG,
					'cscf-updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Replace parent-level attribute clauses with variation-level matching.
	 *
	 * @param array $query_args     Query args with tax_query, meta_query, post__in.
	 * @param array $filter_options Current filter visibility options.
	 * @return array
	 */
	public function modify_query( array $query_args, array $filter_options ): array {
		unset( $filter_options );

		if ( ! $this->is_enabled() ) {
			return $query_args;
		}

		$selected = $this->get_selected_attribute_filters();

		if ( empty( $selected ) ) {
			return $query_args;
		}

		$matching_parent_ids = $this->get_matching_parent_ids( $selected );

		if ( null === $matching_parent_ids ) {
			return $query_args;
		}

		// Zero never matches a post id, so an empty result yields no products.
		if ( empty( $matching_parent_ids ) ) {
			$matching_parent_ids = array( 0 );
		}

		$existing_post_in = isset( $query_args['post__in'] ) ? array_filter( array_map( 'absint', (array) $query_args['post__in'] ) ) : array();

		$query_args['post__in'] = empty( $existing_post_in )
			? $matching_parent_ids
			: array_values( array_intersect( $existing_post_in, $matching_parent_ids ) );

		if ( empty( $query_args['post__in'] ) ) {
			$query_args['post__in'] = array( 0 );
		}

		$query_args['tax_query'] = $this->remove_attribute_tax_clauses(
			isset( $query_args['tax_query'] ) ? (array) $query_args['tax_query'] : array(),
			array_keys( $selected )
		);

		return $query_args;
	}

	/**
	 * Extract selected attribute filters from the current request.
	 *
	 * @return array<string, array<int, string>>
	 */
	private function get_selected_attribute_filters(): array {
		$selected = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, shareable catalog filter URL.
		foreach ( wp_unslash( $_GET ) as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( 0 !== strpos( $key, 'cscf_attr_' ) ) {
				continue;
			}

			$taxonomy = sanitize_key( substr( $key, strlen( 'cscf_attr_' ) ) );

			if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = array();

			foreach ( (array) $value as $term ) {
				if ( ! is_scalar( $term ) ) {
					continue;
				}

				$term = sanitize_title( (string) $term );

				if ( '' !== $term ) {
					$terms[] = $term;
				}
			}

			if ( ! empty( $terms ) ) {
				$selected[ $taxonomy ] = array_values( array_unique( $terms ) );
			}
		}

		return $selected;
	}

	/**
	 * Resolve the parent products matching every selected attribute.
	 *
	 * Variation-level attributes are matched against a single variation, so
	 * selecting Color: Red and Size: M returns only products that have one
	 * Red/M variation, not a product with a Red/S and a separate Blue/M.
	 * Product-level attributes are matched against the product itself.
	 * Only in-stock rows count, so an out-of-stock XL no longer surfaces the
	 * product when filtering by XL.
	 *
	 * @param array<string, array<int, string>> $selected Terms keyed by taxonomy.
	 * @return array<int, int>|null Null when the lookup table cannot be used.
	 */
	private function get_matching_parent_ids( array $selected ): ?array {
		if ( ! $this->lookup_table_available() ) {
			return $this->get_matching_parent_ids_fallback( $selected );
		}

		$term_ids_by_taxonomy = array();

		foreach ( $selected as $taxonomy => $slugs ) {
			$term_ids = $this->get_term_ids( $taxonomy, $slugs );

			// A selection that resolves to no term can never match.
			if ( empty( $term_ids ) ) {
				return array( 0 );
			}

			$term_ids_by_taxonomy[ $taxonomy ] = $term_ids;
		}

		if ( empty( $term_ids_by_taxonomy ) ) {
			return null;
		}

		$cache_key = 'lookup_' . md5( $this->get_cache_last_changed() . '|' . wp_json_encode( $term_ids_by_taxonomy ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// Base set: every selected attribute is satisfied somewhere on the
		// product, by an in-stock row. This alone covers simple products and
		// products that split attributes between the parent and its variations.
		$matches = $this->query_lookup_per_taxonomy( $term_ids_by_taxonomy );

		// Narrow it: when two or more of the selected attributes are variation
		// attributes, one single variation has to carry all of them. Without
		// this, a product with a Red/S and a separate Blue/M would wrongly
		// match Color: Red + Size: M.
		$variation_taxonomies = array_intersect_key(
			$term_ids_by_taxonomy,
			array_flip( $this->get_variation_taxonomies( array_keys( $term_ids_by_taxonomy ) ) )
		);

		if ( count( $variation_taxonomies ) > 1 ) {
			$matches = array_values( array_intersect( $matches, $this->query_lookup( $variation_taxonomies, 1 ) ) );
		}

		wp_cache_set( $cache_key, $matches, self::CACHE_GROUP, 300 );

		return $matches;
	}

	/**
	 * Narrow a list of taxonomies to those used as variation attributes.
	 *
	 * @param array<int, string> $taxonomies Candidate taxonomies.
	 * @return array<int, string>
	 */
	private function get_variation_taxonomies( array $taxonomies ): array {
		if ( empty( $taxonomies ) ) {
			return array();
		}

		global $wpdb;

		$table        = $wpdb->prefix . 'wc_product_attributes_lookup';
		$placeholders = implode( ', ', array_fill( 0, count( $taxonomies ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders are generated; every value is bound.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The only placeholders are inside the generated list, which the sniff cannot see.
		$found = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT taxonomy
				FROM {$table}
				WHERE taxonomy IN ( {$placeholders} )
					AND is_variation_attribute = 1",
				$taxonomies
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_values( array_map( 'strval', (array) $found ) );
	}

	/**
	 * Query the attribute lookup table for rows satisfying every taxonomy.
	 *
	 * @param array<string, array<int, int>> $term_ids_by_taxonomy Term ids keyed by taxonomy.
	 * @param int                            $is_variation         1 for variation rows, 0 for product rows.
	 * @return array<int, int>
	 */
	private function query_lookup( array $term_ids_by_taxonomy, int $is_variation ): array {
		global $wpdb;

		$table      = $wpdb->prefix . 'wc_product_attributes_lookup';
		$conditions = array();
		$params     = array();

		foreach ( $term_ids_by_taxonomy as $taxonomy => $term_ids ) {
			$placeholders = implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) );
			$conditions[] = "( taxonomy = %s AND term_id IN ( {$placeholders} ) )";
			$params[]     = $taxonomy;
			$params       = array_merge( $params, $term_ids );
		}

		$params[] = $is_variation;
		$params[] = count( $term_ids_by_taxonomy );

		$where = implode( ' OR ', $conditions );

		// Grouping by product_id ties variation matches to a single variation.
		$group_by = 1 === $is_variation ? 'product_id, product_or_parent_id' : 'product_or_parent_id';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders are generated; every value is bound.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Args are passed as a single array, which the sniff cannot count against a generated placeholder list.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT product_or_parent_id
				FROM {$table}
				WHERE ( {$where} )
					AND in_stock = 1
					AND is_variation_attribute = %d
				GROUP BY {$group_by}
				HAVING COUNT( DISTINCT taxonomy ) = %d",
				$params
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		return array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
	}

	/**
	 * Intersect per-taxonomy lookup matches, for products that split their
	 * attributes between the parent and its variations.
	 *
	 * @param array<string, array<int, int>> $term_ids_by_taxonomy Term ids keyed by taxonomy.
	 * @return array<int, int>
	 */
	private function query_lookup_per_taxonomy( array $term_ids_by_taxonomy ): array {
		global $wpdb;

		$table   = $wpdb->prefix . 'wc_product_attributes_lookup';
		$matches = null;

		foreach ( $term_ids_by_taxonomy as $taxonomy => $term_ids ) {
			$placeholders = implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) );
			$params       = array_merge( array( $taxonomy ), $term_ids );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders are generated; every value is bound.
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT product_or_parent_id
					FROM {$table}
					WHERE taxonomy = %s
						AND term_id IN ( {$placeholders} )
						AND in_stock = 1",
					$params
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );

			$matches = null === $matches ? $ids : array_values( array_intersect( $matches, $ids ) );
		}

		return is_array( $matches ) ? $matches : array();
	}

	/**
	 * Match against variation post meta when the lookup table is unavailable.
	 *
	 * Cannot see stock status, so it keeps the pre-lookup behaviour.
	 *
	 * @param array<string, array<int, string>> $selected Terms keyed by taxonomy.
	 * @return array<int, int>|null
	 */
	private function get_matching_parent_ids_fallback( array $selected ): ?array {
		global $wpdb;

		$matches = null;

		foreach ( $selected as $taxonomy => $terms ) {
			sort( $terms );

			$placeholders = implode( ', ', array_fill( 0, count( $terms ), '%s' ) );
			$params       = array_merge( array( 'attribute_' . $taxonomy ), $terms );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders are generated; every value is bound.
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT p.post_parent
					FROM {$wpdb->posts} AS p
					INNER JOIN {$wpdb->postmeta} AS pm ON pm.post_id = p.ID
					WHERE p.post_type = 'product_variation'
						AND p.post_status IN ( 'publish', 'private' )
						AND p.post_parent > 0
						AND pm.meta_key = %s
						AND pm.meta_value IN ( {$placeholders} )",
					$params
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$ids     = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
			$matches = null === $matches ? $ids : array_values( array_intersect( $matches, $ids ) );
		}

		return $matches;
	}

	/**
	 * Whether WooCommerce's attribute lookup table is usable.
	 *
	 * @return bool
	 */
	private function lookup_table_available(): bool {
		return 'yes' === get_option( 'woocommerce_attribute_lookup_enabled' );
	}

	/**
	 * Resolve term slugs to term ids for a taxonomy.
	 *
	 * @param string             $taxonomy Attribute taxonomy.
	 * @param array<int, string> $slugs    Term slugs.
	 * @return array<int, int>
	 */
	private function get_term_ids( string $taxonomy, array $slugs ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'slug'       => $slugs,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'absint', (array) $terms ) ) );
	}

	/**
	 * Remove attribute tax clauses replaced by variation parent matching.
	 *
	 * @param array<int, mixed>  $tax_query  Existing tax query.
	 * @param array<int, string> $taxonomies Taxonomies to remove.
	 * @return array<int, mixed>
	 */
	private function remove_attribute_tax_clauses( array $tax_query, array $taxonomies ): array {
		$taxonomies = array_map( 'sanitize_key', $taxonomies );
		$filtered   = array();

		foreach ( $tax_query as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['taxonomy'] ) ) {
				$filtered[] = $clause;
				continue;
			}

			if ( in_array( sanitize_key( (string) $clause['taxonomy'] ), $taxonomies, true ) ) {
				continue;
			}

			$filtered[] = $clause;
		}

		return $filtered;
	}

	/**
	 * Whether variation filtering is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {
		return 'yes' === get_option( self::OPTION_ENABLED, 'no' );
	}

	/**
	 * Cache invalidation token, shared with the free plugin so product edits
	 * expire Pro caches too.
	 *
	 * @return string
	 */
	private function get_cache_last_changed(): string {
		$last_changed = get_option( 'cscf_cache_last_changed', '' );

		return is_string( $last_changed ) ? $last_changed : '';
	}
}
