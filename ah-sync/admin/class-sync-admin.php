<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AH_Sync_Admin {

    private string $version;

    public function __construct( string $version ) {
        $this->version = $version;
    }

    // ─────────────────────────────────────────────────────────
    // Menu
    // ─────────────────────────────────────────────────────────

    public function add_admin_menu() {
        if ( ! $this->current_user_can() ) return;

        // Attach to the same Brello parent menu as bh-tools
        $parent_slug = defined( 'PARENT_MENU_SLUG' ) ? PARENT_MENU_SLUG : 'bh-tools';

        add_submenu_page(
            $parent_slug,
            'Telegra WC Sync',
            'Telegra WC Sync',
            'manage_options',
            'ah-sync--telegra-wc-sync',
            [ $this, 'page_telegra_wc_sync' ]
        );
    }

    private function current_user_can(): bool {
        return current_user_can( 'manage_options' );
    }

    // ─────────────────────────────────────────────────────────
    // Assets
    // ─────────────────────────────────────────────────────────

    public function enqueue_assets( string $hook ) {
        if ( strpos( $hook, 'ah-sync--telegra-wc-sync' ) === false ) return;

        wp_enqueue_style(  'hb-select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css' );
        wp_enqueue_script( 'hb-select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', [ 'jquery' ], null, true );

        wp_enqueue_script(
            'ah-sync-admin',
            AH_SYNC_PLUGIN_URL . 'admin/js/ah-sync-admin.js',
            [ 'jquery', 'hb-select2' ],
            $this->version,
            true
        );

        wp_localize_script( 'ah-sync-admin', 'ahSync', [
            'ajaxurl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'ah_sync_nonce' ),
            'wc_statuses'  => wc_get_order_statuses(),
        ] );
    }

    // ─────────────────────────────────────────────────────────
    // Page
    // ─────────────────────────────────────────────────────────

    public function page_telegra_wc_sync() {
        if ( ! $this->current_user_can() ) wp_die( 'Unauthorized' );
        $states = WC()->countries->get_states( 'US' );
        require_once AH_SYNC_PLUGIN_DIR . 'admin/partials/page-telegra-wc-sync.php';
    }

    // ─────────────────────────────────────────────────────────
    // AJAX — Preview (dry run, saves IDs to transient)
    // ─────────────────────────────────────────────────────────

    public function ajax_telegra_wc_preview() {
        $this->verify_nonce();
        try {
            $args           = $this->parse_form_args();
            $args['dry_run'] = true;
            $args['mode']    = 'apply_fresh';

            // First batch always resets transients
            if ( intval( $_POST['offset'] ?? 0 ) === 0 ) {
                $this->get_runner( AH_Sync_Logger::SOURCE_MANUAL )->clear_transients();
            }

            $result = $this->get_runner( AH_Sync_Logger::SOURCE_MANUAL )->process_batch( $args );
            wp_send_json_success( $result );

        } catch ( \Throwable $th ) {
            $this->handle_exception( $th );
        }
    }

    // ─────────────────────────────────────────────────────────
    // AJAX — Apply (uses cached IDs or re-fetches)
    // ─────────────────────────────────────────────────────────

    public function ajax_telegra_wc_apply() {
        $this->verify_nonce();
        try {
            $args            = $this->parse_form_args();
            $args['dry_run'] = false;
            $args['mode']    = sanitize_text_field( $_POST['apply_mode'] ?? 'apply_fresh' );
            // apply_mode: 'apply_cached' | 'apply_fresh'

            $cached_ids = get_transient( AH_Telegra_WC_Sync::TRANSIENT_PREVIEW_IDS );
            if ( $args['mode'] === 'apply_cached' && empty( $cached_ids ) ) {
                wp_send_json_error( [
                    'message' => 'Preview cache expired (30 min). Please run Preview again or use Apply Fresh.',
                    'code'    => 'cache_expired',
                ] );
                return;
            }

            $result = $this->get_runner( AH_Sync_Logger::SOURCE_MANUAL )->process_batch( $args );
            wp_send_json_success( $result );

        } catch ( \Throwable $th ) {
            $this->handle_exception( $th );
        }
    }

    // ─────────────────────────────────────────────────────────
    // AJAX — Export partial CSV (Stop button)
    // ─────────────────────────────────────────────────────────

    public function ajax_telegra_wc_export() {
        $this->verify_nonce();
        try {
            $upload_dir = wp_upload_dir();
            $base       = $upload_dir['basedir'] . '/bh-exports/';
            $file_path  = $base . 'ah-sync-telegra-wc-temp.csv';

            if ( file_exists( $file_path ) ) {
                $name = 'ah-sync-telegra-wc-PARTIAL-' . date( 'Y-m-d-His' ) . '.csv';
                rename( $file_path, $base . $name );
                delete_transient( AH_Telegra_WC_Sync::TRANSIENT_ACTIVE );
                wp_send_json_success( [ 'file_url' => $upload_dir['baseurl'] . '/bh-exports/' . $name ] );
            } else {
                wp_send_json_error( [ 'message' => 'No partial file found.' ] );
            }
        } catch ( \Throwable $th ) {
            $this->handle_exception( $th );
        }
    }

    // ─────────────────────────────────────────────────────────
    // AJAX — Reset (clear transients, allow new run)
    // ─────────────────────────────────────────────────────────

    public function ajax_telegra_wc_reset() {
        $this->verify_nonce();
        $this->get_runner( AH_Sync_Logger::SOURCE_MANUAL )->clear_transients();
        wp_send_json_success( [ 'message' => 'Reset successful. Ready for a new sync.' ] );
    }

    // ─────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────

    private function get_runner( string $source ): AH_Telegra_WC_Sync {
        return new AH_Telegra_WC_Sync( new AH_Sync_Logger( $source ) );
    }

    private function verify_nonce() {
        if ( ! check_ajax_referer( 'ah_sync_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed.', 'code' => 'invalid_nonce' ] );
            exit;
        }
        if ( ! $this->current_user_can() ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.', 'code' => 'unauthorized' ] );
            exit;
        }
    }

    private function parse_form_args(): array {
        parse_str( $_POST['form_data'] ?? '', $form_data );
        return [
            'offset'           => intval( $_POST['offset'] ?? 0 ),
            'total'            => intval( $_POST['total']  ?? 0 ),
            'batch_size'       => intval( $form_data['batch_size']       ?? 25 ),
            'update_method'    => sanitize_text_field( $form_data['update_method']  ?? 'direct' ),
            'exclude_sync' => boolval( $form_data['exclude_sync'] ?? false ),
            'exclude_renewals' => boolval( $form_data['exclude_renewals'] ?? false ),
            'status'           => array_filter( (array) ( $form_data['status'] ?? [] ) ),
            'states'           => array_filter( (array) ( $form_data['states'] ?? [] ) ),
            'start_date'       => sanitize_text_field( $form_data['start_date'] ?? '' ),
            'end_date'         => sanitize_text_field( $form_data['end_date']   ?? '' ),
        ];
    }

    private function handle_exception( \Throwable $th ) {
        delete_transient( AH_Telegra_WC_Sync::TRANSIENT_ACTIVE );
        wp_send_json_error( [
            'message' => $th->getMessage(),
            'code'    => 'exception',
        ] );
    }
}
