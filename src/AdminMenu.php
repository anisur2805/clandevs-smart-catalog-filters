<?php
/**
 * Admin menu controller.
 *
 * @package ClandevsSmartCatalogFilters
 */

namespace ClandevsSmartCatalogFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers primary Clandevs Smart Catalog Filters admin menu.
 */
final class AdminMenu {
	/** @var string */
	private const MENU_SLUG = 'clandevs-smart-catalog-filters';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 5 );
	}

	/**
	 * Get main menu slug.
	 *
	 * @return string
	 */
	public static function get_menu_slug(): string {
		return self::MENU_SLUG;
	}

	/**
	 * Register top-level menu.
	 *
	 * The first submenu registered by FilterSettings uses the same slug
	 * as this parent, so WordPress merges them into a single menu entry.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Clandevs Smart Catalog Filters', 'clandevs-smart-catalog-filters' ),
			__( 'Clandevs Smart Catalog Filters', 'clandevs-smart-catalog-filters' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-filter',
			56
		);

		/**
		 * Fires after the main plugin menu is registered.
		 * The Pro add-on uses this to register its own submenu pages.
		 *
		 * @param string $menu_slug The main menu slug.
		 */
		do_action( 'cscf_admin_menu_registered', self::MENU_SLUG );

		// Hide the auto-generated duplicate submenu entry for the top-level page.
		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
	}

	/**
	 * Render the dashboard landing page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$links = array(
			array(
				'title' => __( 'Styling', 'clandevs-smart-catalog-filters' ),
				'text'  => __( 'Tune the look and feel of the filters.', 'clandevs-smart-catalog-filters' ),
				'url'   => admin_url( 'admin.php?page=cscf-style-settings' ),
			),
			array(
				'title' => __( 'Filter Settings', 'clandevs-smart-catalog-filters' ),
				'text'  => __( 'Choose which filter blocks appear on the storefront.', 'clandevs-smart-catalog-filters' ),
				'url'   => admin_url( 'admin.php?page=cscf-filter-settings' ),
			),
			array(
				'title' => __( 'Filter Order', 'clandevs-smart-catalog-filters' ),
				'text'  => __( 'Reorder the storefront filter sections.', 'clandevs-smart-catalog-filters' ),
				'url'   => admin_url( 'admin.php?page=cscf-filter-order' ),
			),
			array(
				'title' => __( 'Analytics', 'clandevs-smart-catalog-filters' ),
				'text'  => __( 'Review how customers use your filters.', 'clandevs-smart-catalog-filters' ),
				'url'   => admin_url( 'admin.php?page=cscf-filter-analytics' ),
			),
		);

		if ( class_exists( '\ClandevsSmartCatalogFiltersPro\ProPlugin' ) ) {
			$links[] = array(
				'title' => __( 'Import / Export', 'clandevs-smart-catalog-filters' ),
				'text'  => __( 'Backup or restore filter, style, and Pro settings.', 'clandevs-smart-catalog-filters' ),
				'url'   => admin_url( 'admin.php?page=cscf-import-export' ),
			);
		}
		?>
		<div class="wf-admin-wrap">
			<div class="wf-admin-header">
				<div class="wf-admin-header-icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18l-7 8v6l-4 2v-8L3 4z"/></svg>
				</div>
				<div>
					<h1><?php esc_html_e( 'Clandevs Smart Catalog Filters', 'clandevs-smart-catalog-filters' ); ?></h1>
					<p><?php esc_html_e( 'Quick access to filter styling, visibility, ordering, and reporting.', 'clandevs-smart-catalog-filters' ); ?></p>
				</div>
			</div>

			<div class="wf-admin-grid-2">
				<?php foreach ( $links as $link ) : ?>
					<a class="wf-admin-link-card" href="<?php echo esc_url( (string) $link['url'] ); ?>">
						<strong><?php echo esc_html( (string) $link['title'] ); ?></strong>
						<span><?php echo esc_html( (string) $link['text'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
