<?php
/**
 * Attentive Integration - Configuration & Admin Page
 *
 * Handles settings storage and the tabbed admin interface for the integration.
 *
 * Tabs (visual only — a single <form> wraps all tabs so saving one tab
 * never wipes the values of the others):
 *   1. General       — master on/off switches and API credentials
 *   2. Subscriptions — signup source IDs for marketing and transactional flows
 *   3. Typeform      — webhook URL/secret and field mapping
 *   4. Frontend      — footer form trigger and Attentive SDK settings
 *   5. Tools         — test connection, generate webhook secret
 *   6. Logs          — recent webhook activity (read-only, outside the form)
 *
 * @package BH_Features
 * @subpackage Integrations/Attentive
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Config {

    const OPTION_KEY = 'bh_attentive_settings';

    /**
     * Initialize configuration
     */
    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_page' ), 50 );
    }

    /**
     * Get all settings with defaults
     */
    public static function get_settings() {
        $defaults = array(
            // General
            'enabled'                  => 'no',
            'logging_enabled'          => 'yes',
            'api_key'                  => '',
            'api_base_url'             => 'https://api.attentivemobile.com/v1',
            // Subscriptions
            'sign_up_source_id'        => '',   // legacy — kept for compatibility
            'marketing_source_id'      => '',
            'transactional_source_id'  => '',
            // Typeform
            'webhook_secret'           => '',
            'default_event_name'       => 'survey_completed',
            'phone_field_id'           => '',
            'email_field_id'           => '',
            'field_mappings'           => array(),
            // Frontend Trigger
            'attentive_account'        => '',
            'mobile_creative_id'       => '',
            'desktop_creative_id'      => '',
            'footer_form_selector'     => '',
            'frontend_trigger_enabled' => 'no',
            'trigger_pages'            => 'blog_only',
            // Lead Magnets (Elementor landing-page forms → Attentive)
            // Seeded with the GLP-1 guide so the existing flow keeps working
            // until it's managed from the admin UI.
            'lead_magnets'             => array(
                array(
                    'label'     => 'GLP-1 Guide',
                    'post_id'   => '681857',
                    'form_id'   => 'a623d51',
                    'attribute' => 'Lead Magnet List',
                    'event'     => 'LeadMagnet_Signup',
                    'source_id' => '1350522', // "Email Sign Up | Landing Page" — confirm welcome Journey with Paul
                    'enabled'   => 'yes',
                ),
            ),
            // Catalog Feed — product IDs checked for export to attentive-catalog.ndjson
            'catalog_products'         => array(),
        );

        $saved = get_option( self::OPTION_KEY, array() );
        return wp_parse_args( $saved, $defaults );
    }

    /**
     * Get single setting
     */
    public static function get( $key, $default = null ) {
        $settings = self::get_settings();
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Update settings
     */
    public static function update_settings( $settings ) {
        update_option( self::OPTION_KEY, $settings );
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting(
            'bh_attentive_settings_group',
            self::OPTION_KEY,
            array(
                'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
            )
        );
    }

    /**
     * Sanitize settings before saving
     */
    public static function sanitize_settings( $input ) {
        $sanitized = array();

        // General
        $sanitized['enabled']         = isset( $input['enabled'] ) ? 'yes' : 'no';
        $sanitized['logging_enabled'] = isset( $input['logging_enabled'] ) ? 'yes' : 'no';
        $sanitized['api_key']         = sanitize_text_field( $input['api_key'] ?? '' );
        $sanitized['api_base_url']    = esc_url_raw( $input['api_base_url'] ?? 'https://api.attentivemobile.com/v1' );

        // Subscriptions
        $sanitized['sign_up_source_id']      = sanitize_text_field( $input['sign_up_source_id'] ?? '' );
        $sanitized['marketing_source_id']    = sanitize_text_field( $input['marketing_source_id'] ?? '' );
        $sanitized['transactional_source_id']= sanitize_text_field( $input['transactional_source_id'] ?? '' );

        // Typeform
        $sanitized['webhook_secret']     = sanitize_text_field( $input['webhook_secret'] ?? '' );
        $sanitized['default_event_name'] = sanitize_text_field( $input['default_event_name'] ?? 'survey_completed' );
        $sanitized['phone_field_id']     = sanitize_text_field( $input['phone_field_id'] ?? '' );
        $sanitized['email_field_id']     = sanitize_text_field( $input['email_field_id'] ?? '' );

        $sanitized['field_mappings'] = array();
        if ( isset( $input['field_mappings'] ) && is_array( $input['field_mappings'] ) ) {
            foreach ( $input['field_mappings'] as $mapping ) {
                if ( ! empty( $mapping['typeform_field'] ) && ! empty( $mapping['attentive_attribute'] ) ) {
                    $sanitized['field_mappings'][] = array(
                        'typeform_field'      => sanitize_text_field( $mapping['typeform_field'] ),
                        'attentive_attribute' => sanitize_text_field( $mapping['attentive_attribute'] ),
                    );
                }
            }
        }

        // Frontend Trigger
        $sanitized['attentive_account']        = sanitize_text_field( $input['attentive_account'] ?? '' );
        $sanitized['mobile_creative_id']       = sanitize_text_field( $input['mobile_creative_id'] ?? '' );
        $sanitized['desktop_creative_id']      = sanitize_text_field( $input['desktop_creative_id'] ?? '' );
        $sanitized['footer_form_selector']     = sanitize_text_field( $input['footer_form_selector'] ?? '' );
        $sanitized['frontend_trigger_enabled'] = isset( $input['frontend_trigger_enabled'] ) ? 'yes' : 'no';
        $sanitized['trigger_pages']            = in_array( $input['trigger_pages'] ?? 'blog_only', array( 'all', 'blog_only' ) )
            ? $input['trigger_pages']
            : 'blog_only';

        // Lead Magnets — repeatable rows. Keep only rows that can identify a
        // form (a Page ID or an Elementor Form ID).
        $sanitized['lead_magnets'] = array();
        if ( isset( $input['lead_magnets'] ) && is_array( $input['lead_magnets'] ) ) {
            foreach ( $input['lead_magnets'] as $row ) {
                $post_id   = isset( $row['post_id'] ) ? absint( $row['post_id'] ) : 0;
                $form_id   = sanitize_text_field( $row['form_id'] ?? '' );
                $attribute = sanitize_text_field( $row['attribute'] ?? '' );
                $event     = sanitize_text_field( $row['event'] ?? '' );
                $source_id = sanitize_text_field( $row['source_id'] ?? '' );
                $label     = sanitize_text_field( $row['label'] ?? '' );

                // Need at least one way to match the form.
                if ( ! $post_id && empty( $form_id ) ) {
                    continue;
                }

                $sanitized['lead_magnets'][] = array(
                    'label'     => $label,
                    'post_id'   => $post_id ? (string) $post_id : '',
                    'form_id'   => $form_id,
                    'attribute' => $attribute,
                    'event'     => $event,
                    'source_id' => $source_id,
                    'enabled'   => ( isset( $row['enabled'] ) && $row['enabled'] === '1' ) ? 'yes' : 'no',
                );
            }
        }

        // Catalog Feed — checked product IDs
        $sanitized['catalog_products'] = array();
        if ( isset( $input['catalog_products'] ) && is_array( $input['catalog_products'] ) ) {
            $sanitized['catalog_products'] = array_values( array_unique( array_filter( array_map( 'absint', $input['catalog_products'] ) ) ) );
        }

        return $sanitized;
    }

    /**
     * Add admin menu page
     */
    public static function add_admin_page() {
        add_submenu_page(
            PARENT_MENU_SLUG,
            'Attentive',
            'Attentive',
            'manage_options',
            PARENT_MENU_SLUG . '--attentive',
            array( __CLASS__, 'render_admin_page' )
        );
    }

    /**
     * Products for the Catalog Feed checkbox table — empty array if
     * WooCommerce isn't active instead of a fatal error.
     */
    private static function get_selectable_products_or_empty() {
        if ( ! class_exists( 'BH_Attentive_Catalog_Feed' ) || ! function_exists( 'wc_get_product' ) ) {
            return array();
        }
        return BH_Attentive_Catalog_Feed::get_selectable_products();
    }

    /**
     * Generate webhook secret
     */
    public static function generate_webhook_secret() {
        return wp_generate_password( 32, false, false );
    }

    /**
     * Get webhook URL
     */
    public static function get_webhook_url() {
        return rest_url( 'bh/v1/typeform/webhook' );
    }

    /**
     * Render admin page
     */
    public static function render_admin_page() {
        $settings = self::get_settings();

        // Handle generate secret action (separate form, outside main settings form)
        if ( isset( $_POST['generate_secret'] ) && check_admin_referer( 'bh_attentive_generate_secret' ) ) {
            $settings['webhook_secret'] = self::generate_webhook_secret();
            self::update_settings( $settings );
            echo '<div class="notice notice-success is-dismissible"><p><strong>Webhook secret generated successfully.</strong></p></div>';
        }

        // Handle test connection (separate form, outside main settings form)
        $test_result = null;
        if ( isset( $_POST['test_connection'] ) && check_admin_referer( 'bh_attentive_test_connection' ) ) {
            $api_client  = new BH_Attentive_API_Client();
            $test_result = $api_client->test_connection();
        }

        // Handle "Check Subscription Eligibility" (read-only — sends nothing to
        // the customer, only asks Attentive whether they are already opted in).
        $eligibility_result = null; // [ 'phone' => .., 'email' => .., 'status' => 'subscribed'|'not_subscribed'|'unknown' ]
        if ( isset( $_POST['check_eligibility'] ) && check_admin_referer( 'bh_attentive_check_eligibility' ) ) {
            $elig_phone = isset( $_POST['elig_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['elig_phone'] ) ) : '';
            $elig_email = isset( $_POST['elig_email'] ) ? sanitize_email( wp_unslash( $_POST['elig_email'] ) ) : '';

            // Normalize phone the same way the live flow does, so the lookup matches.
            if ( ! empty( $elig_phone ) && class_exists( 'BH_Attentive_Helper' ) ) {
                $elig_phone = BH_Attentive_Helper::normalize_phone( $elig_phone );
            }

            if ( empty( $elig_phone ) && empty( $elig_email ) ) {
                $eligibility_result = array( 'status' => 'no_input' );
            } else {
                $is_sub = BH_Attentive_Helper::is_subscribed( $elig_phone, $elig_email );
                $eligibility_result = array(
                    'phone'  => $elig_phone,
                    'email'  => $elig_email,
                    'status' => ( $is_sub === true ) ? 'subscribed' : ( ( $is_sub === false ) ? 'not_subscribed' : 'unknown' ),
                );
            }
        }

        // Handle "Generate Catalog Feed" action (separate form, outside main settings form)
        $catalog_result = null;
        if ( isset( $_POST['generate_catalog_feed'] ) && check_admin_referer( 'bh_attentive_generate_catalog_feed' ) ) {
            $catalog_result = BH_Attentive_Catalog_Feed::generate();
        }

        // Active tab — persisted in URL only; does not affect what gets saved
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        $base_url   = admin_url( 'admin.php?page=' . PARENT_MENU_SLUG . '--attentive' );

        $tabs = array(
            'general'       => array( 'label' => 'General',          'icon' => 'dashicons-admin-settings' ),
            'subscriptions' => array( 'label' => 'Subscriptions',    'icon' => 'dashicons-groups' ),
            'typeform'      => array( 'label' => 'Typeform',         'icon' => 'dashicons-forms' ),
            'frontend'      => array( 'label' => 'Frontend Trigger', 'icon' => 'dashicons-admin-site' ),
            'leadmagnets'   => array( 'label' => 'Lead Magnets',     'icon' => 'dashicons-tag' ),
            'catalog'       => array( 'label' => 'Catalog Feed',     'icon' => 'dashicons-cart' ),
            'tools'         => array( 'label' => 'Tools',            'icon' => 'dashicons-admin-tools' ),
            'logs'          => array( 'label' => 'Logs',             'icon' => 'dashicons-list-view' ),
        );
        ?>
        <div class="wrap bh-attentive-wrap">

            <h1 style="display:flex;align-items:center;gap:10px;">
                <span class="dashicons dashicons-bell" style="font-size:28px;width:28px;height:28px;color:#7c3aed;"></span>
                Attentive Integration
            </h1>

            <?php if ( $test_result !== null ) : ?>
                <?php if ( is_wp_error( $test_result ) ) : ?>
                    <div class="notice notice-error is-dismissible">
                        <p><strong>Connection failed:</strong> <?php echo esc_html( $test_result->get_error_message() ); ?></p>
                    </div>
                <?php else : ?>
                    <div class="notice notice-success is-dismissible">
                        <p><strong>Connection successful.</strong> The API key is valid.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php /* ── Tab nav ── */ ?>
            <nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom:0;">
                <?php foreach ( $tabs as $slug => $tab ) : ?>
                    <a href="<?php echo esc_url( $base_url . '&tab=' . $slug ); ?>"
                       class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"
                              style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:4px;margin-top:-2px;"></span>
                        <?php echo esc_html( $tab['label'] ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div style="background:#fff;border:1px solid #c3c4c7;border-top:none;padding:24px 24px 8px;margin-bottom:20px;">

            <?php /* ══════════════════════════════════════════════════════════
                   SINGLE FORM — wraps General + Subscriptions + Typeform + Frontend
                   All fields are always rendered; tabs only toggle visibility.
                   This prevents partial saves from wiping other tabs' values.
               ══════════════════════════════════════════════════════════ */ ?>
            <?php if ( in_array( $active_tab, array( 'general', 'subscriptions', 'typeform', 'frontend', 'leadmagnets', 'catalog' ) ) ) : ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'bh_attentive_settings_group' ); ?>

                <?php /* Hidden inputs for ALL tabs — ensures every value
                        is always submitted regardless of which tab is visible.
                        Visible fields below override these when the user edits them. */ ?>
                <input type="hidden" name="<?php echo self::OPTION_KEY; ?>[enabled]"
                       value="<?php echo esc_attr( $settings['enabled'] === 'yes' ? '' : '' ); ?>">

                <?php /* ── TAB: GENERAL ── */ ?>
                <div id="bh-tab-general" <?php echo $active_tab !== 'general' ? 'style="display:none;"' : ''; ?>>

                    <p class="description" style="margin-bottom:18px;">
                        Master controls and API credentials for the Attentive integration.
                        The API key is required by every other tab — configure it here first.
                    </p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Enable Integration</th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo self::OPTION_KEY; ?>[enabled]"
                                           value="1"
                                           <?php checked( $settings['enabled'], 'yes' ); ?>>
                                    Enable the Attentive integration
                                </label>
                                <p class="description">
                                    When disabled, no events or subscriptions are sent to Attentive,
                                    regardless of other settings.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Enable Logging</th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo self::OPTION_KEY; ?>[logging_enabled]"
                                           value="1"
                                           <?php checked( $settings['logging_enabled'], 'yes' ); ?>>
                                    Log webhook activity and API calls
                                </label>
                                <p class="description">
                                    Logs appear in <strong>WooCommerce → Status → Logs</strong>
                                    and in the <em>Logs</em> tab. Recommended during setup;
                                    can be disabled in production to reduce noise.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">API Key</th>
                            <td>
                                <input type="password"
                                       name="<?php echo self::OPTION_KEY; ?>[api_key]"
                                       value="<?php echo esc_attr( $settings['api_key'] ); ?>"
                                       class="regular-text"
                                       autocomplete="new-password">
                                <p class="description">
                                    Bearer token from Attentive → <strong>Marketplace → API Keys</strong>.
                                    Used for all outbound API calls: subscriptions, custom events, attributes.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">API Base URL</th>
                            <td>
                                <input type="url"
                                       name="<?php echo self::OPTION_KEY; ?>[api_base_url]"
                                       value="<?php echo esc_attr( $settings['api_base_url'] ); ?>"
                                       class="regular-text">
                                <p class="description">
                                    Leave as <code>https://api.attentivemobile.com/v1</code> unless
                                    Attentive support instructs otherwise.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php /* ── TAB: SUBSCRIPTIONS ── */ ?>
                <div id="bh-tab-subscriptions" <?php echo $active_tab !== 'subscriptions' ? 'style="display:none;"' : ''; ?>>

                    <p class="description" style="margin-bottom:18px;">
                        Attentive requires a <strong>Signup Source ID</strong> to classify subscribers
                        and route them into the correct journey. Two separate IDs are used here —
                        one for marketing and one for transactional — so each subscriber type
                        receives only the messages they consented to.
                    </p>

                    <?php /* Marketing */ ?>
                    <div style="background:#f0fdf4;border-left:4px solid #16a34a;padding:16px 20px;border-radius:4px;margin-bottom:24px;">
                        <h3 style="margin:0 0 6px;color:#15803d;">
                            <span class="dashicons dashicons-megaphone" style="vertical-align:middle;margin-right:6px;"></span>
                            Marketing Signup Source
                        </h3>
                        <p style="margin:0 0 14px;color:#166534;font-size:13px;">
                            Used when a customer <strong>explicitly checks the marketing opt-in
                            checkbox</strong> at checkout. Subscribers created with this ID enter
                            the <em>Email | Welcome Flow</em> journey and can receive promotional
                            campaigns and re-engagement messages.<br><br>
                            <strong>Consent required:</strong> this source is only called when
                            <code>_bh_marketing_subscription = yes</code> is set on the order.
                        </p>
                        <table class="form-table" role="presentation" style="margin:0;">
                            <tr>
                                <th scope="row" style="width:220px;">Marketing Source ID</th>
                                <td>
                                    <input type="text"
                                           name="<?php echo self::OPTION_KEY; ?>[marketing_source_id]"
                                           value="<?php echo esc_attr( $settings['marketing_source_id'] ); ?>"
                                           class="regular-text"
                                           placeholder="e.g. 1334962">
                                    <p class="description">
                                        Provided by Attentive support — corresponds to the
                                        <code>signup_source_at_checkout</code> marketing unit.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php /* Transactional */ ?>
                    <div style="background:#eff6ff;border-left:4px solid #2563eb;padding:16px 20px;border-radius:4px;margin-bottom:24px;">
                        <h3 style="margin:0 0 6px;color:#1d4ed8;">
                            <span class="dashicons dashicons-email-alt" style="vertical-align:middle;margin-right:6px;"></span>
                            Transactional Signup Source
                        </h3>
                        <p style="margin:0 0 14px;color:#1e40af;font-size:13px;">
                            Used for <strong>every customer who places an order</strong>, regardless
                            of marketing opt-in. Creating a profile with this ID allows Attentive to
                            receive order-status custom events (e.g. <code>OrderStatus_Send_to_telegra</code>)
                            and send automated reminders — such as nudging customers to complete
                            their questionnaire.<br><br>
                            <strong>No checkbox required:</strong> transactional messages relate
                            directly to an existing order and do not require marketing consent.
                        </p>
                        <table class="form-table" role="presentation" style="margin:0;">
                            <tr>
                                <th scope="row" style="width:220px;">Transactional Source ID</th>
                                <td>
                                    <input type="text"
                                           name="<?php echo self::OPTION_KEY; ?>[transactional_source_id]"
                                           value="<?php echo esc_attr( $settings['transactional_source_id'] ); ?>"
                                           class="regular-text"
                                           placeholder="e.g. 1334963">
                                    <p class="description">
                                        Provided by Attentive support — corresponds to the
                                        transactional signup source for order notifications.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php /* Legacy field — hidden to preserve old value */ ?>
                    <input type="hidden"
                           name="<?php echo self::OPTION_KEY; ?>[sign_up_source_id]"
                           value="<?php echo esc_attr( $settings['sign_up_source_id'] ); ?>">

                </div>

                <?php /* ── TAB: TYPEFORM ── */ ?>
                <div id="bh-tab-typeform" <?php echo $active_tab !== 'typeform' ? 'style="display:none;"' : ''; ?>>

                    <p class="description" style="margin-bottom:18px;">
                        Settings for the Typeform → Attentive webhook pipeline. When a customer
                        completes the online questionnaire, Typeform posts the response to the
                        webhook URL below, which maps the fields and sends a custom event to Attentive.
                    </p>

                    <h3>Webhook</h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Webhook URL</th>
                            <td>
                                <code style="font-size:13px;"><?php echo esc_html( self::get_webhook_url() ); ?></code>
                                <p class="description">
                                    Paste this URL into your Typeform form's
                                    <strong>Connect → Webhooks</strong> section.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Webhook Secret</th>
                            <td>
                                <input type="text"
                                       name="<?php echo self::OPTION_KEY; ?>[webhook_secret]"
                                       value="<?php echo esc_attr( $settings['webhook_secret'] ); ?>"
                                       class="regular-text"
                                       readonly>
                                <p class="description">
                                    Generated in the <em>Tools</em> tab. Copy this value into
                                    Typeform's webhook secret field to verify incoming payloads.
                                </p>
                            </td>
                        </tr>
                    </table>

                    <h3>Field Mapping</h3>
                    <p class="description" style="margin-bottom:12px;">
                        Map Typeform field IDs to the customer's phone and email so they are
                        passed correctly to Attentive.
                    </p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Phone Field ID</th>
                            <td>
                                <input type="text"
                                       name="<?php echo self::OPTION_KEY; ?>[phone_field_id]"
                                       value="<?php echo esc_attr( $settings['phone_field_id'] ); ?>"
                                       class="regular-text"
                                       placeholder="e.g. phone_number">
                                <p class="description">
                                    The Typeform field reference that contains the customer's
                                    phone number in E.164 format.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Email Field ID</th>
                            <td>
                                <input type="text"
                                       name="<?php echo self::OPTION_KEY; ?>[email_field_id]"
                                       value="<?php echo esc_attr( $settings['email_field_id'] ); ?>"
                                       class="regular-text"
                                       placeholder="e.g. email_address">
                                <p class="description">
                                    The Typeform field reference that contains the customer's
                                    email address.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Default Event Name</th>
                            <td>
                                <input type="text"
                                       name="<?php echo self::OPTION_KEY; ?>[default_event_name]"
                                       value="<?php echo esc_attr( $settings['default_event_name'] ); ?>"
                                       class="regular-text"
                                       placeholder="survey_completed">
                                <p class="description">
                                    Custom event type sent to Attentive on each Typeform submission.
                                    Used to trigger journeys or build segments. Example: <code>survey_completed</code>.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php /* ── TAB: FRONTEND TRIGGER ── */ ?>
                <div id="bh-tab-frontend" <?php echo $active_tab !== 'frontend' ? 'style="display:none;"' : ''; ?>>

                    <p class="description" style="margin-bottom:18px;">
                        Controls the Attentive JavaScript SDK loaded on the front end and the
                        footer email form trigger. When enabled, submitting the footer email form
                        fires an Attentive sign-up unit popup to capture the visitor's consent.
                    </p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Enable Frontend Trigger</th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo self::OPTION_KEY; ?>[frontend_trigger_enabled]"
                                           value="1"
                                           <?php checked( $settings['frontend_trigger_enabled'], 'yes' ); ?>>
                                    Load the Attentive SDK and activate the footer form trigger
                                </label>
                                <p class="description">
                                    When enabled, <code>dtag.js</code> is injected into the page head
                                    and a submit listener is attached to the footer form selector below.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Attentive Account Name</th>
                            <td>
                                <input type="text"
                                       name="<?php echo self::OPTION_KEY; ?>[attentive_account]"
                                       value="<?php echo esc_attr( $settings['attentive_account'] ); ?>"
                                       class="regular-text"
                                       placeholder="e.g. brellohealth">
                                <p class="description">
                                    The subdomain from your CDN URL:
                                    <code>cdn.attn.tv/<strong>{account}</strong>/dtag.js</code>.
                                    Found in Attentive → Settings → Account.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Mobile Creative ID</th>
                            <td>
                                <input type="text"
                                       name="<?php echo self::OPTION_KEY; ?>[mobile_creative_id]"
                                       value="<?php echo esc_attr( $settings['mobile_creative_id'] ); ?>"
                                       class="regular-text"
                                       placeholder="e.g. 123456">
                                <p class="description">
                                    Sign-up Unit ID for screens narrower than 760 px.
                                    Found in Attentive → Sign-up Units → ID column.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Desktop Creative ID</th>
                            <td>
                                <input type="text"
                                       name="<?php echo self::OPTION_KEY; ?>[desktop_creative_id]"
                                       value="<?php echo esc_attr( $settings['desktop_creative_id'] ); ?>"
                                       class="regular-text"
                                       placeholder="e.g. 123457">
                                <p class="description">
                                    Sign-up Unit ID for screens 760 px wide or wider.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Footer Form Selector</th>
                            <td>
                                <input type="text"
                                       name="<?php echo self::OPTION_KEY; ?>[footer_form_selector]"
                                       value="<?php echo esc_attr( $settings['footer_form_selector'] ); ?>"
                                       class="regular-text"
                                       placeholder="e.g. #ap3w-embeddable-form-abc123">
                                <p class="description">
                                    CSS selector for the footer email signup form.
                                    The trigger listens for <code>submit</code> events on this element.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Trigger Pages</th>
                            <td>
                                <select name="<?php echo self::OPTION_KEY; ?>[trigger_pages]">
                                    <option value="all" <?php selected( $settings['trigger_pages'], 'all' ); ?>>All Pages</option>
                                    <option value="blog_only" <?php selected( $settings['trigger_pages'], 'blog_only' ); ?>>Blog Pages Only</option>
                                </select>
                                <p class="description">
                                    Restrict where the SDK and trigger script load.
                                    <em>Blog Pages Only</em> avoids conflicts with checkout or account pages.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php /* ── TAB: LEAD MAGNETS ── */ ?>
                <div id="bh-tab-leadmagnets" <?php echo $active_tab !== 'leadmagnets' ? 'style="display:none;"' : ''; ?>>

                    <p class="description" style="margin-bottom:18px;">
                        Connect Elementor landing-page forms to Attentive. For each landing page,
                        match it by its <strong>Page ID</strong> (or Elementor <strong>Form ID</strong> as a
                        fallback), then set the Attentive <strong>custom attribute</strong> and the
                        <strong>custom event</strong> to fire on submit. The event is what lets Attentive
                        trigger a welcome Journey. Add a row for every new lead-magnet form — no code changes needed.
                    </p>

                    <table class="widefat" id="bh-lead-magnets-table" style="margin-bottom:14px;">
                        <thead>
                            <tr>
                                <th style="width:55px;text-align:center;">Active</th>
                                <th>Label</th>
                                <th style="width:100px;">Page ID</th>
                                <th style="width:110px;">Form ID</th>
                                <th>Attribute</th>
                                <th>Event</th>
                                <th style="width:110px;">Source ID</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $magnets = $settings['lead_magnets'];
                            if ( empty( $magnets ) || ! is_array( $magnets ) ) {
                                $magnets = array( array() ); // one empty starter row
                            }
                            $lm_i = 0;
                            foreach ( $magnets as $m ) :
                            ?>
                            <tr class="bh-lm-row">
                                <td style="text-align:center;">
                                    <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[lead_magnets][<?php echo $lm_i; ?>][enabled]" value="1" <?php checked( ( $m['enabled'] ?? 'no' ), 'yes' ); ?>>
                                </td>
                                <td><input type="text" class="regular-text" name="<?php echo self::OPTION_KEY; ?>[lead_magnets][<?php echo $lm_i; ?>][label]" value="<?php echo esc_attr( $m['label'] ?? '' ); ?>" placeholder="GLP-1 Guide"></td>
                                <td><input type="text" style="width:90px;" name="<?php echo self::OPTION_KEY; ?>[lead_magnets][<?php echo $lm_i; ?>][post_id]" value="<?php echo esc_attr( $m['post_id'] ?? '' ); ?>" placeholder="681857"></td>
                                <td><input type="text" style="width:100px;" name="<?php echo self::OPTION_KEY; ?>[lead_magnets][<?php echo $lm_i; ?>][form_id]" value="<?php echo esc_attr( $m['form_id'] ?? '' ); ?>" placeholder="a623d51"></td>
                                <td><input type="text" class="regular-text" name="<?php echo self::OPTION_KEY; ?>[lead_magnets][<?php echo $lm_i; ?>][attribute]" value="<?php echo esc_attr( $m['attribute'] ?? '' ); ?>" placeholder="Lead Magnet List"></td>
                                <td><input type="text" class="regular-text" name="<?php echo self::OPTION_KEY; ?>[lead_magnets][<?php echo $lm_i; ?>][event]" value="<?php echo esc_attr( $m['event'] ?? '' ); ?>" placeholder="LeadMagnet_Signup"></td>
                                <td><input type="text" style="width:100px;" name="<?php echo self::OPTION_KEY; ?>[lead_magnets][<?php echo $lm_i; ?>][source_id]" value="<?php echo esc_attr( $m['source_id'] ?? '' ); ?>" placeholder="1350522"></td>
                                <td style="text-align:center;"><button type="button" class="button-link bh-lm-remove" title="Remove" style="color:#b32d2e;text-decoration:none;">&times;</button></td>
                            </tr>
                            <?php $lm_i++; endforeach; ?>
                        </tbody>
                    </table>

                    <button type="button" class="button button-secondary" id="bh-lm-add">+ Add Lead Magnet</button>

                    <p class="description" style="margin-top:12px;">
                        <strong>Page ID</strong> — the WordPress page ID the form lives on (e.g. <code>681857</code>).
                        <strong>Form ID</strong> — the Elementor form's element ID (e.g. <code>a623d51</code>), used only if Page ID is empty.
                        A row needs at least one of the two. Leave <strong>Event</strong> blank to only set the attribute (no welcome Journey).
                        <strong>Source ID</strong> — the Attentive <em>email</em> Sign-up Source ID (e.g. <code>1350522</code>, from Growth → Sign-up units).
                        Required for the subscription to succeed and to trigger that source's welcome Journey; if left blank, the Marketing Source ID from the Subscriptions tab is used.
                    </p>

                    <script>
                    (function(){
                        var table = document.getElementById('bh-lead-magnets-table');
                        if ( ! table ) { return; }
                        var tbody  = table.querySelector('tbody');
                        var addBtn = document.getElementById('bh-lm-add');
                        var optKey = '<?php echo esc_js( self::OPTION_KEY ); ?>';

                        addBtn.addEventListener('click', function(){
                            var idx = tbody.querySelectorAll('tr.bh-lm-row').length;
                            var base = optKey + '[lead_magnets][' + idx + ']';
                            var tr = document.createElement('tr');
                            tr.className = 'bh-lm-row';
                            tr.innerHTML =
                                '<td style="text-align:center;"><input type="checkbox" name="' + base + '[enabled]" value="1" checked></td>' +
                                '<td><input type="text" class="regular-text" name="' + base + '[label]" placeholder="GLP-1 Guide"></td>' +
                                '<td><input type="text" style="width:90px;" name="' + base + '[post_id]" placeholder="681857"></td>' +
                                '<td><input type="text" style="width:100px;" name="' + base + '[form_id]" placeholder="a623d51"></td>' +
                                '<td><input type="text" class="regular-text" name="' + base + '[attribute]" placeholder="Lead Magnet List"></td>' +
                                '<td><input type="text" class="regular-text" name="' + base + '[event]" placeholder="LeadMagnet_Signup"></td>' +
                                '<td><input type="text" style="width:100px;" name="' + base + '[source_id]" placeholder="1350522"></td>' +
                                '<td style="text-align:center;"><button type="button" class="button-link bh-lm-remove" title="Remove" style="color:#b32d2e;text-decoration:none;">&times;</button></td>';
                            tbody.appendChild(tr);
                        });

                        tbody.addEventListener('click', function(e){
                            if ( e.target && e.target.classList.contains('bh-lm-remove') ) {
                                var row = e.target.closest('tr.bh-lm-row');
                                if ( row ) { row.remove(); }
                            }
                        });
                    })();
                    </script>
                </div>

                <?php /* ── TAB: CATALOG FEED ── */ ?>
                <div id="bh-tab-catalog" <?php echo $active_tab !== 'catalog' ? 'style="display:none;"' : ''; ?>>

                    <p class="description" style="margin-bottom:18px;">
                        Check every product to include in <code>attentive-catalog.ndjson</code>, the feed
                        Attentive polls for product recommendations and Text-to-Buy. The store also has
                        test copies, drafts and one-off offers — only checked products are exported, so
                        nothing goes out unless it's picked here. Check the boxes, click
                        <strong>Save Settings</strong>, then switch to the <em>Generate</em> section below
                        to build the file.
                    </p>

                    <input type="text" id="bh-catalog-filter" class="regular-text"
                           placeholder="Filter by name, SKU or ID..." style="margin-bottom:10px;max-width:320px;">

                    <style>
                        #bh-catalog-products-table tr.bh-row-selected { background-color: #eef6ff; }
                        #bh-catalog-products-table tr.bh-row-selected:hover { background-color: #e0eefc; }
                    </style>

                    <table class="widefat striped" id="bh-catalog-products-table">
                        <thead>
                            <tr>
                                <th style="width:40px;text-align:center;">
                                    <input type="checkbox" id="bh-catalog-select-all" title="Select all visible">
                                </th>
                                <th>Name</th>
                                <th style="width:110px;">ID / SKU</th>
                                <th style="width:100px;">Type</th>
                                <th style="width:90px;">Status</th>
                                <th style="width:110px;">Price</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $catalog_products  = self::get_selectable_products_or_empty();
                            $checked_ids       = array_map( 'strval', $settings['catalog_products'] );
                            if ( empty( $catalog_products ) ) :
                            ?>
                                <tr><td colspan="7"><em>No WooCommerce products found, or WooCommerce is not active.</em></td></tr>
                            <?php else : foreach ( $catalog_products as $p ) :
                                $pid      = (string) $p->get_id();
                                $sku      = $p->get_sku();
                                $checked  = in_array( $pid, $checked_ids, true );
                                $edit_url = get_edit_post_link( $p->get_id(), 'raw' );
                            ?>
                                <tr data-search="<?php echo esc_attr( strtolower( $p->get_name() . ' ' . $sku . ' ' . $pid ) ); ?>"
                                    class="<?php echo $checked ? 'bh-row-selected' : ''; ?>">
                                    <td style="text-align:center;">
                                        <input type="checkbox" class="bh-catalog-checkbox"
                                               name="<?php echo self::OPTION_KEY; ?>[catalog_products][]"
                                               value="<?php echo esc_attr( $pid ); ?>"
                                               <?php checked( $checked ); ?>>
                                    </td>
                                    <td><?php echo esc_html( $p->get_name() ); ?></td>
                                    <td><?php echo esc_html( $sku !== '' ? $sku : $pid ); ?></td>
                                    <td><?php echo esc_html( $p->get_type() ); ?></td>
                                    <td><?php echo esc_html( $p->get_status() ); ?></td>
                                    <td><?php echo wp_kses_post( $p->get_price_html() ); ?></td>
                                    <td>
                                        <?php if ( $edit_url ) : ?>
                                            <a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" rel="noopener noreferrer" title="Edit product">
                                                <span class="dashicons dashicons-edit"></span>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>

                    <script>
                    (function(){
                        var filter = document.getElementById('bh-catalog-filter');
                        var table  = document.getElementById('bh-catalog-products-table');
                        var selAll = document.getElementById('bh-catalog-select-all');
                        if ( ! table ) { return; }
                        var rows = table.querySelectorAll('tbody tr[data-search]');

                        function setRowHighlight( cb ) {
                            var row = cb.closest('tr');
                            if ( row ) { row.classList.toggle('bh-row-selected', cb.checked); }
                        }

                        rows.forEach(function(row){
                            var cb = row.querySelector('.bh-catalog-checkbox');
                            if ( cb ) {
                                cb.addEventListener('change', function(){ setRowHighlight(cb); });
                            }
                        });

                        if ( filter ) {
                            filter.addEventListener('input', function(){
                                var term = filter.value.toLowerCase();
                                rows.forEach(function(row){
                                    row.style.display = row.getAttribute('data-search').indexOf(term) !== -1 ? '' : 'none';
                                });
                            });
                        }

                        if ( selAll ) {
                            selAll.addEventListener('change', function(){
                                rows.forEach(function(row){
                                    if ( row.style.display === 'none' ) { return; }
                                    var cb = row.querySelector('.bh-catalog-checkbox');
                                    if ( cb ) { cb.checked = selAll.checked; setRowHighlight(cb); }
                                });
                            });
                        }
                    })();
                    </script>
                </div>

                <?php /* ── Save button — visible on all editable tabs ── */ ?>
                <?php if ( in_array( $active_tab, array( 'general', 'subscriptions', 'typeform', 'frontend', 'leadmagnets', 'catalog' ) ) ) : ?>
                    <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e5e7eb;">
                        <?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?>
                        <span style="margin-left:10px;color:#6b7280;font-size:13px;">
                            All settings are saved together — switching tabs will not lose your changes.
                        </span>
                    </div>
                <?php endif; ?>

            </form>

            <?php endif; /* end editable tabs form */ ?>

            <?php /* ══════════════════════════════════════════════════════════
                   TAB: CATALOG FEED — generate action (not a setting, separate form)
               ══════════════════════════════════════════════════════════ */ ?>
            <?php if ( $active_tab === 'catalog' ) : ?>

                <div style="border-top:1px solid #e5e7eb;margin-top:8px;padding-top:20px;">
                    <h3 style="margin-top:0;">Generate Feed</h3>

                    <?php if ( $catalog_result !== null ) : ?>
                        <?php if ( $catalog_result['success'] ) : ?>
                            <div class="notice notice-success is-dismissible">
                                <p><strong><?php echo esc_html( $catalog_result['message'] ); ?></strong></p>
                            </div>
                        <?php else : ?>
                            <div class="notice notice-error is-dismissible">
                                <p><strong>Could not generate the feed:</strong> <?php echo esc_html( $catalog_result['message'] ); ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form method="post">
                        <?php wp_nonce_field( 'bh_attentive_generate_catalog_feed' ); ?>
                        <button type="submit" name="generate_catalog_feed" class="button button-primary">
                            Generate Catalog Feed Now
                        </button>
                        <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url( $base_url . '&tab=catalog' ); ?>">
                    </form>

                    <p class="description" style="margin-top:10px;">
                        Rebuilds <code>attentive-catalog.ndjson</code> from the products checked above
                        (using whatever was last saved). Nothing is sent to Attentive by this button —
                        Attentive polls the feed URL below on its own schedule. The file lives under
                        <code>wp-content/uploads/bh-exports/</code> (not inside the plugin folder), so it
                        survives plugin updates and doesn't need special permissions beyond what uploads
                        already has.
                    </p>

                    <?php $file_info = BH_Attentive_Catalog_Feed::get_file_info(); ?>
                    <table class="form-table" role="presentation" style="margin-top:10px;">
                        <tr>
                            <th scope="row">Feed URL</th>
                            <td>
                                <code style="font-size:13px;"><?php echo esc_html( BH_Attentive_Catalog_Feed::get_feed_url() ); ?></code>
                                <?php if ( $file_info ) : ?>
                                    <a href="<?php echo esc_url( BH_Attentive_Catalog_Feed::get_feed_url() ); ?>"
                                       class="button button-small" target="_blank" rel="noopener noreferrer" style="margin-left:8px;">
                                        <span class="dashicons dashicons-download" style="vertical-align:middle;font-size:16px;"></span> Download
                                    </a>
                                <?php else : ?>
                                    <span class="description" style="margin-left:8px;">(not generated yet)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Last Generated</th>
                            <td>
                                <?php
                                $last = get_option( BH_Attentive_Catalog_Feed::LAST_GENERATED_OPTION );
                                echo $last
                                    ? esc_html( date_i18n( 'Y-m-d H:i:s', $last ) )
                                    : '<em>Never</em>';
                                ?>
                            </td>
                        </tr>
                        <?php if ( $file_info ) : ?>
                        <tr>
                            <th scope="row">File Size</th>
                            <td><?php echo esc_html( $file_info['size'] ); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th scope="row">Products Checked</th>
                            <td><?php echo esc_html( count( $settings['catalog_products'] ) ); ?></td>
                        </tr>
                    </table>
                </div>

            <?php endif; ?>

            <?php /* ══════════════════════════════════════════════════════════
                   TAB: TOOLS — separate forms (actions, not settings)
               ══════════════════════════════════════════════════════════ */ ?>
            <?php if ( $active_tab === 'tools' ) : ?>

                <p class="description" style="margin-bottom:18px;">
                    Utility actions for the Attentive integration. These do not modify saved settings.
                </p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Test API Connection</th>
                        <td>
                            <form method="post">
                                <?php wp_nonce_field( 'bh_attentive_test_connection' ); ?>
                                <button type="submit" name="test_connection" class="button button-secondary">
                                    Test Attentive API Connection
                                </button>
                            </form>
                            <p class="description" style="margin-top:6px;">
                                Sends a <code>GET /me</code> request to Attentive using the API key
                                configured in the General tab. A success message confirms the key is valid.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Check Subscription Eligibility</th>
                        <td>
                            <form method="post" style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                <?php wp_nonce_field( 'bh_attentive_check_eligibility' ); ?>
                                <input type="text" name="elig_phone" class="regular-text" style="max-width:180px;"
                                       placeholder="Phone e.g. (555) 123-4567"
                                       value="<?php echo isset( $eligibility_result['phone'] ) ? esc_attr( $eligibility_result['phone'] ) : ''; ?>">
                                <input type="email" name="elig_email" class="regular-text" style="max-width:220px;"
                                       placeholder="or Email"
                                       value="<?php echo isset( $eligibility_result['email'] ) ? esc_attr( $eligibility_result['email'] ) : ''; ?>">
                                <button type="submit" name="check_eligibility" class="button button-secondary">
                                    Check Eligibility
                                </button>
                            </form>

                            <?php if ( is_array( $eligibility_result ) ) : ?>
                                <?php if ( $eligibility_result['status'] === 'no_input' ) : ?>
                                    <p style="margin-top:10px;color:#b45309;">
                                        <span class="dashicons dashicons-warning" style="vertical-align:middle;"></span>
                                        Enter a phone number or email to check.
                                    </p>
                                <?php elseif ( $eligibility_result['status'] === 'subscribed' ) : ?>
                                    <p style="margin-top:10px;color:#15803d;">
                                        <span class="dashicons dashicons-yes-alt" style="vertical-align:middle;"></span>
                                        <strong>Already subscribed.</strong> The live flow will <strong>skip</strong> the
                                        subscribe call for this user — so no "You are already subscribed" SMS is sent.
                                    </p>
                                <?php elseif ( $eligibility_result['status'] === 'not_subscribed' ) : ?>
                                    <p style="margin-top:10px;color:#1d4ed8;">
                                        <span class="dashicons dashicons-info" style="vertical-align:middle;"></span>
                                        <strong>Not subscribed.</strong> The live flow will subscribe this user normally.
                                    </p>
                                <?php else : ?>
                                    <p style="margin-top:10px;color:#b45309;">
                                        <span class="dashicons dashicons-warning" style="vertical-align:middle;"></span>
                                        <strong>Could not determine</strong> (API error or API key missing). The live flow
                                        fails safe and subscribes as before. See <strong>WooCommerce → Status → Logs</strong>
                                        (source <code>bh-attentive</code>) for the raw response.
                                    </p>
                                <?php endif; ?>
                            <?php endif; ?>

                            <p class="description" style="margin-top:6px;">
                                Read-only check — sends a <code>GET /subscriptions</code> to Attentive and reports whether
                                the user is already opted in. <strong>Nothing is sent to the customer</strong> (no SMS, no subscribe).
                                Safe to run against a real customer. The full parsed result (with subscription count and raw
                                response) is logged to <strong>WooCommerce → Status → Logs</strong> (source <code>bh-attentive</code>).
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Generate Webhook Secret</th>
                        <td>
                            <form method="post">
                                <?php wp_nonce_field( 'bh_attentive_generate_secret' ); ?>
                                <button type="submit" name="generate_secret" class="button button-secondary">
                                    Generate New Webhook Secret
                                </button>
                            </form>
                            <p class="description" style="margin-top:6px;">
                                Generates a new 32-character random secret and saves it immediately.
                                Copy the new value from the Typeform tab and update it in Typeform's
                                webhook settings to keep signature verification working.
                            </p>
                        </td>
                    </tr>
                </table>

            <?php endif; ?>

            <?php /* ══════════════════════════════════════════════════════════
                   TAB: LOGS — read-only, outside any form
               ══════════════════════════════════════════════════════════ */ ?>
            <?php if ( $active_tab === 'logs' ) : ?>

                <p class="description" style="margin-bottom:18px;">
                    Recent webhook activity logged by the integration.
                    Detailed API call logs are also available in
                    <strong>WooCommerce → Status → Logs</strong> (source: <code>bh-attentive</code>).
                </p>

                <?php
                $logs = BH_Attentive_Logger::get_recent_logs( 20 );
                if ( empty( $logs ) ) :
                ?>
                    <p><em>No webhook logs yet.</em></p>
                <?php else : ?>
                    <table class="widefat striped" style="margin-top:8px;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Form ID</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $logs as $log ) : ?>
                            <tr>
                                <td><?php echo esc_html( $log->created_at ); ?></td>
                                <td><?php echo esc_html( $log->form_id ); ?></td>
                                <td>
                                    <?php
                                    $phone = $log->phone_number ?? '';
                                    echo esc_html( $phone ? '****' . substr( $phone, -4 ) : '-' );
                                    ?>
                                </td>
                                <td>
                                    <?php if ( $log->status === 'success' ) : ?>
                                        <span style="color:#16a34a;">
                                            <span class="dashicons dashicons-yes-alt" style="vertical-align:middle;"></span>
                                            success
                                        </span>
                                    <?php else : ?>
                                        <span style="color:#dc2626;">
                                            <span class="dashicons dashicons-dismiss" style="vertical-align:middle;"></span>
                                            <?php echo esc_html( $log->status ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $log->error_message ?: '-' ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            <?php endif; ?>

            </div><?php /* panel */ ?>
        </div><?php /* .wrap */ ?>
        <?php
    }
}