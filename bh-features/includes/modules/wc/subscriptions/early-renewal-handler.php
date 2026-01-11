<?php
/**
 * AH Subscriptions Early Renewal Handler
 *
 * Forces immediate Stripe capture for Early Renewals ("Renew now")
 * instead of authorization holds.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AH_Subscriptions_Early_Renewal_Handler' ) ) {

class AH_Subscriptions_Early_Renewal_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {

		// Legacy / charge API path.
		add_filter(
			'wc_stripe_generate_payment_request',
			array( $this, 'filter_payment_request' ),
			20,
			3
		);

		// Modern Payment Intents path.
		add_filter(
			'wc_stripe_generate_create_intent_request',
			array( $this, 'filter_create_intent_request' ),
			20,
			3
		);
	}

	/**
	 * Wrapper for the legacy "generate_payment_request" path.
	 *
	 * @param array    $post_data
	 * @param WC_Order $order
	 * @param object   $prepared_source
	 *
	 * @return array
	 */
	public function filter_payment_request( $post_data, $order, $prepared_source ) {
		return $this->maybe_force_immediate_capture( $post_data, $order, 'payment_request' );
	}

	/**
	 * Wrapper for the Payment Intents "generate_create_intent_request" path.
	 *
	 * Signature may vary by version, so keep third param optional.
	 *
	 * @param array         $request
	 * @param WC_Order      $order
	 * @param object|null   $prepared_source
	 *
	 * @return array
	 */
	public function filter_create_intent_request( $request, $order, $prepared_source = null ) {
		return $this->maybe_force_immediate_capture( $request, $order, 'create_intent' );
	}

	/**
	 * Core logic to detect Early Renewals and force immediate capture.
	 *
	 * @param array    $data
	 * @param WC_Order $order
	 * @param string   $context  "payment_request" or "create_intent"
	 *
	 * @return array
	 */
	protected function maybe_force_immediate_capture( $data, $order, $context ) {

	    $logger  = wc_get_logger();
	    $log_ctx = array( 'source' => 'ah_subscriptions_renewal' );

	    $logger->info( "--- AH Early Renewal [$context] START ---", $log_ctx );

	    // Basic sanity check.
	    if ( ! $order instanceof WC_Order ) {
	        $logger->warning( "[$context] Invalid order object. Aborting.", $log_ctx );
	        return $data;
	    }

	    $order_id       = $order->get_id();
	    $payment_method = $order->get_payment_method();

	    $logger->info( "[$context] Order ID: {$order_id}", $log_ctx );
	    $logger->info( "[$context] Payment method: {$payment_method}", $log_ctx );

	    // Only target Stripe gateway. Allow override via filter if needed.
	    $allowed_gateways = apply_filters(
	        'ah_subscriptions_early_renewal_stripe_gateways',
	        array( 'stripe' )
	    );

	    if ( ! in_array( $payment_method, $allowed_gateways, true ) ) {
	        $logger->info( "[$context] Payment method not allowed. Skipping.", $log_ctx );
	        return $data;
	    }

	    // Check this is a renewal order.
	    $is_renewal = function_exists( 'wcs_order_contains_renewal' ) ? wcs_order_contains_renewal( $order ) : false;
	    $logger->info( "[$context] Is renewal order: " . ( $is_renewal ? 'YES' : 'NO' ), $log_ctx );

	    if ( ! $is_renewal ) {
	        $logger->info( "[$context] Not a renewal order. Skipping.", $log_ctx );
	        $logger->info( "--- AH Early Renewal [$context] END (not renewal) ---", $log_ctx );
	        return $data;
	    }

	    // Detect Early Renewal via meta set in WCS_Cart_Early_Renewal.
	    $early_meta_value = $order->get_meta( '_subscription_renewal_early', true );
	    $is_early         = ! empty( $early_meta_value );
	    
	    $logger->info(
	        "[$context] Is EARLY renewal: " . ( $is_early ? 'YES' : 'NO' ),
	        $log_ctx
	    );

	    if ( ! $is_early ) {
	        $logger->info( "[$context] Renewal but not EARLY. Skipping.", $log_ctx );
	        $logger->info( "--- AH Early Renewal [$context] END (not early) ---", $log_ctx );
	        return $data;
	    }

	    /**
	     * Minimal, safe changes:
	     * - Remove deprecated/invalid 'capture' param for PaymentIntents.
	     * - Force capture_method to 'automatic' ONLY for early renewals.
	     * - Do NOT touch 'confirm' or 'confirmation_method' here to avoid boolean issues.
	     */

	    if ( isset( $data['capture'] ) ) {
	        unset( $data['capture'] );
	        $logger->info( "[$context] Removed deprecated 'capture' param from payload.", $log_ctx );
	    }

	    // Force automatic capture for early renewals.
	    $data['capture_method'] = 'automatic';

		$order->add_order_note(
	        'Attention: Early Renewal detected.'
	    );
	    $logger->info( "--- AH Early Renewal [$context] END (modified) ---", $log_ctx );

	    return $data;
	}


}

add_action( 'woocommerce_loaded', function() {
    if ( class_exists( 'AH_Subscriptions_Early_Renewal_Handler' ) ) {
        new AH_Subscriptions_Early_Renewal_Handler();
    }
});

}