<?php
$dir                    = is_rtl() ? 'rtl' : 'ltr';
$template_exclude_style = apply_filters( 'yaymail_template_exclude_style', [] );
$yaymail_settings       = yaymail_settings();
$container_width        = isset( $yaymail_settings['container_width'] ) && is_numeric( $yaymail_settings['container_width'] ) ? $yaymail_settings['container_width'] : '605';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="<?php echo esc_attr( $dir ); ?>">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1"/>
        <meta name="x-apple-disable-message-reformatting" />
        <?php if ( ! in_array( $template->get_name(), $template_exclude_style ) ) : ?>
            <style>
                h1{ font-family:inherit;text-shadow:unset;text-align:inherit;}
                h2,h3{ font-family:inherit;color:inherit;text-align:inherit;}
                .yaymail-inline-block {display: inline-block;}
                .yaymail-customizer-email-template-container a {color: <?php echo esc_attr( $template->get_text_link_color() ); ?>}
                .yaymail-order-details-table .wc-item-meta {list-style-type: none;}
                /* Ensure container width remains constant on mobile */
                @media screen and (max-width: 600px) {
                    .yaymail-template-content-container {
                        width: <?php echo esc_attr( $container_width ); ?>px !important;
                    }
                }
            /**
            * Media queries are not supported by all email clients, however they do work on modern mobile
            * Gmail clients and can help us achieve better consistency there.
            */
            /* @media screen and (max-width: 600px) {
                .yaymail-template-content-container {
                    width: 100% !important;
                }
                .yaymail-template-content-container .yaymail-element__content {
                    padding: 8px 15px !important;
                }
                .yaymail-template-content-container .yaymail-billing-address-column,
                .yaymail-template-content-container .yaymail-shipping-address-column {
                    display: block !important;
                    width: 100% !important;
                }
                .yaymail-template-content-container .yaymail-shipping-address-column {
                    margin-top: 15px;
                }
                .yaymail-template-content-container .yaymail_item_product_title {
                    min-width: 120px;
                    width: 100%;
                }
            } */
            </style>
        <?php endif; ?>
    </head>
    <body style="background: <?php echo esc_attr( $template->get_background_color() ); ?>" <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">
