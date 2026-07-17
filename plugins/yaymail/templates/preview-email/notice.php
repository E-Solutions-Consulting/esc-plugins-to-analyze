<?php
defined( 'ABSPATH' ) || exit;

$logo_url           = YAYMAIL_PLUGIN_URL . 'assets/images/woocommerce-logo.png';
$template_data      = isset( $args['template_data'] ) ? $args['template_data'] : '';
$is_template_enable = ! empty( $template_data ) ? $template_data->is_enabled() : false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
</head>
<body style="background-color: #fff; padding: 0; text-align: center;" bgcolor="#fff">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background: #f9f9f9; direction: ltr; margin: 0 auto; width: 100%; border-spacing: 0;" bgcolor="#f9f9f9" align="center">
        <tr>
            <td style="padding: 0;">
                <table class="yaymail-template-content-container" style="width: 605px; margin: auto; border-spacing: 0;" width="605" align="center" cellspacing="0">
                    <tr>
                        <td style="padding: 0;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" class="yaymail-customizer-email-template-container yaymail-template-new_order" style="background: #f9f9f9; direction: ltr; margin: 0 auto;" bgcolor="#f9f9f9" align="center">
                                <!-- Notice Section -->
                                <tr>
                                    <div class="yaymail-notice-message" style="width: 605px; direction: initial;">
                                        <div>
                                            <span class="dashicons dashicons-info" style="color: #fbad15; padding-top: 1px;"></span>
                                            <p style="color: #636363;">
                                                <span><?php echo ( $is_template_enable ? esc_html__( 'This template is customized with YayMail, but preview is not currently supported.', 'yaymail' ) : esc_html__( 'This template is not yet supported for preview by WooCommerce.', 'yaymail' ) ); ?></span>
                                            </p>
                                        </div>
                                    </div>
                                </tr>
                                <!-- Heading Section -->
                                <tr>
                                    <td style="padding: 0;">
                                        <div class="yaymail-element" data-yaymail-element-type="heading" data-yaymail-element-id="68356fe0c983b" style="width: 100%; margin: 0 auto;" width="100%">
                                            <table cellpadding="0" cellspacing="0" class="yaymail-element__content" style="background-color: #e1e1e1; padding: 40px 50px; border-spacing: 0; width: 100%; direction: ltr; min-width: 100%;" bgcolor="#873EFF" width="100%">
                                                <tbody>
                                                    <tr>
                                                        <td>
                                                            <div style="color: #636363; font-family: Helvetica,Roboto,Arial,sans-serif; text-align: left;" align="left">
                                                                <h1 style="letter-spacing: -1px; font-family: inherit; text-shadow: unset; text-align: inherit; font-size: 30px; font-weight: 300; line-height: normal; margin: 0px; color: #636363;"><?php esc_html_e( 'Email Heading', 'yaymail' ); ?></h1>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Content Section -->
                                <tr>
                                    <td style="padding: 0;">
                                        <div class="yaymail-element" data-yaymail-element-type="text" data-yaymail-element-id="8e1e807c-532f-4c25-b85f-81777319f9ba" style="width: 100%; margin: 0 auto;" width="100%">
                                            <table cellpadding="0" cellspacing="0" class="yaymail-element__content" style="background-color: #fff; padding: 15px 50px; border-spacing: 0; width: 100%; direction: ltr; min-width: 100%;" bgcolor="#fff" width="100%">
                                                <tbody>
                                                    <tr>
                                                        <td>
                                                            <div style="text-align: left; color: #636363; font-family: Helvetica,Roboto,Arial,sans-serif;" align="left">
                                                                <p style="font-size: 14px; margin: 0px;"><span style="font-size: 18px;"><strong>This is a title</strong></span></p>
                                                                <p style="font-size: 14px; margin: 0px;">&nbsp;</p>
                                                                <p style="font-size: 14px; margin: 0px;"><span>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy.</span></p>
                                                                <p style="font-size: 14px; margin: 0px;">&nbsp;</p>
                                                                <p style="font-size: 14px; margin: 0px;"><span>It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.</span></p>
                                                                <p style="font-size: 14px; margin: 0px;">&nbsp;</p>
                                                                <p style="font-size: 14px; margin: 0px;"><span>Various versions have evolved over the years.</span></p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Footer Section -->
                                <tr>
                                    <td style="padding: 0;">
                                        <div class="yaymail-element" data-yaymail-element-type="footer" data-yaymail-element-id="68356fe0c98b2" style="width: 100%; margin: 0 auto;" width="100%">
                                            <table cellpadding="0" cellspacing="0" class="yaymail-element__content" style="background-color: #e1e1e1; padding: 15px 50px; border-spacing: 0; width: 100%; direction: ltr; min-width: 100%;" bgcolor="#f9f9f9" width="100%">
                                                <tbody>
                                                    <tr>
                                                        <td>
                                                            <div style="color: #636363; font-family: Helvetica,Roboto,Arial,sans-serif;">
                                                                <p style="font-size: 14px; margin: 0px 0px 16px; text-align: center;" align="center">
                                                                    yaymail - Built with <a href="https://woocommerce.com" target="_blank" rel="noopener" style="color: #636363; font-weight: normal; text-decoration: underline;">WooCommerce</a>
                                                                </p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>