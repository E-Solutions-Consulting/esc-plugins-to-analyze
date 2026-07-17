<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages\Premium;

use Objectiv\Plugins\Checkout\Admin\Pages\PageAbstract;
use Objectiv\Plugins\Checkout\Admin\TabNavigation;
use Objectiv\Plugins\Checkout\Admin\Pages\Traits\TabbedAdminPageTrait;
use Objectiv\Plugins\Checkout\Features\LocalPickup;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class LocalPickupAdmin extends PageAbstract {
	use TabbedAdminPageTrait;

	public function __construct() {
		parent::__construct( __( 'Local Pickup', 'checkout-wc' ), 'cfw_manage_local_pickup', 'local-pickup' );
	}

	public function init() {
		parent::init();

		$this->set_tabbed_navigation( new TabNavigation( 'settings' ) );

		$this->get_tabbed_navigation()->add_tab( __( 'Settings', 'checkout-wc' ), add_query_arg( [ 'subpage' => 'settings' ], $this->get_url() ), 'settings' );
		$this->get_tabbed_navigation()->add_tab(
			__( 'Manage Pickup Locations', 'checkout-wc' ),
			add_query_arg(
				[
					'post_type' => LocalPickup::get_post_type(),
				],
				admin_url( 'edit.php' )
			),
			'manage-pickup-locations'
		);

		add_action( 'all_admin_notices', [ $this, 'output_post_type_editor_header' ] );

		/**
		 * Highlights Local Pickup submenu item when
		 * on the New Pickup Location admin page
		 */
		add_filter( 'submenu_file', [ $this, 'maybe_highlight_submenu_item' ] );

		/**
		 * Highlight parent menu
		 */
		add_filter( 'parent_file', [ $this, 'menu_highlight' ] );

		/**
		 * Fix tab highlighting for pickup locations
		 */
		add_filter( 'cfw_selected_tab', [ $this, 'maybe_set_pickup_locations_tab' ] );
	}

	public function output() {
		$current_tab_function = $this->get_tabbed_navigation()->get_current_tab() . '_tab';
		$callable             = [ $this, $current_tab_function ];

		$this->get_tabbed_navigation()->display_tabs();

		call_user_func( $callable );
	}

	public function settings_tab() {
		?>
		<div id="cfw-admin-pages-local-pickup"></div>
		<?php
	}

	/**
	 * @param mixed $submenu_file The submenu file.
	 *
	 * @return mixed
	 */
	public function maybe_highlight_submenu_item( $submenu_file ) {
		global $post;

		$post_type = LocalPickup::get_post_type();

		if ( isset( $_GET['post_type'] ) && $_GET['post_type'] === $post_type ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $this->get_slug();
		} elseif ( $post && $post->post_type === $post_type ) {
			return $this->get_slug();
		}

		return $submenu_file;
	}

	public function menu_highlight( $parent_file ) {
		global $plugin_page, $post_type;

		if ( LocalPickup::get_post_type() === $post_type ) {
			$plugin_page = PageAbstract::$parent_slug; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		return $parent_file;
	}

	/**
	 * Set the pickup locations tab as active when on pickup locations pages
	 *
	 * @param string $selected_tab The currently selected tab.
	 * @return string
	 */
	public function maybe_set_pickup_locations_tab( $selected_tab ) {
		global $post;

		$post_type = LocalPickup::get_post_type();

		// Check if we're on any pickup location related page
		if ( isset( $_GET['post_type'] ) && $_GET['post_type'] === $post_type ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 'manage-pickup-locations';
		} elseif ( $post && $post->post_type === $post_type ) {
			return 'manage-pickup-locations';
		}

		return $selected_tab;
	}

	/**
	 * The admin page wrap
	 *
	 * @since 1.0.0
	 */
	public function output_post_type_editor_header() {
		global $post;

		if ( isset( $_GET['post_type'] ) && LocalPickup::get_post_type() !== $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		} elseif ( isset( $post ) && LocalPickup::get_post_type() !== $post->post_type ) {
			return;
		} elseif ( ! isset( $_GET['post_type'] ) && ! isset( $post ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		?>
		<div class="cfw-admin-notices-container">
			<div class="wp-header-end"></div>
			<div id="cfw-custom-admin-notices"></div>
		</div>
		<div class="cfw-tw">
			<div id="cfw_admin_page_header" class="absolute left-0 right-0 top-0 divide-y shadow z-50">
				<?php
				/**
				 * Fires before the admin page header
				 *
				 * @param LocalPickupAdmin $this The LocalPickupAdmin instance.
				 *
				 * @since 7.0.0
				 */
				do_action( 'cfw_before_admin_page_header', $this );
				?>
				<div class="min-h-[64px] bg-white flex items-center pl-8">
					<span>
						<?php echo file_get_contents( CFW_PATH . '/assets/images/cfw.svg' ); // phpcs:ignore ?>
					</span>
					<nav class="flex" aria-label="Breadcrumb">
						<ol role="list" class="flex items-center space-x-2">
							<li class="m-0">
								<div class="flex items-center">
									<span class="ml-2 text-sm font-medium text-gray-800">
										<?php _e( 'CheckoutWC', 'checkout-wc' ); ?>
									</span>
								</div>
							</li>
							<li class="m-0">
								<div class="flex items-center">
									<!-- Heroicon name: solid/chevron-right -->
									<svg class="flex-shrink-0 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg"
										viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
										<path fill-rule="evenodd"
												d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
												clip-rule="evenodd"/>
									</svg>
									<span class="ml-2 text-sm font-medium text-gray-500" aria-current="page">
										<?php echo wp_kses_post( $this->title ); ?>
									</span>
								</div>
							</li>
						</ol>
					</nav>
				</div>
				<?php
				/**
				 * Fires after the admin page header
				 *
				 * @param LocalPickupAdmin $this The AbandonedCartRecovery instance.
				 *
				 * @since 7.0.0
				 */
				do_action( 'cfw_after_admin_page_header', $this );
				?>
			</div>

			<div class="mt-10 mr-4">
				<?php $this->get_tabbed_navigation()->display_tabs(); ?>
			</div>
		</div>
		<?php
	}

	public function get_shipping_methods(): array {
		// Get all shipping methods
		$data_store = \WC_Data_Store::load( 'shipping-zone' );
		$raw_zones  = $data_store->get_zones();
		$zones      = [];
		$methods    = [];

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
			[
				'settings'             => [
					'pickup_methods'                     => $pickup_methods,
					'enable_pickup'                      => SettingsManager::instance()->get_setting( 'enable_pickup' ) === 'yes',
					'enable_pickup_ship_option'          => SettingsManager::instance()->get_setting( 'enable_pickup_ship_option' ) === 'yes',
					'pickup_ship_option_label'           => SettingsManager::instance()->get_setting( 'pickup_ship_option_label' ),
					'pickup_option_label'                => SettingsManager::instance()->get_setting( 'pickup_option_label' ),
					'pickup_shipping_method_other_label' => SettingsManager::instance()->get_setting( 'pickup_shipping_method_other_label' ),
					'enable_pickup_shipping_method_other_regex' => SettingsManager::instance()->get_setting( 'enable_pickup_shipping_method_other_regex' ) === 'yes',
					'enable_pickup_method_step'          => SettingsManager::instance()->get_setting( 'enable_pickup_method_step' ) === 'yes',
					'hide_pickup_methods'                => SettingsManager::instance()->get_setting( 'hide_pickup_methods' ) === 'yes',
				],
				'woocommerce_settings' => [
					'shipping_methods' => $shipping_methods,
				],
				'params'               => [
					'pickup_locations_edit_screen_url' => admin_url( 'edit.php?post_type=cfw_pickup_location' ),
				],
				'plan'                 => $this->get_plan_data(),
			]
		);
	}
}





