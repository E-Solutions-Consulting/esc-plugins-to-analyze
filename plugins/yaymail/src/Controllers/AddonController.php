<?php

namespace YayMail\Controllers;

use YayMail\Abstracts\BaseController;
use YayMail\Models\AddonModel;
use YayMail\Models\SettingModel;
use YayMail\Utils\Helpers;
use YayMail\Utils\SingletonTrait;

/**
 * Settings Controller
 * * @method static AddonController get_instance()
 */
class AddonController extends BaseController {
    use SingletonTrait;

    private $model = null;

    protected function __construct() {
        $this->model = SettingModel::get_instance();
        $this->init_hooks();
    }

    protected function permission_callback_admin_only() {
        return current_user_can( 'activate_plugins' );
    }

    protected function init_hooks() {
        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/addons',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_addons' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );
        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/addons/activate',
            [
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'exec_activate_addon' ],
                    'permission_callback' => [ $this, 'permission_callback_admin_only' ],
                ],
            ]
        );
        register_rest_route(
            YAYMAIL_REST_NAMESPACE,
            '/addons/deactivate',
            [
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'exec_deactivate_addon' ],
                    'permission_callback' => [ $this, 'permission_callback_admin_only' ],
                ],
            ]
        );
    }

    public function exec_get_addons( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_addons' ], $request );
    }

    public function exec_activate_addon( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'activate_addon' ], $request );
    }

    public function exec_deactivate_addon( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'deactivate_addon' ], $request );
    }

    public function get_addons( \WP_REST_Request $request ) {
        $data     = AddonModel::get_all();
        $platform = $request->get_param( 'platform' );
        $data     = $this->filter_addons_by_platform( $data, $platform );
        $data     = $this->reorder_addons_for_catalog( $data );

        return array_values( $data );
    }

    /**
     * Keep Addons REST list aligned with active YayMail Woo vs Yay WP Pro (same idea as addons_platform in localize).
     *
     * @param array<string, array<string, mixed>> $data Addon rows keyed by namespace.
     * @return array<string, array<string, mixed>>
     */
    private function filter_addons_by_platform( array $data, string $platform ): array {
        $is_yaymail = ( $platform === 'yaymail' ) ? true : false;

        return array_filter(
            $data,
            function ( $addon ) use ( $is_yaymail ) {
                $categories = isset( $addon['categories'] ) && is_array( $addon['categories'] ) ? $addon['categories'] : [];
                if ( $is_yaymail ) {
                    return ! in_array( 'wordpress', $categories, true ); //phpcs:ignore
                } else {
                    return in_array( 'wordpress', $categories, true ); //phpcs:ignore
                }
            }
        );
    }

    /**
     * Pin core Woo addons first; append all others in original order (WordPress-catalog addons follow).
     *
     * @param array<string, array<string, mixed>> $data Addon rows keyed by namespace.
     * @return array<string, array<string, mixed>>
     */
    private function reorder_addons_for_catalog( array $data ): array {
        $pinned_keys = [
            'YayMailAddonConditionalLogic',
            'YayMailAddonWcSubscription',
            'YayMailAddonYITHWishlist',
        ];

        $pinned_set = array_flip( $pinned_keys );

        $head      = [];
        $wordpress = [];
        $others    = [];

        foreach ( $pinned_keys as $key ) {
            if ( isset( $data[ $key ] ) ) {
                $head[ $key ] = $data[ $key ];
            }
        }

        foreach ( $data as $key => $addon ) {
            if ( isset( $pinned_set[ $key ] ) ) {
                continue;
            }

            $categories   = isset( $addon['categories'] ) && is_array( $addon['categories'] ) ? $addon['categories'] : [];
            $is_wordpress = in_array( 'WordPress', $categories, true );

            if ( $is_wordpress ) {
                $wordpress[ $key ] = $addon;
            } else {
                $others[ $key ] = $addon;
            }
        }

        return $head + $wordpress + $others;
    }

    public function activate_addon( \WP_REST_Request $request ) {
        $addon = $request->get_param( 'addon' );

        if ( ! $addon ) {
            return [
                'success' => false,
                'message' => 'Addon not found',
            ];
        }

        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

        $plugin_status = \install_plugin_install_status(
            [
                'slug'    => $addon,
                'version' => '',
            ]
        );

        if ( $plugin_status['status'] === false || empty( $plugin_status['file'] ) ) {
            return [
                'success' => false,
                'message' => 'Addon not installed',
            ];
        }

        $result = activate_plugin( $plugin_status['file'] );

        if ( is_wp_error( $result ) ) {
            return [
                'success' => false,
                'message' => $result->get_error_message(),
                'addon'   => $addon,
            ];
        }
        return [
            'success' => true,
            'message' => 'Addon activated',
        ];
    }

    public function deactivate_addon( \WP_REST_Request $request ) {
        $addon = $request->get_param( 'addon' );

        if ( ! $addon ) {
            return [
                'success' => false,
                'message' => 'Addon not found',
            ];
        }

        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

        $plugin_status = \install_plugin_install_status(
            [
                'slug'    => $addon,
                'version' => '',
            ]
        );

        if ( $plugin_status['status'] === false || empty( $plugin_status['file'] ) ) {
            return [
                'success' => false,
                'message' => 'Addon not installed',
            ];
        }

        deactivate_plugins( $plugin_status['file'] );

        return [
            'success' => true,
            'message' => 'Addon deactivated',
        ];
    }
}
