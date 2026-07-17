<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YayMail\Utils\Helpers;

if ( ! isset( $args['order'] ) || ! ( Helpers::is_woocommerce_order( $args['order'] ) ) ) {
    return;
}
$text_align           = yaymail_get_text_align();
$order_instance       = $args['order'];
$payment_gateway      = wc_get_payment_gateway_by_order( $order_instance );
$payment_instructions = '';
if ( ! empty( $payment_gateway ) ) {
    if ( isset( $payment_gateway->instructions ) ) {
        $payment_instructions = $payment_gateway->instructions;
    } elseif ( ! empty( $payment_gateway->get_option( 'instructions' ) ) ) {
        // This fix the issue with Invoice Payment in Addon Germanized
        $payment_instructions = $payment_gateway->get_option( 'instructions' );
    }
}
if ( ! empty( $payment_instructions ) ) :
    ?>

<div style="color:inherit;text-align: <?php echo esc_attr( $text_align ); ?>" class="yaymail_builder_instructions">
    <?php
    echo wp_kses_post( wpautop( wptexturize( make_clickable( $payment_instructions, 'woocommerce' ) ) ) );
    ?>
</div>

    <?php
endif;

/*
Our bank details
payment: Direct bank transfer
 */
$direct_bank_transfer = esc_html__( 'Direct bank transfer', 'woocommerce' );
if ( false !== $payment_gateway && ! empty( $payment_gateway->account_details ) && $direct_bank_transfer === $payment_gateway->get_method_title() ) {
    // Get the order country and country $locale.
    $country        = ! empty( $order_instance->get_billing_country() ) ? $order_instance->get_billing_country() : '';
    $country_locale = $payment_gateway->get_country_locale();

    // Get sortcode label in the $locale array and use appropriate one.
    $sortcode = isset( $locale[ $country ]['sortcode']['label'] ) ? $country_locale[ $country ]['sortcode']['label'] : __( 'Sort code', 'woocommerce' );

    $bacs_accounts = apply_filters( 'woocommerce_bacs_accounts', $payment_gateway->account_details, $order_instance->get_id() );

    if ( ! empty( $bacs_accounts ) ) {
        $account_html = '';
        $has_details  = false;

        foreach ( $bacs_accounts as $bacs_account ) {
            $bacs_account = (object) $bacs_account;

            if ( $bacs_account->account_name ) {
                $account_html .= '<h3 style="color:inherit;font-size:16px">' . wp_kses_post( wp_unslash( $bacs_account->account_name ) ) . ':</h3>' . PHP_EOL;
            }

            $account_html .= '<ul class="yaymail-bacs-bank-details">' . PHP_EOL;

            // BACS account fields shown on the thanks page and in emails.
            $account_fields = apply_filters(
                'woocommerce_bacs_account_fields',
                [
                    'bank_name'      => [
                        'label' => __( 'Bank', 'woocommerce' ),
                        'value' => $bacs_account->bank_name,
                    ],
                    'account_number' => [
                        'label' => __( 'Account number', 'woocommerce' ),
                        'value' => $bacs_account->account_number,
                    ],
                    'sort_code'      => [
                        'label' => $sortcode,
                        'value' => $bacs_account->sort_code,
                    ],
                    'iban'           => [
                        'label' => __( 'IBAN', 'woocommerce' ),
                        'value' => $bacs_account->iban,
                    ],
                    'bic'            => [
                        'label' => __( 'BIC', 'woocommerce' ),
                        'value' => $bacs_account->bic,
                    ],
                ],
                $order_instance->get_id()
            );

            foreach ( $account_fields as $field_key => $field ) {
                if ( ! empty( $field['value'] ) ) {
                    $account_html .= '<li class="' . esc_attr( $field_key ) . '">' . wp_kses_post( $field['label'] ) . ': <strong>' . wp_kses_post( wptexturize( $field['value'] ) ) . '</strong></li>' . PHP_EOL;
                    $has_details   = true;
                }
            }

            $account_html .= '</ul>';
        }//end foreach

        if ( $has_details ) {
            $account_html = PHP_EOL . $account_html;
            echo wp_kses_post( "<section class='yaymail-builder-wrap-account'> $account_html </section>" );
        }
    }//end if
}//end if
?>
