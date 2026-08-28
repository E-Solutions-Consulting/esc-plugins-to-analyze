<?php
/**
 * Everflow shared helper — single place for TID resolve/save + constants.
 *
 * Used by: WooCommerce checkout, landing-checkout bridge, async Event sender.
 * Change cookie names / event IDs / domain here only.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Everflow_Helper {

    const NID             = 3697;
    const TRACKING_DOMAIN = 'https://www.p9wkp5ctrk.com/';
    /** S2S conversion create (Event 2/5/6/Base). */
    const TRACKING_SCRIPT = 'https://www.p9wkp5ctrk.com/scripts/main.js';
    /**
     * S2S reversal (reject) endpoint — NOT the root conversion URL.
     * Hitting TRACKING_DOMAIN with adv_event_id=5 creates a NEW Event 5 (bug).
     */
    const REVERSAL_ENDPOINT = 'https://www.p9wkp5ctrk.com/reversal';

    /** Brand / advertiser id (aid) */
    const AID = 2;

    /** Brand event IDs (Brands → Brello Health → Events) */
    const EVENT_WOO_CONVERSION       = 2;
    const EVENT_CHARGE_SUCCEEDED     = 5;
    const EVENT_REFUND               = 6;
    const EVENT_ADD_TO_CART          = 7;
    const EVENT_BEGIN_CHECKOUT       = 8;
    const EVENT_SUBSCRIPTION_CREATED = 9;
    const EVENT_ORDER_CREATED        = 10;
    const EVENT_ORDER_CANCELLED      = 11;
    const EVENT_ORDER_FAILED         = 12;
    const EVENT_PAYMENT_FAILED       = 13;
    const EVENT_SUBSCRIPTION_RENEWAL = 14;
    const EVENT_ORDER_COMPLETED      = 15;

    const META_EVENT_9_SENT  = '_ah_everflow_event_9_sent';
    const META_EVENT_8_SENT  = '_ah_everflow_event_8_sent';
    const META_EVENT_10_SENT = '_ah_everflow_event_10_sent';
    const META_EVENT_11_SENT = '_ah_everflow_event_11_sent';
    const META_EVENT_12_SENT = '_ah_everflow_event_12_sent';
    const META_EVENT_13_SENT = '_ah_everflow_event_13_sent';
    const META_EVENT_14_SENT = '_ah_everflow_event_14_sent';

    const META_EFTID  = 'eftid';
    const META_STATUS = 'everflow_eftid_status';
    const SESSION_KEY = 'eftid';

    /** Cookies checked (first match wins). Prefer our `eftid` (console “eftid saved”) over SDK cookie. */
    const TID_COOKIES = [
        'eftid',
        'ef_tid_c_a_2',
        'brello_landing_eftid',
    ];

    /** URL / request params checked. */
    const TID_PARAMS = [
        '_ef_transaction_id',
        'ef_transaction_id',
        'at_transaction_id',
        'transaction_id',
    ];

    /**
     * One Everflow TID only. Admin custom fields / multi-click cookies sometimes
     * store several IDs joined with "|" or "," — postbacks need a single TID.
     *
     * @param string $raw
     * @return string
     */
    public static function normalize_eftid( $raw ) {
        $raw = trim( (string) $raw );
        if ( $raw === '' ) {
            return '';
        }

        foreach ( [ '|', ',' ] as $sep ) {
            if ( strpos( $raw, $sep ) !== false ) {
                $parts = array_values(
                    array_filter(
                        array_map( 'trim', explode( $sep, $raw ) )
                    )
                );
                $raw = $parts[0] ?? '';
                break;
            }
        }

        return sanitize_text_field( $raw );
    }

    /**
     * Resolve Everflow transaction id from explicit value, cookies, request, session.
     *
     * @param string $explicit Optional TID from API body / caller.
     * @return string
     */
    public static function resolve_eftid( $explicit = '' ) {
        $eftid = self::normalize_eftid( $explicit );
        if ( $eftid !== '' ) {
            return $eftid;
        }

        foreach ( self::TID_COOKIES as $cookie ) {
            if ( ! empty( $_COOKIE[ $cookie ] ) ) {
                $from_cookie = self::normalize_eftid( wp_unslash( (string) $_COOKIE[ $cookie ] ) );
                if ( $from_cookie !== '' ) {
                    return $from_cookie;
                }
            }
        }

        foreach ( self::TID_PARAMS as $param ) {
            if ( ! empty( $_REQUEST[ $param ] ) ) {
                $from_req = self::normalize_eftid( wp_unslash( (string) $_REQUEST[ $param ] ) );
                if ( $from_req !== '' ) {
                    return $from_req;
                }
            }
            if ( ! empty( $_GET[ $param ] ) ) {
                $from_get = self::normalize_eftid( wp_unslash( (string) $_GET[ $param ] ) );
                if ( $from_get !== '' ) {
                    return $from_get;
                }
            }
        }

        if ( function_exists( 'WC' ) && WC()->session ) {
            $from_session = self::normalize_eftid(
                (string) WC()->session->get( self::SESSION_KEY, '' )
            );
            if ( $from_session !== '' ) {
                return $from_session;
            }
        }

        return '';
    }

    /**
     * Persist resolved TID into WC session (Woo checkout path).
     */
    public static function capture_to_session() {
        if ( ! function_exists( 'WC' ) || ! WC()->session ) {
            return;
        }

        $eftid = self::resolve_eftid();
        if ( $eftid !== '' ) {
            WC()->session->set( self::SESSION_KEY, $eftid );
        }
    }

    /**
     * Attach eftid to order meta (idempotent — will not overwrite existing).
     *
     * @param WC_Order $order
     * @param string   $explicit Optional TID from landing API body.
     * @param string   $context  Label for order notes (e.g. woo_checkout, landing_checkout).
     * @return string Saved or existing TID (empty if missing).
     */
    public static function attach_to_order( $order, $explicit = '', $context = 'checkout' ) {
        if ( defined( 'BH_DISABLE_EVERFLOW' ) && BH_DISABLE_EVERFLOW ) {
            return '';
        }
        if ( ! $order instanceof WC_Order ) {
            return '';
        }

        $existing = self::normalize_eftid( $order->get_meta( self::META_EFTID ) );
        if ( $existing !== '' ) {
            // Collapse pipe/comma duplicates into one TID on the order.
            if ( (string) $order->get_meta( self::META_EFTID ) !== $existing ) {
                $order->update_meta_data( self::META_EFTID, $existing );
            }
            $order->update_meta_data( self::META_STATUS, 'saved' );
            return $existing;
        }

        $eftid = self::resolve_eftid( $explicit );

        if ( $eftid !== '' ) {
            $order->update_meta_data( self::META_EFTID, $eftid );
            $order->update_meta_data( self::META_STATUS, 'saved' );
            $order->add_order_note(
                sprintf(
                    'Everflow: eftid saved (%s) via %s.',
                    $eftid,
                    sanitize_key( $context )
                )
            );
            return $eftid;
        }

        $order->update_meta_data( self::META_STATUS, 'missing' );
        $order->add_order_note(
            sprintf(
                'Everflow: eftid MISSING via %s. UTM/origin may still show everflow; Event 5 cannot fire without a transaction id.',
                sanitize_key( $context )
            )
        );

        return '';
    }

    /**
     * @param WC_Order $order
     * @return string
     */
    public static function get_order_eftid( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return '';
        }
        return self::normalize_eftid( $order->get_meta( self::META_EFTID ) );
    }

    /**
     * Product key for Everflow order JSON `ps`.
     * Prefer SKU; else order line name (matches admin UI — survives wrong/live slug);
     * else product/variation slug; else ID.
     *
     * @param WC_Order_Item_Product $item
     * @return string
     */
    public static function line_item_product_key( $item ) {
        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            return '';
        }

        $product_id   = (int) $item->get_product_id();
        $variation_id = (int) $item->get_variation_id();
        $lookup_id    = $variation_id > 0 ? $variation_id : $product_id;
        $product      = $lookup_id > 0 ? wc_get_product( $lookup_id ) : $item->get_product();

        $sku = ( $product instanceof WC_Product ) ? trim( (string) $product->get_sku() ) : '';
        if ( $sku !== '' ) {
            return sanitize_text_field( $sku );
        }

        // Line name = what was sold on this order (Semaglutide vs Tirzepatide in admin).
        $from_name = sanitize_title( $item->get_name() );
        if ( $from_name !== '' ) {
            return $from_name;
        }

        if ( $product instanceof WC_Product ) {
            $slug = trim( (string) $product->get_slug() );
            if ( $slug !== '' ) {
                return $slug;
            }
        }

        return $lookup_id > 0 ? (string) $lookup_id : '';
    }
}
