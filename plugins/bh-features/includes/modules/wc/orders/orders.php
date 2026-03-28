<?php
/**
 * AH Orders
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Orders' ) ) {

class AH_Orders {

    public function __construct() {

        // Add this to your main plugin file or in the class constructor
		add_filter('woocommerce_rest_shop_order_schema', function($schema) {
			// Add our custom statuses to the enum
			$schema['properties']['status']['enum'][] = CANCEL_CUSTOMER_REQUEST;
			$schema['properties']['status']['enum'][] = CANCEL_AUTHORIZATION_EXPIRED;
			$schema['properties']['status']['enum'][] = CANCEL_PATIENT_REJECTED;
			// $schema['properties']['status']['enum'][] = 'send-to-telegra';
			// $schema['properties']['status']['enum'][] = 'kit-ready-to-send';
			
			return $schema;
		});

		// Add status mapping for the REST API
		add_filter('woocommerce_rest_prepare_shop_order', function($response, $order, $request) {
			$data = $response->get_data();
			$data['status'] = $order->get_status();
			return rest_ensure_response($data);
		}, 10, 3);

		add_action('woocommerce_before_order_object_save', array($this, 'intercept_status_change_before_save'), 10, 2);
		// Add this to your class constructor or init_hooks()
		add_action('woocommerce_order_status_changed', [$this, 'handle_wc_api_status_change'], 10, 4);

        add_action('bh_stripe_payment_intent_canceled', array( $this, 'handle_stripe_payment_intent_canceled' ), 10, 5);

		/**
		 * Filters the formatted line subtotal in WooCommerce orders to append custom renewal text 
		 * for subscription products based on their billing interval.
		 */
		add_filter( 'woocommerce_order_formatted_line_subtotal', [$this, 'woocommerce_order_formatted_line_subtotal'], 10, 3 );

		add_filter( 'woocommerce_display_item_meta', [$this, 'hb_woocommerce_display_item_meta'], 10, 3 );

		//add_action('woocommerce_order_status_changed', [$this, 'clean_custom_cancel_notes'], 5, 4);
    }


	// function clean_custom_cancel_notes($order_id, $from_status, $to_status, $order) {
	// 	if ($to_status === CANCEL_AUTHORIZATION_EXPIRED) {
	// 		// Prevenir que se añada la note automática
	// 		add_filter('woocommerce_order_note_to_add', '__return_empty_string');
	// 	}
	// }
    
	protected function apply_cancellation_flow__( $order_id, $target_status, $context = 'admin', $args = [] ) {

		// 1️⃣ allowed statuses same
		$allowed = [CANCEL_AUTHORIZATION_EXPIRED, CANCEL_PATIENT_REJECTED, CANCEL_CUSTOMER_REQUEST, 'cancelled'];

		$target_status = str_replace('wc-', '', strtolower($target_status));
		if ( ! in_array($target_status, $allowed, true) ) {
			return ['success' => false, 'message' => 'Invalid target status'];
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return ['success' => false, 'message' => 'Order not found'];
		}

		$lock_key = 'ah_cancel_lock_' . $order->get_id();
		if ( get_transient($lock_key) ) {
			return ['success' => false, 'message' => 'Locked'];
		}

		// 2️⃣ increase lock time to handle Stripe retries (5 min)
		set_transient($lock_key, '1', 300);

		$current   = $order->get_status();
		$pi_id     = $args['pi_id']     ?? '';
		$pi_reason = $args['pi_reason'] ?? '';
		$note_base = $args['note']      ?? '';
		$source_label = ($context === 'stripe') ? 'Stripe' : 'Admin';

		// 3️⃣ NEW: early guard for any cancel-type status (prevents re-cancel)
		if ( in_array( $current, ['cancelled', CANCEL_AUTHORIZATION_EXPIRED, CANCEL_PATIENT_REJECTED, CANCEL_CUSTOMER_REQUEST], true ) ) {
			delete_transient($lock_key);
			if ( function_exists('wc_get_logger') ) {
				wc_get_logger()->info("AH cancellation skipped (already {$current})", [
					'order_id' => $order_id,
					'pi'       => $pi_id,
					'reason'   => $pi_reason
				]);
			}
			return ['success' => true, 'message' => 'Already cancelled-type, skipping duplicate cancel'];
		}

		// (keep old guard)
		if ( $current === $target_status ) {
			delete_transient($lock_key);
			return ['success' => true, 'message' => 'Already at target'];
		}

		$note1 = trim(sprintf('%s: set to cancelled. %s%s',
			$source_label,
			$pi_id ? "Payment Intent: {$pi_id}. " : '',
			$note_base
		));
		
		// $note2 = trim(sprintf('%s: Stripe Pre-Authorization Expired',
		// 	$source_label,
		// 	$pi_id ? " {$pi_id} " : ''
		// ));

		$note2 = trim(sprintf('Stripe Pre-Authorization Expired (Payment Intent ID: %s). ',
			$pi_id,
		));

		// 4️⃣ Only cancel if NOT already any cancel-type
		if ( ! in_array( $current, ['cancelled', CANCEL_AUTHORIZATION_EXPIRED, CANCEL_PATIENT_REJECTED, CANCEL_CUSTOMER_REQUEST], true ) ) {
			// $order->update_status( 'cancelled', $note1, true );
			$order->update_status( 'cancelled' );
		}

		if ( $target_status !== 'cancelled' ) {
			$order->update_status( $target_status, $note2, true );
			//$order->update_status( $target_status );
		}

		// 5️⃣ meta data update same as before
		if ( $context )  $order->update_meta_data('_ah_cancel_source', $context);
		if ( $pi_id )    $order->update_meta_data('_ah_cancel_pi_id', $pi_id);
		if ( $pi_reason )$order->update_meta_data('_ah_cancel_reason', $pi_reason);
		$order->save_meta_data();

		delete_transient($lock_key);

		// 6️⃣ final logging (same)
		if ( function_exists('wc_get_logger') ) {
			wc_get_logger()->info("AH cancellation flow: {$context} → {$target_status}", [
				'order_id' => $order->get_id(),
				'pi'       => $pi_id,
				'reason'   => $pi_reason
			]);
		}
		return ['success' => true, 'message' => 'Updated'];
	}

	protected function apply_cancellation_flow( $order_id, $target_status, $context = 'admin', $args = [] ) {

		// 1️⃣ allowed statuses same
		$allowed = [CANCEL_AUTHORIZATION_EXPIRED, CANCEL_PATIENT_REJECTED, CANCEL_CUSTOMER_REQUEST, 'cancelled'];

		$target_status = str_replace('wc-', '', strtolower($target_status));
		if ( ! in_array($target_status, $allowed, true) ) {
			return ['success' => false, 'message' => 'Invalid target status'];
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return ['success' => false, 'message' => 'Order not found'];
		}

		$lock_key = 'ah_cancel_lock_' . $order->get_id();
		if ( get_transient($lock_key) ) {
			return ['success' => false, 'message' => 'Locked'];
		}

		// 2️⃣ increase lock time to handle Stripe retries (5 min)
		set_transient($lock_key, '1', 300);

		$current   = $order->get_status();
		$pi_id     = $args['pi_id']     ?? '';
		$pi_reason = $args['pi_reason'] ?? '';
		$note_base = $args['note']      ?? '';
		$source_label = ($context === 'stripe') ? 'Stripe' : 'Admin';

		// 3️⃣ NEW: early guard for any cancel-type status (prevents re-cancel)
		if ( in_array( $current, ['cancelled', CANCEL_AUTHORIZATION_EXPIRED, CANCEL_PATIENT_REJECTED, CANCEL_CUSTOMER_REQUEST], true ) ) {
			delete_transient($lock_key);
			if ( function_exists('wc_get_logger') ) {
				wc_get_logger()->info("AH cancellation skipped (already {$current})", [
					'order_id' => $order_id,
					'pi'       => $pi_id,
					'reason'   => $pi_reason
				]);
			}
			return ['success' => true, 'message' => 'Already cancelled-type, skipping duplicate cancel'];
		}

		// (keep old guard)
		if ( $current === $target_status ) {
			delete_transient($lock_key);
			return ['success' => true, 'message' => 'Already at target'];
		}

		$note = $this->get_cancellation_note(
					$target_status,
					$source_label,
					$pi_id,
					$note_base
				);


		// 4️⃣ Only cancel if NOT already any cancel-type
		if ( ! in_array( $current, ['cancelled', CANCEL_AUTHORIZATION_EXPIRED, CANCEL_PATIENT_REJECTED, CANCEL_CUSTOMER_REQUEST], true ) ) {
			$order->update_status( 'cancelled' );
		}

		if ( $target_status !== 'cancelled' ) {
			$order->update_status( $target_status, $note, true );
		}

		// 5️⃣ meta data update same as before
		if ( $context )  $order->update_meta_data('_ah_cancel_source', $context);
		if ( $pi_id )    $order->update_meta_data('_ah_cancel_pi_id', $pi_id);
		if ( $pi_reason )$order->update_meta_data('_ah_cancel_reason', $pi_reason);
		$order->save_meta_data();

		delete_transient($lock_key);

		// 6️⃣ final logging (same)
		if ( function_exists('wc_get_logger') ) {
			wc_get_logger()->info("AH cancellation flow: {$context} → {$target_status}", [
				'order_id' => $order->get_id(),
				'pi'       => $pi_id,
				'reason'   => $pi_reason
			]);
		}
		return ['success' => true, 'message' => 'Updated'];
	}

	protected function get_cancellation_note( $target_status, $source_label, $pi_id = '', $note_base = '' ) {

		switch ( $target_status ) {

			case CANCEL_AUTHORIZATION_EXPIRED:
				return trim(sprintf(
					'%s: Pre-Authorization expired.%s<br>',
					$source_label,
					$pi_id ? " (Payment Intent ID: {$pi_id})." : ''
				));

			case CANCEL_PATIENT_REJECTED:
				return sprintf(
					'%s: ',
					$source_label
				);

			case CANCEL_CUSTOMER_REQUEST:
				return sprintf(
					'%s: Order cancelled at customer request.%s',
					$source_label,
					$note_base ? " {$note_base}" : ''
				);

			case 'cancelled':
			default:
				return sprintf(
					'%s: Order marked as cancelled.',
					$source_label
				);
		}
	}

    /**
	 * Intercepts the status change before saving the order
	 * 
	 */
	public function intercept_status_change_before_save( $order, $data_store ) {
		// Only from admin edit form with an explicit order_status change
		if ( ! is_admin() || ! isset($_POST['order_status']) ) {
			return;
		}

		$new_status   = sanitize_text_field($_POST['order_status']);
		$clean_status = str_replace('wc-', '', $new_status);

		// Only act for our custom cancelled variants
		if ( ! in_array($clean_status, [CANCEL_AUTHORIZATION_EXPIRED,CANCEL_PATIENT_REJECTED,CANCEL_CUSTOMER_REQUEST], true) ) {
			return;
		}

		// If already any cancelled variant, let it pass (or you can still normalize it)
		$current = $order->get_status();
		if ( $current === 'cancelled' || in_array($current, [CANCEL_AUTHORIZATION_EXPIRED,CANCEL_PATIENT_REJECTED,CANCEL_CUSTOMER_REQUEST], true) ) {
			return;
		}

		// Stop recursion around this hook while we perform two-step updates
		remove_action('woocommerce_before_order_object_save', [$this, 'intercept_status_change_before_save'], 10);

		// Delegate to centralized helper
		$this->apply_cancellation_flow(
			$order->get_id(),
			$clean_status,
			'admin',
			['note' => 'Admin change']
		);
		

		// Re-attach hook
		add_action('woocommerce_before_order_object_save', [$this, 'intercept_status_change_before_save'], 10, 2);
	}

    /**
	 * Handles status changes from WooCommerce REST API
	 */
	public function handle_wc_api_status_change($order_id, $from_status, $to_status, $order) {
		// Remove 'wc-' prefix if present
		$from_status = str_replace('wc-', '', $from_status);
		$to_status = str_replace('wc-', '', $to_status);
		
		// Only proceed for cancellation statuses
		$cancellation_statuses = [CANCEL_PATIENT_REJECTED, CANCEL_AUTHORIZATION_EXPIRED, CANCEL_AUTHORIZATION_EXPIRED];
		if (!in_array($to_status, $cancellation_statuses, true)) {
			return;
		}
		
		// Skip if this is already being handled by our custom endpoint
		if (doing_action('rest_api_init') && strpos($_SERVER['REQUEST_URI'] ?? '', '/wp-json/ah/order/update-status') !== false) {
			return;
		}
		
		// Get the source (try to determine if it's from WC REST API)
		$source = 'admin';
		if (defined('REST_REQUEST') && REST_REQUEST) {
			$source = 'wc_rest_api';
		}
		
		// First, set to 'cancelled' to trigger the standard WooCommerce cancellation flow
		if ($from_status !== 'cancelled') {
			$order->update_status('cancelled', 'Preparing for custom cancellation status', true);
		}
		
		// Then set to the custom status
		$order->update_status($to_status, 'Custom cancellation status applied via ' . $source, true);
		
		// Call our cancellation flow
		// $this->apply_cancellation_flow(
		// 	$order_id,
		// 	$to_status,
		// 	$source,
		// 	[
		// 		'note' => 'Status changed via WooCommerce API'
		// 	]
		// );
	}

    /**
     * Handle Stripe "payment_intent.canceled" for AH business rules.
     *
     * This method contains the logic extracted from the original
     * handle_stripe_webhook_events() function.
     *
     * @param int         $order_id
     * @param object      $payment_intent Stripe PaymentIntent object.
     * @param object|null $event         Full Stripe event (optional).
     * @param string      $emoji         Emoji used for Slack message.
     * @param string      $intent_url    Stripe dashboard URL for this intent.
     */
    public function handle_stripe_payment_intent_canceled( $order_id, $payment_intent, $event = null, $emoji = '⚠️', $intent_url = '' ) {

        $url     = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
        $message = "```";
        $message .= "{$emoji} Order ID: <{$url}|{$order_id}>\n";

        $order = wc_get_order( $order_id );
        if ( $order ) {
            $order_status = $order->get_status();
            $message     .= "🆕 Order Status: {$order_status}";

            if ( $order_status !== 'cancelled' ) {
                $status_map = array(
                    'automatic'             => CANCEL_AUTHORIZATION_EXPIRED,
                    'requested_by_customer' => CANCEL_PATIENT_REJECTED,
                );

                $reason = isset( $payment_intent->cancellation_reason ) ? $payment_intent->cancellation_reason : '';
                $target = isset( $status_map[ $reason ] ) ? $status_map[ $reason ] : 'cancelled';

                $payload = array(
                    'pi_id'     => $payment_intent->id,
                    'pi_reason' => $reason,
                    'note'      => 'Stripe payment intent canceled (Payment Intent ID: ' . $payment_intent->id . ').',
                );

                // Reuse existing cancellation flow if available.
                if ( isset( $this ) && is_object( $this ) && is_callable( array( $this, 'apply_cancellation_flow' ) ) ) {
                    $this->apply_cancellation_flow( (int) $order_id, $target, 'stripe', $payload );
                } elseif ( function_exists( 'apply_cancellation_flow' ) ) {
                    $this->apply_cancellation_flow( (int) $order_id, $target, 'stripe', $payload );
                } else {
                    // Fallback: direct status manipulation.
                    $order->set_status(
                        'cancelled',
                        __( 'Stripe payment intent canceled (Payment Intent ID: ' . $payment_intent->id . ').', 'woocommerce' ),
                        true
                    );
                    $order->save();

                    if ( isset( $status_map[ $reason ] ) ) {
                        $new_status = $status_map[ $reason ];
                        $order->set_status(
                            $new_status,
                            __( 'Payment status: ' . str_replace( '_', ' ', $new_status ) . ' (Payment Intent ID: ' . $payment_intent->id . ')', 'woocommerce' ),
                            true
                        );
                        $order->save();
                    }
                }

                // Reload order to show final status in Slack message.
                $order = wc_get_order( $order_id );
                if ( $order ) {
                    $message .= ' -> ' . $order->get_status();
                }
            }

            $message .= "\n";
        }

        if ( ! empty( $intent_url ) ) {
            $message .= "🔎 Stripe: <{$intent_url}|{$payment_intent->id}>";
        } else {
            $message .= "🔎 Stripe Payment Intent: {$payment_intent->id}";
        }

        $message .= "```";

        // Slack notification channel (same as original).
        $channel = 'https://hooks.slack.com/services/SDFASDFSD';
        if ( function_exists( 'ah_send_slack_notification' ) ) {
            ah_send_slack_notification( $message, $channel );
        }

        // Optional logging.
        if ( function_exists( 'wc_get_logger' ) ) {
            $logger = wc_get_logger();
            $logger->info(
                'Stripe Webhook Processed: ' . ( isset( $event->type ) ? $event->type : 'payment_intent.canceled' ),
                array(
                    'order_id'       => $order_id,
                    'payment_intent' => $payment_intent->id,
                )
            );
        }
    }

	/**
	 * Filters the formatted line subtotal in WooCommerce orders to append custom renewal text 
	 * for subscription products based on their billing interval.
	 */
	function woocommerce_order_formatted_line_subtotal($subtotal, $item, $order){
		if (!class_exists('WC_Subscriptions_Product'))
			return $subtotal;

		$product = $item->get_product(); // Obtiene el objeto WC_Product
		if (!WC_Subscriptions_Product::is_subscription($product))
			return $subtotal;

		global $printed_new_line;
		if (isset($printed_new_line) && $printed_new_line)
			return $subtotal;

		$subscription_interval = get_post_meta( $product->get_id(), '_subscription_period_interval', true );
		$custom_text = '';
		if ( $subscription_interval == 1 ) {
			$custom_text = 'Renews every 25 days';
		} elseif ( $subscription_interval == 3 ) {
			$custom_text = 'Renews every 10 weeks';
		}
		if(!empty($custom_text))
			$custom_text = '<br/><small style="display:flex;line-height:1rem">'. $custom_text .'</small>';
		return $subtotal . $custom_text;
	}

	/**
	 * Email Meta Data
	 */
	function hb_woocommerce_display_item_meta($html, $item, $args){
		$strings = array();
		$html    = '';
		$args['before']			=	'<ul class="wc-item-meta" style="padding-left:0"><li style="display: flex;align-items: center;gap: 5px;">';
		$args['label_before']	=	'<strong class="wc-item-meta-label">';

		foreach ( $item->get_all_formatted_meta_data() as $meta_id => $meta ) {
			$value     = $args['autop'] ? wp_kses_post( $meta->display_value ) : wp_kses_post( make_clickable( trim( $meta->display_value ) ) );
			$strings[] = $args['label_before'] . wp_kses_post( $meta->display_key ) . $args['label_after'] . $value;
		}

		if ( $strings ) {
			$html = $args['before'] . implode( $args['separator'], $strings ) . $args['after'];
		}
		return $html;
	}

}
/**
 * Instantiate the module ONLY after WooCommerce is fully loaded
 */
add_action('woocommerce_loaded', function() {
    new AH_Orders();
});

}