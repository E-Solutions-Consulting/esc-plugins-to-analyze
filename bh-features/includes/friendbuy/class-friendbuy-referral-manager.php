<?php
// includes/class-friendbuy-referral-manager.php

if (!defined('ABSPATH')) {
    exit;
}

class FriendBuy_Referral_Manager {

    private $coupon_manager;

    public function __construct() {
        $this->coupon_manager = new FriendBuy_Coupon_Manager();
    }

    /**
     * Grant reward to advocate
     */
    public function grant_advocate_reward($email, $name, $amount, $reward_id, $campaign_name) {
        $user = get_user_by('email', $email);

        if ($user) {
            $this->grant_reward_to_existing_user($user, $amount, $reward_id, $campaign_name);
        } else {
            $this->grant_reward_to_new_user($email, $name, $amount, $reward_id, $campaign_name);
        }
    }

    /**
     * Grant reward to existing user
     */
    private function grant_reward_to_existing_user($user, $amount, $reward_id, $campaign_name) {
        $user_id = $user->ID;
        
        $this->update_user_credit_balance($user_id, $amount, 'add');
        $this->add_reward_to_history($user_id, array(
            'type'      =>  'reward_granted',
            'amount'    =>  $amount,
            'reward_id' =>  $reward_id,
            'campaign'  =>  $campaign_name,
            'date'      =>  current_time('mysql'),
            'status'    =>  'active'
        ));
        
        $credit_balance = $this->get_user_credit_balance($user_id);
        $coupon_code = $this->coupon_manager->create_credit_coupon($user, $credit_balance);
        
        $this->send_reward_notification($user, $amount, $credit_balance, $campaign_name, $coupon_code);
    }

    /**
     * Give reward to unregistered user
     */
    private function grant_reward_to_new_user($email, $name, $amount, $reward_id, $campaign_name) {
        $coupon_code = $this->coupon_manager->create_single_use_coupon($email, $amount);
        $this->send_coupon_notification($email, $name, $coupon_code, $amount, $campaign_name);
        $this->add_coupon_to_registry($email, $coupon_code, $amount, $reward_id);
    }

    /**
     * Revoke reward
     */
    public function revoke_advocate_reward($email, $amount) {
        $user = get_user_by('email', $email);
        
        if ($user) {
            $this->update_user_credit_balance($user->ID, $amount, 'subtract');
            $this->add_reward_to_history($user->ID, array(
                'type' => 'reward_revoked',
                'amount' => $amount,
                'date' => current_time('mysql'),
                'status' => 'revoked'
            ));
        }
    }

    /**
     * Update user credit balance
     */
    private function update_user_credit_balance($user_id, $amount, $operation = 'add') {
        $current_balance = $this->get_user_credit_balance($user_id);
        
        if ($operation === 'add') {
            $new_balance = $current_balance + $amount;
        } else {
            $new_balance = max(0, $current_balance - $amount);
        }
        
        update_user_meta($user_id, '_friendbuy_credit_balance', $new_balance);
        
        return $new_balance;
    }

    /**
     * Get user credit balance
     */
    public function get_user_credit_balance($user_id) {
        return floatval(get_user_meta($user_id, '_friendbuy_credit_balance', true));
    }

    /**
     * Add to reward history
     */
    private function add_reward_to_history($user_id, $reward_data) {
        $history    =   get_user_meta($user_id, '_friendbuy_reward_history', true) ?: array();
        $history[]  =   $reward_data;
        update_user_meta($user_id, '_friendbuy_reward_history', $history);
    }

    /**
     * Send reward notification
     */
    private function send_reward_notification($user, $amount, $total_credit, $campaign_name, $coupon_code = '') {
        $subject = apply_filters(
            'friendbuy_reward_notification_subject', 
            "You've earned $${amount} for referring a friend!"
        );
        
        $message = $this->get_reward_notification_template(
            $user->display_name,
            $amount,
            $total_credit,
            $campaign_name,
            $coupon_code
        );
        
        wp_mail($user->user_email, $subject, $message);
        
        do_action('friendbuy_after_reward_notification', $user->ID, $amount, $campaign_name);
    }

    /**
     * Notification template
     */
    private function get_reward_notification_template($name, $amount, $total_credit, $campaign_name, $coupon_code = '') {
        $message = "
        Hello {$name},

        Congratulations! You've earned $${amount} because someone used your referral code.

        Campaign: {$campaign_name}
        Reward: $${amount}
        Total credit available: $${total_credit}
        ";

        if (!empty($coupon_code)) {
            $message .= "
        Your coupon code: {$coupon_code}
            ";
        }

        $message .= "
        Your credit has been added to your account and can be used on your next purchase or renewal.

        Thank you for recommending our products!
        ";

        return apply_filters('friendbuy_reward_notification_message', $message, $name, $amount, $total_credit, $campaign_name, $coupon_code);
    }
}