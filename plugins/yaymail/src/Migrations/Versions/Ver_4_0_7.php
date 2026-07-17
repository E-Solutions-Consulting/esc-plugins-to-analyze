<?php

namespace YayMail\Migrations\Versions;

use Exception;
use YayMail\Migrations\AbstractMigration;
use YayMail\Utils\SingletonTrait;
use YayMail\YayMailTemplate;

/**
 * Script to migrate from YayMail legacy (pre 4.0.7) to 4.0.7
 */
final class Ver_4_0_7 extends AbstractMigration {

    use SingletonTrait;

    private function __construct() {
        parent::__construct( '3.9.9', '4.0.7' );
    }

    protected function up() {
        $this->migrate_templates();
    }

    /**
     * Private functions
     */
    private function migrate_templates() {
        $this->logger->log( 'Start migrating templates to 4.0.7' );
        global $wpdb;

        // Make sure the backup existed
        if ( empty( $this->backup_option_name ) || empty( get_option( $this->backup_option_name ) ) ) {
            throw new Exception( 'Could not find backup option' );
        }

        $template_posts_query = "
            SELECT * 
            FROM {$wpdb->posts}
            WHERE post_type = 'yaymail_template'
        ";
        $template_posts       = $wpdb->get_results( $template_posts_query ); // phpcs:ignore
        if ( empty( $template_posts ) ) {
            $this->logger->log( 'There is no template to be migrated' );
            return;
        }

        foreach ( $template_posts  as $template ) {
            if ( empty( $template->ID ) ) {
                continue;
            }
            /**
             * ==========================
             * Start Elements migrations
             */

            $elements = get_post_meta( $template->ID, \YayMail\YayMailTemplate::META_KEYS['elements'], true );

            if ( empty( $elements ) ) {
                continue;
            }

            $yaymail_settings     = yaymail_settings();
            $payment_display_mode = $yaymail_settings['payment_display_mode'];

            $element_text = [
                'id'        => uniqid(),
                'type'      => 'text',
                'name'      => 'Text',
                'available' => true,
                'data'      => [
                    'padding'          => [
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'background_color' => '#fff',
                    'text_color'       => '#636363',
                    'font_family'      => 'Helvetica,Roboto,Arial,sans-serif',
                    'rich_text'        => '[yaymail_payment_instructions]',
                ],
            ];

            if ( $payment_display_mode === 'yes' ) {
                $has_payment_instructions = false;
                $order_details_index      = null;

                foreach ( $elements as $key => $element ) {
                    // Check if the element contains the payment instructions shortcode
                    if ( strpos( $element['data']['rich_text'] ?? '', '[yaymail_payment_instructions]' ) !== false ) {
                        $has_payment_instructions = true;
                        break;
                        // Shortcode already exists, no further action needed
                    }

                    // Store the position of the 'order_details' element (only the first match)
                    if ( $order_details_index === null && ( $element['type'] ?? '' ) === 'order_details' ) {
                        $order_details_index = $key;
                    }
                }

                // If the shortcode was not found and we know where to insert, do it now
                if ( ! $has_payment_instructions && $order_details_index !== null ) {
                    array_splice( $elements, $order_details_index, 0, [ $element_text ] );
                }
            }//end if

            update_post_meta( $template->ID, \YayMail\YayMailTemplate::META_KEYS['elements'], $elements );

            /**
             * Finish Template settings migrations
             * ==========================
             */

        }//end foreach
        $this->logger->log( 'Done migrating templates to 4.0.7' );
    }
}
