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
     * Al crear el orden en checkout, mueve el referralCode de la sesión
     * a bh_external.bh_order_meta.
     *
     * @param WC_Order $order
     */
    public static function persist_to_ext_db( WC_Order $order ) {
        if ( session_status() === PHP_SESSION_NONE ) {
            session_start();
        }

        $code = $_SESSION[ self::SESSION_KEY ] ?? '';

        if ( empty( $code ) ) {
            return;
        }

        $logger  = wc_get_logger();
        $context = [ 'source' => 'ah-friendbuy' ];

        if ( ! class_exists( 'BH_ExtDB' ) || ! BH_ExtDB::is_available() ) {
            $logger->error(
                'BH_ExtDB not available — referralCode not persisted for order ' . $order->get_id(),
                $context
            );
            return;
        }

        $result = BH_ExtDB::insert( self::TABLE, [
            'order_id'   => $order->get_id(),
            'meta_key'   => self::META_KEY,
            'meta_value' => $code,
        ] );

        if ( $result ) {
            unset( $_SESSION[ self::SESSION_KEY ] );
            $logger->info(
                'Friendbuy referralCode "' . $code . '" persisted for order ' . $order->get_id(),
                $context
            );
        } else {
            $logger->error(
                'Failed to persist referralCode "' . $code . '" for order ' . $order->get_id(),
                $context
            );
        }
    }

    /**
     * Leer el referralCode desde bh_external para un orden dado.
     *
     * @param int $order_id
     * @return string  Vacío si no existe o DB no disponible.
     */
    public static function get_referral_code( int $order_id ): string {
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