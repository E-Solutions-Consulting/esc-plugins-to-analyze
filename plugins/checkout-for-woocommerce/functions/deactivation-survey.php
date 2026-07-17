<?php
global $pagenow;

use Objectiv\Plugins\Checkout\Admin\DeactivationSurvey;

if ( empty( $pagenow ) || 'plugins.php' !== $pagenow ) {
	return false;
}

$form_fields = cfw_apply_filters( 'cfw_deactivation_form_fields', [] );
?>
<?php if ( ! empty( $form_fields ) ) : ?>
<div id="cfw-deactivation-survey" style="display:none">
	<form id="cfw-deactivation-survey-form">
		<div class="border-b border-gray-200 bg-white py-4">
			<div class="flex flex-wrap items-center justify-between sm:flex-nowrap px-6">
				<div class="mt-2">
					<h3 class="text-base font-semibold leading-6 text-gray-900">
						<?php
						_e( 'CheckoutWC Feedback', 'checkout-wc' );

						?>
					</h3>
				</div>
				<div class="ml-4 mt-2 flex-shrink-0">
					<a href="#" id="cfw_deactivation_survey_close_button" class="inline-flex items-center justify-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-2 rounded focus:outline-none focus:ring-2 focus:ring-gray-400">
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
							<path fill-rule="evenodd" d="M10 9.29289L15.3536 4L16.7071 5.29289L11.4142 10L16.7071 14.7071L15.3536 16L10 10.7071L4.64645 16L3.29289 14.7071L8.58579 10L3.29289 5.29289L4.64645 4L10 9.29289Z" clip-rule="evenodd" />
						</svg>
					</a>
				</div>
			</div>
		</div>
		<div class="px-6">
			<h3 class="text-base mt-4 font-semibold leading-6 text-gray-900">
				<?php esc_html_e( 'May we have a little info about why you are deactivating?', 'checkout-wc' ); ?>
			</h3>
			<?php foreach ( $form_fields as $key => $field_attr ) : ?>
				<?php
				DeactivationSurvey::render_field_html( $field_attr, 'deactivating' );

				?>
			<?php endforeach; ?>
		</div>
		<div class="px-6 py-6 bg-gray-100 mt-6 flex items-center justify-end gap-x-6">
			<div>
				<input type="submit" id="cfw_deactivate_submit" class="hidden cursor-pointer rounded-md bg-blue-600 hover:bg-blue-500 px-3 py-2 text-sm font-semibold text-white shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500" value="Send and Deactivate">
			</div>
			<div>
				<a href="#" id="cfw_skip_deactivate" class="rounded-md bg-white px-3 py-2.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"><?php esc_html_e( 'Skip and Deactivate Now', 'checkout-wc' ); ?></a>
			</div>
		</div>
	</form>
</div>
<?php endif; ?>
