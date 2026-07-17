<?php

namespace YayMail\Controllers;

use YayMail\Abstracts\BaseController;
use YayMail\Models\LibraryTemplateModel;
use YayMail\TemplateLibrary\TemplateLibraryService;
use YayMail\Utils\SingletonTrait;

/**
 * Template Library Controller
 *
 * @method static TemplateLibraryController get_instance()
 */
class TemplateLibraryController extends BaseController {
    use SingletonTrait;

    protected function __construct() {
        $this->init_hooks();
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    protected function init_hooks() {
        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/template-library',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_templates' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [
                        'email_type' => [
                            'type'     => 'string',
                            'required' => true,
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/template-library/saved',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'exec_save_template' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->get_save_template_args(),
                ],
            ]
        );

        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/template-library/saved/(?P<public_id>[a-f0-9-]{36})',
            [
                [
                    'methods'             => \WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'exec_delete_saved_template' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [
                        'public_id' => [
                            'type'     => 'string',
                            'required' => true,
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * REST args for saving a template to the library.
     *
     * @return array
     */
    protected function get_save_template_args() {
        return [
            'scope'                  => [
                'type'     => 'string',
                'required' => true,
                'enum'     => [ 'current', 'all' ],
            ],
            'name'                   => [
                'type'     => 'string',
                'required' => true,
            ],
            'email_type'             => [
                'type'     => 'string',
                'required' => true,
            ],
            'elements'               => [
                'type'     => 'array',
                'required' => true,
            ],
            'email_settings'         => [
                'type'     => 'object',
                'required' => false,
            ],
            'global_header_settings' => [
                'type'     => 'object',
                'required' => false,
            ],
            'global_footer_settings' => [
                'type'     => 'object',
                'required' => false,
            ],
        ];
    }

    /**
     * Exec wrapper for getting list of templates.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function exec_get_templates( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_templates' ], $request );
    }

    /**
     * Get list of template summaries for current email type.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return array
     */
    public function get_templates( \WP_REST_Request $request ) {
        $email_type = sanitize_text_field( $request->get_param( 'email_type' ) );

        if ( empty( $email_type ) ) {
            return [
                'success' => false,
                'message' => __( 'Email type is required.', 'yaymail' ),
            ];
        }

        $templates = TemplateLibraryService::get_instance()->get_merged_list( $email_type );

        return [
            'success'   => true,
            'templates' => $templates,
        ];
    }

    /**
     * Exec wrapper for saving a template to the library.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function exec_save_template( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'save_template' ], $request );
    }

    /**
     * Save current customizer layout as a library template.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return array
     */
    public function save_template( \WP_REST_Request $request ) {
        $scope      = sanitize_text_field( $request->get_param( 'scope' ) );
        $name       = sanitize_text_field( $request->get_param( 'name' ) );
        $email_type = sanitize_text_field( $request->get_param( 'email_type' ) );

        if ( '' === $name ) {
            return [
                'success' => false,
                'message' => __( 'Template name is required.', 'yaymail' ),
            ];
        }

        if ( empty( $email_type ) ) {
            return [
                'success' => false,
                'message' => __( 'Email type is required.', 'yaymail' ),
            ];
        }

        if ( ! in_array( $scope, [ 'current', 'all' ], true ) ) {
            return [
                'success' => false,
                'message' => __( 'Invalid save scope.', 'yaymail' ),
            ];
        }

        $elements = $request->get_param( 'elements' );
        if ( ! is_array( $elements ) || empty( $elements ) ) {
            return [
                'success' => false,
                'message' => __( 'Template elements are required.', 'yaymail' ),
            ];
        }

        $base_payload = [
            'name'                   => $name,
            'elements'               => $elements,
            'email_settings'         => $request->get_param( 'email_settings' ),
            'global_header_settings' => $request->get_param( 'global_header_settings' ),
            'global_footer_settings' => $request->get_param( 'global_footer_settings' ),
        ];

        $email_types = ( 'all' === $scope )
            ? TemplateLibraryService::get_instance()->get_supported_email_types_for_library()
            : [ $email_type ];

        if ( empty( $email_types ) ) {
            return [
                'success' => false,
                'message' => __( 'No supported email types found for bulk save.', 'yaymail' ),
            ];
        }

        $base_row = LibraryTemplateModel::build_row_from_save_payload(
            array_merge( $base_payload, [ 'email_type' => $email_types[0] ] )
        );

        $rows = [];
        foreach ( $email_types as $type ) {
            $row               = $base_row;
            $row['email_type'] = sanitize_text_field( $type );
            $rows[]            = $row;
        }

        if ( 1 === count( $rows ) ) {
            $public_id  = LibraryTemplateModel::create( $rows[0] );
            $public_ids = false !== $public_id ? [ $public_id ] : [];
        } else {
            $public_ids = LibraryTemplateModel::create_many( $rows );
        }

        if ( empty( $public_ids ) ) {
            return [
                'success' => false,
                'message' => count( $email_types ) > 1
                    ? __( 'Failed to save templates.', 'yaymail' )
                    : __( 'Failed to save template.', 'yaymail' ),
            ];
        }

        return [
            'success'       => true,
            'message'       => __( 'Open templates to see your new one.', 'yaymail' ),
            'created_count' => count( $public_ids ),
            'public_ids'    => $public_ids,
            'effect_emails' => $email_types,
        ];
    }

    /**
     * Exec wrapper for deleting a saved library template.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function exec_delete_saved_template( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'delete_saved_template' ], $request );
    }

    /**
     * Hard-delete a saved library template by public_id.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return array
     */
    public function delete_saved_template( \WP_REST_Request $request ) {
        $public_id = sanitize_text_field( $request->get_param( 'public_id' ) );

        if ( '' === $public_id ) {
            return [
                'success' => false,
                'message' => __( 'Template id is required.', 'yaymail' ),
            ];
        }

        $deleted = LibraryTemplateModel::delete_by_public_id( $public_id );

        if ( ! $deleted ) {
            return [
                'success' => false,
                'message' => __( 'Template not found or could not be deleted.', 'yaymail' ),
            ];
        }

        return [
            'success' => true,
            'message' => __( 'Template deleted successfully.', 'yaymail' ),
        ];
    }
}