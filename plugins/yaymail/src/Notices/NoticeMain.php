<?php

namespace YayMail\Notices;

use YayMail\Utils\SingletonTrait;
use YayMail\Notices\Ajax;
use YayMail\SupportedPlugins;
use YayMail\Utils\Logger;

/**
 *
 * @method static NoticeMain get_instance()
 */
class NoticeMain {
    use SingletonTrait;

    private $logger;

    private $supported_plugins;

    protected function __construct() {
        $this->logger = new Logger();

        Ajax::get_instance();

        $this->supported_plugins = SupportedPlugins::get_instance();

        $this->init_hooks();
    }

    protected function init_hooks() {
        if ( ! function_exists( 'YayMail\init' ) ) {
            return;
        }
        // Show recommendation notice
        if ( time() >= (int) get_option( 'yaymail_next_recommendation_suggest_addons_notice_time' ) ) {
            add_action( 'admin_notices', [ $this, 'render_suggest_addons_notice' ] );
        }

        if ( time() >= (int) get_option( 'yaymail_next_recommendation_upgrade_notice_time' ) ) {
            add_action( 'admin_notices', [ $this, 'render_upgrade_notice' ] );
        }

        if ( is_admin() ) {
            if ( time() >= (int) get_option( 'yaymail_next_recommendation_notice_time' ) || time() >= (int) get_option( 'yaymail_next_recommendation_suggest_addons_notice_time' ) || time() >= (int) get_option( 'yaymail_next_recommendation_upgrade_notice_time' ) ) {
                wp_enqueue_script( 'yaymail-notice', YAYMAIL_PLUGIN_URL . 'assets/scripts/notice.js', [ 'jquery' ], yaymail_version(), false );
            }

            wp_localize_script(
                'yaymail-notice',
                'yaymail_notice',
                [
                    'admin_ajax' => admin_url( 'admin-ajax.php' ),
                    'nonce'      => wp_create_nonce( 'yaymail_nonce' ),
                ]
            );
        }

        add_action(
            'after_plugin_row_' . YAYMAIL_PLUGIN_BASENAME,
            [ $this, 'display_under_plugin_notices' ],
            10,
            2
        );
    }



    public function display_under_plugin_notices( $plugin_file ) {

        $plugins = get_plugins();

        $yaymail_addons = array_filter(
            $plugins,
            function ( $key ) use ( $plugins ) {
                if ( ! is_string( $key ) ) {
                    return false;
                }
                $plugin_data           = $plugins[ $key ];
                $is_yaycommerce_author = isset( $plugin_data['Author'] ) && strpos( $plugin_data['Author'], 'YayCommerce' ) !== false;

                $is_yay_wp             = strpos( $key, 'yay-wp-email' ) !== false;
                return $is_yaycommerce_author && (
                    strpos( $key, 'yaymail-addon' ) !== false ||
                    strpos( $key, 'email-customizer' ) !== false ||
                    strpos( $key, 'yaymail-premium-addon' ) !== false ||
                    strpos( $key, 'yaymail-conditional-logic' ) !== false
                ) && ! $is_yay_wp;
            },
            ARRAY_FILTER_USE_KEY
        );

        $addon_info = [];
        foreach ( $yaymail_addons as $plugin_path => $plugin_data ) {
            if ( is_plugin_active( $plugin_path ) && version_compare( $plugin_data['Version'], '4.0', '<' ) ) {
                $addon_info[] = [
                    'name'    => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                ];
            }
        }

        if ( empty( $addon_info ) ) {
            return;
        }

        $wp_list_table = _get_list_table( 'WP_MS_Themes_List_Table' );

        add_action( 'admin_footer', [ $this, 'enqueue_admin_script' ] );

        echo wp_kses_post(
            '<tr class="plugin-update-tr' . ( is_plugin_active( $plugin_file ) ? ' active' : '' ) . '">
                <td colspan="' . esc_attr( $wp_list_table->get_column_count() ) . '" class="plugin-update colspanchange">'
                . $this->get_addon_update_notice()
                . '</td>
            </tr>'
        );
    }

    /**
     * Enqueues a script to modify the plugin row styling in the admin footer.
     */
    public function enqueue_admin_script() {
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var pluginRow = document.querySelector('tr[data-plugin="<?php echo esc_js( YAYMAIL_PLUGIN_BASENAME ); ?>"]');
                if (pluginRow) pluginRow.classList.add('update');
            });
        </script>
        <?php
    }

    /**
     * Returns the notice to update to new core version.
     */
    protected function get_addon_update_notice() {
        return sprintf(
            '<div class="notice inline notice-error notice-alt"><p>%s</p></div>',
            esc_html__( 'Please update the YayMail Addons to version 4.0 or later to ensure compatibility and optimal performance.', 'yaymail' ),
        );
    }

    public function render_suggest_addons_notice() {
        /* List out the 3rd-party that need our addon*/
        $addon_needed_plugins = [];
        foreach ( $this->supported_plugins->get_addon_supported_plugins() as $addon_namespace => $addon ) {
            if ( ! empty( $addon['is_3rd_party_installed'] ) && empty( $addon['installation_status']['is_active'] ) ) {
                $addon_needed_plugins[ $addon_namespace ] = $addon;
            }
        }

        if ( ! empty( $addon_needed_plugins ) ) {
            include YAYMAIL_PLUGIN_PATH . 'templates/notices/suggest-addons.php';
        }
    }

    public function render_upgrade_notice() {

        $pro_needed_plugins = $this->supported_plugins->get_pro_supported_plugins();

        if ( ! empty( $pro_needed_plugins ) ) {
            include YAYMAIL_PLUGIN_PATH . 'templates/notices/upgrade.php';
        }
    }
}
