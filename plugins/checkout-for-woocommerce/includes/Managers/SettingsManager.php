<?php
namespace Objectiv\Plugins\Checkout\Managers;

use Objectiv\Plugins\Checkout\Interfaces\SettingsGetterInterface;

/**
 * Provides standard object for accessing user-defined plugin settings
 *
 * @link checkoutwc.com
 * @since 1.0.0
 * @package Objectiv\Plugins\Checkout\Managers
 */
class SettingsManager extends SettingsManagerAbstract implements SettingsGetterInterface {

	public $prefix = '_cfw_';

	/**
	 * Add suffix
	 *
	 * @param string $setting_name The name of the setting.
	 * @param array  $keys The keys to add to the setting name.
	 * @return string
	 */
	public function add_suffix( string $setting_name, array $keys = array() ): string {
		if ( empty( $keys ) ) {
			return $setting_name;
		}

		asort( $keys );

		return $setting_name . '_' . join( '', $keys );
	}

	/**
	 * Add setting
	 *
	 * @param string $setting The name of the new option.
	 * @param mixed  $value The value of the new option.
	 * @param array  $keys The keys to add to the setting name.
	 * @return bool
	 */
	public function add_setting( string $setting, $value, array $keys = array() ): bool {
		return parent::add_setting( $this->add_suffix( $setting, $keys ), $value );
	}

	/**
	 * Update setting
	 *
	 * @param string       $setting The name of the option.
	 * @param array|string $value The new value of the option.
	 * @param array        $keys The keys to add to the setting name.
	 *
	 * @return bool
	 */
	public function update_setting( string $setting, $value, array $keys = array() ): bool {
		return parent::update_setting( $this->add_suffix( $setting, $keys ), $value );
	}

	/**
	 * Delete setting
	 *
	 * @param string $setting The name of the option.
	 * @param array  $keys The keys to add to the setting name.
	 * @return bool
	 */
	public function delete_setting( string $setting, array $keys = array() ): bool {
		return parent::delete_setting( $this->add_suffix( $setting, $keys ) );
	}

	/**
	 * Get setting
	 *
	 * @param string $setting The name of the option.
	 * @param array  $keys The keys to add to the setting name.
	 * @return false|mixed
	 */
	public function get_setting( string $setting, array $keys = array() ) {
		return parent::get_setting( $this->add_suffix( $setting, $keys ) );
	}

	/**
	 * Get field name
	 *
	 * @param string $setting The name of the setting.
	 * @param array  $keys The keys to add to the setting name.
	 * @return string
	 */
	public function get_field_name( string $setting, array $keys = array() ): string {
		return parent::get_field_name( $this->add_suffix( $setting, $keys ) );
	}
}
