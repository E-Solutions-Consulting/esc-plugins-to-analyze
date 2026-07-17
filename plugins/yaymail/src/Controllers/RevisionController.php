<?php

namespace YayMail\Controllers;

use YayMail\Abstracts\BaseController;
use YayMail\Models\RevisionModel;
use YayMail\Utils\SingletonTrait;
use YayMail\PostTypes\TemplatePostType;

/**
 * Revision Controller
 *
 * @method static RevisionController get_instance()
 */
class RevisionController extends BaseController {
    use SingletonTrait;

    private $model = null;

    public const YAYMAIL_TEMPLATE_REVISION_LIMIT = 50;

    protected function __construct() {
        $this->model = RevisionModel::get_instance();
        $this->init_hooks();
    }

    protected function init_hooks() {
        add_filter( 'wp_save_post_revision_post_has_changed', [ $this, 'set_post_has_change' ], 10, 3 );
        add_filter( 'wp_save_post_revision', [ $this, 'filter_revision_limit' ], 10, 2 );

        $revision_id_args = [
            'revision_id' => [
                'type'     => 'number',
                'required' => true,
            ],
        ];

        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/revisions/query-by-template-name',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_all_revisions' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );
        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/revisions/delete-by-template-name',
            [
                [
                    'methods'             => \WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'exec_delete_all_revisions' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/revision/(?P<revision_id>\d+)',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_revision_by_revision_id' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $revision_id_args,
                ],
            ]
        );
    }


    /**
     * Handle get all revisions
     */
    public function exec_get_all_revisions( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_all_revisions' ], $request );
    }
    public function get_all_revisions( \WP_REST_Request $request ) {
        $template_name = sanitize_text_field( $request->get_param( 'template_name' ) );

        $revisions = $this->model->get_by_template( $template_name );
        return $revisions;
    }

    /**
     * Handle delete all revisions
     */
    public function exec_delete_all_revisions( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'delete_all_revisions' ], $request );
    }
    public function delete_all_revisions( \WP_REST_Request $request ) {
        $template_name = sanitize_text_field( $request->get_param( 'template_name' ) );

        $this->model->delete_by_template( $template_name );
        return [ 'success' => true ];
    }

    /**
     * Handle get revision by revision id
     */
    public function exec_get_revision_by_revision_id( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_revision_by_revision_id' ], $request );
    }
    public function get_revision_by_revision_id( \WP_REST_Request $request ) {
        $id = sanitize_text_field( $request->get_param( 'revision_id' ) );

        return $this->model->get_by_id( $id );
    }

    /**
     * Hooks handler
     */
    public function set_post_has_change( $post_has_changed, $last_revision, $post ) {
        if ( TemplatePostType::POST_TYPE === $post->post_type ) {
            return true;
        }
        return $post_has_changed;
    }
    public function filter_revision_limit( $limit, $post ) {
        if ( TemplatePostType::POST_TYPE === $post->post_type ) {
            return self::YAYMAIL_TEMPLATE_REVISION_LIMIT;
        }

        return $limit;
    }
}
