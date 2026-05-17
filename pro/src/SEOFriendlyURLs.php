<?php
/**
 * SEO-friendly filter URLs with pretty permalinks.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOFriendlyURLs {
	public function register_hooks(): void {
		add_filter( 'cscf_query_args', array( $this, 'parse_seo_url' ), 5, 2 );
		add_action( 'init', array( 'ClandevsSmartCatalogFiltersPro\SEOFriendlyURLs', 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
	}

	public static function register_rewrite_rules(): void {
		add_rewrite_rule( '^shop/filter/([^/]+)/?', 'index.php?post_type=product&cscf_filter=$matches[1]', 'top' );
	}

	public function add_query_vars( array $vars ): array {
		$vars[] = 'cscf_filter';
		return $vars;
	}

	public function parse_seo_url( array $query_args, array $filter_options ): array {
		$filter_slug = get_query_var( 'cscf_filter', '' );
		if ( '' === $filter_slug ) {
			return $query_args;
		}
		$parts = explode( '/', trim( $filter_slug, '/' ) );
		foreach ( $parts as $part ) {
			if ( 0 === strpos( $part, 'cat-' ) ) {
				$slug = substr( $part, 4 );
				$query_args['tax_query'][] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => array( $slug ),
				);
			} elseif ( 0 === strpos( $part, 'brand-' ) ) {
				$slug = substr( $part, 6 );
				$brand_tax = $this->get_brand_taxonomy();
				if ( $brand_tax ) {
					$query_args['tax_query'][] = array(
						'taxonomy' => $brand_tax,
						'field'    => 'slug',
						'terms'    => array( $slug ),
					);
				}
			}
		}
		return $query_args;
	}

	private function get_brand_taxonomy(): string {
		$taxonomies = array( 'product_brand', 'pa_brand', 'brand' );
		foreach ( $taxonomies as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				return $tax;
			}
		}
		return '';
	}
}
