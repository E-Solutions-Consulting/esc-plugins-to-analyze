<?php
/**
 * AH Test Mode module
 *
 * This module allows you to enable a per-session "test mode" using a URL parameter.
 * When test mode is active:
 *  - A visual banner is displayed in the frontend.
 *  - Execution time on the thank you page is measured and shown in the banner.
 *  - A log entry is written to error_log with the measured time.
 *  - WooCommerce redirect after payment is forced to the standard thank you URL.
 *  - The order is marked as a test order (_ah_test_order = yes).
 *
 * Usage:
 *  - Activate:  https://tusitio.com/?ah_test_mode=1
 *  - Deactivate: https://tusitio.com/?ah_test_mode=0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper function to know if current session is in test mode.
 *
 * @return bool
 */
if ( ! function_exists( 'ah_is_test_mode' ) ) {
	function ah_is_test_mode() {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		$session = WC()->session;
        // _print($session, '$session');
		if ( ! $session ) {
			return false;
		}

		return $session->get( 'ah_test_mode' ) === 'yes';
	}
}

if ( ! class_exists( 'AH_Test_Mode' ) ) {

class AH_Test_Mode {

	/**
	 * Holds the start time for timing measurements (microtime).
	 *
	 * @var float|null
	 */
	protected $start_time = null;

	public function __construct() {
		// Set or clear the test mode flag based on URL parameter.
		add_action( 'init', [ $this, 'maybe_set_test_mode_flag' ], 20 );

		// Mark timing start when entering thank you page.
		add_action( 'template_redirect', [ $this, 'maybe_mark_timing_start' ], 5 );

		// Render visual banner in the frontend.
		add_action( 'wp_footer', [ $this, 'maybe_render_test_banner' ] );

		// Log timing information at the end of the request.
		add_action( 'shutdown', [ $this, 'maybe_log_timing' ] );

		// Force redirect to thank you page when in test mode.
		//add_filter( 'woocommerce_payment_successful_result', [ $this, 'maybe_force_thankyou_redirect' ], 999, 2 );

		//add_action('wp_footer', [$this, 'print_tracking_data'], 998);
	}

	/**
	 * Set or clear test mode flag in WooCommerce session using URL parameter.
	 * ?ah_test_mode=1 -> enable
	 * ?ah_test_mode=0 -> disable
	 *
	 * This only affects the current session.
	 */
	public function maybe_set_test_mode_flag() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$session = WC()->session;

		if ( ! $session ) {
			return;
		}

		if ( isset( $_GET['ah_test_mode'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$flag = sanitize_text_field( wp_unslash( $_GET['ah_test_mode'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( $flag === '1' ) {
				$session->set( 'ah_test_mode', 'yes' );
			}

			if ( $flag === '0' ) {
				$session->__unset( 'ah_test_mode' );
			}
		}
	}

	/**
	 * If test mode is active and we are on the thank you page,
	 * mark the start time for execution timing.
	 */
	public function maybe_mark_timing_start() {
		if ( ! ah_is_test_mode() ) {
			return;
		}

		if ( ! function_exists( 'is_checkout' ) || ! function_exists( 'is_wc_endpoint_url' ) ) {
			return;
		}

		// We only care about the order received (thank you) page.
		if ( is_checkout() && is_wc_endpoint_url( 'order-received' ) ) {
			$this->start_time = microtime( true );
		}
	}

	/**
	 * Display a fixed banner at the bottom of the screen when test mode is active.
	 * If timing is available, show the execution time in milliseconds.
	 */
	public function maybe_render_test_banner() {
		if ( ! ah_is_test_mode() ) {
			return;
		}

		$timing_text = '';

		if ( $this->start_time ) {
			$elapsed_ms  = ( microtime( true ) - $this->start_time ) * 1000;
			$timing_text = ' | Scripts exec time (approx): ' . number_format( $elapsed_ms, 2 ) . ' ms';
		}

		
		?>
		<div class="ah-test-mode-banner">
			<p>
				<strong>TEST MODE ACTIVE</strong>
				<?php echo esc_html( $timing_text ); ?>
				<small>
					(Visible only for this session - ?ah_test_mode=0 to disable)
				</small>
			</p>

			<div class="ah-test-mode-checkout-debug">
				Email: <span id="ah_debug_email">-</span>
				Phone: <span id="ah_debug_phone">-</span>
				State: <span id="ah_debug_state">-</span>
			</div>

			<?php if ( function_exists( 'WC' ) && WC()->cart ) : ?>

			<div class="ah-test-mode-cart-debug">
				<table class="wp-list-table widefat fixed striped" style="font-size:12px;background-color:#fff">
					<thead>
						<tr>
							<th style="text-align:left;">Product</th>
							<th>ID</th>
							<th>Variation</th>
							<th>Qty</th>
							<th>SKU</th>
							<th>Categories</th>
							<th>Type</th>
						</tr>
					</thead>

					<tbody>

					<?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) : 

						// $product      = $cart_item['data'];
						// $product_id   = $product->get_id();
						// $variation_id = $cart_item['variation_id'];

						$product		= $cart_item['data'];
						$product_id		= $product->get_id();
						$variation_id 	= $cart_item['variation_id'];

						$parent_id = $product->is_type('variation')
							? $product->get_parent_id()
							: $product_id;

						$categories = wc_get_product_terms(
							$parent_id,
							'product_cat',
							['fields' => 'slugs']
						);

						$qty          = $cart_item['quantity'];
						$sku          = $product->get_sku();
						$type         = $product->get_type();

						?>
						<tr>
							<td><?php echo esc_html( $product->get_name() ); ?></td>
							<td><?php echo esc_html( $parent_id ); ?></td>
							<td><?php echo esc_html( $variation_id ); ?></td>
							<td><?php echo esc_html( $qty ); ?></td>
							<td><?php echo esc_html( $sku ); ?></td>
							<td><?php echo esc_html( implode(', ', $categories) ); ?></td>
							<td><?php echo esc_html( $type ); ?></td>
						</tr>

					<?php endforeach; ?>

					</tbody>

				</table>

			</div>

		<?php endif; ?>
            
			
		</div>
		<style>
			.ah-test-mode-banner{position:fixed;bottom:0;left:0;width:100%;z-index:999999;background:rgba(96, 230, 61, 0.25);color:#000;padding:0.5rem 1rem;line-height:2rem;}
			.ah-test-mode-banner > p{margin:0}
			.ah-test-mode-checkout-debug {
				background-color: #fff;
				line-height: 2rem;
				font-size: 12px;
				text-align: right;
				display: flex;
				gap: 10px;
				justify-content: center;
			}
		</style>
		<script>
			jQuery(function($){

    function updateCheckoutDebug(){

        let email = $('#billing_email').val();
        let phone = $('#billing_phone').val();
        let state = $('#billing_state').val();

        $('#ah_debug_email').text(email || '-');
        $('#ah_debug_phone').text(phone || '-');
        $('#ah_debug_state').text(state || '-');
    }

    // run initially
    updateCheckoutDebug();

    // detect typing
    $(document).on('input change',
        '#billing_email, #billing_phone, #billing_state',
        updateCheckoutDebug
    );

    // WooCommerce AJAX refresh
    $(document.body).on('updated_checkout', function(){
        updateCheckoutDebug();
    });

});
		</script>
		<?php
	}

	/**
	 * Log execution timing to error_log when test mode is active and we measured time.
	 */
	public function maybe_log_timing() {
		if ( ! ah_is_test_mode() ) {
			return;
		}

		if ( ! $this->start_time ) {
			return;
		}

		$elapsed_ms = ( microtime( true ) - $this->start_time ) * 1000;

		// You can change this to your own logging system if needed.
		error_log( sprintf( '[AH TEST MODE] Thank you scripts exec time: %.2f ms', $elapsed_ms ) );
	}

	/**
	 * When test mode is active, force redirect to the standard thank you page URL.
	 * This helps to avoid custom redirects from other modules during tracking tests.
	 *
	 * Also mark the order as a test order using meta key _ah_test_order = yes.
	 *
	 * @param array    $result   Payment result data.
	 * @param int|null $order_id Related order ID.
	 *
	 * @return array
	 */
	public function maybe_force_thankyou_redirect( $result, $order_id ) {
		if ( ! ah_is_test_mode() ) {
			return $result;
		}

		if ( empty( $order_id ) ) {
			return $result;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return $result;
		}

		// Force redirect to the standard thank you page.
		$thankyou_url        = $order->get_checkout_order_received_url();
		$result['redirect']  = $thankyou_url;

		// Mark this as a test order so you can exclude it from integrations if needed.
		$order->update_meta_data( '_ah_test_order', 'yes' );
		$order->save();

		return $result;
	}

	function print_tracking_data(){
		if (!is_order_received_page()) return;

		if ( ! ah_is_test_mode() ) {
			return ;
		}

		global $tracking_data;
		if(!$tracking_data)
			return ;

		echo '<script>';
		echo 'console.log("Tracking Data:", ' . wp_json_encode( $tracking_data ) . ');';
		echo '</script>';

		if ( function_exists( 'wc_get_logger' ) ) {

		    $logger = wc_get_logger();

		    if ( $logger ) {
		        $logger->info(
		            'Tracking Data: ' . print_r( $tracking_data, true ),
		            [ 'source' => 'ah-tracking-debug' ]
		        );
		    }
		}

	}
}

/**
 * Instantiate the module ONLY after WooCommerce is fully loaded
 */
//add_action('woocommerce_loaded', function() {
    new AH_Test_Mode();
//});
}
