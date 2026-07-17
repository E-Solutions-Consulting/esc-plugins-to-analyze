<?php

namespace Objectiv\Plugins\Checkout\Managers;

use Objectiv\Plugins\Checkout\SingletonAbstract;

abstract class SettingsManagerAbstract extends SingletonAbstract {
	public $settings = array();
	public $prefix;
	public $delimiter;

	public function __construct() {}

	public function init() {
		add_action( 'admin_init', array( $this, 'save_settings' ), 0 );
	}

	/**
	 * Add a new setting
	 *
	 * @since 0.1.0
	 *
	 * @param string $setting The name of the new option.
	 * @param mixed  $value The value of the new option.
	 * @return boolean True if successful, false otherwise
	 **/
	public function add_setting( string $setting, $value ): bool {
		$setting = $this->maybe_add_prefix( $setting );

		return add_option( $setting, $value );
	}

	/**
	 * Updates or adds a setting
	 *
	 * @param string       $setting The name of the option.
	 * @param string|array $value The new value of the option.
	 *
	 * @return boolean True if successful, false if not
	 * @since 0.1.0
	 */
	public function update_setting( string $setting, $value ): bool {
		$setting = $this->maybe_add_prefix( $setting );

		$old_value = $this->get_setting( $setting );

		$result = update_option( $setting, $value );

		/**
		 * Fires when setting updates
		 *
		 * @since 10.1.7
		 *
		 * @param string $setting The setting key
		 * @param mixed $value The new value.
		 * @param mixed $old_value The old value.
		 */
		do_action_ref_array( 'cfw_updated_setting', array( $setting, $value, $old_value ) );

		/**
		 * Fires when setting updates
		 *
		 * @since 10.1.7
		 * @param mixed $value The new value.
		 * @param mixed $old_value The old value.
		 */
		do_action_ref_array( 'cfw_updated_setting_' . $setting, array( $value, $old_value ) );

		return $result;
	}

	/**
	 * Deletes a setting
	 *
	 * @since 0.1
	 *
	 * @param string $setting The name of the option.
	 * @return boolean True if successful, false if not
	 **/
	public function delete_setting( string $setting ): bool {
		$setting = $this->maybe_add_prefix( $setting );

		return delete_option( $setting );
	}

	/**
	 * Retrieves a setting value
	 *
	 * @param string $setting The name of the option.
	 * @return mixed The value of the setting
	 * @since 0.1.0
	 */
	public function get_setting( string $setting ) {
		$raw_setting_key = $setting;
		$setting         = $this->maybe_add_prefix( $setting );

		return cfw_apply_filters( 'cfw_get_setting_' . $raw_setting_key, get_option( $setting, false ) );
	}

	/**
	 * Generates HTML field name for a particular setting
	 *
	 * @param string $setting The name of the setting.
	 * @return string The name of the field
	 * @since 0.1.0
	 */
	public function get_field_name( string $setting ): string {
		return "{$this->prefix}_setting[$setting][string]";
	}

	/**
	 * Prints nonce for admin form
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 **/
	public function the_nonce() {
		wp_nonce_field( "save_{$this->prefix}_settings", "{$this->prefix}_save" );
	}

	/**
	 * Saves settings
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 **/
	public function save_settings() {
		if ( ! isset( $_REQUEST[ "{$this->prefix}_setting" ] ) ) {
			return;
		}

		if ( ! check_admin_referer( "save_{$this->prefix}_settings", "{$this->prefix}_save" ) ) {
			return;
		}

		// Only do this if button name is 'submit'
		// This allows for more flexibility with
		// having other buttons on a form that should
		// not actually save but should do other stuff
		if ( isset( $_REQUEST['submit'] ) ) {
			// We can't sanitize this because it could be anything, including code snippets
			$new_settings = wp_unslash( $_REQUEST[ "{$this->prefix}_setting" ] ); // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			if ( ! $new_settings ) {
				return;
			}

			// Strip Magic Slashes
			$new_settings = stripslashes_deep( $new_settings );

			foreach ( $new_settings as $setting_name => $setting_value ) {
				foreach ( $setting_value as $value ) {
					$this->update_setting( $setting_name, $value );
				}
			}
		}

		// Always run this action as long as we had a valid nonce
		// phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment
		do_action( "{$this->prefix}_settings_saved", $new_settings ?? array() );
		// phpcs:enable WooCommerce.Commenting.CommentHooks.MissingHookComment
	}

	private function maybe_add_prefix( string $setting ): string {
		if ( ! empty( $this->prefix ) && strpos( $setting, $this->prefix ) !== 0 ) {
			$setting = $this->prefix . $setting;
		}

		return $setting;
	}

	public function get_settings_obj( $legacy_obj = false ) {
		if ( ! $legacy_obj ) {
			_deprecated_function( __METHOD__, 'CheckoutWC 8.0.0', '' );
		}

		$values = array();

		$obj = get_option( "{$this->prefix}_settings", false );

		if ( $legacy_obj ) {
			return $obj;
		}

		if ( ! $obj ) {
			return $values;
		}

		// If not legacy, update the values from the current settings using the keys from the legacy settings
		foreach ( $obj as $key => $value ) {
			$values[ $key ] = get_option( $this->maybe_add_prefix( $key ), $value );
		}

		return $values;
	}

	/**
	 * Sets settings object
	 *
	 * @param array $newobj The new settings object.
	 * @return boolean True if successful, false otherwise
	 * @since 0.1.0
	 */
	public function set_settings_obj( array $newobj ): bool {
		_deprecated_function( __METHOD__, 'CheckoutWC 8.0.0', '' );
		return false;
	}
}
