<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Admin\Pages\Traits\TabbedAdminPageTrait;
use Objectiv\Plugins\Checkout\Admin\TabNavigation;
use Objectiv\Plugins\Checkout\Features\LocalPickup;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class LocalPickupAdminFree extends PageAbstract {
	use TabbedAdminPageTrait;

	public function __construct() {
		parent::__construct( __( 'Local Pickup', 'checkout-wc' ) . ' <span class="cfw-badge cfw-premium-badge"></span>', 'cfw_manage_local_pickup', 'local-pickup' );
	}

	public function init() {
		parent::init();

		$this->set_tabbed_navigation( new TabNavigation( 'settings' ) );

		$this->get_tabbed_navigation()->add_tab( __( 'Settings', 'checkout-wc' ), add_query_arg( array( 'subpage' => 'settings' ), $this->get_url() ) );
		$this->get_tabbed_navigation()->add_tab(
			__( 'Manage Pickup Locations', 'checkout-wc' ),
			add_query_arg( array( 'subpage' => 'placeholder' ), $this->get_url() )
		);
	}

	public function output() {
		$current_tab_function = $this->get_tabbed_navigation()->get_current_tab() . '_tab';
		$callable             = array( $this, $current_tab_function );

		$this->get_tabbed_navigation()->display_tabs();

		call_user_func( $callable );
	}

	public function settings_tab() {
		?>
		<form action="#" class="space-y-6 transition-all" style="filter: none;">
			<div>
				<div class="md:grid md:grid-cols-3 md:gap-6">
					<div class="md:col-span-1">
						<div class="px-4 sm:px-0">
							<h3 class="text-lg font-medium leading-6 text-gray-900"><?php esc_html_e( 'Local Pickup', 'checkout-wc' ); ?></h3>
							<p class="mt-1 text-sm text-gray-600"><?php esc_html_e( 'Control local pickup options.', 'checkout-wc' ); ?></p>
						</div>
					</div>
					<div class="mt-5 md:mt-0 md:col-span-2" id="local-pickup_content">
						<div></div>
						<div class="shadow sm:rounded-md">
							<div class="cfw-admin-section-component-content px-4 py-5 bg-white space-y-6 sm:p-6">
								<div class="flex items-center space-x-4">
									<button class="bg-lime-500 relative inline-flex h-7 w-14 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
											id="headlessui-switch-:r0:" role="switch" type="button" tabindex="0" aria-checked="true" data-headlessui-state="checked" aria-labelledby="headlessui-label-:r1:" aria-describedby="headlessui-description-:r2:"><span class="sr-only">Use setting</span><span aria-hidden="true" class="translate-x-[1.75rem] pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span></button>
									<span
										class="flex flex-grow flex-col"><span class="text-sm font-medium leading-6 text-gray-900" id="headlessui-label-:r1:"><?php esc_html_e( 'Enable Local Pickup', 'checkout-wc' ); ?></span><span class="text-sm text-gray-500" id="headlessui-description-:r2:"><?php esc_html_e( 'Provide customer with the option to choose their delivery method. Choosing pickup bypasses the shipping address.', 'checkout-wc' ); ?></span></span>
								</div>
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5">
										<input name="enable_pickup_ship_option" type="checkbox" id="cfw_checkbox_enable_pickup_ship_option" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="true"
												checked="">
									</div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_enable_pickup_ship_option" class="font-medium text-gray-700" style="vertical-align: unset;"><?php esc_html_e( 'Enable Shipping Option', 'checkout-wc' ); ?></label>
										<p class="text-gray-500"><?php esc_html_e( 'If you only offer pickup, uncheck this to hide the shipping option.', 'checkout-wc' ); ?></p>
									</div>
								</div>
								<div class="cfw-admin-field-container ">
									<label for="pickup_ship_option_label" class="block text-sm font-medium text-gray-700"><?php esc_html_e( 'Shipping Option Label', 'checkout-wc' ); ?></label>
									<input name="pickup_ship_option_label" type="text" id="pickup_ship_option_label" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 rounded-md"
											value="">
									<p class="mt-2 text-sm text-gray-500"><?php esc_html_e( 'If left blank, this default will be used: Ship', 'checkout-wc' ); ?></p>
								</div>
								<div class="cfw-admin-field-container ">
									<label for="pickup_option_label" class="block text-sm font-medium text-gray-700"><?php esc_html_e( 'Local Pickup Option Label', 'checkout-wc' ); ?></label>
									<input name="pickup_option_label" type="text" id="pickup_option_label" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 rounded-md" value="">
									<p class="mt-2 text-sm text-gray-500"><?php esc_html_e( 'If left blank, this default will be used: Pick up', 'checkout-wc' ); ?></p>
								</div>
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5">
										<input name="enable_pickup_method_step" type="checkbox" id="cfw_checkbox_enable_pickup_method_step" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="false">
									</div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_enable_pickup_method_step" class="font-medium text-gray-700" style="vertical-align: unset;"><?php esc_html_e( 'Enable Pickup Step', 'checkout-wc' ); ?></label>
										<p class="text-gray-500"><?php esc_html_e( 'When Pickup is selected, show the shipping method step. Can be useful when integrating with plugins that allow customers to choose a pickup time slot, etc.', 'checkout-wc' ); ?></p>
									</div>
								</div>
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5">
										<input name="hide_pickup_methods" type="checkbox" id="cfw_checkbox_hide_pickup_methods" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="false">
									</div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_hide_pickup_methods" class="font-medium text-gray-700" style="vertical-align: unset;"><?php esc_html_e( 'Hide Pickup Methods', 'checkout-wc' ); ?></label>
										<p class="text-gray-500"><?php esc_html_e( 'On the pickup step, hide the actual pickup methods. If you need the pickup step and only have one pickup method, you should use this option.', 'checkout-wc' ); ?></p>
									</div>
								</div>
								<div class="cfw-admin-field-container ">
									<input type="hidden" name="pickup_methods">
									<div></div>
									<legend class="text-base font-medium text-gray-900"><?php esc_html_e( 'Local Pickup Shipping Methods', 'checkout-wc' ); ?></legend>
									<p class="text-sm leading-5 text-gray-500"><?php esc_html_e( 'Choose which shipping methods are local pickup options. Only these options will be shown when Pickup is selected. These options will be hidden if Delivery is selected.', 'checkout-wc' ); ?></p>
									<div>
										<div class="flex items-start mt-3">
											<div class="h-5 flex items-center">
												<input name="pickup_methods" type="checkbox" id="pickup_methods_flat_rate:1" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="flat_rate:1">
											</div>
											<div class="ml-3 text-sm">
												<label for="pickup_methods_flat_rate:1" class="font-medium text-gray-700" style="vertical-align: unset;"><?php esc_html_e( 'Virginia: Ground Shipping', 'checkout-wc' ); ?></label>
											</div>
										</div>
										<div class="flex items-start mt-3">
											<div class="h-5 flex items-center">
												<input name="pickup_methods" type="checkbox" id="pickup_methods_flat_rate:4" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="flat_rate:4">
											</div>
											<div class="ml-3 text-sm">
												<label for="pickup_methods_flat_rate:4" class="font-medium text-gray-700" style="vertical-align: unset;"><?php esc_html_e( 'Virginia: Overnight Shipping', 'checkout-wc' ); ?></label>
											</div>
										</div>
										<div class="flex items-start mt-3">
											<div class="h-5 flex items-center">
												<input name="pickup_methods" type="checkbox" id="pickup_methods_free_shipping:9" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="free_shipping:9">
											</div>
											<div class="ml-3 text-sm">
												<label for="pickup_methods_free_shipping:9" class="font-medium text-gray-700" style="vertical-align: unset;"><?php esc_html_e( 'Virginia: Free shipping', 'checkout-wc' ); ?></label>
											</div>
										</div>
										<div class="flex items-start mt-3">
											<div class="h-5 flex items-center">
												<input name="pickup_methods" type="checkbox" id="pickup_methods_local_pickup:10" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="local_pickup:10" checked="">
											</div>
											<div class="ml-3 text-sm">
												<label for="pickup_methods_local_pickup:10" class="font-medium text-gray-700" style="vertical-align: unset;"><?php esc_html_e( 'Virginia: Local pickup', 'checkout-wc' ); ?></label>
											</div>
										</div>
										<div class="flex items-start mt-3">
											<div class="h-5 flex items-center">
												<input name="pickup_methods" type="checkbox" id="pickup_methods_flat_rate:2" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="flat_rate:2">
											</div>
											<div class="ml-3 text-sm">
												<label for="pickup_methods_flat_rate:2" class="font-medium text-gray-700" style="vertical-align: unset;"><?php esc_html_e( 'California: Ground Shipping', 'checkout-wc' ); ?></label>
											</div>
										</div>
										<div class="flex items-start mt-3">
											<div class="h-5 flex items-center">
												<input name="pickup_methods" type="checkbox" id="pickup_methods_flat_rate:3" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="flat_rate:3">
											</div>
											<div class="ml-3 text-sm">
												<label for="pickup_methods_flat_rate:3" class="font-medium text-gray-700" style="vertical-align: unset;"><?php esc_html_e( 'California: Overnight Shipping', 'checkout-wc' ); ?></label>
											</div>
										</div>
										<div class="flex items-start mt-3">
											<div class="h-5 flex items-center">
												<input name="pickup_methods" type="checkbox" id="pickup_methods_flat_rate:7" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="flat_rate:7">
											</div>
											<div class="ml-3 text-sm">
												<label for="pickup_methods_flat_rate:7" class="font-medium text-gray-700" style="vertical-align: unset;"><?php esc_html_e( 'Locations not covered by your other zones: Ground Shipping', 'checkout-wc' ); ?></label>
											</div>
										</div>
										<div class="flex items-start mt-3">
											<div class="h-5 flex items-center">
												<input name="pickup_methods" type="checkbox" id="pickup_methods_flat_rate:8" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="flat_rate:8">
											</div>
											<div class="ml-3 text-sm">
												<label for="pickup_methods_flat_rate:8" class="font-medium text-gray-700" style="vertical-align: unset;"><?php esc_html_e( 'Locations not covered by your other zones: Overnight Shipping', 'checkout-wc' ); ?></label>
											</div>
										</div>
										<div class="flex items-start mt-3">
											<div class="h-5 flex items-center">
												<input name="pickup_methods" type="checkbox" id="pickup_methods_other" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="other">
											</div>
											<div class="ml-3 text-sm">
												<label for="pickup_methods_other" class="font-medium text-gray-700" style="vertical-align: unset;"><?php esc_html_e( 'Other', 'checkout-wc' ); ?></label>
											</div>
										</div>
									</div>
								</div>
								<div class="cfw-admin-field-container relative flex"><a href="https://demo.checkoutwc.com/wp-admin/edit.php?post_type=cfw_pickup_location" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"><?php esc_html_e( 'Edit Pickup Locations', 'checkout-wc' ); ?></a></div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<button class="cfw_admin_page_submit hidden" type="submit"><?php esc_html_e( 'Submit', 'checkout-wc' ); ?></button>
		</form>
		<?php
		$this->premium_lock_html( 'plus' );
	}

	public function get_shipping_methods(): array {
		// Get all shipping methods
		$data_store = \WC_Data_Store::load( 'shipping-zone' );
		$raw_zones  = $data_store->get_zones();
		$zones      = array();
		$methods    = array();

		foreach ( $raw_zones as $raw_zone ) {
			$zones[] = new \WC_Shipping_Zone( $raw_zone );
		}

		$zones[] = new \WC_Shipping_Zone( 0 ); // ADD ZONE "0" MANUALLY

		foreach ( $zones as $zone ) {
			$zone_shipping_methods = $zone->get_shipping_methods();
			foreach ( $zone_shipping_methods as $method ) {
				$methods[ $method->get_rate_id() ] = $zone->get_zone_name() . ': ' . $method->get_title();
			}
		}

		$methods['other'] = __( 'Other', 'checkout-wc' );

		return $methods;
	}

	public function maybe_set_script_data() {
		if ( ! $this->is_current_page() ) {
			return;
		}

		$shipping_methods = $this->get_shipping_methods();
		$pickup_methods   = (array) SettingsManager::instance()->get_setting( 'pickup_methods' );

		// Only include pickup methods that are valid shipping methods
		$pickup_methods = array_intersect_key( array_flip( $pickup_methods ), $shipping_methods );
		$pickup_methods = array_flip( $pickup_methods );

		$this->set_script_data(
			array(
				'settings'             => array(
					'pickup_methods'                     => $pickup_methods,
					'enable_pickup'                      => SettingsManager::instance()->get_setting( 'enable_pickup' ) === 'yes',
					'enable_pickup_ship_option'          => SettingsManager::instance()->get_setting( 'enable_pickup_ship_option' ) === 'yes',
					'pickup_ship_option_label'           => SettingsManager::instance()->get_setting( 'pickup_ship_option_label' ),
					'pickup_option_label'                => SettingsManager::instance()->get_setting( 'pickup_option_label' ),
					'pickup_shipping_method_other_label' => SettingsManager::instance()->get_setting( 'pickup_shipping_method_other_label' ),
					'enable_pickup_shipping_method_other_regex' => SettingsManager::instance()->get_setting( 'enable_pickup_shipping_method_other_regex' ) === 'yes',
					'enable_pickup_method_step'          => SettingsManager::instance()->get_setting( 'enable_pickup_method_step' ) === 'yes',
					'hide_pickup_methods'                => SettingsManager::instance()->get_setting( 'hide_pickup_methods' ) === 'yes',
				),
				'woocommerce_settings' => array(
					'shipping_methods' => $shipping_methods,
				),
				'params'               => array(
					'pickup_locations_edit_screen_url' => admin_url( 'edit.php?post_type=cfw_pickup_location' ),
				),
				'plan'                 => $this->get_plan_data(),
			)
		);
	}
}





