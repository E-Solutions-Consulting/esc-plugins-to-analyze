<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Admin\TabNavigation;
use Objectiv\Plugins\Checkout\Admin\Pages\Traits\TabbedAdminPageTrait;
use Objectiv\Plugins\Checkout\Managers\NoticesManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use WP_Error;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class Advanced extends PageAbstract {
	use TabbedAdminPageTrait;

	public function __construct() {
		parent::__construct( __( 'Advanced', 'checkout-wc' ), 'cfw_manage_advanced', 'cfw_manage_advanced' );
	}

	public function init() {
		parent::init();

		$this->set_tabbed_navigation( new TabNavigation( 'advanced' ) );

		$this->get_tabbed_navigation()->add_tab( __( 'Advanced', 'checkout-wc' ), add_query_arg( array( 'subpage' => 'advanced' ), $this->get_url() ) );
		$this->get_tabbed_navigation()->add_tab( __( 'Scripts', 'checkout-wc' ), add_query_arg( array( 'subpage' => 'scripts' ), $this->get_url() ) );
		$this->get_tabbed_navigation()->add_tab( __( 'Tools', 'checkout-wc' ), add_query_arg( array( 'subpage' => 'tools' ), $this->get_url() ) );

		add_action( 'wp_ajax_cfw_generate_settings', array( $this, 'generate_settings_export' ) );
		add_action( 'admin_init', array( $this, 'maybe_upload_settings' ), 0 );
	}

	public function output() {
		$current_tab_function = $this->get_tabbed_navigation()->get_current_tab() . '_tab';
		$callable             = array( $this, $current_tab_function );

		$this->get_tabbed_navigation()->display_tabs();

		call_user_func( $callable );
	}

	public function scripts_tab() {
		?>
		<div id="cfw-admin-pages-advanced-scripts"></div>
		<?php
	}

	public function advanced_tab() {
		?>
		<div id="cfw-admin-pages-advanced-options"></div>
		<?php
	}

	public function tools_tab() {
		?>
		<form name="settings" action="<?php echo esc_attr( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) ); ?>" method="post"
				enctype="multipart/form-data">
			<div class="space-y-6 mt-4">
				<?php
				cfw_admin_page_section(
					__( 'Export Settings', 'checkout-wc' ),
					__( 'Download a JSON file containing the current plugin settings.', 'checkout-wc' ),
					$this->get_export_settings()
				);
				cfw_admin_page_section(
					__( 'Import Settings', 'checkout-wc' ),
					__( 'Replace your current settings with a previous settings export.', 'checkout-wc' ),
					$this->get_import_settings()
				);
				?>
			</div>
		</form>
		<?php
	}

	public function get_export_settings() {
		ob_start();
		?>
		<input id="export_settings_button" type="button" class="button"
				data-nonce="<?php echo esc_attr( wp_create_nonce( '_cfw__export_settings' ) ); ?>"
				value="<?php _e( 'Export Settings', 'checkout-wc' ); ?>"/>

		<p id="small-description" class="text-gray-500">
			<?php _e( 'Download a backup file of your settings.', 'checkout-wc' ); ?>
		</p>
		<?php
		return ob_get_clean();
	}

	public function get_import_settings() {
		ob_start();
		?>
		<input name="uploaded_settings" type="file" class=""
				value="<?php _e( 'Import Settings', 'checkout-wc' ); ?>"/>
		<?php wp_nonce_field( 'import_cfw_settings_nonce' ); ?>
		<div>
			<input id="import_settings_button" type="submit" class="button" name="import_cfw_settings"
					value="<?php _e( 'Upload File and Import Settings', 'checkout-wc' ); ?>"/>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate Settings JSON file
	 *
	 * @return void
	 * @since  3.8.0
	 */
	public static function generate_settings_export() {
		global $wpdb;

		// Bail if not admin.
		if ( ! current_user_can( 'cfw_export_settings' ) ) {
			wp_die();
		}

		// Bail if nonce check fails.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), '_cfw__export_settings' ) ) {
			wp_die();
		}

		$settings = array();

		// Get all WP options that start with cfw_.
		$values = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE '\_cfw_%' AND option_name <> '_cfw__settings' AND option_name <> '_cfwlite__settings'" );

		foreach ( $values as $value ) {
			$settings[ $value->option_name ] = $value->option_value;
		}

		$templates = cfw_get_available_templates();

		foreach ( $templates as $template ) {
			$key           = '_cfw_logo_attachment_id_' . $template->get_slug();
			$attachment_id = $settings[ $key ] ?? '';

			if ( ! empty( $attachment_id ) ) {
				$settings[ '_cfw_logo_attachment_url_' . $template->get_slug() ] = wp_get_attachment_url( $attachment_id );
			}
		}

		if ( ! empty( $settings ) ) {
			echo wp_json_encode( $settings );
			wp_die();
		}

		wp_die();
	}

	/**
	 * Upload Settings
	 *
	 * @return void
	 * @since  3.8.0
	 */
	public function maybe_upload_settings() {
		// Make sure we're an admin and that we have a valid request
		if ( ! current_user_can( 'cfw_import_settings' ) || empty( $_POST['import_cfw_settings'] ) ) {
			return;
		}

		if (
			! current_user_can( 'upload_files' ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ?? '' ) ), 'import_cfw_settings_nonce' ) ||
			( empty( $_FILES['uploaded_settings'] ) || 0 === $_FILES['uploaded_settings']['size'] ?? 0 )
		) {

			NoticesManager::instance()->add(
				'cfw_import_settings_error',
				__( 'CheckoutWC Settings Import Failed', 'checkout-wc' ),
				__( 'Unable to import settings. Did you select a JSON file to upload?', 'checkout-wc' ),
				array(
					'type'        => 'error',
					'dismissible' => false,
				)
			);
			return;
		}

		$upload = ! empty( $_FILES['uploaded_settings'] ) ? $_FILES['uploaded_settings'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( empty( $upload ) ) {
			wp_die( esc_html__( 'Error. Uploaded file appears empty.', 'checkout-wc' ) );
		}

		$file_tmp_path  = $upload['tmp_name'];
		$file_name      = $upload['name'];
		$file_name_cmps = explode( '.', $file_name );
		$file_extension = strtolower( end( $file_name_cmps ) );

		$new_file_name = md5( time() . $file_name ) . '.' . $file_extension;

		if ( 'json' !== $file_extension ) {
			wp_die( esc_html__( 'Wrong file extension. Uploaded settings must be a JSON file.', 'checkout-wc' ) );
		}

		$wp_uploads = wp_upload_dir();
		$upload_dir = trailingslashit( $wp_uploads['basedir'] );
		$dest_path  = $upload_dir . $new_file_name;

		if ( ! move_uploaded_file( $file_tmp_path, $dest_path ) ) {
			wp_die( esc_html__( 'Error moving uploaded file - check your permissions.', 'checkout-wc' ) );
		}

		$contents = file_get_contents( $dest_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$decoded  = json_decode( $contents, JSON_OBJECT_AS_ARRAY );

		if ( ! $decoded ) {
			wp_die( esc_html__( 'Error decoding JSON file.', 'checkout-wc' ) );
		}

		$active_template_slug = cfw_get_active_template()->get_slug();

		// Pre 9.x logo handler
		if ( ! empty( $decoded['_cfw_logo_attachment_id'] && false !== $decoded['_cfw_logo_attachment_url'] ) && ! isset( $decoded[ '_cfw_logo_attachment_id_' . $active_template_slug ] ) ) {
			$image_upload = $this->upload_logo( $decoded['_cfw_logo_attachment_url'] );

			if ( $image_upload ) {
				$decoded[ '_cfw_logo_attachment_id_' . $active_template_slug ] = $image_upload ? $image_upload : '';
			}
		} else {
			cfw_debug_log( 'Failed to upload pre 9.x logo.' );
		}

		// Handle logos
		$templates = cfw_get_available_templates();

		foreach ( $templates as $template ) {
			$key = '_cfw_logo_attachment_url_' . $template->get_slug();
			$url = $decoded[ $key ] ?? '';

			if ( ! empty( $url ) ) {
				$image_upload_attachment_id = $this->upload_logo( $url );

				if ( $image_upload_attachment_id ) {
					$decoded[ '_cfw_logo_attachment_id_' . $template->get_slug() ] = $image_upload_attachment_id ? $image_upload_attachment_id : '';
				}

				unset( $decoded[ $key ] );
			}
		}

		foreach ( $decoded as $key => $value ) {
			update_option( $key, maybe_unserialize( $value ) );
		}

		wp_delete_file( $dest_path );

		NoticesManager::instance()->add(
			'cfw_import_settings_success',
			__( 'CheckoutWC Settings Import Successful', 'checkout-wc' ),
			__( 'Successfully imported settings.', 'checkout-wc' ),
			array(
				'type'        => 'success',
				'dismissible' => false,
			)
		);
	}

	/**
	 * Upload Logo
	 *
	 * @param string $file_url The file URL.
	 *
	 * @return int|WP_Error|bool
	 * @since  3.8.0
	 */
	public function upload_logo( $file_url ) {
		$filename = basename( $file_url );

		add_filter( 'https_ssl_verify', '__return_false' );
		$logo = wp_remote_get( $file_url );

		if ( is_wp_error( $logo ) ) {
			wc_get_logger()->error( 'Error fetching logo during settings import: ' . $logo->get_error_message(), array( 'source' => 'checkout-wc' ) );
			return false;
		}

		$upload_file = wp_upload_bits( $filename, null, wp_remote_retrieve_body( $logo ) );

		if ( ! $upload_file['error'] ) {
			$wp_file_type = wp_check_filetype( $filename, null );

			$attachment = array(
				'post_mime_type' => $wp_file_type['type'],
				'post_parent'    => 0,
				'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			);

			$attachment_id = wp_insert_attachment( $attachment, $upload_file['file'], 0 );

			if ( ! is_wp_error( $attachment_id ) ) {
				$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] );
				wp_update_attachment_metadata( $attachment_id, $attachment_data );
			}

			return $attachment_id;
		}

		return '';
	}

	public function maybe_set_script_data() {
		if ( ! $this->is_current_page() ) {
			return;
		}

		if ( 'advanced' === $this->get_tabbed_navigation()->get_current_tab() ) {
			$this->set_script_data(
				array(
					'settings' => array(
						'template_loader'             => SettingsManager::instance()->get_setting( 'template_loader' ),
						'enable_beta_version_updates' => SettingsManager::instance()->get_setting( 'enable_beta_version_updates' ) === 'yes',
						'hide_admin_bar_button'       => SettingsManager::instance()->get_setting( 'hide_admin_bar_button' ) === 'yes',
						'enable_debug_log'            => SettingsManager::instance()->get_setting( 'enable_debug_log' ) === 'yes',
						'allow_tracking'              => array( SettingsManager::instance()->get_setting( 'allow_tracking' ) ),
						'allow_uninstall'             => SettingsManager::instance()->get_setting( 'allow_uninstall' ) === 'yes',
					),
					'params'   => array(
						'allow_tracking_hash' => md5( trailingslashit( home_url() ) ),
					),
					'plan'     => $this->get_plan_data(),
				)
			);
		}

		if ( 'scripts' === $this->get_tabbed_navigation()->get_current_tab() ) {
			$this->set_script_data(
				array(
					'settings' => array(
						'header_scripts'           => cfw_get_setting( 'header_scripts', null, '' ),
						'footer_scripts'           => cfw_get_setting( 'footer_scripts', null, '' ),
						'php_snippets'             => cfw_get_setting( 'php_snippets', null, '' ),
						'header_scripts_checkout'  => cfw_get_setting( 'header_scripts_checkout', null, '' ),
						'footer_scripts_checkout'  => cfw_get_setting( 'footer_scripts_checkout', null, '' ),
						'header_scripts_thank_you' => cfw_get_setting( 'header_scripts_thank_you', null, '' ),
						'footer_scripts_thank_you' => cfw_get_setting( 'footer_scripts_thank_you', null, '' ),
						'header_scripts_order_pay' => cfw_get_setting( 'header_scripts_order_pay', null, '' ),
						'footer_scripts_order_pay' => cfw_get_setting( 'footer_scripts_order_pay', null, '' ),
					),
					'plan'     => $this->get_plan_data(),
				)
			);
		}
	}
}
