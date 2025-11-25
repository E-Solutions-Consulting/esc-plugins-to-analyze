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
        $this->referral_manager = new FriendBuy_Referral_Manager();
        $this->coupon_manager = new FriendBuy_Coupon_Manager();
        
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('rest_api_init', array($this, 'register_webhook_endpoints'));

        add_action('show_user_profile', [$this, 'show_referral_balance_in_profile_user']);
        add_action('edit_user_profile', [$this, 'show_referral_balance_in_profile_user']);

        add_action('personal_options_update', [$this, 'save_referral_balance_in_profile_user']);
        add_action('edit_user_profile_update', [$this, 'save_referral_balance_in_profile_user']);

        add_action('woocommerce_subscription_renewal_payment_complete', array($this, 'handle_subscription_renewal'));

        /* ------------------------ Checkbox en Checkout ------------------------- */

        add_action('woocommerce_review_order_before_payment', [$this, 'display_referral_discount_checkbox']);
        add_action('woocommerce_checkout_update_order_review', [$this, 'process_referral_discount_checkbox']);
        /* ---------------------- Aplicar saldo en checkout ---------------------- */
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_referral_discount']);

        add_action('init', [$this, 'add_menu_my_account'], 10, 0);
        add_filter('woocommerce_account_menu_items', [$this, 'add_item_menu_my_account']);

        add_action('woocommerce_account_friendbuy-rewards_endpoint', [$this, 'rewards_endpoint_content']);

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
        $body = json_decode($request->get_body(), true);
        if (empty($body['type']) || $body['type'] !== 'advocateReward') {
            return new WP_REST_Response(['error' => 'Invalid webhook type'], 400);
        }

        $data = $body['data'][0];
        $email = sanitize_email($data['emailAddress']);
        $reward_amount = floatval($data['rewardAmount']);

        $user = get_user_by('email', $email);
        if (!$user) {
            return new WP_REST_Response(['error' => 'User not found'], 404);
        }

        $user_id = $user->ID;
        $current_balance = floatval(get_user_meta($user_id, 'referral_balance', true));
        $new_balance = min($current_balance + $reward_amount, 250);

        update_user_meta($user_id, 'referral_balance', $new_balance);

        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}referral_rewards", [
            'user_id'        => $user_id,
            'reward_id'      => sanitize_text_field($data['rewardId']),
            'amount'         => $reward_amount,
            'created_on'     => current_time('mysql'),
            'source'         => 'friendbuy',
            'used'           => 0,
            'friend_email'   => sanitize_email($data['friends'][0]['friendEmailAddress']),
            'friend_id'      => sanitize_text_field($data['friends'][0]['friendCustomerId']),
        ]);

        return new WP_REST_Response(['success' => true, 'balance' => $new_balance], 200);
    }

    function show_referral_balance_in_profile_user($user) {
        $balance = get_user_meta($user->ID, 'referral_balance', true) ?: 0;
        echo '<h3>Referral Balance (Friendbuy)</h3>';
        echo '<table class="form-table"><tr>';
        echo '<th><label for="referral_balance">Saldo actual</label></th>';
        echo '<td><input type="number" step="0.01" name="referral_balance" value="' . esc_attr($balance) . '" />';
        echo '<p class="description">Total rewards earned. Max. $250 USD.</p></td>';
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

        // Registrar descuento como nota y reducir saldo
        $subscription->add_order_note("Descuento por referidos aplicado: $" . number_format($discount, 2));
        update_user_meta($user_id, 'referral_balance', $balance - $discount);
    }

    function display_referral_discount_checkbox() {
        $balance = get_user_meta(get_current_user_id(), 'referral_balance', true);
        if ($balance > 0) {
            echo '<div class="referral-discount-checkbox" style="margin:10px 0;">';
            echo '<label><input type="checkbox" name="use_referral_balance" value="1" /> ';
            echo 'Use referral balance ($' . number_format($balance, 2) . ' available)</label>';
            echo '</div>';
        }
    }

    function process_referral_discount_checkbox($posted_data) {
        parse_str($posted_data, $output);
        if (isset($output['use_referral_balance']) && $output['use_referral_balance'] == 1) {
            WC()->session->set('use_referral_balance', true);
        } else {
            WC()->session->__unset('use_referral_balance');
        }
    }

    function apply_referral_discount($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (!WC()->session->get('use_referral_balance')) return;

        $user_id = get_current_user_id();
        if (!$user_id) return;

        $balance = floatval(get_user_meta($user_id, 'referral_balance', true));
        if ($balance <= 0) return;

        $discount = min($balance, $cart->get_total('edit'));
        if ($discount > 0) {
            $cart->add_fee(__('Referral Discount', 'friendbuy'), -$discount);
            update_user_meta($user_id, 'referral_balance', $balance - $discount);
        }
    }

    function add_menu_my_account() {
        add_rewrite_endpoint('friendbuy-rewards', EP_ROOT | EP_PAGES);
    }
    function add_item_menu_my_account($items) {
        $items['friendbuy-rewards'] = __('Rewards', 'friendbuy');
        return $items;
    }
    function rewards_endpoint_content() {
        global $wpdb;
        $user_id = get_current_user_id();
        $table = "{$wpdb->prefix}referral_rewards";
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d ORDER BY created_on DESC", $user_id));

        echo '<h3>Rewards History</h3>';
        if (!$rows) {
            echo '<p>You have no registered rewards yet.</p>';
            return;
        }

        echo '<table class="shop_table shop_table_responsive my_account_orders">';
        echo '<thead><tr><th>Date</th><th>Amount</th><th>Friend Email</th><th>Used</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td>' . esc_html($r->created_on) . '</td>';
            echo '<td>$' . number_format($r->amount, 2) . '</td>';
            echo '<td>' . esc_html($r->friend_email) . '</td>';
            echo '<td>' . ($r->used ? 'Sí' : 'No') . '</td></tr>';
        }
        echo '</tbody></table>';
    }

}