<?php

namespace YayMail\Models;

use YayMail\Abstracts\BaseElement;
use YayMail\Elements\ElementsHelper;
use YayMail\Elements\Heading;
use YayMail\Elements\Logo;
use YayMail\Elements\Text;
use YayMail\Elements\Footer;
use YayMail\YayMailTemplate;
use YayMail\Utils\SingletonTrait;
use YayMail\PostTypes\TemplatePostType;
use YayMail\Shortcodes\ShortcodesExecutor;
use YayMail\SupportedPlugins;
use YayMail\Utils\TemplateHelpers;
/**
 * Template Model
 *
 * @method static TemplateModel get_instance()
 */
class TemplateModel {
    use SingletonTrait;

    private const POST_TABLE      = 'posts';
    private const POST_META_TABLE = 'postmeta';
    private static $meta_keys     = YayMailTemplate::META_KEYS;

    /**
     * Query post with given arguments.
     * Build query string base on given arguments
     * then use $wpdb to query it.
     *
     * Returns post id or null if not found
     *
     * @param array $args Given args.
     */
    private static function query_template( $args = [] ) {

        if ( ! is_array( $args ) ) {
            return null;
        }

        global $wpdb;
        $posts     = $wpdb->prefix . self::POST_TABLE;
        $post_meta = $wpdb->prefix . self::POST_META_TABLE;
        $post_type = TemplatePostType::POST_TYPE;

        $clauses = [
            'select' => "SELECT posts.ID FROM $posts AS posts",
            'join'   => "JOIN $post_meta AS postmeta ON ( posts.ID = postmeta.post_id )",
            'where'  => "WHERE posts.post_type = '$post_type' AND posts.post_status IN ('publish', 'pending', 'future')",
        ];

        if ( isset( $args['name'] ) ) {
            $template_name     = $args['name'];
            $template_meta_key = self::$meta_keys['name'];
            $clauses['where'] .= $wpdb->prepare(
                ' AND ( postmeta.meta_key=%s AND postmeta.meta_value = %s )',
                $template_meta_key,
                $template_name
            );
        }

        $query_string = implode( ' ', $clauses );
        $post_id      = $wpdb->get_var( $query_string );

        return $post_id;
    }

    /**
     * Finds all YayMail templates and retrieves relevant information about each template.
     *
     * @return array An array of YayMail templates with their corresponding data.
     *   Each template is represented as an associative array containing the following keys:
     *   - 'key': The unique identifier for the template (same as 'id').
     *   - 'template_title': The title of the template.
     *   - 'status': The status of the template.
     *   - 'recipient': The recipient associated with the template.
     *   - 'source': The plugin name that the template originates from.
     *   - 'last_updated': The date and time of the last modification to the template
     *     (formatted according to the WordPress date and time settings) or 'N/A' if not available.
     *   - 'id': The unique identifier for the template (same as 'key').
     *   - '...': Other template-specific data retrieved from YayMail.
     */
    public static function find_all() {
        $templates = [];

        $excluded_templates = apply_filters( 'yaymail_excluded_templates', [ 'yaymail_global_header_footer' ] );

        if ( yaymail_is_wc_installed() ) {
            /* Wc Emails, may or may not be supported by us */
            $wc_emails = \WC_Emails::instance()->get_emails();

            foreach ( $wc_emails as $wc_email ) {
                $template_id = $wc_email->id ?? null;

                if ( ! isset( $template_id ) ) {
                    continue;
                }

                if ( in_array( $template_id, $excluded_templates, true ) ) {
                    continue;
                }

                if ( SupportedPlugins::get_instance()->get_support_info( $template_id )['status'] !== 'already_supported' ) {
                    // Templates is currently not editable, but could be supported by pro/addon
                    $template_data = self::get_uneditable_template( $wc_email, $templates );
                    $templates[]   = $template_data;
                    continue;
                }

                $email_data    = SupportedPlugins::get_instance()->get_yaymail_template_data( $template_id );
                $template_data = self::get_yaymail_template( $email_data );
                if ( isset( $template_data ) ) {
                    unset( $template_data['elements'] );
                    $templates[] = $template_data;
                }
            }//end foreach
        } else {
            $templates = [];
        }//end if

        // Make sure it will show templates for Automatewoo ...
        // Because those templates don't appear in wc_emails
        $template_ids = array_map( fn( $template ) => $template['name'], $templates );
        foreach ( yaymail_get_emails() as $yaymail_email ) {
            $template_id = $yaymail_email->get_id();
            if ( in_array( $template_id, $excluded_templates, true ) ) {
                continue;
            }
            if ( in_array( $template_id, $template_ids, true ) ) {
                continue;
            }

            $email_data    = SupportedPlugins::get_instance()->get_yaymail_template_data( $template_id );
            $template_data = self::get_yaymail_template( $email_data );
            if ( isset( $template_data ) ) {
                unset( $template_data['elements'] );
                $templates[] = $template_data;
            }
        }

        return $templates;
    }

    private static function get_yaymail_template( $email_data ) {
        /* Template is supported and ready to be edited */
        $template = new YayMailTemplate( $email_data->get_id() );
        if ( ! $template->is_exists() || empty( $email_data ) ) {
            return null;
        }

        $template_data                   = $template->get_data();
        $template_data['elements']       = $template->get_elements();
        $template_data['key']            = $template_data['id'];
        $template_data['template_title'] = $email_data->get_title();
        $template_data['status']         = $template_data['status'];
        $template_data['recipient']      = $email_data->get_recipient();
        $template_data['source']         = $email_data->get_source()['plugin_name'] ?? '';
        $template_data['last_updated']   = isset( get_post( $template_data['id'] )->post_modified ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( get_post( $template_data['id'] )->post_modified ) ) : esc_html__( 'N/A', 'yaymail' );
        return $template_data;
    }

    /**
     * Get template data for template that is currently not editable by YayMail and YayMail addons
     * (It might or might not be supported by YayMail)
     *
     * @param object $wc_email The WooCommerce email object.
     * @param array  $templates The list of templates.
     * @return array The template data.
     */
    private static function get_uneditable_template( $wc_email, $templates ): array {

        $existed_post_ids = array_map( fn( $template ) => $template['id'], $templates );
        do {
            // Create a mockup post id
            $mock_post_id = wp_rand( 1000, 10000 );
        } while ( in_array(
            $mock_post_id,
            $existed_post_ids,
            true
        ) );

        $template_id    = $wc_email->id;
        $support_info   = SupportedPlugins::get_instance()->get_support_info( $template_id );
        $support_status = $support_info['status'];
        $source         = $wc_email->source ?? $support_info['addon']['plugin_name'] ?? '';

        // Get title

        $support_status_labels = [
            'pro_needed'    => __( 'Pro', 'yaymail' ),
            'addon_needed'  => __( 'Addon', 'yaymail' ),
            'not_supported' => __( 'N/A', 'yaymail' ),
        ];

        $template_title = $wc_email->title;
        if ( isset( $support_status_labels[ $support_status ] ) ) {
            $template_title .= ' (' . $support_status_labels[ $support_status ] . ')';
        }

        // Get last_updated
        $post         = get_post( $template_id );
        $last_updated = $post && isset( $post->post_modified )
            ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $post->post_modified ) )
            : esc_html__( 'N/A', 'yaymail' );

        return [
            'id'             => $mock_post_id,
            'key'            => $mock_post_id,
            'name'           => $template_id,
            'support_status' => $support_status,
            'addon_info'     => $support_info['addon'],
            'template_title' => $template_title,
            'status'         => 'inactive',
            'recipient'      => yaymail_get_email_recipient_zone( $wc_email ),
            'source'         => $source,
            'last_updated'   => $last_updated,
        ];
    }

    /**
     * Find template by given name
     * Returns null if not found
     *
     * @param string $name
     */
    public static function find_by_name( $name ) {
        $template_id = self::query_template(
            [
                'name' => $name,
            ]
        );
        if ( empty( $template_id ) ) {
            $support_info = SupportedPlugins::get_instance()->get_support_info( $name );
            return [
                'support_status' => $support_info['status'] ?? '',
                'addon_info'     => $support_info['addon'] ?? '',
            ];
        }
        return self::get_meta_data( $template_id, $name );
    }

    /**
     * Find template by post id
     * Returns null if not found
     *
     * @param number $id
     */
    public static function find_by_id( $id ) {
        if ( ! empty( $id ) && ! is_null( get_post( $id ) ) ) {
            return self::get_meta_data( $id );
        }
        return null;
        // Returns null on failure
    }

    public static function insert( $args = false ) {
        // Insert template data to posts table
        $template_args   = [
            'post_content' => '',
            'post_type'    => TemplatePostType::POST_TYPE,
            'post_title'   => '',
            'post_status'  => 'publish',
        ];
        $new_template_id = wp_insert_post( $template_args );

        // Insert data to post meta table
        update_post_meta( $new_template_id, self::$meta_keys['name'], $args['name'] );
        update_post_meta( $new_template_id, self::$meta_keys['elements'], $args['elements'] );
        update_post_meta( $new_template_id, self::$meta_keys['status'], 'inactive' );
        update_post_meta( $new_template_id, self::$meta_keys['background_color'], YAYMAIL_COLOR_BACKGROUND_DEFAULT );
        update_post_meta( $new_template_id, self::$meta_keys['is_v4_supported'], true );
        if ( ! empty( $args['text_link_color'] ) ) {
            update_post_meta( $new_template_id, self::$meta_keys['text_link_color'], YAYMAIL_COLOR_WC_DEFAULT );
        }
        if ( ! empty( $args['content_background_color'] ) ) {
            update_post_meta( $new_template_id, self::$meta_keys['content_background_color'], '#ffffff' );
        }
        if ( ! empty( $args['content_text_color'] ) ) {
            update_post_meta( $new_template_id, self::$meta_keys['content_text_color'], '#000000' );
        }
        if ( ! empty( $args['title_color'] ) ) {
            update_post_meta( $new_template_id, self::$meta_keys['title_color'], '#000000' );
        }
        if ( ! empty( $args['global_header_settings'] ) ) {
            update_post_meta( $new_template_id, self::$meta_keys['global_header_settings'], $args['global_header_settings'] );
        }
        if ( ! empty( $args['global_footer_settings'] ) ) {
            update_post_meta( $new_template_id, self::$meta_keys['global_footer_settings'], $args['global_footer_settings'] );
        }

        // Hook insert data of integrations

        return self::get_meta_data( $new_template_id );
    }

    public static function update( $template_id, $data, $is_save_revision = false ) {
        if ( isset( $data['elements'] ) && is_array( $data['elements'] ) && isset( self::$meta_keys['elements'] ) ) {
            $elements_data = TemplateHelpers::sanitize_elements_recursive( $data['elements'] );
            update_post_meta( $template_id, self::$meta_keys['elements'], $elements_data );
        }

        if ( ! empty( $data['background_color'] ) && isset( self::$meta_keys['background_color'] ) ) {
            update_post_meta( $template_id, self::$meta_keys['background_color'], $data['background_color'] );
        }

        if ( ! empty( $data['text_link_color'] ) && isset( self::$meta_keys['text_link_color'] ) ) {
            update_post_meta( $template_id, self::$meta_keys['text_link_color'], $data['text_link_color'] );
        }

        if ( ! empty( $data['content_background_color'] ) && isset( self::$meta_keys['content_background_color'] ) ) {
            update_post_meta( $template_id, self::$meta_keys['content_background_color'], $data['content_background_color'] );
        }

        if ( ! empty( $data['content_text_color'] ) && isset( self::$meta_keys['content_text_color'] ) ) {
            update_post_meta( $template_id, self::$meta_keys['content_text_color'], $data['content_text_color'] );
        }

        if ( ! empty( $data['title_color'] ) && isset( self::$meta_keys['title_color'] ) ) {
            update_post_meta( $template_id, self::$meta_keys['title_color'], $data['title_color'] );
        }

        if ( isset( $data['status'] ) && isset( self::$meta_keys['status'] ) ) {
            update_post_meta( $template_id, self::$meta_keys['status'], $data['status'] );
        }

        if ( ! empty( $data['global_header_settings'] ) && isset( self::$meta_keys['global_header_settings'] ) ) {
            update_post_meta( $template_id, self::$meta_keys['global_header_settings'], $data['global_header_settings'] );
        }

        if ( ! empty( $data['global_footer_settings'] ) && isset( self::$meta_keys['global_footer_settings'] ) ) {
            update_post_meta( $template_id, self::$meta_keys['global_footer_settings'], $data['global_footer_settings'] );
        }

        if ( isset( $data['preheader'] ) && isset( self::$meta_keys['preheader'] ) ) {
            update_post_meta( $template_id, self::$meta_keys['preheader'], sanitize_text_field( $data['preheader'] ) );
        }

        // Update post_modified
        $post_data = [
            'ID'                => $template_id,
            'post_modified'     => current_time( 'mysql' ),
            'post_modified_gmt' => current_time( 'mysql', 1 ),
        ];
        wp_update_post( $post_data, true );

        if ( $is_save_revision ) {
            try {
                $revision_model = RevisionModel::get_instance();
                $new_revision   = $revision_model->save( $template_id, $data );
            } catch ( \Throwable $th ) {
                $new_revision = null;
            }

            return [
                'updated_data' => self::get_meta_data( $template_id ),
                'new_revision' => $new_revision,
            ];
        }

        return self::get_meta_data( $template_id );
    }

    public static function delete( $template_id ) {
        wp_delete_post( $template_id, true );
        // TODO: remove relatives.
    }

    /**
     * Retrieve the global header and footer elements.
     *
     * @return array An array containing the global header and footer elements:
     * - 'global_header_elements': array.
     * - 'global_footer_elements': array.
     */
    public static function get_global_header_and_footer() {
        $query_args  = [
            'name' => 'yaymail_global_header_footer',
        ];
        $template_id = self::query_template( $query_args );
        if ( empty( $template_id ) ) {
            self::insert(
                [
                    'name'     => 'yaymail_global_header_footer',
                    'elements' => yaymail_get_default_elements( 'yaymail_global_header_footer' ),
                ]
            );
            $template_id = self::query_template( $query_args );
        }
        $retrieved_meta_data = self::get_meta_data( $template_id );
        $elements            = isset( $retrieved_meta_data['elements'] ) ? $retrieved_meta_data['elements'] : [];

        if ( empty( $elements ) ) {
            /**
             * Get default elements
             */
            $global_header_footer_instance = \YayMail\Emails\GlobalHeaderFooter::get_instance();
            $elements                      = $global_header_footer_instance->get_default_elements();
        }

        $divider_index = array_search( 'skeleton_divider', array_column( $elements, 'type' ), true );

        $global_header_elements = [];
        $global_footer_elements = [];

        if ( false !== $divider_index ) {
            $global_header_elements = array_slice( $elements, 0, $divider_index );
            $global_footer_elements = array_slice( $elements, $divider_index + 1 );
        }

        return [
            'global_header_elements' => $global_header_elements,
            'global_footer_elements' => $global_footer_elements,
        ];
    }

    /**
     * Get template meta data from Database
     *
     * @param number $template_post_id
     * @param string $template_name
     */
    private static function get_meta_data( $template_post_id, $template_name = null ) {

        if ( empty( $template_name ) ) {
            $template_name = self::query_meta_data( $template_post_id, self::$meta_keys['name'], '' );
        }
        $status = self::query_meta_data( $template_post_id, self::$meta_keys['status'], 'inactive' );

        $post          = isset( $template_post_id ) ? get_post( $template_post_id ) : null;
        $post_modified = isset( $post ) ? $post->post_modified : '';

        // /** For editable templates */
        $template_elements = self::query_meta_data( $template_post_id, self::$meta_keys['elements'], [] );

        $support_info = SupportedPlugins::get_instance()->get_support_info( $template_name );
        if ( $support_info['status'] !== 'already_supported' && 'wp-core-edd-password_reset' !== $template_name ) {
            // Template is not editable
            $template_elements = self::get_uneditable_template_placeholder_elements( $support_info );
        } elseif ( isset( $post ) ) {
            // TODO: how to store global default value
            $background_color         = self::query_meta_data( $template_post_id, self::$meta_keys['background_color'], YayMailTemplate::DEFAULT_DATA['background_color'] );
            $text_link_color          = self::query_meta_data( $template_post_id, self::$meta_keys['text_link_color'], YayMailTemplate::DEFAULT_DATA['text_link_color'] );
            $title_color              = self::query_meta_data( $template_post_id, self::$meta_keys['title_color'], YayMailTemplate::DEFAULT_DATA['title_color'] );
            $content_background_color = self::query_meta_data( $template_post_id, self::$meta_keys['content_background_color'], YayMailTemplate::DEFAULT_DATA['content_background_color'] );
            $content_text_color       = self::query_meta_data( $template_post_id, self::$meta_keys['content_text_color'], YayMailTemplate::DEFAULT_DATA['content_text_color'] );
            $global_header_settings   = self::query_meta_data( $template_post_id, self::$meta_keys['global_header_settings'], YayMailTemplate::DEFAULT_DATA['global_header_settings'] );
            $global_footer_settings   = self::query_meta_data( $template_post_id, self::$meta_keys['global_footer_settings'], YayMailTemplate::DEFAULT_DATA['global_footer_settings'] );
            $preheader                = self::query_meta_data( $template_post_id, self::$meta_keys['preheader'], YayMailTemplate::DEFAULT_DATA['preheader'] );
        }

        return [
            'id'                       => $template_post_id,
            'key'                      => $template_post_id,
            'name'                     => $template_name,
            'elements'                 => ElementsHelper::filter_available_elements( $template_elements, $template_name ),
            'status'                   => $status,
            'title_color'              => $title_color ?? '#000000',
            'background_color'         => $background_color ?? YAYMAIL_COLOR_BACKGROUND_DEFAULT,
            'text_link_color'          => $text_link_color ?? YAYMAIL_COLOR_WC_DEFAULT,
            'content_background_color' => $content_background_color ?? '#ffffff',
            'content_text_color'       => $content_text_color ?? '#000000',
            'support_status'           => $support_info['status'] ?? 'already_supported',
            'addon_info'               => $support_info['addon'] ?? '',
            'post_modified'            => $post_modified,
            'global_header_settings'   => isset( $global_header_settings ) ? $global_header_settings : [],
            'global_footer_settings'   => isset( $global_footer_settings ) ? $global_footer_settings : [],
            'preheader'                => isset( $preheader ) ? $preheader : YayMailTemplate::DEFAULT_DATA['preheader'],
            'email_subject'            => self::get_email_subject( $template_name ),
        ];
    }

    /**
     * Get email subject from WooCommerce email settings.
     *
     * @param string $template_name Email / template id.
     * @return string|null
     */
    private static function get_email_subject( $template_name ) {
        $settings = self::get_wc_email_settings( $template_name );
        return is_array( $settings ) && isset( $settings['subject'] ) ? (string) $settings['subject'] : null;
    }

    /**
     * Save email subject to WooCommerce email settings (same option as Woo admin).
     *
     * @param string $template_name Email / template id.
     * @param string $subject       Subject line.
     */
    public static function save_email_subject( $template_name, $subject ) {
        if ( ! is_string( $template_name ) || '' === $template_name ) {
            return;
        }

        $settings            = self::get_wc_email_settings( $template_name );
        $settings['subject'] = sanitize_text_field( $subject );
        update_option( self::get_wc_email_settings_option_name( $template_name ), $settings );
    }

    /**
     * @param string $template_name Email / template id.
     * @return string
     */
    private static function get_wc_email_settings_option_name( $template_name ) {
        return "woocommerce_{$template_name}_settings";
    }

    /**
     * @param string $template_name Email / template id.
     * @return array
     */
    private static function get_wc_email_settings( $template_name ) {
        $settings = get_option( self::get_wc_email_settings_option_name( $template_name ), [] );
        return is_array( $settings ) ? $settings : [];
    }

    private static function get_uneditable_template_placeholder_elements( array $support_info ): array {
        $elements = [];

        $elements[] = BaseElement::reduce_new_element( Logo::get_data() );
        $elements[] = BaseElement::reduce_new_element( Heading::get_data() );
        $elements[] = BaseElement::reduce_new_element( Text::get_data() );
        $elements[] = BaseElement::reduce_new_element( Footer::get_data( [ 'rich_text' => '<p style="font-size: 14px;margin: 0px 0px 16px; text-align: center;">' . esc_html( get_bloginfo( 'name' ) ) . '&nbsp;- Built with <a style="color: ' . esc_attr( YAYMAIL_COLOR_WC_DEFAULT ) . '; font-weight: normal; text-decoration: underline;" href="https://woocommerce.com" target="_blank" rel="noopener">WooCommerce</a></p>' ] ) );

        return $elements;
    }


    private static function query_meta_data( $post_id, $meta_name, $default ) {
        if ( ! isset( $post_id ) ) {
            return $default;
        }

        $meta_value = get_post_meta( $post_id, $meta_name, true );

        if ( empty( $meta_value ) ) {
            return $default;
        }
        return $meta_value;
    }

    public static function get_shortcodes_by_template_name_and_order_id( $template_name, $order_id ) {
        do_action( 'yaymail_before_order_id_changed', $order_id );

        $shortcodes = yaymail_get_email_shortcodes( $template_name );

        // TODO: check shortcodes
        // $shortcodes = apply_filters( 'yaymail_extra_shortcodes', $shortcodes, $template_name, $order_id );

        $executor_data = self::get_shortcode_executor_data( $template_name, $order_id );
        $executor      = new ShortcodesExecutor( $shortcodes, $executor_data );
        return $executor->get_shortcodes_content();
    }

    /**
     * Gets data needed for executing a YayMail shortcode.
     *
     * @param string $template_name The template name.
     * @param string $order_id The order ID. Uses sample data if empty or 'sample_order'.
     * @return array [template, render_data, settings, is_placeholder]
     */
    public static function get_shortcode_executor_data( $template_name, $order_id ) {

        return [
            'template'       => new YayMailTemplate( $template_name ),
            'render_data'    => empty( $order_id ) || ( ! empty( $order_id ) && 'sample_order' === $order_id ) ? [ 'is_sample' => true ] : [ 'order' => wc_get_order( $order_id ) ],
            'settings'       => yaymail_settings(),
            'is_placeholder' => true,
        ];
    }

    public static function get_elements_for_template( $template_id ): array {
        $support_info = SupportedPlugins::get_instance()->get_support_info( $template_id );
        if ( $support_info['status'] !== 'already_supported' ) {
            $elements = array_map(
                function( $element ) {
                    $element['available'] = false;
                    return $element;
                },
                yaymail_get_email_elements_data( 'new_order' )
            );
            return $elements;
        }
        return yaymail_get_email_elements_data( $template_id );
    }

    public static function get_short_data_by_name( $name ) {
        $template_id = self::query_template( [ 'name' => $name ] );
        if ( empty( $template_id ) ) {
            return null;
        }
        return [
            'id'     => $template_id,
            'status' => self::query_meta_data( $template_id, self::$meta_keys['status'], 'inactive' ),
        ];
    }
}