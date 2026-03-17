<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AH_Friendbuy_Coupon_Generator {

    public function __construct() {

        add_action( 'init', [ __CLASS__, 'maybe_generate' ] );

    }

    public static function maybe_generate() {

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( empty( $_GET['friendbuy_generate'] ) ) {
            return;
        }

        $qty    = intval( $_GET['qty'] ?? 100 );
        $sku    = sanitize_text_field( $_GET['sku'] ?? '' );
        $amount = floatval( $_GET['amount'] ?? 25 );

        self::generate( $qty, $sku, $amount );

        wp_die( 'Friendbuy coupons generated successfully.' );
    }

    private static function generate__( $qty, $sku, $amount ) {

        $file = WP_CONTENT_DIR . "/uploads/friendbuy-coupons-" . time() . ".csv";
        $fh   = fopen( $file, 'w' );

        $created = 0;

        while ( $created < $qty ) {

            $code = self::generate_code();

            if ( wc_get_coupon_id_by_code( $code ) ) {
                continue;
            }

            $coupon = new WC_Coupon();
            $coupon->set_code( $code );
            $coupon->set_description( 'Friendbuy Coupon' );
            $coupon->set_discount_type( 'fixed_cart' );
            $coupon->set_amount( $amount );
            $coupon->set_usage_limit( 1 );
            $coupon->set_usage_limit_per_user( 1 );
            $coupon->set_individual_use( true );

            if ( $sku ) {
                $product_id = wc_get_product_id_by_sku( $sku );
                if ( $product_id ) {
                    $coupon->set_product_ids( [ $product_id ] );
                }
            }

            $coupon->save();

            fputcsv( $fh, [ $code ] );

            $created++;
        }

        fclose( $fh );

        wc_get_logger()->info(
            "Friendbuy coupons generated: $file",
            [ 'source' => 'ah-friendbuy' ]
        );
        die('Friendbuy coupons generated!');
    }

    private static function generate( $qty, $sku, $amount ) {

        $filename = 'friendbuy-coupons-' . time() . '.csv';
        $file     = WP_CONTENT_DIR . '/uploads/' . $filename;

        $fh = fopen( $file, 'w' );
        $created = 0;

        while ( $created < $qty ) {

            $code = self::generate_code();

            if ( wc_get_coupon_id_by_code( $code ) ) {
                continue;
            }

            $coupon = new WC_Coupon();
            $coupon->set_code( $code );
            $coupon->set_description( 'Friendbuy Coupon' );
            $coupon->set_discount_type( 'fixed_cart' );
            $coupon->set_amount( $amount );
            $coupon->set_usage_limit( 1 );
            $coupon->set_usage_limit_per_user( 1 );
            $coupon->set_individual_use( true );

            if ( $sku ) {
                $product_id = wc_get_product_id_by_sku( $sku );
                if ( $product_id ) {
                    $coupon->set_product_ids( [ $product_id ] );
                }
            }

            $coupon->save();
            fputcsv( $fh, [ $code ] );

            $created++;
        }

        fclose( $fh );

        wc_get_logger()->info(
            "Friendbuy coupons generated: {$filename}",
            [ 'source' => 'ah-friendbuy' ]
        );

        // ---- FORZAR DESCARGA ----
        if ( file_exists( $file ) ) {

            // Limpia buffers previos (CRÍTICO)
            if ( ob_get_length() ) {
                ob_end_clean();
            }

            header( 'Content-Type: text/csv' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            header( 'Content-Length: ' . filesize( $file ) );
            header( 'Cache-Control: no-store, no-cache' );

            readfile( $file );
            exit;
        }

        wp_die( 'Unable to generate Friendbuy CSV.' );
    }


    private static function generate_code() {
        return 'FRND' . strtoupper( wp_generate_password( 7, false, false ) );
    }
}
// Inicializar el plugin
new AH_Friendbuy_Coupon_Generator();
