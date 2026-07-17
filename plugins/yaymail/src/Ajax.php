<?php

namespace YayMail;

use YayMail\Utils\SingletonTrait;
use YayMail\Models\SettingModel;
use YayMail\Models\TemplateModel;
use YayMail\Models\RevisionModel;
use YayMail\Migrations\MainMigration;
use YayMail\Utils\Helpers;
use YayMail\Migrations\AbstractMigration;

/**
 * I18n Logic
 *
 * @method static Ajax get_instance()
 */
class Ajax {
    use SingletonTrait;

    protected function __construct() {
        $this->init_hooks();
    }

    protected function init_hooks() {
        add_action( 'wp_ajax_yaymail_preview_mail', [ $this, 'preview_mail' ] );
        add_action( 'wp_ajax_yaymail_preview_mail_for_woo', [ $this, 'preview_mail_for_woo' ] );
        add_action( 'wp_ajax_yaymail_send_test_mail', [ $this, 'send_test_mail' ] );
        add_action( 'wp_ajax_yaymail_install_yaysmtp', [ $this, 'install_yaysmtp' ] );
        add_action( 'wp_ajax_yaymail_get_custom_hook_html', [ $this, 'get_custom_hook_html' ] );
        add_action( 'wp_ajax_yaymail_get_template_data_onload', [ $this, 'get_template_data_onload' ] );
        add_action( 'wp_ajax_yaymail_export_templates', [ $this, 'export_templates' ] );
        add_action( 'wp_ajax_yaymail_import_templates', [ $this, 'import_templates' ] );
        add_action( 'wp_ajax_yaymail_review', [ $this, 'yaymail_review' ] );
        add_action( 'wp_ajax_yaymail_change_ghf_tour', [ $this, 'change_ghf_tour' ] );
        add_action( 'wp_ajax_yaymail_dismiss_multi_select_notice', [ $this, 'dismiss_multi_select_notice' ] );
        add_action( 'wp_ajax_yaymail_export_state', [ $this, 'export_state' ] );
        add_action( 'wp_ajax_yaymail_import_state', [ $this, 'import_state' ] );
        add_action( 'wp_ajax_yaymail_dismiss_new_element_notification', [ $this, 'dismiss_new_element_notification' ] );
    }

    public function import_state() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yaymail_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'yaymail' ) ] );
        }
        try {
            $import_file = isset( $_FILES['import_file'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_FILES['import_file'] ) ) : null;
            if ( ! $import_file ) {
                return wp_send_json_error( [ 'mess' => __( 'Can\'t find import file', 'yaymail' ) ] );
            }

            if ( $import_file['type'] !== 'application/zip' ) {
                return wp_send_json_error( [ 'mess' => __( 'Invalid file type. Please upload a ZIP file.', 'yaymail' ) ] );
            }

            $zip = new \ZipArchive();
            if ( $zip->open( $import_file['tmp_name'] ) !== true ) {
                return wp_send_json_error( [ 'mess' => __( 'Cannot open ZIP file.', 'yaymail' ) ] );
            }

            $state_data = null;
            for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                $filename = $zip->getNameIndex( $i );
                if ( $filename === 'yaymail_backup.json' ) {
                    $state_data = $zip->getFromIndex( $i );
                    break;
                }
            }

            $zip->close();

            if ( ! $state_data ) {
                return wp_send_json_error( [ 'mess' => __( 'Cannot find yaymail_backup.json in the ZIP file.', 'yaymail' ) ] );
            }

            $imported_data = json_decode( $state_data );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                return wp_send_json_error( [ 'mess' => __( 'Invalid JSON data in the state file.', 'yaymail' ) ] );
            }

            if ( empty( $imported_data->posts ) || empty( $imported_data->postmeta ) || empty( $imported_data->options ) ) {
                return wp_send_json_error( [ 'mess' => __( 'Invalid state file structure.', 'yaymail' ) ] );
            }

            $migration_model = \YayMail\Models\MigrationModel::get_instance();

            $source_version = $imported_data->version;

            $imported_posts = array_values(
                array_filter(
                    $imported_data->posts,
                    function( $post ) {
                        return isset( $post->post_type ) && 'yaymail_template' === $post->post_type;
                    }
                )
            );

            $imported_postmeta = array_values(
                array_filter(
                    $imported_data->postmeta,
                    function( $postmeta ) use ( $imported_posts ) {
                        if ( empty( $imported_posts ) ) {
                            return false;
                        }
                        if ( ! isset( $postmeta->post_id ) || ! isset( $postmeta->meta_key ) ) {
                            return false;
                        }
                        if ( strpos( (string) $postmeta->meta_key, 'yaymail' ) === false ) {
                            return false;
                        }
                        foreach ( $imported_posts as $post ) {
                            if ( isset( $post->ID ) && (int) $post->ID === (int) $postmeta->post_id ) {
                                return true;
                            }
                        }
                        return false;
                    }
                )
            );

            $imported_options = array_values(
                array_filter(
                    $imported_data->options,
                    function( $option ) {
                        return isset( $option->option_name ) && strpos( (string) $option->option_name, 'yaymail' ) !== false;
                    }
                )
            );

            $backup_data = [
                'posts'        => $imported_posts,
                'postmeta'     => $imported_postmeta,
                'options'      => $imported_options,
                'created_date' => $imported_data->created_date ?? current_datetime()->format( 'Y-m-d H:i:s' ),
                'name'         => '_yaymail_import_backup_' . $source_version,
                'version'      => $source_version,
            ];

            $migration_model->reset( $backup_data );

            wp_send_json_success(
                [
                    'message' => __( 'Import state successfully', 'yaymail' ),
                ]
            );
        } catch ( \Error $error ) {
            yaymail_get_logger( $error );
            wp_send_json_error( [ 'mess' => __( 'Import failed: ', 'yaymail' ) . $error->getMessage() ] );
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
            wp_send_json_error( [ 'mess' => __( 'Import failed: ', 'yaymail' ) . $exception->getMessage() ] );
        }//end try
    }

    public function export_state() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yaymail_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'yaymail' ) ] );
        }

        try {
            global $wpdb;

            /**
             * Backup posts and postmeta
             */
            $query_posts            = "
            SELECT *
            FROM {$wpdb->posts}
            WHERE post_type = 'yaymail_template'
            ";
            $yaymail_template_posts = $wpdb->get_results( $query_posts );// phpcs:ignore

            $query_postmeta            = "
                SELECT *
                FROM {$wpdb->postmeta}
                WHERE meta_key LIKE '%yaymail%'
            ";
            $yaymail_template_postmeta = $wpdb->get_results( $query_postmeta );// phpcs:ignore

            $backup_data = [
                'posts'    => $yaymail_template_posts,
                'postmeta' => $yaymail_template_postmeta,
            ];
            /** ****************************** */

            /**
             * Backup options
             */
            $query_options          = "
            SELECT *
            FROM {$wpdb->options}
            WHERE option_name LIKE '%yaymail%'
        ";
            $yaymail_options        = $wpdb->get_results( $query_options ); // phpcs:ignore
            $backup_data['options'] = $yaymail_options;

            $backup_data['created_date'] = current_datetime()->format( 'Y-m-d H:i:s' );
            $backup_data['version']      = get_option( 'yaymail_version_backup' );

            $backup_data = apply_filters( 'yaymail_backup_state_data', $backup_data );

            wp_send_json_success(
                [
                    'message'   => 'success',
                    'data'      => $backup_data,
                    'file_name' => 'yaymail_export_backup_' . gmdate( 'm-d-Y' ),
                ]
            );

        } catch ( \Error $error ) {
            yaymail_get_logger( $error );
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
        }//end try
    }

    public function sanitize( $array ) {

        return wp_kses_post_deep( $array );
    }

    public function process_plugin_installer( $slug ) {
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

        $api = plugins_api(
            'plugin_information',
            [
                'slug'   => $slug,
                'fields' => [
                    'short_description' => false,
                    'sections'          => false,
                    'requires'          => false,
                    'rating'            => false,
                    'ratings'           => false,
                    'downloaded'        => false,
                    'last_updated'      => false,
                    'added'             => false,
                    'tags'              => false,
                    'compatibility'     => false,
                    'homepage'          => false,
                    'donate_link'       => false,
                ],
            ]
        );

        $skin = new \WP_Ajax_Upgrader_Skin();

        $plugin_upgrader = new \Plugin_Upgrader( $skin );

        try {
            $result = $plugin_upgrader->install( $api->download_link );

            if ( is_wp_error( $result ) ) {
                yaymail_get_logger( $result );
            }

            return true;
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
        }

        return false;
    }

    public function install_yaysmtp() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yaymail_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'yaymail' ) ] );
        }

        if ( ! current_user_can( 'install_plugins' ) && ! current_user_can( 'activate_plugins' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'You do not have permission to install plugins', 'yaymail' ) ] );
        }

        try {
            $is_installed = $this->process_plugin_installer( 'yaysmtp' );

            if ( false === $is_installed ) {
                wp_send_json_error( [ 'message' => $is_installed ] );
            }

            $result = activate_plugin( 'yaysmtp/yay-smtp.php' );

            if ( is_wp_error( $result ) ) {
                return wp_send_json_error( [ 'mess' => esc_html( $result->get_error_message() ) ] );
            }

            wp_send_json_success(
                [
                    'installed' => null === $result,
                ]
            );

        } catch ( \Error $error ) {
            yaymail_get_logger( $error );
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
        }//end try
    }

    public function send_test_mail() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yaymail_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'yaymail' ) ] );
        }
        try {
            $template_name = isset( $_POST['template_name'] ) ? sanitize_text_field( wp_unslash( $_POST['template_name'] ) ) : '';
            $order_id      = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : 'sample_order';
            $email         = isset( $_POST['email'] ) ? sanitize_text_field( $_POST['email'] ) : '';
            $is_wc_email   = strpos( $template_name, 'wp-core-' ) !== 0;

            if ( empty( $template_name ) ) {
                return wp_send_json_error( [ 'mess' => __( 'Can\'t find template', 'yaymail' ) ] );
            }

            if ( empty( $order_id ) && $is_wc_email ) {
                return wp_send_json_error( [ 'mess' => __( 'Can\'t find order', 'yaymail' ) ] );
            }

            if ( empty( $email ) ) {
                return wp_send_json_error( [ 'mess' => __( 'Can\'t find email', 'yaymail' ) ] );
            }

            $template = new YayMailTemplate( $template_name );

            $render_data = [];

            if ( empty( $order_id ) || ( 'sample_order' === $order_id ) ) {
                $render_data['is_sample'] = true;
            } else {
                $render_data['order'] = wc_get_order( $order_id );
            }

            $render_data['is_customized_preview'] = true;
            // check if email template on preview and send test mail

            update_option( 'yaymail_default_email_test', $email );

            $html = $template->get_content( $render_data );

            $headers = "Content-Type: text/html\r\n";
            $subject = __( 'Email Test', 'yaymail' );

            if ( $is_wc_email ) {
                $class_wc_email = \WC_Emails::instance();
                $send_mail      = $class_wc_email->send( $email, $subject, $html, $headers, [] );
            } else {
                $send_mail = wp_mail( $email, $subject, $html, $headers, [] );
            }
            if ( ! $send_mail ) {
                return wp_send_json_error( [ 'mess' => __( 'Can\'t send email', 'yaymail' ) ] );
            }

            wp_send_json_success(
                [
                    'email'             => $email,
                    'send_mail_success' => $send_mail,
                ]
            );
        } catch ( \Error $error ) {
            yaymail_get_logger( $error );
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
        }//end try
    }

    public function preview_mail() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yaymail_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'yaymail' ) ] );
        }
        try {
            $order_id         = ! empty( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : 'sample_order';
            $template_data    = isset( $_POST['template_data'] ) ? $this->sanitize( json_decode( wp_unslash( $_POST['template_data'] ), true ) ) : []; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $unsaved_settings = isset( $_POST['unsaved_settings'] ) ? $this->sanitize( wp_unslash( $_POST['unsaved_settings'] ) ) : []; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

            if ( empty( $template_data ) ) {
                return wp_send_json_error( [ 'mess' => __( 'Can\'t find template', 'yaymail' ) ] );
            }

            if ( empty( $order_id ) ) {
                return wp_send_json_error( [ 'mess' => __( 'Can\'t find order', 'yaymail' ) ] );
            }

            $template = new YayMailTemplate( $template_data['name'] );

            if ( ! empty( $unsaved_settings ) ) {
                global $yaymail_unsaved_settings;
                $yaymail_unsaved_settings = $unsaved_settings;
            }

            $template->set_background_color( $template_data['background_color'] );
            $template->set_text_link_color( $template_data['text_link_color'] );
            $template->set_global_header_settings( $template_data['global_header_settings'] );
            $template->set_global_footer_settings( $template_data['global_footer_settings'] );
            $template->set_elements( $template_data['elements'] );

            $render_data = [];

            if ( empty( $order_id ) || ( 'sample_order' === $order_id ) ) {
                $render_data['is_sample'] = true;
            } else {
                $render_data['order'] = wc_get_order( $order_id );
            }

            $render_data['is_customized_preview'] = true;
            // check if email template on preview and send test mail

            $html = $template->get_content( $render_data );

            if ( strpos( $template->get_name(), 'wp-core-' ) !== 0 ) {
                // TODO: render with passing settings
                $current_email = null;
                $subject       = 'Sample Subject';
                $emails        = wc()->mailer()->emails;
                foreach ( $emails as $email ) {
                    if ( $email->id === $template_data['name'] ) {
                        $current_email = $email;
                        if ( method_exists( $current_email, 'set_object' ) ) {
                            if ( ! empty( $render_data['order'] ) && is_a( $render_data['order'], '\WC_Order' ) ) {
                                $current_email->set_object( $render_data['order'] );
                            } else {
                                $current_email->set_object( Helpers::get_dummy_order() );
                            }
                        }
                        break;
                    }
                }
            }

            if ( ! empty( $current_email ) ) {
                $subject = $current_email->get_subject();
            }
            $email_address = wp_get_current_user()->user_email ?? 'sample@example.com';

            wp_send_json_success(
                [
                    'html'          => $html,
                    'subject'       => $subject,
                    'email_address' => $email_address,
                ]
            );
        } catch ( \Error $error ) {
            yaymail_get_logger( $error );
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
        }//end try
    }

    public function preview_mail_for_woo() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yaymail_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'yaymail' ) ] );
        }
        try {
            $template_name   = isset( $_POST['template_name'] ) ? sanitize_text_field( wp_unslash( $_POST['template_name'] ) ) : '';
            $search_order_id = isset( $_POST['search_order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['search_order_id'] ) ) : null;
            $email_address   = isset( $_POST['email_address'] ) ? sanitize_text_field( wp_unslash( $_POST['email_address'] ) ) : '';

            $email_preview_output = PreviewEmail\PreviewEmailWoo::email_preview_output( $search_order_id, $template_name, $email_address );

            $email_preview_output = apply_filters( 'yaymail_preview_email', $email_preview_output, $search_order_id, $template_name, $email_address );

            $send_mail = false;

            if ( ! empty( $email_address ) && ! empty( $email_preview_output['html'] ) ) {
                $headers        = "Content-Type: text/html\r\n";
                $class_wc_email = \WC_Emails::instance();
                $subject        = __( 'Email Preview', 'yaymail' );
                $send_mail      = $class_wc_email->send( $email_address, $subject, $email_preview_output['html'], $headers, [] );
                if ( ! $send_mail ) {
                    return wp_send_json_error( [ 'mess' => __( 'Can\'t send email', 'yaymail' ) ] );
                }
            }

            wp_send_json_success(
                [
                    'html'                  => ! empty( $email_preview_output['html'] ) ? $email_preview_output['html'] : __( 'No email content found', 'yaymail' ),
                    'subject'               => ! empty( $email_preview_output['subject'] ) ? $email_preview_output['subject'] : __( 'No subject found', 'yaymail' ),
                    'is_disabled_send_mail' => ! empty( $email_preview_output['is_disabled_send_mail'] ) ? $email_preview_output['is_disabled_send_mail'] : false,
                    'send_mail_success'     => $send_mail,
                ]
            );
        } catch ( \Error $error ) {
            yaymail_get_logger( $error );
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
        }//end try
    }

    public function export_templates() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yaymail_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'yaymail' ) ] );
        }
        try {
            $templates = isset( $_POST['templates'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['templates'] ) ) : [];
            // TODO: sanitize
            $default     = [
                'post_type'      => 'yaymail_template',
                'post_status'    => [ 'publish', 'pending', 'future' ],
                'posts_per_page' => '-1',
                'meta_query'     => [
                    [
                        'key'     => YayMailTemplate::META_KEYS['name'],
                        'value'   => $templates,
                        'compare' => 'IN',
                    ],
                ],
            ];
            $export_data = [];
            $query       = new \WP_Query( $default );
            if ( $query->have_posts() ) {
                $posts = $query->get_posts();
                foreach ( $posts as $post ) {
                    $template_name            = get_post_meta( $post->ID, YayMailTemplate::META_KEYS['name'], true );
                    $elements                 = get_post_meta( $post->ID, YayMailTemplate::META_KEYS['elements'], true );
                    $language                 = get_post_meta( $post->ID, YayMailTemplate::META_KEYS['language'], true );
                    $text_link_color          = get_post_meta( $post->ID, YayMailTemplate::META_KEYS['text_link_color'], true );
                    $background_color         = get_post_meta( $post->ID, YayMailTemplate::META_KEYS['background_color'], true );
                    $content_background_color = get_post_meta( $post->ID, YayMailTemplate::META_KEYS['content_background_color'], true );
                    $content_text_color       = get_post_meta( $post->ID, YayMailTemplate::META_KEYS['content_text_color'], true );
                    $file_name                = "{$template_name}.json";
                    if ( empty( $language ) ) {
                        $export_data[] = [
                            'file_name'      => $file_name,
                            'templates_data' => [
                                'template'                 => $template_name,
                                'elements'                 => $elements,
                                'language'                 => '',
                                'text_link_color'          => $text_link_color,
                                'background_color'         => $background_color,
                                'content_background_color' => $content_background_color,
                                'content_text_color'       => $content_text_color,
                                'title_color'              => $title_color,
                            ],
                        ];
                    }
                }//end foreach
            }//end if
            wp_reset_postdata();
            wp_send_json_success(
                [
                    'message'   => __( 'Export successfully', 'yaymail' ),
                    'data'      => $export_data,
                    'file_name' => 'yaymail_customizer_templates_' . gmdate( 'm-d-Y' ),
                ]
            );
        } catch ( \Error $error ) {
            yaymail_get_logger( $error );
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
        }//end try
    }

    public function import_templates() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yaymail_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'yaymail' ) ] );
        }
        try {
            if ( ! empty( $_FILES ) ) {
                $result = $this->process_import( $_FILES );
                wp_send_json_success(
                    [
                        'imported_data' => $result,
                    ]
                );
            } else {
                wp_send_json_error( [ 'message' => __( 'Can\'t find import files.', 'yaymail' ) ] );
            }
        } catch ( \Error $error ) {
            yaymail_get_logger( $error );
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
        }
    }

    public function process_import( $files ) {
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        $imported_templates = [];
        $is_legacy          = false;
        foreach ( $files as $file ) {
            if ( isset( $file['type'] ) ) {
                if ( 'application/json' === $file['type'] ) {
                    if ( ! empty( $file['tmp_name'] ) ) {
                        $file_tmp_name = sanitize_text_field( $file['tmp_name'] );
                        $file_content  = $wp_filesystem->get_contents( $file_tmp_name );
                        $file_content  = json_decode( $file_content, true );
                        if ( ! isset( $file_content['template'] ) ) {
                            if ( isset( $file_content['yaymailTemplateExport'] ) ) {
                                $is_legacy          = true;
                                $imported_templates = array_merge( $imported_templates, $this->process_import_legacy( $file_content ) );
                            } else {
                                continue;
                            }
                        } else {
                            $update_result = $this->processing_import_update_data( $file_content );
                            if ( ! empty( $update_result ) ) {
                                $imported_templates[] = $update_result;
                            }
                        }
                    }//end if
                }//end if
            }//end if
        }//end foreach
        if ( $is_legacy ) {
            MainMigration::get_instance()->migrate( true );
        }
        return $imported_templates;
    }

    public function process_import_legacy( $file_content ) {
        $updated_templates = [];
        // Import templates
        foreach ( $file_content['yaymailTemplateExport'] as $template ) {
            $updated_result = $this->processing_import_update_data( $template, true );
            if ( ! empty( $updated_result ) ) {
                $updated_templates[] = $updated_result;
            }
        }//end foreach
        // Import settings
        $import_settings = isset( $file_content['yaymail_settings'] ) ? $file_content['yaymail_settings'] : [];
        if ( ! empty( $import_settings ) ) {
            update_option( 'yaymail_settings', $import_settings );
        }
        return $updated_templates;
    }

    public function processing_import_update_data( $data, $is_legacy = false ) {
        $template_name    = $data['template'] ?? null;
        $elements         = $data['elements'] ?? null;
        $text_link_color  = $data['text_link_color'] ?? null;
        $background_color = $data['background_color'] ?? null;
        $title_color      = $data['title_color'] ?? null;

        if ( empty( $template_name ) ) {
            return null;
        }

        $template = new YayMailTemplate( $template_name );
        if ( ! $template->is_exists() ) {
            return null;
        }

        $template->set_elements( $elements );
        $template->set_text_link_color( $text_link_color );
        $template->set_background_color( $background_color );
        $template->set_title_color( $title_color );
        $template->set_content_background_color( $content_background_color );
        $template->set_content_text_color( $content_text_color );
        if ( $is_legacy ) {
            $template->set_status( 'inactive' );
        }

        $template->save();

        wp_reset_postdata();

        return [
            'template_name' => $template_name,
        ];
    }

    /**
     * Process a custom hook request and generate HTML content.
     *
     * This function handles a custom hook request, generates HTML content based on the provided data and attributes.
     * It is designed to be used as an AJAX callback.
     *
     * @example $_POST['data'] =
     * [
     *     'template_data' => YayMail\YayMailTemplate,
     *     'order_id' => 'sample_order',
     *     'attributes' => [
     *         [
     *             'name' => 'hook',
     *             'value' => 'your_hook'
     *         ],
     *         [
     *             'name' => 'background_color',
     *             'value' => '#ffffff'
     *         ]
     *     ]
     * ]
     *
     * @return void This function sends a JSON response with HTML content or error messages.
     */
    public function get_custom_hook_html() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yaymail_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'yaymail' ) ] );
        }
        try {
            $attributes = isset( $_POST['data']['attributes'] ) ? $_POST['data']['attributes'] : []; // phpcs:ignore
            if ( empty( $attributes ) ) {
                return wp_send_json_error( [ 'mess' => __( 'Attributes empty', 'yaymail' ) ] );
            }

            /**
             * Build data for shortcode
             */
            $template_model = \YayMail\Models\TemplateModel::get_instance();
            $data           = [];
            if ( ! empty( $_POST['data']['template_data'] ) ) {
                $data = \YayMail\Models\TemplateModel::get_shortcode_executor_data( sanitize_text_field( wp_unslash( $_POST['data']['template_data'] ) ), sanitize_text_field( wp_unslash( $_POST['data']['order_id'] ) ) );

                $data['template']->set_props( sanitize_text_field( wp_unslash( $_POST['data']['template_data'] ) ) );
            }

            $hook_shortcodes = \YayMail\Shortcodes\HookShortcodes::get_instance();
            $html            = $hook_shortcodes->yaymail_handle_custom_hook_shortcode( $data, $attributes );
            wp_send_json_success(
                [
                    'html' => $html,
                ]
            );
        } catch ( \Error $error ) {
            yaymail_get_logger( $error );
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
        }//end try
    }

    /**
     * Get all needed data when load YayMail template to customizer.
     */
    public function get_template_data_onload() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yaymail_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'yaymail' ) ] );
        }
        try {
            $setting_model = SettingModel::get_instance();
            $settings_data = $setting_model->find_all();

            $template_name = isset( $_POST['data']['template_name'] ) ? sanitize_text_field( $_POST['data']['template_name'] ) : 'new_order';
            $order_id      = isset( $_POST['data']['order_id'] ) ? sanitize_text_field( $_POST['data']['order_id'] ) : 'sample_order';

            $template_model = TemplateModel::get_instance();

            $shortcodes_data = $template_model->get_shortcodes_by_template_name_and_order_id( $template_name, $order_id );

            $templates_data = apply_filters( 'yaymail_get_all_templates', $template_model->find_all() );

            $selected_template_data = $template_model->find_by_name( $template_name );

            $elements_data = TemplateModel::get_elements_for_template( $template_name );

            $revision_model = RevisionModel::get_instance();
            $revisions_data = $revision_model->get_by_template( $template_name );

            wp_send_json_success(
                [
                    'settings_data'          => $settings_data,
                    'templates_data'         => $templates_data,
                    'selected_template_data' => $selected_template_data,
                    'elements_data'          => $elements_data,
                    'revisions_data'         => $revisions_data,
                    'shortcodes_data'        => $shortcodes_data,
                ]
            );
        } catch ( \Error $error ) {
            yaymail_get_logger( $error );
            wp_send_json_error( [ 'mess' => $error->getMessage() ] );
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
            wp_send_json_error( [ 'mess' => $exception->getMessage() ] );
        } catch ( \Throwable $throwable ) {
            yaymail_get_logger( $throwable );
            wp_send_json_error( [ 'mess' => $throwable->getMessage() ] );
        }//end try
    }

    public function yaymail_review() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yaymail_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'yaymail' ) ] );
        }
        try {

            $yaymail_review = update_option( 'yaymail_review', true );

            wp_send_json_success(
                [
                    'reviewed' => $yaymail_review,
                ]
            );

        } catch ( \Error $error ) {
            yaymail_get_logger( $error );
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
        }
    }

    public function change_ghf_tour() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yaymail_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'yaymail' ) ] );
        }

        try {
            $next_move = isset( $_POST['next_move'] ) ? sanitize_text_field( wp_unslash( $_POST['next_move'] ) ) : 'initial';
            $ghf_tour  = update_option( 'yaymail_ghf_tour', $next_move );

            wp_send_json_success(
                [
                    'ghf_tour' => $ghf_tour,
                ]
            );
        } catch ( \Error $error ) {
            yaymail_get_logger( $error );
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
        }
    }

    public function dismiss_multi_select_notice() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yaymail_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'yaymail' ) ] );
        }

        try {
            update_option( 'yaymail_show_multi_select_notice', 'no' );

            wp_send_json_success(
                [
                    'show_multi_select_notice' => 'no',
                ]
            );
        } catch ( \Error $error ) {
            yaymail_get_logger( $error );
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
        }
    }

    public function dismiss_new_element_notification() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yaymail_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'yaymail' ) ] );
        }

        $elements = isset( $_POST['elements'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['elements'] ) ) : [];

        $viewed_new_elements = get_option( 'yaymail_viewed_new_elements', [] );
        $viewed_new_elements = array_unique( array_merge( $viewed_new_elements, $elements ) );

        try {
            update_option( 'yaymail_viewed_new_elements', $viewed_new_elements );

            wp_send_json_success(
                [
                    'viewed_new_elements' => $viewed_new_elements,
                ]
            );
        } catch ( \Error $error ) {
            wp_send_json_error( [ 'mess' => $error->getMessage() ] );
        } catch ( \Exception $exception ) {
            yaymail_get_logger( $exception );
        }
    }
}
