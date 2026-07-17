<?php

namespace YayMail\Notices;

use YayMail\Utils\SingletonTrait;

/**
 *
 * @method static Ajax get_instance()
 */
class Ajax {
    use SingletonTrait;

    protected function __construct() {
        $this->init_hooks();
    }

    protected function init_hooks() {
        add_action( 'wp_ajax_yaymail_dismiss_suggest_addons_notice', [ $this, 'yaymail_dismiss_suggest_addons_notice' ] );
        add_action( 'wp_ajax_yaymail_dismiss_upgrade_notice', [ $this, 'yaymail_dismiss_upgrade_notice' ] );
    }

    public function yaymail_dismiss_suggest_addons_notice() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yaymail_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'yaymail' ) ] );
        }
        try {
            // The Notice should comeback after 60 days
            update_option( 'yaymail_next_recommendation_suggest_addons_notice_time', time() + 60 * 60 * 24 * 60 );
            wp_send_json_success();
        } catch ( \Error $error ) {
            yaymail_get_logger( $error );
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
        }
    }

    public function yaymail_dismiss_upgrade_notice() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yaymail_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'yaymail' ) ] );
        }
        try {
            // The Notice should comeback after 60 days
            update_option( 'yaymail_next_recommendation_upgrade_notice_time', time() + 60 * 60 * 24 * 60 );
            wp_send_json_success();
        } catch ( \Error $error ) {
            yaymail_get_logger( $error );
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
        }
    }
}
