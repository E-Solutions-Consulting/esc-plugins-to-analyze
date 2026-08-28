<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Subscription_State_Reactivator {

    private $emailer;
    private $logger;
    private $source = 'ah-state-reactivation';

    public function __construct( AH_Subscription_State_Email $emailer ) {
        $this->emailer = $emailer;
        $this->logger  = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;
    }

    /**
     * Process a subscription by ID.
     *
     * Return array:
     * - status: reactivated|skipped|error
     * - reason: string
     * - state: string
     * - email_sent: 0|1
     *
     * @param int $subscription_id
     * @return array
     */
    public function process_subscription( $subscription_id ) {

        $subscription_id = intval( $subscription_id );

        $subscription = $this->get_subscription( $subscription_id );
        if ( ! $subscription ) {
            return $this->result( 'skipped', 'subscription_not_found' );
        }

        // Guard: must be on-hold right now
        if ( $subscription->get_status() !== 'on-hold' ) {
            return $this->result( 'skipped', 'not_on_hold' );
        }

        // Guard: do not touch if already marked processed
        if ( $subscription->get_meta( '_reactivated_after_state_change', true ) ) {
            return $this->result( 'skipped', 'already_processed' );
        }

        // Detect blocked state from meta keys (_cancelled_state_{xx} = 1)
        $state = $this->detect_blocked_state( $subscription );
        if ( empty( $state ) ) {
            return $this->result( 'skipped', 'no_blocked_state_meta' );
        }

        // Validate state is available (strict)
        if ( ! $this->is_state_available( $state ) ) {
            $this->log( 'info', 'State not available; skipping.', [
                'subscription_id' => $subscription_id,
                'state' => $state,
            ] );
            return $this->result( 'skipped', 'state_not_available', $state );
        }

        // $subscription->set_billing_email('jaime+' . $state . '-reactivated@solutionswebonline.com');


        // Email BEFORE reactivating
        $email_sent = $this->emailer->send_reactivated_email( $subscription, $state );
        if ( ! $email_sent ) {
            $this->log( 'error', 'Email failed; will not reactivate subscription.', [
                'subscription_id' => $subscription_id,
                'state' => $state,
            ] );
            return $this->result( 'error', 'email_failed', $state, 0 );
        }

        // Reactivate
        // $subscription->update_status( 'active', sprintf( 'State re-enabled: %s', $state ) );

        $subscription->set_status( 'active' );
        $subscription->add_order_note( sprintf( 'State re-enabled: %s', $state ) );
        // $subscription->save();

        // Mark processed
        $subscription->update_meta_data( '_reactivated_after_state_change', 1 );
        $subscription->update_meta_data( '_reactivated_state', $state );
        $subscription->update_meta_data( '_reactivated_at', time() );
        $subscription->update_meta_data( '_reactivation_email_sent_at', time() );

        $subscription->save();

        $this->log( 'info', 'Subscription reactivated after state change.', [
            'subscription_id' => $subscription_id,
            'state' => $state,
            'email_sent' => 1,
        ] );

        return $this->result( 'reactivated', 'ok', $state, 1 );
    }

    /**
     * Detect exactly one blocked state meta.
     *
     * @param WC_Subscription $subscription
     * @return string|null
     */
    private function detect_blocked_state( WC_Subscription $subscription ) {

        $metas = $subscription->get_meta_data();
        $hits  = [];

        foreach ( $metas as $meta_obj ) {
            $key = $meta_obj->key;
            $val = $meta_obj->value;

            if ( strpos( $key, '_cancelled_state_' ) !== 0 ) {
                continue;
            }

            // Accept '1', 1, true
            $truthy = (string) $val === '1' || $val === 1 || $val === true;

            if ( $truthy ) {
                $hits[] = $key;
            }
        }

        if ( count( $hits ) === 0 ) {
            return null;
        }

        if ( count( $hits ) > 1 ) {
            $this->log( 'warning', 'Multiple cancelled_state metas found; skipping for safety.', [
                'subscription_id' => $subscription->get_id(),
                'meta_keys' => $hits,
            ] );
            return null;
        }

        $key = $hits[0];
        $state = strtoupper( str_replace( '_cancelled_state_', '', $key ) );

        // Basic validation: two letters
        if ( ! preg_match( '/^[A-Z]{2}$/', $state ) ) {
            $this->log( 'warning', 'Invalid state code extracted from meta key.', [
                'subscription_id' => $subscription->get_id(),
                'meta_key' => $key,
                'state' => $state,
            ] );
            return null;
        }

        return $state;
    }

    /**
     * Strictly checks if a state is currently "available".
     *
     * Uses AH_States::get_config()['states_statuses'][$state] === 'available'
     * (based on your existing pattern).
     *
     * @param string $state
     * @return bool
     */
    private function is_state_available( $state ) {

        
        if ( ! class_exists( 'AH_Licensed_States_Manager' ) || ! method_exists( 'AH_Licensed_States_Manager', 'is_state_available' ) ) {
            // Fail closed: do not reactivate if we cannot verify availability.
            $this->log( 'error', 'AH_Licensed_States_Manager::is_state_available not available; failing closed.', [
                'state' => $state,
            ] );
            return false;
        }

        return AH_Licensed_States_Manager::is_state_available( $state );

        // if ( ! class_exists( 'AH_States' ) || ! method_exists( 'AH_States', 'get_config' ) ) {
        //     // Fail closed: do not reactivate if we cannot verify availability.
        //     $this->log( 'error', 'AH_States::get_config not available; failing closed.', [
        //         'state' => $state,
        //     ] );
        //     return false;
        // }

        // $config = AH_States::get_config();

        // if ( empty( $config['states_statuses'] ) || ! is_array( $config['states_statuses'] ) ) {
        //     $this->log( 'error', 'states_statuses missing from config; failing closed.', [
        //         'state' => $state,
        //     ] );
        //     return false;
        // }

        // $current = isset( $config['states_statuses'][ $state ] ) ? $config['states_statuses'][ $state ] : null;

        // return $current === 'available';
    }

    /**
     * Get subscription object safely.
     *
     * @param int $subscription_id
     * @return WC_Subscription|null
     */
    private function get_subscription( $subscription_id ) {

        if ( function_exists( 'wcs_get_subscription' ) ) {
            $s = wcs_get_subscription( $subscription_id );
            return ( $s instanceof WC_Subscription ) ? $s : null;
        }

        // Fallback: try wc_get_order (may work but not guaranteed for subscriptions).
        $o = wc_get_order( $subscription_id );
        return ( $o instanceof WC_Subscription ) ? $o : null;
    }

    private function result( $status, $reason, $state = '', $email_sent = 0 ) {
        return [
            'status' => $status,
            'reason' => $reason,
            'state'  => $state,
            'email_sent' => intval( $email_sent ),
        ];
    }

    private function log( $level, $message, array $context = [] ) {
        if ( $this->logger ) {
            $this->logger->log( $level, $message, array_merge( [ 'source' => $this->source ], $context ) );
        }
    }
}
