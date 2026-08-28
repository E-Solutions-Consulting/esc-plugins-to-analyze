(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */
	jQuery(document).ready(function($) {
		var licensedStates = allowedStates;
		
		function checkStateWarning(stateField) {
			var selectedState = stateField.val();
			var warningMessage = $("#state-warning");
	
			if ($.inArray(selectedState, licensedStates) === -1) {
				if (warningMessage.length === 0) {
					
					// stateField.closest('.form-field').after('<p class="form-field-wide" id="state-warning" style="color: #eb3737;display: inline-block;margin: 10px 0 -5px;background-color: #fff7f8;position:relative;padding: 2px 5px !important;box-sizing: border-box;"><span>Warning: This state is not licensed for shipping.</span></p>');
					stateField.closest('.form-field').after('<div class="form-field-wide warning-message" id="state-warning" =""><strong>Warning:</strong> This state (' + selectedState + ') is not licensed for shipping.</div>');
					
				}
			} else {
				warningMessage.remove();
			}
		}
	
		$('#_billing_state').on('change', function() {
			checkStateWarning($(this));
		});
	
		checkStateWarning($('#_billing_state'));

		 // Filtrar estados no permitidos para shipping
		const shippingSelect = $('#_shipping_state');
		if (shippingSelect.length) {
			const currentSelected = shippingSelect.val();
		 	shippingSelect.find('option').each(function() {
				const val = $(this).val();
				if (val && !licensedStates.includes(val)) {
					if(currentSelected!==val)
					 	$(this).remove();
					else
						jQuery('#_shipping_state').next().find('.select2-selection').css('border', '1px solid red');
				}
			});
		}
		jQuery("a.edit_address").on("click", function(){
			jQuery('.warning-message.text').hide();
		});

	});
})( jQuery );
