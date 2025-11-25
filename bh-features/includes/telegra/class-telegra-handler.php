<?php
if (!defined('ABSPATH')) {
    exit;
}

class Telegra_Webhook_Handler {

    private $wc_status_send_to_telegra= 'wc-send_to_telegra';

    private $webhook_secret;
    private $referral_manager;
    private $coupon_manager;

    private $log_file=  'bh_plugins-telegra_webhooks';
    private $log_error_file=  'bh_plugin_errors-telegra_webhooks';

    public function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('init', array($this, 'register_custom_order_statuses'));
        add_filter('wc_order_statuses', [$this, 'add_custom_order_statuses']);
    }

	/**
	 * Register custom order statuses
	 */
	function register_custom_order_statuses() {
	    register_post_status('wc-cancelled_cus_req', array(
			'label'                     => 'Cancelled – Customer Request',
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop('Cancelled – Customer Request (%s)', 'Cancelled – Customer Request (%s)'),
		));

		register_post_status('wc-cancelled_auth_exp', array(
			'label'                     => 'Cancelled – Authorization Expired',
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop('Cancelled – Authorization Expired (%s)', 'Cancelled – Authorization Expired (%s)'),
		));

		register_post_status('wc-cancelled_pat_rej', array(
			'label'                     => 'Cancelled – Patient Rejected',
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop('Cancelled – Patient Rejected (%s)', 'Cancelled – Patient Rejected (%s)'),
		));
		/*
	    register_post_status('wc-send_to_telegra', [
	        'label'                     => 'Send to Telegra',
	        'public'                    => true,
	        'exclude_from_search'       => false,
	        'show_in_admin_all_list'    => true,
	        'show_in_admin_status_list' => true,
	        'label_count'               => _n_noop('Send to Telegra (%s)', 'Send to Telegra (%s)')
	    ]);
		*/
	}
	function add_custom_order_statuses($order_statuses) {
	    $new_statuses = array();
		foreach ($order_statuses as $key => $label) {
			$new_statuses[$key] = $label;
			if ('wc-cancelled' === $key) {
				$new_statuses['wc-cancel_cus_req']   =	'Cancelled – Customer Request';
				$new_statuses['wc-cancel_auth_exp'] 	=	'Cancelled – Authorization Expired';
				$new_statuses['wc-cancel_pat_rej']	=	'Cancelled – Patient Rejected';
			}
		}
		return $new_statuses;
	}

	function order_renewal_payment_completed_send_to_telegram( $order_id, $old_status, $new_status, $order ){
		try {
			if (!$order || $new_status!=='processing') {
				return;
			}
			
			if(wcs_order_contains_renewal($order)){
				$subscriptions	=	wcs_get_subscriptions_for_renewal_order($order_id);
				foreach ($subscriptions as $subscription) {
					if ($subscription->get_status() !== 'active') {
						continue;
					}
					$tmd_action = get_option('telemdnow_trigger_action');
					// $order->update_status($this->wc_status_send_to_telegra);
					$order->update_status($tmd_action);
                    //$order->add_order_note('Order renewal payment completed - Sending to Telegra', true);
				}
			}
		} catch (\Throwable $th) {
			return ;
		}
	}

}
new Telegra_Webhook_Handler();