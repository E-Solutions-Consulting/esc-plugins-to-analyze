<?php
/**
 * Free Renewals — variation admin fields.
 *
 * Adds two fields to each subscription variation, mirroring the existing
 * "Renewal Days" (_bh_renewal_days) field pattern:
 *
 *   Free Renewals       (_bh_free_renewals)       X — uncharged renewals.
 *   Plan Duration Days  (_bh_plan_duration_days)  T — explicit expiry (optional).
 *
 * Validation is soft (logged + admin notice) while the term is native: a
 * misconfiguration is surfaced but not blocked. It becomes hard once the plan
 * duration field is adopted as the term source.
 *
 * @package bh-features
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AH_Free_Renewals_Admin' ) ) {

class AH_Free_Renewals_Admin {

	const NOTICE_TRANSIENT = 'ah_free_renewals_admin_notice';

	public function __construct() {
		add_action( 'woocommerce_variation_options_pricing', [ $this, 'render_fields' ], 20, 3 );
		add_action( 'woocommerce_save_product_variation', [ $this, 'save_fields' ], 20, 2 );
		add_action( 'admin_notices', [ $this, 'show_validation_notice' ] );
	}

	/**
	 * Render the Free Renewals and Plan Duration Days fields inside a variation.
	 *
	 * @param int     $loop
	 * @param array   $variation_data
	 * @param WP_Post $variation
	 */
	public function render_fields( int $loop, array $variation_data, WP_Post $variation ): void {
		$free_renewals = get_post_meta( $variation->ID, AH_Free_Renewals::META_FREE_RENEWALS, true );
		$plan_duration = get_post_meta( $variation->ID, AH_Free_Renewals::META_PLAN_DURATION, true );
		?>
		<p class="form-row form-row-first">
			<label for="bh_free_renewals_<?php echo esc_attr( $loop ); ?>">
				<?php esc_html_e( 'Free Renewals', 'bh-features' ); ?>
				<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Number of uncharged renewal deliveries after the initial order. Requires a recurring price of 0. Leave empty for a normal subscription.', 'bh-features' ); ?>"></span>
			</label>
			<input
				type="number"
				min="0"
				step="1"
				id="bh_free_renewals_<?php echo esc_attr( $loop ); ?>"
				name="bh_free_renewals[<?php echo esc_attr( $loop ); ?>]"
				value="<?php echo esc_attr( $free_renewals ); ?>"
			/>
		</p>
		<p class="form-row form-row-last">
			<label for="bh_plan_duration_days_<?php echo esc_attr( $loop ); ?>">
				<?php esc_html_e( 'Plan Duration Days', 'bh-features' ); ?>
				<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Explicit expiry in days from the start date. Leave empty to use the native "Stop renewing after" term.', 'bh-features' ); ?>"></span>
			</label>
			<input
				type="number"
				min="0"
				step="1"
				id="bh_plan_duration_days_<?php echo esc_attr( $loop ); ?>"
				name="bh_plan_duration_days[<?php echo esc_attr( $loop ); ?>]"
				value="<?php echo esc_attr( $plan_duration ); ?>"
			/>
		</p>
		<?php
	}

	/**
	 * Persist both fields and run soft validation.
	 *
	 * @param int $variation_id
	 * @param int $loop
	 */
	public function save_fields( int $variation_id, int $loop ): void {
		$free_renewals = isset( $_POST['bh_free_renewals'][ $loop ] ) ? (int) $_POST['bh_free_renewals'][ $loop ] : 0;
		$plan_duration = isset( $_POST['bh_plan_duration_days'][ $loop ] ) ? (int) $_POST['bh_plan_duration_days'][ $loop ] : 0;

		if ( $free_renewals > 0 ) {
			update_post_meta( $variation_id, AH_Free_Renewals::META_FREE_RENEWALS, $free_renewals );
		} else {
			delete_post_meta( $variation_id, AH_Free_Renewals::META_FREE_RENEWALS );
		}

		if ( $plan_duration > 0 ) {
			update_post_meta( $variation_id, AH_Free_Renewals::META_PLAN_DURATION, $plan_duration );
		} else {
			delete_post_meta( $variation_id, AH_Free_Renewals::META_PLAN_DURATION );
		}

		$this->validate( $variation_id, $free_renewals, $plan_duration );
	}

	/**
	 * Soft validation: surface inconsistent configs without blocking the save.
	 *
	 * @param int $variation_id
	 * @param int $free_renewals
	 * @param int $plan_duration
	 */
	private function validate( int $variation_id, int $free_renewals, int $plan_duration ): void {
		if ( $free_renewals < 1 ) {
			return;
		}

		$warnings = [];

		$recurring_price = (float) get_post_meta( $variation_id, '_subscription_price', true );
		$sign_up_fee     = (float) get_post_meta( $variation_id, '_subscription_sign_up_fee', true );

		if ( ( $recurring_price + $sign_up_fee ) <= 0 ) {
			$warnings[] = sprintf(
				__( 'Variation #%d has %d free renewals but no up-front charge (recurring price and sign-up fee are both 0). Nothing will be charged. Set the recurring subscription price (renewals are zeroed automatically in code).', 'bh-features' ),
				$variation_id,
				$free_renewals
			);
		}

		$renewal_days = (int) get_post_meta( $variation_id, '_bh_renewal_days', true );

		if ( $plan_duration > 0 && $renewal_days > 0 && $plan_duration <= $free_renewals * $renewal_days ) {
			$warnings[] = sprintf(
				__( 'Variation #%d: plan duration (%d days) is not greater than free renewals × renewal days (%d × %d). The plan may expire before all deliveries complete.', 'bh-features' ),
				$variation_id,
				$plan_duration,
				$free_renewals,
				$renewal_days
			);
		}

		if ( empty( $warnings ) ) {
			return;
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();

			foreach ( $warnings as $warning ) {
				$logger->warning( $warning, [ 'source' => 'ah-free-renewals' ] );
			}
		}

		set_transient( self::NOTICE_TRANSIENT, $warnings, 60 );
	}

	/**
	 * Display any validation warnings queued during the last variation save.
	 */
	public function show_validation_notice(): void {
		$warnings = get_transient( self::NOTICE_TRANSIENT );

		if ( empty( $warnings ) || ! is_array( $warnings ) ) {
			return;
		}

		delete_transient( self::NOTICE_TRANSIENT );

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Free Renewals configuration', 'bh-features' ) . '</strong></p>';

		foreach ( $warnings as $warning ) {
			echo '<p>' . esc_html( $warning ) . '</p>';
		}

		echo '</div>';
	}
}

}