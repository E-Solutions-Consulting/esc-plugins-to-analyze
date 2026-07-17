<?php
/**
 * LOB Integration - Configuration & Admin Page
 *
 * Settings store + admin UI for the WooCommerce → LOB direct-mail integration.
 * Everything is admin-driven (API keys, return address, and which subscription
 * status triggers which LOB template), so new mailers need no code changes.
 *
 * @package    BH_Features
 * @subpackage Integrations/Lob
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_LOB_Config {

    const OPTION_KEY = 'bh_lob_settings';

    /**
     * Subscription statuses we can mail on. Slugs match WooCommerce
     * Subscriptions statuses (without the `wc-` prefix).
     *
     * @return array slug => human label
     */
    public static function subscription_statuses() {
        return [
            'active'         => 'Active',
            'pending'        => 'Pending',
            'on-hold'        => 'On hold',
            'pending-cancel' => 'Pending cancellation',
            'cancelled'      => 'Cancelled',
            'expired'        => 'Expired',
            'switched'       => 'Switched',
        ];
    }

    /**
     * All settings merged with defaults.
     *
     * @return array
     */
    public static function get_settings() {
        $defaults = [
            'enabled'         => 'no',
            'logging_enabled' => 'yes',
            'test_mode'       => 'yes',
            'api_key_test'    => '',
            'api_key_live'    => '',
            'mail_type'       => 'postcard', // postcard | letter
            'use_type'        => 'marketing', // marketing | operational (required by LOB)

            // Return (from) address — must be a real address registered/allowed in LOB.
            'from_name'    => '',
            'from_company' => 'Brello Health',
            'from_line1'   => '',
            'from_line2'   => '',
            'from_city'    => '',
            'from_state'   => '',
            'from_zip'     => '',

            // Per-status mailing config: status => [enabled, front, back, file, size].
            'status_mailings' => [],
        ];

        $saved = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $saved ) ) {
            $saved = [];
        }

        return wp_parse_args( $saved, $defaults );
    }

    /**
     * Single setting accessor.
     */
    public static function get( $key, $default = null ) {
        $settings = self::get_settings();
        return $settings[ $key ] ?? $default;
    }

    /**
     * The API key to use given the current test/live mode.
     *
     * @return string
     */
    public static function get_active_api_key() {
        $settings = self::get_settings();
        return ( $settings['test_mode'] === 'yes' )
            ? (string) $settings['api_key_test']
            : (string) $settings['api_key_live'];
    }

    /**
     * Detect the environment a key belongs to from its prefix.
     * LOB keys are prefixed `test_` or `live_`.
     *
     * @param string $key
     * @return string 'test' | 'live' | 'unknown'
     */
    public static function key_environment( $key ) {
        $key = (string) $key;
        if ( strpos( $key, 'live_' ) === 0 ) {
            return 'live';
        }
        if ( strpos( $key, 'test_' ) === 0 ) {
            return 'test';
        }
        return 'unknown';
    }

    /**
     * Whether the integration is enabled and has a usable key.
     *
     * @return bool
     */
    public static function is_active() {
        $settings = self::get_settings();
        return $settings['enabled'] === 'yes' && self::get_active_api_key() !== '';
    }

    /**
     * The mailing config for a given subscription status, or null if that
     * status is not enabled for mailing.
     *
     * Template ids are stored per environment (test vs live) because LOB
     * templates differ between the two. The ids returned here are resolved for
     * whichever mode is currently active, so the worker always sends the id
     * that matches the key it authenticates with.
     *
     * @param string $status
     * @return array|null  [ front, back, file, size ]
     */
    public static function get_status_mailing( $status ) {
        $settings = self::get_settings();
        $row      = $settings['status_mailings'][ $status ] ?? null;

        if ( ! is_array( $row ) || ( $row['enabled'] ?? 'no' ) !== 'yes' ) {
            return null;
        }

        $mode = $settings['test_mode'] === 'yes' ? 'test' : 'live';

        return [
            'front' => self::resolve_mode_value( $row, 'front', $mode ),
            'back'  => self::resolve_mode_value( $row, 'back', $mode ),
            'file'  => self::resolve_mode_value( $row, 'file', $mode ),
            'size'  => $row['size'] ?? '4x6',
        ];
    }

    /**
     * Resolve a per-environment template value ("front_test" / "front_live"),
     * falling back to the legacy single key when in test mode (so configs saved
     * before the test/live split keep working).
     *
     * @param array  $row
     * @param string $base  front | back | file
     * @param string $mode  test | live
     * @return string
     */
    private static function resolve_mode_value( array $row, $base, $mode ) {
        $value = $row[ $base . '_' . $mode ] ?? '';

        if ( $value === '' && $mode === 'test' && ! empty( $row[ $base ] ) ) {
            $value = $row[ $base ]; // legacy value was created with the test key.
        }

        return $value;
    }

    // =========================================================================
    // TEMPLATES (fetched from LOB, cached)
    // =========================================================================

    /**
     * Transient key for the current mode's template cache.
     *
     * @return string
     */
    private static function templates_cache_key() {
        $mode = self::get_settings()['test_mode'] === 'yes' ? 'test' : 'live';
        return 'bh_lob_templates_' . $mode;
    }

    /**
     * Templates for the active key, cached for 10 minutes. Empty array when the
     * key is missing or the fetch fails (the manual text field still works).
     *
     * @return array  List of [ 'id', 'description' ].
     */
    public static function get_templates_cached() {
        $cached = get_transient( self::templates_cache_key() );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        if ( ! class_exists( 'BH_LOB_API_Client' ) || self::get_active_api_key() === '' ) {
            return [];
        }

        $list = ( new BH_LOB_API_Client() )->list_templates();
        if ( is_wp_error( $list ) ) {
            return [];
        }

        set_transient( self::templates_cache_key(), $list, 10 * MINUTE_IN_SECONDS );
        return $list;
    }

    /**
     * Clear both test and live template caches (used by the Refresh button).
     */
    public static function clear_templates_cache() {
        delete_transient( 'bh_lob_templates_test' );
        delete_transient( 'bh_lob_templates_live' );
    }

    /**
     * Render a template picker: a dropdown of fetched templates plus a manual
     * text field for a raw template id / HTML. The text field is the source of
     * truth that gets saved; the dropdown just writes into it via JS. A saved
     * value that is not in the fetched list is preserved and shown as "Manual".
     *
     * @param string $name       Full field name (…[front] / …[back]).
     * @param string $value      Current saved value.
     * @param array  $templates  Fetched templates.
     * @param string $placeholder
     */
    private static function render_template_picker( $name, $value, $templates, $placeholder = 'tmpl_... or HTML' ) {
        $value      = (string) $value;
        $ids        = array_column( $templates, 'id' );
        $in_list    = $value !== '' && in_array( $value, $ids, true );
        $is_manual  = $value !== '' && ! $in_list;
        $uid        = 'lob_' . substr( md5( $name ), 0, 8 );
        ?>
        <select class="bh-lob-tmpl-select" data-target="<?php echo esc_attr( $uid ); ?>" style="max-width:100%;">
            <option value="">— None —</option>
            <?php if ( ! empty( $templates ) ) : ?>
                <optgroup label="Templates">
                    <?php foreach ( $templates as $tmpl ) : ?>
                        <option value="<?php echo esc_attr( $tmpl['id'] ); ?>" <?php selected( $in_list && $value === $tmpl['id'] ); ?>>
                            <?php echo esc_html( $tmpl['description'] . ' (' . $tmpl['id'] . ')' ); ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endif; ?>
            <option value="__manual__" <?php selected( $is_manual ); ?>>Manual / HTML…</option>
        </select>
        <input type="text" id="<?php echo esc_attr( $uid ); ?>" class="regular-text bh-lob-tmpl-input"
            name="<?php echo esc_attr( $name ); ?>"
            value="<?php echo esc_attr( $value ); ?>"
            placeholder="<?php echo esc_attr( $placeholder ); ?>"
            style="margin-top:4px;<?php echo $is_manual ? '' : 'display:none;'; ?>">
        <?php
    }

    // =========================================================================
    // ADMIN
    // =========================================================================

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_page' ], 50 );
        add_action( 'wp_ajax_bh_lob_refresh_templates', [ __CLASS__, 'ajax_refresh_templates' ] );
    }

    /**
     * AJAX: clear the template cache, re-fetch from LOB, and return the list
     * so the dropdowns can be rebuilt without a full page reload.
     */
    public static function ajax_refresh_templates() {
        check_ajax_referer( 'bh_lob_refresh', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Not allowed.' ], 403 );
        }

        self::clear_templates_cache();

        if ( self::get_active_api_key() === '' ) {
            wp_send_json_error( [ 'message' => 'No API key configured.' ] );
        }

        $list = ( new BH_LOB_API_Client() )->list_templates();

        if ( is_wp_error( $list ) ) {
            wp_send_json_error( [ 'message' => $list->get_error_message() ] );
        }

        set_transient( self::templates_cache_key(), $list, 10 * MINUTE_IN_SECONDS );

        wp_send_json_success( [ 'templates' => $list ] );
    }

    public static function register_settings() {
        register_setting(
            'bh_lob_settings_group',
            self::OPTION_KEY,
            [ 'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ] ]
        );
    }

    public static function add_admin_page() {
        $parent = defined( 'PARENT_MENU_SLUG' ) ? PARENT_MENU_SLUG : 'bh-features';
        add_submenu_page(
            $parent,
            'LOB Direct Mail',
            'LOB Direct Mail',
            'manage_options',
            $parent . '--lob',
            [ __CLASS__, 'render_admin_page' ]
        );
    }

    /**
     * Sanitize on save.
     */
    public static function sanitize_settings( $input ) {
        $clean = [];

        $clean['enabled']         = ! empty( $input['enabled'] ) ? 'yes' : 'no';
        $clean['logging_enabled'] = ! empty( $input['logging_enabled'] ) ? 'yes' : 'no';
        $clean['test_mode']       = ! empty( $input['test_mode'] ) ? 'yes' : 'no';

        $clean['api_key_test'] = sanitize_text_field( $input['api_key_test'] ?? '' );
        $clean['api_key_live'] = sanitize_text_field( $input['api_key_live'] ?? '' );

        $clean['mail_type'] = in_array( $input['mail_type'] ?? 'postcard', [ 'postcard', 'letter' ], true )
            ? $input['mail_type']
            : 'postcard';

        $clean['use_type'] = in_array( $input['use_type'] ?? 'marketing', [ 'marketing', 'operational' ], true )
            ? $input['use_type']
            : 'marketing';

        foreach ( [ 'from_name', 'from_company', 'from_line1', 'from_line2', 'from_city', 'from_zip' ] as $k ) {
            $clean[ $k ] = sanitize_text_field( $input[ $k ] ?? '' );
        }
        $clean['from_state'] = strtoupper( sanitize_text_field( $input['from_state'] ?? '' ) );

        $clean['status_mailings'] = [];
        $incoming = isset( $input['status_mailings'] ) && is_array( $input['status_mailings'] )
            ? $input['status_mailings']
            : [];

        foreach ( array_keys( self::subscription_statuses() ) as $status ) {
            $row = $incoming[ $status ] ?? [];
            $clean['status_mailings'][ $status ] = [
                'enabled'    => ! empty( $row['enabled'] ) ? 'yes' : 'no',
                'size'       => sanitize_text_field( $row['size'] ?? '4x6' ),
                // Template ids are stored separately per environment.
                'front_test' => sanitize_text_field( $row['front_test'] ?? '' ),
                'back_test'  => sanitize_text_field( $row['back_test'] ?? '' ),
                'file_test'  => sanitize_text_field( $row['file_test'] ?? '' ),
                'front_live' => sanitize_text_field( $row['front_live'] ?? '' ),
                'back_live'  => sanitize_text_field( $row['back_live'] ?? '' ),
                'file_live'  => sanitize_text_field( $row['file_live'] ?? '' ),
            ];
        }

        return $clean;
    }

    /**
     * Render the settings page.
     */
    public static function render_admin_page() {
        $settings = self::get_settings();

        // Handle "Test Connection" (separate form).
        $test_result = null;
        if ( isset( $_POST['bh_lob_test'] ) && check_admin_referer( 'bh_lob_test_connection' ) ) {
            if ( class_exists( 'BH_LOB_API_Client' ) ) {
                $test_result = ( new BH_LOB_API_Client() )->test_connection();
            }
        }

        // Fetch (cached) templates for the pickers below. The "Refresh Templates"
        // button re-fetches via AJAX without reloading the page.
        $templates = self::get_templates_cached();
        ?>
        <div class="wrap">
            <h1>LOB Direct Mail</h1>
            <p class="description" style="max-width:820px;">
                Sends physical mail (postcards/letters) through
                <a href="https://dashboard.lob.com/" target="_blank" rel="noopener">LOB</a> when a
                subscription changes status. Configure the API keys, your return address, and the
                LOB template for each status below. Use a <strong>test</strong> key
                (<code>test_…</code>) while validating — test keys never send real mail or incur charges.
            </p>

            <?php if ( $test_result !== null ) : ?>
                <?php if ( is_wp_error( $test_result ) ) : ?>
                    <div class="notice notice-error is-dismissible"><p><strong>LOB connection failed:</strong>
                        <?php echo esc_html( $test_result->get_error_message() ); ?></p></div>
                <?php else : ?>
                    <div class="notice notice-success is-dismissible"><p><strong>Connected to LOB.</strong>
                        Mode: <?php echo esc_html( strtoupper( $test_result['mode'] ) ); ?>.</p></div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'bh_lob_settings_group' ); ?>

                <h2>General</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Enable Integration</th>
                        <td><label>
                            <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[enabled]" value="1" <?php checked( $settings['enabled'], 'yes' ); ?>>
                            Send mail to LOB on subscription status changes
                        </label></td>
                    </tr>
                    <tr>
                        <th scope="row">Test Mode</th>
                        <td><label>
                            <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[test_mode]" value="1" <?php checked( $settings['test_mode'], 'yes' ); ?>>
                            Use the test API key (no real mail, no charges)
                        </label></td>
                    </tr>
                    <tr>
                        <th scope="row">Enable Logging</th>
                        <td><label>
                            <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[logging_enabled]" value="1" <?php checked( $settings['logging_enabled'], 'yes' ); ?>>
                            Log to WooCommerce → Status → Logs (source <code>bh-lob</code>)
                        </label></td>
                    </tr>
                    <tr>
                        <th scope="row">Active Environment</th>
                        <td>
                            <?php
                            $sel_mode = $settings['test_mode'] === 'yes' ? 'test' : 'live';
                            $key_env  = self::key_environment( self::get_active_api_key() );
                            $mismatch = self::get_active_api_key() !== '' && $key_env !== 'unknown' && $key_env !== $sel_mode;
                            ?>
                            <strong style="color:<?php echo 'test' === $sel_mode ? '#b45309' : '#15803d'; ?>;">
                                <?php echo strtoupper( $sel_mode ); ?>
                            </strong>
                            <?php if ( self::get_active_api_key() === '' ) : ?>
                                <span class="description">— no <?php echo esc_html( $sel_mode ); ?> API key saved yet.</span>
                            <?php elseif ( $mismatch ) : ?>
                                <span style="color:#b32d2e;font-weight:600;">
                                    ⚠ The saved key looks like a <?php echo esc_html( strtoupper( $key_env ) ); ?> key —
                                    it doesn't match the selected mode. Check the key.
                                </span>
                            <?php else : ?>
                                <span class="description">Key prefix matches (<?php echo esc_html( $key_env . '_' ); ?>…). Templates and mail use this environment.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Test API Key</th>
                        <td><input type="password" class="regular-text" autocomplete="new-password"
                            name="<?php echo self::OPTION_KEY; ?>[api_key_test]" value="<?php echo esc_attr( $settings['api_key_test'] ); ?>"
                            placeholder="test_..."></td>
                    </tr>
                    <tr>
                        <th scope="row">Live API Key</th>
                        <td><input type="password" class="regular-text" autocomplete="new-password"
                            name="<?php echo self::OPTION_KEY; ?>[api_key_live]" value="<?php echo esc_attr( $settings['api_key_live'] ); ?>"
                            placeholder="live_..."></td>
                    </tr>
                    <tr>
                        <th scope="row">Mail Type</th>
                        <td>
                            <select name="<?php echo self::OPTION_KEY; ?>[mail_type]">
                                <option value="postcard" <?php selected( $settings['mail_type'], 'postcard' ); ?>>Postcard</option>
                                <option value="letter" <?php selected( $settings['mail_type'], 'letter' ); ?>>Letter</option>
                            </select>
                            <p class="description">Postcards use Front/Back template ids; letters use a single File/template id.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Use Type</th>
                        <td>
                            <select name="<?php echo self::OPTION_KEY; ?>[use_type]">
                                <option value="marketing" <?php selected( $settings['use_type'], 'marketing' ); ?>>Marketing</option>
                                <option value="operational" <?php selected( $settings['use_type'], 'operational' ); ?>>Operational</option>
                            </select>
                            <p class="description">Required by LOB. Win-back / promotional mail = Marketing; transactional notices = Operational.</p>
                        </td>
                    </tr>
                </table>

                <h2>Return Address (From)</h2>
                <p class="description">Required by LOB on every mail piece. Must be a valid US address.</p>
                <table class="form-table" role="presentation">
                    <?php
                    $addr_fields = [
                        'from_name'    => 'Name',
                        'from_company' => 'Company',
                        'from_line1'   => 'Address line 1',
                        'from_line2'   => 'Address line 2',
                        'from_city'    => 'City',
                        'from_state'   => 'State (2-letter)',
                        'from_zip'     => 'ZIP',
                    ];
                    foreach ( $addr_fields as $key => $label ) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html( $label ); ?></th>
                            <td><input type="text" class="regular-text"
                                name="<?php echo self::OPTION_KEY . '[' . $key . ']'; ?>"
                                value="<?php echo esc_attr( $settings[ $key ] ); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <h2>Status → Mailing</h2>
                <p class="description">
                    Enable the subscription statuses that should trigger a mailing and pick the LOB
                    template. Choose <em>Manual / HTML…</em> to enter a template id or raw HTML by
                    hand. Leave a status disabled to send nothing for it. For the <em>cancelled</em>
                    status this is your win-back mailer.
                </p>
                <p style="margin:0 0 8px;">
                    <?php if ( ! empty( $templates ) ) : ?>
                        <span class="description"><?php echo count( $templates ); ?> template(s) loaded from LOB.</span>
                    <?php elseif ( self::get_active_api_key() === '' ) : ?>
                        <span class="description">Add and save an API key to load templates into the dropdowns.</span>
                    <?php else : ?>
                        <span class="description">No templates found (or fetch failed) — use Manual / HTML.</span>
                    <?php endif; ?>
                    <button type="button" id="bh-lob-refresh-btn" class="button button-small" style="margin-left:6px;">Refresh Templates</button>
                    <span id="bh-lob-refresh-status" class="description" style="margin-left:6px;"></span>
                </p>
                <?php $mode = $settings['test_mode'] === 'yes' ? 'test' : 'live'; $other = 'test' === $mode ? 'live' : 'test'; ?>
                <p style="margin:0 0 8px;padding:6px 10px;border-left:4px solid <?php echo 'test' === $mode ? '#b45309' : '#15803d'; ?>;background:#f6f7f7;">
                    You are editing <strong><?php echo strtoupper( $mode ); ?></strong> templates
                    (Test Mode is <?php echo 'test' === $mode ? 'ON' : 'OFF'; ?>).
                    Test and live templates are stored separately — the <?php echo strtoupper( $other ); ?>
                    template ids you saved are kept and used automatically when you switch modes.
                </p>
                <table class="widefat" style="max-width:900px;margin-bottom:1em;">
                    <thead>
                        <tr>
                            <th style="width:60px;">Send</th>
                            <th>Status</th>
                            <th>Front / File template id</th>
                            <th>Back template id (postcard)</th>
                            <th style="width:80px;">Size</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( self::subscription_statuses() as $slug => $label ) :
                            $row = $settings['status_mailings'][ $slug ] ?? []; ?>
                            <tr>
                                <td style="text-align:center;">
                                    <input type="checkbox" value="1"
                                        name="<?php echo self::OPTION_KEY . '[status_mailings][' . $slug . '][enabled]'; ?>"
                                        <?php checked( ( $row['enabled'] ?? 'no' ), 'yes' ); ?>>
                                </td>
                                <td><strong><?php echo esc_html( $label ); ?></strong><br><code><?php echo esc_html( $slug ); ?></code></td>
                                <td><?php self::render_template_picker(
                                    self::OPTION_KEY . '[status_mailings][' . $slug . '][front_' . $mode . ']',
                                    self::resolve_mode_value( $row, 'front', $mode ),
                                    $templates,
                                    'tmpl_... or HTML'
                                ); ?></td>
                                <td><?php self::render_template_picker(
                                    self::OPTION_KEY . '[status_mailings][' . $slug . '][back_' . $mode . ']',
                                    self::resolve_mode_value( $row, 'back', $mode ),
                                    $templates,
                                    'tmpl_...'
                                ); ?></td>
                                <td><input type="text" style="width:70px;"
                                    name="<?php echo self::OPTION_KEY . '[status_mailings][' . $slug . '][size]'; ?>"
                                    value="<?php echo esc_attr( $row['size'] ?? '4x6' ); ?>"></td>
                            </tr>
                            <?php /* Preserve the other environment's ids + both file ids so saving one mode never wipes the other. */ ?>
                            <input type="hidden" name="<?php echo self::OPTION_KEY . '[status_mailings][' . $slug . '][front_' . $other . ']'; ?>" value="<?php echo esc_attr( $row[ 'front_' . $other ] ?? '' ); ?>">
                            <input type="hidden" name="<?php echo self::OPTION_KEY . '[status_mailings][' . $slug . '][back_' . $other . ']'; ?>" value="<?php echo esc_attr( $row[ 'back_' . $other ] ?? '' ); ?>">
                            <input type="hidden" name="<?php echo self::OPTION_KEY . '[status_mailings][' . $slug . '][file_test]'; ?>" value="<?php echo esc_attr( $row['file_test'] ?? '' ); ?>">
                            <input type="hidden" name="<?php echo self::OPTION_KEY . '[status_mailings][' . $slug . '][file_live]'; ?>" value="<?php echo esc_attr( $row['file_live'] ?? '' ); ?>">
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php submit_button( 'Save Settings' ); ?>
            </form>

            <hr>
            <h2>Tools</h2>
            <form method="post">
                <?php wp_nonce_field( 'bh_lob_test_connection' ); ?>
                <button type="submit" name="bh_lob_test" class="button button-secondary">Test LOB Connection</button>
                <p class="description">Sends <code>GET /addresses?limit=1</code> using the active key (test or live per the toggle above).</p>
            </form>

            <script>
            (function () {
                var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
                var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'bh_lob_refresh' ) ); ?>;

                function esc(s) {
                    return String(s).replace(/[&<>"]/g, function (c) {
                        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
                    });
                }

                // Wire a dropdown to its manual text input (change handler).
                function wire(sel) {
                    var input = document.getElementById(sel.getAttribute('data-target'));
                    if (!input) { return; }
                    sel.onchange = function () {
                        if (sel.value === '__manual__') {
                            input.style.display = '';
                            input.value = '';
                            input.focus();
                        } else {
                            input.value = sel.value; // '' (None) or a template id
                            input.style.display = 'none';
                        }
                    };
                }

                // Rebuild a dropdown's options from a fresh template list, keeping
                // whatever value is currently saved in its paired text input.
                function rebuild(sel, templates) {
                    var input   = document.getElementById(sel.getAttribute('data-target'));
                    var current = input ? input.value : '';
                    var inList  = false;
                    var html    = '<option value="">— None —</option>';

                    if (templates.length) {
                        html += '<optgroup label="Templates">';
                        templates.forEach(function (t) {
                            var isSel = current === t.id;
                            if (isSel) { inList = true; }
                            html += '<option value="' + esc(t.id) + '"' + (isSel ? ' selected' : '') + '>' +
                                    esc(t.description + ' (' + t.id + ')') + '</option>';
                        });
                        html += '</optgroup>';
                    }

                    var isManual = current !== '' && !inList;
                    html += '<option value="__manual__"' + (isManual ? ' selected' : '') + '>Manual / HTML…</option>';

                    sel.innerHTML = html;
                    if (input) { input.style.display = isManual ? '' : 'none'; }
                }

                document.querySelectorAll('.bh-lob-tmpl-select').forEach(wire);

                var btn    = document.getElementById('bh-lob-refresh-btn');
                var status = document.getElementById('bh-lob-refresh-status');

                if (btn) {
                    btn.addEventListener('click', function () {
                        btn.disabled = true;
                        if (status) { status.textContent = 'Refreshing…'; }

                        var body = new URLSearchParams();
                        body.append('action', 'bh_lob_refresh_templates');
                        body.append('nonce', nonce);

                        fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                            .then(function (r) { return r.json(); })
                            .then(function (res) {
                                if (res && res.success) {
                                    var templates = res.data.templates || [];
                                    document.querySelectorAll('.bh-lob-tmpl-select').forEach(function (sel) {
                                        rebuild(sel, templates);
                                    });
                                    if (status) { status.textContent = templates.length + ' template(s) loaded.'; }
                                } else {
                                    if (status) { status.textContent = 'Refresh failed: ' + ((res && res.data && res.data.message) || 'error'); }
                                }
                            })
                            .catch(function () {
                                if (status) { status.textContent = 'Refresh failed (network).'; }
                            })
                            .finally(function () { btn.disabled = false; });
                    });
                }
            })();
            </script>
        </div>
        <?php
    }
}
