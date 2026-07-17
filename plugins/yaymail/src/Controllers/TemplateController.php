<?php

namespace YayMail\Controllers;

use YayMail\Abstracts\BaseController;
use YayMail\Models\SettingModel;
use YayMail\Models\TemplateModel;
use YayMail\Utils\SingletonTrait;
use YayMail\YayMailTemplate;

/**
 * Template Controller
 *
 * @method static TemplateController get_instance()
 */
class TemplateController extends BaseController {
    use SingletonTrait;

    private $model = null;

    protected function __construct() {
        $this->model = TemplateModel::get_instance();
        $this->init_hooks();
    }

    protected function init_hooks() {
        $template_id_args = [
            'template_id' => [
                'type'     => 'string',
                'required' => true,
            ],
        ];
        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/templates',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_all_templates' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/templates/(?P<template_id>\d+)',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_template_by_id' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $template_id_args,
                ],
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'exec_update_template' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $template_id_args,
                ],
                [
                    'methods'             => \WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'exec_delete_template' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $template_id_args,
                ],
            ]
        );

        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/templates/get-template-by-name',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_template_by_name' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/templates/change-status',
            [
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'exec_change_status' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/templates/reset',
            [
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'exec_reset_templates' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/templates/copy-template',
            [
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'exec_copy_template' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/templates/(?P<template_name>[a-zA-Z0-9_-]+)/all-elements',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_all_elements_by_template' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [
                        'template_name' => [
                            'type'     => 'string',
                            'required' => true,
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/templates/(?P<template_name>[a-zA-Z0-9_-]+)/all-shortcodes/(?P<order>[a-zA-Z0-9_-]+)',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_all_shortcodes_by_template' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [
                        'template_name' => [
                            'type'     => 'string',
                            'required' => true,
                        ],
                        'order'         => [
                            'type'     => 'string',
                            'required' => true,
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/templates/global-header-footer/change-status',
            [
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'exec_change_global_header_footer_status' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );
    }

    public function exec_get_all_templates( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_all_templates' ], $request );
    }

    public function get_all_templates( \WP_REST_Request $request ) {
        $templates = $this->model->find_all();
        return apply_filters( 'yaymail_get_all_templates', $templates );
    }

    public function exec_get_template_by_id( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_template_by_id' ], $request );
    }

    public function get_template_by_id( \WP_REST_Request $request ) {
        $id            = sanitize_text_field( $request->get_param( 'template_id' ) );
        $template_data = $this->model::find_by_id( $id );
        return $template_data;
    }

    public function exec_update_template( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'update_template' ], $request );
    }

    public function update_template( \WP_REST_Request $request ) {
        $data     = json_decode( $request->get_param( 'data' ), true );
        $id       = sanitize_text_field( $data['template_id'] );
        $elements = $data['template_elements'];
        // TODO: later
        // $elements                   = Helpers::elements_remove_settings_empty( $request->get_param( 'template_elements' ) );
        $background_color         = sanitize_text_field( $data['background_color'] );
        $text_link_color          = sanitize_text_field( $data['text_link_color'] );
        $content_background_color = sanitize_text_field( $data['content_background_color'] );
        $content_text_color       = sanitize_text_field( $data['content_text_color'] );
        $title_color              = sanitize_text_field( $data['title_color'] );
        $preheader                = sanitize_text_field( $data['preheader'] );
        $global_header_settings   = $data['global_header_settings'] ?? YayMailTemplate::DEFAULT_DATA['global_header_settings'];
        $global_footer_settings   = $data['global_footer_settings'] ?? YayMailTemplate::DEFAULT_DATA['global_footer_settings'];
        $update_data              = [
            'elements'                 => $elements,
            'background_color'         => $background_color,
            'text_link_color'          => $text_link_color,
            'content_background_color' => $content_background_color,
            'content_text_color'       => $content_text_color,
            'title_color'              => $title_color,
            'global_header_settings'   => $global_header_settings,
            'global_footer_settings'   => $global_footer_settings,
            'preheader'                => $preheader,
        ];

        if ( isset( $data['email_subject'] ) ) {
            $template_name = get_post_meta( (int) $id, YayMailTemplate::META_KEYS['name'], true );
            $this->model::save_email_subject( $template_name, $data['email_subject'] );
        }

        return $this->model::update( $id, $update_data, true );
    }

    public function exec_delete_template( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'delete_template' ], $request );
    }

    public function delete_template( \WP_REST_Request $request ) {
        $id = sanitize_text_field( $request->get_param( 'template_id' ) );
        $this->model::delete( $id );
        return [ 'success' => true ];
    }

    public function exec_get_template_by_name( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_template_by_name' ], $request );
    }

    public function get_template_by_name( \WP_REST_Request $request ) {
        $template_name = sanitize_text_field( $request->get_param( 'template_name' ) );
        $template_data = $this->model::find_by_name( $template_name );

        if ( null === $template_data ) {
            $all_emails = yaymail_get_emails();
            if ( in_array(
                $template_name,
                array_map(
                    function ( $email ) {
                        return $email->get_id();
                    },
                    $all_emails
                )
            ) ) {

                $template_data = $this->model::insert(
                    [
                        'name'     => $template_name,
                        'elements' => yaymail_get_default_elements( $template_name ),
                    ]
                );
            }
        }//end if

        return $template_data;
    }

    public function exec_change_status( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'change_status' ], $request );
    }

    public function change_status( \WP_REST_Request $request ) {
        $list_id = is_array( $request->get_param( 'list_id' ) ) ? array_map( 'sanitize_text_field', wp_unslash( $request->get_param( 'list_id' ) ) ) : [];
        $status  = sanitize_text_field( $request->get_param( 'status' ) );
        foreach ( $list_id as $id ) {
            $this->model::update(
                $id,
                [
                    'status' => $status,
                ]
            );
        }
        return [ 'success' => true ];
    }

    public function exec_reset_templates( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'reset_templates' ], $request );
    }

    public function reset_templates( \WP_REST_Request $request ) {
        $list_id            = is_array( $request->get_param( 'list_id' ) ) ? array_map( 'sanitize_text_field', wp_unslash( $request->get_param( 'list_id' ) ) ) : [];
        $list_template_data = [];

        foreach ( $list_id as $id ) {
            $template_data                             = $this->model::find_by_id( $id );
            $default_elements                          = yaymail_get_default_elements( $template_data['name'] );
            $update_data                               = [
                'elements'                 => $default_elements,
                'background_color'         => YayMailTemplate::DEFAULT_DATA['background_color'],
                'text_link_color'          => YayMailTemplate::DEFAULT_DATA['text_link_color'],
                'content_background_color' => YayMailTemplate::DEFAULT_DATA['content_background_color'],
                'content_text_color'       => YayMailTemplate::DEFAULT_DATA['content_text_color'],
                'title_color'              => YayMailTemplate::DEFAULT_DATA['title_color'],
                'global_header_settings'   => wp_parse_args(
                    [
                        'hidden' => true,
                    ],
                    $template_data['global_header_settings'] ?? YayMailTemplate::DEFAULT_DATA['global_header_settings'],
                ),
                'global_footer_settings'   => wp_parse_args(
                    [
                        'hidden' => true,
                    ],
                    $template_data['global_footer_settings'] ?? YayMailTemplate::DEFAULT_DATA['global_footer_settings'],
                ),
            ];
            $template_data['elements']                 = $update_data['elements'];
            $template_data['background_color']         = $update_data['background_color'];
            $template_data['text_link_color']          = $update_data['text_link_color'];
            $template_data['content_background_color'] = $update_data['content_background_color'];
            $template_data['content_text_color']       = $update_data['content_text_color'];
            $template_data['title_color']              = $update_data['title_color'] ?? '#000000';
            $template_data['global_header_settings']   = $update_data['global_header_settings'];
            $template_data['global_footer_settings']   = $update_data['global_footer_settings'];

            $list_template_data[] = $template_data;
            $this->model::update( $id, $update_data, true );
        }//end foreach

        return [
            'success'            => true,
            'list_template_data' => $list_template_data,
        ];
    }


    public function exec_copy_template( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'copy_template' ], $request );
    }

    public function copy_template( \WP_REST_Request $request ) {
        $template_id        = sanitize_text_field( $request->get_param( 'template_id' ) );
        $from_template      = sanitize_text_field( $request->get_param( 'from_template' ) );
        $copy_template_data = $this->model::find_by_name( $from_template );

        if ( empty( $copy_template_data ) || empty( $copy_template_data['id'] ) ) {
            return [
                'success' => false,
                'message' => 'Template not found',
            ];
        }

        $update_data = [
            'elements'                 => ! empty( $copy_template_data['elements'] ) ? $copy_template_data['elements'] : yaymail_get_default_elements( $from_template ),
            'background_color'         => $copy_template_data['background_color'] ?? YAYMAIL_COLOR_BACKGROUND_DEFAULT,
            'content_background_color' => $copy_template_data['content_background_color'] ?? '#ffffff',
            'content_text_color'       => $copy_template_data['content_text_color'] ?? '#000000',
            'text_link_color'          => $copy_template_data['text_link_color'] ?? YAYMAIL_COLOR_WC_DEFAULT,
            'global_header_settings'   => ! empty( $copy_template_data['global_header_settings'] ) ? $copy_template_data['global_header_settings'] : YayMailTemplate::DEFAULT_DATA['global_header_settings'],
            'global_footer_settings'   => ! empty( $copy_template_data['global_footer_settings'] ) ? $copy_template_data['global_footer_settings'] : YayMailTemplate::DEFAULT_DATA['global_footer_settings'],
            'title_color'              => $copy_template_data['title_color'] ?? '#000000',
        ];

        $this->model::update( $template_id, $update_data, true );
        return [
            'success' => true,
        ];
    }

    public function exec_get_all_elements_by_template( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_all_elements' ], $request );
    }

    public function get_all_elements( \WP_REST_Request $request ) {
        $template_name = sanitize_text_field( $request->get_param( 'template_name' ) );
        $elements      = $this->model::get_elements_for_template( $template_name );
        return $elements;
    }

    public function exec_get_all_shortcodes_by_template( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_all_shortcodes' ], $request );
    }

    public function get_all_shortcodes( \WP_REST_Request $request ) {
        $template_name = sanitize_text_field( $request->get_param( 'template_name' ) );
        $order_id      = sanitize_text_field( $request->get_param( 'order' ) );
        return $this->model->get_shortcodes_by_template_name_and_order_id( $template_name, $order_id );
    }

    public function exec_change_global_header_footer_status( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'change_global_header_footer_status' ], $request );
    }

    public function change_global_header_footer_status( \WP_REST_Request $request ) {
        $status = sanitize_text_field( $request->get_param( 'status' ) );
        return SettingModel::update( [ 'global_header_footer_enabled' => $status ] );
    }
}