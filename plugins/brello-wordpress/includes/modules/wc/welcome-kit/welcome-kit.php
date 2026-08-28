<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AH_Welcome_Kit {

    public function __construct() {
        add_action( 'woocommerce_order_status_completed', [ $this, 'handle_completed_order' ], 10, 1 );
        add_action( 'ah_welcome_kit_process_scheduled', [ $this, 'create_kit_order' ], 10, 1 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );
    }

    /**
     * Triggered when an order reaches "completed" status.
     *
     * A lock guards against duplicate processing, since
     * woocommerce_order_status_completed can fire more than once for the same
     * order. The persistent _welcome_kit_created flag is the definitive
     * safety net for late re-fires. If a delay is configured, the kit order
     * is not created here — a WP-Cron event is scheduled instead, and
     * create_kit_order() does the actual work when it fires.
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

            $delay = $this->get_schedule_delay();

            if ( $delay > 0 ) {
                $this->schedule_kit_order( $order, $delay );
                return;
            }

            $this->create_kit_order( $order_id );
        } finally {
            delete_transient( $lock_key );
        }
    }

    /**
     * Schedules a deferred kit order creation via WP-Cron.
     *
     * Guards against scheduling the same order twice (e.g. a duplicate
     * "completed" fire before the lock transient exists on a slow request).
     */
    private function schedule_kit_order( WC_Order $order, int $delay ): void {
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
     * Creates the welcome kit order for a given order ID.
     *
     * Called either immediately (no delay configured) or as the WP-Cron
     * callback for a previously scheduled order.
     */
    public function create_kit_order( int $order_id ): void {
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
     * Delay in seconds before the kit order is created, based on the
     * configured schedule option. Zero means immediate (current behavior).
     */
    private function get_schedule_delay(): int {
        $schedule = get_option( 'ah_welcome_kit_schedule', 'immediate' );

        $map = [
            '1_day'  => DAY_IN_SECONDS,
            '2_day'  => 2 * DAY_IN_SECONDS,
            '1_week' => WEEK_IN_SECONDS,
            '2_week' => 2 * WEEK_IN_SECONDS,
        ];

        return $map[ $schedule ] ?? 0;
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
