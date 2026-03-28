<?php

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Bh_Features_Common {
    public $restricted_states  = ['CT', 'FL'];

    function are_orders_restricted() {
        if (get_option('enable_schedule_restriction') !== 'yes')
            return false;

        $active_days    =   (array) get_option('operating_days', ['friday']);
        $start_time     =   get_option('start_time', '17:00');
        $end_time       =   get_option('end_time', '06:00');
        
        $current_day    =   strtolower(current_time('l'));
        $current_time   =   current_time('H:i');

        $debug = [
            'current_day'   =>  $current_day,
            'current_time'  =>  $current_time,
            'active_days'   =>  $active_days,
            'start_time'    =>  $start_time,
            'end_time'      =>  $end_time
        ];

        if(in_array($current_day, $active_days)) {
            if($current_time >= $start_time) {
                return true;
            }
        }

        $next_day = strtolower(date('l', strtotime('tomorrow')));
        if(in_array($next_day, $active_days)){
            if($current_time < $end_time) {
                return true;
            }
        }
        return false;
    }
    function get_restriction_status() {
        if (get_option('enable_schedule_restriction', 'no') !== 'yes') {
            return ['active' => false, 'message' => ''];
        }

        $active_days    =   (array) get_option('operating_days', ['monday']);
        $start_time     =   get_option('start_time', '23:55');
        $end_time       =   get_option('end_time', '06:00');
        
        $current_day    =   strtolower(current_time('l'));
        $current_time   =   current_time('H:i');

        if (in_array($current_day, $active_days) && $current_time >= $start_time) {
            return [
                'active' => true,
                'message' => sprintf(
                    __('⚠️ The restriction to limit orders from the brellohealt.com/start page is activated until tomorrow at %s', 'your-textdomain'),
                    $end_time
                )
            ];
        }

        $next_day = strtolower(date('l', strtotime('tomorrow')));
        if (in_array($next_day, $active_days) && $current_time <= $end_time) {
            return [
                'active' => true,
                'message' => sprintf(
                    __('⚠️ The restriction to limit orders from the brellohealt.com/start page is activated until tomorrow at %s', 'your-textdomain'),
                    $end_time
                )
            ];
        }

        return ['active' => false, 'message' => ''];
    }
}
