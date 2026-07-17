<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Admin\TabNavigation;
use Objectiv\Plugins\Checkout\Admin\Pages\Traits\TabbedAdminPageTrait;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class OrderBumpsAdminFree extends PageAbstract {
	use TabbedAdminPageTrait;

	public function __construct() {
		parent::__construct( __( 'Order Bumps', 'checkout-wc' ) . ' <span class="cfw-badge cfw-premium-badge"></span>', 'cfw_manage_order_bumps', 'order_bumps' );
	}

	public function init() {
		parent::init();

		$this->set_tabbed_navigation( new TabNavigation( 'settings' ) );

		$this->get_tabbed_navigation()->add_tab( __( 'Settings', 'checkout-wc' ), add_query_arg( array( 'subpage' => 'settings' ), $this->get_url() ) );
		$this->get_tabbed_navigation()->add_tab(
			__( 'Manage Bumps', 'checkout-wc' ),
			add_query_arg(
				array(),
				admin_url( 'edit.php' )
			)
		);
	}

	public function get_url(): string {
		$page_slug = join( '-', array_filter( array( self::$parent_slug, 'order_bumps' ) ) );
		$url       = add_query_arg( 'page', $page_slug, admin_url( 'admin.php' ) );

		return esc_url( $url );
	}

	public function output() {
		if ( ! empty( $notice ) ) {
			echo wp_kses( $notice, cfw_get_allowed_html() );
		}

		if ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$current_tab_function = $this->get_tabbed_navigation()->get_current_tab() . '_tab';
		$callable             = array( $this, $current_tab_function );

		$this->get_tabbed_navigation()->display_tabs();

		call_user_func( $callable );
	}

	public function settings_tab() {
		?>
		<div>
			<div class="md:grid md:grid-cols-3 md:gap-6">
				<div class="md:col-span-1">
					<div class="px-4 sm:px-0">
						<h3 class="text-lg font-medium leading-6 text-gray-900"><?php esc_html_e( 'Order Bumps', 'checkout-wc' ); ?></h3>
						<p class="mt-1 text-sm text-gray-600"><?php esc_html_e( 'Configure Order Bump settings.', 'checkout-wc' ); ?></p>
					</div>
				</div>
				<div class="mt-5 md:mt-0 md:col-span-2" id="order-bumps_content">
					<div></div>
					<div class="shadow sm:rounded-md">
						<div class="cfw-admin-section-component-content px-4 py-5 bg-white space-y-6 sm:p-6">
							<div class="flex items-center space-x-4"><button class="bg-lime-500 relative inline-flex h-7 w-14 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2" id="headlessui-switch-:r0:" role="switch" type="button" tabindex="0" aria-checked="true" data-headlessui-state="checked" aria-labelledby="headlessui-label-:r1:" aria-describedby="headlessui-description-:r2:"><span class="sr-only">Use setting</span><span aria-hidden="true" class="translate-x-[1.75rem] pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span></button><span class="flex flex-grow flex-col"><span class="text-sm font-medium leading-6 text-gray-900" id="headlessui-label-:r1:"><?php esc_html_e( 'Enable Order Bumps', 'checkout-wc' ); ?></span><span class="text-sm text-gray-500" id="headlessui-description-:r2:"><?php esc_html_e( 'Allow Order Bumps to be displayed.', 'checkout-wc' ); ?></span></span></div>
							<div class="cfw-admin-field-container ">
								<label for="max_bumps" class="block text-sm font-medium text-gray-700"><?php esc_html_e( 'Maximum Order Bumps', 'checkout-wc' ); ?></label><input name="max_bumps" type="number" id="max_bumps" class="w-64 shadow-sm focus:ring-blue-500 focus:border-blue-500 block sm:text-sm border border-gray-300 rounded-md" value="-1">
								<p class="mt-2 text-sm text-gray-500"><?php esc_html_e( 'The maximum number of bumps that can be displayed per output location. Use -1 for unlimited.', 'checkout-wc' ); ?></p>
							</div>
							<div class="cfw-admin-field-container ">
								<label for="max_after_checkout_bumps" class="block text-sm font-medium text-gray-700"><?php esc_html_e( 'Maximum Place Order Bumps', 'checkout-wc' ); ?></label><input name="max_after_checkout_bumps" type="number" id="max_after_checkout_bumps" class="w-64 shadow-sm focus:ring-blue-500 focus:border-blue-500 block sm:text-sm border border-gray-300 rounded-md" value="1">
								<p class="mt-2 text-sm text-gray-500"><?php esc_html_e( 'The maximum number of modal bumps that can be displayed after submitting checkout. Use -1 for unlimited.', 'checkout-wc' ); ?></p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		$this->premium_lock_html( 'plus' );
	}
}
