<?php
// includes/class-friendbuy-webhook-handler.php

if (!defined('ABSPATH')) {
    exit;
}

class FriendBuy_Webhook_Handler {

    private $webhook_secret;
    private $referral_manager;
    private $coupon_manager;

    private $log_file=  'bh_plugins-friendbuy_webhooks';
    private $log_error_file=  'bh_plugin_errors-friendbuy_webhooks';

    public function __construct() {
        $this->webhook_secret = get_option('friendbuy_webhook_secret', '06d164cd-d790-41eb-b75f-ae5a54074a7c');        
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('rest_api_init', array($this, 'register_webhook_endpoints'));

        add_action('show_user_profile', [$this, 'show_referral_balance_in_profile_user']);
        add_action('edit_user_profile', [$this, 'show_referral_balance_in_profile_user']);

        add_action('personal_options_update', [$this, 'save_referral_balance_in_profile_user']);
        add_action('edit_user_profile_update', [$this, 'save_referral_balance_in_profile_user']);

        add_action('woocommerce_subscription_renewal_payment_complete', array($this, 'handle_subscription_renewal'));

        add_action('woocommerce_review_order_before_payment', [$this, 'woocommerce_review_order_before_payment_fn']);

        /* --------------------- AJAX to save selection --------------------- */
        add_action('wp_ajax_update_referral_usage', [$this, 'friendbuy_update_referral_usage']);
        add_action('wp_ajax_nopriv_update_referral_usage', [$this, 'friendbuy_update_referral_usage']);

        /* ---------------------- Apply balance at checkout ---------------------- */
        add_action('woocommerce_cart_calculate_fees', [$this, 'woocommerce_cart_calculate_fees_fn']);

        /* ------------------ Deduct balance upon order completion ------------------ */
        add_action('woocommerce_checkout_order_processed', [$this, 'woocommerce_checkout_order_processed_fn']);


        add_action('woocommerce_checkout_update_order_review', [$this, 'sync_referral_usage_from_checkout'], 10, 1);

        //add_action('init', [$this, 'add_menu_my_account'], 10, 0);
        //add_filter('woocommerce_account_menu_items', [$this, 'add_item_menu_my_account']);

        //add_action('woocommerce_account_friendbuy-rewards_endpoint', [$this, 'rewards_endpoint_content']);

        add_filter(
            'wcs_renewal_order_created',
            [$this, 'apply_referral_discount_to_renewal'],
            20,
            2
        );

    }

    /**
     * Registrar endpoints REST para webhooks
     */
    public function register_webhook_endpoints() {
        register_rest_route('friendbuy/v1', '/advocate-reward', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_advocate_reward_webhook'),
            'permission_callback' => '__return_true'
        ));
    }

    function handle_advocate_reward_webhook(WP_REST_Request $request) {
        global $wpdb;
        $headers = $request->get_headers();
        $payload = json_decode($request->get_body(), true);
        //bh_plugins_log(['FriendBuy Webhook Received', $headers, $payload], $this->log_file);

        $logger  = wc_get_logger();
        $context = [ 'source' => 'ah-friendbuy-webhook' ];
        $logger->debug('FriendBuy Webhook Received: ' . $request->get_body(), $context);

        if (empty($payload['type']) || $payload['type'] !== 'advocateReward') {
            $logger->error('Invalid webhook type', $context);
            return new WP_REST_Response(['error' => 'Invalid webhook type'], 400);
        }

        $data = $payload['data'][0];
        $email = sanitize_email($data['emailAddress'] ?? '');
        //$customer_id = isset($data['customerId']) ? intval($data['customerId']) : 0;
        $reward_amount = 25; // Fixed $25 per referral

        // Debug log
        //error_log("Looking for user - Email: $email, Customer ID: $customer_id");
        $msg_logger =   "Looking for user by Email: {$email} ";
        //$logger->info("Looking for user by Email: {$email}", $context);

        // Try to find user by ID first, then by email
        $user = null;
        // if ($customer_id > 0) {
        //     $user = get_user_by('ID', $customer_id);
        //     if ($user) {
        //         error_log("Found user by ID: $customer_id");
        //     }
        // }
        
        if (!empty($email)) {
            $user = get_user_by('email', $email);
            if ($user) {
                $logger->info($msg_logger . "-> Found", $context);
                //error_log("Found user by email: $email");
            }
        }

        if (!$user) {
            $logger->error($msg_logger . " -> Not found", $context);
            //error_log("User not found - Email: $email, Customer ID: $customer_id");
            return new WP_REST_Response(['error' => 'User not found', 'email' => $email, 'customerId' => $customer_id], 404);
        }

        $user_id = $user->ID;

        // 1. Check 10-referral limit
        $referral_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$wpdb->prefix}referral_rewards 
            WHERE user_id = %d 
            AND (expires_at IS NULL OR expires_at > %s)",
            $user_id,
            current_time('mysql')
        ));
        
        if ($referral_count >= 10) {
            $logger->error("Maximum 10 active referrals allowed - referral_count: {$referral_count}", $context);
            return new WP_REST_Response([
                'error' => 'Maximum 10 active referrals allowed',
                'current_referrals' => $referral_count
            ], 400);
        }

        // 2. Check $250 cap
        $current_balance = $this->calculate_referral_balance($user_id);
        if (($current_balance + $reward_amount) > 250) {
            $reward_amount = 250 - $current_balance;
            if ($reward_amount <= 0) {
                $logger->error("Balance limit of $250 reached - current_balance: {$current_balance}", $context);
                return new WP_REST_Response([
                    'error' => 'Balance limit of $250 reached',
                    'current_balance' => $current_balance
                ], 400);
            }
        }

        // Calculate new balance
        $new_balance = $current_balance + $reward_amount;

        // Debug log
        //error_log("Updating balance - User ID: $user_id, Current: $current_balance, Added: $reward_amount, New: $new_balance");
        $logger->info("Updating balance - User ID: {$user_id}, Current: {$current_balance}, Added: {$reward_amount}, New: {$new_balance}", $context );

        // We don't need to update user_meta anymore as we calculate it on the fly
        // The actual balance is now calculated from the referral_rewards table
        //update_user_meta($user_id, 'referral_balance', $new_balance);

        // Insert the reward
        $wpdb->insert("{$wpdb->prefix}referral_rewards", [
            'user_id'        => $user_id,
            'reward_id'      => sanitize_text_field($data['rewardId'] ?? ''),
            'amount'         => $reward_amount,
            'created_on'     => current_time('mysql'),
            'source'         => 'friendbuy',
            'used'           => 0,
            'coupon_code'    => $data['couponCode'] ?? '',
            'friend_email'   => sanitize_email($data['friends'][0]['friendEmailAddress'] ?? ''),
            'friend_id'      => sanitize_text_field($data['friends'][0]['friendCustomerId'] ?? ''),
            'status'         => 'active',
            'expires_at'     => date('Y-m-d H:i:s', strtotime('+1 year'))
        ]);

        return new WP_REST_Response([
            'success' => true, 
            'balance' => $this->calculate_referral_balance($user_id),
            'user_id' => $user_id,
            'referral_count' => $referral_count + 1
        ], 200);
    }

    function show_referral_balance_in_profile_user($user) {
        $balance = $this->calculate_referral_balance($user->ID);
        echo '<h3>Referral Balance (Friendbuy)</h3>';
        echo '<table class="form-table"><tr>';
        echo '<th><label for="referral_balance">Current balance</label></th>';
        echo '<td><strong>$' . number_format($balance, 2) . '</strong>';
        echo '<p class="description">Total available rewards. Max. $250 USD.</p></td>';
        echo '</tr></table>';
    }

    function save_referral_balance_in_profile_user($user_id) {
        if (current_user_can('edit_user', $user_id)) {
            $balance = min(floatval($_POST['referral_balance']), 250);
            update_user_meta($user_id, 'referral_balance', $balance);
        }
    }

    function handle_subscription_renewal($subscription) {
        $user_id = $subscription->get_user_id();
        $balance = floatval(get_user_meta($user_id, 'referral_balance', true));

        if ($balance <= 0) return;


        $total = $subscription->get_total();
        $discount = min($balance, $total);

        bh_plugins_log(['handle_subscription_renewal', ['user_id'=>$user_id, 'balance'=>$balance, 'total'=>$total, '$discount'=>$discount]], $this->log_file);

        $subscription->add_order_note("Referral discount applied: $" . number_format($discount, 2));
        update_user_meta($user_id, 'referral_balance', $balance - $discount);
    }

    /**
     * 
     * */
    function woocommerce_review_order_before_payment_fn() {
        $user_id = get_current_user_id();
        if (!$user_id) return;
        
        $balance = $this->calculate_referral_balance($user_id);
        if ($balance <= 0) return;

        /*

        $checked = WC()->session->get('use_referral_balance') ? 'checked' : '';
        echo '<div class="referral-discount-checkbox" style="margin:10px 0;padding:10px;background:#f8f8f8;border:1px solid #ddd;border-radius:4px;">';
        echo '<label><input type="checkbox" id="use_referral_balance" name="use_referral_balance" value="1" ' . $checked . ' /> ';
        echo 'Use my referral balance ($' . number_format($balance, 2) . ' available)</label>';
        echo '</div>';

        echo "<script>
            jQuery(function($){
                $(document).on('change', '#use_referral_balance', function(){
                    // Trigger WooCommerce to refresh totals (and re-run calculate_fees)
                    $('body').trigger('update_checkout');
                });
            });
            </script>";
        */

    }
    
    function friendbuy_update_referral_usage() {
        check_ajax_referer('friendbuy_nonce', 'security');
        //$use = isset($_POST['use_referral_balance']) && intval($_POST['use_referral_balance']) === 1;
        $use = true;
        if ($use) {
            WC()->session->set('use_referral_balance', true);
        } else {
            WC()->session->__unset('use_referral_balance');
        }
        wp_send_json_success();
    }

    function woocommerce_cart_calculate_fees_fn($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;    

        //$use_balance = WC()->session->get('use_referral_balance');
        $use_balance = true;
        if (!$use_balance) {
            WC()->session->__unset('referral_discount_amount');
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) return;

        $balance = $this->calculate_referral_balance($user_id);
        if ($balance <= 0) return;

        $discount = min($balance, $cart->get_subtotal());
        if ($discount > 0) {
            $cart->add_fee(__('Referral Discount', 'friendbuy'), -$discount);
            WC()->session->set('referral_discount_amount', $discount);
        }
    }
    
    private function calculate_referral_balance($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'referral_rewards';
        
        // Get unused and partially used rewards
        $balance = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(
                CASE 
                    WHEN status = 'partially_used' THEN (amount - COALESCE(used_amount, 0))
                    WHEN used = 0 AND (expires_at IS NULL OR expires_at > %s) THEN amount
                    ELSE 0
                END
            ), 0)
            FROM $table_name 
            WHERE user_id = %d 
            AND (used = 0 OR status = 'partially_used')
            AND (expires_at IS NULL OR expires_at > %s)",
            current_time('mysql'),
            $user_id,
            current_time('mysql')
        ));
        
        return max(0, floatval($balance));
    }
    
    function woocommerce_checkout_order_processed_fn($order_id) {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
        if (!$user_id) return;

        $use_balance = WC()->session->get('use_referral_balance');
        $discount_amount = floatval(WC()->session->get('referral_discount_amount', 0));

        if ($use_balance && $discount_amount > 0) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'referral_rewards';
            
            // Get all unused, unexpired referrals ordered by oldest first
            $referrals = $wpdb->get_results($wpdb->prepare(
                "SELECT id, amount 
                FROM $table_name 
                WHERE user_id = %d 
                AND used = 0 
                AND (expires_at IS NULL OR expires_at > %s)
                ORDER BY created_on ASC", 
                $user_id,
                current_time('mysql')
            ));

            $remaining_discount = $discount_amount;
            $used_referrals = [];

            foreach ($referrals as $referral) {
                if ($remaining_discount <= 0) break;

                $amount_to_use = min($referral->amount, $remaining_discount);
                $used_referrals[] = [
                    'id' => $referral->id,
                    'amount_used' => $amount_to_use,
                    'original_amount' => $referral->amount
                ];

                $remaining_discount -= $amount_to_use;
            }

            // Update the database
            foreach ($used_referrals as $used) {
                if ($used['amount_used'] >= $used['original_amount']) {
                    // Mark as fully used
                    $wpdb->update(
                        $table_name,
                        [
                            'used' => 1,
                            'used_amount' => $used['amount_used'],
                            'status' => 'used',
                            'order_id' => $order_id
                        ],
                        ['id' => $used['id']]
                    );
                } else {
                    // Mark as partially used
                    $wpdb->update(
                        $table_name,
                        [
                            'used_amount' => $used['amount_used'],
                            'status' => 'partially_used',
                            'order_id' => $order_id
                        ],
                        ['id' => $used['id']]
                    );
                }
            }

            // Add order note
            $order_note = "Used referral rewards:\n";
            foreach ($used_referrals as $ref) {
                $order_note .= sprintf(
                    "- Reward #%d: $%.2f of $%.2f\n", 
                    $ref['id'], 
                    $ref['amount_used'], 
                    $ref['original_amount']
                );
            }
            $order->add_order_note($order_note);

            // Clear session
            WC()->session->__unset('use_referral_balance');
            WC()->session->__unset('referral_discount_amount');
        }
    }

    /**
     * Sync checkbox value into WC session using WooCommerce update_order_review payload.
     *
     * @param string $posted_data Query-string from checkout.
     */
    public function sync_referral_usage_from_checkout( $posted_data ) {

        if ( empty( $posted_data ) ) {
            return;
        }

        parse_str( $posted_data, $data );

        //$use = ! empty( $data['use_referral_balance'] ) && $data['use_referral_balance'] === '1';
        $use = true;

        if ( $use ) {
            WC()->session->set( 'use_referral_balance', true );
        } else {
            WC()->session->__unset( 'use_referral_balance' );
            WC()->session->__unset( 'referral_discount_amount' );
        }
    }

    public function apply_referral_discount_to_renewal__($renewal_order, $subscription) {

        $logger  = wc_get_logger();
        $context = ['source' => 'ah-referral-renewal'];

        $logger->info('Hook triggered: renewal_order_created', $context);

        if (!$renewal_order instanceof WC_Order) {
            $logger->error('Invalid renewal order object.', $context);
            return;
        }

        $order_id = $renewal_order->get_id();
        $user_id  = $renewal_order->get_user_id();

        $logger->info("Processing order {$order_id} for user {$user_id}", $context);

        if (!$user_id) {
            $logger->error("Order {$order_id} has no user.", $context);
            return;
        }

        if ($renewal_order->get_meta('_referral_discount_applied')) {
            $logger->info("Discount already applied to order {$order_id}", $context);
            return;
        }

        $balance = $this->calculate_referral_balance($user_id);
        $order_total = (float) $renewal_order->get_total();

        $logger->info(
            "Order {$order_id} - Balance: {$balance} | Order total before discount: {$order_total}",
            $context
        );

        if ($balance <= 0) {
            $logger->info("No balance available for user {$user_id}", $context);
            return;
        }

        if ($order_total <= 0) {
            $logger->info("Order {$order_id} total already zero.", $context);
            return;
        }

        $discount = min($balance, $order_total);

        $logger->info(
            "Calculated discount for order {$order_id}: {$discount}",
            $context
        );

        try {

            $fee = new WC_Order_Item_Fee();
            $fee->set_name('Advocate Rewards Credit');
            $fee->set_amount(-$discount);
            $fee->set_total(-$discount);
            $fee->set_tax_status('none');

            $renewal_order->add_item($fee);

            $renewal_order->calculate_totals(false);

            $new_total = $renewal_order->get_total();

            $renewal_order->update_meta_data('_referral_discount_applied', $discount);
            $renewal_order->save();

            // update_user_meta($user_id, 'referral_balance', $balance - $discount);

            $logger->info(
                "Discount applied successfully to order {$order_id}. New total: {$new_total}. Remaining balance: " . ($balance - $discount),
                $context
            );

            $renewal_order->add_order_note(
                "Referral credit applied: $" . number_format($discount, 2)
            );

        } catch (Exception $e) {

            $logger->error(
                "Error applying discount to order {$order_id}: " . $e->getMessage(),
                $context
            );
        }

        return $renewal_order;
    }

    public function apply_referral_discount_to_renewal($renewal_order, $subscription) {

        global $wpdb;

        $logger  = wc_get_logger();
        $context = ['source' => 'ah-referral-renewal'];

        $logger->info('Hook triggered: renewal_order_created', $context);

        if (!$renewal_order instanceof WC_Order) {
            $logger->error('Invalid renewal order object.', $context);
            return $renewal_order;
        }

        $order_id = $renewal_order->get_id();
        $user_id  = $renewal_order->get_user_id();

        $logger->info("Processing order {$order_id} for user {$user_id}", $context);

        if (!$user_id) {
            return $renewal_order;
        }

        if ($renewal_order->get_meta('_referral_discount_applied')) {
            $logger->info("Discount already applied to order {$order_id}", $context);
            return $renewal_order;
        }

        $balance     = $this->calculate_referral_balance($user_id);
        $order_total = (float) $renewal_order->get_total();

        $logger->info(
            "Order {$order_id} - Balance: {$balance} | Order total before discount: {$order_total}",
            $context
        );

        if ($balance <= 0 || $order_total <= 0) {
            return $renewal_order;
        }

        $discount = min($balance, $order_total);

        $logger->info("Calculated discount: {$discount}", $context);

        $table_name = $wpdb->prefix . 'referral_rewards';

        // Get FIFO rewards
        // $referrals = $wpdb->get_results($wpdb->prepare(
        //     "SELECT id, amount, used_amount
        //      FROM $table_name
        //      WHERE user_id = %d
        //      AND (status = 'unused' OR status = 'partially_used')
        //      AND (expires_at IS NULL OR expires_at > %s)
        //      ORDER BY created_on ASC",
        //     $user_id,
        //     current_time('mysql')
        // ));
        // $referrals = $wpdb->get_results($wpdb->prepare(
        //         "SELECT id, amount 
        //         FROM $table_name 
        //         WHERE user_id = %d 
        //         AND used = 0 
        //         AND (expires_at IS NULL OR expires_at > %s)
        //         ORDER BY created_on ASC", 
        //         $user_id,
        //         current_time('mysql')
        //     ));

        // $remaining = $discount;

        // foreach ($referrals as $ref) {

        //     if ($remaining <= 0) break;

        //     $already_used = (float) $ref->used_amount;
        //     $available    = (float) $ref->amount - $already_used;

        //     if ($available <= 0) continue;

        //     $use = min($available, $remaining);
        //     $new_used = $already_used + $use;

        //     $status = ($new_used >= $ref->amount)
        //         ? 'used'
        //         : 'partially_used';

        //     $wpdb->update(
        //         $table_name,
        //         [
        //             'used_amount' => $new_used,
        //             'status'      => $status,
        //             'order_id'    => $order_id
        //         ],
        //         ['id' => $ref->id]
        //     );

        //     $logger->info(
        //         "Reward {$ref->id} consumed: {$use} | New used_amount: {$new_used}",
        //         $context
        //     );

        //     $remaining -= $use;
        // }

        // Get all unused, unexpired referrals ordered by oldest first
            $referrals = $wpdb->get_results($wpdb->prepare(
                "SELECT id, amount 
                FROM $table_name 
                WHERE user_id = %d 
                AND used = 0 
                AND (expires_at IS NULL OR expires_at > %s)
                ORDER BY created_on ASC", 
                $user_id,
                current_time('mysql')
            ));

            $remaining_discount = $discount;
            $used_referrals = [];

            foreach ($referrals as $referral) {
                if ($remaining_discount <= 0) break;

                $amount_to_use = min($referral->amount, $remaining_discount);
                $used_referrals[] = [
                    'id' => $referral->id,
                    'amount_used' => $amount_to_use,
                    'original_amount' => $referral->amount
                ];

                $remaining_discount -= $amount_to_use;
            }

            // Update the database
            foreach ($used_referrals as $used) {
                if ($used['amount_used'] >= $used['original_amount']) {
                    // Mark as fully used
                    $wpdb->update(
                        $table_name,
                        [
                            'used' => 1,
                            'used_amount' => $used['amount_used'],
                            'status' => 'used',
                            'order_id' => $order_id
                        ],
                        ['id' => $used['id']]
                    );
                } else {
                    // Mark as partially used
                    $wpdb->update(
                        $table_name,
                        [
                            'used_amount' => $used['amount_used'],
                            'status' => 'partially_used',
                            'order_id' => $order_id
                        ],
                        ['id' => $used['id']]
                    );
                }
            }

            // Add order note
            $order_note = "Used referral rewards:\n";
            foreach ($used_referrals as $ref) {
                $order_note .= sprintf(
                    "- Reward #%d: $%.2f of $%.2f\n", 
                    $ref['id'], 
                    $ref['amount_used'], 
                    $ref['original_amount']
                );
            }
            $renewal_order->add_order_note($order_note);

        try {

            $fee = new WC_Order_Item_Fee();
            $fee->set_name('Advocate Rewards Credit');
            $fee->set_amount(-$discount);
            $fee->set_total(-$discount);
            $fee->set_tax_status('none');

            $renewal_order->add_item($fee);
            $renewal_order->calculate_totals(false);

            $renewal_order->update_meta_data('_referral_discount_applied', $discount);
            $renewal_order->save();

            $logger->info(
                "Discount applied. New total: " . $renewal_order->get_total(),
                $context
            );

            $renewal_order->add_order_note(
                "Referral credit applied: $" . number_format($discount, 2)
            );

        } catch (Exception $e) {

            $logger->error(
                "Error applying discount: " . $e->getMessage(),
                $context
            );
        }

        return $renewal_order;
    }





}