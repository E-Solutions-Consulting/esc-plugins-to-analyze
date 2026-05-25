<?php
/**
 * Lumen Integration - API Client
 *
 * Sends purchase reports to the Lumen Partners API.
 * Endpoint: POST /partners/v1/purchases
 * Auth:     X-Partner-Api-Key header
 *
 * @package BH_Features
 * @subpackage Integrations/Lumen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( 'AH_Lumen_API_Client' ) ) {
    return;
}

class AH_Lumen_API_Client {

    const ENDPOINT = '/partners/v1/purchases';

    /**
     * Report a purchase to Lumen.
     *
     * @param WC_Order $order
     * @return array { success: bool, http_code: int, body: array, error: string }
     */
    public static function report_purchase( WC_Order $order ) {

        $order_id = $order->get_id();

        $payload = self::build_payload( $order );

        if ( is_wp_error( $payload ) ) {
            return [
                'success'   => false,
                'http_code' => 0,
                'body'      => [],
                'error'     => $payload->get_error_message(),
            ];
        }

        $url     = AH_Lumen_Config::get_base_url() . self::ENDPOINT;
        $api_key = AH_Lumen_Config::get_api_key();

        AH_Lumen_Logger::log( 'Sending purchase report', [
            'order_id' => $order_id,
            'mode'     => AH_Lumen_Config::get( 'mode' ),
            'url'      => $url,
            'payload'  => $payload,
        ] );

        // $response = wp_remote_post( $url, [
        //     'headers' => [
        //         'X-Partner-Api-Key' => $api_key,
        //         'Content-Type'      => 'application/json',
        //     ],
        //     'body'    => wp_json_encode( $payload ),
        //     'timeout' => 15,
        // ] );
        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Partner ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            $error = $response->get_error_message();
            AH_Lumen_Logger::error( 'HTTP error', [ 'order_id' => $order_id, 'error' => $error ] );
            return [ 'success' => false, 'http_code' => 0, 'body' => [], 'error' => $error ];
        }

        $http_code   = (int) wp_remote_retrieve_response_code( $response );
        $raw_body    = wp_remote_retrieve_body( $response );
        $parsed_body = json_decode( $raw_body, true ) ?: [];

        // $success = $http_code === 201;
        $success = $http_code >= 200 && $http_code < 300 && ! empty( $parsed_body['success'] );

        AH_Lumen_Logger::log( 'API response', [
            'order_id'  => $order_id,
            'http_code' => $http_code,
            'body'      => $parsed_body,
        ], $success ? 'info' : 'error' );

        return [
            'success'   => $success,
            'http_code' => $http_code,
            'body'      => $parsed_body,
            'error'     => $success ? '' : ( $parsed_body['error'] ?? $raw_body ),
        ];
    }

    /**
     * Build the full API payload from a WC_Order.
     *
     * @param WC_Order $order
     * @return array|WP_Error
     */
    private static function build_payload( WC_Order $order ) {

        $fulfillment      = (bool) AH_Lumen_Config::get( 'fulfillment', true );
        $lumen_product_id = AH_Lumen_Config::get( 'lumen_product_id', '' );
        $audience_segment = AH_Lumen_Config::get( 'audience_segment', '' );
        $placement        = AH_Lumen_Config::get( 'placement', 'order_bump' );

        if ( empty( $lumen_product_id ) ) {
            return new WP_Error( 'lumen_config', 'Lumen product ID is not configured.' );
        }

        $payload = [
            'partnerPurchaseId' => (string) $order->get_id(),
            'fulfillment'       => $fulfillment,
            'customer'          => [
                'email'     => $order->get_billing_email(),
                'firstName' => $order->get_billing_first_name(),
                'lastName'  => $order->get_billing_last_name(),
            ],
            'items' => [
                [
                    'productId' => $lumen_product_id,
                    'quantity'  => 1,
                ],
            ],
        ];

        if ( $fulfillment ) {
            $phone_raw = $order->get_billing_phone();
            $phone     = preg_replace( '/\D/', '', $phone_raw );

            $shipping_address = [
                'address1'    => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
                'address2'    => $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
                'city'        => $order->get_shipping_city() ?: $order->get_billing_city(),
                'state'       => $order->get_shipping_state() ?: $order->get_billing_state(),
                'zip'         => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
                'countryCode' => $order->get_shipping_country() ?: $order->get_billing_country(),
                'phone'       => $phone,
            ];

            if ( empty( $shipping_address['address1'] )
                || empty( $shipping_address['city'] )
                || empty( $shipping_address['state'] )
                || empty( $shipping_address['zip'] )
                || empty( $shipping_address['countryCode'] )
                || empty( $shipping_address['phone'] )
            ) {
                return new WP_Error( 'lumen_address', 'Shipping address is incomplete for order #' . $order->get_id() );
            }

            $payload['shippingAddress'] = $shipping_address;
        }

        $additional_info = [];

        if ( ! empty( $audience_segment ) ) {
            $additional_info['audienceSegment'] = $audience_segment;
        }

        if ( ! empty( $placement ) ) {
            $additional_info['placement'] = $placement;
        }

        if ( ! empty( $additional_info ) ) {
            $payload['additionalInfo'] = $additional_info;
        }

        $properties = apply_filters( 'ah_lumen_purchase_properties', [], $order );

        if ( ! empty( $properties ) && is_array( $properties ) ) {
            $payload['properties'] = $properties;
        }

        return $payload;
    }
}
