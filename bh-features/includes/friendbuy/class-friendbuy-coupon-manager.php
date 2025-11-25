<?php
// includes/class-friendbuy-coupon-manager.php

if (!defined('ABSPATH')) {
    exit;
}

class FriendBuy_Coupon_Manager {

    /**
     * Create credit coupon for existing user
     */
    public function create_credit_coupon($user, $amount) {
        $user_id    =   $user->ID;
        $coupon_code=   $this->generate_coupon_code('CREDIT', $user_id);
        
        $existing_coupon = get_user_meta($user_id, '_friendbuy_active_coupon', true);        
        if ($existing_coupon && $this->coupon_exists($existing_coupon)) {
            $this->update_coupon_amount($existing_coupon, $amount);
            return $existing_coupon;
        } else {
            return $this->create_new_coupon(array(
                'code'      =>  $coupon_code,
                'amount'    =>  $amount,
                'type'      =>  'fixed_cart',
                'email_restriction' => $user->user_email,
                'usage_limit'   =>  1,
                'individual_use'=>  true,
                'description'   =>  'Referral Credit - FriendBuy',
                'expiry_days'   =>  90
            ));
        }
    }

    /**
     * Create a one-time coupon for a new user
     */
    public function create_single_use_coupon($email, $amount) {
        $coupon_code = $this->generate_coupon_code('REFERAWARD');
        
        return $this->create_new_coupon(array(
            'code'  =>  $coupon_code,
            'amount'=>  $amount,
            'type'  =>  'fixed_cart',
            'email_restriction' => $email,
            'usage_limit'   =>  1,
            'individual_use'=>  true,
            'description'   =>  'Referral Reward - FriendBuy',
            'expiry_days'   =>  90
        ));
    }

    /**
     * Create a new coupon in WooCommerce
     */
    private function create_new_coupon($args) {
        $defaults = array(
            'description' => 'FriendBuy Reward',
            'expiry_days' => 90
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $coupon_data = array(
            'post_title' => $args['code'],
            'post_content' => '',
            'post_status' => 'publish',
            'post_author' => 1,
            'post_type' => 'shop_coupon',
            'post_excerpt' => $args['description']
        );
        
        $coupon_id = wp_insert_post($coupon_data);
        
        if (is_wp_error($coupon_id)) {
            throw new Exception('Error creating coupon: ' . $coupon_id->get_error_message());
        }
        
        update_post_meta($coupon_id, 'discount_type', $args['type']);
        update_post_meta($coupon_id, 'coupon_amount', $args['amount']);
        update_post_meta($coupon_id, 'individual_use', $args['individual_use'] ? 'yes' : 'no');
        update_post_meta($coupon_id, 'usage_limit', $args['usage_limit']);
        update_post_meta($coupon_id, 'usage_limit_per_user', $args['usage_limit']);
        
        if (!empty($args['email_restriction'])) {
            update_post_meta($coupon_id, 'customer_email', array($args['email_restriction']));
        }
        
        if ($args['expiry_days'] > 0) {
            update_post_meta($coupon_id, 'date_expires', strtotime("+{$args['expiry_days']} days"));
        }
        
        do_action('friendbuy_after_coupon_created', $coupon_id, $args);
        
        return $args['code'];
    }

    /**
     * Generate unique coupon code
     */
    private function generate_coupon_code($prefix, $user_id = '') {
        $suffix = $user_id ? $user_id . '_' . time() : strtoupper(wp_generate_password(6, false));
        return $prefix . '_' . $suffix;
    }

    /**
     * Check if a coupon exists
     */
    private function coupon_exists($coupon_code) {
        $coupon = new WC_Coupon($coupon_code);
        return $coupon->get_id() > 0;
    }
}