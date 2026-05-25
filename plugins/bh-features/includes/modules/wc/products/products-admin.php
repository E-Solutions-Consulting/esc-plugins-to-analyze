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
		add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'variation_settings'], 50, 3 );
		add_action( 'woocommerce_save_product_variation', [ $this, 'save_variation_settings'], 10, 2 );

		add_action( 'woocommerce_variation_options_pricing', [$this, 'render_variation_renewal_days_field'], 10, 3 );
		add_action( 'woocommerce_save_product_variation', [$this, 'save_variation_renewal_days_field'], 10, 2 );
    }

	function variation_settings( $loop, $variation_data, $variation ) {
		$variation_id 				=	$variation->ID;
		$bh_checkout_text_supply    =	get_post_meta( $variation_id, 'bh_checkout_text_supply', true );
		$bh_checkout_text_due_today	=	get_post_meta( $variation_id, 'bh_checkout_text_due_today', true );
		$bh_checkout_text_term_conditions	=	get_post_meta( $variation_id, 'bh_checkout_text_term_conditions', true );

		echo '<hr>';
		echo '<div class="form-row form-row-full woovr-variation-settings">';
		echo '<h3>' . esc_html__( 'Checkout Info', 'bh-features' ) . '</h3>';
		echo '<div class="woovr-variation-wrap bh">';

		echo '<p class="form-field form-row">';
		echo '<label>' . esc_html__( 'Supply Text', 'bh-features' ) . '</label>';
		echo '<input type="text" class="bh_checkout_text_supply" name="' . esc_attr( 'bh_checkout_text_supply[' . $variation_id . ']' ) . '" value="' . esc_attr( $bh_checkout_text_supply ) . '"/>';
		echo '</p>';
		echo '<p class="form-field form-row">';
		echo '<label>' . esc_html__( 'Due Today Text', 'bh-features' ) . '</label>';
		echo '<input type="text" class="bh_checkout_text_due_today" name="' . esc_attr( 'bh_checkout_text_due_today[' . $variation_id . ']' ) . '" value="' . esc_attr( $bh_checkout_text_due_today ) . '"/>';
		echo '</p>';
		echo '<p class="form-field form-row">';
		echo '<label>' . esc_html__( 'Terms & Conditions', 'bh-features' ) . '</label>';
		echo '<input type="text" class="bh_checkout_text_term_conditions" name="' . esc_attr( 'bh_checkout_text_term_conditions[' . $variation_id . ']' ) . '" value="' . esc_attr( $bh_checkout_text_term_conditions ) . '"/>';
		echo '</p>';
		echo '</div></div>';
		echo '<style>.woovr-variation-wrap.bh p.form-field.form-row {display: flex;align-items: center;justify-content: space-between;}.woovr-variation-wrap.bh p.form-field.form-row > label {min-width: 130px;}</style>';
	}

	function save_variation_settings( $post_id ) {
		if ( isset( $_POST['bh_checkout_text_supply'][ $post_id ] ) ) {
			update_post_meta( $post_id, 'bh_checkout_text_supply', sanitize_text_field( $_POST['bh_checkout_text_supply'][ $post_id ] ) );
		} else {
			delete_post_meta( $post_id, 'bh_checkout_text_supply' );
		}

		if ( isset( $_POST['bh_checkout_text_due_today'][ $post_id ] ) ) {
			update_post_meta( $post_id, 'bh_checkout_text_due_today', sanitize_text_field( $_POST['bh_checkout_text_due_today'][ $post_id ] ) );
		} else {
			delete_post_meta( $post_id, 'bh_checkout_text_due_today' );
		}

		if ( isset( $_POST['bh_checkout_text_term_conditions'][ $post_id ] ) ) {
			update_post_meta( $post_id, 'bh_checkout_text_term_conditions', sanitize_text_field( $_POST['bh_checkout_text_term_conditions'][ $post_id ] ) );
		} else {
			delete_post_meta( $post_id, 'bh_checkout_text_term_conditions' );
		}
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
