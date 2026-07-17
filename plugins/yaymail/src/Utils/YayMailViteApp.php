<?php

namespace YayMail\Utils;

use YayMail\Utils\SingletonTrait;

/**
 * YayMail Vite App enqueue logic
 */
class YayMailViteApp {
    use SingletonTrait;

    private $entries              = [];
    private $manifest             = [];
    private $preload_module_files = [];
    private $style_files          = [];

    protected function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 99 );
        add_action( 'admin_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 99 );
    }

    private function get_plugin_url() {
        if ( Helpers::is_yaymail_lite_and_yay_wp_pro_active() ) {
            return YAYMAIL_WP_PLUGIN_URL;
        }
        return YAYMAIL_PLUGIN_URL;
    }

    public function enqueue_entry( string $key = 'yaymail-main.tsx', array $deps = [] ) {
        if ( isset( $this->entries[ $key ] ) ) {
            return;
        }
        $this->entries[ $key ] = [
            'key'  => $key,
            'deps' => $deps,
        ];
    }

    public function wp_enqueue_scripts() {
        if ( empty( $this->entries ) ) {
            return;
        }

        if ( ! YAYMAIL_IS_DEVELOPMENT ) {
            $this->load_manifest();
            foreach ( $this->entries as $entry_opts ) {
                $this->load_entry( $entry_opts['key'] );
            }
        }

        $this->register_entry_modules();

        add_action( 'admin_head', [ $this, 'register_preload_modules' ] );
        add_action( 'wp_head', [ $this, 'register_preload_modules' ] );

        $this->register_styles();
    }

    private function load_entry( $entry_key ) {
        $entry_options = $this->get_module_opts( $entry_key );
        if ( null === $entry_options ) {
            return;
        }

        if ( isset( $entry_options['css'] ) ) {
            $this->load_styles( $entry_options['css'] );
        }

        if ( isset( $entry_options['imports'] ) ) {
            foreach ( $entry_options['imports'] as $import_key ) {
                $import_module_opt = $this->get_module_opts( $import_key );

                if ( ! in_array( $import_module_opt['file'], $this->preload_module_files, true ) ) {
                    $this->preload_module_files[] = $import_module_opt['file'];
                }

                if ( isset( $import_module_opt['css'] ) ) {
                    $this->load_styles( $import_module_opt['css'] );
                }
            }
        }
    }

    private function register_entry_modules() {
        add_filter(
            'script_loader_tag',
            function ( $tag, $handle, $src ) {
                if ( strpos( $handle, 'module/yaymail/' ) !== false ) {
                    $str  = "type='module'";
                    $str .= YAYMAIL_IS_DEVELOPMENT ? ' crossorigin' : '';
                    $tag  = '<script ' . $str . ' src="' . $src . '" id="' . $handle . '-js"></script>';
                }
                return $tag;
            },
            10,
            3
        );

        foreach ( $this->entries as $entry_key => $entry_opts ) {
            if ( YAYMAIL_IS_DEVELOPMENT ) {
                wp_register_script( "module/yaymail/$entry_key", "http://localhost:3000/$entry_key", $entry_opts['deps'], null, true );// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion

            } else {
                $url = $this->get_plugin_url() . 'assets/dist/yaymail/' . $this->get_module_opts( $entry_key )['file'];
                wp_register_script( "module/yaymail/$entry_key", $url, $entry_opts['deps'], yaymail_version(), true );
            }

            wp_enqueue_script( "module/yaymail/$entry_key" );
        }

        do_action( 'yaymail_after_enqueue_scripts', $this->entries );
    }

    public function register_preload_modules() {
        if ( YAYMAIL_IS_DEVELOPMENT ) {
            echo '<script type="module">
            import RefreshRuntime from "http://localhost:3000/@react-refresh"
            RefreshRuntime.injectIntoGlobalHook(window)
            window.$RefreshReg$ = () => {}
            window.$RefreshSig$ = () => (type) => type
            window.__vite_plugin_react_preamble_installed__ = true
            </script>';

        } else {
            foreach ( $this->preload_module_files as $file ) {
                echo ( '<link rel="modulepreload" href="' . esc_attr( $this->get_plugin_url() . 'assets/dist/yaymail/' . $file ) . '">' );
            }
        }
    }

    private function register_styles() {
        if ( YAYMAIL_IS_DEVELOPMENT ) {
            return;
        }

        foreach ( $this->style_files as $file ) {
            wp_register_style( 'yay/' . $file, $this->get_plugin_url() . 'assets/dist/yaymail/' . $file, [], yaymail_version() );
            wp_enqueue_style( 'yay/' . $file );
        }
    }

    private function load_manifest() {
        global $wp_filesystem;
        require_once ABSPATH . '/wp-admin/includes/file.php';
        WP_Filesystem();

        if ( Helpers::is_yaymail_lite_and_yay_wp_pro_active() ) {
            $plugin_path = YAYMAIL_WP_PLUGIN_PATH;
        } else {
            $plugin_path = YAYMAIL_PLUGIN_PATH;
        }

        $content = $wp_filesystem->get_contents( $plugin_path . 'assets/dist/yaymail/manifest.json' );

        // when $wp_filesystem is not available, use file_get_contents
        if ( ! $content ) {
            $content = file_get_contents( $plugin_path . 'assets/dist/yaymail/manifest.json' );
        }

        $this->manifest = json_decode( $content, true );
    }

    private function get_module_opts( $key ) {
        return $this->manifest[ $key ] ?? null;
    }

    private function load_styles( array $style_files ) {
        if ( empty( $style_files ) ) {
            return;
        }
        foreach ( $style_files as $file ) {
            if ( ! in_array( $file, $this->style_files, true ) ) {
                $this->style_files[] = $file;
            }
        }
    }
}
