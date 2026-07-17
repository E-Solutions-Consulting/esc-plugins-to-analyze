<?php

namespace YayMail\Notices;

use YayMail\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * NoticeWPMail Class
 *
 * @method static NoticeWPMail get_instance()
 */
class NoticeWPMail {
    use SingletonTrait;

    protected function __construct() {
        add_action(
            'after_plugin_row_' . YAYMAIL_PLUGIN_BASENAME,
            [ $this, 'display_under_plugin_notices' ],
            100,
            2
        );

        add_action( 'admin_footer', [ $this, 'enqueue_admin_script' ] );

        // Handle Yay WP installation
        add_action( 'admin_action_yay_wp_install', [ $this, 'install_yay_wp' ] );

        // Show admin notices after installation
        add_action( 'admin_notices', [ $this, 'show_install_notices' ] );
    }

    private function is_yay_wp_installed() {
        return defined( 'YAYMAIL_WP_VERSION' );
    }

    /**
     * Displays the required notices below the plugin row if dependencies are missing.
     *
     * @param string $plugin_file
     */
    public function display_under_plugin_notices( $plugin_file ) {
        if ( $this->is_yay_wp_installed() ) {
            return;
            // No need to show notices if dependencies are met
        }

        $wp_list_table = _get_list_table( 'WP_MS_Themes_List_Table' );

        echo wp_kses_post(
            '<tr class="plugin-update-tr' . ( is_plugin_active( $plugin_file ) ? ' active' : '' ) . '">
                <td colspan="' . esc_attr( $wp_list_table->get_column_count() ) . '" class="plugin-update colspanchange">'
                . ( ! $this->is_yay_wp_installed() ? $this->get_yay_wp_required_notice() : '' )
                . '</td>
            </tr>'
        );
    }

    /**
     * Returns the notice for missing YayMail - WP Email Customizer plugin.
     */
    protected function get_yay_wp_required_notice() {
        $yay_wp_versions = [
            'yay-wp-email-customizer/yay-wp-email-customizer.php',
            'yay-wp-email-customizer-pro/yay-wp-email-customizer.php',
        ];

        $all_plugins        = get_plugins();
        $plugin_to_activate = null;

        foreach ( $yay_wp_versions as $plugin_file ) {
            if ( array_key_exists( $plugin_file, $all_plugins ) && ! is_plugin_active( $plugin_file ) ) {
                $plugin_to_activate = $plugin_file;
                break;
            }
        }

        if ( $plugin_to_activate ) {
            $activate_url = wp_nonce_url(
                admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $plugin_to_activate ) ),
                'activate-plugin_' . $plugin_to_activate
            );

            return sprintf(
                '<div class="notice inline notice-warning notice-alt"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__( 'To customize WordPress core emails, you need to activate Yay WP Email Customizer plugin.', 'yaymail' ),
                esc_url( $activate_url ),
                esc_html__( 'Activate Now', 'yaymail' )
            );
        }

        $install_url = wp_nonce_url(
            admin_url( 'admin.php?action=yay_wp_install' ),
            'yay-wp-install'
        );

        return sprintf(
            '<div class="notice inline notice-warning notice-alt"><p>%s <a href="%s">%s</a></p></div>',
            esc_html__( 'To customize WordPress core emails, you need to install and activate Yay WP Email Customizer plugin. Get', 'yaymail' ),
            esc_url( $install_url ),
            esc_html__( 'Yay WP Email Customizer', 'yaymail' ),
        );
    }

    /**
     * Handles the installation of YayMail - WP Email Customizer plugin.
     */
    public function install_yaymail_wp() {
        // Check user permissions
        if ( ! current_user_can( 'install_plugins' ) ) {
            wp_die( esc_html__( 'You do not have permission to install plugins.', 'yaymail' ) );
        }

        // Verify nonce
        check_admin_referer( 'yay-wp-install' );

        // Include required WordPress files
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        // Create upgrader instance
        $upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );

        // Install the plugin
        $download_url = 'https://downloads.wordpress.org/plugin/yay-wp-email-customizer.zip';
        $result       = $upgrader->install( $download_url );

        // Check if installation was successful
        if ( is_wp_error( $result ) ) {
            wp_redirect( admin_url( 'plugins.php?yay-wp-install-error=1' ) );
            exit;
        }

        // Try to activate the plugin
        $plugin_file     = 'yay-wp-email-customizer/yay-wp-email-customizer.php';
        $activate_result = activate_plugin( $plugin_file );

        if ( is_wp_error( $activate_result ) ) {
            wp_redirect( admin_url( 'plugins.php?yay-wp-installed=1&yay-wp-activate-error=1' ) );
            exit;
        }

        // Success - redirect back to plugins page
        wp_redirect( admin_url( 'plugins.php?yay-wp-installed=1&yay-wp-activated=1' ) );
        exit;
    }

    /**
     * Displays admin notices after plugin installation.
     */
    public function show_install_notices() {
        if ( isset( $_GET['yay-wp-install-error'] ) ) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php esc_html_e( 'Failed to install Yay WP Email Customizer plugin. Please try installing it manually from WordPress.org.', 'yaymail' ); ?></p>
            </div>
            <?php
        }

        if ( isset( $_GET['yay-wp-installed'] ) && isset( $_GET['yay-wp-activated'] ) ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'Yay WP Email Customizer plugin has been successfully installed and activated!', 'yaymail' ); ?></p>
            </div>
            <?php
        } elseif ( isset( $_GET['yay-wp-installed'] ) && isset( $_GET['yay-wp-activate-error'] ) ) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php esc_html_e( 'Yay WP Email Customizer plugin was installed but could not be activated automatically. Please activate it manually.', 'yaymail' ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Enqueues a script to modify the plugin row styling in the admin footer.
     */
    public function enqueue_admin_script() {
        // Check is Yay WP Email Customizer plugin installed
        if ( $this->is_yay_wp_installed() ) {
            return;
        }
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var pluginRow = document.querySelector('tr[data-plugin="<?php echo esc_js( YAYMAIL_PLUGIN_BASENAME ); ?>"]');
                if (pluginRow) pluginRow.classList.add('update');
            });
        </script>
        <?php
    }
}
