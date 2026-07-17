<?php
namespace YayMail\Controllers;

use YayMail\Abstracts\BaseController;
use YayMail\Migrations\AbstractMigration;
use YayMail\Models\MigrationModel;
use YayMail\Utils\SingletonTrait;
/**
 * Migration Controller
 *
 * @method static MigrationController get_instance()
 */
class MigrationController extends BaseController {
    use SingletonTrait;

    /** @var MigrationModel */
    private $model;

    private function __construct() {
        $this->model = MigrationModel::get_instance();
        $this->init_hooks();
    }

    private function init_hooks() {
        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/migrations/get-onload-data',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_onload_data' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );
        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/migrations/migrate',
            [
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'exec_migrate' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );
        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/migrations/reset/(?P<backup_name>[a-zA-Z0-9_-]+)',
            [
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'exec_reset' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [
                        'backup_name' => [
                            'type'     => 'string',
                            'required' => true,
                        ],
                    ],
                ],
            ]
        );
    }

    public function exec_get_onload_data( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_onload_data' ], $request );
    }
    public function get_onload_data( \WP_REST_Request $request ) {
        return $this->model->get_onload_data( $request );
    }

    public function exec_migrate( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'migrate' ], $request );
    }
    public function migrate( \WP_REST_Request $request ) {
        $response = $this->model->migrate();
        return array_merge( [ 'success' => true ], $response );
    }

    public function exec_reset( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'reset' ], $request );
    }
    public function reset( \WP_REST_Request $request ) {
        $backup_name = sanitize_text_field( $request->get_param( 'backup_name' ) );
        $backup_data = get_option( $backup_name, null );

        if ( ! $backup_data ) {
            return [
                'success' => false,
                'message' => esc_html__( 'Backup not found', 'yaymail' ),
            ];
        }

        $version                = str_replace( AbstractMigration::BACKUP_PREFIX, '', $backup_name );
        $version                = str_replace( '_', '.', $version );
        $backup_data['name']    = $backup_name;
        $backup_data['version'] = $version;
        $response               = $this->model->reset( $backup_data );
        return array_merge( [ 'success' => true ], $response );
    }
}
