<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Admin\Pages\Traits\TabbedAdminPageTrait;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class AbandonedCartRecoveryAdminFree extends PageAbstract {
	use TabbedAdminPageTrait;

	public function __construct() {
		parent::__construct( __( 'Cart Recovery', 'checkout-wc' ) . ' <span class="cfw-badge cfw-premium-badge"></span>', 'cfw_manage_acr', 'acr' );
	}

	public function output() {
		$this->report_tab();
	}

	public function report_tab() {
		?>
		<nav class="relative z-0 rounded-lg shadow flex divide-x divide-gray-200 mb-6" aria-label="Tabs"><a href="https://demo.checkoutwc.com/wp-admin/admin.php?page=cfw-settings-acr&amp;subpage=report" class="text-gray-500 first:rounded-l-lg last:rounded-r-lg hover:text-gray-700 group relative min-w-0 flex-1 overflow-hidden bg-white py-4 px-4 text-sm font-medium text-center hover:bg-gray-50 focus:z-10"><span><?php esc_html_e( 'Report', 'checkout-wc' ); ?></span><span aria-hidden="true" class="bg-transparent absolute inset-x-0 bottom-0 h-0.5"></span></a>
			<a
				href="https://demo.checkoutwc.com/wp-admin/edit.php?post_type=cfw_acr_emails" class="text-gray-500 first:rounded-l-lg last:rounded-r-lg hover:text-gray-700 group relative min-w-0 flex-1 overflow-hidden bg-white py-4 px-4 text-sm font-medium text-center hover:bg-gray-50 focus:z-10"><span><?php esc_html_e( 'Emails', 'checkout-wc' ); ?></span><span aria-hidden="true" class="bg-transparent absolute inset-x-0 bottom-0 h-0.5"></span></a><a href="https://demo.checkoutwc.com/wp-admin/admin.php?page=cfw-settings-acr&amp;subpage=settings" class="text-gray-900 first:rounded-l-lg last:rounded-r-lg group relative min-w-0 flex-1 overflow-hidden bg-white py-4 px-4 text-sm font-medium text-center hover:bg-gray-50 focus:z-10"><span><?php esc_html_e( 'Settings', 'checkout-wc' ); ?></span><span aria-hidden="true" class="bg-blue-500 absolute inset-x-0 bottom-0 h-0.5"></span></a></nav>
		<div id="cfw-admin-pages-acr-settings-free">
			<div class="cfw-tw">
				<div class="mb-6">
					<label for="default-search" class="mb-2 text-sm font-medium text-gray-900 sr-only dark:text-white">Search</label>
					<div class="relative">
						<div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
							<svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
								<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"></path>
							</svg>
						</div>
						<input type="search" id="cfw_form_search" class="block w-full p-4 ps-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
								placeholder="<?php esc_html_e( 'Search Fields...', 'checkout-wc' ); ?>" value="">
					</div>
				</div>
				<form action="#" class="space-y-6 transition-all" style="filter: none;">
					<div>
						<div class="md:grid md:grid-cols-3 md:gap-6">
							<div class="md:col-span-1">
								<div class="px-4 sm:px-0">
									<h3 class="text-lg font-medium leading-6 text-gray-900"><?php esc_html_e( 'Abandoned Cart Recovery', 'checkout-wc' ); ?></h3>
									<p class="mt-1 text-sm text-gray-600"><?php esc_html_e( 'Configure Abandoned Cart Recovery settings.', 'checkout-wc' ); ?></p>
								</div>
							</div>
							<div class="mt-5 md:mt-0 md:col-span-2" id="abandoned-cart-recovery_content">
								<div>
									<div class="bg-white shadow sm:rounded-lg mb-6">
										<div class="px-4 py-5 sm:p-6">
											<h3 class="text-base font-semibold leading-6 text-gray-900">
												<?php esc_html_e( 'Error: WP Cron Configured Incorrectly!', 'checkout-wc' ); ?>				</h3>
											<div class="mt-2 sm:flex sm:items-start sm:justify-between">
												<div class="max-w-xl text-sm text-gray-500">
													<p class="mb-2">
														<?php esc_html_e( 'It looks like WP Cron is enabled which will cause issues with tracking carts and sending emails.', 'checkout-wc' ); ?> </p>
													<p class="mb-2">
														<?php esc_html_e( 'To properly configure WP Cron for ACR, please read our guide:', 'checkout-wc' ); ?>
														<br>
														<a class="text-blue-600 underline" target="_blank" href="https://www.checkoutwc.com/documentation/how-to-ensure-your-wordpress-cron-is-working-properly/"><?php esc_html_e( 'Properly configure WordPress cron for Abandoned Cart Recovery', 'checkout-wc' ); ?></a>
													</p>
												</div>
											</div>
										</div>
									</div>
									<div class="bg-white shadow sm:rounded-lg mb-6">
										<div class="px-4 py-5 sm:p-6">
											<h3 class="text-base font-semibold leading-6 text-gray-900">
												<?php esc_html_e( 'SendWP - Transactional Email', 'checkout-wc' ); ?>			</h3>
											<div class="mt-2 sm:flex sm:items-start sm:justify-between">
												<div class="max-w-xl text-sm text-gray-500">
													<p class="mb-2">
														<?php esc_html_e( 'SendWP makes getting emails delivered as simple as a few clicks. So you can relax know those important emails are being delivered on time.', 'checkout-wc' ); ?> </p>
													<p class="mb-2">
														<?php echo wp_kses_post( __( 'Try SendWP now and <strong>get your first month for just $1.</strong>', 'checkout-wc' ) ); ?> </p>
													<p>
														<a href="https://www.checkoutwc.com/documentation/abandoned-cart-recovery/" target="_blank" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
															<?php esc_html_e( 'Learn More', 'checkout-wc' ); ?>
														</a>
													</p>
													<p class="mt-2">
														<em>
															<?php esc_html_e( 'Note: SendWP is optional. You can use any transactional email service you prefer.', 'checkout-wc' ); ?>						</em>
													</p>
												</div>
												<div class="mt-5 sm:mt-0 sm:ml-6 sm:flex sm:flex-shrink-0 sm:items-center">
													<div class="text-center w-96">
														<div>
															<button type="button" id="cfw_sendwp_install_button" class="inline-flex items-center mb-2 px-6 py-3 border border-transparent text-lg shadow font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
																<?php esc_html_e( 'Connect to SendWP', 'checkout-wc' ); ?> </button>
														</div>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="shadow sm:rounded-md">
									<div class="cfw-admin-section-component-content px-4 py-5 bg-white space-y-6 sm:p-6">
										<div class="flex items-center space-x-4">
											<button class="bg-lime-500 relative inline-flex h-7 w-14 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
													id="headlessui-switch-:r0:" role="switch" type="button" tabindex="0" aria-checked="true" data-headlessui-state="checked" aria-labelledby="headlessui-label-:r1:" aria-describedby="headlessui-description-:r2:"><span class="sr-only">Use setting</span><span aria-hidden="true" class="translate-x-[1.75rem] pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span></button>
											<span
												class="flex flex-grow flex-col"><span class="text-sm font-medium leading-6 text-gray-900" id="headlessui-label-:r1:"><?php esc_html_e( 'Enable Abandoned Cart Tracking', 'checkout-wc' ); ?></span><span class="text-sm text-gray-500" id="headlessui-description-:r2:"><?php esc_html_e( 'Enable Abandoned Cart Recovery feature.', 'checkout-wc' ); ?></span></span>
										</div>
										<div class="cfw-admin-field-container ">
											<label for="acr_abandoned_time" class="block text-sm font-medium text-gray-700"><?php esc_html_e( 'Cart Is Abandoned After X Minutes', 'checkout-wc' ); ?></label>
											<input name="acr_abandoned_time" type="number" id="acr_abandoned_time" class="w-64 shadow-sm focus:ring-blue-500 focus:border-blue-500 block sm:text-sm border border-gray-300 rounded-md"
													value="15">
											<p class="mt-2 text-sm text-gray-500"><?php esc_html_e( 'The number of minutes after which a cart is considered abandoned.', 'checkout-wc' ); ?></p>
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
									<h3 class="text-lg font-medium leading-6 text-gray-900"><?php esc_html_e( 'Email Sending', 'checkout-wc' ); ?></h3>
									<p class="mt-1 text-sm text-gray-600"><?php esc_html_e( 'Configure email sending options.', 'checkout-wc' ); ?></p>
								</div>
							</div>
							<div class="mt-5 md:mt-0 md:col-span-2" id="email-sending_content">
								<div></div>
								<div class="shadow sm:rounded-md">
									<div class="cfw-admin-section-component-content px-4 py-5 bg-white space-y-6 sm:p-6">
										<div class="flex items-center space-x-4">
											<button class="bg-gray-400 relative inline-flex h-7 w-14 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
													id="headlessui-switch-:r3:" role="switch" type="button" tabindex="0" aria-checked="false" data-headlessui-state="" aria-labelledby="headlessui-label-:r4:" aria-describedby="headlessui-description-:r5:"><span class="sr-only">Use setting</span><span aria-hidden="true" class="translate-x-0 pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span></button>
											<span
												class="flex flex-grow flex-col"><span class="text-sm font-medium leading-6 text-gray-900" id="headlessui-label-:r4:"><?php esc_html_e( 'Disable Email Sending', 'checkout-wc' ); ?></span><span class="text-sm text-gray-500" id="headlessui-description-:r5:"><?php esc_html_e( 'Do not actually send any emails but allow carts to be tracked even if there are no emails configured.', 'checkout-wc' ); ?></span></span>
										</div>
										<div class="cfw-admin-field-container ">
											<label for="acr_from_name" class="block text-sm font-medium text-gray-700"><?php esc_html_e( 'From Name', 'checkout-wc' ); ?></label>
											<input name="acr_from_name" type="text" id="acr_from_name" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 rounded-md" value="CheckoutWC Demo Store">
											<p class="mt-2 text-sm text-gray-500"><?php esc_html_e( 'The name you wish Abandoned Cart Recovery emails to be sent from.', 'checkout-wc' ); ?></p>
										</div>
										<div class="cfw-admin-field-container ">
											<label for="acr_from_address" class="block text-sm font-medium text-gray-700"><?php esc_html_e( 'From Address', 'checkout-wc' ); ?></label>
											<input name="acr_from_address" type="text" id="acr_from_address" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 rounded-md" value="sample@email.com">
											<p class="mt-2 text-sm text-gray-500"><?php esc_html_e( 'The email address you wish Abandoned Cart Recovery emails to be sent from.', 'checkout-wc' ); ?></p>
										</div>
										<div class="cfw-admin-field-container ">
											<label for="acr_reply_to_address" class="block text-sm font-medium text-gray-700"><?php esc_html_e( 'Reply-To Address', 'checkout-wc' ); ?></label>
											<input name="acr_reply_to_address" type="text" id="acr_reply_to_address" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 rounded-md"
													value="sample@email.com">
											<p class="mt-2 text-sm text-gray-500"><?php esc_html_e( 'The email address you wish Abandoned Cart Recovery emails replies to be sent to.', 'checkout-wc' ); ?></p>
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
									<h3 class="text-lg font-medium leading-6 text-gray-900"><?php esc_html_e( 'Advanced Options', 'checkout-wc' ); ?></h3>
									<p class="mt-1 text-sm text-gray-600"><?php esc_html_e( 'Configure advanced options.', 'checkout-wc' ); ?></p>
								</div>
							</div>
							<div class="mt-5 md:mt-0 md:col-span-2" id="advanced-options_content">
								<div></div>
								<div class="shadow sm:rounded-md">
									<div class="cfw-admin-section-component-content px-4 py-5 bg-white space-y-6 sm:p-6">
										<div class="cfw-admin-field-container ">
											<input type="hidden" name="acr_recovered_order_statuses">
											<div></div>
											<legend class="text-base font-medium text-gray-900"><?php esc_html_e( 'Cart Recovered Order Statuses', 'checkout-wc' ); ?></legend>
											<p class="text-sm leading-5 text-gray-500"><?php esc_html_e( 'Choose which Order Statuses indicate a successful order.', 'checkout-wc' ); ?></p>
											<div>
												<div class="flex items-start mt-3">
													<div class="h-5 flex items-center">
														<input name="acr_recovered_order_statuses" type="checkbox" id="acr_recovered_order_statuses_wc-checkout-draft" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="wc-checkout-draft">
													</div>
													<div class="ml-3 text-sm">
														<label for="acr_recovered_order_statuses_wc-checkout-draft" class="font-medium text-gray-700" style="vertical-align: unset;">Draft</label>
													</div>
												</div>
												<div class="flex items-start mt-3">
													<div class="h-5 flex items-center">
														<input name="acr_recovered_order_statuses" type="checkbox" id="acr_recovered_order_statuses_wc-pending" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="wc-pending">
													</div>
													<div class="ml-3 text-sm">
														<label for="acr_recovered_order_statuses_wc-pending" class="font-medium text-gray-700" style="vertical-align: unset;">Pending payment</label>
													</div>
												</div>
												<div class="flex items-start mt-3">
													<div class="h-5 flex items-center">
														<input name="acr_recovered_order_statuses" type="checkbox" id="acr_recovered_order_statuses_wc-on-hold" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="wc-on-hold">
													</div>
													<div class="ml-3 text-sm">
														<label for="acr_recovered_order_statuses_wc-on-hold" class="font-medium text-gray-700" style="vertical-align: unset;">On hold</label>
													</div>
												</div>
												<div class="flex items-start mt-3">
													<div class="h-5 flex items-center">
														<input name="acr_recovered_order_statuses" type="checkbox" id="acr_recovered_order_statuses_wc-cancelled" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="wc-cancelled">
													</div>
													<div class="ml-3 text-sm">
														<label for="acr_recovered_order_statuses_wc-cancelled" class="font-medium text-gray-700" style="vertical-align: unset;">Cancelled</label>
													</div>
												</div>
												<div class="flex items-start mt-3">
													<div class="h-5 flex items-center">
														<input name="acr_recovered_order_statuses" type="checkbox" id="acr_recovered_order_statuses_wc-refunded" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="wc-refunded">
													</div>
													<div class="ml-3 text-sm">
														<label for="acr_recovered_order_statuses_wc-refunded" class="font-medium text-gray-700" style="vertical-align: unset;">Refunded</label>
													</div>
												</div>
												<div class="flex items-start mt-3">
													<div class="h-5 flex items-center">
														<input name="acr_recovered_order_statuses" type="checkbox" id="acr_recovered_order_statuses_wc-failed" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="wc-failed">
													</div>
													<div class="ml-3 text-sm">
														<label for="acr_recovered_order_statuses_wc-failed" class="font-medium text-gray-700" style="vertical-align: unset;">Failed</label>
													</div>
												</div>
												<div class="flex items-start mt-3">
													<div class="h-5 flex items-center">
														<input name="acr_recovered_order_statuses" type="checkbox" id="acr_recovered_order_statuses_wc-processing" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="wc-processing"
																checked="">
													</div>
													<div class="ml-3 text-sm">
														<label for="acr_recovered_order_statuses_wc-processing" class="font-medium text-gray-700" style="vertical-align: unset;">Confirmed</label>
													</div>
												</div>
												<div class="flex items-start mt-3">
													<div class="h-5 flex items-center">
														<input name="acr_recovered_order_statuses" type="checkbox" id="acr_recovered_order_statuses_wc-new-processing" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="wc-new-processing">
													</div>
													<div class="ml-3 text-sm">
														<label for="acr_recovered_order_statuses_wc-new-processing" class="font-medium text-gray-700" style="vertical-align: unset;">Processing</label>
													</div>
												</div>
												<div class="flex items-start mt-3">
													<div class="h-5 flex items-center">
														<input name="acr_recovered_order_statuses" type="checkbox" id="acr_recovered_order_statuses_wc-completed" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="wc-completed" checked="">
													</div>
													<div class="ml-3 text-sm">
														<label for="acr_recovered_order_statuses_wc-completed" class="font-medium text-gray-700" style="vertical-align: unset;">Shipped</label>
													</div>
												</div>
											</div>
										</div>
										<div class="cfw-admin-field-container ">
											<input type="hidden" name="acr_excluded_roles">
											<div></div>
											<legend class="text-base font-medium text-gray-900">Exclude From Abandoned Cart Recovery By Role</legend>
											<p class="text-sm leading-5 text-gray-500">Check any user role that should be excluded from abandoned cart emails.</p>
											<div>
												<div class="flex items-start mt-3">
													<div class="h-5 flex items-center">
														<input name="acr_excluded_roles" type="checkbox" id="acr_excluded_roles_administrator" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="administrator">
													</div>
													<div class="ml-3 text-sm">
														<label for="acr_excluded_roles_administrator" class="font-medium text-gray-700" style="vertical-align: unset;">Administrator</label>
													</div>
												</div>
												<div class="flex items-start mt-3">
													<div class="h-5 flex items-center">
														<input name="acr_excluded_roles" type="checkbox" id="acr_excluded_roles_editor" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="editor">
													</div>
													<div class="ml-3 text-sm">
														<label for="acr_excluded_roles_editor" class="font-medium text-gray-700" style="vertical-align: unset;">Editor</label>
													</div>
												</div>
												<div class="flex items-start mt-3">
													<div class="h-5 flex items-center">
														<input name="acr_excluded_roles" type="checkbox" id="acr_excluded_roles_author" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="author">
													</div>
													<div class="ml-3 text-sm">
														<label for="acr_excluded_roles_author" class="font-medium text-gray-700" style="vertical-align: unset;">Author</label>
													</div>
												</div>
												<div class="flex items-start mt-3">
													<div class="h-5 flex items-center">
														<input name="acr_excluded_roles" type="checkbox" id="acr_excluded_roles_contributor" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="contributor">
													</div>
													<div class="ml-3 text-sm">
														<label for="acr_excluded_roles_contributor" class="font-medium text-gray-700" style="vertical-align: unset;">Contributor</label>
													</div>
												</div>
												<div class="flex items-start mt-3">
													<div class="h-5 flex items-center">
														<input name="acr_excluded_roles" type="checkbox" id="acr_excluded_roles_subscriber" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="subscriber">
													</div>
													<div class="ml-3 text-sm">
														<label for="acr_excluded_roles_subscriber" class="font-medium text-gray-700" style="vertical-align: unset;">Subscriber</label>
													</div>
												</div>
												<div class="flex items-start mt-3">
													<div class="h-5 flex items-center">
														<input name="acr_excluded_roles" type="checkbox" id="acr_excluded_roles_customer" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="customer">
													</div>
													<div class="ml-3 text-sm">
														<label for="acr_excluded_roles_customer" class="font-medium text-gray-700" style="vertical-align: unset;">Customer</label>
													</div>
												</div>
												<div class="flex items-start mt-3">
													<div class="h-5 flex items-center">
														<input name="acr_excluded_roles" type="checkbox" id="acr_excluded_roles_shop_manager" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300 rounded" value="shop_manager">
													</div>
													<div class="ml-3 text-sm">
														<label for="acr_excluded_roles_shop_manager" class="font-medium text-gray-700" style="vertical-align: unset;">Shop manager</label>
													</div>
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
									<h3 class="text-lg font-medium leading-6 text-gray-900"><?php esc_html_e( 'Danger Zone', 'checkout-wc' ); ?></h3>
									<p class="mt-1 text-sm text-gray-600"><?php esc_html_e( 'Clear your cart data.', 'checkout-wc' ); ?></p>
								</div>
							</div>
							<div class="mt-5 md:mt-0 md:col-span-2" id="danger-zone_content">
								<div></div>
								<div class="shadow sm:rounded-md">
									<div class="cfw-admin-section-component-content px-4 py-5 bg-white space-y-6 sm:p-6">
										<div class="cfw-admin-field-container relative flex"><a href="https://demo.checkoutwc.com/wp-admin/admin.php?page=cfw-settings-acr&amp;subpage=settings&amp;clear-all-acr-carts=true&amp;nonce=3b781e3a21" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"><?php esc_html_e( 'Delete All Tracked Carts', 'checkout-wc' ); ?></a></div>
										<p><?php esc_html_e( 'Note: This resets ALL abandoned cart recovery statistics!', 'checkout-wc' ); ?></p>
									</div>
								</div>
							</div>
						</div>
					</div>
					<button class="cfw_admin_page_submit hidden" type="submit">Submit</button>
				</form>
			</div>
		</div>
		<?php
		$this->premium_lock_html( 'plus' );
	}
}
