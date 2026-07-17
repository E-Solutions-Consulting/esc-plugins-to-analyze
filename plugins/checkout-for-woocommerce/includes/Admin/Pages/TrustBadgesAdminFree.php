<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class TrustBadgesAdminFree extends PageAbstract {
	public function __construct() {
		parent::__construct( __( 'Trust Badges', 'checkout-wc' ) . ' <span class="cfw-badge cfw-premium-badge"></span>', 'cfw_manage_trust_badges', 'trust-badges' );
	}

	public function output() {
		?>
		<form action="#" class="space-y-6 transition-all" style="filter: none;">
			<div>
				<div class="md:grid md:grid-cols-3 md:gap-6">
					<div class="md:col-span-1">
						<div class="px-4 sm:px-0">
							<h3 class="text-lg font-medium leading-6 text-gray-900"><?php esc_html_e( 'Trust Badge Options', 'checkout-wc' ); ?></h3>
							<p class="mt-1 text-sm text-gray-600"><?php esc_html_e( 'Configure general Trust Badge options.', 'checkout-wc' ); ?></p>
						</div>
					</div>
					<div class="mt-5 md:mt-0 md:col-span-2" id="trust-badge-options_content">
						<div></div>
						<div class="shadow sm:rounded-md">
							<div class="cfw-admin-section-component-content px-4 py-5 bg-white space-y-6 sm:p-6">
								<div class="flex items-center space-x-4">
									<button class="bg-lime-500 relative inline-flex h-7 w-14 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2" id="headlessui-switch-:r0:" role="switch" type="button" tabindex="0" aria-checked="true" data-headlessui-state="checked" aria-labelledby="headlessui-label-:r1:" aria-describedby="headlessui-description-:r2:">
										<span class="sr-only">Use setting</span>
										<span aria-hidden="true" class="translate-x-[1.75rem] pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
									</button>
									<span class="flex flex-grow flex-col">
										<span class="text-sm font-medium leading-6 text-gray-900" id="headlessui-label-:r1:"><?php esc_html_e( 'Enable Trust Badges', 'checkout-wc' ); ?></span>
										<span class="text-sm text-gray-500" id="headlessui-description-:r2:"><?php esc_html_e( 'Enable trust badges on CheckoutWC templates. Uncheck to hide badges.', 'checkout-wc' ); ?></span>
									</span>
								</div>
								<div class="cfw-admin-field-container cfw-admin-field-radio-group ">
									<legend class="text-base font-medium text-gray-900"><?php esc_html_e( 'Trust Badge Output Location', 'checkout-wc' ); ?></legend>
									<p class="text-sm leading-5 text-gray-500"><?php esc_html_e( 'Where to display the trust badges on the checkout page.', 'checkout-wc' ); ?></p>
									<div class="space-y-5 mt-4">
										<div class="relative flex items-start">
											<div class="flex items-center h-5">
												<input name="trust_badge_position" type="radio" id="trust_badge_position_below_cart_summary" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300" value="below_cart_summary" checked="">
											</div>
											<div class="ml-3 text-sm">
												<label for="trust_badge_position_below_cart_summary" class="font-medium text-gray-700" style="vertical-align: unset;"><?php esc_html_e( 'Below the checkout cart summary', 'checkout-wc' ); ?></label>
												<p id="small-description" class="text-gray-500"><?php esc_html_e( 'Output in a single column below the checkout cart summary totals', 'checkout-wc' ); ?></p>
											</div>
										</div>
										<div class="relative flex items-start">
											<div class="flex items-center h-5">
												<input name="trust_badge_position" type="radio" id="trust_badge_position_below_checkout_form" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300" value="below_checkout_form">
											</div>
											<div class="ml-3 text-sm">
												<label for="trust_badge_position_below_checkout_form" class="font-medium text-gray-700" style="vertical-align: unset;"><?php esc_html_e( 'After the checkout form', 'checkout-wc' ); ?></label>
												<p id="small-description" class="text-gray-500"><?php esc_html_e( 'Output in a single row below the checkout form above the footer', 'checkout-wc' ); ?></p>
											</div>
										</div>
										<div class="relative flex items-start">
											<div class="flex items-center h-5">
												<input name="trust_badge_position" type="radio" id="trust_badge_position_in_footer" class="focus:ring-blue-800 h-4 w-4 text-blue-500 border-gray-300" value="in_footer">
											</div>
											<div class="ml-3 text-sm">
												<label for="trust_badge_position_in_footer" class="font-medium text-gray-700" style="vertical-align: unset;"><?php esc_html_e( 'Top of the footer', 'checkout-wc' ); ?></label>
												<p id="small-description" class="text-gray-500"><?php esc_html_e( 'Output in a single row inside the footer', 'checkout-wc' ); ?></p>
											</div>
										</div>
									</div>
								</div>
								<div class="cfw-admin-field-container ">
									<label for="trust_badges_title" class="block text-sm font-medium text-gray-700"><?php esc_html_e( 'Heading', 'checkout-wc' ); ?></label>
									<input name="trust_badges_title" type="text" id="trust_badges_title" placeholder="<?php esc_attr_e( 'Example: Why choose us?', 'checkout-wc' ); ?>" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 rounded-md" value="<?php esc_attr_e( "We've Got You Covered!", 'checkout-wc' ); ?>">
									<p class="mt-2 text-sm text-gray-500"><?php esc_html_e( 'Example: Why choose us?', 'checkout-wc' ); ?></p>
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
							<h3 class="text-lg font-medium leading-6 text-gray-900"><?php esc_html_e( 'Trust Badges', 'checkout-wc' ); ?></h3>
							<p class="mt-1 text-sm text-gray-600"><?php esc_html_e( 'Create trust badges to display on your checkout page.', 'checkout-wc' ); ?></p>
						</div>
					</div>
					<div class="mt-5 md:mt-0 md:col-span-2" id="trust-badges_content">
						<div></div>
						<div class="shadow sm:rounded-md">
							<div class="cfw-admin-section-component-content px-4 py-5 bg-white space-y-6 sm:p-6">
								<div>
									<div>
										<div>
											<div>
												<div data-rfd-droppable-id="droppable" data-rfd-droppable-context-id=":r3:">
													<div data-rfd-draggable-context-id=":r3:" data-rfd-draggable-id="badge-tb-0" tabindex="0" role="button" aria-describedby="rfd-hidden-text-:r3:-hidden-text-:r4:" data-rfd-drag-handle-draggable-id="badge-tb-0" data-rfd-drag-handle-context-id=":r3:" draggable="false">
														<div class="flex space-x-4 items-start group mb-6">
															<div class="shrink align-baseline" tabindex="0" role="button" aria-describedby="rfd-hidden-text-:r3:-hidden-text-:r4:" data-rfd-drag-handle-draggable-id="badge-tb-0" data-rfd-drag-handle-context-id=":r3:" draggable="false">
																<label class="block text-sm font-medium leading-6 text-gray-900 mb-2">&nbsp;</label>
																<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 transition-all stroke-gray-300 group-hover:stroke-gray-600">
																	<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"></path>
																</svg>
															</div>
															<div class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-6 max-w-md">
																<div class="col-span-full flex space-x-4 items-center group">
																	<label class="grow block text-sm font-medium leading-6 text-gray-900"><?php esc_html_e( 'Title', 'checkout-wc' ); ?> <input type="text" placeholder="<?php esc_attr_e( 'Title', 'checkout-wc' ); ?>" class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" value="<?php esc_attr_e( '30 Day Money Back Guarantee!', 'checkout-wc' ); ?>">
																	</label>
																	<label class="grow block text-sm font-medium leading-6 text-gray-900" style="display: none;"><?php esc_html_e( 'Subtitle', 'checkout-wc' ); ?> <input type="text" placeholder="<?php esc_attr_e( 'Subtitle', 'checkout-wc' ); ?>" class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" value="">
																	</label>
																</div>
																<div class="col-span-full flex space-x-4 items-start group">
																	<div class="grow">
																		<label class="block text-sm font-medium leading-6 text-gray-900"><?php esc_html_e( 'Template', 'checkout-wc' ); ?> <select class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
																				<option value="guarantee"><?php esc_html_e( 'Guarantee', 'checkout-wc' ); ?></option>
																				<option value="review"><?php esc_html_e( 'Review', 'checkout-wc' ); ?></option>
																			</select>
																		</label>
																	</div>
																	<div class="grow">
																		<label class="block text-sm font-medium leading-6 text-gray-900"><?php esc_html_e( 'Image', 'checkout-wc' ); ?> <div class="block">
																				<button type="button" class="mt-2 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"><?php esc_html_e( 'Choose Image', 'checkout-wc' ); ?></button>
																			</div>
																		</label>
																		<a href="#" class="block font-medium text-blue-600 underline dark:text-blue-500 hover:no-underline mt-1"><?php esc_html_e( 'Clear image', 'checkout-wc' ); ?></a>
																	</div>
																</div>
																<div class="col-span-full">
																	<label class="block text-sm font-medium leading-6 text-gray-900"><?php esc_html_e( 'Description', 'checkout-wc' ); ?></label>
																	<div>
																		<textarea class="block min-w-[414px] min-h-[100px] rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"><?php esc_html_e( "Every product we sell comes with a 30-day money-back guarantee. Have a problem? Let us know and we'll make it right!", 'checkout-wc' ); ?></textarea>
																		<div class="space-y-4 sm:flex sm:items-center sm:space-x-10 sm:space-y-0 mt-2">
																			<div class="flex items-center">
																				<input id="WYSIWYG" name="editor-mode-tb-0" type="radio" class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-600">
																				<label for="WYSIWYG" class="ml-3 block text-sm font-medium leading-6 text-gray-900"><?php esc_html_e( 'WYSIWYG', 'checkout-wc' ); ?></label>
																			</div>
																			<div class="flex items-center">
																				<input id="HTML" name="editor-mode-tb-0" type="radio" class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-600" checked="">
																				<label for="HTML" class="ml-3 block text-sm font-medium leading-6 text-gray-900"><?php esc_html_e( 'HTML', 'checkout-wc' ); ?></label>
																			</div>
																		</div>
																	</div>
																</div>
																<div class="col-span-full">
																	<label class="block text-sm font-medium leading-6 text-gray-900"><?php esc_html_e( 'Display Conditions', 'checkout-wc' ); ?></label>
																	<button type="button" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"><?php esc_html_e( 'Manage Display Conditions', 'checkout-wc' ); ?></button>
																</div>
															</div>
															<div class="grow h-full flex justify-center items-center">
																<div class="max-w-xl w-full shadow rounded-lg p-4">
																	<div class="flex items-center grow mb-6 max-w-lg">
																		<div class="mr-4 flex-shrink-0">
																			<img src="<?php echo trailingslashit( CFW_PATH_URL_BASE ); ?>assets/images/30day.png" class="w-full max-w-28 h-auto" alt="<?php esc_attr_e( '30 Day Money Back Guarantee!', 'checkout-wc' ); ?>">
																		</div>
																		<div>
																			<h3 class="text-base font-semibold mb-2 text-left text-[--cfw-tb-guarantee-title]"><?php esc_html_e( '30 Day Money Back Guarantee!', 'checkout-wc' ); ?></h3>
																			<p class="text-sm text-left text-[--cfw-tb-guarantee-content]"><?php esc_html_e( "Every product we sell comes with a 30-day money-back guarantee. Have a problem? Let us know and we'll make it right!", 'checkout-wc' ); ?></p>
																		</div>
																	</div>
																</div>
															</div>
															<div class="shrink">
																<label class="block text-sm font-medium leading-6 text-gray-900 mb-2">&nbsp;</label>
																<div>
																	<button class="transition-all bg-gray-300 group-hover:bg-red-600 hover:bg-red-700 text-white font-bold py-1 px-1 rounded-full inline-flex items-center">
																		<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
																			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6"></path>
																		</svg>
																	</button>
																</div>
															</div>
														</div>
													</div>
												</div>
											</div>
										</div>
										<div class="flex justify-center items-center">
											<button type="button" class="mt-2 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"><?php esc_html_e( 'Add Trust Badge', 'checkout-wc' ); ?></button>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<button class="cfw_admin_page_submit hidden" type="submit">Submit</button>
		</form>
		<?php
		$this->premium_lock_html();
	}
}
