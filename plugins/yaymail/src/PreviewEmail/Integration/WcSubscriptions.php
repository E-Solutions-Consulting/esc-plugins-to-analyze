<?php

namespace YayMail\PreviewEmail\Integration;

use YayMail\Utils\SingletonTrait;

/**
 *
 * @method static WcSubscriptions get_instance()
 */
class WcSubscriptions {
    use SingletonTrait;

    private function __construct() {
        add_filter( 'yaymail_preview_email_woo_additional_order_id', [ $this, 'check_email_subscription' ], 10, 3 );
        add_action( 'yaymail_preview_email_woo_additional_order_trigger', [ $this, 'trigger_email' ], 10, 3 );
    }

    public function trigger_email( $email, $additional_data, $order_id ) {
        if ( isset( $additional_data['error'] ) ) {
            return;
        }

        $email_class = get_class( $email );

        if ( $this->is_email_trigger_order( $email_class ) ) {
            $email->trigger( $order_id, wc_get_order( $order_id ) );
        } elseif ( $this->is_email_trigger_subscription( $email_class ) && function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            $order_subscriptions = wcs_get_subscriptions_for_order( $order_id );
            $subscription        = array_pop( $order_subscriptions );
            $email->trigger( $subscription );
        } else {
            $email->trigger( $order_id, wc_get_order( $order_id ) );
        }
    }

    public function check_email_subscription( $result, $email_class, $order_id ) {
        $error_text = sprintf( __( 'This is not a valid subscription order. Please select a valid subscription order ID. <br><br>Go to orders page to check %s.', 'yaymail' ), '<a target="_blank" href="' . admin_url( 'edit.php?post_type=shop_order' ) . '">Order ID</a>' );

        if ( $this->is_email_trigger_subscription( $email_class ) || $this->is_email_trigger_order( $email_class ) ) {
            if ( ! wcs_order_contains_subscription( $order_id ) ) {
                $result = [ 'error' => $error_text ];
                return $result;
            }
            return true;
        }

        return $result;
    }

    private function is_email_trigger_order( $class_email ) {
        $array = [
            'WCS_Email_Completed_Renewal_Order',
            'WCS_Email_Completed_Switch_Order',
            'WCS_Email_Customer_On_Hold_Renewal_Order',
            'WCS_Email_New_Renewal_Order',
            'WCS_Email_New_Switch_Order',
            'WCS_Email_Processing_Renewal_Order',
            'WCS_Email_Payment_Retry',
            'WCS_Email_Customer_Payment_Retry',
        ];
        return in_array( $class_email, $array, true );
    }

    private function is_email_trigger_subscription( $class_email ) {
        $array = [
            'WCS_Email_Cancelled_Subscription',
            'WCS_Email_Expired_Subscription',
            'WCS_Email_On_Hold_Subscription',
        ];
        return in_array( $class_email, $array, true );
    }
}
