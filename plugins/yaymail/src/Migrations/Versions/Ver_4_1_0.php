<?php

namespace YayMail\Migrations\Versions;

use Exception;
use YayMail\Migrations\AbstractMigration;
use YayMail\Models\SettingModel;
use YayMail\Utils\SingletonTrait;
use YayMail\YayMailTemplate;

/**
 * Script to migrate from YayMail legacy (pre 4.0.7) to 4.0.7
 */
final class Ver_4_1_0 extends AbstractMigration {

    use SingletonTrait;

    private function __construct() {
        parent::__construct( '4.0.9', '4.1.0' );
    }

    protected function up() {
        $this->migrate_templates();
    }

    /**
     * Private functions
     */
    private function migrate_templates() {
        $this->logger->log( 'Start migrating templates to 4.1.0' );
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

        $has_global_header_foter_in_templates = false;
        $templates_need_to_hide_header        = [];
        $templates_need_to_hide_footer        = [];

        $default_template_global_header_settings = YayMailTemplate::DEFAULT_DATA['global_header_settings'];
        $default_template_global_footer_settings = YayMailTemplate::DEFAULT_DATA['global_footer_settings'];

        foreach ( $template_posts  as $template ) {
            if ( empty( $template->ID ) ) {
                continue;
            }
            /**
             * ==========================
             * Start Elements migrations
             */

            $elements = get_post_meta( $template->ID, YayMailTemplate::META_KEYS['elements'], true );

            if ( empty( $elements ) ) {
                continue;
            }

            $has_global_header_in_template = false;
            $has_global_footer_in_template = false;

            $template_global_header_override_content = null;
            $template_global_header_settings         = get_post_meta( $template->ID, YayMailTemplate::META_KEYS['global_header_settings'], true );
            $template_global_footer_settings         = get_post_meta( $template->ID, YayMailTemplate::META_KEYS['global_footer_settings'], true );

            $legacy_default_global_header_override_content = '<h1 style="font-size: 30px; font-weight: 300; line-height: normal; margin: 0px; color: inherit; text-align: left;">Email Heading</h1>';

            if ( empty( $template_global_footer_settings ) ) {
                $template_global_footer_settings = $default_template_global_footer_settings;
            }
            if ( empty( $template_global_header_settings ) ) {
                $template_global_header_settings = $default_template_global_header_settings;
            }

            foreach ( $elements as $key => $element ) {
                if ( $element['type'] !== 'global_header' && $element['type'] !== 'global_footer' ) {
                    continue;
                }
                if ( $element['type'] === 'global_header' ) {
                    $has_global_header_in_template = true;
                    if ( $legacy_default_global_header_override_content !== $element['data']['rich_text'] ) {
                        $template_global_header_override_content = $element['data']['rich_text'];
                    }
                }
                if ( $element['type'] === 'global_footer' ) {
                    $has_global_footer_in_template = true;
                }
            }

            if ( $has_global_header_in_template || $has_global_footer_in_template ) {
                $has_global_header_foter_in_templates = true;
            }

            if ( ! $has_global_header_in_template ) {
                $templates_need_to_hide_header[] = $template->ID;
            } elseif ( ! empty( $template_global_header_override_content ) ) {
                $template_global_header_settings['content_override'] = true;
                $template_global_header_settings['heading_content']  = $template_global_header_override_content;
            }

            if ( ! $has_global_footer_in_template ) {
                $templates_need_to_hide_footer[] = $template->ID;
            }

            update_post_meta( $template->ID, YayMailTemplate::META_KEYS['global_header_settings'], $template_global_header_settings );
            update_post_meta( $template->ID, YayMailTemplate::META_KEYS['global_footer_settings'], $template_global_footer_settings );

            /**
             * Finish Template settings migrations
             * ==========================
             */

        }//end foreach

        if ( $has_global_header_foter_in_templates ) {
            SettingModel::update(
                [
                    'global_header_footer_enabled' => true,
                ]
            );
            foreach ( $templates_need_to_hide_header as $template_id ) {
                $template_global_header_settings = get_post_meta( $template->ID, YayMailTemplate::META_KEYS['global_header_settings'], true );
                if ( empty( $template_global_footer_settings ) ) {
                    $template_global_footer_settings = $default_template_global_footer_settings;
                }
                $template_global_header_settings['hidden'] = true;
                update_post_meta( $template_id, YayMailTemplate::META_KEYS['global_header_settings'], $template_global_header_settings );
            }
            foreach ( $templates_need_to_hide_footer as $template_id ) {
                $template_global_footer_settings = get_post_meta( $template->ID, YayMailTemplate::META_KEYS['global_footer_settings'], true );
                if ( empty( $template_global_header_settings ) ) {
                    $template_global_header_settings = $default_template_global_header_settings;
                }
                $template_global_footer_settings['hidden'] = true;
                update_post_meta( $template_id, YayMailTemplate::META_KEYS['global_footer_settings'], $template_global_footer_settings );
            }
        }//end if

        $this->logger->log( 'Done migrating templates to 4.0.7' );
    }
}
