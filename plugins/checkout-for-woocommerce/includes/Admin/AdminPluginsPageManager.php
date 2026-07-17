<?php

namespace Objectiv\Plugins\Checkout\Admin;

use Objectiv\Plugins\Checkout\Managers\UpdatesManager;

/**
 * Handles Pro-specific plugin page functionality (license nag, settings link, license fields for survey).
 *
 * Note: The deactivation survey itself is now handled by DeactivationSurvey class
 * which is initialized in init.php for both Pro and Lite versions.
 *
 * @since 11.0.1 Survey functionality moved to DeactivationSurvey class
 */
class AdminPluginsPageManager {
	protected $cfw_admin_url;

	public function __construct( string $cfw_admin_url ) {
		$this->cfw_admin_url = $cfw_admin_url;
	}

	public function init() {
		add_action( 'after_plugin_row_' . CFW_PATH_BASE, array( $this, 'add_key_nag' ), 10, 2 );
		add_filter(
			'plugin_action_links_' . plugin_basename( CFW_MAIN_FILE ),
			array(
				$this,
				'add_action_link',
			),
			10,
			1
		);

		// Add license-specific fields to the deactivation survey
		add_filter( 'cfw_deactivation_form_fields', array( $this, 'add_license_fields_to_survey' ) );
	}

	public function add_action_link( $links ): array {
		$settings_link = array(
			'<a href="' . $this->cfw_admin_url . '">' . __( 'Settings', 'checkout-wc' ) . '</a>',
		);

		return array_merge( $settings_link, $links );
	}

	public function add_key_nag() {
		$key_status = UpdatesManager::instance()->get_field_value( 'key_status' );

		if ( empty( $key_status ) ) {
			return;
		}

		if ( 'valid' === $key_status ) {
			return;
		}

		$current = get_site_transient( 'update_plugins' );
		if ( isset( $current->response[ plugin_basename( __FILE__ ) ] ) ) {
			return;
		}

		if ( is_network_admin() || ! is_multisite() ) {
			$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
			echo '<tr class="plugin-update-tr"><td colspan="' . sanitize_key( $wp_list_table->get_column_count() ) . '" class="plugin-update colspanchange"><div class="update-message">';
			echo '<span style="color:red">' . esc_html__( "You're missing out on important updates because your license key is missing, invalid, or expired.", 'checkout-wc' ) . '</span>';
			echo '</div></td></tr>';
		}
	}

	/**
	 * Add license-specific fields to the deactivation survey.
	 *
	 * These fields are only added for Pro version where UpdatesManager is available.
	 *
	 * @since 11.0.1
	 * @param array $fields The existing form fields.
	 * @return array
	 */
	public function add_license_fields_to_survey( array $fields ): array {
		$license_data = get_option( 'cfw_license_data', false );

		$fields[] = array(
			'id'          => 'price_id',
			'label'       => '',
			'type'        => 'hidden',
			'name'        => 'price_id',
			'value'       => UpdatesManager::instance()->get_license_price_id(),
			'required'    => '',
			'extra-class' => '',
		);

		$fields[] = array(
			'id'          => 'license_status',
			'label'       => '',
			'type'        => 'hidden',
			'name'        => 'license_status',
			'value'       => $license_data ? $license_data->license : '',
			'required'    => '',
			'extra-class' => '',
		);

		return $fields;
	}
}
