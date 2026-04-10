<?php
/**
 * Attentive Questionnaire Status
 *
 * Tracks questionnaire completion state in Attentive via custom attributes.
 * Uses Action Scheduler for async processing — no blocking on status change.
 *
 * Flow:
 *   1. Order status changes (sync)  → enqueue async action only
 *   2. Action Scheduler (async)     → validate + send to Attentive
 *
 * Guards:
 *   - No telemdnow_entity_id → skip (order never reached Telegra)
 *   - Already triggered      → skip (deduplication via bh_attentive_events)
 *   - No phone/email         → skip
 *
 * Status mapping:
 *   send_to_telegra → pending
 *   waiting_room, prerequisites, error_review,
 *   admin_review, provider_review, collect_payment,
 *   fulfillment, completed                          → completed
 *   cancelled, failed, refunded, trash, cancel_*   → cancelled
 *   on-hold, pending                                → ignored
 *
 * @package    BH_Features
 * @subpackage Integrations/Attentive
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Questionnaire_Status {

    /**
     * Attentive attribute name.
     * Must match exactly what's configured in the Attentive dashboard.
     */
    const ATTENTIVE_ATTRIBUTE = 'questionnaire_status';

    /**
     * Attentive event type — triggers the Journey in Attentive.
     * Must match the event name configured in the Attentive Journey.
     */
    const ATTENTIVE_EVENT = 'Questionnaire_Incomplete';

    /**
     * Action Scheduler hook names.
     */
    const ACTION_ENTERED = 'bh_attentive_questionnaire_entered';
    const ACTION_LEFT    = 'bh_attentive_questionnaire_left';

    /**
     * Action Scheduler group — visible in WC > Status > Scheduled Actions.
     */
    const AS_GROUP = 'bh-attentive-questionnaire';

    /**
     * Statuses that mean the patient completed the questionnaire and moved forward.
     */
    const COMPLETED_STATUSES = [
        'waiting_room',
        'prerequisites',
        'error_review',   // questionnaire completed — failed post-questionnaire validation
        'admin_review',
        'provider_review',
        'collect_payment',
        'fulfillment',
        'completed',
    ];

    /**
     * Statuses that mean the order is dead.
     * Journey should stop but we don't mark as "completed".
     */
    const DEAD_STATUSES = [
        'cancelled',
        'failed',
        'refunded',
        'trash',
        CANCEL_CUSTOMER_REQUEST,
        CANCEL_AUTHORIZATION_EXPIRED,
        CANCEL_PATIENT_REJECTED,
    ];

    /**
     * Statuses to ignore — order may still return to send_to_telegra.
     */
    const IGNORE_STATUSES = [
        'on-hold',
        'pending',
    ];

    // =========================================================================
    // INIT
    // =========================================================================

    public function __construct() {

        // Sync hook — only enqueues, no heavy work.
        add_action(
            'woocommerce_order_status_changed',
            [ $this, 'handle_status_change' ],
            20,
            4
        );

        // Async workers — executed by Action Scheduler in background.
        add_action( self::ACTION_ENTERED, [ $this, 'process_entered' ] );
        add_action( self::ACTION_LEFT, [ $this, 'process_left' ], 10, 2 );
    }

    // =========================================================================
    // SYNC — only enqueue, return immediately
    // =========================================================================

    /**
     * Enqueue async action on order status change.
     * Executes in microseconds — no HTTP calls, no heavy DB work.
     *
     * @param int      $order_id
     * @param string   $old_status
     * @param string   $new_status
     * @param WC_Order $order
     */
    public function handle_status_change( int $order_id, string $old_status, string $new_status, $order ): void {

        // Entering send_to_telegra.
        if ( $new_status === SEND_TO_TELEGRA ) {
            $this->enqueue( self::ACTION_ENTERED, $order_id );
            return;
        }

        // Leaving send_to_telegra.
        if ( $old_status === SEND_TO_TELEGRA ) {

            // Skip ignored statuses early — no need to enqueue at all.
            if ( in_array( $new_status, self::IGNORE_STATUSES, true ) ) {
                $this->log( "Order {$order_id} moved to {$new_status} — ignored." );
                return;
            }

            $this->enqueue( self::ACTION_LEFT, $order_id, [ 'new_status' => $new_status ] );
        }
    }

    /**
     * Enqueue a single async action.
     * Checks for existing pending action to prevent stacking duplicates.
     *
     * @param string $action
     * @param int    $order_id
     * @param array  $extra_args
     */
    private function enqueue( string $action, int $order_id, array $extra_args = [] ): void {

        $args = array_merge( [ 'order_id' => $order_id ], $extra_args );

        // Prevent duplicate pending actions for the same order + action.
        $existing = as_get_scheduled_actions( [
            'hook'   => $action,
            'args'   => $args,
            'status' => \ActionScheduler_Store::STATUS_PENDING,
            'group'  => self::AS_GROUP,
        ], 'ids' );

        if ( ! empty( $existing ) ) {
            $this->log( "Action {$action} already pending for order {$order_id} — skipping duplicate." );
            return;
        }

        as_enqueue_async_action( $action, $args, self::AS_GROUP );

        $this->log( "Enqueued {$action} for order {$order_id}." );
    }

    // =========================================================================
    // ASYNC WORKERS — executed by Action Scheduler in background
    // =========================================================================

    /**
     * Process order that entered send_to_telegra.
     *
     * @param int $order_id
     */
    public function process_entered( int $order_id ): void {

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            $this->log( "process_entered: Order {$order_id} not found." );
            return;
        }

        // Guard: order must have reached Telegra.
        // If telemdnow_entity_id is empty, the order never made it to Telegra
        // due to a 502 or API error. Skipping — handled separately.
        $entity_id = $order->get_meta( 'telemdnow_entity_id', true );
        if ( empty( $entity_id ) ) {
            $this->log( "process_entered: Order {$order_id} has no telemdnow_entity_id — skipping." );
            return;
        }

        // Guard: only fire once per order lifetime.
        if ( BH_Attentive_Events_Log::order_was_triggered( $order_id, BH_Attentive_Events_Log::EVENT_QUESTIONNAIRE_PENDING ) ) {
            $this->log( "process_entered: Order {$order_id} already triggered — skipping." );
            return;
        }

        $phone = BH_Attentive_Helper::normalize_phone( $order->get_billing_phone() );
        $email = $order->get_billing_email();

        if ( empty( $phone ) && empty( $email ) ) {
            $this->log( "process_entered: Order {$order_id} — no phone or email." );
            return;
        }

        // Ensure user exists in Attentive before sending events.
        // BH_Attentive_Helper::subscribe_user( $phone, $email );
        BH_Attentive_Helper::subscribe_user_integration( $phone, $email );

        // 1. Send event → triggers the Attentive Journey.
        BH_Attentive_Helper::send_event(
            self::ATTENTIVE_EVENT,
            $phone,
            $email,
            [
                'order_id'     => (string) $order_id,
                'order_number' => $order->get_order_number(),
            ]
        );

        // 2. Set attribute → Attentive Journey checks this before each message.
        BH_Attentive_Helper::set_attributes( $phone, $email, [
            self::ATTENTIVE_ATTRIBUTE => 'pending',
        ]);

        // 3. Record in external DB → prevents future duplicate triggers.
        BH_Attentive_Events_Log::order_mark_triggered(
            $order_id,
            BH_Attentive_Events_Log::EVENT_QUESTIONNAIRE_PENDING,
            [
                'phone' => $phone,
                'email' => $email,
            ]
        );

        $this->log( "process_entered: Order {$order_id} — questionnaire_status set to pending." );
    }

    /**
     * Process order that left send_to_telegra.
     *
     * @param int    $order_id
     * @param string $new_status
     */
    public function process_left( int $order_id, string $new_status ): void {

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            $this->log( "process_left: Order {$order_id} not found." );
            return;
        }

        // Guard: if questionnaire was never triggered (no telemdnow_entity_id
        // at the time of entering), nothing to resolve in Attentive.
        if ( ! BH_Attentive_Events_Log::order_was_triggered( $order_id, BH_Attentive_Events_Log::EVENT_QUESTIONNAIRE_PENDING ) ) {
            $this->log( "process_left: Order {$order_id} was never triggered — nothing to resolve." );
            return;
        }

        // Guard: already resolved — prevent double processing.
        $event = BH_Attentive_Events_Log::order_get_event( $order_id, BH_Attentive_Events_Log::EVENT_QUESTIONNAIRE_PENDING );
        if ( $event && ! empty( $event['resolved_at'] ) ) {
            $this->log( "process_left: Order {$order_id} already resolved ({$event['resolved_reason']}) — skipping." );
            return;
        }

        $phone = BH_Attentive_Helper::normalize_phone( $order->get_billing_phone() );
        $email = $order->get_billing_email();

        if ( empty( $phone ) && empty( $email ) ) {
            $this->log( "process_left: Order {$order_id} — no phone or email." );
            return;
        }

        // Determine attribute value and resolution reason.
        if ( in_array( $new_status, self::COMPLETED_STATUSES, true ) ) {
            $attribute_value = 'completed';
            $resolved_reason = BH_Attentive_Events_Log::REASON_COMPLETED;

        } elseif ( in_array( $new_status, self::DEAD_STATUSES, true ) ) {
            $attribute_value = 'cancelled';
            $resolved_reason = BH_Attentive_Events_Log::REASON_CANCELLED;

        } else {
            // Unknown status — stop the Journey to avoid indefinite messaging.
            $attribute_value = 'completed';
            $resolved_reason = BH_Attentive_Events_Log::REASON_COMPLETED;
            $this->log( "process_left: Order {$order_id} — unknown status '{$new_status}', defaulting to completed." );
        }

        // Update attribute in Attentive → stops the Journey before next message.
        BH_Attentive_Helper::set_attributes( $phone, $email, [
            self::ATTENTIVE_ATTRIBUTE => $attribute_value,
        ]);

        // Mark as resolved in external DB.
        BH_Attentive_Events_Log::order_mark_resolved(
            $order_id,
            BH_Attentive_Events_Log::EVENT_QUESTIONNAIRE_PENDING,
            $resolved_reason
        );

        $this->log( "process_left: Order {$order_id} moved to {$new_status} — questionnaire_status set to {$attribute_value}." );
    }

    // =========================================================================
    // LOGGING
    // =========================================================================

    private function log( string $message ): void {
        // BH_Attentive_Helper::log( '[Questionnaire] ' . $message );
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->info(
                $message,
                [ 'source' => 'bh-attentive-questionnaire' ]
            );
        }
    }
}