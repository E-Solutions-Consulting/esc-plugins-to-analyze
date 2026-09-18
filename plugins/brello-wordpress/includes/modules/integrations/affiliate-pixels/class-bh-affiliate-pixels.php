<?php
/**
 * Load partner pixels only when URL/cookie affid matches the registry.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Affiliate_Pixels {

    /** @var array<string, array> */
    private $registry = [];

    public function __construct() {
        $path = plugin_dir_path( __FILE__ ) . 'registry.php';
        if ( file_exists( $path ) ) {
            $loaded = include $path;
            if ( is_array( $loaded ) ) {
                $this->registry = $loaded;
            }
        }

        add_action( 'wp_head', [ $this, 'print_head_pixels' ], 25 );
        add_action( 'wp_footer', [ $this, 'print_purchase_pixels' ], 90 );
    }

    /**
     * Resolve Everflow affid from URL, first-touch cookie, or order meta on thank-you.
     */
    public function resolve_affid() {
        if ( ! empty( $_GET['affid'] ) ) {
            return sanitize_text_field( wp_unslash( (string) $_GET['affid'] ) );
        }
        if ( ! empty( $_COOKIE['ef_entry_affid'] ) ) {
            return sanitize_text_field( wp_unslash( (string) $_COOKIE['ef_entry_affid'] ) );
        }
        if ( ! empty( $_COOKIE['eftid'] ) && function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
            // Fall through to order meta below.
        }
        if ( function_exists( 'is_order_received_page' ) && is_order_received_page() && function_exists( 'wc_get_order' ) ) {
            global $wp;
            $order_id = 0;
            if ( isset( $wp->query_vars['order-received'] ) ) {
                $order_id = absint( $wp->query_vars['order-received'] );
            }
            if ( $order_id ) {
                $order = wc_get_order( $order_id );
                if ( $order ) {
                    $from_order = $order->get_meta( '_ah_everflow_entry_affid' );
                    if ( $from_order ) {
                        return sanitize_text_field( (string) $from_order );
                    }
                }
            }
        }
        return '';
    }

    public function print_head_pixels() {
        if ( is_admin() ) {
            return;
        }
        $affid = $this->resolve_affid();
        if ( $affid === '' || empty( $this->registry[ $affid ]['head'] ) ) {
            return;
        }
        echo "\n<!-- BH affiliate pixels head affid=" . esc_attr( $affid ) . " -->\n";
        foreach ( (array) $this->registry[ $affid ]['head'] as $snippet ) {
            // Registry is maintainer-controlled PHP, not user input.
            echo $snippet . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    public function print_purchase_pixels() {
        if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
            return;
        }
        $affid = $this->resolve_affid();
        if ( $affid === '' || empty( $this->registry[ $affid ]['purchase'] ) ) {
            return;
        }
        echo "\n<!-- BH affiliate pixels purchase affid=" . esc_attr( $affid ) . " -->\n";
        foreach ( (array) $this->registry[ $affid ]['purchase'] as $snippet ) {
            echo $snippet . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
