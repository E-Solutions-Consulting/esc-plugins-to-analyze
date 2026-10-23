<?php
/**
 * Attentive Integration - Catalog Sync
 *
 * Uploads the ndjson file built by BH_Attentive_Catalog_Feed to Attentive's
 * Product Catalog API and reports back the per-line validation result, so a
 * bad feed can be caught before it replaces the live catalog. Attentive's
 * upload flow is three calls: create an upload session (optionally
 * validate-only), PUT the file to the presigned S3 URL it returns, then poll
 * that same upload for its processed status and errors — Attentive
 * processes it asynchronously, so the status right after the PUT is usually
 * still "initialized".
 *
 * @package BH_Features
 * @subpackage Integrations/Attentive
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Catalog_Sync {

    const AJAX_ACTION  = 'bh_attentive_catalog_sync';
    const NONCE_ACTION = 'bh_attentive_catalog_sync';

    /**
     * Statuses that mean "Attentive is still working on it" — poll again.
     */
    const PENDING_STATUSES = array( 'initialized', 'validating', 'uploading', 'processing' );

    public function __construct() {
        add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    /**
     * Only on the Attentive settings page.
     */
    public function enqueue_admin_scripts() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== PARENT_MENU_SLUG . '--attentive' ) {
            return;
        }

        wp_enqueue_script(
            'bh-attentive-catalog-sync',
            plugin_dir_url( __FILE__ ) . 'assets/js/catalog-sync.js',
            array( 'jquery' ),
            '1.0.0',
            true
        );

        wp_localize_script( 'bh-attentive-catalog-sync', 'bhAttentiveCatalogSync', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
        ) );
    }

    public function handle_ajax() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        $validate_only = ! empty( $_POST['validate_only'] );
        $result        = self::run( $validate_only );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * Runs the full create-session / upload / poll cycle and returns
     * Attentive's final upload status, or a WP_Error if any step fails
     * outright (missing file, network error, rejected session).
     */
    public static function run( $validate_only ) {
        $file_path = BH_Attentive_Catalog_Feed::get_file_path();
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'bh_attentive_catalog_no_file', 'Generate the catalog feed first — there is no file to upload yet.' );
        }

        $file_contents = file_get_contents( $file_path );
        if ( $file_contents === false || trim( $file_contents ) === '' ) {
            return new WP_Error( 'bh_attentive_catalog_empty_file', 'The catalog feed file is empty.' );
        }

        $settings = BH_Attentive_Config::get_settings();
        $client   = new BH_Attentive_API_Client( $settings['catalog_api_key'] );

        $session = $client->create_catalog_upload( $validate_only );
        if ( is_wp_error( $session ) ) {
            return $session;
        }
        if ( empty( $session['uploadId'] ) || empty( $session['uploadUrl'] ) ) {
            return new WP_Error( 'bh_attentive_catalog_bad_session', 'Attentive did not return an upload session.' );
        }

        $upload = $client->upload_catalog_file( $session['uploadUrl'], $file_contents );
        if ( is_wp_error( $upload ) ) {
            return $upload;
        }

        $status = self::poll_result( $client, $session['uploadId'] );
        if ( is_wp_error( $status ) ) {
            return $status;
        }

        $status['validateOnly'] = (bool) $validate_only;
        return $status;
    }

    /**
     * Attentive processes the upload asynchronously — poll a few times with
     * a short delay instead of returning the still-"initialized" status from
     * right after the PUT. Stops early once the status leaves the pending
     * set; otherwise returns whatever the last poll had.
     */
    private static function poll_result( $client, $upload_id, $max_attempts = 6, $delay_seconds = 2 ) {
        $status = null;

        for ( $attempt = 0; $attempt < $max_attempts; $attempt++ ) {
            if ( $attempt > 0 ) {
                sleep( $delay_seconds );
            }

            $status = $client->get_catalog_upload( $upload_id );
            if ( is_wp_error( $status ) ) {
                return $status;
            }

            if ( ! in_array( $status['status'] ?? '', self::PENDING_STATUSES, true ) ) {
                break;
            }
        }

        return $status;
    }
}

new BH_Attentive_Catalog_Sync();
