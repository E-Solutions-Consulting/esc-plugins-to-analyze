<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Friendbuy_Referral_Tracker {

    const SESSION_KEY = '_friendbuy_referral_code';
    const META_KEY    = '_friendbuy_referral_code';
    const TABLE       = 'bh_order_meta';

    public static function init() {
        add_action( 'init', [ __CLASS__, 'capture_from_url' ], 1 );
        add_action( 'woocommerce_checkout_order_created', [ __CLASS__, 'persist_to_ext_db' ] );
    }

    /**
     * Si la URL contiene ?referralCode=XXX, lo guarda en sesión PHP.
     * SiteGround no cachea URLs con query strings, así que PHP siempre corre en ese request.
     */
    public static function capture_from_url() {
        if ( empty( $_GET['referralCode'] ) ) {
            return;
        }

        $code = sanitize_text_field( wp_unslash( $_GET['referralCode'] ) );

        if ( session_status() === PHP_SESSION_NONE ) {
            session_start();
        }

        $_SESSION[ self::SESSION_KEY ] = $code;
    }

    /**
     * Classic checkout: move referralCode from the PHP session onto the order.
     *
     * @param WC_Order $order Order.
     */
    public static function persist_to_ext_db( WC_Order $order ) {
        if ( session_status() === PHP_SESSION_NONE ) {
            session_start();
        }

        $code = isset( $_SESSION[ self::SESSION_KEY ] ) ? (string) $_SESSION[ self::SESSION_KEY ] : '';
        $code = sanitize_text_field( $code );
        if ( $code === '' ) {
            return;
        }

        if ( self::persist_code_for_order( $order, $code ) ) {
            unset( $_SESSION[ self::SESSION_KEY ] );
        }
    }

    /**
     * Persist a referral code for an order (classic session path + landing bridge).
     * Writes WooCommerce order meta always; ExtDB when available.
     *
     * Landing checkout calls this directly. The method was missing in production,
     * so a headless order with a referral code fatally errored before the code
     * was saved and before the subscription was created.
     *
     * @param WC_Order $order Order.
     * @param string   $code  Referral code.
     * @return bool True when WooCommerce order meta was saved (or already present).
     */
    public static function persist_code_for_order( WC_Order $order, string $code ): bool {
        $code = sanitize_text_field( $code );
        if ( $code === '' || $order->get_id() < 1 ) {
            return false;
        }

        $existing_meta = (string) $order->get_meta( self::META_KEY );
        $existing_ext  = self::get_referral_code_from_extdb( (int) $order->get_id() );

        if ( $existing_meta === '' ) {
            $order->update_meta_data( self::META_KEY, $code );
            $order->save();
        }

        if ( $existing_ext !== '' ) {
            return true;
        }

        $logger  = wc_get_logger();
        $context = [ 'source' => 'ah-friendbuy' ];

        if ( ! class_exists( 'BH_ExtDB' ) || ! BH_ExtDB::is_available() ) {
            $logger->warning(
                'BH_ExtDB not available — referralCode saved to WC order meta only for order ' . $order->get_id(),
                $context
            );
            return true;
        }

        $result = BH_ExtDB::insert( self::TABLE, [
            'order_id'   => $order->get_id(),
            'meta_key'   => self::META_KEY,
            'meta_value' => $code,
        ] );

        if ( $result ) {
            $logger->info(
                'Friendbuy referralCode "' . $code . '" persisted for order ' . $order->get_id(),
                $context
            );
            return true;
        }

        $logger->error(
            'Failed to persist referralCode "' . $code . '" to ExtDB for order ' . $order->get_id() . ' (WC meta kept).',
            $context
        );
        return true;
    }

    /**
     * Read the referral code: ExtDB first, then WooCommerce order meta.
     *
     * @param int $order_id Order ID.
     * @return string Empty if none.
     */
    public static function get_referral_code( int $order_id ): string {
        $from_ext = self::get_referral_code_from_extdb( $order_id );
        if ( $from_ext !== '' ) {
            return $from_ext;
        }

        $order = wc_get_order( $order_id );
        if ( $order ) {
            return sanitize_text_field( (string) $order->get_meta( self::META_KEY ) );
        }

        return '';
    }

    /**
     * @param int $order_id Order ID.
     * @return string
     */
    private static function get_referral_code_from_extdb( int $order_id ): string {
        if ( ! class_exists( 'BH_ExtDB' ) || ! BH_ExtDB::is_available() ) {
            return '';
        }

        $value = BH_ExtDB::get_var(
            'SELECT meta_value FROM bh_order_meta WHERE order_id = ? AND meta_key = ? LIMIT 1',
            [ $order_id, self::META_KEY ]
        );

        return (string) ( $value ?? '' );
    }
}

AH_Friendbuy_Referral_Tracker::init();