<?php
/**
 * BH Subscriptions Renewal Endpoint
 *
 * Secure REST endpoint to allow customers to trigger a manual renewal
 * of their own subscription from an external app.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Bh_Subscriptions_Renew_Endpoint' ) ) {

class Bh_Subscriptions_Renew_Endpoint {

    /**
     * Allowed subscription statuses for manual renewal.
     *
     * @var array
     */
    protected $allowed_statuses = array( 'active', 'on-hold', 'pending' );

    /**
     * Minimal delay between renewal attempts (seconds).
     *
     * @var int
     */
    protected $min_retry_delay = 30;

    /**
     * Max attempts allowed per hour per subscription/user.
     *
     * @var int
     */
    protected $max_attempts_per_hour = 10;

    /**
     * Constructor.
     */
    public function __construct() {
        //add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_filter( 'wcs_limited_recurring_coupon_manager_enabled', [$this, 'wcs_limited_recurring_coupon_manager_enabled'], 10, 2 );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {

        register_rest_route(
            'bh/v1',
            '/subscriptions/(?P<id>\d+)/renew',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE, // POST
                    'callback'            => array( $this, 'handle_renew_request' ),
                    'permission_callback' => array( $this, 'permission_check' ),
                    'args'                => array(
                        'id' => array(
                            'description' => 'Subscription ID',
                            'type'        => 'integer',
                            'required'    => true,
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Permission check callback.
     *
     * This endpoint is intended to be called by authenticated customers
     * (the same users that own subscriptions in WooCommerce).
     *
     * Authentication can be done via cookies + nonce, JWT, OAuth, etc.
     * Here we only check that a user is logged in.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function permission_check( $request ) {

        // if ( ! is_user_logged_in() ) {
        //     return new WP_Error(
        //         'bh_renew_unauthorized',
        //         __( 'You must be logged in to renew a subscription.', 'bh-features' ),
        //         array( 'status' => 401 )
        //     );
        // }

        // You could add extra capability checks here if needed.
        // Example: only allow users that can "read" (default for customers).
        // $user_id = get_current_user_id();
        // if ( $user_id <= 0 ) {
        //     return new WP_Error(
        //         'bh_renew_invalid_user',
        //         __( 'Invalid authenticated user.', 'bh-features' ),
        //         array( 'status' => 401 )
        //     );
        // }

        return true;
    }

    /**
     * Handle the subscription renewal request.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_renew_request( WP_REST_Request $request ) {

        // Basic safety: ensure WCS functions exist.
        if ( ! function_exists( 'wcs_get_subscription' ) || ! function_exists( 'wcs_create_renewal_order' ) ) {
            return new WP_Error(
                'bh_renew_wcs_missing',
                __( 'Subscriptions engine is not available.', 'bh-features' ),
                array( 'status' => 500 )
            );
        }

        $current_user_id = get_current_user_id();
        $subscription_id = absint( $request->get_param( 'id' ) );

        if ( $subscription_id <= 0 ) {
            return new WP_Error(
                'bh_renew_invalid_id',
                __( 'Invalid subscription ID.', 'bh-features' ),
                array( 'status' => 400 )
            );
        }

        // $subscription = wcs_get_subscription( $subscription_id );
        // return new WP_Error(
        //         'bh_renew_invalid_id',
        //         __( 'Invalid subscription ID.', 'bh-features' ),
        //         array( 'status' => 400, 'result'=> $subscription->get_meta_data() )
        //     );

        // $payment_method = $subscription->get_payment_method();
        // $payment_method_title = $subscription->get_payment_method_title();
        // return new WP_Error(
        //         'bh_renew_missing_request_id',
        //         __( 'Test.', 'bh-features' ),
        //         array( 'status' => 400, 'data'=> [$payment_method, $payment_method_title] )
        //     );

        // Parse JSON body and require a request_id to mitigate replay attacks.
        $body_params = $request->get_json_params();
        $request_id  = isset( $body_params['request_id'] ) ? sanitize_text_field( $body_params['request_id'] ) : '';

        if ( empty( $request_id ) ) {
            return new WP_Error(
                'bh_renew_missing_request_id',
                __( 'Missing request_id parameter.', 'bh-features' ),
                array( 'status' => 400 )
            );
        }

        // Prevent exact same request_id from being reused (simple anti-replay).
        $replay_key = 'bh_renew_replay_' . $subscription_id . '_' . md5( $request_id . '|' . $current_user_id );
        if ( get_transient( $replay_key ) ) {
            return new WP_Error(
                'bh_renew_duplicate_request',
                __( 'This renewal request has already been processed.', 'bh-features' ),
                array( 'status' => 409 )
            );
        }

        // Mark this request_id as used for a short period.
        // 1 hour is usually more than enough; adjust if needed.
        set_transient( $replay_key, 1, HOUR_IN_SECONDS );

        // Load subscription.
        $subscription = wcs_get_subscription( $subscription_id );

        if ( ! $subscription instanceof WC_Subscription ) {
            return new WP_Error(
                'bh_renew_not_found',
                __( 'Subscription not found.', 'bh-features' ),
                array( 'status' => 404 )
            );
        }

        // Ownership check: the current user must be the owner of the subscription.
        $subscription_user_id = (int) $subscription->get_user_id();

        // if ( $subscription_user_id !== $current_user_id ) {
        //     // IMPORTANT: do not leak if the subscription exists or not for other users.
        //     return new WP_Error(
        //         'bh_renew_forbidden',
        //         __( 'You are not allowed to renew this subscription.', 'bh-features' ),
        //         array( 'status' => 403 )
        //     );
        // }

        // Validate subscription status.
        $status = $subscription->get_status();

        if ( ! in_array( $status, $this->allowed_statuses, true ) ) {
            return new WP_Error(
                'bh_renew_invalid_status',
                __( 'This subscription cannot be renewed in its current status.', 'bh-features' ),
                array(
                    'status' => 409,
                    'data'   => array(
                        'subscription_status' => $status,
                    ),
                )
            );
        }

        // Optional: allow external business rules to block renewal (e.g. Telegra, maintenance window).
        $can_renew = apply_filters( 'bh_can_renew_subscription', true, $subscription, $request );
        if ( ! $can_renew ) {
            return new WP_Error(
                'bh_renew_blocked_by_rules',
                __( 'This subscription cannot be renewed at this time due to business rules.', 'bh-features' ),
                array( 'status' => 409 )
            );
        }

        // Validate next payment date (optional business logic).
        $next_payment_date = $subscription->get_date( 'next_payment' );

        if ( empty( $next_payment_date ) ) {
            return new WP_Error(
                'bh_renew_missing_next_payment',
                __( 'This subscription does not have a next payment date configured.', 'bh-features' ),
                array( 'status' => 409 )
            );
        }

        // Basic rate limiting: avoid spamming renew attempts.
        $rate_key = 'bh_renew_rate_' . $subscription_id . '_' . $current_user_id;
        $rate     = get_transient( $rate_key );

        $now = time();
        if ( ! is_array( $rate ) ) {
            $rate = array(
                'count' => 0,
                'start' => $now,
            );
        }

        // Reset window if older than 1 hour.
        if ( ( $now - $rate['start'] ) > HOUR_IN_SECONDS ) {
            $rate['count'] = 0;
            $rate['start'] = $now;
        }

        if ( $rate['count'] >= $this->max_attempts_per_hour ) {
            return new WP_Error(
                'bh_renew_rate_limited',
                __( 'Too many renewal attempts. Please try again later.', 'bh-features' ),
                array( 'status' => 429 )
            );
        }

        // Minimal delay between attempts (anti double-click).
        $last_attempt_key = 'bh_renew_last_' . $subscription_id . '_' . $current_user_id;
        if ( get_transient( $last_attempt_key ) ) {
            return new WP_Error(
                'bh_renew_too_soon',
                __( 'Please wait a moment before trying to renew again.', 'bh-features' ),
                array( 'status' => 429 )
            );
        }

        // Update rate limiting state.
        $rate['count']++;
        set_transient( $rate_key, $rate, HOUR_IN_SECONDS );

        // Set short cool-down.
        set_transient( $last_attempt_key, 1, $this->min_retry_delay );

        // Validate payment method (basic checks for Stripe).
        $payment_method = $subscription->get_payment_method();

        // if ( empty( $payment_method ) ) {
        //     return new WP_Error(
        //         'bh_renew_missing_payment_method',
        //         __( 'This subscription does not have a payment method configured.', 'bh-features' ),
        //         array( 'status' => 409 )
        //     );
        // }

        // You can tighten these checks to match your Stripe gateway IDs.
        // Example: only allow if payment method is Stripe-based.
        // if ( strpos( $payment_method, 'stripe' ) === false ) { ... }

        $stripe_customer_id        = $subscription->get_meta( '_stripe_customer_id', true );
        $stripe_payment_method_id  = $subscription->get_meta( '_stripe_payment_method_id', true );
        $stripe_subscription_id    = $subscription->get_meta( '_stripe_subscription_id', true );

        // if ( empty( $stripe_customer_id ) || empty( $stripe_payment_method_id ) ) {
        //     // We allow missing _stripe_subscription_id because the logic might be different,
        //     // but you can enforce it if required in your environment.
        //     return new WP_Error(
        //         'bh_renew_incomplete_payment_data',
        //         __( 'This subscription does not have complete payment data for renewal.', 'bh-features' ),
        //         array( 'status' => 409 )
        //     );
        // }

        // Check if there is already a renewal order pending/processing for this subscription.
        // $has_open_renewal = $this->has_open_renewal_order( $subscription );

        $validation = $this->validate_last_order_status( $subscription );

        if ( ! $validation['result'] ) {
            return new WP_Error(
                'bh_renew_last_order_not_final',
                __( 'The last order is not in a final state.', 'bh-features' ),
                array(
                    'status' => 409,
                    'data'   => array(
                        'last_order_status' => $validation['status'],
                    ),
                )
            );
        }

        // Create renewal order inside a try/catch to avoid leaking internal errors.
        try {
            /**
             * Exactly mimic WooCommerce Subscriptions scheduled renewal
             * (this is what the admin action "Process Renewal" runs internally)
             */
            do_action( 'woocommerce_scheduled_subscription_payment', $subscription->get_id() );

            /**
             * After triggering the renewal action, WCS will:
             * - Create the renewal order  
             * - Copy payment meta
             * - Trigger Stripe UPE PaymentIntent
             * - Update status (processing / failed / completed)
             * - Log all notes into the order
             */

            // Give WCS/Stripe a moment to complete hooks (important)
            wc_maybe_define_constant( 'WC_DOING_ASYNC', true );
            //do_action( 'woocommerce_subscription_payment_complete', $subscription->get_id() );

            // Now retrieve the newly created renewal order
            $renewal_orders = $subscription->get_related_orders( 'ids', 'renewal' );

            if ( empty( $renewal_orders ) || ! is_array( $renewal_orders ) ) {
                return new WP_Error(
                    'bh_renew_no_order_created',
                    __( 'No renewal order was created during the renewal process.', 'bh-features' ),
                    array( 'status' => 500 )
                );
            }

            $renewal_order_id = max( $renewal_orders );
            $renewal_order    = wc_get_order( $renewal_order_id );

            if ( ! $renewal_order instanceof WC_Order ) {
                return new WP_Error(
                    'bh_renew_invalid_order',
                    __( 'Renewal order was created but is invalid.', 'bh-features' ),
                    array( 'status' => 500 )
                );
            }

            // (Optional) Log for debugging
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->info(
                    "[BH Renew Endpoint] Renewal successfully triggered via scheduled-payment hook. Subscription {$subscription->get_id()}, order {$renewal_order_id}.",
                    array( 'source' => 'bh-renew-endpoint' )
                );
            }

            // --- Force WooCommerce to trigger order status hooks now --- //
            $current_status = $renewal_order->get_status();

            // Trigger specific status hook (processing)
            do_action( 'woocommerce_order_status_' . $current_status, $renewal_order->get_id() );

            // Trigger general status change hook
            do_action( 'woocommerce_order_status_changed', 
                $renewal_order->get_id(),
                'pending',
                $current_status, 
                $renewal_order
            );

        } catch ( \Throwable $e ) {

            if ( function_exists( 'wc_get_logger' ) ) {
                $logger = wc_get_logger();
                $logger->error(
                    sprintf(
                        "[BH Renew Endpoint] Fatal during renewal. Subscription ID: %d, User ID: %d, Error: %s\nTrace:\n%s",
                        $subscription_id,
                        $current_user_id,
                        $e->getMessage(),
                        $e->getTraceAsString()
                    ),
                    array( 'source' => 'bh-renew-endpoint' )
                );
            }

            return new WP_Error(
                'bh_renew_internal_error',
                __( 'Unexpected error while creating the renewal order.', 'bh-features' ),
                array( 'status' => 500 )
            );
        }


        // Allow other systems (Telegra, etc.) to hook into this event.
        do_action( 'bh_subscription_renewal_order_created', $subscription, $renewal_order, $request );

        // Build minimal response (do not expose private data).
        $response_data = array(
            'success'           => true,
            'subscription_id'   => (int) $subscription->get_id(),
            'renewal_order_id'  => (int) $renewal_order->get_id(),
            'message'           => __( 'Renewal order successfully created.', 'bh-features' ),
        );

        return new WP_REST_Response( $response_data, 200 );
    }

    /**
     * Check if the subscription already has an open renewal order.
     *
     * We want to avoid creating multiple renewal orders for the same subscription
     * in states like pending, processing, on-hold, etc.
     *
     * @param WC_Subscription $subscription
     *
     * @return bool|WP_Error
     */
    protected function has_open_renewal_order( $subscription ) {

        if ( ! $subscription instanceof WC_Subscription ) {
            return new WP_Error(
                'bh_renew_invalid_subscription_object',
                __( 'Invalid subscription object.', 'bh-features' ),
                array( 'status' => 500 )
            );
        }

        // Get related renewal orders (WCS helper).
        $related_orders = $subscription->get_related_orders(
            $subscription->get_id(),
            'all',
            'renewal'
        );

        if ( empty( $related_orders ) || ! is_array( $related_orders ) ) {
            return false;
        }

        // Statuses considered as "open" or still relevant.
        $open_statuses = apply_filters(
            'bh_renew_open_order_statuses',
            array( 'pending', 'on-hold', 'processing' )
        );

        foreach ( $related_orders as $order_id ) {
            $order = wc_get_order( $order_id );

            if ( ! $order instanceof WC_Order ) {
                continue;
            }

            if ( in_array( $order->get_status(), $open_statuses, true ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate last order status and return both result and the last order status.
     *
     * @param WC_Subscription $subscription
     * @return array {
     *   @type bool   $result Whether renewal is allowed.
     *   @type string $status Last order status slug.
     * }
     */
    protected function validate_last_order_status( $subscription ) {

        if ( ! $subscription instanceof WC_Subscription ) {
            return array(
                'result' => false,
                'status' => 'unknown',
            );
        }

        $orders = $subscription->get_related_orders( 'all', 'all' );

        if ( empty( $orders ) || ! is_array( $orders ) ) {
            return array(
                'result' => false,
                'status' => 'none',
            );
        }

        $last_order_id = max( $orders );
        $last_order    = wc_get_order( $last_order_id );

        if ( ! $last_order instanceof WC_Order ) {
            return array(
                'result' => false,
                'status' => 'invalid',
            );
        }

        $status = $last_order->get_status(); // slug sin prefijo

        // BLOCKED STATES
        $blocked_states = array(
            'waiting_room',
            'prerequisites',
            'provider_review',
            'collect_payment',
            'error_review',
            'admin_review',
        );

        if ( in_array( $status, $blocked_states, true ) ) {
            return array(
                'result' => false,
                'status' => $status,
            );
        }

        // FINAL STATES
        $final_states = array(
            'completed',
            'cancelled',
            'refunded',
            'failed',
            'cancel_cus_req',
            'cancel_auth_exp',
            'cancel_pat_rej',
        );

        return array(
            'result' => in_array( $status, $final_states, true ),
            'status' => $status,
        );
    }

    /**
     * Prevent errors from Limited Recurring Coupon Manager during manual renewal via API.
     *
     * WCS sometimes receives a subscription ID instead of an order ID,
     * which causes "Call to a member function get_used_coupons() on int".
     *
     * This filter disables the coupon manager **only when WCS receives an INT**
     * (subscription ID), which is the scenario triggered by our renewal endpoint.
     */
    
    function wcs_limited_recurring_coupon_manager_enabled( $enabled, $order_or_subscription ) {

        // If WCS passed an integer instead of an object, disable the manager.
        if ( is_int( $order_or_subscription ) ) {
            return false;
        }

        // If WCS passed something that isn't an order or subscription, also disable.
        if ( ! is_object( $order_or_subscription ) ) {
            return false;
        }

        // Normal behavior — keep the manager active.
        return $enabled;

    }





}

// Initialize the endpoint.
new Bh_Subscriptions_Renew_Endpoint();

}

