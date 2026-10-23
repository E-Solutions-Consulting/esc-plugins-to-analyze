<?php
/**
 * Renewal review emails
 *
 * Emails the customer when their subscription enters RENEWAL_PENDING_REVIEW
 * (see AH_Renewal_Intake_Review), and again halfway through the grace period
 * and one day before it ends. Reminders are derived from the grace period so
 * they stay consistent if it is changed, and they are skipped if the
 * subscription has left review by the time they fire.
 *
 * The email classes (subject, heading, body, sender) are editable under
 * WooCommerce > Settings > Emails. Delivery is through Resend (RESEND_APIKEY).
 *
 * @package bh-features
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Renewal_Review_Emails' ) ) {

class AH_Renewal_Review_Emails {

    const REMINDER_HOOK    = 'ah_renewal_review_reminder';
    const AS_GROUP         = 'renewal-intake-review';
    const META_DEADLINE    = '_ah_renewal_review_deadline';
    const META_SENT_PREFIX = '_ah_renewal_review_email_sent_';

    const EMAIL_CLASSES = [
        'start' => 'AH_Email_Renewal_Review_Started',
        'mid'   => 'AH_Email_Renewal_Review_Reminder_Mid',
        'final' => 'AH_Email_Renewal_Review_Reminder_Final',
    ];

    private $log_context = [ 'source' => 'ah-renewal-review-emails' ];

    public function __construct() {
        add_filter( 'woocommerce_email_classes', [ $this, 'register_emails' ] );
        add_action( 'ah_renewal_review_started', [ $this, 'on_review_started' ], 10, 3 );
        add_action( self::REMINDER_HOOK, [ $this, 'handle_reminder' ], 10, 3 );
        add_action( 'woocommerce_subscription_status_updated', [ $this, 'on_leaving_review' ], 10, 3 );
    }

    /**
     * Leaving review ends this review cycle: drop its pending reminders and
     * forget which notices were sent, so a later review for the same order
     * (e.g. CS reactivated while it was still stuck) notifies the customer again.
     *
     * @param WC_Subscription $subscription
     * @param string          $new_status
     * @param string          $old_status
     */
    public function on_leaving_review( $subscription, $new_status, $old_status ) {
        if ( RENEWAL_PENDING_REVIEW !== $old_status || ! $subscription instanceof WC_Subscription ) {
            return;
        }

        $stuck_order_id = (int) $subscription->get_meta( AH_Renewal_Intake_Review::META_TRIGGER_ORDER );

        if ( $stuck_order_id ) {
            foreach ( [ 'mid', 'final' ] as $type ) {
                as_unschedule_all_actions( self::REMINDER_HOOK, [ $subscription->get_id(), $stuck_order_id, $type ], self::AS_GROUP );
            }
        }

        foreach ( array_keys( self::EMAIL_CLASSES ) as $type ) {
            $subscription->delete_meta_data( self::META_SENT_PREFIX . $type );
        }
        $subscription->save_meta_data();
    }

    public function register_emails( $emails ) {
        require_once __DIR__ . '/class-ah-email-renewal-review.php';

        foreach ( self::EMAIL_CLASSES as $class ) {
            $emails[ $class ] = new $class();
        }

        return $emails;
    }

    /**
     * @param WC_Subscription $subscription
     * @param WC_Order        $stuck_order
     * @param int             $grace_days
     */
    public function on_review_started( $subscription, $stuck_order, $grace_days ) {
        $grace_days = (int) $grace_days;

        $subscription->update_meta_data( self::META_DEADLINE, time() + ( $grace_days * DAY_IN_SECONDS ) );
        $subscription->save_meta_data(); // Meta only — a full save() could revert dates from a stale object.

        $this->send_notice( $subscription, $stuck_order, 'start' );
        $this->schedule_reminders( $subscription->get_id(), $stuck_order->get_id(), $grace_days );
    }

    private function schedule_reminders( $subscription_id, $stuck_order_id, $grace_days ) {
        $final_day = $grace_days - 1;
        $mid_day   = (int) floor( $grace_days / 2 );

        $plan = [];

        if ( $final_day >= 1 ) {
            $plan['final'] = $final_day;
        }

        if ( $mid_day >= 1 && $mid_day < $final_day ) {
            $plan['mid'] = $mid_day;
        }

        foreach ( $plan as $type => $days ) {
            $args = [ $subscription_id, $stuck_order_id, $type ];

            if ( as_has_scheduled_action( self::REMINDER_HOOK, $args, self::AS_GROUP ) ) {
                continue;
            }

            as_schedule_single_action( time() + ( $days * DAY_IN_SECONDS ), self::REMINDER_HOOK, $args, self::AS_GROUP );
        }
    }

    /**
     * @param int    $subscription_id
     * @param int    $stuck_order_id
     * @param string $type 'mid' or 'final'
     */
    public function handle_reminder( $subscription_id, $stuck_order_id, $type ) {
        $subscription = wcs_get_subscription( $subscription_id );
        $stuck_order  = wc_get_order( $stuck_order_id );

        if ( ! $subscription instanceof WC_Subscription || ! $stuck_order ) {
            return;
        }

        if ( ! $subscription->has_status( RENEWAL_PENDING_REVIEW ) ) {
            $this->log( 'info', sprintf( '[handle_reminder] %s skipped — subscription_id=%d already left review (status=%s).', $type, $subscription_id, $subscription->get_status() ) );
            return;
        }

        if ( (int) $subscription->get_meta( AH_Renewal_Intake_Review::META_TRIGGER_ORDER ) !== (int) $stuck_order_id ) {
            $this->log( 'info', sprintf( '[handle_reminder] %s skipped — subscription_id=%d is in a different review than order #%d.', $type, $subscription_id, $stuck_order_id ) );
            return;
        }

        if ( $stuck_order->has_status( 'completed' ) ) {
            $this->log( 'info', sprintf( '[handle_reminder] %s skipped — order #%d already completed.', $type, $stuck_order_id ) );
            return;
        }

        if ( $stuck_order->has_status( AH_Renewal_Intake_Review::RESOLVED_STATUSES ) ) {
            // Cancelled/refunded while under review: CS is deciding with the
            // customer — "your order is still pending" would be wrong.
            $this->log( 'info', sprintf( '[handle_reminder] %s skipped — order #%d is %s, waiting on a CS decision.', $type, $stuck_order_id, $stuck_order->get_status() ) );
            return;
        }

        $this->send_notice( $subscription, $stuck_order, $type );
    }

    private function send_notice( $subscription, $stuck_order, $type ) {
        $sent_key = self::META_SENT_PREFIX . $type;

        if ( (int) $subscription->get_meta( $sent_key ) === $stuck_order->get_id() ) {
            return false;
        }

        $emails = WC()->mailer()->get_emails();
        $class  = self::EMAIL_CLASSES[ $type ];

        if ( empty( $emails[ $class ] ) ) {
            $this->log( 'error', sprintf( '[send_notice] email class %s is not registered.', $class ) );
            return false;
        }

        $sent = $emails[ $class ]->trigger(
            $subscription->get_id(),
            $stuck_order->get_id(),
            (int) $subscription->get_meta( self::META_DEADLINE )
        );

        if ( $sent ) {
            $subscription->update_meta_data( $sent_key, $stuck_order->get_id() );
            $subscription->save_meta_data();
            $subscription->add_order_note(
                sprintf( 'Renewal review email "%s" sent to %s.', $type, $subscription->get_billing_email() )
            );
        }

        return $sent;
    }

    private function log( $level, $message ) {
        wc_get_logger()->log( $level, $message, $this->log_context );
    }
}

add_action( 'woocommerce_loaded', function () {
    new AH_Renewal_Review_Emails();
} );

}
