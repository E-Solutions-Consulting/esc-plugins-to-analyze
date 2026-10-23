<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AH_Welcome_Kit {

    public function __construct() {
        add_action( 'woocommerce_order_status_completed', [ $this, 'handle_completed_order' ], 10, 1 );
        add_action( 'ah_welcome_kit_process_scheduled', [ $this, 'create_kit_order' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );
        add_filter( 'woocommerce_order_note_class', [ $this, 'add_note_class' ], 10, 2 );
    }

    /**
     * Tags Welcome Kit notes (parent order, subscription and kit order) so
     * they get their own style + gift icon in the order notes box. Matched by
     * content because every note this module writes starts with "Welcome Kit".
     *
     * @param string[] $classes
     * @param object   $note    As returned by wc_get_order_notes().
     */
    public function add_note_class( $classes, $note ) {
        $content = isset( $note->content ) ? trim( (string) $note->content ) : '';

        if (
            $content !== ''
            && stripos( $content, 'Welcome Kit' ) === 0
            && ! preg_match( '/^Welcome Kit order .* scheduled to be created on /i', $content )
        ) {
            $classes[] = 'ah-wk-note';
        }
        return $classes;
    }

    /**
     * Triggered when an order reaches "completed" status.
     *
     * A lock guards against duplicate processing, since
     * woocommerce_order_status_completed can fire more than once for the same
     * order. The persistent kit plan / _welcome_kit_created flag are the
     * definitive safety net for late re-fires.
     *
     * The matched gifts are split into groups by their effective status and
     * delay (each gift row can override both), and each group becomes its own
     * kit order: created right away when its delay is zero, or scheduled via
     * Action Scheduler otherwise. The plan is frozen on the order at this
     * point, so a delayed kit ships what was configured when the order
     * completed, even if the settings change in the meantime.
     */
    public function handle_completed_order( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $lock_key = 'ah_wk_lock_' . $order_id;
        if ( get_transient( $lock_key ) ) return;
        set_transient( $lock_key, 1, 30 );

        try {
            $eligibility = new AH_Welcome_Kit_Eligibility( $order );
            if ( ! $eligibility->passes() ) return;

            $plan = $this->build_plan( $eligibility );
            if ( empty( $plan ) ) return;

            $order->update_meta_data( '_welcome_kit_plan', $plan );
            $order->save();

            // Same $order instance throughout, so each step reads the plan
            // as the previous step left it (a fresh wc_get_order() copy
            // could save a stale plan over it).
            foreach ( $plan as $group_key => $group ) {
                if ( $group['delay'] > 0 ) {
                    $this->schedule_kit_order( $order, (string) $group_key, $group['delay'] );
                } else {
                    $this->create_group_kit_order( $order, (string) $group_key );
                }
            }
        } finally {
            delete_transient( $lock_key );
        }
    }

    /**
     * Kit plan for an order: one entry per kit order to create, keyed by
     * group key, with the kit order ID filled in once it's created.
     *
     * @return array<string,array{status:string,delay:int,gifts:int[],kit_order_id:int}>
     */
    private function build_plan( AH_Welcome_Kit_Eligibility $eligibility ): array {
        $plan = [];
        foreach ( $eligibility->resolve_kit_groups() as $group_key => $group ) {
            $group['kit_order_id'] = 0;
            $plan[ $group_key ]    = $group;
        }
        return $plan;
    }

    /**
     * Schedules a deferred kit order creation via WP-Cron.
     *
     * Guards against scheduling the same order twice (e.g. a duplicate
     * "completed" fire before the lock transient exists on a slow request).
     */
    private function schedule_kit_order__staging( WC_Order $order, int $delay ): void {
        $order_id = $order->get_id();
        if ( wp_next_scheduled( 'ah_welcome_kit_process_scheduled', [ $order_id ] ) ) return;

        $timestamp = time() + $delay;
        wp_schedule_single_event( $timestamp, 'ah_welcome_kit_process_scheduled', [ $order_id ] );

        $order->update_meta_data( '_welcome_kit_scheduled_for', $timestamp );
        $order->add_order_note(
            sprintf( 'Welcome Kit order scheduled to be created on %s.', date_i18n( 'Y-m-d H:i', $timestamp ) ),
            false,
            false
        );
        $order->save();
    }

    /**
     * Schedules a deferred kit order creation via Action Scheduler.
     *
     * Uses Action Scheduler (not WP-Cron) so the scheduled action is written to
     * its own row, avoiding the serialized `cron` option that failed to persist
     * on the order-completion request in production. Guards against scheduling
     * the same order/group twice, and only writes the note/plan if scheduling
     * actually succeeded, so the order note can no longer report a schedule
     * that was lost.
     */
    private function schedule_kit_order( WC_Order $order, string $group_key, int $delay ): void {
        $order_id = $order->get_id();
        $logger   = wc_get_logger();
        $args     = [ $order_id, $group_key ];

        // Idempotency guard: bail if a pending action already exists for this order/group.
        $existing = as_get_scheduled_actions( [
            'hook'   => 'ah_welcome_kit_process_scheduled',
            'args'   => $args,
            'status' => \ActionScheduler_Store::STATUS_PENDING,
        ], 'ids' );

        if ( ! empty( $existing ) ) {
            $logger->info(
                sprintf( 'Schedule skipped for order %d, group %s — pending action already exists (action_id %d).', $order_id, $group_key, (int) reset( $existing ) ),
                [ 'source' => 'welcome-kit' ]
            );
            return;
        }

        $timestamp = time() + $delay;

        $action_id = as_schedule_single_action(
            $timestamp,
            'ah_welcome_kit_process_scheduled',
            $args,
            'welcome-kit'
        );

        // as_schedule_single_action() returns the action ID (int > 0) on success,
        // or 0 on failure. Do not write the note/plan unless scheduling succeeded,
        // otherwise the order note would claim a schedule that does not exist.
        if ( ! $action_id ) {
            $logger->error(
                sprintf( 'Failed to schedule kit order for order %d, group %s.', $order_id, $group_key ),
                [ 'source' => 'welcome-kit' ]
            );
            return;
        }

        $plan = (array) $order->get_meta( '_welcome_kit_plan' );
        $plan[ $group_key ]['scheduled_for'] = $timestamp;
        $plan[ $group_key ]['action_id']     = $action_id;
        $order->update_meta_data( '_welcome_kit_plan', $plan );
        $order->update_meta_data( '_welcome_kit_scheduled_for', $timestamp );
        $order->add_order_note(
            sprintf(
                'Welcome Kit order (%s) scheduled to be created on %s with status "%s".',
                self::gift_names( $plan[ $group_key ]['gifts'] ?? [] ),
                date_i18n( 'Y-m-d H:i', $timestamp ),
                self::status_label( $plan[ $group_key ]['status'] ?? '' )
            ),
            false,
            false
        );
        $order->save();

        $logger->info(
            sprintf(
                'Kit order scheduled for order %d, group %s — action_id %d, runs at %s GMT (delay %ds).',
                $order_id,
                $group_key,
                $action_id,
                gmdate( 'Y-m-d H:i:s', $timestamp ),
                $delay
            ),
            [ 'source' => 'welcome-kit' ]
        );
    }

    /**
     * Creates the welcome kit order for a given order ID.
     *
     * Called either immediately (no delay configured) or as the WP-Cron
     * callback for a previously scheduled order.
     */
    public function create_kit_order__staging( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        if ( $order->get_meta( '_welcome_kit_created' ) ) return;

        $kit_product_id = get_option( 'ah_welcome_kit_product_id' );
        $kit_product    = wc_get_product( $kit_product_id );
        if ( ! $kit_product ) return;

        $factory   = new AH_Welcome_Kit_Order_Factory( $order, $kit_product );
        $kit_order = $factory->create();

        if ( is_wp_error( $kit_order ) ) {
            wc_get_logger()->error(
                "AH_Welcome_Kit: failed to create kit order for order {$order_id}: " . $kit_order->get_error_message(),
                [ 'source' => 'welcome-kit' ]
            );
            return;
        }

        $order->update_meta_data( '_welcome_kit_created', true );
        $order->update_meta_data( '_welcome_kit_order_id', $kit_order->get_id() );
        $order->add_order_note(
            "Welcome Kit order #{$kit_order->get_id()} created automatically.",
            false,
            false
        );
        $order->save();

        $subscriptions = wcs_get_subscriptions_for_order( $order, [ 'order_type' => 'parent' ] );
        foreach ( $subscriptions as $subscription ) {
            $subscription->add_order_note(
                "Welcome Kit order #{$kit_order->get_id()} created automatically from order #{$order->get_id()}.",
                false,
                false
            );
        }

        do_action( 'ah_welcome_kit_created', $kit_order, $order );
    }

    /**
     * Creates one welcome kit order (one group of the order's kit plan).
     *
     * Called either immediately (group with no delay) or as the Action
     * Scheduler callback for a previously scheduled group. An empty group key
     * comes from an action scheduled by the previous version (args were just
     * [ order_id ]): in that case every pending group is created now, since
     * that action already waited out its delay.
     */
    public function create_kit_order( int $order_id, string $group_key = '' ): void {
        $logger = wc_get_logger();

        $logger->info(
            sprintf( 'Processing kit order creation for order %d%s.', $order_id, $group_key !== '' ? ", group {$group_key}" : '' ),
            [ 'source' => 'welcome-kit' ]
        );

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            $logger->error(
                sprintf( 'Processing aborted — order %d not found.', $order_id ),
                [ 'source' => 'welcome-kit' ]
            );
            return;
        }

        // Re-checked here (not just at scheduling time) because a delayed
        // kit can sit in Action Scheduler for days/weeks — if Welcome Kit
        // gets disabled in the meantime, the job must not still go through.
        if ( get_option( 'ah_welcome_kit_enabled' ) !== 'yes' ) {
            $logger->info(
                sprintf( 'Processing skipped for order %d — Welcome Kit is disabled.', $order_id ),
                [ 'source' => 'welcome-kit' ]
            );
            return;
        }

        if ( $order->get_meta( '_welcome_kit_created' ) ) {
            $logger->info(
                sprintf( 'Processing skipped for order %d — kit already created (kit order %s).', $order_id, $order->get_meta( '_welcome_kit_order_id' ) ?: 'n/a' ),
                [ 'source' => 'welcome-kit' ]
            );
            return;
        }

        $plan = $order->get_meta( '_welcome_kit_plan' );
        if ( ! is_array( $plan ) || empty( $plan ) ) {
            $plan = $this->build_plan( new AH_Welcome_Kit_Eligibility( $order ) );
            if ( empty( $plan ) ) {
                $logger->error(
                    sprintf( 'Processing aborted for order %d — no gift product resolved.', $order_id ),
                    [ 'source' => 'welcome-kit' ]
                );
                return;
            }
            $order->update_meta_data( '_welcome_kit_plan', $plan );
            $order->save();
        }

        if ( $group_key === '' ) {
            foreach ( array_keys( $plan ) as $key ) {
                $this->create_group_kit_order( $order, (string) $key );
            }
            return;
        }

        if ( ! isset( $plan[ $group_key ] ) ) {
            $logger->error(
                sprintf( 'Processing aborted for order %d — group %s not found in kit plan.', $order_id, $group_key ),
                [ 'source' => 'welcome-kit' ]
            );
            return;
        }

        $this->create_group_kit_order( $order, $group_key );
    }

    /**
     * Creates the kit order for one plan group, records it on the plan, and
     * sets the _welcome_kit_created flag once every group has its kit order.
     */
    private function create_group_kit_order( WC_Order $order, string $group_key ): void {
        $logger   = wc_get_logger();
        $order_id = $order->get_id();

        $plan  = (array) $order->get_meta( '_welcome_kit_plan' );
        $group = $plan[ $group_key ] ?? null;
        if ( ! $group ) return;

        if ( ! empty( $group['kit_order_id'] ) ) {
            $logger->info(
                sprintf( 'Processing skipped for order %d, group %s — kit already created (kit order %d).', $order_id, $group_key, $group['kit_order_id'] ),
                [ 'source' => 'welcome-kit' ]
            );
            return;
        }

        $gift_products = [];
        foreach ( (array) ( $group['gifts'] ?? [] ) as $gift_id ) {
            $product = wc_get_product( $gift_id );
            if ( $product ) {
                $gift_products[] = $product;
            }
        }

        if ( empty( $gift_products ) ) {
            $logger->error(
                sprintf( 'Processing aborted for order %d, group %s — no gift product resolved.', $order_id, $group_key ),
                [ 'source' => 'welcome-kit' ]
            );
            return;
        }

        $factory   = new AH_Welcome_Kit_Order_Factory( $order, $gift_products, (string) ( $group['status'] ?? '' ) );
        $kit_order = $factory->create();

        if ( is_wp_error( $kit_order ) ) {
            $logger->error(
                sprintf( 'Failed to create kit order for order %d, group %s: %s', $order_id, $group_key, $kit_order->get_error_message() ),
                [ 'source' => 'welcome-kit' ]
            );
            return;
        }

        $plan[ $group_key ]['kit_order_id'] = $kit_order->get_id();
        $order->update_meta_data( '_welcome_kit_plan', $plan );

        $kit_order_ids = array_filter( array_map( 'intval', array_column( $plan, 'kit_order_id' ) ) );
        if ( ! $order->get_meta( '_welcome_kit_order_id' ) ) {
            $order->update_meta_data( '_welcome_kit_order_id', $kit_order->get_id() );
        }
        if ( count( $kit_order_ids ) === count( $plan ) ) {
            $order->update_meta_data( '_welcome_kit_created', true );
        }

        $order->add_order_note(
            sprintf( 'Welcome Kit order %s created automatically.', self::order_link( $kit_order->get_id(), $kit_order ) ),
            false,
            false
        );
        $order->save();

        $subscriptions = wcs_get_subscriptions_for_order( $order, [ 'order_type' => 'parent' ] );
        foreach ( $subscriptions as $subscription ) {
            $subscription->add_order_note(
                sprintf(
                    'Welcome Kit order %s created automatically from order %s.',
                    self::order_link( $kit_order->get_id(), $kit_order ),
                    self::order_link( $order->get_id(), $order )
                ),
                false,
                false
            );
        }

        $logger->info(
            sprintf(
                'Kit order created for order %d, group %s — kit order #%d, %d parent subscription(s) noted.',
                $order_id,
                $group_key,
                $kit_order->get_id(),
                count( $subscriptions )
            ),
            [ 'source' => 'welcome-kit' ]
        );

        do_action( 'ah_welcome_kit_created', $kit_order, $order );
    }

    /**
     * Statuses a kit order can be created with.
     *
     * @return array<string,string> slug => label
     */
    public static function status_options(): array {
        return [
            KIT_READY_TO_SEND => 'Kit Ready to Send',
            'processing'      => 'Processing',
            'completed'       => 'Completed',
        ];
    }

    /**
     * Send schedules, relative to the parent order reaching "completed".
     *
     * @return array<string,array{label:string,delay:int}> slug => label + delay in seconds
     */
    public static function schedule_options(): array {
        return [
            'immediate' => [ 'label' => 'Immediately', 'delay' => 0 ],
            '1_day'     => [ 'label' => '1 day after', 'delay' => DAY_IN_SECONDS ],
            '2_day'     => [ 'label' => '2 days after', 'delay' => 2 * DAY_IN_SECONDS ],
            '1_week'    => [ 'label' => '1 week after', 'delay' => WEEK_IN_SECONDS ],
            '2_week'    => [ 'label' => '2 weeks after', 'delay' => 2 * WEEK_IN_SECONDS ],
        ];
    }

    public static function get_default_status(): string {
        $status = (string) get_option( 'ah_welcome_kit_default_status', KIT_READY_TO_SEND );
        return isset( self::status_options()[ $status ] ) ? $status : KIT_READY_TO_SEND;
    }

    public static function get_default_schedule(): string {
        $schedule = (string) get_option( 'ah_welcome_kit_schedule', 'immediate' );
        return isset( self::schedule_options()[ $schedule ] ) ? $schedule : 'immediate';
    }

    /**
     * Delay in seconds for a schedule slug. Zero means immediate.
     */
    public static function schedule_to_delay( string $schedule ): int {
        return self::schedule_options()[ $schedule ]['delay'] ?? 0;
    }

    /**
     * Identifies a kit order group (same status + same delay) within an order.
     */
    public static function group_key( string $status, int $delay ): string {
        return $status . '|' . $delay;
    }

    private static function status_label( string $status ): string {
        return self::status_options()[ $status ] ?? $status;
    }

    /**
     * Comma-separated gift product names, for order notes.
     *
     * @param int[] $gift_ids
     */
    private static function gift_names( array $gift_ids ): string {
        $names = [];
        foreach ( $gift_ids as $gift_id ) {
            $product = wc_get_product( $gift_id );
            $names[] = $product ? $product->get_name() : "#{$gift_id}";
        }
        return implode( ', ', $names );
    }

    /**
     * Whether an order is an auto-generated welcome kit order.
     *
     * Tracking modules (Attentive, Everflow, Friendbuy) should call this at the
     * top of their status handlers and bail early, so the phantom kit order
     * does not produce spurious tracking events.
     */
    public static function is_kit_order( WC_Order $order ): bool {
        return (bool) $order->get_meta( '_welcome_kit_origin' );
    }

    /**
     * A ready-to-embed "#123" link to an order's edit screen, safe for
     * order notes (WooCommerce runs note content through wp_kses_post(),
     * which allows <a href>).
     */
    private static function order_link( int $order_id, ?WC_Order $order = null ): string {
        $order = $order ?? wc_get_order( $order_id );
        $label = $order ? $order->get_order_number() : $order_id;
        return sprintf(
            '<a href="%s">#%s</a>',
            esc_url( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ),
            esc_html( $label )
        );
    }

    /**
     * Enqueue the status badge styles in admin.
     */
    public function enqueue_admin_styles(): void {
        wp_enqueue_style(
            'ah-welcome-kit-admin',
            plugins_url( 'assets/css/admin.css', __FILE__ ),
            [],
            defined( 'BH_FEATURES_VERSION' ) ? BH_FEATURES_VERSION : '1.0.0',
            'all'
        );
    }
}
