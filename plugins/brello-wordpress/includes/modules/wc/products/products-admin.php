<?php
/**
 * WooCommerce Add to Cart Behavior Module
 *
 * Handles redirect behavior, notices, and other logic for
 * product "Add to Cart" actions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Products_Admin' ) ) {

class AH_Products_Admin {

    public function __construct() {
		add_action( 'woocommerce_variation_options_pricing', [$this, 'render_variation_renewal_days_field'], 10, 3 );
		add_action( 'woocommerce_save_product_variation', [$this, 'save_variation_renewal_days_field'], 10, 2 );
    }

	/**
	 * Render the Renewal Days field inside each product variation panel.
	 *
	 * @param int    $loop           Variation loop index.
	 * @param array  $variation_data Variation data array.
	 * @param WP_Post $variation     Variation post object.
	 */
	function render_variation_renewal_days_field( int $loop, array $variation_data, WP_Post $variation ): void {
		$value = get_post_meta( $variation->ID, '_bh_renewal_days', true );
		?>
		<div class="form-row form-row-first">
			<label for="bh_renewal_days_<?php echo esc_attr( $loop ); ?>">
				<?php esc_html_e( 'Renewal Days', 'bh-features' ); ?>
				<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Number of days added to the completion date of the renewal order to calculate the next payment date. Leave empty to use the default constant.', 'bh-features' ); ?>"></span>
			</label>
			<input
				type="number"
				id="bh_renewal_days_<?php echo esc_attr( $loop ); ?>"
				name="bh_renewal_days[<?php echo esc_attr( $loop ); ?>]"
				value="<?php echo esc_attr( $value ); ?>"
				min="1"
				step="1"
				placeholder="<?php esc_attr_e( 'e.g. 25', 'bh-features' ); ?>"
				class="short"
			/>
		</div>
		<?php
	}

	/**
	 * Save the Renewal Days field for each variation.
	 *
	 * @param int $variation_id
	 * @param int $loop
	 */
	function save_variation_renewal_days_field( int $variation_id, int $loop ): void {
		$value = isset( $_POST['bh_renewal_days'][ $loop ] ) ? (int) $_POST['bh_renewal_days'][ $loop ] : 0;

		if ( $value > 0 ) {
			update_post_meta( $variation_id, '_bh_renewal_days', $value );
		} else {
			delete_post_meta( $variation_id, '_bh_renewal_days' );
		}
	}

}

add_action('woocommerce_loaded', function() {
    new AH_Products_Admin();
});

}
