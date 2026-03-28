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
	var edited	=	false;
	jQuery(document).ready(function($) {
		$("#pause-months-select").on("change", function() {
			info($(this).val());
		});
		$("#pause-subscription-checkbox").on("change", function() {
			if ($(this).is(":checked")) {
				$("#pause-box").slideDown();
			} else {
				$("#pause-box").slideUp();
				revertDates();
			}
		});

		$("#confirm-pause").on("click", function(e) {
			e.preventDefault();
			$("#pause-box").fadeOut();
			applyPause($("#pause-months-select").val());
		});

		$("#cancel-pause").on("click", function(e) {
			e.preventDefault();
			$("#pause-box").fadeOut();
			$("#pause-subscription-checkbox").prop("checked", false);					
			revertDates();
		});

		function applyPause(months) {
			
			if(typeof originalNextPayment!=="undefined" ){
				const nextPaymentDate	=	new Date(originalNextPayment);

				if (!isNaN(nextPaymentDate)) {
					nextPaymentDate.setMonth(nextPaymentDate.getMonth() + parseInt(months));			
					$("#next_payment")
					.val(nextPaymentDate.toISOString().split("T")[0])
					.parent().addClass("edited");
					$("#next_payment").trigger( "change" )

					setTimeout(() => {
						$("#next_payment").parent().removeClass("edited")
					}, 5000);
					edited	=	true;
				}
			}

			if(typeof originalTrialEnd!=="undefined" ){
				const trialEndDate 		=	new Date(originalTrialEnd);
				if (!isNaN(trialEndDate)) {
					trialEndDate.setMonth(trialEndDate.getMonth() + parseInt(months));
					$("#trial_end")
					.val(trialEndDate.toISOString().split("T")[0])
					.parent().addClass("edited");
					$("#trial_end").trigger( "change" )

					setTimeout(() => {
						$("#trial_end").parent().removeClass("edited")
					}, 5000);
					edited	=	true;
				}
			}
			if(edited){
				$('#pause-info').slideDown();
			}
		}

		function revertDates() {
			if(!edited)
				return ;

			if(typeof originalTrialEnd!=="undefined" ){
				$("#trial_end")
					.val(originalTrialEnd)
					.parent().addClass("edited");

					setTimeout(() => {
						$("#trial_end").parent().removeClass("edited")
					}, 5000);
			}
			if(typeof originalNextPayment!=="undefined" ){
				$("#next_payment")
					.val(originalNextPayment)
					.parent().addClass("edited");

					setTimeout(() => {
						$("#next_payment").parent().removeClass("edited")
					}, 5000);
			}
			edited	=	false;
			$('#pause-info').slideUp();
		}
		function info(months) {
			let title='New Date';
			let info="";
			if(typeof originalNextPayment!=="undefined" ){
				const nextPaymentDate	=	new Date(originalNextPayment);
				if (!isNaN(nextPaymentDate)) {
					nextPaymentDate.setMonth(nextPaymentDate.getMonth() + parseInt(months));
					info	=	"Next Payment: " + nextPaymentDate.toISOString().split("T")[0];
				}
			}
			if(typeof originalTrialEnd!=="undefined" ){
				const trialEndDate 		=	new Date(originalTrialEnd);
				if (!isNaN(trialEndDate)) {
					trialEndDate.setMonth(trialEndDate.getMonth() + parseInt(months));
					if(info){
						info	+=	"<br>";
						title	+=	's';
					}
					info	+=	"Trial End: " + trialEndDate.toISOString().split("T")[0];
				}
			}
			if(info){
				info = title + '<hr>' + info;
				$("#original_dates").html(info);
			}
		}
		info(months_paused);		

		function restrictSubscriptionFields() {
	        const $periodField = $('#_billing_period');
	        if ($periodField.length) {
	            $periodField.find('option').not('[value="month"]').remove();
	            if ($periodField.val() !== 'month') {
	                $periodField.val('month').trigger('change');
	            }
	        }
	        const $intervalField = $('#_billing_interval');
	        if ($intervalField.length) {
	            $intervalField.find('option').not('[value="1"], [value="3"]').remove();
	            if (!['1', '3'].includes($intervalField.val())) {
	                $intervalField.val('1').trigger('change');
	            }
	        }
	    }
	    restrictSubscriptionFields();

	});

})( jQuery );
