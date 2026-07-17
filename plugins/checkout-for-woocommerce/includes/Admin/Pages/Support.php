<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class Support extends PageAbstract {
	public function __construct() {
		parent::__construct( __( 'Support', 'checkout-wc' ), 'manage_woocommerce', 'support' );
	}

	public function output() {
		?>
		<div class="max-w-3xl pb-8">
			<div>
				<p class="text-5xl font-bold text-gray-900">
					<?php _e( 'Awesome support is in our DNA.', 'checkout-wc' ); ?>
				</p>
				<p class="max-w-xl mt-5 text-2xl text-gray-500">
					<?php _e( 'Our Knowledge Base is packed with tips, tricks, and common troubleshooting steps.', 'checkout-wc' ); ?>
				</p>
				<p class="mt-6">
					<a href="https://www.checkoutwc.com/documentation/" target="_blank" class="inline-flex items-center px-6 py-3 border border-transparent text-lg shadow font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
						<?php _e( 'Read Our Documentation', 'checkout-wc' ); ?>
					</a>
				</p>
			</div>
		</div>
		<div class="hidden sm:block" aria-hidden="true">
			<div class="py-8">
				<div class="border-t border-gray-300"></div>
			</div>
		</div>

		<p class="text-2xl text-gray-900">
			<?php _e( 'Some Popular Knowledge Base Articles', 'checkout-wc' ); ?>
		</p>

		<?php if ( defined( 'CFW_PREMIUM_PLAN_IDS' ) ) : ?>
			<ul class="mt-4 text-base">
				<li><a class="text-blue-600 underline" target="_blank" href="https://www.checkoutwc.com/documentation/getting-started/"><?php esc_html_e( 'Getting Started', 'checkout-wc' ); ?></a></li>
				<li><a class="text-blue-600 underline" target="_blank" href="https://www.checkoutwc.com/documentation/troubleshooting/"><?php esc_html_e( 'Troubleshooting', 'checkout-wc' ); ?></a></li>
				<li><a class="text-blue-600 underline" target="_blank" href="https://www.checkoutwc.com/documentation/upgrading-your-license/"><?php esc_html_e( 'Upgrading Your License', 'checkout-wc' ); ?></a></li>
				<li><a class="text-blue-600 underline" target="_blank" href="https://www.checkoutwc.com/documentation/how-to-enable-billing-and-shipping-phone-fields/"><?php esc_html_e( 'How To Enable Billing and Shipping Phone Fields', 'checkout-wc' ); ?></a></li>
				<li><a class="text-blue-600 underline" target="_blank" href="https://www.checkoutwc.com/documentation/how-to-enable-cart-editing/"><?php esc_html_e( 'How To Enable Cart Editing', 'checkout-wc' ); ?></a></li>
				<li><a class="text-blue-600 underline" target="_blank" href="https://www.checkoutwc.com/documentation/how-to-get-and-configure-your-google-api-key/"><?php esc_html_e( 'How To Register and Configure Your Google API Key', 'checkout-wc' ); ?></a></li>
				<li><a class="text-blue-600 underline" target="_blank" href="https://www.checkoutwc.com/documentation/how-to-add-a-custom-field/"><?php esc_html_e( 'How To Add a Custom Field to Checkout for WooCommerce', 'checkout-wc' ); ?></a></li>
				<li><a class="text-blue-600 underline" target="_blank" href="https://www.checkoutwc.com/documentation/how-to-enable-the-woocommerce-notes-field/"><?php esc_html_e( 'How to Enable The WooCommerce Notes Field', 'checkout-wc' ); ?></a></li>
			</ul>
		<?php else : ?>
			<ul class="mt-4 text-base">
				<li><a class="text-blue-600 underline" target="_blank" href="https://www.checkoutwc.com/documentation/getting-started/"><?php esc_html_e( 'Getting Started', 'checkout-wc' ); ?></a></li>
				<li><a class="text-blue-600 underline" target="_blank" href="https://www.checkoutwc.com/documentation/troubleshooting/"><?php esc_html_e( 'Troubleshooting', 'checkout-wc' ); ?></a></li>
				<li><a class="text-blue-600 underline" target="_blank" href="https://www.checkoutwc.com/pricing"><?php esc_html_e( 'Buy a License', 'checkout-wc' ); ?></a></li>
			</ul>
		<?php endif; ?>

		<p class="text-2xl text-gray-900 mt-6">
			<?php _e( 'Still Need Help?', 'checkout-wc' ); ?>
		</p>

		<input type="submit" id="checkoutwc-support-button" class="mt-4 cursor-pointer inline-flex items-center px-6 py-3 border border-transparent text-base shadow font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" value="<?php esc_attr_e( 'Contact Support', 'checkout-wc' ); ?>">
		<script type="text/javascript">!function(e,t,n){function a(){var e=t.getElementsByTagName("script")[0],n=t.createElement("script");n.type="text/javascript",n.async=!0,n.src="https://beacon-v2.helpscout.net",e.parentNode.insertBefore(n,e)}if(e.Beacon=n=function(t,n,a){e.Beacon.readyQueue.push({method:t,options:n,data:a})},n.readyQueue=[],"complete"===t.readyState)return a();e.attachEvent?e.attachEvent("onload",a):e.addEventListener("load",a,!1)}(window,document,window.Beacon||function(){});</script>

		<?php if ( defined( 'CFW_PREMIUM_PLAN_IDS' ) ) : ?>
			<script type="text/javascript">window.Beacon('init', '355a5a54-eb9d-4b64-ac5f-39c95644ad36')</script>
		<?php else : ?>
			<script type="text/javascript">window.Beacon('init', '5217742c-5849-4434-a2fe-fa85a2397793')</script>
		<?php endif; ?>

		<script>
			jQuery("#checkoutwc-support-button").on( 'click', function() {
				Beacon("open");
			});
		</script>
		<?php
	}
}
