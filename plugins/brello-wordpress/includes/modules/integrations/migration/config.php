<?php
/**
 * Migration module configuration.
 *
 * Settings stored in a single WP option, following the Attentive pattern.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Migration_Config {

    const OPTION_KEY = 'ah_migration_settings';

    /**
     * Get all settings merged with defaults.
     *
     * @return array
     */
    public static function get_settings() {
        $defaults = array(
            'enabled'            => 'no',
            'blocked_message'    => 'Your account is being migrated to the new platform. You can no longer log in here.',
            'consent_title'      => 'Your new Brello experience is ready',
            'consent_text'       => 'We are moving accounts to our new platform. Accept below and our team will migrate your account — we will let you know as soon as it is ready.',
            'consent_button'     => 'Accept migration',
            'consent_accepted_message' => 'Thanks! Your migration request was received. We will notify you when your account is ready on the new platform.',
            'pp_api_base'        => '',
            'pp_publishable_key' => '',
            'pp_tenant_slug'     => '',
            'app_login_url'      => '',
        );

        $settings = get_option( self::OPTION_KEY, array() );

        return wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );
    }

    /**
     * Get a single setting.
     *
     * @param string $key
     * @return mixed
     */
    public static function get( $key ) {
        $settings = self::get_settings();
        return isset( $settings[ $key ] ) ? $settings[ $key ] : '';
    }

    /**
     * Whether the module is enabled.
     *
     * @return bool
     */
    public static function is_enabled() {
        return 'yes' === self::get( 'enabled' );
    }

    /**
     * Save settings.
     *
     * @param array $settings
     * @return void
     */
    public static function save_settings( array $settings ) {
        $clean = array(
            'enabled'            => ( isset( $settings['enabled'] ) && 'yes' === $settings['enabled'] ) ? 'yes' : 'no',
            'blocked_message'    => isset( $settings['blocked_message'] ) ? sanitize_text_field( $settings['blocked_message'] ) : '',
            'consent_title'      => isset( $settings['consent_title'] ) ? sanitize_text_field( $settings['consent_title'] ) : '',
            'consent_text'       => isset( $settings['consent_text'] ) ? sanitize_text_field( $settings['consent_text'] ) : '',
            'consent_button'     => isset( $settings['consent_button'] ) ? sanitize_text_field( $settings['consent_button'] ) : '',
            'consent_accepted_message' => isset( $settings['consent_accepted_message'] ) ? sanitize_text_field( $settings['consent_accepted_message'] ) : '',
            'pp_api_base'        => isset( $settings['pp_api_base'] ) ? esc_url_raw( trim( $settings['pp_api_base'] ) ) : '',
            'pp_publishable_key' => isset( $settings['pp_publishable_key'] ) ? sanitize_text_field( $settings['pp_publishable_key'] ) : '',
            'pp_tenant_slug'     => isset( $settings['pp_tenant_slug'] ) ? sanitize_key( $settings['pp_tenant_slug'] ) : '',
            'app_login_url'      => isset( $settings['app_login_url'] ) ? esc_url_raw( trim( $settings['app_login_url'] ) ) : '',
        );

        update_option( self::OPTION_KEY, $clean, false );
    }
}