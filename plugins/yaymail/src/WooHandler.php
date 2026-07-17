<?php

namespace YayMail;

use YayMail\Utils\Helpers;
use YayMail\Utils\SingletonTrait;

/**
 * Handles WooCommerce email preview
 *
 * @method static WooHandler get_instance()
 */
class WooHandler {

    use SingletonTrait;

    protected function __construct() {
        add_filter( 'woocommerce_prepare_email_for_preview', [ $this, 'display_preview_notice' ] );
        add_filter( 'woocommerce_mail_content', [ $this, 'handle_default_preview_content' ] );
        // Add settings to WooCommerce email options section
        add_filter( 'woocommerce_get_settings_email', [ $this, 'add_settings' ], 10, 2 );
        add_filter(
            'woocommerce_get_settings_advanced',
            function( $settings ) {
                foreach ( $settings as $index => $setting ) {
                    if ( $setting['id'] === 'woocommerce_feature_block_email_editor_enabled' ) {
                        $introduction_text           = sprintf( __( 'You can customize WooCommerce emails with <a href="%s" target="_blank">YayMail - WooCommerce Email Customizer</a>', 'yaymail' ), esc_url( admin_url( 'admin.php?page=yaymail-settings#' ), 'yaymail' ) );
                        $settings[ $index ]['desc'] .= '<br/><br/>' . $introduction_text . '<br/>';
                    }
                }
                return $settings;
            }
        );
    }

    public function display_preview_notice( $email ) {

        if ( ! ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'preview-mail' ) ) ) {
            return $email;
        }

        if ( isset( $_GET['preview_woocommerce_mail'] ) && ! Helpers::is_true( sanitize_text_field( wp_unslash( $_GET['preview_woocommerce_mail'] ) ) ) ) {
            return $email;
        }

        if ( isset( $_GET['rest_route'] ) && $_GET['rest_route'] === '/wc-admin-email/settings/email/send-preview' ) {
            return $email;
        }
        if ( ! isset( $email->id ) ) {
            return $email;
        }
        $email_id = $email->id;

        $yaymail_template = new YayMailTemplate( $email_id );

        if ( ! $yaymail_template->is_exists() ) {
            return $email;
        }

        if ( ! $yaymail_template->is_enabled() ) {
            return $email;
        }

        add_filter( 'yaymail_previewing_template_is_yaymail_template', '__return_true' );
        ob_start();
        ?>
            <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; max-width: 600px; margin: 20px auto; padding: 20px; text-align: center;">
                <div style="background-color: #f7f4fa; padding: 12px 12px 24px 12px; border-radius: 4px; margin-bottom: 20px;">
                    <h2 style="color: <?php echo esc_attr( YAYMAIL_COLOR_WC_DEFAULT ); ?>; font-size: 24px; margin-bottom: 8px;"><?php esc_html_e( 'YayMail Template Preview', 'yaymail' ); ?></h2>
                    <p style="color:rgb(110, 110, 110); font-size: 14px; margin-bottom: 24px;"><?php esc_html_e( 'This is one of your WooCommerce email templates customized with YayMail. You can modify its colors, layout, and content in the YayMail editor.', 'yaymail' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=yaymail-settings#/customizer/?template=' . $email_id ) ); ?>" target="_blank" style="display: inline-block; background-color: <?php echo esc_attr( YAYMAIL_COLOR_WC_DEFAULT ); ?>; color: #fff; font-size: 12px; padding: 8px 12px; border-radius: 3px; text-decoration: none;"><?php esc_html_e( 'Customized Template', 'yaymail' ); ?></a>
                </div>
            </div>
        <?php
        $content = ob_get_clean();
        yaymail_kses_post_e( $content );
        return $email;
    }

    public function handle_default_preview_content( $content ) {

        if ( ! ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'preview-mail' ) ) ) {
            return $content;
        }

        if ( isset( $_GET['preview_woocommerce_mail'] ) && ! Helpers::is_true( sanitize_text_field( wp_unslash( $_GET['preview_woocommerce_mail'] ) ) ) ) {
            return $content;
        }

        if ( isset( $_GET['rest_route'] ) && $_GET['rest_route'] === '/wc-admin-email/settings/email/send-preview' ) {
            return $content;
        }

        if ( apply_filters( 'yaymail_previewing_template_is_yaymail_template', false ) ) {
            return '';
        }
        return $content;
    }

    /**
     * Add YayMail settings to WooCommerce email options.
     *
     * @param array  $settings        WooCommerce email settings.
     * @param string $current_section Current settings section.
     * @return array Modified settings.
     */
    public function add_settings( $settings, $current_section ) {
        // Only add to the email options section (empty section)
        if ( $current_section !== '' ) {
            return $settings;
        }

        $yaymail_settings = [
            [
                'title' => __( 'WooCommerce Email Designer', 'yaymail' ),
                'type'  => 'title',
                'id'    => 'yaymail_email_designer',
            ],
            [
                'title'    => __( 'Customize WooCommerce Emails', 'yaymail' ),
                'desc'     => '',
                'id'       => 'woocommerce_customizer_emails',
                'type'     => 'yaymail_button',
                'desc_tip' => true,
            ],
            [
                'type' => 'sectionend',
                'id'   => 'yaymail_email_designer',
            ],
        ];

        // Add custom button HTML
        add_action( 'woocommerce_admin_field_yaymail_button', [ $this, 'output_button' ] );

        $settings = array_merge( $settings, $yaymail_settings );

        return $settings;
    }

    /**
     * Output the custom button HTML
     *
     * @param array $value Button field settings.
     */
    public function output_button( $value ) {
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
            </th>
            <td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
                <button type="button" 
                        class="button"
                        id="<?php echo esc_attr( $value['id'] ); ?>"
                        onclick="window.open('<?php echo esc_url( admin_url( 'admin.php?page=yaymail-settings' ) ); ?>', '_blank')"
                >
                    <?php esc_html_e( 'Open YayMail', 'yaymail' ); ?>
                </button>
                <p class="description"><?php esc_html_e( 'Make Woocommerce Emails match your brand. ', 'yaymail' ); ?><a href="https://yaycommerce.com/yaymail-woocommerce-email-customizer/" target="_blank"><?php esc_html_e( 'YayMail - WooCommerce Email Customizer', 'yaymail' ); ?></a> <?php esc_html_e( ' plugin by ', 'yaymail' ); ?> <a href="https://yaycommerce.com/" target="_blank"><?php esc_html_e( 'YayCommerce', 'yaymail' ); ?></a>.</p>
            </td>
        </tr>
        <?php
    }
}
