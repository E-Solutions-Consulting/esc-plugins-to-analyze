<?php
/**
 * LOB Subscription Events
 *
 * Hooks WooCommerce Subscriptions status changes and, for statuses enabled in
 * the LOB settings, sends a direct-mail piece (postcard/letter) through LOB.
 *
 * Design (mirrors the Attentive module):
 *  - The WC hook only ENQUEUES an Action Scheduler job and returns; the LOB
 *    HTTP call runs in the background worker (group `bh-lob`).
 *  - Deduplication lives in the external DB (bh_external.bh_lob_events) via
 *    BH_LOB_Events_Log — never in postmeta / wc_orders_meta.
 *
 * @package    BH_Features
 * @subpackage Integrations/Lob
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_LOB_Subscription_Events {

    const AS_HOOK  = 'bh_lob_process_subscription_status';
    const AS_GROUP = 'bh-lob';

    public function __construct() {
        // WC Subscriptions: fired whenever a subscription's status changes.
        add_action( 'woocommerce_subscription_status_updated', [ $this, 'enqueue_status_change' ], 20, 3 );

        // Background worker.
        add_action( self::AS_HOOK, [ $this, 'worker_process_status' ], 10, 1 );
    }

    /**
     * Enqueue-only handler. Runs in the request that changed the status.
     *
     * @param WC_Subscription $subscription
     * @param string          $new_status  Without the wc- prefix.
     * @param string          $old_status
     */
    public function enqueue_status_change( $subscription, $new_status, $old_status ) {

        if ( ! BH_LOB_Config::is_active() ) {
            return;
        }

        if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_id' ) ) {
            return;
        }

        // Only enqueue when this status is actually configured to mail —
        // avoids scheduling jobs that would no-op.
        if ( null === BH_LOB_Config::get_status_mailing( $new_status ) ) {
            return;
        }

        if ( ! function_exists( 'as_enqueue_async_action' ) ) {
            return;
        }

        as_enqueue_async_action(
            self::AS_HOOK,
            [ [
                'subscription_id' => $subscription->get_id(),
                'new_status'      => $new_status,
                'old_status'      => $old_status,
            ] ],
            self::AS_GROUP
        );

        BH_LOB_Logger::log( 'Enqueued subscription status mailing', [
            'subscription_id' => $subscription->get_id(),
            'new_status'      => $new_status,
        ] );
    }

    /**
     * Background worker — performs the LOB call.
     *
     * @param array $args [ subscription_id, new_status, old_status ]
     */
    public function worker_process_status( $args ) {

        $subscription_id = (int) ( $args['subscription_id'] ?? 0 );
        $new_status      = (string) ( $args['new_status'] ?? '' );

        if ( ! $subscription_id || '' === $new_status ) {
            return;
        }

        if ( ! BH_LOB_Config::is_active() ) {
            return;
        }

        // Re-read config in the worker (settings may have changed since enqueue).
        $mailing = BH_LOB_Config::get_status_mailing( $new_status );
        if ( null === $mailing ) {
            return;
        }

        // Deduplication: one mailing per (subscription, status).
        if ( BH_LOB_Events_Log::sub_was_triggered( $subscription_id, $new_status ) ) {
            BH_LOB_Logger::log( 'Subscription status already mailed — skipping', [
                'subscription_id' => $subscription_id,
                'status'          => $new_status,
            ] );
            return;
        }

        if ( ! function_exists( 'wcs_get_subscription' ) ) {
            return;
        }

        $subscription = wcs_get_subscription( $subscription_id );
        if ( ! $subscription ) {
            return;
        }

        $to = $this->build_to_address( $subscription );
        if ( null === $to ) {
            BH_LOB_Logger::error( 'No usable US address — skipping mailing', [
                'subscription_id' => $subscription_id,
                'status'          => $new_status,
            ] );
            BH_LOB_Events_Log::sub_mark_triggered( $subscription_id, $new_status, 'skipped', null, [ 'reason' => 'no_address' ] );
            return;
        }

        $from     = $this->build_from_address();
        $settings = BH_LOB_Config::get_settings();

        $merge_variables = [
            'first_name'      => $subscription->get_shipping_first_name() ?: $subscription->get_billing_first_name(),
            'last_name'       => $subscription->get_shipping_last_name() ?: $subscription->get_billing_last_name(),
            'subscription_id' => (string) $subscription_id,
            'status'          => $new_status,
        ];

        $metadata = [
            'subscription_id' => (string) $subscription_id,
            'status'          => $new_status,
            'source'          => 'bh-features',
        ];

        $client = new BH_LOB_API_Client();

        $use_type = $settings['use_type'] ?? 'marketing';

        if ( 'letter' === $settings['mail_type'] ) {
            $response = $client->create_letter( $to, $from, [
                'file'            => $mailing['file'] ?: $mailing['front'],
                'color'           => true,
                'use_type'        => $use_type,
                'description'     => 'Brello subscription ' . $new_status,
                'merge_variables' => $merge_variables,
                'metadata'        => $metadata,
            ] );
        } else {
            $response = $client->create_postcard( $to, $from, [
                'front'           => $mailing['front'],
                'back'            => $mailing['back'] ?: $mailing['front'],
                'size'            => $mailing['size'],
                'use_type'        => $use_type,
                'description'     => 'Brello subscription ' . $new_status,
                'merge_variables' => $merge_variables,
                'metadata'        => $metadata,
            ] );
        }

        if ( is_wp_error( $response ) ) {
            BH_LOB_Events_Log::sub_mark_triggered( $subscription_id, $new_status, 'failed', null, [
                'error' => $response->get_error_message(),
            ] );
            BH_LOB_Logger::error( 'LOB mailing failed', [
                'subscription_id' => $subscription_id,
                'status'          => $new_status,
                'error'           => $response->get_error_message(),
            ] );
            return;
        }

        $lob_id = $response['id'] ?? null;

        BH_LOB_Events_Log::sub_mark_triggered( $subscription_id, $new_status, 'sent', $lob_id, [
            'expected_delivery' => $response['expected_delivery_date'] ?? null,
        ] );

        // Store a quick-reference flag in the external DB (not postmeta).
        if ( class_exists( 'AH_Order_Meta' ) ) {
            AH_Order_Meta::set( $subscription_id, '_bh_lob_last_mailed_status', $new_status );
            if ( $lob_id ) {
                AH_Order_Meta::set( $subscription_id, '_bh_lob_last_mail_id', $lob_id );
            }
        }

        BH_LOB_Logger::log( 'LOB mailing sent', [
            'subscription_id' => $subscription_id,
            'status'          => $new_status,
            'lob_id'          => $lob_id,
        ] );
    }

    /**
     * Build the recipient (to) address from the subscription, preferring the
     * shipping address and falling back to billing. Returns null when there is
     * no usable US street address.
     *
     * @param WC_Subscription $subscription
     * @return array|null  LOB inline address object.
     */
    private function build_to_address( $subscription ) {

        $prefix = $subscription->get_shipping_address_1() ? 'shipping' : 'billing';

        $getter = function ( $field ) use ( $subscription, $prefix ) {
            $method = "get_{$prefix}_{$field}";
            return method_exists( $subscription, $method ) ? (string) $subscription->{$method}() : '';
        };

        $line1   = $getter( 'address_1' );
        $country = strtoupper( $getter( 'country' ) ?: 'US' );

        if ( '' === $line1 || 'US' !== $country ) {
            return null;
        }

        $name = trim( $getter( 'first_name' ) . ' ' . $getter( 'last_name' ) );

        return array_filter( [
            'name'            => $name !== '' ? $name : 'Customer',
            'address_line1'   => $line1,
            'address_line2'   => $getter( 'address_2' ),
            'address_city'    => $getter( 'city' ),
            'address_state'   => strtoupper( $getter( 'state' ) ),
            'address_zip'     => $getter( 'postcode' ),
            'address_country' => 'US',
        ], static function ( $v ) {
            return $v !== '';
        } );
    }

    /**
     * Build the sender (from) address from settings.
     *
     * @return array LOB inline address object.
     */
    private function build_from_address() {
        $s = BH_LOB_Config::get_settings();

        return array_filter( [
            'name'            => $s['from_name'],
            'company'         => $s['from_company'],
            'address_line1'   => $s['from_line1'],
            'address_line2'   => $s['from_line2'],
            'address_city'    => $s['from_city'],
            'address_state'   => $s['from_state'],
            'address_zip'     => $s['from_zip'],
            'address_country' => 'US',
        ], static function ( $v ) {
            return $v !== '' && $v !== null;
        } );
    }
}
