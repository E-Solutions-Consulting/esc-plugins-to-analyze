<?php
/**
 * Elementor Editor Performance Optimizer
 *
 * Aggressive optimizations for WooCommerce + Elementor editor.
 * Prevents WooCommerce from loading heavy frontend logic while editing.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Elementor_Editor_Optimizer {

    public function __construct() {

        add_action('elementor/editor/before_enqueue_scripts', array( $this, 'disable_wc_assets' ), 100);
        add_filter('woocommerce_cart_fragments_enabled', array( $this, 'disable_wc_fragments' ));
        add_action('init', array( $this, 'disable_wc_session' ), 1);
        add_action('init', array( $this, 'disable_wc_notices' ), 2);
        add_action('wp_enqueue_scripts', array( $this, 'disable_wc_ajax_scripts' ), 100);
        add_filter('woocommerce_is_rest_api_request',  array( $this, 'disable_rest_api_request' ));

    }

    /**
     * Check if Elementor editor is active
     */
    private function is_elementor_editor() {

        if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
            return false;
        }

        if ( isset( $_GET['action'] ) && $_GET['action'] === 'elementor' ) {
            return true;
        }

        return false;

    }

    /**
     * Remove WooCommerce scripts/styles from editor
     */
    public function disable_wc_assets() {

        if ( ! $this->is_elementor_editor() ) {
            return;
        }

        // Scripts
        wp_dequeue_script( 'wc-cart-fragments' );
        wp_dequeue_script( 'woocommerce' );
        wp_dequeue_script( 'wc-add-to-cart' );
        wp_dequeue_script( 'js-cookie' );
        wp_dequeue_script( 'selectWoo' );
        wp_dequeue_script( 'wc-checkout' );

        // Styles
        wp_dequeue_style( 'woocommerce-general' );
        wp_dequeue_style( 'woocommerce-layout' );
        wp_dequeue_style( 'woocommerce-smallscreen' );

    }

    /**
     * Disable cart fragments
     */
    public function disable_wc_fragments( $enabled ) {

        if ( $this->is_elementor_editor() ) {
            return false;
        }

        return $enabled;

    }

    /**
     * Disable WooCommerce session
     */
    public function disable_wc_session() {

        if ( ! $this->is_elementor_editor() ) {
            return;
        }

        if ( function_exists( 'WC' ) && WC()->session ) {

            remove_action(
                'init',
                array( WC()->session, 'init' ),
                0
            );

        }

    }

    /**
     * Disable WooCommerce notices
     */
    public function disable_wc_notices() {

        if ( ! $this->is_elementor_editor() ) {
            return;
        }

        remove_action( 'wp_loaded', array( 'WC_Form_Handler', 'add_to_cart_action' ), 20 );

    }

    /**
     * Disable AJAX scripts
     */
    public function disable_wc_ajax_scripts() {

        if ( ! $this->is_elementor_editor() ) {
            return;
        }

        wp_dequeue_script( 'wc-cart' );
        wp_dequeue_script( 'wc-checkout' );

    }
    public function disable_rest_api_request($is_rest) {

        if ( isset($_GET['action']) && $_GET['action'] === 'elementor' ) {
            return true;
        }
        return $is_rest;
    }

}
