<?php
/**
 * Attentive Integration - Landing Page Form Handler
 *
 * Hooks into Elementor Pro form submissions and, for any landing-page form
 * configured under Attentive → Lead Magnets, subscribes the lead to Attentive,
 * sets the configured custom attribute, and fires the configured custom event
 * (which lets Attentive trigger a welcome Journey).
 *
 * Configuration is admin-driven (Attentive → Lead Magnets tab) — add a new
 * landing page / form there, no code changes required.
 *
 * Design notes:
 *  - The Elementor hook only ENQUEUES an Action Scheduler job and returns,
 *    so the form submit (AJAX) stays fast and never blocks on Attentive.
 *  - All HTTP calls run inside the AS worker (blocking is fine there).
 *  - Payloads are EMAIL-ONLY — no empty `phone` key is sent (Attentive
 *    rejects an empty phone string).
 *  - Only forms matched in the Lead Magnets config are processed; every other
 *    Elementor form on the site is ignored.
 *
 * @package BH_Features
 * @subpackage Integrations/Attentive
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Landing_Page_Form {

    /**
     * Action Scheduler hook + group.
     */
    const AS_HOOK  = 'bh_attentive_process_lead_magnet';
    const AS_GROUP = 'bh-attentive';

    /**
     * Initialize the handler.
     */
    public static function init() {
        add_action( 'elementor_pro/forms/new_record', [ __CLASS__, 'handle_form_submission' ], 10, 2 );
        add_action( self::AS_HOOK, [ __CLASS__, 'worker_process_lead' ], 10, 1 );
    }

    /**
     * Elementor Pro form submission — runs in the AJAX request.
     * Matches the form against the Lead Magnets config, extracts the email,
     * and enqueues the async job.
     *
     * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record
     * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $handler
     */
    public static function handle_form_submission( $record, $handler ) {

        // Only forms configured under Attentive → Lead Magnets.
        $magnet = self::match_lead_magnet();
        if ( ! $magnet ) {
            return;
        }

        // Extract the email field.
        $raw_fields = $record->get( 'fields' );
        $email      = isset( $raw_fields['email']['value'] )
            ? sanitize_email( $raw_fields['email']['value'] )
            : '';

        if ( empty( $email ) || ! is_email( $email ) ) {
            return;
        }

        $form_name = (string) $record->get_form_settings( 'form_name' );

        // Enqueue only — no blocking HTTP here, so the form submit returns fast.
        as_enqueue_async_action(
            self::AS_HOOK,
            [ [
                'email'     => $email,
                'form_name' => $form_name,
                'label'     => $magnet['label'] ?? '',
                'post_id'   => $magnet['post_id'] ?? '',
                'attribute' => $magnet['attribute'] ?? '',
                'event'     => $magnet['event'] ?? '',
                'source_id' => $magnet['source_id'] ?? '',
            ] ],
            self::AS_GROUP
        );

        BH_Attentive_Helper::log( '[Landing Page] Lead enqueued', [
            'email'     => $email,
            'label'     => $magnet['label'] ?? '',
            'form_name' => $form_name,
        ] );
    }

    /**
     * Find the enabled Lead Magnet config matching the current submission.
     * Elementor posts post_id / form_id with the AJAX request, so we can read
     * them here (this method runs in the request, not the worker).
     *
     * @return array|null Matched config row, or null if none.
     */
    private static function match_lead_magnet() {

        $magnets = BH_Attentive_Config::get( 'lead_magnets', [] );
        if ( empty( $magnets ) || ! is_array( $magnets ) ) {
            return null;
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
        $form_id = isset( $_POST['form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : '';

        foreach ( $magnets as $m ) {

            if ( ( $m['enabled'] ?? 'no' ) !== 'yes' ) {
                continue;
            }

            // Primary match: the page the form lives on.
            if ( ! empty( $m['post_id'] ) && (int) $m['post_id'] === $post_id ) {
                return $m;
            }

            // Fallback match: the specific Elementor form element id.
            if ( ! empty( $m['form_id'] ) && $m['form_id'] === $form_id ) {
                return $m;
            }
        }

        return null;
    }

    /**
     * Action Scheduler worker — does the actual Attentive work in the
     * background. Blocking HTTP calls are fine here.
     *
     * @param array $args [ email, form_name, label, post_id, attribute, event ]
     */
    public static function worker_process_lead( $args ) {

        $email     = isset( $args['email'] ) ? sanitize_email( $args['email'] ) : '';
        $form_name = $args['form_name'] ?? '';
        $label     = $args['label'] ?? '';
        $post_id   = $args['post_id'] ?? '';
        $attribute = $args['attribute'] ?? '';
        $event     = $args['event'] ?? '';
        $source_id = $args['source_id'] ?? '';

        if ( empty( $email ) || ! is_email( $email ) ) {
            return;
        }

        // Deduplication — one signup per email per landing page per 24h.
        $dedup_key = 'bh_lead_magnet_' . md5( $email . '|' . ( $post_id ?: $label ) );
        if ( get_transient( $dedup_key ) ) {
            BH_Attentive_Helper::log( '[Landing Page] Duplicate within window — skipping', [ 'email' => $email ] );
            return;
        }

        $settings = BH_Attentive_Config::get_settings();
        $api_key  = $settings['api_key'] ?? '';

        if ( empty( $api_key ) ) {
            BH_Attentive_Helper::log( '[Landing Page] API key not configured — aborting' );
            return;
        }

        $base = trailingslashit( $settings['api_base_url'] ?? 'https://api.attentivemobile.com/v1' );

        // Sign-up Source ID — per lead-magnet row, falling back to the marketing
        // source configured under Attentive → Subscriptions.
        if ( empty( $source_id ) ) {
            $source_id = $settings['marketing_source_id'] ?? '';
        }

        // 1. Subscribe the lead (email-only — no phone collected on these forms).
        //    Attentive's /subscriptions REQUIRES a signUpSourceId; the locale-only
        //    fallback is rejected with HTTP 400 "Invalid json passed in body".
        //    The signUpSourceId also routes the lead into that source's welcome Journey.
        $subscribe_payload = [ 'user' => [ 'email' => $email ] ];

        if ( ! empty( $source_id ) ) {
            $subscribe_payload['signUpSourceId'] = $source_id;
        } else {
            // No source configured — best-effort fallback (will likely 400 until a
            // Sign-up Source ID is set in Attentive → Lead Magnets).
            $subscribe_payload['locale']              = 'en_US';
            $subscribe_payload['externalIdentifiers'] = [ 'clientUserId' => md5( $email ) ];
            BH_Attentive_Helper::log( '[Landing Page] No signUpSourceId configured — subscribe may be rejected', [ 'email' => $email ] );
        }

        self::api_post( $base . 'subscriptions', $subscribe_payload, $api_key, 'subscribe' );

        // 2. Set the configured custom attribute (skip if not configured).
        if ( ! empty( $attribute ) ) {
            self::api_post( $base . 'attributes/custom', [
                'user'       => [ 'email' => $email ],
                'properties' => [ $attribute => true ],
            ], $api_key, 'attributes' );
        }

        // 3. Fire the configured custom event so Attentive can trigger a
        //    welcome Journey (skip if no event configured).
        if ( ! empty( $event ) ) {
            self::api_post( $base . 'events/custom', [
                'type'            => $event,
                'externalEventId' => wp_generate_uuid4(),
                'occurredAt'      => gmdate( 'c' ),
                'user'            => [ 'email' => $email ],
                'properties'      => [
                    'source'    => 'landing_page',
                    'label'     => $label,
                    'post_id'   => (string) $post_id,
                    'form_name' => $form_name,
                ],
            ], $api_key, 'event' );
        }

        // Mark processed so rapid re-submits don't double-fire.
        set_transient( $dedup_key, 1, DAY_IN_SECONDS );

        BH_Attentive_Helper::log( '[Landing Page] Lead processed successfully', [
            'email' => $email,
            'label' => $label,
        ] );
    }

    /**
     * POST a JSON payload to Attentive and log the result.
     *
     * @param string $url
     * @param array  $data
     * @param string $api_key
     * @param string $label   Short label for logging.
     * @return array|WP_Error
     */
    private static function api_post( $url, $data, $api_key, $label ) {

        $response = wp_remote_post( $url, [
            'headers'  => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'     => wp_json_encode( $data ),
            'blocking' => true,  // running in an AS worker — safe to block.
            'timeout'  => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            BH_Attentive_Helper::log( "[Landing Page] {$label} error", [
                'error' => $response->get_error_message(),
            ] );
        } else {
            BH_Attentive_Helper::log( "[Landing Page] {$label} response", [
                'code' => wp_remote_retrieve_response_code( $response ),
                'body' => wp_remote_retrieve_body( $response ),
            ] );
        }

        return $response;
    }
}
