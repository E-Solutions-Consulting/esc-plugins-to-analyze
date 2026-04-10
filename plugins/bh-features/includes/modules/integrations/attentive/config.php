<?php
/**
 * Attentive Integration - Configuration & Admin Page
 * 
 * Handles settings storage and admin interface for the integration.
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
            'enabled'            => 'no',
            'api_key'            => '',
            'api_base_url'       => 'https://api.attentivemobile.com/v1',
            'sign_up_source_id'  => '',
            'webhook_secret'     => '',
            'logging_enabled'    => 'yes',
            'default_event_name' => 'survey_completed',
            'phone_field_id'     => '',
            'email_field_id'     => '',
            'field_mappings'     => array(),
            // Frontend Trigger Settings
            'attentive_account'       => '',
            'mobile_creative_id'      => '',
            'desktop_creative_id'     => '',
            'footer_form_selector'    => '',
            'frontend_trigger_enabled'=> 'no',
            'trigger_pages'           => 'blog_only', // 'all' or 'blog_only'
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
        
        $sanitized['enabled'] = isset( $input['enabled'] ) ? 'yes' : 'no';
        $sanitized['api_key'] = sanitize_text_field( $input['api_key'] ?? '' );
        $sanitized['api_base_url'] = esc_url_raw( $input['api_base_url'] ?? 'https://api.attentivemobile.com/v1' );
        $sanitized['sign_up_source_id'] = sanitize_text_field( $input['sign_up_source_id'] ?? '' );
        $sanitized['webhook_secret'] = sanitize_text_field( $input['webhook_secret'] ?? '' );
        $sanitized['logging_enabled'] = isset( $input['logging_enabled'] ) ? 'yes' : 'no';
        $sanitized['default_event_name'] = sanitize_text_field( $input['default_event_name'] ?? 'survey_completed' );
        $sanitized['phone_field_id'] = sanitize_text_field( $input['phone_field_id'] ?? '' );
        $sanitized['email_field_id'] = sanitize_text_field( $input['email_field_id'] ?? '' );
        
        // Sanitize field mappings array
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
        
        // Frontend Trigger Settings
        $sanitized['attentive_account'] = sanitize_text_field( $input['attentive_account'] ?? 'hellowellness' );
        $sanitized['mobile_creative_id'] = sanitize_text_field( $input['mobile_creative_id'] ?? '' );
        $sanitized['desktop_creative_id'] = sanitize_text_field( $input['desktop_creative_id'] ?? '' );
        $sanitized['footer_form_selector'] = sanitize_text_field( $input['footer_form_selector'] ?? '' );
        $sanitized['frontend_trigger_enabled'] = isset( $input['frontend_trigger_enabled'] ) ? 'yes' : 'no';
        $sanitized['trigger_pages'] = in_array( $input['trigger_pages'] ?? 'all', array( 'all', 'blog_only' ) ) 
            ? $input['trigger_pages'] 
            : 'all';
        
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
        
        // Handle generate secret action
        if ( isset( $_POST['generate_secret'] ) && check_admin_referer( 'bh_attentive_generate_secret' ) ) {
            $settings['webhook_secret'] = self::generate_webhook_secret();
            self::update_settings( $settings );
            echo '<div class="notice notice-success"><p>Webhook secret generated successfully!</p></div>';
        }
        
        // Handle test connection
        $test_result = null;
        if ( isset( $_POST['test_connection'] ) && check_admin_referer( 'bh_attentive_test_connection' ) ) {
            $api_client = new BH_Attentive_API_Client();
            $test_result = $api_client->test_connection();
        }
        ?>
        <div class="wrap">
            <h1>Typeform x Attentive Integration</h1>
            
            <?php if ( $test_result !== null ): ?>
                <?php if ( is_wp_error( $test_result ) ): ?>
                    <div class="notice notice-error">
                        <p><strong>Connection Failed:</strong> <?php echo esc_html( $test_result->get_error_message() ); ?></p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-success">
                        <p><strong>Connection Successful!</strong> API key is valid.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'bh_attentive_settings_group' ); ?>
                
                <h2>General Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Integration</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[enabled]" value="1" <?php checked( $settings['enabled'], 'yes' ); ?>>
                                Enable Typeform x Attentive integration
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enable Logging</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[logging_enabled]" value="1" <?php checked( $settings['logging_enabled'], 'yes' ); ?>>
                                Log webhook activity and API calls
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h2>Attentive API Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="password" name="<?php echo self::OPTION_KEY; ?>[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>" class="regular-text">
                            <p class="description">Bearer token from Attentive platform</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">API Base URL</th>
                        <td>
                            <input type="url" name="<?php echo self::OPTION_KEY; ?>[api_base_url]" value="<?php echo esc_attr( $settings['api_base_url'] ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sign-up Source ID</th>
                        <td>
                            <input type="text" name="<?php echo self::OPTION_KEY; ?>[sign_up_source_id]" value="<?php echo esc_attr( $settings['sign_up_source_id'] ); ?>" class="regular-text">
                            <p class="description">Found in Attentive → Sign-up Units tab → ID column</p>
                        </td>
                    </tr>
                </table>
                
                <h2>Typeform Webhook Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Webhook URL</th>
                        <td>
                            <code><?php echo esc_html( self::get_webhook_url() ); ?></code>
                            <p class="description">Add this URL to your Typeform webhook settings</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Webhook Secret</th>
                        <td>
                            <input type="text" name="<?php echo self::OPTION_KEY; ?>[webhook_secret]" value="<?php echo esc_attr( $settings['webhook_secret'] ); ?>" class="regular-text" readonly>
                        </td>
                    </tr>
                </table>
                
                <h2>Field Mapping</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Phone Field ID</th>
                        <td>
                            <input type="text" name="<?php echo self::OPTION_KEY; ?>[phone_field_id]" value="<?php echo esc_attr( $settings['phone_field_id'] ); ?>" class="regular-text">
                            <p class="description">Typeform field ID for phone number</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Email Field ID</th>
                        <td>
                            <input type="text" name="<?php echo self::OPTION_KEY; ?>[email_field_id]" value="<?php echo esc_attr( $settings['email_field_id'] ); ?>" class="regular-text">
                            <p class="description">Typeform field ID for email</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Event Name</th>
                        <td>
                            <input type="text" name="<?php echo self::OPTION_KEY; ?>[default_event_name]" value="<?php echo esc_attr( $settings['default_event_name'] ); ?>" class="regular-text">
                            <p class="description">Custom event name sent to Attentive (e.g., survey_completed)</p>
                        </td>
                    </tr>
                </table>
                
                <h2>Frontend Trigger Settings</h2>
                <p class="description">Configure the footer email signup to trigger Attentive sign-up units.</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Frontend Trigger</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[frontend_trigger_enabled]" value="1" <?php checked( $settings['frontend_trigger_enabled'], 'yes' ); ?>>
                                Enable footer form trigger (loads Attentive SDK and triggers on email submit)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Attentive Account Name</th>
                        <td>
                            <input type="text" name="<?php echo self::OPTION_KEY; ?>[attentive_account]" value="<?php echo esc_attr( $settings['attentive_account'] ); ?>" class="regular-text">
                            <p class="description">Account name from CDN URL (e.g., brellohealth from cdn.attn.tv/brellohealth/dtag.js)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Mobile Creative ID</th>
                        <td>
                            <input type="text" name="<?php echo self::OPTION_KEY; ?>[mobile_creative_id]" value="<?php echo esc_attr( $settings['mobile_creative_id'] ); ?>" class="regular-text">
                            <p class="description">Sign-up Unit ID for mobile devices (fullscreen popup)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Desktop Creative ID</th>
                        <td>
                            <input type="text" name="<?php echo self::OPTION_KEY; ?>[desktop_creative_id]" value="<?php echo esc_attr( $settings['desktop_creative_id'] ); ?>" class="regular-text">
                            <p class="description">Sign-up Unit ID for desktop devices (fullscreen popup)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Footer Form Selector</th>
                        <td>
                            <input type="text" name="<?php echo self::OPTION_KEY; ?>[footer_form_selector]" value="<?php echo esc_attr( $settings['footer_form_selector'] ); ?>" class="regular-text">
                            <p class="description">CSS selector for the footer email form (e.g., #ap3w-embeddable-form-...)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Trigger Pages</th>
                        <td>
                            <select name="<?php echo self::OPTION_KEY; ?>[trigger_pages]">
                                <option value="all" <?php selected( $settings['trigger_pages'], 'all' ); ?>>All Pages</option>
                                <option value="blog_only" <?php selected( $settings['trigger_pages'], 'blog_only' ); ?>>Blog Pages Only</option>
                            </select>
                            <p class="description">Where should the Attentive trigger load?</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button( 'Save Settings' ); ?>
            </form>
            
            <hr>
            
            <h2>Tools</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Generate New Secret</th>
                    <td>
                        <form method="post">
                            <?php wp_nonce_field( 'bh_attentive_generate_secret' ); ?>
                            <button type="submit" name="generate_secret" class="button">Generate New Webhook Secret</button>
                        </form>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Test Connection</th>
                    <td>
                        <form method="post">
                            <?php wp_nonce_field( 'bh_attentive_test_connection' ); ?>
                            <button type="submit" name="test_connection" class="button">Test Attentive API Connection</button>
                        </form>
                    </td>
                </tr>
            </table>
            
            <hr>
            
            <h2>Recent Webhook Logs</h2>
            <?php
            $logs = BH_Attentive_Logger::get_recent_logs( 20 );
            if ( empty( $logs ) ):
            ?>
                <p>No webhook logs yet.</p>
            <?php else: ?>
                <table class="widefat striped">
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
                        <?php foreach ( $logs as $log ): ?>
                        <tr>
                            <td><?php echo esc_html( $log->created_at ); ?></td>
                            <td><?php echo esc_html( $log->form_id ); ?></td>
                            <td><?php echo esc_html( substr( $log->phone_number, -4 ) ? '****' . substr( $log->phone_number, -4 ) : '-' ); ?></td>
                            <td>
                                <span class="<?php echo $log->status === 'success' ? 'dashicons dashicons-yes-alt' : 'dashicons dashicons-dismiss'; ?>" style="color: <?php echo $log->status === 'success' ? 'green' : 'red'; ?>;"></span>
                                <?php echo esc_html( $log->status ); ?>
                            </td>
                            <td><?php echo esc_html( $log->error_message ?: '-' ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
