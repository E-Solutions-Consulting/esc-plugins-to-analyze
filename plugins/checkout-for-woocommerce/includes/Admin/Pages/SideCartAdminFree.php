<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * Side Cart Admin Page
 *
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class SideCartAdminFree extends PageAbstract {
	public function __construct() {
		parent::__construct( __( 'Side Cart', 'checkout-wc' ) . ' <span class="cfw-badge cfw-premium-badge"></span>', 'cfw_manage_side_cart', 'side-cart' );
	}

	public function output() {
		?>
		<div class="space-y-6 transition-all" style="filter: none;">
			<div>
				<div class="md:grid md:grid-cols-3 md:gap-6">
					<div class="md:col-span-1">
						<div class="px-4 sm:px-0">
							<h3 class="text-lg font-medium leading-6 text-gray-900"><?php esc_html_e( 'Side Cart', 'checkout-wc' ); ?></h3>
							<p class="mt-1 text-sm text-gray-600"><?php esc_html_e( 'Configure the Side Cart.', 'checkout-wc' ); ?></p>
						</div>
					</div>
					<div class="mt-5 md:mt-0 md:col-span-2" id="side-cart_content">
						<div></div>
						<div class="shadow sm:rounded-md">
							<div class="cfw-admin-section-component-content px-4 py-5 bg-white space-y-6 sm:p-6">
								<div class="flex items-center space-x-4"><button class="bg-lime-500 relative inline-flex h-7 w-14 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2" id="headlessui-switch-:r0:" role="switch" type="button" tabindex="0" aria-checked="true" data-headlessui-state="checked" aria-labelledby="headlessui-label-:r1:" aria-describedby="headlessui-description-:r2:"><span class="sr-only">Use setting</span><span aria-hidden="true" class="translate-x-[1.75rem] pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span></button><span class="flex flex-grow flex-col"><span class="text-sm font-medium leading-6 text-gray-900" id="headlessui-label-:r1:"><?php esc_html_e( 'Enable Side Cart', 'checkout-wc' ); ?></span><span class="text-sm text-gray-500" id="headlessui-description-:r2:"><?php esc_html_e( 'Replace your cart page with a beautiful side cart that slides in from the right when items are added to the cart.', 'checkout-wc' ); ?></span></span></div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div>
				<div class="md:grid md:grid-cols-3 md:gap-6">
					<div class="md:col-span-1">
						<div class="px-4 sm:px-0">
							<h3 class="text-lg font-medium leading-6 text-gray-900"><?php esc_html_e( 'Side Cart Icon', 'checkout-wc' ); ?></h3>
							<p class="mt-1 text-sm text-gray-600"><?php esc_html_e( 'Used by the Side Cart and Floating Side Cart Button.', 'checkout-wc' ); ?></p>
						</div>
					</div>
					<div class="mt-5 md:mt-0 md:col-span-2" id="side-cart-icon_content">
						<div></div>
						<div class="shadow sm:rounded-md">
							<div class="cfw-admin-section-component-content px-4 py-5 bg-white space-y-6 sm:p-6">
								<div class="flex space-x-4">
									<div class="grow space-y-4 cfw-admin-section-component-content">
										<div class="cfw-admin-field-container cfw-admin-field-horizontal-icon-radio-group ">
											<legend class="text-base font-medium text-gray-900"><?php esc_html_e( 'Icon', 'checkout-wc' ); ?></legend>
											<p class="text-sm leading-5 text-gray-500"><?php esc_html_e( 'Choose the Side Cart icon.', 'checkout-wc' ); ?></p>
											<div class="mt-4 space-y-4 sm:flex sm:items-center sm:space-y-0 sm:space-x-10">
												<div class="relative flex items-start">
													<div class="flex items-center h-8"><input name="side_cart_icon" type="radio" id="side_cart_icon_cart-outline.svg" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300" value="cart-outline.svg" checked=""></div>
													<div class="ml-3 text-sm flex items-center">
														<label for="side_cart_icon_cart-outline.svg" class="font-medium text-gray-700 ml-2" style="vertical-align: unset;">
															<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
																<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
															</svg>
														</label>
													</div>
												</div>
												<div class="relative flex items-start">
													<div class="flex items-center h-8"><input name="side_cart_icon" type="radio" id="side_cart_icon_cart-solid.svg" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300" value="cart-solid.svg"></div>
													<div class="ml-3 text-sm flex items-center">
														<label for="side_cart_icon_cart-solid.svg" class="font-medium text-gray-700 ml-2" style="vertical-align: unset;">
															<svg xmlns="http://www.w3.org/2000/svg" class="cfw-side-cart-icon-solid" viewBox="0 0 20 20" fill="currentColor">
																<path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"></path>
															</svg>
														</label>
													</div>
												</div>
												<div class="relative flex items-start">
													<div class="flex items-center h-8"><input name="side_cart_icon" type="radio" id="side_cart_icon_shopping-bag-outline.svg" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300" value="shopping-bag-outline.svg"></div>
													<div class="ml-3 text-sm flex items-center">
														<label for="side_cart_icon_shopping-bag-outline.svg" class="font-medium text-gray-700 ml-2" style="vertical-align: unset;">
															<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
																<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
															</svg>
														</label>
													</div>
												</div>
												<div class="relative flex items-start">
													<div class="flex items-center h-8"><input name="side_cart_icon" type="radio" id="side_cart_icon_shopping-bag-solid.svg" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300" value="shopping-bag-solid.svg"></div>
													<div class="ml-3 text-sm flex items-center">
														<label for="side_cart_icon_shopping-bag-solid.svg" class="font-medium text-gray-700 ml-2" style="vertical-align: unset;">
															<svg xmlns="http://www.w3.org/2000/svg" class="cfw-side-cart-icon-solid" viewBox="0 0 20 20" fill="currentColor">
																<path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v1H5a1 1 0 00-.994.89l-1 9A1 1 0 004 18h12a1 1 0 00.994-1.11l-1-9A1 1 0 0015 7h-1V6a4 4 0 00-4-4zm2 5V6a2 2 0 10-4 0v1h4zm-6 3a1 1 0 112 0 1 1 0 01-2 0zm7-1a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"></path>
															</svg>
														</label>
													</div>
												</div>
											</div>
										</div>
										<div class="cfw-admin-field-container cfw-admin-upload-control-parent ">
											<legend class="text-base font-medium text-gray-900"><?php esc_html_e( 'Custom Icon', 'checkout-wc' ); ?></legend>
											<p class="text-sm leading-5 text-gray-500"><?php esc_html_e( 'Upload a custom icon. Overrides the icon selection above. SVG REQUIRED.', 'checkout-wc' ); ?></p>
											<div class="cfw-admin-image-preview-wrapper mb-4 mt-4"></div>
											<div class="block"><button type="button" class="mt-2 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"><?php esc_html_e('Upload Image', 'checkout-wc' ); ?></button><button type="button" class="mt-2 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"><?php esc_html_e( 'Clear', 'checkout-wc' ); ?></button></div>
										</div>
										<div class="cfw-admin-field-container ">
											<label for="side_cart_icon_width" class="block text-sm font-medium text-gray-700"><?php esc_html_e( 'Width', 'checkout-wc' ); ?></label><input name="side_cart_icon_width" type="number" id="side_cart_icon_width" class="w-64 shadow-sm focus:ring-blue-500 focus:border-blue-500 block sm:text-sm border border-gray-300 rounded-md" value="34">
											<p class="mt-2 text-sm text-gray-500"><?php esc_html_e( 'The width of the icon in pixels. Default: 34', 'checkout-wc' ); ?></p>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div>
				<div class="md:grid md:grid-cols-3 md:gap-6">
					<div class="md:col-span-1">
						<div class="px-4 sm:px-0">
							<h3 class="text-lg font-medium leading-6 text-gray-900"><?php esc_html_e( 'Suggested Products', 'checkout-wc' ); ?></h3>
							<p class="mt-1 text-sm text-gray-600"><?php esc_html_e( 'Configure Suggested Products cross-sells.', 'checkout-wc' ); ?></p>
						</div>
					</div>
					<div class="mt-5 md:mt-0 md:col-span-2" id="suggested-products_content">
						<div></div>
						<div class="shadow sm:rounded-md">
							<div class="cfw-admin-section-component-content px-4 py-5 bg-white space-y-6 sm:p-6">
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5"><input name="enable_side_cart_suggested_products" type="checkbox" id="cfw_checkbox_enable_side_cart_suggested_products" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="false"></div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_enable_side_cart_suggested_products" class="font-medium text-gray-700" style="vertical-align: unset;"><?php esc_html_e( 'Enable Suggested Products', 'checkout-wc' ); ?></label>
										<p class="text-gray-500"><?php esc_html_e( 'Display cross sells / suggested products at the bottom of the side cart.', 'checkout-wc' ); ?></p>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div>
				<div class="md:grid md:grid-cols-3 md:gap-6">
					<div class="md:col-span-1">
						<div class="px-4 sm:px-0">
							<h3 class="text-lg font-medium leading-6 text-gray-900"><?php esc_html_e( 'Floating Side Cart Button', 'checkout-wc' ); ?></h3>
							<p class="mt-1 text-sm text-gray-600"><?php esc_html_e( 'Configure the Floating Side Cart Button', 'checkout-wc' ); ?></p>
						</div>
					</div>
					<div class="mt-5 md:mt-0 md:col-span-2" id="floating-side-cart-button_content">
						<div></div>
						<div class="shadow sm:rounded-md">
							<div class="cfw-admin-section-component-content px-4 py-5 bg-white space-y-6 sm:p-6">
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5"><input name="enable_floating_cart_button" type="checkbox" id="cfw_checkbox_enable_floating_cart_button" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="true" checked=""></div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_enable_floating_cart_button" class="font-medium text-gray-700" style="vertical-align: unset;">Enable Floating Cart Button</label>
										<p class="text-gray-500">Enable floating cart button on the bottom right of pages.</p>
									</div>
								</div>
								<div class="flex space-x-4 cfw-admin-section-component-content">
									<div class="cfw-admin-field-container ">
										<label for="floating_cart_button_right_position" class="block text-sm font-medium text-gray-700">Right Position</label><input name="floating_cart_button_right_position" type="number" id="floating_cart_button_right_position" class="w-64 shadow-sm focus:ring-blue-500 focus:border-blue-500 block sm:text-sm border border-gray-300 rounded-md" value="20">
										<p class="mt-2 text-sm text-gray-500">The position from the right side of the screen in pixels. Default: 20</p>
									</div>
									<div class="cfw-admin-field-container ">
										<label for="floating_cart_button_bottom_position" class="block text-sm font-medium text-gray-700">Bottom Position</label><input name="floating_cart_button_bottom_position" type="number" id="floating_cart_button_bottom_position" class="w-64 shadow-sm focus:ring-blue-500 focus:border-blue-500 block sm:text-sm border border-gray-300 rounded-md" value="20">
										<p class="mt-2 text-sm text-gray-500">The position from the bottom of the screen in pixels. Default: 20</p>
									</div>
								</div>
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5"><input name="hide_floating_cart_button_empty_cart" type="checkbox" id="cfw_checkbox_hide_floating_cart_button_empty_cart" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="false"></div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_hide_floating_cart_button_empty_cart" class="font-medium text-gray-700" style="vertical-align: unset;">Hide Button If Empty Cart</label>
										<p class="text-gray-500">Hide floating cart button if cart is empty.</p>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div>
				<div class="md:grid md:grid-cols-3 md:gap-6">
					<div class="md:col-span-1">
						<div class="px-4 sm:px-0">
							<h3 class="text-lg font-medium leading-6 text-gray-900">Free Shipping Progress Bar</h3>
							<p class="mt-1 text-sm text-gray-600">Configure the Free Shipping Progress Bar</p>
						</div>
					</div>
					<div class="mt-5 md:mt-0 md:col-span-2" id="free-shipping-progress-bar_content">
						<div></div>
						<div class="shadow sm:rounded-md">
							<div class="cfw-admin-section-component-content px-4 py-5 bg-white space-y-6 sm:p-6">
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5"><input name="enable_free_shipping_progress_bar" type="checkbox" id="cfw_checkbox_enable_free_shipping_progress_bar" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="true" checked=""></div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_enable_free_shipping_progress_bar" class="font-medium text-gray-700" style="vertical-align: unset;">Enable Free Shipping Progress Bar</label>
										<p class="text-gray-500">Enable Free Shipping progress bar to show customers how close they are to obtaining free shipping. Uses your shipping settings to determine limits. To override, specify amount below.</p>
									</div>
								</div>
								<div class="cfw-admin-field-container ">
									<label for="side_cart_free_shipping_threshold" class="block text-sm font-medium text-gray-700">Free Shipping Threshold</label><input name="side_cart_free_shipping_threshold" type="number" id="side_cart_free_shipping_threshold" class="w-64 shadow-sm focus:ring-blue-500 focus:border-blue-500 block sm:text-sm border border-gray-300 rounded-md" value="50">
									<p class="mt-2 text-sm text-gray-500">Cart subtotal required to qualify for free shipping. To use automatic detection based on shipping configuration, leave blank. Enter in store base currency.</p>
								</div>
								<div class="cfw-admin-field-container ">
									<label for="side_cart_amount_remaining_message" class="block text-sm font-medium text-gray-700">Amount Remaining Message</label><input name="side_cart_amount_remaining_message" type="text" id="side_cart_amount_remaining_message" placeholder="You're %s away from free shipping!" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 rounded-md" value="">
									<p class="mt-2 text-sm text-gray-500">The amount remaining to qualify for free shipping message. Leave blank for default. Default: You're %s away from free shipping!</p>
								</div>
								<div class="cfw-admin-field-container ">
									<label for="side_cart_free_shipping_message" class="block text-sm font-medium text-gray-700">Free Shipping Message</label><input name="side_cart_free_shipping_message" type="text" id="side_cart_free_shipping_message" placeholder="Congrats! You get free standard shipping." class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 rounded-md" value="">
									<p class="mt-2 text-sm text-gray-500">The free shipping message. Leave blank for default. Default: Congrats! You get free standard shipping.</p>
								</div>
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5"><input name="enable_free_shipping_progress_bar_at_checkout" type="checkbox" id="cfw_checkbox_enable_free_shipping_progress_bar_at_checkout" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="false"></div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_enable_free_shipping_progress_bar_at_checkout" class="font-medium text-gray-700" style="vertical-align: unset;">Enable Free Shipping Progress Bar At Checkout</label>
										<p class="text-gray-500">Enable Free Shipping Progress Bar on the checkout page cart summary.</p>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div>
				<div class="md:grid md:grid-cols-3 md:gap-6">
					<div class="md:col-span-1">
						<div class="px-4 sm:px-0">
							<h3 class="text-lg font-medium leading-6 text-gray-900">Options</h3>
							<p class="mt-1 text-sm text-gray-600">Control various Side Cart options.</p>
						</div>
					</div>
					<div class="mt-5 md:mt-0 md:col-span-2" id="options_content">
						<div></div>
						<div class="shadow sm:rounded-md">
							<div class="cfw-admin-section-component-content px-4 py-5 bg-white space-y-6 sm:p-6">
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5"><input name="shake_floating_cart_button" type="checkbox" id="cfw_checkbox_shake_floating_cart_button" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="false"></div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_shake_floating_cart_button" class="font-medium text-gray-700" style="vertical-align: unset;">Disable Cart Auto Open</label>
										<p class="text-gray-500">Instead of opening the side cart, gently shake the floating cart button (if visible) to indicate a successful add to cart event.</p>
									</div>
								</div>
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5"><input name="enable_ajax_add_to_cart" type="checkbox" id="cfw_checkbox_enable_ajax_add_to_cart" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="true" checked=""></div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_enable_ajax_add_to_cart" class="font-medium text-gray-700" style="vertical-align: unset;">Enable AJAX Add to Cart</label>
										<p class="text-gray-500">Use AJAX on archive and single product pages to add items to cart. By default, WooCommerce requires a full form submit with page reload. Enabling this option uses AJAX to add items to the cart.</p>
									</div>
								</div>
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5"><input name="enable_side_cart_payment_buttons" type="checkbox" id="cfw_checkbox_enable_side_cart_payment_buttons" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="false"></div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_enable_side_cart_payment_buttons" class="font-medium text-gray-700" style="vertical-align: unset;">Enable Express Payment Buttons</label>
										<p class="text-gray-500">Enable express payment buttons from gateways that support the WooCommerce Minicart.</p>
									</div>
								</div>
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5"><input name="enable_order_bumps_on_side_cart" type="checkbox" id="cfw_checkbox_enable_order_bumps_on_side_cart" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="true" checked=""></div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_enable_order_bumps_on_side_cart" class="font-medium text-gray-700" style="vertical-align: unset;">Enable Order Bumps</label>
										<p class="text-gray-500">Enable order bumps that are set to display below cart items to appear in side cart.</p>
									</div>
								</div>
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5"><input name="allow_side_cart_item_variation_changes" type="checkbox" id="cfw_checkbox_allow_side_cart_item_variation_changes" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="true" checked=""></div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_allow_side_cart_item_variation_changes" class="font-medium text-gray-700" style="vertical-align: unset;">Allow Variation Changes</label>
										<p class="text-gray-500">Displays an edit link under cart items that allows customers to change which variation is selected in the cart.</p>
									</div>
								</div>
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5"><input name="show_side_cart_item_discount" type="checkbox" id="cfw_checkbox_show_side_cart_item_discount" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="false"></div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_show_side_cart_item_discount" class="font-medium text-gray-700" style="vertical-align: unset;">Enable Sale Prices</label>
										<p class="text-gray-500">Enable sale price under on cart item labels in side cart. Example: <s>$10.00</s> $5.00</p>
									</div>
								</div>
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5"><input name="enable_promo_codes_on_side_cart" type="checkbox" id="cfw_checkbox_enable_promo_codes_on_side_cart" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="true" checked=""></div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_enable_promo_codes_on_side_cart" class="font-medium text-gray-700" style="vertical-align: unset;">Enable Coupons</label>
										<p class="text-gray-500">Enable customers to apply coupons from the side cart.</p>
									</div>
								</div>
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5"><input name="enable_side_cart_coupon_code_link" type="checkbox" id="cfw_checkbox_enable_side_cart_coupon_code_link" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value=""></div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_enable_side_cart_coupon_code_link" class="font-medium text-gray-700" style="vertical-align: unset;">Hide Coupon Code Field Behind Link</label>
										<p class="text-gray-500">Initially hide coupon field until "Have a coupon code?" link is clicked.</p>
									</div>
								</div>
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5"><input name="enable_side_cart_totals" type="checkbox" id="cfw_checkbox_enable_side_cart_totals" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="false"></div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_enable_side_cart_totals" class="font-medium text-gray-700" style="vertical-align: unset;">Show Shipping and Totals</label>
										<p class="text-gray-500">Enable customers to see shipping and order total in addition to subtotal.</p>
									</div>
								</div>
								<div class="cfw-admin-field-container relative flex items-start ">
									<div class="flex items-center h-5"><input name="enable_side_cart_continue_shopping_button" type="checkbox" id="cfw_checkbox_enable_side_cart_continue_shopping_button" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded disabled:bg-gray-100 disabled:border" value="false"></div>
									<div class="ml-3 text-sm">
										<label for="cfw_checkbox_enable_side_cart_continue_shopping_button" class="font-medium text-gray-700" style="vertical-align: unset;">Enable Continue Shopping Button</label>
										<p class="text-gray-500">Enable Continue Shopping Button at bottom of Side Cart. Disabled by default.</p>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<button class="cfw_admin_page_submit hidden" type="submit">Submit</button>
		</div>
		<?php
		$this->premium_lock_html( 'plus' );
	}
}
