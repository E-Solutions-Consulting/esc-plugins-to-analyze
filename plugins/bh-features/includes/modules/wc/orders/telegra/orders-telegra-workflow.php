<?php
/**
 * Handles Telegra order status updates and refunds
 *
 * @package    BH_Features
 * @subpackage BH_Features/includes
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'AH_Orders_Telegra_Workflow' ) ) {

/**
 * Class AH_Orders_Telegra_Workflow
 */
class AH_Orders_Telegra_Workflow{

    // private static $instance = null;

    // public static function get_instance() {
    //     if (is_null(self::$instance)) {
    //         self::$instance = new self();
    //     }
    //     return self::$instance;
    // }

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'woocommerce_order_status_changed', [$this, 'update_on_hold_stripe_auth_order_to_telemdnow_status'], 5, 4);
        // add_filter('telegra_should_process_status_update', [$this, 'handle_telegra_status_update'], 10, 2);
    }

    /**
     * Checks if an on-hold order is a Stripe authorization with hold(not captured),
     * and updates it to the Telemdnow-configured status
     */
    function update_on_hold_stripe_auth_order_to_telemdnow_status($order_id, $old_status, $new_status, $order) {

        if ( $new_status !== 'on-hold' ) {
            return;
        }
        $order_status	= get_option( 'telemdnow_trigger_action' );
        $order = wc_get_order( $order_id );
        if ( $order->has_status($order_status) ) {
            return;
        }

        if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
            return;
        }

        if ( $order->get_payment_method() !== 'stripe' ) {
            return;
        }

        $intent_id  = $order->get_meta('_stripe_intent_id');
        $captured   = $order->get_meta('_stripe_charge_captured');

        $is_authorization = (
            ! empty($intent_id) &&
            $captured === 'no'
        );

        if ( ! $is_authorization ) {
            return;
        }

        $order->update_status(
            $order_status,
            'Stripe authorization detected.'
        );
    }

    /**
     * Handle Telegra status updates
     *
     * @param bool $should_process Whether to process the status update
     * @param array $data Status update data
     * @return bool
     */
    public function handle_telegra_status_update($should_process, $data) {
        // Initialize logging with proper directory structure
        $upload_dir = wp_upload_dir();
        $log_dir = trailingslashit($upload_dir['basedir']) . 'ah-logs/';
        
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            // Protect directory
            file_put_contents($log_dir . '.htaccess', "Deny from all\n");
            file_put_contents($log_dir . 'index.php', "<?php\n// Silence is golden.\n");
        }

        $log_file = $log_dir . 'telegra_debug.log';
        
        // Log rotation - keep under 5MB
        if (file_exists($log_file) && filesize($log_file) > 5 * 1024 * 1024) {
            rename($log_file, $log_dir . 'telegra_debug_' . date('Y-m-d_His') . '.log');
        }
        
        $log_message = "=== New Webhook Request ===\n";
        $log_message .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $log_message .= "Input Data: " . print_r($data, true) . "\n";
        
        try {
            $order = null;
            $order_id = null;
            
            // Case 1: Order object is directly provided
            if (isset($data['order']) && is_object($data['order'])) {
                $order = $data['order'];
                $order_id = $order->get_id();
                $log_message .= "Order object provided. ID: {$order_id}\n";
            }
            // Case 2: Extract order ID from webhook data
            elseif (isset($data['targetEntity']['externalIdentifier'])) {
                $order_id = absint($data['targetEntity']['externalIdentifier']); // Sanitize
                $order = wc_get_order($order_id);
                $log_message .= "Order ID from webhook: {$order_id}\n";
            }
            
            if (!$order || !$order_id) {
                $log_message .= "Error: Could not determine order from webhook data\n";
                file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
                return $should_process;
            }
            
            $current_status = $order->get_status();
            $log_message .= "Order #{$order_id} - Current Status: {$current_status}\n";
            
            // Check if already processed
            if ($order->get_meta('_provider_review_processed') === 'yes') {
                $log_message .= "Provider Review already processed for this order, skipping\n";
                file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
                return false;
            }

            // Check for Telegra cancellation
            $is_cancelled = $order->get_meta('telegra_order_cancelled', true);
            if ($is_cancelled) {
                $log_message .= "Telegra order is marked as cancelled\n";
                
                if (!$order->has_status('cancelled')) {
                    $order->update_status('cancelled', __('Order cancelled in Telegra', 'ah-features'));
                    $log_message .= "Updated WooCommerce order status to cancelled\n";
                } else {
                    $log_message .= "Order is already cancelled in WooCommerce\n";
                }
                
                file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
                return false;
            }
            
            // Check for provider_review status in eventData or direct status
            $status = null;
            
            // Check in eventData
            if (isset($data['eventData']['newStatus'])) {
                $status = $data['eventData']['newStatus'];
                $log_message .= "Status from eventData: $status\n";
            } 
            // Check direct status in data
            elseif (isset($data['telegra_status'])) {
                $status = $data['telegra_status'];
                $log_message .= "Status from telegra_status: $status\n";
            }
            
            if ($status === 'provider_review') {
                $log_message .= "Processing Provider Review status...\n";
                $this->handle_provider_review($order);
                $log_message .= "Provider Review processing completed\n";
                file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
                return false;
            }
            
            // Check for provider_review in order notes (fallback)
            $notes = wc_get_order_notes(['order_id' => $order_id]);
            foreach ($notes as $note) {
                // Check both old and new note properties
                $note_content = isset($note->content) ? $note->content : $note->comment_content;
                if (strpos(strtolower($note_content), 'provider review') !== false) {
                    $log_message .= "Found 'Provider Review' in order notes\n";
                    $this->handle_provider_review($order);
                    $log_message .= "Provider Review processing completed\n";
                    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
                    return false;
                }
            }
            
            $log_message .= "No Provider Review status found in webhook data or order notes\n";
            
        } catch (Exception $e) {
            $log_message .= "ERROR: " . $e->getMessage() . "\n";
        }
        
        file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
        return $should_process;
    }

    /**
     * Handle Provider Review status
     */
    private function handle_provider_review($order) {
        // 1. First, check if already processed
        if ($order->get_meta('_provider_review_processed') === 'yes') {
            return;
        }

        $order_id = $order->get_id();
        $order_status = $order->get_status();
        $payment_method = $order->get_payment_method();
        
        // 2. Check payment method and status
        if ($payment_method !== 'stripe') {
            $order->add_order_note('Provider Review: Unsupported payment method - ' . $payment_method);
            $order->update_meta_data('_provider_review_processed', 'yes');
            $order->save();
            return;
        }

        // 3. Handle cancelled orders
        if ($order_status === 'cancelled') {
            $order->add_order_note(
                'Provider Review: Order is cancelled. ' .
                'Please process the $50 charge manually through Stripe.'
            );
            $order->update_meta_data('_provider_review_processed', 'yes');
            $order->save();
            return;
        }
        
        // 3.1 Handle on-hold orders - capture payment first
        if ($order_status === 'on-hold') {
            try {
                // Capture the payment
                $order->payment_complete();
                $order->update_status('processing', 'Payment captured automatically for Provider Review processing');
                $order->add_order_note('Payment captured and order status updated to Processing for Provider Review');
            } catch (Exception $e) {
                $order->add_order_note('Failed to capture payment automatically: ' . $e->getMessage());
                $order->update_meta_data('_provider_review_processed', 'yes');
                $order->save();
                return;
            }
        }

        try {
            // 4. Get the original order total
            $original_total = $order->get_total();
            
            // 5. Calculate the refund amount (original total - $50 we want to keep)
            $refund_amount = $original_total - 50.00;
            
            // 6. Process the partial refund (this will keep $50 in the system)
            $refund = wc_create_refund([
                'amount'         => $refund_amount,
                'reason'         => 'Provider Review - Partial Refund',
                'order_id'       => $order_id,
                'refund_payment' => true
            ]);
            
            if (is_wp_error($refund)) {
                $error_msg = $refund->get_error_message();
                $manual_note = "Provider Review: Failed to process automatic refund.\n";
                $manual_note .= "• Order Status: " . $order_status . "\n";
                $manual_note .= "• Payment Method: " . $payment_method . "\n";
                $manual_note .= "• Error: " . $error_msg . "\n\n";
                $manual_note .= "ACTION REQUIRED: Please process manually through Stripe:\n";
                $manual_note .= "1. Capture the payment (if not already captured)\n";
                $manual_note .= "2. Issue a partial refund of $" . number_format($refund_amount, 2) . "\n";
                $manual_note .= "3. Leave a note here when complete";
                
                $order->add_order_note($manual_note, true);
                
                // Also send an email to admin
                $admin_email = get_option('admin_email');
                wp_mail(
                    $admin_email,
                    'Manual Refund Required for Order #' . $order_id,
                    $manual_note
                );
                
                throw new Exception('Refund failed: ' . $error_msg);
            }
            
            // 7. Add detailed order note
            $order->add_order_note(
                "PROVIDER REVIEW PROCESSED\n" .
                "• Processed partial refund: $" . number_format($refund_amount, 2) . "\n" .
                "• Net amount kept: $50.00"
            );
            
            // 8. Set order status to completed
            if ($order_status !== 'completed') {
                $order->update_status('completed', 'Provider Review processing completed');
            }
            
        } catch (Exception $e) {
            // Log the error
            error_log('Provider Review Error - Order #' . $order_id . ': ' . $e->getMessage());
            
            // Add error note to order
            if (isset($order) && is_a($order, 'WC_Order')) {
                $order->add_order_note('ERROR processing Provider Review: ' . $e->getMessage());
            }
            
            // Re-throw to be handled by the caller
            throw $e;
            
        } finally {
            // 9. Always mark as processed, even if there was an error
            $order->update_meta_data('_provider_review_processed', 'yes');
            $order->save();
        }
    }
}

/**
 * Instantiate the module after WooCommerce is loaded.
 */
add_action( 'plugins_loaded', function() {
    new AH_Orders_Telegra_Workflow();
} );

}