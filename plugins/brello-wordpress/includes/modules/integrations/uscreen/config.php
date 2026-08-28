<?php
/**
 * Uscreen integration config.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Uscreen_Config {

    const OPTION_KEY = 'ah_uscreen_settings';

    /**
     * Initialize config: settings + admin page.
     */
    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 50 );
    }

    /**
     * Get all settings merged with defaults.
     *
     * @return array
     */
    public static function get_settings() {
        $defaults = array(
            'api_key'              => 'WxafbCzvCXhg2JhESNB9i9hd',
            'base_url'             => 'https://www.uscreen.io/publisher_api/v1/',
            'offer_id'             => '222626',
            'account_creation_mode'=> 'sso_only', // sso_only | password_silent | password_email
            'logging_enabled'      => 'yes',      // yes | no
        );

        $saved = get_option( self::OPTION_KEY, array() );

        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        return wp_parse_args( $saved, $defaults );
    }

    /**
     * Get single setting key.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public static function get( $key, $default = null ) {
        $settings = self::get_settings();

        if ( array_key_exists( $key, $settings ) ) {
            return $settings[ $key ];
        }

        return $default;
    }

    /**
     * Update settings array.
     *
     * @param array $settings
     */
    public static function update_settings( array $settings ) {
        update_option( self::OPTION_KEY, $settings );
    }

    /**
     * Register settings and fields.
     */
    public static function register_settings() {
        register_setting(
            'ah_uscreen_settings_group',
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
                'default'           => array(),
            )
        );

        add_settings_section(
            'ah_uscreen_main_section',
            __( 'Uscreen Integration Settings', 'ah-uscreen' ),
            '__return_false',
            'ah-uscreen-settings'
        );

        // API Key.
        add_settings_field(
            'ah_uscreen_api_key',
            __( 'Uscreen API Key', 'ah-uscreen' ),
            array( __CLASS__, 'render_field_api_key' ),
            'ah-uscreen-settings',
            'ah_uscreen_main_section'
        );

        // Base URL.
        add_settings_field(
            'ah_uscreen_base_url',
            __( 'Base URL', 'ah-uscreen' ),
            array( __CLASS__, 'render_field_base_url' ),
            'ah-uscreen-settings',
            'ah_uscreen_main_section'
        );

        // Offer ID.
        add_settings_field(
            'ah_uscreen_offer_id',
            __( 'Offer / Plan ID', 'ah-uscreen' ),
            array( __CLASS__, 'render_field_offer_id' ),
            'ah-uscreen-settings',
            'ah_uscreen_main_section'
        );

        // Account creation mode.
        add_settings_field(
            'ah_uscreen_account_creation_mode',
            __( 'Account Creation Mode', 'ah-uscreen' ),
            array( __CLASS__, 'render_field_account_creation_mode' ),
            'ah-uscreen-settings',
            'ah_uscreen_main_section'
        );

        // Logging.
        add_settings_field(
            'ah_uscreen_logging_enabled',
            __( 'Logging', 'ah-uscreen' ),
            array( __CLASS__, 'render_field_logging_enabled' ),
            'ah-uscreen-settings',
            'ah_uscreen_main_section'
        );

    }

    /**
     * Sanitize settings before saving.
     *
     * @param array $input
     *
     * @return array
     */
    public static function sanitize_settings( $input ) {
        $input = is_array( $input ) ? $input : array();
        $current = self::get_settings();

        $sanitized = array();

        $sanitized['api_key'] = isset( $input['api_key'] )
            ? trim( (string) $input['api_key'] )
            : $current['api_key'];

        $sanitized['base_url'] = isset( $input['base_url'] )
            ? esc_url_raw( trim( (string) $input['base_url'] ) )
            : $current['base_url'];

        $sanitized['offer_id'] = isset( $input['offer_id'] )
            ? sanitize_text_field( $input['offer_id'] )
            : $current['offer_id'];

        $allowed_modes = array( 'sso_only', 'password_silent', 'password_email' );
        $mode = isset( $input['account_creation_mode'] )
            ? (string) $input['account_creation_mode']
            : $current['account_creation_mode'];
        $sanitized['account_creation_mode'] = in_array( $mode, $allowed_modes, true ) ? $mode : 'sso_only';

        $sanitized['logging_enabled'] = ( isset( $input['logging_enabled'] ) && 'yes' === $input['logging_enabled'] )
            ? 'yes'
            : 'no';

        return $sanitized;
    }

    /**
     * Register admin page under BH Features / Integrations.
     */
    public static function register_admin_page() {
        /**
         * Filter parent slug in case you need to change menu structure.
         *
         * Default assumes an existing BH Features parent menu.
         */
        $parent_slug = apply_filters( 'ah_uscreen_admin_parent_slug', PARENT_MENU_SLUG );

        // Fallback to Settings if custom parent does not exist.
        global $submenu;
        if ( empty( $submenu[ $parent_slug ] ) && ! menu_page_url( $parent_slug, false ) ) {
            $parent_slug = 'options-general.php';
        }

        add_submenu_page(
            $parent_slug,
            __( 'BrelloRise', 'ah-uscreen' ),
            __( 'BrelloRise', 'ah-uscreen' ),
            'manage_options',
            PARENT_MENU_SLUG . '--brellorise',
            array( __CLASS__, 'render_settings_page' ),
            'dashicons-admin-site',
        );
    }

    /**
     * Render settings page wrapper.
     */
    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = self::get_settings();

        // Test connection handler.
        $test_result = null;
        if ( isset( $_POST['ah_uscreen_test_connection'] ) && check_admin_referer( 'ah_uscreen_test_connection_action', 'ah_uscreen_test_connection_nonce' ) ) {
            if ( class_exists( 'AH_Uscreen_Client' ) ) {
                $test_result = AH_Uscreen_Client::test_connection();
            }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Uscreen Integration', 'ah-uscreen' ); ?></h1>

            <?php if ( $test_result instanceof WP_Error ) : ?>
                <div class="notice notice-error">
                    <p>
                        <?php
                        printf(
                            /* translators: %s is WP_Error message. */
                            esc_html__( 'Connection failed: %s', 'ah-uscreen' ),
                            esc_html( $test_result->get_error_message() )
                        );
                        ?>
                    </p>
                </div>
            <?php elseif ( is_array( $test_result ) && ! empty( $test_result['success'] ) ) : ?>
                <div class="notice notice-success">
                    <p><?php esc_html_e( 'Connection successful. Uscreen API is reachable.', 'ah-uscreen' ); ?></p>
                </div>
            <?php elseif ( is_array( $test_result ) && isset( $test_result['message'] ) ) : ?>
                <div class="notice notice-warning">
                    <p><?php echo esc_html( $test_result['message'] ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php
                settings_fields( 'ah_uscreen_settings_group' );
                do_settings_sections( 'ah-uscreen-settings' );
                submit_button();
                ?>

                <hr />

                <h2><?php esc_html_e( 'Connection Test', 'ah-uscreen' ); ?></h2>
                <p><?php esc_html_e( 'Use this button to verify that the API key and base URL are correct.', 'ah-uscreen' ); ?></p>

                <?php wp_nonce_field( 'ah_uscreen_test_connection_action', 'ah_uscreen_test_connection_nonce' ); ?>
                <p>
                    <button type="submit" name="ah_uscreen_test_connection" class="button button-secondary">
                        <?php esc_html_e( 'Test Connection', 'ah-uscreen' ); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Field: API key.
     */
    public static function render_field_api_key() {
        $settings = self::get_settings();
        ?>
        <input type="password"
               name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]"
               value="<?php echo esc_attr( $settings['api_key'] ); ?>"
               class="regular-text"
               autocomplete="off" />
        <p class="description">
            <?php esc_html_e( 'Uscreen API key.', 'ah-uscreen' ); ?>
        </p>
        <?php
    }

    /**
     * Field: Base URL.
     */
    public static function render_field_base_url() {
        $settings = self::get_settings();
        ?>
        <input type="text"
               name="<?php echo esc_attr( self::OPTION_KEY ); ?>[base_url]"
               value="<?php echo esc_attr( $settings['base_url'] ); ?>"
               class="regular-text code" />
        <p class="description">
            <?php esc_html_e( 'Base URL for the Uscreen Publisher API.', 'ah-uscreen' ); ?>
        </p>
        <?php
    }

    /**
     * Field: Offer ID.
     */
    public static function render_field_offer_id() {
        $settings = self::get_settings();
        ?>
        <input type="text"
               name="<?php echo esc_attr( self::OPTION_KEY ); ?>[offer_id]"
               value="<?php echo esc_attr( $settings['offer_id'] ); ?>"
               class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Uscreen Offer / Plan ID to assign for BrelloRise access.', 'ah-uscreen' ); ?>
        </p>
        <?php
    }

    /**
     * Field: Account creation mode.
     */
    public static function render_field_account_creation_mode() {
        $settings = self::get_settings();
        $mode = $settings['account_creation_mode'];
        ?>
        <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[account_creation_mode]">
            <option value="sso_only" <?php selected( $mode, 'sso_only' ); ?>>
                <?php esc_html_e( 'C) SSO only (no password management)', 'ah-uscreen' ); ?>
            </option>
            <option value="password_silent" <?php selected( $mode, 'password_silent' ); ?>>
                <?php esc_html_e( 'A) Random password, no email', 'ah-uscreen' ); ?>
            </option>
            <option value="password_email" <?php selected( $mode, 'password_email' ); ?>>
                <?php esc_html_e( 'B) Random password + welcome email (if supported)', 'ah-uscreen' ); ?>
            </option>
        </select>
        <p class="description">
            <?php esc_html_e( 'Controls how new Uscreen accounts are created for WooCommerce subscribers.', 'ah-uscreen' ); ?>
        </p>
        <?php
    }

    /**
     * Field: Logging.
     */
    public static function render_field_logging_enabled() {
        $settings = self::get_settings();
        ?>
        <label>
            <input type="checkbox"
                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[logging_enabled]"
                   value="yes" <?php checked( $settings['logging_enabled'], 'yes' ); ?> />
            <?php esc_html_e( 'Enable logging to WooCommerce logs (log source: "uscreen").', 'ah-uscreen' ); ?>
        </label>
        <?php
    }

}
