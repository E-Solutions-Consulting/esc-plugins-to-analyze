<?php

namespace Objectiv\Plugins\Checkout\API;

use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use WP_Error;
use WP_REST_Server;

class SettingsAPI {
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	public function register_route() {
		register_rest_route(
			'checkoutwc/v1',
			'setting/(?P<setting_key>[\S]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_setting' ),
				'permission_callback' => array( $this, 'can_access_settings_api' ),
			)
		);

		register_rest_route(
			'checkoutwc/v1',
			'settings',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_multiple_settings' ),
				'permission_callback' => array( $this, 'can_access_settings_api' ),
			)
		);

		register_rest_route(
			'checkoutwc/v1',
			'settings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_multiple_settings' ),
				'permission_callback' => array( $this, 'can_access_settings_api' ),
			)
		);

		register_rest_route(
			'checkoutwc/v1',
			'setting/(?P<setting_key>[\S]+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_setting' ),
				'permission_callback' => array( $this, 'can_access_settings_api' ),
			)
		);
	}

	public function get_setting( \WP_REST_Request $request ) {
		$key = $request->get_param( 'setting_key' );

		if ( isset( $_GET['keys'] ) && is_array( $_GET['keys'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
			$keys = $this->recursive_sanitize_text_field( wp_unslash( $_GET['keys'] ) );
		} else {
			$keys = array();
		}

		if ( stripos( $key, 'wp_option/' ) === 0 ) {
			return rest_ensure_response(
				array(
					'key'   => str_replace( 'wp_option/', '', $key ),
					'value' => get_option( $key ),
				)
			);
		}

		return rest_ensure_response(
			array(
				'key'   => $key,
				'value' => SettingsManager::instance()->get_setting( $key, $keys ),
			)
		);
	}

	public function get_multiple_settings( \WP_REST_Request $request ) {
		$manager       = SettingsManager::instance();
		$body          = json_decode( $request->get_body() );
		$response_data = array();

		// Check if the 'settings' key exists and is an array
		if ( ! isset( $body->settings ) ) {
			$response_data['error'] = 'No settings requested';
			$response               = new WP_Error( '400', 'No settings requested', $response_data );

			return rest_ensure_response( $response );
		}

		foreach ( $body->settings as $setting ) {
			if ( ! isset( $setting->name ) ) {
				// Skip if the name is not set
				$response_data[] = array( 'error' => 'Missing name for a setting.' );
				continue;
			}

			$name = $setting->name;
			$keys = isset( $setting->keys ) ? $this->recursive_sanitize_text_field( $setting->keys ) : array();

			if ( stripos( $name, 'wp_option/' ) === 0 ) {
				$option_name = str_replace( 'wp_option/', '', $name );
				$value       = get_option( $option_name );
			} else {
				$value = $manager->get_setting( $name, $keys );
			}

			// Check if the setting exists
			if ( is_null( $value ) ) {
				$response_data[] = array(
					'name'  => $name,
					'error' => "Setting $name does not exist.",
				);
			} else {
				// Add the retrieved setting to the response
				$response_data[] = array(
					'name'  => $name,
					'value' => $value,
				);
			}
		}

		return rest_ensure_response( $response_data );
	}

	public function update_setting( \WP_REST_Request $request ) {
		$manager       = SettingsManager::instance();
		$key           = $request->get_param( 'setting_key' );
		$body          = json_decode( $request->get_body() );
		$response_data = array();

		if ( ! isset( $body->value ) ) {
			$response_data['error'] = 'No value provided';
			$response               = new WP_Error( '400', 'No value provided', $response_data );

			return rest_ensure_response( $response );
		}

		if ( isset( $_GET['keys'] ) && is_array( $_GET['keys'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$keys = $this->recursive_sanitize_text_field( wc_clean( wp_unslash( $_GET['keys'] ) ) );
		} else {
			$keys = array();
		}

		$value = $this->convert_nested_array_objects_to_arrays( $body->value );

		if ( stripos( $key, 'wp_option/' ) === 0 ) {
			$option_name = str_replace( 'wp_option/', '', $key );
			update_option( $option_name, $value );
		} else {
			$manager->update_setting( $key, $value, $keys );
		}

		$newValue                 = $manager->get_setting( $key, $keys );
		$success                  = $this->areEqual( $value, $newValue );
		$response_data['success'] = $success;

		if ( $success ) {
			return rest_ensure_response( $response_data );
		}

		$response_data['error'] = "Unable to update setting_key: $key to value: $value";
		$response               = new WP_Error( '400', 'Unable to update setting. If this error persists contact your site administrator.', $response_data );

		return rest_ensure_response( $response );
	}

	public function update_multiple_settings( \WP_REST_Request $request ) {
		$manager       = SettingsManager::instance();
		$body          = json_decode( $request->get_body() );
		$response_data = array();

		// Check if the 'settings' key exists and is an array
		if ( ! isset( $body->settings ) ) {
			$response_data['error'] = 'No value provided';
			$response               = new WP_Error( '400', 'No value provided', $response_data );

			return rest_ensure_response( $response );
		}

		foreach ( $body->settings as $setting ) {
			if ( ! isset( $setting->name ) || ! isset( $setting->value ) ) {
				// Skip if key or value is not set
				$response_data[] = array( 'error' => 'Missing key or value for a setting.' );
				continue;
			}

			$name  = $setting->name;
			$value = $this->convert_nested_array_objects_to_arrays( $setting->value );
			$keys  = isset( $setting->keys ) ? $this->recursive_sanitize_text_field( $setting->keys ) : array();

			if ( stripos( $name, 'wp_option/' ) === 0 ) {
				$option_name = str_replace( 'wp_option/', '', $name );
				update_option( $option_name, $value );
				$newValue = get_option( $option_name );
			} else {
				// Update the setting
				$manager->update_setting( $name, $value, $keys );
				$newValue = $manager->get_setting( $name, $keys );
			}

			$success = $this->areEqual( $value, $newValue );

			// Add the update status of each setting to the response
			$response_data[] = array(
				'name'      => $name,
				'new_value' => $newValue,
				'success'   => $success,
				'error'     => $success ? null : "Unable to update setting_key: $name to value: $value",
			);
		}

		return rest_ensure_response( $response_data );
	}


	public function areEqual( $a, $b ): bool {
		if ( gettype( $a ) !== gettype( $b ) ) {
			return false;
		}

		if ( is_scalar( $a ) || is_null( $a ) ) {
			return ( $a === $b );
		}

		if ( is_array( $a ) ) {
			if ( count( $a ) !== count( $b ) ) {
				return false;
			}

			foreach ( $a as $key => $value ) {
				if ( ! array_key_exists( $key, $b ) ) {
					return false;
				}

				if ( ! $this->areEqual( $value, $b[ $key ] ) ) {
					return false;
				}
			}

			return true;
		}

		// For objects, we need to compare their properties
		$a_properties = get_object_vars( $a );
		$b_properties = get_object_vars( $b );

		if ( count( $a_properties ) !== count( $b_properties ) ) {
			return false;
		}

		foreach ( $a_properties as $property => $value ) {
			if ( ! array_key_exists( $property, $b_properties ) ) {
				return false;
			}

			if ( ! $this->areEqual( $value, $b_properties[ $property ] ) ) {
				return false;
			}
		}

		return true;
	}


	public function can_access_settings_api(): bool {
		return current_user_can( 'cfw_manage_options' );
	}

	public function recursive_sanitize_text_field( $field ) {
		foreach ( $field as $key => &$value ) {
			if ( is_array( $value ) ) {
				$value = $this->recursive_sanitize_text_field( $value );
			} else {
				$value = sanitize_text_field( $value );
			}
		}

		return $field;
	}

	public function convert_nested_array_objects_to_arrays( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		// Convert array of stdClasses to array of arrays, recursively
		return array_map(
			function ( $nestedValue ) {
				if ( is_object( $nestedValue ) ) {
					// Convert object to array
					$nestedValue = (array) $nestedValue;
				}

				// Recursively process nested arrays or objects
				return $this->convert_nested_array_objects_to_arrays( $nestedValue );
			},
			$value
		);
	}
}
