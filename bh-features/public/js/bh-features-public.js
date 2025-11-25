(function( $ ) {
	'use strict';
	var edit_address	=	false;
	var prefix_fieldname=	'';
	console.log('plugin bh-features 23112012');
	jQuery(document).ready(function ($) {
		let autocompleteBilling, autocompleteShipping;
	
		function initializeAutocomplete() {
			try {
				const billingInput = document.getElementById('billing_address_1');
				const shippingInput = document.getElementById('shipping_address_1');
	
				if (!billingInput || !shippingInput) throw new Error("No se encontraron los campos de dirección.");
	
				// Autocomplete para billing
				autocompleteBilling = new google.maps.places.Autocomplete(billingInput, {
					types: ['geocode'],
					componentRestrictions: { country: 'us' }
				});
	
				// Autocomplete para shipping
				autocompleteShipping = new google.maps.places.Autocomplete(shippingInput, {
					types: ['geocode'],
					componentRestrictions: { country: 'us' }
				});
	
				// Listeners para actualizar los campos
				autocompleteBilling.addListener('place_changed', () => fillInAddress('billing'));
				autocompleteShipping.addListener('place_changed', () => fillInAddress('shipping'));
			} catch (error) {
				console.error("Error inicializando Google Autocomplete:", error);
			}
		}
	
		function fillInAddress(type) {
			try {
				const autocomplete = type === 'billing' ? autocompleteBilling : autocompleteShipping;
				const place = autocomplete.getPlace();
				if (!place || !place.address_components) throw new Error("No se pudo obtener la dirección seleccionada.");
	
				// Variables para almacenar los valores
				let address1 = '';
				let address2 = '';
				let city = '';
				let state = '';
				let postcode = '';
				let country = '';
	
				// Mapear los componentes de la dirección
				place.address_components.forEach(component => {
					const types = component.types;
	
					if (types.includes('street_number')) {
						address1 = component.long_name + ' ' + address1;
					}
					if (types.includes('route')) {
						address1 += component.long_name;
					}
					if (types.includes('sublocality') || types.includes('neighborhood')) {
						address2 = component.long_name;
					}
					if (types.includes('locality')) {
						city = component.long_name;
					}
					if (types.includes('administrative_area_level_1')) {
						state = component.short_name;
					}
					if (types.includes('postal_code')) {
						postcode = component.long_name;
					}
					if (types.includes('country')) {
						country = component.short_name;
					}
				});
	
				// Actualizar los campos del formulario
				$(`#${type}_address_1`).val(address1);
				$(`#${type}_address_2`).val(address2);
				$(`#${type}_city`).val(city);
				$(`#${type}_state`).val(state);
				$(`#${type}_postcode`).val(postcode);
				$(`#${type}_country`).val(country).change();
	
				// Actualizar la dirección formateada en tiempo real
				updateFormattedAddress();
			} catch (error) {
				console.error(`Error rellenando la dirección (${type}):`, error);
			}
		}
		function copyBillingToShippingData(){
			$('#shipping_address_1').val($('#billing_address_1').val());
			$('#shipping_address_2').val($('#billing_address_2').val());
			$('#shipping_city').val($('#billing_city').val());
			$('#shipping_state').val($('#billing_state').val());
			$('#shipping_postcode').val($('#billing_postcode').val());
			$('#shipping_country').val($('#billing_country').val()).change();
		}
		function updateFormattedAddress() {
			try {
				let firstName = $('#billing_first_name').val() || '';
				let lastName = $('#billing_last_name').val() || '';
				let company = $('#billing_company').val() || '';
				let billingAddress1 = $('#billing_address_1').val() || '';
				let billingAddress2 = $('#billing_address_2').val() || '';
				let billingCity = $('#billing_city').val() || '';
				let billingState = $('#billing_state').val() || '';
				let billingPostcode = $('#billing_postcode').val() || '';
				let billingCountry = $('#billing_country option:selected').text() || '';
	
				let shippingAddress1 = $('#shipping_address_1').val() || '';
				let shippingAddress2 = $('#shipping_address_2').val() || '';
				let shippingCity = $('#shipping_city').val() || '';
				let shippingState = $('#shipping_state').val() || '';
				let shippingPostcode = $('#shipping_postcode').val() || '';
				let shippingCountry = $('#shipping_country option:selected').text() || '';
	
				const formattedName = (firstName ? firstName.toUpperCase() + "'S " : ""); // Convertir a mayúsculas y concatenar 'S
        		$('#custom_client_name').text(formattedName);
				let formattedBillingAddress = `
					${billingAddress1}<br>
					${billingAddress2 ? billingAddress2 + '<br>' : ''}
					${billingCity}, ${billingState} ${billingPostcode}<br>
					${billingCountry}
				`;

				let formattedShippingAddress = `
					${shippingAddress1}<br>
					${shippingAddress2 ? shippingAddress2 + '<br>' : ''}
					${shippingCity}, ${shippingState} ${shippingPostcode}<br>
					${shippingCountry}
				`;
				$('#formatted-billing-address').html(formattedBillingAddress.trim());
				$('#formatted-shipping-address').html(formattedShippingAddress.trim());
			} catch (error) {
				console.error("Error actualizando la dirección formateada:", error);
			}
		}
		function updateFields(){
			if(!$('#ship-to-different-address-checkbox').is(':checked')){
				copyBillingToShippingData();
				updateFormattedAddress();
			}
		}
		$('#ship-to-different-address-checkbox').change(function () {
			if (this.checked) return;

			copyBillingToShippingData();
			updateFormattedAddress();
		});
		$('body').on('change input', '#billing_first_name, #billing_last_name, #billing_company, #billing_address_1, #billing_address_2, #billing_city, #billing_state, #billing_postcode, #billing_country, #shipping_first_name, #shipping_last_name, #shipping_company, #shipping_address_1, #shipping_address_2, #shipping_city, #shipping_state, #shipping_postcode, #shipping_country', function () {
			try {
				updateFormattedAddress();
			} catch (error) {
				console.error("Error actualizando en tiempo real:", error);
			}
		});

		/*
		try {
			initializeAutocomplete();
		} catch (error) {
			console.error("Error al inicializar todo el proceso:", error);
		}
		*/

		try {
			updateFormattedAddress();
		} catch (error) {
			console.error("Error al inicializar la dirección formateada:", error);
		}
		 $(document).on('click', '.edit-address-button', function (e) {
			e.preventDefault();
			edit_address 	=	true;
			prefix_fieldname=	jQuery(this).attr('id');
			$('#argmc-prev').trigger('click');
		});
		$('body').on('argmcAfterStepChange', function(event, prevStep, currentStep) {
			//console.log('argmcAfterStepChange', 'prevStep-> ' + prevStep, 'currentStep-> ' + currentStep, 'edit_address-> ' + edit_address, 'prefix_fieldname-> ' + prefix_fieldname);
			if(currentStep=='billing-shipping-step' && edit_address){
				setTimeout(function () {
					if(!$('#ship-to-different-address-checkbox').is(':checked'))
						prefix_fieldname	=	'billing_';

					const $input = $('#' + prefix_fieldname + 'address_1');					
					if ($input.length) {
						$('html, body').animate({
							scrollTop: $input.offset().top - 100
						}, 500, function () {
							$input.focus();
						});
					}
					edit_address 		=	false;
					prefix_fieldname	=	'';
				}, 500);
			} 
		});
		jQuery('body').on('argmcBeforeStepChange', function(event, currentStep, nextStep) {
			//console.log('argmcBeforeStepChange', 'currentStep-> ' + currentStep, 'nextStep-> ' + nextStep);
			updateFields();
		});
	});
})( jQuery );
