<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Features\Turnstile;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Managers\UpdatesManager;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class Integrations extends PageAbstract {
	public function __construct() {
		parent::__construct( __( 'Integrations', 'checkout-wc' ), 'cfw_manage_integrations', 'integrations' );
	}

	public function init() {
		$integrations = cfw_apply_filters( 'cfw_admin_integrations_checkbox_fields', array() );

		if ( ! defined( 'CFW_PREMIUM_PLAN_IDS' ) && count( $integrations ) === 0 ) {
			return;
		}

		parent::init();
	}

	public function output() {
		?>
		<div id="cfw-admin-pages-integrations"></div>
		<?php
	}

	public function maybe_set_script_data() {
		if ( ! $this->is_current_page() ) {
			return;
		}

		$this->set_script_data(
			array(
				'settings'     => array(
					'google_places_api_key' => SettingsManager::instance()->get_setting( 'google_places_api_key' ),
					
					// Turnstile settings
					'turnstile_enabled'                    => SettingsManager::instance()->get_setting( 'turnstile_enabled' ) === 'yes',
					'turnstile_site_key'                   => SettingsManager::instance()->get_setting( 'turnstile_site_key' ),
					'turnstile_secret_key'                 => SettingsManager::instance()->get_setting( 'turnstile_secret_key' ),
					'turnstile_checkout_enabled'           => SettingsManager::instance()->get_setting( 'turnstile_checkout_enabled' ) === 'yes',
					'turnstile_order_pay_enabled'          => SettingsManager::instance()->get_setting( 'turnstile_order_pay_enabled' ) === 'yes',
					'turnstile_login_enabled'              => SettingsManager::instance()->get_setting( 'turnstile_login_enabled' ) === 'yes',
					'turnstile_register_enabled'           => SettingsManager::instance()->get_setting( 'turnstile_register_enabled' ) === 'yes',
					'turnstile_position'                   => SettingsManager::instance()->get_setting( 'turnstile_position' ),
					'turnstile_theme'                      => SettingsManager::instance()->get_setting( 'turnstile_theme' ),
					'turnstile_size'                       => SettingsManager::instance()->get_setting( 'turnstile_size' ),
					'turnstile_guest_only'                 => SettingsManager::instance()->get_setting( 'turnstile_guest_only' ) === 'yes',
					'turnstile_has_conflict'               => Turnstile::has_conflict(),
					'turnstile_conflict_notice'            => Turnstile::get_conflict_notice(),
				),
				/**
				 * Filters third party checkboxes here:  WP Admin > CheckoutWC > Advanced > Integrations
				 *
				 * Use to add additional integration settings
				 *
				 * @param array $integrations The integrations admin page class
				 * @since 9.0.0
				 */
				'integrations' => apply_filters( 'cfw_admin_integrations_checkbox_fields', array() ),
				'plan'         => $this->get_plan_data(),
			)
		);
	}
}
