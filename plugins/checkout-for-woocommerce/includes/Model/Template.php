<?php

namespace Objectiv\Plugins\Checkout\Model;

use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * Template handler for associated template piece. Typically there should only be 3 of these in total (header, footer,
 * content)
 *
 * @link checkoutwc.com
 * @since 2.0.0
 * @package Objectiv\Plugins\Checkout\Core
 */
class Template {
	private $stylesheet_file_name = 'style.css';
	private $basepath             = '';
	private $baseuri              = '';
	private $name                 = '';
	private $description          = '';
	private $author               = '';
	private $authoruri            = '';
	private $version              = '';
	private $supports             = array();
	private $slug                 = '';

	/**
	 * @since 2.0.0
	 * @static
	 * @var array $default_headers
	 */
	public static $default_headers = array(
		'Name'        => 'Template Name',
		'Description' => 'Description',
		'Author'      => 'Author',
		'AuthorURI'   => 'Author URI',
		'Version'     => 'Version',
		'Supports'    => 'Supports',
	);

	/**
	 * Template constructor.
	 *
	 * @param string $slug The template slug.
	 */
	public function __construct( string $slug ) {
		/**
		 * Locate the template
		 *
		 * Search WordPress theme template folder first, then plugin
		 */
		if ( is_dir( trailingslashit( CFW_PATH_THEME_TEMPLATE ) . $slug ) ) {
			$this->basepath = trailingslashit( CFW_PATH_THEME_TEMPLATE ) . $slug;
			$this->baseuri  = trailingslashit( get_stylesheet_directory_uri() ) . 'checkout-wc/' . $slug;
		} elseif ( is_dir( trailingslashit( CFW_PATH_PLUGIN_TEMPLATE ) . $slug ) ) {
			$this->basepath = trailingslashit( CFW_PATH_PLUGIN_TEMPLATE ) . $slug;
			$this->baseuri  = trailingslashit( CFW_PATH_URL_BASE ) . 'templates/' . $slug;
		}

		$this->slug = $slug;

		$this->load();
	}

	/**
	 * Load template information for given path
	 */
	private function load() {
		/**
		 * Template Information
		 */
		$stylesheet_path = $this->get_stylesheet_path();

		if ( $stylesheet_path ) {
			$data = get_file_data( $stylesheet_path, self::$default_headers );

			$data['Name']     = ( '' === $data['Name'] ) ? ucfirst( basename( $this->get_basepath() ) ) : $data['Name'];
			$data['Supports'] = isset( $data['Supports'] ) ? explode( ', ', $data['Supports'] ) : array();

			foreach ( $data as $key => $value ) {
				$key          = str_replace( ' ', '_', $key );
				$key          = sanitize_key( $key );
				$this->{$key} = $value;
			}
		}
	}

	/**
	 * Load the theme template functions file
	 *
	 * @return void
	 * @since 2.0.0
	 */
	public function load_functions() {
		$functions_path = trailingslashit( $this->get_basepath() ) . 'functions.php';

		if ( file_exists( $functions_path ) ) {
			require_once $functions_path;
		}
	}

	/**
	 * Load the template init settings file
	 *
	 * @return void
	 * @since 2.0.0
	 */
	public function init() {
		$init_path    = trailingslashit( $this->get_basepath() ) . 'init.php';
		$default_path = trailingslashit( $this->get_basepath() ) . 'defaults.php';

		// Skip writing settings for templates that don't have their own defaults.php.
		// This prevents a partial plugin install (e.g. Lite) from writing standard fallback
		// defaults for Pro templates whose defaults.php doesn't exist yet, which would block
		// the correct values from being written later by add_option().
		if ( ! file_exists( $default_path ) ) {
			return;
		}

		$defaults = $this->get_default_settings();

		$settings_manager = SettingsManager::instance();

		foreach ( $defaults as $setting => $value ) {
			if ( defined( 'CFW_FORCE_TEMPLATE_RESET' ) ) {
				$settings_manager->update_setting( $setting, $value, array( $this->get_slug() ) );
			} else {
				$settings_manager->add_setting( $setting, $value, array( $this->get_slug() ) );
			}
		}

		if ( file_exists( $init_path ) ) {
			require_once $init_path;
		}
	}

	/**
	 * Get the default settings array
	 *
	 * @return array
	 */
	public function get_default_settings(): array {
		$default_path = trailingslashit( $this->get_basepath() ) . 'defaults.php';

		if ( file_exists( $default_path ) ) {
			require $default_path;

			return $defaults;
		}

		return $this->get_standard_default_settings();
	}

	/**
	 * @param string $setting The setting name.
	 *
	 * @return string
	 */
	public function get_default_setting( $setting ): string {
		$defaults = $this->get_default_settings();

		return ! empty( $defaults[ $setting ] ) ? $defaults[ $setting ] : '';
	}

	/**
	 * @return string[]
	 */
	public function get_standard_default_settings(): array {
		return array(
			'body_background_color'             => '#ffffff',
			'body_text_color'                   => '#333333',
			'header_background_color'           => '#ffffff',
			'footer_background_color'           => '#ffffff',
			'header_text_color'                 => '#2b2b2b',
			'footer_color'                      => '#999999',
			'link_color'                        => '#0073aa',
			'button_color'                      => '#333333',
			'button_text_color'                 => '#ffffff',
			'button_hover_color'                => '#555555',
			'button_text_hover_color'           => '#ffffff',
			'secondary_button_color'            => '#999999',
			'secondary_button_text_color'       => '#ffffff',
			'secondary_button_hover_color'      => '#666666',
			'secondary_button_text_hover_color' => '#ffffff',
			'summary_background_color'          => '#fafafa',
			'summary_mobile_background_color'   => '#fafafa',
			'summary_text_color'                => '#333333',
			'summary_link_color'                => '#0073aa',
			'cart_item_quantity_color'          => '#7f7f7f',
			'cart_item_quantity_text_color'     => '#ffffff',
			'breadcrumb_completed_text_color'   => '#7f7f7f',
			'breadcrumb_current_text_color'     => '#333333',
			'breadcrumb_next_text_color'        => '#7f7f7f',
			'breadcrumb_completed_accent_color' => '#333333',
			'breadcrumb_current_accent_color'   => '#333333',
			'breadcrumb_next_accent_color'      => '#333333',
		);
	}

	public function view( $filename, $parameters = array() ) {
		$filename_with_basepath = trailingslashit( $this->get_basepath() ) . $filename;
		$template_name          = $this->get_slug();
		$template_piece_name    = basename( $filename, '.php' );

		if ( file_exists( $filename_with_basepath ) ) {
			/**
			 * Fires before template is output
			 *
			 * @since 3.0.0
			 */
			do_action( "cfw_template_load_before_{$template_name}_{$template_piece_name}" );

			// Extract any parameters for use in the template
			extract( $parameters );

			// Pass the parameters to the view
			require $filename_with_basepath;

			/**
			 * Fires after template has been echoed out
			 *
			 * @since 3.0.0
			 */
			do_action( "cfw_template_load_after_{$template_name}_{$template_piece_name}" );
		}
	}

	/**
	 * @param string $capability The capability.
	 *
	 * @return bool
	 */
	public function supports( $capability ): bool {
		return in_array( $capability, $this->get_supports(), true );
	}

	/**
	 * @return string
	 */
	public function get_template_uri(): string {
		return $this->baseuri;
	}

	/**
	 * Return fully qualified path to stylesheet
	 *
	 * @return string|bool $stylesheet
	 */
	public function get_stylesheet_path() {
		$stylesheet = trailingslashit( $this->get_basepath() ) . $this->get_stylesheet_filename();

		return file_exists( $stylesheet ) ? $stylesheet : false;
	}

	/**
	 * @return string The template stylesheet filename.
	 */
	public function get_stylesheet_filename(): string {
		return $this->stylesheet_file_name;
	}

	/**
	 * @return string The template base path.
	 */
	public function get_basepath(): string {
		return $this->basepath;
	}

	/**
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * @param string $name The template name.
	 */
	public function set_name( $name ) {
		$this->name = $name;
	}

	/**
	 * @return string The template description.
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * @param string $description The template description.
	 */
	public function set_description( $description ) {
		$this->description = $description;
	}

	/**
	 * @return string
	 */
	public function get_author() {
		return $this->author;
	}

	/**
	 * @return string The template version.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * @param string $version The template version.
	 */
	public function set_version( $version ) {
		$this->version = $version;
	}

	/**
	 * @return array
	 */
	public function get_supports(): array {
		return (array) $this->supports;
	}

	/**
	 * @param array $supports The template supports.
	 */
	public function set_supports( $supports ) {
		$this->supports = $supports;
	}

	/**
	 * @return string The template slug.
	 */
	public function get_slug(): string {
		return $this->slug;
	}


	public function run_on_plugin_activation() {
		$this->init();
	}

	public static function get_all_available(): array {
		$templates = array();

		foreach ( glob( trailingslashit( cfw_get_plugin_template_path() ) . '*', GLOB_ONLYDIR ) as $template ) {
			$templates[ basename( $template ) ] = new self( basename( $template ) );
		}

		if ( is_dir( CFW_PATH_THEME_TEMPLATE ) ) {
			foreach ( glob( trailingslashit( CFW_PATH_THEME_TEMPLATE ) . '*', GLOB_ONLYDIR ) as $template ) {
				$templates[ basename( $template ) ] = new self( basename( $template ) );
			}
		}

		ksort( $templates );

		return $templates;
	}

	public static function init_active_template( self $template ) {
		$template->load_functions();

		if ( defined( 'CFW_BUILD_PROCESS' ) && CFW_BUILD_PROCESS === 2 ) {
			add_action(
				'cfw_load_template_assets',
				function () use ( $template ) {
					$asset_file_path = trailingslashit( $template->get_basepath() ) . 'build/index.asset.php';

					if ( is_readable( $asset_file_path ) ) {
						$asset_file = include $asset_file_path;
					} else {
						$asset_file = array(
							'version'      => '1.0.0',
							'dependencies' => array( 'jquery' ),
						);
					}

					$url = $template->get_template_uri() . '/build/style-index.css';

					if ( is_rtl() ) {
						$url = str_replace( '.css', '-rtl.css', $url );
					}

					// Register styles & scripts.
					wp_enqueue_style( 'cfw_front_template_css', $url, array(), $asset_file['version'] );
					wp_enqueue_script( 'wc-checkout', $template->get_template_uri() . '/build/index.js', $asset_file['dependencies'], $asset_file['version'], true );
				}
			);

			return;
		}

		/**
		 * Legacy template asset loader
		 */
		add_action(
			'cfw_load_template_assets',
			function () use ( $template ) {
				$min = ( ! CFW_DEV_MODE ) ? '.min' : '';

				wp_enqueue_style( 'cfw_front_template_css', $template->get_template_uri() . "/style{$min}.css", array(), CFW_VERSION );
				wp_enqueue_script( 'wc-checkout', $template->get_template_uri() . "/theme{$min}.js", array( 'jquery' ), CFW_VERSION, true );
			}
		);
	}
}
