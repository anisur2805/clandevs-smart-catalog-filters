<?php
/**
 * Review prompt controller.
 *
 * @package ClandevsSmartCatalogFilters
 */

namespace ClandevsSmartCatalogFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows a dismissible review prompt after configurable usage thresholds.
 */
final class ReviewPrompt {
	/** @var string */
	private const ACTIVATION_OPTION = 'cscf_activation_time';

	/** @var string */
	private const FILTER_COUNT_OPTION = 'cscf_filter_use_count';

	/** @var string */
	private const USER_META_KEY = 'cscf_review_prompt_dismissed';

	/** @var int */
	private const DEFAULT_DAYS_THRESHOLD = 7;

	/** @var int */
	private const DEFAULT_USE_THRESHOLD = 50;

	/**
	 * Register class hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_script' ) );
		add_action( 'wp_ajax_cscf_dismiss_review_prompt', array( $this, 'handle_dismiss' ) );
	}

	/**
	 * Enqueue the script that persists the notice dismissal.
	 *
	 * @return void
	 */
	public function enqueue_admin_script(): void {
		if ( ! $this->should_show_notice() ) {
			return;
		}

		wp_enqueue_script(
			'cscf-review-prompt',
			CSCF_PLUGIN_URL . 'assets/js/review-prompt.js',
			array( 'jquery' ),
			CSCF_VERSION,
			true
		);
	}

	/**
	 * Increment filter usage counter.
	 *
	 * @return void
	 */
	public static function record_filter_use(): void {
		$count = (int) get_option( self::FILTER_COUNT_OPTION, 0 );
		update_option( self::FILTER_COUNT_OPTION, $count + 1, false );
	}

	/**
	 * Render review notice if thresholds are met and not dismissed.
	 *
	 * @return void
	 */
	public function maybe_render_notice(): void {
		if ( ! $this->should_show_notice() ) {
			return;
		}

		$review_url  = 'https://wordpress.org/support/plugin/clandevs-smart-catalog-filters/reviews/#new-post';
		$dismiss_url = wp_nonce_url(
			admin_url( 'admin-ajax.php?action=cscf_dismiss_review_prompt' ),
			'cscf_dismiss_review_prompt',
			'nonce'
		);
		?>
		<div class="notice notice-info is-dismissible cscf-review-notice" data-dismiss-url="<?php echo esc_url( $dismiss_url ); ?>">
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: review URL. */
						__( 'Enjoying <strong>Clandevs — WooCommerce Product Filter</strong>? Please <a href="%s" target="_blank">leave a review</a> to help others discover it.', 'clandevs-smart-catalog-filters' ),
						esc_url( $review_url )
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Determine whether the review notice should be shown.
	 *
	 * @return bool
	 */
	private function should_show_notice(): bool {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$dismissed = (bool) get_user_meta( $user_id, self::USER_META_KEY, true );
		if ( $dismissed ) {
			return false;
		}

		$activated_at = (int) get_option( self::ACTIVATION_OPTION, 0 );
		if ( $activated_at <= 0 ) {
			return false;
		}

		$days_threshold = (int) apply_filters( 'cscf_review_prompt_days', self::DEFAULT_DAYS_THRESHOLD );
		if ( time() < ( $activated_at + ( DAY_IN_SECONDS * max( 1, $days_threshold ) ) ) ) {
			return false;
		}

		$use_threshold = (int) apply_filters( 'cscf_review_prompt_uses', self::DEFAULT_USE_THRESHOLD );
		$use_count     = (int) get_option( self::FILTER_COUNT_OPTION, 0 );
		if ( $use_count < max( 1, $use_threshold ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Handle dismiss action via AJAX.
	 *
	 * @return void
	 */
	public function handle_dismiss(): void {
		$user_id = get_current_user_id();
		if ( ! $user_id || ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'clandevs-smart-catalog-filters' ) ) );
		}

		if ( ! check_ajax_referer( 'cscf_dismiss_review_prompt', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'clandevs-smart-catalog-filters' ) ) );
		}

		update_user_meta( $user_id, self::USER_META_KEY, 1 );
		wp_send_json_success( array( 'message' => __( 'Dismissed', 'clandevs-smart-catalog-filters' ) ) );
	}
}
