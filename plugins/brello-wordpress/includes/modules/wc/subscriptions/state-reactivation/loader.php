<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * State Reactivation Module
 *
 * - Batch process subscriptions on-hold due to state restrictions.
 * - Send transactional email BEFORE reactivating.
 * - Mark processed to avoid loops.
 */

require_once __DIR__ . '/subscription-state-query.php';
require_once __DIR__ . '/subscription-state-email.php';
require_once __DIR__ . '/subscription-state-reactivator.php';

add_action( 'init', function () {

    if ( empty( $_GET['ah_test_state_reactivation_email'] ) ) {
        return;
    }

    // --- INPUTS ---
    $state  = isset( $_GET['state'] ) ? strtoupper( trim( $_GET['state'] ) ) : '';
    $limit  = isset( $_GET['limit'] ) ? max( 1, intval( $_GET['limit'] ) ) : 50;
    $offset = isset( $_GET['offset'] ) ? max( 0, intval( $_GET['offset'] ) ) : 0;

    // dry=1 => NO email, NO reactivación
    $dry_run = isset( $_GET['dry'] ) ? ( intval( $_GET['dry'] ) === 1 ) : true;

    if ( ! preg_match( '/^[A-Z]{2}$/', $state ) ) {
        _print( 'Missing or invalid --state param (example: ?state=FL)' );
        die;
    }

    $query       = new AH_Subscription_State_Query();
    $emailer     = new AH_Subscription_State_Email();
    $reactivator = new AH_Subscription_State_Reactivator( $emailer );

    // --- QUERY by STATE ---
    $ids = $query->get_candidate_ids_by_state( $state, $limit, $offset );

    _print(
        sprintf(
            'STATE TEST (%s) Found %d candidate subscriptions (limit=%d offset=%d dry=%s)',
            $state,
            count( $ids ),
            $limit,
            $offset,
            $dry_run ? 'yes' : 'no'
        )
    );

    $results = [
        'processed'   => 0,
        'reactivated' => 0,
        'skipped'     => 0,
        'errors'      => 0,
        'ids'         => [],
    ];

    foreach ( $ids as $subscription_id ) {

        $results['processed']++;
        $results['ids'][] = $subscription_id;

        if ( $dry_run ) {
            // DRY RUN — do not send email or reactivate
            _print( sprintf(
                '[DRY] #%d (%s) would be processed',
                $subscription_id,
                $state
            ) );

            continue;
        }

        // REAL RUN
        $r = $reactivator->process_subscription( $subscription_id );

        if ( $r['status'] === 'reactivated' ) {
            $results['reactivated']++;
        } elseif ( $r['status'] === 'skipped' ) {
            $results['skipped']++;
        } else {
            $results['errors']++;
        }

        _print( sprintf(
            '#%d %s => %s (%s)',
            $subscription_id,
            $r['state'],
            $r['status'],
            ! empty( $r['reason'] ) ? $r['reason'] : 'no-reason'
        ) );
    }

    _print( wp_json_encode( $results, JSON_PRETTY_PRINT ) );
    die('Done.');
});


add_action(
    'ah_state_status_changed',
    'ah_handle_state_becoming_available',
    10,
    3
);

function ah_handle_state_becoming_available( $state, $old_status, $new_status ) {

    if ( $new_status !== 'available' ) {
        return;
    }

    // Optional: only if coming from restricted/unavailable
    if ( in_array( $old_status, [ 'available', null ], true ) ) {
        return;
    }

    $logger = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;

    if ( $logger ) {
        $logger->info(
            'State became available; scheduling subscription reactivation batch.',
            [
                'source'     => 'ah-state-reactivation',
                'state'      => $state,
                'old_status' => $old_status,
                'new_status' => $new_status,
            ]
        );
    }

    $message    =   "```";
    $message   .=   sprintf(
        "⚠️ Notice: %s (%s) is Now Available!",
        AH_States::get_name( strtoupper( $state ) ),
        strtoupper( $state )
    );  
    $message .= "```";

    bh_send_slack_notification($message, BH_SLACK_CHANNEL_STATES_LIVE_UPDATE);

    /**
     * IMPORTANT:
     * We do NOT process subscriptions here.
     * We only schedule or mark the state as eligible.
     */

    do_action(
        'ah_state_available_for_reactivation',
        $state
    );
}

add_action(
    'ah_state_available_for_reactivation',
    function ( $state ) {

        if ( ! wp_next_scheduled( 'ah_run_state_reactivation', [ $state ] ) ) {
            wp_schedule_single_event(
                time() + 60,
                'ah_run_state_reactivation',
                [ $state ]
            );
        }
    }
);

// add_action(
//     'ah_run_state_reactivation',
//     function ( $state ) {

//         $lock_key = 'ah_state_reactivation_lock_' . strtolower( $state );

//         // ---- LOCK (avoid parallel runs)
//         if ( get_transient( $lock_key ) ) {
//             return;
//         }
//         set_transient( $lock_key, 1, 300 ); // 5 min safety lock

//         $query       = new AH_Subscription_State_Query();
//         $emailer     = new AH_Subscription_State_Email();
//         $reactivator = new AH_Subscription_State_Reactivator( $emailer );

//         // ---- Throttle config (SAFE for Gmail)
//         $batch_size  = 5;          // emails per run
//         $delay       = 90;         // seconds between runs
//         $offset_key  = 'ah_state_reactivation_offset_' . strtolower( $state );

//         $offset = (int) get_option( $offset_key, 0 );

//         $ids = $query->get_candidate_ids( $batch_size, $offset );

//         foreach ( $ids as $subscription_id ) {
//             $reactivator->process_subscription( $subscription_id );
//         }

//         if ( count( $ids ) === $batch_size ) {
//             // More pending → schedule next chunk
//             update_option( $offset_key, $offset + $batch_size, false );

//             wp_schedule_single_event(
//                 time() + $delay,
//                 'ah_run_state_reactivation',
//                 [ $state ]
//             );
//         } else {
//             // Done → cleanup
//             delete_option( $offset_key );
//         }

//         delete_transient( $lock_key );
//     }
// );

add_action(
    'ah_run_state_reactivation',
    function ( $state ) {

        $state = strtoupper( trim( $state ) );

        $query       = new AH_Subscription_State_Query();
        $emailer     = new AH_Subscription_State_Email();
        $reactivator = new AH_Subscription_State_Reactivator( $emailer );

        $batch_size  = 5;
        $delay       = 90;
        $offset_key  = 'ah_state_reactivation_offset_' . strtolower( $state );
        $offset      = (int) get_option( $offset_key, 0 );

        $ids = $query->get_candidate_ids_by_state( $state, $batch_size, $offset );

        foreach ( $ids as $subscription_id ) {
            $reactivator->process_subscription( $subscription_id );
        }

        if ( count( $ids ) === $batch_size ) {
            update_option( $offset_key, $offset + $batch_size, false );

            wp_schedule_single_event(
                time() + $delay,
                'ah_run_state_reactivation',
                [ $state ]
            );
        } else {
            delete_option( $offset_key );
        }
    }
);




